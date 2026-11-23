<?php
require_once __DIR__ . '/config/db.php';

$totalDonations = $pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn();
$distributedCount = $pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'distributed'")->fetchColumn();
$verifiedDonors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'donor' AND verification_status = 'verified'")->fetchColumn();

// Bukti terima sekarang disimpan di claims (bukan lagi di donations.distribution_photo).
$stmt = $pdo->query("
    SELECT d.*, u.full_name AS donor_name,
           (SELECT cl.proof_photo FROM claims cl
            WHERE cl.donation_id = d.id AND cl.proof_photo IS NOT NULL
            ORDER BY cl.id DESC LIMIT 1) AS proof_photo,
           (SELECT cl.received_notes FROM claims cl
            WHERE cl.donation_id = d.id AND cl.received_notes IS NOT NULL
            ORDER BY cl.id DESC LIMIT 1) AS received_notes
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.status = 'distributed'
    ORDER BY d.distributed_at DESC
    LIMIT 6
");
$recentDistributions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Porsela - Rescue Surplus Food, Feed Those in Need</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Porsela</a>
        <div class="nav-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="nav-user">Hi, <?= e($_SESSION['full_name']) ?> (<?= e(roleLabel($_SESSION['role'])) ?>)</span>
                <a href="<?= e(roleHome($_SESSION['role'])) ?>" class="btn btn-primary btn-sm">Dashboard</a>
                <a href="dashboard.php" class="btn btn-outline btn-sm">Dampak</a>
                <a href="logout.php?csrf=<?= csrfToken() ?>" class="btn btn-outline btn-sm">Logout</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-outline btn-sm">Dampak</a>
                <a href="login.php" class="btn btn-outline btn-sm">Login</a>
                <a href="register.php" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<section class="hero" style="background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('./assets/images/header.png'); height: 75vh; background-size: cover; background-position: center;">
    <h1>Turn Surplus Food Into Someone's Meal</h1>
    <p>Porsela connects food donors &mdash; restaurants, bakeries, caterers, households &mdash; with the people who need it most. Post surplus food with a photo, set its safe-to-eat deadline, and let receivers claim the portions they need. Supporting SDG 2: Zero Hunger.</p>
    <div class="hero-actions">
        <a href="register.php?role=donor" class="btn btn-accent">Share Surplus Food (Donor)</a>
        <a href="register.php?role=beneficiary" class="btn btn-outline" style="color:#fff; border-color:#fff;">Find Food Nearby (Receiver)</a>
    </div>
</section>

<div class="stats-grid">
    <div class="stat-card">
        <h3><?= (int)$totalDonations ?></h3>
        <p>Total Submissions</p>
    </div>
    <div class="stat-card">
        <h3><?= (int)$distributedCount ?></h3>
        <p>Items Distributed</p>
    </div>
    <div class="stat-card">
            <h3><?= (int)$verifiedDonors ?></h3>
            <p>Verified Donors</p>
        </div>
    </div>

    <main class="container">
        <h2 class="section-title">Recent Verified Distributions</h2>
        <?php if (empty($recentDistributions)): ?>
            <p class="meta-text">No completed distributions yet. Be the first to share surplus food!</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($recentDistributions as $item): ?>
                <article class="card">
                    <div class="media-row">
                                            <img src="<?= e($item['item_photo']) ?>" alt="Food donation">
                                            <img src="<?= e($item['proof_photo'] ?? '') ?>" alt="Proof of receipt">
                                        </div>
                                        <div class="card-body">
                                            <div>
                                                <span class="badge badge-distributed">Distributed</span>
                                                <span class="badge badge-target"><?= $item['delivery_method'] === 'antar' ? 'Delivered' : 'Picked up' ?></span>
                                            </div>
                                            <h3><?= e($item['title']) ?></h3>
                                            <p class="meta-text">
                                                <strong>Category:</strong> <?= e($item['category_name'] ?? '-') ?> | <strong>Porsi:</strong> <?= (int)$item['porsi'] ?>
                                            </p>
                                            <p class="meta-text">
                                                <strong>Donor:</strong> <?= e($item['donor_name']) ?>
                                            </p>
                                            <?php if (!empty($item['received_notes'])): ?>
                                                <p style="font-size:0.9rem;">"<?= e($item['received_notes']) ?>"</p>
                                            <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Porsela Platform &mdash; porsi berlebih jadi makanan. Built with native HTML, CSS &amp; PHP. Supporting SDG 2: Zero Hunger.</p>
</footer>
</body>
</html>