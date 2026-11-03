"""Seed data awal aplikasi PHP dari folder STEP 1/4/5 (sekali jalan).

Sumber data lama (Excel per dokter + template PDF) dipindahkan ke database
aplikasi PHP supaya bisa langsung dipakai/diuji. Setelah aplikasi berjalan,
data dikelola lewat UI (termasuk "Ambil Data" dari SIMRS).

Pakai:
    python web/bin/seed_step_data.py
"""

import re
import sqlite3
from pathlib import Path

import openpyxl
import pymupdf

WEB = Path(__file__).resolve().parent.parent
WORKSPACE = WEB.parent
DB = WEB / "storage" / "app.db"

DIR_SURAT = WORKSPACE / "STEP 1 Dokumen Awal"
DIR_EXCEL = WORKSPACE / "STEP 4 Dokumen Dengan No Form"
DIR_TEMPLATE = WORKSPACE / "STEP 5 Mater Template Dokument"


def norm(teks: str) -> str:
    return re.sub(r"\s+", " ", (teks or "")).strip().upper()


def halaman(path: Path) -> int:
    try:
        doc = pymupdf.open(str(path))
        try:
            return doc.page_count
        finally:
            doc.close()
    except Exception:
        return 0


def kolom_header(baris):
    hdr = [norm(c) for c in (baris or [])]

    def cari(pred):
        for i, h in enumerate(hdr):
            if h and pred(i, h):
                return i
        return None

    i_no = cari(lambda i, h: h in ("NO_FORM", "NO FORM", "KODE FORM (FN)", "KODE FN", "FN")
                or "NO_FORM" in h or "NO FORM" in h or "(FN)" in h or h.endswith("FN"))
    i_nama = cari(lambda i, h: h in ("NAMA_FORM", "NAMA FORM", "NAMA FORMULIR")
                  or "NAMA_FORM" in h or "NAMA FORM" in h or "JUDUL" in h)
    if i_nama is None:
        return None
    i_kode = cari(lambda i, h: i not in (i_no, i_nama)
                  and (h in ("KODE", "KODE RSMPP", "KODE DOKUMEN") or "NOMOR DOKUMEN" in h))
    if i_kode is None:
        return None
    return i_kode, i_no, i_nama


def baca_dokumen(xlsx: Path) -> list[dict]:
    wb = openpyxl.load_workbook(str(xlsx), data_only=True)
    try:
        rows = list(wb.active.iter_rows(values_only=True))
    finally:
        wb.close()
    idx = None
    kolom = None
    for i, r in enumerate(rows):
        k = kolom_header(r)
        if k:
            idx, kolom = i, k
            break
    if kolom is None:
        return []
    i_kode, i_no, i_nama = kolom
    hasil = []
    for r in rows[idx + 1:]:
        if not r or i_kode >= len(r) or not r[i_kode]:
            continue
        no_form = str(r[i_no]).strip() if i_no is not None and r[i_no] else ""
        if not no_form:
            continue
        hasil.append({
            "kode": str(r[i_kode]).strip(),
            "no_form": no_form,
            "nama_form": str(r[i_nama]).strip() if r[i_nama] else "",
        })
    return hasil


def cari_excel(nama: str) -> Path | None:
    target = norm(nama)
    kandidat = [(norm(p.stem), p) for p in DIR_EXCEL.iterdir()
                if p.suffix.lower() in (".xlsx", ".xls") and not p.name.startswith("~$")
                and not norm(p.stem).startswith("DAFTAR")]
    for stem, p in kandidat:
        if stem == target:
            return p
    for stem, p in kandidat:
        if target and (target in stem or stem in target):
            return p
    return None


def main() -> None:
    con = sqlite3.connect(DB)
    cur = con.cursor()

    templates = {norm(p.stem): p for p in DIR_TEMPLATE.iterdir() if p.suffix.lower() == ".pdf"}
    surat_files = sorted(DIR_SURAT.rglob("*.pdf"))

    n_dokter = n_surat = n_dok = n_tpl = 0
    for path in surat_files:
        stem = path.stem.strip()
        m = re.match(r"^(\d+)\s+(.*)$", stem)
        nomor, nama = (m.group(1), m.group(2).strip()) if m else (stem, stem)

        cur.execute("SELECT id FROM dokter WHERE nomor = ?", (nomor,))
        row = cur.fetchone()
        if row:
            dokter_id = row[0]
            cur.execute("UPDATE dokter SET nama = ? WHERE id = ?", (nama, dokter_id))
        else:
            cur.execute("INSERT INTO dokter (nomor, nama) VALUES (?, ?)", (nomor, nama))
            dokter_id = cur.lastrowid
            n_dokter += 1

        cur.execute("SELECT id FROM surat WHERE dokter_id = ? AND filename = ?", (dokter_id, path.name))
        if not cur.fetchone():
            cur.execute(
                "INSERT INTO surat (dokter_id, filename, path, halaman, sha256) VALUES (?, ?, ?, ?, '')",
                (dokter_id, path.name, str(path), halaman(path)))
            n_surat += 1

        xlsx = cari_excel(nama)
        if not xlsx:
            continue
        for item in baca_dokumen(xlsx):
            cur.execute("SELECT id FROM form_master WHERE no_form = ?", (item["no_form"],))
            f = cur.fetchone()
            if not f:
                cur.execute("INSERT INTO form_master (no_form, nama_form) VALUES (?, ?)",
                            (item["no_form"], item["nama_form"]))
            cur.execute(
                """INSERT INTO dokumen (dokter_id, no_form, kode_rsmpp, nama_form, sumber)
                   VALUES (?, ?, ?, ?, 'seed')
                   ON CONFLICT(dokter_id, no_form) DO UPDATE SET
                     kode_rsmpp = excluded.kode_rsmpp,
                     nama_form = excluded.nama_form""",
                (dokter_id, item["no_form"], item["kode"], item["nama_form"]))
            n_dok += 1

    for kunci, path in templates.items():
        cur.execute("SELECT id, nama_form FROM form_master")
        cocok = None
        for form_id, nama_form in cur.fetchall():
            if norm(nama_form) == kunci:
                cocok = form_id
                break
        if not cocok:
            continue
        cur.execute("SELECT id FROM template_form WHERE form_id = ? AND aktif = 1", (cocok,))
        if cur.fetchone():
            continue
        cur.execute(
            "INSERT INTO template_form (form_id, versi, filename, path, halaman, sha256, aktif) VALUES (?, 1, ?, ?, ?, '', 1)",
            (cocok, path.name, str(path), halaman(path)))
        n_tpl += 1

    con.commit()
    con.close()
    print(f"dokter baru {n_dokter}, surat baru {n_surat}, dokumen diproses {n_dok}, template baru {n_tpl}")


if __name__ == "__main__":
    main()
