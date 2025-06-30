// js/claims_pagination_mobile.js
// Script ini mengelola paginasi sisi klien untuk bagian "Produk/Tugas Tersedia"
// khusus untuk tampilan mobile pada halaman claims.php.

document.addEventListener('DOMContentLoaded', function() {
    const productCardsContainerClaims = document.getElementById('product-cards-container-claims');
    if (!productCardsContainerClaims) {
        console.log("Product cards container for claims not found. Mobile pagination disabled.");
        return;
    }

    const productCardsClaims = Array.from(productCardsContainerClaims.querySelectorAll('.product-card-claims'));
    const mobilePaginationNavClaims = document.getElementById('claims-mobile-pagination-nav');
    const mobilePrevBtnClaims = document.getElementById('claims-mobile-prev-btn');
    const mobileNextBtnClaims = document.getElementById('claims-mobile-next-btn');
    const mobilePageInfoClaims = document.getElementById('claims-mobile-page-info');
    
    const itemsPerPageMobileClaims = 9; // Menampilkan 9 item per "halaman" di mobile (3x3)
    let currentPageMobileClaims = 1;
    let totalMobilePagesClaims = Math.ceil(productCardsClaims.length / itemsPerPageMobileClaims);

    // Fungsi untuk menampilkan kartu produk yang relevan untuk halaman saat ini di mobile
    function showMobilePageClaims(page) {
        // Pastikan halaman tidak kurang dari 1 dan tidak lebih dari total halaman
        currentPageMobileClaims = Math.max(1, Math.min(page, totalMobilePagesClaims));

        const startIndex = (currentPageMobileClaims - 1) * itemsPerPageMobileClaims;
        const endIndex = startIndex + itemsPerPageMobileClaims;

        productCardsClaims.forEach((card, index) => {
            if (index >= startIndex && index < endIndex) {
                card.style.display = 'flex'; // Tampilkan sebagai flex item
            } else {
                card.style.display = 'none'; // Sembunyikan
            }
        });

        // Perbarui status tombol navigasi
        if (mobilePrevBtnClaims) {
            mobilePrevBtnClaims.disabled = currentPageMobileClaims === 1;
            mobilePrevBtnClaims.classList.toggle('opacity-50', currentPageMobileClaims === 1);
            mobilePrevBtnClaims.classList.toggle('cursor-not-allowed', currentPageMobileClaims === 1);
        }
        if (mobileNextBtnClaims) {
            mobileNextBtnClaims.disabled = currentPageMobileClaims === totalMobilePagesClaims;
            mobileNextBtnClaims.classList.toggle('opacity-50', currentPageMobileClaims === totalMobilePagesClaims);
            mobileNextBtnClaims.classList.toggle('cursor-not-allowed', currentPageMobileClaims === totalMobilePagesClaims);
        }

        // Perbarui info halaman
        if (mobilePageInfoClaims) {
            mobilePageInfoClaims.textContent = `Page ${currentPageMobileClaims} of ${totalMobilePagesClaims}`;
        }
    }

    // Fungsi untuk memeriksa ukuran layar dan mengaktifkan/menonaktifkan paginasi mobile
    function checkScreenSizeAndTogglePaginationClaims() {
        // Lebar breakpoint mobile (sesuai Tailwind sm:hidden)
        const isMobile = window.innerWidth < 768; // Tailwind default for sm breakpoint is 768px in MD

        if (isMobile && productCardsClaims.length > itemsPerPageMobileClaims) {
            // Tampilkan paginasi mobile jika ada lebih dari 9 kartu
            if (mobilePaginationNavClaims) mobilePaginationNavClaims.style.display = 'flex';
            // Sembunyikan paginasi PHP (jika ada, ini hanya untuk memastikan)
            const phpPagination = document.querySelector('nav.hidden.sm\\:flex');
            if (phpPagination) phpPagination.style.display = 'none';
            
            showMobilePageClaims(currentPageMobileClaims); // Tampilkan halaman pertama
        } else {
            // Sembunyikan paginasi mobile
            if (mobilePaginationNavClaims) mobilePaginationNavClaims.style.display = 'none';
            // Tampilkan kembali semua kartu (untuk desktop/tablet)
            productCardsClaims.forEach(card => card.style.display = 'flex');
            // Tampilkan paginasi PHP (jika ada)
            const phpPagination = document.querySelector('nav.hidden.sm\\:flex');
            if (phpPagination) phpPagination.style.display = 'flex'; // Tampilkan pagination PHP
        }

        // Jika tidak ada cukup kartu untuk paginasi mobile, sembunyikan tombol
        if (productCardsClaims.length <= itemsPerPageMobileClaims && mobilePaginationNavClaims) {
            mobilePaginationNavClaims.style.display = 'none';
        }
    }

    // Event Listeners untuk tombol navigasi mobile
    if (mobilePrevBtnClaims) {
        mobilePrevBtnClaims.addEventListener('click', function() {
            showMobilePageClaims(currentPageMobileClaims - 1);
        });
    }

    if (mobileNextBtnClaims) {
        mobileNextBtnClaims.addEventListener('click', function() {
            showMobilePageClaims(currentPageMobileClaims + 1);
        });
    }

    // Jalankan saat DOMContentLoaded dan pada resize
    checkScreenSizeAndTogglePaginationClaims();
    window.addEventListener('resize', checkScreenSizeAndTogglePaginationClaims);
});
