<?php
// about_us.php
// Halaman "Tentang Kami" yang menjelaskan visi dan misi perusahaan.

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Ambil data pengguna lengkap untuk header (jika pengguna login)
$user_data = [];
$username = 'Pengguna'; // Default jika tidak login
if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    try {
        $stmt = $pdo->prepare("SELECT balance, points, membership_level, is_admin, username FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user_data = $stmt->fetch();
        if ($user_data) {
            $username = $user_data['username'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data in about_us.php: " . $e->getMessage());
    }
}

/**
 * Fungsi untuk menyensor bagian belakang nominal transaksi.
 * Contoh: 1234567.89 menjadi 1.234.XXX
 * @param float $amount Jumlah transaksi.
 * @return string Nominal yang sudah disensor.
 */
function censorGlobalAmountDisplay($amount) {
    $amount_str = number_format($amount, 0, '', ''); 
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

// Ambil riwayat transaksi keseluruhan situs (dianonimkan, semua status) untuk marquee
$global_transactions = [];
try {
    $stmt = $pdo->prepare("SELECT type, amount, status, created_at, bank_name, account_number, account_name FROM transactions ORDER BY created_at DESC LIMIT 10"); 
    $stmt->execute();
    $global_transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching global transactions for about_us.php marquee: " . $e->getMessage());
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
        LIMIT 20
    ");
    $stmt->execute();
    $all_approved_claims_for_marquee = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching all approved claims for marquee in about_us.php: " . $e->getMessage());
}

// Bangun konten marquee
$censored_username_welcome = substr(htmlspecialchars($username), 0, 1) . str_repeat('*', strlen(htmlspecialchars($username)) - 1);
$marquee_content_parts = ["Selamat Datang, " . $censored_username_welcome . "!"];

foreach ($global_transactions as $transaction) {
    $transaction_color_class = '';
    if ($transaction['type'] === 'deposit') {
        $transaction_color_class = 'text-green-400';
    } elseif ($transaction['type'] === 'withdraw') {
        $transaction_color_class = 'text-red-400';
    }
    
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
    $transaction_text .= " (No. Rek: " . censorAccountNumberDisplay($transaction['account_number'] ?? '') . ")";
    $marquee_content_parts[] = "<span class=\"{$transaction_color_class}\">{$transaction_text}</span>";
}

foreach ($all_approved_claims_for_marquee as $claim) {
    $censored_claim_username = htmlspecialchars($claim['username']);
    if (strlen($censored_claim_username) > 3) {
        $censored_claim_username = substr($censored_claim_username, 0, 1) . str_repeat('*', strlen($censored_claim_username) - 2) . substr($censored_claim_username, -1);
    } else {
        $censored_claim_username = str_repeat('*', strlen($censored_claim_username));
    }

    $claim_text = "Tugas '" . htmlspecialchars($claim['product_name']) . "' berhasil diklaim oleh " . $censored_claim_username . " (" . censorGlobalAmountDisplay($claim['claim_amount']) . ")";
    $marquee_content_parts[] = "<span class=\"text-yellow-400\">{$claim_text}</span>";
}

$final_marquee_text = implode(' | ', $marquee_content_parts);

// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<style>
    /* Flicker animation for elements */
    @keyframes fadeEffect {
        0% { opacity: 1; }
        50% { opacity: 0.1; } /* More transparent when "disappearing" */
        100% { opacity: 1; }
    }

    .flicker-animation {
        animation: fadeEffect 3s infinite alternate; /* 3s duration, infinite loop, alternate direction */
    }

    /* Style for leaner sitemap */
    .sitemap-container {
        background-color: #ffffff;
        padding: 0.75rem; /* Smaller padding */
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        margin-bottom: 1.5rem; /* Smaller bottom margin for sitemap */
    }

    .sitemap-container ul {
        list-style: none;
        padding: 0;
        display: flex; /* For horizontal layout on desktop */
        justify-content: space-around; /* Distribute links evenly */
        flex-wrap: wrap; /* Allow wrapping on small screens */
    }

    .sitemap-container ul li {
        margin: 0.25rem 0; /* Smaller vertical spacing */
    }

    .sitemap-container ul li a {
        display: block;
        padding: 0.5rem 0.75rem; /* Smaller padding */
        border-radius: 0.375rem; /* rounded-md */
        color: #4A5568; /* gray-700 */
        font-weight: 600; /* semi-bold */
        text-decoration: none;
        transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
        white-space: nowrap; /* Ensure link text is not clipped */
        font-size: 0.9rem; /* Slightly smaller font size */
    }

    .sitemap-container ul li a:hover {
        background-color: #EBF8FF; /* blue-50 */
        color: #2B6CB0; /* blue-700 */
    }

    /* Gaya khusus untuk konten Wikipedia */
    .wikipedia-style-section {
        background-color: #ffffff;
        padding: 1.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        margin-bottom: 2rem;
    }

    .wikipedia-style-section h1,
    .wikipedia-style-section h2,
    .wikipedia-style-section h3 {
        color: #202122; /* Warna teks gelap seperti Wikipedia */
        font-family: sans-serif; /* Font umum seperti Wikipedia */
        margin-top: 1.5em; /* Spasi atas mirip Wikipedia */
        margin-bottom: 0.5em; /* Spasi bawah mirip Wikipedia */
        border-bottom: 1px solid #a2a9b1; /* Garis bawah untuk judul */
        padding-bottom: 0.3em;
    }

    .wikipedia-style-section h1 {
        font-size: 2.2em; /* Ukuran font lebih besar untuk H1 */
        border-bottom: 2px solid #a2a9b1; /* Garis bawah lebih tebal untuk H1 */
    }
    .wikipedia-style-section h2 {
        font-size: 1.8em;
    }
    .wikipedia-style-section h3 {
        font-size: 1.4em;
        border-bottom: none; /* Sub-subjudul tanpa garis bawah */
    }

    .wikipedia-style-section p {
        line-height: 1.6; /* Spasi baris yang lebih nyaman dibaca */
        margin-bottom: 1em;
        color: #202122;
        text-align: justify;
    }

    .wikipedia-style-section ul {
        list-style: disc; /* Bullet points standar */
        padding-left: 20px; /* Indentasi untuk daftar */
        margin-bottom: 1em;
        color: #202122;
    }

    .wikipedia-style-section ul li {
        margin-bottom: 0.5em;
    }

    .wikipedia-style-section strong {
        font-weight: bold;
    }

    .wikipedia-style-section a {
        color: #3366cc; /* Warna link Wikipedia */
        text-decoration: none;
    }

    .wikipedia-style-section a:hover {
        text-decoration: underline;
    }

    /* Daftar isi minimalis */
    .toc-container {
        border: 1px solid #a2a9b1;
        background-color: #f8f9fa;
        padding: 1em 1.5em;
        margin-bottom: 2em;
        border-radius: 5px;
        width: fit-content; /* Sesuaikan lebar dengan konten */
        max-width: 100%;
    }

    .toc-container h2 {
        font-size: 1.2em;
        margin-top: 0;
        border-bottom: none;
        padding-bottom: 0;
    }

    .toc-container ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .toc-container ul ul { /* Sub-daftar */
        padding-left: 1.2em;
    }

    .toc-container ul li a {
        display: block;
        padding: 0.2em 0;
        color: #3366cc;
        text-decoration: none;
    }

    .toc-container ul li a:hover {
        text-decoration: underline;
    }


    /* Sesuaikan sitemap untuk tampilan mobile */
    @media (max-width: 767px) { /* For screens smaller than sm */
        .sitemap-container {
            padding: 0.5rem; /* Even smaller padding on mobile */
            margin-bottom: 1rem;
        }
        .sitemap-container ul {
            flex-direction: column; /* Change to vertical layout */
            align-items: stretch; /* Stretch items to fill width */
        }
        .sitemap-container ul li a {
            text-align: center; /* Center link text */
            padding: 0.4rem 0.6rem; /* Even smaller padding */
            font-size: 0.85rem; /* Even smaller font size */
        }
        .wikipedia-style-section h1 {
            font-size: 1.8em;
        }
        .wikipedia-style-section h2 {
            font-size: 1.5em;
        }
        .wikipedia-style-section h3 {
            font-size: 1.2em;
        }
    }
</style>

<main class="flex-grow container mx-auto p-4 md:p-8">
    <!-- Sitemap -->
    <section class="sitemap-container">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="about_us.php">Tentang Kami</a></li>
            <li><a href="claims.php">Tugas</a></li>
            <li><a href="deposit.php">Isi Saldo</a></li>
            <li><a href="withdraw.php">Tarik Saldo</a></li>
        </ul>
    </section>

    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Tentang Kami & Kontak</h1>

    <!-- Marquee -->
    <div class="bg-gray-900 border border-gray-700 text-white px-4 py-2 mb-6 rounded-full shadow-lg overflow-hidden flex items-center justify-center flicker-animation">
        <h4 class="font-semibold text-lg text-center whitespace-nowrap overflow-hidden">
            <marquee behavior="scroll" direction="left" scrollamount="4" class="inline-block py-0.5">
                <?= $final_marquee_text ?>
            </marquee>
        </h4>
    </div>

    <!-- Konten Utama "Tentang Kami" bergaya Wikipedia -->
    <div class="space-y-8">
        <section class="wikipedia-style-section">
            <h1>Tentang Kami & Kontak</h1>

            <p><strong>PT. DEPRINTZ SUKSES SEJAHTERA</strong> adalah sebuah platform yang didedikasikan untuk **meningkatkan rating penjualan, pengiriman, dan investasi barang**. Didirikan dengan visi untuk menciptakan ekosistem yang efisien antara penjual, penyedia logistik, dan investor, perusahaan ini beroperasi sebagai perseroan terbatas (PT) biasa. Platform ini secara khusus **memungkinkan para pemodal untuk melakukan penanaman modal dan mendapatkan komisi dari pihak ketiga**.</p>

            <div class="toc-container">
                <h2>Daftar Isi</h2>
                <ul>
                    <li><a href="#model-bisnis">1 Model Bisnis</a>
                        <ul>
                            <li><a href="#peluang-peningkatan-penjualan">1.1 Peluang Peningkatan Penjualan & Komisi Pihak Ketiga</a></li>
                            <li><a href="#proses-fasilitasi-pengiriman">1.2 Proses Fasilitasi Pengiriman Barang</a></li>
                        </ul>
                    </li>
                    <li><a href="#distribusi-mesin">2 Distributor Mesin Usaha</a></li>
                    <li><a href="#hubungi-kami">3 Hubungi Kami</a>
                        <ul>
                            <li><a href="#cs-admin">3.1 Customer Service & Admin</a></li>
                            <li><a href="#rekber">3.2 Rekening Bersama (Rekber)</a></li>
                        </ul>
                    </li>
                </ul>
            </div>

            <h2 id="model-bisnis">1. Model Bisnis</h2>
            <p>Sebagai sebuah platform, kami berfokus pada peningkatan penjualan dan efisiensi pengiriman barang. Model bisnis kami dirancang untuk memfasilitasi transaksi dan memberikan peluang investasi bagi individu atau entitas yang ingin berpartisipasi dalam pertumbuhan penjualan produk dan jasa.</p>

            <h3 id="peluang-peningkatan-penjualan">1.1. Peluang Peningkatan Penjualan & Komisi Pihak Ketiga</h3>
            <p>Kami menjalin kemitraan strategis dengan <strong>Deprintz</strong>, sebuah distributor mesin usaha terkemuka. Melalui platform kami, investor dapat menanamkan modal ke dalam proyek-proyek terkait peningkatan penjualan atau pengiriman barang Deprintz. Ini memungkinkan investor untuk mendapatkan **komisi dari pihak ketiga** yang terlibat dalam transaksi dan mendapatkan manfaat dari peningkatan penjualan atau efisiensi logistik.</p>

            <h3 id="proses-fasilitasi-pengiriman">1.2. Proses Fasilitasi Pengiriman Barang</h3>
            <p>Dalam ekosistem kami, "tugas" mengacu pada pesanan atau item yang memerlukan pendanaan awal untuk pengiriman atau penyelesaian. Ketika seorang investor melakukan "klaim" atas suatu tugas, mereka menyediakan modal yang diperlukan untuk memfasilitasi keberangkatan atau pemrosesan barang. Modal ini berperan penting dalam mempercepat siklus penjualan dan pengiriman.</p>
            <p>Setelah barang berhasil mencapai konsumen dan transaksi disetujui, modal yang telah Anda tanamkan akan dikembalikan. Selain itu, Anda akan mendapatkan **komisi yang dibayarkan oleh pihak ketiga** yang mendapatkan manfaat dari peningkatan penjualan atau efisiensi logistik yang Anda bantu danai.</p>
            <p class="call-to-action-text mt-8">
                "Mari bergabung dengan mitra kami, dan dapatkan keuntungan yang mudah hanya 
                bermodalkan secara daring tidak perlu repot - repot pengurusan, aman, 
                terpercaya dan modal Anda pasti kembali disertai komisi dari pihak ketiga!"
            </p>

            <h2 id="distribusi-mesin">2. Distributor Mesin Usaha</h2>
            <p><strong>PT. DEPRINTZ SUKSES SEJAHTERA</strong> merupakan distributor mesin usaha terlengkap dengan harga terbaik di Indonesia. Mereka memiliki <em>showroom</em> fisik di Surabaya dan jaringan rekanan yang tersebar di berbagai kota besar di Indonesia, termasuk Sulawesi Utara, Bali, Malang, Yogyakarta, Bandung, Semarang, Ambon, Nusa Tenggara Timur, Makassar, Medan, Denpasar, Lombok, Kupang, Kalimantan (Balikpapan, Banjarmasin, Pontianak, Samarinda, Palangkaraya), Sulawesi (Manado, Palu, Gorontalo), Gresik, Probolinggo, Jawa Timur, dan Jawa Barat (Bogor).</p>
            <p>Produk yang ditawarkan oleh Deprintz meliputi beragam mesin industri dan peralatan digital printing, di antaranya:</p>
            <ul>
                <li>Mesin Laser Cutting Engraving Marking (CO2, Metal, Kain)</li>
                <li>Mesin CNC Router</li>
                <li>Mesin Marking</li>
                <li>Mesin Cutting Sticker Plotter Paper Vinyl</li>
                <li>Mesin Usaha Industri</li>
                <li>Mesin Printing (Digital Outdoor Solvent, Indoor EcoSolvent, Print and Cut, UV LED, Printer Kain Garment, Printer ID Card, Printer Foto, Mesin Printer Fotocopy, Printer 3D)</li>
                <li>Mesin Garment, Konveksi, dan Jahit</li>
                <li>Mesin Makanan</li>
                <li>Peralatan dan Bahan Pendukung: Alat Grounding, Alat Potong, Mesin Pond, Mesin Tekuk/Bending, Mesin Cetak Stempel, Mesin Jilid Buku, Peralatan Sablon Digital, Mesin Laminasi, Alat Seaming Banner Spanduk, Genset, Pompa Air, Mesin Las, Mesin mini printer label, Mesin Kasir, Barcode Scanner, Printer Barcode, Cash Drawer.</li>
                <li>Jasa Advertising dan Percetakan, Papan Bunga Ucapan.</li>
                <li>Alat dan Perlengkapan Display Promosi: Standing Banner, Running Text, Videotron, Slim Light Box LED, Strip Light Part LED.</li>
            </ul>
            <p>Deprintz dikenal sebagai distributor yang menyediakan mesin dan alat display promosi dengan harga terbaik, menjadikan mereka mitra terpercaya dalam bidang industri.</p>

            <h2 id="hubungi-kami">3. Hubungi Kami</h2>
            <p>Untuk pertanyaan, bantuan, atau informasi lebih lanjut, Anda dapat menghubungi kami melalui saluran berikut:</p>

            <h3 id="cs-admin">3.1. Customer Service & Admin</h3>
            <ul class="list-none !pl-0">
                <li class="mb-2">
                    <p class="text-gray-700 flex items-center">
                        <i class="fab fa-whatsapp text-green-500 text-xl mr-2"></i>
                        CS Whatsapp: <a href="https://wa.me/62123456789" target="_blank" class="text-blue-600 hover:underline ml-2">+62123456789</a>
                    </p>
                </li>
                <li>
                    <p class="text-gray-700 flex items-center">
                        <i class="fab fa-whatsapp text-green-500 text-xl mr-2"></i>
                        Admin Whatsapp: <a href="https://wa.me/6282160195535" target="_blank" class="text-blue-600 hover:underline ml-2">+6282160195535</a>
                    </p>
                </li>
            </ul>

            <h3 id="rekber">3.2. Rekening Bersama (Rekber)</h3>
            <p>Kami menyediakan rekening bersama untuk kemudahan transaksi Anda, untuk mendapatkan
            alamat rekening bersam, atau anda ingin melakukan Deposit atau Withdraw harus 
            menghubungi livechat terlebih dahulu. Ataupun melalui Contact Person resmi milik
            Deprintz yang bisa anda dapatkan dari livechat kami.
            </p>
            <p class="call-to-action-text mt-8">
                "Mari bergabung dengan mitra kami, dan dapatkan keuntungan yang mudah hanya 
                bermodalkan secara daring tidak perlu repot - repot pengurusan, aman, 
                terpercaya dan modal Anda pasti kembali disertai komisi dari pihak ketiga!"
            </p>
        </section>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Wikipedia-style "Table of Contents" scrolling
        document.querySelectorAll('.toc-container a').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();

                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    });
</script>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>
