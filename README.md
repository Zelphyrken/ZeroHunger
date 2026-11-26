# Porsela — Platform Penyaluran Makanan Berlebih

## 1. Nama dan Deskripsi Proyek

**Nama proyek:** Porsela (dari "porsi selamat")

**Deskripsi:** Porsela adalah aplikasi web yang menghubungkan pemilik makanan berlebih dengan pihak yang membutuhkan. Restoran, katering, toko roti, maupun rumah tangga bisa memublikasikan makanan yang masih layak konsumsi; panti asuhan, posyandu, dan dapur umum komunitas bisa mencari dan mengklaim porsi sesuai kebutuhan, lalu mengunggah bukti terima sebagai jejak penyaluran.

Aplikasi dibangun dengan PHP native (tanpa framework) dan MySQL/MariaDB sehingga mudah dijalankan di XAMPP, mudah dibaca, dan tidak memerlukan dependensi eksternal.

**Tema:** SDG 2 — Zero Hunger (Tanpa Kelaparan)

## 2. Latar Belakang dan Tujuan Proyek

**Latar belakang.** Tiga masalah utama yang melatarbelakangi proyek ini:

1. **Berton-ton Makanan Terbuang.** Setiap hari, restoran, katering, dan toko roti menghasilkan surplus makanan layak konsumsi yang akhirnya terbuang menjadi sampah organik (food waste).
2. **Kesenjangan Akses Pangan.** Di sisi lain, banyak panti asuhan dan masyarakat prasejahtera di sekitar kita yang kesulitan mendapatkan makanan bernutrisi dan higienis.
3. **Ketiadaan Sistem Real-Time.** Belum ada platform terpadu yang memantau batas aman waktu konsumsi (expiry time) dan pelacakan status penjemputan donasi secara transparan.

Porsela menjawab ketiganya: makanan berlebih dipublikasikan lengkap dengan jumlah porsi dan batas aman waktu konsumsi (menjawab masalah 1 dan 3); penerima dapat mencari makanan layak di sekitarnya lalu mengklaim porsi sesuai kebutuhan (menjawab masalah 2); dan setiap penyaluran punya status yang terlihat kedua pihak serta bukti terima berupa foto, sehingga prosesnya bisa diaudit (menjawab masalah 3). Ditambah verifikasi akun oleh admin supaya donasi iseng tidak sampai ke penerima.

**Tujuan proyek:**

1. Memberi donatur cara cepat memublikasikan makanan berlebih lengkap dengan jumlah porsi dan batas layak konsumsinya.
2. Membantu penerima menemukan makanan yang masih layak di sekitarnya, lalu mengklaim sejumlah porsi sesuai kebutuhan.
3. Mencatat setiap penyaluran: siapa mengklaim, berapa porsi, dan bukti terima foto.
4. Memberi admin alat verifikasi agar hanya donatur sungguhan yang donasinya tampil.
5. Menampilkan dampak secara terukur (porsi terselamatkan, penerima terbantu, donatur aktif).

**Bukan tujuan:** platform tidak mengantar makanan (penyerahan dilakukan langsung antara donatur dan penerima), tidak ada transaksi uang, dan tidak ada fitur media sosial.

## 3. Daftar Fitur / Modul yang Diimplementasikan

