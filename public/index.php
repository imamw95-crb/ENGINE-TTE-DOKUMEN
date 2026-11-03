<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

define('FPDF_FONTPATH', rtrim(cfg('font_dir'), '/\\') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/pdf/join.php';
require __DIR__ . '/../src/services/impor.php';
require __DIR__ . '/../src/services/generate.php';
require __DIR__ . '/../src/services/simrs.php';
require __DIR__ . '/../src/controllers.php';

session_name((string) cfg('session_name'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$rute = (string) ($_GET['r'] ?? 'dashboard');

$publik = ['login'];
csrf_wajib();
if (!in_array($rute, $publik, true)) {
    wajib_login();
}

$peta = [
    'login' => 'ctrl_login',
    'logout' => 'ctrl_logout',
    'dashboard' => 'ctrl_dashboard',
    'surat' => 'ctrl_surat',
    'surat.upload' => 'ctrl_surat_upload',
    'surat.hapus' => 'ctrl_surat_hapus',
    'dokter' => 'ctrl_dokter',
    'dokter.detail' => 'ctrl_dokter_detail',
    'dokter.simpan' => 'ctrl_dokter_simpan',
    'dokumen.simpan' => 'ctrl_dokumen_simpan',
    'dokumen.hapus' => 'ctrl_dokumen_hapus',
    'dokumen.impor' => 'ctrl_dokumen_impor',
    'template' => 'ctrl_template',
    'template.upload' => 'ctrl_template_upload',
    'template.aktif' => 'ctrl_template_aktif',
    'template.hapus' => 'ctrl_template_hapus',
    'master' => 'ctrl_master',
    'master.upload' => 'ctrl_master_upload',
    'simrs' => 'ctrl_simrs',
    'simrs.akun' => 'ctrl_simrs_akun',
    'simrs.akun.hapus' => 'ctrl_simrs_akun_hapus',
    'simrs.sumber' => 'ctrl_simrs_sumber',
    'simrs.sumber.hapus' => 'ctrl_simrs_sumber_hapus',
    'simrs.uji.login' => 'ctrl_simrs_uji_login',
    'simrs.uji' => 'ctrl_simrs_uji',
    'simrs.ambil' => 'ctrl_simrs_ambil',
    'simrs.terapkan' => 'ctrl_simrs_terapkan',
    'simrs.batal' => 'ctrl_simrs_batal',
    'generate' => 'ctrl_generate',
    'generate.mulai' => 'ctrl_generate_mulai',
    'generate.satu' => 'ctrl_generate_satu',
    'generate.selesai' => 'ctrl_generate_selesai',
    'unduh' => 'ctrl_unduh',
];

if (!isset($peta[$rute])) {
    http_response_code(404);
    exit('Halaman tidak ditemukan: ' . e($rute));
}

($peta[$rute])();
