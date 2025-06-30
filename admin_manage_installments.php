<?php
// admin_manage_installments.php
// Halaman ini memungkinkan admin untuk meninjau, menyetujui, atau menolak
// pengajuan cicilan tugas dari pengguna.

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Periksa apakah pengguna sudah login dan apakah dia admin
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Arahkan ke halaman login jika belum login
    exit();
}

$current_user_id = $_SESSION['user_id'];
$is_admin = false;
try {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();
    if ($user_data && $user_data['is_admin']) {
        $is_admin = true;
    } else {
        // Jika bukan admin, arahkan kembali ke dashboard pengguna
        header('Location: dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error checking admin status in admin_manage_installments.php: " . $e->getMessage());
    header('Location: index.php'); // Redirect ke login jika ada masalah otentikasi
    exit();
}

$message = '';
$message_type = ''; // 'success' atau 'error'

// Ambil pesan dari URL jika ada (setelah proses persetujuan/penolakan)
// Note: Jangan lupa di admin_process_installment.php, redirect ke halaman ini dengan parameter message dan type
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
}

// --- Logika Paginasi, Pencarian, dan Filter ---
$records_per_page = 10;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$current_page || $current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING); // 'all', 'pending', 'approved', 'rejected', 'completed'

if (empty($status_filter)) {
    $status_filter = 'all'; // Default filter status
}

$sql_conditions = [];
$sql_bind_params = [];

if (!empty($search_query)) {
    // Search by installment ID, username, or product name
    $sql_conditions[] = "(i.id LIKE :search_id OR u.username LIKE :search_username OR p.name LIKE :search_product_name)";
    $search_param = '%' . $search_query . '%';
    $sql_bind_params[':search_id'] = $search_param;
    $sql_bind_params[':search_username'] = $search_param;
    $sql_bind_params[':search_product_name'] = $search_param;
}

if ($status_filter !== 'all') {
    $sql_conditions[] = "i.status = :status_filter";
    $sql_bind_params[':status_filter'] = $status_filter;
}

$where_clause = '';
if (!empty($sql_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $sql_conditions);
}

