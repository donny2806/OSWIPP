<?php
// admin_manage_claims.php
// Halaman ini memungkinkan admin untuk meninjau, menyetujui, atau menolak
// klaim tugas yang diajukan oleh pengguna.

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
    error_log("Error checking admin status in admin_manage_claims.php: " . $e->getMessage());
    header('Location: index.php'); // Redirect ke login jika ada masalah otentikasi
    exit();
}

$message = '';
$message_type = ''; // 'success' atau 'error'

// Tangani aksi persetujuan atau penolakan klaim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['claim_id'])) {
        $action = $_POST['action']; // 'approve' atau 'reject'
        $claim_id = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);

        if (!$claim_id) {
            $message = 'ID klaim tidak valid.';
            $message_type = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                // Ambil detail klaim dan user terkait dengan FOR UPDATE
                $stmt = $pdo->prepare("
                    SELECT c.id, c.user_id, c.product_id, c.claim_amount, c.commission_percentage, c.points_awarded, c.status, u.balance, u.points
                    FROM claims c
                    JOIN users u ON c.user_id = u.id
                    WHERE c.id = ? FOR UPDATE
                ");
                $stmt->execute([$claim_id]);
                $claim = $stmt->fetch();

                if (!$claim) {
                    $message = 'Klaim tidak ditemukan.';
                    $message_type = 'error';
                    $pdo->rollBack();
                } elseif ($claim['status'] !== 'pending') {
                    $message = 'Klaim ini sudah diproses.';
                    $message_type = 'error';
                    $pdo->rollBack();
                } else {
                    $user_id_affected = $claim['user_id'];
                    $claim_amount = $claim['claim_amount'];
                    $commission_percentage = $claim['commission_percentage'];
                    $points_awarded = $claim['points_awarded'];
                    $current_user_balance = $claim['balance'];
                    $current_user_points = $claim['points'];

                    if ($action === 'approve') {
                        // Perbarui status klaim menjadi 'approved'
                        $stmt_update_claim = $pdo->prepare("UPDATE claims SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                        $stmt_update_claim->execute([$current_user_id, $claim_id]);

                        // Hitung komisi
                        $commission_amount = $claim_amount * $commission_percentage;
                        
                        // Kembalikan harga produk ke saldo + tambahkan komisi + tambahkan poin
                        $new_balance = $current_user_balance + $claim_amount + $commission_amount;
                        $new_points = $current_user_points + $points_awarded;

                        $stmt_update_user = $pdo->prepare("UPDATE users SET balance = ?, points = ? WHERE id = ?");
                        $stmt_update_user->execute([$new_balance, $new_points, $user_id_affected]);

                        $message = "Klaim tugas ID " . $claim_id . " berhasil disetujui. Saldo user ID " . $user_id_affected . " dikembalikan Rp " . number_format($claim_amount, 2, ',', '.') . ", ditambahkan komisi Rp " . number_format($commission_amount, 2, ',', '.') . " dan " . number_format($points_awarded, 0) . " poin.";
                        $message_type = 'success';
                    } elseif ($action === 'reject') {
                        // Perbarui status klaim menjadi 'rejected'
                        $stmt_update_claim = $pdo->prepare("UPDATE claims SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
                        $stmt_update_claim->execute([$current_user_id, $claim_id]);

                        // Kembalikan saldo yang telah dipotong saat klaim
                        $new_balance = $current_user_balance + $claim_amount;
                        $stmt_update_user_balance = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                        $stmt_update_user_balance->execute([$new_balance, $user_id_affected]);

                        $message = "Klaim tugas ID " . $claim_id . " ditolak. Saldo user ID " . $user_id_affected . " dikembalikan Rp " . number_format($claim_amount, 2, ',', '.') . ".";
                        $message_type = 'success';
                    }
                    $pdo->commit(); // Commit transaksi
                }
            } catch (PDOException $e) {
                $pdo->rollBack(); // Rollback jika ada kesalahan
                error_log("Error processing claim action in admin_manage_claims.php: " . $e->getMessage());
                $message = 'Terjadi kesalahan database saat memproses klaim: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// --- Logika Paginasi, Pencarian, dan Filter ---
$records_per_page = 10;
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$current_page || $current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING); // 'all', 'pending', 'approved', 'rejected'

if (empty($status_filter)) {
    $status_filter = 'all'; // Default filter status
}

$sql_conditions = [];
$sql_bind_params = [];

if (!empty($search_query)) {
    // Search by claim ID, username, or product name
    $sql_conditions[] = "(c.id LIKE :search_id OR u.username LIKE :search_username OR p.name LIKE :search_product_name)";
    $search_param = '%' . $search_query . '%';
    $sql_bind_params[':search_id'] = $search_param;
    $sql_bind_params[':search_username'] = $search_param;
    $sql_bind_params[':search_product_name'] = $search_param;
}

if ($status_filter !== 'all') {
    $sql_conditions[] = "c.status = :status_filter";
    $sql_bind_params[':status_filter'] = $status_filter;
}

$where_clause = '';
if (!empty($sql_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $sql_conditions);
}

// Hitung total record untuk paginasi
try {
    $count_sql = "SELECT COUNT(*)
                  FROM claims c
                  JOIN users u ON c.user_id = u.id
                  JOIN products p ON c.product_id = p.id"
                  . $where_clause;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($sql_bind_params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    error_log("Error counting claims for admin_manage_claims.php: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
    $message = "Gagal menghitung jumlah klaim: " . $e->getMessage();
    $message_type = 'error';
}

// Ambil data klaim untuk ditampilkan dengan paginasi, pencarian, dan filter
$claims = [];
try {
    $sql_claims = "SELECT 
                        c.id, c.user_id, u.username, p.name AS product_name, 
                        c.claim_amount, c.commission_percentage, c.points_awarded, c.status, 
                        c.claimed_at, c.approved_at, a.username AS admin_approver 
                   FROM claims c
                   JOIN users u ON c.user_id = u.id
                   JOIN products p ON c.product_id = p.id
                   LEFT JOIN users a ON c.approved_by = a.id"
                   . $where_clause . " ORDER BY c.claimed_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql_claims);

    // Bind parameters for WHERE conditions
    foreach ($sql_bind_params as $param_name => $param_value) {
        $stmt->bindValue($param_name, $param_value);
    }

    // Bind LIMIT and OFFSET parameters
    $stmt->bindValue(':limit', (int)$records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $claims = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching claims for admin_manage_claims.php: " . $e->getMessage());
    $message = "Gagal memuat daftar klaim tugas: " . $e->getMessage();
    $message_type = 'error';
}

// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<main class="flex-grow container mx-auto p-4 md:p-8">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Kelola Klaim Tugas</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg
                    <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>"
                    role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Daftar Klaim Tugas</h2>

        <!-- Search and Filter Form -->
        <form action="admin_manage_claims.php" method="GET" class="mb-6 flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4">
            <div class="relative w-full sm:w-1/2">
                <input type="text" name="search" placeholder="Cari ID klaim, username, atau produk..."
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
                </select>
            </div>
            <button type="submit"
                    class="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
                Filter
            </button>
            <?php if (!empty($search_query) || (!empty($status_filter) && $status_filter !== 'all')): ?>
                <a href="admin_manage_claims.php" class="w-full sm:w-auto px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 text-center transition duration-200">Reset</a>
            <?php endif; ?>
        </form>

        <div class="overflow-x-auto">
            <?php if (empty($claims) && empty($message)): ?>
                <p class="text-gray-600 text-center py-8">Tidak ada klaim tugas ditemukan.</p>
            <?php elseif (empty($claims) && !empty($message) && $message_type === 'error'): ?>
                 <p class="text-gray-600 text-center py-8">Terjadi kesalahan saat memuat klaim. Silakan periksa pesan kesalahan di atas.</p>
            <?php else: ?>
                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                    <thead class="bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Klaim</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengguna</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk/Tugas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Klaim</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Komisi (%)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poin</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu Klaim</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diproses Oleh</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($claims as $claim): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($claim['id']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($claim['username']) ?> (ID: <?= htmlspecialchars($claim['user_id']) ?>)</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($claim['product_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($claim['claim_amount'], 2, ',', '.') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= number_format($claim['commission_percentage'] * 100, 0) ?>%</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= number_format($claim['points_awarded'], 0) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                        $status_color = '';
                                        switch ($claim['status']) {
                                            case 'pending': $status_color = 'bg-yellow-100 text-yellow-800'; break;
                                            case 'approved': $status_color = 'bg-green-100 text-green-800'; break;
                                            case 'rejected': $status_color = 'bg-red-100 text-red-800'; break;
                                            default: $status_color = 'bg-gray-100 text-gray-800'; break;
                                        }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                        <?= ucfirst($claim['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($claim['claimed_at'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($claim['admin_approver'] ?? 'N/A') ?>
                                    <?php if ($claim['approved_at']): ?>
                                        <br><span class="text-xs"><?= date('d M Y H:i', strtotime($claim['approved_at'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($claim['status'] === 'pending'): ?>
                                        <button type="button" onclick="openApproveConfirmModal(<?= $claim['id'] ?>)" class="text-green-600 hover:text-green-900 mr-3">Setujui</button>
                                        <button type="button" onclick="openRejectConfirmModal(<?= $claim['id'] ?>)" class="text-red-600 hover:text-red-900">Tolak</button>
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

    <!-- Pagination Links -->
    <?php if ($total_pages > 1 || !empty($search_query) || (!empty($status_filter) && $status_filter !== 'all')): ?>
        <nav class="mt-8 flex justify-center" aria-label="Pagination">
            <ul class="flex items-center -space-x-px h-10 text-base">
                <?php
                $pagination_base_url = 'admin_manage_claims.php?';
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

<!-- Approve Confirmation Modal -->
<div id="approveConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white text-center">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Konfirmasi Persetujuan Klaim</h3>
        <p class="text-gray-700 mb-6">Apakah Anda yakin ingin <span class="font-bold text-green-600">MENYETUJUI</span> klaim tugas ini?</p>
        <div class="flex justify-center space-x-4">
            <button type="button" onclick="closeApproveConfirmModal()"
                    class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
            <button type="button" id="confirmApproveButton"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-200">Setujui</button>
        </div>
    </div>
</div>

<!-- Reject Confirmation Modal -->
<div id="rejectConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white text-center">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Konfirmasi Penolakan Klaim</h3>
        <p class="text-gray-700 mb-6">Apakah Anda yakin ingin <span class="font-bold text-red-600">MENOLAK</span> klaim tugas ini?</p>
        <div class="flex justify-center space-x-4">
            <button type="button" onclick="closeRejectConfirmModal()"
                    class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
            <button type="button" id="confirmRejectButton"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-200">Tolak</button>
        </div>
    </div>
</div>

<!-- JavaScript untuk modal konfirmasi -->
<script>
    let claimIdToApprove = null;
    let claimIdToReject = null;

    function openApproveConfirmModal(claimId) {
        claimIdToApprove = claimId;
        document.getElementById('approveConfirmModal').classList.remove('hidden');
    }

    function closeApproveConfirmModal() {
        document.getElementById('approveConfirmModal').classList.add('hidden');
        claimIdToApprove = null;
    }

    document.getElementById('confirmApproveButton').onclick = function() {
        if (claimIdToApprove !== null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_manage_claims.php';

            const claimIdInput = document.createElement('input');
            claimIdInput.type = 'hidden';
            claimIdInput.name = 'claim_id';
            claimIdInput.value = claimIdToApprove;
            form.appendChild(claimIdInput);

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

    function openRejectConfirmModal(claimId) {
        claimIdToReject = claimId;
        document.getElementById('rejectConfirmModal').classList.remove('hidden');
    }

    function closeRejectConfirmModal() {
        document.getElementById('rejectConfirmModal').classList.add('hidden');
        claimIdToReject = null;
    }

    document.getElementById('confirmRejectButton').onclick = function() {
        if (claimIdToReject !== null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_manage_claims.php';

            const claimIdInput = document.createElement('input');
            claimIdInput.type = 'hidden';
            claimIdInput.name = 'claim_id';
            claimIdInput.value = claimIdToReject;
            form.appendChild(claimIdInput);

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
