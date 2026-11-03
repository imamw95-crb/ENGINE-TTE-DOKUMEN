<?php $judul = 'Ambil data Dari SIMRS'; require __DIR__ . '/_header.php'; ?>
<h1>Ambil data Dari SIMRS</h1>
<p class="sub">Atur akun &amp; sumber data SIMRS di sini. Alur: pilih dokter → Uji Sumber → Ambil Data →
  lihat dampak → Terapkan. Data belum berubah sebelum tombol "Terapkan" ditekan.</p>

<?php if (!boleh_tulis()): ?>
<p class="muted">Role Anda hanya bisa melihat.</p>
<?php else: ?>

<div class="kartu" style="margin-bottom:16px">
  <h3 style="margin-top:0">1. Akun SIMRS</h3>
  <form method="post" action="<?= e(url('simrs.akun')) ?>" id="form-akun">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="id" id="akun-id-field" value="0">
    <div class="baris">
      <div><label>Label</label><input type="text" name="label" id="akun-label" placeholder="Akun TTE" required></div>
      <div><label>Username</label><input type="text" name="username" id="akun-username" required></div>
      <div><label>ipserver (opsional)</label><input type="text" name="ipserver" id="akun-ipserver"></div>
      <div><label>Password</label><input type="password" name="password" id="akun-password" placeholder="disimpan AES-256" autocomplete="new-password"></div>
      <div style="flex:0">
        <button class="btn" type="submit" id="akun-submit">Simpan akun</button>
        <button class="btn kecil abu" type="button" id="akun-batal" hidden onclick="batalAkun()">Batal</button>
      </div>
    </div>
    <p class="sempit muted" id="akun-info" style="margin:8px 0 0">Mode: tambah akun baru.</p>
  </form>
  <?php if ($akun): ?>
  <table style="margin-top:12px">
    <thead><tr><th>Label</th><th>Username</th><th>ipserver</th><th>Password</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($akun as $a): ?>
      <tr>
        <td><?= e($a['label']) ?></td>
        <td><?= e($a['username']) ?></td>
        <td class="muted"><?= e($a['ipserver']) ?: '-' ?></td>
        <td><?= $a['password_enc'] ? '<span class="badge ok">tersimpan</span>' : '<span class="badge tunggu">kosong</span>' ?></td>
        <td class="kanan" style="white-space:nowrap">
          <button class="btn kecil abu" type="button"
                  data-id="<?= (int) $a['id'] ?>"
                  data-label="<?= e($a['label']) ?>"
                  data-username="<?= e($a['username']) ?>"
                  data-ipserver="<?= e($a['ipserver']) ?>"
                  onclick="editAkun(this)">Edit</button>
          <button class="btn kecil" type="button" onclick="ujiLogin(<?= (int) $a['id'] ?>, this)">Uji login</button>
          <form method="post" action="<?= e(url('simrs.akun.hapus')) ?>" class="inline"
                onsubmit="return confirm('Hapus akun ini?')">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <input type="hidden" name="username" value="<?= e($a['username']) ?>">
            <button class="btn kecil merah" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="sempit muted" id="pesan-login" style="margin:8px 0 0"></p>
  <?php endif; ?>
</div>

