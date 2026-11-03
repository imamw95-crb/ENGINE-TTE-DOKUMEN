# TTE PATROL ENGINE — aplikasi PHP

Aplikasi penggabung dokumen TTE QuickSign RSMPP (versi PHP, tanpa framework).
Alur: **unggah surat → ambil/isi dokumen → kelola template → generate → unduh**.

Nama & urutan output tetap sama dengan versi lama:
`<nama dokter> - <no_form> - <nama form>.pdf`, isi **Surat (3 hal) → LAMPIRAN 1 → Template → Halaman Terakhir (2 hal)**.

## Kebutuhan

- PHP 8.1+ dengan ekstensi `pdo_sqlite`, `curl`, `dom`, `mbstring`, `openssl`, `fileinfo`
- Composer — library: `setasign/fpdi`, `setasign/tfpdf`, `maennchen/zipstream-php`
- Python 3.10+ **hanya untuk skrip bantu**: `bin/siapkan_master.py`, `bin/seed_step_data.py`,
  `bin/golden_test.py` — paketnya: `python -m pip install pymupdf openpyxl`

## Baru clone repo ini? Baca ini dulu

Repo ini **hanya berisi kode**. Dua hal sengaja tidak ikut di-commit (lihat `.gitignore`) dan
harus disiapkan sendiri. Tanpa itu aplikasi tetap bisa dibuka (login, unggah surat, kelola
template), tetapi **generate selalu gagal**.

| Tidak ikut | Cara mendapatkannya |
|---|---|
| `vendor/` — library FPDI / tFPDF / ZipStream | `composer install` |
| `storage/` — `app.db`, `secret.key`, master PDF, font, surat, template, output | dibuat otomatis; master LAMPIRAN 1 diunggah lewat menu Master (atau `python bin\siapkan_master.py`) |

Gejala kalau `storage/master/` belum disiapkan — saat menekan **Generate**:

```
Posisi placeholder LAMPIRAN 1 belum tersedia. Jalankan bin/siapkan_master.py
```

`bin/siapkan_master.py` membaca dua PDF sumber dari folder kerja lama:

```
..\STEP 4 Dokumen Dengan No Form\Mater Lampiran.pdf
..\STEP 6 Halaman Master Terakhir\Halaman Terakhir.pdf
```

**Opsi A — clone di dalam folder kerja (paling mudah).** Taruh repo sebagai
`...\ttepatrol\web` dan pastikan `STEP 4 ...` serta `STEP 6 ...` ada di `...\ttepatrol\`.
Kedua path itu ditemukan otomatis oleh script.

**Opsi B — clone di tempat lain.** Unduh kedua PDF tersebut, lalu ubah konstanta
`SUMBER_LAMPIRAN` dan `SUMBER_AKHIR` di bagian atas `bin\siapkan_master.py` ke lokasi berkasnya
(script ini **tidak** membaca environment variable). Di Linux/macOS, ganti juga baris
`shutil.copyfile(r"C:\Windows\Fonts\calibri.ttf", ...)` menjadi path Calibri/Carlito setempat.

Tidak punya kedua PDF itu? LAMPIRAN 1 memang tidak bisa dibuat tanpa berkas sumbernya —
lihat [Catatan penting: LAMPIRAN 1](#catatan-penting-lampiran-1).

> **Agent AI / kontributor:** baca [AGENTS.md](AGENTS.md) sebelum mengubah kode. Isinya aturan
> singkat: apa yang tidak boleh di-commit, cara verifikasi, dan jebakan PDF + encoding.

## Mulai dari clone (langkah demi langkah)

```powershell
# 1. dependensi PHP
composer install

# 2. dependensi Python (hanya untuk skrip bantu)
python -m pip install pymupdf openpyxl

# 3. master LAMPIRAN 1 + LAMPIRAN 2 + font Calibri (sekali saja; lihat "Baru clone" di atas)
python bin\siapkan_master.py

# 4. buat tabel database + akun admin
php bin\init.php admin "GantiPassword!" admin

# 5. opsional: impor data lama dari folder STEP 1/4/5
python bin\seed_step_data.py

