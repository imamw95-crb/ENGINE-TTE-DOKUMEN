<?php $judul = 'Dashboard'; require __DIR__ . '/_header.php'; ?>
<h1>Dashboard</h1>
<p class="sub">Alur: unggah surat → ambil/isi dokumen → kelola template → generate &amp; unduh.</p>

<div class="grid">
  <div class="kartu"><div class="angka"><?= (int) $angka['dokter'] ?></div><div class="label">Dokter</div></div>
  <div class="kartu"><div class="angka"><?= (int) $angka['surat'] ?></div><div class="label">Surat terunggah</div></div>
  <div class="kartu"><div class="angka"><?= (int) $angka['dokumen'] ?></div><div class="label">Dokumen</div></div>
  <div class="kartu"><div class="angka"><?= (int) $angka['form'] ?></div><div class="label">Form master</div></div>
  <div class="kartu"><div class="angka"><?= (int) $angka['template'] ?></div><div class="label">Template aktif</div></div>
  <div class="kartu"><div class="angka"><?= (int) $angka['hasil'] ?></div><div class="label">PDF hasil</div></div>
</div>

<h2>Alur kerja</h2>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr))">
  <div class="kartu">
    <div class="angkah"><span class="nomor">1</span><strong>Unggah surat</strong></div>
    <p class="sempit muted">Upload PDF surat permohonan QuickSign (bisa banyak sekaligus).</p>
    <p class="sempit"><span class="badge <?= $angka['surat'] ? 'ok' : 'tunggu' ?>"><?= (int) $angka['surat'] ?> surat</span></p>
    <a class="btn kecil" href="<?= e(url('surat')) ?>">Buka langkah 1</a>
  </div>
  <div class="kartu">
    <div class="angkah"><span class="nomor">2</span><strong>Dokumen &amp; No. Form</strong></div>
    <p class="sempit muted">Ambil dari SIMRS atau isi manual: kode RSMPP + no form per dokter.</p>
    <p class="sempit"><span class="badge <?= $angka['dokumen'] ? 'ok' : 'tunggu' ?>"><?= (int) $angka['dokumen'] ?> dokumen</span>
       <span class="badge netral"><?= (int) $angka['dokter'] ?> dokter</span></p>
    <a class="btn kecil" href="<?= e(url('dokter')) ?>">Buka langkah 2</a>
  </div>
  <div class="kartu">
    <div class="angkah"><span class="nomor">3</span><strong>Template form</strong></div>
    <p class="sempit muted">Satu template PDF per form; bisa diunggah ulang (versi baru).</p>
    <p class="sempit"><span class="badge <?= $angka['template'] ? 'ok' : 'tunggu' ?>"><?= (int) $angka['template'] ?> template aktif</span></p>
    <a class="btn kecil" href="<?= e(url('template')) ?>">Buka langkah 3</a>
  </div>
  <div class="kartu">
    <div class="angkah"><span class="nomor">4</span><strong>Generate &amp; unduh</strong></div>
    <p class="sempit muted">Gabung surat + LAMPIRAN 1 + template + halaman terakhir, lalu unduh.</p>
    <p class="sempit"><span class="badge <?= $angka['hasil'] ? 'ok' : 'tunggu' ?>"><?= (int) $angka['hasil'] ?> PDF hasil</span></p>
    <a class="btn kecil" href="<?= e(url('generate')) ?>">Buka langkah 4</a>
  </div>
</div>

<h2>Pengaturan</h2>
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr))">
  <div class="kartu">
    <strong>Ambil data dari SIMRS</strong>
    <p class="sempit muted">Akun, sumber data, pencarian dokter, dan penerapan kode RSMPP.</p>
    <a class="btn kecil abu" href="<?= e(url('simrs')) ?>">Buka</a>
  </div>
  <div class="kartu">
    <strong>Master lampiran</strong>
    <p class="sempit muted">LAMPIRAN 1 (dikelola skrip) dan Halaman Terakhir (LAMPIRAN 2).</p>
    <a class="btn kecil abu" href="<?= e(url('master')) ?>">Buka</a>
  </div>
</div>

<h2>Master lampiran</h2>
<div class="kartu padat">
  <table>
    <thead><tr><th>Jenis</th><th>Berkas</th><th>Halaman</th><th>Status</th></tr></thead>
    <tbody>
      <?php
      $adaJenis = [];
      foreach ($master as $m) {
          if ((int) $m['aktif'] === 1) {
              $adaJenis[$m['jenis']] = $m;
          }
      }
      $bawaan = [
          'lampiran1' => cfg('master_dir') . DIRECTORY_SEPARATOR . 'lampiran1_bersih.pdf',
          'lampiran2' => cfg('master_dir') . DIRECTORY_SEPARATOR . 'halaman_akhir.pdf',
      ];
      foreach (['lampiran1' => 'LAMPIRAN 1', 'lampiran2' => 'Halaman Terakhir (LAMPIRAN 2)'] as $jenis => $label):
          $m = $adaJenis[$jenis] ?? null;
          $pakaiBawaan = !$m && is_file($bawaan[$jenis]); ?>
      <tr>
        <td><?= e($label) ?></td>
        <td><?= $m ? e($m['filename']) : ($pakaiBawaan ? '<span class="muted">' . e(basename($bawaan[$jenis])) . '</span>' : '<span class="muted">belum diunggah</span>') ?></td>
        <td><?= $m ? (int) $m['halaman'] : ($pakaiBawaan ? pdf_halaman($bawaan[$jenis]) : '-') ?></td>
        <td>
          <?php if ($m): ?><span class="badge ok">aktif</span>
          <?php elseif ($pakaiBawaan): ?><span class="badge info">bawaan</span>
          <?php else: ?><span class="badge gagal">belum ada</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h2>Batch terakhir</h2>
<div class="kartu padat">
  <table>
    <thead><tr><th>Kode</th><th>Status</th><th class="kanan">File</th><th>Dibuat</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batch as $b): ?>
      <tr>
        <td><?= e($b['kode']) ?></td>
        <td><span class="badge <?= $b['status'] === 'selesai' ? 'ok' : 'info' ?>"><?= e($b['status']) ?></span></td>
        <td class="kanan"><?= (int) $b['total_file'] ?></td>
        <td class="muted"><?= e($b['created_at']) ?></td>
        <td class="kanan"><a class="btn kecil abu" href="<?= e(url('unduh', ['jenis' => 'batch', 'id' => $b['id']])) ?>">ZIP batch</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$batch): ?><tr><td colspan="5" class="muted">Belum ada batch.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
