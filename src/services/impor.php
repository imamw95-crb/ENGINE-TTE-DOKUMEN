<?php

declare(strict_types=1);

/** Impor daftar dokumen dari CSV/TSV (header: kode, no_form, nama_form). */

function deteksi_pemisah(string $baris): string
{
    foreach (["\t" => substr_count($baris, "\t"), ';' => substr_count($baris, ';'), ',' => substr_count($baris, ',')] as $pemisah => $n) {
        if ($n > 0) {
            return $pemisah;
        }
    }
    return ',';
}

function impor_dokumen(string $isi, int $dokterId): array
{
    $isi = str_replace(["\r\n", "\r"], "\n", trim($isi));
    if ($isi === '') {
        return ['ok' => false, 'pesan' => 'Data kosong'];
    }

    $baris = array_values(array_filter(array_map('trim', explode("\n", $isi)), static fn ($b) => $b !== ''));
    $pemisah = deteksi_pemisah($baris[0]);

    $pecah = static fn (string $b): array => array_map('trim', str_getcsv($b, $pemisah));

    // cari baris header
    $idxHeader = null;
    $kolom = ['kode' => null, 'no_form' => null, 'nama' => null];
    foreach ($baris as $i => $b) {
        $sel = array_map(static fn ($s) => normal_form($s), $pecah($b));
        foreach ($sel as $j => $teks) {
            if ($kolom['no_form'] === null && (str_contains($teks, 'NO_FORM') || str_contains($teks, 'NO FORM')
                    || str_contains($teks, '(FN)') || $teks === 'FN')) {
                $kolom['no_form'] = $j;
            } elseif ($kolom['nama'] === null && (str_contains($teks, 'NAMA_FORM') || str_contains($teks, 'NAMA FORM')
                    || str_contains($teks, 'JUDUL'))) {
                $kolom['nama'] = $j;
            } elseif ($kolom['kode'] === null && ($teks === 'KODE' || str_contains($teks, 'KODE RSMPP')
                    || str_contains($teks, 'NOMOR DOKUMEN'))) {
                $kolom['kode'] = $j;
            }
        }
        if ($kolom['nama'] !== null && $kolom['kode'] !== null) {
            $idxHeader = $i;
            break;
        }
        $kolom = ['kode' => null, 'no_form' => null, 'nama' => null];
    }

    $mulai = $idxHeader === null ? 0 : $idxHeader + 1;
    if ($idxHeader === null) {
        // tanpa header: asumsi urutan kode, no_form, nama_form
        $kolom = ['kode' => 0, 'no_form' => 1, 'nama' => 2];
    }

    $baru = 0;
    $ubah = 0;
    $dilewati = 0;

    $cariForm = db()->prepare('SELECT id FROM form_master WHERE no_form = ?');
    $insForm = db()->prepare('INSERT INTO form_master (no_form, nama_form) VALUES (?, ?)');
    $updForm = db()->prepare('UPDATE form_master SET nama_form = ? WHERE id = ?');
    $cariDok = db()->prepare('SELECT id FROM dokumen WHERE dokter_id = ? AND no_form = ?');
    $insDok = db()->prepare("INSERT INTO dokumen (dokter_id, no_form, kode_rsmpp, nama_form, sumber) VALUES (?, ?, ?, ?, 'impor')");
    $updDok = db()->prepare('UPDATE dokumen SET kode_rsmpp = ?, nama_form = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');

    for ($i = $mulai; $i < count($baris); $i++) {
        $sel = $pecah($baris[$i]);
        $kode = trim((string) ($sel[$kolom['kode']] ?? ''));
        $noForm = trim((string) ($sel[$kolom['no_form']] ?? ''));
        $nama = trim((string) ($sel[$kolom['nama']] ?? ''));

        if ($noForm === '' && $nama === '') {
            $dilewati++;
            continue;
        }

        $cariForm->execute([$noForm]);
        $formId = $cariForm->fetchColumn();
        if ($formId === false) {
            $insForm->execute([$noForm, $nama]);
            $formId = (int) db()->lastInsertId();
        } elseif ($nama !== '') {
            $updForm->execute([$nama, $formId]);
        }

        $cariDok->execute([$dokterId, $noForm]);
        $dokId = $cariDok->fetchColumn();
        if ($dokId === false) {
            $insDok->execute([$dokterId, $noForm, $kode, $nama]);
            $baru++;
        } else {
            $updDok->execute([$kode, $nama, $dokId]);
            $ubah++;
        }
    }

    return ['ok' => true, 'baru' => $baru, 'ubah' => $ubah, 'dilewati' => $dilewati];
}
