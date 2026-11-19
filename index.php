<?php
require_once __DIR__ . '/config/db.php';

$totalDonations = $pdo->query("SELECT COUNT(*) FROM donations")->fetchColumn();
$distributedCount = $pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'distributed'")->fetchColumn();
$volunteerCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'volunteer'")->fetchColumn();

$stmt = $pdo->query("
    SELECT d.*, u.full_name AS donor_name, v.full_name AS volunteer_name
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    LEFT JOIN users v ON d.volunteer_id = v.id
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
    <title>Berbagi - Share Kindness, Deliver Hope</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Berbagi</a>
        <div class="nav-links">
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="nav-user">Hi, <?= e($_SESSION['full_name']) ?> (<?= e(ucfirst($_SESSION['role'])) ?>)</span>
                <?php if ($_SESSION['role'] === 'donor'): ?>
                    <a href="donor_dashboard.php" class="btn btn-primary btn-sm">Donor Dashboard</a>
                <?php else: ?>
                    <a href="volunteer_dashboard.php" class="btn btn-primary btn-sm">Volunteer Dashboard</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline btn-sm">Login</a>
                <a href="register.php" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<section class="hero" style="background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('./assets/images/header.png'); height: 75vh; background-size: cover; background-position: center;">
    <h1>Share What You Have, Reach Who Needs It Most</h1>
    <p>Berbagi connects generous donors with dedicated volunteers. Submit clothes, food, toys, or essentials with photo &amp; video proof, and track real photo proof when volunteers deliver them.</p>
    <div class="hero-actions">
        <a href="register.php?role=donor" class="btn btn-accent">Share Items (Donor)</a>
        <a href="register.php?role=volunteer" class="btn btn-outline" style="color:#fff; border-color:#fff;">Join as Volunteer</a>
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
        <h3><?= (int)$volunteerCount ?></h3>
        <p>Active Volunteers</p>
    </div>
</div>

<main class="container">
    <h2 class="section-title">Recent Verified Distributions</h2>
    <?php if (empty($recentDistributions)): ?>
        <p class="meta-text">No completed distributions yet. Be the first to share or volunteer!</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($recentDistributions as $item): ?>
                <article class="card">
                    <div class="media-row">
                        <img src="<?= e($item['item_photo']) ?>" alt="Donated item">
                        <img src="<?= e($item['distribution_photo']) ?>" alt="Distribution proof">
                    </div>
                    <div class="card-body">
                        <div>
                            <span class="badge badge-distributed">Distributed</span>
                            <span class="badge badge-target">Target: <?= e($item['target_group']) ?></span>
                        </div>
                        <h3><?= e($item['title']) ?></h3>
                        <p class="meta-text">
                            <strong>Category:</strong> <?= e($item['category']) ?> | <strong>Qty:</strong> <?= e($item['quantity']) ?>
                        </p>
                        <p class="meta-text">
                            <strong>Donor:</strong> <?= e($item['donor_name']) ?> &rarr; <strong>Delivered by:</strong> <?= e($item['volunteer_name']) ?>
                        </p>
                        <?php if (!empty($item['distribution_notes'])): ?>
                            <p style="font-size:0.9rem;">"<?= e($item['distribution_notes']) ?>"</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> Berbagi Platform. Built with native HTML, CSS &amp; PHP.</p>
</footer>
</body>
</html>