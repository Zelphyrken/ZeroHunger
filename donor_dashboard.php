<?php
require_once __DIR__ . '/config/db.php';
requireRole('donor');

$error = '';
$success = '';

if (isset($_GET['updated'])) {
    $success = "Donasi berhasil diperbarui.";
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY nama")->fetchAll();
$isVerified = (($_SESSION['verification_status'] ?? 'pending') === 'verified');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $donationId = (int)($_POST['donation_id'] ?? 0);

    // ---- Hapus donasi (hanya selama belum ada porsi yang tersalurkan) ----
    if ($action === 'delete') {
        $checkStmt = $pdo->prepare("SELECT item_photo, status FROM donations WHERE id = ? AND donor_id = ?");
        $checkStmt->execute([$donationId, $_SESSION['user_id']]);
        $existing = $checkStmt->fetch();

        if (!$existing) {
            $error = "Donasi tidak ditemukan.";
        } elseif ($existing['status'] === 'distributed') {
            $error = "Donasi yang sudah tersalurkan tidak bisa dihapus.";
        } else {
            $delStmt = $pdo->prepare("DELETE FROM donations WHERE id = ? AND donor_id = ? AND status != 'distributed'");
            $delStmt->execute([$donationId, $_SESSION['user_id']]);

            if ($delStmt->rowCount() > 0) {
                @unlink(__DIR__ . '/' . $existing['item_photo']);
                $success = "Donasi dihapus.";
            } else {
                $error = "Gagal menghapus donasi.";
            }
        }
    }

    // ---- Posting donasi makanan ----
    elseif ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $porsi = (int)($_POST['porsi'] ?? 0);
        $expiresAt = trim($_POST['expires_at'] ?? '');
        $deliveryMethod = in_array($_POST['delivery_method'] ?? '', ['ambil', 'antar'], true) ? $_POST['delivery_method'] : '';
        $pickupAddress = trim($_POST['pickup_address'] ?? '');
        $description = trim($_POST['description'] ?? '');

        // Kategori diambil dari tabel, bukan string bebas dari form.
        $catStmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $catStmt->execute([$categoryId]);
        $category = $catStmt->fetch();

        $ts = strtotime($expiresAt);

        if (!$title || !$category || $porsi < 1 || !$deliveryMethod || !$pickupAddress || !$description) {
            $error = "Semua kolom wajib diisi dan jumlah porsi minimal 1.";
        } elseif ($ts === false) {
            $error = "Batas layak konsumsi tidak valid.";
        } elseif ($ts <= time()) {
            $error = "Batas layak konsumsi harus di masa depan.";
        } else {
            $photoResult = handleUpload(
                $_FILES['item_photo'] ?? [],
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                'items',
                5 * 1024 * 1024
            );

            if (isset($photoResult['error'])) {
                $error = "Foto makanan: " . $photoResult['error'];
            } else {
                // Kolom warisan (category, quantity, item_video) sudah dibuang di migration_v3.
                $stmt = $pdo->prepare("
                    INSERT INTO donations
                        (donor_id, title, category_id, porsi, expires_at,
                         description, pickup_address, delivery_method, item_photo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $title,
                    $categoryId,
                    $porsi,
                    date('Y-m-d H:i:s', $ts),
                    $description,
                    $pickupAddress,
                    $deliveryMethod,
                    $photoResult['path'],
                ]);

                $success = $isVerified
                    ? "Donasi terkirim dan sudah tampil di pencarian penerima."
                    : "Donasi terkirim, tapi belum tampil ke penerima sampai akunmu diverifikasi admin.";
            }
        }
    }

    // ---- Setujui / tolak klaim penerima ----
    elseif ($action === 'claim_decision') {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $decision = ($_POST['decision'] ?? '') === 'disetujui' ? 'disetujui' : 'ditolak';

        // donation_id diambil dari DB (bukan hidden input) supaya status donasi selalu ikut tersinkron.
        $findStmt = $pdo->prepare("
            SELECT cl.donation_id
            FROM claims cl
            JOIN donations d ON d.id = cl.donation_id
            WHERE cl.id = ? AND d.donor_id = ?
        ");
        $findStmt->execute([$claimId, $_SESSION['user_id']]);
        $claimDonationId = (int)$findStmt->fetchColumn();

        // Guard kepemilikan: klaim hanya bisa diputus donatur pemilik donasinya.
        $stmt = $pdo->prepare("
            UPDATE claims cl
            JOIN donations d ON d.id = cl.donation_id
            SET cl.status_claim = ?
            WHERE cl.id = ? AND d.donor_id = ? AND cl.status_claim = 'menunggu'
        ");
        $stmt->execute([$decision, $claimId, $_SESSION['user_id']]);

        if ($stmt->rowCount() > 0) {
            $success = $decision === 'disetujui' ? "Klaim disetujui." : "Klaim ditolak, porsinya dilepas lagi.";
            syncDonationStatus($pdo, $claimDonationId);
        } else {
            $error = "Klaim tidak ditemukan atau sudah diputus sebelumnya.";
        }
    }
}

$donationStmt = $pdo->prepare("SELECT * FROM v_donations WHERE donor_id = ? ORDER BY created_at DESC");
$donationStmt->execute([$_SESSION['user_id']]);
$myDonations = $donationStmt->fetchAll();

$claimStmt = $pdo->prepare("
    SELECT cl.*, d.title, d.delivery_method, u.full_name AS beneficiary_name, u.phone AS beneficiary_phone
    FROM claims cl
    JOIN donations d ON d.id = cl.donation_id
    JOIN users u ON u.id = cl.beneficiary_id
    WHERE d.donor_id = ?
    ORDER BY FIELD(cl.status_claim, 'menunggu', 'disetujui', 'selesai', 'ditolak'), cl.created_at DESC
");
$claimStmt->execute([$_SESSION['user_id']]);
$incomingClaims = $claimStmt->fetchAll();

$claimBadge = [
    'menunggu' => 'badge-pending',
    'disetujui' => 'badge-claimed',
    'ditolak' => 'badge-target',
    'selesai' => 'badge-distributed',
];
$statusLabel = ['pending' => 'tersedia', 'claimed' => 'diproses', 'distributed' => 'selesai'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= themeClass() === 'dark' ? '#101D24' : '#F7F7F7' ?>">
    <title>Donor Dashboard - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body class="app-page <?= themeClass() ?>">
<?php require __DIR__ . '/includes/navigation.php'; ?>

<main class="container" id="main-content" tabindex="-1">
    <header class="page-header"><h1>Ruang berbagi Anda</h1><p>Bagikan makanan berlebih, tanggapi klaim, dan ikuti penyalurannya.</p><nav class="section-links" aria-label="Bagian dashboard"><a href="#bagikan">Bagikan makanan</a><a href="#klaim-masuk">Klaim masuk</a><a href="#donasi-saya">Donasi saya</a></nav></header>
    <?php if (!$isVerified): ?>
        <div class="alert alert-warning" role="status">
            <strong>Akunmu belum diverifikasi admin.</strong> Donasi tetap bisa dibuat, tapi belum tampil di pencarian penerima.
        </div>
    <?php endif; ?>

    <details class="donation-composer" id="bagikan" <?= ($error || $success) ? 'open' : '' ?>>
        <summary class="btn btn-primary">Bagikan makanan berlebih</summary>
    <section class="form-panel">
        <h2 class="section-title">Bagikan Makanan Berlebih</h2>
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" role="status"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group">
                    <label>Nama Makanan
                    <input type="text" name="title" required placeholder="e.g. Nasi Kotak Ayam Bakar">
                </label>
                </div>
                <div class="form-group">
                    <label>Kategori
                    <select name="category_id" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= e($cat['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                </div>
                <div class="form-group">
                    <label>Jumlah Porsi
                    <input type="number" name="porsi" min="1" required placeholder="e.g. 30">
                </label>
                </div>
                <div class="form-group">
                    <label>Batas Layak Konsumsi
                    <input type="datetime-local" name="expires_at" required>
                </label>
                    <p class="form-hint">Setelah lewat waktu ini, donasi otomatis hilang dari pencarian penerima.</p>
                </div>
                <div class="form-group">
                    <label>Metode Penyerahan
                    <select name="delivery_method" required>
                        <option value="ambil">Diambil sendiri oleh penerima</option>
                        <option value="antar">Saya antarkan ke alamat penerima</option>
                    </select>
                </label>
                </div>
                <div class="form-group">
                    <label>Foto Makanan
                    <input type="file" name="item_photo" accept="image/jpeg,image/png,image/webp" required>
                </label>
                    <p class="form-hint">JPG, PNG, atau WEBP (maks 5MB)</p>
                </div>
            </div>

            <div class="form-group">
                <label>Alamat / Lokasi
                    <input type="text" name="pickup_address" required placeholder="Alamat lengkap tempat makanan diambil atau dikirim">
                </label>
            </div>

            <div class="form-group">
                <label>Deskripsi &amp; Catatan Allergen
                    <textarea name="description" rows="3" required placeholder="Kondisi makanan, isi paket, jam dibuat, catatan alergen (mis. mengandung kacang)."></textarea>
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Posting Donasi</button>
        </form>
    </section>
    </details>

    <h2 class="section-title" id="klaim-masuk">Klaim Masuk (<?= count($incomingClaims) ?>)</h2>
    <?php if (empty($incomingClaims)): ?>
        <p class="meta-text empty-state">Belum ada penerima yang mengajukan klaim.</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($incomingClaims as $claim): ?>
                <article class="card">
                    <div class="card-body">
                        <div>
                            <span class="badge <?= e($claimBadge[$claim['status_claim']] ?? 'badge-target') ?>"><?= e($claim['status_claim']) ?></span>
                            <span class="badge badge-target"><?= $claim['delivery_method'] === 'antar' ? 'Kirim' : 'Ambil' ?></span>
                        </div>
                        <h3><?= e($claim['title']) ?></h3>
                        <p class="meta-text">
                            <strong>Pemohon:</strong> <?= e($claim['beneficiary_name']) ?> &middot; <?= e($claim['beneficiary_phone']) ?><br>
                            <strong>Minta:</strong> <?= (int)$claim['porsi_diminta'] ?> porsi
                            <?php if ($claim['scheduled_at']): ?>
                                <br><strong>Jadwal:</strong> <?= e(date('d M Y H:i', strtotime($claim['scheduled_at']))) ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($claim['alasan']): ?>
                            <p class="meta-text">"<?= e($claim['alasan']) ?>"</p>
                        <?php endif; ?>
                        <?php if ($claim['delivery_address']): ?>
                            <p class="meta-text"><strong>Alamat kirim:</strong> <?= e($claim['delivery_address']) ?></p>
                        <?php endif; ?>
                        <?php if ($claim['proof_photo']): ?>
                            <div class="proof-box">
                                <p><strong>Bukti terima dari penerima</strong></p>
                                <img src="<?= e($claim['proof_photo']) ?>" alt="Bukti terima" class="proof-img" width="640" height="480" loading="lazy" decoding="async" data-site-image>
                                <?php if ($claim['received_notes']): ?>
                                    <p>"<?= e($claim['received_notes']) ?>"</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($claim['status_claim'] === 'menunggu'): ?>
                            <div class="card-actions">
                                <form method="POST">
            <?= csrfField() ?>
                                    <input type="hidden" name="action" value="claim_decision">
                                    <input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>">
                                    <input type="hidden" name="donation_id" value="<?= (int)$claim['donation_id'] ?>">
                                    <input type="hidden" name="decision" value="disetujui">
                                    <button type="submit" class="btn btn-primary btn-sm">Setujui</button>
                                </form>
                                <form method="POST">
            <?= csrfField() ?>
                                    <input type="hidden" name="action" value="claim_decision">
                                    <input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>">
                                    <input type="hidden" name="donation_id" value="<?= (int)$claim['donation_id'] ?>">
                                    <input type="hidden" name="decision" value="ditolak">
                                    <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="section-title" id="donasi-saya">Donasi Saya (<?= count($myDonations) ?>)</h2>
    <?php if (empty($myDonations)): ?>
        <p class="meta-text empty-state">Belum ada donasi.</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($myDonations as $d): ?>
                <article class="card">
                    <img src="<?= e($d['item_photo']) ?>" alt="Foto makanan" class="card-photo" width="640" height="480" loading="lazy" decoding="async" data-site-image>
                    <div class="card-body">
                        <div>
                            <span class="badge badge-<?= e($d['status']) ?>"><?= e($statusLabel[$d['status']] ?? $d['status']) ?></span>
                            <span class="badge <?= $d['delivery_method'] === 'antar' ? 'badge-delivery' : 'badge-pickup' ?>">
                                <?= $d['delivery_method'] === 'antar' ? 'Bisa Diantar' : 'Ambil Sendiri' ?>
                            </span>
                        </div>
                        <h3><?= e($d['title']) ?></h3>
                        <p class="meta-text"><?= e($d['category_name'] ?? '-') ?> &bull; <?= (int)$d['porsi'] ?> porsi &bull; sisa <?= (int)$d['sisa_porsi'] ?></p>
                        <p class="meta-text">
                            <strong>Batas layak:</strong>
                            <?= $d['expires_at'] ? e(date('d M Y H:i', strtotime($d['expires_at']))) : 'tidak dibatasi' ?>
                        </p>
                        <p class="meta-text"><strong>Lokasi:</strong> <?= e($d['pickup_address']) ?></p>

                        <?php if ($d['status'] !== 'distributed'): ?>
                            <div class="card-actions">
                                <a href="edit_donation.php?id=<?= (int)$d['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="POST" onsubmit="return confirm('Hapus donasi ini?');">
            <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
