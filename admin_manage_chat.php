<?php
// admin_manage_chat.php
// Halaman ini memungkinkan admin untuk melihat daftar pengguna aktif
// dan memulai atau melanjutkan percakapan dengan mereka.

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
    error_log("Error checking admin status in admin_manage_chat.php: " . $e->getMessage());
    header('Location: index.php'); // Redirect ke login jika ada masalah otentikasi
    exit();
}

$message = '';
$message_type = ''; // 'success' atau 'error'

// Ambil daftar semua pengguna (non-admin) untuk chat
// Bagian ini akan dimuat via JavaScript menggunakan API
$users_for_chat = []; // Initialize as empty, JS will fill this

// Tambahkan kolom is_read_by_admin ke tabel chats jika belum ada
try {
    $pdo->exec("
        ALTER TABLE chats
        ADD COLUMN is_read_by_admin BOOLEAN DEFAULT FALSE;
    ");
} catch (PDOException $e) {
    // Abaikan error jika kolom sudah ada
    // Ini adalah fallback untuk memastikan kolom ada jika skema tidak diupdate secara manual
    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
        error_log("Error adding is_read_by_admin column: " . $e->getMessage());
    }
}


// Tentukan user target chat jika dipilih dari daftar
$target_chat_user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$target_chat_username = '';
$chat_messages = []; // Ini akan diisi oleh JavaScript

