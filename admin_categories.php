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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= themeClass() === 'dark' ? '#101D24' : '#F7F7F7' ?>">
    <title>Kategori - Porsela</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/site.js" defer></script>
</head>
<body class="app-page category-page <?= themeClass() ?>">
<?php require __DIR__ . '/includes/navigation.php'; ?>

<main class="container" id="main-content" tabindex="-1">
    <header class="page-header"><h1>Kategori makanan</h1><p>Kelola kategori agar makanan lebih mudah ditemukan oleh penerima.</p></header>
    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <section class="form-panel">
        <h2 class="section-title">Tambah Kategori</h2>
        <form method="POST" class="form-grid">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Nama kategori
                    <input type="text" name="nama" required placeholder="mis. Makanan Siap Santap">
                </label>
            </div>
            <div class="form-group">
                <label>Deskripsi
                    <input type="text" name="deskripsi" placeholder="keterangan singkat">
                </label>
            </div>
            <div class="form-group">
                <span class="form-spacer" aria-hidden="true"></span>
                <button type="submit" class="btn btn-primary">Tambah</button>
            </div>
        </form>
    </section>

    <h2 class="section-title">Daftar Kategori (<?= count($rows) ?>)</h2>
    <?php if (empty($rows)): ?>
        <p class="empty-state">Belum ada kategori. Tambahkan kategori makanan melalui form di atas.</p>
    <?php endif; ?>
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
                            <label>Nama
                    <input type="text" name="nama" required value="<?= e($row['nama']) ?>">
                </label>
                        </div>
                        <div class="form-group">
                            <label>Deskripsi
                    <input type="text" name="deskripsi" value="<?= e($row['deskripsi'] ?? '') ?>">
                </label>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