# 6. jalankan
php -S 0.0.0.0:8081 -t public
```

Buka <http://localhost:8081>, lalu login dengan akun dari langkah 4.

Langkah 4 sekaligus mencetak status master — pastikan keduanya `(ok)`:

```
Master LAMPIRAN 1: ...\storage\master\lampiran1_bersih.pdf (ok)
Master LAMPIRAN 2: ...\storage\master\halaman_akhir.pdf (ok)
```

Kalau masih `(BELUM ADA)`, langkah 3 belum berhasil (umumnya PDF sumber tidak ditemukan).

`storage/app.db` pada instalasi baru masih kosong: dokter, dokumen, dan template diisi lewat UI
(unggah surat → dokumen & no. form → template) atau lewat langkah 5.

Uji cepat setelah ada data:

```powershell
php bin\generate.php "dr. Putri Panji Lestari"
python bin\golden_test.py
```

## Halaman

Menu disusun **step by step** di sidebar kiri:

| Langkah | Menu | Fungsi |
|---|---|---|
| 1 | **Unggah surat** | Unggah PDF surat (bisa banyak). Nomor + nama dokter dibaca dari nama file `<nomor> <nama>.pdf`, bisa dikoreksi. |
| 2 | **Dokumen & No. Form** | Daftar dokter & dokumen (kode RSMPP + no form). Ada tombol **Ambil data dari SIMRS** (global) dan tombol **SIMRS** per dokter yang langsung membuka halaman SIMRS dengan dokter sudah terpilih. Isi manual / impor CSV tetap tersedia. |
| 3 | **Template form** | Unggah / unggah ulang template per form (versioning), aktifkan versi, hapus, lihat PDF. |
| 4 | **Generate & unduh** | Generate per dokter atau semua dokter (progress), unduh per file / ZIP dokter / ZIP batch. |
| — | **Ambil data dari SIMRS** (Pengaturan) | Login SIMRS → uji endpoint → petakan kolom → *Ambil Data* (per dokter) → *Terapkan*. |
| — | **Master lampiran** (Pengaturan) | Unggah **LAMPIRAN 1** (placeholder dibersihkan otomatis oleh `bin/siapkan_master.py`) dan **Halaman Terakhir / LAMPIRAN 2**. |

Setiap halaman langkah menampilkan penanda **"Langkah N dari 4"** di atas judul.

## Catatan penting: LAMPIRAN 1

PHP (FPDI/tFPDF) **tidak bisa menghapus teks** yang sudah ada di PDF. Karena itu
placeholder di master LAMPIRAN 1 dibersihkan oleh `bin/siapkan_master.py` (PyMuPDF)
menjadi PDF bersih + `storage/master/lampiran1_posisi.json`; aplikasi lalu hanya
menempelkan nilai baru di posisi tersebut.

Dua cara mengganti master LAMPIRAN 1:

1. **Unggah lewat menu Master lampiran** (disarankan). Unggah PDF 1 halaman yang masih
   berisi placeholder `RSMPP26090009` dan `FN25010002 - ...`; aplikasi memanggil
   `bin/siapkan_master.py` otomatis (butuh Python + `pymupdf`, atur `TTE_PYTHON` bila
   `python` tidak ada di PATH), menyimpan PDF bersih sebagai versi baru, dan menghitung
   ulang posisi placeholder. Berkas asli dihapus setelah diproses.
2. **Manual lewat skrip**, mis. untuk desain baru di luar aplikasi: ganti
   `STEP 4 Dokumen Dengan No Form\Mater Lampiran.pdf`, jalankan
   `python bin/siapkan_master.py`, lalu generate ulang.

## Struktur

```
web/
├── public/index.php          front controller + router (?r=...)
├── public/assets/app.css
├── src/
│   ├── config.php            konfigurasi (env-driven)
│   ├── helpers.php           util (escape, csrf, flash, nama file aman)
│   ├── db.php                PDO + skema + enkripsi password SIMRS
│   ├── controllers.php       semua handler
│   ├── pdf/join.php          isi LAMPIRAN 1 + gabung PDF (FPDI/tFPDF)
│   └── services/
│       ├── generate.php      orkestrasi generate per dokter
│       ├── simrs.php         login SIMRS, ambil halaman, parsing & pemetaan tabel
│       └── impor.php         impor CSV dokumen
├── views/                    layout + halaman
├── bin/
│   ├── init.php              buat tabel + user
│   ├── generate.php          generate lewat CLI
│   ├── siapkan_master.py     bersihkan master LAMPIRAN 1
│   ├── seed_step_data.py     migrasi data STEP 1/4/5 ke database
│   ├── golden_test.py        bandingkan hasil PHP dengan output lama
│   └── impor_template.php   impor template PDF sekaligus dari folder
└── storage/                  runtime — TIDAK di-commit (lihat .gitignore)
```

Selain `storage/`, `vendor/` juga tidak di-commit. Aturan kerja untuk agent AI ada di
[AGENTS.md](AGENTS.md).

## Konfigurasi (environment)

| Env | Default |
|---|---|
| `TTE_DB_DSN` | `sqlite:<web>/storage/app.db` |
| `TTE_STORAGE` | `<web>/storage` |
| `TTE_OUTPUT` | `<web>/storage/output` |
| `TTE_PYTHON` | `python` (interpreter untuk `bin/siapkan_master.py` saat unggah LAMPIRAN 1) |
| `TTE_SIMRS_BASE` | `http://10.x.x.x/simrs` (placeholder — isi lewat `.env`) |

