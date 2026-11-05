<?php
$set = setting();
$periode = $_GET['periode'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $periode)) $periode = date('Y-m');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Setor simpanan wajib beberapa anggota sekaligus (misal potong gaji). UNIQUE (anggota, jenis, periode) mencegah dobel.
    $ok = 0;
    db()->beginTransaction();
    foreach (array_map('intval', $_POST['anggota'] ?? []) as $aid) {
        try {
            q("INSERT INTO simpanan (kode, anggota_id, jenis, arah, jumlah, periode, tanggal, keterangan, user_id) VALUES (?, ?, 'wajib', 'setor', ?, ?, CURDATE(), 'Setoran wajib kolektif', ?)",
                ['TMP-' . bin2hex(random_bytes(4)), $aid, $set['simpanan_wajib'], $periode, user()['id']]);
            $id = (int) db()->lastInsertId();
            q('UPDATE simpanan SET kode = ? WHERE id = ?', ['SP-' . date('ym') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT), $id]);
            $ok++;
        } catch (PDOException $e) { /* sudah bayar periode ini, lewati */ }
    }
    db()->commit();
    flash("$ok setoran wajib periode " . bulan_id($periode) . ' tercatat.');
    redirect('simpanan.wajib', ['periode' => $periode]);
}
$belum = q("SELECT a.* FROM anggota a WHERE a.status = 'aktif' AND a.tgl_gabung <= LAST_DAY(?) AND NOT EXISTS (SELECT 1 FROM simpanan s WHERE s.anggota_id = a.id AND s.jenis = 'wajib' AND s.periode = ?) ORDER BY a.nama", [$periode . '-01', $periode])->fetchAll();
$sudah = (int) q("SELECT COUNT(*) FROM simpanan WHERE jenis = 'wajib' AND periode = ?", [$periode])->fetchColumn();
$title = 'Tagihan simpanan wajib';
$sub = bulan_id($periode) . ' · ' . $sudah . ' sudah bayar · ' . count($belum) . ' belum';
$actions = '<form class="filters" method="get"><input type="hidden" name="page" value="simpanan.wajib"><input type="month" name="periode" value="' . e($periode) . '" onchange="this.form.submit()" style="padding:10px 14px;border-radius:28px;border:0;background:var(--surface-3)"></form>';
require __DIR__ . '/../../includes/header.php';
?>
<form method="post" class="card outlined"><?= csrf_field() ?>
    <div class="card-head"><h2>Belum membayar</h2><label style="display:flex;gap:8px;align-items:center;margin:0;font-size:14px"><input type="checkbox" onclick="document.querySelectorAll('.cb').forEach(c=>c.checked=this.checked)"> Pilih semua</label></div>
    <div class="table-wrap"><table><tbody>
        <?php foreach ($belum as $a): ?><tr><td style="width:40px"><input type="checkbox" class="cb" name="anggota[]" value="<?= $a['id'] ?>"></td><td><strong><?= e($a['nama']) ?></strong><small><?= e($a['no_anggota']) ?> · <?= e($a['pekerjaan']) ?></small></td><td class="text-right num"><?= rupiah($set['simpanan_wajib']) ?></td></tr><?php endforeach; ?>
        <?php if (!$belum): ?><tr><td class="empty">Semua anggota sudah membayar simpanan wajib periode ini.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php if ($belum): ?><div class="card-body form-actions"><button class="btn btn-filled"><?= icon('check', 18) ?> Catat setoran terpilih</button></div><?php endif; ?>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
