<?php
// admin_manage_products.php
// Halaman ini memungkinkan admin untuk mengelola produk (tugas):
// melihat daftar produk, menambah baru, mengedit detail, dan menghapus produk.

// AKTIFKAN SEMUA PELAPORAN ERROR UNTUK DEBUGGING (HANYA DI LINGKUNGAN PENGEMBANGAN!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inisialisasi message variables di awal
$message = '';
$message_type = ''; // 'success' atau 'error'

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// --- DEBUG: Periksa koneksi database ---
if (!isset($pdo) || !$pdo) {
    $message = "Kesalahan koneksi database: Variabel \$pdo tidak tersedia atau null setelah db_connect.php di-include. Harap periksa file db_connect.php Anda.";
    $message_type = 'error';
    error_log($message);
} else {
    // Koneksi berhasil, lanjutkan dengan logika aplikasi
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
        $message = 'Terjadi kesalahan saat memeriksa status admin: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Hanya lanjutkan jika tidak ada masalah koneksi DB dan admin status sudah dicek
if (!empty($pdo) && (empty($message_type) || $message_type !== 'error')) { 
    // Direktori untuk menyimpan gambar produk (path sistem file)
    $uploadDir = __DIR__ . '/uploads/product_images/';

    // Buat direktori jika belum ada
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) { // Izin 0777 untuk demo, sesuaikan di produksi
            $message = "Gagal membuat direktori unggahan: " . htmlspecialchars($uploadDir) . ". Periksa izin folder.";
            $message_type = 'error';
            error_log("Failed to create upload directory: " . $uploadDir);
        } else {
            error_log("Upload directory created successfully: " . $uploadDir);
        }
    }

    // Fungsi untuk mengelola unggah gambar
    function handleImageUpload($file_data, $upload_dir, $current_image_url = null) {
        error_log("handleImageUpload called. File Data: " . print_r($file_data, true));
        error_log("Upload Dir: " . $upload_dir);

        if (!isset($file_data) || !is_array($file_data) || !isset($file_data['error'])) {
            throw new Exception("Data file unggahan tidak valid.");
        }

        if ($file_data['error'] === UPLOAD_ERR_NO_FILE) {
            error_log("No file uploaded via this input. Returning current image URL: " . ($current_image_url ?? 'None'));
            return $current_image_url; 
        }

        if ($file_data['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = 'Terjadi kesalahan PHP saat mengunggah gambar. Kode error: ' . $file_data['error'];
            switch ($file_data['error']) {
                case UPLOAD_ERR_INI_SIZE: $errorMessage .= ' (Ukuran file melebihi batas upload_max_filesize di php.ini)'; break;
                case UPLOAD_ERR_FORM_SIZE: $errorMessage .= ' (Ukuran file melebihi batas MAX_FILE_SIZE di form HTML)'; break;
                case UPLOAD_ERR_PARTIAL: $errorMessage .= ' (File hanya terunggah sebagian)'; break;
                case UPLOAD_ERR_NO_TMP_DIR: $errorMessage .= ' (Direktori sementara PHP tidak ditemukan atau tidak dapat ditulisi)'; break;
                case UPLOAD_ERR_CANT_WRITE: $errorMessage .= ' (Gagal menulis file ke disk)'; break;
                case UPLOAD_ERR_EXTENSION: $errorMessage .= ' (Ekstensi PHP menghentikan unggahan file - periksa ekstensi PHP Anda)'; break;
                default: $errorMessage .= ' (Kode error tidak diketahui)'; break;
            }
            error_log("Upload error (PHP): " . $errorMessage);
            throw new Exception($errorMessage);
        }
        
        if (empty($file_data['tmp_name']) || !is_uploaded_file($file_data['tmp_name'])) {
            $errorMessage = "File sementara tidak ditemukan atau bukan file yang diunggah. tmp_name: " . ($file_data['tmp_name'] ?? 'NULL');
            error_log($errorMessage);
            throw new Exception($errorMessage);
        }

        $fileName = $file_data['name'];
        $fileTmpName = $file_data['tmp_name'];
        $fileSize = $file_data['size'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $maxFileSize = 5 * 1024 * 1024; // 5 MB

        if (!in_array($fileExt, $allowedExtensions)) {
            error_log("File extension not allowed: " . $fileExt);
            throw new Exception('Tipe file gambar tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.');
        }
        if ($fileSize > $maxFileSize) {
            error_log("File size too large: " . $fileSize . " bytes.");
            throw new Exception('Ukuran file gambar terlalu besar. Maksimal 5MB.');
        }

        $newFileName = uniqid('product_', true) . '.' . $fileExt;
        $fileDestination = $upload_dir . $newFileName; // Ini adalah path sistem file

        if (!is_dir($upload_dir)) {
            error_log("Upload directory does not exist: " . $upload_dir);
            throw new Exception("Direktori tujuan unggahan tidak ada: " . htmlspecialchars($upload_dir));
        }
        if (!is_writable($upload_dir)) {
            error_log("Upload directory is not writable: " . $upload_dir . " (Check permissions!)");
            throw new Exception("Direktori tujuan unggahan tidak dapat ditulisi: " . htmlspecialchars($upload_dir) . ". Periksa izin folder.");
        }

        error_log("Attempting to move uploaded file from " . $fileTmpName . " to " . $fileDestination);
        if (move_uploaded_file($fileTmpName, $fileDestination)) {
            error_log("File successfully moved to: " . $fileDestination);
            
            // Hapus gambar lama jika ada dan berbeda dari yang baru
            if ($current_image_url && !empty($current_image_url) && strpos($current_image_url, 'placehold.co') === false && strpos($current_image_url, 'http') === false) {
                // Konversi URL relatif ke path fisik sebelum menghapus
                // Asumsi URL relatif di database adalah 'uploads/product_images/...'
                $old_image_path = __DIR__ . '/' . $current_image_url; 
                if (file_exists($old_image_path) && ($old_image_path !== $fileDestination)) { // Pastikan bukan file yang baru saja diunggah
                    error_log("Deleting old image: " . $old_image_path);
                    unlink($old_image_path);
                }
            }
            
            // KEMBALIKAN URL RELATIF UNTUK WEB (misalnya 'uploads/product_images/namafile.jpg')
            // Ini diasumsikan root dokumen web Anda berada di 'situs_tugas/'
            return 'uploads/product_images/' . $newFileName; 
        } else {
            $lastError = error_get_last();
            error_log("Failed to move uploaded file. Possible error: " . print_r($lastError, true));
            throw new Exception('Gagal memindahkan file gambar yang diunggah. Pastikan izin direktori benar dan tidak ada masalah server.');
        }
    }


    // Tangani aksi tambah, edit, atau hapus produk
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("POST Request Received. POST Data: " . print_r($_POST, true));
        error_log("FILES Data: " . print_r($_FILES, true));

        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            $initial_upload_error_message = ''; 

            // --- BLOK DIAGNOSIS UNGGAH AWAL YANG LEBIH AGGRESIF ---
            if (($action === 'add_product' || $action === 'edit_product')) {
                if (!isset($_FILES['image'])) {
                    $initial_upload_error_message = 'Input file gambar tidak terdeteksi. Pastikan atribut "name" pada input file adalah "image" dan form memiliki enctype="multipart/form-data".';
                    error_log("CRITICAL: \$_FILES['image'] not set for action " . $action);
                } else if (!is_array($_FILES['image'])) {
                    $initial_upload_error_message = 'Struktur data file unggahan tidak valid. Harap laporkan ini sebagai bug.';
                    error_log("CRITICAL: \$_FILES['image'] is not an array.");
                } else if (isset($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_OK && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $file_upload_error_code = $_FILES['image']['error'];
                    switch ($file_upload_error_code) {
                        case UPLOAD_ERR_INI_SIZE: $initial_upload_error_message = 'Ukuran file melebihi batas upload_max_filesize di php.ini.'; break;
                        case UPLOAD_ERR_FORM_SIZE: $initial_upload_error_message = 'Ukuran file melebihi batas MAX_FILE_SIZE di form HTML.'; break;
                        case UPLOAD_ERR_PARTIAL: $initial_upload_error_message = 'File hanya terunggah sebagian)'; break;
                        case UPLOAD_ERR_NO_TMP_DIR: $initial_upload_error_message = 'Direktori sementara PHP tidak ditemukan atau tidak dapat ditulisi.'; break;
                        case UPLOAD_ERR_CANT_WRITE: $initial_upload_error_message = 'Gagal menulis file ke disk. Periksa izin PHP di folder sementara.'; break;
                        case UPLOAD_ERR_EXTENSION: $initial_upload_error_message = 'Ekstensi PHP menghentikan unggahan file.'; break;
                        default: $initial_upload_error_message = 'Terjadi kesalahan unggah tidak diketahui. Kode: ' . $file_upload_error_code; break;
                    }
                    error_log("Initial PHP upload error detected from \$_FILES array: " . $initial_upload_error_message);
                } else if ($action === 'add_product' && $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                    $initial_upload_error_message = 'Gambar produk wajib diisi untuk penambahan produk baru.';
                }
            }
            // --- END BLOK DIAGNOSIS UNGGAH AWAL ---

            if (empty($initial_upload_error_message)) {
                try {
                    if ($action === 'add_product') {
                        $name = trim($_POST['name']);
                        $description = trim($_POST['description']);
                        $product_price = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
                        $commission_percentage = filter_input(INPUT_POST, 'commission_percentage', FILTER_VALIDATE_FLOAT);
                        $points_awarded = filter_input(INPUT_POST, 'points_awarded', FILTER_VALIDATE_FLOAT);
                        $image_url = null;

                        if (empty($name) || empty($description) || $product_price === false || $product_price <= 0 || $commission_percentage === false || $commission_percentage <= 0 || $points_awarded === false || $points_awarded < 0) {
                            $message = 'Semua field wajib diisi dengan nilai yang valid.';
                            $message_type = 'error';
                        } else {
                            // Panggil handleImageUpload hanya jika file diunggah
                            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                                $image_url = handleImageUpload($_FILES['image'], $uploadDir);
                            } else {
                                // Jika tidak ada file diunggah, gunakan placeholder
                                $image_url = 'https://placehold.co/50x50/CCCCCC/666666?text=No';
                            }
                            
                            $stmt = $pdo->prepare("INSERT INTO products (name, description, image_url, product_price, commission_percentage, points_awarded) VALUES (?, ?, ?, ?, ?, ?)");
                            if ($stmt->execute([$name, $description, $image_url, $product_price, $commission_percentage / 100, $points_awarded])) {
                                $message = 'Produk baru berhasil ditambahkan.';
                                $message_type = 'success';
                            } else {
                                $message = 'Gagal menambahkan produk baru.';
                                $message_type = 'error';
                            }
                        }
                    } elseif ($action === 'edit_product') {
                        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
                        $name = trim($_POST['name']);
                        $description = trim($_POST['description']);
                        $product_price = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
                        $commission_percentage = filter_input(INPUT_POST, 'commission_percentage', FILTER_VALIDATE_FLOAT);
                        $points_awarded = filter_input(INPUT_POST, 'points_awarded', FILTER_VALIDATE_FLOAT);
                        $current_image_url = $_POST['current_image_url'] ?? null;
                        $image_url = $current_image_url; 

                        if (!$product_id || empty($name) || empty($description) || $product_price === false || $product_price <= 0 || $commission_percentage === false || $commission_percentage <= 0 || $points_awarded === false || $points_awarded < 0) {
                            $message = 'Semua field wajib diisi dengan nilai yang valid.';
                            $message_type = 'error';
                        } else {
                            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                                $image_url = handleImageUpload($_FILES['image'], $uploadDir, $current_image_url);
                            } else if (isset($_POST['remove_current_image']) && $_POST['remove_current_image'] == 'true') {
                                if ($current_image_url && !empty($current_image_url) && strpos($current_image_url, 'placehold.co') === false && strpos($current_image_url, 'http') === false) {
                                    // Konversi URL relatif ke path fisik sebelum menghapus
                                    $old_image_path = __DIR__ . '/' . $current_image_url;
                                    if (file_exists($old_image_path)) {
                                        unlink($old_image_path);
                                        error_log("Old image removed: " . $old_image_path);
                                    }
                                }
                                $image_url = null; 
                            }

                            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, image_url = ?, product_price = ?, commission_percentage = ?, points_awarded = ? WHERE id = ?");
                            if ($stmt->execute([$name, $description, $image_url, $product_price, $commission_percentage / 100, $points_awarded, $product_id])) {
                                $message = 'Produk berhasil diperbarui.';
                                $message_type = 'success';
                            } else {
                                $message = 'Gagal memperbarui produk.';
                                $message_type = 'error';
                            }
                        }
                    } elseif ($action === 'delete_product') {
                        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

                        if (!$product_id) {
                            $message = 'ID produk tidak valid.';
                            $message_type = 'error';
                        } else {
                            $stmt_image = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
                            $stmt_image->execute([$product_id]);
                            $product_to_delete = $stmt_image->fetch();
                            $image_to_delete = $product_to_delete['image_url'] ?? null;

                            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                            if ($stmt->execute([$product_id])) {
                                if ($image_to_delete && !empty($image_to_delete) && strpos($image_to_delete, 'placehold.co') === false && strpos($image_to_delete, 'http') === false) {
                                    // Konversi URL relatif ke path fisik sebelum menghapus
                                    $image_path_to_delete = __DIR__ . '/' . $image_to_delete;
                                    if (file_exists($image_path_to_delete)) {
                                        unlink($image_path_to_delete);
                                        error_log("Old image removed during delete: " . $image_path_to_delete);
                                    }
                                }
                                $message = 'Produk berhasil dihapus.';
                                $message_type = 'success';
                            } else {
                                $message = 'Gagal menghapus produk.';
                                $message_type = 'error';
                            }
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error in admin_manage_products (PDO): " . $e->getMessage());
                    $message = 'Terjadi kesalahan database saat memproses aksi: ' . $e->getMessage(); // Lebih spesifik
                    $message_type = 'error';
                } catch (Exception $e) {
                    error_log("Error in admin_manage_products (General): " . $e->getMessage());
                    $message = $e->getMessage(); 
                    $message_type = 'error';
                }
            } else {
                $message = $initial_upload_error_message;
                $message_type = 'error';
            }
        }
    }

    // --- Paginasi dan Pencarian ---
    $items_per_page = 15; // Jumlah item per halaman (maksimal 15 item per halaman)
    $current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $current_page = $current_page ? $current_page : 1; // Default ke halaman 1

    $search_query = trim($_GET['search'] ?? ''); // Ambil langsung dari $_GET dan trim

    $offset = ($current_page - 1) * $items_per_page;

    // Persiapan query SQL dasar
    $base_sql = "SELECT id, name, description, image_url, product_price, commission_percentage, points_awarded FROM products";
    $count_sql = "SELECT COUNT(*) FROM products";
    $where_clauses = [];
    $params = []; // Parameter untuk klausa WHERE (pencarian)

    // Tambahkan kondisi pencarian jika ada
    if (!empty($search_query)) {
        $where_clauses[] = "(name LIKE :search_name OR description LIKE :search_description)";
        $params[':search_name'] = '%' . $search_query . '%';
        $params[':search_description'] = '%' . $search_query . '%';
    }

    // Gabungkan WHERE clauses
    if (!empty($where_clauses)) {
        $base_sql .= " WHERE " . implode(" AND ", $where_clauses);
        $count_sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // Tambahkan ORDER BY, LIMIT dan OFFSET untuk paginasi menggunakan placeholder bernama
    $base_sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
    
    $products = [];
    $total_products = 0;

    if (empty($message_type) || $message_type !== 'error') {
        try {
            // Dapatkan total produk (untuk paginasi)
            $stmt_count = $pdo->prepare($count_sql);
            // Bind parameter untuk count query (tanpa limit/offset, hanya parameter pencarian)
            foreach ($params as $key => $val) {
                $stmt_count->bindValue($key, $val);
            }
            $stmt_count->execute();
            $total_products = $stmt_count->fetchColumn();

            // Dapatkan produk untuk halaman saat ini
            $stmt_products = $pdo->prepare($base_sql);
            
            // Bind parameter pencarian
            foreach ($params as $key => $val) {
                $stmt_products->bindValue($key, $val);
            }

            // Bind parameter LIMIT dan OFFSET secara eksplisit sebagai INT
            $stmt_products->bindValue(':limit', (int)$items_per_page, PDO::PARAM_INT);
            $stmt_products->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

            // DEBUG: Log parameter yang dikirim
            error_log("DEBUG: Final parameters for products query (after bindValue): Limit=" . $items_per_page . ", Offset=" . $offset . ", SearchParams=" . print_r($params, true));

            $stmt_products->execute();
            $products = $stmt_products->fetchAll();

            error_log("DEBUG: Total products matching criteria: " . $total_products);
            error_log("DEBUG: Products fetched for current page: " . count($products));

        } catch (PDOException $e) {
            error_log("Error fetching products with pagination/search: " . $e->getMessage());
            $message = "Gagal memuat daftar produk: " . $e->getMessage();
            $message_type = 'error';
            $products = []; // Pastikan $products kosong jika ada error
        }
    } else {
        $products = []; // Pastikan $products kosong jika ada error DB sebelumnya
    }

    $total_pages = ceil($total_products / $items_per_page);
    $total_pages = max(1, $total_pages); // Pastikan minimal ada 1 halaman
    // Pastikan halaman saat ini tidak melebihi total halaman yang tersedia
    if ($current_page > $total_pages && $total_products > 0) { // Hanya redirect jika ada produk dan halaman melebihi
        $current_page = $total_pages;
        // Redirect untuk memastikan URL konsisten dengan halaman yang valid
        $redirect_url = 'admin_manage_products.php?page=' . $current_page;
        if (!empty($search_query)) {
            $redirect_url .= '&search=' . urlencode($search_query);
        }
        header('Location: ' . $redirect_url);
        exit();
    }


} else {
    $products = []; // Pastikan $products kosong jika ada error koneksi DB di awal
    $total_products = 0;
    $total_pages = 0;
    $current_page = 1;
    $search_query = '';
}

// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<main class="flex-grow container mx-auto p-4 md:p-8">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Kelola Produk/Tugas</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg 
                    <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>" 
                    role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="bg-white p-6 rounded-xl shadow-md mb-8">
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
            <h2 class="text-2xl font-bold text-gray-800">Daftar Produk</h2>
            
            <!-- Formulir Pencarian -->
            <form action="admin_manage_products.php" method="GET" class="flex w-full md:w-auto">
                <input type="text" name="search" placeholder="Cari produk..." 
                       value="<?= htmlspecialchars($search_query) ?>"
                       class="flex-grow shadow-sm appearance-none border rounded-l-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-r-lg shadow-md transition duration-200">
                    Cari
                </button>
            </form>

            <button onclick="openProductModal('add')" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 w-full md:w-auto">
                Tambah Produk Baru
            </button>
        </div>
        
        <?php if (empty($products)): ?>
            <p class="text-gray-600 text-center">Tidak ada produk/tugas yang ditemukan
            <?php if (!empty($search_query)): ?>
                untuk pencarian "<?= htmlspecialchars($search_query) ?>".
            <?php endif; ?>
            Pastikan ada data di tabel 'products' database Anda atau tambahkan produk baru.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                    <thead class="bg-gray-100 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gambar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Komisi (%)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poin</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= htmlspecialchars($product['id']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://placehold.co/50x50/CCCCCC/666666?text=No') ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>" 
                                         class="w-12 h-12 object-cover rounded-full"
                                         onerror="this.onerror=null;this.src='https://placehold.co/50x50/CCCCCC/666666?text=No';">
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-800 max-w-xs overflow-hidden text-ellipsis whitespace-nowrap"><?= htmlspecialchars($product['description']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">Rp <?= number_format($product['product_price'], 2, ',', '.') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= number_format($product['commission_percentage'] * 100, 0) ?>%</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= number_format($product['points_awarded'], 0) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="openProductModal('edit', <?= htmlspecialchars(json_encode($product)) ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                    <button onclick="confirmDelete(<?= $product['id'] ?>)" 
                                            class="text-red-600 hover:text-red-900">Hapus</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Navigasi Paginasi -->
            <?php if ($total_pages > 1): ?>
                <nav class="flex justify-center items-center space-x-2 mt-8">
                    <?php 
                        $pagination_base_url = 'admin_manage_products.php?';
                        if (!empty($search_query)) {
                            $pagination_base_url .= 'search=' . urlencode($search_query) . '&';
                        }
                    ?>

                    <!-- Tombol Previous -->
                    <?php if ($current_page > 1): ?>
                        <a href="<?= $pagination_base_url ?>page=<?= $current_page - 1 ?>" 
                           class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 transition duration-200">
                            &laquo; Sebelumnya
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-400 cursor-not-allowed">
                            &laquo; Sebelumnya
                        </span>
                    <?php endif; ?>

                    <!-- Tautan Halaman -->
                    <?php 
                    // Tentukan rentang halaman yang akan ditampilkan
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);

                    if ($start_page > 1) {
                        echo '<a href="' . $pagination_base_url . 'page=1" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 transition duration-200">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="px-3 py-2 text-gray-700">...</span>';
                        }
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="<?= $pagination_base_url ?>page=<?= $i ?>"
                           class="px-3 py-2 rounded-lg 
                               <?= $i === $current_page ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-100' ?>
                               transition duration-200">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="px-3 py-2 text-gray-700">...</span>';
                        }
                        echo '<a href="' . $pagination_base_url . 'page=' . $total_pages . '" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 transition duration-200">' . $total_pages . '</a>';
                    }
                    ?>

                    <!-- Tombol Next -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?= $pagination_base_url ?>page=<?= $current_page + 1 ?>" 
                           class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 transition duration-200">
                            Berikutnya &raquo;
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-400 cursor-not-allowed">
                            Berikutnya &raquo;
                        </span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<!-- Add/Edit Product Modal -->
