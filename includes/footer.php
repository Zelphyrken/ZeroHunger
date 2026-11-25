<footer class="site-footer">
    <div class="site-wrap">
        <div class="site-footer-top">
            <div><a href="index.php" class="site-brand" aria-label="PORSELA, beranda" translate="no">PORSELA<span aria-hidden="true">.</span></a><p>Porsi berlebih jadi makanan.<br>Kepedulian menjadi kebersamaan.</p></div>
            <nav class="site-footer-links" aria-label="Navigasi footer"><a href="index.php#cara-berbagi">Cara berbagi</a><a href="dashboard.php">Dampak bersama</a>
                <?php if (isset($_SESSION['user_id'])): ?><a href="<?= e(roleHome($_SESSION['role'])) ?>">Dashboard</a><?php else: ?><a href="register.php">Bergabung</a><?php endif; ?>
            </nav>
            <div class="site-sdg"><span class="site-sdg-number" aria-hidden="true">2</span><p>Mendukung SDG 2<strong>Zero Hunger</strong><span>Bersama menuju dunia tanpa kelaparan.</span></p></div>
        </div>
        <div class="site-footer-bottom"><p>&copy; <?= date('Y') ?> PORSELA Platform</p></div>
    </div>
</footer>
