<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập và quyền admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    error_log("Phiên đăng nhập không hợp lệ: " . print_r($_SESSION, true));
    header("Location: ../login_view.php");
    exit();
}

// Bao gồm file kết nối cơ sở dữ liệu và model
include_once '../config/db_connect.php';
include_once '../models/ShopModel.php';

// Gán giá trị mặc định cho $_SESSION['shop_db'] nếu chưa tồn tại
if (!isset($_SESSION['shop_db'])) {
    $_SESSION['shop_db'] = 'shop_1'; // Giá trị mặc định
}

// Khởi tạo Model
$model = new ShopModel($host, $username, $password, 'fashion_shop');

// Lấy danh sách các cơ sở
try {
    $shops = $model->getShops();
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách cơ sở: " . $e->getMessage());
    $shops = [];
}

// Xử lý chuyển đổi cơ sở
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_shop'])) {
    $new_shop_db = $_POST['shop_db'] ?? '';

    try {
        // Kiểm tra xem cơ sở dữ liệu có hợp lệ không
        if ($model->isValidShop($new_shop_db)) {
            // Làm mới session
            $_SESSION['shop_db'] = $new_shop_db;
            session_regenerate_id(true); // Tạo lại ID session để làm mới hoàn toàn

            // Xóa cache nếu có
            if (function_exists('apcu_clear_cache')) {
                apcu_clear_cache();
            }

            // Đóng kết nối
            $model->close();

            // Chuyển hướng để làm mới dữ liệu
            header("Location: ../view/add_order.php");
            exit();
        } else {
            $error = "Cơ sở dữ liệu không hợp lệ.";
        }
    } catch (Exception $e) {
        error_log("Lỗi khi chuyển đổi cơ sở: " . $e->getMessage());
        $error = "Lỗi khi chuyển đổi cơ sở: " . $e->getMessage();
    }
}

// Đóng kết nối
$model->close();

// Tải View
include '../view/switch_shop_view.php';
?>