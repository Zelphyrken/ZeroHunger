<?php
require_once __DIR__ . '/config/db.php';
requireRole('admin');

$error = '';
$success = '';

// Aksi admin: verifikasi atau tolak akun DONATUR (penerima tidak perlu diverifikasi).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $userId = (int)($_POST['user_id'] ?? 0);
    $decision = ($_POST['decision'] ?? '') === 'verified' ? 'verified' : 'rejected';

    $stmt = $pdo->prepare("
        UPDATE users
        SET verification_status = ?, verified_at = NOW(), verified_by = ?
        WHERE id = ? AND role = 'donor'
    ");
    $stmt->execute([$decision, $_SESSION['user_id'], $userId]);

    if ($stmt->rowCount() > 0) {
        $success = $decision === 'verified' ? "Akun donatur diverifikasi." : "Akun donatur ditolak/diblokir.";
    } else {
        $error = "Akun tidak ditemukan atau bukan akun donatur.";
    }
}

$pending = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM donations d WHERE d.donor_id = u.id) AS donation_count
    FROM users u
    WHERE u.role = 'donor' AND u.verification_status = 'pending'
    ORDER BY u.created_at ASC
")->fetchAll();

$accounts = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM donations d WHERE d.donor_id = u.id) AS donation_count
    FROM users u
    ORDER BY FIELD(u.role, 'donor', 'beneficiary', 'admin'), u.created_at DESC
")->fetchAll();

$audit = $pdo->query("
    SELECT * FROM v_donations
    ORDER BY created_at DESC
    LIMIT 30
")->fetchAll();

$verifBadge = ['pending' => 'badge-pending', 'verified' => 'badge-distributed', 'rejected' => 'badge-claimed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= themeClass() ?>">
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Porsela</a>
        <div class="nav-links">
            <span class="nav-user">Admin: <?= e($_SESSION['full_name']) ?></span>
            <a href="admin_categories.php" class="btn btn-outline btn-sm">Kategori</a>
            <a href="dashboard.php" class="btn btn-outline btn-sm">Dampak</a>
            <a href="index.php" class="btn btn-outline btn-sm">Home</a>
            <a href="logout.php?csrf=<?= csrfToken() ?>" class="btn btn-outline btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <h2 class="section-title">Donatur Menunggu Verifikasi (<?= count($pending) ?>)</h2>
    <?php if (empty($pending)): ?>
        <p class="meta-text">Tidak ada antrean verifikasi.</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($pending as $donor): ?>
                <article class="card">
                    <div class="card-body">
                        <span class="badge badge-pending">Menunggu</span>
                        <h3><?= e($donor['full_name']) ?></h3>
                        <p class="meta-text"><strong>Email:</strong> <?= e($donor['email']) ?><br>
                            <strong>HP/WA:</strong> <?= e($donor['phone']) ?><br>
                            <strong>Donasi diajukan:</strong> <?= (int)$donor['donation_count'] ?><br>
                            <strong>Daftar:</strong> <?= e(date('d M Y H:i', strtotime($donor['created_at']))) ?></p>
                        <div class="card-actions">
                            <form method="POST">
            <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$donor['id'] ?>">
                                <input type="hidden" name="decision" value="verified">
                                <button type="submit" class="btn btn-primary btn-sm">Verifikasi</button>
                            </form>
                            <form method="POST">
            <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$donor['id'] ?>">
                                <input type="hidden" name="decision" value="rejected">
                                <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="section-title" style="margin-top:2.5rem;">Semua Akun (<?= count($accounts) ?>)</h2>
    <div class="grid-cards">
        <?php foreach ($accounts as $acc): ?>
            <article class="card">
                <div class="card-body">
                    <div>
                        <span class="badge badge-target"><?= e(roleLabel($acc['role'])) ?></span>
                        <span class="badge <?= e($verifBadge[$acc['verification_status']] ?? 'badge-target') ?>">
                            <?= e($acc['verification_status']) ?>
                        </span>
                    </div>
                    <h3><?= e($acc['full_name']) ?></h3>
                    <p class="meta-text"><?= e($acc['email']) ?> &middot; <?= e($acc['phone']) ?><br>
                        Donasi: <?= (int)$acc['donation_count'] ?>
                        <?php if ($acc['verified_at']): ?>
                            &middot; diverifikasi <?= e(date('d M Y', strtotime($acc['verified_at']))) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <h2 class="section-title" style="margin-top:2.5rem;">Audit Donasi (30 terbaru)</h2>
    <div class="grid-cards">
        <?php foreach ($audit as $row): ?>
            <article class="card">
                <div class="card-body">
                    <div>
                        <span class="badge badge-<?= e($row['status'] === 'pending' ? 'pending' : ($row['status'] === 'claimed' ? 'claimed' : 'distributed')) ?>">
                            <?= e($row['status']) ?>
                        </span>
                        <span class="badge badge-target"><?= $row['delivery_method'] === 'antar' ? 'Diantar' : 'Diambil' ?></span>
                    </div>
                    <h3><?= e($row['title']) ?></h3>
                    <p class="meta-text"><strong>Donatur:</strong> <?= e($row['donor_name']) ?>
                        (<?= e($row['donor_verification']) ?>)<br>
                        <strong>Porsi:</strong> <?= (int)$row['porsi'] ?> &middot; sisa <?= (int)$row['sisa_porsi'] ?><br>
                        <strong>Kategori:</strong> <?= e($row['category_name'] ?? '-') ?></p>
                    <p class="form-hint">Dibuat <?= e(date('d M Y H:i', strtotime($row['created_at']))) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<footer>
    <p>&copy; <?= date('Y') ?> Porsela Platform &mdash; panel pengawasan donatur. Supporting SDG 2: Zero Hunger.</p>
</footer>
</body>
</html>
