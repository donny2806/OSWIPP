<?php
// dashboard.php
// Halaman dashboard pengguna setelah login.
// Menampilkan ringkasan saldo, poin, level, profil, tugas tersedia, dan aktivitas transaksi terbaru.

// Aktifkan pelaporan kesalahan untuk membantu debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php'; // Pastikan db_connect.php ada dan tidak ada error di dalamnya

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Arahkan ke halaman login jika belum login
    exit();
}

$current_user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna'; // Ambil username dari sesi

$message = '';
$message_type = ''; // 'success' atau 'error'

// Ambil data pengguna lengkap dan periksa status admin
$user_data = [];
$is_admin_user = false; // Flag untuk status admin
try {
    $stmt = $pdo->prepare("SELECT id, username, email, balance, points, membership_level, is_admin, profile_picture_url, full_name, address, phone_number, nationality FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();
    if (!$user_data) {
        // Jika data pengguna tidak ditemukan, hancurkan sesi dan arahkan ke login
        session_destroy();
        header('Location: index.php');
        exit();
    }
    // Update username di sesi jika ada perubahan atau untuk memastikan konsistensi
    $_SESSION['username'] = $user_data['username'];
    $username = $user_data['username'];
    $is_admin_user = $user_data['is_admin']; // Set flag admin

    // REDIRECT ADMIN: Jika pengguna adalah admin, arahkan ke admin_dashboard.php
    if ($is_admin_user) {
        header('Location: admin_dashboard.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Error fetching user data in dashboard.php: " . $e->getMessage());
    $message = "Terjadi kesalahan saat memuat data pengguna.";
    $message_type = 'error';
    // Fallback data pengguna jika ada kesalahan database
    $user_data = [
        'id' => $current_user_id,
        'username' => $_SESSION['username'],
        'email' => 'N/A',
        'balance' => 0,
        'points' => 0,
        'membership_level' => 'Bronze',
        'is_admin' => 0,
        'profile_picture_url' => 'uploads/profile_pictures/default.png',
        'full_name' => 'N/A',
        'address' => 'N/A',
        'phone_number' => 'N/A',
        'nationality' => 'N/A'
    ];
}

// Ambil daftar ID produk yang sudah memiliki pengajuan cicilan pending/approved oleh pengguna saat ini
$user_installed_product_ids = [];
try {
    $stmt = $pdo->prepare("SELECT product_id FROM installments WHERE user_id = ? AND (status = 'pending' OR status = 'approved')");
    $stmt->execute([$current_user_id]);
    $user_installed_product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN); // Mengambil hanya kolom product_id
} catch (PDOException $e) {
    error_log("Error fetching user installed product IDs: " . $e->getMessage());
    // Lanjutkan eksekusi meskipun ada error, array akan kosong
}


// Ambil daftar produk yang tersedia untuk diklaim oleh pengguna (Tugas Tersedia)
// Query ini sudah memfilter tugas yang belum pernah diklaim oleh user yang sedang login.
$available_products = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.name, 
            p.description, 
            p.image_url, 
            p.product_price, 
            p.commission_percentage, 
            p.points_awarded
        FROM 
            products p
        WHERE 
            NOT EXISTS (
                SELECT 1
                FROM claims c
                WHERE c.product_id = p.id
                AND c.user_id = ? -- Memastikan klaim oleh user yang sedang login (tanpa filter status, berarti pernah klaim apa pun statusnya)
            )
        ORDER BY p.id DESC
        LIMIT 6 -- Mengambil lebih banyak untuk memungkinkan paginasi
    ");
    $stmt->execute([$current_user_id]);
    $available_products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching available products (claims) in dashboard.php: " . $e->getMessage());
    $message = "Gagal memuat daftar tugas: " . $e->getMessage();
    $message_type = 'error';
}


// Ambil daftar produk yang tersedia untuk diajukan cicilan (Cicilan Tersedia)
// Query ini sudah memfilter produk yang belum memiliki cicilan 'pending' atau 'approved'
// oleh user yang sedang login.
$available_installment_products = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.name, 
            p.description, 
            p.image_url, 
            p.product_price, 
            p.commission_percentage, 
            p.points_awarded
        FROM 
            products p
        WHERE 
            NOT EXISTS (
                SELECT 1
                FROM installments i
                WHERE i.product_id = p.id
                AND i.user_id = ?
                AND (i.status = 'pending' OR i.status = 'approved')
            )
        ORDER BY p.id DESC
        LIMIT 3
    ");
    $stmt->execute([$current_user_id]);
    $available_installment_products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching available installment products in dashboard.php: " . $e->getMessage());
    $message = "Gagal memuat daftar cicilan: " . $e->getMessage();
    $message_type = 'error';
}


// Ambil daftar cicilan yang diajukan oleh pengguna ini untuk dashboard (hanya 3 terbaru untuk tabel)
$user_active_installments = []; 

// Data khusus untuk chart dashboard dan ringkasannya (HANYA cicilan 'approved')
$chart_approved_original_price = 0;
$chart_approved_remaining_amount = 0;
$chart_approved_paid_amount = 0;

