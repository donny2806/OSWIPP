<?php
// deposit.php
// Halaman ini memungkinkan pengguna untuk mengajukan deposit saldo.

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

$user_id = $_SESSION['user_id']; // Ambil user ID yang login
$username = $_SESSION['username'] ?? 'Pengguna'; // Ambil username dari sesi

$message = '';
$message_type = ''; // 'success' atau 'error'

// Tangani pesan dari process_deposit.php
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
}

// Ambil data pengguna lengkap untuk header (masih dibutuhkan oleh header.php, dan untuk username di marquee)
$user_data = [];
try {
    $stmt = $pdo->prepare("SELECT balance, points, membership_level, is_admin, username FROM users WHERE id = ?"); 
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    if (!$user_data) {
        session_destroy();
        header('Location: index.php');
        exit();
    }
    // Update username from fetched data in case it wasn't set in session or for accurate display
    $username = $user_data['username']; 
} catch (PDOException $e) {
    error_log("Error fetching user data in deposit.php: " . $e->getMessage());
    // Lanjutkan dengan nilai default jika ada kesalahan
    $user_data = ['balance' => 0, 'points' => 0, 'membership_level' => 'N/A', 'is_admin' => 0, 'username' => $_SESSION['username'] ?? 'Pengguna'];
    $username = $user_data['username']; // Use fallback username
}

