<?php
// claims.php
// Halaman ini memungkinkan pengguna untuk melihat dan mengklaim produk/tugas.
// Menyertakan fitur pencarian dan paginasi responsif.

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Arahkan ke halaman login jika belum login
    exit();
}

$current_user_id = $_SESSION['user_id'];
// Ambil data pengguna lengkap untuk header (masih dibutuhkan oleh header.php)
$user_data = [];
try {
    $stmt = $pdo->prepare("SELECT balance, points, membership_level, is_admin, username FROM users WHERE id = ?");    
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();
    if (!$user_data) {
        session_destroy();
        header('Location: index.php');
        exit();
    }
    $_SESSION['username'] = $user_data['username'];    
    $username = $user_data['username'];
} catch (PDOException $e) {
    error_log("Error fetching user data in claims.php: " . $e->getMessage());
    // Lanjutkan dengan nilai default jika ada kesalahan
    $user_data = ['balance' => 0, 'points' => 0, 'membership_level' => 'N/A', 'is_admin' => 0, 'username' => $_SESSION['username'] ?? 'Pengguna'];
}


$message = '';
$message_type = ''; // 'success' atau 'error'

// Tangani pesan dari process_klaim.php (sekarang dari SESSION)
if (isset($_SESSION['flash_message'])) {
    $message = htmlspecialchars($_SESSION['flash_message']);
    $message_type = htmlspecialchars($_SESSION['flash_message_type']);
    // Hapus pesan dari sesi agar tidak muncul lagi setelah refresh
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_type']);
} else if (isset($_GET['message']) && isset($_GET['type'])) { // Fallback jika masih ada pesan GET
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
}


// Ambil parameter pencarian
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- Logika Paginasi untuk Tugas Tersedia ---
$items_per_page_desktop = 12; // Jumlah item per halaman untuk desktop/tablet
// Pada tampilan mobile, kita akan membatasi tampilan visual ke 3 item per "sub-halaman" menggunakan JS dari 12 item yang diambil.

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $items_per_page_desktop;

// Hitung total produk yang tersedia (dengan filter pencarian)
$total_products = 0;
try {
    $sql_count = "
        SELECT COUNT(*)    
        FROM products p
        WHERE NOT EXISTS (
            SELECT 1
            FROM claims c
            WHERE c.product_id = p.id
            AND (c.status = 'pending' OR c.status = 'approved')
        )
    ";
    // Modifikasi: Tambahkan pencarian berdasarkan deskripsi produk
    if (!empty($search_query)) {
        $sql_count .= " AND (p.name LIKE :search_query_name OR p.description LIKE :search_query_desc)";
    }

    $stmt_count = $pdo->prepare($sql_count);
    // Modifikasi: Bind parameter untuk nama dan deskripsi
    if (!empty($search_query)) {
        $searchTerm = '%' . $search_query . '%';
        $stmt_count->bindValue(':search_query_name', $searchTerm, PDO::PARAM_STR);
        $stmt_count->bindValue(':search_query_desc', $searchTerm, PDO::PARAM_STR);
    }
    $stmt_count->execute();
    $total_products = $stmt_count->fetchColumn();
} catch (PDOException $e) {
    error_log("Error counting products for pagination in claims.php: " . $e->getMessage());
}

$total_pages = ceil($total_products / $items_per_page_desktop);

