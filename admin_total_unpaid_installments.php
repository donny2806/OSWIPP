<?php
// admin_total_unpaid_installments.php
// Halaman untuk menampilkan total cicilan yang disetujui (tetapi belum selesai dibayar) oleh user,
// serta daftar user yang memiliki cicilan tersebut beserta detailnya, dengan pencarian reaktif dan paginasi JavaScript.
// Sekarang dengan fitur "Cetak Dokumen" yang memicu dialog cetak browser hanya untuk detail pengguna dalam mode portrait.

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

try {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data_session = $stmt->fetch(); // Mengubah nama variabel untuk menghindari konflik

    if ($user_data_session && $user_data_session['is_admin']) {
        $is_admin = true;
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

// Inisialisasi total global
$grand_total_original_price = 0;
$grand_total_paid_amount = 0;
$grand_total_remaining_amount = 0;

$all_users_with_unpaid_installments_raw = []; // Data mentah dari DB sebelum dikelompokkan
$users_grouped_data = []; // Data yang dikelompokkan per user

try {
    // Ambil semua cicilan yang statusnya 'approved' (disetujui) dan belum 'completed'
    $stmt = $pdo->prepare("
        SELECT
            i.id AS installment_id,
            i.user_id,
            u.username,
            u.full_name,
            p.name AS product_name,
            i.original_product_price,
            i.remaining_amount,
            i.status,
            i.requested_at
        FROM
            installments i
        JOIN
            users u ON i.user_id = u.id
        JOIN
            products p ON i.product_id = p.id
        WHERE
            i.status = 'approved' AND i.remaining_amount > 0
        ORDER BY
            u.username ASC, i.requested_at DESC
    ");
    $stmt->execute();
    $all_users_with_unpaid_installments_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kumpulkan cicilan per user dan hitung total per user untuk SEMUA data yang disetujui dan belum selesai
    foreach ($all_users_with_unpaid_installments_raw as $installment) {
        $user_id = $installment['user_id'];
        $paid_amount_for_installment = $installment['original_product_price'] - $installment['remaining_amount'];

        if (!isset($users_grouped_data[$user_id])) {
            $users_grouped_data[$user_id] = [
                'user_id' => $user_id,
                'username' => htmlspecialchars($installment['username']),
                'full_name' => htmlspecialchars($installment['full_name'] ?: 'N/A'),
                'total_original_price' => 0,
                'total_paid_amount' => 0,
                'total_remaining_amount' => 0,
                'installments' => []
            ];
        }

        // Tambahkan ke total per user
        $users_grouped_data[$user_id]['total_original_price'] += $installment['original_product_price'];
        $users_grouped_data[$user_id]['total_paid_amount'] += $paid_amount_for_installment;
        $users_grouped_data[$user_id]['total_remaining_amount'] += $installment['remaining_amount'];

        // Tambahkan ke grand total global (ini perlu dilakukan di sini karena ini adalah tempat kita mengiterasi semua data)
        $grand_total_original_price += $installment['original_product_price'];
        $grand_total_paid_amount += $paid_amount_for_installment;
        $grand_total_remaining_amount += $installment['remaining_amount'];

        // Tambahkan detail cicilan ke user
        $users_grouped_data[$user_id]['installments'][] = $installment;
    }

    // Ubah asosiatif array menjadi array indeks numerik agar mudah diakses di JS
    $users_grouped_data = array_values($users_grouped_data);
    
    // Urutkan pengguna berdasarkan username untuk urutan yang konsisten di JavaScript
    usort($users_grouped_data, function($a, $b) {
        return strcmp($a['username'], $b['username']);
    });

} catch (PDOException $e) {
    error_log("Error fetching approved unpaid installments: " . $e->getMessage());
    $error_message = "Terjadi kesalahan saat mengambil data cicilan: " . $e->getMessage();
}

/**
 * Fungsi pembantu untuk memformat mata uang untuk tampilan.
 * @param float $amount The amount to format.
 * @return string String mata uang yang diformat.
 */
function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<main class="flex-grow container mx-auto p-4 md:p-8">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Cicilan yang Disetujui (Belum Selesai)</h1>

    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Ringkasan Global Cicilan Disetujui (Belum Selesai)</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-lg shadow overflow-hidden">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-medium uppercase tracking-wider">Kategori</th>
                        <th class="py-3 px-4 text-left text-sm font-medium uppercase tracking-wider">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 whitespace-nowrap text-lg font-semibold text-gray-900">Total Harga Awal (Disetujui)</td>
                        <td class="py-3 px-4 whitespace-nowrap text-lg text-green-700 font-bold"><?= format_currency($grand_total_original_price) ?></td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 whitespace-nowrap text-lg font-semibold text-gray-900">Total Sudah Dibayar (Dari Cicilan Disetujui)</td>
                        <td class="py-3 px-4 whitespace-nowrap text-lg text-blue-700 font-bold"><?= format_currency($grand_total_paid_amount) ?></td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 whitespace-nowrap text-lg text-red-700 font-bold">Total Sisa Pembayaran (Dari Cicilan Disetujui)</td>
                        <td class="py-3 px-4 whitespace-nowrap text-lg text-red-700 font-bold"><?= format_currency($grand_total_remaining_amount) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pie Chart for Summary -->
        <div class="mt-8 flex justify-center items-center flex-wrap gap-4">
            <div class="w-full md:w-1/2 lg:w-1/3 p-4">
                <canvas id="summaryPieChart"></canvas>
            </div>
            <div class="w-full md:w-1/2 lg:w-1/3 p-4 text-center">
                <p class="text-xl font-semibold text-gray-800 mb-2">Distribusi Pembayaran</p>
                <div class="flex items-center justify-center mb-2">
                    <span class="inline-block w-4 h-4 rounded-full bg-blue-500 mr-2"></span>
                    <span class="text-gray-700">Sudah Dibayar</span>
                </div>
                <div class="flex items-center justify-center">
                    <span class="inline-block w-4 h-4 rounded-full bg-red-500 mr-2"></span>
                    <span class="text-gray-700">Belum Dibayar</span>
                </div>
            </div>
        </div>

        <div class="mt-6 text-center">
            <a href="admin_manage_installments.php?filter=approved" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">
                <i class="fas fa-list-alt mr-2"></i> Lihat Detail Cicilan Disetujui
            </a>
        </div>
    </section>

    <!-- Bagian Detail Pengguna dengan Cicilan Disetujui (Belum Selesai) - Single User View -->
    <section id="user-detail-section" class="bg-white p-6 rounded-xl shadow-md mt-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Detail Pengguna dengan Cicilan Disetujui (Belum Selesai)</h2>
        
        <!-- Search Box (JavaScript Controlled) -->
        <div class="mb-6 flex flex-col sm:flex-row gap-3">
            <input type="text" id="searchInput" placeholder="Cari pengguna (username/nama lengkap)" class="flex-grow px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            <button id="resetSearchBtn" class="px-4 py-2 bg-gray-300 text-gray-800 font-semibold rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 flex items-center justify-center hidden">
                <i class="fas fa-times mr-1"></i> Reset
            </button>
        </div>

        <!-- Kontainer untuk menampilkan data pengguna dan paginasi oleh JavaScript -->
        <div id="userDisplayContainer">
            <!-- Konten ini akan diisi oleh JavaScript -->
            <p class="text-gray-600 text-center">Memuat data...</p>
        </div>
    </section>

    <!-- Bagian Baru: Tabel Daftar Pengguna dengan Total Sisa Bayar (Pencarian & Paginasi) -->
    <section class="bg-white p-6 rounded-xl shadow-md mt-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Daftar Ringkasan Cicilan Per Pengguna</h2>
        
        <!-- Search Box for Table -->
        <div class="mb-4">
            <input type="text" id="tableSearchInput" placeholder="Cari di tabel (username/nama lengkap)" class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>

        <div id="userTableContainer" class="overflow-x-auto">
            <!-- Table content will be rendered here by JavaScript -->
            <p class="text-gray-600 text-center">Memuat tabel...</p>
        </div>

        <!-- Pagination for Table -->
        <div class="flex justify-between items-center mt-6">
            <button id="prevTablePageBtn"
                class="px-4 py-2 rounded-md shadow-sm transition duration-150 ease-in-out
                bg-indigo-600 hover:bg-indigo-700 text-white" disabled>
                <i class="fas fa-chevron-left mr-2"></i> Sebelumnya
            </button>
            <span class="text-gray-700 font-semibold">
                Halaman <span id="currentTablePage">1</span> dari <span id="totalTablePages">1</span>
            </span>
            <button id="nextTablePageBtn"
                class="px-4 py-2 rounded-md shadow-sm transition duration-150 ease-in-out
                bg-indigo-600 hover:bg-indigo-700 text-white" disabled>
                Selanjutnya <i class="fas fa-chevron-right ml-2"></i>
            </button>
        </div>
    </section>
</main>

<!-- Sertakan Chart.js library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Data pengguna yang dikirim dari PHP (berisi semua pengguna dengan cicilan yang dikelompokkan)
    const allUsersData = <?= json_encode($users_grouped_data) ?>;
    
    // Data total global dari PHP untuk Chart
    const grandTotalPaidAmount = <?= json_encode($grand_total_paid_amount) ?>;
    const grandTotalRemainingAmount = <?= json_encode($grand_total_remaining_amount) ?>;

    // --- Bagian untuk Tampilan Pengguna Tunggal ---
    let filteredUsersSingle = [];
    let currentUserIndexSingle = 0;

    const searchInputSingle = document.getElementById('searchInput');
    const resetSearchBtnSingle = document.getElementById('resetSearchBtn');
    const userDisplayContainerSingle = document.getElementById('userDisplayContainer');

    // Helper untuk memformat mata uang
    function formatCurrency(amount) {
        return 'Rp ' + parseFloat(amount).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    // Fungsi untuk mencetak detail pengguna menggunakan dialog cetak browser
    function printUserToPdf(userId) {
        const userDetailSection = document.getElementById('user-detail-section');
        const elementToPrint = document.getElementById(`user-${userId}-details`);
        
        if (elementToPrint && userDetailSection) {
            // Simpan display asli dari semua bagian utama yang akan disembunyikan
            const elementsToHide = document.querySelectorAll('main > h1, main > section:not(#user-detail-section), header, footer');
            
            const originalDisplays = [];
            elementsToHide.forEach(el => {
                originalDisplays.push({ element: el, display: el.style.display });
                el.style.display = 'none'; // Sembunyikan elemen
            });

            // Buat style untuk cetak
            const style = document.createElement('style');
            style.innerHTML = `
                @page { size: portrait; margin: 1cm; } /* Kembali ke portrait, margin 1cm */
                body { margin: 0; padding: 0; }
                
                /* Pastikan hanya bagian detail pengguna yang terlihat */
                #user-detail-section { 
                    display: block !important; 
                    position: relative; 
                    width: auto; 
                    max-width: 100%; 
                    box-sizing: border-box; 
                    padding: 20px; 
                    background-color: white; 
                    margin: 0; 
                } 

                #user-${userId}-details { 
                    display: block !important; 
                    margin: 0 auto; 
                    background-color: white; 
                    padding: 0; 
                    border: none; 
                    box-shadow: none; 
                    width: auto; 
                    max-width: 100%; 
                    box-sizing: border-box;
                }
                
                #user-${userId}-details .overflow-x-auto { 
                    overflow-x: visible !important; 
                    width: 100%; 
                }
                #user-${userId}-details table {
                    width: 100% !important; 
                    table-layout: auto; 
                }
                #user-${userId}-details table th, #user-${userId}-details table td { 
                    white-space: normal; 
                    word-wrap: break-word; 
                    font-size: 0.7em; 
                    padding: 5px 8px; 
                    text-align: left; 
                }
                /* Sembunyikan tombol cetak dan paginasi di dalam PDF */
                #user-${userId}-details .mt-4.text-right { display: none !important; } 
                #user-${userId}-details .flex.justify-between.items-center.mt-6 { display: none !important; }
                /* Tampilkan logo di cetakan */
                #user-${userId}-details .print-logo {
                    display: block !important;
                    margin-bottom: 20px;
                    text-align: center; /* Posisi logo di tengah */
                }
                #user-${userId}-details .print-logo img {
                    max-width: 150px; 
                    height: auto;
                }
            `;
            document.head.appendChild(style);

            // Trigger cetak browser
            window.print();

            // Kembalikan elemen dan gaya setelah cetak
            setTimeout(() => {
                document.head.removeChild(style);
                originalDisplays.forEach(item => {
                    item.element.style.display = item.display; 
                });
            }, 500); 

        } else {
            console.error("Elemen untuk dicetak tidak ditemukan untuk user ID:", userId);
            alert("Gagal mencetak. Detail pengguna tidak ditemukan.");
        }
    }

    // Fungsi untuk merender data pengguna tertentu
    function renderUserSingle(index) {
        if (filteredUsersSingle.length === 0) {
            userDisplayContainerSingle.innerHTML = '<p class="text-gray-600 text-center">Tidak ada pengguna yang cocok dengan cicilan yang disetujui dan belum diselesaikan.</p>';
            return;
        }

        if (index < 0 || index >= filteredUsersSingle.length) {
            console.error('Indeks pengguna tunggal tidak valid:', index);
            return;
        }

        currentUserIndexSingle = index; // Update current index

        const user = filteredUsersSingle[currentUserIndexSingle];
        let installmentsHtml = '';

        if (user.installments.length > 0) {
            installmentsHtml = `
                <h4 class="text-lg font-medium text-gray-700 mb-2">Detail Cicilan Disetujui:</h4>
                <div class="overflow-x-auto"> <!-- Pastikan div ini memiliki overflow-x-auto -->
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-3 text-left text-xs font-medium uppercase tracking-wider">ID Cicilan</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Produk</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Harga Awal</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Sudah Dibayar</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Sisa Bayar</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Status</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">Diminta Pada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            ${user.installments.map(inst => `
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-3 whitespace-nowrap text-sm text-gray-800">${inst.installment_id}</td>
                                    <td class="py-2 px-3 whitespace-nowrap text-sm text-gray-800">${inst.product_name}</td>
                                    <td class="py-2 px-3 whitespace-nowrap text-sm text-green-600">${formatCurrency(inst.original_product_price)}</td>
                                    <td class="py-2 px-3 whitespace-nowrap text-sm text-blue-600">${formatCurrency(inst.original_product_price - inst.remaining_amount)}</td>
                                    <td class="py-2 px-3 whitespace-nowrap text-sm text-red-600">${formatCurrency(inst.remaining_amount)}</td>
                                    <td class="py-2 px-3 whitespace-nowrap text-sm">
                                        <span class="font-medium text-blue-600">${inst.status.charAt(0).toUpperCase() + inst.status.slice(1)}</span>
                                    </td>
                                    <td class="py-2 px-3 whitespace-nowrap text-sm text-gray-600">${new Date(inst.requested_at).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            installmentsHtml = '<p class="text-gray-600">Tidak ada detail cicilan yang ditemukan untuk pengguna ini.</p>';
        }

        userDisplayContainerSingle.innerHTML = `
            <div id="user-${user.user_id}-details" class="bg-gray-50 p-5 rounded-lg shadow-sm border border-gray-200">
                <div class="print-logo hidden"> <!-- Logo untuk cetak, tersembunyi di tampilan web -->
                    <img src="logo.png" alt="Logo Perusahaan" onerror="this.onerror=null;this.src='https://placehold.co/150x50/cccccc/000000?text=Logo+Error';">
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-3">
                    <i class="fas fa-user-circle mr-2 text-blue-600"></i>
                    ${user.full_name} (<span class="text-blue-600">${user.username}</span>)
                </h3>
                <div class="mb-4">
                    <p class="text-gray-700"><strong>Total Harga Awal (Disetujui):</strong> <span class="font-bold text-green-700">${formatCurrency(user.total_original_price)}</span></p>
                    <p class="text-gray-700"><strong>Total Sudah Dibayar:</strong> <span class="font-bold text-blue-700">${formatCurrency(user.total_paid_amount)}</span></p>
                    <p class="text-gray-700"><strong>Total Sisa Pembayaran:</strong> <span class="font-bold text-red-700">${formatCurrency(user.total_remaining_amount)}</span></p>
                </div>

                <div class="mt-4 text-right">
                    <button id="printPdfBtn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-print mr-2"></i> Cetak Dokumen
                    </button>
                </div>

                ${installmentsHtml}
            </div>

            <!-- Kontrol Paginasi -->
            <div class="flex justify-between items-center mt-6">
                <button id="prevUserBtnSingle"
                    class="px-4 py-2 rounded-md shadow-sm transition duration-150 ease-in-out
                    ${currentUserIndexSingle === 0 ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 text-white'}"
                    ${currentUserIndexSingle === 0 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left mr-2"></i> Sebelumnya
                </button>
                <span class="text-gray-700 font-semibold">
                    Pengguna ${filteredUsersSingle.length > 0 ? (currentUserIndexSingle + 1) : 0} dari ${filteredUsersSingle.length}
                </span>
                <button id="nextUserBtnSingle"
                    class="px-4 py-2 rounded-md shadow-sm transition duration-150 ease-in-out
                    ${currentUserIndexSingle === filteredUsersSingle.length - 1 ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 text-white'}"
                    ${currentUserIndexSingle === filteredUsersSingle.length - 1 ? 'disabled' : ''}>
                    Selanjutnya <i class="fas fa-chevron-right ml-2"></i>
                </button>
            </div>
        `;
        // Beri id khusus pada section detail pengguna agar mudah ditargetkan saat cetak
        userDisplayContainerSingle.closest('section').id = 'user-detail-section';


        // Attach event listeners to new buttons
        document.getElementById('prevUserBtnSingle').onclick = goToPreviousUserSingle;
        document.getElementById('nextUserBtnSingle').onclick = goToNextUserSingle;
        document.getElementById('printPdfBtn').onclick = function() {
            // Pass user's full_name and username to the print function
            printUserToPdf(user.user_id, user.username, user.full_name);
        };
    }

    // Fungsi untuk memfilter pengguna berdasarkan input pencarian
    function filterUsersSingle() {
        const query = searchInputSingle.value.toLowerCase();
        if (query) {
            filteredUsersSingle = allUsersData.filter(user =>
                user.username.toLowerCase().includes(query) ||
                user.full_name.toLowerCase().includes(query)
            );
            resetSearchBtnSingle.classList.remove('hidden');
        } else {
            filteredUsersSingle = [...allUsersData]; // Clone all data if no search query
            resetSearchBtnSingle.classList.add('hidden');
        }
        currentUserIndexSingle = 0; // Reset index to the first user in the filtered list
        renderUserSingle(currentUserIndexSingle);
    }

    // Navigasi: Sebelumnya
    function goToPreviousUserSingle() {
        if (currentUserIndexSingle > 0) {
            renderUserSingle(currentUserIndexSingle - 1);
        }
    }

    // Navigasi: Selanjutnya
    function goToNextUserSingle() {
        if (currentUserIndexSingle < filteredUsersSingle.length - 1) {
            renderUserSingle(currentUserIndexSingle + 1);
        }
    }

    // Event Listeners for Single User Display
    searchInputSingle.addEventListener('keyup', filterUsersSingle);
    resetSearchBtnSingle.addEventListener('click', function() {
        searchInputSingle.value = ''; // Clear search input
        filterUsersSingle(); // Trigger filter to show all users
    });

    // Inisialisasi tampilan awal Single User Display
    filterUsersSingle(); 

    // --- Bagian Baru: Tabel Daftar Pengguna dengan Total Sisa Bayar ---
    let filteredTableUsers = [];
    let currentTablePage = 0;
    const rowsPerPage = 10; // Menampilkan 10 baris per halaman

    const tableSearchInput = document.getElementById('tableSearchInput');
    const userTableContainer = document.getElementById('userTableContainer');
    const prevTablePageBtn = document.getElementById('prevTablePageBtn');
    const nextTablePageBtn = document.getElementById('nextTablePageBtn');
    const currentTablePageSpan = document.getElementById('currentTablePage');
    const totalTablePagesSpan = document.getElementById('totalTablePages');

    // Fungsi untuk merender tabel pengguna
    function renderUserTable() {
        const startIndex = currentTablePage * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;
        const usersToDisplay = filteredTableUsers.slice(startIndex, endIndex);

        const totalPages = Math.ceil(filteredTableUsers.length / rowsPerPage);
        totalTablePagesSpan.textContent = totalPages === 0 ? 1 : totalPages; // Hindari 0 halaman jika tidak ada data
        currentTablePageSpan.textContent = totalPages === 0 ? 0 : (currentTablePage + 1);

        if (filteredTableUsers.length === 0) {
            userTableContainer.innerHTML = '<p class="text-gray-600 text-center">Tidak ada pengguna yang cocok ditemukan.</p>';
            prevTablePageBtn.disabled = true;
            nextTablePageBtn.disabled = true;
            return;
        }

        let tableHtml = `
            <table class="min-w-full bg-white rounded-lg shadow overflow-hidden">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="py-3 px-4 text-left text-sm font-medium uppercase tracking-wider">Username</th>
                        <th class="py-3 px-4 text-left text-sm font-medium uppercase tracking-wider">Nama Lengkap</th>
                        <th class="py-3 px-4 text-left text-sm font-medium uppercase tracking-wider">Total Sisa Bayar</th>
                        <th class="py-3 px-4 text-left text-sm font-medium uppercase tracking-wider">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
        `;

        usersToDisplay.forEach(user => {
            tableHtml += `
                <tr class="hover:bg-gray-50">
                    <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800">${user.username}</td>
                    <td class="py-3 px-4 whitespace-nowrap text-sm text-gray-800">${user.full_name}</td>
                    <td class="py-3 px-4 whitespace-nowrap text-sm text-red-600 font-bold">${formatCurrency(user.total_remaining_amount)}</td>
                    <td class="py-3 px-4 whitespace-nowrap text-sm">
                        <a href="#" onclick="showUserInSingleView(${user.user_id}); return false;" class="text-indigo-600 hover:text-indigo-900 font-semibold">Lihat</a>
                    </td>
                </tr>
            `;
        });

        tableHtml += `
                </tbody>
            </table>
        `;
        userTableContainer.innerHTML = tableHtml;

        // Update pagination button states
        prevTablePageBtn.disabled = currentTablePage === 0;
        nextTablePageBtn.disabled = (currentTablePage + 1) * rowsPerPage >= filteredTableUsers.length;
    }

    // Fungsi untuk memfilter tabel pengguna
    function filterUserTable() {
        const query = tableSearchInput.value.toLowerCase();
        if (query) {
            filteredTableUsers = allUsersData.filter(user =>
                user.username.toLowerCase().includes(query) ||
                user.full_name.toLowerCase().includes(query)
            );
        } else {
            filteredTableUsers = [...allUsersData]; // Clone all data if no search query
        }
        currentTablePage = 0; // Reset page to 0 on new filter
        renderUserTable();
    }

    // Navigasi tabel: Sebelumnya
    function goToPrevTablePage() {
        if (currentTablePage > 0) {
            currentTablePage--;
            renderUserTable();
        }
    }

    // Navigasi tabel: Selanjutnya
    function goToNextTablePage() {
        if ((currentTablePage + 1) * rowsPerPage < filteredTableUsers.length) {
            currentTablePage++;
            renderUserTable();
        }
    }

    // Fungsi global untuk menampilkan user dari tabel ke single view
    window.showUserInSingleView = function(userId) {
        const userIndex = allUsersData.findIndex(user => user.user_id === userId);
        if (userIndex !== -1) {
            // Set search input value and trigger filter for single view
            // This will make the single view render the found user and reset its pagination
            searchInputSingle.value = allUsersData[userIndex].username; // Or full_name
            filterUsersSingle(); 
            // Ensure the correct user is displayed if filterUsersSingle might jump to the first filtered
            // For exact jump, after filterUsersSingle, re-find the index in filteredUsersSingle and render it.
            const newIndex = filteredUsersSingle.findIndex(user => user.user_id === userId);
            if (newIndex !== -1) {
                renderUserSingle(newIndex);
            }
            // Scroll to the single user display section
            userDisplayContainerSingle.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };


    // Event Listeners for User Table
    tableSearchInput.addEventListener('keyup', filterUserTable);
    prevTablePageBtn.addEventListener('click', goToPrevTablePage);
    nextTablePageBtn.addEventListener('click', goToNextTablePage);

    // Inisialisasi tampilan awal untuk tabel
    filterUserTable(); // Initial call to display all users in table or based on initial empty search


    // --- Inisialisasi Pie Chart ---
    const ctx = document.getElementById('summaryPieChart').getContext('2d');
    const totalAmount = grandTotalPaidAmount + grandTotalRemainingAmount;

    let paidPercentage = 0;
    let remainingPercentage = 0;

    if (totalAmount > 0) {
        paidPercentage = (grandTotalPaidAmount / totalAmount) * 100;
        remainingPercentage = (grandTotalRemainingAmount / totalAmount) * 100;
    }

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Sudah Dibayar', 'Belum Dibayar'],
            datasets: [{
                data: [paidPercentage, remainingPercentage],
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)', // Tailwind blue-500
                    'rgba(239, 68, 68, 0.7)'  // Tailwind red-500
                ],
                borderColor: [
                    'rgba(59, 130, 246, 1)',
                    'rgba(239, 68, 68, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += context.parsed.toFixed(2) + '%';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>
