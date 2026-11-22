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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Porsela</a>
        <div class="nav-links">
            <a href="login.php" class="btn btn-outline btn-sm">Login</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="auth-box">
        <h2 style="margin-bottom:1rem;">Create an Account</h2>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>I want to join as</label>
                <select name="role" required>
                    <option value="donor" <?= $selectedRole === 'donor' ? 'selected' : '' ?>>Donor (Share Surplus Food)</option>
                    <option value="beneficiary" <?= $selectedRole === 'beneficiary' ? 'selected' : '' ?>>Receiver (Find Food)</option>
                </select>
                <p class="form-hint">Akun donor masuk antrean verifikasi admin dulu. Selama belum diverifikasi, donasinya tidak tampil di pencarian penerima.</p>
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Phone / WhatsApp Number</label>
                <input type="text" name="phone" required placeholder="e.g. 08123456789" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password (min. 6 characters)</label>
                <input type="password" name="password" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Register</button>
        </form>
        <p class="meta-text" style="margin-top:1rem; text-align:center;">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>
</div>
</body>
</html>