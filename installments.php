<?php
// installments.php
// Halaman ini memungkinkan pengguna untuk mengajukan cicilan tugas
// dan melihat daftar cicilan mereka (pending, approved, rejected, completed).

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
$message = '';
$message_type = ''; // 'success' atau 'error'

// Ambil pesan dari sesi (flash message)
if (isset($_SESSION['flash_message'])) {
    $message = htmlspecialchars($_SESSION['flash_message']);
    $message_type = htmlspecialchars($_SESSION['flash_message_type'] ?? 'info'); // Default ke info jika tidak diset
    // Hapus pesan dari sesi agar tidak muncul lagi saat refresh
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}


// --- Data untuk Menentukan Status Produk ---

// Ambil ID produk yang sedang dalam klaim (pending/approved) oleh siapa pun
$claimed_product_ids = [];
try {
    $stmt = $pdo->prepare("SELECT product_id FROM claims WHERE status IN ('pending', 'approved')");
    $stmt->execute();
    $claimed_product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    error_log("Error fetching claimed product IDs: " . $e->getMessage());
}

// Ambil status cicilan pengguna saat ini untuk setiap produk (semua status)
$user_installment_statuses = [];
// Variabel ini digunakan untuk badge status, bukan untuk pie chart lagi.
$has_rejected_installments_for_badge = false;
try {
    $stmt = $pdo->prepare("SELECT id, product_id, status, remaining_amount FROM installments WHERE user_id = ?");
    $stmt->execute([$current_user_id]);
    while ($row = $stmt->fetch()) {
        $user_installment_statuses[$row['product_id']] = [
            'status' => $row['status'],
            'installment_id' => $row['id'],
            'remaining_amount' => $row['remaining_amount']
        ];
        // Periksa jika ada cicilan yang ditolak untuk kebutuhan badge (jika ada logika badge lain yang butuh ini)
        if ($row['status'] === 'rejected') {
            $has_rejected_installments_for_badge = true;
        }
    }
} catch (PDOException | Exception $e) {
    error_log("Error fetching user installment statuses: " . $e->getMessage());
}

// Ambil semua produk yang "aktif" untuk ditampilkan di bagian pengajuan cicilan
// Produk yang diklaim oleh orang lain dan produk yang sudah memiliki cicilan apapun oleh user ini tidak ditampilkan
$all_available_products = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.image_url, p.product_price, p.commission_percentage, p.points_awarded
        FROM products p
        WHERE NOT EXISTS (
            SELECT 1 FROM claims c
            WHERE c.product_id = p.id AND c.status IN ('pending', 'approved')
        )
        AND NOT EXISTS (
            SELECT 1 FROM installments i
            WHERE i.product_id = p.id AND i.user_id = ?
        )
        ORDER BY p.id DESC
    ");
    $stmt->execute([$current_user_id]);
    $all_available_products = $stmt->fetchAll();
} catch (PDOException | Exception $e) {
    error_log("Error fetching all active products for installment section: " . $e->getMessage());
    $message = "Gagal memuat daftar tugas yang tersedia untuk pengajuan.";
    $message_type = 'error';
}


// --- Ambil daftar cicilan yang diajukan oleh pengguna ini ---
// Dipisah menjadi 'active' (pending, approved, rejected) dan 'completed')
$user_active_installments = [];
$user_completed_installments = [];
$installments_for_payment_dropdown = []; // Data khusus untuk JS (hanya yang 'approved' dan belum lunas)

// Variabel baru untuk pie chart
$total_approved_original_price = 0;
$total_pending_original_price = 0;
$total_remaining_amount_approved = 0; // Hanya untuk status 'approved'
$total_rejected_amount = 0; // Jumlah harga asli dari cicilan yang ditolak

try {
    $stmt = $pdo->prepare("
        SELECT i.id, p.name AS product_name, p.image_url, i.original_product_price, i.remaining_amount,
               i.status, i.requested_at, u.username AS admin_approver, i.approved_at, i.completed_at, i.points_awarded
        FROM installments i
        JOIN products p ON i.product_id = p.id
        LEFT JOIN users u ON i.approved_by = u.id
        WHERE i.user_id = ?
        ORDER BY i.requested_at DESC
    ");
    $stmt->execute([$current_user_id]);
    $all_user_installments = $stmt->fetchAll();

    foreach ($all_user_installments as $inst) {
        if ($inst['status'] === 'completed') {
            $user_completed_installments[] = $inst;
        } else {
            // Ini adalah cicilan aktif (pending, approved, atau rejected)
            $user_active_installments[] = $inst; // Tetap kumpulkan semua untuk tabel

            if ($inst['status'] === 'approved') {
                $total_approved_original_price += $inst['original_product_price'];
                $total_remaining_amount_approved += $inst['remaining_amount'];
            } elseif ($inst['status'] === 'pending') {
                $total_pending_original_price += $inst['original_product_price'];
            } elseif ($inst['status'] === 'rejected') {
                $total_rejected_amount += $inst['original_product_price'];
            }
        }

        // Isi data untuk dropdown pembayaran (hanya yang 'approved' dan belum lunas)
        if ($inst['status'] === 'approved' && $inst['remaining_amount'] > 0) {
            $installments_for_payment_dropdown[] = [
                'id' => $inst['id'],
                'product_name' => $inst['product_name'],
                'remaining_amount' => $inst['remaining_amount'],
                'image_url' => $inst['image_url']
            ];
        }
    }
} catch (PDOException | Exception $e) {
    error_log("Error fetching user installments for table: " . $e->getMessage());
    $message = "Gagal memuat daftar cicilan Anda.";
    $message_type = 'error';
}

// Hitung ulang total jumlah yang sudah dibayar untuk grup approved
$total_paid_amount_approved = $total_approved_original_price - $total_remaining_amount_approved;

// Perbarui total original price active untuk ringkasan di bawah chart
$total_original_price_active_installments_display = $total_approved_original_price + $total_pending_original_price + $total_rejected_amount;
$total_paid_amount_active_installments_display = $total_paid_amount_approved;
$total_remaining_amount_active_installments_display = $total_remaining_amount_approved;


// Ambil riwayat pembayaran cicilan untuk pengguna ini
$installment_payment_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            t.id AS transaction_id,
            t.amount,
            t.created_at,
            t.status,
            t.description,
            SUBSTRING_INDEX(SUBSTRING_INDEX(t.description, 'ID ', -1), ' ', 1) AS installment_id_from_desc,
            p.name AS product_name
        FROM transactions t
        LEFT JOIN installments i ON t.description LIKE CONCAT('%cicilan ID ', i.id, '%') AND t.user_id = i.user_id
        LEFT JOIN products p ON i.product_id = p.id
        WHERE t.user_id = ?
          AND t.type = 'withdraw'
          AND t.description LIKE 'Pembayaran cicilan ID %'
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$current_user_id]);
    $installment_payment_history = $stmt->fetchAll();
} catch (PDOException | Exception $e) {
    error_log("Error fetching installment payment history: " . $e->getMessage());
    // Anda bisa set $message atau log ini sebagai kesalahan terpisah
}