try {
    // Ambil data untuk tampilan tabel (dibatasi 3)
    $stmt_table_data = $pdo->prepare("
        SELECT i.id, p.name AS product_name, p.image_url, i.original_product_price, i.remaining_amount, 
               i.status, i.requested_at, u.username AS admin_approver, i.approved_at, i.completed_at, i.points_awarded
        FROM installments i
        JOIN products p ON i.product_id = p.id
        LEFT JOIN users u ON i.approved_by = u.id 
        WHERE i.user_id = ? AND i.status IN ('pending', 'approved', 'rejected', 'completed')
        ORDER BY i.requested_at DESC
        LIMIT 3
    "); 
    $stmt_table_data->execute([$current_user_id]);
    $user_active_installments = $stmt_table_data->fetchAll();

    // Ambil data khusus untuk chart dan ringkasannya (HANYA cicilan 'approved')
    $stmt_chart_data = $pdo->prepare("
        SELECT 
            SUM(original_product_price) AS total_original, 
            SUM(remaining_amount) AS total_remaining
        FROM installments
        WHERE user_id = ? AND status = 'approved'
    ");
    $stmt_chart_data->execute([$current_user_id]);
    $chart_data = $stmt_chart_data->fetch(PDO::FETCH_ASSOC);

    $chart_approved_original_price = (float)($chart_data['total_original'] ?? 0);
    $chart_approved_remaining_amount = (float)($chart_data['total_remaining'] ?? 0);

    $chart_approved_paid_amount = $chart_approved_original_price - $chart_approved_remaining_amount;

} catch (PDOException $e) {
    error_log("Error fetching installment data for dashboard: " . $e->getMessage());
    // Fallback ke 0 jika ada kesalahan untuk data chart
    $chart_approved_original_price = 0;
    $chart_approved_remaining_amount = 0;
    $chart_approved_paid_amount = 0;
}


// Ambil riwayat transaksi keseluruhan situs (dianonimkan, semua status)
$global_transactions = [];
try {
    // Ambil semua transaksi, termasuk bank_name, account_number, account_name
    $stmt = $pdo->prepare("SELECT type, amount, status, created_at, bank_name, account_number, account_name FROM transactions ORDER BY created_at DESC LIMIT 10"); 
    $stmt->execute();
    $global_transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching global transactions for dashboard.php: " . $e->getMessage());
    // Tidak menampilkan pesan error ke pengguna untuk ini, biarkan bagian kosong
}

// Ambil semua klaim yang berhasil disetujui oleh pengguna lain untuk marquee
$all_approved_claims_for_marquee = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            u.username,
            p.name AS product_name, 
            c.claim_amount, 
            c.approved_at
        FROM claims c
        JOIN users u ON c.user_id = u.id
        JOIN products p ON c.product_id = p.id
        WHERE c.status = 'approved'
        ORDER BY c.approved_at DESC
        LIMIT 20 // Batasi jumlah untuk marquee agar tidak terlalu panjang
    ");
    $stmt->execute();
    $all_approved_claims_for_marquee = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching all approved claims for marquee in dashboard.php: " . $e->getMessage());
}

// Ambil riwayat transaksi cicilan keseluruhan situs (dianonimkan, semua status)
$global_installment_transactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            i.id, 
            u.username, 
            p.name AS product_name, 
            i.original_product_price, 
            i.remaining_amount, 
            i.status, 
            i.approved_at, 
            i.completed_at,
            i.requested_at
        FROM installments i
        JOIN users u ON i.user_id = u.id
        JOIN products p ON i.product_id = p.id
        ORDER BY i.requested_at DESC
        LIMIT 7 -- Batasi jumlah untuk tampilan di kolom kiri
    ");
    $stmt->execute();
    $global_installment_transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching global installment transactions for dashboard.php: " . $e->getMessage());
}


/**
 * Fungsi untuk menyensor bagian belakang nominal transaksi.
 * Contoh: 1234567.89 menjadi 1.234.XXX
 * @param float $amount Jumlah transaksi.
 * @return string Nominal yang sudah disensor.
 */
function censorGlobalAmountDisplay($amount) {
    // Pastikan amount adalah float untuk perhitungan yang benar
    $amount = (float)$amount; 
    $amount_str = number_format($amount, 0, '', ''); // Format tanpa desimal, tanpa pemisah ribuan: "1234567"
    
    // Pastikan panjang string lebih dari 0 untuk menghindari error substr
    if (empty($amount_str)) {
        return 'Rp XXX';
    }

    // Ganti digit terakhir dengan 'XXX'
    // Ambil semua kecuali 3 digit terakhir, lalu tambahkan 'XXX'
    // Jika string kurang dari 3 digit, seluruhnya menjadi 'XXX'
    $censored_integer_part = (strlen($amount_str) > 3) ? 
                                substr($amount_str, 0, -3) . 'XXX' : 
                                str_repeat('X', strlen($amount_str));

    // Tambahkan pemisah ribuan untuk bagian yang tidak disensor
    $formatted_censored_integer_part = number_format((float)str_replace('XXX', '', $censored_integer_part), 0, ',', '.') . (strpos($censored_integer_part, 'XXX') !== false ? 'XXX' : '');
    
    // Periksa apakah ada bagian desimal (jika perlu ditampilkan)
    $decimal_part = ($amount - floor($amount) > 0) ? ',' . substr(number_format($amount, 2, '.', ''), -2) : '';

    return 'Rp ' . $formatted_censored_integer_part . $decimal_part;
}

