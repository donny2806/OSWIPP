<?php
// process_profile_update.php
// Skrip ini memproses pembaruan data profil pengguna dari dashboard.

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
$message = '';
$message_type = '';

// Direktori untuk menyimpan gambar profil (path sistem file)
$uploadDir = __DIR__ . '/uploads/profile_pictures/';

// Pastikan direktori ada
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Izin 0777 untuk demo, sesuaikan di produksi
}

// Fungsi untuk mengelola unggahan gambar profil
function handleProfilePictureUpload($file_data, $upload_dir, $current_profile_picture_url = null) {
    if (!isset($file_data) || !is_array($file_data) || !isset($file_data['error'])) {
        return null; // Tidak ada file atau data tidak valid, kembalikan null atau URL saat ini
    }

    if ($file_data['error'] === UPLOAD_ERR_NO_FILE) {
        return $current_profile_picture_url; // Tidak ada file baru, gunakan yang lama
    }

    if ($file_data['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Terjadi kesalahan saat mengunggah gambar. Kode error: ' . $file_data['error']);
    }
    
    $fileName = $file_data['name'];
    $fileTmpName = $file_data['tmp_name'];
    $fileSize = $file_data['size'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $maxFileSize = 2 * 1024 * 1024; // 2 MB

    if (!in_array($fileExt, $allowedExtensions)) {
        throw new Exception('Tipe file gambar profil tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.');
    }
    if ($fileSize > $maxFileSize) {
        throw new Exception('Ukuran file gambar profil terlalu besar. Maksimal 2MB.');
    }

    $newFileName = uniqid('profile_', true) . '.' . $fileExt;
    $fileDestination = $upload_dir . $newFileName;

    if (!is_writable($upload_dir)) {
        throw new Exception("Direktori tujuan unggahan tidak dapat ditulisi. Periksa izin folder.");
    }

    if (move_uploaded_file($fileTmpName, $fileDestination)) {
        // Hapus gambar lama jika ada dan bukan gambar default atau gambar yang baru saja diunggah
        if ($current_profile_picture_url && !empty($current_profile_picture_url) && 
            strpos($current_profile_picture_url, 'default.png') === false && 
            strpos($current_profile_picture_url, 'http') === false) { // Hindari menghapus default.png atau eksternal URL
            $old_image_path = __DIR__ . '/' . $current_profile_picture_url; 
            if (file_exists($old_image_path) && ($old_image_path !== $fileDestination)) {
                unlink($old_image_path);
            }
        }
        return 'uploads/profile_pictures/' . $newFileName; // URL relatif
    } else {
        throw new Exception('Gagal memindahkan file gambar profil yang diunggah.');
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $phone_number = trim($_POST['phone_number']);
    $nationality = trim($_POST['nationality']);
    $current_profile_picture_url = $_POST['current_profile_picture_url'] ?? null;

    try {
        $pdo->beginTransaction();

        // 1. Dapatkan URL gambar profil saat ini dari database
        $stmt_get_current_pic = $pdo->prepare("SELECT profile_picture_url FROM users WHERE id = ?");
        $stmt_get_current_pic->execute([$current_user_id]);
        $db_current_profile_pic_url = $stmt_get_current_pic->fetchColumn();

        // Setel URL awal untuk pembaruan
        $new_profile_picture_url = $db_current_profile_pic_url;

        // 2. Tangani unggahan gambar baru
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            $new_profile_picture_url = handleProfilePictureUpload($_FILES['profile_picture'], $uploadDir, $db_current_profile_pic_url);
        } elseif (isset($_POST['remove_profile_picture']) && $_POST['remove_profile_picture'] == 'true') {
            // Jika checkbox "Hapus Gambar" dicentang dan bukan gambar default
            if ($db_current_profile_pic_url && strpos($db_current_profile_pic_url, 'default.png') === false && strpos($db_current_profile_pic_url, 'http') === false) {
                $old_image_path = __DIR__ . '/' . $db_current_profile_pic_url;
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            $new_profile_picture_url = 'uploads/profile_pictures/default.png'; // Kembali ke gambar default
        }
        // Jika tidak ada gambar baru diunggah dan "Hapus Gambar" tidak dicentang, 
        // maka $new_profile_picture_url akan tetap sama dengan $db_current_profile_pic_url.

        // 3. Periksa duplikasi username atau email (kecuali untuk user yang sedang diedit)
        $stmt_check_duplicate = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt_check_duplicate->execute([$username, $email, $current_user_id]);
        if ($stmt_check_duplicate->fetchColumn() > 0) {
            $message = 'Username atau email sudah digunakan oleh pengguna lain.';
            $message_type = 'error';
            $pdo->rollBack();
        } else {
            // 4. Update data pengguna
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, address = ?, phone_number = ?, nationality = ?, profile_picture_url = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $username, $email, $address, $phone_number, $nationality, $new_profile_picture_url, $current_user_id])) {
                // Perbarui sesi dengan username yang baru jika diubah
                $_SESSION['username'] = $username;
                $message = "Profil berhasil diperbarui.";
                $message_type = 'success';
                $pdo->commit();
            } else {
                $message = "Gagal memperbarui profil. Silakan coba lagi.";
                $message_type = 'error';
                $pdo->rollBack();
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Redirect kembali ke dashboard dengan pesan
header("Location: dashboard.php?message=" . urlencode($message) . "&type=" . urlencode($message_type));
exit();
?>
