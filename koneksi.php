<?php
$conn = mysqli_connect("localhost", "root", "zwei1", "rental_mobil");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
