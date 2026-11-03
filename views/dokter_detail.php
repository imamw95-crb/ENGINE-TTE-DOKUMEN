<?php $judul = $dokter['nama']; require __DIR__ . '/_header.php'; ?>
<h1><?= e($dokter['nama']) ?></h1>
<p class="sub">Nomor <?= e($dokter['nomor']) ?> · <?= count($dokumen) ?> dokumen · <?= count($surat) ?> surat</p>

<div class="baris" style="margin-bottom:16px">
  <div style="flex:0">
    <button class="btn" type="button" onclick="generateSatu(this, <?= (int) $dokter['id'] ?>)">Generate PDF</button>
  </div>
  <div style="flex:0">
    <a class="btn abu" href="<?= e(url('unduh', ['jenis' => 'dokter', 'id' => $dokter['id']])) ?>">Unduh ZIP dokter</a>
  </div>
  <div style="flex:0">
    <a class="btn abu" href="<?= e(url('simrs', ['dokter_id' => $dokter['id']])) ?>"
       title="Ambil kode RSMPP & daftar form dokter ini dari SIMRS">Ambil data dari SIMRS</a>
  </div>
  <div id="status-gen" class="sempit muted"></div>
</div>

<script>
async function generateSatu(tombol, dokterId) {
  tombol.disabled = true;
  const status = document.getElementById('status-gen');
  status.textContent = 'Memproses...';
  const body = new URLSearchParams({csrf: '<?= e(csrf()) ?>', dokter_id: dokterId});
  try {
    const r = await fetch('<?= e(url('generate.satu')) ?>', {method: 'POST', body});
    const d = await r.json();
    if (!d.ok) { status.textContent = 'Gagal: ' + (d.pesan || 'tidak diketahui'); }
    else {
      status.innerHTML = `<span class="badge ok">${d.jumlah} file</span> dibuat` +
        (d.dilewati.length ? ` · dilewati ${d.dilewati.length}` : '') +
        (d.masalah.length ? ` · masalah ${d.masalah.length}` : '');
      setTimeout(() => location.reload(), 1200);
    }
  } catch (e) {
    status.textContent = 'Gagal: ' + e.message;
  }
  tombol.disabled = false;
}
</script>

<?php if (boleh_tulis()): ?>
<div class="kartu" style="margin-bottom:16px">
  <h3 style="margin-top:0" id="judul-dokumen">Tambah / perbarui dokumen</h3>
  <form method="post" action="<?= e(url('dokumen.simpan')) ?>" id="form-dokumen">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="dokter_id" value="<?= (int) $dokter['id'] ?>">
    <input type="hidden" name="id" id="dokumen_id" value="">
    <div class="baris">
      <div><label for="no_form">No Form</label><input type="text" id="no_form" name="no_form" placeholder="FN24120001" required></div>
      <div><label for="kode">Kode RSMPP</label><input type="text" id="kode" name="kode_rsmpp" placeholder="RSMPP26090001"></div>
      <div style="flex:2"><label for="nama_form">Nama form</label><input type="text" id="nama_form" name="nama_form" placeholder="KLINIK ONKOLOGI RADIASI ..."></div>
      <div style="flex:0">
        <button class="btn" type="submit" id="simpan-dokumen">Simpan</button>
        <button class="btn abu" type="button" id="batal-dokumen" onclick="batalEditDokumen()" style="display:none;margin-left:6px">Batal</button>
      </div>
    </div>
  </form>

  <h3>Impor CSV / tempel data</h3>
  <form method="post" action="<?= e(url('dokumen.impor')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="dokter_id" value="<?= (int) $dokter['id'] ?>">
    <p class="sempit muted">Format: header <code>kode,no_form,nama_form</code> (pemisah koma/titik-koma/tab).</p>
    <input type="file" name="csv" accept=".csv,.txt,text/csv">
    <label for="data">atau tempel di sini</label>
    <textarea id="data" name="data" rows="4" placeholder="kode,no_form,nama_form&#10;RSMPP26090001,FN24120001,KLINIK ONKOLOGI RADIASI ..."></textarea>
    <div style="margin-top:10px"><button class="btn" type="submit">Impor</button></div>
  </form>
