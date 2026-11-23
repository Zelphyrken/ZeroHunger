-- ============================================================
-- Data demo untuk versi SDG 2 (Zero Hunger)
-- Prasyarat: database/migration_v2.sql sudah dijalankan.
-- Jalankan: "/c/xampp/mysql/bin/mysql.exe" -u root berbagi_db < database/seed_demo.sql
--
-- Bisa dijalankan berulang tanpa menggandakan data (insert dijaga
-- oleh UNIQUE email dan pengecekan judul donasi / pasangan klaim).
--
-- Semua akun demo memakai password: password123
-- ============================================================

USE berbagi_db;

SET @pw = '$2y$10$gKSV8eiCQVQ.o8HDAQcIK.0Q1cNged.nhZmeKKzkUiJFYt8sQzwIi';

-- ------------------------------------------------------------
-- Akun: 1 admin, 2 donatur (1 terverifikasi + 1 belum), 3 penerima
-- ------------------------------------------------------------
INSERT INTO users (full_name, email, phone, password_hash, role, verification_status, verified_at) VALUES
  ('Admin Berbagi',          'admin@berbagi.test',    '081200000001', @pw, 'admin',       'verified', NOW()),
  ('Warung Bu Sari',         'warung@berbagi.test',   '081200000002', @pw, 'donor',       'verified', NOW()),
  ('Katering Rasa Ibu',      'katering@berbagi.test', '081200000003', @pw, 'donor',       'pending',  NULL),
  ('Panti Asuhan Harapan',   'panti@berbagi.test',    '081200000004', @pw, 'beneficiary', 'verified', NOW()),
  ('Posyandu Melati',        'posyandu@berbagi.test', '081200000005', @pw, 'beneficiary', 'verified', NOW()),
  ('Komunitas Berbagi Makan','komunitas@berbagi.test','081200000006', @pw, 'beneficiary', 'verified', NOW())
ON DUPLICATE KEY UPDATE
  role = VALUES(role),
  verification_status = VALUES(verification_status),
  verified_at = VALUES(verified_at),
  password_hash = VALUES(password_hash);

-- ------------------------------------------------------------
-- Donasi. 4 milik donatur terverifikasi (tampil), 2 milik donatur
-- pending (harus TIDAK tampil di pencarian penerima).
-- ------------------------------------------------------------
INSERT INTO donations (donor_id, title, category_id, porsi, description, pickup_address, item_photo, delivery_method, expires_at, status)
SELECT u.id, 'Nasi Kotak Ayam Bakar', c.id, 30,
       'Sisa catering acara kantor, masih hangat, dikemas per kotak.',
       'Jl. Sudirman No. 45, Purwokerto', 'uploads/items/e956eb4448cac88a1fcde89b075b599f.jpg',
       'antar', DATE_ADD(NOW(), INTERVAL 12 HOUR), 'pending'
FROM users u JOIN categories c ON c.nama = 'Makanan Siap Santap'
LEFT JOIN donations d0 ON d0.title = 'Nasi Kotak Ayam Bakar'
WHERE u.email = 'warung@berbagi.test' AND d0.id IS NULL;

INSERT INTO donations (donor_id, title, category_id, porsi, description, pickup_address, item_photo, delivery_method, expires_at, status)
SELECT u.id, 'Roti Tawar & Pastry Pagi', c.id, 20,
       'Roti sisa display hari ini, belum lewat tanggal, layak konsumsi.',
       'Toko Roti Bu Sari, Jl. Pahlawan No. 8, Purwokerto', 'uploads/items/15b945c6fc14544a132232f912e4a8da.png',
       'ambil', DATE_ADD(NOW(), INTERVAL 1 DAY), 'claimed'
FROM users u JOIN categories c ON c.nama = 'Roti & Kue'
LEFT JOIN donations d0 ON d0.title = 'Roti Tawar & Pastry Pagi'
WHERE u.email = 'warung@berbagi.test' AND d0.id IS NULL;

INSERT INTO donations (donor_id, title, category_id, porsi, description, pickup_address, item_photo, delivery_method, expires_at, status)
SELECT u.id, 'Bubur Ayam Sisa Dagangan', c.id, 25,
       'Sisa dagangan pagi, masih layak, cocok untuk sarapan.',
       'Pasar Manis, Blok C No. 3, Purwokerto', 'uploads/items/e956eb4448cac88a1fcde89b075b599f.jpg',
       'antar', DATE_ADD(NOW(), INTERVAL 6 HOUR), 'pending'
FROM users u JOIN categories c ON c.nama = 'Makanan Siap Santap'
LEFT JOIN donations d0 ON d0.title = 'Bubur Ayam Sisa Dagangan'
WHERE u.email = 'warung@berbagi.test' AND d0.id IS NULL;

INSERT INTO donations (donor_id, title, category_id, porsi, description, pickup_address, item_photo, delivery_method, expires_at, status)
SELECT u.id, 'Prasmanan Sisa Hajatan', c.id, 40,
       'Prasmanan sisa hajatan, baru disiapkan pagi ini, piringan belum tersentuh.',
       'Jl. Veteran No. 77, Purwokerto', 'uploads/items/e956eb4448cac88a1fcde89b075b599f.jpg',
       'ambil', DATE_ADD(NOW(), INTERVAL 1 DAY), 'distributed'
FROM users u JOIN categories c ON c.nama = 'Makanan Siap Santap'
LEFT JOIN donations d0 ON d0.title = 'Prasmanan Sisa Hajatan'
WHERE u.email = 'warung@berbagi.test' AND d0.id IS NULL;

