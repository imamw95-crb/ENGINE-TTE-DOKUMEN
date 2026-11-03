<?php $judul = 'Step 4 · Dokumen'; require __DIR__ . '/_header.php'; ?>
<h1>Step 4 — Dokumen &amp; No. Form</h1>
<p class="sub">Daftar dokumen per dokter (kode RSMPP + no form). Bisa diisi manual, impor CSV,
  atau <a href="<?= e(url('simrs')) ?>">diambil dari SIMRS</a>.</p>

<div class="kartu" style="margin-bottom:14px;background:#f8fafc;border-color:#cbd5e1">
  <div class="baris" style="align-items:center">
    <div style="flex:1">
      <strong>Ambil otomatis dari SIMRS</strong>
      <p class="sempit muted" style="margin:4px 0 0">
        Kode RSMPP &amp; daftar form tiap dokter tidak perlu diketik manual — ambil dari menu
        <em>Master No. Form</em> di SIMRS, per dokter.
      </p>
    </div>
    <div style="flex:0">
      <a class="btn" href="<?= e(url('simrs')) ?>">Ambil data dari SIMRS</a>
    </div>
  </div>
</div>

<?php if (boleh_tulis()): ?>
<div class="kartu">
  <h3 style="margin-top:0">Tambah / perbarui dokter</h3>
  <form method="post" action="<?= e(url('dokter.simpan')) ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <div class="baris">
      <div><label for="nomor">Nomor dokter</label><input type="text" id="nomor" name="nomor" required></div>
      <div style="flex:2"><label for="nama">Nama dokter</label><input type="text" id="nama" name="nama" required></div>
      <div style="flex:0"><button class="btn" type="submit">Simpan</button></div>
    </div>
  </form>
</div>
<?php endif; ?>

<form method="get" style="margin:16px 0">
  <input type="hidden" name="r" value="dokter">
  <div class="baris">
    <div><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama atau nomor dokter"></div>
    <div style="flex:0"><button class="btn kecil abu" type="submit">Cari</button></div>
  </div>
</form>

<div class="kartu padat">
  <table>
    <thead><tr><th>Nomor</th><th>Nama</th><th class="kanan">Surat</th><th class="kanan">Dokumen</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $d): ?>
      <tr>
        <td class="muted"><?= e($d['nomor']) ?></td>
        <td><a href="<?= e(url('dokter.detail', ['id' => $d['id']])) ?>"><?= e($d['nama']) ?></a></td>
        <td class="kanan"><?= $d['jml_surat'] ? (int) $d['jml_surat'] : '<span class="badge gagal">0</span>' ?></td>
        <td class="kanan"><?= (int) $d['jml_dokumen'] ?></td>
        <td class="kanan" style="white-space:nowrap">
          <a class="btn kecil abu" href="<?= e(url('dokter.detail', ['id' => $d['id']])) ?>">Buka</a>
          <a class="btn kecil" href="<?= e(url('simrs', ['dokter_id' => $d['id']])) ?>">SIMRS</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted">Belum ada dokter. Unggah surat di Step 1.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
