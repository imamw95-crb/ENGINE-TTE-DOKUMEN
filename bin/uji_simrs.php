<?php

declare(strict_types=1);

/**
 * Uji ambil data SIMRS lewat CLI (tanpa browser).
 *
 *   php bin/uji_simrs.php [sumber_id]
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/services/simrs.php';

$pdo = db();
$akun = $pdo->query('SELECT * FROM simrs_akun WHERE aktif = 1 ORDER BY id LIMIT 1')->fetch();
$sumberId = (int) ($argv[1] ?? 0);
$sumber = $sumberId > 0
    ? $pdo->query('SELECT * FROM simrs_sumber WHERE id = ' . $sumberId)->fetch()
    : $pdo->query('SELECT * FROM simrs_sumber WHERE aktif = 1 ORDER BY id DESC LIMIT 1')->fetch();

if (!$akun) {
    fwrite(STDERR, "Belum ada akun SIMRS. Jalankan bin/siapkan_simrs.php\n");
    exit(2);
}
if (!$sumber) {
    fwrite(STDERR, "Belum ada sumber data.\n");
    exit(2);
}

echo "Akun   : {$akun['label']} ({$akun['username']})\n";
echo "Sumber : {$sumber['nama']} -> " . ($sumber['url_data'] ?: $sumber['url']) . "\n";

$login = simrs_login($akun);
echo "Login  : " . ($login['ok'] ? 'OK' : 'GAGAL') . " — {$login['pesan']}\n";
if (!$login['ok']) {
    exit(1);
}

$hasil = simrs_sumber_data($akun, $sumber);
echo 'Ambil  : ' . ($hasil['ok'] ? 'OK' : 'GAGAL') . " — {$hasil['pesan']} (mode {$hasil['jenis']})\n";
if (!$hasil['ok']) {
    exit(1);
}

$t = $hasil['tabel'][0] ?? null;
if ($t) {
    echo 'Kolom  : ' . implode(' | ', $t['headers']) . "\n";
    foreach (array_slice($t['rows'], 0, 3) as $r) {
        echo '  - ' . implode(' | ', $r) . "\n";
    }
}

// cek pencocokan dokter untuk sumber per_dokter
$kolomKaryawan = 1;
$namaSet = [];
foreach ($t['rows'] as $r) {
    $nama = trim((string) ($r[$kolomKaryawan] ?? ''));
    if ($nama !== '') {
        $namaSet[$nama] = true;
    }
}
echo 'Karyawan unik di SIMRS: ' . count($namaSet) . "\n";

$cocok = 0;
$tidak = 0;
$contohTidak = [];
foreach (array_keys($namaSet) as $nama) {
    $stmt = $pdo->prepare('SELECT nama FROM dokter WHERE lower(trim(nama)) = ? OR lower(nama) LIKE ? LIMIT 1');
    $stmt->execute([strtolower(trim($nama)), strtolower(trim($nama)) . '%']);
    if ($stmt->fetchColumn()) {
        $cocok++;
    } else {
        $tidak++;
        if (count($contohTidak) < 5) {
            $contohTidak[] = $nama;
        }
    }
}
echo "Cocok dengan dokter di aplikasi: {$cocok}, tidak cocok: {$tidak}\n";
foreach ($contohTidak as $n) {
    echo "  ? {$n}\n";
}
