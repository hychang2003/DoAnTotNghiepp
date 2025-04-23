<?php
session_start();
include '../config/db_connect.php';
include '../models/CustomerModel.php';

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khởi tạo các biến mặc định
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';
$shop_name = $shop_db; // Giá trị mặc định nếu không lấy được tên cửa hàng
$errors = [];
$role = isset($_SESSION['role']) ? $_SESSION['role'] : ''; // Giá trị mặc định cho role
$session_username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Khách'; // Tên người dùng ứng dụng

// Khởi tạo Model
$model = new CustomerModel($host, $username, $password, $shop_db);

// Lấy tên cửa hàng từ bảng shop
try {
    $conn_main = new mysqli($host, $username, $password, 'fashion_shop');
    if ($conn_main->connect_error) {
        throw new Exception("Lỗi kết nối đến cơ sở dữ liệu chính: " . $conn_main->connect_error);
    }
    $conn_main->set_charset("utf8mb4");

    $sql = "SELECT name FROM shop WHERE db_name = ?";
    $stmt = $conn_main->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Lỗi chuẩn bị truy vấn name: " . $conn_main->error);
    }
    $stmt->bind_param('s', $shop_db);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $shop_name = $row['name'] ?? $shop_db;
    if (!$row) {
        error_log("Không tìm thấy name cho db_name = '$shop_db' trong bảng shop.");
    }
    $stmt->close();
    $conn_main->close();
} catch (Exception $e) {
    error_log("Lỗi khi lấy tên cửa hàng: " . $e->getMessage());
    $errors[] = "Không thể lấy tên cửa hàng.";
}

// Xử lý thêm khách hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = trim($_POST['name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Kiểm tra dữ liệu đầu vào
    if (empty($name)) {
        $errors[] = "Tên khách hàng là bắt buộc.";
    }
    if (empty($phone_number)) {
        $errors[] = "Số điện thoại là bắt buộc.";
    }
    if (strlen($name) > 100) {
        $errors[] = "Tên khách hàng không được dài quá 100 ký tự.";
    }
    if (!preg_match('/^[0-9]{10,15}$/', $phone_number)) {
        $errors[] = "Số điện thoại phải là số và có từ 10 đến 15 chữ số.";
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ.";
    }
    if (strlen($address) > 255) {
        $errors[] = "Địa chỉ không được dài quá 255 ký tự.";
    }

    // Ghi log dữ liệu đầu vào
    error_log("Dữ liệu thêm khách hàng: name=$name, phone_number=$phone_number, email=$email, address=$address");

    // Thêm khách hàng nếu không có lỗi
    if (empty($errors)) {
        try {
            if ($model->addCustomer($name, $phone_number, $email, $address)) {
                header("Location: ../view/customer.php?customer_added=success");
                exit();
            } else {
                $errors[] = "Lỗi khi thêm khách hàng.";
            }
        } catch (Exception $e) {
            error_log("Lỗi thêm khách hàng: " . $e->getMessage());
            $errors[] = "Lỗi khi thêm khách hàng: " . $e->getMessage();
        }
    }
}

// Đóng kết nối
$model->close();

// Tải View
include '../view/add_customer_view.php';
?>