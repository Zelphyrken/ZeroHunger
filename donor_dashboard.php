<?php
require_once __DIR__ . '/config/db.php';
requireRole('donor');

$error = '';
$success = '';

if (isset($_GET['updated'])) {
    $success = "Donation updated successfully.";
}

$allowedCategories = ['Clothing / Shirt', 'Food', 'Toys', 'Other'];
$allowedTargets = ['Anyone in Need', 'Street Workers', 'Orphans', 'Elderly', 'Disaster Victims'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    // DELETE DONATION (only before distributed)
    if ($action === 'delete') {
        $donationId = (int)($_POST['donation_id'] ?? 0);

        $checkStmt = $pdo->prepare("
            SELECT item_photo, item_video, status
            FROM donations
            WHERE id = ? AND donor_id = ?
        ");
        $checkStmt->execute([$donationId, $_SESSION['user_id']]);
        $existing = $checkStmt->fetch();

        if (!$existing) {
            $error = "Donation not found.";
        } elseif ($existing['status'] === 'distributed') {
            $error = "Cannot delete a donation that has already been distributed.";
        } else {
            $delStmt = $pdo->prepare("
                DELETE FROM donations
                WHERE id = ? AND donor_id = ? AND status != 'distributed'
            ");
            $delStmt->execute([$donationId, $_SESSION['user_id']]);

            if ($delStmt->rowCount() > 0) {
                @unlink(__DIR__ . '/' . $existing['item_photo']);
                @unlink(__DIR__ . '/' . $existing['item_video']);
                $success = "Donation deleted successfully.";
            } else {
                $error = "Unable to delete donation.";
            }
        }
    }

    // CREATE DONATION
    elseif ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? '';
        $quantity = trim($_POST['quantity'] ?? '');
        $targetGroup = $_POST['target_group'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $pickupAddress = trim($_POST['pickup_address'] ?? '');

        if (!$title || !$quantity || !$description || !$pickupAddress ||
            !in_array($category, $allowedCategories, true) ||
            !in_array($targetGroup, $allowedTargets, true)) {
            $error = "Please fill in all fields accurately.";
        } else {
            $photoResult = handleUpload(
                $_FILES['item_photo'] ?? [],
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                'items',
                5 * 1024 * 1024
            );

            if (isset($photoResult['error'])) {
                $error = "Item Photo Error: " . $photoResult['error'];
            } else {
                $videoResult = handleUpload(
                    $_FILES['item_video'] ?? [],
                    ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'],
                    'items',
                    25 * 1024 * 1024
                );

                if (isset($videoResult['error'])) {
                    @unlink(__DIR__ . '/' . $photoResult['path']);
                    $error = "Item Video Error: " . $videoResult['error'];
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO donations
                        (donor_id, title, category, quantity, target_group, description, pickup_address, item_photo, item_video)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $title,
                        $category,
                        $quantity,
                        $targetGroup,
                        $description,
                        $pickupAddress,
                        $photoResult['path'],
                        $videoResult['path']
                    ]);
                    $success = "Thank you! Your donation submission has been posted for volunteers.";
                }  
            }
        }
    }
}

$stmt = $pdo->prepare("
    SELECT d.*, v.full_name AS volunteer_name, v.phone AS volunteer_phone
    FROM donations d
    LEFT JOIN users v ON d.volunteer_id = v.id
    WHERE d.donor_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$myDonations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - Berbagi</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Berbagi</a>
        <div class="nav-links">
            <span class="nav-user">Donor: <?= e($_SESSION['full_name']) ?></span>
            <a href="index.php" class="btn btn-outline btn-sm">Home</a>
            <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
        </div>
    </div>
</nav>

<main class="container">
    <section class="form-panel">
        <h2 class="section-title">Submit Items to Share</h2>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group">
                    <label>Item Title</label>
                    <input type="text" name="title" required placeholder="e.g. 5 Clean Cotton T-Shirts">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <?php foreach ($allowedCategories as $cat): ?>
                            <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="text" name="quantity" required placeholder="e.g. 5 pcs / 10 boxes">
                </div>
                <div class="form-group">
                    <label>Target Recipient</label>
                    <select name="target_group" required>
                        <?php foreach ($allowedTargets as $target): ?>
                            <option value="<?= e($target) ?>"><?= e($target) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Pickup Address / Location</label>
                <input type="text" name="pickup_address" required placeholder="Street, District, City (where volunteer can pick up)">
            </div>

            <div class="form-group">
                <label>Item Description &amp; Condition</label>
                <textarea name="description" rows="3" required placeholder="Describe item condition, sizes, expiry date (if food), etc."></textarea>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Proof Photo of Items (Required)</label>
                    <input type="file" name="item_photo" accept="image/jpeg,image/png,image/webp" required>
                    <p class="form-hint">JPG, PNG, or WEBP (Max 5MB)</p>
                </div>
                <div class="form-group">
                    <label>Proof Video of Items (Required)</label>
                    <input type="file" name="item_video" accept="video/mp4,video/webm,video/quicktime" required>
                    <p class="form-hint">MP4, WEBM, or MOV (Max 25MB)</p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Submit Donation</button>
        </form>
    </section>

    <h2 class="section-title">My Submissions</h2>
    <?php if (empty($myDonations)): ?>
        <p class="meta-text">You haven't submitted any items yet.</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($myDonations as $d): ?>
                <article class="card">
                    <div class="media-row">
                        <img src="<?= e($d['item_photo']) ?>" alt="Item photo">
                        <video controls preload="metadata">
                            <source src="<?= e($d['item_video']) ?>">
                        </video>
                    </div>
                    <div class="card-body">
                        <div>
                            <span class="badge badge-<?= e($d['status']) ?>"><?= e($d['status']) ?></span>
                            <span class="badge badge-target">Target: <?= e($d['target_group']) ?></span>
                        </div>
                        <h3><?= e($d['title']) ?></h3>
                        <p class="meta-text"><?= e($d['category']) ?> &bull; Qty: <?= e($d['quantity']) ?></p>
                        <p style="font-size:0.9rem;"><?= e($d['description']) ?></p>
                        <p class="meta-text"><strong>Pickup:</strong> <?= e($d['pickup_address']) ?></p>

                        <?php if ($d['volunteer_name']): ?>
                            <p class="meta-text">
                                <strong>Volunteer:</strong> <?= e($d['volunteer_name']) ?> (<?= e($d['volunteer_phone']) ?>)
                            </p>
                        <?php endif; ?>

                        <?php if ($d['status'] === 'distributed' && $d['distribution_photo']): ?>
                            <div class="proof-box">
                                <p><strong>Distribution Proof Uploaded!</strong></p>
                                <img src="<?= e($d['distribution_photo']) ?>" alt="Distribution result" class="proof-img">
                                <?php if ($d['distribution_notes']): ?>
                                    <p style="margin-top:0.5rem;">"<?= e($d['distribution_notes']) ?>"</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($d['status'] !== 'distributed'): ?>
                            <div class="card-actions">
                                <a href="edit_donation.php?id=<?= (int)$d['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this donation?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>