| # | Modul | Isi implementasi |
|---|---|---|
| 1 | Autentikasi multi-peran | Register pilihan peran (donatur/penerima), login dengan sesi, logout bertoken, password di-hash, redirect dashboard sesuai peran |
| 2 | Profil pengguna & tema | Ubah nama dan nomor HP, ganti password (wajib password lama), pilihan tema terang/gelap yang disimpan di cookie |
| 3 | Manajemen kategori pangan | CRUD kategori oleh admin; kategori dipakai sebagai relasi donasi dan tidak bisa dihapus bila masih dipakai |
| 4 | Posting donasi makanan | Donatur mengisi nama makanan, kategori, jumlah porsi, batas layak konsumsi, metode penyerahan, alamat, deskripsi & catatan allergen, serta foto (maks 5MB) |
| 5 | Pencarian & filter tanpa reload | Live search di halaman penerima: kata kunci, kategori, dan metode penyerahan diproses di browser |
| 6 | Klaim porsi | Penerima mengajukan klaim (jumlah porsi, alasan, jadwal, alamat kirim bila diantar); donatur menyetujui atau menolak |
| 7 | Pelacakan status penyaluran | Status donasi `pending` → `claimed` → `distributed` dan status klaim `menunggu` → `disetujui` → `selesai` / `ditolak` |
| 8 | Bukti terima & ulasan | Penerima mengunggah foto bukti terima plus catatan, lalu memberi ulasan bintang 1–5 |
| 9 | Dashboard statistik dampak | Halaman publik berisi porsi terselamatkan, penerima terbantu, donatur aktif, donasi menunggu klaim, rekap per kategori dan metode penyerahan |
| 10 | Verifikasi akun donatur | Admin menyetujui atau menolak akun donatur; akun berstatus menunggu tidak menampilkan donasinya di pencarian penerima |
| 11 | Pengawasan & audit admin | Daftar seluruh akun beserta status verifikasinya, audit read-only 30 donasi terbaru, dan blokir akun yang ditolak |
| 12 | Pengamanan transaksi & form | Token CSRF di semua form POST dan tautan logout; klaim diproses dalam transaksi dengan penguncian baris; donasi yang sudah diklaim tidak bisa diedit atau dihapus |

Dua belas modul di atas adalah yang berjalan di aplikasi. Aturan detail tiap modul (perhitungan sisa porsi, kadaluarsa, metode penyerahan, ulasan) ada di bagian **Aturan bisnis penting** pada Lampiran.

## 4. Teknologi yang Digunakan

| Lapisan | Teknologi |
|---|---|
| Bahasa backend | PHP 8 (native, tanpa framework, tanpa Composer) |
| Database | MySQL/MariaDB via PDO, prepared statement, `EMULATE_PREPARES = false` |
| Server lokal | XAMPP (Apache 2.4 + MariaDB 10.4 — diuji pada PHP 8.3 dan MariaDB 10.4.32) |
| Frontend | HTML5, CSS3 (variabel CSS, tema terang/gelap, grid responsif), JavaScript vanilla (manipulasi DOM untuk live search) |
| Penyimpanan berkas | Folder `uploads/items` (foto makanan) dan `uploads/proofs` (bukti terima) |
| Version control | Git + GitHub |

Tanpa dependensi eksternal: tidak ada framework PHP, tidak ada library frontend, tidak ada `composer install` maupun `npm install`.

## 5. Panduan Instalasi dan Konfigurasi

**Prasyarat:** XAMPP (Apache + MySQL/MariaDB) dan Git.

**Langkah 1 — ambil kode**

```
git clone https://github.com/Zelphyrken/ZeroHunger.git
cd ZeroHunger
```

**Langkah 2 — letakkan di folder web server (opsional)**

Salin folder proyek ke `C:\xampp\htdocs\ZeroHunger` agar bisa diakses lewat Apache. Bisa juga dijalankan langsung dari lokasi clone memakai server bawaan PHP (lihat bagian 6).

**Langkah 3 — jalankan Apache dan MySQL** dari XAMPP Control Panel.

**Langkah 4 — buat database dan import skema (urut, sekali saja)**

```
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE berbagi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
C:\xampp\mysql\bin\mysql.exe -u root berbagi_db < database/berbagi_db.sql
C:\xampp\mysql\bin\mysql.exe -u root berbagi_db < database/migration_v2.sql
C:\xampp\mysql\bin\mysql.exe -u root berbagi_db < database/migration_v3.sql
C:\xampp\mysql\bin\mysql.exe -u root berbagi_db < database/seed_demo.sql
```

Penjelasan berkas: `berbagi_db.sql` skema awal yang membuat tabel `users` dan `donations`; `migration_v2.sql` menambah peran admin/penerima, tabel `categories`, `claims`, `reviews`, kolom porsi/kadaluarsa/metode penyerahan, dan view `v_donations`; `migration_v3.sql` membuang kolom warisan versi awal; `seed_demo.sql` mengisi data demo dan aman dijalankan berulang.

