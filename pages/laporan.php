<?php
$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$P = [$dari, $sampai];

// Arus kas periode
$simp = q("SELECT jenis, arah, SUM(jumlah) t FROM simpanan WHERE tanggal BETWEEN ? AND ? GROUP BY jenis, arah", $P)->fetchAll();
$setor = []; $tarik = 0;
foreach ($simp as $s) { if ($s['arah'] === 'setor') $setor[$s['jenis']] = (int) $s['t']; else $tarik += (int) $s['t']; }
$ang = q('SELECT COALESCE(SUM(pokok),0) pokok, COALESCE(SUM(bunga),0) bunga, COALESCE(SUM(denda),0) denda, COUNT(*) n FROM angsuran WHERE dibayar_at BETWEEN ? AND ?', $P)->fetch();
$cair = q("SELECT COALESCE(SUM(jumlah),0) jumlah, COALESCE(SUM(provisi),0) provisi, COALESCE(SUM(diterima),0) diterima, COUNT(*) n FROM pinjaman WHERE tgl_cair BETWEEN ? AND ?", $P)->fetch();
$masuk = array_sum($setor) + $ang['pokok'] + $ang['bunga'] + $ang['denda'] + $cair['provisi'];
$keluar = $tarik + $cair['jumlah'];
$pendapatan = $ang['bunga'] + $ang['denda'] + $cair['provisi'];

// Posisi saat ini
$saldo = q("SELECT jenis, SUM(CASE WHEN arah='setor' THEN jumlah ELSE -jumlah END) t FROM simpanan GROUP BY jenis")->fetchAll(PDO::FETCH_KEY_PAIR);
$outstanding = (int) q("SELECT COALESCE(SUM(a.pokok),0) FROM angsuran a JOIN pinjaman p ON p.id = a.pinjaman_id WHERE p.status = 'berjalan' AND a.dibayar_at IS NULL")->fetchColumn();

// Kolektibilitas: dilihat dari angsuran tertua yang belum dibayar
$kol = q("SELECT p.id, p.kode, ag.nama, ag.telepon, MIN(a.jatuh_tempo) tertua, COUNT(CASE WHEN a.jatuh_tempo < CURDATE() THEN 1 END) n_telat,
          SUM(CASE WHEN a.jatuh_tempo < CURDATE() THEN a.total ELSE 0 END) tunggakan, SUM(a.pokok) sisa
          FROM pinjaman p JOIN anggota ag ON ag.id = p.anggota_id JOIN angsuran a ON a.pinjaman_id = p.id AND a.dibayar_at IS NULL
          WHERE p.status = 'berjalan' GROUP BY p.id ORDER BY tertua")->fetchAll();
$kelas = ['Lancar' => [0, 0], 'Dalam perhatian (1–90 hr)' => [0, 0], 'Kurang lancar (91–180 hr)' => [0, 0], 'Macet (>180 hr)' => [0, 0]];
$telatList = [];
foreach ($kol as $k) {
    $hari = max(0, (int) ((strtotime(date('Y-m-d')) - strtotime($k['tertua'])) / 86400));
    $kls = $hari === 0 ? 'Lancar' : ($hari <= 90 ? 'Dalam perhatian (1–90 hr)' : ($hari <= 180 ? 'Kurang lancar (91–180 hr)' : 'Macet (>180 hr)'));
    $kelas[$kls][0]++; $kelas[$kls][1] += $k['sisa'];
    if ($hari) $telatList[] = $k + ['hari' => $hari, 'kelas' => $kls];
}
$npl = $outstanding ? ($kelas['Kurang lancar (91–180 hr)'][1] + $kelas['Macet (>180 hr)'][1]) / $outstanding * 100 : 0;

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tunggakan-koperasi-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Kode', 'Anggota', 'Telepon', 'Angsuran telat', 'Hari telat', 'Tunggakan', 'Sisa pokok', 'Kolektibilitas'], ';', '"', '');
    foreach ($telatList as $t) fputcsv($o, [$t['kode'], $t['nama'], $t['telepon'], $t['n_telat'], $t['hari'], $t['tunggakan'], $t['sisa'], $t['kelas']], ';', '"', '');
    exit;
}

$title = 'Laporan';
$sub = tgl($dari) . ' – ' . tgl($sampai);
$actions = '<a class="btn btn-outlined" href="' . url('laporan', ['dari' => $dari, 'sampai' => $sampai, 'export' => 'csv']) . '">' . icon('arrow-down', 18) . ' CSV tunggakan</a><button class="btn btn-filled" onclick="window.print()">' . icon('printer', 18) . ' Cetak</button>';
require __DIR__ . '/../includes/header.php';
?>
<form method="get" class="card outlined no-print" style="margin-bottom:20px"><div class="card-body filters" style="align-items:end"><input type="hidden" name="page" value="laporan">
    <div><label>Dari</label><input type="date" name="dari" value="<?= e($dari) ?>"></div><div><label>Sampai</label><input type="date" name="sampai" value="<?= e($sampai) ?>"></div>
    <button class="btn btn-tonal">Tampilkan</button></div></form>

