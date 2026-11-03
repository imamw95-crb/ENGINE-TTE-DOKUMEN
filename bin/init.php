<?php

declare(strict_types=1);

/**
 * Inisialisasi aplikasi: buat tabel + user admin.
 *
 * Pemakaian:
 *   php bin/init.php [username] [password] [role]
 *   php bin/init.php admin Rahasia123 admin
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

define('FPDF_FONTPATH', rtrim(cfg('font_dir'), '/\\') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/pdf/join.php';

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'Admin#2026';
$role = $argv[3] ?? 'admin';

$pdo = db();

$stmt = $pdo->prepare('SELECT id FROM users WHERE lower(username) = lower(?)');
$stmt->execute([$username]);
$id = $stmt->fetchColumn();

if ($id === false) {
    $pdo->prepare('INSERT INTO users (username, nama, role, pass_hash) VALUES (?, ?, ?, ?)')
        ->execute([$username, ucfirst($username), $role, password_hash($password, PASSWORD_DEFAULT)]);
    echo "User dibuat: {$username} ({$role})\n";
} else {
    $pdo->prepare('UPDATE users SET pass_hash = ?, role = ?, aktif = 1 WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $role, $id]);
    echo "User diperbarui: {$username} ({$role})\n";
}

echo 'Database: ' . cfg('db') . "\n";
echo 'Master LAMPIRAN 1: ' . master_lampiran_path() . (is_file(master_lampiran_path()) ? ' (ok)' : ' (BELUM ADA)') . "\n";
echo 'Master LAMPIRAN 2: ' . master_akhir_path() . (is_file(master_akhir_path()) ? ' (ok)' : ' (BELUM ADA)') . "\n";
