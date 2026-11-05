<?php
$s = q('SELECT s.*, a.nama, a.no_anggota, u.nama petugas FROM simpanan s JOIN anggota a ON a.id = s.anggota_id LEFT JOIN users u ON u.id = s.user_id WHERE s.id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$s) redirect('simpanan');
$saldo = saldo_simpanan((int) $s['anggota_id'], $s['jenis']);
$title = 'Bukti transaksi';
$sub = e($s['kode']);
$actions = '<button class="btn btn-filled" onclick="window.print()">' . icon('printer', 18) . ' Cetak</button><a class="btn" href="' . url('simpanan') . '">Transaksi baru</a>';
require __DIR__ . '/../includes/header.php';
?>
<div class="slip">
    <div style="text-align:center;margin-bottom:14px"><b style="font-size:17px"><?= e(setting()['nama']) ?></b><div class="muted"><?= e(setting()['alamat']) ?></div></div>
    <div style="text-align:center;margin-bottom:16px"><span class="chip <?= $s['arah'] === 'setor' ? 'chip-green' : 'chip-red' ?>"><?= $s['arah'] === 'setor' ? 'BUKTI SETORAN' : 'BUKTI PENARIKAN' ?></span></div>
    <div class="row"><span class="muted">No. transaksi</span><b><?= e($s['kode']) ?></b></div>
    <div class="row"><span class="muted">Tanggal</span><span><?= tgl($s['tanggal']) ?></span></div>
    <div class="row"><span class="muted">Anggota</span><span><?= e($s['nama']) ?> (<?= e($s['no_anggota']) ?>)</span></div>
    <div class="row"><span class="muted">Jenis</span><span><?= JENIS_SIMPANAN[$s['jenis']] ?><?= $s['periode'] ? ' · ' . bulan_id($s['periode']) : '' ?></span></div>
    <div class="row"><span class="muted">Jumlah</span><b style="font-size:18px"><?= rupiah($s['jumlah']) ?></b></div>
    <div class="row"><span class="muted">Saldo <?= strtolower(JENIS_SIMPANAN[$s['jenis']]) ?></span><span><?= rupiah($saldo) ?></span></div>
    <div style="display:flex;justify-content:space-between;margin-top:34px;text-align:center"><div>Anggota<br><br><br>(<?= e($s['nama']) ?>)</div><div>Petugas<br><br><br>(<?= e($s['petugas'] ?? '-') ?>)</div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
