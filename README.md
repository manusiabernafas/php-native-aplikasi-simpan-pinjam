# Aplikasi Koperasi Simpan Pinjam (PHP Native + MySQL)

Aplikasi Koperasi Simpan Pinjam berbasis PHP Native dan MySQL untuk koperasi karyawan, koperasi RT/RW, KSP, BMT, atau arisan yang sudah berkembang. Semua kegiatan koperasi dicatat dalam satu aplikasi: data anggota, simpanan pokok, wajib, dan sukarela, pengajuan sampai pelunasan pinjaman, angsuran dengan denda otomatis, sampai laporan kolektibilitas untuk rapat anggota.

Perhitungan angsuran mendukung dua metode yang paling sering dipakai koperasi, yaitu Flat dan Anuitas, lengkap dengan simulasi sebelum pengajuan. Kodenya ditulis tanpa framework sehingga mudah dipelajari untuk tugas kuliah atau skripsi, dan cukup lengkap untuk langsung dipakai pengurus koperasi yang ingin meninggalkan buku kas dan Excel. Tampilannya mengikuti gaya Material Design 3 yang bersih dan nyaman dipakai seharian.

## Cara kerja

Setiap anggota punya buku besar simpanan: semua setoran dan penarikan tercatat per baris, sehingga saldo selalu bisa ditelusuri. Simpanan pokok dibayar sekali, simpanan wajib dibayar per bulan dan sistem menolak pembayaran dobel untuk periode yang sama (dijaga constraint UNIQUE di database). Hanya simpanan sukarela yang bisa ditarik, dan baris anggota dikunci (SELECT ... FOR UPDATE) saat penarikan agar dua teller tidak bisa menarik saldo yang sama bersamaan.

Pengajuan pinjaman dimulai dari halaman simulasi: pilih anggota, jumlah, tenor, dan metode Flat atau Anuitas, lalu sistem menampilkan jadwal angsuran, total bunga, dana yang diterima setelah provisi, serta perbandingan dengan metode lainnya. Plafon dihitung dari total simpanan anggota dikali pengali yang bisa diatur, dan satu anggota hanya boleh punya satu pinjaman aktif. Ketua menyetujui atau menolak dengan alasan, bendahara mencairkan dana, lalu jadwal angsuran dengan tanggal jatuh tempo tiap bulan dibuat otomatis.

Saat anggota membayar, teller cukup memilih berapa angsuran yang dibayar; denda keterlambatan dihitung otomatis dari persen per hari dengan batas maksimal. Pelunasan dipercepat hanya menagih pokok untuk bulan-bulan yang belum jatuh tempo. Laporan menampilkan arus kas per periode, pendapatan jasa, posisi simpanan dan piutang, kolektibilitas pinjaman beserta rasio NPL, serta daftar tunggakan yang bisa diekspor ke CSV untuk penagihan.

## Fitur

