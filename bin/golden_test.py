"""Golden test: hasil aplikasi PHP vs hasil lama (Python) untuk 1 dokter."""

import re
from pathlib import Path

import pymupdf

PHP = Path(r"C:\laragon\www\ttepatrol\web\storage\output\dr. Putri Panji Lestari")
GOLD = Path(r"C:\laragon\www\ttepatrol\Output Hasil Dokumen PDF Join\dr. Putri Panji Lestari")

php = {p.name: p for p in PHP.glob("*.pdf")}
gold = {p.name: p for p in GOLD.glob("*.pdf")}

print(f"file PHP  : {len(php)}")
print(f"file lama : {len(gold)}")
print(f"nama sama : {set(php) == set(gold)}")
if set(php) != set(gold):
    print("  hanya di PHP :", sorted(set(php) - set(gold))[:5])
    print("  hanya di lama:", sorted(set(gold) - set(php))[:5])

beda_halaman = 0
beda_lampiran = 0
total_halaman = 0
contoh = []

for nama in sorted(set(php) & set(gold)):
    a = pymupdf.open(str(php[nama]))
    b = pymupdf.open(str(gold[nama]))
    try:
        total_halaman += a.page_count
        if a.page_count != b.page_count:
            beda_halaman += 1
            contoh.append(f"{nama}: {a.page_count} vs {b.page_count} halaman")
            continue
        ta = re.sub(r"\s+", " ", a[3].get_text()).strip()
        tb = re.sub(r"\s+", " ", b[3].get_text()).strip()
        # bandingkan isi LAMPIRAN 1 tanpa normalisasi karakter penghubung
        ta_cmp = ta.replace("\u2010", "-").replace("\u2011", "-")
        tb_cmp = tb.replace("\u2010", "-").replace("\u2011", "-")
        if ta_cmp != tb_cmp:
            beda_lampiran += 1
            contoh.append(f"{nama}: isi LAMPIRAN 1 beda")
            if len(contoh) < 4:
                contoh.append("  PHP : " + ta_cmp[:180])
                contoh.append("  LAMA: " + tb_cmp[:180])
    finally:
        a.close()
        b.close()

print(f"\ntotal halaman PHP: {total_halaman}")
print(f"beda jumlah halaman: {beda_halaman}")
print(f"beda isi LAMPIRAN 1 : {beda_lampiran}")
for c in contoh[:10]:
    print("  !!", c)
print("\nGOLDEN " + ("OK" if (set(php) == set(gold) and beda_halaman == 0 and beda_lampiran == 0) else "TIDAK LOLOS"))
