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

// Kiểm tra ID chương trình khuyến mãi
if (!isset($_GET['id'])) {
    header("Location: flash_sale.php?error=" . urlencode("Không tìm thấy ID chương trình khuyến mãi."));
    exit();
}

$flash_sale_id = $_GET['id'];

// Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
$conn->begin_transaction();

try {
    // Xóa liên kết chương trình khuyến mãi khỏi sản phẩm
    $sql_update = "UPDATE product SET flash_sale_id = NULL WHERE flash_sale_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param('i', $flash_sale_id);
    $stmt_update->execute();
    $stmt_update->close();

    // Xóa chương trình khuyến mãi
    $sql_delete = "DELETE FROM flash_sale WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param('i', $flash_sale_id);
    $stmt_delete->execute();
    $stmt_delete->close();

    $conn->commit();
    header("Location: flash_sale.php?flash_sale_deleted=success");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    header("Location: flash_sale.php?error=" . urlencode("Lỗi khi xóa chương trình khuyến mãi: " . $e->getMessage()));
    exit();
}

// Đóng kết nối
$conn->close();
?>