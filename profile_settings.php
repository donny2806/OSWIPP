<?php
// profile_settings.php
// Halaman ini memungkinkan pengguna untuk mengedit data profil mereka.

// Mulai sesi jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Sertakan file koneksi database
require_once __DIR__ . '/db_connect.php';

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Arahkan ke halaman login jika belum login
    exit();
}

$current_user_id = $_SESSION['user_id'];

$message = '';
$message_type = ''; // 'success' atau 'error'

// Tangani pesan dari session (setelah pengalihan dari process_profile_update.php atau process_password_update.php)
if (isset($_SESSION['form_message'])) {
    $message = htmlspecialchars($_SESSION['form_message']);
    $message_type = htmlspecialchars($_SESSION['form_message_type'] ?? 'error'); // Default type if not set

    // Hapus pesan dari sesi agar tidak muncul lagi setelah refresh
    unset($_SESSION['form_message']);
    unset($_SESSION['form_message_type']);
}

// Ambil data pengguna lengkap
$user_data = [];
try {
    // Pastikan semua kolom yang relevan diambil dari database
    $stmt = $pdo->prepare("SELECT balance, points, membership_level, is_admin, profile_picture_url, full_name, email, address, phone_number, nationality, username FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user_data = $stmt->fetch();
    if (!$user_data) {
        // Jika data pengguna tidak ditemukan, mungkin sesi tidak valid
        session_destroy();
        header('Location: index.php');
        exit();
    }
    // Perbarui username sesi agar selalu sinkron dengan database
    $_SESSION['username'] = $user_data['username'];
    $username = $user_data['username']; // Pastikan variabel $username terisi dari data database
} catch (PDOException $e) {
    error_log("Error fetching user data in profile_settings: " . $e->getMessage());
    $message = "Terjadi kesalahan saat memuat data pengguna.";
    $message_type = 'error';
    // Karena ini error dari pengambilan data, mungkin kita tidak ingin menghapus message session yang ada
    // Jika ada message session dari proses sebelumnya, biarkan ditampilkan
    if (!isset($_SESSION['form_message'])) {
        $_SESSION['form_message'] = $message;
        $_SESSION['form_message_type'] = $message_type;
    }
}

// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<main class="flex-grow container mx-auto p-4 md:p-8">
    <h1 class="text-4xl font-extrabold text-gray-900 mb-8 text-center">Pengaturan Profil</h1>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg
                    <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>"
                    role="alert">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap justify-center gap-8 items-start"> <!-- Added items-start here -->
        <!-- Form Rubah Password (dipindahkan ke kiri dan lebarnya disamakan) -->
        <section class="bg-white p-6 rounded-xl shadow-md w-full max-w-lg">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Ubah Kata Sandi</h2>
            <form id="passwordForm" action="process_password_update.php" method="POST" class="space-y-3">
                <input type="hidden" name="user_id" value="<?= $current_user_id ?>">

                <div>
                    <label for="current_password" class="block text-gray-700 text-sm font-semibold mb-1">Kata Sandi Lama</label>
                    <input type="password" id="current_password" name="current_password" required
                           class="shadow-sm appearance-none border rounded-lg w-full py-1.5 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label for="new_password" class="block text-gray-700 text-sm font-semibold mb-1">Kata Sandi Baru</label>
                    <input type="password" id="new_password" name="new_password" required
                           class="shadow-sm appearance-none border rounded-lg w-full py-1.5 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <div id="passwordStrengthIndicator" class="mt-1 text-sm font-medium"></div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 mt-1">
                        <div id="passwordStrengthBar" class="h-2.5 rounded-full transition-all duration-300 ease-in-out" style="width: 0%"></div>
                    </div>
                </div>
                <div>
                    <label for="confirm_new_password" class="block text-gray-700 text-sm font-semibold mb-1">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required
                           class="shadow-sm appearance-none border rounded-lg w-full py-1.5 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="flex justify-end mt-4">
                    <button type="submit"
                            class="w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">
                        Ubah Kata Sandi
                    </button>
                </div>
            </form>
        </section>

        <!-- Form Data Diri User (tetap di kanan) -->
        <section class="bg-white p-6 rounded-xl shadow-md w-full max-w-lg">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Detail Profil</h2>
                <button type="button" id="editProfileBtn"
                        class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                    Edit
                </button>
            </div>
            <form id="profileForm" action="process_profile_update.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="update_profile" value="1">
                <input type="hidden" name="current_profile_picture_url" value="<?= htmlspecialchars($user_data['profile_picture_url'] ?? 'uploads/profile_pictures/default.png') ?>">

                <div class="flex flex-col items-center mb-4">
                    <img id="profilePicturePreview"
                            src="<?= htmlspecialchars($user_data['profile_picture_url'] ?? 'uploads/profile_pictures/default.png') ?>"
                            alt="Foto Profil"
                            class="w-32 h-32 rounded-full object-cover border-4 border-blue-200 mb-2">
                    <label for="profile_picture" class="cursor-pointer bg-blue-500 hover:bg-blue-600 text-white text-sm py-1 px-3 rounded-full transition duration-200 profile-edit-input-toggle">
                        Ubah Foto
                    </label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="hidden profile-edit-input">
                    <label class="inline-flex items-center mt-2 text-sm text-red-600 profile-edit-input-toggle">
                        <input type="checkbox" name="remove_profile_picture" id="removeProfilePicture" value="true" class="form-checkbox h-4 w-4 text-red-600 profile-edit-input">
                        <span class="ml-2">Hapus Foto Profil</span>
                    </label>
                </div>

                <div>
                    <label for="full_name" class="block text-gray-700 text-sm font-semibold mb-2">Nama Lengkap</label>
                    <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user_data['full_name'] ?? '') ?>" readonly
                            class="profile-editable-field shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label for="username_profile" class="block text-gray-700 text-sm font-semibold mb-2">Username</label>
                    <input type="text" id="username_profile" name="username" value="<?= htmlspecialchars($user_data['username']) ?>" required readonly
                            class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight bg-gray-100 cursor-not-allowed">
                </div>
                <div>
                    <label for="email_profile" class="block text-gray-700 text-sm font-semibold mb-2">Email</label>
                    <input type="email" id="email_profile" name="email" value="<?= htmlspecialchars($user_data['email'] ?? '') ?>" required readonly
                            class="profile-editable-field shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label for="balance_display" class="block text-gray-700 text-sm font-semibold mb-2">Saldo</label>
                    <input type="text" id="balance_display" value="Rp <?= number_format($user_data['balance'], 2, ',', '.') ?>" readonly
                            class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                </div>
                <div>
                    <label for="points_display" class="block text-gray-700 text-sm font-semibold mb-2">Poin</label>
                    <input type="text" id="points_display" value="<?= number_format($user_data['points'], 0, ',', '.') ?>" readonly
                            class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                </div>
                <div>
                    <label for="membership_level_display" class="block text-gray-700 text-sm font-semibold mb-2">Level Keanggotaan</label>
                    <input type="text" id="membership_level_display" value="<?= htmlspecialchars($user_data['membership_level']) ?>" readonly
                            class="shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight">
                </div>
                <div>
                    <label for="address" class="block text-gray-700 text-sm font-semibold mb-2">Alamat</label>
                    <textarea id="address" name="address" rows="3" readonly
                                  class="profile-editable-field shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"><?= htmlspecialchars($user_data['address'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="phone_number" class="block text-gray-700 text-sm font-semibold mb-2">Nomor HP</label>
                    <input type="text" id="phone_number" name="phone_number" value="<?= htmlspecialchars($user_data['phone_number'] ?? '') ?>" readonly
                            class="profile-editable-field shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label for="nationality" class="block text-gray-700 text-sm font-semibold mb-2">Kewarganegaraan</label>
                    <input type="text" id="nationality" name="nationality" value="<?= htmlspecialchars($user_data['nationality'] ?? '') ?>" readonly
                            class="profile-editable-field shadow-sm appearance-none border rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="flex justify-end space-x-4 mt-6">
                    <button type="submit" id="saveProfileBtn" disabled
                            class="w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200 opacity-50 cursor-not-allowed">
                        Simpan Perubahan
                    </button>
                    <button type="button" id="cancelEditBtn" style="display:none;"
                            class="w-auto bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-200">
                        Batal
                    </button>
                </div>
            </form>
        </section>
    </div>
</main>

<?php include_once __DIR__ . '/footer.php'; // Sertakan footer ?>

<script>
    // JavaScript untuk pratinjau gambar profil dan mode edit form
    const profilePictureInput = document.getElementById('profile_picture');
    const profilePicturePreview = document.getElementById('profilePicturePreview');
    const removeProfilePictureCheckbox = document.getElementById('removeProfilePicture');
    const currentProfilePictureUrlInput = document.querySelector('input[name="current_profile_picture_url"]');

    // Profile Edit Mode Elements
    const profileForm = document.getElementById('profileForm');
    const editableFields = document.querySelectorAll('.profile-editable-field');
    const profileEditInputToggles = document.querySelectorAll('.profile-edit-input-toggle');
    const editProfileBtn = document.getElementById('editProfileBtn');
    const saveProfileBtn = document.getElementById('saveProfileBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');

    // Store initial values to revert on cancel
    let initialProfileValues = {};

    function toggleEditMode(enable) {
        editableFields.forEach(field => {
            field.readOnly = !enable;
            if (enable) {
                field.classList.remove('bg-gray-100'); // Hapus gaya readonly
                field.classList.remove('cursor-not-allowed'); // Hapus gaya cursor not allowed
            } else {
                field.classList.add('bg-gray-100'); // Tambahkan gaya readonly
                field.classList.add('cursor-not-allowed'); // Tambahkan gaya cursor not allowed
            }
        });

        profileEditInputToggles.forEach(el => {
            if (enable) {
                el.style.display = 'inline-flex'; // Tampilkan tombol ubah foto dan hapus
            } else {
                el.style.display = 'none'; // Sembunyikan
            }
        });

        // Toggle visibility/state of buttons
        if (enable) {
            editProfileBtn.style.display = 'none';
            saveProfileBtn.style.display = 'block';
            saveProfileBtn.disabled = false;
            saveProfileBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            cancelEditBtn.style.display = 'block';

            // Store current values for potential reset
            editableFields.forEach(field => {
                initialProfileValues[field.id] = field.value;
            });
            // Store current image URL as well
            initialProfileValues['profilePicturePreview'] = profilePicturePreview.src;

        } else {
            editProfileBtn.style.display = 'block';
            saveProfileBtn.style.display = 'none';
            saveProfileBtn.disabled = true;
            saveProfileBtn.classList.add('opacity-50', 'cursor-not-allowed');
            cancelEditBtn.style.display = 'none';

            // Revert image preview if not in edit mode
            profilePicturePreview.src = currentProfilePictureUrlInput.value || 'uploads/profile_pictures/default.png';
            removeProfilePictureCheckbox.checked = false; // Pastikan checkbox hapus tidak terpilih
            profilePictureInput.value = ''; // Reset file input
        }
    }

    // Event Listeners for Profile Picture
    if (profilePictureInput) {
        profilePictureInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePicturePreview.src = e.target.result;
                    removeProfilePictureCheckbox.checked = false; // Uncheck jika ada gambar baru
                };
                reader.readAsDataURL(file);
            } else {
                // If no file selected (e.g., user cancels file selection), revert to current or default
                profilePicturePreview.src = currentProfilePictureUrlInput.value || 'uploads/profile_pictures/default.png';
            }
        });
    }

    if (removeProfilePictureCheckbox) {
        removeProfilePictureCheckbox.addEventListener('change', function() {
            if (this.checked) {
                profilePicturePreview.src = 'uploads/profile_pictures/default.png';
                profilePictureInput.value = ''; // Clear file input if "remove" is checked
            } else {
                profilePicturePreview.src = currentProfilePictureUrlInput.value || 'uploads/profile_pictures/default.png';
            }
        });
    }

    // Event Listeners for Edit/Save/Cancel buttons
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', function() {
            toggleEditMode(true);
        });
    }

    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function() {
            // Revert form fields to initial values
            for (const id in initialProfileValues) {
                if (document.getElementById(id)) {
                    document.getElementById(id).value = initialProfileValues[id];
                }
            }
            // Revert profile picture preview
            profilePicturePreview.src = initialProfileValues['profilePicturePreview'];
            removeProfilePictureCheckbox.checked = false; // Uncheck
            profilePictureInput.value = ''; // Clear file input

            toggleEditMode(false); // Switch back to read-only mode
        });
    }

    // Set initial state on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleEditMode(false); // Start with fields read-only and save button disabled
    });


    // --- JavaScript for Password Strength Indicator ---
    const newPasswordInput = document.getElementById('new_password');
    const passwordStrengthIndicator = document.getElementById('passwordStrengthIndicator');
    const passwordStrengthBar = document.getElementById('passwordStrengthBar'); // New bar element

    if (newPasswordInput && passwordStrengthIndicator && passwordStrengthBar) {
        newPasswordInput.addEventListener('input', updatePasswordStrength);
    }

    function updatePasswordStrength() {
        const password = newPasswordInput.value;
        let strength = 0;
        let feedback = '';
        let colorClass = '';
        let barWidth = 0;

        // Criteria for strength
        const hasLowercase = /[a-z]/.test(password);
        const hasUppercase = /[A-Z]/.test(password);
        const hasNumbers = /[0-9]/.test(password);
        const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]+/.test(password);

        // Score based on criteria
        // Max strength score can be 7
        if (password.length >= 8) strength++; // Length 8+ (1 point)
        if (password.length >= 12) strength++; // Length 12+ (1 point)
        if (hasLowercase) strength++; // (1 point)
        if (hasUppercase) strength++; // (1 point)
        if (hasNumbers) strength++; // (1 point)
        if (hasSpecial) strength++; // (1 point)

        // Add an extra point for good mix of characters
        if ((hasLowercase || hasUppercase) && hasNumbers && hasSpecial && password.length >= 8) strength++; // (1 bonus point)


        // Determine strength level, feedback, color, and bar width
        if (password.length === 0) {
            feedback = '';
            colorClass = '';
            barWidth = 0;
        } else if (strength <= 2) {
            feedback = 'Sangat Lemah';
            colorClass = 'bg-red-500'; // Tailwind class for background color
            barWidth = 25;
        } else if (strength <= 4) {
            feedback = 'Lemah';
            colorClass = 'bg-orange-500'; // Tailwind class for background color
            barWidth = 50;
        } else if (strength <= 5) {
            feedback = 'Sedang';
            colorClass = 'bg-yellow-500'; // Tailwind class for background color
            barWidth = 75;
        } else { // strength > 5 (Kuat atau Sangat Kuat)
            feedback = 'Sangat Kuat'; // Combined Kuat and Sangat Kuat for simpler bar progression
            colorClass = 'bg-green-500'; // Tailwind class for background color
            barWidth = 100;
        }

        passwordStrengthIndicator.textContent = feedback;
        // Ensure text color is applied
        passwordStrengthIndicator.className = 'mt-1 text-sm font-medium ' + (password.length === 0 ? '' : 'text-' + colorClass.split('-')[1] + '-700');

        // Update the bar
        passwordStrengthBar.style.width = barWidth + '%';
        passwordStrengthBar.className = 'h-2.5 rounded-full transition-all duration-300 ease-in-out ' + colorClass;
    }
</script>
