<?php
$user = user_aktif();
$ruteAktif = (string) ($_GET['r'] ?? 'dashboard');

// langkah-langkah utama (urutan kerja)
$langkahMenu = [
    'surat' => ['no' => 1, 'label' => 'Unggah surat', 'ket' => 'PDF surat permohonan'],
    'dokter' => ['no' => 2, 'label' => 'Dokumen & No. Form', 'ket' => 'kode RSMPP per dokter'],
    'template' => ['no' => 3, 'label' => 'Template form', 'ket' => 'master PDF tiap form'],
    'generate' => ['no' => 4, 'label' => 'Generate & unduh', 'ket' => 'gabung & ambil hasil'],
];
$menuAlat = [
    'simrs' => 'Ambil data dari SIMRS',
    'master' => 'Master lampiran',
];

$aktifKey = null;
foreach (array_keys($langkahMenu) as $k) {
    if ($ruteAktif === $k || str_starts_with($ruteAktif, $k . '.')) {
        $aktifKey = $k;
    }
}
$adaSidebar = (bool) $user;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul ?? 'TTE PATROL ENGINE') ?> · TTE PATROL ENGINE</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<?php if ($user): ?>
<header class="topbar">
  <div class="brand">TTE <span>PATROL ENGINE</span></div>
  <div class="kanan">
    <a href="<?= e(url('dashboard')) ?>" class="<?= $ruteAktif === 'dashboard' ? 'aktif' : '' ?>">Beranda</a>
    <span><?= e($user['nama'] ?: $user['username']) ?> · <?= e($user['role']) ?></span>
    <form method="post" action="<?= e(url('logout')) ?>" class="inline">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <button type="submit">Keluar</button>
    </form>
  </div>
</header>

<div class="shell">
  <aside class="sidebar">
    <div class="grup">Alur kerja</div>
    <?php foreach ($langkahMenu as $r => $m):
        $aktif = $aktifKey === $r ? 'aktif' : ''; ?>
      <a href="<?= e(url($r)) ?>" class="<?= $aktif ?>">
        <span class="nomor"><?= (int) $m['no'] ?></span>
        <span>
          <span class="nama"><?= e($m['label']) ?></span>
          <span class="ket"><?= e($m['ket']) ?></span>
        </span>
      </a>
    <?php endforeach; ?>

    <div class="grup">Pengaturan</div>
    <?php foreach ($menuAlat as $r => $label):
        $aktif = $ruteAktif === $r || str_starts_with($ruteAktif, $r . '.') ? 'aktif' : ''; ?>
      <a href="<?= e(url($r)) ?>" class="<?= $aktif ?>">
        <span class="nomor alat">•</span>
        <span><span class="nama"><?= e($label) ?></span></span>
      </a>
    <?php endforeach; ?>
  </aside>

  <main class="isi">
    <?php if ($aktifKey !== null):
        $m = $langkahMenu[$aktifKey]; ?>
      <div class="langkah-chip">Langkah <?= (int) $m['no'] ?> dari 4 · <?= e($m['label']) ?></div>
    <?php endif; ?>
<?php else: ?>
<main class="wrap">
<?php endif; ?>
<?php foreach (flash() as $f): ?>
  <div class="flash <?= $f['jenis'] === 'error' ? 'error' : 'info' ?>"><?= e($f['pesan']) ?></div>
<?php endforeach; ?>
