-- ============================================================
-- migration_v3: buang kolom warisan versi "donasi barang"
-- Target : MariaDB 10.11 / XAMPP, database `berbagi_db`
-- Jalankan SETELAH migration_v2.sql, dan setelah kode PHP memakai kolom baru.
-- Jalankan: "/c/xampp/mysql/bin/mysql.exe" -u root berbagi_db < database/migration_v3.sql
-- ============================================================

USE berbagi_db;

-- View dibuang dulu: definisinya memakai `d.*`, jadi dia memegang kolom lama
-- yang sebentar lagi dihapus dan akan error kalau dibiarkan.
DROP VIEW IF EXISTS v_donations;

-- FK + index milik kolom yang akan dibuang harus dilepas lebih dulu.
ALTER TABLE donations DROP FOREIGN KEY fk_donations_volunteer;
ALTER TABLE donations DROP KEY idx_volunteer;

ALTER TABLE donations
  DROP COLUMN category,            -- sudah digantikan category_id
  DROP COLUMN quantity,            -- sudah digantikan porsi (INT)
  DROP COLUMN target_group,        -- konsep lama, tidak dipakai di alur pangan
  DROP COLUMN item_video,          -- donasi pangan cukup foto
  DROP COLUMN volunteer_id,        -- relawan sudah tidak ada; admin hanya mengawasi
  DROP COLUMN distribution_photo,  -- bukti terima pindah ke claims.proof_photo
  DROP COLUMN distribution_notes,  -- catatan terima pindah ke claims.received_notes
  DROP COLUMN claimed_at;          -- klaim sekarang per-penerima di tabel claims

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
