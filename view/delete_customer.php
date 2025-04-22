<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu của cơ sở hiện tại
function getShopConnection($host, $username, $password, $shop_db) {
    $conn = new mysqli($host, $username, $password, $shop_db);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại
$conn = getShopConnection($host, $username, $password, $shop_db);

// Kiểm tra ID khách hàng
if (!isset($_GET['id'])) {
    header("Location: customer.php?error=" . urlencode("Không tìm thấy ID khách hàng."));
    exit();
}

$customer_id = $_GET['id'];

// Xóa khách hàng
$sql = "DELETE FROM customer WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    header("Location: customer.php?error=" . urlencode("Lỗi chuẩn bị truy vấn: " . $conn->error));
    exit();
}
$stmt->bind_param('i', $customer_id);
if ($stmt->execute()) {
    $stmt->close();
    header("Location: customer.php?customer_deleted=success");
    exit();
} else {
    $stmt->close();
    header("Location: customer.php?error=" . urlencode("Lỗi khi xóa khách hàng: " . $stmt->error));
    exit();
}

// Đóng kết nối
$conn->close();
?>