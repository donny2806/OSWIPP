<?php
// footer.php
// File ini berisi bagian kaki (footer) dari setiap halaman, termasuk informasi hak cipta
// dan tautan ke file JavaScript.
?>
    </main> <!-- Tutup tag main dari halaman utama jika ada, atau ini bisa dihilangkan -->

    <!-- Footer yang selalu tampil dan ukurannya sangat diperkecil -->
    <footer id="main-footer" class="bg-gray-800 text-white py-1 px-4">
        <div class="container mx-auto text-center text-[0.65rem] leading-tight">
            <p>&copy; <?= date('Y') ?> PT Deprintz Sukses Sejahtera. Hak Cipta Dilindungi Undang-Undang.</p>
            <p class="mt-0.5">Jl. Margomulyo I Blok E No. 12, Balongsari,<br>Kec. Tandes, Surabaya. </p>
            <p class="mt-0.25">Surabaya, Jawa Timur 60183</p>
            <p class="mt-0.25">WhatsApp : +62 821-6019-5535 | Email : info@deprintz.com</p>
        </div>
    </footer>

    <!-- JavaScript untuk fungsionalitas UI -->
    <script src="js/script.js"></script>
    <?php if (isset($loggedIn) && $loggedIn): // Periksa apakah $loggedIn ada dan true ?>
        <!-- Hanya muat chat.js jika user login -->
        <script src="js/chat.js"></script>
    <?php endif; ?>
</body>
</html>
