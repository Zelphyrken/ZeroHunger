<?php
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . roleHome($_SESSION['role']));
    exit;
}

$error = '';
$allowedRoles = ['donor', 'beneficiary'];
$selectedRole = in_array($_GET['role'] ?? '', $allowedRoles, true) ? $_GET['role'] : 'donor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = in_array($_POST['role'] ?? '', $allowedRoles, true) ? $_POST['role'] : 'donor';

    if (!$fullName || !$email || !$phone || strlen($password) < 6) {
        $error = "All fields are required and password must be at least 6 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = "An account with this email already exists.";
        } else {
            // Donatur baru masuk antrean verifikasi admin; penerima langsung aktif.
            $verification = $role === 'donor' ? 'pending' : 'verified';
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, phone, password_hash, role, verification_status, verified_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fullName,
                $email,
                $phone,
                $hash,
                $role,
                $verification,
                $verification === 'verified' ? date('Y-m-d H:i:s') : null,
            ]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['full_name'] = $fullName;
            $_SESSION['role'] = $role;
            $_SESSION['verification_status'] = $verification;

            header("Location: " . roleHome($role));
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
    <title>Register - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body class="app-page auth-page auth-register <?= themeClass() ?>">
<a href="#main-content" class="site-skip">Lewati ke konten utama</a>
<div class="auth-shell">
    <header class="auth-header">
        <nav class="auth-nav" aria-label="Navigasi autentikasi">
            <a href="index.php" class="site-brand" aria-label="PORSELA, beranda" translate="no">PORSELA<span aria-hidden="true">.</span></a>
            <a href="index.php" class="auth-back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/></svg>Kembali ke beranda</a>
        </nav>
    </header>

<main class="container auth-layout" id="main-content" tabindex="-1">
    <section class="auth-story"><h1>Satu akun. Banyak kesempatan berbagi.</h1><p>Jadi donatur atau penerima. Mulai dari satu porsi untuk membuat kepedulian sampai ke meja berikutnya.</p></section>
    <div class="auth-form-panel">
    <div class="auth-box">
        <h2>Bergabung dengan PORSELA</h2>
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <fieldset class="auth-roles" aria-describedby="auth-role-note">
                <legend>Saya ingin bergabung sebagai</legend>
                <div class="auth-role-options">
                <label class="auth-role-card">
                    <input type="radio" name="role" value="donor" required <?= ($role ?? $selectedRole) === 'donor' ? 'checked' : '' ?>>
                    <span><strong>Donatur</strong> <small>(berbagi makanan)</small></span>
                </label>
                <label class="auth-role-card">
                    <input type="radio" name="role" value="beneficiary" required <?= ($role ?? $selectedRole) === 'beneficiary' ? 'checked' : '' ?>>
                    <span><strong>Penerima</strong> <small>(mencari makanan)</small></span>
                </label>
                </div>
            </fieldset>
            <div class="form-group">
                <label>Nama lengkap
                    <span class="auth-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
                    <input autocomplete="name" type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
                    </span>
                </label>
            </div>
            <div class="form-group">
                <label>Alamat email
                    <span class="auth-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg>
                    <input autocomplete="email" spellcheck="false" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                    </span>
                </label>
            </div>
            <div class="form-group">
                <label>Nomor HP / WhatsApp
                    <span class="auth-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M7 3H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3l-5-2-2 2a15 15 0 0 1-7-7l2-2-2-5Z"/></svg>
                    <input autocomplete="tel" inputmode="tel" type="text" name="phone" required placeholder="e.g. 08123456789" value="<?= e($_POST['phone'] ?? '') ?>">
                    </span>
                </label>
            </div>
            <div class="form-group">
                <label>Password (minimal 6 karakter)
                    <span class="auth-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/></svg>
                    <input autocomplete="new-password" type="password" name="password" required minlength="6">
                    </span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary full-width">Daftar</button>
            <p class="form-hint auth-note" id="auth-role-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/></svg><span>Akun donor masuk antrean verifikasi admin dulu. Selama belum diverifikasi, donasinya tidak tampil di pencarian penerima.</span></p>
        </form>
        <p class="auth-switch">
            Sudah punya akun? <a href="login.php">Login di sini</a>
        </p>
    </div>
    </div>
</main>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
