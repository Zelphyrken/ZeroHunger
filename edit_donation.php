<?php
require_once __DIR__ . '/config/db.php';
requireRole('donor');

$donationId = (int)($_GET['id'] ?? $_POST['donation_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM donations WHERE id = ? AND donor_id = ?");
$stmt->execute([$donationId, $_SESSION['user_id']]);
$donation = $stmt->fetch();

if (!$donation) {
    die("Donation not found or access denied. <a href='donor_dashboard.php'>Back to Dashboard</a>");
}

if ($donation['status'] === 'distributed') {
    die("This donation has already been distributed and can no longer be edited. <a href='donor_dashboard.php'>Back to Dashboard</a>");
}

$allowedCategories = ['Clothing / Shirt', 'Food', 'Toys', 'Other'];
$allowedTargets = ['Anyone in Need', 'Street Workers', 'Orphans', 'Elderly', 'Disaster Victims'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $newPhotoPath = $donation['item_photo'];
        $newVideoPath = $donation['item_video'];
        $uploadedNewPhoto = false;
        $uploadedNewVideo = false;

        // Replace photo only if a new file was selected
        if (isset($_FILES['item_photo']) && $_FILES['item_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $photoResult = handleUpload(
                $_FILES['item_photo'],
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                'items',
                5 * 1024 * 1024
            );
            if (isset($photoResult['error'])) {
                $error = "Item Photo Error: " . $photoResult['error'];
            } else {
                $newPhotoPath = $photoResult['path'];
                $uploadedNewPhoto = true;
            }
        }

        // Replace video only if a new file was selected and no photo error occurred
        if (!$error && isset($_FILES['item_video']) && $_FILES['item_video']['error'] !== UPLOAD_ERR_NO_FILE) {
            $videoResult = handleUpload(
                $_FILES['item_video'],
                ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov'],
                'items',
                25 * 1024 * 1024
            );
            if (isset($videoResult['error'])) {
                if ($uploadedNewPhoto) {
                    @unlink(__DIR__ . '/' . $newPhotoPath);
                }
                $error = "Item Video Error: " . $videoResult['error'];
            } else {
                $newVideoPath = $videoResult['path'];
                $uploadedNewVideo = true;
            }
        }

        if (!$error) {
            $updateStmt = $pdo->prepare("
                UPDATE donations
                SET title = ?,
                    category = ?,
                    quantity = ?,
                    target_group = ?,
                    description = ?,
                    pickup_address = ?,
                    item_photo = ?,
                    item_video = ?
                WHERE id = ? AND donor_id = ? AND status != 'distributed'
            ");
            $updateStmt->execute([
                $title,
                $category,
                $quantity,
                $targetGroup,
                $description,
                $pickupAddress,
                $newPhotoPath,
                $newVideoPath,
                $donationId,
                $_SESSION['user_id']
            ]);

            if ($updateStmt->rowCount() > 0 || (!$uploadedNewPhoto && !$uploadedNewVideo)) {
                // Clean up old files if replaced
                if ($uploadedNewPhoto) {
                    @unlink(__DIR__ . '/' . $donation['item_photo']);
                }
                if ($uploadedNewVideo) {
                    @unlink(__DIR__ . '/' . $donation['item_video']);
                }
                header("Location: donor_dashboard.php?updated=1");
                exit;
            } else {
                if ($uploadedNewPhoto) @unlink(__DIR__ . '/' . $newPhotoPath);
                if ($uploadedNewVideo) @unlink(__DIR__ . '/' . $newVideoPath);
                $error = "Unable to update. The donation may have already been distributed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Donation - Berbagi</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Berbagi</a>
        <div class="nav-links">
            <a href="donor_dashboard.php" class="btn btn-outline btn-sm">Back to Dashboard</a>
            <a href="logout.php" class="btn btn-outline btn-sm">Logout</a>
        </div>
    </div>
</nav>

<main class="container">
    <section class="form-panel">
        <h2 class="section-title">Edit Donation Submission</h2>

        <?php if ($donation['status'] === 'claimed'): ?>
            <div class="alert" style="background: var(--info-bg); color: var(--info-text);">
                Note: A volunteer has already claimed this item for pickup, but it has not been distributed yet.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="donation_id" value="<?= (int)$donation['id'] ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>Item Title</label>
                    <input type="text" name="title" required value="<?= e($_POST['title'] ?? $donation['title']) ?>">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <?php $curCat = $_POST['category'] ?? $donation['category']; ?>
                        <?php foreach ($allowedCategories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= $curCat === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="text" name="quantity" required value="<?= e($_POST['quantity'] ?? $donation['quantity']) ?>">
                </div>
                <div class="form-group">
                    <label>Target Recipient</label>
                    <select name="target_group" required>
                        <?php $curTarget = $_POST['target_group'] ?? $donation['target_group']; ?>
                        <?php foreach ($allowedTargets as $target): ?>
                            <option value="<?= e($target) ?>" <?= $curTarget === $target ? 'selected' : '' ?>><?= e($target) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Pickup Address / Location</label>
                <input type="text" name="pickup_address" required value="<?= e($_POST['pickup_address'] ?? $donation['pickup_address']) ?>">
            </div>

            <div class="form-group">
                <label>Item Description &amp; Condition</label>
                <textarea name="description" rows="3" required><?= e($_POST['description'] ?? $donation['description']) ?></textarea>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Current Photo (Upload new to replace)</label>
                    <img src="<?= e($donation['item_photo']) ?>" alt="Current item photo" class="media-preview">
                    <input type="file" name="item_photo" accept="image/jpeg,image/png,image/webp">
                    <p class="form-hint">Leave empty to keep current photo (Max 5MB)</p>
                </div>
                <div class="form-group">
                    <label>Current Video (Upload new to replace)</label>
                    <video controls preload="metadata" class="media-preview">
                        <source src="<?= e($donation['item_video']) ?>">
                    </video>
                    <input type="file" name="item_video" accept="video/mp4,video/webm,video/quicktime">
                    <p class="form-hint">Leave empty to keep current video (Max 25MB)</p>
                </div>
            </div>

            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="donor_dashboard.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </section>
</main>
</body>
</html>