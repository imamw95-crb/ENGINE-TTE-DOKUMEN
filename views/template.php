<?php $judul = 'Step 5 · Template'; require __DIR__ . '/_header.php'; ?>
<h1>Step 5 — Master template</h1>
<p class="sub">Satu template aktif per form. Unggah ulang = versi baru (versi lama tetap tersimpan); nama &amp; no form bisa diperbaiki lewat tombol <strong>edit</strong>.</p>

<div class="kartu padat">
  <table>
    <thead><tr><th>No form</th><th>Nama form</th><th class="kanan">Dipakai</th><th>Template aktif</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($forms as $f):
          $daftar = $versi[$f['id']] ?? [];
          $aktif = null;
          foreach ($daftar as $v) {
              if ((int) $v['aktif'] === 1) { $aktif = $v; break; }
          }
          ?>
      <tr>
        <td><?= e($f['no_form']) ?></td>
        <td><?= e($f['nama_form']) ?></td>
        <td class="kanan"><?= (int) $f['dipakai'] ?></td>
        <td>
          <?php if ($aktif): ?>
            <span class="badge ok">v<?= (int) $aktif['versi'] ?></span>
            <span class="sempit muted"><?= e($aktif['filename']) ?> · <?= (int) $aktif['halaman'] ?> hal</span>
            <a class="btn kecil abu" href="<?= e(url('unduh', ['jenis' => 'template', 'id' => $aktif['id']])) ?>" target="_blank">lihat</a>
          <?php else: ?>
            <span class="badge tunggu">belum ada</span>
          <?php endif; ?>
        </td>
        <td class="kanan">
          <?php if (boleh_tulis()): ?>
          <form method="post" action="<?= e(url('template.upload')) ?>" enctype="multipart/form-data" class="inline">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="form_id" value="<?= (int) $f['id'] ?>">
            <input type="file" name="berkas" accept="application/pdf" required style="width:210px;display:inline-block">
            <button class="btn kecil" type="submit"><?= $aktif ? 'Unggah ulang' : 'Unggah' ?></button>
          </form>
          <details class="sempit" style="margin-top:6px;text-align:left">
            <summary class="muted" style="cursor:pointer">edit nama / no form</summary>
            <form method="post" action="<?= e(url('template.simpan')) ?>" style="margin-top:6px">
              <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
              <input type="text" name="no_form" value="<?= e($f['no_form']) ?>" required
                     aria-label="No form" style="max-width:150px;font-size:12.5px">
              <input type="text" name="nama_form" value="<?= e($f['nama_form']) ?>" required
                     aria-label="Nama form" style="margin-top:4px;font-size:12.5px">
              <button class="btn kecil" type="submit" style="margin-top:4px">Simpan</button>
            </form>
          </details>
          <?php endif; ?>
        </td>
      </tr>
      <?php if ($daftar): ?>
      <tr>
        <td colspan="5" class="sempit muted" style="background:#fbfcfe">
          Versi:
          <?php foreach ($daftar as $v): ?>
            <span class="pill">v<?= (int) $v['versi'] ?> · <?= (int) $v['halaman'] ?> hal<?= (int) $v['aktif'] === 1 ? ' · aktif' : '' ?></span>
            <a class="pill" href="<?= e(url('unduh', ['jenis' => 'template', 'id' => $v['id']])) ?>" target="_blank">lihat</a>
            <?php if (boleh_tulis()): ?>
              <?php if ((int) $v['aktif'] !== 1): ?>
              <form method="post" action="<?= e(url('template.aktif')) ?>" class="inline">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                <button class="btn kecil abu" type="submit">aktifkan</button>
              </form>
              <?php endif; ?>
              <form method="post" action="<?= e(url('template.hapus')) ?>" class="inline" onsubmit="return confirm('Hapus versi ini?')">
                <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                <button class="btn kecil merah" type="submit">hapus</button>
              </form>
            <?php endif; ?>
          <?php endforeach; ?>
        </td>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$forms): ?><tr><td colspan="5" class="muted">Belum ada form. Impor dokumen di Step 4 dulu.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