- Login 3 peran: Ketua, Bendahara, dan Teller dengan hak akses berbeda
- Dashboard: total simpanan, piutang pinjaman, arus kas 6 bulan, pengajuan menunggu, tunggakan, dan angsuran jatuh tempo 7 hari
- Data anggota dengan nomor anggota otomatis, validasi NIK, dan status aktif/keluar
- Detail anggota: saldo per jenis simpanan, plafon pinjaman, mutasi, dan riwayat pinjaman
- Simpanan Pokok, Wajib, dan Sukarela dalam satu buku besar (ledger) setor/tarik
- Simpanan wajib per periode bulan, tidak bisa dibayar dobel untuk bulan yang sama
- Tagihan simpanan wajib kolektif: centang banyak anggota lalu bayar sekaligus
- Penarikan hanya untuk simpanan sukarela dan tidak bisa melebihi saldo (aman dari penarikan bersamaan)
- Bukti setoran/penarikan siap cetak
- Simulasi pinjaman metode Flat dan Anuitas lengkap dengan perbandingan total bayar
- Plafon pinjaman otomatis = kelipatan total simpanan (bisa diatur), maksimal 1 pinjaman aktif per anggota
- Alur pinjaman: Diajukan, Disetujui/Ditolak (ketua), Dicairkan (bendahara), Berjalan, Lunas
- Potongan provisi otomatis saat pencairan dan jadwal angsuran dibuat otomatis
- Kartu angsuran: jatuh tempo, pokok, bunga, status bayar, dan hari keterlambatan
- Denda keterlambatan otomatis: persen per hari dengan batas maksimal
- Bayar satu atau beberapa angsuran sekaligus, aman dari pembayaran ganda
- Pelunasan dipercepat: bunga bulan-bulan berikutnya tidak ditagih
- Laporan arus kas, pendapatan jasa (bunga, denda, provisi), posisi simpanan, dan piutang
- Kolektibilitas pinjaman (Lancar, Dalam Perhatian, Kurang Lancar, Macet) dan rasio NPL
- Daftar tunggakan dan ekspor CSV untuk penagihan
- Pengaturan bunga, provisi, denda, simpanan pokok/wajib, plafon, dan manajemen pengguna
- Keamanan: PDO prepared statement, password_hash, CSRF, transaksi database dengan penguncian baris
- Data contoh 60 anggota, 1.500+ transaksi simpanan, dan 50+ pinjaman
- Tampilan Material Design 3 bernuansa ungu, responsif di HP, ikon lokal (bisa offline)
- Tanpa framework dan tanpa Composer, cukup XAMPP

## Instalasi

**Kebutuhan:** PHP 8.0 atau lebih baru dan MySQL/MariaDB. Paling mudah pakai XAMPP atau Laragon.

1. Ekstrak file ZIP lalu pindahkan folder `koperasi` ke folder web server:
   - XAMPP: `C:\xampp\htdocs\koperasi`
   - Laragon: `C:\laragon\www\koperasi`

2. Jalankan **Apache** dan **MySQL**.

3. Buka `http://localhost/phpmyadmin`, klik **Import**, pilih file `database/koperasi_simpan_pinjam.sql`, lalu klik **Import**. Database `koperasi_simpan_pinjam` beserta data contohnya akan dibuat otomatis.

   Atau lewat terminal:
   ```bash
   mysql -u root -p < database/koperasi_simpan_pinjam.sql
   ```
   Tanggal data contoh menyesuaikan hari saat import, jadi jatuh tempo dan tunggakan selalu terlihat realistis.

4. Jika user/password MySQL kamu bukan bawaan XAMPP, sesuaikan `config/database.php`:
   ```php
   return [
       'host'     => 'localhost',
       'port'     => 3306,
       'database' => 'koperasi_simpan_pinjam',
       'username' => 'root',
       'password' => '',
   ];
   ```

5. Buka `http://localhost/koperasi` lalu login dengan akun demo (password semua `admin123`):
   - Ketua: `ketua` (menyetujui/menolak pinjaman, kelola pengguna)
   - Bendahara: `admin` (pencairan, pelunasan, pengaturan)
   - Teller: `teller` (anggota, simpanan, terima angsuran)

6. Atur bunga, provisi, denda, dan besaran simpanan di menu **Pengaturan**, lalu ganti password akun demo.

**Tanpa XAMPP?** Dari dalam folder project jalankan `php -S localhost:8000` lalu buka `http://localhost:8000`.

## Struktur folder

```
koperasi/
├── assets/css/style.css     Tampilan Material Design 3
├── config/database.php      Koneksi MySQL
├── database/                File SQL + data contoh
├── includes/bootstrap.php   Helper: koneksi, CSRF, rumus angsuran & denda
├── pages/                   Halaman (anggota, simpanan, pinjaman, laporan, pengaturan)
└── index.php                Router
```

Rumus angsuran ada di fungsi `jadwal_angsuran()` dan denda di `denda_angsuran()` pada `includes/bootstrap.php`.

---
Source code ini dibagikan gratis oleh **Papicode** — https://papicode.id
