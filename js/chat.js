// js/chat.js
// Script ini mengelola fungsionalitas live chat, termasuk mengirim dan menerima pesan,
// serta menampilkan pesan dalam jendela chat.

document.addEventListener('DOMContentLoaded', function() {
    // Pastikan elemen-elemen ini ada sebelum melanjutkan eksekusi skrip
    const chatWindow = document.getElementById('chatWindow');
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const sendMessageBtn = document.getElementById('sendMessageBtn');
    const sendImageBtn = document.getElementById('sendImageBtn');
    const chatImageInput = document.getElementById('chatImageInput');

    // Jika salah satu elemen kunci tidak ditemukan, hentikan eksekusi
    if (!chatWindow || !chatMessages || !chatInput || !sendMessageBtn || !sendImageBtn || !chatImageInput) {
        console.error("Salah satu elemen DOM chat pengguna tidak ditemukan. Pastikan halaman HTML memiliki semua ID yang diperlukan.");
        // Nonaktifkan input chat jika elemen tidak ada
        if (chatInput) chatInput.disabled = true;
        if (sendMessageBtn) sendMessageBtn.disabled = true;
        if (sendImageBtn) sendImageBtn.disabled = true;
        if (chatMessages) { // Tampilkan pesan error jika chatMessages ada
            chatMessages.innerHTML = '<div class="text-center text-red-500 text-sm mt-4">Chat tidak dapat dimuat karena elemen penting hilang.</div>';
        }
        return; // Hentikan eksekusi script chat
    }


    // Pastikan window.loggedInUserId tersedia dari header.php
    const currentLoggedInUserId = window.loggedInUserId;
    if (currentLoggedInUserId === null || typeof currentLoggedInUserId === 'undefined') {
        console.error("User ID not available for chat. Make sure header.php sets window.loggedInUserId correctly.");
        chatMessages.innerHTML = '<div class="text-center text-red-500 text-sm mt-4">Chat tidak tersedia. Silakan login kembali atau hubungi dukungan.</div>';
        // Nonaktifkan input chat jika user ID tidak ada
        chatInput.disabled = true;
        sendMessageBtn.disabled = true;
        sendImageBtn.disabled = true;
        return; // Hentikan eksekusi script chat
    }

    // Variabel untuk menyimpan ID pesan terakhir yang dimuat
    let lastFetchedMessageId = 0;
    let initialLoadComplete = false; // Flag untuk menandakan pemuatan awal semua pesan sudah selesai
    let isLoadingMessages = false; // Flag untuk mencegah beberapa panggilan loadChatMessages berjalan bersamaan

    // Fungsi untuk memainkan suara notifikasi (seperti BlackBerry Messenger)
    function playNotificationSound() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(440, audioCtx.currentTime); // Frekuensi standar (A4)
            gainNode.gain.setValueAtTime(0.5, audioCtx.currentTime); // Volume sedang

            oscillator.start();
            gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5); // Meluruh cepat
            oscillator.stop(audioCtx.currentTime + 0.5);
        } catch (e) {
            console.warn("Gagal memainkan suara notifikasi:", e);
        }
    }

    // Fungsi untuk memuat pesan chat dari server
    async function loadChatMessages(fetchOnlyNew = false) {
        if (isLoadingMessages) {
            return;
        }
        isLoadingMessages = true; // Setel flag loading
        console.log('Memulai pemuatan pesan chat untuk user ID:', currentLoggedInUserId, 'Fetch hanya yang baru:', fetchOnlyNew, 'Last Message ID:', lastFetchedMessageId);
        try {
            let url = 'api/chat_api.php?action=getMessages';

            // Pada pemuatan awal (fetchOnlyNew=false), selalu kosongkan dan reset state
            if (!fetchOnlyNew) {
                chatMessages.innerHTML = '<div class="text-center text-gray-500 text-sm mt-4">Memuat pesan...</div>'; // Tampilkan pesan loading
                lastFetchedMessageId = 0; // Reset ID untuk awal yang baru
            } else if (lastFetchedMessageId > 0) { // Untuk pengambilan inkremental berikutnya
                url += `&last_message_id=${lastFetchedMessageId}`;
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Respons non-OK dari chat_api.php:', response.status, errorText);
                throw new Error(`HTTP error! status: ${response.status} - ${errorText}`);
            }

            const newMessages = await response.json();
            console.log('Pesan diterima dari API:', newMessages);

            // Hapus pesan "Memuat pesan..." jika ini adalah pemuatan penuh dan ada pesan yang diterima
            if (!fetchOnlyNew && chatMessages.innerHTML.includes("Memuat pesan...") && newMessages.length > 0) {
                chatMessages.innerHTML = '';
            }

            let hasNewIncomingMessage = false; // Flag untuk suara notifikasi
            if (newMessages.length > 0) {
                let imagesToLoad = [];
                newMessages.forEach(msg => {
                    const messageElement = displayMessage(msg);
                    if (messageElement && msg.image_url) {
                        const img = messageElement.querySelector('img');
                        if (img && !img.complete) { // Hanya tambahkan ke Promise jika gambar belum selesai dimuat
                            imagesToLoad.push(new Promise(resolve => {
                                img.onload = resolve;
                                img.onerror = resolve;
                            }));
                        }
                    }
                    if (msg.id > lastFetchedMessageId) {
                        lastFetchedMessageId = msg.id;
                        // Cek apakah pesan baru ini adalah pesan masuk (bukan yang dikirim oleh pengguna saat ini)
                        if (msg.sender_id != currentLoggedInUserId) {
                            hasNewIncomingMessage = true;
                        }
                    }
                });

                // Tunggu hingga semua gambar dimuat (tetap penting untuk rendering yang benar)
                if (imagesToLoad.length > 0) {
                    await Promise.all(imagesToLoad);
                }

                // Setelah semua pesan ditampilkan, putar suara jika ada pesan masuk baru dan ini bukan pemuatan awal
                if (hasNewIncomingMessage && initialLoadComplete) {
                    playNotificationSound();
                }

                // Hanya set initialLoadComplete setelah pemuatan awal selesai
                if (!fetchOnlyNew) {
                    initialLoadComplete = true;
                }
            }

            // Tampilkan pesan "Belum ada pesan" jika chat kosong setelah pemuatan
            if (chatMessages.childElementCount === 0 && newMessages.length === 0) {
                chatMessages.innerHTML = '<div class="text-center text-gray-500 text-sm mt-4">Belum ada pesan. Mulai percakapan Anda!</div>';
            }


        } catch (error) {
            console.error('Gagal memuat pesan chat:', error);
            if (chatMessages.childElementCount === 0 || chatMessages.innerHTML.includes('Gagal memuat pesan')) {
                chatMessages.innerHTML = '<div class="text-center text-red-500 text-sm mt-4">Gagal memuat pesan. Coba lagi nanti. Cek konsol browser untuk detail.</div>';
            }
        } finally {
            isLoadingMessages = false;
        }
    }

    // Fungsi untuk menggulir ke bawah pesan chat (tetap ada untuk penggunaan manual jika diinginkan)
    function scrollChatToBottom() {
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    // Fungsi untuk menampilkan pesan ke UI
    function displayMessage(msg) {
        const isSent = msg.sender_id == currentLoggedInUserId;
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('chat-message', isSent ? 'sent' : 'received', 'p-2', 'my-2', 'rounded-lg', 'flex', 'flex-col');
        messageDiv.style.maxWidth = '80%';
        messageDiv.style.wordWrap = 'break-word';

        if (isSent) {
            // Untuk pesan yang dikirim oleh pengguna, gunakan teks hitam
            messageDiv.classList.add('bg-blue-500', 'text-black', 'ml-auto');
        } else {
            messageDiv.classList.add('bg-gray-200', 'text-gray-800', 'mr-auto');
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
        timestamp.classList.add('timestamp', 'text-xs', 'mt-1', 'block', isSent ? 'text-blue-200' : 'text-gray-600', 'self-end');
        const date = new Date(msg.sent_at);
        timestamp.textContent = date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' ' +
                                 date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
        messageDiv.appendChild(timestamp);

        chatMessages.appendChild(messageDiv);
        return messageDiv;
    }

    // Fungsi untuk mengirim pesan
    async function sendMessage(messageText, imageUrl = null) {
        if (!messageText && !imageUrl) {
            return;
        }

        console.log('Mengirim pesan:', { messageText, imageUrl });

        try {
            const formData = new FormData();
            formData.append('action', 'sendMessage');
            if (messageText) {
                formData.append('message', messageText);
            }
            if (imageUrl) {
                formData.append('image_url', imageUrl);
            }

            const response = await fetch('api/chat_api.php', {
                method: 'POST',
                body: formData
            });

            console.log('Respons kirim pesan diterima:', response);

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Respons non-OK saat kirim pesan:', response.status, errorText);
                alert('Gagal mengirim pesan: ' + (errorText || 'Unknown error.')); // Menggunakan alert()
                throw new Error(`HTTP error! status: ${response.status} - ${errorText}`);
            }

            const result = await response.json();
            console.log('Hasil kirim pesan API:', result);

            if (result.success) {
                chatInput.value = '';
                chatImageInput.value = '';
                // Setelah pesan dikirim, kita masih ingin memuat pesan terbaru
                // NAMUN TIDAK ADA AUTO-SCROLL. Pengguna harus scroll manual.
                loadChatMessages(true);
            } else {
                console.error('Gagal mengirim pesan (API result success=false):', result.message);
                alert('Gagal mengirim pesan: ' + (result.message || 'Unknown error.')); // Menggunakan alert()
            }
        } catch (error) {
            console.error('Error mengirim pesan:', error);
            alert('Terjadi kesalahan saat mengirim pesan. Cek konsol untuk detail.'); // Menggunakan alert()
        }
    }

    // Event listener untuk tombol kirim pesan
    if (sendMessageBtn) {
        sendMessageBtn.addEventListener('click', function() {
            sendMessage(chatInput.value);
        });
    }

    // Event listener untuk input field (tekan Enter)
    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage(chatInput.value);
            }
        });
    }

    // Event listener untuk tombol kirim gambar
    if (sendImageBtn) {
        sendImageBtn.addEventListener('click', function() {
            chatImageInput.click();
        });
    }

    // Event listener saat file gambar dipilih
    if (chatImageInput) {
        chatImageInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (file) {
                const loadingMessage = document.createElement('div');
                loadingMessage.id = 'upload-loading';
                loadingMessage.classList.add('text-center', 'text-gray-500', 'text-sm', 'my-2');
                loadingMessage.textContent = 'Mengunggah gambar...'; // Menambahkan teks loading
                chatMessages.appendChild(loadingMessage);
                // Tidak ada auto-scroll di sini setelah upload dimulai
                // scrollChatToBottom();

                const uploadFormData = new FormData();
                uploadFormData.append('image', file);

                try {
                    console.log('Mengunggah gambar ke upload_image.php...');
                    const uploadResponse = await fetch('api/upload_image.php', {
                        method: 'POST',
                        body: uploadFormData
                    });

                    console.log('Respons upload gambar diterima:', uploadResponse);

                    if (!uploadResponse.ok) {
                        const errorText = await uploadResponse.text();
                        console.error('Respons non-OK saat upload gambar:', uploadResponse.status, errorText);
                        alert('Gagal mengunggah gambar: ' + (errorText || 'Unknown error.')); // Menggunakan alert()
                        throw new Error(`HTTP error! status: ${uploadResponse.status} - ${errorText}`);
                    }

                    const uploadResult = await uploadResponse.json();
                    console.log('Hasil upload gambar API:', uploadResult);

                    if (uploadResult.success && uploadResult.image_url) {
                        sendMessage(chatInput.value, uploadResult.image_url);
                    } else {
                        alert('Gagal mengunggah gambar: ' + (uploadResult.message || 'Unknown error.')); // Menggunakan alert()
                    }
                } catch (error) {
                    console.error('Error saat mengunggah gambar:', error);
                    alert('Terjadi kesalahan saat mengirim pesan. Cek konsol untuk detail.'); // Menggunakan alert()
                } finally {
                    const loadingIndicator = document.getElementById('upload-loading');
                    if (loadingIndicator) {
                        loadingIndicator.remove();
                    }
                }
            }
        });
    }

    // Muat pesan saat DOM selesai dimuat (pemuatan awal semua pesan)
    loadChatMessages(false);

    // Muat ulang pesan setiap 3 detik untuk simulasi real-time sederhana (hanya ambil yang baru)
    setInterval(() => loadChatMessages(true), 3000);
});