</div>
<?php endif; ?>

<h2>Surat</h2>
<div class="kartu padat">
  <table>
    <thead><tr><th>File</th><th class="kanan">Halaman</th><th>Diunggah</th></tr></thead>
    <tbody>
      <?php foreach ($surat as $s): ?>
      <tr><td class="sempit"><?= e($s['filename']) ?></td><td class="kanan"><?= (int) $s['halaman'] ?></td>
        <td class="muted sempit"><?= e($s['uploaded_at']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$surat): ?><tr><td colspan="3" class="muted">Belum ada surat — unggah di Step 1.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<h2>Dokumen &amp; kode RSMPP</h2>
<div class="kartu padat">
  <table>
    <thead><tr><th>No form</th><th>Kode RSMPP</th><th>Nama form</th><th>Template</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($dokumen as $d): ?>
      <tr>
        <td><?= e($d['no_form']) ?></td>
        <td class="muted"><?= e($d['kode_rsmpp']) ?></td>
        <td><?= e($d['nama_form']) ?></td>
        <td>
          <?php if (isset($template[$d['no_form']])): ?>
            <span class="badge ok">siap</span>
          <?php else: ?>
            <span class="badge tunggu">tunggu template</span>
          <?php endif; ?>
        </td>
        <td class="kanan">
          <?php if (boleh_tulis()): ?>
          <button class="btn kecil abu" type="button"
                  data-id="<?= (int) $d['id'] ?>"
                  data-no-form="<?= e($d['no_form']) ?>"
                  data-kode="<?= e($d['kode_rsmpp']) ?>"
                  data-nama="<?= e($d['nama_form']) ?>"
                  onclick="editDokumen(this)">Edit</button>
          <form method="post" action="<?= e(url('dokumen.hapus')) ?>" class="inline" onsubmit="return confirm('Hapus dokumen ini?')">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="dokter_id" value="<?= (int) $dokter['id'] ?>">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <button class="btn kecil merah" type="submit">Hapus</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$dokumen): ?><tr><td colspan="5" class="muted">Belum ada dokumen.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<h2>Hasil terakhir</h2>
<div class="kartu padat">
  <table>
    <thead><tr><th>No form</th><th>File</th><th class="kanan">Halaman</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($hasil as $h): ?>
      <tr>
        <td><?= e($h['no_form']) ?></td>
        <td class="sempit"><?= e($h['filename']) ?></td>
        <td class="kanan"><?= (int) $h['halaman'] ?></td>
        <td class="kanan"><a class="btn kecil" href="<?= e(url('unduh', ['jenis' => 'file', 'id' => $h['id']])) ?>">Unduh</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$hasil): ?><tr><td colspan="4" class="muted">Belum ada hasil.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if (boleh_tulis()): ?>
<script>
function editDokumen(btn) {
  const d = btn.dataset;
  document.getElementById('dokumen_id').value = d.id;
  document.getElementById('no_form').value = d.noForm;
  document.getElementById('kode').value = d.kode;
  document.getElementById('nama_form').value = d.nama;
  document.getElementById('judul-dokumen').textContent = 'Edit dokumen ' + d.noForm;
  document.getElementById('simpan-dokumen').textContent = 'Simpan perubahan';
  document.getElementById('batal-dokumen').style.display = '';
  document.getElementById('form-dokumen').scrollIntoView({ behavior: 'smooth', block: 'center' });
  document.getElementById('no_form').focus();
}

function batalEditDokumen() {
  document.getElementById('form-dokumen').reset();
  document.getElementById('dokumen_id').value = '';
  document.getElementById('judul-dokumen').textContent = 'Tambah / perbarui dokumen';
  document.getElementById('simpan-dokumen').textContent = 'Simpan';
  document.getElementById('batal-dokumen').style.display = 'none';
}
</script>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
