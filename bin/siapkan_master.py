"""Siapkan master PDF untuk aplikasi PHP (dijalankan sekali / saat master diganti).

Kenapa Python: FPDI/tFPDF tidak bisa menghapus teks yang sudah ada di PDF.
Placeholder `RSMPP26090009` dan `FN25010002 - ...` di Mater Lampiran perlu
benar-benar dihapus (bukan ditutup kotak putih) supaya lapisan teks hasilnya bersih.
Hasilnya disimpan ke storage/master/lampiran1_bersih.pdf, lalu aplikasi PHP
hanya menempelkan nilai baru di posisi yang tercatat.

Pakai (bawaan, memakai STEP 4 + STEP 6):
    python web/bin/siapkan_master.py

Pakai untuk PDF hasil unggahan (dipanggil aplikasi saat unggah LAMPIRAN 1):
    python web/bin/siapkan_master.py --sumber unggahan.pdf \
        --keluaran storage/master/lampiran1_<waktu>_bersih.pdf \
        --posisi storage/master/lampiran1_posisi.json --lewati-akhir --lewati-font
"""

import argparse
import json
import shutil
from pathlib import Path

import pymupdf

WEB = Path(__file__).resolve().parent.parent
WORKSPACE = WEB.parent
STORAGE = WEB / "storage"

SUMBER_LAMPIRAN = WORKSPACE / "STEP 4 Dokumen Dengan No Form" / "Mater Lampiran.pdf"
SUMBER_AKHIR = WORKSPACE / "STEP 6 Halaman Master Terakhir" / "Halaman Terakhir.pdf"

PLACEHOLDER_KODE = "RSMPP26090009"
PLACEHOLDER_NAMA_PREFIX = "FN25010002"
MAX_X = 540.0
MIN_SIZE = 7.5


def bersihkan(sumber: Path, keluaran: Path, posisi_keluar: Path) -> dict:
    """Hapus teks placeholder di halaman 1 lalu simpan PDF bersih + posisi tulisnya."""
    if not sumber.is_file():
        raise SystemExit(f"Berkas sumber tidak ditemukan: {sumber}")

    doc = pymupdf.open(str(sumber))
    try:
        if doc.page_count != 1:
            raise SystemExit(
                f"LAMPIRAN 1 harus 1 halaman (berkas ini {doc.page_count} halaman).")
        page = doc[0]

        slot = {}
        for blok in page.get_text("dict")["blocks"]:
            if blok["type"] != 0:
                continue
            for baris in blok["lines"]:
                for span in baris["spans"]:
                    teks = span["text"].strip()
                    if teks == PLACEHOLDER_KODE:
                        slot["kode"] = span
                    elif teks.startswith(PLACEHOLDER_NAMA_PREFIX):
                        slot["nama"] = span

        if set(slot) != {"kode", "nama"}:
            raise SystemExit(
                "Placeholder tidak ditemukan di lapisan teks PDF "
                f"('{PLACEHOLDER_KODE}' dan teks berawalan '{PLACEHOLDER_NAMA_PREFIX}'). "
                "PDF hasil scan tidak bisa dipakai.")

        posisi = {
            "page_width": round(page.rect.width, 2),
            "page_height": round(page.rect.height, 2),
            "max_x": MAX_X,
            "min_fontsize": MIN_SIZE,
        }
        for kunci, span in slot.items():
            posisi[kunci] = {
                "origin": [round(span["origin"][0], 2), round(span["origin"][1], 2)],
                "size": round(span["size"], 2),
                "bbox": [round(v, 2) for v in span["bbox"]],
            }

        for kunci in slot:
            page.add_redact_annot(pymupdf.Rect(slot[kunci]["bbox"]))
        page.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_NONE,
                              graphics=pymupdf.PDF_REDACT_LINE_ART_NONE,
                              text=pymupdf.PDF_REDACT_TEXT_REMOVE)

        keluaran.parent.mkdir(parents=True, exist_ok=True)
        doc.save(str(keluaran), garbage=4, deflate=True)
    finally:
        doc.close()

    posisi_keluar.parent.mkdir(parents=True, exist_ok=True)
    posisi_keluar.write_text(json.dumps(posisi, indent=2, ensure_ascii=False), encoding="utf-8")
    return posisi


def main(argv=None) -> None:
    parser = argparse.ArgumentParser(description="Bersihkan master LAMPIRAN 1 dari teks placeholder.")
    parser.add_argument("--sumber", default=str(SUMBER_LAMPIRAN),
                        help="PDF sumber (bawaan: STEP 4/Mater Lampiran.pdf)")
    parser.add_argument("--keluaran", default=str(STORAGE / "master" / "lampiran1_bersih.pdf"),
                        help="PDF bersih hasil skrip")
    parser.add_argument("--posisi", default=str(STORAGE / "master" / "lampiran1_posisi.json"),
                        help="Berkas JSON posisi placeholder")
    parser.add_argument("--lewati-akhir", action="store_true",
                        help="jangan salin Halaman Terakhir (STEP 6) ke storage/master")
    parser.add_argument("--lewati-font", action="store_true",
                        help="jangan salin font Calibri ke storage/fonts")
    args = parser.parse_args(argv)

    (STORAGE / "master").mkdir(parents=True, exist_ok=True)
    posisi = bersihkan(Path(args.sumber), Path(args.keluaran), Path(args.posisi))

    if not args.lewati_akhir:
        akhir = STORAGE / "master" / "halaman_akhir.pdf"
        if SUMBER_AKHIR.is_file():
            shutil.copyfile(SUMBER_AKHIR, akhir)
        else:
            print(f"PERINGATAN: {SUMBER_AKHIR} tidak ada; halaman akhir tidak disalin.")

    if not args.lewati_font:
        asal_font = Path(r"C:\Windows\Fonts\calibri.ttf")
        tujuan_font = STORAGE / "fonts" / "unifont" / "calibri.ttf"
        if asal_font.is_file():
            tujuan_font.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(asal_font, tujuan_font)
        else:
            print(f"PERINGATAN: {asal_font} tidak ada; font tidak disalin.")

    print(f"OK: {args.keluaran}")
    print(f"posisi: {posisi['kode']['origin']} / {posisi['nama']['origin']}")


if __name__ == "__main__":
    main()
