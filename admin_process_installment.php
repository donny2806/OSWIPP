<?php
// admin_process_installment.php
// Skrip backend untuk admin menyetujui atau menolak pengajuan cicilan tugas.

// AKTIFKAN SEMUA PELAPORAN ERROR UNTUK DEBUGGING (HANYA DI LINGKUNGAN PENGEMBANGAN!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai sesi PHP jika belum dimulai.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html'); // Set header ke text/html agar error PHP bisa tampil di browser

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Periksa apakah admin sudah login
if (!isset($_SESSION['user_id'])) {
    error_log("admin_process_installment.php: User not logged in. Redirecting to index.php");
    header('Location: index.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$is_admin = false;
try {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();
    if ($user_data && $user_data['is_admin']) {
        $is_admin = true;
    } else {
        error_log("admin_process_installment.php: Non-admin user attempted to access.");
        header('Location: dashboard.php'); // Arahkan ke dashboard biasa jika bukan admin
        exit();
    }
} catch (PDOException $e) {
    error_log("admin_process_installment.php: Error checking admin status: " . $e->getMessage());
    header('Location: index.php'); // Redirect ke login jika ada masalah otentikasi
    exit();
}

$message = '';
$message_type = '';

// Pastikan request adalah POST dan semua data yang diperlukan diset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['installment_id'])) {
    $action = $_POST['action']; // 'approve' atau 'reject'
    $installment_id = filter_input(INPUT_POST, 'installment_id', FILTER_VALIDATE_INT);

    if (!$installment_id) {
        $message = 'ID cicilan tidak valid.';
        $message_type = 'error';
        header('Location: admin_manage_installments.php?message=' . urlencode($message) . '&type=' . $message_type);
        exit();
    }

    try {
        $pdo->beginTransaction();
        error_log("admin_process_installment.php: Database transaction started for installment ID " . $installment_id);

        // Ambil detail cicilan dengan FOR UPDATE untuk mencegah race condition
        $stmt_fetch_installment = $pdo->prepare("SELECT id, user_id, product_id, original_product_price, remaining_amount, status FROM installments WHERE id = ? FOR UPDATE");
        $stmt_fetch_installment->execute([$installment_id]);
        $installment = $stmt_fetch_installment->fetch();

        if (!$installment) {
            $message = 'Pengajuan cicilan tidak ditemukan.';
            $message_type = 'error';
            $pdo->rollBack();
            header('Location: admin_manage_installments.php?message=' . urlencode($message) . '&type=' . $message_type);
            exit();
        }

        if ($installment['status'] !== 'pending') {
            $message = 'Pengajuan cicilan ini sudah diproses.';
            $message_type = 'error';
            $pdo->rollBack();
            header('Location: admin_manage_installments.php?message=' . urlencode($message) . '&type=' . $message_type);
            exit();
        }

        if ($action === 'approve') {
            // Set status menjadi 'approved'
            $stmt_update_status = $pdo->prepare("UPDATE installments SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt_update_status->execute([$current_user_id, $installment_id]);

            // Pada persetujuan, remaining_amount akan tetap original_product_price (belum ada pembayaran)
            // User akan melihat ini dan harus melunasi.

            $message = "Pengajuan cicilan ID " . $installment_id . " berhasil disetujui.";
            $message_type = 'success';

        } elseif ($action === 'reject') {
            // Set status menjadi 'rejected'
            $stmt_update_status = $pdo->prepare("UPDATE installments SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt_update_status->execute([$current_user_id, $installment_id]);

            $message = "Pengajuan cicilan ID " . $installment_id . " berhasil ditolak.";
            $message_type = 'success';
        } else {
            $message = 'Aksi tidak valid.';
            $message_type = 'error';
        }

        $pdo->commit(); // Commit transaksi jika semua operasi berhasil
        error_log("admin_process_installment.php: Action '" . $action . "' for installment ID " . $installment_id . " committed successfully.");
        header('Location: admin_manage_installments.php?message=' . urlencode($message) . '&type=' . $message_type);
        exit();

    } catch (PDOException $e) {
        // Rollback transaksi jika terjadi kesalahan
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("admin_process_installment.php: Database transaction rolled back due to PDOException: " . $e->getMessage());
        }
        error_log("admin_process_installment.php: Error processing installment action (PDOException): " . $e->getMessage());
        header('Location: admin_manage_installments.php?message=Terjadi kesalahan database saat memproses cicilan.&type=error');
        exit();
    } catch (Exception $e) {
        // Tangani exception lainnya
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("admin_process_installment.php: General error processing installment action: " . $e->getMessage());
        }
        error_log("admin_process_installment.php: General error processing installment action: " . $e->getMessage());
        header('Location: admin_manage_installments.php?message=Terjadi kesalahan saat memproses cicilan: ' . htmlspecialchars($e->getMessage()) . '&type=error');
        exit();
    }
} else {
    // Jika tidak ada POST request yang valid, atau data yang diperlukan tidak diset
    error_log("admin_process_installment.php: Invalid request method or missing POST data.");
    header('Location: admin_manage_installments.php?message=Permintaan tidak valid.&type=error');
    exit();
}
?>
