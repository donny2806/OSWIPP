<?php
// process_password_update.php
// File ini menangani logika pembaruan kata sandi pengguna.

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];

// Periksa apakah permintaan adalah POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data yang dikirimkan dari formulir
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    // Inisialisasi variabel pesan
    // Pesan akan disimpan di sesi, bukan di URL
    $_SESSION['form_message'] = '';
    $_SESSION['form_message_type'] = 'error'; // Default ke error

    try {
        // 1. Ambil kata sandi yang tersimpan dari database untuk pengguna saat ini
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION['form_message'] = "Pengguna tidak ditemukan.";
            header('Location: profile_settings.php');
            exit();
        }

        $hashed_password_from_db = $user['password'];

        // 2. Verifikasi kata sandi lama
        // Gunakan password_verify() untuk membandingkan kata sandi lama yang dimasukkan dengan hash di database
        if (!password_verify($current_password, $hashed_password_from_db)) {
            $_SESSION['form_message'] = "Kata sandi lama salah.";
            header('Location: profile_settings.php');
            exit();
        }

        // 3. Validasi kata sandi baru
        if (empty($new_password)) {
            $_SESSION['form_message'] = "Kata sandi baru tidak boleh kosong.";
            header('Location: profile_settings.php');
            exit();
        }

        if (strlen($new_password) < 6) { // Contoh: kata sandi minimal 6 karakter
            $_SESSION['form_message'] = "Kata sandi baru harus minimal 6 karakter.";
            header('Location: profile_settings.php');
            exit();
        }

        if ($new_password !== $confirm_new_password) {
            $_SESSION['form_message'] = "Konfirmasi kata sandi baru tidak cocok.";
            header('Location: profile_settings.php');
            exit();
        }

        // 4. Hash kata sandi baru
        // Gunakan password_hash() untuk membuat hash kata sandi baru yang aman
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

        // 5. Perbarui kata sandi di database
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->execute([$hashed_new_password, $current_user_id]);

        $_SESSION['form_message'] = "Kata sandi berhasil diubah.";
        $_SESSION['form_message_type'] = 'success';

    } catch (PDOException $e) {
        // Catat kesalahan ke log server (penting untuk debugging, jangan tampilkan ke pengguna)
        error_log("Error updating password: " . $e->getMessage());
        $_SESSION['form_message'] = "Terjadi kesalahan saat mengubah kata sandi Anda. Silakan coba lagi.";
        $_SESSION['form_message_type'] = 'error';
    }
} else {
    // Jika bukan permintaan POST, alihkan kembali ke halaman pengaturan profil
    $_SESSION['form_message'] = "Akses tidak sah.";
    $_SESSION['form_message_type'] = 'error';
}

// Alihkan kembali ke halaman pengaturan profil tanpa parameter URL
header('Location: profile_settings.php');
exit();
?>
