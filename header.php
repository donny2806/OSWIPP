<?php
// header.php
// File ini berisi bagian kepala (header) dari setiap halaman, termasuk tag HTML head,
// tautan CSS, dan navigasi utama.

// Mulai sesi PHP jika belum dimulai. Ini penting untuk mengelola status login pengguna.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Inisialisasi variabel user info
$loggedIn = false;
$username = 'Guest';
$balance = 0.00;
$points = 0.00;
$membership_level = 'N/A';
$is_admin = false;
$profile_picture_url = 'uploads/profile_pictures/default.png'; // Default
$full_name = 'N/A';
$phone_number = 'N/A';
$email = 'N/A'; // Tambahkan inisialisasi email
$loggedInUserId = 'null'; // Inisialisasi untuk JS

// Periksa apakah pengguna sudah login
if (isset($_SESSION['user_id'])) {
    $loggedIn = true;
    $user_id = $_SESSION['user_id'];
    $loggedInUserId = json_encode($user_id); // Set user ID untuk JavaScript
    
    // Ambil informasi pengguna dari database
    try {
        // Pastikan 'email' juga diambil dari database
        $stmt = $pdo->prepare("SELECT username, balance, points, membership_level, is_admin, profile_picture_url, full_name, phone_number, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            $username = htmlspecialchars($user['username']);
            $balance = number_format($user['balance'], 2, ',', '.'); // Format saldo IDR
            $points = number_format($user['points'], 0, ',', '.'); // Format poin (tidak ada desimal untuk poin)
            $membership_level = htmlspecialchars($user['membership_level']);
            $is_admin = $user['is_admin'];
            $profile_picture_url = htmlspecialchars($user['profile_picture_url'] ?? 'uploads/profile_pictures/default.png');
            $full_name = htmlspecialchars($user['full_name'] ?? 'Nama Lengkap Belum Diatur');
            $phone_number = htmlspecialchars($user['phone_number'] ?? 'Nomor HP Belum Diatur');
            $email = htmlspecialchars($user['email'] ?? 'Email Belum Diatur'); // Ambil email
        } else {
            // Jika user_id di sesi tidak valid, hapus sesi
            session_unset();
            session_destroy();
            $loggedIn = false;
            $loggedInUserId = 'null';
        }
    } catch (PDOException $e) {
        // Tangani error, misal log error dan reset sesi
        error_log("Error fetching user data in header: " . $e->getMessage());
        session_unset();
        session_destroy();
        $loggedIn = false;
        $loggedInUserId = 'null';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deprintz App</title> <!-- Judul telah diubah di sini -->
    <!-- Tailwind CSS CDN untuk styling responsif -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome untuk ikon (misalnya ikon burger, chat) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* Pastikan html dan body tidak memiliki margin atau padding bawaan */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%; /* Penting untuk flexbox 100vh */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5; /* Warna latar belakang umum */
            padding-top: 64px; /* Sesuaikan dengan tinggi header */
            /* padding-bottom dihapus karena footer tidak fixed */
            min-height: 100vh; /* Pastikan body setidaknya setinggi viewport */
            display: flex;
            flex-direction: column; /* Atur body sebagai flex container kolom */
        }
        /* Header beku, putih, sedikit transparan */
        .header-fixed {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: rgba(255, 255, 255, 0.9); /* Putih dengan sedikit transparansi */
            backdrop-filter: blur(5px); /* Efek blur untuk latar belakang */
            z-index: 1000; /* Pastikan header di atas konten lain */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); /* Sedikit bayangan */
        }
        /* Konten utama harus fleksibel untuk mengisi ruang */
        main {
            flex-grow: 1; /* Membuat main mengambil sisa ruang vertikal yang tersedia */
        }
        /* Custom styling for burger menu icon */
        .burger-icon {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 24px;
            height: 18px;
            cursor: pointer;
            transition: all 0.3s ease-in-out;
            z-index: 1001; /* Pastikan burger icon di atas overlay */
        }
        .burger-icon span {
            display: block;
            width: 100%;
            height: 2px;
            background: #333;
            border-radius: 9999px;
            transition: all 0.3s ease-in-out;
        }
        .burger-icon.open span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }
        .burger-icon.open span:nth-child(2) {
            opacity: 0;
        }
        .burger-icon.open span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }

        /* Overlay untuk menu burger */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none; /* Sembunyikan secara default */
        }
        .sidebar {
            position: fixed;
            top: 0;
            right: -300px; /* Sembunyikan di luar layar */
            width: 300px;
            height: 100%;
            background-color: #fff;
            box-shadow: -5px 0 15px rgba(0,0,0,0.1);
            transition: right 0.3s ease-in-out;
            z-index: 1000;
            padding: 24px;
            overflow-y: auto;
        }
        .sidebar.open {
            right: 0; /* Tampilkan di layar */
        }
        .sidebar-header {
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .sidebar-menu a {
            display: block;
            padding: 12px 0;
            color: #333;
            font-weight: 500;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }
        .sidebar-menu a:hover {
            background-color: #f5f5f5;
        }

        /* Live Chat Button */
        .live-chat-button {
            position: fixed; /* Tetap fixed agar selalu terlihat */
            bottom: 20px; /* Sesuaikan posisi agar tidak terlalu dekat dengan tepi */
            right: 20px;
            background-color: #3B82F6; /* Biru Tailwind */
            color: white;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 995; /* Di atas konten normal */
            transition: transform 0.2s ease-in-out;
        }
        .live-chat-button:hover {
            transform: scale(1.05);
        }

        /* Live Chat Window */
        .chat-window {
            position: fixed; /* Tetap fixed agar selalu terlihat */
            bottom: 90px; /* Di atas tombol chat */
            right: 20px;
            width: 350px;
            height: 450px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: none; /* Sembunyikan secara default */
            flex-direction: column;
            overflow: hidden;
            z-index: 996; /* Di atas tombol chat */
        }
        .chat-header {
            background-color: #3B82F6;
            color: white;
            padding: 1rem;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-messages {
            flex-grow: 1;
            padding: 1rem;
            overflow-y: auto;
            background-color: #f9fafb;
        }
        .chat-input {
            display: flex;
            padding: 1rem;
            border-top: 1px solid #eee;
            background-color: #fff;
        }
        .chat-input input {
            flex-grow: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-right: 0.5rem;
        }
        .chat-input button {
            background-color: #3B82F6;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .chat-input button:hover {
            background-color: #2563EB;
        }
        .chat-message {
            margin-bottom: 0.75rem;
            padding: 0.6rem 0.9rem;
            border-radius: 15px;
            max-width: 80%;
            word-wrap: break-word;
        }
        .chat-message.sent {
            background-color: #DCF8C6; /* Hijau muda */
            align-self: flex-end;
            margin-left: auto;
            text-align: right;
            border-bottom-right-radius: 2px;
        }
        .chat-message.received {
            background-color: #E0E0E0; /* Abu-abu muda */
            align-self: flex-start;
            margin-right: auto;
            text-align: left;
            border-bottom-left-radius: 2px;
        }
        .chat-message p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.3;
        }
        .chat-message .timestamp {
            font-size: 0.7rem;
            color: #666;
            margin-top: 4px;
            display: block;
        }
        .chat-message img {
            max-width: 100%;
            border-radius: 8px;
            margin-top: 8px;
        }

        /* Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                width: 250px; /* Lebih kecil di mobile */
            }
            .chat-window {
                width: calc(100% - 40px); /* Lebar penuh minus margin di mobile */
                height: calc(100% - 130px); /* Tinggi yang disesuaikan (sesuaikan dengan tombol chat) */
                bottom: 80px; /* Sesuaikan posisi agar tidak menutupi tombol chat di mobile */
                right: 20px;
            }
            .live-chat-button {
                bottom: 20px; /* Posisikan di atas footer di mobile */
            }
        }
    </style>
