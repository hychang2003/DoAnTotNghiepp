<?php
header('Content-Type: application/json');

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu
function getConnection($host, $username, $password, $dbname) {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        return null;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

$response = ['error' => '', 'products' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_name'])) {
    $db_name = $_POST['db_name'];

    // Kết nối đến cơ sở dữ liệu của shop được chọn
    $conn = getConnection($host, $username, $password, $db_name);
    if ($conn === null) {
        $response['error'] = "Không thể kết nối đến cơ sở dữ liệu của shop.";
        echo json_encode($response);
        exit();
    }

    // Lấy danh sách sản phẩm cùng với tồn kho
    $sql = "SELECT p.id, p.name, p.price, i.quantity 
            FROM `$db_name`.product p 
            LEFT JOIN `$db_name`.inventory i ON p.id = i.product_id";
    $result = $conn->query($sql);

    if ($result === false) {
        $response['error'] = "Lỗi truy vấn sản phẩm: " . $conn->error;
    } else {
        while ($row = $result->fetch_assoc()) {
            $response['products'][] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name']),
                'price' => floatval($row['price']),
                'quantity' => intval($row['quantity'] ?? 0)
            ];
        }
    }

    $conn->close();
} else {
    $response['error'] = "Yêu cầu không hợp lệ.";
}

echo json_encode($response);
?>