<?php
require_once __DIR__ . '/config/db.php';

// Logout lewat tautan bertoken: tanpa token yang cocok, sesi tidak dihancurkan.
// Ini menutup CSRF logout (situs lain memaksa user keluar tanpa sepengetahuannya).
$sent = $_GET['csrf'] ?? '';
if (!is_string($sent) || !hash_equals(csrfToken(), $sent)) {
    http_response_code(419);
    die("Permintaan logout tidak sah. Muat ulang halaman lalu coba lagi.");
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header("Location: index.php");
exit;
