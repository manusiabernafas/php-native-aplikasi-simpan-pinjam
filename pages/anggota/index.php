<?php
$cari = trim($_GET['cari'] ?? '');
$rows = q("SELECT a.*, COALESCE(SUM(CASE WHEN s.arah='setor' THEN s.jumlah ELSE -s.jumlah END),0) simpanan,
           (SELECT COALESCE(SUM(an.pokok),0) FROM angsuran an JOIN pinjaman p ON p.id = an.pinjaman_id WHERE p.anggota_id = a.id AND p.status = 'berjalan' AND an.dibayar_at IS NULL) sisa_pinjaman,
           (SELECT COUNT(*) FROM angsuran an JOIN pinjaman p ON p.id = an.pinjaman_id WHERE p.anggota_id = a.id AND p.status = 'berjalan' AND an.dibayar_at IS NULL AND an.jatuh_tempo < CURDATE()) tunggak
           FROM anggota a LEFT JOIN simpanan s ON s.anggota_id = a.id WHERE a.nama LIKE ? OR a.no_anggota LIKE ? OR a.nik LIKE ? GROUP BY a.id ORDER BY a.no_anggota", ["%$cari%", "%$cari%", "%$cari%"])->fetchAll();
$title = 'Anggota';
$sub = count($rows) . ' anggota';
$actions = '<form class="filters" method="get"><input type="hidden" name="page" value="anggota"><input type="search" name="cari" value="' . e($cari) . '" placeholder="Cari nama / no. anggota / NIK"></form><a class="btn btn-filled" href="' . url('anggota.form') . '">' . icon('plus', 18) . ' Anggota baru</a>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="card outlined"><div class="table-wrap"><table>
    <thead><tr><th>No. anggota</th><th>Nama</th><th>Pekerjaan</th><th>Bergabung</th><th class="text-right">Simpanan</th><th class="text-right">Sisa pinjaman</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($rows as $a): ?>
        <tr onclick="location.href='<?= url('anggota.detail', ['id' => $a['id']]) ?>'" style="cursor:pointer">
            <td class="num"><?= e($a['no_anggota']) ?></td><td><strong><?= e($a['nama']) ?></strong><small><?= e($a['telepon']) ?></small></td><td><?= e($a['pekerjaan']) ?></td><td><?= tgl($a['tgl_gabung']) ?></td>
            <td class="text-right num"><?= rupiah($a['simpanan']) ?></td><td class="text-right num"><?= $a['sisa_pinjaman'] ? rupiah($a['sisa_pinjaman']) : '-' ?></td>
            <td><?= $a['tunggak'] ? '<span class="chip chip-red">Menunggak ' . $a['tunggak'] . '</span>' : ($a['status'] === 'aktif' ? '<span class="chip chip-green">Aktif</span>' : '<span class="chip chip-gray">Keluar</span>') ?></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
