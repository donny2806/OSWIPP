<?php
// api/chat_api.php
// Endpoint API ini mengelola pengiriman dan pengambilan pesan chat,
// dengan dukungan yang lebih baik untuk admin dan pengguna.

// Mulai sesi PHP jika belum dimulai. Penting untuk mengidentifikasi pengguna.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json'); // Pastikan respons dalam format JSON

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login untuk menggunakan chat.']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$is_current_user_admin = false;

// Periksa apakah pengguna saat ini adalah admin
try {
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data_check_admin = $stmt->fetch();
    if ($user_data_check_admin && $user_data_check_admin['is_admin']) {
        $is_current_user_admin = true;
    }
} catch (PDOException $e) {
    error_log("Error checking admin status for chat_api: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Kesalahan server saat memverifikasi status admin: ' . $e->getMessage()]);
    exit();
}


// --- GET Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        $action = $_GET['action'];

        if ($action === 'getMessages') {
            $target_user_id = filter_input(INPUT_GET, 'target_user_id', FILTER_VALIDATE_INT);
            // Tambahkan parameter opsional untuk ID pesan terakhir
            $last_message_id = filter_input(INPUT_GET, 'last_message_id', FILTER_VALIDATE_INT);
            $messages = [];

            try {
                $sql_conditions = [];
                $sql_params = [];

                if ($is_current_user_admin && $target_user_id) {
                    // ADMIN melihat chat dengan user spesifik
                    // Pesan antara admin_id dan target_user_id
                    // Termasuk pesan dari user ke admin umum (receiver_id IS NULL)
                    // dan pesan broadcast dari admin (sender admin, receiver_id IS NULL)
                    $sql_conditions[] = "(c.sender_id = :admin_id AND c.receiver_id = :target_user_id)";
                    $sql_conditions[] = "(c.sender_id = :target_user_id AND c.receiver_id = :admin_id)";
                    // Pesan dari user ke admin umum (receiver_id IS NULL). Ini akan muncul di chat admin dengan user tersebut.
                    $sql_conditions[] = "(c.sender_id = :target_user_id AND c.receiver_id IS NULL AND u.is_admin = FALSE)"; 
                    // Pesan broadcast dari admin (sender admin, receiver_id IS NULL). Ini akan muncul di chat admin dengan user manapun.
                    $sql_conditions[] = "(c.sender_id = :admin_id_broadcast AND c.receiver_id IS NULL AND (SELECT is_admin FROM users WHERE id = :admin_id_broadcast_check) = TRUE)"; 

                    $sql_params[':admin_id'] = $current_user_id;
                    $sql_params[':target_user_id'] = $target_user_id;
                    $sql_params[':admin_id_broadcast'] = $current_user_id;
                    $sql_params[':admin_id_broadcast_check'] = $current_user_id;

                } else {
                    // PENGGUNA BIASA melihat chat dengan admin, ATAU admin tanpa target spesifik
                    // (admin tanpa target spesifik akan melihat chat broadcast dan pesan dari user ke admin)
                    $sql_conditions[] = "(c.sender_id = :current_user_id AND c.receiver_id IN (SELECT id FROM users WHERE is_admin = TRUE))";
                    $sql_conditions[] = "(c.sender_id IN (SELECT id FROM users WHERE is_admin = TRUE) AND c.receiver_id = :current_user_id)";
                    $sql_conditions[] = "(u.is_admin = TRUE AND c.receiver_id IS NULL)";

                    $sql_params[':current_user_id'] = $current_user_id;
                }

                $sql = "SELECT c.id, c.sender_id, u.username AS sender_username, c.message, c.image_url, c.sent_at
                        FROM chats c
                        JOIN users u ON c.sender_id = u.id
                        WHERE (" . implode(" OR ", $sql_conditions) . ")";

                // Tambahkan kondisi untuk hanya mengambil pesan yang lebih baru dari last_message_id
                if ($last_message_id) {
                    $sql .= " AND c.id > :last_message_id";
                    $sql_params[':last_message_id'] = $last_message_id;
                }
                
                $sql .= " ORDER BY c.sent_at ASC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($sql_params);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($messages);

            } catch (PDOException $e) {
                error_log("Error fetching chat messages: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Gagal mengambil pesan: ' . $e->getMessage()]);
            }
        } elseif ($action === 'getUnreadStatusAndUsers') { // Endpoint untuk dashboard admin (list pengguna)
            if (!$is_current_user_admin) {
                echo json_encode(['success' => false, 'message' => 'Akses ditolak. Hanya admin yang dapat melihat status belum dibaca.']);
                exit();
            }

            try {
                // Mengambil daftar semua pengguna non-admin beserta:
                // - last_message_time: waktu pesan terakhir antara user ini dan admin saat ini
                // - unread_messages_count_direct: jumlah pesan belum dibaca dari user ini ke admin saat ini (receiver_id = admin_id)
                // - unread_messages_count_broadcast_from_user: jumlah pesan belum dibaca dari user ini ke admin umum (receiver_id IS NULL)
                // Diurutkan berdasarkan last_message_time (terbaru di atas, NULL di bawah) dan kemudian username.
                $stmt = $pdo->prepare("
                    SELECT 
                        u.id, 
                        u.username,
                        (
                            SELECT MAX(sent_at) 
                            FROM chats 
                            WHERE (sender_id = u.id AND receiver_id = :current_admin_id_a) 
                               OR (sender_id = :current_admin_id_b AND receiver_id = u.id)
                               OR (sender_id = u.id AND receiver_id IS NULL) -- Mempertimbangkan semua pesan dari user ke admin umum untuk waktu pesan terakhir
                               OR (sender_id = :current_admin_id_c AND receiver_id IS NULL AND (SELECT is_admin FROM users WHERE id = :current_admin_id_d) = TRUE) 
                        ) AS last_message_time,
                        (SELECT COUNT(*) FROM chats WHERE sender_id = u.id AND receiver_id = :current_admin_id_e AND is_read_by_admin = FALSE) AS unread_messages_count_direct,
                        (SELECT COUNT(*) FROM chats WHERE sender_id = u.id AND receiver_id IS NULL AND is_read_by_admin = FALSE) AS unread_messages_count_broadcast_from_user
                    FROM users u
                    WHERE u.is_admin = FALSE
                    ORDER BY 
                        CASE WHEN last_message_time IS NULL THEN 1 ELSE 0 END, 
                        last_message_time DESC, 
                        u.username ASC
                ");
                $stmt->bindParam(':current_admin_id_a', $current_user_id, PDO::PARAM_INT);
                $stmt->bindParam(':current_admin_id_b', $current_user_id, PDO::PARAM_INT);
                $stmt->bindParam(':current_admin_id_c', $current_user_id, PDO::PARAM_INT);
                $stmt->bindParam(':current_admin_id_d', $current_user_id, PDO::PARAM_INT);
                $stmt->bindParam(':current_admin_id_e', $current_user_id, PDO::PARAM_INT);
                $stmt->execute();
                $users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'users' => $users_data]);

            } catch (PDOException $e) {
                error_log("Error fetching unread status and users: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Gagal mengambil daftar pengguna: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Aksi GET tidak valid.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Aksi GET tidak ditentukan.']);
    }
} 
// --- POST Requests ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'sendMessage') {
            $message_text = $_POST['message'] ?? null;
            $image_url = $_POST['image_url'] ?? null; // <--- chat.js akan mengirim URL gambar ke sini
            $receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT); // ID penerima (khususnya untuk admin)

            // Validasi input pesan/gambar
            if (empty($message_text) && empty($image_url)) {
                echo json_encode(['success' => false, 'message' => 'Pesan atau gambar tidak boleh kosong.']);
                exit();
            }

            $final_receiver_id = null; 
            $is_read_by_admin_status = FALSE; // Default: pesan dari user ke admin belum dibaca

            if ($is_current_user_admin) {
                // Admin mengirim pesan
                // Jika receiver_id disediakan, kirim ke user spesifik
                // Jika tidak, receiver_id akan NULL (broadcast dari admin)
                $final_receiver_id = $receiver_id; 
                // Admin tidak bisa mengirim pesan ke dirinya sendiri
                if ($final_receiver_id == $current_user_id) {
                    echo json_encode(['success' => false, 'message' => 'Admin tidak dapat mengirim pesan ke diri sendiri.']);
                    exit();
                }
                // Jika receiver_id ada, cek apakah user tersebut benar-benar ada dan bukan admin
                if ($final_receiver_id !== null) {
                    try {
                        $stmt_check_receiver = $pdo->prepare("SELECT id, is_admin FROM users WHERE id = ?");
                        $stmt_check_receiver->execute([$final_receiver_id]);
                        $rec_data = $stmt_check_receiver->fetch();
                        if (!$rec_data || $rec_data['is_admin']) {
                            echo json_encode(['success' => false, 'message' => 'Penerima tidak valid atau admin tidak dapat chat dengan admin lain.']);
                            exit();
                        }
                    } catch (PDOException $e) {
                           error_log("Error checking receiver for admin message: " . $e->getMessage());
                           echo json_encode(['success' => false, 'message' => 'Kesalahan database saat memeriksa penerima: ' . $e->getMessage()]);
                           exit();
                    }
                }
                // Pesan dari admin diasumsikan sudah dibaca oleh admin (pengirimnya)
                $is_read_by_admin_status = TRUE; 
            } else {
                // Pengguna biasa mengirim pesan
                // Pesan dari pengguna biasa diasumsikan selalu ditujukan ke admin.
                // Kita akan mencari ID admin pertama yang ditemukan sebagai target.
                try {
                    $stmt_admin = $pdo->query("SELECT id FROM users WHERE is_admin = TRUE LIMIT 1");
                    $admin_user = $stmt_admin->fetch();
                    $final_receiver_id = $admin_user ? $admin_user['id'] : null; // Kirim ke admin pertama yang ditemukan, atau NULL jika tidak ada admin
                    
                    // Jika tidak ada admin, user tidak bisa chat
                    if ($final_receiver_id === null) {
                        echo json_encode(['success' => false, 'message' => 'Tidak ada admin yang tersedia untuk chat saat ini. Pastikan ada akun admin yang aktif.']);
                        exit();
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching admin for user message: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Kesalahan database saat mencari admin: ' . $e->getMessage()]);
                    exit();
                }
            }

            try {
                // Kolom is_read_by_admin ditambahkan di sini.
                // TRUE jika pengirim adalah admin, FALSE jika pengirim adalah user biasa.
                // CAST boolean to integer (0 or 1) for database compatibility
                $is_read_by_admin_int = (int)$is_read_by_admin_status; 

                $stmt = $pdo->prepare("INSERT INTO chats (sender_id, receiver_id, message, image_url, is_read_by_admin) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$current_user_id, $final_receiver_id, $message_text, $image_url, $is_read_by_admin_int]);

                echo json_encode(['success' => true, 'message' => 'Pesan berhasil dikirim.']);
            } catch (PDOException $e) {
                error_log("Error sending chat message: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Gagal mengirim pesan ke database: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Aksi POST tidak valid.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Aksi POST tidak ditentukan.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
}
?>
