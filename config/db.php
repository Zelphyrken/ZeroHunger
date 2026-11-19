<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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