</head>
<body>
    <!-- Global JavaScript variable for user ID -->
    <script>
        // Set a global variable for the logged-in user's ID
        // This makes the ID available to chat.js without embedding PHP directly
        window.loggedInUserId = <?= $loggedInUserId ?>;    
        window.isAdmin = <?= json_encode($is_admin) ?>; // Add isAdmin global variable
        // Removed window.isLiveChatAdminPage as it's no longer relevant for the simplified chat logic
    </script>
    <!-- Header Utama -->
    <header class="header-fixed py-4 px-6 md:px-10 flex items-center justify-between">
        <!-- Logo / Nama Situs -->
        <div class="text-xl font-bold text-gray-800 flex items-center">
            <a href="dashboard.php" class="flex items-center">
                <img src="logo.png" alt="Logo Situs Tugas" class="h-8 mr-2"> <!-- Sesuaikan path jika perlu -->
            </a>
        </div>

        <!-- Burger Menu (Mobile) -->
        <div class="md:hidden flex items-center">
            <div id="burgerBtn" class="burger-icon">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>

        <!-- Navigasi Desktop -->
        <nav class="hidden md:flex items-center space-x-6">
            <?php if ($loggedIn): ?>
                <span class="text-gray-700 font-medium hidden lg:inline">Halo, <?= $username ?>!</span>
                <span class="text-gray-700 font-medium">Saldo: Rp <?= $balance ?></span>
                <span class="text-gray-700 font-medium">Poin: <?= $points ?></span>
                <span class="text-gray-700 font-medium">Level: <?= $membership_level ?></span>
                <?php if ($is_admin): ?>
                    <a href="admin_dashboard.php" class="text-blue-600 hover:text-blue-800 font-medium">Admin Panel</a>
                <?php endif; ?>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">Logout</a>
            <?php else: ?>
                <a href="index.php" class="text-blue-600 hover:text-blue-800 font-medium">Login</a>
                <a href="register.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">Registrasi</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Overlay untuk menu burger -->
    <div id="overlay" class="overlay"></div>

    <!-- Sidebar Menu (untuk mobile) -->
    <aside id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <?php if ($loggedIn): ?>
                <div class="flex flex-col items-start mb-4"> <!-- Ubah items-center menjadi items-start untuk rata kiri -->
                    <img src="<?= $profile_picture_url ?>" alt="Foto Profil" class="w-20 h-20 rounded-full object-cover border-2 border-blue-300 mb-2">
                    <h3 class="text-xl font-bold text-gray-800"><?= $username ?></h3>
                    <p class="text-sm text-gray-600 mt-1"><?= $full_name ?></p>
                    <p class="text-sm text-gray-600"><?= $phone_number ?></p>
                    <p class="text-sm text-gray-600"><?= $email ?></p> <!-- Tambahkan email -->
                    <a href="profile_settings.php" class="text-blue-600 hover:text-blue-800 font-semibold text-sm mt-2">Edit Profil</a> <!-- Tautan Edit Profil -->
                </div>
            <?php else: ?>
                <h3 class="text-xl font-bold text-gray-800">Menu</h3>
                <p class="text-sm text-gray-600 mt-1"><?= $username ?></p>
            <?php endif; ?>
        </div>
        <nav class="sidebar-menu">
            <?php if ($loggedIn): ?>
                <a href="dashboard.php" class="flex items-center space-x-2"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
                <a href="#" class="flex items-center space-x-2">
                    <i class="fas fa-wallet"></i> 
                    <span>Saldo: Rp <?= $balance ?></span>
                </a>
                <a href="#" class="flex items-center space-x-2">
                    <i class="fas fa-coins"></i> 
                    <span>Poin: <?= $points ?></span>
                </a>
                <a href="#" class="flex items-center space-x-2">
                    <i class="fas fa-trophy"></i> 
                    <span>Level: <?= $membership_level ?></span>
                </a>
                <a href="deposit.php" class="flex items-center space-x-2"><i class="fas fa-money-bill-wave"></i> <span>Isi Saldo</span></a>
                <a href="withdraw.php" class="flex items-center space-x-2"><i class="fas fa-money-check-alt"></i> <span>Tarik Saldo</span></a>
                <?php if ($is_admin): ?>
                    <a href="admin_dashboard.php" class="flex items-center space-x-2"><i class="fas fa-cogs"></i> <span>Admin Panel</span></a>
                <?php endif; ?>
                <a href="logout.php" class="flex items-center space-x-2 text-red-600 hover:bg-red-50 mt-4"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            <?php else: ?>
                <a href="index.php" class="flex items-center space-x-2"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
                <a href="register.php" class="flex items-center space-x-2"><i class="fas fa-user-plus"></i> <span>Registrasi</span></a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Live Chat Button (hanya untuk pengguna biasa, tidak untuk admin) -->
    <?php if (!$is_admin): ?>
    <div id="liveChatBtn" class="live-chat-button">
        <i class="fas fa-comments"></i>
    </div>

    <!-- Live Chat Window (hanya untuk pengguna biasa) -->
    <div id="chatWindow" class="chat-window">
        <div class="chat-header">
            <h4 class="text-lg font-semibold">Live Chat</h4>
            <button id="closeChatBtn" class="text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="chatMessages" class="chat-messages">
            <!-- Pesan chat akan dimuat di sini -->
            <div class="text-center text-gray-500 text-sm mt-4">Memuat pesan...</div>
        </div>
        <div class="chat-input">
            <input type="text" id="chatInput" placeholder="Ketik pesan Anda...">
            <input type="file" id="chatImageInput" accept="image/*" class="hidden">
            <button id="sendImageBtn" class="mr-2 bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-2 rounded-lg">
                <i class="fas fa-image"></i>
            </button>
            <button id="sendMessageBtn">Kirim</button>
        </div>
    </div>
    <?php endif; ?>
