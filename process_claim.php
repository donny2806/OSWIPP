<?php
// process_claim.php
// Skrip backend untuk memproses pengajuan klaim tugas oleh pengguna.

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
    // Tambahkan error_log sebelum redirect untuk debugging
    error_log("process_claim.php: User not logged in. Redirecting to index.php");
    header('Location: index.php');
    exit();
}

// Dapatkan parameter URL saat ini untuk redirect kembali ke halaman yang sama
// Ini akan mempertahankan halaman paginasi dan query pencarian.
$redirect_params = [];
if (isset($_POST['current_page'])) {
    $redirect_params['page'] = filter_var($_POST['current_page'], FILTER_VALIDATE_INT);
}
if (isset($_POST['search_query'])) {
    $redirect_params['search'] = $_POST['search_query'];
}
$redirect_url_query = http_build_query($redirect_params);
$redirect_base_url = 'claims.php'; // Tujuan redirect adalah claims.php
$final_redirect_location = empty($redirect_url_query) ? $redirect_base_url : $redirect_base_url . '?' . $redirect_url_query;


// Pastikan request adalah POST dan product_id diset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

    if (!$product_id) {
        error_log("process_claim.php: Invalid product ID received: " . ($_POST['product_id'] ?? 'NULL'));
        $_SESSION['flash_message'] = 'Produk tidak valid.';
        $_SESSION['flash_message_type'] = 'error';
        header('Location: ' . $final_redirect_location);
        exit();
    }

    try {
        // --- VALIDASI PENTING: CEK KLAIM GANDA (GLOBAL) ---
        // Periksa apakah produk sudah memiliki klaim pending atau approved oleh user manapun
        $stmt_check_global_claim = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE product_id = ? AND (status = 'pending' OR status = 'approved')");
        $stmt_check_global_claim->execute([$product_id]);
        $existing_global_claims_count = $stmt_check_global_claim->fetchColumn();

        if ($existing_global_claims_count > 0) {
            error_log("process_claim.php: Product " . $product_id . " already has a pending/approved claim by another user or by this user.");
            $_SESSION['flash_message'] = 'Tugas ini sudah diklaim oleh pengguna lain atau sedang diproses. Produk tidak lagi tersedia.';
            $_SESSION['flash_message_type'] = 'error';
            header('Location: ' . $final_redirect_location);
            exit();
        }
        // --- AKHIR VALIDASI KLAIM GANDA (GLOBAL) ---

        // Ambil detail produk
        $stmt_product = $pdo->prepare("SELECT product_price, commission_percentage, points_awarded FROM products WHERE id = ?");
        $stmt_product->execute([$product_id]);
        $product = $stmt_product->fetch();

        if (!$product) {
            error_log("process_claim.php: Product ID " . $product_id . " not found.");
            $_SESSION['flash_message'] = 'Produk tidak ditemukan.';
            $_SESSION['flash_message_type'] = 'error';
            header('Location: ' . $final_redirect_location);
            exit();
        }

        // Ambil saldo pengguna
        $stmt_user_balance = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt_user_balance->execute([$user_id]);
        $user_balance = $stmt_user_balance->fetchColumn();

        // Periksa apakah saldo mencukupi
        if ($user_balance < $product['product_price']) {
            error_log("process_claim.php: User " . $user_id . " has insufficient balance (" . $user_balance . ") for product " . $product_id . " price (" . $product['product_price'] . ")");
            $_SESSION['flash_message'] = 'Saldo Anda tidak mencukupi untuk mengklaim tugas ini. Harap deposit saldo.';
            $_SESSION['flash_message_type'] = 'error';
            header('Location: ' . $final_redirect_location);
            exit();
        }

        // Mulai transaksi database untuk memastikan konsistensi data
        $pdo->beginTransaction();
        error_log("process_claim.php: Database transaction started for user " . $user_id . ", product " . $product_id);

        // Kurangi saldo pengguna
        $new_balance = $user_balance - $product['product_price'];
        $stmt_update_balance = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt_update_balance->execute([$new_balance, $user_id]);
        error_log("process_claim.php: User " . $user_id . " balance updated to " . $new_balance);

        // Hitung jumlah klaim (harga produk)
        $claim_amount = $product['product_price']; 
        $commission_percentage = $product['commission_percentage'];
        $points_awarded = $product['points_awarded'];

        // Masukkan klaim ke tabel 'claims' dengan status 'pending'
        $stmt_claim = $pdo->prepare("INSERT INTO claims (user_id, product_id, claim_amount, commission_percentage, points_awarded, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt_claim->execute([$user_id, $product_id, $claim_amount, $commission_percentage, $points_awarded]);

        // Commit transaksi jika semua operasi berhasil
        $pdo->commit();
        error_log("process_claim.php: Claim inserted for user " . $user_id . ", product " . $product_id . " with amount " . $claim_amount);
        error_log("process_claim.php: Database transaction committed successfully.");

        // Redirect kembali ke claims.php dengan pesan sukses dan mempertahankan parameter
        $_SESSION['flash_message'] = 'Klaim tugas berhasil diajukan dan menunggu persetujuan admin!';
        $_SESSION['flash_message_type'] = 'success';
        header('Location: ' . $final_redirect_location);
        exit();

    } catch (PDOException $e) {
        // Rollback transaksi jika terjadi kesalahan
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("process_claim.php: Database transaction rolled back due to PDOException.");
        }
        error_log("process_claim.php: Error processing claim (PDOException): " . $e->getMessage());
        $_SESSION['flash_message'] = 'Terjadi kesalahan database saat memproses klaim.';
        $_SESSION['flash_message_type'] = 'error';
        header('Location: ' . $final_redirect_location);
        exit();
    } catch (Exception $e) {
        // Tangani exception lainnya
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("process_claim.php: Database transaction rolled back due to General Exception.");
        }
        error_log("process_claim.php: General error processing claim: " . $e->getMessage());
        $_SESSION['flash_message'] = 'Terjadi kesalahan saat memproses klaim: ' . htmlspecialchars($e->getMessage());
        $_SESSION['flash_message_type'] = 'error';
        header('Location: ' . $final_redirect_location);
        exit();
    }
} else {
    // Jika tidak ada POST request atau product_id tidak diset, arahkan kembali ke claims.php
    error_log("process_claim.php: Invalid request method or product_id not set.");
    $_SESSION['flash_message'] = 'Permintaan klaim tidak valid.';
    $_SESSION['flash_message_type'] = 'error';
    header('Location: ' . $final_redirect_location);
    exit();
}
?>