INSERT INTO donations (donor_id, title, category_id, porsi, description, pickup_address, item_photo, delivery_method, expires_at, status)
SELECT u.id, 'Sayur Bayam & Wortel Segar', c.id, 15,
       'Sisa order pelanggan yang batal, kondisi masih segar.',
       'Dapur Katering Rasa Ibu, Jl. Gerilya No. 21, Purwokerto', 'uploads/items/e956eb4448cac88a1fcde89b075b599f.jpg',
       'ambil', DATE_ADD(NOW(), INTERVAL 2 DAY), 'pending'
FROM users u JOIN categories c ON c.nama = 'Buah & Sayur Segar'
LEFT JOIN donations d0 ON d0.title = 'Sayur Bayam & Wortel Segar'
WHERE u.email = 'katering@berbagi.test' AND d0.id IS NULL;

INSERT INTO donations (donor_id, title, category_id, porsi, description, pickup_address, item_photo, delivery_method, expires_at, status)
SELECT u.id, 'Beras & Minyak Goreng', c.id, 5,
       'Paket sembako bantuan yang belum terdistribusi.',
       'Dapur Katering Rasa Ibu, Jl. Gerilya No. 21, Purwokerto', 'uploads/items/15b945c6fc14544a132232f912e4a8da.png',
       'antar', DATE_ADD(NOW(), INTERVAL 7 DAY), 'pending'
FROM users u JOIN categories c ON c.nama = 'Sembako & Bahan Pokok'
LEFT JOIN donations d0 ON d0.title = 'Beras & Minyak Goreng'
WHERE u.email = 'katering@berbagi.test' AND d0.id IS NULL;

-- ------------------------------------------------------------
-- Klaim penerima: 1 menunggu (antar), 1 disetujui (ambil), 1 selesai + bukti
-- ------------------------------------------------------------
INSERT INTO claims (donation_id, beneficiary_id, porsi_diminta, alasan, delivery_address, scheduled_at, status_claim, proof_photo, received_notes, received_at)
SELECT d.id, u.id, 15, 'Untuk makan malam 15 anak panti.', 'Jl. Panti Asuhan No. 12, Purwokerto',
       DATE_ADD(NOW(), INTERVAL 6 HOUR), 'menunggu', NULL, NULL, NULL
FROM donations d JOIN users u ON u.email = 'panti@berbagi.test'
LEFT JOIN claims c0 ON c0.donation_id = d.id AND c0.beneficiary_id = u.id
WHERE d.title = 'Nasi Kotak Ayam Bakar' AND c0.id IS NULL;

INSERT INTO claims (donation_id, beneficiary_id, porsi_diminta, alasan, delivery_address, scheduled_at, status_claim, proof_photo, received_notes, received_at)
SELECT d.id, u.id, 5, 'Untuk posyandu balita, diambil sendiri pukul 15.00.', NULL,
       DATE_ADD(NOW(), INTERVAL 1 DAY), 'disetujui', NULL, NULL, NULL
FROM donations d JOIN users u ON u.email = 'posyandu@berbagi.test'
LEFT JOIN claims c0 ON c0.donation_id = d.id AND c0.beneficiary_id = u.id
WHERE d.title = 'Roti Tawar & Pastry Pagi' AND c0.id IS NULL;

INSERT INTO claims (donation_id, beneficiary_id, porsi_diminta, alasan, delivery_address, scheduled_at, status_claim, proof_photo, received_notes, received_at)
SELECT d.id, u.id, 40, 'Dimasak ulang untuk dapur umum komunitas.',
       NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), 'selesai',
       'uploads/proofs/4e609b8c123e43ffea275bf37d826dfb.jpg',
       'Diterima lengkap 40 porsi, langsung disalurkan malam itu.',
       DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM donations d JOIN users u ON u.email = 'komunitas@berbagi.test'
LEFT JOIN claims c0 ON c0.donation_id = d.id AND c0.beneficiary_id = u.id
WHERE d.title = 'Prasmanan Sisa Hajatan' AND c0.id IS NULL;

-- ------------------------------------------------------------
-- Ulasan untuk klaim yang sudah selesai
-- ------------------------------------------------------------
INSERT INTO reviews (claim_id, rating, komentar)
SELECT c.id, 5, 'Makanannya masih layak dan porsinya sesuai janji. Terima kasih Bu Sari!'
FROM claims c JOIN donations d ON d.id = c.donation_id
LEFT JOIN reviews r ON r.claim_id = c.id
WHERE d.title = 'Prasmanan Sisa Hajatan' AND r.id IS NULL;

-- ------------------------------------------------------------
-- Samakan status donasi dengan kondisi klaimnya. Tanpa ini, seed meninggalkan
-- donasi ber-badge "tersedia" padahal sudah ada klaim yang menunggu diputus.
UPDATE donations d SET d.status = 'claimed'
WHERE d.status != 'distributed'
  AND EXISTS (
    SELECT 1 FROM claims c
    WHERE c.donation_id = d.id AND c.status_claim IN ('menunggu', 'disetujui')
  );

-- Cek hasil
-- ------------------------------------------------------------
SELECT 'users' AS tabel, COUNT(*) AS baris FROM users
UNION ALL SELECT 'categories', COUNT(*) FROM categories
UNION ALL SELECT 'donations', COUNT(*) FROM donations
UNION ALL SELECT 'claims', COUNT(*) FROM claims
UNION ALL SELECT 'reviews', COUNT(*) FROM reviews;

-- Donasi yang TAMPIL ke penerima (donatur terverifikasi, belum kadaluarsa, sisa porsi > 0)
SELECT id, title, category_name, porsi, sisa_porsi, delivery_method, expires_at
FROM v_donations
WHERE donor_verification = 'verified' AND status != 'distributed' AND expires_at > NOW() AND sisa_porsi > 0;
