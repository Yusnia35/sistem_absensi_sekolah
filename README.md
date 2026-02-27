# Sistem Absensi Sekolah

Ringkasan singkat proyek ini: aplikasi absensi berbasis PHP (no framework) yang berjalan di lingkungan XAMPP / Apache + MySQL. Aplikasi menyediakan manajemen siswa, guru, jadwal, token QR untuk absensi, dan akun per-peran (admin, siswa, guru, mahasiswa).

## Persyaratan
- PHP 7.4+ (disarankan PHP 8)
- MySQL / MariaDB
- XAMPP (Windows) atau LAMP stack

## Struktur direktori (inti)
- `index.php`, `login.php`, `logout.php` — entry dan autentikasi
- `dashboard.php` — tampilan ringkasan
- `akun_siswa_manage.php`, `akun_guru_manage.php` — halaman admin untuk membuat/ubah/hapus akun per peran
- `models/` — model-model kecil (DB helpers): `AkunSiswa.php`, `AkunGuru.php`, `ModelBase.php`, dll.
- `config/` — `config.php`, `database.php` untuk koneksi PDO
- `assets/` — CSS dan JS
- `database/schema.sql` — skema awal database

## Instalasi cepat (di Windows/XAMPP)
1. Letakkan folder `absensi_sekolah` di `C:\xampp\htdocs\`.
2. Buka `phpMyAdmin` atau CLI MySQL dan buat database `absensi_sekolah`.
3. Impor schema: buka `database/schema.sql` dan jalankan SQL-nya untuk membuat tabel awal.
4. Pastikan konfigurasi koneksi di `config/database.php` sesuai (user/password/dbname).
5. Buka browser: `http://localhost/absensi_sekolah/`.

## Menambahkan akun siswa / guru
- Admin dapat membuat akun siswa lewat halaman: `akun_siswa_manage.php`.
- Admin dapat membuat akun guru lewat halaman: `akun_guru_manage.php`.
- Password disimpan dengan hashing (password_hash). Jika database memiliki password lama sebagai plaintext, library akan mencoba memverifikasi dan memigrasikannya otomatis pada login.

Contoh alur pembuatan akun siswa:
1. Login sebagai admin.
2. Buka `Manajemen Akun Siswa` (menu Dashboard → *Tambah Siswa* atau langsung `akun_siswa_manage.php`).
3. Pilih siswa yang belum memiliki akun, masukkan `username` dan `password`, lalu klik `Buat Akun`.

## Autentikasi
- Sistem mendukung beberapa tabel akun: `user` (admin), `akun_siswa`, `akun_guru`, `akun_mahasiswa`.
- Setelah login, session menyimpan `user_role` dan `user_id` (dan role-spesifik id seperti `id_siswa`).

## Perubahan penting / catatan pengembang
- Ditambahkan model `models/AkunSiswa.php` dan `models/AkunGuru.php` untuk operasi CRUD dan autentikasi akun role.
- Halaman admin `akun_siswa_manage.php` dan `akun_guru_manage.php` dibuat untuk menambah/ubah/hapus akun.
- Untuk membatasi absensi pada ruangan tertentu, rencana penambahan kolom `ruangan.allow_absensi` (TINYINT) ada di list todo; belum diterapkan otomatis.

## Database migration contoh (manual)
Untuk menambahkan kolom `allow_absensi` ke tabel `ruangan` jalankan SQL berikut di database Anda:

```sql
ALTER TABLE ruangan
ADD COLUMN allow_absensi TINYINT(1) NOT NULL DEFAULT 0;
```

Setelah menambahkan kolom tersebut, aplikasi perlu diperbarui untuk mengecek flag ini sebelum menerima absensi berbasis token (halaman `absensi/input.php`).

## Keamanan dan perbaikan yang disarankan
- Pastikan environment produksi mematikan error display dan menggunakan logging.
- Terapkan CSRF token untuk semua formulir yang mengubah data.
- Batasi percobaan login (rate limiting) dan pertimbangkan reCAPTCHA pada halaman login.
- Tinjau penggunaan `addslashes`/`htmlspecialchars` pada output dan sanitasi input lebih ketat jika menerima file/CSV impor.

## Pengembangan lebih lanjut
- Buat `akun_mahasiswa` model dan manajemen akun (paralel dengan siswa/guru).
- Tambah impor CSV untuk pembuatan akun massal.
- Tambahkan laporan absensi harian/per-siswa dan ekspor CSV.

## Kontak & bantuan
Jika ada masalah atau ingin fitur tambahan, tambahkan issue di repository atau hubungi pengembang proyek.

---
Dokumentasi ini dibuat untuk membantu pengembang menjalankan dan mengelola aplikasi lokal. Jika Anda ingin saya menambahkan instruksi khusus (contoh: cara menjalankan di Linux, menambahkan seed data, atau script migrasi otomatis), beri tahu saya dan saya akan menambahkannya.

