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

// Xử lý xóa hóa đơn
if (isset($_GET['id'])) {
    $order_id = $_GET['id'];

    // Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
    $conn->begin_transaction();

    try {
        // Xóa chi tiết hóa đơn trước
        $sql_delete_details = "DELETE FROM order_detail WHERE order_id = ?";
        $stmt_details = $conn->prepare($sql_delete_details);
        $stmt_details->bind_param('i', $order_id);
        $stmt_details->execute();
        $stmt_details->close();

        // Xóa hóa đơn
        $sql = "DELETE FROM `order` WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $order_id);

        if ($stmt->execute()) {
            $stmt->close();
            $conn->commit();
            header("Location: order.php?order_deleted=success");
            exit();
        } else {
            throw new Exception("Lỗi khi xóa hóa đơn: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: order.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: order.php?error=" . urlencode("Không tìm thấy ID hóa đơn."));
    exit();
}

$conn->close();
?>