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

// Kiểm tra ID sản phẩm
if (!isset($_GET['id'])) {
    header("Location: products_list.php?error=" . urlencode("Không tìm thấy ID sản phẩm."));
    exit();
}

$product_id = $_GET['id'];

// Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
$conn->begin_transaction();

try {
    // Xóa sản phẩm
    $sql = "DELETE FROM product WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    header("Location: products_list.php?product_deleted=success");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    header("Location: products_list.php?error=" . urlencode("Lỗi khi xóa sản phẩm: " . $e->getMessage()));
    exit();
}

// Đóng kết nối
$conn->close();
?>