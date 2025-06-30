<?php
// register.php
// Halaman untuk registrasi pengguna baru.

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Jika pengguna sudah login, arahkan ke dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$success_message = ''; // Variabel untuk menyimpan pesan sukses
$error_message =    ''; // Variabel untuk menyimpan pesan kesalahan

// Tangani proses registrasi ketika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']); // Tambahkan: Ambil Nama Lengkap dari input
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validasi input
    if (empty($username) || empty($email) || empty($full_name) || empty($password) || empty($confirm_password)) { // Tambahkan $full_name untuk validasi kosong
        $error_message = "Semua kolom harus diisi.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Konfirmasi password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password minimal 6 karakter.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        try {
            // Periksa apakah username atau email sudah ada
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Username atau email sudah terdaftar. Silakan gunakan yang lain.";
            } else {
                // Hash password sebelum disimpan
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Masukkan pengguna baru ke database, termasuk full_name
                $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $full_name, $hashed_password])) { // Tambahkan $full_name di execute
                    $success_message = "Registrasi berhasil! Silakan login.";
                    // Arahkan ke halaman login setelah beberapa detik atau langsung
                    // header('Location: index.php');
                    // exit();
                } else {
                    $error_message = "Gagal mendaftar. Silakan coba lagi.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Terjadi kesalahan database: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage()); // Log kesalahan untuk debugging
        }
    }
}

// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<style>
    body {
        margin: 0;
        background-color: #FFFFFF;
        overflow: hidden;
    }

    /* Base styles for main container */
    .main-container-layout {
        position: relative;
        height: 100vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 2rem 1rem;
    }

    /* Base styles for panels that will animate */
    .animated-panel {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 2rem;
        box-sizing: border-box;
        /* Updated transition for visibility and opacity */
        transition: opacity 0.5s ease-out, filter 0.5s ease, visibility 0s;
    }

    /* Styling untuk area konten kiri */
    .left-content-area {
        color: #333;
        text-shadow: 0 1px 2px rgba(255,255,255,0.3);
        text-align: center;
        /* Added transition for blur/opacity for consistency */
        transition: opacity 0.5s ease-out, filter 0.5s ease-out;
    }

    /* Slideshow Container */
    .slideshow-container {
        position: relative;
        width: 80%;
        max-width: 400px;
        height: auto;
        margin-bottom: 1.5rem;
        overflow: hidden;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }

    .slideshow-image {
        width: 100%;
        height: auto;
        display: block;
        opacity: 0;
        transition: opacity 1s ease-in-out;
        position: absolute;
        top: 0;
        left: 0;
    }

    .slideshow-image.active {
        opacity: 1;
    }

    /* Styling untuk pembungkus formulir register */
    .register-form-wrapper {
        background-color: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(8px);
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        width: 90%;
        max-width: 400px;
        margin: auto;
        color: #333;
    }
    .register-form-wrapper h2 {
        color: #333;
        font-size: 1.5rem;
    }
    .register-form-wrapper label, .register-form-wrapper p {
        color: #333;
    }

    /* Styling untuk indikator kekuatan password (teks) */
    #password-strength-indicator {
        font-size: 0.75rem;
        font-weight: bold;
        text-align: right;
        margin-top: 0.25rem;
        height: 1.25rem; /* Beri tinggi tetap untuk mencegah CLS */
    }
    .strength-weak {
        color: #ef4444;
    }
    .strength-medium {
        color: #f59e0b;
    }
    .strength-strong {
        color: #22c55e;
    }


    /* --- MOBILE-SPECIFIC STYLES (DEFAULT) --- */
    @media (max-width: 767px) {
        .left-content-area {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 5;
            opacity: 1;
            filter: none;
        }

        .left-content-area.blurred-bg {
            opacity: 0.5;
            filter: blur(5px);
            pointer-events: none;
        }

        .register-form-wrapper {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translateX(-50%) translateY(-50%);
            /* Use opacity and visibility for smooth transitions */
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            display: flex; /* Keep display flex for layout when visible */
            /* Hide visibility after opacity fades out */
            transition: opacity 0.5s ease-out, visibility 0s 0.5s;
            flex-direction: column; /* Ensure vertical stacking on mobile */
            justify-content: center;
            align-items: center;
        }

        .register-form-wrapper.visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            /* Show visibility immediately as opacity fades in */
            transition: opacity 0.5s ease-out, visibility 0s 0s;
        }
        
        #showRegisterFormBtn {
            display: flex;
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 15;
        }
        #hideRegisterFormBtn {
            display: flex; /* Initially hidden by JS 'hidden' class */
            z-index: 15;
        }
    }

    /* --- PC AND TABLET STYLES (min-width: 768px) --- */
    @media (min-width: 768px) {
        .main-container-layout {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 4rem;
        }

        .animated-panel {
            position: relative;
            width: auto;
            height: auto;
            padding: 0;
            transform: translateY(0) !important;
            opacity: 1 !important;
            filter: none !important;
            pointer-events: auto !important;
            align-items: flex-start;
            margin: 0;
        }
        
        .left-content-area {
            flex: 1;
            max-width: 45%;
            margin-right: 2rem;
            color: #333;
            text-shadow: 0 1px 2px rgba(255,255,255,0.3);
            text-align: left;
            z-index: auto;
            /* Ensure no blur or opacity changes on desktop */
            opacity: 1;
            filter: none;
            pointer-events: auto;
        }
        .left-content-area h3 {
            font-size: 1.5rem;
        }
        .left-content-area p {
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .register-form-wrapper {
            flex-shrink: 0;
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            align-items: center;
            z-index: auto;
            display: flex; /* Always flex on desktop */
            opacity: 1; /* Always visible on desktop */
            visibility: visible;
            pointer-events: auto;
            transition: none; /* Disable transitions on desktop */
            flex-direction: column;
            justify-content: center;
        }

        /* Sembunyikan tombol khusus seluler di desktop/tablet */
        #showRegisterFormBtn, #hideRegisterFormBtn {
            display: none !important;
        }
    }
