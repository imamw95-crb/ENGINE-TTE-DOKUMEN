<?php

declare(strict_types=1);

/* ============================== bantuan ============================== */

function simpan_upload_pdf(array $file, string $folder, string $nama): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal (kode ' . ($file['error'] ?? '?') . ')');
    }
    if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        throw new RuntimeException('Hanya file PDF yang diterima: ' . $file['name']);
    }
    $info = @finfo_open(FILEINFO_MIME_TYPE);
    $mime = $info ? (string) finfo_file($info, $file['tmp_name']) : '';
    if ($info) {
        finfo_close($info);
    }
    if ($mime !== '' && !str_contains($mime, 'pdf')) {
        throw new RuntimeException('Isi file bukan PDF valid: ' . $file['name']);
    }
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }
    $tujuan = $folder . DIRECTORY_SEPARATOR . nama_aman($nama);
    if (is_file($tujuan)) {
        $tujuan = $folder . DIRECTORY_SEPARATOR . pathinfo(nama_aman($nama), PATHINFO_FILENAME)
            . '_' . date('YmdHis') . '.pdf';
    }
    if (!move_uploaded_file($file['tmp_name'], $tujuan)) {
        throw new RuntimeException('Gagal menyimpan file: ' . $file['name']);
    }
    return $tujuan;
}

function dokter_upsert(string $nomor, string $nama): int
{
    $stmt = db()->prepare('SELECT id FROM dokter WHERE nomor = ?');
    $stmt->execute([$nomor]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        $ins = db()->prepare('INSERT INTO dokter (nomor, nama) VALUES (?, ?)');
        $ins->execute([$nomor, $nama]);
        return (int) db()->lastInsertId();
    }
    $upd = db()->prepare('UPDATE dokter SET nama = ? WHERE id = ?');
    $upd->execute([$nama, $id]);
    return (int) $id;
}

/* ============================== auth ============================== */

function ctrl_login(): void
{
    if (user_aktif() !== null) {
        redirect('dashboard');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT * FROM users WHERE lower(username) = lower(?) AND aktif = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['pass_hash'])) {
            flash('Username atau password salah.', 'error');
            view('login', ['username' => $username]);
            return;
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        db()->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([$user['id']]);
        redirect('dashboard');
    }
    view('login', ['username' => '']);
}

function ctrl_logout(): void
{
    $_SESSION = [];
    session_destroy();
    redirect('login');
}

/* ============================== dashboard ============================== */

function ctrl_dashboard(): void
{
    $pdo = db();
    $angka = [
        'dokter' => (int) $pdo->query('SELECT COUNT(*) FROM dokter')->fetchColumn(),
        'surat' => (int) $pdo->query('SELECT COUNT(*) FROM surat')->fetchColumn(),
        'form' => (int) $pdo->query('SELECT COUNT(*) FROM form_master')->fetchColumn(),
        'dokumen' => (int) $pdo->query('SELECT COUNT(*) FROM dokumen')->fetchColumn(),
        'template' => (int) $pdo->query('SELECT COUNT(*) FROM template_form WHERE aktif = 1')->fetchColumn(),
        'hasil' => (int) $pdo->query('SELECT COUNT(*) FROM hasil')->fetchColumn(),
    ];
    $batch = $pdo->query('SELECT * FROM batch ORDER BY id DESC LIMIT 8')->fetchAll();
    $master = $pdo->query("SELECT * FROM master_berkas ORDER BY id DESC")->fetchAll();
    view('dashboard', ['angka' => $angka, 'batch' => $batch, 'master' => $master]);
}

/* ============================== surat (step 1) ============================== */

