<?php
// db_connect.php
// File ini bertanggung jawab untuk membuat koneksi ke database MariaDB.

// Konfigurasi database
define('DB_HOST', 'localhost'); // Host database Anda
define('DB_USER', 'root');     // Username database Anda
define('DB_PASS', '1');         // Password database Anda
define('DB_NAME', 'tugas_claim_db'); // Nama database Anda

try {
    // Buat objek PDO (PHP Data Objects) untuk koneksi database
    // DSN (Data Source Name) menentukan jenis database, host, dan nama database.
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    
    // Atur mode error PDO ke Exception. Ini akan membuat PDO membuang pengecualian
    // jika ada kesalahan SQL, memudahkan debugging.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Atur mode fetch default menjadi associative array. Ini berarti hasil query
    // akan dikembalikan sebagai array di mana kolom dapat diakses dengan nama kolomnya.
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // echo "Koneksi database berhasil!"; // Anda bisa mengaktifkan ini untuk tes awal
} catch (PDOException $e) {
    // Tangani kesalahan koneksi database
    // Jangan tampilkan pesan error detail di produksi untuk alasan keamanan.
    // Log error ke file instead.
    die("Koneksi database gagal: " . $e->getMessage());
}
?>
