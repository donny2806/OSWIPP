// js/script.js
// Script ini mengelola interaksi UI dasar seperti menu burger dan tampilan chat.

document.addEventListener('DOMContentLoaded', function() {
    const burgerBtn = document.getElementById('burgerBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const liveChatBtn = document.getElementById('liveChatBtn');
    const chatWindow = document.getElementById('chatWindow');
    const closeChatBtn = document.getElementById('closeChatBtn');

    // Toggle Sidebar dan Overlay
    function toggleSidebar() {
        sidebar.classList.toggle('open');
        overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
        burgerBtn.classList.toggle('open'); // Mengubah ikon burger
    }

    if (burgerBtn) {
        burgerBtn.addEventListener('click', toggleSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', toggleSidebar); // Tutup sidebar saat klik overlay
    }

    // Toggle Chat Window
    if (liveChatBtn) {
        liveChatBtn.addEventListener('click', function() {
            chatWindow.style.display = chatWindow.style.display === 'flex' ? 'none' : 'flex';
            // Jika jendela chat dibuka, gulir ke bawah untuk menampilkan pesan terbaru
            if (chatWindow.style.display === 'flex') {
                const chatMessages = document.getElementById('chatMessages');
                if (chatMessages) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            }
        });
    }

    if (closeChatBtn) {
        closeChatBtn.addEventListener('click', function() {
            chatWindow.style.display = 'none';
        });
    }
});
