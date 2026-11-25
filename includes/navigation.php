<?php
// Presentation only; access checks stay in the original page controllers.
$uiPage = basename($_SERVER['SCRIPT_NAME']);
$uiHome = $uiPage === 'index.php';
$uiMain = $uiHome ? 'home-main' : 'main-content';
$uiNavId = $uiHome ? 'home-navigation' : 'site-navigation';
?>
<a href="#<?= $uiMain ?>" class="site-skip">Lewati ke konten utama</a>
<header class="site-header">
    <nav class="site-nav site-wrap" aria-label="Navigasi utama">
        <a href="index.php" class="site-brand" aria-label="PORSELA, beranda" <?= $uiHome ? 'aria-current="page"' : '' ?> translate="no">PORSELA<span aria-hidden="true">.</span></a>
        <button class="site-menu-toggle" type="button" aria-expanded="false" aria-controls="<?= $uiNavId ?>" hidden>Menu <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 8h16M4 16h16"/></svg></button>
        <div class="site-nav-links" id="<?= $uiNavId ?>">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="<?= $uiHome ? '' : 'index.php' ?>#cara-berbagi" class="site-nav-link">Cara berbagi</a>
                <a href="<?= $uiHome ? '' : 'index.php' ?>#cerita-berbagi" class="site-nav-link">Cerita berbagi</a>
            <?php else: ?>
                <a href="index.php" class="site-nav-link" <?= $uiHome ? 'aria-current="page"' : '' ?>>Beranda</a>
            <?php endif; ?>
            <a href="dashboard.php" class="site-nav-link" <?= $uiPage === 'dashboard.php' ? 'aria-current="page"' : '' ?>>Dampak</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_categories.php" class="site-nav-link" <?= $uiPage === 'admin_categories.php' ? 'aria-current="page"' : '' ?>>Kategori</a>
                <?php endif; ?>
                <a href="profile.php" class="site-nav-link" <?= $uiPage === 'profile.php' ? 'aria-current="page"' : '' ?>>Profil</a>
                <span class="site-nav-user">Halo, <?= e($_SESSION['full_name']) ?><small><?= e(roleLabel($_SESSION['role'])) ?></small></span>
                <a href="<?= e(roleHome($_SESSION['role'])) ?>" class="btn btn-primary site-nav-cta" <?= $uiPage === roleHome($_SESSION['role']) ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <a href="logout.php?csrf=<?= csrfToken() ?>" class="site-nav-link">Logout</a>
            <?php else: ?>
                <a href="login.php" class="site-nav-link" <?= $uiPage === 'login.php' ? 'aria-current="page"' : '' ?>>Login</a>
                <a href="register.php" class="btn btn-primary site-nav-cta" <?= $uiPage === 'register.php' ? 'aria-current="page"' : '' ?>>Daftar untuk berbagi</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
