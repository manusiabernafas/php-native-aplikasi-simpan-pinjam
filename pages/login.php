<?php
if (user()) redirect('dashboard');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = q('SELECT * FROM users WHERE username = ?', [trim($_POST['username'] ?? '')])->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        session_regenerate_id(true);
        unset($u['password']);
        $_SESSION['user'] = $u;
        redirect('dashboard');
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Masuk · <?= e(setting()['nama']) ?></title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><div class="login-page"><form method="post" class="login-box">
    <?= csrf_field() ?>
    <div class="logo"><?= icon('piggy', 28) ?></div>
    <h1>Masuk</h1><p class="muted" style="margin:0 0 22px"><?= e(setting()['nama']) ?></p>
    <?php if ($error): ?><div class="snack error"><?= e($error) ?></div><?php endif; ?>
    <div class="field"><label>Username</label><input type="text" name="username" required autofocus></div>
    <div class="field"><label>Password</label><input type="password" name="password" required></div>
    <button class="btn btn-filled btn-block" style="margin-top:8px">Masuk</button>
    <div class="note">Akun demo (password <b>admin123</b>): <b>ketua</b> menyetujui pinjaman · <b>admin</b> bendahara · <b>teller</b> kasir</div>
</form></div></body></html>
