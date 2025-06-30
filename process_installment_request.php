<?php
// process_installment_request.php
// Skrip backend untuk memproses pengajuan cicilan tugas oleh pengguna.

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
    error_log("process_installment_request.php: User not logged in. Redirecting to index.php");
    $_SESSION['flash_message'] = 'Anda harus login untuk mengajukan cicilan.';
    $_SESSION['flash_message_type'] = 'error';
    header('Location: index.php');
    exit();
}

// Pastikan request adalah POST dan product_id serta original_product_price diset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id']) && isset($_POST['original_product_price'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $original_product_price = filter_input(INPUT_POST, 'original_product_price', FILTER_VALIDATE_FLOAT);

    if (!$product_id || $original_product_price === false || $original_product_price <= 0) {
        error_log("process_installment_request.php: Invalid product ID or price received. Product ID: " . ($_POST['product_id'] ?? 'NULL') . ", Price: " . ($_POST['original_product_price'] ?? 'NULL'));
        $_SESSION['flash_message'] = 'Detail tugas tidak valid untuk pengajuan cicilan.';
        $_SESSION['flash_message_type'] = 'error';
        header('Location: installments.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Ambil detail produk tambahan (komisi, poin)
        $stmt_product = $pdo->prepare("SELECT commission_percentage, points_awarded FROM products WHERE id = ?");
        $stmt_product->execute([$product_id]);
        $product_details = $stmt_product->fetch();

        if (!$product_details) {
            error_log("process_installment_request.php: Product ID " . $product_id . " not found in products table.");
            $_SESSION['flash_message'] = 'Produk tidak ditemukan.';
            $_SESSION['flash_message_type'] = 'error';
            $pdo->rollBack();
            header('Location: installments.php');
            exit();
        }

        $commission_percentage = $product_details['commission_percentage'];
        $points_awarded = $product_details['points_awarded'];

        // Cek apakah user sudah mengajukan cicilan untuk produk ini (pending/approved)
        $stmt_check_existing = $pdo->prepare("SELECT COUNT(*) FROM installments WHERE user_id = ? AND product_id = ? AND (status = 'pending' OR status = 'approved')");
        $stmt_check_existing->execute([$user_id, $product_id]);
        if ($stmt_check_existing->fetchColumn() > 0) {
            error_log("process_installment_request.php: User " . $user_id . " already has a pending or approved installment for product " . $product_id);
            $_SESSION['flash_message'] = 'Anda sudah mengajukan cicilan untuk tugas ini atau cicilan Anda sedang diproses.';
            $_SESSION['flash_message_type'] = 'error';
            $pdo->rollBack();
            header('Location: installments.php');
            exit();
        }

        // --- PERUBAHAN UTAMA: TIDAK ADA LAGI PEMOTONGAN SALDO DI AWAL ---
        // Saldo tidak lagi diperiksa atau dipotong di sini.
        // Cukup catat pengajuan cicilan.

        // Masukkan cicilan baru ke tabel 'installments' dengan status 'pending'
        // remaining_amount diatur ke original_product_price
        $stmt_insert_installment = $pdo->prepare("INSERT INTO installments (user_id, product_id, original_product_price, remaining_amount, commission_percentage, points_awarded, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        if ($stmt_insert_installment->execute([$user_id, $product_id, $original_product_price, $original_product_price, $commission_percentage, $points_awarded])) {
            $pdo->commit();
            error_log("process_installment_request.php: New installment for product " . $product_id . " successfully created for user " . $user_id . ". No upfront balance deduction.");
            $_SESSION['flash_message'] = 'Pengajuan cicilan Anda berhasil diajukan dan menunggu persetujuan admin! Saldo Anda tidak dipotong di muka.';
            $_SESSION['flash_message_type'] = 'success';
            header('Location: installments.php');
            exit();
        } else {
            throw new Exception("Gagal memasukkan cicilan ke database.");
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("process_installment_request.php: Database transaction rolled back due to PDOException: " . $e->getMessage());
        }
        $_SESSION['flash_message'] = 'Terjadi kesalahan database saat memproses pengajuan cicilan: ' . $e->getMessage();
        $_SESSION['flash_message_type'] = 'error';
        header('Location: installments.php');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("process_installment_request.php: General error processing installment request: " . $e->getMessage());
        }
        $_SESSION['flash_message'] = 'Terjadi kesalahan saat memproses pengajuan cicilan: ' . $e->getMessage();
        $_SESSION['flash_message_type'] = 'error';
        header('Location: installments.php');
        exit();
    }
} else {
    // Jika tidak ada POST request yang valid atau data yang diperlukan tidak diset
    error_log("process_installment_request.php: Invalid request method or missing POST data.");
    $_SESSION['flash_message'] = 'Permintaan pengajuan cicilan tidak valid.';
    $_SESSION['flash_message_type'] = 'error';
    header('Location: installments.php');
    exit();
}
