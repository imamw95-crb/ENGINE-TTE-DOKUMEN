<?php

declare(strict_types=1);

/**
 * Klien SIMRS (CodeIgniter) — login, ambil halaman, parsing tabel.
 *
 * Temuan dari sistem RSMPP:
 *  - form login: POST /index.php/home/do_login, field ipserver, username, password
 *  - tanpa CSRF token
 *  - respons sukses/gagal memakai header "Refresh: 0;url=..."
 *  - cookie `rsmpp_session` menyimpan ip_address + user_agent, jadi UA & IP harus konsisten
 */

function simrs_jar_path(array $akun): string
{
    $nama = 'simrs_' . (int) $akun['id'] . '_' . substr(hash('sha256', (string) $akun['username']), 0, 8) . '.txt';
    return cfg('tmp_dir') . DIRECTORY_SEPARATOR . $nama;
}

function simrs_http(string $url, array $opsi = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => cfg('simrs_ua'),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    if (!empty($opsi['cookie_jar'])) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $opsi['cookie_jar']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $opsi['cookie_jar']);
    }
    if (($opsi['metode'] ?? 'GET') === 'POST') {
        $data = $opsi['data'] ?? [];
        curl_setopt($ch, CURLOPT_POST, true);
        // kirim sebagai application/x-www-form-urlencoded (jangan multipart)
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : (string) $data);
    }
    if (!empty($opsi['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $opsi['headers']);
    }

    $mentah = curl_exec($ch);
    $errno = curl_errno($ch);
    $pesan = curl_error($ch);
    $kode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ukuranHeader = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($mentah === false || $errno !== 0) {
        return ['ok' => false, 'kode' => 0, 'header' => '', 'body' => '', 'pesan' => "Gagal konek: {$pesan}"];
    }

    return [
        'ok' => $kode >= 200 && $kode < 400,
        'kode' => $kode,
        'header' => substr($mentah, 0, $ukuranHeader),
        'body' => substr($mentah, $ukuranHeader),
        'pesan' => '',
    ];
}

/** Login ke SIMRS; mengembalikan status + pesan. */
function simrs_login(array $akun): array
{
    $jar = simrs_jar_path($akun);
    @unlink($jar);

    $base = rtrim((string) ($akun['base_url'] ?? cfg('simrs_base')), '/');
    simrs_http($base . '/index.php/home', ['cookie_jar' => $jar]);

    $res = simrs_http($base . '/index.php/home/do_login', [
        'metode' => 'POST',
        'cookie_jar' => $jar,
        'data' => [
            'ipserver' => (string) ($akun['ipserver'] ?? ''),
            'username' => (string) $akun['username'],
            'password' => dekripsi($akun['password_enc'] ?? ''),
        ],
    ]);

    if (!$res['ok']) {
        return ['ok' => false, 'pesan' => 'Server SIMRS tidak merespons dengan benar (HTTP ' . $res['kode'] . ')'];
    }

    $isiCookie = '';
    foreach (preg_split('/\r?\n/', $res['header']) ?: [] as $baris) {
        if (stripos($baris, 'set-cookie:') === 0) {
            $isiCookie .= urldecode($baris);
        }
    }

    if (stripos($isiCookie, 'login_error') !== false) {
        return ['ok' => false, 'pesan' => 'Login SIMRS gagal: username/password/ipserver tidak cocok'];
    }
    if (stripos($isiCookie, 'user_data') !== false && preg_match('/user_data";s:\d+:"([^"]*)"/', $isiCookie, $m)
        && trim($m[1]) !== '') {
        return ['ok' => true, 'pesan' => 'Login berhasil sebagai ' . ($m[1] ?: $akun['username'])];
    }

    return ['ok' => true, 'pesan' => 'Login diproses (cookie diterima)'];
}

function simrs_sesi_aktif(array $akun): bool
{
    return is_file(simrs_jar_path($akun)) && (time() - (int) filemtime(simrs_jar_path($akun))) < 6900;
}

/** Pastikan login aktif; mengembalikan [ok, pesan]. */
function simrs_siap(array $akun): array
{
    if (simrs_sesi_aktif($akun)) {
        return ['ok' => true, 'pesan' => ''];
    }
    return simrs_login($akun);
}

/** Ambil halaman / endpoint (GET atau POST) memakai sesi SIMRS. */
function simrs_ambil_mentah(array $akun, string $url, string $metode = 'GET', array $data = []): array
{
    $siap = simrs_siap($akun);
    if (!$siap['ok']) {
        return $siap + ['body' => '', 'url' => $url, 'header' => ''];
    }

    $jar = simrs_jar_path($akun);
    $res = simrs_http($url, ['cookie_jar' => $jar, 'metode' => $metode, 'data' => $data,
        'headers' => ['X-Requested-With: XMLHttpRequest']]);

    // sesi habis -> login ulang sekali
    if (stripos($res['body'], 'name="password"') !== false && stripos($res['body'], 'Sign In') !== false) {
        $login = simrs_login($akun);
        if (!$login['ok']) {
            return $login + ['body' => '', 'url' => $url, 'header' => ''];
        }
        $res = simrs_http($url, ['cookie_jar' => $jar, 'metode' => $metode, 'data' => $data,
            'headers' => ['X-Requested-With: XMLHttpRequest']]);
    }

    $res['url'] = $url;
    return $res;
}

/** Ambil halaman HTML (dipakai "Uji Sumber" mode HTML). */
function simrs_get(array $akun, string $url): array
{
    return simrs_ambil_mentah($akun, $url, 'GET');
}

/** Ambil semua tabel HTML dari sebuah halaman. */
function simrs_parse_tabel(string $html): array
{
    if (trim($html) === '') {
        return [];
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $xp = new DOMXPath($dom);
    $hasil = [];
    $index = 0;
    foreach ($xp->query('//table') as $tabel) {
        /** @var DOMElement $tabel */
        $baris = [];
        foreach ($xp->query('.//tr', $tabel) as $tr) {
            $sel = [];
            foreach ($xp->query('./th|./td', $tr) as $td) {
                /** @var DOMElement $td */
                $teks = trim(preg_replace('/\s+/u', ' ', $td->textContent) ?? '');
                $sel[] = $teks;
            }
            if ($sel !== []) {
                $baris[] = $sel;
            }
        }
        if (count($baris) < 2) {
            continue;
        }
        $header = $baris[0];
        $isi = array_slice($baris, 1);
        if (count($header) < 2 && count($isi[0] ?? []) < 2) {
            continue;
        }
        $hasil[] = ['index' => $index, 'headers' => $header, 'rows' => $isi, 'jumlah' => count($isi)];
        $index++;
    }
    return $hasil;
}

/**
 * Terapkan pemetaan kolom ke tabel hasil parsing.
 * pemetaan: {tabel:int, kolom:{no_form:int, kode:int, nama:int, karyawan:int}, mulai_baris:int}
 */
function simrs_petakan(array $tabel, array $pemetaan): array
{
    $idx = (int) ($pemetaan['tabel'] ?? 0);
    if (!isset($tabel[$idx])) {
        return [];
    }
    $kolom = $pemetaan['kolom'] ?? [];
    $mulai = (int) ($pemetaan['mulai_baris'] ?? 0);
    $baris = array_slice($tabel[$idx]['rows'], $mulai);

    $hasil = [];
    foreach ($baris as $r) {
        $ambil = static fn ($kunci) => isset($kolom[$kunci]) && $kolom[$kunci] !== '' && isset($r[(int) $kolom[$kunci]])
            ? trim((string) $r[(int) $kolom[$kunci]]) : '';
        $noForm = $ambil('no_form');
        $kode = $ambil('kode');
        $nama = $ambil('nama');
        if ($noForm === '' && $kode === '' && $nama === '') {
            continue;
        }
        $hasil[] = [
            'no_form' => $noForm,
            'kode_rsmpp' => $kode,
            'nama_form' => $nama,
            'karyawan' => $ambil('karyawan'),
            'mentah' => json_encode($r, JSON_UNESCAPED_UNICODE),
        ];
    }
    return $hasil;
}

/** Pisahkan "FN24120001 - NAMA FORM" menjadi [kode, nama]. */
function pecah_noform(string $teks): array
{
    $teks = trim($teks);
    if (preg_match('/^(FN\d+)\s*[-–]\s*(.+)$/u', $teks, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    return [$teks, ''];
}

/**
 * Ambil data dari sumber SIMRS (mendukung endpoint DataTables JSON & tabel HTML).
 *
 * @return array{ok:bool, pesan:string, jenis:string, tabel:array}
 */
function simrs_sumber_data(array $akun, array $sumber, int $maksBaris = 5000): array
{
    $urlData = trim((string) ($sumber['url_data'] ?? ''));

    if ($urlData === '') {
        $res = simrs_ambil_mentah($akun, (string) $sumber['url']);
        if (!$res['ok']) {
            return ['ok' => false, 'pesan' => $res['pesan'] ?: 'Gagal mengambil halaman', 'jenis' => 'html', 'tabel' => []];
        }
        return ['ok' => true, 'pesan' => '', 'jenis' => 'html', 'tabel' => simrs_parse_tabel($res['body'])];
    }

    $params = json_decode((string) ($sumber['params'] ?? ''), true);
    if (!is_array($params)) {
        $params = [];
    }
    $metode = strtoupper((string) ($sumber['metode'] ?? 'POST')) === 'GET' ? 'GET' : 'POST';
    $semua = [];
    $total = null;
    $headers = [];
    $mulai = 0;
    for ($putaran = 0; $putaran < 20; $putaran++) {
        $kirim = array_merge([
            'sEcho' => $putaran + 1,
            'iDisplayStart' => $mulai,
            'iDisplayLength' => $maksBaris,
            'sSearch' => '',
            'iSortingCols' => 1,
            'iSortCol_0' => 0,
            'sSortDir_0' => 'asc',
        ], $params);

        $url = $metode === 'GET' ? $urlData . (str_contains($urlData, '?') ? '&' : '?') . http_build_query($kirim) : $urlData;
        $res = simrs_ambil_mentah($akun, $url, $metode, $metode === 'POST' ? $kirim : []);

        if (!$res['ok']) {
            return ['ok' => false, 'pesan' => $res['pesan'] ?: 'Gagal mengambil data', 'jenis' => 'json', 'tabel' => []];
        }

        $json = json_decode($res['body'], true);
        if (!is_array($json)) {
            $cuplik = trim(substr(strip_tags($res['body']), 0, 160));
            return ['ok' => false, 'pesan' => 'Respons bukan JSON: ' . $cuplik, 'jenis' => 'json', 'tabel' => []];
        }

        $baris = $json['aaData'] ?? $json['data'] ?? (array_is_list($json) ? $json : []);
        if (!is_array($baris) || $baris === []) {
            break;
        }
        if ($headers === []) {
            if (!empty($json['sColumns'])) {
                $headers = array_values(array_filter(array_map('trim', explode(',', (string) $json['sColumns'])), static fn ($x) => $x !== ''));
            } elseif (is_array($baris[0]) && !array_is_list($baris[0])) {
                $headers = array_keys($baris[0]);
                $baris = array_map(static fn ($r) => array_values($r), $baris);
            } else {
                $headers = array_map(static fn ($i) => 'Kolom ' . ($i + 1), array_keys((array) $baris[0]));
            }
        }

        $semua = array_merge($semua, array_map(static fn ($r) => is_array($r) ? array_map(
            static fn ($c) => is_scalar($c) ? trim((string) $c) : '', $r) : [trim((string) $r)], $baris));

        $total = (int) ($json['iTotalRecords'] ?? $json['recordsTotal'] ?? count($semua));
        $mulai += count($baris);
        if (count($semua) >= $total || count($baris) < $maksBaris) {
            break;
        }
    }

    if ($semua === []) {
        return ['ok' => false, 'pesan' => 'Endpoint tidak mengembalikan baris (periksa parameter, mis. aktif=1)',
            'jenis' => 'json', 'tabel' => []];
    }

    return [
        'ok' => true,
        'pesan' => count($semua) . ' baris dari ' . ($total ?? count($semua)) . ' total',
        'jenis' => 'json',
        'tabel' => [['index' => 0, 'headers' => $headers, 'rows' => $semua, 'jumlah' => count($semua)]],
    ];
}
