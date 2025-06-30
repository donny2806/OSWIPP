<?php
// withdraw.php
// Halaman ini memungkinkan pengguna untuk mengajukan penarikan saldo.

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
$username = $_SESSION['username'] ?? 'Pengguna'; // Ambil username dari sesi

$message = '';
$message_type = ''; // 'success' atau 'error'

// --- Tangani pesan dari sesi (Flash Message Pattern) ---
// Periksa apakah ada flash message dari sesi
if (isset($_SESSION['flash_message'])) {
    $message = htmlspecialchars($_SESSION['flash_message']);
    $message_type = $_SESSION['flash_message_type'] ?? 'info'; // Default ke 'info' jika tidak diset
    
    // Hapus flash message dari sesi agar tidak muncul lagi setelah refresh
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_type']);
}
// --- Akhir penanganan pesan dari sesi ---

// Ambil data pengguna untuk menampilkan saldo dan untuk username di marquee
$user_data = [];
try {
    $stmt = $pdo->prepare("SELECT balance, username FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();
    if (!$user_data) {
        session_destroy();
        header('Location: index.php');
        exit();
    }
    $username = $user_data['username']; // Update username from fetched data
} catch (PDOException $e) {
    error_log("Error fetching user data in withdraw.php: " . $e->getMessage());
    $message = "Terjadi kesalahan saat memuat data pengguna.";
    $message_type = 'error';
    $user_data = ['balance' => 0, 'username' => $_SESSION['username'] ?? 'Pengguna']; // Fallback
    $username = $user_data['username'];
}

// Tangani proses pengajuan penarikan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdraw'])) {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $account_name = trim($_POST['account_name']);

    if ($amount === false || $amount <= 0 || empty($bank_name) || empty($account_number) || empty($account_name)) {
        $_SESSION['flash_message'] = "Jumlah penarikan, nama bank, nomor rekening, dan nama pemilik rekening harus diisi dengan benar.";
        $_SESSION['flash_message_type'] = 'error';
        header("Location: withdraw.php"); // Redirect tanpa parameter
        exit();
    } elseif ($amount < 10000) { // Contoh minimal penarikan
        $_SESSION['flash_message'] = "Minimal penarikan adalah Rp 10.000.";
        $_SESSION['flash_message_type'] = 'error';
        header("Location: withdraw.php"); // Redirect tanpa parameter
        exit();
    } else {
        try {
            $pdo->beginTransaction();

            // Ambil saldo pengguna saat ini (dengan lock untuk menghindari race condition)
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$current_user_id]);
            $current_balance = $stmt->fetchColumn();

            if ($current_balance < $amount) {
                $_SESSION['flash_message'] = "Saldo Anda tidak mencukupi untuk penarikan ini.";
                $_SESSION['flash_message_type'] = 'error';
                $pdo->rollBack();
                header("Location: withdraw.php"); // Redirect tanpa parameter
                exit();
            } else {
                // Kurangi saldo pengguna
                $new_balance = $current_balance - $amount;
                $stmt_update_balance = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt_update_balance->execute([$new_balance, $current_user_id]);

                // Simpan transaksi penarikan ke database dengan status 'pending'
                $stmt_insert_transaction = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, bank_name, account_number, account_name) VALUES (?, 'withdraw', ?, 'pending', ?, ?, ?)");
                if ($stmt_insert_transaction->execute([$current_user_id, $amount, $bank_name, $account_number, $account_name])) {
                    $_SESSION['flash_message'] = "Pengajuan penarikan sebesar Rp " . number_format($amount, 2, ',', '.') . " berhasil diajukan. Menunggu persetujuan admin.";
                    $_SESSION['flash_message_type'] = 'success';
                    $pdo->commit();
                    header("Location: withdraw.php"); // Redirect tanpa parameter
                    exit(); // Penting: Hentikan eksekusi setelah redirect
                } else {
                    $_SESSION['flash_message'] = "Gagal mengajukan penarikan. Silakan coba lagi.";
                    $_SESSION['flash_message_type'] = 'error';
                    $pdo->rollBack();
                    header("Location: withdraw.php"); // Redirect tanpa parameter
                    exit();
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack(); // Rollback jika ada kesalahan
            error_log("Error processing withdraw transaction: " . $e->getMessage());
            $_SESSION['flash_message'] = "Terjadi kesalahan database: " . $e->getMessage();
            $_SESSION['flash_message_type'] = 'error';
            header("Location: withdraw.php"); // Redirect tanpa parameter
            exit();
        }
    }
}

