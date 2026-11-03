# AGENTS.md — aturan kerja repo TTE PATROL ENGINE

Aplikasi PHP (tanpa framework) untuk menggabungkan dokumen TTE QuickSign RSMPP menjadi
**satu PDF per form per dokter**. Isi tiap PDF, urutannya tetap:

**Surat permohonan (3 hal) → LAMPIRAN 1 hasil generate (1 hal) → Template form (1–3 hal) → Halaman Terakhir / LAMPIRAN 2 (2 hal)**

Baca [README.md](README.md) untuk panduan pemakaian; dokumen ini untuk yang mengubah kode.

## Menjalankan & memverifikasi

```powershell
composer install                 # vendor/ tidak ada di repo
python -m pip install pymupdf openpyxl
python bin\siapkan_master.py     # butuh STEP 4/STEP 6 di folder induk — lihat README
php bin\init.php admin "GantiPassword!" admin
php -S 0.0.0.0:8081 -t public    # http://localhost:8081
```

Setelah mengubah kode, minimal:

```powershell
php -l src\controllers.php                     # untuk setiap file PHP yang disentuh
php bin\generate.php "dr. Putri Panji Lestari" # hasil harus 29 file, 214 halaman
python bin\golden_test.py                      # harus "GOLDEN OK"
```

Kalau tidak ada data di `storage/app.db`, uji generate tidak bisa dijalankan — sebutkan itu di
laporan alih-alih mengarang hasil.

## Jangan

- **Jangan** `git add` `storage/` atau `vendor/`. `storage/` berisi `app.db`, `secret.key`
  (kunci AES-256 password SIMRS), surat, dan output PDF berisi data pasien.
- **Jangan** menaruh kredensial, token, URL/akun SIMRS nyata, atau path absolut mesin pribadi
  di kode yang di-commit. Repo ini publik. Alamat SIMRS disimpan di `.env` (gitignored),
  jangan dikembalikan ke `src/config.php`.
- **Jangan** menghapus atau menimpa `storage/app.db`, `storage/surat/`, `storage/output/`
  tanpa perintah eksplisit dari user. Kalau perlu merapikan, pindahkan ke folder backup.
- **Jangan** mengubah urutan bagian PDF, format nama berkas
  (`<nama dokter> - <no_form> - <nama form>.pdf`), atau urutan file per dokter (`no_form` menaik).
- **Jangan** menambahkan library lewat `composer require` tanpa memperbarui `composer.lock`
  dan menyebutkannya di ringkasan.

## Peta kode

| Berkas | Isi |
|---|---|
| `public/index.php` | front controller, router `?r=...`, sesi, dispatch ke controller |
| `src/config.php` | konfigurasi (semua path diturunkan dari `dirname(__DIR__)`, bisa dioverride env `TTE_*`) |
| `src/helpers.php` | `cfg()`, escape HTML, CSRF, flash, nama file aman, `jumlah_halaman()` |
| `src/db.php` | koneksi PDO SQLite, skema tabel (idempoten), enkripsi/dekripsi `secret.key` |
| `src/controllers.php` | semua handler halaman + endpoint AJAX (surat, dokter, template, generate, SIMRS, unduh) |
| `src/pdf/join.php` | isi LAMPIRAN 1 + gabung PDF memakai FPDI/tFPDF |
| `src/services/generate.php` | orkestrasi generate per dokter (pilih template, nama file, tulis `hasil_file`) |
| `src/services/simrs.php` | login SIMRS, panggil endpoint DataTables, parsing & pemetaan baris |
| `src/services/impor.php` | impor CSV dokumen |
| `views/` | layout (`_header`/`_footer`) + halaman per langkah (`tutorial` berisi panduan pemakaian) |
| `Dockerfile`, `docker-compose.yml`, `docker/` | image PHP 8.3 + Apache; `docker/entrypoint.sh` menyiapkan folder `storage/` sebelum Apache jalan |
| `bin/` | skrip CLI: `init.php`, `generate.php`, `impor_template.php`, `siapkan_master.py`, `seed_step_data.py`, `golden_test.py`, `uji_simrs.php` |

## Jebakan yang sering bikin salah

- **FPDI/tFPDF tidak bisa menghapus teks.** Karena itu `Mater Lampiran.pdf` dibersihkan
  oleh `bin/siapkan_master.py` (PyMuPDF) menjadi PDF bersih di `storage/master/`, dan posisi
  tulisnya disimpan di `storage/master/lampiran1_posisi.json`. Unggah LAMPIRAN 1 dari menu
  Master memanggil skrip itu otomatis (lewat `proc_open`, interpreter dari `TTE_PYTHON`);
  bila Python/`pymupdf` tidak terpasang, unggahan LAMPIRAN 1 gagal dengan pesan jelas
  (pakai jalur skrip manual). PHP hanya menempelkan teks baru. Tanpa JSON posisi,
  `lampiran_buat()` melempar `RuntimeException`.
- `define('FPDF_FONTPATH', ...)` harus dipanggil **sebelum** file FPDF dimuat
  (lihat `bin/generate.php` dan `bin/init.php`).
- Font Calibri disalin dari `C:\Windows\Fonts\calibri.ttf` oleh `siapkan_master.py` — tidak
  di-commit karena lisensi; di OS lain ganti path atau pakai Carlito.
- `bin/siapkan_master.py` dan `bin/seed_step_data.py` memakai path **folder induk**
  (`WORKSPACE / "STEP 4 ..."`), bukan environment variable.
- Encoding console Windows: set `$env:PYTHONIOENCODING='utf-8'` sebelum mencetak teks PDF
  (ada karakter bullet `U+F0B7`); jangan menambah output non-ASCII ke skrip yang mencetak
  isi PDF.
- Folder `storage/` dibuat malas (`mkdir` di `helpers.php`, `controllers.php`,
  `services/generate.php`), jadi tidak perlu menyiapkan foldernya manual.
- Kode RSMPP **berbeda per dokter** — jangan menyamakan satu daftar kode untuk semua dokter.
- Pencocokan template memakai `nama_form` ternormalisasi (uppercase, spasi tunggal, trim)
  dibandingkan dengan nama file template tanpa `.pdf`; form tanpa template akan dilewati dan
  dilaporkan, bukan error.
