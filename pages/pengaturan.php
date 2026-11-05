<?php
require_role('ketua', 'bendahara');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['form'] ?? '') {
            case 'koperasi':
                $f = ['nama', 'alamat', 'telepon', 'simpanan_pokok', 'simpanan_wajib', 'bunga_default', 'provisi_persen', 'denda_persen_hari', 'denda_maks_persen', 'maks_kali_simpanan'];
                $v = array_map(fn ($k) => trim($_POST[$k] ?? ''), $f);
                if ($v[0] === '') throw new RuntimeException('Nama koperasi wajib diisi.');
                foreach (array_slice($v, 3) as $n) if (!is_numeric($n) || $n < 0) throw new RuntimeException('Angka pengaturan tidak valid.');
                q('UPDATE pengaturan SET ' . implode(', ', array_map(fn ($k) => "$k = ?", $f)) . ' WHERE id = 1', $v);
                flash('Pengaturan koperasi disimpan. Bunga baru berlaku untuk pengajuan berikutnya.');
                break;
            case 'user':
                $role = $_POST['role'] ?? 'teller';
                if (!in_array($role, ['ketua', 'bendahara', 'teller'], true) || trim($_POST['nama']) === '' || !preg_match('/^[a-z0-9_.]{3,30}$/', $_POST['username'] ?? '')) throw new RuntimeException('Data pengguna tidak valid (username huruf kecil/angka, min 3).');
                if (strlen($_POST['password'] ?? '') < 6) throw new RuntimeException('Password minimal 6 karakter.');
                if (q('SELECT 1 FROM users WHERE username = ?', [$_POST['username']])->fetch()) throw new RuntimeException('Username sudah dipakai.');
                q('INSERT INTO users (nama, username, password, role) VALUES (?,?,?,?)', [trim($_POST['nama']), $_POST['username'], password_hash($_POST['password'], PASSWORD_DEFAULT), $role]);
                flash('Pengguna ditambahkan.');
                break;
            case 'hapus':
                $uid = (int) $_POST['uid'];
                if ($uid === (int) user()['id']) throw new RuntimeException('Tidak bisa menghapus akun sendiri.');
                q('DELETE FROM users WHERE id = ?', [$uid]);
                flash('Pengguna dihapus.');
                break;
            case 'password':
                $me = q('SELECT password FROM users WHERE id = ?', [user()['id']])->fetch();
                if (!password_verify($_POST['lama'] ?? '', $me['password'])) throw new RuntimeException('Password lama salah.');
                if (strlen($_POST['baru'] ?? '') < 6) throw new RuntimeException('Password baru minimal 6 karakter.');
                q('UPDATE users SET password = ? WHERE id = ?', [password_hash($_POST['baru'], PASSWORD_DEFAULT), user()['id']]);
                flash('Password diganti.');
                break;
        }
        redirect('pengaturan');
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}
$s = setting();
$users = q('SELECT id, nama, username, role FROM users ORDER BY FIELD(role,"ketua","bendahara","teller"), nama')->fetchAll();
$title = 'Pengaturan';
require __DIR__ . '/../includes/header.php';
$fld = fn ($k, $l, $t = 'number', $step = '1') => '<div><label>' . $l . '</label><input type="' . $t . '" name="' . $k . '" value="' . e($s[$k]) . '"' . ($t === 'number' ? ' step="' . $step . '" min="0"' : '') . ' required></div>';
?>
<?php if ($error): ?><div class="snack error"><?= e($error) ?></div><?php endif; ?>
<div class="grid-2">
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="form" value="koperasi">
        <div class="card-head"><h2>Profil & aturan koperasi</h2></div>
        <div class="card-body"><div class="form-grid">
            <div class="full"><label>Nama koperasi</label><input type="text" name="nama" value="<?= e($s['nama']) ?>" required></div>
            <div class="full"><label>Alamat</label><input type="text" name="alamat" value="<?= e($s['alamat']) ?>"></div>
            <?= $fld('telepon', 'Telepon', 'text') ?>
            <?= $fld('maks_kali_simpanan', 'Plafon (x total simpanan)') ?>
            <?= $fld('simpanan_pokok', 'Simpanan pokok (Rp)', 'number', '1000') ?>
            <?= $fld('simpanan_wajib', 'Simpanan wajib / bulan (Rp)', 'number', '1000') ?>
            <?= $fld('bunga_default', 'Bunga pinjaman (% / bulan)', 'number', '0.01') ?>
            <?= $fld('provisi_persen', 'Provisi (%)', 'number', '0.01') ?>
            <?= $fld('denda_persen_hari', 'Denda (% angsuran / hari)', 'number', '0.01') ?>
            <?= $fld('denda_maks_persen', 'Denda maksimal (%)', 'number', '0.01') ?>
        </div><div class="form-actions"><button class="btn btn-filled">Simpan</button></div></div>
    </form>
    <div>
        <div class="card outlined"><div class="card-head"><h2>Pengguna</h2></div><div class="table-wrap"><table>
            <tbody><?php foreach ($users as $u): ?><tr><td><strong><?= e($u['nama']) ?></strong><small>@<?= e($u['username']) ?></small></td><td><span class="chip chip-outline"><?= ucfirst($u['role']) ?></span></td>
                <td class="text-right"><?php if ((int) $u['id'] !== (int) user()['id'] && punya_role('ketua')): ?><form method="post" onsubmit="return confirm('Hapus pengguna ini?')"><?= csrf_field() ?><input type="hidden" name="form" value="hapus"><input type="hidden" name="uid" value="<?= $u['id'] ?>"><button class="icon-btn" title="Hapus"><?= icon('trash', 18) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
        <?php if (punya_role('ketua')): ?>
        <form method="post" class="card-body"><?= csrf_field() ?><input type="hidden" name="form" value="user"><div class="form-grid">
            <div><label>Nama</label><input type="text" name="nama" required></div><div><label>Username</label><input type="text" name="username" required></div>
            <div><label>Password</label><input type="password" name="password" required></div><div><label>Role</label><select name="role"><option value="teller">Teller</option><option value="bendahara">Bendahara</option><option value="ketua">Ketua</option></select></div>
        </div><div class="form-actions"><button class="btn btn-tonal"><?= icon('plus', 18) ?> Tambah pengguna</button></div></form>
        <?php endif; ?></div>
        <form method="post" class="card outlined mt"><?= csrf_field() ?><input type="hidden" name="form" value="password">
            <div class="card-head"><h2>Ganti password saya</h2></div><div class="card-body"><div class="form-grid">
                <div><label>Password lama</label><input type="password" name="lama" required></div><div><label>Password baru</label><input type="password" name="baru" required></div>
            </div><div class="form-actions"><button class="btn btn-outlined">Ganti password</button></div></div></form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
