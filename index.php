<?php
// index.php
// Halaman utama untuk login pengguna.

// Aktifkan pelaporan kesalahan untuk debugging (hapus atau nonaktifkan di produksi)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
// Pastikan db_connect.php TIDAK mengeluarkan output apapun (spasi, baris baru, dll.)
require_once __DIR__ . '/db_connect.php';

$error_message = ''; // Variabel untuk menyimpan pesan kesalahan

// Tangani proses login ketika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error_message = "Username dan password tidak boleh kosong.";
    } else {
        try {
            // Siapkan query untuk mengambil data pengguna berdasarkan username
            $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Verifikasi password
            if ($user && password_verify($password, $user['password'])) {
                // Login berhasil, simpan ID pengguna di sesi
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Arahkan ke dashboard
                // PENTING: header() HARUS dipanggil sebelum output HTML apapun
                header('Location: dashboard.php');
                exit(); // Selalu panggil exit() setelah header()
            } else {
                $error_message = "Username atau password salah.";
            }
        } catch (PDOException $e) {
            $error_message = "Terjadi kesalahan database: " . $e->getMessage();
            error_log("Login error: " . $e->getMessage()); // Log kesalahan untuk debugging
        }
    }
}

// Jika pengguna sudah login (baik dari sesi sebelumnya atau setelah login berhasil),
// arahkan mereka ke dashboard. Pindahkan ini ke sini untuk memastikan redirect terjadi
// sebelum HTML lain dikirim.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Sertakan header halaman HANYA setelah semua pemrosesan PHP yang mungkin menyebabkan redirect selesai
// Di sinilah output HTML dimulai
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

    /* Styling for left content area */
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

    /* Styling for login form wrapper */
    .login-form-wrapper {
        background-color: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(8px);
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        width: 90%;
        max-width: 400px;
        margin: auto;
        color: #333;
    }
    .login-form-wrapper h2, .login-form-wrapper label, .login-form-wrapper p {
        color: #333;
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

        .login-form-wrapper {
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

        .login-form-wrapper.visible {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            /* Show visibility immediately as opacity fades in */
            transition: opacity 0.5s ease-out, visibility 0s 0s;
        }
        
        #showLoginFormBtn {
            display: flex;
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 15;
        }
        #hideLoginFormBtn {
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

        .login-form-wrapper {
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

        /* Hide mobile-specific buttons on desktop/tablet */
        #showLoginFormBtn, #hideLoginFormBtn {
            display: none !important;
        }
    }
</style>

<!-- Background Interaktif Three.js dihapus -->
<!-- <div id="interactiveBg" class="interactive-bg-container"></div> -->

