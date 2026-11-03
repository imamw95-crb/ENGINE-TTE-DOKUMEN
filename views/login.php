<?php $judul = 'Masuk'; require __DIR__ . '/_header.php'; ?>
<div class="login-box">
  <h1>Masuk TTE PATROL ENGINE</h1>
  <p class="sub">Gabung dokumen TTE QuickSign RSMPP</p>
  <form method="post" action="<?= e(url('login')) ?>">
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= e($username ?? '') ?>" autocomplete="username" autofocus required>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit" class="btn">Masuk</button>
  </form>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
