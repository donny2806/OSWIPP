<?php
// logout.php
// Skrip ini bertanggung jawab untuk mengakhiri sesi pengguna dan mengarahkan mereka
// kembali ke halaman login.

// Pastikan sesi sudah dimulai sebelum mencoba menghancurkannya.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel sesi.
$_SESSION = array();

// Jika ingin menghancurkan sesi sepenuhnya, hapus juga cookie sesi.
// Catatan: Ini akan menghancurkan sesi, dan bukan hanya data sesi!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Akhirnya, hancurkan sesi.
session_destroy();

// Arahkan pengguna kembali ke halaman login (index.php) setelah logout.
header("Location: index.php");
exit();
?>
