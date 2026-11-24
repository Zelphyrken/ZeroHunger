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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= themeClass() === 'dark' ? '#15251e' : '#f7f5ed' ?>">
    <title>Dampak - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body class="app-page <?= themeClass() ?>">
<?php require __DIR__ . '/includes/navigation.php'; ?>

<main class="container" id="main-content" tabindex="-1">
    <header class="page-header"><h1>Dampak bersama</h1><p>Angka dihitung dari klaim yang benar-benar selesai, setelah penerima mengirim bukti terima.</p></header>


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

    <h2 class="section-title">Cara Porsi Disalurkan</h2>
    <table class="table">
        <thead>
            <tr><th scope="col">Metode</th><th scope="col">Klaim Selesai</th><th scope="col">Porsi</th></tr>
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

    <h2 class="section-title">Rekap per Kategori</h2>
    <table class="table">
        <thead>
            <tr><th scope="col">Kategori</th><th scope="col">Jumlah Donasi</th><th scope="col">Porsi Tersalur</th></tr>
        </thead>
        <tbody>
            <?php if (empty($byCategory)): ?>
                <tr><td colspan="3">Belum ada kategori untuk direkap.</td></tr>
            <?php endif; ?>
            <?php foreach ($byCategory as $row): ?>
                <tr>
                    <td><?= e($row['nama']) ?></td>
                    <td><?= (int)$row['jumlah_donasi'] ?></td>
                    <td><?= (int)$row['porsi_tersalur'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2 class="section-title">Ulasan Penerima</h2>
    <p class="meta-text">
        <?php if ($ratingAvg !== null): ?>
            Rata-rata bintang <strong><?= $ratingAvg ?></strong> dari <?= (int)$reviewRow['jumlah'] ?> ulasan.
        <?php else: ?>
            Belum ada ulasan.
        <?php endif; ?>
    </p>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
