<?php $judul = 'Tutorial penggunaan'; require __DIR__ . '/_header.php'; ?>
<h1>Tutorial penggunaan</h1>
<p class="sub">
  Alur kerja: <strong>unggah surat → dokumen &amp; no. form → template form → generate &amp; unduh</strong>.
  Master lampiran disiapkan sekali di awal. Menu di kiri mengikuti urutan langkah ini.
</p>

<div class="kartu" style="margin-bottom:16px">
  <h3 style="margin-top:0">Sebelum mulai (sekali saja)</h3>
  <ol class="sempit" style="margin:0;padding-left:20px;line-height:1.7">
    <li>Login sebagai <strong>admin</strong> atau <strong>operator</strong> (viewer hanya bisa melihat).</li>
    <li>Buka <a href="<?= e(url('master')) ?>">Master lampiran</a> → unggah <strong>LAMPIRAN 1</strong> dan <strong>Halaman Terakhir (LAMPIRAN 2)</strong>.
      Tanpa keduanya, generate akan ditolak.</li>
    <li>Siapkan <a href="<?= e(url('template')) ?>">template form</a> untuk setiap jenis dokumen yang mau dibuat.</li>
  </ol>
</div>

<h2>1. Unggah surat</h2>
<div class="kartu">
  <p class="sempit" style="margin-top:0">
    Menu <a href="<?= e(url('surat')) ?>">Unggah surat</a>. Nama berkas menentukan nomor dan nama dokter:
    <code>&lt;nomor dokter&gt; &lt;nama dokter&gt;.pdf</code> — contoh
    <code>202304024 dr. Putri Panji Lestari.pdf</code>. Nomor &amp; nama bisa dikoreksi setelah diunggah.
  </p>
  <ul class="sempit" style="line-height:1.7">
    <li>Bisa unggah banyak PDF sekaligus.</li>
    <li>Surat yang dipakai adalah <strong>surat permohonan QuickSign</strong> (3 halaman).</li>
    <li>Satu dokter = satu surat. Mengunggah ulang akan menambah berkas; pilih yang benar di halaman dokter.</li>
  </ul>
</div>

<h2>2. Dokumen &amp; No. Form</h2>
<div class="kartu">
  <p class="sempit" style="margin-top:0">
    Menu <a href="<?= e(url('dokter')) ?>">Dokumen &amp; No. Form</a>. Tiap dokter punya daftar dokumen
    dengan <strong>kode RSMPP</strong> (berbeda per dokter) dan <strong>no_form</strong>
    (mis. <code>FN24120001</code>) beserta nama form.
  </p>
  <ul class="sempit" style="line-height:1.7">
    <li>Isi manual, impor CSV, atau ambil dari SIMRS (lihat bagian <a href="#simrs">Ambil data dari SIMRS</a>).</li>
    <li>Status <span class="badge tunggu">tunggu template</span> berarti belum ada template untuk form itu —
      dokumen tetap tersimpan, tapi dilewati saat generate.</li>
    <li>Kode RSMPP jangan disamakan antar dokter; ambil dari data masing-masing.</li>
  </ul>
</div>

<h2>3. Template form</h2>
<div class="kartu">
  <p class="sempit" style="margin-top:0">
    Menu <a href="<?= e(url('template')) ?>">Template form</a>. Satu template per form; nama berkas
    <strong>harus sama</strong> dengan nama form (tanpa <code>.pdf</code>), mis.
    <code>FORM BUKTI PELAYANAN.pdf</code>.
  </p>
  <ul class="sempit" style="line-height:1.7">
    <li>Unggah ulang = versi baru; versi lama bisa diaktifkan kembali dari daftar.</li>
    <li><strong>Edit</strong> (buka “edit nama / no form”) untuk memperbaiki no form atau nama form;
      perubahan ikut diterapkan ke daftar dokumen dokter yang memakai form tersebut.</li>
    <li>Form tanpa template akan dilewati saat generate dan dilaporkan, bukan error.</li>
    <li>Template dipakai apa adanya (1–3 halaman), jadi pastikan hasil scan/export-nya rapi.</li>
  </ul>
</div>

<h2>4. Generate &amp; unduh</h2>
<div class="kartu">
  <p class="sempit" style="margin-top:0">
    Menu <a href="<?= e(url('generate')) ?>">Generate &amp; unduh</a>. Pilih satu dokter atau semua dokter,
    tunggu progres selesai, lalu unduh per file / ZIP per dokter / ZIP per batch.
  </p>
  <p class="sempit" style="margin-bottom:6px">Isi tiap PDF (urutan tetap):</p>
  <table>
    <thead><tr><th>Bagian</th><th class="kanan">Halaman</th><th>Sumber</th></tr></thead>
    <tbody>
      <tr><td>Surat permohonan</td><td class="kanan">3</td><td>Menu Unggah surat</td></tr>
      <tr><td>LAMPIRAN 1 / Attachment 1</td><td class="kanan">1</td><td>Master LAMPIRAN 1 (diisi nomor &amp; nama dokumen)</td></tr>
      <tr><td>Template form</td><td class="kanan">1–3</td><td>Menu Template form</td></tr>
      <tr><td>Halaman Terakhir / LAMPIRAN 2</td><td class="kanan">2</td><td>Master LAMPIRAN 2</td></tr>
    </tbody>
  </table>
  <p class="sempit" style="margin-bottom:0">
    Nama file hasil: <code>&lt;nama dokter&gt; - &lt;no_form&gt; - &lt;nama form&gt;.pdf</code>,
    diurutkan menaik berdasarkan <code>no_form</code>.
  </p>
