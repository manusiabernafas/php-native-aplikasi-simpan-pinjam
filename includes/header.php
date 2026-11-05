<?php
$navPengajuan = (int) q("SELECT COUNT(*) FROM pinjaman WHERE status = 'diajukan'")->fetchColumn();
$navTunggak = (int) q("SELECT COUNT(*) FROM angsuran a JOIN pinjaman p ON p.id = a.pinjaman_id WHERE p.status = 'berjalan' AND a.dibayar_at IS NULL AND a.jatuh_tempo < CURDATE()")->fetchColumn();
$navMenu = [
    'Utama' => [['dashboard', 'Beranda', 'home', 0], ['anggota', 'Anggota', 'users', 0]],
    'Transaksi' => [['simpanan', 'Simpanan', 'piggy', 0], ['pinjaman', 'Pinjaman', 'hand', $navPengajuan + $navTunggak]],
    'Lainnya' => [['laporan', 'Laporan', 'chart', 0]],
];
if (in_array(user()['role'], ['ketua', 'bendahara'], true)) $navMenu['Lainnya'][] = ['pengaturan', 'Pengaturan', 'settings', 0];
$navCur = explode('.', $_GET['page'] ?? 'dashboard')[0];
if ($navCur === 'bukti') $navCur = 'simpanan';
$flash = flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Beranda') ?> · <?= e(setting()['nama']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">
    <aside class="drawer" id="drawer">
        <div class="head"><span class="logo"><?= icon('piggy', 24) ?></span><div><b><?= e(setting()['nama']) ?></b><small>Simpan Pinjam</small></div></div>
        <a class="fab" href="<?= url('simpanan') ?>"><?= icon('plus', 20) ?> Transaksi simpanan</a>
        <nav>
            <?php foreach ($navMenu as $sec => $links): ?>
                <div class="sec"><?= $sec ?></div>
                <?php foreach ($links as [$k, $l, $ic, $n]): ?><a href="<?= url($k) ?>" class="<?= $navCur === $k ? 'active' : '' ?>"><?= icon($ic, 22) ?> <?= $l ?><?php if ($n): ?><span class="n chip chip-red" style="height:22px;padding:0 8px"><?= $n ?></span><?php endif; ?></a><?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="grow"></div>
        <div class="me"><span class="av"><?= e(strtoupper(substr(user()['nama'], 0, 1))) ?></span><div class="grow"><b><?= e(user()['nama']) ?></b><small><?= e(ucfirst(user()['role'])) ?></small></div><a class="icon-btn" href="<?= url('logout') ?>" title="Keluar"><?= icon('logout', 20) ?></a></div>
    </aside>
    <main class="main">
        <header class="appbar">
            <button class="icon-btn menu-toggle" onclick="document.getElementById('drawer').classList.toggle('open')"><?= icon('menu') ?></button>
            <h1><?= e($title ?? 'Beranda') ?><?php if (!empty($sub)): ?><small><?= $sub ?></small><?php endif; ?></h1>
            <div class="acts"><?= $actions ?? '' ?></div>
        </header>
        <?php if ($flash): ?><div class="snack <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