Alternatif lewat antarmuka: buka `http://localhost/phpmyadmin`, buat database bernama `berbagi_db`, lalu import keempat berkas di atas secara berurutan.

**Langkah 5 — sesuaikan konfigurasi bila perlu**

Semua pengaturan database ada di `config/db.php`:

```php
$host = 'localhost';
$dbname = 'berbagi_db';
$username = 'root';
$password = '';          // kosongkan bila MySQL lokal tanpa password
```

- Jika MySQL kamu memakai password, isi variabel `$password`.
- Jika nama database berbeda, ubah `$dbname` (dan sesuaikan perintah import).
- Berkas ini juga memaksa zona waktu `Asia/Jakarta` dan `SET time_zone = '+07:00'`. **Jangan dihapus**: bawaan `php.ini` XAMPP memakai `Europe/Berlin`, sehingga tanpa penyamaan ini validasi batas layak konsumsi meleset 6 jam.
- Pastikan folder `uploads/items` dan `uploads/proofs` dapat ditulis server (di Windows umumnya sudah bisa; folder dibuat otomatis bila hilang).

## 6. Panduan Menjalankan Aplikasi

**Cara A — lewat Apache (disarankan)**

1. Pastikan proyek berada di `C:\xampp\htdocs\ZeroHunger` dan Apache + MySQL aktif.
2. Buka `http://localhost/ZeroHunger/index.php`.
3. Halaman utama menampilkan statistik dampak dan daftar penyaluran terverifikasi. Dari navbar: **Login**, **Register**, **Dampak**.

**Cara B — server bawaan PHP (tanpa Apache)**

```
C:\xampp\php\php.exe -S localhost:8000 -t C:\xampp\htdocs\ZeroHunger
```

Lalu buka `http://localhost:8000/index.php`.

**Akun demo** (password semua: `password123`)

| Email | Peran |
|---|---|
| admin@berbagi.test | admin |
| warung@berbagi.test | donatur terverifikasi |
| katering@berbagi.test | donatur menunggu verifikasi |
| panti@berbagi.test | penerima |
| posyandu@berbagi.test | penerima |
| komunitas@berbagi.test | penerima |

**Alur uji coba yang disarankan (urutan demo)**

1. Login sebagai **admin** → panel admin menampilkan 1 donatur menunggu verifikasi → klik **Verifikasi**.
2. Login sebagai **warung@berbagi.test** (donatur) → isi form "Bagikan Makanan Berlebih" (kategori, porsi, batas layak konsumsi, metode diantar/diambil, foto) → donasi muncul di daftar donasi sendiri.
3. Login sebagai **panti@berbagi.test** (penerima) → cari makanan dengan filter, klik **Ajukan Klaim**, isi porsi dan alamat kirim bila metodenya diantar.
4. Kembali ke akun donatur → **Setujui** klaim yang masuk.
5. Kembali ke akun penerima → **Konfirmasi Terima** dengan mengunggah foto bukti → status klaim menjadi `selesai` → beri **ulasan** bintang.
6. Buka **Dampak** (dapat diakses tanpa login) → angka porsi terselamatkan, penerima terbantu, dan rekap kategori ikut bertambah.

**Peran pengguna dan hak akses**

| Peran | enum `users.role` | Yang bisa dilakukan |
|---|---|---|
| Donatur | `donor` | Posting donasi, setujui/tolak klaim, edit/hapus donasi yang belum ada klaim |
| Penerima | `beneficiary` | Live search, ajukan klaim, unggah bukti terima, beri ulasan |
| Admin | `admin` | Verifikasi/tolak akun donatur, audit read-only semua donasi, CRUD kategori |

Donatur yang belum diverifikasi tetap bisa posting, tetapi donasinya belum muncul di pencarian penerima. Peran admin tidak bisa didaftarkan sendiri melalui halaman register; akunnya disiapkan lewat data awal.

## Lampiran

### Aturan bisnis penting

