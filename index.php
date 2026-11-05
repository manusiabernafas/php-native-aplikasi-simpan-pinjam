<?php
require __DIR__ . '/includes/bootstrap.php';

$routes = [
    'login'          => 'pages/login.php',
    'logout'         => 'pages/logout.php',
    'dashboard'      => 'pages/dashboard.php',
    'anggota'        => 'pages/anggota/index.php',
    'anggota.form'   => 'pages/anggota/form.php',
    'anggota.detail' => 'pages/anggota/detail.php',
    'simpanan'       => 'pages/simpanan/index.php',
    'simpanan.wajib' => 'pages/simpanan/wajib.php',
    'pinjaman'       => 'pages/pinjaman/index.php',
    'pinjaman.ajukan'=> 'pages/pinjaman/ajukan.php',
    'pinjaman.detail'=> 'pages/pinjaman/detail.php',
    'pinjaman.aksi'  => 'pages/pinjaman/aksi.php',
    'bukti'          => 'pages/bukti.php',
    'laporan'        => 'pages/laporan.php',
    'pengaturan'     => 'pages/pengaturan.php',
];

$page = $_GET['page'] ?? 'dashboard';
if (!isset($routes[$page])) {
    http_response_code(404);
    $page = 'dashboard';
}
if ($page !== 'login') require_login();
verify_csrf();

require __DIR__ . '/' . $routes[$page];