/**
 * Censors an account number to show first 2 and last 2 digits, with 'X's in between.
 * Examples: "1234567890" -> "12XXXXXX90", "1234" -> "12XX", "123" -> "1XXX"
 * @param string $account_number The account number to censor.
 * @return string The censored account number.
 */
function censorAccountNumberDisplay($account_number) {
    $account_number = (string)$account_number; 
    $length = strlen($account_number);

    if (empty($account_number)) {
        return 'XXX-XXX-XXX'; // Placeholder jika kosong
    }
    
    if ($length <= 4) {
        // Untuk angka yang sangat pendek, tampilkan digit pertama, sisanya X
        return substr($account_number, 0, 1) . str_repeat('X', max(0, $length - 1));
    }
    
    // Untuk panjang > 4, sensor bagian tengah. Pastikan jumlah 'X' tidak negatif.
    $middle_x_count = max(0, $length - 4); 
    return substr($account_number, 0, 2) . str_repeat('X', $middle_x_count) . substr($account_number, -2);
}

/**
 * Censors a name to show first and last character with asterisks in between.
 * Examples: "John Doe" -> "J***e", "Alice" -> "A***e", "Bob" -> "B*b", "Me" -> "M*"
 * @param string $name The name to censor.
 * @return string The censored name.
 */
function censorName($name) {
    $name = (string)($name ?? 'Anonim');
    $length = mb_strlen($name, 'UTF-8');

    if ($length <= 1) {
        return '*'; // e.g., 'A' becomes '*'
    } elseif ($length == 2) {
        return mb_substr($name, 0, 1, 'UTF-8') . '*'; // e.g., 'AB' becomes 'A*'
    } else { // length >= 3
        return mb_substr($name, 0, 1, 'UTF-8') . str_repeat('*', max(0, $length - 2)) . mb_substr($name, -1, 1, 'UTF-8');
    }
}

// Bangun konten marquee
// Sensor username untuk "Selamat Datang"
$censored_username_welcome = censorName($username); // Menggunakan fungsi censorName yang baru
$marquee_content_parts = ["Selamat Datang, " . $censored_username_welcome . "!"];


// Tambahkan transaksi global ke marquee dengan pewarnaan
foreach ($global_transactions as $transaction) {
    $transaction_color_class = '';
    if ($transaction['type'] === 'deposit') {
        $transaction_color_class = 'text-green-400'; // Hijau untuk deposit
    } elseif ($transaction['type'] === 'withdraw') {
        $transaction_color_class = 'text-red-400'; // Merah untuk withdraw
    }
    
    // Gunakan fungsi censorName yang baru
    $censored_account_name_for_marquee = censorName($transaction['account_name'] ?? 'Anonim');

    $transaction_text = ucfirst($transaction['type']) . " " . censorGlobalAmountDisplay($transaction['amount']) . " oleh " . $censored_account_name_for_marquee;
    if ($transaction['type'] === 'deposit' && !empty($transaction['bank_name'])) {
        $transaction_text .= " via " . htmlspecialchars($transaction['bank_name']);
    }
    $transaction_text .= " (No. Rek: " . censorAccountNumberDisplay($transaction['account_number'] ?? '') . ")"; // Tambahkan nomor rekening disensor
    $marquee_content_parts[] = "<span class=\"{$transaction_color_class}\">{$transaction_text}</span>";
}

// Tambahkan semua klaim yang berhasil disetujui ke marquee dengan pewarnaan
foreach ($all_approved_claims_for_marquee as $claim) {
    // Gunakan fungsi censorName yang baru
    $censored_claim_username_for_marquee = censorName($claim['username']);

    $claim_text = "Tugas '" . htmlspecialchars($claim['product_name']) . "' berhasil diklaim oleh " . $censored_claim_username_for_marquee . " (" . censorGlobalAmountDisplay($claim['claim_amount']) . ")";
    $marquee_content_parts[] = "<span class=\"text-yellow-400\">{$claim_text}</span>"; // Kuning untuk klaim disetujui
}

$final_marquee_text = implode(' | ', $marquee_content_parts);

// Untuk variabel $loggedIn yang digunakan di footer.php, kita bisa set berdasarkan $_SESSION['user_id']
$loggedIn = isset($_SESSION['user_id']);


// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<style>
    /* Animasi berkedip untuk elemen */
    @keyframes fadeEffect {
        0% { opacity: 1; }
        50% { opacity: 0.1; } /* Lebih transparan saat "menghilang" */
        100% { opacity: 1; }
    }

    .flicker-animation {
        animation: fadeEffect 3s infinite alternate; /* Durasi 3 detik, berulang tak terbatas, bolak-balik */
    }

    /* Gaya untuk sitemap yang lebih ramping */
    .sitemap-container {
        background-color: #ffffff;
        padding: 0.75rem; /* Padding lebih kecil */
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        margin-bottom: 1.5rem; /* Jarak bawah sitemap lebih kecil */
    }

    .sitemap-container ul {
        list-style: none;
        padding: 0;
        display: flex; /* Untuk tata letak horizontal di desktop */
        justify-content: space-around; /* Agar link tersebar merata */
        flex-wrap: wrap; /* Izinkan wrap pada layar kecil */
    }

    .sitemap-container ul li {
        margin: 0.25rem 0; /* Jarak vertikal lebih kecil */
    }

    .sitemap-container ul li a {
        display: block;
        padding: 0.5rem 0.75rem; /* Padding lebih kecil */
        border-radius: 0.375rem; /* rounded-md */
        color: #4A5568; /* gray-700 */
        font-weight: 600; /* semi-bold */
        text-decoration: none;
        transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
        white-space: nowrap; /* Pastikan teks link tidak terpotong */
        font-size: 0.9rem; /* Ukuran font sedikit lebih kecil */
    }

    .sitemap-container ul li a:hover {
        background-color: #EBF8FF; /* blue-50 */
        color: #2B6CB0; /* blue-700 */
    }

    /* Sesuaikan sitemap untuk tampilan mobile */
    @media (max-width: 767px) { /* Untuk layar yang lebih kecil dari sm */
        .sitemap-container {
            padding: 0.5rem; /* Padding lebih kecil lagi di mobile */
            margin-bottom: 1rem;
        }
        .sitemap-container ul {
            flex-direction: column; /* Ubah ke tata letak vertikal */
            align-items: stretch; /* Regangkan item agar memenuhi lebar */
        }
        .sitemap-container ul li a {
            text-align: center; /* Pusatkan teks link */
            padding: 0.4rem 0.6rem; /* Padding lebih kecil lagi */
            font-size: 0.85rem; /* Ukuran font lebih kecil lagi */
        }
    }

    /* Styling untuk chart dengan efek 3D */
    .chart-wrapper {
        position: relative;
        height: 200px; /* Sesuai dengan style inline sebelumnya */
        width: 100%;
        max-width: 384px; /* max-w-sm */
        margin-left: auto;
        margin-right: auto;
        perspective: 1000px; /* Memberikan kedalaman 3D */
    }

    .chart-canvas {
        transform: rotateX(20deg); /* Rotasi untuk efek 3D */
        box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.2); /* Bayangan untuk kedalaman */
        transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
    }

    .chart-canvas:hover {
        transform: rotateX(15deg) scale(1.02); /* Efek hover ringan */
        box-shadow: 0px 15px 30px rgba(0, 0, 0, 0.3);
    }
</style>

