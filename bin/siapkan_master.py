"""Siapkan master PDF untuk aplikasi PHP (dijalankan sekali / saat master diganti).

Kenapa Python: FPDI/tFPDF tidak bisa menghapus teks yang sudah ada di PDF.
Placeholder `RSMPP26090009` dan `FN25010002 - ...` di Mater Lampiran perlu
benar-benar dihapus (bukan ditutup kotak putih) supaya lapisan teks hasilnya bersih.
Hasilnya disimpan ke storage/master/lampiran1_bersih.pdf, lalu aplikasi PHP
hanya menempelkan nilai baru di posisi yang tercatat.

Pakai:
    python web/bin/siapkan_master.py
"""

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


def main() -> None:
    (STORAGE / "master").mkdir(parents=True, exist_ok=True)
    (STORAGE / "fonts" / "unifont").mkdir(parents=True, exist_ok=True)

    doc = pymupdf.open(str(SUMBER_LAMPIRAN))
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
        raise SystemExit(f"Placeholder tidak ditemukan: {sorted(slot)}")

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

    tujuan = STORAGE / "master" / "lampiran1_bersih.pdf"
    doc.save(str(tujuan), garbage=4, deflate=True)
    doc.close()

    shutil.copyfile(SUMBER_AKHIR, STORAGE / "master" / "halaman_akhir.pdf")
    shutil.copyfile(r"C:\Windows\Fonts\calibri.ttf", STORAGE / "fonts" / "unifont" / "calibri.ttf")

    (STORAGE / "master" / "lampiran1_posisi.json").write_text(
        json.dumps(posisi, indent=2, ensure_ascii=False), encoding="utf-8")

    print(f"OK: {tujuan}")
    print(f"posisi: {posisi['kode']['origin']} / {posisi['nama']['origin']}")


if __name__ == "__main__":
    main()