</div>

<h2>Master lampiran</h2>
<div class="kartu">
  <p class="sempit" style="margin-top:0">
    Menu <a href="<?= e(url('master')) ?>">Master lampiran</a>. Setiap unggahan menjadi versi baru;
    versi lama tetap tersimpan dan bisa diaktifkan lagi.
  </p>
  <h3>LAMPIRAN 1</h3>
  <ul class="sempit" style="line-height:1.7">
    <li>Unggah PDF <strong>1 halaman</strong> yang masih memuat placeholder
      <code>RSMPP26090009</code> dan <code>FN25010002 - ...</code>.</li>
    <li>Placeholder dihapus otomatis oleh <code>bin/siapkan_master.py</code> (PyMuPDF) dan posisi tulisnya
      dihitung ulang; PDF hasil scan tanpa lapisan teks akan ditolak.</li>
    <li>Kalau desain berubah (logo/alamat), cukup edit PDF-nya lalu unggah ulang — tidak perlu ubah kode.</li>
  </ul>
  <h3>Halaman Terakhir (LAMPIRAN 2)</h3>
  <ul class="sempit" style="line-height:1.7;margin-bottom:0">
    <li>Unggah <code>Halaman Terakhir.pdf</code>, biasanya <strong>2 halaman</strong> dan tanpa placeholder.</li>
  </ul>
</div>

<h2 id="simrs">Ambil data dari SIMRS</h2>
<div class="kartu">
  <p class="sempit" style="margin-top:0">
    Menu <a href="<?= e(url('simrs')) ?>">Ambil data dari SIMRS</a>.
    Alurnya: simpan akun → tentukan sumber/endpoint → <em>uji</em> → petakan kolom →
    <em>Ambil Data</em> per dokter → <em>Terapkan</em> ke daftar dokumen.
  </p>
  <ul class="sempit" style="line-height:1.7;margin-bottom:0">
    <li>Data hasil ambil masuk ke tabel staging dulu, jadi bisa diperiksa sebelum diterapkan.</li>
    <li>Alamat SIMRS disimpan di berkas <code>.env</code>, bukan di kode.</li>
  </ul>
</div>

<h2>Kalau ada masalah</h2>
<div class="kartu padat">
  <table>
    <thead><tr><th>Gejala</th><th>Penyebab</th><th>Solusi</th></tr></thead>
    <tbody>
      <tr>
        <td class="sempit">Generate ditolak / “Posisi placeholder LAMPIRAN 1 belum tersedia”</td>
        <td class="sempit">Master LAMPIRAN 1 (atau 2) belum ada</td>
        <td class="sempit">Unggah di menu Master lampiran.</td>
      </tr>
      <tr>
        <td class="sempit">“LAMPIRAN 1 harus tepat 1 halaman” / placeholder tidak ditemukan</td>
        <td class="sempit">PDF lebih dari 1 halaman, hasil scan, atau teks placeholder diubah</td>
        <td class="sempit">Pakai PDF 1 halaman berlapis teks yang memuat kedua placeholder.</td>
      </tr>
      <tr>
        <td class="sempit">“Gagal menyiapkan master: Python tidak bisa dijalankan”</td>
        <td class="sempit">Python/PyMuPDF belum ada di server</td>
        <td class="sempit">Image Docker bawaan sudah menyertakan Python. Untuk instalasi manual, set <code>TTE_PYTHON</code>.</td>
      </tr>
      <tr>
        <td class="sempit">Dokumen berstatus <span class="badge tunggu">tunggu template</span> dan tidak ikut ter-generate</td>
        <td class="sempit">Belum ada template dengan nama yang sama</td>
        <td class="sempit">Unggah template di menu Template form (nama berkas = nama form).</td>
      </tr>
      <tr>
        <td class="sempit">Login gagal: <code>attempt to write a readonly database</code></td>
        <td class="sempit">Perintah CLI dijalankan sebagai root sehingga <code>app.db</code> tidak bisa ditulis Apache</td>
        <td class="sempit">Jalankan dengan <code>-u www-data</code> (Docker) atau perbaiki pemilik berkas.</td>
      </tr>
      <tr>
        <td class="sempit">File hasil tidak bisa dibuka / ditimpa</td>
        <td class="sempit">PDF masih terbuka di viewer</td>
        <td class="sempit">Tutup viewer, lalu generate ulang.</td>
      </tr>
      <tr>
        <td class="sempit">Hasil hanya beberapa file dari daftar dokumen</td>
        <td class="sempit">Sebagian form belum punya template</td>
        <td class="sempit">Lihat laporan generate; lengkapi template lalu generate ulang.</td>
      </tr>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
