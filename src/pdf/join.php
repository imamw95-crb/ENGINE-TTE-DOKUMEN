<?php

declare(strict_types=1);

/**
 * Inti pembuatan PDF: isi LAMPIRAN 1 + gabung 4 bagian.
 *
 * Catatan penting: master LAMPIRAN 1 (`storage/master/lampiran1_bersih.pdf`)
 * sudah dibersihkan dari teks placeholder oleh `bin/siapkan_master.py`,
 * sehingga di sini cukup menulis nilai baru pada posisi yang tercatat.
 * PHP tidak dipakai untuk menghapus teks karena FPDI/tFPDF tidak mendukungnya.
 */

use setasign\Fpdi\Tfpdf\Fpdi as FpdiTfpdf;

const TTE_PT_MM = 25.4 / 72.0;

function posisi_lampiran(): array
{
    static $posisi = null;
    if ($posisi === null) {
        $berkas = cfg('master_dir') . DIRECTORY_SEPARATOR . 'lampiran1_posisi.json';
        $posisi = is_file($berkas)
            ? (json_decode((string) file_get_contents($berkas), true) ?: [])
            : [];
    }
    return $posisi;
}

function master_lampiran_path(): string
{
    $stmt = db()->query("SELECT path FROM master_berkas WHERE jenis = 'lampiran1' AND aktif = 1 ORDER BY id DESC LIMIT 1");
    $path = $stmt->fetchColumn();
    if ($path && is_file((string) $path)) {
        return (string) $path;
    }
    return cfg('master_dir') . DIRECTORY_SEPARATOR . 'lampiran1_bersih.pdf';
}

function master_akhir_path(): string
{
    $stmt = db()->query("SELECT path FROM master_berkas WHERE jenis = 'lampiran2' AND aktif = 1 ORDER BY id DESC LIMIT 1");
    $path = $stmt->fetchColumn();
    if ($path && is_file((string) $path)) {
        return (string) $path;
    }
    return cfg('master_dir') . DIRECTORY_SEPARATOR . 'halaman_akhir.pdf';
}

function pdf_baru(): FpdiTfpdf
{
    $pdf = new FpdiTfpdf('P', 'mm');
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetCreator('TTE Patrol');
    $pdf->SetTitle('Dokumen TTE RSMPP');
    return $pdf;
}

function pdf_halaman(string $path): int
{
    if (!is_file($path)) {
        return 0;
    }
    try {
        $pdf = pdf_baru();
        return (int) $pdf->setSourceFile($path);
    } catch (Throwable $e) {
        return jumlah_halaman($path);
    }
}

/** Buat halaman LAMPIRAN 1 terisi kode RSMPP + "no_form - nama form". */
function lampiran_buat(string $kode, string $noForm, string $namaForm, string $dest): void
{
    $posisi = posisi_lampiran();
    if (empty($posisi['kode']) || empty($posisi['nama'])) {
        throw new RuntimeException('Posisi placeholder LAMPIRAN 1 belum tersedia. Jalankan bin/siapkan_master.py');
    }

    $pdf = pdf_baru();
    $pdf->AddFont('calibri', '', 'calibri.ttf', true);

    $pdf->setSourceFile(master_lampiran_path());
    $tpl = $pdf->importPage(1);
    $ukuran = $pdf->getTemplateSize($tpl);
    $pdf->AddPage($ukuran['orientation'], [$ukuran['width'], $ukuran['height']]);
    $pdf->useTemplate($tpl, 0, 0, $ukuran['width'], $ukuran['height']);

    $nilai = [
        'kode' => $kode,
        'nama' => $noForm !== '' ? $noForm . ' - ' . $namaForm : $namaForm,
    ];

    $maxXmm = (float) ($posisi['max_x'] ?? 540.0) * TTE_PT_MM;
    $minPt = (float) ($posisi['min_fontsize'] ?? 7.5);

    $pdf->SetTextColor(0, 0, 0);
    foreach (['kode', 'nama'] as $kunci) {
        $teks = trim($nilai[$kunci]);
        if ($teks === '') {
            continue;
        }
        $x = (float) $posisi[$kunci]['origin'][0] * TTE_PT_MM;
        $y = (float) $posisi[$kunci]['origin'][1] * TTE_PT_MM;
        $ukuranFont = (float) $posisi[$kunci]['size'];

        $pdf->SetFont('calibri', '', $ukuranFont);
        while ($ukuranFont > $minPt && $x + $pdf->GetStringWidth($teks) > $maxXmm) {
            $ukuranFont -= 0.1;
            $pdf->SetFont('calibri', '', $ukuranFont);
        }
        $pdf->Text($x, $y, $teks);
    }

    $pdf->Output('F', $dest);
}

/**
 * Gabung beberapa PDF berurutan.
 * @param array<int, array{nama:string, path:string}> $bagian
 * @return array{halaman: array<string,int>, total: int}
 */
function pdf_gabung(array $bagian, string $dest): array
{
    $pdf = pdf_baru();
    $catatan = [];
    $total = 0;

    foreach ($bagian as $b) {
        if (!is_file($b['path'])) {
            throw new RuntimeException('Berkas tidak ada: ' . $b['nama']);
        }
        $jumlah = (int) $pdf->setSourceFile($b['path']);
        for ($i = 1; $i <= $jumlah; $i++) {
            $tpl = $pdf->importPage($i);
            $ukuran = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($ukuran['orientation'], [$ukuran['width'], $ukuran['height']]);
            $pdf->useTemplate($tpl, 0, 0, $ukuran['width'], $ukuran['height']);
            $total++;
        }
        $catatan[$b['nama']] = $jumlah;
    }

    $pdf->Output('F', $dest);
    $catatan['total'] = $total;
    return ['halaman' => $catatan, 'total' => $total];
}