<div class="stats">
    <div class="stat hero"><small>Kas masuk</small><strong><?= rupiah($masuk) ?></strong></div>
    <div class="stat tonal"><small>Kas keluar</small><strong><?= rupiah($keluar) ?></strong></div>
    <div class="stat"><small>Pendapatan jasa</small><strong><?= rupiah($pendapatan) ?></strong></div>
    <div class="stat tert"><small>NPL (kurang lancar + macet)</small><strong><?= number_format($npl, 1, ',', '.') ?>%</strong></div>
</div>

<div class="grid-2 mt">
    <div class="card outlined"><div class="card-head"><h2>Arus kas periode</h2></div><div class="card-body">
        <dl class="kv">
            <?php foreach (JENIS_SIMPANAN as $k => $l): ?><dt>Setoran <?= strtolower($l) ?></dt><dd><?= rupiah($setor[$k] ?? 0) ?></dd><?php endforeach; ?>
            <dt>Angsuran pokok (<?= $ang['n'] ?>x)</dt><dd><?= rupiah($ang['pokok']) ?></dd>
            <dt>Bunga pinjaman</dt><dd><?= rupiah($ang['bunga']) ?></dd>
            <dt>Denda keterlambatan</dt><dd><?= rupiah($ang['denda']) ?></dd>
            <dt>Provisi</dt><dd><?= rupiah($cair['provisi']) ?></dd>
            <dt><b>Total masuk</b></dt><dd><b><?= rupiah($masuk) ?></b></dd>
            <dt>Penarikan sukarela</dt><dd style="color:var(--red)">- <?= rupiah($tarik) ?></dd>
            <dt>Pencairan pinjaman (<?= $cair['n'] ?>)</dt><dd style="color:var(--red)">- <?= rupiah($cair['jumlah']) ?></dd>
            <dt><b>Kas bersih</b></dt><dd><b><?= rupiah($masuk - $keluar) ?></b></dd>
        </dl></div></div>
    <div>
        <div class="card outlined"><div class="card-head"><h2>Posisi saat ini</h2></div><div class="card-body"><dl class="kv">
            <?php foreach (JENIS_SIMPANAN as $k => $l): ?><dt><?= $l ?></dt><dd><?= rupiah($saldo[$k] ?? 0) ?></dd><?php endforeach; ?>
            <dt><b>Total simpanan anggota</b></dt><dd><b><?= rupiah(array_sum($saldo)) ?></b></dd>
            <dt><b>Piutang pinjaman (outstanding)</b></dt><dd><b><?= rupiah($outstanding) ?></b></dd>
        </dl></div></div>
        <div class="card outlined mt"><div class="card-head"><h2>Kolektibilitas pinjaman</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Kelas</th><th>Pinjaman</th><th class="text-right">Sisa pokok</th></tr></thead>
            <tbody><?php foreach ($kelas as $l => [$n, $t]): ?><tr><td><?= $l ?></td><td><?= $n ?></td><td class="text-right num"><?= rupiah($t) ?></td></tr><?php endforeach; ?></tbody>
        </table></div></div>
    </div>
</div>

<div class="card outlined mt"><div class="card-head"><h2>Daftar tunggakan</h2><span class="muted"><?= count($telatList) ?> pinjaman</span></div><div class="table-wrap"><table>
    <thead><tr><th>Kode</th><th>Anggota</th><th>Angsuran telat</th><th>Hari telat</th><th class="text-right">Tunggakan</th><th class="text-right">Sisa pokok</th><th>Kelas</th></tr></thead>
    <tbody><?php foreach ($telatList as $t): ?><tr><td><a href="<?= url('pinjaman.detail', ['id' => $t['id']]) ?>"><?= e($t['kode']) ?></a></td><td><?= e($t['nama']) ?><small><?= e($t['telepon']) ?></small></td>
        <td><?= $t['n_telat'] ?>x</td><td><?= $t['hari'] ?> hari</td><td class="text-right num"><?= rupiah($t['tunggakan']) ?></td><td class="text-right num"><?= rupiah($t['sisa']) ?></td>
        <td><span class="chip <?= $t['hari'] > 90 ? 'chip-red' : 'chip-amber' ?>"><?= e(strtok($t['kelas'], ' (')) ?></span></td></tr><?php endforeach; ?>
    <?php if (!$telatList): ?><tr><td colspan="7" class="empty">Tidak ada tunggakan.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
