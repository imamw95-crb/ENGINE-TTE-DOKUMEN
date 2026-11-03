<?php $judul = 'Generate & Unduh'; require __DIR__ . '/_header.php'; ?>
<h1>Generate &amp; unduh</h1>
<p class="sub">Satu PDF per dokumen: Surat (3 hal) → LAMPIRAN 1 → Template → Halaman Terakhir (2 hal).
  Hasil disimpan di <code>storage/output/&lt;nama dokter&gt;/</code>.</p>

<?php if (boleh_tulis()): ?>
<div class="kartu" style="margin-bottom:16px">
  <div class="baris">
    <div style="flex:0"><button class="btn" type="button" onclick="generateSemua()" id="tombol-mulai">Generate semua dokter</button></div>
    <div style="flex:0"><a class="btn abu" href="<?= e(url('generate')) ?>">Muat ulang</a></div>
    <div id="status-batch" class="sempit muted" style="flex:2"></div>
  </div>
  <progress id="progres" value="0" max="100" style="margin-top:12px"></progress>
  <pre class="log" id="log" style="margin-top:10px"></pre>
  <div id="tautan-zip"></div>
</div>
<?php endif; ?>

<div class="kartu padat">
  <table>
    <thead><tr><th>Nomor</th><th>Nama dokter</th><th class="kanan">Surat</th><th class="kanan">Dokumen</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($dokter as $d): ?>
      <tr id="dokter-<?= (int) $d['id'] ?>">
        <td class="muted"><?= e($d['nomor']) ?></td>
        <td><a href="<?= e(url('dokter.detail', ['id' => $d['id']])) ?>"><?= e($d['nama']) ?></a></td>
        <td class="kanan"><?= $d['jml_surat'] ? (int) $d['jml_surat'] : '<span class="badge gagal">0</span>' ?></td>
        <td class="kanan"><?= (int) $d['jml_dokumen'] ?></td>
        <td class="kanan" style="white-space:nowrap">
          <?php if (boleh_tulis() && $d['jml_surat'] > 0): ?>
            <button class="btn kecil" type="button" onclick="generateSatu(<?= (int) $d['id'] ?>)">Generate</button>
          <?php endif; ?>
          <?php if ($d['jml_dokumen'] > 0): ?>
            <a class="btn kecil abu" href="<?= e(url('unduh', ['jenis' => 'dokter', 'id' => $d['id']])) ?>">ZIP</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$dokter): ?><tr><td colspan="5" class="muted">Belum ada dokter.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($batch): ?>
<h2>Batch</h2>
<div class="kartu padat">
  <table>
    <thead><tr><th>Kode</th><th>Status</th><th class="kanan">File</th><th>Dibuat</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($batch as $b): ?>
      <tr>
        <td><?= e($b['kode']) ?></td>
        <td><span class="badge <?= $b['status'] === 'selesai' ? 'ok' : 'info' ?>"><?= e($b['status']) ?></span></td>
        <td class="kanan"><?= (int) $b['total_file'] ?></td>
        <td class="muted sempit"><?= e($b['created_at']) ?></td>
        <td class="kanan"><a class="btn kecil abu" href="<?= e(url('unduh', ['jenis' => 'batch', 'id' => $b['id']])) ?>">ZIP batch</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>
const csrf = '<?= e(csrf()) ?>';
let batchId = null;

function catat(teks) {
  const log = document.getElementById('log');
  log.textContent += teks + '\n';
  log.scrollTop = log.scrollHeight;
}

async function mulaiBatch() {
  const r = await fetch('index.php?r=generate.mulai', {method: 'POST',
    body: new URLSearchParams({csrf})});
  const d = await r.json();
  batchId = d.batch_id;
  document.getElementById('status-batch').textContent = 'Batch ' + d.kode + ' dimulai';
  return d;
}

async function generateSatu(dokterId) {
  if (!batchId) await mulaiBatch();
  const r = await fetch('index.php?r=generate.satu', {method: 'POST',
    body: new URLSearchParams({csrf, dokter_id: dokterId, batch_id: batchId})});
  const d = await r.json();
  const baris = document.getElementById('dokter-' + dokterId);
  if (baris) {
    const sel = baris.querySelector('td:last-child');
    sel.innerHTML = d.ok
      ? `<span class="badge ok">${d.jumlah} file</span> <a class="btn kecil abu" href="index.php?r=unduh&jenis=dokter&id=${dokterId}">ZIP</a>`
      : `<span class="badge gagal">gagal</span>`;
  }
  catat(d.ok
    ? `✔ ${baris ? baris.children[1].textContent.trim() : dokterId}: ${d.jumlah} file` +
      (d.dilewati.length ? `, ${d.dilewati.length} dilewati` : '') +
      (d.masalah.length ? `, ${d.masalah.length} masalah` : '')
    : `✘ ${dokterId}: ${d.pesan}`);
  return d;
}

async function generateSemua() {
  const tombol = document.getElementById('tombol-mulai');
  tombol.disabled = true;
  document.getElementById('log').textContent = '';
  await mulaiBatch();
  const ids = [...document.querySelectorAll('tbody tr[id^="dokter-"]')]
    .map(tr => parseInt(tr.id.replace('dokter-', ''), 10));
  const progres = document.getElementById('progres');
  progres.max = ids.length;
  for (let i = 0; i < ids.length; i++) {
    await generateSatu(ids[i]);
    progres.value = i + 1;
  }
  const r = await fetch('index.php?r=generate.selesai', {method: 'POST',
    body: new URLSearchParams({csrf, batch_id: batchId})});
  const d = await r.json();
  document.getElementById('status-batch').textContent = 'Selesai — ' + d.total_file + ' file';
  document.getElementById('tautan-zip').innerHTML =
    `<a class="btn" href="index.php?r=unduh&jenis=batch&id=${batchId}">Unduh ZIP batch</a>`;
  tombol.disabled = false;
}
</script>
<?php require __DIR__ . '/_footer.php'; ?>
