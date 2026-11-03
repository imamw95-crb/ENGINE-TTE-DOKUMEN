<?php

declare(strict_types=1);

/** Ambil konfigurasi global. */
function cfg(?string $kunci = null, $default = null)
{
    static $konfig = null;
    if ($konfig === null) {
        $konfig = require __DIR__ . '/config.php';
        foreach ($konfig as $k => $v) {
            if (is_string($v) && str_contains($v, DIRECTORY_SEPARATOR) && in_array($k, [
                    'storage', 'master_dir', 'template_dir', 'surat_dir', 'output_dir', 'tmp_dir', 'font_dir'], true)) {
                if (!is_dir($v)) {
                    @mkdir($v, 0777, true);
                }
            }
        }
        foreach (['master_dir', 'template_dir', 'surat_dir', 'output_dir', 'tmp_dir', 'font_dir'] as $k) {
            if (!is_dir($konfig[$k])) {
                @mkdir($konfig[$k], 0777, true);
            }
        }
    }
    if ($kunci === null) {
        return $konfig;
    }
    return $konfig[$kunci] ?? $default;
}

function e($teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $rute, array $params = []): string
{
    return 'index.php?' . http_build_query(array_merge(['r' => $rute], $params));
}

function redirect(string $rute, array $params = []): never
{
    header('Location: ' . url($rute, $params));
    exit;
}

function flash(?string $pesan = null, string $jenis = 'info'): array
{
    if ($pesan !== null) {
        $_SESSION['flash'][] = ['pesan' => $pesan, 'jenis' => $jenis];
        return [];
    }
    $daftar = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $daftar;
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_wajib(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $kirim = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($kirim) || !hash_equals($_SESSION['csrf'] ?? '', $kirim)) {
        http_response_code(400);
        exit('CSRF token tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

/** Bersihkan nama file agar aman di Windows. */
function nama_aman(string $teks): string
{
    $hasil = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]/u', '', $teks);
    return trim(preg_replace('/\s+/u', ' ', $hasil) ?? '');
}

function ukuran_manusia(int $byte): string
{
    if ($byte >= 1048576) {
        return round($byte / 1048576, 1) . ' MB';
    }
    if ($byte >= 1024) {
        return round($byte / 1024) . ' KB';
    }
    return $byte . ' B';
}

function sha256_file(string $path): string
{
    return is_file($path) ? hash_file('sha256', $path) : '';
}

/** Jumlah halaman PDF tanpa library (hitung "/Type /Page" pada objek). */
function jumlah_halaman(string $path): int
{
    if (!is_file($path)) {
        return 0;
    }
    $isi = file_get_contents($path, false, null, 0, 4 << 20);
    if ($isi === false) {
        return 0;
    }
    $n = preg_match_all('/\/Type\s*\/Page[^s]/', $isi);
    if ($n > 0) {
        return $n;
    }
    if (preg_match('/\/Count\s+(\d+)/', $isi, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/** Parse "<nomor> <nama>.pdf" menjadi [nomor, nama]. */
function parse_nama_surat(string $namaFile): array
{
    $stem = preg_replace('/\.pdf$/i', '', trim($namaFile)) ?? $namaFile;
    $stem = preg_replace('/\s+/u', ' ', $stem) ?? $stem;
    if (preg_match('/^(\d{6,})\s+(.+)$/u', $stem, $m)) {
        return [$m[1], trim($m[2])];
    }
    return ['', trim($stem)];
}

function normal_form(string $teks): string
{
    return strtoupper(trim(preg_replace('/\s+/u', ' ', $teks) ?? ''));
}

function view(string $nama, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $berkas = __DIR__ . '/../views/' . $nama . '.php';
    if (!is_file($berkas)) {
        http_response_code(500);
        exit('View tidak ditemukan: ' . e($nama));
    }
    require $berkas;
}

function json_keluar(array $data, int $kode = 200): never
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
