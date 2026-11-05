<?php
$status = $_GET['status'] ?? 'berjalan';
$w = []; $p = [];
if (isset(STATUS_PINJ[$status])) { $w[] = 'p.status = ?'; $p[] = $status; }
elseif ($status === 'tunggak') $w[] = "p.status = 'berjalan' AND EXISTS (SELECT 1 FROM angsuran a WHERE a.pinjaman_id = p.id AND a.dibayar_at IS NULL AND a.jatuh_tempo < CURDATE())";
$rows = q("SELECT p.*, ag.nama, ag.no_anggota, (SELECT COUNT(*) FROM angsuran WHERE pinjaman_id = p.id AND dibayar_at IS NOT NULL) bayar,
           (SELECT COALESCE(SUM(pokok),0) FROM angsuran WHERE pinjaman_id = p.id AND dibayar_at IS NULL) sisa,
           (SELECT MIN(jatuh_tempo) FROM angsuran WHERE pinjaman_id = p.id AND dibayar_at IS NULL) berikut
           FROM pinjaman p JOIN anggota ag ON ag.id = p.anggota_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY p.id DESC', $p)->fetchAll();
$hit = q('SELECT status, COUNT(*) FROM pinjaman GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$title = 'Pinjaman';
$actions = '<a class="btn btn-filled" href="' . url('pinjaman.ajukan') . '">' . icon('plus', 18) . ' Pengajuan baru</a>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="chips" style="margin-bottom:14px">
    <?php foreach (['berjalan' => 'Berjalan', 'tunggak' => 'Menunggak', 'diajukan' => 'Diajukan', 'disetujui' => 'Menunggu pencairan', 'lunas' => 'Lunas', 'ditolak' => 'Ditolak', 'semua' => 'Semua'] as $k => $l): ?>
        <a class="chip <?= $status === $k ? 'on' : '' ?>" href="<?= url('pinjaman', ['status' => $k]) ?>"><?= $l ?><?= isset($hit[$k]) ? ' · ' . $hit[$k] : '' ?></a>
    <?php endforeach; ?>
</div>
<div class="card outlined"><div class="table-wrap"><table>
    <thead><tr><th>Kode</th><th>Anggota</th><th class="text-right">Pinjaman</th><th>Tenor</th><th>Progres</th><th class="text-right">Sisa pokok</th><th>Berikutnya</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($rows as $r): $telat = $r['status'] === 'berjalan' && $r['berikut'] && $r['berikut'] < date('Y-m-d'); ?>
        <tr onclick="location.href='<?= url('pinjaman.detail', ['id' => $r['id']]) ?>'" style="cursor:pointer">
            <td class="num"><?= e($r['kode']) ?><small><?= e($r['metode']) ?></small></td><td><strong><?= e($r['nama']) ?></strong><small><?= e($r['tujuan']) ?></small></td>
            <td class="text-right num"><?= rupiah($r['jumlah']) ?></td><td><?= $r['tenor'] ?> bln</td>
            <td style="min-width:120px"><?php if (in_array($r['status'], ['berjalan', 'lunas'], true)): ?><div class="progress"><span style="width:<?= round($r['bayar'] / $r['tenor'] * 100) ?>%"></span></div><small><?= $r['bayar'] ?>/<?= $r['tenor'] ?></small><?php endif; ?></td>
            <td class="text-right num"><?= $r['status'] === 'berjalan' ? rupiah($r['sisa']) : '-' ?></td>
            <td><?= $r['status'] === 'berjalan' ? tgl($r['berikut']) . ($telat ? ' <span class="chip chip-red" style="height:22px">telat</span>' : '') : '-' ?></td>
            <td><?= badge(STATUS_PINJ, $r['status']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" class="empty">Tidak ada pinjaman.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