Alamat SIMRS **tidak** disimpan di repo. Isi di berkas `.env` (sudah masuk `.gitignore`),
dipakai baik oleh `php -S ...` lokal maupun `docker compose`:

```
TTE_SIMRS_BASE=http://<ip-simrs>/simrs
```

Nilai `.env` hanya dipakai kalau environment variable bernama sama belum diset, jadi
`TTE_SIMRS_BASE=... php -S ...` tetap menang.

## Sumber data SIMRS yang sudah dipetakan (SIMRS)

Menu di SIMRS: **IT → Master No. Form** (`index.php/itmaster/noform`).
Data tabelnya dimuat lewat DataTables (bukan HTML), jadi aplikasi memakai endpoint JSON-nya:

| Sumber | Endpoint | Metode | Params | Nilai kolom |
|---|---|---|---|---|
| Master No. Form (per dokter) | `index.php/itmaster/listener_dt_noform_user` | POST | `{"aktif":"1"}` | `[0]` kode RSMPP · `[1]` karyawan/dokter · `[2]` "FN… - NAMA" |
| Master form (tanpa dokter) | `index.php/itmaster/listener_dt_noform` | POST | `{"aktif":"1"}` | `[0]` id · `[1]` kode FN · `[2]` nama form · `[3]` no dokumen RSMPP/F.IRM… |

Catatan dari pengujian langsung:

- Tanpa `aktif=1` endpoint mengembalikan **0 baris**.
- Harus **POST** (GET hanya memberi 10 baris / kosong).
- Satu permintaan dengan `iDisplayLength=5000` sudah mengembalikan seluruh 3030 baris.
- Login memakai cookie `rsmpp_session`; akun disimpan terenkripsi di tabel `simrs_akun`.
- Login gagal terdeteksi dari cookie (`flash:new:login_error`), bukan dari HTML.

Alur di menu **Setting SIMRS**:

1. **Akun** dan **sumber data** bisa ditambah, **diedit** (tombol *Edit* mengisi formulir,
   kosongkan password bila tidak diganti), **diuji login**, atau **dihapus**.
2. Pilih **akun**, **sumber**, dan **dokter** (wajib — data hanya diambil untuk satu dokter).
   Kolom **cari dokter** bisa dipakai mengetik nama atau nomor dokter untuk menyaring daftar.
3. *Uji Sumber* → kolom terpakai mengikuti pemetaan yang tersimpan (bisa diubah kapan saja).
4. *Ambil Data* → hanya baris dokter itu yang masuk `simrs_staging`.
   Duplikat no_form di SIMRS (kode lama + baru untuk form yang sama) digabung otomatis,
   dipakai yang `lastchange`-nya paling baru.
5. Halaman menampilkan **dampak**: berapa dokumen baru / kode akan diubah / sudah sama.
6. *Terapkan ke &lt;nama dokter&gt;*. Secara default **kode lama tidak ditimpa** —
   hanya dokumen baru yang ditambahkan. Centang "Timpa kode RSMPP" bila memang ingin mengganti.

## Uji

```powershell
php bin\generate.php "dr. Putri Panji Lestari"
python bin\golden_test.py
```

Hasil yang diharapkan: **29 file, 214 halaman**, nama file sama, isi LAMPIRAN 1 sama
dengan output versi lama (hasil uji terakhir: `GOLDEN OK`).