// Hitung total record untuk paginasi
try {
    $count_sql = "SELECT COUNT(*)
                  FROM installments i
                  JOIN users u ON i.user_id = u.id
                  JOIN products p ON i.product_id = p.id"
                  . $where_clause;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($sql_bind_params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    error_log("Error counting installments for admin_manage_installments.php: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
    $message = "Gagal menghitung jumlah pengajuan cicilan: " . $e->getMessage();
    $message_type = 'error';
}

// Ambil data pengajuan cicilan untuk ditampilkan dengan paginasi, pencarian, dan filter
$installments = [];
try {
    $sql_installments = "SELECT 
                            i.id, i.user_id, u.username, 
                            p.name AS product_name, p.image_url, 
                            i.original_product_price, i.remaining_amount, i.status, 
                            i.requested_at, i.approved_at, a.username AS admin_approver 
                        FROM installments i
                        JOIN users u ON i.user_id = u.id
                        JOIN products p ON i.product_id = p.id
                        LEFT JOIN users a ON i.approved_by = a.id"
                        . $where_clause . " ORDER BY i.requested_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql_installments);

    // Bind parameters for WHERE conditions
    foreach ($sql_bind_params as $param_name => $param_value) {
        $stmt->bindValue($param_name, $param_value);
    }

    // Bind LIMIT and OFFSET parameters
    $stmt->bindValue(':limit', (int)$records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $installments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching installments for admin_manage_installments.php: " . $e->getMessage());
    $message = "Gagal memuat daftar pengajuan cicilan: " . $e->getMessage();
    $message_type = 'error';
}

// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<main class="flex-grow container mx-auto p-4 md:p-8">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Kelola Pengajuan Cicilan Tugas</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg
                    <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>"
                    role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Daftar Pengajuan Cicilan</h2>

        <form action="admin_manage_installments.php" method="GET" class="mb-6 flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4">
            <div class="relative w-full sm:w-1/2">
                <input type="text" name="search" placeholder="Cari ID cicilan, username, atau tugas..."
                       value="<?= htmlspecialchars($search_query) ?>"
                       class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 pl-10 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <div class="w-full sm:w-1/4">
                <select name="status"
                        class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <button type="submit"
                    class="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
                Filter
            </button>
            <?php if (!empty($search_query) || (!empty($status_filter) && $status_filter !== 'all')): ?>
                <a href="admin_manage_installments.php" class="w-full sm:w-auto px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 text-center transition duration-200">Reset</a>
            <?php endif; ?>
        </form>

        <div class="overflow-x-auto">
            <?php if (empty($installments) && empty($message)): ?>
                <p class="text-gray-600 text-center py-8">Tidak ada pengajuan cicilan ditemukan.</p>
            <?php elseif (empty($installments) && !empty($message) && $message_type === 'error'): ?>
                 <p class="text-gray-600 text-center py-8">Terjadi kesalahan saat memuat pengajuan cicilan. Silakan periksa pesan kesalahan di atas.</p>
            <?php else: ?>
                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                    <thead class="bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Cicilan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengguna</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tugas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Awal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa Bayar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diajukan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diproses Oleh</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($installments as $installment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($installment['id']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($installment['username']) ?> (ID: <?= htmlspecialchars($installment['user_id']) ?>)</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($installment['product_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($installment['original_product_price'], 2, ',', '.') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($installment['remaining_amount'], 2, ',', '.') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $status_color = '';
                                        switch ($installment['status']) {
                                            case 'pending': $status_color = 'bg-yellow-100 text-yellow-800'; break;
                                            case 'approved': $status_color = 'bg-green-100 text-green-800'; break;
                                            case 'rejected': $status_color = 'bg-red-100 text-red-800'; break;
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
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($installment['status'] === 'pending'): ?>
                                        <button type="button" onclick="openApproveConfirmModal(<?= $installment['id'] ?>)" class="text-green-600 hover:text-green-900 mr-3">Setujui</button>
                                        <button type="button" onclick="openRejectConfirmModal(<?= $installment['id'] ?>)" class="text-red-600 hover:text-red-900">Tolak</button>
                                    <?php else: ?>
                                        <span class="text-gray-500">Sudah Diproses</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($total_pages > 1 || !empty($search_query) || (!empty($status_filter) && $status_filter !== 'all')): ?>
        <nav class="mt-8 flex justify-center" aria-label="Pagination">
            <ul class="flex items-center -space-x-px h-10 text-base">
                <?php
                $pagination_base_url = 'admin_manage_installments.php?';
                $pagination_params = [];
                if (!empty($search_query)) {
                    $pagination_params['search'] = urlencode($search_query);
                }
                if (!empty($status_filter) && $status_filter !== 'all') {
                    $pagination_params['status'] = urlencode($status_filter);
                }
                ?>

                <?php if ($current_page > 1): ?>
                    <li>
                        <a href="<?= $pagination_base_url ?>page=<?= $current_page - 1 ?><?= !empty($pagination_params) ? '&' . http_build_query($pagination_params) : '' ?>"
                           class="flex items-center justify-center px-4 h-10 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700">
                            <span class="sr-only">Previous</span>
                            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 1 1 5l4 4"/>
                            </svg>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li>
                        <a href="<?= $pagination_base_url ?>page=<?= $i ?><?= !empty($pagination_params) ? '&' . http_build_query($pagination_params) : '' ?>"
                           class="flex items-center justify-center px-4 h-10 leading-tight border
                           <?= $i === $current_page ? 'text-blue-600 bg-blue-50 border-blue-300' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-100 hover:text-gray-700' ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <li>
                        <a href="<?= $pagination_base_url ?>page=<?= $current_page + 1 ?><?= !empty($pagination_params) ? '&' . http_build_query($pagination_params) : '' ?>"
                           class="flex items-center justify-center px-4 h-10 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700">
                            <span class="sr-only">Next</span>
                            <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                            </svg>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</main>

<div id="approveConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white text-center">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Konfirmasi Persetujuan Cicilan</h3>
        <p class="text-gray-700 mb-6">Apakah Anda yakin ingin <span class="font-bold text-green-600">MENYETUJUI</span> pengajuan cicilan ini?</p>
        <div class="flex justify-center space-x-4">
            <button type="button" onclick="closeApproveConfirmModal()"
                    class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
            <button type="button" id="confirmApproveButton"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-200">Setujui</button>
        </div>
    </div>
</div>

<div id="rejectConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white text-center">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Konfirmasi Penolakan Cicilan</h3>
        <p class="text-gray-700 mb-6">Apakah Anda yakin ingin <span class="font-bold text-red-600">MENOLAK</span> pengajuan cicilan ini?</p>
        <div class="flex justify-center space-x-4">
            <button type="button" onclick="closeRejectConfirmModal()"
                    class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
            <button type="button" id="confirmRejectButton"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-200">Tolak</button>
        </div>
    </div>
</div>

<script>
    let installmentIdToApprove = null;
    let installmentIdToReject = null;

    function openApproveConfirmModal(installmentId) {
        installmentIdToApprove = installmentId;
        document.getElementById('approveConfirmModal').classList.remove('hidden');
    }

    function closeApproveConfirmModal() {
        document.getElementById('approveConfirmModal').classList.add('hidden');
        installmentIdToApprove = null;
    }

    document.getElementById('confirmApproveButton').onclick = function() {
        if (installmentIdToApprove !== null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_process_installment.php'; // Mengarah ke admin_process_installment.php

            const installmentIdInput = document.createElement('input');
            installmentIdInput.type = 'hidden';
            installmentIdInput.name = 'installment_id';
            installmentIdInput.value = installmentIdToApprove;
            form.appendChild(installmentIdInput);

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'approve';
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        }
        closeApproveConfirmModal(); // Close modal after submission
    };

    function openRejectConfirmModal(installmentId) {
        installmentIdToReject = installmentId;
        document.getElementById('rejectConfirmModal').classList.remove('hidden');
    }

    function closeRejectConfirmModal() {
        document.getElementById('rejectConfirmModal').classList.add('hidden');
        installmentIdToReject = null;
    }

    document.getElementById('confirmRejectButton').onclick = function() {
        if (installmentIdToReject !== null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_process_installment.php'; // Mengarah ke admin_process_installment.php

            const installmentIdInput = document.createElement('input');
            installmentIdInput.type = 'hidden';
            installmentIdInput.name = 'installment_id';
            installmentIdInput.value = installmentIdToReject;
            form.appendChild(installmentIdInput);

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'reject';
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        }
        closeRejectConfirmModal(); // Close modal after submission
    };

    // Tutup modal jika klik di luar area modal
    window.onclick = function(event) {
        const approveModal = document.getElementById('approveConfirmModal');
        const rejectModal = document.getElementById('rejectConfirmModal');

        if (event.target == approveModal) {
            approveModal.classList.add('hidden');
        }
        if (event.target == rejectModal) {
            rejectModal.classList.add('hidden');
        }
    }
</script>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>
