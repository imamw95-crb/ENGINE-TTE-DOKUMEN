<?php
/**
 * Konfigurasi aplikasi TTE PATROL ENGINE (PHP).
 * Semua path diturunkan dari lokasi file ini; tidak ada path absolut hardcoded
 * kecuali nilai default yang bisa dioverride lewat environment variable.
 */

declare(strict_types=1);

$root = dirname(__DIR__);          // ...\ttepatrol\web
$workspace = dirname($root);       // ...\ttepatrol

// Muat berkas .env (tidak di-commit) bila ada, mis. berisi TTE_SIMRS_BASE.
// Dibungkus closure supaya variabel lokalnya tidak menimpa variabel pemanggil cfg().
(static function (string $berkas): void {
    if (!is_file($berkas)) {
        return;
    }
    foreach (file($berkas, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $baris) {
        $baris = trim($baris);
        if ($baris === '' || str_starts_with($baris, '#') || !str_contains($baris, '=')) {
            continue;
        }
        [$kunci, $nilai] = explode('=', $baris, 2);
        $kunci = trim($kunci);
        if ($kunci === '' || getenv($kunci) !== false) {
            continue;   // environment asli menang
        }
        putenv($kunci . '=' . trim($nilai, " \t\"'"));
        $_ENV[$kunci] = (string) getenv($kunci);
    }
})($root . DIRECTORY_SEPARATOR . '.env');

$env = static function (string $kunci, string $default = ''): string {
    $nilai = getenv($kunci);
    return $nilai === false || $nilai === '' ? $default : $nilai;
};

return [
    'root' => $root,
    'workspace' => $workspace,
    'storage' => $env('TTE_STORAGE', $root . DIRECTORY_SEPARATOR . 'storage'),
    'master_dir' => $env('TTE_MASTER', $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'master'),
    'template_dir' => $env('TTE_TEMPLATE', $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'template'),
    'surat_dir' => $env('TTE_SURAT', $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'surat'),
    'output_dir' => $env('TTE_OUTPUT', $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'output'),
    'tmp_dir' => $env('TTE_TMP', $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp'),
    'font_dir' => $env('TTE_FONT_DIR', $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'fonts'),
    'db' => $env('TTE_DB_DSN', 'sqlite:' . $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app.db'),
    'db_user' => $env('TTE_DB_USER', ''),
    'db_pass' => $env('TTE_DB_PASS', ''),
    'simrs_base' => $env('TTE_SIMRS_BASE', 'http://10.x.x.x/simrs'),
    'simrs_ua' => $env('TTE_SIMRS_UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36'),
    'session_name' => 'tte_patrol_php',
    'app_name' => 'TTE PATROL ENGINE',
];
