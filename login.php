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
    <meta name="theme-color" content="<?= themeClass() === 'dark' ? '#15251e' : '#f7f5ed' ?>">
    <title>Login - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body class="app-page <?= themeClass() ?>">
<?php require __DIR__ . '/includes/navigation.php'; ?>

<main class="container auth-layout" id="main-content" tabindex="-1">
    <section class="auth-story"><h1>Kembali menyambung kebaikan.</h1><p>Masuk untuk melanjutkan berbagi makanan dan mengelola aktivitas Anda.</p><a href="index.php">Tentang PORSELA</a></section>
    <div class="auth-box">
        <h2>Selamat datang kembali</h2>
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Alamat email
                    <input autocomplete="email" spellcheck="false" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </label>
            </div>
            <div class="form-group">
                <label>Password
                    <input autocomplete="current-password" type="password" name="password" required>
                </label>
            </div>
            <button type="submit" class="btn btn-primary full-width">Login</button>
        </form>
        <p class="auth-switch">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
        </p>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>