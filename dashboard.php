<?php
require_once __DIR__ . '/config/db.php';
// Halaman dampak: bisa dibuka siapa saja, tanpa login.
$totalPorsi = (int)$pdo->query("SELECT COALESCE(SUM(porsi_diminta), 0) FROM claims WHERE status_claim = 'selesai'")->fetchColumn();
$penerimaTerbantu = (int)$pdo->query("SELECT COUNT(DISTINCT beneficiary_id) FROM claims WHERE status_claim = 'selesai'")->fetchColumn();
$donaturAktif = (int)$pdo->query("
    SELECT COUNT(DISTINCT d.donor_id)
    FROM claims cl JOIN donations d ON d.id = cl.donation_id
    WHERE cl.status_claim IN ('disetujui', 'selesai')
")->fetchColumn();
$donasiTersedia = (int)$pdo->query("
    SELECT COUNT(*) FROM v_donations
    WHERE donor_verification = 'verified' AND status != 'distributed'
      AND (expires_at IS NULL OR expires_at > NOW()) AND sisa_porsi > 0
")->fetchColumn();

$byMethod = $pdo->query("
    SELECT d.delivery_method, COALESCE(SUM(cl.porsi_diminta), 0) AS porsi, COUNT(*) AS jumlah_klaim
    FROM claims cl JOIN donations d ON d.id = cl.donation_id
    WHERE cl.status_claim = 'selesai'
    GROUP BY d.delivery_method
")->fetchAll();

$byCategory = $pdo->query("
    SELECT c.nama,
           COUNT(DISTINCT d.id) AS jumlah_donasi,
           COALESCE(SUM(CASE WHEN cl.status_claim = 'selesai' THEN cl.porsi_diminta END), 0) AS porsi_tersalur
    FROM categories c
    LEFT JOIN donations d ON d.category_id = c.id
    LEFT JOIN claims cl ON cl.donation_id = d.id
    GROUP BY c.id, c.nama
    ORDER BY porsi_tersalur DESC, c.nama
")->fetchAll();

$reviewRow = $pdo->query("SELECT AVG(rating) AS rata, COUNT(*) AS jumlah FROM reviews")->fetch();
$ratingAvg = $reviewRow['rata'] !== null ? round((float)$reviewRow['rata'], 1) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dampak - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= themeClass() ?>">
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Porsela</a>
        <div class="nav-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= e(roleHome($_SESSION['role'])) ?>" class="btn btn-outline btn-sm">Dashboard</a>
                <a href="logout.php?csrf=<?= csrfToken() ?>" class="btn btn-outline btn-sm">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline btn-sm">Login</a>
                <a href="register.php" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container">
    <h2 class="section-title">Dampak Porsela</h2>
    <p class="meta-text">Angka dihitung dari klaim yang benar-benar selesai (penerima sudah kirim bukti terima).</p>

    <div class="stats-grid stats-grid-inline">
        <div class="stat-card">
            <h3><?= $totalPorsi ?></h3>
            <p>Porsi Terselamatkan</p>
        </div>
        <div class="stat-card">
            <h3><?= $penerimaTerbantu ?></h3>
            <p>Penerima Terbantu</p>
        </div>
        <div class="stat-card">
            <h3><?= $donaturAktif ?></h3>
            <p>Donatur Aktif</p>
        </div>
        <div class="stat-card">
            <h3><?= $donasiTersedia ?></h3>
            <p>Donasi Menunggu Diklaim</p>
        </div>
    </div>

    <h3 class="section-title">Cara Porsi Disalurkan</h3>
    <table class="table">
        <thead>
            <tr><th>Metode</th><th>Klaim Selesai</th><th>Porsi</th></tr>
        </thead>
        <tbody>
            <?php if (empty($byMethod)): ?>
                <tr><td colspan="3">Belum ada klaim yang selesai.</td></tr>
            <?php else: ?>
                <?php foreach ($byMethod as $row): ?>
                    <tr>
                        <td><?= $row['delivery_method'] === 'antar' ? 'Diantar donatur' : 'Diambil penerima' ?></td>
                        <td><?= (int)$row['jumlah_klaim'] ?></td>
                        <td><?= (int)$row['porsi'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h3 class="section-title" style="margin-top:2.5rem;">Rekap per Kategori</h3>
    <table class="table">
        <thead>
            <tr><th>Kategori</th><th>Jumlah Donasi</th><th>Porsi Tersalur</th></tr>
        </thead>
        <tbody>
            <?php foreach ($byCategory as $row): ?>
                <tr>
                    <td><?= e($row['nama']) ?></td>
                    <td><?= (int)$row['jumlah_donasi'] ?></td>
                    <td><?= (int)$row['porsi_tersalur'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3 class="section-title" style="margin-top:2.5rem;">Ulasan Penerima</h3>
    <p class="meta-text">
        <?php if ($ratingAvg !== null): ?>
            Rata-rata bintang <strong><?= $ratingAvg ?></strong> dari <?= (int)$reviewRow['jumlah'] ?> ulasan.
        <?php else: ?>
            Belum ada ulasan.
        <?php endif; ?>
    </p>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Porsela Platform &mdash; porsi berlebih jadi makanan. Supporting SDG 2: Zero Hunger.</p>
</footer>
</body>
</html>