<div id="productModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
        <h3 class="text-xl font-bold text-gray-800 mb-4" id="productModalTitle">Tambah Produk Baru</h3>
        <form id="productForm" action="admin_manage_products.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" id="productAction">
            <input type="hidden" name="product_id" id="productId">
            <input type="hidden" name="current_image_url" id="currentImageUrl">

            <div>
                <label for="productName" class="block text-gray-700 text-sm font-semibold mb-2">Nama Produk/Tugas</label>
                <input type="text" id="productName" name="name" required 
                       class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Contoh: Desain Logo Perusahaan">
            </div>
            <div>
                <label for="productDescription" class="block text-gray-700 text-sm font-semibold mb-2">Deskripsi</label>
                <textarea id="productDescription" name="description" rows="3" required 
                          class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          placeholder="Deskripsi singkat tentang tugas..."></textarea>
            </div>
            <div>
                <label for="productImage" class="block text-gray-700 text-sm font-semibold mb-2">Gambar Produk</label>
                <input type="file" id="productImage" name="image" accept="image/*"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin mengubah. Maks 5MB (JPG, PNG, GIF).</p>
                <div id="currentImageContainer" class="mt-2 hidden">
                    <p class="text-sm text-gray-700 mb-2">Gambar Saat Ini:</p>
                    <img id="productImagePreview" src="" alt="Gambar Produk" class="w-24 h-24 object-cover rounded-md border border-gray-300">
                    <label class="inline-flex items-center mt-2 text-sm text-red-600">
                        <input type="checkbox" name="remove_current_image" id="removeCurrentImage" value="true" class="form-checkbox h-4 w-4 text-red-600">
                        <span class="ml-2">Hapus Gambar Saat Ini</span>
                    </label>
                </div>
            </div>
            <div>
                <label for="productPrice" class="block text-gray-700 text-sm font-semibold mb-2">Harga Produk (IDR)</label>
                <input type="number" id="productPrice" name="product_price" step="any" min="1" required 
                       class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Contoh: 150000">
            </div>
            <div>
                <label for="commissionPercentage" class="block text-gray-700 text-sm font-semibold mb-2">Persentase Komisi (%)</label>
                <input type="number" id="commissionPercentage" name="commission_percentage" step="0.01" min="0.01" max="100" required 
                       class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Contoh: 5 (untuk 5%)">
            </div>
            <div>
                <label for="pointsAwarded" class="block text-gray-700 text-sm font-semibold mb-2">Poin Diberikan</label>
                <input type="number" id="pointsAwarded" name="points_awarded" step="1" min="0" required 
                       class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       placeholder="Contoh: 10">
            </div>
            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="closeProductModal()" 
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
                <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">Simpan Produk</button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript untuk modal dan konfirmasi hapus -->
