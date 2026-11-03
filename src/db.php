<?php

declare(strict_types=1);

/** Koneksi PDO (SQLite default) + skema + seed awal. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = cfg('db');
    $pdo = new PDO($dsn, cfg('db_user') ?: null, cfg('db_pass') ?: null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if (str_starts_with($dsn, 'sqlite')) {
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    migrasi($pdo);
    return $pdo;
}

function migrasi(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        nama TEXT NOT NULL DEFAULT '',
        role TEXT NOT NULL DEFAULT 'viewer',
        pass_hash TEXT NOT NULL,
        aktif INTEGER NOT NULL DEFAULT 1,
        last_login TEXT
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS dokter (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nomor TEXT NOT NULL UNIQUE,
        nama TEXT NOT NULL,
        aktif INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS surat (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dokter_id INTEGER NOT NULL REFERENCES dokter(id) ON DELETE CASCADE,
        filename TEXT NOT NULL,
        path TEXT NOT NULL,
        halaman INTEGER NOT NULL DEFAULT 0,
        sha256 TEXT,
        uploaded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS form_master (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        no_form TEXT NOT NULL UNIQUE,
        nama_form TEXT NOT NULL
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS template_form (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        form_id INTEGER NOT NULL REFERENCES form_master(id) ON DELETE CASCADE,
        versi INTEGER NOT NULL DEFAULT 1,
        filename TEXT NOT NULL,
        path TEXT NOT NULL,
        halaman INTEGER NOT NULL DEFAULT 0,
        sha256 TEXT,
        aktif INTEGER NOT NULL DEFAULT 1,
        uploaded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS dokumen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        dokter_id INTEGER NOT NULL REFERENCES dokter(id) ON DELETE CASCADE,
        no_form TEXT NOT NULL,
        kode_rsmpp TEXT NOT NULL DEFAULT '',
        nama_form TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'siap',
        sumber TEXT NOT NULL DEFAULT 'manual',
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (dokter_id, no_form)
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS master_berkas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        jenis TEXT NOT NULL,
        filename TEXT NOT NULL,
        path TEXT NOT NULL,
        halaman INTEGER NOT NULL DEFAULT 0,
        sha256 TEXT,
        aktif INTEGER NOT NULL DEFAULT 1,
        uploaded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS simrs_akun (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label TEXT NOT NULL,
        username TEXT NOT NULL,
        ipserver TEXT NOT NULL DEFAULT '',
        password_enc TEXT,
        aktif INTEGER NOT NULL DEFAULT 1
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS simrs_sumber (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        jenis TEXT NOT NULL DEFAULT 'master',
        url TEXT NOT NULL,
        metode TEXT NOT NULL DEFAULT 'POST',
        pemetaan TEXT,
        aktif INTEGER NOT NULL DEFAULT 1,
        catatan TEXT
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS simrs_staging (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sumber_id INTEGER,
        dokter_nomor TEXT,
        no_form TEXT,
        kode_rsmpp TEXT,
        nama_form TEXT,
        mentah TEXT,
        diambil_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS batch (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        kode TEXT NOT NULL UNIQUE,
        dibuat_oleh INTEGER,
        status TEXT NOT NULL DEFAULT 'jalan',
        total_file INTEGER NOT NULL DEFAULT 0,
        folder TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        selesai TEXT
    )
    SQL);

    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS hasil (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        batch_id INTEGER NOT NULL REFERENCES batch(id) ON DELETE CASCADE,
        dokter_id INTEGER,
        no_form TEXT,
        nama_form TEXT,
        filename TEXT NOT NULL,
        path TEXT NOT NULL,
        halaman INTEGER NOT NULL DEFAULT 0,
        ukuran INTEGER NOT NULL DEFAULT 0
    )
    SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_dokumen_dokter ON dokumen(dokter_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_hasil_batch ON hasil(batch_id)');

    tambah_kolom($pdo, 'simrs_sumber', 'url_data', 'TEXT');
    tambah_kolom($pdo, 'simrs_sumber', 'params', 'TEXT');
    tambah_kolom($pdo, 'simrs_staging', 'karyawan', 'TEXT');
    tambah_kolom($pdo, 'simrs_staging', 'dokter_id', 'INTEGER');
}

/** Tambah kolom bila belum ada (migrasi ringan untuk SQLite). */
function tambah_kolom(PDO $pdo, string $tabel, string $kolom, string $definisi): void
{
    $ada = false;
    foreach ($pdo->query('PRAGMA table_info(' . $tabel . ')') as $info) {
        if (($info['name'] ?? '') === $kolom) {
            $ada = true;
            break;
        }
    }
    if (!$ada) {
        $pdo->exec('ALTER TABLE ' . $tabel . ' ADD COLUMN ' . $kolom . ' ' . $definisi);
    }
}

function user_aktif(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND aktif = 1');
        $stmt->execute([$_SESSION['uid']]);
        $user = $stmt->fetch() ?: null;
        if ($user === null) {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

function wajib_login(): array
{
    $user = user_aktif();
    if ($user === null) {
        redirect('login');
    }
    return $user;
}

function boleh_tulis(): bool
{
    $user = user_aktif();
    return $user !== null && in_array($user['role'], ['admin', 'operator'], true);
}

function wajib_tulis(): void
{
    if (!boleh_tulis()) {
        http_response_code(403);
        exit('Role Anda tidak berhak melakukan aksi ini.');
    }
}

/** Kunci enkripsi lokal untuk password SIMRS. */
function kunci_lokal(): string
{
    static $kunci = null;
    if ($kunci !== null) {
        return $kunci;
    }
    $berkas = cfg('storage') . DIRECTORY_SEPARATOR . 'secret.key';
    if (!is_file($berkas)) {
        file_put_contents($berkas, bin2hex(random_bytes(32)));
        @chmod($berkas, 0600);
    }
    $kunci = hash('sha256', trim((string) file_get_contents($berkas)), true);
    return $kunci;
}

function enkripsi(string $teks): string
{
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($teks, 'aes-256-cbc', kunci_lokal(), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function dekripsi(?string $teks): string
{
    if (!$teks) {
        return '';
    }
    $mentah = base64_decode($teks, true);
    if ($mentah === false || strlen($mentah) < 17) {
        return '';
    }
    $iv = substr($mentah, 0, 16);
    $isi = substr($mentah, 16);
    $hasil = openssl_decrypt($isi, 'aes-256-cbc', kunci_lokal(), OPENSSL_RAW_DATA, $iv);
    return $hasil === false ? '' : $hasil;
}