<main class="flex-grow container mx-auto p-4 md:p-8">
    <!-- Sitemap yang baru diposisikan dan dirampingkan -->
    <section class="sitemap-container">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="about_us.php">Tentang Kami</a></li>
            <li><a href="claims.php">Tugas</a></li>
            <li><a href="installments.php">Cicilan</a></li>
        </ul>
    </section>

    <!-- Marquee yang dirampingkan dan bergaya, kini dengan flicker-animation -->
    <div class="bg-gray-900 border border-gray-700 text-white px-4 py-2 mb-6 rounded-full shadow-lg overflow-hidden flex items-center justify-center flicker-animation">
        <h4 class="font-semibold text-lg text-center whitespace-nowrap overflow-hidden">
            <marquee behavior="scroll" direction="left" scrollamount="4" class="inline-block py-0.5">
                <?= $final_marquee_text ?>
            </marquee>
        </h4>
    </div>

    <!-- NOTIFIKASI UMUM (jika ada) -->
    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg 
                            <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>" 
                            role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Kolom Kiri: Profil Pengguna dan Riwayat Transaksi Cicilan (Global) -->
        <!-- Menambahkan hidden lg:block untuk menyembunyikan di layar kecil -->
        <div class="lg:col-span-1 space-y-8 hidden lg:block"> 
            <!-- Profil Pengguna -->
            <section class="bg-white p-6 rounded-xl shadow-md h-fit">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-800">Profil Anda</h2>
                    <a href="profile_settings.php" class="text-blue-600 hover:text-blue-800 font-semibold text-sm py-1 px-3 rounded-md border border-blue-600 hover:border-blue-800 transition duration-200">
                        Edit
                    </a>
                </div>
                <div class="flex flex-col items-center mb-6">
                    <img src="<?= htmlspecialchars($user_data['profile_picture_url'] ?? 'https://placehold.co/128x128/CCCCCC/666666?text=Foto+Profil') ?>" 
                                 alt="Foto Profil" 
                                 class="w-32 h-32 rounded-full object-cover border-4 border-blue-300 shadow-lg">
                    <h3 class="text-xl font-bold text-gray-900 mt-3"><?= htmlspecialchars($user_data['username']) ?></h3>
                </div>

                <div class="space-y-4">
                    <div class="border-b pb-2">
                        <p class="text-sm font-semibold text-gray-700">Nama Lengkap:</p>
                        <p class="text-gray-900"><?= htmlspecialchars($user_data['full_name'] ?? '-') ?></p>
                    </div>
                    <div class="border-b pb-2">
                        <p class="text-sm font-semibold text-gray-700">Email:</p>
                        <p class="text-gray-900"><?= htmlspecialchars($user_data['email']) ?></p>
                    </div>
                    <div class="border-b pb-2">
                        <p class="text-sm font-semibold text-gray-700">Saldo:</p>
                        <p class="text-gray-900">Rp <?= number_format($user_data['balance'], 2, ',', '.') ?></p>
                    </div>
                    <div class="border-b pb-2">
                        <p class="text-sm font-semibold text-gray-700">Poin:</p>
                        <p class="text-gray-900"><?= number_format($user_data['points'], 0, ',', '.') ?></p>
                    </div>
                    <div class="border-b pb-2">
                        <p class="text-sm font-semibold text-gray-700">Level Keanggotaan:</p>
                        <p class="text-gray-900"><?= htmlspecialchars($user_data['membership_level']) ?></p>
                    </div>
                    <div class="border-b pb-2">
                        <p class="text-sm font-semibold text-gray-700">Alamat:</p>
                        <p class="text-gray-900"><?= nl2br(htmlspecialchars($user_data['address'] ?? '-')) ?></p>
                    </div>
                    <div class="border-b pb-2">
                        <p class="text-sm font-semibold text-gray-700">Nomor HP:</p>
                        <p class="text-gray-900"><?= htmlspecialchars($user_data['phone_number'] ?? '-') ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Kewarganegaraan:</p>
                        <p class="text-gray-900"><?= htmlspecialchars($user_data['nationality'] ?? '-') ?></p>
                    </div>
                </div>
            </section>

            <!-- Riwayat Transaksi Cicilan (Global) - Di bawah Profil Anda -->
            <section class="bg-white p-6 rounded-xl shadow-md h-fit">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Riwayat Transaksi Cicilan (Global)</h2>
                <?php if (empty($global_installment_transactions)): ?>
                    <p class="text-gray-600 text-center">Belum ada riwayat transaksi cicilan yang tercatat.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengguna</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tugas</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($global_installment_transactions as $installment): ?>
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-800">
                                            <?= censorName($installment['username']) ?>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-800">
                                            <?= htmlspecialchars($installment['product_name']) ?>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-bold 
                                            <?php 
                                                // Warna merah jika remaining_amount > 0, hijau jika 0 (lunas)
                                                // Status 'approved' menunjukkan masih mencicil
                                                echo ($installment['status'] === 'completed' || $installment['remaining_amount'] == 0) ? 'text-green-600' : 'text-red-600'; 
                                            ?>">
                                            Rp <?= number_format($installment['remaining_amount'], 2, ',', '.') ?>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <?php
                                                $status_color_class = ''; // Variabel untuk kelas warna Tailwind
                                                if ($installment['status'] === 'completed' || $installment['remaining_amount'] == 0) {
                                                    $status_text = 'Lunas';
                                                    $status_color_class = 'bg-green-100 text-green-800'; // Lunas (hijau)
                                                } else {
                                                    $status_text = 'Mencicil';
                                                    $status_color_class = 'bg-red-100 text-red-800'; // Mencicil (merah)
                                                }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color_class ?>">
                                                <?= $status_text ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-4">
                        <a href="installments.php" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                            Lihat Selengkapnya <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        </div>


        <!-- Kolom Tengah & Kanan: Ringkasan Akun, Cicilan Anda, Tugas Tersedia, dan Aktivitas Transaksi Situs -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Ringkasan Saldo, Poin, Level -->
            <section class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Ringkasan Akun</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                    <div class="bg-green-50 p-5 rounded-lg shadow-sm">
                        <i class="fas fa-wallet text-green-600 text-3xl mb-2"></i>
                        <p class="text-xl font-semibold text-gray-700">Saldo Anda</p>
                        <p class="text-3xl font-bold text-green-700 mt-1">Rp <?= number_format($user_data['balance'], 2, ',', '.') ?></p>
                    </div>
                    <div class="bg-indigo-50 p-5 rounded-lg shadow-sm">
                        <i class="fas fa-star text-indigo-600 text-3xl mb-2"></i>
                        <p class="text-xl font-semibold text-gray-700">Poin Anda</p>
                        <p class="text-3xl font-bold text-indigo-700 mt-1"><?= number_format($user_data['points'], 0, ',', '.') ?></p>
                    </div>
                    <div class="bg-purple-50 p-5 rounded-lg shadow-sm">
                        <i class="fas fa-gem text-purple-600 text-3xl mb-2"></i>
                        <p class="text-xl font-semibold text-gray-700">Level Keanggotaan</p>
                        <p class="text-3xl font-bold text-purple-700 mt-1"><?= htmlspecialchars($user_data['membership_level']) ?></p>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row justify-center gap-4 mt-6">
                    <a href="deposit.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg shadow-md text-center transition duration-200">
                        <i class="fas fa-plus-circle mr-2"></i> Isi Saldo
                    </a>
                    <a href="withdraw.php" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-3 px-6 rounded-lg shadow-md text-center transition duration-200">
                        <i class="fas fa-minus-circle mr-2"></i> Tarik Saldo
                    </a>
                </div>
            </section>

            <!-- Bagian Cicilan Anda (Aktif) - Dipindahkan ke sini -->
            <section id="user-active-installments-section" class="bg-white p-6 rounded-xl shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 text-center">Cicilan Anda (Aktif)</h2>
                    <a href="installments.php" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                        Lihat Semua <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <?php if ($chart_approved_original_price == 0): ?>
                    <p class="text-gray-600 text-center">Anda belum memiliki cicilan disetujui untuk ditampilkan di grafik.</p>
                <?php else: ?>
                    <!-- Ringkasan Chart untuk Cicilan Aktif -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">Ringkasan Cicilan Aktif</h3>
                        <div class="relative w-full max-w-sm mx-auto chart-wrapper" style="height: 200px;">
                            <canvas id="dashboardActiveInstallmentPieChart" class="chart-canvas"></canvas>
                        </div>
                        <div class="mt-4 text-center text-sm">
                            <p class="text-gray-700">Total Harga Awal (Disetujui): <strong class="text-indigo-600">Rp <?= number_format($chart_approved_original_price, 2, ',', '.') ?></strong></p>
                            <p class="text-gray-700">Total Sudah Dibayar (Disetujui): <strong class="text-green-600">Rp <?= number_format($chart_approved_paid_amount, 2, ',', '.') ?></strong></p>
                            <p class="text-gray-700">Total Sisa Pembayaran (Disetujui): <strong class="text-red-600">Rp <?= number_format($chart_approved_remaining_amount, 2, ',', '.') ?></strong></p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white rounded-lg overflow-hidden">
                            <thead class="bg-gray-100 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tugas</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($user_active_installments as $installment): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-sm font-medium text-gray-900"><?= htmlspecialchars($installment['product_name']) ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm font-bold <?= $installment['status'] === 'approved' && $installment['remaining_amount'] > 0 ? 'text-red-600' : 'text-gray-800' ?>">
                                            Rp <?= number_format($installment['remaining_amount'], 2, ',', '.') ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <?php
                                                $status_color = '';
                                                switch ($installment['status']) {
                                                    case 'pending': $status_color = 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'approved': $status_color = 'bg-green-100 text-green-800'; break;
                                                    case 'rejected': $status_color = 'bg-red-100 text-red-800'; break;
                                                    case 'completed': $status_color = 'bg-blue-100 text-blue-800'; break; // New status
                                                    default: $status_color = 'bg-gray-100 text-gray-800'; break;
                                                }
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                                <?= ucfirst($installment['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-6">
                        <a href="installments.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">
                            Kelola Cicilan
                        </a>
                    </div>
                <?php endif; ?>
            </section>


            <!-- Tugas Tersedia (hanya 3 terbaru) -->
            <section class="bg-white p-6 rounded-xl shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 text-center">Tugas Tersedia</h2>
                    <a href="claims.php" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                        Buka Selengkapnya <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <?php if (empty($available_products)): ?>
                    <p class="text-gray-600 text-center">Tidak ada tugas yang tersedia untuk Anda saat ini.</p>
                <?php else: ?>
                    <div id="product-cards-container" class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($available_products as $product): ?>
                            <div class="product-card bg-gray-50 p-4 rounded-lg shadow-sm flex flex-col justify-between">
                                <div>
                                    <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://placehold.co/100x80/CCCCCC/666666?text=No+Image') ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                                 class="w-full h-32 object-cover rounded-md mb-3 mx-auto"
                                                 onerror="this.onerror=null;this.src='https://placehold.co/100x80/CCCCCC/666666?text=No+Image';">
                                    <h3 class="font-semibold text-gray-900 mb-1 line-clamp-1"><?= htmlspecialchars($product['name']) ?></h3>
                                    <p class="text-gray-700 text-sm mb-2 line-clamp-3"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                                    <p class="font-bold text-blue-600">Harga: Rp <?= number_format($product['product_price'], 2, ',', '.') ?></p>
                                    <p class="text-green-600 text-sm">Komisi: <?= number_format($product['commission_percentage'] * 100, 0) ?>%</p>
                                    <p class="text-purple-600 text-sm mb-3">Poin: <?= number_format($product['points_awarded'], 0) ?></p>
                                </div>
                                <div class="flex flex-col gap-2 mt-4">
                                    <!-- Tombol Klaim Tugas telah dihapus sesuai permintaan -->
                                    <!-- Tombol Ajukan Cicilan telah dihapus sesuai permintaan -->
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Navigasi paginasi mobile untuk Tugas Tersedia -->
                    <nav id="mobile-pagination-nav-tasks" class="flex items-center justify-between px-4 py-3 sm:hidden" style="display: none;">
                        <button id="mobile-prev-btn-tasks" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 opacity-50 cursor-not-allowed">
                            Sebelumnya
                        </button>
                        <span id="mobile-page-info-tasks" class="text-sm text-gray-700">Halaman 1 dari 1</span>
                        <button id="mobile-next-btn-tasks" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Berikutnya
                        </button>
                    </nav>
                <?php endif; ?>
            </section>

            <!-- Cicilan Tersedia (hanya 3 terbaru) - Bagian ini dinonaktifkan di PHP dengan if (false) -->
            <?php if (false): // Wrap the entire section in an if (false) to hide it ?>
            <section class="bg-white p-6 rounded-xl shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-800 text-center">Cicilan Tersedia</h2>
                    <a href="installments.php" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                        Buka Selengkapnya <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <?php if (empty($available_installment_products)): ?>
                    <p class="text-gray-600 text-center">Tidak ada cicilan yang tersedia untuk Anda saat ini.</p>
                <?php else: ?>
                    <div id="installment-product-cards-container" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach ($available_installment_products as $product): ?>
                            <div class="product-card bg-gray-50 p-4 rounded-lg shadow-sm flex flex-col justify-between">
                                <div>
                                    <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://placehold.co/100x80/CCCCCC/666666?text=No+Image') ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                                 class="w-full h-32 object-cover rounded-md mb-3 mx-auto"
                                                 onerror="this.onerror=null;this.src='https://placehold.co/100x80/CCCCCC/666666?text=No+Image';">
                                    <h3 class="font-semibold text-gray-900 mb-1 line-clamp-1"><?= htmlspecialchars($product['name']) ?></h3>
                                    <p class="text-gray-700 text-sm mb-2 line-clamp-3"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                                    <p class="font-bold text-blue-600">Harga: Rp <?= number_format($product['product_price'], 2, ',', '.') ?></p>
                                    <p class="text-green-600 text-sm">Komisi: <?= number_format($product['commission_percentage'] * 100, 0) ?>%</p>
                                    <p class="text-purple-600 text-sm mb-3">Poin: <?= number_format($product['points_awarded'], 0) ?></p>
                                </div>
                                <form action="process_installment_request.php" method="POST" class="w-full">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                    <button type="submit" 
                                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                                        Ajukan Cicilan
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Navigasi paginasi mobile untuk cicilan tersedia -->
                    <nav id="mobile-pagination-nav-installments" class="flex items-center justify-between px-4 py-3 sm:hidden" style="display: none;">
                        <button id="mobile-prev-btn-installments" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 opacity-50 cursor-not-allowed">
                            Previous
                        </button>
                        <span id="mobile-page-info-installments" class="text-sm text-gray-700">Page 1 of 1</span>
                        <button id="mobile-next-btn-installments" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </button>
                    </nav>
                <?php endif; ?>
            </section>
            <?php endif; ?>


            <!-- Aktivitas Transaksi Situs Terbaru (Global) -->
            <section class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Aktivitas Transaksi Situs Terbaru (Anonim)</h2>
                <?php if (empty($global_transactions)): ?>
                    <p class="text-gray-600 text-center">Belum ada aktivitas transaksi situs yang tercatat.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengirim</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($global_transactions as $transaction): ?>
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $transaction['type'] === 'deposit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= ucfirst($transaction['type']) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">
                                            <?= censorGlobalAmountDisplay($transaction['amount']) ?>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">
                                            <?= censorName($transaction['account_name'] ?? 'Anonim') ?>
                                            (<?= htmlspecialchars($transaction['bank_name'] ?? 'Bank') ?>)
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('d M Y H:i', strtotime($transaction['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<!-- Chat Window -->
<div id="chatWindow" class="fixed bottom-20 right-6 w-80 h-96 bg-white rounded-lg shadow-xl flex flex-col hidden z-50">
    <div class="flex justify-between items-center bg-blue-600 text-white p-3 rounded-t-lg">
        <h3 class="text-lg font-semibold">Live Chat</h3>
        <button id="closeChatBtn" class="text-white hover:text-gray-200">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div id="chatMessages" class="flex-1 p-3 overflow-y-auto bg-gray-100 chat-messages-container">
        <!-- Messages will be loaded here by JavaScript -->
        <div class="text-center text-gray-500 text-sm mt-4">Memuat pesan...</div>
    </div>
    <div class="border-t border-gray-200 p-3 flex items-center">
        <input type="text" id="chatInput" placeholder="Ketik pesan..." class="flex-1 border rounded-lg py-2 px-3 mr-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <button id="sendMessageBtn" class="bg-blue-600 text-white p-2 rounded-lg hover:bg-blue-700">
            <i class="fas fa-paper-plane"></i>
        </button>
        <input type="file" id="chatImageInput" accept="image/*" class="hidden">
        <button id="sendImageBtn" class="bg-gray-300 text-gray-800 p-2 rounded-lg hover:bg-gray-400 ml-2">
            <i class="fas fa-image"></i>
        </button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Chart.js CDN -->
<script>
    // Pastikan variabel ini tersedia secara global untuk chat.js
    window.loggedInUserId = <?= json_encode($user_data['id'] ?? null) ?>;
    window.isAdmin = <?= json_encode($user_data['is_admin'] ?? false) ?>;

    // Data untuk pie chart cicilan aktif di dashboard (HANYA status 'approved')
    const totalOriginalPriceApprovedChart = parseFloat(<?= json_encode($chart_approved_original_price) ?>);
    const totalRemainingAmountApprovedChart = parseFloat(<?= json_encode($chart_approved_remaining_amount) ?>);
    const totalPaidAmountApprovedChart = parseFloat(<?= json_encode($chart_approved_paid_amount) ?>);

    // --- DEBUGGING JAVASCRIPT: Log data untuk chart cicilan ---
    console.log('Dashboard Installment Chart Data from PHP (in JS):');
    console.log('totalOriginalPriceApprovedChart:', totalOriginalPriceApprovedChart, typeof totalOriginalPriceApprovedChart);
    console.log('totalRemainingAmountApprovedChart:', totalRemainingAmountApprovedChart, typeof totalRemainingAmountApprovedChart);
    console.log('totalPaidAmountApprovedChart:', totalPaidAmountApprovedChart, typeof totalPaidAmountApprovedChart);
    // --- END DEBUGGING JAVASCRIPT ---


    document.addEventListener('DOMContentLoaded', function() {
        const ctxDashboard = document.getElementById('dashboardActiveInstallmentPieChart');
        const chartContainer = ctxDashboard ? ctxDashboard.parentElement : null;

        // Pastikan ctxDashboard ada dan ada data untuk menggambar chart
        // Chart hanya akan digambar jika ada data 'approved' yang relevan
        if (ctxDashboard && totalOriginalPriceApprovedChart > 0 && 
            !isNaN(totalPaidAmountApprovedChart) && !isNaN(totalRemainingAmountApprovedChart) &&
            isFinite(totalPaidAmountApprovedChart) && isFinite(totalRemainingAmountApprovedChart)) {
            
            // Jika ada instance chart sebelumnya, hancurkan dulu untuk menghindari duplikasi
            if (window.myDashboardPieChart instanceof Chart) {
                window.myDashboardPieChart.destroy();
            }

            window.myDashboardPieChart = new Chart(ctxDashboard, {
                type: 'doughnut', 
                data: {
                    labels: ['Sudah Dibayar (Disetujui)', 'Sisa Pembayaran (Disetujui)'],
                    datasets: [{
                        data: [totalPaidAmountApprovedChart, totalRemainingAmountApprovedChart],
                        backgroundColor: ['#4CAF50', '#F44336'], // Green for Paid, Red for Remaining
                        borderColor: ['#ffffff', '#ffffff'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 12 
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        const total = context.dataset.data.reduce((sum, val) => sum + val, 0);
                                        const percentage = total === 0 ? 0 : (context.parsed / total) * 100; // Handle division by zero
                                        label += 'Rp ' + context.parsed.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + percentage.toFixed(2) + '%)';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        } else if (chartContainer) {
            // Ganti canvas dengan pesan jika tidak ada cicilan aktif atau totalnya 0 atau tidak valid
            if (window.myDashboardPieChart instanceof Chart) {
                window.myDashboardPieChart.destroy();
            }
            chartContainer.innerHTML = '<p class="text-center text-gray-500 text-md p-4">Tidak ada cicilan disetujui untuk ditampilkan di grafik.</p>';
            chartContainer.style.height = 'auto'; // Sesuaikan tinggi container
        }


        // Script untuk Paginasi Mobile Tugas Tersedia (baru)
        const tasksProductsContainer = document.getElementById('product-cards-container');
        if (tasksProductsContainer) {
            const tasksProductsCards = Array.from(tasksProductsContainer.querySelectorAll('.product-card'));
            const mobilePaginationNavTasks = document.getElementById('mobile-pagination-nav-tasks');
            const mobilePrevBtnTasks = document.getElementById('mobile-prev-btn-tasks');
            const mobileNextBtnTasks = document.getElementById('mobile-next-btn-tasks');
            const mobilePageInfoTasks = document.getElementById('mobile-page-info-tasks');
            
            const itemsPerPageTasks = 2; // Menampilkan 2 item per halaman di mobile
            let currentPageTasks = 1;
            let totalMobilePagesTasks = Math.ceil(tasksProductsCards.length / itemsPerPageTasks);

            function showTasksPage(page) {
                currentPageTasks = Math.max(1, Math.min(page, totalMobilePagesTasks));

                const startIndex = (currentPageTasks - 1) * itemsPerPageTasks;
                const endIndex = startIndex + itemsPerPageTasks;

                tasksProductsCards.forEach((card, index) => {
                    if (index >= startIndex && index < endIndex) {
                        card.style.display = 'flex'; // Menggunakan flex karena kartu memiliki flexbox
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (mobilePrevBtnTasks) {
                    mobilePrevBtnTasks.disabled = currentPageTasks === 1;
                    mobilePrevBtnTasks.classList.toggle('opacity-50', currentPageTasks === 1);
                    mobilePrevBtnTasks.classList.toggle('cursor-not-allowed', currentPageTasks === 1);
                }
                if (mobileNextBtnTasks) {
                    mobileNextBtnTasks.disabled = currentPageTasks === totalMobilePagesTasks;
                    mobileNextBtnTasks.classList.toggle('opacity-50', currentPageTasks === totalMobilePagesTasks);
                    mobileNextBtnTasks.classList.toggle('cursor-not-allowed', currentPageTasks === totalMobilePagesTasks);
                }

                if (mobilePageInfoTasks) {
                    mobilePageInfoTasks.textContent = `Halaman ${currentPageTasks} dari ${totalMobilePagesTasks}`;
                }
            }

            function checkScreenSizeAndToggleTasksPagination() {
                const isMobile = window.innerWidth < 768; // Misalnya, di bawah 768px dianggap mobile

                if (isMobile && tasksProductsCards.length > itemsPerPageTasks) {
                    if (mobilePaginationNavTasks) mobilePaginationNavTasks.style.display = 'flex';
                    showTasksPage(currentPageTasks);
                } else {
                    if (mobilePaginationNavTasks) mobilePaginationNavTasks.style.display = 'none';
                    tasksProductsCards.forEach(card => card.style.display = 'flex'); // Tampilkan semua di desktop
                }

                // Sembunyikan navigasi jika hanya ada 1 halaman (atau kurang dari itemsPerPage)
                if (tasksProductsCards.length <= itemsPerPageTasks && mobilePaginationNavTasks) {
                    mobilePaginationNavTasks.style.display = 'none';
                }
            }

            if (mobilePrevBtnTasks) {
                mobilePrevBtnTasks.addEventListener('click', function() {
                    showTasksPage(currentPageTasks - 1);
                });
            }

            if (mobileNextBtnTasks) {
                mobileNextBtnTasks.addEventListener('click', function() {
                    showTasksPage(currentPageTasks + 1);
                });
            }

            // Inisialisasi pada DOMContentLoaded dan resize
            checkScreenSizeAndToggleTasksPagination();
            window.addEventListener('resize', checkScreenSizeAndToggleTasksPagination);
        }
    });
</script>

<?php 
// Sertakan footer halaman
// Ini akan mencakup penutupan tag </body> dan </html>,
// serta memuat script.js dan chat.js (jika user login)
include_once __DIR__ . '/footer.php'; 
?>
