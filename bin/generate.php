<?php

declare(strict_types=1);

/**
 * Generate lewat CLI (tanpa membuka browser) — berguna untuk uji & batch terjadwal.
 *
 *   php bin/generate.php "dr. Putri Panji Lestari"
 *   php bin/generate.php --semua
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

define('FPDF_FONTPATH', rtrim(cfg('font_dir'), '/\\') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/pdf/join.php';
require __DIR__ . '/../src/services/generate.php';

$arg = $argv[1] ?? '';
if ($arg === '' || $arg === '--semua') {
    $stmt = db()->query('SELECT id, nama FROM dokter ORDER BY nama');
} else {
    $stmt = db()->prepare('SELECT id, nama FROM dokter WHERE nama = ? OR nomor = ?');
    $stmt->execute([$arg, $arg]);
}

$daftar = $stmt->fetchAll();
if (!$daftar) {
    fwrite(STDERR, "Dokter tidak ditemukan: {$arg}\n");
    exit(2);
}

$kode = 'B' . date('YmdHis');
db()->prepare("INSERT INTO batch (kode, status) VALUES (?, 'jalan')")->execute([$kode]);
$batchId = (int) db()->lastInsertId();

$total = 0;
foreach ($daftar as $d) {
    try {
        $hasil = generate_dokter((int) $d['id'], $batchId);
        $total += $hasil['jumlah'];
        printf("%-48s %3d file%s\n", $d['nama'], $hasil['jumlah'],
            $hasil['dilewati'] ? ' (' . count($hasil['dilewati']) . ' dilewati)' : '');
        foreach ($hasil['masalah'] as $m) {
            echo '   !! ', $m, PHP_EOL;
        }
    } catch (Throwable $ex) {
        printf("%-48s GAGAL: %s\n", $d['nama'], $ex->getMessage());
    }
}

db()->prepare("UPDATE batch SET status = 'selesai', total_file = ?, selesai = CURRENT_TIMESTAMP WHERE id = ?")
    ->execute([$total, $batchId]);

echo "\nTotal {$total} file di " . cfg('output_dir') . " (batch {$kode})\n";
