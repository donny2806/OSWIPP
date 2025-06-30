<?php
// admin_manage_accounts.php
// Halaman ini memungkinkan admin untuk mengelola akun pengguna:
// melihat daftar pengguna, mengedit detail (level, saldo, poin), dan menghapus akun.

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
    error_log("Error checking admin status: " . $e->getMessage());
    header('Location: index.php'); // Redirect ke login jika ada masalah otentikasi
    exit();
}

$message = '';
$message_type = ''; // 'success' atau 'error'

// Tangani aksi edit atau delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $user_id_to_process = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        if (!$user_id_to_process) {
            $message = 'ID pengguna tidak valid.';
            $message_type = 'error';
        } elseif ($user_id_to_process == $current_user_id) {
            $message = 'Anda tidak dapat mengelola akun Anda sendiri dari sini.';
            $message_type = 'error';
        } else {
            try {
                if ($action === 'edit_user') {
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    $balance = filter_input(INPUT_POST, 'balance', FILTER_VALIDATE_FLOAT);
                    $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_FLOAT);
                    $membership_level = $_POST['membership_level'];

                    // !!! PERBAIKAN DI SINI: Tambahkan 'VVIP' ke array validasi level keanggotaan
                    $valid_membership_levels = ['Bronze', 'Silver', 'Gold', 'Platinum', 'VVIP'];

                    // Validasi dasar
                    if (empty($username) || empty($email) || $balance === false || $points === false || !in_array($membership_level, $valid_membership_levels)) {
                        $message = 'Semua field wajib diisi dan valid. Pastikan level keanggotaan valid.';
                        $message_type = 'error';
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $message = 'Format email tidak valid.';
                        $message_type = 'error';
                    } else {
                        // Periksa duplikasi username/email (kecuali untuk user_id yang sedang diedit)
                        $stmt_check_duplicate = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
                        $stmt_check_duplicate->execute([$username, $email, $user_id_to_process]);
                        if ($stmt_check_duplicate->fetchColumn() > 0) {
                            $message = 'Username atau email sudah digunakan oleh pengguna lain.';
                            $message_type = 'error';
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, balance = ?, points = ?, membership_level = ? WHERE id = ?");
                            if ($stmt->execute([$username, $email, $balance, $points, $membership_level, $user_id_to_process])) {
                                $message = 'Akun pengguna berhasil diperbarui.';
                                $message_type = 'success';
                            } else {
                                $message = 'Gagal memperbarui akun pengguna.';
                                $message_type = 'error';
                            }
                        }
                    }
                } elseif ($action === 'delete_user') {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$user_id_to_process])) {
                        $message = 'Akun pengguna berhasil dihapus.';
                        $message_type = 'success';
                    } else {
                        $message = 'Gagal menghapus akun pengguna.';
                        $message_type = 'error';
                    }
                }
            } catch (PDOException $e) {
                error_log("Error in admin_manage_accounts: " . $e->getMessage());
                $message = 'Terjadi kesalahan database: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// --- Logika Paginasi dan Pencarian ---
$records_per_page = 10; // Jumlah record per halaman
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$current_page || $current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
$search_param = '%' . $search_query . '%';

$sql_conditions = [];
$sql_bind_params = []; // Mengubah ini untuk menyimpan named parameters

if (!empty($search_query)) {
    $sql_conditions[] = "(username LIKE :search_username OR email LIKE :search_email)";
    $sql_bind_params[':search_username'] = $search_param;
    $sql_bind_params[':search_email'] = $search_param;
}

$where_clause = '';
if (!empty($sql_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $sql_conditions);
}

// Hitung total record untuk paginasi
try {
    $count_sql = "SELECT COUNT(*) FROM users" . $where_clause;
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($sql_bind_params); // Menggunakan named parameters di sini
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    error_log("Error counting users for admin_manage_accounts: " . $e->getMessage());
    $total_records = 0;
    $total_pages = 1;
    $message = "Gagal menghitung jumlah pengguna: " . $e->getMessage(); // Menambahkan pesan exception
    $message_type = 'error';
}


// Ambil data pengguna untuk ditampilkan dengan paginasi dan pencarian
$users = [];
try {
    // Membangun query secara dinamis, mengikat LIMIT dan OFFSET di akhir.
    $sql_users = "SELECT id, username, email, balance, points, membership_level, is_admin, created_at FROM users" . $where_clause . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql_users);

    // Bind parameter untuk kondisi WHERE (sekarang menggunakan named parameters)
    foreach ($sql_bind_params as $param_name => $param_value) {
        $stmt->bindValue($param_name, $param_value);
    }

    // Bind parameter LIMIT dan OFFSET secara eksplisit sebagai integer
    $stmt->bindValue(':limit', (int)$records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users for admin_manage_accounts: " . $e->getMessage());
    $message = "Gagal memuat daftar pengguna: " . $e->getMessage(); // Menambahkan pesan exception
    $message_type = 'error';
}

// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<main class="flex-grow container mx-auto p-4 md:p-8">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Kelola Akun Pengguna</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg
                            <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>"
                            role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Daftar Pengguna</h2>

        <!-- Search Form -->
        <form action="admin_manage_accounts.php" method="GET" class="mb-6 flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4">
            <div class="relative w-full sm:w-1/2">
                <input type="text" name="search" placeholder="Cari berdasarkan username atau email..."
                        value="<?= htmlspecialchars($search_query) ?>"
                        class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 pl-10 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <button type="submit"
                    class="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
                Cari
            </button>
            <?php if (!empty($search_query)): ?>
                <a href="admin_manage_accounts.php" class="w-full sm:w-auto px-6 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 text-center transition duration-200">Reset</a>
            <?php endif; ?>
        </form>

        <div class="overflow-x-auto">
            <?php if (empty($users) && empty($message)): ?>
                <p class="text-gray-600 text-center py-8">Tidak ada pengguna ditemukan.</p>
            <?php elseif (empty($users) && !empty($message) && $message_type === 'error'): ?>
                   <p class="text-gray-600 text-center py-8">Terjadi kesalahan saat memuat pengguna. Silakan periksa pesan kesalahan di atas.</p>
            <?php else: ?>
                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                    <thead class="bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poin</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin?</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terdaftar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($user['id']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($user['username']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($user['email']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($user['balance'], 2, ',', '.') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= number_format($user['points'], 0, ',', '.') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($user['membership_level']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $user['is_admin'] ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= $user['is_admin'] ? 'Ya' : 'Tidak' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($user['created_at'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($user['id'] != $current_user_id): // Admin tidak bisa mengedit/menghapus akun sendiri ?>
                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($user)) ?>)"
                                                class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                        <button onclick="openDeleteConfirmModal(<?= $user['id'] ?>)"
                                                class="text-red-600 hover:text-red-900">Hapus</button>
                                    <?php else: ?>
                                        <span class="text-gray-500">Tidak Bisa</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- Pagination Links - Moved outside the table section -->
    <?php if (!empty($users) || !empty($search_query)): // Only show pagination if there are users or a search query is active ?>
        <nav class="mt-8 flex justify-center" aria-label="Pagination">
            <ul class="flex items-center -space-x-px h-10 text-base">
                <?php if ($current_page > 1): ?>
                    <li>
                        <a href="?page=<?= $current_page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"
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
                        <a href="?page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"
                           class="flex items-center justify-center px-4 h-10 leading-tight border
                           <?= $i === $current_page ? 'text-blue-600 bg-blue-50 border-blue-300' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-100 hover:text-gray-700' ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <li>
                        <a href="?page=<?= $current_page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>"
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

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Edit Pengguna</h3>
        <form id="editUserForm" action="admin_manage_accounts.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="editUserId">
            <div>
                <label for="editUsername" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                <input type="text" id="editUsername" name="username" required
                       class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
                <label for="editEmail" class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                <input type="email" id="editEmail" name="email" required
                       class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
                <label for="editBalance" class="block text-gray-700 text-sm font-semibold mb-2">Saldo</label>
                <input type="number" id="editBalance" name="balance" step="any" required
                       class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
                <label for="editPoints" class="block text-gray-700 text-sm font-semibold mb-2">Poin</label>
                <input type="number" id="editPoints" name="points" step="any" required
                       class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
                <label for="editMembershipLevel" class="block text-gray-700 text-sm font-semibold mb-2">Level Keanggotaan</label>
                <select id="editMembershipLevel" name="membership_level" required
                         class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="Bronze">Bronze</option>
                    <option value="Silver">Silver</option>
                    <option value="Gold">Gold</option>
                    <option value="Platinum">Platinum</option>
                    <option value="VVIP">VVIP</option> <!-- !!! PENAMBAHAN OPSI VVIP DI SINI !!! -->
                </select>
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeEditModal()"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white text-center">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Konfirmasi Penghapusan</h3>
        <p class="text-gray-700 mb-6">Apakah Anda yakin ingin menghapus akun pengguna ini? Semua data terkait (transaksi, klaim, chat) akan dihapus secara permanen.</p>
        <div class="flex justify-center space-x-4">
            <button type="button" onclick="closeDeleteConfirmModal()"
                    class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
            <button type="button" id="confirmDeleteButton"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-200">Hapus</button>
        </div>
    </div>