<script>
    const productModal = document.getElementById('productModal');
    const productModalTitle = document.getElementById('productModalTitle');
    const productForm = document.getElementById('productForm');
    const productId = document.getElementById('productId');
    const productAction = document.getElementById('productAction');
    const productName = document.getElementById('productName');
    const productDescription = document.getElementById('productDescription');
    const productImage = document.getElementById('productImage');
    const productImagePreview = document.getElementById('productImagePreview');
    const currentImageContainer = document.getElementById('currentImageContainer');
    const currentImageUrlInput = document.getElementById('currentImageUrl');
    const removeCurrentImageCheckbox = document.getElementById('removeCurrentImage');
    const productPrice = document.getElementById('productPrice');
    const commissionPercentage = document.getElementById('commissionPercentage');
    const pointsAwarded = document.getElementById('pointsAwarded');

    function openProductModal(mode, product = null) {
        productForm.reset(); // Bersihkan form
        currentImageContainer.classList.add('hidden'); // Sembunyikan preview gambar
        removeCurrentImageCheckbox.checked = false; // Uncheck remove image

        if (mode === 'add') {
            productModalTitle.textContent = 'Tambah Produk Baru';
            productAction.value = 'add_product';
            productId.value = '';
            currentImageUrlInput.value = '';
            productImage.required = true; // Gambar wajib diisi saat tambah baru
            productImagePreview.src = ''; // Bersihkan preview saat tambah baru
        } else if (mode === 'edit') {
            productModalTitle.textContent = 'Edit Produk';
            productAction.value = 'edit_product';
            productId.value = product.id;
            productName.value = product.name;
            productDescription.value = product.description;
            // Pastikan nilai float diformat dengan benar untuk input type="number"
            productPrice.value = parseFloat(product.product_price).toFixed(2);
            commissionPercentage.value = parseFloat(product.commission_percentage * 100).toFixed(2); // Komisi dari DB adalah desimal (misal 0.05), tampilkan sebagai persentase (5)
            pointsAwarded.value = parseFloat(product.points_awarded).toFixed(0);
            productImage.required = false; // Gambar tidak wajib diisi saat edit

            if (product.image_url) {
                currentImageContainer.classList.remove('hidden');
                productImagePreview.src = product.image_url;
                currentImageUrlInput.value = product.image_url;
            } else {
                currentImageContainer.classList.add('hidden');
                currentImageUrlInput.value = '';
                productImagePreview.src = ''; // Bersihkan preview jika tidak ada gambar saat ini
            }
        }
        productModal.classList.remove('hidden');
    }

    function closeProductModal() {
        productModal.classList.add('hidden');
    }

    function confirmDelete(productId) {
        // Ganti dengan modal kustom Anda jika tidak ingin menggunakan confirm()
        // Ini adalah implementasi modal kustom yang disarankan
        const confirmationModal = document.createElement('div');
        confirmationModal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center';
        confirmationModal.innerHTML = `
            <div class="relative p-5 border w-96 shadow-lg rounded-md bg-white text-center">
                <h3 class="text-xl font-bold text-gray-800 mb-4">Konfirmasi Hapus</h3>
                <p class="mb-6">Apakah Anda yakin ingin menghapus produk ini? Semua klaim terkait produk ini juga akan dihapus.</p>
                <div class="flex justify-center space-x-4">
                    <button id="cancelDelete" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400 transition duration-200">Batal</button>
                    <button id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition duration-200">Hapus</button>
                </div>
            </div>
        `;
        document.body.appendChild(confirmationModal);

        document.getElementById('cancelDelete').onclick = function() {
            document.body.removeChild(confirmationModal);
        };

        document.getElementById('confirmDeleteBtn').onclick = function() {
            document.body.removeChild(confirmationModal);
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin_manage_products.php';

            const productIdInput = document.createElement('input');
            productIdInput.type = 'hidden';
            productIdInput.name = 'product_id';
            productIdInput.value = productId;
            form.appendChild(productIdInput);

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_product';
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        };
    }

    // Tutup modal jika klik di luar area modal
    window.onclick = function(event) {
        if (event.target == productModal) {
            closeProductModal();
        }
    }

    // ----- Fitur Pratinjau Gambar Sisi Klien -----
    if (productImage) {
        productImage.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    productImagePreview.src = e.target.result;
                    currentImageContainer.classList.remove('hidden'); // Tampilkan container pratinjau
                    removeCurrentImageCheckbox.checked = false; // Pastikan checkbox hapus tidak terpilih
                };
                reader.readAsDataURL(file); // Baca file sebagai URL data
            } else {
                // Jika tidak ada file yang dipilih (misal user membatalkan pilihan),
                // sembunyikan pratinjau atau kembali ke gambar saat ini jika mode edit
                if (productAction.value === 'add_product') {
                    currentImageContainer.classList.add('hidden');
                    productImagePreview.src = '';
                } else if (productAction.value === 'edit_product' && currentImageUrlInput.value) {
                    // Jika mode edit dan ada URL gambar saat ini, tampilkan kembali gambar itu
                    productImagePreview.src = currentImageUrlInput.value;
                    currentImageContainer.classList.remove('hidden');
                } else {
                    // Sembunyikan jika tidak ada file dan tidak ada gambar sebelumnya
                    currentImageContainer.classList.add('hidden');
                    productImagePreview.src = '';
                }
            }
        });
    }
</script>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>
