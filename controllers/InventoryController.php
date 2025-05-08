<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Phiên đăng nhập không hợp lệ: " . print_r($_SESSION, true));
    header("Location: ../login_view.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bao gồm file kết nối cơ sở dữ liệu và model
include_once '../config/db_connect.php';
include_once '../models/InventoryModel.php';

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("session_username được gán: " . $session_username);

// Khởi tạo Model
$model = new InventoryModel($host, $username, $password, $shop_db);

// Lấy tab hiện tại từ query string
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Lấy tên cơ sở
try {
    $shop_name = $model->getShopName('fashion_shop', $shop_db);
} catch (Exception $e) {
    error_log("Lỗi khi lấy tên cơ sở: " . $e->getMessage());
    $shop_name = $shop_db;
}

// Lấy danh sách sản phẩm và tồn kho
try {
    $inventory = $model->getInventory();
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách tồn kho: " . $e->getMessage());
    $inventory = [];
}

// Định nghĩa đường dẫn gốc của thư mục ảnh
$image_base_url = "/assets/images/";
$image_default_url = "/datn/assets/images/default.jpg";

// Đóng kết nối
$model->close();

// Tải View
include '../view/inventory_stock_view.php';
?>