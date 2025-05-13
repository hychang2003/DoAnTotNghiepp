<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Phiên đăng nhập không hợp lệ: " . print_r($_SESSION, true));
    header("Location: ../login_view.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error_log');

date_default_timezone_set('Asia/Ho_Chi_Minh');

include_once '../config/db_connect.php';
include_once '../models/InventoryModel.php';

$shop_db = $_SESSION['shop_db'] ?? 'shop_11';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("session_username được gán: " . $session_username);

$model = new InventoryModel($host, $username, $password, $shop_db);

$action = $_GET['action'] ?? '';

if ($action === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['error' => '', 'inventory' => []];
    try {
        $query = $_GET['query'] ?? '';
        $shop_id = $model->getShopId('fashion_shopp', $shop_db);
        error_log("Bắt đầu tìm kiếm tồn kho với query: " . $query . ", shop_id: " . $shop_id);
        $response['inventory'] = $model->searchInventory($shop_id, $query);
        error_log("Tìm kiếm tồn kho hoàn tất, số sản phẩm: " . count($response['inventory']));
    } catch (Exception $e) {
        $response['error'] = "Lỗi khi tìm kiếm tồn kho: " . $e->getMessage();
        error_log("Lỗi tìm kiếm tồn kho: " . $e->getMessage());
    }
    $model->close();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

try {
    $shop_name = $model->getShopName('fashion_shopp', $shop_db);
} catch (Exception $e) {
    error_log("Lỗi khi lấy tên cơ sở: " . $e->getMessage());
    $shop_name = $shop_db;
}

try {
    $inventory = $model->getInventory();
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách tồn kho: " . $e->getMessage());
    $inventory = [];
}

$image_base_url = "/assets/images/";
$image_default_url = "/datn/assets/images/default.jpg";

$model->close();

include '../view/inventory_stock_view.php';
?>