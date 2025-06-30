<?php
// process_deposit.php
// Skrip backend untuk memproses pengajuan deposit saldo oleh pengguna.

// AKTIFKAN SEMUA PELAPORAN ERROR UNTUK DEBUGGING (HANYA DI LINGKUNGAN PENGEMBANGAN!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai sesi PHP jika belum dimulai. Penting untuk mengidentifikasi pengguna.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html'); // Set header ke text/html agar error PHP bisa tampil di browser

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    error_log("process_deposit.php: User not logged in. Redirecting to index.php");
    header('Location: index.php');
    exit();
}

// Direktori untuk menyimpan gambar resi deposit
$uploadDir = __DIR__ . '/uploads/deposit_receipts/';

// Buat direktori jika belum ada
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) { // Izin 0777 untuk demo, sesuaikan di produksi
        $message = "Gagal membuat direktori unggahan resi: " . htmlspecialchars($uploadDir) . ". Periksa izin folder.";
        error_log("Failed to create upload directory for receipts: " . $uploadDir);
        header('Location: deposit.php?message=' . urlencode($message) . '&type=error');
        exit();
    } else {
        error_log("Upload directory for receipts created successfully: " . $uploadDir);
    }
}

// Fungsi untuk mengelola unggah gambar resi
function handleReceiptImageUpload($file_data, $upload_dir) {
    error_log("handleReceiptImageUpload called. File Data: " . print_r($file_data, true));
    error_log("Upload Dir: " . $upload_dir);

    if (!isset($file_data) || !is_array($file_data) || !isset($file_data['error'])) {
        throw new Exception("Data file unggahan resi tidak valid.");
    }

    if ($file_data['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('Anda harus mengunggah bukti transfer (resi).');
    }

    if ($file_data['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'Terjadi kesalahan PHP saat mengunggah resi. Kode error: ' . $file_data['error'];
        switch ($file_data['error']) {
            case UPLOAD_ERR_INI_SIZE: $errorMessage .= ' (Ukuran file melebihi batas upload_max_filesize di php.ini)'; break;
            case UPLOAD_ERR_FORM_SIZE: $errorMessage .= ' (Ukuran file melebihi batas MAX_FILE_SIZE di form HTML)'; break;
            case UPLOAD_ERR_PARTIAL: $errorMessage .= ' (File hanya terunggah sebagian)'; break;
            case UPLOAD_ERR_NO_TMP_DIR: $errorMessage .= ' (Direktori sementara PHP tidak ditemukan atau tidak dapat ditulisi)'; break;
            case UPLOAD_ERR_CANT_WRITE: $errorMessage .= ' (Gagal menulis file ke disk)'; break;
            case UPLOAD_ERR_EXTENSION: $errorMessage .= ' (Ekstensi PHP menghentikan unggahan file - periksa ekstensi PHP Anda)'; break;
            default: $errorMessage .= ' (Kode error tidak diketahui)'; break;
        }
        error_log("Receipt Upload error (PHP): " . $errorMessage);
        throw new Exception($errorMessage);
    }
    
    if (empty($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name'])) {
        $errorMessage = "File sementara resi tidak ditemukan atau bukan file yang diunggah. tmp_name: " . ($file_data['tmp_name'] ?? 'NULL');
        error_log($errorMessage);
        throw new Exception($errorMessage);
    }

    $fileName = $file_data['name'];
    $fileTmpName = $file_data['tmp_name'];
    $fileSize = $file_data['size'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5 MB

    if (!in_array($fileExt, $allowedExtensions)) {
        error_log("File extension for receipt not allowed: " . $fileExt);
        throw new Exception('Tipe file resi tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.');
    }
    if ($fileSize > $maxFileSize) {
        error_log("File size for receipt too large: " . $fileSize . " bytes.");
        throw new Exception('Ukuran file resi terlalu besar. Maksimal 5MB.');
    }

    $newFileName = uniqid('receipt_', true) . '.' . $fileExt;
    $fileDestination = $upload_dir . $newFileName; 

    if (!is_dir($upload_dir)) {
        error_log("Upload directory for receipts does not exist: " . $upload_dir);
        throw new Exception("Direktori tujuan unggahan resi tidak ada: " . htmlspecialchars($upload_dir));
    }
    if (!is_writable($upload_dir)) {
        error_log("Upload directory for receipts is not writable: " . $upload_dir . " (Check permissions!)");
        throw new Exception("Direktori tujuan unggahan resi tidak dapat ditulisi: " . htmlspecialchars($upload_dir) . ". Periksa izin folder.");
    }

    error_log("Attempting to move uploaded receipt file from " . $fileTmpName . " to " . $fileDestination);
    if (move_uploaded_file($fileTmpName, $fileDestination)) {
        error_log("Receipt file successfully moved to: " . $fileDestination);
        // KEMBALIKAN URL RELATIF UNTUK WEB (misalnya 'uploads/deposit_receipts/namafile.jpg')
        // Ini diasumsikan root dokumen web Anda berada di 'situs_tugas/'
        return 'uploads/deposit_receipts/' . $newFileName; 
    } else {
        $lastError = error_get_last();
        error_log("Failed to move uploaded receipt file. Possible error: " . print_r($lastError, true));
        throw new Exception('Gagal memindahkan file resi yang diunggah. Pastikan izin direktori benar dan tidak ada masalah server.');
    }
}


// Pastikan request adalah POST dan semua data yang diperlukan diset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['amount']) && 
    isset($_POST['user_bank_name']) &&
    isset($_POST['user_account_name']) &&
    isset($_POST['user_account_number']) &&
    isset($_FILES['receipt_image'])
) {
    $user_id = $_SESSION['user_id'];
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $user_bank_name = trim($_POST['user_bank_name']);
    $user_account_name = trim($_POST['user_account_name']);
    $user_account_number = trim($_POST['user_account_number']);

    if ($amount === false || $amount <= 0) {
        error_log("process_deposit.php: Invalid deposit amount received: " . ($_POST['amount'] ?? 'NULL'));
        header('Location: deposit.php?message=Jumlah deposit tidak valid.&type=error');
        exit();
    }
    if (empty($user_bank_name) || empty($user_account_name) || empty($user_account_number)) {
        error_log("process_deposit.php: Missing user bank details.");
        header('Location: deposit.php?message=Semua detail bank pengirim wajib diisi.&type=error');
        exit();
    }

    $receipt_image_url = null;
    try {
        // Panggil fungsi untuk mengunggah gambar resi
        $receipt_image_url = handleReceiptImageUpload($_FILES['receipt_image'], $uploadDir);
    } catch (Exception $e) {
        error_log("process_deposit.php: Error uploading receipt image: " . $e->getMessage());
        header('Location: deposit.php?message=' . urlencode($e->getMessage()) . '&type=error');
        exit();
    }

    try {
        // Mulai transaksi database untuk memastikan konsistensi data
        $pdo->beginTransaction();
        error_log("process_deposit.php: Database transaction started for deposit from user " . $user_id);

        // Masukkan permintaan deposit ke tabel 'transactions' dengan status 'pending'
        $stmt_deposit = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, bank_name, account_number, account_name, receipt_image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_deposit->execute([$user_id, 'deposit', $amount, 'pending', $user_bank_name, $user_account_number, $user_account_name, $receipt_image_url]);

        // Commit transaksi jika semua operasi berhasil
        $pdo->commit();
        error_log("process_deposit.php: Deposit request successfully inserted and transaction committed for user " . $user_id);

        header('Location: deposit.php?message=Pengajuan deposit Anda berhasil! Menunggu persetujuan admin.&type=success');
        exit();

    } catch (PDOException $e) {
        // Rollback transaksi jika terjadi kesalahan
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("process_deposit.php: Database transaction rolled back due to PDOException: " . $e->getMessage());
        }
        error_log("process_deposit.php: Error processing deposit (PDOException): " . $e->getMessage());
        header('Location: deposit.php?message=Terjadi kesalahan database saat memproses deposit.&type=error');
        exit();
    } catch (Exception $e) {
        // Tangani exception lainnya
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("process_deposit.php: General error processing deposit: " . $e->getMessage());
        }
        error_log("process_deposit.php: General error processing deposit: " . $e->getMessage());
        header('Location: deposit.php?message=Terjadi kesalahan saat memproses deposit: ' . htmlspecialchars($e->getMessage()) . '&type=error');
        exit();
    }
} else {
    // Jika tidak ada POST request atau data yang diperlukan tidak diset
    error_log("process_deposit.php: Invalid request method or missing POST data for deposit.");
    header('Location: deposit.php?message=Permintaan deposit tidak valid. Data tidak lengkap.&type=error');
    exit();
}