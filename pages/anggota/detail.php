<?php
$a = q('SELECT * FROM anggota WHERE id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$a) redirect('anggota');
$saldo = []; foreach (JENIS_SIMPANAN as $k => $_) $saldo[$k] = saldo_simpanan((int) $a['id'], $k);
$mutasi = q('SELECT * FROM simpanan WHERE anggota_id = ? ORDER BY tanggal DESC, id DESC LIMIT 40', [$a['id']])->fetchAll();
$pinjaman = q("SELECT p.*, (SELECT COUNT(*) FROM angsuran WHERE pinjaman_id = p.id AND dibayar_at IS NOT NULL) lunas_ke,
               (SELECT COALESCE(SUM(pokok),0) FROM angsuran WHERE pinjaman_id = p.id AND dibayar_at IS NULL) sisa FROM pinjaman p WHERE p.anggota_id = ? ORDER BY p.id DESC", [$a['id']])->fetchAll();
$wajibBulanIni = q("SELECT 1 FROM simpanan WHERE anggota_id = ? AND jenis = 'wajib' AND periode = ?", [$a['id'], date('Y-m')])->fetchColumn();
$plafon = array_sum($saldo) * setting()['maks_kali_simpanan'];
$title = $a['nama'];
$sub = e($a['no_anggota']) . ' · bergabung ' . tgl($a['tgl_gabung']) . ' · ' . e($a['pekerjaan']);
$actions = '<a class="btn" href="' . url('anggota.form', ['id' => $a['id']]) . '">Ubah</a><a class="btn btn-tonal" href="' . url('simpanan', ['anggota' => $a['id']]) . '">' . icon('piggy', 18) . ' Setor / tarik</a><a class="btn btn-filled" href="' . url('pinjaman.ajukan', ['anggota' => $a['id']]) . '">' . icon('hand', 18) . ' Ajukan pinjaman</a>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="stats">
    <div class="stat hero"><small>Total simpanan</small><strong><?= rupiah(array_sum($saldo)) ?></strong><span>plafon pinjaman <?= rupiah($plafon) ?></span></div>
    <?php foreach (JENIS_SIMPANAN as $k => $l): ?><div class="stat"><small><?= $l ?></small><strong><?= rupiah($saldo[$k]) ?></strong><?php if ($k === 'wajib'): ?><span><?= $wajibBulanIni ? 'Bulan ini sudah bayar' : '<span style="color:var(--red)">Bulan ini belum bayar</span>' ?></span><?php endif; ?></div><?php endforeach; ?>
</div>
<div class="grid-2">
    <div class="card"><div class="card-head"><h2>Buku simpanan</h2><span class="muted">40 mutasi terakhir</span></div>
        <div class="table-wrap" style="max-height:480px;overflow:auto"><table>
            <thead><tr><th>Tanggal</th><th>Keterangan</th><th class="text-right">Masuk</th><th class="text-right">Keluar</th></tr></thead>
            <tbody><?php foreach ($mutasi as $m): ?><tr><td><?= tgl($m['tanggal']) ?><small><?= e($m['kode']) ?></small></td><td><?= JENIS_SIMPANAN[$m['jenis']] ?><small><?= e($m['periode'] ? bulan_id($m['periode']) : $m['keterangan']) ?></small></td>
                <td class="text-right num"><?= $m['arah'] === 'setor' ? rupiah($m['jumlah']) : '' ?></td><td class="text-right num" style="color:var(--red)"><?= $m['arah'] === 'tarik' ? rupiah($m['jumlah']) : '' ?></td></tr><?php endforeach; ?></tbody>
        </table></div></div>
    <div class="card"><div class="card-head"><h2>Riwayat pinjaman</h2></div><div class="card-body">
        <?php foreach ($pinjaman as $p): $pct = $p['tenor'] ? round($p['lunas_ke'] / $p['tenor'] * 100) : 0; ?>
            <a href="<?= url('pinjaman.detail', ['id' => $p['id']]) ?>" class="card outlined" style="display:block;padding:14px;margin-bottom:10px;color:inherit">
                <div style="display:flex;justify-content:space-between;align-items:center"><b class="num"><?= rupiah($p['jumlah']) ?></b><?= badge(STATUS_PINJ, $p['status']) ?></div>
                <div class="muted" style="font-size:13px;margin:4px 0 10px"><?= e($p['kode']) ?> · <?= $p['tenor'] ?> bulan · <?= e($p['metode']) ?> · <?= e($p['tujuan']) ?></div>
                <?php if (in_array($p['status'], ['berjalan', 'lunas'], true)): ?><div class="progress"><span style="width:<?= $pct ?>%"></span></div><div class="muted" style="font-size:12.5px;margin-top:6px"><?= $p['lunas_ke'] ?>/<?= $p['tenor'] ?> angsuran · sisa pokok <?= rupiah($p['sisa']) ?></div><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (!$pinjaman): ?><p class="muted">Belum pernah meminjam.</p><?php endif; ?>
    </div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