// Ambil saldo pengguna saat ini (tetap diperlukan untuk modal pembayaran)
$user_balance = 0;
try {
    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_balance_data = $stmt->fetch();
    if ($user_balance_data) {
        $user_balance = $user_balance_data['balance'];
    }
} catch (PDOException | Exception $e) {
    error_log("Error fetching user balance: " . $e->getMessage());
    $message = "Gagal memuat saldo Anda.";
    $message_type = 'error';
}


// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<!-- Include Chart.js library via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>

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

    /* Styling untuk chart tanpa efek 3D */
    .chart-wrapper {
        position: relative;
        height: 250px; /* Pastikan tinggi konsisten */
        width: 100%;
        max-width: 384px; /* max-w-sm */
        margin-left: auto;
        margin-right: auto;
    }

    .chart-canvas {
        /* Hapus properti transform dan box-shadow */
        /* transform: rotateX(20deg); */
        /* box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.2); */
        transition: none; /* Hapus transisi jika tidak ada efek yang berubah */
    }

    .chart-canvas:hover {
        /* Hapus efek hover yang terkait dengan 3D */
        /* transform: rotateX(15deg) scale(1.02); */
        /* box-shadow: 0px 15px 30px rgba(0, 0, 0, 0.3); */
    }

    /* Gaya untuk dropdown dengan indikator panah kustom */
    .custom-select-arrow {
        -webkit-appearance: none; /* Hapus panah default di WebKit browsers */
        -moz-appearance: none;    /* Hapus panah default di Firefox */
        appearance: none;         /* Hapus panah default di browser modern */
        background-image: url('data:image/svg+xml;utf8,<svg fill="%234A5568" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>'); /* Panah SVG */
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1.5em; /* Sesuaikan ukuran panah */
        padding-right: 2.5rem; /* Ruang untuk panah agar tidak menimpa teks */
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

    <!-- Kontainer pesan JavaScript global baru -->
    <div id="js-message-container" class="w-full mx-auto max-w-2xl mb-4"></div>

</main>
<main class="flex-grow container mx-auto p-4 md:p-8">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Cicil Tugas</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg
                    <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>"
                    role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Bagian Ringkasan Cicilan Aktif (Pie Chart) -->
    <section id="summary-active-installments-section" class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Ringkasan Cicilan Aktif</h2>
        <?php if (empty($user_active_installments)): ?>
            <p class="text-gray-600 text-center">Anda belum memiliki pengajuan cicilan aktif.</p>
        <?php else: ?>
            <div class="mb-8 p-4 bg-gray-50 rounded-lg shadow-sm grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">Status Pembayaran Cicilan (Disetujui)</h3>
                    <div class="chart-wrapper">
                        <canvas id="approvedInstallmentDonutChart" class="chart-canvas"></canvas>
                    </div>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">Status Pengajuan Cicilan (Pending & Ditolak)</h3>
                    <div class="chart-wrapper">
                        <canvas id="pendingRejectedInstallmentPieChart" class="chart-canvas"></canvas>
                    </div>
                </div>
                <div class="md:col-span-2 mt-4 text-center">
                    <p class="text-gray-700">Total Harga Awal Cicilan Aktif: <strong class="text-indigo-600">Rp <?= number_format($total_original_price_active_installments_display, 2, ',', '.') ?></strong></p>
                    <p class="text-gray-700">Total Sudah Dibayar (Disetujui): <strong class="text-green-600">Rp <?= number_format($total_paid_amount_active_installments_display, 2, ',', '.') ?></strong></p>
                    <p class="text-gray-700">Total Sisa Pembayaran (Disetujui): <strong class="text-red-600">Rp <?= number_format($total_remaining_amount_active_installments_display, 2, ',', '.') ?></strong></p>
                    <?php if ($total_pending_original_price > 0): ?>
                        <p class="text-gray-700">Total Cicilan Pending: <strong class="text-purple-600">Rp <?= number_format($total_pending_original_price, 2, ',', '.') ?></strong></p>
                    <?php endif; ?>
                    <?php if ($total_rejected_amount > 0): ?>
                        <p class="text-gray-700">Total Cicilan Ditolak: <strong class="text-yellow-800">Rp <?= number_format($total_rejected_amount, 2, ',', '.') ?></strong></p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Tombol "Bayar Cicilan" global di bawah pie chart -->
            <div class="text-center mt-8">
                <?php if (!empty($installments_for_payment_dropdown)): ?>
                    <button onclick="openGlobalPaymentModal()"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-200">
                        Bayar Cicilan Anda
                    </button>
                <?php else: ?>
                    <p class="text-gray-500">Tidak ada cicilan yang disetujui dan belum lunas untuk dibayar.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Bagian Ajukan Cicilan Tugas Baru -->
    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Ajukan Cicilan Tugas Baru</h2>

        <!-- Kotak Pencarian -->
        <div class="mb-6">
            <input type="text" id="newInstallmentSearch" placeholder="Cari tugas..."
                    class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <span id="newInstallmentSearchResultCount" class="text-sm text-gray-600 mt-2 block"></span>
        </div>

        <?php if (empty($all_available_products)): ?>
            <p class="text-gray-600 text-center">Tidak ada tugas yang tersedia untuk pengajuan cicilan baru.</p>
        <?php else: ?>
            <div id="new-installment-cards-container" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($all_available_products as $product): ?>
                    <div class="new-installment-card bg-gray-50 border border-gray-200 rounded-lg p-3 flex flex-col items-center text-center shadow-sm">
                        <!-- Gambar Produk di bagian "Ajukan Cicilan Tugas Baru" -->
                        <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://placehold.co/80x80/CCCCCC/666666?text=No') ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="w-20 h-20 object-cover mb-3"
                             onerror="this.onerror=null;this.src='https://placehold.co/80x80/CCCCCC/666666?text=No';">
                        <h3 class="text-md font-semibold text-gray-900 mb-1 line-clamp-2"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="text-gray-700 text-xs mb-2"><?= htmlspecialchars($product['description']) ?></p>
                        <p class="text-md font-bold text-indigo-600 mb-3">Rp <?= number_format($product['product_price'], 2, ',', '.') ?></p>

                        <?php
                        $product_status_text = 'Tersedia untuk Cicilan Baru';
                        $product_status_class = 'text-gray-800';
                        $action_button_html = '<button onclick="confirmInstallmentRequest(' . $product['id'] . ', \'' . htmlspecialchars($product['name']) . '\', ' . $product['product_price'] . ')"
                                 class="mt-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-1.5 px-3 rounded-lg text-sm shadow-md transition duration-200">Ajukan Cicil</button>';
                        ?>
                        <p class="text-xs <?= $product_status_class ?> mb-3"><?= $product_status_text ?></p>
                        <?= $action_button_html ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Navigasi Paginasi Mobile untuk Ajukan Cicilan Tugas Baru -->
            <nav id="new-installments-mobile-pagination-nav" class="flex items-center justify-between px-4 py-3 sm:hidden" style="display: none;">
                <button id="new-installments-mobile-prev-btn" class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 opacity-50 cursor-not-allowed">
                    Previous
                </button>
                <span id="new-installments-mobile-page-info" class="text-sm text-gray-700">Page 1 of 1</span>
                <button id="new-installments-mobile-next-btn" class="ml-3 relative inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </button>
                </nav>
            <?php endif; ?>
    </section>

    <!-- Bagian Cicilan Anda (Aktif: Tabel) - Kontainer terpisah, di bawah Ajukan Cicilan Tugas Baru -->
    <section id="user-active-installments-table-section" class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Daftar Cicilan Anda (Aktif)</h2>
        <?php if (empty($user_active_installments)): ?>
            <p class="text-gray-600 text-center">Anda belum memiliki pengajuan cicilan aktif.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                    <thead class="bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Cicilan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gambar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Tugas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Awal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa Pembayaran</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diajukan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diproses Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($user_active_installments as $installment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($installment['id']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <img src="<?= htmlspecialchars($installment['image_url'] ?? 'https://placehold.co/50x50/CCCCCC/666666?text=No') ?>"
                                         alt="<?= htmlspecialchars($installment['product_name']) ?>"
                                         class="w-12 h-12 object-cover"
                                         onerror="this.onerror=null;this.src='https://placehold.co/50x50/CCCCCC/666666?text=No';">
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($installment['product_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($installment['original_product_price'], 2, ',', '.') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold <?= $installment['status'] === 'approved' && $installment['remaining_amount'] > 0 ? 'text-red-600' : 'text-gray-800' ?>">
                                    Rp <?= number_format($installment['remaining_amount'], 2, ',', '.') ?>
                                    <?php if ($installment['status'] === 'approved' && $installment['remaining_amount'] > 0): ?>
                                        <p class="text-xs text-gray-500">(Harus Dilunasi)</p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $status_color = '';
                                        switch ($installment['status']) {
                                            case 'pending': $status_color = 'bg-purple-100 text-purple-800'; break; // Changed to purple
                                            case 'approved': $status_color = 'bg-green-100 text-green-800'; break;
                                            case 'rejected': $status_color = 'bg-yellow-200 text-yellow-900'; break;
                                            case 'completed': $status_color = 'bg-blue-100 text-blue-800'; break;
                                            default: $status_color = 'bg-gray-100 text-gray-800'; break;
                                        }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                        <?= ucfirst($installment['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($installment['requested_at'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($installment['admin_approver'] ?? 'N/A') ?>
                                    <?php if ($installment['approved_at']): ?>
                                        <br><span class="text-xs"><?= date('d M Y H:i', strtotime($installment['approved_at'])) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tombol "Bayar Cicilan" global -->
            <div class="text-center mt-8">
                <?php if (!empty($installments_for_payment_dropdown)): ?>
                    <button onclick="openGlobalPaymentModal()"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-200">
                        Bayar Cicilan Anda
                    </button>
                <?php else: ?>
                    <p class="text-gray-500">Tidak ada cicilan yang disetujui dan belum lunas untuk dibayar.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Bagian Riwayat Pembayaran Cicilan -->
    <section id="installment-history-section" class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Riwayat Pembayaran Cicilan Anda</h2>

        <?php if (empty($installment_payment_history)): ?>
            <p class="text-gray-600 text-center">Anda belum memiliki riwayat pembayaran cicilan.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                    <thead class="bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Transaksi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Cicilan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tugas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Bayar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Bayar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($installment_payment_history as $payment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($payment['transaction_id']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($payment['installment_id_from_desc'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($payment['product_name'] ?? 'Tugas Tidak Ditemukan') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($payment['amount'], 2, ',', '.') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $status_color = '';
                                        switch ($payment['status']) {
                                            case 'pending': $status_color = 'bg-yellow-100 text-yellow-800'; break;
                                            case 'completed': $status_color = 'bg-green-100 text-green-800'; break;
                                            case 'rejected': $status_color = 'bg-red-100 text-red-800'; break;
                                            default: $status_color = 'bg-gray-100 text-gray-800'; break;
                                        }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                        <?= ucfirst($payment['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($payment['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Bagian Cicilan Selesai (Completed) -->
    <section id="user-completed-installments-section" class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Cicilan Selesai</h2>

        <?php if (empty($user_completed_installments)): ?>
            <p class="text-gray-600 text-center">Anda belum memiliki cicilan yang lunas.</p>
        <?php else: ?>
            <div id="completed-installment-cards-container" class="grid grid-cols-2 lg:grid-cols-2 gap-6">
                <?php foreach ($user_completed_installments as $installment): ?>
                    <div class="completed-installment-card bg-gray-50 border border-gray-200 rounded-lg p-3 flex flex-col items-center text-center shadow-sm">
                        <!-- Gambar Produk di bagian "Cicilan Selesai" -->
                        <img src="<?= htmlspecialchars($installment['image_url'] ?? 'https://placehold.co/80x80/CCCCCC/666666?text=No') ?>"
                            alt="<?= htmlspecialchars($installment['product_name']) ?>"
                            class="w-20 h-20 object-cover mb-3"
                            onerror="this.onerror=null;this.src='https://placehold.co/80x80/CCCCCC/666666?text=No';">
                        <h3 class="text-md font-semibold text-gray-900 mb-1 line-clamp-2"><?= htmlspecialchars($installment['product_name']) ?></h3>
                        <p class="text-gray-700 text-xs mb-2">Harga Awal: Rp <?= number_format($installment['original_product_price'], 2, ',', '.') ?></p>
                        <p class="text-sm font-bold text-blue-600 mb-1">Lunas: <?= date('d M Y', strtotime($installment['completed_at'])) ?></p>
                        <p class="text-sm font-bold text-purple-600 mb-3">Poin: <?= number_format($installment['points_awarded'], 0) ?></p>
                        <button disabled class="mt-auto bg-gray-500 text-white font-bold py-1.5 px-3 rounded-lg text-sm cursor-not-allowed">Selesai</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Navigasi Paginasi Mobile untuk Cicilan Selesai -->
            <nav id="completed-installments-mobile-pagination-nav" class="flex items-center justify-between px-4 py-3 sm:hidden" style="display: none;">
                <button id="completed-installments-mobile-prev-btn" class="relative inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 opacity-50 cursor-not-allowed">
                    Previous
                </button>
                <span id="completed-installments-mobile-page-info" class="text-sm text-gray-700">Page 1 of 1</span>
                <button id="completed-installments-mobile-next-btn" class="ml-3 relative inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </button>
                </nav>
        <?php endif; ?>
    </section>

</main>

<!-- Modal Konfirmasi Pengajuan Cicilan -->
<div id="confirmInstallmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
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

<!-- Modal Pembayaran Cicilan -->
<div id="paymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <!-- Judul "Bayar Cicilan" rata tengah -->
        <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">Bayar Cicilan</h3>
        <form id="paymentForm" action="process_installment_payment.php" method="POST">
            <div class="mb-4">
                <label for="selectInstallmentToPay" class="block text-gray-700 text-sm font-semibold mb-2">Pilih Cicilan</label>
                <select id="selectInstallmentToPay" name="installment_id" required
                        class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent custom-select-arrow">
                    <!-- Options akan diisi oleh JavaScript -->
                </select>
            </div>

            <!-- Gambar Produk di bawah dropdown di modal pembayaran -->
            <div class="mb-4 text-center">
                <img id="paymentModalProductImage" src="https://placehold.co/100x100/CCCCCC/666666?text=No+Image"
                            alt="Gambar Produk"
                            class="w-24 h-24 object-cover mx-auto border border-gray-300"
                            onerror="this.onerror=null;this.src='https://placehold.co/100x100/CCCCCC/666666?text=No+Image';">
            </div>

            <!-- Detail "Tugas", "Sisa Pembayaran", "Saldo Anda" rata kiri-kanan -->
            <div class="flex justify-between items-center mb-2">
                <p class="text-gray-700">Tugas:</p>
                <strong id="paymentModalProductName"></strong>
            </div>
            <div class="flex justify-between items-center mb-4">
                <p class="text-gray-700">Sisa Pembayaran:</p>
                <strong id="paymentModalRemainingAmount"></strong>
            </div>
            <div class="flex justify-between items-center mb-4">
                <p class="text-gray-700">Saldo Anda:</p>
                <strong id="paymentModalUserBalance">Rp <?= number_format($user_balance, 2, ',', '.') ?></strong>
            </div>

            <div class="mb-4">
                <label for="paymentAmountInput" class="block text-gray-700 text-sm font-semibold mb-2">Jumlah Pembayaran (Rp)</label>
                <input type="number"
                        id="paymentAmountInput"
                        name="payment_amount"
                        step="any"
                        min="1"
                        required
                        class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Masukkan nominal">
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closePaymentModal()"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
                <button type="submit"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-200">Bayar</button>
            </div>
        </form>
    </div>
</div>


<script>
    // Ambil data cicilan yang disetujui dari PHP untuk digunakan di JavaScript
    const installmentsForPayment = <?= json_encode($installments_for_payment_dropdown) ?>;
    const userBalance = parseFloat(<?= json_encode($user_balance) ?>);

    // Data yang lebih spesifik untuk pie chart dari PHP
    const totalApprovedOriginalPrice = parseFloat(<?= json_encode($total_approved_original_price) ?>);
    const totalPendingOriginalPrice = parseFloat(<?= json_encode($total_pending_original_price) ?>);
    const totalRemainingAmountApproved = parseFloat(<?= json_encode($total_remaining_amount_approved) ?>);
    const totalRejectedAmount = parseFloat(<?= json_encode($total_rejected_amount) ?>);

    const totalPaidAmountApproved = totalApprovedOriginalPrice - totalRemainingAmountApproved;

    function confirmInstallmentRequest(productId, productName, productPrice) {
        document.getElementById('modalProductId').value = productId;
        document.getElementById('modalProductName').textContent = productName;
        document.getElementById('modalProductPrice').textContent = 'Rp ' + parseFloat(productPrice).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('modalOriginalProductPrice').value = productPrice;
        document.getElementById('confirmInstallmentModal').classList.remove('hidden');
    }

    function closeConfirmModal() {
        document.getElementById('confirmInstallmentModal').classList.add('hidden');
    }

    // Fungsi untuk menampilkan pesan temporer di bagian atas halaman (global)
    function displayGlobalJsMessage(msg, type) {
        const jsMessageContainer = document.getElementById('js-message-container');
        let messageDiv = document.createElement('div');
        messageDiv.textContent = msg;
        messageDiv.className = 'p-4 mb-4 text-sm rounded-lg temp-js-message';

        if (type === 'success') {
            messageDiv.classList.add('bg-green-100', 'text-green-800', 'border', 'border-green-400');
        } else if (type === 'error') {
            messageDiv.classList.add('bg-red-100', 'text-red-800', 'border', 'border-red-400');
        } else if (type === 'warning') { // For the "no installments" message
            messageDiv.classList.add('bg-yellow-100', 'text-yellow-800', 'border', 'border-yellow-400');
        } else {
            messageDiv.classList.add('bg-blue-100', 'text-blue-800', 'border', 'border-blue-400');
        }

        // Clear any existing temporary JS messages first
        jsMessageContainer.querySelectorAll('.temp-js-message').forEach(el => el.remove());
        jsMessageContainer.appendChild(messageDiv);

        setTimeout(() => messageDiv.remove(), 5000); // Remove after 5 seconds
    }

    // Fungsi untuk membuka modal pembayaran global
    function openGlobalPaymentModal() {
        const selectElement = document.getElementById('selectInstallmentToPay');
        selectElement.innerHTML = ''; // Bersihkan opsi sebelumnya

        // Filter cicilan yang aktif (approved dan sisa > 0)
        const activeInstallments = installmentsForPayment.filter(inst => inst.remaining_amount > 0);

        if (activeInstallments.length === 0) {
            // Tampilkan pesan di kontainer pesan global
            displayGlobalJsMessage('Tidak ada cicilan yang disetujui dan belum lunas untuk dibayar.', 'warning');
            return; // Cegah modal terbuka
        }

        activeInstallments.forEach(inst => {
            const option = document.createElement('option');
            option.value = inst.id;
            option.textContent = inst.product_name;
            selectElement.appendChild(option);
        });

        document.getElementById('paymentModalUserBalance').textContent = 'Rp ' + userBalance.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        updatePaymentModalDetails();

        selectElement.onchange = updatePaymentModalDetails;

        document.getElementById('paymentModal').classList.remove('hidden');
    }

    // Fungsi untuk memperbarui detail pembayaran di modal berdasarkan pilihan dropdown
    function updatePaymentModalDetails() {
        const selectElement = document.getElementById('selectInstallmentToPay');
        const selectedId = parseInt(selectElement.value);
        const selectedInstallment = installmentsForPayment.find(inst => inst.id === selectedId);
        const paymentModalProductImage = document.getElementById('paymentModalProductImage');


        if (selectedInstallment) {
            document.getElementById('paymentModalProductName').textContent = selectedInstallment.product_name;
            document.getElementById('paymentModalRemainingAmount').textContent = 'Rp ' + parseFloat(selectedInstallment.remaining_amount).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            paymentModalProductImage.src = selectedInstallment.image_url || 'https://placehold.co/100x100/CCCCCC/666666?text=No+Image';

            const paymentAmountInput = document.getElementById('paymentAmountInput');
            paymentAmountInput.value = Math.min(selectedInstallment.remaining_amount, userBalance);
            paymentAmountInput.max = Math.min(selectedInstallment.remaining_amount, userBalance);
        } else {
            document.getElementById('paymentModalProductName').textContent = '';
            document.getElementById('paymentModalRemainingAmount').textContent = 'Rp 0,00';
            document.getElementById('paymentAmountInput').value = '';
            document.getElementById('paymentAmountInput').max = 0;
            paymentModalProductImage.src = 'https://placehold.co/100x100/CCCCCC/666666?text=No+Image';
        }
    }

    // Fungsi untuk menutup modal pembayaran
    function closePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
        window.location.reload();
    }

    // Validasi pembayaran cicilan sisi klien sebelum submit modal pembayaran
    document.getElementById('paymentForm').addEventListener('submit', function(event) {
        const paymentAmountInput = document.getElementById('paymentAmountInput');
        const paymentAmount = parseFloat(paymentAmountInput.value);

        const selectElement = document.getElementById('selectInstallmentToPay');
        const selectedId = parseInt(selectElement.value);
        const selectedInstallment = installmentsForPayment.find(inst => inst.id === selectedId);

        if (!selectedInstallment) {
            displayFormMessage('Silakan pilih cicilan yang ingin dibayar.', 'error');
            event.preventDefault();
            return;
        }

        const remainingAmount = selectedInstallment.remaining_amount;

        if (isNaN(paymentAmount) || paymentAmount <= 0) {
            displayFormMessage('Jumlah pembayaran harus angka positif.', 'error');
            event.preventDefault();
            return;
        }

        if (paymentAmount > remainingAmount) {
            displayFormMessage('Jumlah pembayaran tidak boleh melebihi sisa cicilan (Rp ' + remainingAmount.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ').', 'error');
            event.preventDefault();
            return;
        }

        if (paymentAmount > userBalance) {
            displayFormMessage('Saldo Anda tidak cukup untuk melakukan pembayaran ini. Saldo Anda: Rp ' + userBalance.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '.','error');
            event.preventDefault();
            return;
        }
    });

    // Fungsi untuk menampilkan pesan di dalam form pembayaran
    function displayFormMessage(msg, type) {
        let messageDiv = document.getElementById('paymentFormMessage');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.id = 'paymentFormMessage';
            messageDiv.classList.add('p-3', 'mb-4', 'text-sm', 'rounded-lg');
            const formElements = document.getElementById('paymentForm');
            formElements.insertBefore(messageDiv, formElements.firstChild);
        }
        messageDiv.textContent = msg;
        messageDiv.className = ''; // Reset classes
        messageDiv.classList.add('p-3', 'mb-4', 'text-sm', 'rounded-lg');
        if (type === 'success') {
            messageDiv.classList.add('bg-green-100', 'text-green-800', 'border', 'border-green-400');
        } else if (type === 'error') {
            messageDiv.classList.add('bg-red-100', 'text-red-800', 'border', 'border-red-400');
        } else {
            messageDiv.classList.add('bg-blue-100', 'text-blue-800', 'border', 'border-blue-400');
        }
        setTimeout(() => messageDiv.remove(), 5000);
    }


    // Tutup modal jika klik di luar area modal
    window.onclick = function(event) {
        const confirmModal = document.getElementById('confirmInstallmentModal');
        const paymentModal = document.getElementById('paymentModal');

        if (event.target == confirmModal) {
            closeConfirmModal();
        }
        if (event.target == paymentModal) {
            closePaymentModal();
        }
    }

    // Inisialisasi Pie Chart
    document.addEventListener('DOMContentLoaded', function() {
        // Doughnut Chart for Approved Installments (Paid vs Remaining)
        const approvedCtx = document.getElementById('approvedInstallmentDonutChart');
        const approvedChartContainer = approvedCtx ? approvedCtx.parentElement : null;

        if (approvedChartContainer) {
            const approvedDataExists = (totalPaidAmountApproved > 0 || totalRemainingAmountApproved > 0);

            if (!approvedDataExists) {
                approvedChartContainer.innerHTML = `
                    <div class="relative w-full h-48 flex items-center justify-center bg-gray-100 border border-gray-200 rounded-lg">
                        <p class="text-center text-gray-500 text-lg font-semibold z-10 p-4">
                            Tidak ada data cicilan disetujui untuk ditampilkan.
                        </p>
                    </div>
                `;
            } else {
                new Chart(approvedCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Sudah Dibayar (Disetujui)', 'Sisa Pembayaran (Disetujui)'],
                        datasets: [{
                            data: [totalPaidAmountApproved, totalRemainingAmountApproved],
                            backgroundColor: ['#4CAF50', '#F44336'], // Green, Red
                            borderColor: ['#ffffff', '#ffffff'],
                            borderWidth: 2,
                            offset: [0, 0]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: { size: 14 }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) label += ': ';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = (value / total * 100).toFixed(2);
                                        return label + 'Rp ' + value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Pie Chart for Pending and Rejected Installments
        const pendingRejectedCtx = document.getElementById('pendingRejectedInstallmentPieChart');
        const pendingRejectedChartContainer = pendingRejectedCtx ? pendingRejectedCtx.parentElement : null;

        if (pendingRejectedChartContainer) {
            const pendingRejectedDataExists = (totalPendingOriginalPrice > 0 || totalRejectedAmount > 0);

            if (!pendingRejectedDataExists) {
                pendingRejectedChartContainer.innerHTML = `
                    <div class="relative w-full h-48 flex items-center justify-center bg-gray-100 border border-gray-200 rounded-lg">
                        <p class="text-center text-gray-500 text-lg font-semibold z-10 p-4">
                            Tidak ada data cicilan pending/ditolak untuk ditampilkan.
                        </p>
                    </div>
                `;
            } else {
                new Chart(pendingRejectedCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Pending', 'Ditolak'],
                        datasets: [{
                            data: [totalPendingOriginalPrice, totalRejectedAmount],
                            backgroundColor: ['#800080', '#FFD700'], // Purple, Gold
                            borderColor: ['#ffffff', '#ffffff'],
                            borderWidth: 2,
                            offset: [0, 30] // Offset for 'Ditolak'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: { size: 14 }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) label += ': ';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = (value / total * 100).toFixed(2);
                                        return label + 'Rp ' + value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    });

    // Script untuk Paginasi Mobile Cicilan Selesai
    document.addEventListener('DOMContentLoaded', function() {
        const completedInstallmentCardsContainer = document.getElementById('completed-installment-cards-container');
        if (!completedInstallmentCardsContainer) {
            return;
        }

        const completedInstallmentCards = Array.from(completedInstallmentCardsContainer.querySelectorAll('.completed-installment-card'));
        const mobilePaginationNavCompleted = document.getElementById('completed-installments-mobile-pagination-nav');
        const mobilePrevBtnCompleted = document.getElementById('completed-installments-mobile-prev-btn');
        const mobileNextBtnCompleted = document.getElementById('completed-installments-mobile-next-btn');
        const mobilePageInfoCompleted = document.getElementById('completed-installments-mobile-page-info');

        const itemsPerPageMobileCompleted = 2;
        let currentPageMobileCompleted = 1;
        let totalMobilePagesCompleted = Math.ceil(completedInstallmentCards.length / itemsPerPageMobileCompleted);
        if (completedInstallmentCards.length === 0) totalMobilePagesCompleted = 0; // Ensure 0 if no items

        // Fungsi untuk menampilkan kartu produk yang relevan untuk halaman saat ini di mobile
        function showMobilePageCompleted(page) {
            currentPageMobileCompleted = Math.max(1, Math.min(page, totalMobilePagesCompleted > 0 ? totalMobilePagesCompleted : 1)); // Ensure page doesn't go below 1 if no pages

            const startIndex = (currentPageMobileCompleted - 1) * itemsPerPageMobileCompleted;
            const endIndex = startIndex + itemsPerPageMobileCompleted;

            completedInstallmentCards.forEach((card, index) => {
                if (index >= startIndex && index < endIndex) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });

            // Perbarui status tombol navigasi
            if (mobilePrevBtnCompleted) {
                mobilePrevBtnCompleted.disabled = currentPageMobileCompleted === 1 || totalMobilePagesCompleted === 0;
                mobilePrevBtnCompleted.classList.toggle('opacity-50', currentPageMobileCompleted === 1 || totalMobilePagesCompleted === 0);
                mobilePrevBtnCompleted.classList.toggle('cursor-not-allowed', currentPageMobileCompleted === 1 || totalMobilePagesCompleted === 0);
            }
            if (mobileNextBtnCompleted) {
                mobileNextBtnCompleted.disabled = currentPageMobileCompleted === totalMobilePagesCompleted || totalMobilePagesCompleted === 0;
                mobileNextBtnCompleted.classList.toggle('opacity-50', currentPageMobileCompleted === totalMobilePagesCompleted || totalMobilePagesCompleted === 0);
                mobileNextBtnCompleted.classList.toggle('cursor-not-allowed', currentPageMobileCompleted === totalMobilePagesCompleted || totalMobilePagesCompleted === 0);
            }

            // Perbarui info halaman
            if (mobilePageInfoCompleted) {
                mobilePageInfoCompleted.textContent = `Page ${completedInstallmentCards.length === 0 ? 0 : currentPageMobileCompleted} of ${totalMobilePagesCompleted}`;
            }
        }

        // Fungsi untuk memeriksa ukuran layar dan mengaktifkan/menonaktifkan paginasi mobile
        function checkScreenSizeAndTogglePaginationCompleted() {
            const isMobile = window.innerWidth < 768;

            if (isMobile && completedInstallmentCards.length > itemsPerPageMobileCompleted) {
                if (mobilePaginationNavCompleted) mobilePaginationNavCompleted.style.display = 'flex';
                const phpPagination = document.querySelector('#user-completed-installments-section .grid:not(#completed-installment-cards-container)');
                if (phpPagination) phpPagination.style.display = 'none';

                showMobilePageCompleted(currentPageMobileCompleted);
            } else {
                if (mobilePaginationNavCompleted) mobilePaginationNavCompleted.style.display = 'none';
                completedInstallmentCards.forEach(card => card.style.display = 'flex');
            }

            if (completedInstallmentCards.length <= itemsPerPageMobileCompleted && mobilePaginationNavCompleted) {
                mobilePaginationNavCompleted.style.display = 'none';
            }
        }

        // Event Listeners untuk tombol navigasi mobile
        if (mobilePrevBtnCompleted) {
            mobilePrevBtnCompleted.addEventListener('click', function() {
                showMobilePageCompleted(currentPageMobileCompleted - 1);
            });
        }

        if (mobileNextBtnCompleted) {
            mobileNextBtnCompleted.addEventListener('click', function() {
                showMobilePageCompleted(currentPageMobileCompleted + 1);
            });
        }

        // Jalankan saat DOMContentLoaded dan pada resize
        checkScreenSizeAndTogglePaginationCompleted();
        window.addEventListener('resize', checkScreenSizeAndTogglePaginationCompleted);
    });


    // Script untuk Paginasi Mobile Ajukan Cicilan Tugas Baru dan Fungsi Pencarian
    document.addEventListener('DOMContentLoaded', function() {
        const newInstallmentCardsContainer = document.getElementById('new-installment-cards-container');
        if (!newInstallmentCardsContainer) {
            return;
        }

        const allNewInstallmentCards = Array.from(newInstallmentCardsContainer.querySelectorAll('.new-installment-card'));
        const mobilePaginationNavNew = document.getElementById('new-installments-mobile-pagination-nav');
        const mobilePrevBtnNew = document.getElementById('new-installments-mobile-prev-btn');
        const mobileNextBtnNew = document.getElementById('new-installments-mobile-next-btn');
        const mobilePageInfoNew = document.getElementById('new-installments-mobile-page-info');
        const newInstallmentSearchInput = document.getElementById('newInstallmentSearch');
        const searchResultCountSpan = document.getElementById('newInstallmentSearchResultCount');

        const itemsPerPageMobileNew = 6;
        let currentPageMobileNew = 1;
        let filteredCards = [];

        // Function to filter cards based on search term
        function filterCards() {
            const searchTerm = newInstallmentSearchInput.value.toLowerCase();
            filteredCards = allNewInstallmentCards.filter(card => {
                const productName = card.querySelector('h3').textContent.toLowerCase();
                const productDescription = card.querySelector('p:nth-of-type(1)').textContent.toLowerCase();
                return productName.includes(searchTerm) || productDescription.includes(searchTerm);
            });
            currentPageMobileNew = 1;
            renderCards();
        }

        // Function to render cards based on current filter and pagination state
        function renderCards() {
            const isMobile = window.innerWidth < 768;

            if (searchResultCountSpan) {
                searchResultCountSpan.textContent = `${filteredCards.length} item ditemukan.`;
            }

            if (isMobile && filteredCards.length > itemsPerPageMobileNew) {
                if (mobilePaginationNavNew) mobilePaginationNavNew.style.display = 'flex';

                const startIndex = (currentPageMobileNew - 1) * itemsPerPageMobileNew;
                const endIndex = startIndex + itemsPerPageMobileNew;

                allNewInstallmentCards.forEach(card => card.style.display = 'none');
                filteredCards.slice(startIndex, endIndex).forEach(card => card.style.display = 'flex');

                updatePaginationNav();
            } else {
                if (mobilePaginationNavNew) mobilePaginationNavNew.style.display = 'none';

                // Display only filtered cards, not all cards, on desktop or when pagination is not active
                allNewInstallmentCards.forEach(card => card.style.display = 'none');
                filteredCards.forEach(card => card.style.display = 'flex');
            }
        }

        // Function to update pagination buttons and page info
        function updatePaginationNav() {
            const totalMobilePagesNew = Math.ceil(filteredCards.length / itemsPerPageMobileNew);
            const displayTotalPages = filteredCards.length === 0 ? 0 : totalMobilePagesNew;

            if (mobilePrevBtnNew) {
                mobilePrevBtnNew.disabled = currentPageMobileNew === 1 || filteredCards.length === 0;
                mobilePrevBtnNew.classList.toggle('opacity-50', currentPageMobileNew === 1 || filteredCards.length === 0);
                mobilePrevBtnNew.classList.toggle('cursor-not-allowed', currentPageMobileNew === 1 || filteredCards.length === 0);
            }
            if (mobileNextBtnNew) {
                mobileNextBtnNew.disabled = currentPageMobileNew === totalMobilePagesNew || filteredCards.length === 0;
                mobileNextBtnNew.classList.toggle('opacity-50', currentPageMobileNew === totalMobilePagesNew || filteredCards.length === 0);
                mobileNextBtnNew.classList.toggle('cursor-not-allowed', currentPageMobileNew === totalMobilePagesNew || filteredCards.length === 0);
            }

            if (mobilePageInfoNew) {
                mobilePageInfoNew.textContent = `Page ${filteredCards.length === 0 ? 0 : currentPageMobileNew} of ${displayTotalPages}`;
            }
        }

        // Event Listeners
        if (mobilePrevBtnNew) {
            mobilePrevBtnNew.addEventListener('click', function() {
                if (currentPageMobileNew > 1) {
                    currentPageMobileNew--;
                    renderCards();
                }
            });
        }

        if (mobileNextBtnNew) {
            mobileNextBtnNew.addEventListener('click', function() {
                const totalMobilePagesNew = Math.ceil(filteredCards.length / itemsPerPageMobileNew);
                if (currentPageMobileNew < totalMobilePagesNew) {
                    currentPageMobileNew++;
                    renderCards();
                }
            });
        }

        if (newInstallmentSearchInput) {
            newInstallmentSearchInput.addEventListener('input', filterCards);
        }

        // Initial render and on resize
        filterCards();
        window.addEventListener('resize', renderCards);
    });
</script>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>