if ($target_chat_user_id) {
    try {
        // Verifikasi bahwa target user ada dan bukan admin
        $stmt_target_user = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND is_admin = FALSE");
        $stmt_target_user->execute([$target_chat_user_id]);
        $target_user = $stmt_target_user->fetch();

        if ($target_user) {
            $target_chat_username = htmlspecialchars($target_user['username']);
            
            // Tandai pesan dari user ini ke admin sebagai sudah dibaca
            // Termasuk pesan direct ke admin saat ini dan pesan ke admin umum (receiver_id IS NULL)
            $stmt_mark_read = $pdo->prepare("
                UPDATE chats 
                SET is_read_by_admin = TRUE 
                WHERE (sender_id = :target_user_id AND receiver_id = :current_admin_id AND is_read_by_admin = FALSE) 
                   OR (sender_id = :target_user_id AND receiver_id IS NULL AND is_read_by_admin = FALSE)
            ");
            $stmt_mark_read->bindParam(':target_user_id', $target_chat_user_id, PDO::PARAM_INT);
            $stmt_mark_read->bindParam(':current_admin_id', $current_user_id, PDO::PARAM_INT);
            $stmt_mark_read->execute();

            // Pesan awal akan dimuat oleh JavaScript, bukan di PHP lagi
        } else {
            $message = "Pengguna chat tidak ditemukan atau bukan pengguna biasa.";
            $message_type = 'error';
            $target_chat_user_id = null; // Reset ID jika tidak valid
        }
    } catch (PDOException $e) {
        error_log("Error fetching chat messages for admin: " . $e->getMessage());
        $message = "Gagal memuat pesan chat.";
        $message_type = 'error';
    }
}

// Sertakan header halaman
include_once __DIR__ . '/header.php';
?>

<main class="flex-grow container mx-auto p-4 md:p-8 flex flex-col lg:flex-row gap-8">
    <div class="lg:w-1/4 bg-white p-6 rounded-xl shadow-md flex-shrink-0">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Daftar Pengguna</h2>
        <div class="mb-4">
            <input type="text" id="userSearchInput" placeholder="Cari pengguna..."
                   class="w-full p-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div id="userListContainer" class="max-h-[60vh] overflow-y-auto"> <p class="text-gray-600 text-center">Memuat pengguna...</p>
        </div>
    </div>

    <div class="lg:w-3/4 bg-white p-6 rounded-xl shadow-md flex flex-col" data-target-user-id="<?= $target_chat_user_id ?? '' ?>">
        <h1 class="text-4xl font-extrabold text-gray-900 mb-6 text-center">Live Chat Admin</h1>

        <?php if ($message): ?>
            <div class="p-4 mb-4 text-sm rounded-lg 
                        <?= $message_type === 'success' ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-red-100 text-red-800 border border-red-400' ?>" 
                        role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($target_chat_user_id): ?>
            <div class="flex flex-col flex-grow border border-gray-200 rounded-lg overflow-hidden h-[60vh] md:h-[70vh]"> 
                <div class="bg-blue-600 text-white p-4 font-semibold text-lg flex justify-between items-center">
                    <span>Chat dengan: <?= $target_chat_username ?></span>
                </div>
                <div id="adminChatMessages" class="chat-messages flex-grow p-4 overflow-y-auto bg-gray-50">
                    <!-- Pesan akan dimuat di sini oleh JavaScript -->
                    <div class="text-center text-gray-500 mt-4">Memuat pesan...</div>
                </div>
                <div class="chat-input p-4 border-t border-gray-200 bg-white flex items-center">
                    <input type="text" id="adminChatInput" placeholder="Ketik pesan Anda..." 
                           class="flex-grow p-2 border border-gray-300 rounded-lg mr-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <input type="file" id="adminChatImageInput" accept="image/*" class="hidden">
                    <button id="adminSendImageBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-2 rounded-lg mr-2 transition duration-200">
                        <i class="fas fa-image"></i>
                    </button>
                    <button id="adminSendMessageBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-200">
                        Kirim
                    </button>
                </div>
            </div>
        <?php else: ?>
            <p class="text-center text-gray-600 mt-10 text-lg">Pilih pengguna dari daftar di samping untuk memulai chat.</p>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pastikan elemen-elemen ini ada sebelum mengaksesnya
    const adminChatMessages = document.getElementById('adminChatMessages');
    const adminChatInput = document.getElementById('adminChatInput');
    const adminSendMessageBtn = document.getElementById('adminSendMessageBtn');
    const adminSendImageBtn = document.getElementById('adminSendImageBtn');
    const adminChatImageInput = document.getElementById('adminChatImageInput');
    const userListContainer = document.getElementById('userListContainer');
    const userSearchInput = document.getElementById('userSearchInput'); // New: Search input
    
    // Periksa apakah targetUserId ada sebelum mencoba mengaksesnya
    const targetUserIdElement = document.querySelector('[data-target-user-id]');
    const targetUserId = targetUserIdElement ? parseInt(targetUserIdElement.dataset.targetUserId) : null;

    const currentAdminId = <?= $current_user_id ?>; // ID admin yang sedang login

    let allUsers = []; // Store all users fetched from API
    let originalTitle = document.title;
    let notificationInterval = null;
    let hasUnreadMessages = false;
    let lastFetchedMessageId = 0; // Tambahkan untuk melacak ID pesan terakhir yang dimuat
    let initialChatLoad = true; // Flag untuk pemuatan chat pertama kali dengan user
    let isLoadingChatMessages = false; // Flag untuk mencegah panggilan loadAdminChatMessages bersamaan

    function scrollChatToBottomAdmin() {
        if (adminChatMessages) {
            adminChatMessages.scrollTop = adminChatMessages.scrollHeight;
        }
    }

    function playNotificationSound() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(600, audioCtx.currentTime);
            gainNode.gain.setValueAtTime(0.5, audioCtx.currentTime);

            oscillator.start();
            gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
            oscillator.stop(audioCtx.currentTime + 0.3);
        } catch (e) {
            console.warn("Gagal memainkan suara notifikasi:", e);
        }
    }

    function toggleTitleNotification() {
        if (hasUnreadMessages) {
            document.title = (document.title === originalTitle || document.title === 'Situs Tugas') ? '[!] Ada Pesan Masuk !' : originalTitle;
        } else {
            document.title = originalTitle;
            clearInterval(notificationInterval);
            notificationInterval = null;
        }
    }

    function displayAdminMessage(msg) {
        if (!adminChatMessages) return null; // Tambahkan guard clause

        const isSent = msg.sender_id == currentAdminId;
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('chat-message', isSent ? 'sent' : 'received', 'p-2', 'my-2', 'rounded-lg', 'flex', 'flex-col');
        messageDiv.style.maxWidth = '80%';
        messageDiv.style.wordWrap = 'break-word';
        
        if (isSent) {
            messageDiv.classList.add('bg-blue-500', 'ml-auto'); // Hapus text-white dari sini
        } else {
            messageDiv.classList.add('bg-gray-200', 'mr-auto');
        }

        if (!isSent && msg.sender_username) {
            const senderName = document.createElement('p');
            senderName.classList.add('font-semibold', 'text-xs', 'text-blue-700', 'mb-1');
            senderName.textContent = msg.sender_username;
            messageDiv.appendChild(senderName);
        }

        if (msg.message) {
            const messageText = document.createElement('p');
            messageText.textContent = msg.message;
            // Ubah warna teks di sini: admin's sent messages (isSent) akan menjadi text-gray-900 (hitam gelap)
            // User's received messages (!isSent) akan tetap text-gray-800
            messageText.classList.add(isSent ? 'text-gray-900' : 'text-gray-800'); 
            messageDiv.appendChild(messageText);
        }
        if (msg.image_url) {
            const messageImage = document.createElement('img');
            messageImage.src = msg.image_url;
            messageImage.alt = 'Gambar terlampir';
            messageImage.classList.add('block', 'mt-2', 'max-w-[150px]', 'max-h-[150px]', 'object-contain', 'rounded-md', 'cursor-pointer');
            messageImage.onclick = function() {
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center; z-index: 1000;';
                overlay.onclick = () => document.body.removeChild(overlay);

                const fullImage = document.createElement('img');
                fullImage.src = msg.image_url;
                fullImage.style.cssText = 'max-width: 90%; max-height: 90%; object-fit: contain;';
                overlay.appendChild(fullImage);
                document.body.appendChild(overlay);
            };
            messageDiv.appendChild(messageImage);
        }
        
        const timestamp = document.createElement('span');
        // Warna timestamp disesuaikan dengan warna latar belakang pesan:
        // Admin sent (blue background): text-blue-200
        // User received (gray background): text-gray-600
        timestamp.classList.add('timestamp', 'text-xs', 'mt-1', 'block', isSent ? 'text-blue-200' : 'text-gray-600', 'self-end');
        const date = new Date(msg.sent_at);
        timestamp.textContent = date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' ' +
                                 date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
        messageDiv.appendChild(timestamp);

        adminChatMessages.appendChild(messageDiv);
        return messageDiv;
    }

    // Fungsi untuk memuat pesan chat admin (hanya yang baru jika fetchOnlyNew=true)
    async function loadAdminChatMessages(fetchOnlyNew = false) {
        if (!adminChatMessages || !targetUserId || isLoadingChatMessages) {
            if (isLoadingChatMessages) console.log("Admin chat messages already loading, skipping.");
            return;
        }
        isLoadingChatMessages = true; // Set flag
        console.log('Memuat pesan admin untuk user ID:', targetUserId, 'Fetch hanya yang baru:', fetchOnlyNew, 'Last Message ID:', lastFetchedMessageId);

        try {
            let url = `api/chat_api.php?action=getMessages&target_user_id=${targetUserId}`;
            if (fetchOnlyNew && lastFetchedMessageId > 0) {
                url += `&last_message_id=${lastFetchedMessageId}`;
            } else {
                // Untuk pemuatan awal atau refresh penuh, kosongkan chatMessages
                adminChatMessages.innerHTML = '<div class="text-center text-gray-500 mt-4">Memuat pesan...</div>'; 
                lastFetchedMessageId = 0; // Reset lastFetchedMessageId pada full load
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                const errorData = await response.json();
                console.error('Respons non-OK dari chat_api.php (getMessages):', response.status, errorData.message);
                throw new Error(`HTTP error! status: ${response.status} - ${errorData.message}`);
            }

            const newMessages = await response.json();
            console.log('Pesan baru diterima dari API (admin):', newMessages);
            
            // Periksa apakah scroll berada di paling bawah sebelum menambahkan pesan
            const wasScrolledToBottom = adminChatMessages.scrollHeight - adminChatMessages.clientHeight <= adminChatMessages.scrollTop + 50;

            if (initialChatLoad && adminChatMessages.innerHTML.includes("Memuat pesan...")) {
                adminChatMessages.innerHTML = ''; // Hapus pesan 'Memuat pesan...'
            }

            if (newMessages.length > 0) {
                let imagesToLoad = [];
                newMessages.forEach(msg => {
                    const messageElement = displayAdminMessage(msg);
                    if (messageElement && msg.image_url) { // Pastikan messageElement tidak null
                        const img = messageElement.querySelector('img');
                        if (img) {
                            imagesToLoad.push(new Promise(resolve => {
                                img.onload = resolve;
                                img.onerror = resolve;
                            }));
                        }
                    }
                    if (msg.id > lastFetchedMessageId) {
                        lastFetchedMessageId = msg.id;
                    }
                });

                if (imagesToLoad.length > 0) {
                    await Promise.all(imagesToLoad);
                }

                if (wasScrolledToBottom || (!fetchOnlyNew && initialChatLoad)) { // Hanya scroll pada pemuatan awal penuh atau jika sudah di bawah
                    requestAnimationFrame(() => scrollChatToBottomAdmin());
                }
                initialChatLoad = false;
            } else if (adminChatMessages.childElementCount === 0 && !fetchOnlyNew) {
                // Jika tidak ada pesan baru dan chat kosong setelah pemuatan awal penuh
                adminChatMessages.innerHTML = '<div class="text-center text-gray-500 mt-4">Belum ada pesan dengan pengguna ini.</div>';
            }


        } catch (error) {
            console.error('Gagal memuat pesan chat admin:', error);
            if (adminChatMessages && (adminChatMessages.childElementCount === 0 || adminChatMessages.innerHTML.includes('Gagal memuat pesan'))) {
                 adminChatMessages.innerHTML = `<div class="text-center text-red-500 text-sm mt-4">Gagal memuat pesan: ${error.message}</div>`;
            }
        } finally {
            isLoadingChatMessages = false; // Reset flag
        }
    }

    async function sendAdminMessage(messageText, file = null) { // Menerima file langsung
        if (!messageText && !file) {
            return;
        }
        if (!targetUserId) {
            alert('Pilih pengguna untuk chat terlebih dahulu!');
            return;
        }

        let imageUrl = null;
        if (file) {
            const uploadFormData = new FormData();
            uploadFormData.append('image', file);

            try {
                const uploadResponse = await fetch('api/upload_image.php', {
                    method: 'POST',
                    body: uploadFormData
                });

                if (!uploadResponse.ok) {
                    const errorText = await uploadResponse.text();
                    console.error('Respons non-OK saat upload gambar admin:', uploadResponse.status, errorText);
                    alert('Gagal mengunggah gambar: ' + (errorText || 'Unknown error.'));
                    throw new Error(`HTTP error! status: ${uploadResponse.status} - ${errorText}`);
                }

                const uploadResult = await uploadResponse.json();
                if (uploadResult.success && uploadResult.image_url) {
                    imageUrl = uploadResult.image_url;
                } else {
                    alert('Gagal mengunggah gambar: ' + (uploadResult.message || 'Unknown error.'));
                    return; // Hentikan proses jika upload gambar gagal
                }
            } catch (error) {
                console.error('Error saat mengunggah gambar admin:', error);
                alert('Terjadi kesalahan saat mengunggah gambar. Cek konsol untuk detail.');
                return; // Hentikan proses jika ada error
            }
        }

        try {
            const formData = new FormData();
            formData.append('action', 'sendMessage');
            formData.append('receiver_id', targetUserId);
            if (messageText) {
                formData.append('message', messageText);
            }
            if (imageUrl) {
                formData.append('image_url', imageUrl); // Kirim URL gambar yang sudah diunggah
            }

            const response = await fetch('api/chat_api.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorData = await response.json();
                console.error('Respons non-OK saat kirim pesan admin:', response.status, errorData.message);
                alert('Gagal mengirim pesan: ' + (errorData.message || 'Unknown error.'));
                throw new Error(`HTTP error! status: ${response.status} - ${errorData.message}`);
            }

            const result = await response.json();
            if (result.success) {
                if (adminChatInput) adminChatInput.value = '';
                if (adminChatImageInput) adminChatImageInput.value = '';
                checkNewMessagesAndUserList(); // Muat ulang pesan dan daftar pengguna
            } else {
                console.error('Gagal mengirim pesan admin (API result success=false):', result.message);
                alert('Gagal mengirim pesan: ' + (result.message || 'Unknown error.'));
            }
        } catch (error) {
            console.error('Error mengirim pesan admin:', error);
            alert('Terjadi kesalahan saat mengirim pesan.');
        }
    }

    // Function to render the user list
    function renderUserList(users) {
        if (!userListContainer) return; // Guard clause

        let userListHtml = '<ul class="space-y-2">';
        let totalUnreadInSystem = 0;

        if (users.length === 0) {
            userListContainer.innerHTML = '<p class="text-gray-500 text-center">Tidak ada pengguna yang ditemukan.</p>';
            hasUnreadMessages = false;
            return;
        }

        users.forEach(user => {
            const unreadCount = (parseInt(user.unread_messages_count_direct) || 0) + (parseInt(user.unread_messages_count_broadcast_from_user) || 0);
            
            if (unreadCount > 0) {
                 if (targetUserId === null || targetUserId !== user.id) {
                    totalUnreadInSystem += unreadCount;
                }
            }

            const isActive = targetUserId == user.id ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-800';

            userListHtml += `
                <li>
                    <a href="admin_manage_chat.php?user_id=${user.id}"
                        class="flex items-center p-3 rounded-lg hover:bg-gray-100 transition duration-150 ease-in-out relative ${isActive}">
                        <i class="fas fa-user-circle mr-3 text-xl"></i>
                        <span>${user.username} (ID: ${user.id})</span>
                        ${unreadCount > 0 ? `<span class="absolute right-3 top-1/2 -translate-y-1/2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">${unreadCount}</span>` : ''}
                    </a>
                </li>
            `;
        });
        userListHtml += '</ul>';
        userListContainer.innerHTML = userListHtml;

        const prevHasUnreadMessages = hasUnreadMessages;
        hasUnreadMessages = (totalUnreadInSystem > 0);
        
        if (hasUnreadMessages && notificationInterval === null) {
            notificationInterval = setInterval(toggleTitleNotification, 1000);
            playNotificationSound();
        } else if (!hasUnreadMessages && notificationInterval !== null) {
            toggleTitleNotification();
            clearInterval(notificationInterval);
            notificationInterval = null;
        }
    }

    // Function to filter users based on search input
    function filterUsers() {
        if (!userSearchInput) return; // Guard clause
        const searchTerm = userSearchInput.value.toLowerCase();
        const filteredUsers = allUsers.filter(user => 
            user.username.toLowerCase().includes(searchTerm) || 
            String(user.id).includes(searchTerm)
        );
        renderUserList(filteredUsers);
    }

    async function checkNewMessagesAndUserList() {
        if (!userListContainer) return; // Guard clause
        try {
            const response = await fetch('api/chat_api.php?action=getUnreadStatusAndUsers', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                const errorData = await response.json();
                console.error('Respons non-OK dari chat_api.php (getUnreadStatusAndUsers):', response.status, errorData.message);
                userListContainer.innerHTML = `<p class="text-red-500 text-center">Error: ${errorData.message}</p>`;
                throw new Error(`HTTP error! status: ${response.status} - ${errorData.message}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                console.error('API response for getUnreadStatusAndUsers not successful:', data.message);
                userListContainer.innerHTML = `<p class="text-red-500 text-center">Error: ${data.message}</p>`;
                return;
            }

            // Store all fetched users
            allUsers = data.users;
            filterUsers(); // Render the list, applying any existing search term

            // Only load chat messages if a target user is selected
            if (targetUserId) {
                loadAdminChatMessages(true); // Fetch only new messages for the current chat
            }

        } catch (error) {
            console.error('Gagal memeriksa pesan baru dan daftar pengguna:', error);
            if (userListContainer) {
                userListContainer.innerHTML = `<p class="text-red-500 text-center">Gagal memuat pengguna: ${error.message}</p>`;
            }
        }
    }

    // Panggil fungsi-fungsi secara kondisional setelah elemen DOM dipastikan ada
    // Ini adalah blok untuk inisialisasi yang hanya berjalan sekali saat DOM siap
    // Pastikan adminChatMessages ada sebelum mencoba memanggil loadAdminChatMessages
    if (adminChatMessages && targetUserId) {
        loadAdminChatMessages(false); // Load all messages initially if a target user is selected
    }

    // Event listeners
    if (adminSendMessageBtn) {
        adminSendMessageBtn.addEventListener('click', function() {
            sendAdminMessage(adminChatInput.value);
        });
    }

    if (adminChatInput) {
        adminChatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // Mencegah baris baru pada textarea
                sendAdminMessage(adminChatInput.value);
            }
        });
    }

    if (adminSendImageBtn) {
        adminSendImageBtn.addEventListener('click', function() {
            adminChatImageInput.click();
        });
    }

    if (adminChatImageInput) {
        adminChatImageInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (file) {
                const loadingMessage = document.createElement('div');
                loadingMessage.id = 'upload-loading';
                loadingMessage.classList.add('text-center', 'text-gray-500', 'text-sm', 'my-2');
                if (adminChatMessages) adminChatMessages.appendChild(loadingMessage); // Cek sebelum append
                scrollChatToBottomAdmin(); // Gulir untuk melihat loading message

                try {
                    await sendAdminMessage(null, file); // Kirim file gambar langsung
                } catch (error) {
                    console.error('Error saat mengirim gambar admin:', error);
                    alert('Terjadi kesalahan saat mengirim gambar.');
                } finally {
                    const loadingIndicator = document.getElementById('upload-loading');
                    if (loadingIndicator) {
                        loadingIndicator.remove();
                    }
                }
            }
        });
    }

    // New: Event listener for search input
    if (userSearchInput) {
        userSearchInput.addEventListener('keyup', filterUsers);
    }
    
    // Panggil fungsi untuk memeriksa pesan baru dan daftar pengguna setiap 3 detik
    checkNewMessagesAndUserList();
    setInterval(checkNewMessagesAndUserList, 3000);
});
</script>

<?php include_once __DIR__ . '/footer.php'; ?>