// Ambil riwayat penarikan pengguna yang sedang login
$user_withdrawals = [];
try {
    $stmt = $pdo->prepare("SELECT id, amount, status, created_at, bank_name, account_number, account_name FROM transactions WHERE user_id = ? AND type = 'withdraw' ORDER BY created_at DESC");
    $stmt->execute([$current_user_id]);
    $user_withdrawals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching user withdrawals in withdraw.php: " . $e->getMessage());
    // Tidak menampilkan pesan error ke pengguna untuk ini, biarkan bagian kosong
}

/**
 * Fungsi untuk menyensor bagian belakang nominal transaksi.
 * Contoh: 1234567.89 menjadi 1.234.XXX
 * @param float $amount Jumlah transaksi.
 * @return string Nominal yang sudah disensor.
 */
function censorGlobalAmountDisplay($amount) {
    $amount_str = number_format($amount, 0, '', ''); // Format tanpa desimal, tanpa pemisah ribuan: "1234567"
    
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
 * Menampilkan 2 digit pertama dan 2 digit terakhir, sisanya diganti bintang.
 * @param string $accountNumber Nomor rekening asli.
 * @return string Nomor rekening yang disensor.
*/
function censorAccountNumber($accountNumber) {
    $len = strlen($accountNumber);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return substr($accountNumber, 0, 2) . str_repeat('*', $len - 4) . substr($accountNumber, -2);
}

// Ambil seluruh transaksi deposit/withdraw situs (dianonimkan)
$global_transactions = []; 
try {
    // Ambil semua transaksi, termasuk bank_name, account_number, account_name
    $stmt = $pdo->prepare("SELECT type, amount, status, created_at, bank_name, account_number, account_name FROM transactions ORDER BY created_at DESC LIMIT 10"); 
    $stmt->execute();
    $global_transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching global transactions for withdraw.php: " . $e->getMessage());
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
    error_log("Error fetching all approved claims for marquee in withdraw.php: " . $e->getMessage());
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
    if (!empty($transaction['bank_name'])) { // Bank name is applicable for both deposit and withdraw
        $transaction_text .= " via " . htmlspecialchars($transaction['bank_name']);
    }
    $transaction_text .= " (No. Rek: " . censorAccountNumber($transaction['account_number'] ?? '') . ")"; 
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
</style>

<main class="flex-grow container mx-auto p-4 md:p-8">
    <!-- Sitemap yang baru diposisikan dan dirampingkan -->
    <section class="sitemap-container">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="about_us.php">Tentang Kami</a></li>
            <li><a href="claims.php">Tugas</a></li>
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

    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Tarik Saldo (Withdraw)</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg 
                    <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>" 
                    role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="bg-white p-6 rounded-xl shadow-md mb-8 text-center">
        <h2 class="text-lg font-semibold text-gray-700">Saldo Anda Saat Ini</h2>
        <p class="text-3xl font-bold text-blue-600 mt-2">Rp <?= number_format($user_data['balance'], 2, ',', '.') ?></p>
    </section>

    <!-- Kontainer untuk Form Penarikan dan Riwayat Penarikan -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <!-- Form Pengajuan Penarikan Saldo (Kiri) -->
        <section class="bg-white p-6 rounded-xl shadow-md h-fit">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Ajukan Penarikan Saldo</h2>
            <form action="withdraw.php" method="POST" class="space-y-4">
                <div>
                    <label for="amount" class="block text-gray-700 text-sm font-semibold mb-2">Jumlah Penarikan (IDR)</label>
                    <input type="number" id="amount" name="amount" min="10000" step="1000" required 
                           class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                           placeholder="Minimum Rp 10.000">
                    <p class="text-xs text-gray-500 mt-1">Minimum penarikan Rp 10.000.</p>
                </div>
                <div>
                    <label for="bank_name" class="block text-gray-700 text-sm font-semibold mb-2">Nama Bank Tujuan</label>
                    <input type="text" id="bank_name" name="bank_name" required 
                           class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                           placeholder="Contoh: Bank Central Asia (BCA)">
                </div>
                <div>
                    <label for="account_number" class="block text-gray-700 text-sm font-semibold mb-2">Nomor Rekening Tujuan</label>
                    <input type="text" id="account_number" name="account_number" required 
                           class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                           placeholder="Contoh: 1234567890">
                </div>
                <div>
                    <label for="account_name" class="block text-gray-700 text-sm font-semibold mb-2">Nama Pemilik Rekening</label>
                    <input type="text" id="account_name" name="account_name" required 
                           class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                           placeholder="Contoh: Nama Lengkap Anda">
                </div>
                <button type="submit" name="submit_withdraw" 
                        class="w-full bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">
                    Ajukan Penarikan
                </button>
            </form>
        </section>

        <!-- Riwayat Penarikan Anda (Kanan) -->
        <section class="bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Riwayat Penarikan Anda</h2>
            <?php if (empty($user_withdrawals)): ?>
                <p class="text-gray-600 text-center">Anda belum memiliki riwayat penarikan.</p>
            <?php else: ?>
                <div class="overflow-y-auto max-h-[500px]">
                    <table class="min-w-full bg-white rounded-lg overflow-hidden">
                        <thead class="bg-gray-100 border-b border-gray-200 sticky top-0 bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bank Tujuan</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($user_withdrawals as $withdrawal): ?>
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($withdrawal['id']) ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($withdrawal['amount'], 2, ',', '.') ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">
                                        <?= htmlspecialchars($withdrawal['bank_name']) ?><br>
                                        <span class="text-xs text-gray-500">A.N.: <?= htmlspecialchars($withdrawal['account_name']) ?></span><br>
                                        <span class="text-xs text-gray-500">No. Rek: <?= htmlspecialchars($withdrawal['account_number']) ?></span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <?php
                                            $status_color = '';
                                            switch ($withdrawal['status']) {
                                                case 'pending': $status_color = 'bg-yellow-100 text-yellow-800'; break;
                                                case 'completed': $status_color = 'bg-green-100 text-green-800'; break;
                                                case 'rejected': $status_color = 'bg-red-100 text-red-800'; break;
                                                default: $status_color = 'bg-gray-100 text-gray-800'; break;
                                            }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                            <?= ucfirst($withdrawal['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($withdrawal['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Bagian Aktivitas Withdraw Situs Terbaru (Dianonimkan) -->
    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Aktivitas Withdraw Situs Terbaru (Anonim)</h2>
        <?php if (empty($global_transactions)): ?>
            <p class="text-gray-600 text-center">Belum ada aktivitas withdraw situs yang tercatat.</p>
        <?php else: ?>
            <div class="overflow-y-auto max-h-[400px]"> <!-- Scrollbar untuk riwayat global -->
                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                    <thead class="bg-gray-100 border-b border-gray-200 sticky top-0 bg-gray-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bank</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor Rekening</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($global_transactions as $transaction): ?>
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800"><?= censorGlobalAmountDisplay($transaction['amount']) ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($transaction['bank_name']) ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800"><?= censorAccountNumber($transaction['account_number']) ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($transaction['created_at'])) ?></td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <?php
                                        $status_color = '';
                                        switch ($transaction['status']) {
                                            case 'pending': $status_color = 'bg-yellow-100 text-yellow-800'; break;
                                            case 'completed': $status_color = 'bg-green-100 text-green-800'; break;
                                            case 'approved': $status_color = 'bg-green-100 text-green-800'; break;
                                            case 'rejected': $status_color = 'bg-red-100 text-red-800'; break;
                                            default: $status_color = 'bg-gray-100 text-gray-800'; break;
                                        }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                        <?= ucfirst($transaction['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>
