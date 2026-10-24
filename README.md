# Skill Matching

Aplikasi Skill Matching Kelompok 7 menggunakan Laravel 13, PHP 8.3, dan Blade.

## Prasyarat dan instalasi

PHP 8.3+, Composer 2, Node.js yang mendukung Vite 8 (20.19+ atau 22.12+) dan npm. Aktifkan PDO SQLite untuk pengujian.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
```

Jangan menimpa .env yang sudah tersedia. Setiap clone menggunakan APP_KEY, database, APP_URL dan SESSION_COOKIE berbeda. File .env, vendor, node_modules dan database lokal tidak masuk Git.

## SCRUM-70: akun dan lembaga

Role akun adalah pelamar, admin dan sertifikasi. Akun baru secara default aktif, berperan pelamar dan belum terverifikasi email. Lembaga memiliki satu profil dengan status pending, active, rejected atau disabled. Status persetujuan lembaga terpisah dari email_verified_at dan is_active pada akun.

Migrasi menambah kolom pada tabel users yang sudah ada, bukan membuat ulang tabel atau menghapus data. Fitur halaman registrasi, login, pembatasan akses dan persetujuan Admin dikerjakan pada tiket berikutnya; halaman awal masih bawaan Laravel.

### Menyiapkan data lokal

Pastikan DB_DATABASE menunjuk database khusus clone ini, bukan database project referensi. Untuk SQLite, buat file database terlebih dahulu jika belum tersedia. Isi nilai berikut hanya dalam .env lokal:

```dotenv
OWNER_EMAIL=admin@skillmatching.local
OWNER_PASSWORD=
DEMO_SEED=true
DEMO_PASSWORD=
ALLOW_DEMO_IN_PRODUCTION=false
```

Isi kedua password dengan nilai minimal 12 karakter. Tidak ada password bawaan di source. OWNER_PASSWORD dibutuhkan ketika Admin belum ada. Jika DEMO_SEED=false, seeder hanya menyiapkan Admin. Di production, akun contoh membutuhkan DEMO_SEED=true sekaligus ALLOW_DEMO_IN_PRODUCTION=true.

Setelah memastikan target database benar, jalankan secara manual:

```powershell
php artisan config:clear
php artisan migrate --seed
php artisan migrate:status
```

Tidak perlu migrate:fresh. Seeder dapat diulang dengan php artisan db:seed tanpa mereset password, nama, status atau data akun yang sudah tersedia. Seeder menolak konflik role pada alamat Admin/lembaga contoh dan tidak mengirim email.

| Akun contoh | Role | Status awal |
| --- | --- | --- |
| Nilai OWNER_EMAIL | Admin | Aktif, email terverifikasi |
| baru@example.test | Pelamar | Aktif, email terverifikasi |
| belum.verifikasi@example.test | Pelamar | Aktif, email belum terverifikasi |
| nonaktif@example.test | Pelamar | Nonaktif, email terverifikasi |
| kontak@logistiknusantara.id | Badan Sertifikasi | Lembaga aktif, email terverifikasi |
| lembaga.pending@example.test | Badan Sertifikasi | Menunggu persetujuan, email terverifikasi |
| lembaga.rejected@example.test | Badan Sertifikasi | Ditolak, email terverifikasi |
| lembaga.disabled@example.test | Badan Sertifikasi | Lembaga nonaktif, email terverifikasi |
| lembaga.unverified@example.test | Badan Sertifikasi | Lembaga disetujui, email belum terverifikasi |

Semua akun contoh selain Admin menggunakan DEMO_PASSWORD. Alamat contoh bukan inbox pengujian SMTP; gunakan alamat nyata milik penguji ketika menguji pengiriman email pada tahap autentikasi.

## Pengujian dan menjalankan aplikasi

```powershell
php artisan test --compact
php artisan serve --host=127.0.0.1 --port=8105
```

Port 8105 digunakan clone Anata. Sesuaikan APP_URL dan port saat menjalankan clone lain. Untuk menampilkan halaman awal tanpa migrasi, gunakan SESSION_DRIVER=file dan CACHE_STORE=file dalam .env lokal.

Tes menggunakan SQLite :memory:, mail array/fake, queue sync dan session array sesuai phpunit.xml. Jangan menjalankan tes menggunakan database referensi. Pada tahap ini belum ada aset frontend baru; npm run build diperlukan ketika aset diubah pada tahap layout.

Branch anggota: aura, aldi, dewanti, wisnu, anata. Branch integrasi: main. Integrasi dilakukan melalui folder Aura setelah pengujian. Waktu metadata Git untuk latihan yang disesuaikan dibedakan dari waktu pengerjaan dan push aktual.

## SCRUM-71: layout dan navigasi

Jalankan npm ci (atau npm install pada checkout pertama sebelum lockfile tersedia), lalu npm run build. Jalankan clone Aldi pada port 8102:

```powershell
php artisan serve --host=127.0.0.1 --port=8102
```

Buka http://127.0.0.1:8102/preview untuk memilih tampilan Pelamar, Admin, Badan Sertifikasi, autentikasi atau komponen UI. Semua menu workspace masih placeholder; tidak menampilkan data akun. Preview hanya tersedia pada APP_ENV=local atau testing dan mengembalikan 404 di production. Form autentikasi preview dinonaktifkan dan tidak menerima pengiriman kredensial.

Layout menggunakan warna hijau, sidebar desktop, menu seluler yang dapat dibuka dengan keyboard tanpa JavaScript, header, footer, tombol kembali sesuai konteks dan tautan lewati ke konten. Contoh komponen mencakup pesan berhasil/gagal, validasi form, checkbox dan tabel yang dapat digeser di layar sempit. Font sistem dan ikon SVG lokal tidak memerlukan layanan eksternal.

Pemeriksaan manual: buka setiap peran, klik seluruh menu dan Kembali, periksa penanda menu aktif; ulangi pada lebar 390px dan 768px. Pada HP, buka Menu navigasi untuk memilih halaman. Pada /preview/komponen, hanya area tabel yang boleh bergeser horizontal, bukan seluruh halaman. Periksa juga ketiga tampilan autentikasi. Fitur login dan pengelolaan data belum diaktifkan pada tahap ini.

## SCRUM-63: registrasi dan verifikasi email

Buka /register untuk Pelamar atau /register?role=sertifikasi untuk lembaga. Field lembaga hanya terlihat dan aktif ketika jenis akun Badan Sertifikasi dipilih. Admin tidak dapat didaftarkan melalui registrasi publik. Password minimal 12 karakter, harus dikonfirmasi, dan tidak dikembalikan ke form saat validasi gagal.

Akun baru belum terverifikasi; lembaga baru berstatus pending. Registrasi tidak otomatis login. Buka tautan email untuk mengonfirmasi email saja; persetujuan lembaga tetap terpisah. Tautan ditandatangani dan berlaku 60 menit. /verification-notice menyediakan kirim ulang dengan respons umum serta batas per IP dan jeda 60 detik per email. Kegagalan pengiriman email tidak menghapus akun yang sudah dibuat.

Pada konfigurasi lokal MAIL_MAILER=log, email disimpan ke storage/logs/laravel.log, bukan dikirim ke inbox. Jangan screenshot atau membagikan tautan verifikasi yang masih aktif. Pengujian SMTP nyata memerlukan instruksi terpisah; tes otomatis memakai notifikasi palsu. Login diimplementasikan pada SCRUM-61.

Tidak ada migrasi baru untuk SCRUM-63. Gunakan database lokal yang sudah dimigrasikan pada SCRUM-70. Di clone Anata jalankan npm ci, npm run build dan php artisan serve --host=127.0.0.1 --port=8105. Gunakan email unik saat mencoba registrasi dan jangan menjalankan migrate:fresh terhadap data yang masih dibutuhkan.

## SCRUM-61: login, logout dan akses peran

Halaman awal sekarang mengarah ke /login. Akun aktif dan terverifikasi masuk ke /pelamar/dashboard, /admin/dashboard atau /sertifikasi/dashboard. Akun lembaga pending, ditolak, nonaktif atau belum memiliki profil hanya dapat membuka /sertifikasi/menunggu-verifikasi dan logout. Email belum terverifikasi mengarah ke /verification-notice tanpa sesi login. Akun pengguna nonaktif tidak dapat masuk.

Navigasi operasional tetap berupa placeholder sesuai cakupan tahap ini. Semua URL ruang kerja memeriksa sesi, kelayakan akun, kesesuaian role dan status lembaga di server. Penggantian status akun atau lembaga berlaku pada permintaan berikutnya, termasuk sesi yang sudah login. Preview lokal tetap terpisah dari ruang operasional dan tidak menampilkan data akun.

Login dibatasi lima kesalahan per kombinasi email/IP selama 60 detik, serta batas permintaan per IP. Password salah dan email tidak dikenal memberi pesan yang sama. Login memperbarui ID sesi dan tidak memakai URL tujuan dari luar. Logout hanya menerima POST ber-CSRF serta menghapus sesi dan memperbarui token CSRF.

Tidak ada migrasi baru. Jika database clone Aura masih kosong, pastikan targetnya database Aura, isi konfigurasi seed lokal, lalu jalankan php artisan migrate --seed. Gunakan akun contoh SCRUM-70 untuk mencoba login. Form lupa password ditambahkan pada SCRUM-62; halaman status lembaga akan dilengkapi pada SCRUM-82. Jalankan npm ci, npm run build dan php artisan serve --host=127.0.0.1 --port=8101 untuk demo Aura.

## SCRUM-62: pemulihan kata sandi

Dari /login pilih Lupa kata sandi atau buka /forgot-password. Respons permintaan tidak membedakan email terdaftar dan tidak terdaftar. Permintaan dibatasi per IP dan broker membatasi pengiriman ulang per akun selama 60 detik. Token disimpan dalam bentuk hash di password_reset_tokens, berlaku 60 menit, dan dihapus setelah reset berhasil. Tautan yang tidak valid, kedaluwarsa atau pernah digunakan menyediakan tombol meminta tautan baru.

Password baru minimal 12 karakter dan harus dikonfirmasi. Reset tidak otomatis login, tidak memverifikasi email, tidak mengaktifkan akun dan tidak mengubah persetujuan lembaga. Setelah reset, login dengan password baru; password lama ditolak. Remember token diganti. Ruang kerja menggunakan auth.session sehingga sesi lama yang sudah tercatat ditolak pada permintaan berikutnya setelah password berubah.

Tidak ada migrasi baru. Di clone Dewanti, siapkan database lokal menggunakan migrasi/seeder SCRUM-70 bila belum tersedia. Jalankan npm run build dan php artisan serve --host=127.0.0.1 --port=8103. MAIL_MAILER=log menulis email pemulihan ke storage/logs/laravel.log; tidak mengirim ke inbox. Jangan membagikan atau menampilkan token/tautan pemulihan aktif dalam screenshot. Pengiriman SMTP nyata hanya dilakukan bila diinstruksikan. Gunakan akun khusus uji; password akun demo yang direset tidak dikembalikan oleh seeder berulang.
