<?php
require_once __DIR__ . '/config/db.php';
requireRole('donor');

$donationId = (int)($_GET['id'] ?? $_POST['donation_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM donations WHERE id = ? AND donor_id = ?");
$stmt->execute([$donationId, $_SESSION['user_id']]);
$donation = $stmt->fetch();

if (!$donation) {
    die("Donasi tidak ditemukan atau bukan milikmu. <a href='donor_dashboard.php'>Kembali ke dashboard</a>");
}

// Kalau sudah ada klaim aktif atau sudah tersalurkan, porsi/isi donasi tidak boleh diubah lagi.
$claimStmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE donation_id = ? AND status_claim IN ('menunggu', 'disetujui', 'selesai')");
$claimStmt->execute([$donationId]);
$activeClaims = (int)$claimStmt->fetchColumn();

if ($donation['status'] === 'distributed' || $activeClaims > 0) {
    die("Donasi ini sudah diklaim penerima, jadi tidak bisa diedit lagi. <a href='donor_dashboard.php'>Kembali ke dashboard</a>");
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY nama")->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $title = trim($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $porsi = (int)($_POST['porsi'] ?? 0);
    $expiresAt = trim($_POST['expires_at'] ?? '');
    $deliveryMethod = in_array($_POST['delivery_method'] ?? '', ['ambil', 'antar'], true) ? $_POST['delivery_method'] : '';
    $pickupAddress = trim($_POST['pickup_address'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $catStmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $catStmt->execute([$categoryId]);
    $category = $catStmt->fetch();

    $ts = strtotime($expiresAt);

    if (!$title || !$category || $porsi < 1 || !$deliveryMethod || !$pickupAddress || !$description) {
        $error = "Semua kolom wajib diisi dan jumlah porsi minimal 1.";
    } elseif ($ts === false) {
        $error = "Batas layak konsumsi tidak valid.";
    } else {
        $newPhotoPath = $donation['item_photo'];
        $uploadedNewPhoto = false;

        if (isset($_FILES['item_photo']) && $_FILES['item_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $photoResult = handleUpload(
                $_FILES['item_photo'],
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                'items',
                5 * 1024 * 1024
            );

            if (isset($photoResult['error'])) {
                $error = "Foto makanan: " . $photoResult['error'];
            } else {
                $newPhotoPath = $photoResult['path'];
                $uploadedNewPhoto = true;
            }
        }

        if (!$error) {
            $upd = $pdo->prepare("
                UPDATE donations
                SET title = ?, category_id = ?, porsi = ?,
                    expires_at = ?, delivery_method = ?, pickup_address = ?, description = ?, item_photo = ?
                WHERE id = ? AND donor_id = ?
            ");
            $upd->execute([
                $title,
                $categoryId,
                $porsi,
                date('Y-m-d H:i:s', $ts),
                $deliveryMethod,
                $pickupAddress,
                $description,
                $newPhotoPath,
                $donationId,
                $_SESSION['user_id'],
            ]);

            if ($uploadedNewPhoto && $donation['item_photo'] && $donation['item_photo'] !== $newPhotoPath) {
                @unlink(__DIR__ . '/' . $donation['item_photo']);
            }

            header("Location: donor_dashboard.php?updated=1");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= themeClass() === 'dark' ? '#101D24' : '#F7F7F7' ?>">
    <title>Edit Donation - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body class="app-page <?= themeClass() ?>">
<?php require __DIR__ . '/includes/navigation.php'; ?>

<main class="container" id="main-content" tabindex="-1">
    <header class="page-header"><h1>Perbarui donasi</h1><p>Periksa kembali informasi makanan, jumlah porsi, dan waktu penyerahan.</p><nav class="section-links"><a href="donor_dashboard.php">Kembali ke Dashboard</a></nav></header>
    <section class="form-panel">
        <h2 class="section-title">Edit Donasi: <?= e($donation['title']) ?></h2>
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="donation_id" value="<?= (int)$donation['id'] ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Nama Makanan
                    <input type="text" name="title" required value="<?= e($_POST['title'] ?? $donation['title']) ?>">
                </label>
                </div>
                <div class="form-group">
                    <label>Kategori
                    <select name="category_id" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)$donation['category_id'] ? 'selected' : '' ?>>
                                <?= e($cat['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                </div>
                <div class="form-group">
                    <label>Jumlah Porsi
                    <input type="number" name="porsi" min="1" required value="<?= (int)$donation['porsi'] ?>">
                </label>
                </div>
                <div class="form-group">
                    <label>Batas Layak Konsumsi
                    <input type="datetime-local" name="expires_at" required
                           value="<?= $donation['expires_at'] ? e(date('Y-m-d\TH:i', strtotime($donation['expires_at']))) : '' ?>">
                </label>
                </div>
                <div class="form-group">
                    <label>Metode Penyerahan
                    <select name="delivery_method" required>
                        <option value="ambil" <?= $donation['delivery_method'] === 'ambil' ? 'selected' : '' ?>>Diambil sendiri oleh penerima</option>
                        <option value="antar" <?= $donation['delivery_method'] === 'antar' ? 'selected' : '' ?>>Saya antarkan ke alamat penerima</option>
                    </select>
                </label>
                </div>
                <div class="form-group">
                    <label>Ganti Foto (opsional)
                    <input type="file" name="item_photo" accept="image/jpeg,image/png,image/webp">
                </label>
                    <p class="form-hint">Biarkan kosong kalau tidak ingin ganti foto (maks 5MB).</p>
                </div>
            </div>

            <div class="form-group">
                <label>Alamat / Lokasi
                    <input type="text" name="pickup_address" required value="<?= e($_POST['pickup_address'] ?? $donation['pickup_address']) ?>">
                </label>
            </div>

            <div class="form-group">
                <label>Deskripsi &amp; Catatan Allergen
                    <textarea name="description" rows="3" required><?= e($_POST['description'] ?? $donation['description']) ?></textarea>
                </label>
            </div>

            <img src="<?= e($donation['item_photo']) ?>" alt="Foto saat ini" class="media-preview" width="640" height="480" loading="lazy" decoding="async" data-site-image>

            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
