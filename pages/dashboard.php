<?php
$totalSimpanan = (int) q("SELECT COALESCE(SUM(CASE WHEN arah='setor' THEN jumlah ELSE -jumlah END),0) FROM simpanan")->fetchColumn();
$outstanding = (int) q("SELECT COALESCE(SUM(a.pokok),0) FROM angsuran a JOIN pinjaman p ON p.id = a.pinjaman_id WHERE p.status = 'berjalan' AND a.dibayar_at IS NULL")->fetchColumn();
$tunggak = q("SELECT a.*, p.kode, p.anggota_id, ag.nama, ag.no_anggota, ag.telepon FROM angsuran a JOIN pinjaman p ON p.id = a.pinjaman_id JOIN anggota ag ON ag.id = p.anggota_id
              WHERE p.status = 'berjalan' AND a.dibayar_at IS NULL AND a.jatuh_tempo < CURDATE() ORDER BY a.jatuh_tempo")->fetchAll();
$nplPokok = (int) q("SELECT COALESCE(SUM(a.pokok),0) FROM angsuran a JOIN pinjaman p ON p.id = a.pinjaman_id WHERE p.status = 'berjalan' AND a.dibayar_at IS NULL
                     AND p.id IN (SELECT pinjaman_id FROM angsuran WHERE dibayar_at IS NULL AND jatuh_tempo < CURDATE() - INTERVAL 90 DAY)")->fetchColumn();
$jatuhTempo = q("SELECT a.*, p.kode, ag.nama FROM angsuran a JOIN pinjaman p ON p.id = a.pinjaman_id JOIN anggota ag ON ag.id = p.anggota_id
                 WHERE p.status = 'berjalan' AND a.dibayar_at IS NULL AND a.jatuh_tempo BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY ORDER BY a.jatuh_tempo")->fetchAll();
$pengajuan = q("SELECT p.*, ag.nama FROM pinjaman p JOIN anggota ag ON ag.id = p.anggota_id WHERE p.status = 'diajukan' ORDER BY p.tgl_pengajuan")->fetchAll();
$anggota = (int) q("SELECT COUNT(*) FROM anggota WHERE status = 'aktif'")->fetchColumn();

// Arus kas 6 bulan: masuk (setoran + angsuran + denda) vs keluar (pencairan + penarikan)
$kas = [];
for ($i = 5; $i >= 0; $i--) $kas[date('Y-m', strtotime("first day of -$i month"))] = ['in' => 0, 'out' => 0];
$awal = array_key_first($kas) . '-01';
foreach (q("SELECT DATE_FORMAT(tanggal,'%Y-%m') m, SUM(CASE WHEN arah='setor' THEN jumlah END) s, SUM(CASE WHEN arah='tarik' THEN jumlah END) t FROM simpanan WHERE tanggal >= ? GROUP BY m", [$awal]) as $r) {
    if (isset($kas[$r['m']])) { $kas[$r['m']]['in'] += (int) $r['s']; $kas[$r['m']]['out'] += (int) $r['t']; }
}
foreach (q("SELECT DATE_FORMAT(dibayar_at,'%Y-%m') m, SUM(total + denda) v FROM angsuran WHERE dibayar_at >= ? GROUP BY m", [$awal]) as $r) if (isset($kas[$r['m']])) $kas[$r['m']]['in'] += (int) $r['v'];
foreach (q("SELECT DATE_FORMAT(tgl_cair,'%Y-%m') m, SUM(diterima) v FROM pinjaman WHERE tgl_cair >= ? GROUP BY m", [$awal]) as $r) if (isset($kas[$r['m']])) $kas[$r['m']]['out'] += (int) $r['v'];
$maxK = max(1, ...array_values(array_map(fn ($k) => max($k), $kas)));

$title = 'Halo, ' . explode(' ', user()['nama'])[0];
$sub = tgl(date('Y-m-d')) . ' · ' . $anggota . ' anggota aktif';
$actions = '<a class="btn btn-tonal" href="' . url('pinjaman.ajukan') . '">' . icon('calc', 18) . ' Simulasi & ajukan pinjaman</a>';
require __DIR__ . '/../includes/header.php';
?>
<div class="stats">
    <div class="stat hero"><small>Total simpanan anggota</small><strong><?= rupiah($totalSimpanan) ?></strong><span>pokok, wajib & sukarela</span></div>
    <div class="stat tonal"><small style="color:inherit">Pinjaman beredar</small><strong><?= rupiah($outstanding) ?></strong><span style="color:inherit">sisa pokok belum kembali</span></div>
    <div class="stat tert"><small style="color:inherit">Angsuran menunggak</small><strong><?= count($tunggak) ?></strong><span style="color:inherit"><?= rupiah(array_sum(array_column($tunggak, 'total'))) ?></span></div>
    <div class="stat"><small>NPL (kurang lancar + macet)</small><strong><?= $outstanding ? number_format($nplPokok / $outstanding * 100, 1, ",", ".") : 0 ?>%</strong><span><?= rupiah($nplPokok) ?></span></div>
</div>
<div class="grid-2">
    <div class="card">
        <div class="card-head"><h2>Arus kas 6 bulan</h2><div class="legend"><span><i style="background:var(--primary)"></i>Masuk</span><span><i style="background:var(--tertiary)"></i>Keluar</span></div></div>
        <div class="card-body"><div class="bars">
            <?php foreach ($kas as $m => $k): ?><div class="bar-col" title="<?= bulan_id($m) ?>: masuk <?= rupiah($k['in']) ?>, keluar <?= rupiah($k['out']) ?>">
                <div class="bar-pair"><div class="bar" style="height:<?= round($k['in'] / $maxK * 100) ?>%"></div><div class="bar out" style="height:<?= round($k['out'] / $maxK * 100) ?>%"></div></div>
                <small><?= substr(bulan_id($m), 0, 3) ?></small></div><?php endforeach; ?>
        </div></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Pengajuan menunggu</h2><a href="<?= url('pinjaman', ['status' => 'diajukan']) ?>">Semua</a></div>
        <div class="table-wrap"><table><tbody>
            <?php foreach ($pengajuan as $p): ?><tr><td><strong><?= e($p['nama']) ?></strong><small><?= e($p['tujuan']) ?> · <?= $p['tenor'] ?> bln</small></td><td class="text-right num"><?= rupiah($p['jumlah']) ?></td><td class="actions"><a class="btn btn-sm btn-tonal" href="<?= url('pinjaman.detail', ['id' => $p['id']]) ?>">Tinjau</a></td></tr><?php endforeach; ?>
            <?php if (!$pengajuan): ?><tr><td class="empty">Tidak ada pengajuan baru.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
</div>
<div class="grid-2 mt">
    <div class="card">
        <div class="card-head"><h2>Angsuran menunggak</h2><span class="chip chip-red"><?= count($tunggak) ?></span></div>
        <div class="table-wrap" style="max-height:360px;overflow:auto"><table>
            <thead><tr><th>Anggota</th><th>Pinjaman</th><th>Jatuh tempo</th><th class="text-right">Tagihan + denda</th></tr></thead>
            <tbody><?php foreach ($tunggak as $a): ?><tr onclick="location.href='<?= url('pinjaman.detail', ['id' => $a['pinjaman_id']]) ?>'" style="cursor:pointer">
                <td><strong><?= e($a['nama']) ?></strong><small><?= e($a['no_anggota']) ?></small></td><td><?= e($a['kode']) ?><small>angsuran ke-<?= $a['ke'] ?></small></td>
                <td><?= tgl($a['jatuh_tempo']) ?><small style="color:var(--red)"><?= (int) floor((time() - strtotime($a['jatuh_tempo'])) / 86400) ?> hari</small></td>
                <td class="text-right num"><strong><?= rupiah($a['total'] + denda_angsuran($a)) ?></strong><small>denda <?= rupiah(denda_angsuran($a)) ?></small></td></tr><?php endforeach; ?></tbody>
        </table></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Jatuh tempo 7 hari</h2></div>
        <div class="table-wrap"><table><tbody>
            <?php foreach ($jatuhTempo as $a): ?><tr><td><strong><?= e($a['nama']) ?></strong><small><?= e($a['kode']) ?> · ke-<?= $a['ke'] ?></small></td><td><?= tgl($a['jatuh_tempo']) ?></td><td class="text-right num"><?= rupiah($a['total']) ?></td></tr><?php endforeach; ?>
            <?php if (!$jatuhTempo): ?><tr><td class="empty">Tidak ada.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
