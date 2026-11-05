<?php
$id = (int) ($_GET['id'] ?? 0);
$a = $id ? q('SELECT * FROM anggota WHERE id = ?', [$id])->fetch() : null;
$d = $a ?: ['nik' => '', 'nama' => '', 'jk' => 'L', 'alamat' => '', 'telepon' => '', 'pekerjaan' => '', 'tgl_gabung' => date('Y-m-d'), 'status' => 'aktif'];
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($d as $k => $_) $d[$k] = trim($_POST[$k] ?? $d[$k]);
    try {
        if (!preg_match('/^\d{16}$/', $d['nik']) || $d['nama'] === '') throw new RuntimeException('NIK 16 digit dan nama wajib diisi.');
        if (q('SELECT 1 FROM anggota WHERE nik = ? AND id <> ?', [$d['nik'], $id])->fetchColumn()) throw new RuntimeException('NIK sudah terdaftar.');
        db()->beginTransaction();
        if ($id) {
            q('UPDATE anggota SET nik=?, nama=?, jk=?, alamat=?, telepon=?, pekerjaan=?, status=? WHERE id=?', [$d['nik'], $d['nama'], $d['jk'] === 'P' ? 'P' : 'L', $d['alamat'], $d['telepon'], $d['pekerjaan'], $d['status'] === 'keluar' ? 'keluar' : 'aktif', $id]);
        } else {
            $no = 'KSB-' . str_pad((string) (1 + (int) q('SELECT COUNT(*) FROM anggota')->fetchColumn()), 4, '0', STR_PAD_LEFT);
            q('INSERT INTO anggota (no_anggota, nik, nama, jk, alamat, telepon, pekerjaan, tgl_gabung) VALUES (?,?,?,?,?,?,?,?)', [$no, $d['nik'], $d['nama'], $d['jk'] === 'P' ? 'P' : 'L', $d['alamat'], $d['telepon'], $d['pekerjaan'], $d['tgl_gabung']]);
            $id = (int) db()->lastInsertId();
            // Anggota baru langsung membayar simpanan pokok
            if (!empty($_POST['bayar_pokok'])) {
                q("INSERT INTO simpanan (kode, anggota_id, jenis, arah, jumlah, tanggal, keterangan, user_id) VALUES (?, ?, 'pokok', 'setor', ?, CURDATE(), 'Simpanan pokok saat bergabung', ?)",
                    ['SP-' . date('ym') . '-' . bin2hex(random_bytes(3)), $id, setting()['simpanan_pokok'], user()['id']]);
            }
        }
        db()->commit();
        flash('Data anggota disimpan.');
        redirect('anggota.detail', ['id' => $id]);
    } catch (RuntimeException $ex) {
        if (db()->inTransaction()) db()->rollBack();
        $error = $ex->getMessage();
    }
}
$title = $a ? 'Ubah anggota' : 'Anggota baru';
require __DIR__ . '/../../includes/header.php';
?>
<?php if ($error): ?><div class="snack error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card" style="max-width:820px"><?= csrf_field() ?><div class="card-body" style="padding-top:20px">
    <div class="form-grid">
        <div><label>NIK</label><input type="text" name="nik" maxlength="16" value="<?= e($d['nik']) ?>" required></div>
        <div><label>Nama lengkap</label><input type="text" name="nama" value="<?= e($d['nama']) ?>" required></div>
        <div><label>Jenis kelamin</label><select name="jk"><option value="L">Laki-laki</option><option value="P" <?= $d['jk'] === 'P' ? 'selected' : '' ?>>Perempuan</option></select></div>
        <div><label>Pekerjaan / bagian</label><input type="text" name="pekerjaan" value="<?= e($d['pekerjaan']) ?>"></div>
        <div class="full"><label>Alamat</label><input type="text" name="alamat" value="<?= e($d['alamat']) ?>"></div>
        <div><label>Telepon</label><input type="text" name="telepon" value="<?= e($d['telepon']) ?>"></div>
        <?php if ($a): ?><div><label>Status</label><select name="status"><option value="aktif">Aktif</option><option value="keluar" <?= $d['status'] === 'keluar' ? 'selected' : '' ?>>Keluar</option></select></div>
        <?php else: ?><div><label>Tanggal bergabung</label><input type="date" name="tgl_gabung" value="<?= e($d['tgl_gabung']) ?>"></div>
            <div class="full"><label style="display:flex;gap:8px;align-items:center;font-size:14px"><input type="checkbox" name="bayar_pokok" value="1" checked> Catat setoran simpanan pokok <?= rupiah(setting()['simpanan_pokok']) ?> sekarang</label></div><?php endif; ?>
    </div>
    <div class="form-actions"><a class="btn" href="<?= url('anggota') ?>">Batal</a><button class="btn btn-filled">Simpan</button></div>
</div></form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
