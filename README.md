# Porsela

Platform penyaluran makanan berlebih menjadi porsi layak konsumsi. Tema **SDG 2: Zero Hunger**.
Dibangun dengan PHP native + MySQL (XAMPP), tanpa framework, tanpa Composer.

## Peran pengguna

| Peran | enum `users.role` | Yang bisa dilakukan |
|---|---|---|
| Donatur | `donor` | Posting donasi (kategori, porsi, batas layak konsumsi, metode penyerahan, foto), setujui/tolak klaim, edit/hapus donasi yang belum tersalurkan |
| Penerima | `beneficiary` | Cari makanan (live search), ajukan klaim porsi, unggah bukti terima, beri ulasan |
| Admin | `admin` | Verifikasi/tolak akun donatur, moderasi, audit read-only semua donasi, CRUD kategori |

Donatur yang belum diverifikasi tetap bisa posting, tapi donasinya **tidak muncul** di pencarian penerima.

## Menjalankan

Apache + MySQL dari XAMPP, lalu buka `http://localhost/<folder-proyek>/index.php`.
Bisa juga tanpa Apache:

```
C:\xampp\php\php.exe -S localhost:8000 -t E:\ZeroHunger
```

### Setup database (urut, sekali saja)

```
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE berbagi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
C:\xampp\mysql\bin\mysql.exe -u root berbagi_db < database/berbagi_db.sql
C:\xampp\mysql\bin\mysql.exe -u root berbagi_db < database/migration_v2.sql
C:\xampp\mysql\bin\mysql.exe -u root berbagi_db < database/migration_v3.sql
C:\xampp\mysql\bin\mysql.exe -u root berbagi_db < database/seed_demo.sql
```

- `berbagi_db.sql` — skema awal versi donasi barang
- `migration_v2.sql` — 3 peran, tabel `categories` / `claims` / `reviews`, kolom porsi & kadaluarsa, view `v_donations`
- `migration_v3.sql` — buang kolom warisan (jalankan setelah kode PHP versi baru terpasang)
- `seed_demo.sql` — data demo, aman dijalankan berulang

### Akun demo (password semua: `password123`)

| Email | Peran |
|---|---|
| admin@berbagi.test | admin |
| warung@berbagi.test | donatur terverifikasi |
| katering@berbagi.test | donatur menunggu verifikasi |
| panti@berbagi.test | penerima |
| posyandu@berbagi.test | penerima |
| komunitas@berbagi.test | penerima |

## Aturan bisnis yang penting

1. **Sisa porsi** = `porsi` dikurangi total klaim berstatus `menunggu`, `disetujui`, dan `selesai`. Klaim `menunggu` sudah dihitung terpesan, klaim `ditolak` otomatis melepas porsinya. Dihitung di view `v_donations` sebagai `sisa_porsi`.
2. **Klaim** divalidasi di dalam transaksi dengan `SELECT ... FOR UPDATE` pada baris donasi, jadi dua penerima tidak bisa mengklaim porsi yang sama.
3. **Kadaluarsa**: donasi lewat `expires_at` hilang dari pencarian dan klaim ditolak.
4. **Metode penyerahan**: `antar` mewajibkan penerima mengisi alamat kirim; `ambil` tidak.
5. **Status donasi** diturunkan otomatis oleh `syncDonationStatus()`: `distributed` kalau porsi tersalurkan sudah penuh, `claimed` kalau masih ada klaim aktif, `pending` kalau tidak ada.
6. **Satu ulasan per klaim** (UNIQUE di DB), rating dibatasi 1-5 lewat CHECK constraint.
7. **Verifikasi donatur** hanya menyentuh akun dengan `role = 'donor'`.

## Struktur file

```
index.php                 landing page publik (statistik + distribusi terverifikasi)
dashboard.php             halaman dampak publik (porsi terselamatkan, rekap kategori/metode)
login.php register.php    autentikasi 2 peran (donor/beneficiary)
logout.php                logout bertoken CSRF
donor_dashboard.php       posting donasi, keputusan klaim, daftar donasi sendiri
edit_donation.php         edit donasi (ditolak kalau sudah ada klaim aktif)
penerima_dashboard.php    live search, ajukan klaim, bukti terima, ulasan
admin_dashboard.php       verifikasi donatur, daftar akun, audit donasi
admin_categories.php      CRUD kategori
profile.php               ubah nama/HP, ganti password, tema gelap (cookie)
config/db.php             koneksi PDO + helper (e, requireRole, roleHome, csrf*, syncDonationStatus, handleUpload)
assets/css/style.css      tema terang/gelap, badge, tabel, filter bar
assets/js/live-search.js  filter keyword + kategori + metode tanpa reload
database/                 skema, migrasi v2/v3, seed demo
uploads/items, uploads/proofs   foto makanan & bukti terima
```

## Keamanan yang sudah ada

- Password `password_hash()` / `password_verify()`, query selalu prepared statement (PDO, `EMULATE_PREPARES = false`).
- Token CSRF di semua form POST dan pada tautan logout (`hash_equals`).
- Semua output dinamis dilewatkan `e()` (`htmlspecialchars`).
- Upload: MIME dicek `finfo`, ukuran dibatasi, nama file diacak, hanya jpg/png/webp.
- Guard peran: `requireRole()` di setiap halaman dashboard; kepemilikan data dicek di klausa SQL (bukan di PHP), mis. donatur hanya bisa memutus klaim donasinya sendiri.
- Alamat kirim penerima tidak pernah ditampilkan di halaman publik.

## Catatan lingkungan (penting saat pindah mesin)

- **Zona waktu**: `php.ini` XAMPP memakai `Europe/Berlin`, MySQL memakai jam sistem. `config/db.php` memaksa keduanya ke `Asia/Jakarta` + `SET time_zone = '+07:00'`. Jangan dihapus, kalau tidak validasi kadaluarsa meleset 6 jam.
- **`sql_mode` tidak strict** di XAMPP: kolom NOT NULL tanpa default bisa terisi string kosong tanpa error. Jangan andalkan NOT NULL sebagai validasi tunggal.
- `php -S` bawaan PHP bersifat single-thread: satu request yang menggantung akan memblokir request berikutnya. Saat menguji pakai `curl`, selalu set `--max-time` dan header `Connection: close`.

## Utang teknis

- Kolom warisan sudah dibuang di `migration_v3.sql`, tapi berkas `berbagi_db.sql` (skema awal) masih versi lama dan hanya berguna sebagai titik awal migrasi.
- `v_donations` menghitung `sisa_porsi` dengan subquery per baris; cukup untuk skala praktikum, ganti dengan kolom tersimpan kalau data membesar.
- Belum ada rate limit / captcha di halaman register.
- Halaman publik (`index.php`) belum menerapkan tema gelap; baru halaman setelah login.
