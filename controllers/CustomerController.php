<?php
include_once '../config/session_check.php';
include_once '../config/db_connect.php';
include_once '../models/CustomerModel.php';

// Debug session và thời gian xử lý
$start_time = microtime(true);
error_log("CustomerController.php - Session ID: " . session_id());
error_log("CustomerController.php - Logged in: " . (isset($_SESSION['loggedin']) ? 'true' : 'false'));

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khởi tạo các biến mặc định
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$errors = [];
$success = '';
$role = $_SESSION['role'] ?? '';
$session_username = $_SESSION['username'] ?? 'Khách';

// Lấy tên cửa hàng từ fashion_shopp
$conn_common = new mysqli($host, $username, $password, 'fashion_shopp');
if ($conn_common->connect_error) {
    error_log("Lỗi kết nối đến fashion_shopp: " . $conn_common->connect_error);
    $shop_name = $shop_db;
} else {
    $conn_common->set_charset("utf8mb4");
    $sql = "SELECT name FROM shop WHERE db_name = ?";
    $stmt = $conn_common->prepare($sql);
    $stmt->bind_param('s', $shop_db);
    $stmt->execute();
    $result = $stmt->get_result();
    $shop_name = ($result->num_rows > 0) ? $result->fetch_assoc()['name'] : $shop_db;
    $stmt->close();
    $conn_common->close();
}

// Khởi tạo Model
$model = new CustomerModel($host, $username, $password, $shop_db);

// Debug
error_log("CustomerController.php - Action: " . ($_GET['action'] ?? 'none') . ", shop_db: $shop_db, shop_name: $shop_name");

// Xử lý các hành động
$action = $_GET['action'] ?? '';

if ($action === 'delete') {
    // Xóa khách hàng
    $customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($customer_id > 0) {
        try {
            $model->deleteCustomer($customer_id);
            header("Location: ../view/customer.php?customer_deleted=success");
        } catch (Exception $e) {
            error_log("Lỗi xóa khách hàng ID $customer_id từ $shop_db: " . $e->getMessage());
            header("Location: ../view/customer.php?error=" . urlencode($e->getMessage()));
        }
    } else {
        error_log("ID khách hàng không hợp lệ: $customer_id");
        header("Location: ../view/customer.php?error=" . urlencode("ID khách hàng không hợp lệ."));
    }
    $model->close();
    exit();
} elseif ($action === 'update') {
    // Cập nhật khách hàng
    $customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $customer = null;

    if ($customer_id > 0) {
        try {
            $customer = $model->getCustomerById($customer_id);
            if (!$customer) {
                $errors[] = "Khách hàng không tồn tại.";
            }
        } catch (Exception $e) {
            error_log("Lỗi lấy khách hàng ID $customer_id từ $shop_db: " . $e->getMessage());
            $errors[] = "Lỗi khi lấy thông tin khách hàng.";
        }
    } else {
        $errors[] = "ID khách hàng không hợp lệ.";
    }

    // Xử lý biểu mẫu cập nhật
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        // Kiểm tra dữ liệu đầu vào
        if (empty($name)) {
            $errors[] = "Tên khách hàng không được để trống.";
        } elseif (strlen($name) > 100) {
            $errors[] = "Tên khách hàng không được dài quá 100 ký tự.";
        }
        if (empty($phone_number)) {
            $errors[] = "Số điện thoại không được để trống.";
        } elseif (!preg_match("/^[0-9]{10,11}$/", $phone_number)) {
            $errors[] = "Số điện thoại phải có 10-11 số.";
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email không hợp lệ.";
        }

        // Cập nhật nếu không có lỗi
        if (empty($errors)) {
            try {
                if ($model->updateCustomer($customer_id, $name, $email, $phone_number, $address)) {
                    header("Location: ../view/customer.php?customer_updated=success");
                } else {
                    $errors[] = "Lỗi khi cập nhật khách hàng.";
                }
            } catch (Exception $e) {
                error_log("Lỗi cập nhật khách hàng ID $customer_id từ $shop_db: " . $e->getMessage());
                $errors[] = $e->getMessage();
            }
        }
    }

    // Tải View cập nhật
    include '../view/update_customer_view.php';
    $model->close();
    exit();
} elseif ($action === 'add') {
    // Xử lý thêm khách hàng
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        // Kiểm tra dữ liệu đầu vào
        if (empty($name)) {
            $errors[] = "Tên khách hàng không được để trống.";
        } elseif (strlen($name) > 100) {
            $errors[] = "Tên khách hàng không được dài quá 100 ký tự.";
        }
        if (empty($phone_number)) {
            $errors[] = "Số điện thoại không được để trống.";
        } elseif (!preg_match("/^[0-9]{10,11}$/", $phone_number)) {
            $errors[] = "Số điện thoại phải có 10-11 số.";
        }
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email không hợp lệ.";
        }

        // Thêm khách hàng nếu không có lỗi
        if (empty($errors)) {
            try {
                if ($model->addCustomer($name, $phone_number, $email, $address)) {
                    header("Location: ../view/customer.php?customer_added=success");
                } else {
                    $errors[] = "Lỗi khi thêm khách hàng.";
                }
            } catch (Exception $e) {
                error_log("Lỗi thêm khách hàng vào $shop_db: " . $e->getMessage());
                $errors[] = $e->getMessage();
            }
        }
    }

    // Tải View thêm khách hàng
    include '../view/add_customer_view.php';
    $model->close();
    exit();
} elseif ($action === 'history') {
    // Xem lịch sử mua hàng
    $customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $customer = null;
    $history = [];

    if ($customer_id > 0) {
        try {
            $customer = $model->getCustomerById($customer_id);
            if (!$customer) {
                $errors[] = "Khách hàng không tồn tại.";
            } else {
                $history = $model->getCustomerHistory($customer_id);
            }
        } catch (Exception $e) {
            error_log("Lỗi lấy lịch sử khách hàng ID $customer_id từ $shop_db: " . $e->getMessage());
            $errors[] = "Lỗi khi lấy thông tin lịch sử mua hàng.";
        }
    } else {
        $errors[] = "ID khách hàng không hợp lệ.";
    }

    // Tải View lịch sử
    include '../view/customer_history_view.php';
    $model->close();
    exit();
} else {
    // Lấy danh sách khách hàng
    try {
        $customers = $model->getCustomers();
    } catch (Exception $e) {
        error_log("Lỗi khi lấy danh sách khách hàng từ $shop_db: " . $e->getMessage());
        $customers = [];
    }

    // Tải View danh sách khách hàng
    include '../view/customer.php';
    $model->close();
}

// Debug thời gian xử lý
$end_time = microtime(true);
error_log("CustomerController.php - Thời gian xử lý: " . ($end_time - $start_time) . " giây");
?>