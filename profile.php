<?php
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    // ---- Simpan tema (cookie 1 tahun) ----
    if ($action === 'theme') {
        $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
        setcookie('theme', $theme, time() + 60 * 60 * 24 * 365, '/');
        $_COOKIE['theme'] = $theme; // biar render halaman ini langsung ikut berubah
        $success = "Tema disimpan.";
    }

    // ---- Ubah nama & nomor HP ----
    elseif ($action === 'profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!$fullName || !$phone) {
            $error = "Nama dan nomor HP wajib diisi.";
        } else {
            $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")
                ->execute([$fullName, $phone, $_SESSION['user_id']]);
            $_SESSION['full_name'] = $fullName;
            $success = "Profil diperbarui.";
        }
    }

    // ---- Ganti password ----
    elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $error = "Password lama salah.";
        } elseif (strlen($new) < 6) {
            $error = "Password baru minimal 6 karakter.";
        } else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            $success = "Password diganti.";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

$theme = ($_COOKIE['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= themeClass() ?>">
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Porsela</a>
        <div class="nav-links">
            <a href="<?= e(roleHome($me['role'])) ?>" class="btn btn-outline btn-sm">Dashboard</a>
            <a href="dashboard.php" class="btn btn-outline btn-sm">Dampak</a>
            <a href="logout.php?csrf=<?= csrfToken() ?>" class="btn btn-outline btn-sm">Logout</a>
        </div>
    </div>
</nav>

<main class="container">
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="grid-cards">
        <section class="form-panel">
            <h2 class="section-title">Profil</h2>
            <p class="meta-text">
                Role: <strong><?= e(roleLabel($me['role'])) ?></strong><br>
                Status: <strong><?= e($me['verification_status']) ?></strong><br>
                Bergabung: <?= e(date('d M Y', strtotime($me['created_at']))) ?><br>
                Email: <?= e($me['email']) ?>
                <?php if ($me['role'] === 'donor' && $me['verification_status'] !== 'verified'): ?>
                    <br>Akun donor perlu diverifikasi admin sebelum donasinya tampil di pencarian.
                <?php endif; ?>
            </p>

            <form method="POST">
            <?= csrfField() ?>
                <input type="hidden" name="action" value="profile">
                <div class="form-group">
                    <label>Nama</label>
                    <input type="text" name="full_name" required value="<?= e($me['full_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Nomor HP / WhatsApp</label>
                    <input type="text" name="phone" required value="<?= e($me['phone']) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Profil</button>
            </form>
        </section>

        <section class="form-panel">
            <h2 class="section-title">Ganti Password</h2>
            <form method="POST">
            <?= csrfField() ?>
                <input type="hidden" name="action" value="password">
                <div class="form-group">
                    <label>Password lama</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>Password baru (min. 6 karakter)</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <button type="submit" class="btn btn-secondary btn-sm">Ganti Password</button>
            </form>
        </section>

        <section class="form-panel">
            <h2 class="section-title">Tema Tampilan</h2>
            <p class="meta-text">Pilihan disimpan di cookie browser, jadi tetap aktif saat halaman dibuka lagi.</p>
            <form method="POST">
            <?= csrfField() ?>
                <input type="hidden" name="action" value="theme">
                <div class="form-group">
                    <label>Tema</label>
                    <select name="theme">
                        <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>Terang</option>
                        <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>Gelap</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Simpan Tema</button>
            </form>
        </section>
    </div>
</main>
</body>
</html>
