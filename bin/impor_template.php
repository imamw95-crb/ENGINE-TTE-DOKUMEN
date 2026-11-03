<?php

declare(strict_types=1);

/**
 * Impor template form dari sebuah folder PDF (batch, tanpa lewat browser).
 *
 *   php bin/impor_template.php "<folder-pdf>" ["<form-master.json>"]
 *
 * JSON opsional untuk mengisi tabel form_master lebih dulu (upsert):
 *   [{"no_form":"FN25010002","nama_form":"FORM BUKTI PELAYANAN"}, ...]
 *
 * Nama berkas dicocokkan dengan `nama_form` memakai normalisasi yang sama seperti
 * generator (uppercase, spasi tunggal, trim), karena sebagian berkas sumber punya
 * spasi di akhir nama. Berkas yang tidak cocok / sudah terdaftar dilaporkan, bukan error.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

define('FPDF_FONTPATH', rtrim(cfg('font_dir'), '/\\') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/pdf/join.php';

function norm_form(string $teks): string
{
    return trim(preg_replace('/\s+/u', ' ', mb_strtoupper($teks)) ?? '');
}

$folder = $argv[1] ?? '';
if ($folder === '' || !is_dir($folder)) {
    fwrite(STDERR, "Folder tidak ditemukan: {$folder}\n");
    fwrite(STDERR, "Pakai: php bin/impor_template.php \"<folder-pdf>\" [form-master.json]\n");
    exit(2);
}

$json = $argv[2] ?? '';
if ($json !== '') {
    $mentah = @file_get_contents($json);
    $daftar = $mentah === false ? null : json_decode($mentah, true);
    if (!is_array($daftar)) {
        fwrite(STDERR, "JSON form_master tidak bisa dibaca: {$json}\n");
        exit(2);
    }
    $upsert = db()->prepare(
        'INSERT INTO form_master (no_form, nama_form) VALUES (?, ?)
         ON CONFLICT(no_form) DO UPDATE SET nama_form = excluded.nama_form'
    );
    $n = 0;
    foreach ($daftar as $f) {
        $noForm = trim((string) ($f['no_form'] ?? ''));
        $namaForm = trim((string) ($f['nama_form'] ?? ''));
        if ($noForm === '' || $namaForm === '') {
            continue;
        }
        $upsert->execute([$noForm, $namaForm]);
        $n++;
    }
    echo "form_master: {$n} baris di-upsert\n";
}

$peta = [];
foreach (db()->query('SELECT id, no_form, nama_form FROM form_master') as $f) {
    $peta[norm_form((string) $f['nama_form'])] = $f;
}

$berkas = glob(rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
sort($berkas);

if (!$berkas) {
    fwrite(STDERR, "Tidak ada PDF di folder: {$folder}\n");
    exit(2);
}

$target = (string) cfg('template_dir');
if (!is_dir($target)) {
    mkdir($target, 0777, true);
}

$baru = 0;
$lewat = 0;
$gagal = [];

foreach ($berkas as $file) {
    $nama = basename($file);
    $form = $peta[norm_form(pathinfo($nama, PATHINFO_FILENAME))] ?? null;
    if ($form === null) {
        $gagal[] = $nama . ' — tidak ada form yang cocok';
        continue;
    }

    $cek = db()->prepare('SELECT COUNT(*) FROM template_form WHERE form_id = ? AND filename = ? AND aktif = 1');
    $cek->execute([$form['id'], nama_aman($nama)]);
    if ((int) $cek->fetchColumn() > 0) {
        $lewat++;
        continue;
    }

    $halaman = pdf_halaman($file);
    if ($halaman <= 0) {
        $gagal[] = $nama . ' — PDF tidak terbaca';
        continue;
    }

    $tujuan = $target . DIRECTORY_SEPARATOR . nama_aman($nama);
    if (realpath($file) !== realpath($tujuan) && !copy($file, $tujuan)) {
        $gagal[] = $nama . ' — gagal menyalin';
        continue;
    }

    $versi = (int) db()->query('SELECT COALESCE(MAX(versi), 0) FROM template_form WHERE form_id = '
        . (int) $form['id'])->fetchColumn() + 1;
    db()->prepare('UPDATE template_form SET aktif = 0 WHERE form_id = ?')->execute([$form['id']]);
    db()->prepare('INSERT INTO template_form (form_id, versi, filename, path, halaman, sha256, aktif)
                   VALUES (?, ?, ?, ?, ?, ?, 1)')
        ->execute([$form['id'], $versi, basename($tujuan), $tujuan, $halaman, sha256_file($tujuan)]);

    printf("%-12s %-58s v%d  %d hal\n", $form['no_form'], $form['nama_form'], $versi, $halaman);
    $baru++;
}

echo "\nBaru: {$baru} | dilewati (sudah ada): {$lewat} | gagal: " . count($gagal) . "\n";
foreach ($gagal as $g) {
    echo '  !! ', $g, PHP_EOL;
}

exit($gagal ? 1 : 0);