<div class="kartu" style="margin-bottom:16px">
  <h3 style="margin-top:0">2. Sumber data</h3>
  <p class="sempit muted">
    Menu di SIMRS: <code>IT → Master No. Form</code> (URL <code>index.php/itmaster/noform</code>,
    hash <code>#itmaster/noform</code> hanya penanda tab di browser). Datanya dimuat lewat DataTables,
    jadi isi juga <strong>URL data</strong> <code>index.php/itmaster/listener_dt_noform_user</code>
    dengan parameter <code>{"aktif":"1"}</code> dan metode <strong>POST</strong>.
  </p>
  <form method="post" action="<?= e(url('simrs.sumber')) ?>" id="form-sumber">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="id" id="sumber-id-field" value="0">
    <div class="baris">
      <div><label>Nama sumber</label><input type="text" name="nama" id="sumber-nama" placeholder="Master No. Form User" required></div>
      <div><label>Jenis</label>
        <select name="jenis" id="sumber-jenis">
          <option value="per_dokter">per dokter (ada kolom karyawan)</option>
          <option value="master">master form (tanpa dokter)</option>
        </select>
      </div>
      <div><label>Metode</label>
        <select name="metode" id="sumber-metode">
          <option value="POST">POST (DataTables)</option>
          <option value="GET">GET</option>
        </select>
      </div>
      <div style="flex:2"><label>URL halaman</label><input type="text" name="url" id="sumber-url" placeholder="<?= e($base_url) ?>/index.php/itmaster/noform"></div>
    </div>
    <div class="baris" style="margin-top:8px">
      <div style="flex:2"><label>URL data (JSON)</label><input type="text" name="url_data" id="sumber-url-data" placeholder="<?= e($base_url) ?>/index.php/itmaster/listener_dt_noform_user"></div>
      <div><label>Params (JSON)</label><input type="text" name="params" id="sumber-params" value='{"aktif":"1"}'></div>
      <div style="flex:0">
        <button class="btn" type="submit" id="sumber-submit">Simpan sumber</button>
        <button class="btn kecil abu" type="button" id="sumber-batal" hidden onclick="batalSumber()">Batal</button>
      </div>
    </div>
    <div style="margin-top:8px">
      <label>Pemetaan kolom tersimpan (JSON, opsional)</label>
      <input type="text" name="pemetaan" id="sumber-pemetaan" placeholder='{"tabel":0,"pecah_noform":true,"kolom":{"no_form":2,"kode":0,"nama":"","karyawan":1}}'>
    </div>
    <p class="sempit muted" id="sumber-info" style="margin:8px 0 0">Mode: tambah sumber baru.</p>
  </form>
  <?php if ($sumber): ?>
  <table style="margin-top:12px">
    <thead><tr><th>#</th><th>Nama</th><th>Jenis</th><th>Metode</th><th>URL data</th><th>Params</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($sumber as $s): ?>
      <tr>
        <td><?= (int) $s['id'] ?></td>
        <td><?= e($s['nama']) ?></td>
        <td><span class="badge info"><?= e($s['jenis']) ?></span></td>
        <td class="sempit"><?= e($s['metode']) ?></td>
        <td class="sempit muted"><?= e($s['url_data'] ?: '(HTML: ' . $s['url'] . ')') ?></td>
        <td class="sempit muted"><?= e($s['params'] ?: '-') ?></td>
        <td class="kanan" style="white-space:nowrap">
          <button class="btn kecil abu" type="button"
                  data-id="<?= (int) $s['id'] ?>"
                  data-nama="<?= e($s['nama']) ?>"
                  data-jenis="<?= e($s['jenis']) ?>"
                  data-metode="<?= e($s['metode']) ?>"
                  data-url="<?= e($s['url']) ?>"
                  data-url-data="<?= e($s['url_data']) ?>"
                  data-params="<?= e($s['params']) ?>"
                  data-pemetaan="<?= e($s['pemetaan']) ?>"
                  onclick="editSumber(this)">Edit</button>
          <form method="post" action="<?= e(url('simrs.sumber.hapus')) ?>" class="inline"
                onsubmit="return confirm('Hapus sumber ini beserta staging-nya?')">
            <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button class="btn kecil merah" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="kartu" style="margin-bottom:16px">
  <h3 style="margin-top:0">3. Uji &amp; petakan kolom</h3>
  <?php if (!$akun || !$sumber): ?>
    <p class="sempit" style="color:#b45309">Simpan minimal satu akun dan satu sumber dulu.</p>
  <?php else: ?>
  <div class="baris">
    <div><label>Akun</label>
      <select id="akun-id">
        <?php foreach ($akun as $a): ?><option value="<?= (int) $a['id'] ?>"><?= e($a['label']) ?> · <?= e($a['username']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label>Sumber</label>
      <select id="sumber-id">
        <?php foreach ($sumber as $s): ?>
        <option value="<?= (int) $s['id'] ?>"
                data-jenis="<?= e($s['jenis']) ?>"
                data-pemetaan="<?= e($s['pemetaan']) ?>">#<?= (int) $s['id'] ?> <?= e($s['nama']) ?> (<?= e($s['jenis']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>Ambil untuk dokter</label>
      <input type="search" id="cari-dokter" placeholder="Cari nama / nomor dokter…" autocomplete="off"
             style="margin-bottom:6px" oninput="saringDokter()">
      <select id="dokter-id" size="1">
        <?php foreach (db()->query('SELECT id, nomor, nama FROM dokter ORDER BY nama') as $d): ?>
        <option value="<?= (int) $d['id'] ?>"
                data-nama="<?= e($d['nama']) ?>"
                data-nomor="<?= e($d['nomor']) ?>"
                <?= ((int) ($dokter_terpilih ?? 0) === (int) $d['id']) ? 'selected' : '' ?>><?= e($d['nama']) ?> (<?= e($d['nomor']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <div class="sempit muted" id="info-dokter" style="margin-top:4px"></div>
    </div>
    <div style="flex:0"><button class="btn" type="button" onclick="ujiSumber()">Uji Sumber</button></div>
  </div>
  <p class="sempit muted" style="margin:8px 0 0">
    Data <strong>hanya diambil untuk satu dokter</strong> yang dipilih di atas — dokter lain tidak tersentuh.
  </p>

  <div id="pesan-uji" class="sempit muted" style="margin-top:10px"></div>

  <div id="panel-petakan" hidden style="margin-top:14px">
    <div class="baris">
      <div><label>Tabel</label><select id="pilih-tabel" onchange="gambarContoh()"></select></div>
      <div><label>Kolom No Form</label><select id="kol-no-form" onchange="gambarContoh()"></select></div>
      <div><label>Kolom Kode RSMPP</label><select id="kol-kode" onchange="gambarContoh()"></select></div>
      <div><label>Kolom Nama Form</label><select id="kol-nama" onchange="gambarContoh()"></select></div>
      <div><label>Kolom Karyawan/Dokter</label><select id="kol-karyawan" onchange="gambarContoh()"></select></div>
    </div>
    <label style="margin-top:10px;display:flex;gap:8px;align-items:center">
      <input type="checkbox" id="pecah-noform" checked style="width:auto">
      <span>Pisahkan kolom No Form berformat <code>FN24120001 - NAMA FORM</code> menjadi kode + nama</span>
    </label>

    <div id="contoh-tabel" style="margin-top:12px"></div>

    <div class="baris" style="margin-top:12px">
      <div style="flex:0"><button class="btn" type="button" onclick="ambilData()">Ambil Data</button></div>
      <div id="pesan-ambil" class="sempit muted"></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="kartu">
  <h3 style="margin-top:0">4. Terapkan staging</h3>
  <?php if (!$jumlah_staging): ?>
    <p class="sempit muted">Staging kosong. Lakukan "Ambil Data" di atas.</p>
  <?php else: ?>
    <p class="sempit">
      Staging: <strong><?= (int) $jumlah_staging ?></strong> baris
      <?php if ($dokter_staging): ?>
        untuk <strong><?= e($dokter_staging['nama']) ?></strong> (<?= e($dokter_staging['nomor']) ?>)
      <?php endif; ?>
      (menampilkan maks. 200).
    </p>

    <div class="kartu" style="background:#fbfcfe;margin-bottom:12px">
      <strong>Dampak bila diterapkan:</strong>
      <span class="badge ok"><?= (int) $ringkas['tambah'] ?> dokumen baru</span>
      <?php if ($ringkas['ubah'] > 0): ?>
        <span class="badge gagal"><?= (int) $ringkas['ubah'] ?> kode akan diubah</span>
      <?php else: ?>
        <span class="badge netral">0 kode diubah</span>
      <?php endif; ?>
      <span class="badge info"><?= (int) $ringkas['sama'] ?> sudah sama</span>
      <?php if ($ringkas['contoh_ubah']): ?>
        <ul class="sempit muted" style="margin:8px 0 0 18px">
          <?php foreach ($ringkas['contoh_ubah'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="kartu padat" style="max-height:280px;overflow:auto;margin-bottom:12px">
      <table>
        <thead><tr><th>Karyawan</th><th>No Form</th><th>Kode RSMPP</th><th>Nama form</th></tr></thead>
        <tbody>
          <?php foreach ($staging as $s): ?>
          <tr>
            <td class="sempit"><?= e($s['karyawan'] ?: '-') ?></td>
            <td><?= e($s['no_form']) ?></td>
            <td class="muted sempit"><?= e($s['kode_rsmpp']) ?></td>
            <td class="sempit"><?= e($s['nama_form']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($dokter_staging): ?>
    <form method="post" action="<?= e(url('simrs.terapkan')) ?>"
          onsubmit="return confirm('Terapkan <?= (int) $jumlah_staging ?> baris ke <?= e(addslashes($dokter_staging['nama'])) ?> saja?')">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="dokter_id" value="<?= (int) $dokter_staging['id'] ?>">
      <label style="display:flex;gap:8px;align-items:center;margin:0 0 12px">
        <input type="checkbox" name="timpa" value="1" style="width:auto">
        <span>Timpa kode RSMPP yang berbeda (default: <strong>tidak</strong> — hanya menambah dokumen baru)</span>
      </label>
      <button class="btn" type="submit">Terapkan ke <?= e($dokter_staging['nama']) ?></button>
    </form>
    <?php endif; ?>

    <form method="post" action="<?= e(url('simrs.batal')) ?>" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <button class="btn kecil merah" type="submit">Kosongkan staging</button>
    </form>
  <?php endif; ?>
</div>

<script>
let tabelTerakhir = [];
const csrf = '<?= e(csrf()) ?>';

function esc(s) { return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

/* ---------- pencarian dokter ---------- */
const SEMUA_DOKTER = [...document.querySelectorAll('#dokter-id option')].map(o => ({
  id: o.value, nama: o.dataset.nama || o.textContent, nomor: o.dataset.nomor || ''
}));

function saringDokter() {
  const q = document.getElementById('cari-dokter').value.trim().toLowerCase();
  const sel = document.getElementById('dokter-id');
  const terpilih = sel.value;
  const cocok = q === '' ? SEMUA_DOKTER
    : SEMUA_DOKTER.filter(d => d.nama.toLowerCase().includes(q) || d.nomor.toLowerCase().includes(q));

  sel.innerHTML = '';
  cocok.forEach(d => {
    const o = new Option(`${d.nama} (${d.nomor})`, d.id);
    o.dataset.nama = d.nama;
    o.dataset.nomor = d.nomor;
    sel.add(o);
  });
  if (cocok.some(d => d.id === terpilih)) {
    sel.value = terpilih;
  }
  document.getElementById('info-dokter').textContent =
    `${cocok.length} dari ${SEMUA_DOKTER.length} dokter`;
  if (cocok.length === 0) {
    sel.add(new Option('(tidak ada yang cocok)', ''));
  }
}

function inisialDokter() {
  const info = document.getElementById('info-dokter');
  if (info) {
    info.textContent = `${SEMUA_DOKTER.length} dokter — ketik untuk mencari`;
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', inisialDokter);
} else {
  inisialDokter();
}

/* ---------- edit akun & sumber ---------- */
function editAkun(btn) {
  const d = btn.dataset;
  document.getElementById('akun-id-field').value = d.id;
  document.getElementById('akun-label').value = d.label;
  document.getElementById('akun-username').value = d.username;
  document.getElementById('akun-ipserver').value = d.ipserver || '';
  document.getElementById('akun-password').value = '';
  document.getElementById('akun-submit').textContent = 'Simpan perubahan';
  document.getElementById('akun-batal').hidden = false;
  document.getElementById('akun-info').textContent =
    'Mode: edit akun #' + d.id + ' — kosongkan password bila tidak ingin mengganti.';
  document.getElementById('form-akun').scrollIntoView({behavior: 'smooth', block: 'center'});
}

function batalAkun() {
  document.getElementById('form-akun').reset();
  document.getElementById('akun-id-field').value = '0';
  document.getElementById('akun-submit').textContent = 'Simpan akun';
  document.getElementById('akun-batal').hidden = true;
  document.getElementById('akun-info').textContent = 'Mode: tambah akun baru.';
}

function editSumber(btn) {
  const d = btn.dataset;
  document.getElementById('sumber-id-field').value = d.id;
  document.getElementById('sumber-nama').value = d.nama;
  document.getElementById('sumber-jenis').value = d.jenis;
  document.getElementById('sumber-metode').value = d.metode || 'POST';
  document.getElementById('sumber-url').value = d.url || '';
  document.getElementById('sumber-url-data').value = d.urlData || '';
  document.getElementById('sumber-params').value = d.params || '';
  document.getElementById('sumber-pemetaan').value = d.pemetaan || '';
  document.getElementById('sumber-submit').textContent = 'Simpan perubahan';
  document.getElementById('sumber-batal').hidden = false;
  document.getElementById('sumber-info').textContent =
    'Mode: edit sumber #' + d.id + '.';
  document.getElementById('form-sumber').scrollIntoView({behavior: 'smooth', block: 'center'});
}

function batalSumber() {
  document.getElementById('form-sumber').reset();
  document.getElementById('sumber-id-field').value = '0';
  document.getElementById('sumber-submit').textContent = 'Simpan sumber';
  document.getElementById('sumber-batal').hidden = true;
  document.getElementById('sumber-info').textContent = 'Mode: tambah sumber baru.';
}

async function ujiLogin(akunId, btn) {
  const pesan = document.getElementById('pesan-login');
  pesan.style.color = '';
  pesan.textContent = 'Menguji login akun #' + akunId + '...';
  if (btn) { btn.disabled = true; }
  try {
    const r = await fetch('index.php?r=simrs.uji.login', {
      method: 'POST', body: new URLSearchParams({csrf, akun_id: akunId})});
    const d = await r.json();
    pesan.textContent = (d.ok ? '✔ ' : '✘ ') + d.pesan;
    pesan.style.color = d.ok ? '#15803d' : '#b91c1c';
  } catch (e) {
    pesan.textContent = '✘ Gagal: ' + e.message;
    pesan.style.color = '#b91c1c';
  }
  if (btn) { btn.disabled = false; }
}

function opsiKolom(id, sertakanKosong) {
  const sel = document.getElementById(id);
  sel.innerHTML = '';
  if (sertakanKosong) sel.add(new Option('(tidak dipakai)', ''));
  const idx = parseInt(document.getElementById('pilih-tabel').value || '0', 10);
  const t = tabelTerakhir.find(x => x.index === idx);
  (t ? t.headers : []).forEach((h, i) => sel.add(new Option(`[${i}] ${h || '(kosong)'}`, i)));
  if (sertakanKosong) sel.value = '';
}

function isiPilihanTabel() {
  const pilih = document.getElementById('pilih-tabel');
  pilih.innerHTML = '';
  tabelTerakhir.forEach(t => pilih.add(new Option(`Tabel ${t.index} (${t.jumlah} baris)`, t.index)));
  opsiKolom('kol-no-form', false);
  opsiKolom('kol-kode', true);
  opsiKolom('kol-nama', true);
  opsiKolom('kol-karyawan', true);
  terkaKolom();
  gambarContoh();
}

function terkaKolom() {
  const idx = parseInt(document.getElementById('pilih-tabel').value || '0', 10);
  const t = tabelTerakhir.find(x => x.index === idx);
  if (!t) return;
  t.headers.forEach((h, i) => {
    const teks = (h || '').toUpperCase();
    if (teks.includes('NOFORM') || teks.includes('NO FORM') || teks.includes('NO_FORM')) document.getElementById('kol-no-form').value = i;
    if (teks === 'KODE' || teks === 'ID' || teks.includes('KODE RSMPP') || teks.includes('KODE MPHG')) document.getElementById('kol-kode').value = i;
    if (teks.includes('NAMA')) document.getElementById('kol-nama').value = i;
    if (teks.includes('KARYAWAN') || teks.includes('DOKTER')) document.getElementById('kol-karyawan').value = i;
  });
}

function gambarContoh() {
  const idx = parseInt(document.getElementById('pilih-tabel').value || '0', 10);
  const t = tabelTerakhir.find(x => x.index === idx);
  if (!t) { document.getElementById('contoh-tabel').innerHTML = ''; return; }
  const head = t.headers.map(h => `<th>${esc(h)}</th>`).join('');
  const isi = t.contoh.map(r => '<tr>' + r.map(c => `<td class="sempit">${esc(c)}</td>`).join('') + '</tr>').join('');
  document.getElementById('contoh-tabel').innerHTML =
    `<div class="kartu padat"><table><thead><tr>${head}</tr></thead><tbody>${isi || '<tr><td class="muted">kosong</td></tr>'}</tbody></table></div>`;
}

async function ujiSumber() {
  const pesan = document.getElementById('pesan-uji');
  pesan.textContent = 'Mengambil data...';
  const data = new URLSearchParams({csrf,
    akun_id: document.getElementById('akun-id').value,
    sumber_id: document.getElementById('sumber-id').value});
  try {
    const r = await fetch('index.php?r=simrs.uji', {method: 'POST', body: data});
    const d = await r.json();
    if (!d.ok) { pesan.textContent = 'Gagal: ' + d.pesan; return; }
    tabelTerakhir = d.tabel || [];
    document.getElementById('panel-petakan').hidden = tabelTerakhir.length === 0;
    pesan.textContent = d.pesan + (d.jenis === 'json' ? ' · mode JSON' : ' · mode HTML');
    isiPilihanTabel();
    pakaiPemetaanTersimpan();
  } catch (e) { pesan.textContent = 'Gagal: ' + e.message; }
}

/** Terapkan pemetaan yang pernah disimpan untuk sumber terpilih. */
function pakaiPemetaanTersimpan() {
  const opt = document.getElementById('sumber-id').selectedOptions[0];
  if (!opt || !opt.dataset.pemetaan) return;
  let peta;
  try { peta = JSON.parse(opt.dataset.pemetaan); } catch (e) { return; }
  if (!peta || !peta.kolom) return;
  const set = (id, nilai) => {
    if (nilai === undefined || nilai === null || nilai === '') return;
    const sel = document.getElementById(id);
    if ([...sel.options].some(o => o.value === String(nilai))) sel.value = String(nilai);
  };
  set('kol-no-form', peta.kolom.no_form);
  set('kol-kode', peta.kolom.kode);
  set('kol-nama', peta.kolom.nama);
  set('kol-karyawan', peta.kolom.karyawan);
  if (peta.pecah_noform !== undefined) {
    document.getElementById('pecah-noform').checked = !!peta.pecah_noform;
  }
  gambarContoh();
}

async function ambilData() {
  const pesan = document.getElementById('pesan-ambil');
  pesan.textContent = 'Mengambil & menyimpan ke staging...';
  const pemetaan = {
    tabel: parseInt(document.getElementById('pilih-tabel').value || '0', 10),
    pecah_noform: document.getElementById('pecah-noform').checked,
    kolom: {
      no_form: document.getElementById('kol-no-form').value,
      kode: document.getElementById('kol-kode').value,
      nama: document.getElementById('kol-nama').value,
      karyawan: document.getElementById('kol-karyawan').value
    }
  };
  const data = new URLSearchParams({csrf,
    akun_id: document.getElementById('akun-id').value,
    sumber_id: document.getElementById('sumber-id').value,
    dokter_id: document.getElementById('dokter-id').value,
    pemetaan: JSON.stringify(pemetaan)});
  try {
    const r = await fetch('index.php?r=simrs.ambil', {method: 'POST', body: data});
    const d = await r.json();
    if (!d.ok) { pesan.textContent = 'Gagal: ' + d.pesan; return; }
    pesan.textContent = d.pesan + ' — memuat ulang...';
    setTimeout(() => location.reload(), 800);
  } catch (e) { pesan.textContent = 'Gagal: ' + e.message; }
}
</script>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