// Ambil riwayat deposit pengguna
$user_deposits = [];
try {
    $stmt = $pdo->prepare("SELECT id, amount, status, bank_name, account_name, created_at, receipt_image_url FROM transactions WHERE user_id = ? AND type = 'deposit' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $user_deposits = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching user deposits in deposit.php: " . $e->getMessage());
    // Tidak mengubah pesan utama, tetapi bisa tambahkan log atau pesan terpisah jika ingin
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


// Ambil seluruh transaksi deposit situs (dianonimkan)
$global_deposits = [];
try {
    $stmt = $pdo->prepare("SELECT amount, status, created_at, bank_name, account_number, account_name FROM transactions WHERE type = 'deposit' AND (status = 'completed' OR status = 'approved') ORDER BY created_at DESC LIMIT 10"); // Hanya 10 transaksi terakhir yang sukses
    $stmt->execute();
    $global_deposits = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching global deposits in deposit.php: " . $e->getMessage());
    // Tidak menampilkan pesan error ke pengguna untuk ini.
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
    error_log("Error fetching all approved claims for marquee in deposit.php: " . $e->getMessage());
}

// Bangun konten marquee
// Sensor username untuk "Selamat Datang"
$censored_username_welcome = substr(htmlspecialchars($username), 0, 1) . str_repeat('*', strlen(htmlspecialchars($username)) - 1);
$marquee_content_parts = ["Selamat Datang, " . $censored_username_welcome . "!"];

// Tambahkan transaksi global ke marquee dengan pewarnaan
foreach ($global_deposits as $transaction) {
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
    $transaction_text .= " (No. Rek: " . censorAccountNumber($transaction['account_number'] ?? '') . ")"; // Diperbaiki: memanggil censorAccountNumber
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

    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Pengajuan Deposit Saldo</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg 
                    <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>" 
                    role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Informasi Rekening Bank Tujuan</h2>
        
        <!-- Modifikasi di bawah ini untuk perataan teks, textbox kuning, dan italic -->
        <!-- Perubahan: div "grid" dihilangkan, ditambahkan max-w-3xl dan mx-auto untuk centering -->
        <!-- Perubahan: text-yellow-800 diubah menjadi text-black -->
        <div class="bg-yellow-100 text-black p-4 rounded-lg italic text-center max-w-3xl mx-auto">
            <p class="mb-0">
                Untuk melakukan Deposit ke Rekening milik Deprintz, investor / pemodal harap menghubungi
                livechat kami di situs di tombol balon di perangkat anda. Anda harus melakukan 
                konfirmasi terlebih dahulu dengan Costumer Service resmi kami ataupun menghubungi 
                Contact Person milik perusahaan kami. Untuk mendapatkan aksesnya anda harus melakukan
                kontak / menghubungi dari balon livechat di perangkat anda.
            </p>
        </div>
        <!-- Akhir Modifikasi -->

    </section>

    <!-- Kontainer untuk Form dan Riwayat Transaksi -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <!-- Form Pengajuan Deposit (Kiri) -->
        <section class="bg-white p-6 rounded-xl shadow-md h-fit">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Formulir Pengajuan Deposit</h2>
            <form action="process_deposit.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="amount" class="block text-gray-700 text-sm font-semibold mb-2">Jumlah Deposit (IDR)</label>
                    <input type="number" id="amount" name="amount" min="10000" step="1000" required 
                           class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Contoh: 50000">
                    <p class="text-xs text-gray-500 mt-1">Minimal deposit Rp 10.000.</p>
                </div>
                
                <div>
                    <label for="user_bank_name" class="block text-gray-700 text-sm font-semibold mb-2">Nama Bank Anda</label>
                    <input type="text" id="user_bank_name" name="user_bank_name" required 
                           class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Contoh: BCA, Mandiri, BRI">
                </div>
                <div>
                    <label for="user_account_name" class="block text-gray-700 text-sm font-semibold mb-2">Nama Pemilik Rekening Anda</label>
                    <input type="text" id="user_account_name" name="user_account_name" required 
                           class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Contoh: Nama Anda Sesuai Rekening">
                </div>
                <div>
                    <label for="user_account_number" class="block text-gray-700 text-sm font-semibold mb-2">Nomor Rekening Anda</label>
                    <input type="text" id="user_account_number" name="user_account_number" required 
                           class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Contoh: 1234567890">
                </div>

                <div>
                    <label for="receipt_image" class="block text-gray-700 text-sm font-semibold mb-2">Unggah Bukti Transfer (Resi)</label>
                    <input type="file" id="receipt_image" name="receipt_image" accept="image/*" required
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-500 mt-1">Hanya gambar (JPG, PNG, GIF). Maks 5MB.</p>
                    <div id="receiptImagePreviewContainer" class="mt-2 hidden">
                        <p class="text-sm text-gray-700 mb-2">Pratinjau Resi:</p>
                        <img id="receiptImagePreview" src="" alt="Pratinjau Resi" class="w-48 h-auto object-cover rounded-md border border-gray-300">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                    Ajukan Deposit
                </button>
            </form>
        </section>

        <!-- Riwayat Deposit Anda (Kanan) -->
        <section class="bg-white p-6 rounded-xl shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Riwayat Deposit Anda</h2>
            <?php if (empty($user_deposits)): ?>
                <p class="text-gray-600 text-center">Anda belum memiliki riwayat deposit.</p>
            <?php else: ?>
                <div class="overflow-y-auto max-h-[500px]"> <!-- Tambah max-h dan overflow-y-auto untuk scroll -->
                    <table class="min-w-full bg-white rounded-lg overflow-hidden">
                        <thead class="bg-gray-100 border-b border-gray-200 sticky top-0 bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bank Pengirim</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($user_deposits as $deposit): ?>
                                <tr>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($deposit['id']) ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($deposit['amount'], 2, ',', '.') ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">
                                        <?= htmlspecialchars($deposit['bank_name']) ?><br>
                                        <span class="text-xs text-gray-500"><?= htmlspecialchars($deposit['account_name']) ?></span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <?php
                                            $status_color = '';
                                            switch ($deposit['status']) {
                                                case 'pending': $status_color = 'bg-yellow-100 text-yellow-800'; break;
                                                case 'completed': $status_color = 'bg-green-100 text-green-800'; break;
                                                case 'rejected': $status_color = 'bg-red-100 text-red-800'; break;
                                                default: $status_color = 'bg-gray-100 text-gray-800'; break;
                                            }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                            <?= ucfirst($deposit['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($deposit['created_at'])) ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800">
                                        <?php if (!empty($deposit['receipt_image_url'])): ?>
                                            <a href="<?= htmlspecialchars($deposit['receipt_image_url']) ?>" target="_blank" class="text-blue-600 hover:underline">
                                                Lihat Resi
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Bagian Aktivitas Deposit Situs Terbaru (Dianonimkan) -->
    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Aktivitas Deposit Situs Terbaru (Anonim)</h2>
        <?php if (empty($global_deposits)): ?>
            <p class="text-gray-600 text-center">Belum ada aktivitas deposit situs yang tercatat.</p>
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
                        <?php foreach ($global_deposits as $deposit): ?>
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800"><?= censorGlobalAmountDisplay($deposit['amount']) ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($deposit['bank_name']) ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800"><?= censorAccountNumber($deposit['account_number']) ?></td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($deposit['created_at'])) ?></td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <?php
                                        $status_color = '';
                                        switch ($deposit['status']) {
                                            case 'pending': $status_color = 'bg-yellow-100 text-yellow-800'; break;
                                            case 'completed': $status_color = 'bg-green-100 text-green-800'; break;
                                            case 'approved': $status_color = 'bg-green-100 text-green-800'; break;
                                            case 'rejected': $status_color = 'bg-red-100 text-red-800'; break;
                                            default: $status_color = 'bg-gray-100 text-gray-800'; break;
                                        }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                        <?= ucfirst($deposit['status']) ?>
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

<script>
    // Fitur Pratinjau Gambar Resi Sisi Klien
    const receiptImageInput = document.getElementById('receipt_image');
    const receiptImagePreview = document.getElementById('receiptImagePreview');
    const receiptImagePreviewContainer = document.getElementById('receiptImagePreviewContainer');

    if (receiptImageInput) {
        receiptImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    receiptImagePreview.src = e.target.result;
                    receiptImagePreviewContainer.classList.remove('hidden'); // Tampilkan container pratinjau
                };
                reader.readAsDataURL(file); // Baca file sebagai URL data
            } else {
                receiptImagePreview.src = '';
                receiptImagePreviewContainer.classList.add('hidden'); // Sembunyikan jika tidak ada file dipilih
            }
        });
    }
</script>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>
