<?php
// api/upload_image.php
// Endpoint API ini mengelola unggahan gambar untuk fitur chat.

// Mulai sesi PHP jika belum dimulai. Penting untuk mengidentifikasi pengguna.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json'); // Pastikan respons dalam format JSON

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login untuk mengunggah gambar.']);
    exit();
}

// Pastikan ada file yang diunggah
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'Tidak ada file gambar yang diunggah atau terjadi kesalahan unggah.';
    if (isset($_FILES['image']['error'])) {
        switch ($_FILES['image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'Ukuran file melebihi batas yang diizinkan.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'File hanya sebagian terunggah.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = 'Tidak ada file yang dipilih untuk diunggah.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = 'Direktori sementara tidak ditemukan.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = 'Gagal menulis file ke disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = 'Ekstensi PHP menghentikan unggahan file.';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit();
}

$file = $_FILES['image'];
$fileName = $file['name'];
$fileTmpName = $file['tmp_name'];
$fileSize = $file['size'];
$fileError = $file['error'];
$fileType = $file['type'];

// Dapatkan ekstensi file
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Tentukan ekstensi yang diizinkan
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

// Folder tujuan untuk menyimpan gambar
$uploadDir = '../uploads/chat_images/';

// Buat folder jika belum ada
if (!is_dir($uploadDir)) {
    // Coba buat direktori dengan izin yang sesuai
    if (!mkdir($uploadDir, 0755, true)) { // Izin 0755 lebih aman dari 0777 di produksi
        echo json_encode(['success' => false, 'message' => 'Gagal membuat direktori unggahan. Periksa izin server.']);
        exit();
    }
}

// Validasi file
if (!in_array($fileExt, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.']);
    exit();
}

if ($fileError !== 0) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat mengunggah file. Kode error: ' . $fileError]);
    exit();
}

// Batasi ukuran file (misal: 5MB)
$maxFileSize = 5 * 1024 * 1024; // 5 MB
if ($fileSize > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB.']);
    exit();
}

// Hasilkan nama file unik
$newFileName = uniqid('', true) . '.' . $fileExt;
$fileDestination = $uploadDir . $newFileName;

// Pindahkan file yang diunggah dari direktori sementara ke direktori tujuan
if (move_uploaded_file($fileTmpName, $fileDestination)) {
    // Berikan URL gambar yang dapat diakses oleh browser
    $image_url = 'uploads/chat_images/' . $newFileName; // URL relatif dari root situs

    echo json_encode(['success' => true, 'message' => 'Gambar berhasil diunggah.', 'image_url' => $image_url]);
} else {
    // Pesan kesalahan lebih spesifik untuk kegagalan move_uploaded_file
    $lastError = error_get_last();
    $errorMessage = 'Gagal memindahkan file yang diunggah. ';
    if ($lastError && isset($lastError['message'])) {
        $errorMessage .= 'Detail: ' . $lastError['message'];
    } else {
        $errorMessage .= 'Kemungkinan masalah izin direktori atau file sementara tidak ada.';
    }
    echo json_encode(['success' => false, 'message' => $errorMessage]);
}
?>
