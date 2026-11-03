<?php $judul = 'Step 1 · Surat'; require __DIR__ . '/_header.php'; ?>
<h1>Step 1 — Surat permohonan</h1>
<p class="sub">Unggah PDF surat QuickSign. Nomor &amp; nama dokter dibaca dari nama file
  <code>&lt;nomor&gt; &lt;nama dokter&gt;.pdf</code> — bisa dikoreksi di bawah bila perlu.</p>

<div class="kartu">
  <form method="post" action="<?= e(url('surat.upload')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <label for="berkas">File PDF (boleh pilih banyak)</label>
    <input type="file" id="berkas" name="berkas[]" accept="application/pdf" multiple required>
    <div class="baris" style="margin-top:10px">
      <div>
        <label for="nomor">Nomor dokter (opsional, untuk semua file)</label>
        <input type="text" id="nomor" name="nomor" placeholder="202304024">
      </div>
      <div>
        <label for="nama">Nama dokter (opsional)</label>
        <input type="text" id="nama" name="nama" placeholder="dr. Nama Lengkap, Sp.X">
      </div>
      <div style="flex:0">
        <button type="submit" class="btn">Unggah</button>
      </div>
    </div>
  </form>

  <div style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--line)">
    <strong class="sempit">Langkah berikutnya</strong>
    <p class="sempit muted" style="margin:4px 0 8px">
      Setelah surat terunggah, ambil Master No. Form (kode RSMPP) dari SIMRS untuk dokter tersebut.
    </p>
    <a class="btn kecil" href="<?= e(url('simrs')) ?>">Lanjut → Ambil data Master Form dari SIMRS</a>
  </div>
</div>

<h2>Surat tersimpan (<?= count($rows) ?>)</h2>
<div class="kartu padat">
  <table>
    <thead><tr><th>Nomor</th><th>Dokter</th><th>File</th><th class="kanan">Halaman</th><th>Diunggah</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['nomor']) ?></td>
        <td><a href="<?= e(url('dokter.detail', ['id' => $r['dokter_id']])) ?>"><?= e($r['dokter']) ?></a></td>
        <td class="muted sempit"><?= e($r['filename']) ?></td>
        <td class="kanan"><?= (int) $r['halaman'] ?></td>
        <td class="muted sempit"><?= e($r['uploaded_at']) ?></td>
        <td class="kanan">
          <?php if (boleh_tulis()): ?>
          <form method="post" action="<?= e(url('surat.hapus')) ?>" class="inline" onsubmit="return confirm('Hapus surat ini?')">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="btn kecil merah" type="submit">Hapus</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">Belum ada surat.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
