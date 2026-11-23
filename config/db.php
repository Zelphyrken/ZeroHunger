<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PHP dan MySQL harus satu jam. Tanpa ini, validasi kadaluarsa meleset
// sebesar selisih timezone php.ini (Europe/Berlin) vs jam sistem MySQL (+07:00).
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$dbname = 'berbagi_db';
$username = 'root';
$password = ''; // Change if your MySQL has a password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Kunci zona waktu sesi MySQL supaya NOW() tidak ikut jam sistem yang bisa berubah.
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function requireRole(string $role): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $role) {
        header("Location: login.php");
        exit;
    }
}

/** Halaman beranda tiap role. Sumber tunggal supaya tidak ada redirect yang beda-beda. */
function roleHome(string $role): string {
    $map = [
        'donor' => 'donor_dashboard.php',
        'beneficiary' => 'penerima_dashboard.php',
        'admin' => 'admin_dashboard.php',
    ];
    return $map[$role] ?? 'login.php';
}

function roleLabel(string $role): string {
    $map = ['donor' => 'Donor', 'beneficiary' => 'Receiver', 'admin' => 'Admin'];
    return $map[$role] ?? $role;
}

/** Token CSRF per sesi. Dipakai di semua form POST. */
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

/** Dipanggil di awal blok POST. Hentikan request kalau token tidak cocok. */
function csrfCheck(): void {
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrfToken(), $sent)) {
        http_response_code(419);
        die("Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.");
    }
}

/** Theme dari cookie, dipakai sebagai class <body>. */
function themeClass(): string {
    return ($_COOKIE['theme'] ?? 'light') === 'dark' ? 'dark' : '';
}

/** Sinkronkan status donasi dari kondisi klaimnya. pending=tersedia, claimed=diproses, distributed=selesai. */
function syncDonationStatus(PDO $pdo, int $donationId): void {
    if ($donationId <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT d.porsi,
               COALESCE(SUM(CASE WHEN cl.status_claim = 'selesai' THEN cl.porsi_diminta END), 0) AS tersalur,
               COUNT(CASE WHEN cl.status_claim IN ('menunggu', 'disetujui') THEN 1 END) AS aktif
        FROM donations d
        LEFT JOIN claims cl ON cl.donation_id = d.id
        WHERE d.id = ?
        GROUP BY d.id
    ");
    $stmt->execute([$donationId]);
    $row = $stmt->fetch();

    if (!$row) {
        return;
    }

    $status = ((int)$row['tersalur'] >= (int)$row['porsi'])
        ? 'distributed'
        : (((int)$row['aktif'] > 0) ? 'claimed' : 'pending');

    $pdo->prepare("
        UPDATE donations
        SET status = ?,
            distributed_at = CASE WHEN ? = 'distributed' THEN COALESCE(distributed_at, NOW()) ELSE NULL END
        WHERE id = ?
    ")->execute([$status, $status, $donationId]);
}

function ensureUploadDirs(): void {
    $dirs = [__DIR__ . '/../uploads/items', __DIR__ . '/../uploads/proofs'];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function handleUpload(array $file, array $allowedMimeTypes, string $subDir, int $maxSizeBytes): array {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'File upload failed or missing. Check file size limits.'];
    }

    if ($file['size'] > $maxSizeBytes) {
        $mb = round($maxSizeBytes / (1024 * 1024), 1);
        return ['error' => "File exceeds maximum allowed size of {$mb}MB."];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mime, $allowedMimeTypes)) {
        return ['error' => 'Invalid file format uploaded.'];
    }

    ensureUploadDirs();
    $ext = $allowedMimeTypes[$mime];
    $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = __DIR__ . "/../uploads/{$subDir}/{$newFilename}";

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['error' => 'Could not save uploaded file.'];
    }

    return ['path' => "uploads/{$subDir}/{$newFilename}"];
}