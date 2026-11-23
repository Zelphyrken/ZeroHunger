<?php
require_once __DIR__ . '/config/db.php';
requireRole('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if ($action === 'create') {
        if (!$nama) {
            $error = "Nama kategori wajib diisi.";
        } else {
            $dupe = $pdo->prepare("SELECT id FROM categories WHERE nama = ?");
            $dupe->execute([$nama]);

            if ($dupe->fetch()) {
                $error = "Kategori itu sudah ada.";
            } else {
                $pdo->prepare("INSERT INTO categories (nama, deskripsi) VALUES (?, ?)")
                    ->execute([$nama, $deskripsi !== '' ? $deskripsi : null]);
                $success = "Kategori ditambahkan.";
            }
        }
    } elseif ($action === 'update') {
        if ($id < 1 || !$nama) {
            $error = "Data kategori tidak lengkap.";
        } else {
            $pdo->prepare("UPDATE categories SET nama = ?, deskripsi = ? WHERE id = ?")
                ->execute([$nama, $deskripsi !== '' ? $deskripsi : null, $id]);
            $success = "Kategori diperbarui.";
        }
    } elseif ($action === 'delete') {
        // Kategori yang masih dipakai donasi tidak boleh dihapus (kalau tidak, kategori donasi itu jadi kosong).
        $used = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE category_id = ?");
        $used->execute([$id]);
        $usedCount = (int)$used->fetchColumn();

        if ($usedCount > 0) {
            $error = "Kategori ini masih dipakai {$usedCount} donasi, pindahkan donasinya dulu.";
        } else {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            $success = "Kategori dihapus.";
        }
    }
}

$rows = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM donations d WHERE d.category_id = c.id) AS used
    FROM categories c
    ORDER BY c.nama
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= themeClass() ?>">
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="brand">Porsela</a>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="btn btn-outline btn-sm">Admin Panel</a>
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

    <section class="form-panel">
        <h2 class="section-title">Tambah Kategori</h2>
        <form method="POST" class="form-grid">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Nama kategori</label>
                <input type="text" name="nama" required placeholder="mis. Makanan Siap Santap">
            </div>
            <div class="form-group">
                <label>Deskripsi</label>
                <input type="text" name="deskripsi" placeholder="keterangan singkat">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Tambah</button>
            </div>
        </form>
    </section>

    <h2 class="section-title">Daftar Kategori (<?= count($rows) ?>)</h2>
    <div class="grid-cards">
        <?php foreach ($rows as $row): ?>
            <article class="card">
                <div class="card-body">
                    <div>
                        <span class="badge badge-target"><?= (int)$row['used'] ?> donasi</span>
                    </div>
                    <form method="POST">
            <?= csrfField() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <div class="form-group">
                            <label>Nama</label>
                            <input type="text" name="nama" required value="<?= e($row['nama']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Deskripsi</label>
                            <input type="text" name="deskripsi" value="<?= e($row['deskripsi'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Hapus kategori ini?');">
            <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