1. **Sisa porsi** = `porsi` dikurangi total klaim berstatus `menunggu`, `disetujui`, dan `selesai`. Klaim `menunggu` sudah dihitung terpesan; klaim `ditolak` otomatis melepas porsinya. Dihitung di view `v_donations` sebagai `sisa_porsi`.
2. **Klaim** divalidasi di dalam transaksi dengan `SELECT ... FOR UPDATE` pada baris donasi, jadi dua penerima tidak bisa mengklaim porsi yang sama.
3. **Kadaluarsa**: donasi yang melewati `expires_at` hilang dari pencarian dan klaimnya ditolak.
4. **Metode penyerahan**: `antar` mewajibkan penerima mengisi alamat kirim; `ambil` tidak memakainya.
5. **Status donasi** diturunkan otomatis oleh `syncDonationStatus()`: `distributed` bila porsi tersalurkan sudah penuh, `claimed` bila masih ada klaim aktif, `pending` bila tidak ada.
6. **Satu ulasan per klaim** (UNIQUE di database), rating dibatasi 1–5 lewat CHECK constraint.
7. **Verifikasi akun** hanya berlaku untuk akun berperan donatur.

### Struktur berkas

```
index.php                 landing page publik (statistik + distribusi terverifikasi)
dashboard.php             halaman dampak publik (porsi terselamatkan, rekap kategori/metode)
login.php register.php    masuk dan daftar untuk peran donatur & penerima
logout.php                logout bertoken CSRF
donor_dashboard.php       posting donasi, keputusan klaim, daftar donasi sendiri
edit_donation.php         edit donasi (ditolak bila sudah ada klaim aktif)
penerima_dashboard.php    live search, ajukan klaim, bukti terima, ulasan
admin_dashboard.php       verifikasi donatur, daftar akun, audit donasi
admin_categories.php      CRUD kategori
profile.php               ubah nama/HP, ganti password, tema gelap (cookie)
config/db.php             koneksi PDO + helper (e, requireRole, roleHome, csrf*, syncDonationStatus, handleUpload)
assets/css/style.css      tema terang/gelap, badge, tabel, filter bar
assets/js/live-search.js  filter kata kunci + kategori + metode tanpa reload
database/                 skema awal, migrasi v2 & v3, seed demo
uploads/items             foto makanan yang didonasikan
uploads/proofs            foto bukti terima dari penerima
```

Catatan: folder `docs/` (draft PRD beserta hasil generate HTML/PDF) tidak ikut masuk repo karena sudah terdaftar di `.gitignore`.

### Keamanan yang sudah diterapkan

- Password di-hash (`password_hash` / `password_verify`); seluruh query memakai prepared statement PDO.
- Token CSRF di semua form POST dan pada tautan logout, diverifikasi dengan `hash_equals`.
- Semua output dinamis di-escape lewat helper `e()` (`htmlspecialchars`).
- Unggahan berkas divalidasi tipe MIME dengan `finfo`, dibatasi ukurannya, nama berkas diacak, hanya jpg/png/webp.
- Guard peran `requireRole()` di setiap halaman dashboard; kepemilikan data diperiksa di klausa SQL (mis. donatur hanya bisa memutus klaim donasinya sendiri).
- Alamat kirim penerima tidak pernah ditampilkan di halaman publik.

### Catatan lingkungan

- **Zona waktu**: `php.ini` XAMPP memakai `Europe/Berlin` sedangkan MySQL memakai jam sistem; `config/db.php` menyamakan keduanya ke `Asia/Jakarta` dan `+07:00`.
- **`sql_mode` tidak strict** di XAMPP: kolom NOT NULL tanpa nilai default bisa terisi string kosong tanpa error, jadi NOT NULL jangan dijadikan satu-satunya validasi.
- **`php -S` bersifat single-thread**: satu permintaan yang menggantung memblokir permintaan berikutnya; saat menguji dengan curl, gunakan `--max-time` dan header `Connection: close`.

### Utang teknis

- `berbagi_db.sql` masih menyimpan skema versi awal dan hanya berguna sebagai titik awal migrasi.
- `v_donations` menghitung `sisa_porsi` dengan subquery per baris; cukup untuk skala praktikum, ganti dengan kolom tersimpan bila data membesar.
- Belum ada rate limit atau captcha pada halaman register.
- Halaman publik (`index.php`) belum menerapkan tema gelap; baru halaman setelah login.
- Belum ada notifikasi ke donatur saat ada klaim baru (saat ini harus membuka dashboard).