Pemeriksaan SIMRS tanpa browser:

```powershell
php bin\uji_simrs.php
```

## Deploy dengan Docker

Repo sudah menyertakan `Dockerfile`, `docker-compose.yml`, dan konfigurasi Apache/PHP di `docker/`.
Image = PHP 8.3 + Apache (document root `public/`), `vendor/` di-install saat build, dan `storage/`
di-mount dari host supaya database serta PDF hasil generate tetap ada ketika container dibuat ulang.

### Deploy pertama

```bash
git clone https://github.com/imamw95-crb/ENGINE-TTE-DOKUMEN.git ~/ttepatrol-web
cd ~/ttepatrol-web
mkdir -p storage/master storage/template storage/surat storage/output storage/tmp storage/fonts/unifont
```

Empat berkas berikut **tidak ada di repo** dan harus disalin dari instalasi lokal (`web/storage/`):

```
storage/master/lampiran1_bersih.pdf
storage/master/lampiran1_posisi.json
storage/master/halaman_akhir.pdf
storage/fonts/unifont/calibri.ttf
```

Kalau keempatnya belum ada, container tetap jalan tetapi generate akan gagal
(lihat [Catatan penting: LAMPIRAN 1](#catatan-penting-lampiran-1)).

```bash
docker compose up -d --build
docker compose exec -T -u www-data web php bin/init.php admin 'PASSWORD_ANDA' admin
```

Buka `http://<ip-server>:8090` lalu login. Port host diatur pada `docker-compose.yml` (`"8090:80"`).

> `-u www-data` **wajib**. Apache berjalan sebagai `www-data`; kalau perintah dijalankan tanpa
> `-u` (jadi root), `storage/app.db` dimiliki root dan login gagal dengan
> `SQLITE_READONLY: attempt to write a readonly database`.

### Update ke versi terbaru

```bash
cd ~/ttepatrol-web
git pull
docker compose up -d --build
```

### Perintah harian

```bash
docker compose ps                       # status + healthcheck
docker compose logs -f --tail=100 web   # log Apache/PHP
docker compose exec web php bin/generate.php "dr. Putri Panji Lestari"
docker compose exec -T -u www-data web php bin/impor_template.php <folder-pdf> [form-master.json]
docker compose exec web ls -l storage/master   # cek master sudah lengkap
```

### Catatan

- `TTE_SIMRS_BASE` bisa diganti tanpa mengubah kode: buat `.env` berisi
  `TTE_SIMRS_BASE=http://<ip-simrs>/simrs`, lalu `docker compose up -d`. Berkas `.env` juga dibaca
  oleh aplikasi (lihat [Konfigurasi](#konfigurasi-environment)).
- Upload dibatasi 64 MB (`docker/php.ini`), waktu eksekusi 600 detik untuk generate massal.
- Semua isi `storage/` dimiliki `www-data`. Menyalin berkas master ke container yang **sudah
  berjalan** pakai `docker compose cp <berkas> web:/var/www/html/storage/master/` (SFTP ke folder
  host akan ditolak karena permission), lalu `docker compose restart web`.
- Container tidak membawa data dokter/pasien apa pun: isi lewat UI atau
  `php bin/generate.php` setelah data dimasukkan.
- **Unggah LAMPIRAN 1 dari UI butuh Python + `pymupdf` di runtime** (untuk menjalankan
  `bin/siapkan_master.py`). Image bawaan hanya PHP, jadi di Docker: siapkan master di host
  (`python bin/siapkan_master.py`) lalu salin dengan `docker compose cp` di atas, atau
  tambahkan Python ke image dan set `TTE_PYTHON`. Unggah Halaman Terakhir (LAMPIRAN 2)
  tidak butuh Python.
- Untuk mengisi 29 template form sekaligus tanpa klik satu per satu:
  `docker compose exec -T -u www-data web php bin/impor_template.php /path/folder /path/form_master.json`

## Keamanan

- Login lokal (role `admin` / `operator` / `viewer`), CSRF token di semua POST.
- Password SIMRS disimpan **terenkripsi AES-256-CBC** dengan kunci lokal `storage/secret.key`.
- Semua unduhan melewati tabel hasil (tidak ada path dari request langsung ke filesystem).
- Folder `STEP 1`–`STEP 6` tidak pernah ditulis; hanya dibaca saat seed.
