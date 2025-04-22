<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ cho PHP
date_default_timezone_set('Asia/Ho_Chi_Minh');

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

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại
$conn = getConnection($host, $username, $password, $shop_db);

// Lấy ID danh mục từ URL
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Xóa danh mục
if ($category_id > 0) {
    // Kiểm tra xem danh mục có sản phẩm liên quan không
    $sql_check_products = "SELECT COUNT(*) as product_count FROM `$shop_db`.product WHERE category_id = ?";
    $stmt_check_products = $conn->prepare($sql_check_products);
    if ($stmt_check_products === false) {
        die("Lỗi chuẩn bị truy vấn kiểm tra sản phẩm: " . $conn->error);
    }
    $stmt_check_products->bind_param('i', $category_id);
    $stmt_check_products->execute();
    $result_check_products = $stmt_check_products->get_result();
    $product_count = $result_check_products->fetch_assoc()['product_count'] ?? 0;
    $stmt_check_products->close();

    if ($product_count > 0) {
        // Nếu danh mục có sản phẩm, không cho phép xóa
        header("Location: product_category.php?error=" . urlencode("Không thể xóa danh mục vì vẫn còn $product_count sản phẩm thuộc danh mục này."));
        exit();
    }

    // Tiến hành xóa danh mục
    $sql_delete = "DELETE FROM `$shop_db`.category WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    if ($stmt_delete === false) {
        die("Lỗi chuẩn bị truy vấn xóa: " . $conn->error);
    }
    $stmt_delete->bind_param('i', $category_id);
    if ($stmt_delete->execute()) {
        // Chuyển hướng về trang danh sách danh mục với thông báo thành công
        header("Location: product_category.php?success=" . urlencode("Xóa danh mục thành công."));
        exit();
    } else {
        header("Location: product_category.php?error=" . urlencode("Lỗi khi xóa danh mục: " . $stmt_delete->error));
        exit();
    }
    $stmt_delete->close();
} else {
    header("Location: product_category.php?error=" . urlencode("ID danh mục không hợp lệ."));
    exit();
}

// Đóng kết nối
$conn->close();
?>