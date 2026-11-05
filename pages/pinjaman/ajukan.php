<?php
$set = setting();
$anggotaList = q("SELECT id, no_anggota, nama FROM anggota WHERE status = 'aktif' ORDER BY nama")->fetchAll();
$d = [
    'anggota_id' => (int) ($_REQUEST['anggota'] ?? $_REQUEST['anggota_id'] ?? 0),
    'jumlah' => max(0, (int) ($_REQUEST['jumlah'] ?? 5000000)),
    'tenor' => min(36, max(1, (int) ($_REQUEST['tenor'] ?? 12))),
    'metode' => ($_REQUEST['metode'] ?? 'flat') === 'anuitas' ? 'anuitas' : 'flat',
    'tujuan' => trim($_REQUEST['tujuan'] ?? ''),
];
$bunga = (float) $set['bunga_default'];
$jadwal = $d['jumlah'] ? jadwal_angsuran($d['jumlah'], $bunga, $d['tenor'], $d['metode']) : [];
$pembanding = $d['jumlah'] ? jadwal_angsuran($d['jumlah'], $bunga, $d['tenor'], $d['metode'] === 'flat' ? 'anuitas' : 'flat') : [];
$info = null;
if ($d['anggota_id']) {
    $simp = saldo_simpanan($d['anggota_id']);
    $aktif = (int) q("SELECT COUNT(*) FROM pinjaman WHERE anggota_id = ? AND status IN ('diajukan','disetujui','berjalan')", [$d['anggota_id']])->fetchColumn();
    $info = ['simpanan' => $simp, 'plafon' => $simp * $set['maks_kali_simpanan'], 'aktif' => $aktif];
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$info) throw new RuntimeException('Pilih anggota.');
        if ($d['jumlah'] < 500000) throw new RuntimeException('Pinjaman minimal Rp 500.000.');
        if ($d['jumlah'] > $info['plafon']) throw new RuntimeException('Melebihi plafon ' . $set['maks_kali_simpanan'] . 'x total simpanan (' . rupiah($info['plafon']) . ').');
        if ($info['aktif']) throw new RuntimeException('Anggota masih punya pinjaman yang belum selesai.');
        if ($d['tujuan'] === '') throw new RuntimeException('Isi tujuan pinjaman.');
        q('INSERT INTO pinjaman (kode, anggota_id, jumlah, tenor, bunga_persen, metode, tujuan, tgl_pengajuan) VALUES (?,?,?,?,?,?,?,CURDATE())', ['TMP-' . bin2hex(random_bytes(4)), $d['anggota_id'], $d['jumlah'], $d['tenor'], $bunga, $d['metode'], $d['tujuan']]);
        $id = (int) db()->lastInsertId();
        q('UPDATE pinjaman SET kode = ? WHERE id = ?', ['PJ-' . date('ym') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT), $id]);
        flash('Pengajuan pinjaman tersimpan dan menunggu persetujuan ketua.');
        redirect('pinjaman.detail', ['id' => $id]);
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}
$tot = fn ($j) => array_sum(array_column($j, 'total'));
$title = 'Simulasi & pengajuan pinjaman';
$sub = 'Bunga ' . $bunga . '% per bulan · provisi ' . $set['provisi_persen'] . '% dipotong saat pencairan';
require __DIR__ . '/../../includes/header.php';
?>
<?php if ($error): ?><div class="snack error"><?= e($error) ?></div><?php endif; ?>
<div class="grid-2">
    <div>
        <form method="get" class="card"><input type="hidden" name="page" value="pinjaman.ajukan">
            <div class="card-head"><h2>Simulasi</h2></div>
            <div class="card-body"><div class="form-grid">
                <div class="full"><label>Anggota</label><select name="anggota" onchange="this.form.submit()"><option value="0">Pilih anggota (opsional untuk simulasi)</option><?php foreach ($anggotaList as $a): ?><option value="<?= $a['id'] ?>" <?= $d['anggota_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['no_anggota'] . ' · ' . $a['nama']) ?></option><?php endforeach; ?></select></div>
                <div><label>Jumlah pinjaman</label><input type="number" name="jumlah" value="<?= $d['jumlah'] ?>" step="100000" min="0"></div>
                <div><label>Tenor (bulan)</label><input type="number" name="tenor" value="<?= $d['tenor'] ?>" min="1" max="36"></div>
                <div class="full"><label>Metode bunga</label><div class="seg"><label><input type="radio" name="metode" value="flat" <?= $d['metode'] === 'flat' ? 'checked' : '' ?>>Flat</label><label><input type="radio" name="metode" value="anuitas" <?= $d['metode'] === 'anuitas' ? 'checked' : '' ?>>Anuitas</label></div></div>
                <input type="hidden" name="tujuan" value="<?= e($d['tujuan']) ?>">
            </div><div class="form-actions"><button class="btn btn-tonal"><?= icon('calc', 18) ?> Hitung ulang</button></div></div>
        </form>
        <?php if ($jadwal): ?>
        <div class="card mt"><div class="card-head"><h2>Ringkasan</h2></div><div class="card-body">
            <div class="sim">
                <div><small>Angsuran pertama</small><b><?= rupiah($jadwal[0]['total']) ?></b></div>
                <div><small>Total bunga</small><b><?= rupiah(array_sum(array_column($jadwal, 'bunga'))) ?></b></div>
                <div><small>Dana diterima</small><b><?= rupiah($d['jumlah'] - round($d['jumlah'] * $set['provisi_persen'] / 100)) ?></b></div>
            </div>
            <p class="muted" style="font-size:13px;margin:14px 0 0">Perbandingan: dengan metode <b><?= $d['metode'] === 'flat' ? 'anuitas' : 'flat' ?></b> total bayar <?= rupiah($tot($pembanding)) ?> (<?= $tot($pembanding) < $tot($jadwal) ? 'lebih hemat ' . rupiah($tot($jadwal) - $tot($pembanding)) : 'lebih mahal ' . rupiah($tot($pembanding) - $tot($jadwal)) ?>).</p>
            <?php if ($info): ?>
                <dl class="kv mt" style="margin-top:16px"><dt>Total simpanan</dt><dd><?= rupiah($info['simpanan']) ?></dd><dt>Plafon (<?= $set['maks_kali_simpanan'] ?>x)</dt><dd><b style="color:<?= $d['jumlah'] > $info['plafon'] ? 'var(--red)' : 'inherit' ?>"><?= rupiah($info['plafon']) ?></b></dd>
                    <dt>Pinjaman aktif</dt><dd><?= $info['aktif'] ? '<span class="chip chip-red">Masih ada</span>' : '<span class="chip chip-green">Tidak ada</span>' ?></dd></dl>
                <form method="post" class="mt"><?= csrf_field() ?>
                    <?php foreach (['anggota_id', 'jumlah', 'tenor', 'metode'] as $f): ?><input type="hidden" name="<?= $f ?>" value="<?= e($d[$f]) ?>"><?php endforeach; ?>
                    <label>Tujuan pinjaman</label><input type="text" name="tujuan" value="<?= e($d['tujuan']) ?>" required placeholder="Biaya sekolah, renovasi rumah, modal usaha...">
                    <div class="form-actions"><button class="btn btn-filled"><?= icon('hand', 18) ?> Ajukan pinjaman</button></div>
                </form>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>
    </div>
    <div class="card outlined"><div class="card-head"><h2>Jadwal angsuran <?= e($d['metode']) ?></h2><span class="muted"><?= $d['tenor'] ?> bulan</span></div>
        <div class="table-wrap" style="max-height:640px;overflow:auto"><table>
            <thead><tr><th>Ke</th><th class="text-right">Pokok</th><th class="text-right">Bunga</th><th class="text-right">Angsuran</th><th class="text-right">Sisa pokok</th></tr></thead>
            <tbody><?php foreach ($jadwal as $j): ?><tr><td><?= $j['ke'] ?></td><td class="text-right num"><?= rupiah($j['pokok']) ?></td><td class="text-right num"><?= rupiah($j['bunga']) ?></td><td class="text-right num"><b><?= rupiah($j['total']) ?></b></td><td class="text-right num muted"><?= rupiah($j['sisa']) ?></td></tr><?php endforeach; ?></tbody>
            <?php if ($jadwal): ?><tfoot><tr><td><b>Total</b></td><td class="text-right num"><b><?= rupiah(array_sum(array_column($jadwal, 'pokok'))) ?></b></td><td class="text-right num"><b><?= rupiah(array_sum(array_column($jadwal, 'bunga'))) ?></b></td><td class="text-right num"><b><?= rupiah($tot($jadwal)) ?></b></td><td></td></tr></tfoot><?php endif; ?>
        </table></div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
