<?php
require_once __DIR__ . '/config/db.php';
requireRole('volunteer');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $donationId = (int)($_POST['donation_id'] ?? 0);

    if ($action === 'claim' && $donationId > 0) {
        $stmt = $pdo->prepare("
            UPDATE donations
            SET status = 'claimed', volunteer_id = ?, claimed_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$_SESSION['user_id'], $donationId]);

        if ($stmt->rowCount() > 0) {
            $success = "You claimed this donation! Please contact the donor for pickup and upload distribution proof once delivered.";
        } else {
            $error = "This item is no longer available.";
        }
    } elseif ($action === 'distribute' && $donationId > 0) {
        $notes = trim($_POST['distribution_notes'] ?? '');
        $proofResult = handleUpload(
            $_FILES['distribution_photo'] ?? [],
            ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
            'proofs',
            5 * 1024 * 1024
        );

        if (isset($proofResult['error'])) {
            $error = "Proof Photo Error: " . $proofResult['error'];
        } else {
            $stmt = $pdo->prepare("
                UPDATE donations
                SET status = 'distributed',
                    distribution_photo = ?,
                    distribution_notes = ?,
                    distributed_at = NOW()
                WHERE id = ? AND volunteer_id = ? AND status = 'claimed'
            ");
            $stmt->execute([$proofResult['path'], $notes, $donationId, $_SESSION['user_id']]);

            if ($stmt->rowCount() > 0) {
                $success = "Distribution confirmed! Thank you for delivering kindness.";
            } else {
                @unlink(__DIR__ . '/' . $proofResult['path']);
                $error = "Unable to update distribution status.";
            }
        }
    }
}

// Fetch Volunteer's active & completed tasks
$myStmt = $pdo->prepare("
    SELECT d.*, u.full_name AS donor_name, u.phone AS donor_phone
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.volunteer_id = ?
    ORDER BY FIELD(d.status, 'claimed', 'distributed'), d.claimed_at DESC
");
$myStmt->execute([$_SESSION['user_id']]);
$myTasks = $myStmt->fetchAll();

// Fetch Available (pending) donations
$availStmt = $pdo->query("
    SELECT d.*, u.full_name AS donor_name
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.status = 'pending'
    ORDER BY d.created_at DESC
");
$availableDonations = $availStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Dashboard - Berbagi</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Berbagi</a>
        <div class="nav-links">
            <span class="nav-user">Volunteer: <?= e($_SESSION['full_name']) ?></span>
            <a href="index.php" class="btn btn-outline btn-sm">Home</a>
            <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
        </div>
    </div>
</nav>

<main class="container">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- Section 1: My Claimed & Distributed Tasks -->
    <h2 class="section-title">My Distribution Tasks</h2>
    <?php if (empty($myTasks)): ?>
        <p class="meta-text" style="margin-bottom:2.5rem;">You haven't claimed any donations yet. Browse available items below!</p>
    <?php else: ?>
        <div class="grid-cards" style="margin-bottom:3rem;">
            <?php foreach ($myTasks as $task): ?>
                <article class="card">
                    <div class="media-row">
                        <img src="<?= e($task['item_photo']) ?>" alt="Item photo">
                        <video controls preload="metadata">
                            <source src="<?= e($task['item_video']) ?>">
                        </video>
                    </div>
                    <div class="card-body">
                        <div>
                            <span class="badge badge-<?= e($task['status']) ?>"><?= e($task['status']) ?></span>
                            <span class="badge badge-target">Target: <?= e($task['target_group']) ?></span>
                        </div>
                        <h3><?= e($task['title']) ?></h3>
                        <p class="meta-text"><?= e($task['category']) ?> &bull; Qty: <?= e($task['quantity']) ?></p>
                        <p class="meta-text"><strong>Donor:</strong> <?= e($task['donor_name']) ?> (<?= e($task['donor_phone']) ?>)</p>
                        <p class="meta-text"><strong>Pickup Address:</strong> <?= e($task['pickup_address']) ?></p>

                        <?php if ($task['status'] === 'claimed'): ?>
                            <form method="POST" enctype="multipart/form-data" style="margin-top:0.75rem; border-top:1px solid var(--border); padding-top:0.75rem;">
                                <input type="hidden" name="action" value="distribute">
                                <input type="hidden" name="donation_id" value="<?= (int)$task['id'] ?>">

                                <div class="form-group">
                                    <label>Upload Distribution Proof Photo (Required)</label>
                                    <input type="file" name="distribution_photo" accept="image/jpeg,image/png,image/webp" required>
                                </div>
                                <div class="form-group">
                                    <label>Delivery Notes (Optional)</label>
                                    <input type="text" name="distribution_notes" placeholder="e.g. Given to street workers near Central Station">
                                </div>
                                <button type="submit" class="btn btn-primary" style="width:100%;">Mark as Distributed</button>
                            </form>
                        <?php elseif ($task['status'] === 'distributed' && $task['distribution_photo']): ?>
                            <div class="proof-box">
                                <p><strong>Distribution Completed</strong></p>
                                <img src="<?= e($task['distribution_photo']) ?>" alt="Distribution proof" class="proof-img">
                                <?php if ($task['distribution_notes']): ?>
                                    <p style="margin-top:0.5rem;">"<?= e($task['distribution_notes']) ?>"</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Section 2: Available Donations -->
    <h2 class="section-title">Available Donations to Distribute</h2>
    <?php if (empty($availableDonations)): ?>
        <p class="meta-text">No pending donations right now. Check back soon!</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($availableDonations as $item): ?>
                <article class="card">
                    <div class="media-row">
                        <img src="<?= e($item['item_photo']) ?>" alt="Item photo">
                        <video controls preload="metadata">
                            <source src="<?= e($item['item_video']) ?>">
                        </video>
                    </div>
                    <div class="card-body">
                        <div>
                            <span class="badge badge-pending">Pending Pickup</span>
                            <span class="badge badge-target">Target: <?= e($item['target_group']) ?></span>
                        </div>
                        <h3><?= e($item['title']) ?></h3>
                        <p class="meta-text"><?= e($item['category']) ?> &bull; Qty: <?= e($item['quantity']) ?></p>
                        <p style="font-size:0.9rem;"><?= e($item['description']) ?></p>
                        <p class="meta-text"><strong>Donor:</strong> <?= e($item['donor_name']) ?></p>
                        <p class="meta-text"><strong>Pickup Area:</strong> <?= e($item['pickup_address']) ?></p>

                        <form method="POST" style="margin-top:auto; padding-top:0.5rem;">
                            <input type="hidden" name="action" value="claim">
                            <input type="hidden" name="donation_id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" class="btn btn-accent" style="width:100%;">Claim &amp; Distribute</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>