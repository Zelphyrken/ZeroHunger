<?php
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'donor' ? 'donor_dashboard.php' : 'volunteer_dashboard.php'));
    exit;
}

$error = '';
$selectedRole = ($_GET['role'] ?? 'donor') === 'volunteer' ? 'volunteer' : 'donor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = ($_POST['role'] ?? '') === 'volunteer' ? 'volunteer' : 'donor';

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
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$fullName, $email, $phone, $hash, $role]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['full_name'] = $fullName;
            $_SESSION['role'] = $role;

            header("Location: " . ($role === 'donor' ? 'donor_dashboard.php' : 'volunteer_dashboard.php'));
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
    <title>Register - Berbagi</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Berbagi</a>
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
            <div class="form-group">
                <label>I want to join as</label>
                <select name="role" required>
                    <option value="donor" <?= $selectedRole === 'donor' ? 'selected' : '' ?>>Donor (Share Items)</option>
                    <option value="volunteer" <?= $selectedRole === 'volunteer' ? 'selected' : '' ?>>Volunteer (Distribute Items)</option>
                </select>
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