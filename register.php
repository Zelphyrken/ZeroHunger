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
    <meta name="theme-color" content="<?= themeClass() === 'dark' ? '#15251e' : '#f7f5ed' ?>">
    <title>Register - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body class="app-page <?= themeClass() ?>">
<?php require __DIR__ . '/includes/navigation.php'; ?>

<main class="container auth-layout" id="main-content" tabindex="-1">
    <section class="auth-story"><h1>Satu akun. Banyak kesempatan berbagi.</h1><p>Jadi donatur atau penerima. Mulai dari satu porsi untuk membuat kepedulian sampai ke meja berikutnya.</p><a href="index.php">Tentang PORSELA</a></section>
    <div class="auth-box">
        <h2>Bergabung dengan PORSELA</h2>
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Saya ingin bergabung sebagai
                    <select name="role" required>
                    <option value="donor" <?= $selectedRole === 'donor' ? 'selected' : '' ?>>Donatur (berbagi makanan)</option>
                    <option value="beneficiary" <?= $selectedRole === 'beneficiary' ? 'selected' : '' ?>>Penerima (mencari makanan)</option>
                </select>
                </label>
                <p class="form-hint">Akun donor masuk antrean verifikasi admin dulu. Selama belum diverifikasi, donasinya tidak tampil di pencarian penerima.</p>
            </div>
            <div class="form-group">
                <label>Nama lengkap
                    <input autocomplete="name" type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
                </label>
            </div>
            <div class="form-group">
                <label>Alamat email
                    <input autocomplete="email" spellcheck="false" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </label>
            </div>
            <div class="form-group">
                <label>Nomor HP / WhatsApp
                    <input autocomplete="tel" inputmode="tel" type="text" name="phone" required placeholder="e.g. 08123456789" value="<?= e($_POST['phone'] ?? '') ?>">
                </label>
            </div>
            <div class="form-group">
                <label>Password (minimal 6 karakter)
                    <input autocomplete="new-password" type="password" name="password" required minlength="6">
                </label>
            </div>
            <button type="submit" class="btn btn-primary full-width">Daftar</button>
        </form>
        <p class="auth-switch">
            Sudah punya akun? <a href="login.php">Login di sini</a>
        </p>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>