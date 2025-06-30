// js/product_pagination_mobile.js
// Script ini mengelola paginasi sisi klien untuk bagian "Tugas Tersedia"
// khusus untuk tampilan mobile.

document.addEventListener('DOMContentLoaded', function() {
    const productCardsContainer = document.getElementById('product-cards-container');
    if (!productCardsContainer) {
        console.log("Product cards container not found. Mobile pagination disabled.");
        return;
    }

    const productCards = Array.from(productCardsContainer.querySelectorAll('.product-card'));
    const mobilePaginationNav = document.getElementById('mobile-pagination-nav');
    const mobilePrevBtn = document.getElementById('mobile-prev-btn');
    const mobileNextBtn = document.getElementById('mobile-next-btn');
    const mobilePageInfo = document.getElementById('mobile-page-info');
    
    const itemsPerPageMobile = 3; // Menampilkan 3 item per "halaman" di mobile
    let currentPageMobile = 1;
    let totalMobilePages = Math.ceil(productCards.length / itemsPerPageMobile);

    // Fungsi untuk menampilkan kartu produk yang relevan untuk halaman saat ini
    function showMobilePage(page) {
        // Pastikan halaman tidak kurang dari 1 dan tidak lebih dari total halaman
        currentPageMobile = Math.max(1, Math.min(page, totalMobilePages));

        const startIndex = (currentPageMobile - 1) * itemsPerPageMobile;
        const endIndex = startIndex + itemsPerPageMobile;

        productCards.forEach((card, index) => {
            if (index >= startIndex && index < endIndex) {
                card.style.display = 'flex'; // Tampilkan sebagai flex item
            } else {
                card.style.display = 'none'; // Sembunyikan
            }
        });

        // Perbarui status tombol navigasi
        if (mobilePrevBtn) {
            mobilePrevBtn.disabled = currentPageMobile === 1;
            mobilePrevBtn.classList.toggle('opacity-50', currentPageMobile === 1);
            mobilePrevBtn.classList.toggle('cursor-not-allowed', currentPageMobile === 1);
        }
        if (mobileNextBtn) {
            mobileNextBtn.disabled = currentPageMobile === totalMobilePages;
            mobileNextBtn.classList.toggle('opacity-50', currentPageMobile === totalMobilePages);
            mobileNextBtn.classList.toggle('cursor-not-allowed', currentPageMobile === totalMobilePages);
        }

        // Perbarui info halaman
        if (mobilePageInfo) {
            mobilePageInfo.textContent = `Page ${currentPageMobile} of ${totalMobilePages}`;
        }
    }

    // Fungsi untuk memeriksa ukuran layar dan mengaktifkan/menonaktifkan paginasi mobile
    function checkScreenSizeAndTogglePagination() {
        // Lebar breakpoint mobile (sesuai Tailwind sm:hidden)
        const isMobile = window.innerWidth < 768; // Tailwind default for sm breakpoint is 768px in MD

        if (isMobile && productCards.length > itemsPerPageMobile) {
            // Tampilkan paginasi mobile jika ada lebih dari 3 kartu
            if (mobilePaginationNav) mobilePaginationNav.style.display = 'flex';
            // Sembunyikan paginasi PHP (jika ada, ini hanya untuk memastikan)
            const phpPagination = document.querySelector('nav.hidden.sm\\:flex');
            if (phpPagination) phpPagination.style.display = 'none';
            
            showMobilePage(currentPageMobile); // Tampilkan halaman pertama
        } else {
            // Sembunyikan paginasi mobile
            if (mobilePaginationNav) mobilePaginationNav.style.display = 'none';
            // Tampilkan kembali semua kartu (untuk desktop/tablet)
            productCards.forEach(card => card.style.display = 'flex');
            // Tampilkan paginasi PHP (jika ada)
            const phpPagination = document.querySelector('nav.hidden.sm\\:flex');
            if (phpPagination) phpPagination.style.display = 'flex'; // Tampilkan pagination PHP
        }

        // Jika tidak ada cukup kartu untuk paginasi mobile, sembunyikan tombol
        if (productCards.length <= itemsPerPageMobile && mobilePaginationNav) {
            mobilePaginationNav.style.display = 'none';
        }
    }

    // Event Listeners untuk tombol navigasi mobile
    if (mobilePrevBtn) {
        mobilePrevBtn.addEventListener('click', function() {
            showMobilePage(currentPageMobile - 1);
        });
    }

    if (mobileNextBtn) {
        mobileNextBtn.addEventListener('click', function() {
            showMobilePage(currentPageMobile + 1);
        });
    }

    // Jalankan saat DOMContentLoaded dan pada resize
    checkScreenSizeAndTogglePagination();
    window.addEventListener('resize', checkScreenSizeAndTogglePagination);
});
