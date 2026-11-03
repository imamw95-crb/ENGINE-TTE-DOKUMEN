<?php $judul = 'Master Lampiran'; require __DIR__ . '/_header.php'; ?>
<h1>Master lampiran</h1>
<p class="sub">LAMPIRAN 1 (berisi Nomor Dokumen &amp; Nama Dokumen) dan Halaman Terakhir / LAMPIRAN 2.</p>

<?php if (boleh_tulis()): ?>
<div class="baris" style="margin-bottom:16px">
  <div class="kartu" style="flex:2">
    <h3 style="margin-top:0">LAMPIRAN 1 — dikelola lewat skrip</h3>
    <p class="sempit muted">
      Halaman ini berisi placeholder teks yang harus <strong>benar-benar dihapus</strong>
      (bukan ditutup kotak putih). FPDI/tFPDF di PHP tidak bisa menghapus teks, jadi
      master LAMPIRAN 1 disiapkan sekali oleh <code>python web/bin/siapkan_master.py</code>
      dan disimpan sebagai <code>storage/master/lampiran1_bersih.pdf</code>.
    </p>
    <p class="sempit muted">
      Bila desain LAMPIRAN 1 berubah: ganti
      <code>STEP 4 Dokumen Dengan No Form\Mater Lampiran.pdf</code>, lalu jalankan
      <code>python web/bin/siapkan_master.py</code> lagi (posisi teks ikut dihitung ulang).
    </p>
  </div>
  <div class="kartu">
    <h3 style="margin-top:0">Unggah Halaman Terakhir</h3>
    <p class="sempit muted">Biasanya 2 halaman (LAMPIRAN 2 + riwayat revisi). Tidak berisi placeholder.</p>
    <form method="post" action="<?= e(url('master.upload')) ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="jenis" value="lampiran2">
      <input type="file" name="berkas" accept="application/pdf" required>
      <div style="margin-top:10px"><button class="btn" type="submit">Unggah</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="kartu padat">
  <table>
    <thead><tr><th>Jenis</th><th>Berkas</th><th class="kanan">Halaman</th><th>Status</th><th>Diunggah</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['jenis']) ?></td>
        <td class="sempit"><?= e($r['filename']) ?></td>
        <td class="kanan"><?= (int) $r['halaman'] ?></td>
        <td><?= (int) $r['aktif'] === 1 ? '<span class="badge ok">aktif</span>' : '<span class="badge netral">lama</span>' ?></td>
        <td class="muted sempit"><?= e($r['uploaded_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted">Belum ada master terunggah (memakai berkas bawaan di storage/master).</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<h2>Posisi placeholder LAMPIRAN 1</h2>
<div class="kartu">
  <?php $pos = posisi_lampiran(); ?>
  <?php if ($pos): ?>
    <p class="sempit">
      Halaman <?= e($pos['page_width'] ?? '') ?> × <?= e($pos['page_height'] ?? '') ?> pt ·
      kode di (<?= e(implode(', ', $pos['kode']['origin'] ?? [])) ?>) ·
      nama di (<?= e(implode(', ', $pos['nama']['origin'] ?? [])) ?>)
    </p>
    <p class="sempit muted">Bila master LAMPIRAN 1 diganti dan posisi berubah, jalankan
      <code>python web/bin/siapkan_master.py</code> untuk menghitung ulang.</p>
  <?php else: ?>
    <p class="sempit" style="color:#b91c1c">Posisi belum tersedia — jalankan <code>python web/bin/siapkan_master.py</code>.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