// Ambil daftar produk yang tersedia untuk diklaim oleh pengguna (dengan paginasi SQL dan filter pencarian)
$products = [];
try {
    $sql = "
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
        WHERE NOT EXISTS (
            SELECT 1
            FROM claims c
            WHERE c.product_id = p.id
            AND (c.status = 'pending' OR c.status = 'approved')
        )
    ";
    // Modifikasi: Tambahkan pencarian berdasarkan deskripsi produk
    if (!empty($search_query)) {
        $sql .= " AND (p.name LIKE :search_query_name OR p.description LIKE :search_query_desc)";
    }
    $sql .= " ORDER BY p.id DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    // Modifikasi: Bind parameter untuk nama dan deskripsi
    if (!empty($search_query)) {
        $searchTerm = '%' . $search_query . '%';
        $stmt->bindValue(':search_query_name', $searchTerm, PDO::PARAM_STR);
        $stmt->bindValue(':search_query_desc', $searchTerm, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $items_per_page_desktop, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching products in claims.php with pagination and search: " . $e->getMessage());
    $message = "Gagal memuat daftar tugas.";
    $message_type = 'error';
}

/**
 * Fungsi untuk menyensor bagian belakang nominal transaksi.
 * Contoh: 1234567.89 menjadi 1.234.XXX
 * @param float $amount Jumlah transaksi.
 * @return string Nominal yang sudah disensor.
 */
function censorGlobalAmountDisplay($amount) {
    $amount_str = number_format($amount, 0, '', ''); // Format tanpa desimal, tanpa pemisah ribuan: "1234567"
    
    // Ganti 3 digit terakhir dengan 'XXX'
    if (strlen($amount_str) > 3) {
        $censored_part = substr($amount_str, 0, -3) . 'XXX';
    } else {
        $censored_part = 'XXX';    
    }
    
    $full_nominal_str = number_format($amount, 2, ',', '.');    
    $comma_pos = strpos($full_nominal_str, ',');
    
    $integer_part_formatted = ($comma_pos !== false) ? substr($full_nominal_str, 0, $comma_pos) : $full_nominal_str;

    if (strlen($integer_part_formatted) > 3) {
        $last_dot_pos = strrpos($integer_part_formatted, '.');    
        
        if ($last_dot_pos !== false && (strlen($integer_part_formatted) - $last_dot_pos - 1) >= 3) {
             $censored_final = substr($integer_part_formatted, 0, $last_dot_pos + 1) . 'XXX';
        } elseif (strlen($integer_part_formatted) >= 3) {
            $censored_final = substr($integer_part_formatted, 0, -3) . 'XXX';
        } else {
            $censored_final = 'XXX';    
        }
    } else {
        $censored_final = 'XXX';    
    }
    
    return 'Rp ' . $censored_final;
}

/**
 * Fungsi untuk menyensor nomor rekening.
 * Menampilkan 2 digit pertama dan 2 digit terakhir, sisanya dengan 'X'.
 * @param string $account_number Nomor rekening.
 * @return string Nomor rekening yang sudah disensor.
 */
function censorAccountNumberDisplay($account_number) {
    if (empty($account_number)) {
        return 'XXX-XXX-XXX'; // Placeholder jika kosong
    }
    $length = strlen($account_number);
    if ($length <= 4) {
        return str_repeat('X', $length); // Sensor penuh jika terlalu pendek
    }
    return substr($account_number, 0, 2) . str_repeat('X', $length - 4) . substr($account_number, -2);
}

// Ambil riwayat transaksi keseluruhan situs (dianonimkan, semua status)
$global_transactions = [];
try {
    // Ambil semua transaksi, termasuk bank_name, account_number, account_name
    $stmt = $pdo->prepare("SELECT type, amount, status, created_at, bank_name, account_number, account_name FROM transactions ORDER BY created_at DESC LIMIT 10");    
    $stmt->execute();
    $global_transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching global transactions for claims.php: " . $e->getMessage());
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
    error_log("Error fetching all approved claims for marquee in claims.php: " . $e->getMessage());
}

// Bangun konten marquee
// Sensor username untuk "Selamat Datang"
$censored_username_welcome = substr(htmlspecialchars($username), 0, 1) . str_repeat('*', strlen(htmlspecialchars($username)) - 1);
$marquee_content_parts = ["Selamat Datang, " . $censored_username_welcome . "!"];

// Tambahkan transaksi global ke marquee dengan pewarnaan
foreach ($global_transactions as $transaction) {
    $transaction_color_class = '';
    if ($transaction['type'] === 'deposit') {
        $transaction_color_class = 'text-green-400'; // Hijau untuk deposit
    } elseif ($transaction['type'] === 'withdraw') {
        $transaction_color_class = 'text-red-400'; // Merah untuk withdraw
    }
    
    // Sensor nama akun pengirim
    $censored_account_name = htmlspecialchars($transaction['account_name'] ?? 'Anonim');
    if (strlen($censored_account_name) > 3) {
        $censored_account_name = substr($censored_account_name, 0, 1) . str_repeat('*', strlen($censored_account_name) - 2) . substr($censored_account_name, -1);
    } else {
        $censored_account_name = str_repeat('*', strlen($censored_account_name));
    }


    $transaction_text = ucfirst($transaction['type']) . " " . censorGlobalAmountDisplay($transaction['amount']) . " oleh " . $censored_account_name;
    if ($transaction['type'] === 'deposit' && !empty($transaction['bank_name'])) {
        $transaction_text .= " via " . htmlspecialchars($transaction['bank_name']);
    }
    $transaction_text .= " (No. Rek: " . censorAccountNumberDisplay($transaction['account_number'] ?? '') . ")"; // Tambahkan nomor rekening disensor
    $marquee_content_parts[] = "<span class=\"{$transaction_color_class}\">{$transaction_text}</span>";
}

// Tambahkan semua klaim yang disetujui ke marquee dengan pewarnaan
foreach ($all_approved_claims_for_marquee as $claim) {
    // Sensor username pengklaim
    $censored_claim_username = htmlspecialchars($claim['username']);
    if (strlen($censored_claim_username) > 3) {
        $censored_claim_username = substr($censored_claim_username, 0, 1) . str_repeat('*', strlen($censored_claim_username) - 2) . substr($censored_claim_username, -1);
    } else {
        $censored_claim_username = str_repeat('*', strlen($censored_claim_username));
    }

    $claim_text = "Tugas '" . htmlspecialchars($claim['product_name']) . "' berhasil diklaim oleh " . $censored_claim_username . " (" . censorGlobalAmountDisplay($claim['claim_amount']) . ")";
    $marquee_content_parts[] = "<span class=\"text-yellow-400\">{$claim_text}</span>"; // Kuning untuk klaim disetujui
}

$final_marquee_text = implode(' | ', $marquee_content_parts);


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

    /* Gaya untuk notifikasi Toast */
    .toast-notification {
        position: fixed;
        top: 20px; /* Jarak dari atas */
        right: 20px; /* Jarak dari kanan */
        padding: 15px 25px;
        border-radius: 8px;
        color: white;
        font-weight: bold;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        display: none; /* Sembunyikan secara default */
        opacity: 0;
        transition: opacity 0.5s ease-in-out;
    }

    .toast-notification.show {
        display: block;
        opacity: 1;
    }

    .toast-notification.success {
        background-color: #4CAF50; /* Green */
    }

    .toast-notification.error {
        background-color: #F44336; /* Red */
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

    <!-- NOTIFIKASI TOAST -->
    <div id="toastNotification" class="toast-notification"></div>

    <!-- Bagian Pencarian Produk -->
    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Cari Produk/Tugas</h2>
        <form action="claims.php" method="GET" class="flex flex-col sm:flex-row gap-4 items-center justify-center">
            <input type="text" name="search" placeholder="Cari berdasarkan nama atau deskripsi produk..."
                   class="shadow-sm appearance-none border rounded-lg w-full sm:w-2/3 py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit"    
                    class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 flex items-center justify-center">
                <i class="fas fa-search mr-2"></i> Cari
            </button>
            <?php if (!empty($search_query)): ?>
                <a href="claims.php" class="w-full sm:w-auto bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 flex items-center justify-center">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- Bagian Daftar Produk/Tugas -->
    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Daftar Produk Tersedia</h2>
        <?php if (empty($products)): ?>
            <p class="text-gray-600 text-center">Tidak ada produk/tugas yang tersedia sesuai kriteria Anda saat ini.</p>
        <?php else: ?>
            <div id="product-cards-container-claims" class="grid grid-cols-2 xs:grid-cols-3 sm:grid-cols-3 lg:grid-cols-4 gap-2 md:gap-4 mb-4">
                <?php foreach ($products as $product): ?>
                    <div class="product-card-claims bg-gray-50 p-2 xs:p-2.5 sm:p-3 rounded-lg shadow-sm flex flex-col justify-between text-center text-xs xs:text-sm">
                        <div>
                            <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://placehold.co/120x80/CCCCCC/666666?text=No+Image') ?>"    
                                 alt="<?= htmlspecialchars($product['name']) ?>"    
                                 class="w-full h-16 xs:h-20 sm:h-24 object-cover rounded-md mb-2 mx-auto"
                                 onerror="this.onerror=null;this.src='https://placehold.co/120x80/CCCCCC/666666?text=No+Image';">
                            <h3 class="font-semibold text-gray-900 mb-1 leading-snug line-clamp-2"><?= htmlspecialchars($product['name']) ?></h3>    
                            <p class="text-gray-700 mb-2 line-clamp-3 leading-snug text-[0.6rem] xs:text-xs"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                            <p class="font-bold text-blue-600 leading-snug">Harga: Rp <?= number_format($product['product_price'], 2, ',', '.') ?></p>
                            <p class="text-green-600 leading-snug">Komisi: <?= number_format($product['commission_percentage'] * 100, 0) ?>%</p>
                            <p class="text-purple-600 mb-2 leading-snug">Poin: <?= number_format($product['points_awarded'], 0) ?></p>
                        </div>
                        <form action="process_claim.php" method="POST" class="w-full mt-auto">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                            <input type="hidden" name="current_page" value="<?= htmlspecialchars($current_page) ?>">
                            <input type="hidden" name="search_query" value="<?= htmlspecialchars($search_query) ?>">
                            <button type="submit"    
                                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1.5 px-2 xs:py-2 xs:px-3 text-xs xs:text-sm rounded-lg transition duration-200 mb-2">
                                Klaim Tugas
                            </button>
                        </form>
                        <!-- Tombol Ajukan Cicil -->
                        <button onclick="confirmInstallmentRequest(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= $product['product_price'] ?>)"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-2 xs:py-2 xs:px-3 text-xs xs:text-sm rounded-lg shadow-md transition duration-200">
                            Ajukan Cicil
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Navigasi Paginasi PHP (untuk Desktop/Tablet) -->
            <?php if ($total_pages > 1): ?>
                <nav class="hidden sm:flex items-center justify-between px-4 py-3 sm:px-6">
                    <div>
                        <p class="text-sm text-gray-700">
                            Menampilkan
                            <span class="font-medium"><?= $offset + 1 ?></span>
                            sampai
                            <span class="font-medium"><?= min($offset + $items_per_page_desktop, $total_products) ?></span>
                            dari
                            <span class="font-medium"><?= $total_products ?></span>
                            tugas
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <a href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"    
                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $current_page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                                <span class="sr-only">Previous</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"    
                                   aria-current="page" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?= $i === $current_page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            <a href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"    
                               class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $current_page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                                <span class="sr-only">Next</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </nav>
                    </div>
                </nav>
            <?php endif; ?>

            <!-- Navigasi Paginasi Mobile (Client-side JS) -->
            <nav id="claims-mobile-pagination-nav" class="flex items-center justify-between px-4 py-3 sm:hidden" style="display: none;">
                <button id="claims-mobile-prev-btn" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 opacity-50 cursor-not-allowed">
                    Previous
                </button>
                <span id="claims-mobile-page-info" class="text-sm text-gray-700">Page 1 of 1</span>
                <button id="claims-mobile-next-btn" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </button>
            </nav>

        <?php endif; ?>
    </section>
</main>

<!-- Modal Konfirmasi Pengajuan Cicilan (Ditambahkan ke claims.php) -->
<div id="confirmInstallmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Konfirmasi Pengajuan Cicilan</h3>
        <p class="text-gray-700 mb-4">Anda akan mengajukan cicilan untuk tugas "<strong id="modalProductName"></strong>" seharga <strong id="modalProductPrice"></strong>.</p>
        <p class="text-sm text-red-600 mb-4">Setelah diajukan, ini akan menunggu persetujuan admin.</p>
        <form id="installmentRequestForm" action="process_installment_request.php" method="POST">
            <input type="hidden" name="product_id" id="modalProductId">
            <input type="hidden" name="original_product_price" id="modalOriginalProductPrice">
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeConfirmModal()"    
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
                <button type="submit" name="submit_installment"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">Ya, Ajukan</button>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>

<!-- Sertakan script JS untuk paginasi mobile khusus claims -->
<script src="js/claims_pagination_mobile.js"></script>

<script>
    // Fungsionalitas Notifikasi Toast
    document.addEventListener('DOMContentLoaded', function() {
        const toastNotification = document.getElementById('toastNotification');
        const message = "<?= $message ?>";
        const type = "<?= $message_type ?>";

        if (message) {
            toastNotification.textContent = message;
            toastNotification.classList.add('show', type);

            // Sembunyikan notifikasi setelah 5 detik
            setTimeout(() => {
                toastNotification.classList.remove('show');
                // Hapus kelas tipe setelah transisi selesai untuk persiapan pesan berikutnya
                toastNotification.addEventListener('transitionend', function handler() {
                    toastNotification.classList.remove('success', 'error');
                    toastNotification.removeEventListener('transitionend', handler);
                });
            }, 5000);
        }
    });

    // Fungsi untuk membuka modal konfirmasi pengajuan cicilan (sama seperti di installments.php)
    function confirmInstallmentRequest(productId, productName, productPrice) {
        document.getElementById('modalProductId').value = productId;
        document.getElementById('modalProductName').textContent = productName;
        document.getElementById('modalProductPrice').textContent = 'Rp ' + parseFloat(productPrice).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('modalOriginalProductPrice').value = productPrice;
        document.getElementById('confirmInstallmentModal').classList.remove('hidden');
    }

    // Fungsi untuk menutup modal konfirmasi pengajuan cicilan
    function closeConfirmModal() {
        document.getElementById('confirmInstallmentModal').classList.add('hidden');
    }

    // Tutup modal jika klik di luar area modal
    window.onclick = function(event) {
        const confirmModal = document.getElementById('confirmInstallmentModal');
        if (event.target == confirmModal) {
            closeConfirmModal();
        }
    }
</script>