function ctrl_surat(): void
{
    $rows = db()->query('SELECT s.*, d.nama AS dokter, d.nomor AS nomor
                         FROM surat s JOIN dokter d ON d.id = s.dokter_id
                         ORDER BY d.nama')->fetchAll();
    view('surat', ['rows' => $rows]);
}

function ctrl_surat_upload(): void
{
    wajib_tulis();
    $files = $_FILES['berkas'] ?? null;
    if (!$files) {
        flash('Tidak ada file yang diunggah.', 'error');
        redirect('surat');
    }

    $daftar = [];
    if (is_array($files['name'])) {
        foreach ($files['name'] as $i => $nama) {
            $daftar[] = [
                'name' => $nama,
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }
    } else {
        $daftar[] = $files;
    }

    $berhasil = 0;
    $gagal = [];
    foreach ($daftar as $file) {
        try {
            [$nomor, $namaDokter] = parse_nama_surat((string) $file['name']);
            $nomorPost = trim((string) ($_POST['nomor'] ?? ''));
            $namaPost = trim((string) ($_POST['nama'] ?? ''));
            $nomor = $nomorPost !== '' ? $nomorPost : $nomor;
            $namaDokter = $namaPost !== '' ? $namaPost : $namaDokter;
            if ($nomor === '' || $namaDokter === '') {
                throw new RuntimeException('Nomor/nama dokter tidak terbaca dari nama file: ' . $file['name']);
            }

            $tujuan = simpan_upload_pdf($file, cfg('surat_dir'), $nomor . ' ' . $namaDokter . '.pdf');
            $dokterId = dokter_upsert($nomor, $namaDokter);

            $halaman = pdf_halaman($tujuan);
            $stmt = db()->prepare('INSERT INTO surat (dokter_id, filename, path, halaman, sha256) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$dokterId, basename($tujuan), $tujuan, $halaman, sha256_file($tujuan)]);
            $berhasil++;
        } catch (Throwable $ex) {
            $gagal[] = $ex->getMessage();
        }
    }

    flash("{$berhasil} surat diunggah." . ($gagal ? ' Gagal: ' . implode('; ', $gagal) : ''),
        $gagal ? 'error' : 'info');
    redirect('surat');
}

function ctrl_surat_hapus(): void
{
    wajib_tulis();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM surat WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        db()->prepare('DELETE FROM surat WHERE id = ?')->execute([$id]);
        flash('Surat dihapus: ' . $row['filename'], 'info');
    }
    redirect('surat');
}

/* ============================== dokter & dokumen (step 4) ============================== */

function ctrl_dokter(): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $sql = 'SELECT d.*,
              (SELECT COUNT(*) FROM surat s WHERE s.dokter_id = d.id) AS jml_surat,
              (SELECT COUNT(*) FROM dokumen k WHERE k.dokter_id = d.id) AS jml_dokumen
            FROM dokter d';
    $params = [];
    if ($q !== '') {
        $sql .= ' WHERE d.nama LIKE ? OR d.nomor LIKE ?';
        $params = ["%{$q}%", "%{$q}%"];
    }
    $sql .= ' ORDER BY d.nama';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    view('dokter', ['rows' => $stmt->fetchAll(), 'q' => $q]);
}

function ctrl_dokter_detail(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM dokter WHERE id = ?');
    $stmt->execute([$id]);
    $dokter = $stmt->fetch();
    if (!$dokter) {
        http_response_code(404);
        exit('Dokter tidak ditemukan');
    }

    $stmt = db()->prepare('SELECT * FROM surat WHERE dokter_id = ? ORDER BY id DESC');
    $stmt->execute([$id]);
    $surat = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT * FROM dokumen WHERE dokter_id = ? ORDER BY no_form');
    $stmt->execute([$id]);
    $dokumen = $stmt->fetchAll();

    $template = peta_template();

    $stmt = db()->prepare('SELECT * FROM hasil WHERE dokter_id = ? ORDER BY id DESC LIMIT 40');
    $stmt->execute([$id]);
    $hasil = $stmt->fetchAll();

    view('dokter_detail', compact('dokter', 'surat', 'dokumen', 'template', 'hasil'));
}

function ctrl_dokter_simpan(): void
{
    wajib_tulis();
    $id = (int) ($_POST['id'] ?? 0);
    $nomor = trim((string) ($_POST['nomor'] ?? ''));
    $nama = trim((string) ($_POST['nama'] ?? ''));
    if ($nomor === '' || $nama === '') {
        flash('Nomor dan nama dokter wajib diisi.', 'error');
        redirect('dokter');
    }
    if ($id > 0) {
        db()->prepare('UPDATE dokter SET nomor = ?, nama = ? WHERE id = ?')->execute([$nomor, $nama, $id]);
    } else {
        $id = dokter_upsert($nomor, $nama);
    }
    flash('Data dokter disimpan.', 'info');
    redirect('dokter.detail', ['id' => $id]);
}

function ctrl_dokumen_simpan(): void
{
    wajib_tulis();
    $dokterId = (int) ($_POST['dokter_id'] ?? 0);
    $noForm = trim((string) ($_POST['no_form'] ?? ''));
    $kode = trim((string) ($_POST['kode_rsmpp'] ?? ''));
    $nama = trim((string) ($_POST['nama_form'] ?? ''));
    if ($dokterId <= 0 || $noForm === '') {
        flash('Dokter dan No Form wajib diisi.', 'error');
        redirect('dokter.detail', ['id' => $dokterId]);
    }

    $stmt = db()->prepare('SELECT id FROM form_master WHERE no_form = ?');
    $stmt->execute([$noForm]);
    if ($stmt->fetchColumn() === false) {
        db()->prepare('INSERT INTO form_master (no_form, nama_form) VALUES (?, ?)')->execute([$noForm, $nama]);
    } elseif ($nama !== '') {
        db()->prepare('UPDATE form_master SET nama_form = ? WHERE no_form = ?')->execute([$nama, $noForm]);
    }

    db()->prepare("INSERT INTO dokumen (dokter_id, no_form, kode_rsmpp, nama_form, sumber)
                   VALUES (?, ?, ?, ?, 'manual')
                   ON CONFLICT(dokter_id, no_form) DO UPDATE SET
                     kode_rsmpp = excluded.kode_rsmpp,
                     nama_form = excluded.nama_form,
                     updated_at = CURRENT_TIMESTAMP")
        ->execute([$dokterId, $noForm, $kode, $nama]);

    flash("Dokumen {$noForm} disimpan.", 'info');
    redirect('dokter.detail', ['id' => $dokterId]);
}

function ctrl_dokumen_hapus(): void
{
    wajib_tulis();
    $id = (int) ($_POST['id'] ?? 0);
    $dokterId = (int) ($_POST['dokter_id'] ?? 0);
    db()->prepare('DELETE FROM dokumen WHERE id = ?')->execute([$id]);
    redirect('dokter.detail', ['id' => $dokterId]);
}

function ctrl_dokumen_impor(): void
{
    wajib_tulis();
    $dokterId = (int) ($_POST['dokter_id'] ?? 0);
    $isi = (string) ($_POST['data'] ?? '');

    if (isset($_FILES['csv']) && ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $isi = (string) file_get_contents($_FILES['csv']['tmp_name']);
    }

    if ($dokterId <= 0) {
        flash('Pilih dokter terlebih dahulu.', 'error');
        redirect('dokter');
    }

    $hasil = impor_dokumen($isi, $dokterId);
    if (!$hasil['ok']) {
        flash($hasil['pesan'], 'error');
    } else {
        flash("Impor selesai: {$hasil['baru']} baru, {$hasil['ubah']} diperbarui, {$hasil['dilewati']} dilewati.", 'info');
    }
    redirect('dokter.detail', ['id' => $dokterId]);
}

/* ============================== template (step 5) ============================== */

function ctrl_template(): void
{
    $sql = 'SELECT f.*,
              (SELECT COUNT(*) FROM template_form t WHERE t.form_id = f.id) AS jml_versi,
              (SELECT COUNT(*) FROM dokumen d WHERE d.no_form = f.no_form) AS dipakai
            FROM form_master f ORDER BY f.no_form';
    $forms = db()->query($sql)->fetchAll();

    $versi = [];
    foreach (db()->query('SELECT * FROM template_form ORDER BY form_id, versi DESC') as $row) {
        $versi[$row['form_id']][] = $row;
    }
    view('template', ['forms' => $forms, 'versi' => $versi]);
}

function ctrl_template_upload(): void
{
    wajib_tulis();
    $formId = (int) ($_POST['form_id'] ?? 0);
    if ($formId <= 0 || !isset($_FILES['berkas'])) {
        flash('Form dan file wajib dipilih.', 'error');
        redirect('template');
    }

    try {
        $path = simpan_upload_pdf($_FILES['berkas'], cfg('template_dir'), (string) $_FILES['berkas']['name']);
        $halaman = pdf_halaman($path);
        if ($halaman <= 0) {
            throw new RuntimeException('PDF tidak terbaca / 0 halaman.');
        }
        $versi = (int) db()->query('SELECT COALESCE(MAX(versi), 0) FROM template_form WHERE form_id = ' . $formId)->fetchColumn() + 1;
        db()->prepare('UPDATE template_form SET aktif = 0 WHERE form_id = ?')->execute([$formId]);
        db()->prepare('INSERT INTO template_form (form_id, versi, filename, path, halaman, sha256, aktif)
                       VALUES (?, ?, ?, ?, ?, ?, 1)')
            ->execute([$formId, $versi, basename($path), $path, $halaman, sha256_file($path)]);
        flash("Template versi {$versi} diunggah ({$halaman} halaman).", 'info');
    } catch (Throwable $ex) {
        flash('Gagal: ' . $ex->getMessage(), 'error');
    }
    redirect('template');
}

function ctrl_template_aktif(): void
{
    wajib_tulis();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT form_id FROM template_form WHERE id = ?');
    $stmt->execute([$id]);
    $formId = $stmt->fetchColumn();
    if ($formId !== false) {
        db()->prepare('UPDATE template_form SET aktif = 0 WHERE form_id = ?')->execute([$formId]);
        db()->prepare('UPDATE template_form SET aktif = 1 WHERE id = ?')->execute([$id]);
        flash('Versi template diaktifkan.', 'info');
    }
    redirect('template');
}

function ctrl_template_hapus(): void
{
    wajib_tulis();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM template_form WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        db()->prepare('DELETE FROM template_form WHERE id = ?')->execute([$id]);
        @unlink($row['path']);
        flash('Versi template dihapus.', 'info');
    }
    redirect('template');
}

/* ============================== master lampiran ============================== */

function ctrl_master(): void
{
    $rows = db()->query('SELECT * FROM master_berkas ORDER BY id DESC')->fetchAll();
    view('master', ['rows' => $rows]);
}

function ctrl_master_upload(): void
{
    wajib_tulis();
    $jenis = (string) ($_POST['jenis'] ?? '');
    if (!in_array($jenis, ['lampiran1', 'lampiran2'], true) || !isset($_FILES['berkas'])) {
        flash('Jenis master tidak valid.', 'error');
        redirect('master');
    }

    if ($jenis === 'lampiran1') {
        flash('LAMPIRAN 1 tidak bisa diunggah dari sini: PDF-nya harus dibersihkan dari teks placeholder '
            . '(PHP tidak bisa menghapus teks di dalam PDF). Ganti berkas sumber lalu jalankan '
            . 'python web/bin/siapkan_master.py.', 'error');
        redirect('master');
    }

    try {
        $path = simpan_upload_pdf($_FILES['berkas'], cfg('master_dir'), basename((string) $_FILES['berkas']['name']));
        $halaman = pdf_halaman($path);
        if ($halaman <= 0) {
            throw new RuntimeException('PDF tidak terbaca / 0 halaman.');
        }
        db()->prepare('UPDATE master_berkas SET aktif = 0 WHERE jenis = ?')->execute([$jenis]);
        db()->prepare('INSERT INTO master_berkas (jenis, filename, path, halaman, sha256, aktif)
                       VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([$jenis, basename($path), $path, $halaman, sha256_file($path)]);
        flash("Master {$jenis} diperbarui ({$halaman} halaman).", 'info');
    } catch (Throwable $ex) {
        flash('Gagal: ' . $ex->getMessage(), 'error');
    }
    redirect('master');
}

/* ============================== SIMRS ============================== */

function ctrl_simrs(): void
{
    $akun = db()->query('SELECT * FROM simrs_akun ORDER BY id')->fetchAll();
    $sumber = db()->query('SELECT * FROM simrs_sumber ORDER BY id')->fetchAll();
    $staging = db()->query('SELECT * FROM simrs_staging ORDER BY id DESC LIMIT 200')->fetchAll();
    $jumlahStaging = (int) db()->query('SELECT COUNT(*) FROM simrs_staging')->fetchColumn();

    $dokterStaging = null;
    $ringkas = ['tambah' => 0, 'ubah' => 0, 'sama' => 0, 'contoh_ubah' => []];
    if ($jumlahStaging > 0) {
        $idStaging = (int) db()->query('SELECT COALESCE(MAX(dokter_id), 0) FROM simrs_staging')->fetchColumn();
        if ($idStaging > 0) {
            $stmt = db()->prepare('SELECT * FROM dokter WHERE id = ?');
            $stmt->execute([$idStaging]);
            $dokterStaging = $stmt->fetch() ?: null;
        }

        if ($dokterStaging) {
            $lama = [];
            foreach (db()->query('SELECT no_form, kode_rsmpp FROM dokumen WHERE dokter_id = ' . $idStaging) as $r) {
                $lama[$r['no_form']] = $r['kode_rsmpp'];
            }
            foreach ($staging as $s) {
                $noForm = (string) $s['no_form'];
                if ($noForm === '') {
                    continue;
                }
                if (!isset($lama[$noForm])) {
                    $ringkas['tambah']++;
                } elseif ($lama[$noForm] !== (string) $s['kode_rsmpp']) {
                    $ringkas['ubah']++;
                    if (count($ringkas['contoh_ubah']) < 5) {
                        $ringkas['contoh_ubah'][] = $noForm . ': ' . $lama[$noForm] . ' → ' . $s['kode_rsmpp'];
                    }
                } else {
                    $ringkas['sama']++;
                }
            }
        }
    }

    view('simrs', ['akun' => $akun, 'sumber' => $sumber, 'staging' => $staging,
        'jumlah_staging' => $jumlahStaging, 'dokter_staging' => $dokterStaging,
        'ringkas' => $ringkas, 'base_url' => cfg('simrs_base'),
        'dokter_terpilih' => (int) ($_GET['dokter_id'] ?? 0)]);
}

function ctrl_simrs_akun(): void
{
    wajib_tulis();
    $id = (int) ($_POST['id'] ?? 0);
    $label = trim((string) ($_POST['label'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $ipserver = trim((string) ($_POST['ipserver'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($label === '' || $username === '') {
        flash('Label dan username wajib diisi.', 'error');
        redirect('simrs');
    }

    if ($id > 0) {
        db()->prepare('UPDATE simrs_akun SET label = ?, username = ?, ipserver = ? WHERE id = ?')
            ->execute([$label, $username, $ipserver, $id]);
        if ($password !== '') {
            db()->prepare('UPDATE simrs_akun SET password_enc = ? WHERE id = ?')
                ->execute([enkripsi($password), $id]);
        }
    } else {
        db()->prepare('INSERT INTO simrs_akun (label, username, ipserver, password_enc) VALUES (?, ?, ?, ?)')
            ->execute([$label, $username, $ipserver, enkripsi($password)]);
    }
    flash('Akun SIMRS disimpan.', 'info');
    redirect('simrs');
}

function ctrl_simrs_sumber(): void
{
    wajib_tulis();
    $id = (int) ($_POST['id'] ?? 0);
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $url = trim((string) ($_POST['url'] ?? ''));
    $urlData = trim((string) ($_POST['url_data'] ?? ''));
    $jenis = (string) ($_POST['jenis'] ?? 'per_dokter');
    $metode = strtoupper((string) ($_POST['metode'] ?? 'POST')) === 'GET' ? 'GET' : 'POST';
    $params = trim((string) ($_POST['params'] ?? ''));
    $pemetaan = trim((string) ($_POST['pemetaan'] ?? ''));

    if ($params !== '') {
        $cek = json_decode($params, true);
        if (!is_array($cek)) {
            flash('Params harus JSON valid, mis. {"aktif":"1"}', 'error');
            redirect('simrs');
        }
        $params = json_encode($cek, JSON_UNESCAPED_UNICODE);
    }

    if ($pemetaan !== '') {
        $cek = json_decode($pemetaan, true);
        if (!is_array($cek)) {
            flash('Pemetaan harus JSON valid (atau kosongkan).', 'error');
            redirect('simrs');
        }
        $pemetaan = json_encode($cek, JSON_UNESCAPED_UNICODE);
    }

    if ($nama === '' || ($url === '' && $urlData === '')) {
        flash('Nama dan minimal satu URL wajib diisi.', 'error');
        redirect('simrs');
    }

    if ($id > 0) {
        db()->prepare('UPDATE simrs_sumber SET nama = ?, url = ?, url_data = ?, jenis = ?, metode = ?, params = ?, pemetaan = ? WHERE id = ?')
            ->execute([$nama, $url, $urlData, $jenis, $metode, $params, $pemetaan ?: null, $id]);
        flash('Sumber data diperbarui.', 'info');
    } else {
        db()->prepare('INSERT INTO simrs_sumber (nama, url, url_data, jenis, metode, params, pemetaan) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$nama, $url, $urlData, $jenis, $metode, $params, $pemetaan ?: null]);
        flash('Sumber data disimpan.', 'info');
    }
    redirect('simrs');
}

function ctrl_simrs_akun_hapus(): void
{
    wajib_tulis();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM simrs_akun WHERE id = ?')->execute([$id]);
        @unlink(cfg('tmp_dir') . DIRECTORY_SEPARATOR . 'simrs_' . $id . '_'
            . substr(hash('sha256', (string) ($_POST['username'] ?? '')), 0, 8) . '.txt');
        flash('Akun SIMRS dihapus.', 'info');
    }
    redirect('simrs');
}

function ctrl_simrs_sumber_hapus(): void
{
    wajib_tulis();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM simrs_staging WHERE sumber_id = ?')->execute([$id]);
        db()->prepare('DELETE FROM simrs_sumber WHERE id = ?')->execute([$id]);
        flash('Sumber data dihapus.', 'info');
    }
    redirect('simrs');
}

function ctrl_simrs_uji_login(): void
{
    wajib_tulis();
    $akunId = (int) ($_POST['akun_id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM simrs_akun WHERE id = ?');
    $stmt->execute([$akunId]);
    $akun = $stmt->fetch();
    if (!$akun) {
        json_keluar(['ok' => false, 'pesan' => 'Akun tidak ditemukan'], 422);
    }
    if (empty($akun['password_enc'])) {
        json_keluar(['ok' => false, 'pesan' => 'Password belum diisi untuk akun ini'], 422);
    }
    $hasil = simrs_login($akun);
    json_keluar(['ok' => $hasil['ok'], 'pesan' => $hasil['pesan']], $hasil['ok'] ? 200 : 401);
}

function ctrl_simrs_uji(): void
{
    wajib_tulis();
    $akunId = (int) ($_POST['akun_id'] ?? 0);
    $sumberId = (int) ($_POST['sumber_id'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM simrs_akun WHERE id = ?');
    $stmt->execute([$akunId]);
    $akun = $stmt->fetch();
    $stmt = db()->prepare('SELECT * FROM simrs_sumber WHERE id = ?');
    $stmt->execute([$sumberId]);
    $sumber = $stmt->fetch();

    if (!$akun) {
        json_keluar(['ok' => false, 'pesan' => 'Akun belum dipilih'], 422);
    }
    if (!$sumber) {
        json_keluar(['ok' => false, 'pesan' => 'Sumber belum dipilih'], 422);
    }
    if (!empty($_POST['url_data'])) {
        $sumber['url_data'] = trim((string) $_POST['url_data']);
    }
    if (!empty($_POST['url'])) {
        $sumber['url'] = trim((string) $_POST['url']);
    }
    if (!empty($_POST['params'])) {
        $sumber['params'] = (string) $_POST['params'];
    }

    $hasil = simrs_sumber_data($akun, $sumber);
    if (!$hasil['ok']) {
        json_keluar(['ok' => false, 'pesan' => $hasil['pesan']], 502);
    }

    $ringkas = array_map(static fn ($t) => [
        'index' => $t['index'],
        'headers' => $t['headers'],
        'jumlah' => $t['jumlah'],
        'contoh' => array_slice($t['rows'], 0, 3),
    ], $hasil['tabel']);

    json_keluar([
        'ok' => true,
        'pesan' => $hasil['pesan'] !== '' ? $hasil['pesan'] : count($ringkas) . ' tabel terdeteksi',
        'jenis' => $hasil['jenis'],
        'tabel' => $ringkas,
    ]);
}

function ctrl_simrs_ambil(): void
{
    wajib_tulis();
    $akunId = (int) ($_POST['akun_id'] ?? 0);
    $sumberId = (int) ($_POST['sumber_id'] ?? 0);
    $dokterId = (int) ($_POST['dokter_id'] ?? 0);
    $pemetaan = json_decode((string) ($_POST['pemetaan'] ?? ''), true);

    $stmt = db()->prepare('SELECT * FROM simrs_akun WHERE id = ?');
    $stmt->execute([$akunId]);
    $akun = $stmt->fetch();
    $stmt = db()->prepare('SELECT * FROM simrs_sumber WHERE id = ?');
    $stmt->execute([$sumberId]);
    $sumber = $stmt->fetch();
    $stmt = db()->prepare('SELECT * FROM dokter WHERE id = ?');
    $stmt->execute([$dokterId]);
    $dokter = $stmt->fetch();

    if (!$akun || !$sumber) {
        json_keluar(['ok' => false, 'pesan' => 'Akun/sumber belum lengkap'], 422);
    }
    if (!$dokter) {
        json_keluar(['ok' => false, 'pesan' => 'Pilih dokter dulu — data diambil per dokter, bukan semua'], 422);
    }
    if (!is_array($pemetaan) || !isset($pemetaan['tabel'])) {
        json_keluar(['ok' => false, 'pesan' => 'Pemetaan kolom belum diatur'], 422);
    }

    $hasil = simrs_sumber_data($akun, $sumber);
    if (!$hasil['ok']) {
        json_keluar(['ok' => false, 'pesan' => $hasil['pesan']], 502);
    }

    $baris = simrs_petakan($hasil['tabel'], $pemetaan);
    if (!$baris) {
        json_keluar(['ok' => false, 'pesan' => 'Tidak ada baris yang cocok dengan pemetaan'], 422);
    }

    $pecah = !empty($pemetaan['pecah_noform']);
    $total = 0;
    $ganda = 0;
    $barisDokter = [];

    foreach ($baris as $b) {
        $karyawan = trim((string) ($b['karyawan'] ?? ''));
        if ($karyawan !== '' && !staging_milik_dokter($b, $dokter['nama'])) {
            continue;
        }
        $barisDokter[] = $b;
    }

    if (!$barisDokter) {
        json_keluar(['ok' => false, 'pesan' => 'Tidak ada baris untuk ' . $dokter['nama']
            . ' di sumber ini. Cek ejaan nama dokter di SIMRS.'], 422);
    }

    // kolom tanggal untuk memilih baris terbaru bila ada duplikat no_form
    $idxTanggal = isset($pemetaan['kolom']['tanggal']) && $pemetaan['kolom']['tanggal'] !== ''
        ? (int) $pemetaan['kolom']['tanggal'] : null;
    if ($idxTanggal === null) {
        foreach (($hasil['tabel'][0]['headers'] ?? []) as $i => $h) {
            $hu = strtoupper((string) $h);
            if (str_contains($hu, 'LASTCHANGE') || str_contains($hu, 'UPDATED') || str_contains($hu, 'TANGGAL')) {
                $idxTanggal = $i;
                break;
            }
        }
    }

    // gabungkan duplikat: SIMRS bisa menyimpan kode lama & baru untuk form yang sama
    $unik = [];
    foreach ($barisDokter as $b) {
        $noForm = (string) $b['no_form'];
        $nama = (string) $b['nama_form'];
        if ($pecah) {
            [$kodeForm, $namaDari] = pecah_noform($noForm);
            $noForm = $kodeForm;
            if ($nama === '') {
                $nama = $namaDari;
            }
        }
        if ($noForm === '' && $nama === '') {
            continue;
        }
        $b['_no_form'] = $noForm;
        $b['_nama'] = $nama;
        $b['_tanggal'] = $idxTanggal !== null ? (string) ($b['mentah'] ?? '') : '';

        if (isset($unik[$noForm])) {
            $ganda++;
            $tanggalBaru = $idxTanggal !== null
                ? trim((string) (json_decode((string) $b['mentah'], true)[$idxTanggal] ?? '')) : '';
            $tanggalLama = $idxTanggal !== null
                ? trim((string) (json_decode((string) $unik[$noForm]['mentah'], true)[$idxTanggal] ?? '')) : '';
            if ($tanggalBaru === '' || $tanggalBaru <= $tanggalLama) {
                continue;
            }
        }
        $unik[$noForm] = $b;
    }

    db()->prepare('DELETE FROM simrs_staging WHERE sumber_id = ?')->execute([$sumberId]);
    $ins = db()->prepare('INSERT INTO simrs_staging (sumber_id, dokter_id, karyawan, no_form, kode_rsmpp, nama_form, mentah)
                          VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($unik as $noForm => $b) {
        $ins->execute([$sumberId, (int) $dokter['id'], $b['karyawan'], $noForm, $b['kode_rsmpp'], $b['_nama'], $b['mentah']]);
        $total++;
    }
    db()->prepare('UPDATE simrs_sumber SET pemetaan = ? WHERE id = ?')
        ->execute([json_encode($pemetaan, JSON_UNESCAPED_UNICODE), $sumberId]);

    $pesan = $total . ' baris untuk ' . $dokter['nama'] . ' masuk staging';
    if ($ganda > 0) {
        $pesan .= ' (' . $ganda . ' baris duplikat digabung, dipakai yang terbaru)';
    }
    json_keluar([
        'ok' => true,
        'pesan' => $pesan,
        'jumlah' => $total,
        'dokter' => $dokter['nama'],
        'contoh' => array_slice(array_values($unik), 0, 5),
    ]);
}

/** Apakah baris staging milik dokter ini (dicocokkan lewat kolom karyawan)? */
function staging_milik_dokter(array $baris, string $namaDokter): bool
{
    $k = strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($baris['karyawan'] ?? '')) ?? ''));
    if ($k === '') {
        return true;
    }
    $n = strtolower(trim(preg_replace('/\s+/u', ' ', $namaDokter) ?? ''));
    return $k === $n || str_starts_with($n, $k) || str_starts_with($k, $n);
}

function ctrl_simrs_terapkan(): void
{
    wajib_tulis();
    $dokterId = (int) ($_POST['dokter_id'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM dokter WHERE id = ?');
    $stmt->execute([$dokterId]);
    $dokter = $stmt->fetch();
    if (!$dokter) {
        flash('Dokter tidak ditemukan.', 'error');
        redirect('simrs');
    }

    $rows = db()->query('SELECT * FROM simrs_staging ORDER BY id')->fetchAll();
    $milik = array_values(array_filter($rows, static function (array $r) use ($dokter, $dokterId): bool {
        $idBaris = (int) ($r['dokter_id'] ?? 0);
        if ($idBaris > 0) {
            return $idBaris === $dokterId;
        }
        return staging_milik_dokter($r, $dokter['nama']);
    }));

    if (!$milik) {
        flash('Staging tidak berisi data untuk ' . $dokter['nama'] . '.', 'error');
        redirect('simrs');
    }

    // ringkasan dampak sebelum menulis
    $lama = [];
    foreach (db()->query('SELECT no_form, kode_rsmpp FROM dokumen WHERE dokter_id = ' . $dokterId) as $r) {
        $lama[$r['no_form']] = $r['kode_rsmpp'];
    }
    $tambah = 0;
    $ubah = 0;
    $sama = 0;
    foreach ($milik as $r) {
        $noForm = trim((string) $r['no_form']);
        if ($noForm === '') {
            continue;
        }
        if (!isset($lama[$noForm])) {
            $tambah++;
        } elseif ($lama[$noForm] !== (string) $r['kode_rsmpp']) {
            $ubah++;
        } else {
            $sama++;
        }
    }

    $upd = db()->prepare("INSERT INTO dokumen (dokter_id, no_form, kode_rsmpp, nama_form, sumber)
                          VALUES (?, ?, ?, ?, 'simrs')
                          ON CONFLICT(dokter_id, no_form) DO UPDATE SET
                            kode_rsmpp = excluded.kode_rsmpp,
                            nama_form = excluded.nama_form,
                            updated_at = CURRENT_TIMESTAMP");
    $timpa = !empty($_POST['timpa']);
    $dilewati = 0;
    foreach ($milik as $r) {
        $noForm = trim((string) $r['no_form']);
        if ($noForm === '') {
            continue;
        }
        if (isset($lama[$noForm]) && !$timpa) {
            $dilewati++;
            continue;
        }
        $upd->execute([$dokterId, $noForm, $r['kode_rsmpp'], $r['nama_form']]);
    }

    $pesan = "Diterapkan ke {$dokter['nama']}: {$tambah} dokumen baru, {$ubah} kode diubah, "
        . "{$sama} sudah sama";
    $pesan .= $dilewati > 0
        ? ", {$dilewati} dokumen lama tidak disentuh (centang \"timpa kode\" bila memang ingin diganti)."
        : '.';
    flash($pesan, ($timpa && $ubah > 0) ? 'error' : 'info');
    redirect('dokter.detail', ['id' => $dokterId]);
}

/** Cari dokter berdasarkan nama (persis, lalu awalan/terkandung). */
function cocok_dokter(string $nama): ?array
{
    $nama = strtolower(trim(preg_replace('/\s+/u', ' ', $nama) ?? ''));
    if ($nama === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM dokter WHERE lower(trim(nama)) = ?');
    $stmt->execute([$nama]);
    if ($d = $stmt->fetch()) {
        return $d;
    }

    $stmt = db()->prepare('SELECT * FROM dokter WHERE lower(nama) LIKE ? ORDER BY length(nama) LIMIT 1');
    $stmt->execute([$nama . '%']);
    if ($d = $stmt->fetch()) {
        return $d;
    }

    $stmt = db()->prepare('SELECT * FROM dokter WHERE ? LIKE lower(nama) || \'%\' ORDER BY length(nama) DESC LIMIT 1');
    $stmt->execute([$nama]);
    return $stmt->fetch() ?: null;
}

function ctrl_simrs_batal(): void
{
    wajib_tulis();
    db()->exec('DELETE FROM simrs_staging');
    flash('Staging dikosongkan.', 'info');
    redirect('simrs');
}

/* ============================== generate & unduh ============================== */

function ctrl_generate(): void
{
    $sql = 'SELECT d.*,
              (SELECT COUNT(*) FROM surat s WHERE s.dokter_id = d.id) AS jml_surat,
              (SELECT COUNT(*) FROM dokumen k WHERE k.dokter_id = d.id) AS jml_dokumen
            FROM dokter d ORDER BY d.nama';
    $dokter = db()->query($sql)->fetchAll();
    $batch = db()->query('SELECT * FROM batch ORDER BY id DESC LIMIT 10')->fetchAll();
    view('generate', ['dokter' => $dokter, 'batch' => $batch]);
}

function ctrl_generate_mulai(): void
{
    wajib_tulis();
    $kode = 'B' . date('YmdHis');
    db()->prepare('INSERT INTO batch (kode, dibuat_oleh) VALUES (?, ?)')
        ->execute([$kode, user_aktif()['id']]);
    json_keluar(['ok' => true, 'batch_id' => (int) db()->lastInsertId(), 'kode' => $kode]);
}

function ctrl_generate_satu(): void
{
    wajib_tulis();
    $dokterId = (int) ($_POST['dokter_id'] ?? 0);
    $batchId = (int) ($_POST['batch_id'] ?? 0) ?: null;
    try {
        $hasil = generate_dokter($dokterId, $batchId);
        json_keluar(['ok' => true] + $hasil);
    } catch (Throwable $ex) {
        json_keluar(['ok' => false, 'pesan' => $ex->getMessage()], 500);
    }
}

function ctrl_generate_selesai(): void
{
    wajib_tulis();
    $batchId = (int) ($_POST['batch_id'] ?? 0);
    $total = (int) db()->query('SELECT COUNT(*) FROM hasil WHERE batch_id = ' . $batchId)->fetchColumn();
    db()->prepare("UPDATE batch SET status = 'selesai', total_file = ?, selesai = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$total, $batchId]);
    json_keluar(['ok' => true, 'total_file' => $total]);
}

function ctrl_unduh(): void
{
    wajib_login();
    $jenis = (string) ($_GET['jenis'] ?? 'file');
    $id = (int) ($_GET['id'] ?? 0);

    if ($jenis === 'file') {
        $stmt = db()->prepare('SELECT * FROM hasil WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || !is_file($row['path'])) {
            http_response_code(404);
            exit('File tidak ditemukan');
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($row['filename']) . '"');
        header('Content-Length: ' . filesize($row['path']));
        readfile($row['path']);
        exit;
    }

    if ($jenis === 'template') {
        $stmt = db()->prepare('SELECT * FROM template_form WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || !is_file($row['path'])) {
            http_response_code(404);
            exit('Template tidak ditemukan');
        }
        $unduh = isset($_GET['unduh']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($unduh ? 'attachment' : 'inline') . '; filename="' . basename($row['filename']) . '"');
        header('Content-Length: ' . filesize($row['path']));
        readfile($row['path']);
        exit;
    }

    $daftar = [];
    $namaZip = 'hasil-tte.zip';

    if ($jenis === 'dokter') {
        $stmt = db()->prepare('SELECT h.*, d.nama AS dokter FROM hasil h JOIN dokter d ON d.id = h.dokter_id WHERE h.dokter_id = ? ORDER BY h.no_form');
        $stmt->execute([$id]);
        $daftar = $stmt->fetchAll();
        $namaZip = 'hasil-' . ($daftar[0]['dokter'] ?? 'dokter') . '.zip';
    } elseif ($jenis === 'batch') {
        $stmt = db()->prepare('SELECT h.*, d.nama AS dokter FROM hasil h JOIN dokter d ON d.id = h.dokter_id WHERE h.batch_id = ? ORDER BY d.nama, h.no_form');
        $stmt->execute([$id]);
        $daftar = $stmt->fetchAll();
        $stmt = db()->prepare('SELECT kode FROM batch WHERE id = ?');
        $stmt->execute([$id]);
        $namaZip = 'batch-' . ((string) $stmt->fetchColumn() ?: $id) . '.zip';
    }

    if (!$daftar) {
        http_response_code(404);
        exit('Belum ada hasil untuk diunduh');
    }

    $zip = new ZipStream\ZipStream(
        outputName: nama_aman($namaZip) ?: 'hasil.zip',
        sendHttpHeaders: true,
        defaultEnableZeroHeader: true,
    );
    foreach ($daftar as $row) {
        if (!is_file($row['path'])) {
            continue;
        }
        $namaFolder = nama_aman($row['dokter'] ?? 'dokter');
        $zip->addFileFromPath($namaFolder . '/' . basename($row['filename']), $row['path']);
    }
    $zip->finish();
    exit;
}