</style>

<main class="flex-grow container mx-auto p-4 md:p-8 main-container-layout">
    <!-- Konten Kiri (Awalnya terlihat) -->
    <div id="leftContentArea" class="left-content-area animated-panel">
        <div class="slideshow-container">
            <img src="produk/produk.png" alt="Gambar Produk 1" class="slideshow-image active" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
            <img src="produk/produk2.png" alt="Gambar Produk 2" class="slideshow-image" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
            <img src="produk/produk3.png" alt="Gambar Produk 3" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
            <img src="produk/produk4.png" alt="Gambar Produk 4" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
            <img src="produk/produk5.png" alt="Gambar Produk 5" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
        </div>
        <h3>Selamat Datang di Deprintz App!</h3>
        <p>
            Solusi terbaik untuk mengatasi permasalahan percetakan, terpercaya, aman dan berkualitas. Bergabunglah di jaringan kami dan mulai hasilkan sekarang!
        </p>
        <!-- Tombol "Daftar Sekarang" -->
        <button id="showRegisterFormBtn" class="mt-8 px-6 py-3 bg-green-600 text-white font-bold rounded-lg shadow-md transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105 flex items-center justify-center">
            Daftar Sekarang <i class="fas fa-arrow-right ml-2"></i>
        </button>
    </div>

    <!-- Form Registrasi (Awalnya tersembunyi di mobile, tapi selalu terlihat di desktop/tablet oleh CSS/JS) -->
    <div id="registerFormWrapper" class="register-form-wrapper animated-panel">
        <!-- Tombol "Kembali" -->
        <button id="hideRegisterFormBtn" class="text-gray-600 hover:text-gray-800 mb-4 flex items-center hidden">
            <i class="fas fa-arrow-left mr-2"></i> Kembali
        </button>
        <h2>Registrasi</h2>
        
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Sukses!</strong>
                <span class="block sm:inline"><?= htmlspecialchars($success_message) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="space-y-2">
            <div>
                <label for="username" class="block text-gray-700 text-sm font-semibold mb-1">Username</label>
                <input type="text" id="username" name="username" required
                        class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Buat username Anda" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>
            <div>
                <label for="email" class="block text-gray-700 text-sm font-semibold mb-1">Email</label>
                <input type="email" id="email" name="email" required
                        class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Masukkan alamat email Anda" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>
            <!-- Input untuk Nama Lengkap -->
            <div>
                <label for="full_name" class="block text-gray-700 text-sm font-semibold mb-1">Nama Lengkap</label>
                <input type="text" id="full_name" name="full_name" required
                        class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Masukkan nama lengkap Anda" value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
            </div>
            <div>
                <label for="password" class="block text-gray-700 text-sm font-semibold mb-1">Password</label>
                <input type="password" id="password" name="password" required
                        class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Buat password (min. 6 karakter)">
                <!-- Indikator Kekuatan Password (Teks) -->
                <div id="password-strength-indicator"></div>
            </div>
            <div>
                <label for="confirm_password" class="block text-gray-700 text-sm font-semibold mb-1">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                        class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Konfirmasi password Anda">
            </div>
            <button type="submit" name="register"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105">
                Daftar
            </button>
        </form>
        
        <p class="text-center text-gray-600 text-sm mt-4">
            Sudah punya akun? <a href="index.php" class="text-blue-600 hover:text-blue-800 font-semibold">Login di sini</a>.
        </p>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const leftContentArea = document.getElementById('leftContentArea');
        const registerFormWrapper = document.getElementById('registerFormWrapper');
        const showRegisterFormBtn = document.getElementById('showRegisterFormBtn');
        const hideRegisterFormBtn = document.getElementById('hideRegisterFormBtn');

        // Referensi elemen password dan indikator
        const passwordInput = document.getElementById('password');
        const passwordStrengthIndicator = document.getElementById('password-strength-indicator');

        // Check if there was an error message or success message from PHP
        const hasServerMessage = JSON.parse('<?php echo !empty($error_message) || !empty($success_message) ? 'true' : 'false'; ?>');

        /**
         * Manages the initial visibility of the content area and registration form
         * based on screen size and the presence of a server-side message.
         * This function is called only once on DOMContentLoaded.
         */
        function manageFormVisibilityOnInit() {
            if (window.innerWidth < 768) { // Mobile view
                if (hasServerMessage) {
                    // If there's a message from PHP, show the registration form immediately
                    leftContentArea.classList.add('blurred-bg');
                    registerFormWrapper.classList.add('visible');
                    showRegisterFormBtn.classList.add('hidden');
                    hideRegisterFormBtn.classList.remove('hidden');
                } else {
                    // Default mobile state: content area visible, registration form hidden
                    leftContentArea.classList.remove('blurred-bg');
                    registerFormWrapper.classList.remove('visible');
                    showRegisterFormBtn.classList.remove('hidden');
                    hideRegisterFormBtn.classList.add('hidden');
                }
            } else { // PC and Tablet view (min-width: 768px)
                // On desktop/tablet, both content and form are always visible side-by-side
                // and buttons are hidden by CSS media queries.
                leftContentArea.classList.remove('blurred-bg'); // Ensure no blur
                registerFormWrapper.classList.remove('visible'); // Remove 'visible' class as desktop CSS handles it
            }
        }

        // Fungsi untuk mengecek kekuatan password
        function checkPasswordStrength(password) {
            let score = 0;
            let text = '';
            let textColorClass = '';

            const hasLowercase = /[a-z]/.test(password);
            const hasUppercase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecialChar = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]+/.test(password);

            if (password.length >= 6) {
                score += 1;
            }
            if (password.length >= 8) {
                score += 1;
            }
            if (password.length >= 12) {
                score += 1;
            }

            let criteriaMet = 0;
            if (hasLowercase) criteriaMet++;
            if (hasUppercase) criteriaMet++;
            if (hasNumber) criteriaMet++;
            if (hasSpecialChar) criteriaMet++;

            if (criteriaMet >= 2) {
                score += 1;
            }
            if (criteriaMet >= 3) {
                score += 1;
            }
            if (criteriaMet === 4) {
                score += 1;
            }

            if (password.length === 0) {
                text = '';
                textColorClass = '';
            } else if (score < 3) {
                text = 'Lemah';
                textColorClass = 'strength-weak';
            } else if (score < 5) {
                text = 'Sedang';
                textColorClass = 'strength-medium';
            } else {
                text = 'Kuat';
                textColorClass = 'strength-strong';
            }

            return { text, textColorClass };
        }

        // Event listener untuk input password
        if (passwordInput && passwordStrengthIndicator) {
            passwordInput.addEventListener('input', function() {
                const strength = checkPasswordStrength(this.value);
                passwordStrengthIndicator.textContent = strength.text;
                // Remove existing strength classes before adding the new one
                passwordStrengthIndicator.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
                passwordStrengthIndicator.classList.add(strength.textColorClass);
            });
        }

        // Event listener untuk tombol "Daftar Sekarang"
        if (showRegisterFormBtn) {
            showRegisterFormBtn.addEventListener('click', function() {
                if (window.innerWidth < 768) { // Hanya untuk mobile
                    leftContentArea.classList.add('blurred-bg');
                    registerFormWrapper.classList.add('visible');

                    showRegisterFormBtn.classList.add('hidden');
                    hideRegisterFormBtn.classList.remove('hidden');
                }
            });
        }

        // Event listener untuk tombol "Kembali"
        if (hideRegisterFormBtn) {
            hideRegisterFormBtn.addEventListener('click', function() {
                if (window.innerWidth < 768) { // Hanya untuk mobile
                    registerFormWrapper.classList.remove('visible');
                    leftContentArea.classList.remove('blurred-bg');
                    
                    showRegisterFormBtn.classList.remove('hidden');
                    hideRegisterFormBtn.classList.add('hidden');
                }
            });
        }

        // --- Slideshow Logic ---
        let slideIndex = 0;
        const slideshowImages = document.querySelectorAll('.slideshow-image');
        const slideshowContainer = document.querySelector('.slideshow-container');

        // Set the height of the slideshow container based on the first image to prevent layout shifts
        function setSlideshowContainerHeight() {
            if (slideshowImages.length > 0) {
                const firstImage = slideshowImages[0];
                if (firstImage.complete) {
                    slideshowContainer.style.height = `${firstImage.clientHeight}px`;
                } else {
                    firstImage.onload = () => {
                        slideshowContainer.style.height = `${firstImage.clientHeight}px`;
                    };
                }
            }
        }

        function showSlides() {
            if (slideshowImages.length === 0) return;

            for (let i = 0; i < slideshowImages.length; i++) {
                slideshowImages[i].classList.remove('active');
            }
            slideIndex++;
            if (slideIndex > slideshowImages.length) {
                slideIndex = 1;
            }
            slideshowImages[slideIndex - 1].classList.add('active');
            setTimeout(showSlides, 3000); // Change image every 3 seconds
        }

        // Call slideshow functions after images are loaded
        window.addEventListener('load', () => {
            setSlideshowContainerHeight(); // Set initial height
            showSlides(); // Start slideshow
        });
        window.addEventListener('resize', setSlideshowContainerHeight); // Adjust height on resize

        // Initial setup for form visibility when the DOM is ready
        manageFormVisibilityOnInit();
        // Removed `window.addEventListener('resize', setInitialVisibility);` for form visibility
        // as virtual keyboard on mobile triggers resize and can cause unexpected hiding.
    });
</script>

<?php
// Sertakan footer halaman
include_once __DIR__ . '/footer.php';
?>
