<?php
// admin_get_summary_data.php
// Endpoint ini menyediakan data ringkasan sistem dalam format JSON untuk dashboard admin.

// Aktifkan pelaporan kesalahan PHP secara penuh untuk debugging
// PENTING: NONAKTIFKAN ini di lingkungan produksi!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
// Pastikan file ini TIDAK mengeluarkan output apapun (spasi, baris baru, dll.)
require_once __DIR__ . '/db_connect.php';

// Atur header untuk respons JSON
header('Content-Type: application/json');

// Inisialisasi data default. Jika ada masalah, ini yang akan dikembalikan,
// bersama dengan informasi error jika terjadi.
$summary_data = [
    'total_users' => 0,
    'pending_deposits' => 0,
    'pending_withdrawals' => 0,
    'pending_claims' => 0,
    'pending_installments' => 0,
    'incoming_messages' => 0,
    'total_installments' => 0, // Variabel untuk jumlah user dengan cicilan belum lunas
    'error' => null,   // Akan berisi deskripsi error jika ada
    'message' => null  // Akan berisi pesan detail error jika ada
];

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    $summary_data['error'] = 'Unauthorized';
    $summary_data['message'] = 'User not logged in. Sesi tidak ditemukan atau kadaluarsa.';
    echo json_encode($summary_data); // Kirim respons error
    exit();
}

$current_user_id = $_SESSION['user_id'];
$is_admin = false;

try {
    // Verifikasi bahwa pengguna yang login adalah admin
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();

    if ($user_data && $user_data['is_admin']) {
        $is_admin = true;
    } else {
        // Jika bukan admin, tolak akses
        $summary_data['error'] = 'Forbidden';
        $summary_data['message'] = 'Akses ditolak. Pengguna ID ' . $current_user_id . ' bukan admin.';
        echo json_encode($summary_data); // Kirim respons error
        exit();
    }
} catch (PDOException $e) {
    // Tangani kesalahan database saat memverifikasi status admin
    error_log("Error checking admin status in admin_get_summary_data.php: " . $e->getMessage());
    $summary_data['error'] = 'Database Error';
    $summary_data['message'] = 'Terjadi kesalahan saat memverifikasi status admin: ' . $e->getMessage();
    echo json_encode($summary_data); // Kirim respons error
    exit();
}

// Jika sudah lolos verifikasi admin, ambil data ringkasan
if ($is_admin) {
    try {
        // Total Pengguna
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM users");
        $summary_data['total_users'] = $stmt->fetch()['total'];

        // Deposit Tertunda
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM transactions WHERE type = 'deposit' AND status = 'pending'");
        $summary_data['pending_deposits'] = $stmt->fetch()['total'];

        // Penarikan Tertunda
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM transactions WHERE type = 'withdraw' AND status = 'pending'");
        $summary_data['pending_withdrawals'] = $stmt->fetch()['total'];

        // Klaim Tugas Tertunda
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM claims WHERE status = 'pending'");
        $summary_data['pending_claims'] = $stmt->fetch()['total'];

        // Klaim Cicil Tertunda
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM installments WHERE status = 'pending'");
        $summary_data['pending_installments'] = $stmt->fetch()['total'];

        // Pesan Masuk (belum dibaca)
        $stmt = $pdo->prepare("
            SELECT COUNT(c.id) AS total_unread
            FROM chats c
            JOIN users u ON c.sender_id = u.id
            WHERE u.is_admin = FALSE 
              AND c.is_read_by_admin = FALSE 
              AND (c.receiver_id = :current_admin_id OR c.receiver_id IS NULL)
        ");
        $stmt->bindParam(':current_admin_id', $current_user_id, PDO::PARAM_INT);
        $stmt->execute();
        $summary_data['incoming_messages'] = $stmt->fetch()['total_unread'];

        // Hitung jumlah user yang masih memiliki sisa pembayaran cicilan yang disetujui
        // Ini akan digunakan untuk "Jumlah User Belum Lunas (Disetujui)" di dashboard
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT user_id) AS total
            FROM installments
            WHERE status = 'approved' AND remaining_amount > 0
        ");
        $summary_data['total_installments'] = $stmt->fetch()['total'];

    } catch (PDOException $e) {
        // Tangani kesalahan saat mengambil data ringkasan
        error_log("Error fetching admin dashboard summary data: " . $e->getMessage());
        $summary_data['error'] = 'Database Query Error';
        $summary_data['message'] = 'Gagal mengambil data ringkasan: ' . $e->getMessage();
        // Data ringkasan akan tetap 0 jika terjadi error query di sini
    }
}

// Kembalikan data dalam format JSON
echo json_encode($summary_data);

exit(); // Pastikan tidak ada output lain setelah JSON
?>
