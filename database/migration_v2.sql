-- ============================================================
-- Berbagi -> versi SDG 2 (Zero Hunger): 3 role, 5 tabel
-- Target : MariaDB 10.11 / XAMPP, database `berbagi_db`
-- Jalankan: "/c/xampp/mysql/bin/mysql.exe" -u root berbagi_db < database/migration_v2.sql
-- Sekali jalan (DDL MariaDB auto-commit, tidak bisa di-rollback).
--
-- Prinsip: kolom lama (category, quantity, item_video, volunteer_id) DIPERTAHANKAN
-- supaya file PHP versi lama tetap jalan selama transisi. Buang nanti setelah
-- donasi/laporan semua file PHP memakai kolom baru.
--
-- Pemetaan role: donor=donatur (post donasi), beneficiary=penerima (klaim),
--                admin=pengawas (verifikasi donatur, moderasi, audit read-only).
-- Pemetaan status donasi: pending=tersedia, claimed=diproses, distributed=selesai
--                (nilai enum tidak diubah supaya data lama tidak rusak).
-- ============================================================

USE berbagi_db;

-- ------------------------------------------------------------
-- 1. users: role baru + status verifikasi donatur
-- ------------------------------------------------------------
-- WAJIB duluan: konversi data sebelum enum dipersempit, kalau tidak
-- baris role='volunteer' jadi string kosong di mode strict.
UPDATE users SET role = 'admin' WHERE role = 'volunteer';

ALTER TABLE users
  MODIFY `role` ENUM('donor','beneficiary','admin') NOT NULL,
  ADD COLUMN verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'verified' AFTER `role`,
  ADD COLUMN verified_at DATETIME NULL AFTER verification_status,
  ADD COLUMN verified_by INT UNSIGNED NULL AFTER verified_at;

-- akun lama dianggap sah (default 'verified'); PHP set 'pending' untuk pendaftaran donatur baru
ALTER TABLE users
  ADD CONSTRAINT fk_users_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- 2. categories: ganti ENUM kategori yang di-hardcode di PHP
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  deskripsi VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO categories (nama, deskripsi) VALUES
  ('Makanan Siap Santap', 'Nasi kotak, lauk matang, sisa prasmanan acara'),
  ('Roti & Kue',          'Bakery, pastry, kue basah maupun kering'),
  ('Buah & Sayur Segar',  'Produk segar siap olah'),
  ('Sembako & Bahan Pokok','Beras, minyak, telur, mie instan');

-- ------------------------------------------------------------
-- 3. donations: porsi, kadaluarsa, metode pengiriman, laporan
-- ------------------------------------------------------------
ALTER TABLE donations
  ADD COLUMN category_id INT UNSIGNED NULL AFTER category,
  ADD COLUMN porsi INT UNSIGNED NULL AFTER quantity,
  ADD COLUMN expires_at DATETIME NULL AFTER porsi,
  ADD COLUMN delivery_method ENUM('ambil','antar') NOT NULL DEFAULT 'ambil' AFTER pickup_address,
  ADD COLUMN report_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER status;

-- backfill kategori: cocokkan langsung, sisanya 'Food' -> Siap Santap, lalu default
UPDATE donations d JOIN categories c ON c.nama = d.category SET d.category_id = c.id WHERE d.category_id IS NULL;
UPDATE donations d JOIN categories c ON c.nama = 'Makanan Siap Santap' SET d.category_id = c.id
  WHERE d.category_id IS NULL AND d.category = 'Food';
UPDATE donations SET category_id = (SELECT id FROM categories WHERE nama = 'Sembako & Bahan Pokok')
  WHERE category_id IS NULL;

-- backfill porsi dari string quantity bebas ("30 kotak" -> 30)
UPDATE donations SET porsi = CAST(REGEXP_SUBSTR(quantity, '[0-9]+') AS UNSIGNED)
  WHERE porsi IS NULL AND quantity REGEXP '[0-9]+';
UPDATE donations SET porsi = 1 WHERE porsi IS NULL;
ALTER TABLE donations MODIFY `porsi` INT UNSIGNED NOT NULL DEFAULT 1;

-- backfill kadaluarsa baris lama supaya data demo tidak langsung hilang dari listing
UPDATE donations SET expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE expires_at IS NULL;
-- sengaja tetap NULL-able: PHP versi lama belum mengirim expires_at, dan NULL = "tanpa batas waktu"

ALTER TABLE donations
  ADD KEY idx_status_expires (status, expires_at),
  ADD KEY idx_delivery (delivery_method),
  ADD CONSTRAINT fk_donations_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- 4. claims: pengajuan klaim porsi oleh penerima
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS claims (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donation_id INT UNSIGNED NOT NULL,
  beneficiary_id INT UNSIGNED NOT NULL,
  porsi_diminta INT UNSIGNED NOT NULL,
  alasan TEXT NULL,
  delivery_address TEXT NULL,   -- wajib hanya bila donations.delivery_method = 'antar'
  scheduled_at DATETIME NULL,
  status_claim ENUM('menunggu','disetujui','ditolak','selesai') NOT NULL DEFAULT 'menunggu',
  proof_photo VARCHAR(500) NULL,
  received_notes TEXT NULL,
  received_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_claims_donation (donation_id),
  KEY idx_claims_beneficiary (beneficiary_id),
  KEY idx_claims_status (status_claim),
  CONSTRAINT fk_claims_donation FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
  CONSTRAINT fk_claims_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_claims_porsi CHECK (porsi_diminta > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 5. reviews: ulasan penerima setelah makanan diterima
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  claim_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  komentar TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reviews_claim (claim_id),   -- 1 ulasan per klaim
  CONSTRAINT fk_reviews_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- 6. view: sisa porsi per donasi (dipakai penerima & admin)
-- ------------------------------------------------------------
-- 'menunggu' ikut dihitung sebagai terpesan supaya satu porsi tidak diklaim dua kali;
-- klaim 'ditolak' otomatis melepas porsinya.
-- ponytail: subquery per baris, cukup untuk skala praktikum; kalau data besar,
-- ganti dengan kolom sisa_porsi yang di-update di transaksi klaim.
CREATE OR REPLACE VIEW v_donations AS
SELECT
  d.*,
  c.nama AS category_name,
  u.full_name AS donor_name,
  u.phone AS donor_phone,
  u.verification_status AS donor_verification,
  d.porsi - COALESCE((
    SELECT SUM(cl.porsi_diminta) FROM claims cl
    WHERE cl.donation_id = d.id AND cl.status_claim IN ('menunggu','disetujui','selesai')
  ), 0) AS sisa_porsi
FROM donations d
LEFT JOIN categories c ON c.id = d.category_id
JOIN users u ON u.id = d.donor_id;
