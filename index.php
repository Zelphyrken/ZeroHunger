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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="PORSELA menghubungkan makanan berlebih yang masih layak konsumsi dengan masyarakat yang membutuhkan. Berbagi porsi, menumbuhkan harapan.">
    <meta name="theme-color" content="<?= themeClass() === 'dark' ? '#101D24' : '#F7F7F7' ?>">
    <title>PORSELA — Berbagi Porsi, Menumbuhkan Harapan</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <script src="assets/js/home.js" defer></script>
    <script src="assets/js/site.js" defer></script>
</head>
<body class="home-page <?= themeClass() ?>">
<?php require __DIR__ . '/includes/navigation.php'; ?>

<main id="home-main" tabindex="-1">
    <section class="home-hero home-wrap" aria-labelledby="home-title">
        <h1 id="home-title">Berbagi porsi,<br>menumbuhkan <em>harapan.</em></h1>
        <div class="home-hero-intro">
            <p>Makanan berlebih, kesempatan untuk peduli. PORSELA menghubungkan makanan yang masih layak konsumsi dengan masyarakat yang membutuhkan.</p>
            <div class="home-actions">
                <a href="register.php?role=donor" class="btn btn-accent home-button">Mulai Berdonasi</a>
                <a href="register.php?role=beneficiary" class="btn btn-outline home-button">Cari Makanan</a>
            </div>
        </div>
        <figure class="home-hero-figure">
            <img src="assets/images/header.png" width="1366" height="786" alt="Orang-orang saling menggenggam tangan di sekeliling meja sebagai bentuk kebersamaan." fetchpriority="high">
            <figcaption><span>Berawal dari kepedulian. Bertumbuh lewat kebersamaan.</span><span class="home-photo-note">Porsi berlebih jadi berarti.</span></figcaption>
        </figure>
    </section>

    <section class="home-impact home-wrap" aria-labelledby="home-impact-title">
        <div class="home-impact-intro">
            <h2 id="home-impact-title">Kebaikan yang<br>terus tersambung.</h2>
            <a class="home-text-link" href="dashboard.php">Lihat dampak bersama</a>
        </div>
        <dl class="home-stats">
            <div><dt>Total Donasi</dt><dd><?= number_format((int)$totalDonations, 0, ',', '.') ?></dd></div>
            <div><dt>Makanan Tersalurkan</dt><dd><?= number_format((int)$distributedCount, 0, ',', '.') ?></dd></div>
            <div><dt>Donatur Terverifikasi</dt><dd><?= number_format((int)$verifiedDonors, 0, ',', '.') ?></dd></div>
        </dl>
    </section>

    <section class="home-stories home-wrap" id="cerita-berbagi" aria-labelledby="home-stories-title">
        <div class="home-section-heading">
            <h2 id="home-stories-title">Porsi tersalurkan.<br><em>Kepedulian tersampaikan.</em></h2>
            <p>Distribusi terbaru dari komunitas PORSELA. Dari makanan yang dibagikan hingga bukti penerimaan, setiap proses punya cerita.</p>
        </div>
        <?php if (empty($recentDistributions)): ?>
            <div class="home-empty">
                <h3>Cerita berbagi pertama bisa dimulai dari Anda.</h3>
                <p>Belum ada distribusi yang selesai. Bagikan makanan berlebih yang masih layak konsumsi dan mulai sambungkan kebaikan.</p>
                <a href="register.php?role=donor" class="home-text-link">Mulai Berdonasi</a>
            </div>
        <?php else: ?>
        <div class="home-distributions">
            <?php foreach ($recentDistributions as $item): ?>
                <article class="home-distribution">
                    <div class="home-distribution-media">
                        <div class="home-food-photo home-media">
                            <span class="home-image-fallback">Foto makanan belum tersedia</span>
                            <?php if (!empty($item['item_photo'])): ?>
                                <img src="<?= e($item['item_photo']) ?>" alt="Makanan donasi: <?= e($item['title']) ?>" width="640" height="480" loading="lazy" decoding="async" data-home-image>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($item['proof_photo'])): ?>
                            <figure class="home-proof">
                                <div class="home-media">
                                    <span class="home-image-fallback">Foto bukti tidak tersedia</span>
                                    <img src="<?= e($item['proof_photo']) ?>" alt="Bukti penerimaan donasi <?= e($item['title']) ?>" width="160" height="120" loading="lazy" decoding="async" data-home-image>
                                </div>
                                <figcaption>Bukti penerimaan</figcaption>
                            </figure>
                        <?php endif; ?>
                    </div>
                    <div class="home-distribution-body">
                        <div class="home-distribution-status"><span class="home-status">Tersalurkan</span><span><?= $item['delivery_method'] === 'antar' ? 'Diantar' : 'Diambil langsung' ?></span></div>
                        <h3><?= e($item['title']) ?></h3>
                        <p class="home-donor">Dibagikan oleh <strong><?= e($item['donor_name']) ?></strong></p>
                        <dl class="home-distribution-details">
                            <div><dt>Jumlah</dt><dd><?= number_format((int)$item['porsi'], 0, ',', '.') ?> porsi</dd></div>
                            <div><dt>Kategori</dt><dd><?= e($item['category_name'] ?? '-') ?></dd></div>
                        </dl>
                        <?php if (!empty($item['received_notes'])): ?>
                            <blockquote class="home-receipt-note"><p>&ldquo;<?= e($item['received_notes']) ?>&rdquo;</p><cite>Catatan penerimaan</cite></blockquote>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="home-how" id="cara-berbagi" aria-labelledby="home-how-title">
        <div class="home-wrap home-how-layout">
            <div class="home-how-intro">
                <h2 id="home-how-title">Ada porsi lebih?<br><em>Ada cara berbagi.</em></h2>
                <p>Dari dapur rumah, warung, restoran, hingga katering. Kepedulian Anda bisa sampai ke meja makan berikutnya.</p>
                <a href="register.php?role=donor" class="home-text-link">Bagikan makanan Anda</a>
            </div>
            <ol class="home-steps">
                <li><span class="home-step-number" aria-hidden="true">01</span><div><h3>Bagikan makanan berlebih</h3><p>Donatur mengunggah foto makanan, jumlah porsi, batas layak konsumsi, dan metode penyerahan.</p></div></li>
                <li><span class="home-step-number" aria-hidden="true">02</span><div><h3>Temukan dan ajukan klaim</h3><p>Penerima mencari makanan yang tersedia dan mengajukan klaim sesuai jumlah porsi yang dibutuhkan.</p></div></li>
                <li><span class="home-step-number" aria-hidden="true">03</span><div><h3>Selesaikan penyerahan bersama</h3><p>Donatur dan penerima menyelesaikan penyerahan. Bukti penerimaan melengkapi cerita berbagi.</p></div></li>
            </ol>
        </div>
    </section>

    <section class="home-closing home-wrap" aria-labelledby="home-closing-title">
        <div><h2 id="home-closing-title">Porsi kecil Anda.<br><em>Arti besar bagi sesama.</em></h2><p>Makanan yang masih baik layak mendapat kesempatan untuk dinikmati. Mari mulai dari yang bisa kita bagikan hari ini.</p></div>
        <a href="register.php" class="btn btn-primary home-button">Jadi bagian dari PORSELA</a>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
