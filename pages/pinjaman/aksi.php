<?php
// Semua perubahan status pinjaman & pembayaran angsuran lewat sini (POST saja)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('pinjaman');
$id = (int) ($_POST['id'] ?? 0);
$aksi = $_POST['aksi'] ?? '';
$set = setting();
$pesan = '';
try {
    db()->beginTransaction();
    // Kunci baris pinjaman: dua teller tidak bisa membayar angsuran yang sama bersamaan
    $p = q('SELECT * FROM pinjaman WHERE id = ? FOR UPDATE', [$id])->fetch();
    if (!$p) throw new RuntimeException('Pinjaman tidak ditemukan.');

    switch ($aksi) {
        case 'setujui':
        case 'tolak':
            if (!punya_role('ketua')) throw new RuntimeException('Hanya ketua yang dapat memutuskan pengajuan.');
            if ($p['status'] !== 'diajukan') throw new RuntimeException('Pengajuan ini sudah diputuskan.');
            $catatan = trim($_POST['catatan'] ?? '');
            if ($aksi === 'tolak' && $catatan === '') throw new RuntimeException('Tuliskan alasan penolakan.');
            q('UPDATE pinjaman SET status = ?, catatan = ?, disetujui_oleh = ? WHERE id = ?', [$aksi === 'setujui' ? 'disetujui' : 'ditolak', $catatan ?: null, user()['id'], $id]);
            $pesan = $aksi === 'setujui' ? 'Pinjaman disetujui, siap dicairkan bendahara.' : 'Pengajuan ditolak.';
            break;

        case 'cair':
            if (!punya_role('ketua', 'bendahara')) throw new RuntimeException('Pencairan dilakukan oleh bendahara.');
            if ($p['status'] !== 'disetujui') throw new RuntimeException('Pinjaman belum disetujui atau sudah dicairkan.');
            $provisi = (int) round($p['jumlah'] * $set['provisi_persen'] / 100);
            $hariIni = date('Y-m-d');
            foreach (jadwal_angsuran((int) $p['jumlah'], (float) $p['bunga_persen'], (int) $p['tenor'], $p['metode']) as $j) {
                q('INSERT INTO angsuran (pinjaman_id, ke, jatuh_tempo, pokok, bunga, total) VALUES (?,?,?,?,?,?)',
                    [$id, $j['ke'], tambah_bulan($hariIni, $j['ke']), $j['pokok'], $j['bunga'], $j['total']]);
            }
            q("UPDATE pinjaman SET status = 'berjalan', provisi = ?, diterima = ?, tgl_cair = ? WHERE id = ?", [$provisi, $p['jumlah'] - $provisi, $hariIni, $id]);
            $pesan = 'Dana ' . rupiah($p['jumlah'] - $provisi) . ' dicairkan (provisi ' . rupiah($provisi) . '). Jadwal ' . $p['tenor'] . ' angsuran dibuat.';
            break;

        case 'bayar':
            if ($p['status'] !== 'berjalan') throw new RuntimeException('Pinjaman tidak dalam status berjalan.');
            $n = max(1, (int) ($_POST['jumlah_angsuran'] ?? 1));
            $belum = q('SELECT * FROM angsuran WHERE pinjaman_id = ? AND dibayar_at IS NULL ORDER BY ke LIMIT ' . $n, [$id])->fetchAll();
            if (!$belum) throw new RuntimeException('Semua angsuran sudah dibayar.');
            $total = 0;
            foreach ($belum as $a) {
                $denda = denda_angsuran($a);
                q('UPDATE angsuran SET denda = ?, dibayar_at = CURDATE(), user_id = ? WHERE id = ?', [$denda, user()['id'], $a['id']]);
                $total += $a['total'] + $denda;
            }
            $pesan = 'Angsuran ke-' . implode(', ', array_column($belum, 'ke')) . ' dibayar, total ' . rupiah($total) . '.';
            break;

        case 'lunasi':
            // Pelunasan dipercepat: tunggakan + denda dibayar penuh, angsuran bulan berjalan dibayar penuh,
            // angsuran berikutnya hanya pokoknya (bunga masa depan tidak ditagih).
            if (!punya_role('ketua', 'bendahara')) throw new RuntimeException('Pelunasan dipercepat diproses bendahara.');
            if ($p['status'] !== 'berjalan') throw new RuntimeException('Pinjaman tidak dalam status berjalan.');
            $belum = q('SELECT * FROM angsuran WHERE pinjaman_id = ? AND dibayar_at IS NULL ORDER BY ke', [$id])->fetchAll();
            $total = 0; $hemat = 0; $penuh = true;
            foreach ($belum as $a) {
                $jatuhTempoLewat = $a['jatuh_tempo'] <= date('Y-m-d');
                if ($jatuhTempoLewat || $penuh) {
                    $denda = denda_angsuran($a);
                    q('UPDATE angsuran SET denda = ?, dibayar_at = CURDATE(), user_id = ? WHERE id = ?', [$denda, user()['id'], $a['id']]);
                    $total += $a['total'] + $denda;
                    if (!$jatuhTempoLewat) $penuh = false;   // angsuran bulan berjalan sudah dihitung penuh
                } else {
                    q('UPDATE angsuran SET bunga = 0, total = pokok, dibayar_at = CURDATE(), user_id = ? WHERE id = ?', [user()['id'], $a['id']]);
                    $total += $a['pokok'];
                    $hemat += $a['bunga'];
                }
            }
            $pesan = 'Pinjaman dilunasi ' . rupiah($total) . '. Anggota hemat bunga ' . rupiah($hemat) . '.';
            break;

        default:
            throw new RuntimeException('Aksi tidak dikenal.');
    }

    // Tutup otomatis jika semua angsuran lunas
    if ($aksi === 'bayar' || $aksi === 'lunasi') {
        $sisa = (int) q('SELECT COUNT(*) FROM angsuran WHERE pinjaman_id = ? AND dibayar_at IS NULL', [$id])->fetchColumn();
        if ($sisa === 0) {
            q("UPDATE pinjaman SET status = 'lunas' WHERE id = ?", [$id]);
            $pesan .= ' Pinjaman LUNAS.';
        }
    }
    db()->commit();
    flash($pesan);
} catch (RuntimeException $ex) {
    if (db()->inTransaction()) db()->rollBack();
    flash($ex->getMessage(), 'error');
}
redirect('pinjaman.detail', ['id' => $id]);
