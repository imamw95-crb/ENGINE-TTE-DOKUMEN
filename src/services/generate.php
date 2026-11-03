<?php

declare(strict_types=1);

require_once __DIR__ . '/../pdf/join.php';

/** Peta no_form => path template aktif. */
function peta_template(): array
{
    $sql = 'SELECT f.no_form, t.path, t.filename
            FROM template_form t
            JOIN form_master f ON f.id = t.form_id
            WHERE t.aktif = 1';
    $peta = [];
    foreach (db()->query($sql) as $row) {
        $peta[$row['no_form']] = $row;
    }
    return $peta;
}

/**
 * Buat semua PDF untuk satu dokter.
 * @return array{jumlah:int, dilewati:array<string>, folder:string, masalah:array<string>}
 */
function generate_dokter(int $dokterId, ?int $batchId = null): array
{
    $stmt = db()->prepare('SELECT * FROM dokter WHERE id = ?');
    $stmt->execute([$dokterId]);
    $dokter = $stmt->fetch();
    if (!$dokter) {
        throw new RuntimeException('Dokter tidak ditemukan');
    }

    $stmt = db()->prepare('SELECT * FROM surat WHERE dokter_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$dokterId]);
    $surat = $stmt->fetch();
    if (!$surat || !is_file($surat['path'])) {
        throw new RuntimeException('Surat permohonan belum diunggah untuk ' . $dokter['nama']);
    }

    $stmt = db()->prepare('SELECT * FROM dokumen WHERE dokter_id = ? ORDER BY no_form');
    $stmt->execute([$dokterId]);
    $dokumen = $stmt->fetchAll();

    $template = peta_template();
    $folder = cfg('output_dir') . DIRECTORY_SEPARATOR . nama_aman($dokter['nama']);
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $jumlah = 0;
    $dilewati = [];
    $masalah = [];
    $akhir = master_akhir_path();

    foreach ($dokumen as $d) {
        $tpl = $template[$d['no_form']] ?? null;
        if (!$tpl) {
            $dilewati[] = $d['no_form'] . ' - ' . $d['nama_form'] . ' (template belum ada)';
            continue;
        }

        $namaFile = nama_aman($dokter['nama'] . ' - ' . $d['no_form'] . ' - ' . $d['nama_form']) . '.pdf';
        $tujuan = $folder . DIRECTORY_SEPARATOR . $namaFile;
        $lampiran = cfg('tmp_dir') . DIRECTORY_SEPARATOR . 'lampiran_' . $dokterId . '_' . $d['no_form'] . '.pdf';

        try {
            lampiran_buat($d['kode_rsmpp'], $d['no_form'], $d['nama_form'], $lampiran);
            $hasil = pdf_gabung([
                ['nama' => 'surat', 'path' => $surat['path']],
                ['nama' => 'lampiran1', 'path' => $lampiran],
                ['nama' => 'template', 'path' => $tpl['path']],
                ['nama' => 'halaman_akhir', 'path' => $akhir],
            ], $tujuan);
        } catch (Throwable $ex) {
            $masalah[] = $d['no_form'] . ': ' . $ex->getMessage();
            @unlink($lampiran);
            continue;
        }
        @unlink($lampiran);

        $jumlah++;
        if ($batchId !== null) {
            $ins = db()->prepare('INSERT INTO hasil (batch_id, dokter_id, no_form, nama_form, filename, path, halaman, ukuran)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$batchId, $dokterId, $d['no_form'], $d['nama_form'], $namaFile, $tujuan,
                $hasil['total'], (int) filesize($tujuan)]);
        }
    }

    return ['jumlah' => $jumlah, 'dilewati' => $dilewati, 'masalah' => $masalah, 'folder' => $folder];
}