</div>


<!-- JavaScript untuk modal dan konfirmasi hapus -->
<script>
    function openEditModal(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUsername').value = user.username;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editBalance').value = parseFloat(user.balance).toFixed(2); // Pastikan format 2 desimal
        document.getElementById('editPoints').value = parseFloat(user.points).toFixed(0); // Poin tanpa desimal
        document.getElementById('editMembershipLevel').value = user.membership_level;
        document.getElementById('editUserModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }

    let userIdToDelete = null; // Variable to store the user ID for deletion

    function openDeleteConfirmModal(userId) {
        userIdToDelete = userId;
        document.getElementById('deleteConfirmModal').classList.remove('hidden');
    }

    function closeDeleteConfirmModal() {
        document.getElementById('deleteConfirmModal').classList.add('hidden');
        userIdToDelete = null; // Reset the user ID
    }

    document.getElementById('confirmDeleteButton').onclick = function() {
        if (userIdToDelete !== null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_manage_accounts.php';

            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userIdToDelete;
            form.appendChild(userIdInput);

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_user';
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        }
    };


    // Tutup modal jika klik di luar area modal
    window.onclick = function(event) {
        const editModal = document.getElementById('editUserModal');
        const deleteModal = document.getElementById('deleteConfirmModal');

        if (event.target == editModal) {
            editModal.classList.add('hidden');
        }
        if (event.target == deleteModal) {
            deleteModal.classList.add('hidden');
        }
    }
</script>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>
