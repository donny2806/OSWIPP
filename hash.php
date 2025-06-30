<?php
// password_hashing_tool.php
// Skrip ini digunakan untuk menghasilkan hash password yang aman menggunakan password_hash().
// Anda dapat menjalankan skrip ini dari command line untuk mendapatkan hash dari sebuah password,
// yang kemudian dapat Anda gunakan untuk memasukkan atau memperbarui password pengguna (termasuk admin)
// secara manual di database Anda.

// Password yang ingin Anda hash
$password_to_hash = 'admin123'; // Ganti dengan password yang Anda inginkan

// Gunakan PASSWORD_DEFAULT (algoritma hashing bcrypt yang direkomendasikan saat ini)
// password_hash() secara otomatis menangani salting secara acak dan iterasinya.
$hashed_password = password_hash($password_to_hash, PASSWORD_DEFAULT);

echo "Password asli: " . $password_to_hash . "\n";
echo "Password ter-hash (siap untuk database): " . $hashed_password . "\n";

// Cara memverifikasi password (untuk tujuan demonstrasi atau debugging)
// Pada aplikasi nyata, Anda akan menggunakan password_verify() saat login.
// $is_valid = password_verify($password_to_hash, $hashed_password);
// echo "Verifikasi (password asli vs hash): " . ($is_valid ? "Valid" : "Tidak Valid") . "\n";

?>
