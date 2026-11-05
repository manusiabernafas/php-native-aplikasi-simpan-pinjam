<?php
$set = setting();
$anggotaList = q("SELECT id, no_anggota, nama FROM anggota WHERE status = 'aktif' ORDER BY nama")->fetchAll();
$aid = (int) ($_GET['anggota'] ?? $_POST['anggota_id'] ?? 0);
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $jenis = $_POST['jenis'] ?? '';
        $arah = ($_POST['arah'] ?? 'setor') === 'tarik' ? 'tarik' : 'setor';
        $jumlah = (int) $_POST['jumlah'];
        if (!isset(JENIS_SIMPANAN[$jenis]) || $jumlah <= 0 || !$aid) throw new RuntimeException('Lengkapi anggota, jenis, dan jumlah.');
        $periode = null;
        db()->beginTransaction();
        q('SELECT id FROM anggota WHERE id = ? FOR UPDATE', [$aid]);   // kunci agar saldo tidak dibaca ganda saat penarikan bersamaan
        if ($arah === 'tarik') {
            // Hanya simpanan sukarela yang bisa ditarik selama masih menjadi anggota
            if ($jenis !== 'sukarela') throw new RuntimeException('Simpanan pokok dan wajib hanya bisa diambil saat keluar dari keanggotaan.');
            if ($jumlah > saldo_simpanan($aid, 'sukarela')) throw new RuntimeException('Saldo sukarela tidak cukup (' . rupiah(saldo_simpanan($aid, 'sukarela')) . ').');
        } elseif ($jenis === 'wajib') {
            $periode = $_POST['periode'] ?: date('Y-m');
            $jumlah = (int) $set['simpanan_wajib'];
        } elseif ($jenis === 'pokok' && saldo_simpanan($aid, 'pokok') > 0) {
            throw new RuntimeException('Simpanan pokok anggota ini sudah lunas.');
        }
        q('INSERT INTO simpanan (kode, anggota_id, jenis, arah, jumlah, periode, tanggal, keterangan, user_id) VALUES (?,?,?,?,?,?,CURDATE(),?,?)',
            ['TMP-' . bin2hex(random_bytes(4)), $aid, $jenis, $arah, $jumlah, $periode, trim($_POST['keterangan'] ?? '') ?: null, user()['id']]);
        $id = (int) db()->lastInsertId();
        q('UPDATE simpanan SET kode = ? WHERE id = ?', [($arah === 'setor' ? 'SP-' : 'TR-') . date('ym') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT), $id]);
        db()->commit();
        flash(($arah === 'setor' ? 'Setoran ' : 'Penarikan ') . JENIS_SIMPANAN[$jenis] . ' ' . rupiah($jumlah) . ' tercatat.');
        redirect('bukti', ['id' => $id]);
    } catch (PDOException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        $error = 'Simpanan wajib periode tersebut sudah dibayar.';
    } catch (RuntimeException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        $error = $ex->getMessage();
    }
}
$riwayat = q('SELECT s.*, a.nama, a.no_anggota FROM simpanan s JOIN anggota a ON a.id = s.anggota_id ORDER BY s.id DESC LIMIT 25')->fetchAll();
$hariIni = q("SELECT COALESCE(SUM(CASE WHEN arah='setor' THEN jumlah END),0) masuk, COALESCE(SUM(CASE WHEN arah='tarik' THEN jumlah END),0) keluar FROM simpanan WHERE tanggal = CURDATE()")->fetch();
$title = 'Simpanan';
$sub = 'Hari ini masuk ' . rupiah($hariIni['masuk']) . ' · keluar ' . rupiah($hariIni['keluar']);
$actions = '<a class="btn btn-tonal" href="' . url('simpanan.wajib') . '">' . icon('calendar', 18) . ' Tagihan wajib bulan ini</a>';
require __DIR__ . '/../../includes/header.php';
?>
<?php if ($error): ?><div class="snack error"><?= e($error) ?></div><?php endif; ?>
<div class="grid-2">
    <form method="post" class="card"><?= csrf_field() ?>
        <div class="card-head"><h2>Transaksi baru</h2></div>
        <div class="card-body"><div class="form-grid">
            <div class="full"><label>Anggota</label><select name="anggota_id" required><option value="">Pilih anggota</option><?php foreach ($anggotaList as $a): ?><option value="<?= $a['id'] ?>" <?= $aid === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['no_anggota'] . ' · ' . $a['nama']) ?></option><?php endforeach; ?></select></div>
            <div class="full"><label>Jenis transaksi</label><div class="seg">
                <label><input type="radio" name="arah" value="setor" checked>Setor</label><label><input type="radio" name="arah" value="tarik">Tarik</label></div></div>
            <div><label>Jenis simpanan</label><select name="jenis" id="jenis"><?php foreach (JENIS_SIMPANAN as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'sukarela' ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
            <div><label>Jumlah (Rp)</label><input type="number" name="jumlah" id="jumlah" min="1000" step="1000" required></div>
            <div><label>Periode (khusus wajib)</label><input type="text" name="periode" placeholder="<?= date('Y-m') ?>" pattern="\d{4}-\d{2}"></div>
            <div><label>Keterangan</label><input type="text" name="keterangan"></div>
        </div>
        <p class="muted" style="font-size:12.5px">Simpanan pokok <?= rupiah($set['simpanan_pokok']) ?> (sekali), wajib <?= rupiah($set['simpanan_wajib']) ?>/bulan. Hanya simpanan sukarela yang bisa ditarik.</p>
        <div class="form-actions"><button class="btn btn-filled"><?= icon('check', 18) ?> Simpan & cetak bukti</button></div></div>
    </form>
    <div class="card"><div class="card-head"><h2>Transaksi terakhir</h2></div>
        <div class="table-wrap" style="max-height:520px;overflow:auto"><table><tbody>
            <?php foreach ($riwayat as $r): ?><tr><td><strong><?= e($r['nama']) ?></strong><small><?= JENIS_SIMPANAN[$r['jenis']] ?> · <?= tgl($r['tanggal']) ?></small></td>
                <td class="text-right num" style="color:<?= $r['arah'] === 'tarik' ? 'var(--red)' : 'var(--green)' ?>"><?= $r['arah'] === 'tarik' ? '−' : '+' ?><?= rupiah($r['jumlah']) ?></td>
                <td class="actions"><a class="icon-btn" href="<?= url('bukti', ['id' => $r['id']]) ?>"><?= icon('receipt', 18) ?></a></td></tr><?php endforeach; ?>
        </tbody></table></div></div>
</div>
<script>
document.getElementById('jenis').addEventListener('change', function () {
    var j = document.getElementById('jumlah');
    if (this.value === 'wajib') j.value = <?= (int) $set['simpanan_wajib'] ?>;
    if (this.value === 'pokok') j.value = <?= (int) $set['simpanan_pokok'] ?>;
});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