<main class="flex-grow container mx-auto p-4 md:p-8 main-container-layout">
    <!-- Konten Kiri (Awalnya terlihat) -->
    <div id="leftContentArea" class="left-content-area animated-panel">
        <div class="slideshow-container">
            <img src="produk/produk.png" alt="Gambar Produk 1" class="slideshow-image active" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
            <img src="produk/produk2.png" alt="Gambar Produk 2" class="slideshow-image" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
            <img src="produk/produk3.png" alt="Gambar Produk 3" class="slideshow-image" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
            <img src="produk/produk4.png" alt="Gambar Produk 4" class="slideshow-image" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
            <img src="produk/produk5.png" alt="Gambar Produk 5" class="slideshow-image" onerror="this.onerror=null;this.src='https://placehold.co/600x400/CCCCCC/666666?text=Gambar+Produk+Tidak+Ditemukan';">
        </div>
        <h3 class="text-3xl font-bold mb-4">Selamat Datang di Deprintz App!</h3>
        <p class="text-lg leading-relaxed">
            Solusi terbaik untuk mengatasi permasalahan percetakan, terpercaya, aman dan berkualitas. Bergabunglah di jaringan kami dan mulai hasilkan sekarang!
        </p>
        <!-- Tombol "Login Sekarang" -->
        <button id="showLoginFormBtn" class="mt-8 px-6 py-3 bg-blue-600 text-white font-bold rounded-lg shadow-md transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105 flex items-center justify-center">
            Login Sekarang <i class="fas fa-arrow-right ml-2"></i>
        </button>
    </div>

    <!-- Form Login (Awalnya tersembunyi di mobile, tapi selalu terlihat di desktop/tablet oleh CSS/JS) -->
    <div id="loginFormWrapper" class="login-form-wrapper animated-panel">
        <!-- Tombol "Kembali" -->
        <button id="hideLoginFormBtn" class="text-gray-600 hover:text-gray-800 mb-4 flex items-center hidden">
            <i class="fas fa-arrow-left mr-2"></i> Kembali
        </button>
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Login</h2>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST" class="space-y-5">
            <div>
                <label for="username" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                <input type="text" id="username" name="username" required 
                        class="shadow-sm appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Masukkan username Anda">
            </div>
            <div>
                <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">Password</label>
                <input type="password" id="password" name="password" required 
                        class="shadow-sm appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Masukkan password Anda">
            </div>
            <button type="submit" name="login" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-105">
                Login
            </button>
        </form>
        
        <p class="text-center text-gray-600 text-sm mt-6">
            Belum punya akun? <a href="register.php" class="text-blue-600 hover:text-blue-800 font-semibold">Daftar di sini</a>.
        </p>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const leftContentArea = document.getElementById('leftContentArea');
        const loginFormWrapper = document.getElementById('loginFormWrapper');
        const showLoginFormBtn = document.getElementById('showLoginFormBtn');
        const hideLoginFormBtn = document.getElementById('hideLoginFormBtn');

        // Check if there was an error message from PHP
        const hasErrorMessage = JSON.parse('<?php echo !empty($error_message) ? 'true' : 'false'; ?>');

        /**
         * Manages the initial visibility of the content area and login form
         * based on screen size and the presence of an error message.
         * This function is called only once on DOMContentLoaded.
         */
        function manageFormVisibilityOnInit() {
            if (window.innerWidth < 768) { // Mobile view
                if (hasErrorMessage) {
                    // If there's an error from PHP, show the login form immediately
                    leftContentArea.classList.add('blurred-bg');
                    loginFormWrapper.classList.add('visible');
                    showLoginFormBtn.classList.add('hidden');
                    hideLoginFormBtn.classList.remove('hidden');
                } else {
                    // Default mobile state: content area visible, login form hidden
                    leftContentArea.classList.remove('blurred-bg');
                    loginFormWrapper.classList.remove('visible');
                    showLoginFormBtn.classList.remove('hidden');
                    hideLoginFormBtn.classList.add('hidden');
                }
            } else { // PC and Tablet view (min-width: 768px)
                // On desktop/tablet, both content and form are always visible side-by-side
                // and buttons are hidden by CSS media queries.
                leftContentArea.classList.remove('blurred-bg'); // Ensure no blur
                loginFormWrapper.classList.remove('visible'); // Remove 'visible' class as desktop CSS handles it
            }
        }

        // Event listener for the "Login Sekarang" button
        if (showLoginFormBtn) {
            showLoginFormBtn.addEventListener('click', function() {
                if (window.innerWidth < 768) { // Only for mobile view
                    leftContentArea.classList.add('blurred-bg'); // Blur and dim background
                    loginFormWrapper.classList.add('visible');   // Show the login form

                    // Toggle button visibility
                    showLoginFormBtn.classList.add('hidden');
                    hideLoginFormBtn.classList.remove('hidden');
                }
            });
        }

        // Event listener for the "Kembali" button
        if (hideLoginFormBtn) {
            hideLoginFormBtn.addEventListener('click', function() {
                if (window.innerWidth < 768) { // Only for mobile view
                    loginFormWrapper.classList.remove('visible'); // Hide the login form
                    leftContentArea.classList.remove('blurred-bg'); // Remove blur from background

                    // Toggle button visibility
                    showLoginFormBtn.classList.remove('hidden');
                    hideLoginFormBtn.classList.add('hidden');
                }
            });
        }

        // --- Slideshow Logic (Remains unchanged, as it's separate from login form issues) ---
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
// Pastikan footer.php juga TIDAK mengeluarkan output apapun sebelum dipanggil
include_once __DIR__ . '/footer.php';
?>
