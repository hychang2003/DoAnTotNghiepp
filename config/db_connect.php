<?php
// Thông tin kết nối cơ sở dữ liệu
$host = 'localhost';
$username = 'root'; // Thay bằng username của bạn
$password = ''; // Thay bằng password của bạn
$dbname = 'fashion_shop'; // Sửa tên cơ sở dữ liệu thành 'fashion_shop' (dựa trên file SQL bạn đã cung cấp)

// Tạo kết nối
$conn = new mysqli($host, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
?>