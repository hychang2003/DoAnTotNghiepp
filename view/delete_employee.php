<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Kiểm tra quyền truy cập
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu
function getConnection($host, $username, $password, $dbname) {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Kết nối đến cơ sở dữ liệu chính (fashion_shop)
$conn = getConnection($host, $username, $password, 'fashion_shop');

// Kiểm tra xem id có được truyền qua không
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: employee.php?error=invalid_id");
    exit();
}

$id = (int)$_GET['id'];

// Xóa nhân viên từ bảng users
$sql = "DELETE FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Lỗi chuẩn bị truy vấn: " . $conn->error);
}

$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    // Xóa thành công, chuyển hướng về employee.php với thông báo
    header("Location: employee.php?delete_success=true");
} else {
    // Xóa thất bại, chuyển hướng với thông báo lỗi
    header("Location: employee.php?error=delete_failed");
}

$stmt->close();
$conn->close();
exit();
?>