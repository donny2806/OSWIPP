<?php
// admin_dashboard.php
// Halaman dashboard utama untuk admin.
// Menampilkan ringkasan dan tautan navigasi ke fitur manajemen lainnya.
// Ditambahkan fungsionalitas text-to-speech untuk pembaruan ringkasan sistem.

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Periksa apakah pengguna sudah login dan apakah dia admin
if (!isset($_SESSION['user_id'])) {
    // Jika belum login, arahkan ke halaman login
    header('Location: index.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$is_admin = false;
$admin_username = 'Admin';

try {
    $stmt = $pdo->prepare("SELECT username, is_admin FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();

    if ($user_data && $user_data['is_admin']) {
        $is_admin = true;
        $admin_username = htmlspecialchars($user_data['username']);
    } else {
        // Jika bukan admin, arahkan kembali ke dashboard pengguna atau halaman utama
        header('Location: dashboard.php'); // Atau index.php
        exit();
    }
} catch (PDOException $e) {
    error_log("Error checking admin status: " . $e->getMessage());
    // Fallback atau redirect ke halaman error
    header('Location: index.php'); // Redirect ke login jika ada masalah otentikasi
    exit();
}

// Ambil beberapa statistik ringkasan untuk dashboard admin
// Nilai awal di sini akan 0, dan akan diperbarui oleh JavaScript secara real-time
$total_users = 0;
$pending_deposits = 0;
$pending_withdrawals = 0;
$pending_claims = 0;
$pending_installments = 0;
$incoming_messages = 0; // Variabel baru untuk pesan masuk
$total_installments = 0; // Variabel baru untuk total cicilan (jumlah user yang belum lunas)

// Logika pengambilan data ringkasan di PHP telah dipindahkan ke admin_get_summary_data.php
// Pastikan admin_get_summary_data.php menghitung 'incoming_messages' dengan query seperti:
// SELECT COUNT(*) FROM chats WHERE receiver_id = [admin_id_anda] AND is_read_by_admin = 0;
// Atau jika pesan ditujukan untuk admin secara umum (receiver_id NULL), sesuaikan query.
// PENTING: Untuk 'total_installments', pastikan admin_get_summary_data.php menghitung:
// SELECT COUNT(DISTINCT user_id) FROM installments WHERE status = 'approved' AND remaining_amount > 0;
// Ini akan menghitung jumlah user unik yang masih memiliki sisa pembayaran cicilan yang disetujui.

/**
 * Fungsi untuk menyensor bagian belakang nominal transaksi.
 * Contoh: 1234567.89 menjadi Rp 1.234.XXX
 * @param float $amount Jumlah transaksi.
 * @return string Nominal yang sudah disensor.
 */
function censorGlobalAmountDisplay($amount) {
    // Ambil bagian integer dari amount
    $integer_part = floor($amount);
    $amount_str = (string)$integer_part; // Konversi ke string

    $length = strlen($amount_str);

    if ($length <= 3) {
        // Jika kurang dari atau sama dengan 3 digit, sensor semua digit dengan 'X'
        return 'Rp ' . str_repeat('X', $length);
    } else {
        // Sensor 3 digit terakhir dengan 'XXX'
        $prefix = substr($amount_str, 0, $length - 3);
        // Format bagian prefix dengan pemisah ribuan
        $formatted_prefix = number_format((float)$prefix, 0, ',', '.');
        
        return 'Rp ' . $formatted_prefix . '.XXX';
    }
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

/**
 * Censors a name to show first and last character with asterisks in between.
 * Examples: "John Doe" -> "J****e", "Alice" -> "A***e", "Bob" -> "B*b", "Me" -> "M*"
 * @param string $name The name to censor.
 * @return string The censored name.
 */
function censorName($name) {
    $name = (string)($name ?? ''); // Pastikan itu string, default ke kosong jika null
    $length = mb_strlen($name, 'UTF-8');

    if ($length === 0) {
        return 'Anonim'; // Atau placeholder lain yang sesuai
    } elseif ($length === 1) {
        return mb_substr($name, 0, 1, 'UTF-8') . '*'; // Contoh: 'A' menjadi 'A*'
    } elseif ($length === 2) {
        return mb_substr($name, 0, 1, 'UTF-8') . '*'; // Contoh: 'AB' menjadi 'A*'
    } else { // length >= 3
        return mb_substr($name, 0, 1, 'UTF-8') . str_repeat('*', max(0, $length - 2)) . mb_substr($name, -1, 1, 'UTF-8');
    }
}


// Ambil riwayat transaksi keseluruhan situs (dianonimkan, semua status) untuk marquee
$global_transactions = [];
try {
    $stmt = $pdo->prepare("SELECT type, amount, status, created_at, bank_name, account_number, account_name FROM transactions ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $global_transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching global transactions for admin_dashboard.php marquee: " . $e->getMessage());
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
    error_log("Error fetching all approved claims for admin_dashboard.php marquee: " . $e->getMessage());
}

// Bangun konten marquee
// Sensor username admin untuk "Selamat Datang"
$censored_admin_username_welcome = censorName($admin_username); // Menggunakan fungsi censorName yang lebih baik
$marquee_content_parts = ["Selamat Datang, Admin " . $censored_admin_username_welcome . "!"];

// Tambahkan transaksi global ke marquee dengan pewarnaan
foreach ($global_transactions as $transaction) {
    $transaction_color_class = '';
    if ($transaction['type'] === 'deposit') {
        $transaction_color_class = 'text-green-400'; // Hijau untuk deposit
    } elseif ($transaction['type'] === 'withdraw') {
        $transaction_color_class = 'text-red-400'; // Merah untuk withdraw
    }
    
    // Gunakan fungsi censorName yang lebih baik
    $censored_account_name = censorName($transaction['account_name'] ?? 'Anonim');


    $transaction_text = ucfirst($transaction['type']) . " " . censorGlobalAmountDisplay($transaction['amount']) . " oleh " . $censored_account_name;
    if ($transaction['type'] === 'deposit' && !empty($transaction['bank_name'])) {
        $transaction_text .= " via " . htmlspecialchars($transaction['bank_name']);
    }
    $transaction_text .= " (No. Rek: " . censorAccountNumberDisplay($transaction['account_number'] ?? '') . ")"; // Tambahkan nomor rekening disensor
    $marquee_content_parts[] = "<span class=\"{$transaction_color_class}\">{$transaction_text}</span>";
}

// Tambahkan semua klaim yang disetujui ke marquee dengan pewarnaan
foreach ($all_approved_claims_for_marquee as $claim) {
    // Gunakan fungsi censorName yang lebih baik
    $censored_claim_username = censorName($claim['username']);

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
    <!-- Marquee yang dirampingkan dan bergaya, kini dengan flicker-animation -->
    <div class="bg-gray-900 border border-gray-700 text-white px-4 py-2 mb-6 rounded-full shadow-lg overflow-hidden flex items-center justify-center flicker-animation">
        <h4 class="font-semibold text-lg text-center whitespace-nowrap overflow-hidden">
            <marquee behavior="scroll" direction="left" scrollamount="4" class="inline-block py-0.5">
                <?= $final_marquee_text ?>
            </marquee>
        </h4>
    </div>

    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Dashboard Admin</h1>

    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Ringkasan Sistem</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6 text-center">
            <!-- Total Pengguna -->
            <a href="admin_manage_accounts.php" class="bg-blue-50 p-5 rounded-lg shadow-sm block hover:bg-blue-100 transition duration-200">
                <i class="fas fa-users text-blue-600 text-3xl mb-2"></i>
                <p class="text-xl font-semibold text-gray-700">Total Pengguna</p>
                <p id="totalUsers" class="text-3xl font-bold text-blue-700 mt-1"><?= $total_users ?></p>
            </a>
            <!-- Deposit Tertunda -->
            <a href="admin_manage_transactions.php?search=&status=pending&type=deposit" class="bg-yellow-50 p-5 rounded-lg shadow-sm block hover:bg-yellow-100 transition duration-200">
                <i class="fas fa-coins text-yellow-600 text-3xl mb-2"></i>
                <p class="text-xl font-semibold text-gray-700">Deposit Tertunda</p>
                <p id="pendingDeposits" class="text-3xl font-bold text-yellow-700 mt-1"><?= $pending_deposits ?></p>
            </a>
            <!-- Penarikan Tertunda -->
            <a href="admin_manage_transactions.php?search=&status=pending&type=withdraw" class="bg-orange-50 p-5 rounded-lg shadow-sm block hover:bg-orange-100 transition duration-200">
                <i class="fas fa-hand-holding-usd text-orange-600 text-3xl mb-2"></i>
                <p class="text-xl font-semibold text-gray-700">Penarikan Tertunda</p>
                <p id="pendingWithdrawals" class="text-3xl font-bold text-orange-700 mt-1"><?= $pending_withdrawals ?></p>
            </a>
            <!-- Klaim Tugas Tertunda -->
            <a href="admin_manage_claims.php?search=&status=pending" class="bg-purple-50 p-5 rounded-lg shadow-sm block hover:bg-purple-100 transition duration-200">
                <i class="fas fa-tasks text-purple-600 text-3xl mb-2"></i>
                <p class="text-xl font-semibold text-gray-700">Klaim Tugas Tertunda</p>
                <p id="pendingClaims" class="text-3xl font-bold text-purple-700 mt-1"><?= $pending_claims ?></p>
            </a>
            <!-- Kartu untuk Klaim Cicil Tertunda -->
            <a href="admin_manage_installments.php?search=&status=pending" class="bg-red-50 p-5 rounded-lg shadow-sm block hover:bg-red-100 transition duration-200">
                <i class="fas fa-file-invoice-dollar text-red-600 text-3xl mb-2"></i>
                <p class="text-xl font-semibold text-gray-700">Klaim Cicil Tertunda</p>
                <p id="pendingInstallments" class="text-3xl font-bold text-red-700 mt-1"><?= $pending_installments ?></p>
            </a>
            <!-- Kartu baru untuk Total Cicilan (diperbarui untuk menampilkan jumlah user dengan cicilan belum lunas) -->
            <a href="admin_total_unpaid_installments.php" class="bg-indigo-50 p-5 rounded-lg shadow-sm block hover:bg-indigo-100 transition duration-200">
                <i class="fas fa-users-slash text-indigo-600 text-3xl mb-2"></i> <!-- Icon yang relevan -->
                <p class="text-xl font-semibold text-gray-700">Jumlah User Belum Lunas (Disetujui)</p>
                <p id="totalInstallments" class="text-3xl font-bold text-indigo-700 mt-1"><?= $total_installments ?></p>
            </a>
            <!-- Kartu baru untuk Pesan Masuk -->
            <a href="admin_manage_chat.php" class="bg-cyan-50 p-5 rounded-lg shadow-sm block hover:bg-cyan-100 transition duration-200">
                <i class="fas fa-envelope text-cyan-600 text-3xl mb-2"></i> <!-- Icon yang relevan untuk pesan -->
                <p class="text-xl font-semibold text-gray-700">Pesan Masuk</p>
                <p id="incomingMessages" class="text-3xl font-bold text-cyan-700 mt-1"><?= $incoming_messages ?></p>
            </a>
        </div>
    </section>

    <section class="bg-white p-6 rounded-xl shadow-md">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Panel Administrasi</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <a href="admin_manage_accounts.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-5 px-6 rounded-lg shadow-lg text-center flex flex-col items-center justify-center transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105">
                <i class="fas fa-users-cog text-4xl mb-3"></i>
                <span>Kelola Akun Pengguna</span>
            </a>
            <a href="admin_manage_products.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-5 px-6 rounded-lg shadow-lg text-center flex flex-col items-center justify-center transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105">
                <i class="fas fa-cubes text-4xl mb-3"></i>
                <span>Kelola Produk/Tugas</span>
            </a>
            <a href="admin_manage_transactions.php" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-5 px-6 rounded-lg shadow-lg text-center flex flex-col items-center justify-center transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105">
                <i class="fas fa-exchange-alt text-4xl mb-3"></i>
                <span>Kelola Transaksi</span>
            </a>
            <a href="admin_manage_claims.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-5 px-6 rounded-lg shadow-lg text-center flex flex-col items-center justify-center transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105">
                <i class="fas fa-check-double text-4xl mb-3"></i>
                <span>Kelola Klaim Tugas</span>
            </a>
            <!-- Link untuk Kelola Cicilan -->
            <a href="admin_manage_installments.php" class="bg-pink-600 hover:bg-pink-700 text-white font-bold py-5 px-6 rounded-lg shadow-lg text-center flex flex-col items-center justify-center transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105">
                <i class="fas fa-hand-holding-heart text-4xl mb-3"></i> <!-- Icon yang relevan dengan cicilan -->
                <span>Kelola Cicilan</span>
            </a>
            <a href="admin_manage_chat.php" class="bg-red-600 hover:bg-red-700 text-white font-bold py-5 px-6 rounded-lg shadow-lg text-center flex flex-col items-center justify-center transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105">
                <i class="fas fa-comments text-4xl mb-3"></i>
                <span>Kelola Live Chat</span>
            </a>
        </div>
    </section>
</main>

<script>
let previousSummaryData = null; // Variabel untuk menyimpan data ringkasan sebelumnya

document.addEventListener('DOMContentLoaded', function() {
    // Fungsi untuk membuat sistem berbicara
    function speak(text) {
        if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'id-ID'; // Set bahasa ke Bahasa Indonesia
            utterance.rate = 1; // Kecepatan bicara (default 1)
            utterance.pitch = 1; // Nada suara (default 1)
            window.speechSynthesis.speak(utterance);
        } else {
            console.warn('SpeechSynthesis API tidak didukung di browser ini.');
        }
    }

    // Fungsi untuk mengambil dan memperbarui data ringkasan
    function updateSummaryData() {
        fetch('admin_get_summary_data.php')
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('Network response was not ok. Status: ' + response.status + '. Response Text: ' + text);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                console.error('Server returned an error:', data.error, 'Message:', data.message);
                return;
            }

            // Jika ini bukan fetch pertama, bandingkan data
            if (previousSummaryData) {
                if (data.pending_deposits > previousSummaryData.pending_deposits) {
                    speak('Deposit tertunda');
                }
                if (data.pending_withdrawals > previousSummaryData.pending_withdrawals) {
                    speak('Penarikan tertunda');
                }
                if (data.pending_claims > previousSummaryData.pending_claims) {
                    speak('Klaim tugas tertunda');
                }
                if (data.pending_installments > previousSummaryData.pending_installments) {
                    speak('Klaim cicil tertunda');
                }
                if (data.total_installments > previousSummaryData.total_installments) {
                    speak('Pelanggan mencicil');
                }
                if (data.incoming_messages > previousSummaryData.incoming_messages) {
                    speak('Ada pesan masuk');
                }
            }
            
            // Perbarui setiap elemen dengan data terbaru
            document.getElementById('totalUsers').innerText = data.total_users;
            document.getElementById('pendingDeposits').innerText = data.pending_deposits;
            document.getElementById('pendingWithdrawals').innerText = data.pending_withdrawals;
            document.getElementById('pendingClaims').innerText = data.pending_claims;
            document.getElementById('pendingInstallments').innerText = data.pending_installments;
            document.getElementById('incomingMessages').innerText = data.incoming_messages;
            document.getElementById('totalInstallments').innerText = data.total_installments; 

            // Simpan data yang baru diambil sebagai data sebelumnya untuk perbandingan berikutnya
            previousSummaryData = data;

        })
        .catch(error => {
            console.error('There was a problem with the fetch operation:', error);
        });
    }

    // Panggil fungsi segera setelah DOM dimuat
    updateSummaryData();

    // Panggil fungsi setiap 10 detik (Anda bisa menyesuaikan interval ini)
    setInterval(updateSummaryData, 10000); // 10000 milidetik = 10 detik
});
</script>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>
