<?php
header('Content-Type: application/json');

include '../config/db_connect.php';

function getShopConnection($host, $username, $password, $shop_db) {
    $conn = new mysqli($host, $username, $password, $shop_db);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    return $conn;
}

session_start();
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';
$conn = getShopConnection($host, $username, $password, $shop_db);

$product_id = $_GET['product_id'] ?? 0;

$sql = "SELECT p.flash_sale_id, f.discount, f.start_date, f.end_date, f.status 
        FROM product p 
        LEFT JOIN flash_sale f ON p.flash_sale_id = f.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

$response = ['discount' => 0, 'is_active' => false];

if ($product && $product['flash_sale_id'] && $product['status'] == 1) {
    $current_date = date('Y-m-d H:i:s');
    if ($current_date >= $product['start_date'] && $current_date <= $product['end_date']) {
        $response['discount'] = $product['discount'];
        $response['is_active'] = true;
    }
}

echo json_encode($response);

$stmt->close();
$conn->close();
?>