# TTE PATROL ENGINE — aplikasi PHP

Aplikasi penggabung dokumen TTE QuickSign RSMPP (versi PHP, tanpa framework).
Alur: **unggah surat → ambil/isi dokumen → kelola template → generate → unduh**.

Nama & urutan output tetap sama dengan versi lama:
`<nama dokter> - <no_form> - <nama form>.pdf`, isi **Surat (3 hal) → LAMPIRAN 1 → Template → Halaman Terakhir (2 hal)**.

## Kebutuhan

- PHP 8.1+ (`pdo_sqlite`, `curl`, `dom`, `mbstring`, `openssl`, `fileinfo`)
- Composer (sudah terpasang: `setasign/fpdi`, `setasign/tfpdf`, `maennchen/zipstream-php`)
- Python 3.10+ **hanya untuk dua skrip bantu**: `bin/siapkan_master.py` dan `bin/seed_step_data.py`

## Instalasi

```powershell
cd C:\laragon\www\ttepatrol\web
composer install

python bin\siapkan_master.py      # sekali: bersihkan master LAMPIRAN 1 + posisi teks
php bin\init.php admin "GantiPassword!" admin

# opsional: pindahkan data lama (STEP 1/4/5) ke database
python bin\seed_step_data.py

php -S 0.0.0.0:8081 -t public     # buka http://localhost:8081
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
| — | **Master lampiran** (Pengaturan) | Unggah Halaman Terakhir (LAMPIRAN 2). **LAMPIRAN 1 dikelola skrip** (lihat di bawah). |

Setiap halaman langkah menampilkan penanda **"Langkah N dari 4"** di atas judul.

## Catatan penting: LAMPIRAN 1

PHP (FPDI/tFPDF) **tidak bisa menghapus teks** yang sudah ada di PDF. Karena itu
`Mater Lampiran.pdf` dibersihkan sekali oleh `bin/siapkan_master.py` (memakai PyMuPDF)
menjadi `storage/master/lampiran1_bersih.pdf`, dan posisi teksnya disimpan di
`storage/master/lampiran1_posisi.json`. Aplikasi lalu hanya menempelkan nilai baru.

Kalau desain LAMPIRAN 1 berubah:
1. ganti `STEP 4 Dokumen Dengan No Form\Mater Lampiran.pdf`,
2. jalankan `python bin/siapkan_master.py`,
3. generate ulang.

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
│   └── golden_test.py        bandingkan hasil PHP dengan output lama
└── storage/                  app.db, master/, template/, surat/, output/, tmp/
```

## Konfigurasi (environment)

| Env | Default |
|---|---|
| `TTE_DB_DSN` | `sqlite:<web>/storage/app.db` |
| `TTE_STORAGE` | `<web>/storage` |
| `TTE_OUTPUT` | `<web>/storage/output` |
| `TTE_SIMRS_BASE` | `http://10.4.0.22/simrs` |

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

## Keamanan

- Login lokal (role `admin` / `operator` / `viewer`), CSRF token di semua POST.
- Password SIMRS disimpan **terenkripsi AES-256-CBC** dengan kunci lokal `storage/secret.key`.
- Semua unduhan melewati tabel hasil (tidak ada path dari request langsung ke filesystem).
- Folder `STEP 1`–`STEP 6` tidak pernah ditulis; hanya dibaca saat seed.
