<?php

declare(strict_types=1);

/**
 * Daftarkan akun + sumber data SIMRS ke menu Settings (tabel simrs_akun / simrs_sumber).
 *
 * Kredensial dibaca dari environment supaya tidak tersimpan di riwayat shell:
 *   $env:TTE_SIMRS_USER='202307056'
 *   $env:TTE_SIMRS_PASS='...'
 *   php bin/siapkan_simrs.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/db.php';

$user = getenv('TTE_SIMRS_USER') ?: '';
$pass = getenv('TTE_SIMRS_PASS') ?: '';
$label = getenv('TTE_SIMRS_LABEL') ?: 'Akun SIMRS';
$base = rtrim(cfg('simrs_base'), '/');

if ($user === '' || $pass === '') {
    fwrite(STDERR, "Set TTE_SIMRS_USER dan TTE_SIMRS_PASS dulu.\n");
    exit(2);
}

$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM simrs_akun WHERE username = ?');
$stmt->execute([$user]);
$akunId = $stmt->fetchColumn();
if ($akunId === false) {
    $pdo->prepare('INSERT INTO simrs_akun (label, username, ipserver, password_enc) VALUES (?, ?, "", ?)')
        ->execute([$label, $user, enkripsi($pass)]);
    $akunId = (int) $pdo->lastInsertId();
    echo "Akun dibuat (#{$akunId})\n";
} else {
    $pdo->prepare('UPDATE simrs_akun SET label = ?, password_enc = ?, aktif = 1 WHERE id = ?')
        ->execute([$label, enkripsi($pass), $akunId]);
    echo "Akun diperbarui (#{$akunId})\n";
}

$sumber = [
    [
        'nama' => 'Master No. Form (per dokter)',
        'jenis' => 'per_dokter',
        'metode' => 'POST',
        'url' => $base . '/index.php/itmaster/noform',
        'url_data' => $base . '/index.php/itmaster/listener_dt_noform_user',
        'params' => json_encode(['aktif' => '1']),
    ],
];

foreach ($sumber as $s) {
    $stmt = $pdo->prepare('SELECT id FROM simrs_sumber WHERE nama = ?');
    $stmt->execute([$s['nama']]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        $pdo->prepare('INSERT INTO simrs_sumber (nama, jenis, url, url_data, metode, params) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$s['nama'], $s['jenis'], $s['url'], $s['url_data'], $s['metode'], $s['params']]);
        echo "Sumber dibuat: {$s['nama']} (#" . $pdo->lastInsertId() . ")\n";
    } else {
        $pdo->prepare('UPDATE simrs_sumber SET jenis = ?, url = ?, url_data = ?, metode = ?, params = ?, aktif = 1 WHERE id = ?')
            ->execute([$s['jenis'], $s['url'], $s['url_data'], $s['metode'], $s['params'], $id]);
        echo "Sumber diperbarui: {$s['nama']} (#{$id})\n";
    }
}
