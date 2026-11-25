<?php
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . roleHome($_SESSION['role']));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = "Invalid email or password.";
    } elseif ($user['verification_status'] === 'rejected') {
        // admin memblokir akun ini
        $error = "Akun ini diblokir oleh admin. Hubungi admin@porsela untuk klarifikasi.";
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['verification_status'] = $user['verification_status'];

        header("Location: " . roleHome($user['role']));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= themeClass() === 'dark' ? '#101D24' : '#F7F7F7' ?>">
    <title>Login - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body class="app-page auth-page auth-login <?= themeClass() ?>">
<a href="#main-content" class="site-skip">Lewati ke konten utama</a>
<div class="auth-shell">
    <header class="auth-header">
        <nav class="auth-nav" aria-label="Navigasi autentikasi">
            <a href="index.php" class="site-brand" aria-label="PORSELA, beranda" translate="no">PORSELA<span aria-hidden="true">.</span></a>
            <a href="index.php" class="auth-back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg>Kembali ke beranda</a>
        </nav>
    </header>

<main class="container auth-layout" id="main-content" tabindex="-1">
    <section class="auth-story"><h1>Kembali menyambung kebaikan.</h1><p>Masuk untuk melanjutkan berbagi makanan dan mengelola aktivitas Anda.</p></section>
    <div class="auth-form-panel">
    <div class="auth-box">
        <h2>Selamat datang kembali</h2>
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Alamat email
                    <span class="auth-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg>
                    <input autocomplete="email" spellcheck="false" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                    </span>
                </label>
            </div>
            <div class="form-group">
                <label>Password
                    <span class="auth-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/></svg>
                    <input autocomplete="current-password" type="password" name="password" required>
                    </span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary full-width">Login</button>
        </form>
        <p class="auth-switch">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
        </p>
    </div>
    </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
