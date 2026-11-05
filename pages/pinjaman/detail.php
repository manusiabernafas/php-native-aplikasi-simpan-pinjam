<?php
$p = q('SELECT p.*, a.nama, a.no_anggota, a.telepon, u.nama penyetuju FROM pinjaman p JOIN anggota a ON a.id = p.anggota_id
        LEFT JOIN users u ON u.id = p.disetujui_oleh WHERE p.id = ?', [(int) ($_GET['id'] ?? 0)])->fetch();
if (!$p) redirect('pinjaman');
$angs = q('SELECT * FROM angsuran WHERE pinjaman_id = ? ORDER BY ke', [$p['id']])->fetchAll();
$jadwal = $angs ?: jadwal_angsuran((int) $p['jumlah'], (float) $p['bunga_persen'], (int) $p['tenor'], $p['metode']);
$lunasKe = count(array_filter($angs, fn ($a) => $a['dibayar_at']));
$belum = array_values(array_filter($angs, fn ($a) => !$a['dibayar_at']));
$sisaPokok = array_sum(array_column($belum, 'pokok'));
$tunggak = array_values(array_filter($belum, fn ($a) => $a['jatuh_tempo'] < date('Y-m-d')));
$dendaBerjalan = array_sum(array_map('denda_angsuran', $tunggak));
$dibayar = array_sum(array_map(fn ($a) => $a['dibayar_at'] ? $a['total'] + $a['denda'] : 0, $angs));

// Estimasi pelunasan dipercepat (logika sama dengan aksi 'lunasi')
$estLunas = 0; $estHemat = 0; $penuh = true;
foreach ($belum as $a) {
    if ($a['jatuh_tempo'] <= date('Y-m-d') || $penuh) {
        $estLunas += $a['total'] + denda_angsuran($a);
        if ($a['jatuh_tempo'] > date('Y-m-d')) $penuh = false;
    } else { $estLunas += $a['pokok']; $estHemat += $a['bunga']; }
}
$title = 'Pinjaman ' . $p['kode'];
$sub = e($p['nama']) . ' · ' . e($p['no_anggota']);
$actions = '<button class="btn btn-outlined" onclick="window.print()">' . icon('printer', 18) . ' Cetak</button>';
require __DIR__ . '/../../includes/header.php';
?>
<div class="grid-2 rev">
    <div>
        <div class="card"><div class="card-head"><h2><?= rupiah($p['jumlah']) ?></h2><?= badge(STATUS_PINJ, $p['status']) ?></div><div class="card-body">
            <dl class="kv">
                <dt>Tujuan</dt><dd><?= e($p['tujuan']) ?></dd>
                <dt>Tenor & bunga</dt><dd><?= $p['tenor'] ?> bulan · <?= (float) $p['bunga_persen'] ?>%/bln · <?= ucfirst($p['metode']) ?></dd>
                <dt>Diajukan</dt><dd><?= tgl($p['tgl_pengajuan']) ?></dd>
                <?php if ($p['penyetuju']): ?><dt>Diputuskan oleh</dt><dd><?= e($p['penyetuju']) ?></dd><?php endif; ?>
                <?php if ($p['tgl_cair']): ?><dt>Dicairkan</dt><dd><?= tgl($p['tgl_cair']) ?> · diterima <?= rupiah($p['diterima']) ?> (provisi <?= rupiah($p['provisi']) ?>)</dd><?php endif; ?>
                <?php if ($p['catatan']): ?><dt>Catatan</dt><dd><?= e($p['catatan']) ?></dd><?php endif; ?>
            </dl>
            <?php if ($angs): ?>
                <div class="progress mt" style="margin-top:18px"><span style="width:<?= round($lunasKe / $p['tenor'] * 100) ?>%"></span></div>
                <div class="muted" style="font-size:13px;margin-top:6px"><?= $lunasKe ?> dari <?= $p['tenor'] ?> angsuran lunas · total dibayar <?= rupiah($dibayar) ?></div>
            <?php endif; ?>
        </div></div>

        <?php if ($p['status'] === 'diajukan'): ?>
            <div class="card mt no-print"><div class="card-head"><h2>Keputusan ketua</h2></div><div class="card-body">
                <?php if (punya_role('ketua')): ?>
                    <form method="post" action="<?= url('pinjaman.aksi') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <label>Catatan (wajib jika ditolak)</label><input type="text" name="catatan" placeholder="Contoh: disetujui dengan jaminan BPKB">
                        <div class="form-actions"><button class="btn btn-outlined" name="aksi" value="tolak"><?= icon('x', 18) ?> Tolak</button><button class="btn btn-filled" name="aksi" value="setujui"><?= icon('check', 18) ?> Setujui</button></div>
                    </form>
                <?php else: ?><p class="muted" style="margin:0">Menunggu persetujuan ketua koperasi.</p><?php endif; ?>
            </div></div>
        <?php elseif ($p['status'] === 'disetujui'): ?>
            <div class="card mt no-print"><div class="card-head"><h2>Pencairan dana</h2></div><div class="card-body">
                <dl class="kv"><dt>Pinjaman</dt><dd><?= rupiah($p['jumlah']) ?></dd><dt>Provisi <?= (float) setting()['provisi_persen'] ?>%</dt><dd>- <?= rupiah(round($p['jumlah'] * setting()['provisi_persen'] / 100)) ?></dd>
                    <dt>Diterima anggota</dt><dd><b><?= rupiah($p['jumlah'] - round($p['jumlah'] * setting()['provisi_persen'] / 100)) ?></b></dd></dl>
                <?php if (punya_role('ketua', 'bendahara')): ?>
                    <form method="post" action="<?= url('pinjaman.aksi') ?>" class="form-actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn btn-filled" name="aksi" value="cair"><?= icon('wallet', 18) ?> Cairkan & buat jadwal</button></form>
                <?php else: ?><p class="muted">Pencairan dilakukan bendahara.</p><?php endif; ?>
            </div></div>
        <?php elseif ($p['status'] === 'berjalan'): ?>
            <div class="card mt no-print"><div class="card-head"><h2>Bayar angsuran</h2><span class="muted">sisa pokok <?= rupiah($sisaPokok) ?></span></div><div class="card-body">
                <?php if ($tunggak): ?><div class="note" style="margin-bottom:14px"><?= icon('alert', 18) ?> <?= count($tunggak) ?> angsuran lewat jatuh tempo. Denda berjalan <?= rupiah($dendaBerjalan) ?>.</div><?php endif; ?>
                <form method="post" action="<?= url('pinjaman.aksi') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <label>Jumlah angsuran dibayar</label>
                    <select name="jumlah_angsuran" id="nAng"><?php $acc = 0; foreach (array_slice($belum, 0, 6) as $i => $a): $acc += $a['total'] + denda_angsuran($a); ?><option value="<?= $i + 1 ?>" <?= $i + 1 === max(1, count($tunggak)) ? 'selected' : '' ?>><?= $i + 1 ?> angsuran (ke-<?= $belum[0]['ke'] ?><?= $i ? '–' . $a['ke'] : '' ?>) · <?= rupiah($acc) ?></option><?php endforeach; ?></select>
                    <div class="form-actions"><button class="btn btn-filled" name="aksi" value="bayar"><?= icon('receipt', 18) ?> Terima pembayaran</button></div>
                </form>
                <?php if (punya_role('ketua', 'bendahara')): ?>
                    <hr style="border:0;border-top:1px solid var(--outline-variant);margin:18px 0">
                    <form method="post" action="<?= url('pinjaman.aksi') ?>" onsubmit="return confirm('Lunasi seluruh pinjaman sebesar <?= rupiah($estLunas) ?>?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><b>Pelunasan dipercepat</b><div class="muted" style="font-size:13px">Bayar <?= rupiah($estLunas) ?> · hemat bunga <?= rupiah($estHemat) ?></div></div>
                        <button class="btn btn-tonal" name="aksi" value="lunasi"><?= icon('check', 18) ?> Lunasi sekarang</button></div>
                    </form>
                <?php endif; ?>
            </div></div>
        <?php endif; ?>
    </div>

    <div class="card outlined"><div class="card-head"><h2><?= $angs ? 'Kartu angsuran' : 'Rencana angsuran' ?></h2><span class="muted"><?= ucfirst($p['metode']) ?></span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Ke</th><th>Jatuh tempo</th><th class="text-right">Pokok</th><th class="text-right">Bunga</th><th class="text-right">Angsuran</th><th class="text-right">Denda</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($jadwal as $a): $paid = $a['dibayar_at'] ?? null; $jt = $a['jatuh_tempo'] ?? null; $late = !$paid && $jt && $jt < date('Y-m-d'); $dn = $jt ? denda_angsuran($a) : 0; ?>
                <tr><td><?= $a['ke'] ?></td><td class="nw"><?= $jt ? tgl($jt) : "-" ?></td><td class="text-right num"><?= rupiah($a['pokok']) ?></td><td class="text-right num"><?= rupiah($a['bunga']) ?></td>
                    <td class="text-right num"><b><?= rupiah($a['total']) ?></b></td><td class="text-right num"><?= $dn ? rupiah($dn) : '-' ?></td>
                    <td><?= $paid ? '<span class="chip chip-green">' . tgl($paid) . '</span>' : ($late ? '<span class="chip chip-red">Telat ' . (int) ((strtotime(date('Y-m-d')) - strtotime($jt)) / 86400) . ' hr</span>' : ($jt ? '<span class="chip chip-gray">Belum</span>' : '')) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
