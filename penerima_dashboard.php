<?php
require_once __DIR__ . '/config/db.php';
requireRole('beneficiary');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    // ---- Ajukan klaim porsi ----
    if ($action === 'claim') {
        $donationId = (int)($_POST['donation_id'] ?? 0);
        $porsi = (int)($_POST['porsi_diminta'] ?? 0);
        $alasan = trim($_POST['alasan'] ?? '');
        $deliveryAddress = trim($_POST['delivery_address'] ?? '');
        $scheduledInput = trim($_POST['scheduled_at'] ?? '');
        $scheduledTs = $scheduledInput !== '' ? strtotime($scheduledInput) : false;

        try {
            $pdo->beginTransaction();

            // FOR UPDATE: kunci baris donasi supaya dua penerima tidak bisa mengklaim porsi yang sama.
            $stmt = $pdo->prepare("
                SELECT d.porsi, d.delivery_method, d.expires_at, d.status, u.verification_status
                FROM donations d
                JOIN users u ON u.id = d.donor_id
                WHERE d.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$donationId]);
            $don = $stmt->fetch();

            $sumStmt = $pdo->prepare("
                SELECT COALESCE(SUM(porsi_diminta), 0)
                FROM claims
                WHERE donation_id = ? AND status_claim IN ('menunggu', 'disetujui', 'selesai')
            ");
            $sumStmt->execute([$donationId]);
            $terpesan = (int)$sumStmt->fetchColumn();

            $dupStmt = $pdo->prepare("
                SELECT COUNT(*) FROM claims
                WHERE donation_id = ? AND beneficiary_id = ? AND status_claim IN ('menunggu', 'disetujui')
            ");
            $dupStmt->execute([$donationId, $_SESSION['user_id']]);
            $sudahKlaim = (int)$dupStmt->fetchColumn();

            if (!$don || $don['verification_status'] !== 'verified') {
                $error = "Donasi tidak tersedia.";
            } elseif ($don['status'] === 'distributed') {
                $error = "Donasi ini sudah tersalurkan.";
            } elseif ($don['expires_at'] && strtotime($don['expires_at']) <= time()) {
                $error = "Makanan sudah lewat batas layak konsumsi.";
            } elseif ($porsi < 1) {
                $error = "Jumlah porsi minimal 1.";
            } elseif ($sudahKlaim > 0) {
                $error = "Kamu masih punya klaim aktif untuk donasi ini.";
            } elseif ($porsi > ($don['porsi'] - $terpesan)) {
                $error = "Sisa porsi hanya " . ($don['porsi'] - $terpesan) . ".";
            } elseif ($don['delivery_method'] === 'antar' && $deliveryAddress === '') {
                $error = "Donatur mengantar, jadi alamat kirim wajib diisi.";
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO claims (donation_id, beneficiary_id, porsi_diminta, alasan, delivery_address, scheduled_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([
                    $donationId,
                    $_SESSION['user_id'],
                    $porsi,
                    $alasan !== '' ? $alasan : null,
                    $don['delivery_method'] === 'antar' ? $deliveryAddress : null,
                    $scheduledTs !== false ? date('Y-m-d H:i:s', $scheduledTs) : null,
                ]);

                syncDonationStatus($pdo, $donationId);
                $success = "Klaim terkirim. Tunggu persetujuan donatur.";
            }

            if ($error) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Gagal memproses klaim, coba lagi.";
        }
    }

    // ---- Konfirmasi terima + bukti terima ----
    elseif ($action === 'confirm_receipt') {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $notes = trim($_POST['received_notes'] ?? '');

        $ownStmt = $pdo->prepare("SELECT donation_id FROM claims WHERE id = ? AND beneficiary_id = ? AND status_claim = 'disetujui'");
        $ownStmt->execute([$claimId, $_SESSION['user_id']]);
        $claim = $ownStmt->fetch();

        if (!$claim) {
            $error = "Klaim tidak ditemukan atau belum disetujui donatur.";
        } else {
            $proofResult = handleUpload(
                $_FILES['proof_photo'] ?? [],
                ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
                'proofs',
                5 * 1024 * 1024
            );

            if (isset($proofResult['error'])) {
                $error = "Foto bukti terima: " . $proofResult['error'];
            } else {
                $upd = $pdo->prepare("
                    UPDATE claims
                    SET status_claim = 'selesai', proof_photo = ?, received_notes = ?, received_at = NOW()
                    WHERE id = ? AND beneficiary_id = ? AND status_claim = 'disetujui'
                ");
                $upd->execute([$proofResult['path'], $notes !== '' ? $notes : null, $claimId, $_SESSION['user_id']]);

                if ($upd->rowCount() > 0) {
                    syncDonationStatus($pdo, (int)$claim['donation_id']);
                    $success = "Terima kasih! Bukti terima tersimpan dan porsi tercatat tersalurkan.";
                } else {
                    @unlink(__DIR__ . '/' . $proofResult['path']);
                    $error = "Gagal menyimpan bukti terima.";
                }
            }
        }
    }

    // ---- Ulasan setelah makanan diterima ----
    elseif ($action === 'review') {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $komentar = trim($_POST['komentar'] ?? '');

        $ownStmt = $pdo->prepare("SELECT id FROM claims WHERE id = ? AND beneficiary_id = ? AND status_claim = 'selesai'");
        $ownStmt->execute([$claimId, $_SESSION['user_id']]);

        if (!$ownStmt->fetch()) {
            $error = "Ulasan hanya bisa dikirim untuk klaim yang sudah selesai.";
        } else {
            $pdo->prepare("
                INSERT INTO reviews (claim_id, rating, komentar) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE rating = VALUES(rating), komentar = VALUES(komentar)
            ")->execute([$claimId, $rating, $komentar !== '' ? $komentar : null]);
            $success = "Ulasan tersimpan, terima kasih!";
        }
    }
}

// Live search diproses di DOM; data dikirim sekali lewat atribut data-*.
$categories = $pdo->query("SELECT * FROM categories ORDER BY nama")->fetchAll();

$stmt = $pdo->query("
    SELECT *
    FROM v_donations
    WHERE donor_verification = 'verified'
      AND status != 'distributed'
      AND (expires_at IS NULL OR expires_at > NOW())
      AND sisa_porsi > 0
    ORDER BY expires_at IS NULL, expires_at ASC
");
$available = $stmt->fetchAll();

$claimStmt = $pdo->prepare("
    SELECT cl.*, d.title, d.delivery_method, d.pickup_address, u.full_name AS donor_name, u.phone AS donor_phone,
           r.rating, r.komentar
    FROM claims cl
    JOIN donations d ON d.id = cl.donation_id
    JOIN users u ON u.id = d.donor_id
    LEFT JOIN reviews r ON r.claim_id = cl.id
    WHERE cl.beneficiary_id = ?
    ORDER BY cl.created_at DESC
");
$claimStmt->execute([$_SESSION['user_id']]);
$myClaims = $claimStmt->fetchAll();

$claimBadge = [
    'menunggu' => 'badge-pending',
    'disetujui' => 'badge-claimed',
    'ditolak' => 'badge-target',
    'selesai' => 'badge-distributed',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= themeClass() === 'dark' ? '#15251e' : '#f7f5ed' ?>">
    <title>Receiver Dashboard - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body class="app-page <?= themeClass() ?>">
<?php require __DIR__ . '/includes/navigation.php'; ?>

<main class="container" id="main-content" tabindex="-1">
    <header class="page-header"><h1>Temukan porsi untuk hari ini</h1><p>Cari makanan yang tersedia dan ikuti proses klaim Anda di satu tempat.</p><nav class="section-links" aria-label="Bagian dashboard"><a href="#makanan-tersedia">Makanan tersedia</a><a href="#klaim-saya">Klaim saya</a></nav></header>
    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <h2 class="section-title" id="makanan-tersedia">Makanan Siap Diselamatkan (<span id="ls-count" aria-live="polite"><?= count($available) ?></span>)</h2>

    <div class="filter-bar">
        <div class="form-group">
            <label for="ls-q">Cari makanan / donatur</label>
            <input type="text" id="ls-q" placeholder="mis. nasi, roti, Bu Sari" autocomplete="off">
        </div>
        <div class="form-group">
            <label for="ls-cat">Kategori</label>
            <select id="ls-cat">
                <option value="">Semua kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"><?= e($cat['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="ls-del">Metode</label>
            <select id="ls-del">
                <option value="">Semua metode</option>
                <option value="antar">Bisa Diantar</option>
                <option value="ambil">Ambil Sendiri</option>
            </select>
        </div>
    </div>

    <p class="meta-text empty-state" id="ls-empty" role="status" hidden>Tidak ada donasi yang cocok dengan filter.</p>

    <?php if (empty($available)): ?>
        <p class="meta-text empty-state">Belum ada donasi yang bisa diklaim saat ini.</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($available as $item): ?>
                <article class="card" data-food-card
                         data-title="<?= e(strtolower($item['title'] . ' ' . $item['donor_name'])) ?>"
                         data-cat="<?= (int)$item['category_id'] ?>"
                         data-del="<?= e($item['delivery_method']) ?>">
                    <img src="<?= e($item['item_photo']) ?>" alt="Makanan donasi" class="card-photo" width="640" height="480" loading="lazy" decoding="async" data-site-image>
                    <div class="card-body">
                        <div>
                            <span class="badge <?= $item['delivery_method'] === 'antar' ? 'badge-delivery' : 'badge-pickup' ?>">
                                <?= $item['delivery_method'] === 'antar' ? 'Bisa Diantar' : 'Ambil Sendiri' ?>
                            </span>
                            <span class="badge badge-pending"><?= (int)$item['sisa_porsi'] ?> porsi sisa</span>
                        </div>
                        <h3><?= e($item['title']) ?></h3>
                        <p class="meta-text">
                            <strong>Kategori:</strong> <?= e($item['category_name'] ?? '-') ?><br>
                            <strong>Donatur:</strong> <?= e($item['donor_name']) ?> &middot; <?= e($item['donor_phone']) ?>
                        </p>
                        <p class="meta-text">
                            <strong>Batas layak konsumsi:</strong>
                            <?= $item['expires_at'] ? e(date('d M Y H:i', strtotime($item['expires_at']))) : 'tidak dibatasi' ?><br>
                            <strong>Lokasi:</strong> <?= e($item['pickup_address']) ?>
                        </p>

                        <?php if ($item['delivery_method'] === 'antar'): ?>
                            <p class="form-hint">Donatur mengantar, jadi alamat kirim wajib diisi saat klaim.</p>
                        <?php endif; ?>

                        <details>
                            <summary class="btn btn-primary btn-sm">Ajukan Klaim</summary>
                            <form method="POST">
            <?= csrfField() ?>
                                <input type="hidden" name="action" value="claim">
                                <input type="hidden" name="donation_id" value="<?= (int)$item['id'] ?>">
                                <div class="form-group">
                                    <label>Jumlah porsi (maks <?= (int)$item['sisa_porsi'] ?>)
                    <input type="number" name="porsi_diminta" min="1" max="<?= (int)$item['sisa_porsi'] ?>" required>
                </label>
                                </div>
                                <div class="form-group">
                                    <label>Alasan / peruntukan
                    <textarea name="alasan" rows="2" placeholder="mis. untuk makan malam 15 anak panti"></textarea>
                </label>
                                </div>
                                <?php if ($item['delivery_method'] === 'antar'): ?>
                                    <div class="form-group">
                                        <label>Alamat kirim
                    <textarea name="delivery_address" rows="2" required></textarea>
                </label>
                                    </div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label>Jadwal ambil / antar (opsional)
                    <input type="datetime-local" name="scheduled_at">
                </label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Kirim Klaim</button>
                            </form>
                        </details>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 class="section-title" id="klaim-saya">Klaim Saya (<?= count($myClaims) ?>)</h2>
    <?php if (empty($myClaims)): ?>
        <p class="meta-text empty-state">Belum ada klaim.</p>
    <?php else: ?>
        <div class="grid-cards">
            <?php foreach ($myClaims as $claim): ?>
                <article class="card">
                    <div class="card-body">
                        <div>
                            <span class="badge <?= e($claimBadge[$claim['status_claim']] ?? 'badge-target') ?>"><?= e($claim['status_claim']) ?></span>
                            <span class="badge <?= $claim['delivery_method'] === 'antar' ? 'badge-delivery' : 'badge-pickup' ?>">
                                <?= $claim['delivery_method'] === 'antar' ? 'Diantar' : 'Diambil' ?>
                            </span>
                            <?php if ($claim['rating'] !== null): ?>
                                <span class="badge badge-distributed">&#9733; <?= (int)$claim['rating'] ?></span>
                            <?php endif; ?>
                        </div>
                        <h3><?= e($claim['title']) ?></h3>
                        <p class="meta-text">
                            <strong>Porsi diminta:</strong> <?= (int)$claim['porsi_diminta'] ?> &middot;
                            <strong>Donatur:</strong> <?= e($claim['donor_name']) ?> (<?= e($claim['donor_phone']) ?>)<br>
                            <strong>Lokasi:</strong> <?= e($claim['pickup_address']) ?>
                        </p>
                        <?php if ($claim['delivery_address']): ?>
                            <p class="meta-text"><strong>Kirim ke:</strong> <?= e($claim['delivery_address']) ?></p>
                        <?php endif; ?>
                        <?php if ($claim['received_notes']): ?>
                            <p class="meta-text">"<?= e($claim['received_notes']) ?>"</p>
                        <?php endif; ?>

                        <?php if ($claim['status_claim'] === 'disetujui'): ?>
                            <div class="proof-box">
                                <p><strong>Sudah menerima makanan? Unggah buktinya.</strong></p>
                                <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
                                    <input type="hidden" name="action" value="confirm_receipt">
                                    <input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>">
                                    <div class="form-group">
                                        <label>Foto bukti terima
                    <input type="file" name="proof_photo" accept="image/jpeg,image/png,image/webp" required>
                </label>
                                    </div>
                                    <div class="form-group">
                                        <label>Catatan
                    <textarea name="received_notes" rows="2" placeholder="mis. diterima lengkap, langsung dibagikan"></textarea>
                </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">Konfirmasi Terima</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <?php if ($claim['status_claim'] === 'selesai'): ?>
                            <div class="card-actions">
                                <form method="POST" class="full-width">
            <?= csrfField() ?>
                                    <input type="hidden" name="action" value="review">
                                    <input type="hidden" name="claim_id" value="<?= (int)$claim['id'] ?>">
                                    <div class="form-group">
                                        <label><?= $claim['rating'] !== null ? 'Ubah ulasan' : 'Beri ulasan' ?>
                    <select name="rating">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <option value="<?= $i ?>" <?= (int)$claim['rating'] === $i ? 'selected' : '' ?>>&#9733; <?= $i ?></option>
                                            <?php endfor; ?>
                                        </select>
                </label>
                                    </div>
                                    <div class="form-group">
                                        <textarea aria-label="Komentar ulasan" name="komentar" rows="2" placeholder="Ucapan terima kasih atau masukan"><?= e($claim['komentar'] ?? '') ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-secondary btn-sm">Simpan Ulasan</button>
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
<script src="assets/js/live-search.js"></script>
</body>
</html>
