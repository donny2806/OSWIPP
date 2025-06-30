<?php
// process_installment_payment.php
// Skrip backend untuk memproses pembayaran cicilan tugas oleh pengguna.

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
    error_log("process_installment_payment.php: User not logged in. Redirecting to index.php");
    $_SESSION['flash_message'] = 'Anda harus login untuk melakukan pembayaran cicilan.';
    $_SESSION['flash_message_type'] = 'error';
    header('Location: index.php');
    exit();
}

// Pastikan request adalah POST dan data yang diperlukan diset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['installment_id']) && isset($_POST['payment_amount'])) {
    $user_id = $_SESSION['user_id'];
    $installment_id = filter_input(INPUT_POST, 'installment_id', FILTER_VALIDATE_INT);
    $payment_amount = filter_input(INPUT_POST, 'payment_amount', FILTER_VALIDATE_FLOAT);

    if (!$installment_id || $payment_amount === false || $payment_amount <= 0) {
        error_log("process_installment_payment.php: Invalid installment ID or payment amount received. Installment ID: " . ($_POST['installment_id'] ?? 'NULL') . ", Amount: " . ($_POST['payment_amount'] ?? 'NULL'));
        $_SESSION['flash_message'] = 'Detail pembayaran cicilan tidak valid.';
        $_SESSION['flash_message_type'] = 'error';
        header('Location: installments.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Ambil detail cicilan dan saldo pengguna dengan lock untuk menghindari race condition
        $stmt = $pdo->prepare("
            SELECT 
                i.id, i.user_id, i.product_id, i.original_product_price, i.remaining_amount, 
                i.commission_percentage, i.points_awarded, i.status,
                u.balance, u.points, p.name AS product_name
            FROM installments i
            JOIN users u ON i.user_id = u.id
            JOIN products p ON i.product_id = p.id
            WHERE i.id = ? AND i.user_id = ? FOR UPDATE
        ");
        $stmt->execute([$installment_id, $user_id]);
        $installment_data = $stmt->fetch();

        if (!$installment_data) {
            error_log("process_installment_payment.php: Installment ID " . $installment_id . " not found for user " . $user_id . " or not authorized.");
            $_SESSION['flash_message'] = 'Cicilan tidak ditemukan atau Anda tidak berhak membayarnya.';
            $_SESSION['flash_message_type'] = 'error';
            $pdo->rollBack();
            header('Location: installments.php');
            exit();
        }

        if ($installment_data['status'] !== 'approved' && $installment_data['status'] !== 'pending') { // Izinkan pending jika belum ada admin approval
            // Asumsi setelah admin approve baru bisa bayar, tapi jika belum ada admin page
            // maka pending juga bisa dibayar untuk kebutuhan testing atau alur sederhana
            error_log("process_installment_payment.php: Installment ID " . $installment_id . " status is " . $installment_data['status'] . ", not 'approved' or 'pending'.");
            $_SESSION['flash_message'] = 'Cicilan ini tidak dalam status yang bisa dibayar.';
            $_SESSION['flash_message_type'] = 'error';
            $pdo->rollBack();
            header('Location: installments.php');
            exit();
        }

        if ($installment_data['remaining_amount'] <= 0) {
            error_log("process_installment_payment.php: Installment ID " . $installment_id . " already fully paid.");
            $_SESSION['flash_message'] = 'Cicilan ini sudah lunas sepenuhnya.';
            $_SESSION['flash_message_type'] = 'error';
            $pdo->rollBack();
            header('Location: installments.php');
            exit();
        }

        if ($payment_amount > $installment_data['remaining_amount']) {
            $payment_amount = $installment_data['remaining_amount']; // Jangan bayar lebih dari sisa
            error_log("process_installment_payment.php: Payment amount adjusted to remaining amount for installment " . $installment_id);
        }

        $current_user_balance = $installment_data['balance'];
        if ($current_user_balance < $payment_amount) {
            error_log("process_installment_payment.php: User " . $user_id . " has insufficient balance (" . $current_user_balance . ") for payment amount (" . $payment_amount . ") for installment " . $installment_id);
            $_SESSION['flash_message'] = 'Saldo Anda tidak mencukupi untuk pembayaran ini. Saldo Anda: Rp ' . number_format($current_user_balance, 2, ',', '.') . '.';
            $_SESSION['flash_message_type'] = 'error';
            $pdo->rollBack();
            header('Location: installments.php');
            exit();
        }

        // Kurangi saldo pengguna
        $new_user_balance = $current_user_balance - $payment_amount;
        $stmt_update_user_balance = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt_update_user_balance->execute([$new_user_balance, $user_id]);
        error_log("process_installment_payment.php: User " . $user_id . " balance updated to " . $new_user_balance . " after payment deduction.");

        // Kurangi sisa pembayaran cicilan
        $new_remaining_amount = $installment_data['remaining_amount'] - $payment_amount;
        $stmt_update_installment = $pdo->prepare("UPDATE installments SET remaining_amount = ? WHERE id = ?");
        $stmt_update_installment->execute([$new_remaining_amount, $installment_id]);
        error_log("process_installment_payment.php: Installment " . $installment_id . " remaining amount updated to " . $new_remaining_amount);

        // Catat transaksi pembayaran (withdraw dari saldo user)
        $description = "Pembayaran cicilan ID " . $installment_id . " untuk tugas '" . $installment_data['product_name'] . "'";
        $stmt_insert_transaction = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, description) VALUES (?, 'withdraw', ?, 'completed', ?)");
        $stmt_insert_transaction->execute([$user_id, $payment_amount, $description]);
        error_log("process_installment_payment.php: Transaction recorded for installment payment of " . $payment_amount);

        // Jika cicilan lunas sepenuhnya (sisa pembayaran menjadi 0 atau kurang)
        if ($new_remaining_amount <= 0) {
            $stmt_update_installment_status = $pdo->prepare("UPDATE installments SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt_update_installment_status->execute([$installment_id]);
            error_log("process_installment_payment.php: Installment " . $installment_id . " marked as completed.");

            // LUNAS: Saldo masuk ke user sesuai dengan harga tugas yang dicicil + komisi + poin
            $original_price = $installment_data['original_product_price'];
            $commission_percentage = $installment_data['commission_percentage'];
            $points_awarded = $installment_data['points_awarded'];
            
            $commission_amount = $original_price * $commission_percentage;
            $total_payout = $original_price + $commission_amount; // Uang yang masuk ke user

            error_log("process_installment_payment.php Debug Payout: original_price=" . $original_price . ", commission_percentage=" . $commission_percentage . ", commission_amount=" . $commission_amount . ", points_awarded=" . $points_awarded . ", total_payout=" . $total_payout);


            $current_user_points = $installment_data['points'];
            $final_user_balance_after_payout = $new_user_balance + $total_payout;
            $new_user_points = $current_user_points + $points_awarded;
            
            error_log("process_installment_payment.php Debug User Update: old_user_balance (after payment deduction)=" . $new_user_balance . ", new_user_balance (after payout)=" . $final_user_balance_after_payout . ", old_user_points=" . $current_user_points . ", new_user_points=" . $new_user_points);

            // Tambahkan payout ke saldo pengguna
            $stmt_final_payout = $pdo->prepare("UPDATE users SET balance = ?, points = ? WHERE id = ?");
            $stmt_final_payout->execute([$final_user_balance_after_payout, $new_user_points, $user_id]);
            error_log("process_installment_payment.php: User " . $user_id . " received final payout of " . $total_payout . " and " . $points_awarded . " points for completed installment " . $installment_id);

            // Catat payout sebagai transaksi "deposit" ke user
            $payout_description = "Penyelesaian cicilan tugas '" . $installment_data['product_name'] . "' (Harga Asli + Komisi)";
            $stmt_insert_payout_transaction = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, description) VALUES (?, 'deposit', ?, 'completed', ?)");
            $stmt_insert_payout_transaction->execute([$user_id, $total_payout, $payout_description]);
            error_log("process_installment_payment.php: Payout transaction recorded for user " . $user_id . " with amount " . $total_payout);

            // Pesan notifikasi yang diminta pengguna
            $_SESSION['flash_message'] = 'Cicilan ' . $installment_data['product_name'] . ' Anda sudah lunas, komisi sudah ditambahkan ke saldo Anda !';
            $_SESSION['flash_message_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Pembayaran cicilan sebesar Rp ' . number_format($payment_amount, 2, ',', '.') . ' berhasil! Sisa pembayaran: Rp ' . number_format($new_remaining_amount, 2, ',', '.') . '.';
            $_SESSION['flash_message_type'] = 'success';
        }

        $pdo->commit();
        header('Location: installments.php');
        exit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("process_installment_payment.php: Database transaction rolled back due to PDOException: " . $e->getMessage());
        }
        $_SESSION['flash_message'] = 'Terjadi kesalahan database saat memproses pembayaran cicilan: ' . $e->getMessage();
        $_SESSION['flash_message_type'] = 'error';
        header('Location: installments.php');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("process_installment_payment.php: General error processing installment payment: " . $e->getMessage());
        }
        $_SESSION['flash_message'] = 'Terjadi kesalahan saat memproses pembayaran cicilan: ' . $e->getMessage();
        $_SESSION['flash_message_type'] = 'error';
        header('Location: installments.php');
        exit();
    }
} else {
    // Jika tidak ada POST request yang valid atau data yang diperlukan tidak diset
    error_log("process_installment_payment.php: Invalid request method or missing POST data.");
    $_SESSION['flash_message'] = 'Permintaan pembayaran cicilan tidak valid.';
    $_SESSION['flash_message_type'] = 'error';
    header('Location: installments.php');
    exit();
}
