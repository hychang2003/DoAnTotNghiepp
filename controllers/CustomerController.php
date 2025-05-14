<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error_log');

date_default_timezone_set('Asia/Ho_Chi_Minh');

error_log("Bắt đầu CustomerController.php. Session hiện tại: " . print_r($_SESSION, true));

include_once '../config/db_connect.php';
include_once '../models/CustomerModel.php';

if ($conn->connect_error) {
    error_log("Lỗi kết nối cơ sở dữ liệu trong CustomerController: " . $conn->connect_error);
    if (isset($_GET['action']) && $_GET['action'] === 'search') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Lỗi hệ thống: Không thể kết nối cơ sở dữ liệu.']);
        exit();
    }
    header("Location: ../index.php?error=" . urlencode("Lỗi kết nối cơ sở dữ liệu."));
    exit();
}
error_log("Kết nối cơ sở dữ liệu được xác nhận trong CustomerController.");

$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
try {
    $model = new CustomerModel($host, $username, $password, $shop_db);
    error_log("Khởi tạo CustomerModel thành công với shop_db=$shop_db.");
} catch (Exception $e) {
    error_log("Lỗi khởi tạo CustomerModel: " . $e->getMessage());
    if (isset($_GET['action']) && $_GET['action'] === 'search') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Lỗi hệ thống: Không thể khởi tạo mô hình dữ liệu.']);
        exit();
    }
    header("Location: ../index.php?error=" . urlencode("Lỗi khởi tạo mô hình dữ liệu."));
    exit();
}

$action = $_GET['action'] ?? '';
error_log("Hành động được yêu cầu: $action");

if ($action === 'search') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['error' => '', 'customers' => []];
    try {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            throw new Exception("Bạn cần đăng nhập để tìm kiếm khách hàng.");
        }
        $query = $_GET['query'] ?? '';
        error_log("Bắt đầu tìm kiếm khách hàng với query: " . $query);
        $response['customers'] = $model->searchCustomers($query);
        error_log("Tìm kiếm khách hàng hoàn tất, số khách hàng: " . count($response['customers']));
    } catch (Exception $e) {
        $response['error'] = "Lỗi khi tìm kiếm khách hàng: " . $e->getMessage();
        error_log("Lỗi tìm kiếm khách hàng: " . $e->getMessage());
    }
    $model->close();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

if ($action === 'delete') {
    $customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($customer_id > 0) {
        try {
            error_log("Xóa khách hàng ID: $customer_id trong $shop_db");
            $model->deleteCustomer($customer_id);
            header("Location: ../view/customer.php?customer_deleted=success");
        } catch (Exception $e) {
            error_log("Lỗi xóa khách hàng ID $customer_id: " . $e->getMessage());
            header("Location: ../view/customer.php?error=" . urlencode($e->getMessage()));
        }
    } else {
        error_log("Lỗi xóa khách hàng: ID không hợp lệ ($customer_id)");
        header("Location: ../view/customer.php?error=" . urlencode("ID khách hàng không hợp lệ."));
    }
    $model->close();
    exit();
}

if ($action === 'update') {
    $customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $customer = null;

    if ($customer_id > 0) {
        try {
            error_log("Lấy thông tin khách hàng ID: $customer_id để cập nhật trong $shop_db");
            $customer = $model->getCustomerById($customer_id);
            if (!$customer) {
                error_log("Không tìm thấy khách hàng ID: $customer_id");
                header("Location: ../view/customer.php?error=" . urlencode("Khách hàng không tồn tại."));
                exit();
            }
        } catch (Exception $e) {
            error_log("Lỗi lấy thông tin khách hàng ID $customer_id: " . $e->getMessage());
            header("Location: ../view/customer.php?error=" . urlencode("Lỗi khi lấy thông tin khách hàng."));
            exit();
        }
    } else {
        error_log("Lỗi cập nhật khách hàng: ID không hợp lệ ($customer_id)");
        header("Location: ../view/customer.php?error=" . urlencode("ID khách hàng không hợp lệ."));
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $errors = [];

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

        if (empty($errors)) {
            try {
                error_log("Cập nhật khách hàng ID $customer_id: name=$name, email=$email, phone=$phone_number, address=$address trong $shop_db");
                if ($model->updateCustomer($customer_id, $name, $email, $phone_number, $address)) {
                    header("Location: ../view/customer.php?customer_updated=success");
                } else {
                    error_log("Cập nhật khách hàng ID $customer_id không thành công: Không có thay đổi dữ liệu");
                    header("Location: ../view/update_customer_view.php?id=$customer_id&error=" . urlencode("Lỗi khi cập nhật khách hàng."));
                }
            } catch (Exception $e) {
                error_log("Lỗi cập nhật khách hàng ID $customer_id: " . $e->getMessage());
                header("Location: ../view/update_customer_view.php?id=$customer_id&error=" . urlencode($e->getMessage()));
            }
        } else {
            error_log("Lỗi validation khi cập nhật khách hàng ID $customer_id: " . implode(", ", $errors));
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header("Location: ../view/update_customer_view.php?id=$customer_id");
        }
    } else {
        include '../view/update_customer_view.php';
    }
    $model->close();
    exit();
}

if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        $errors = [];

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

        if (empty($errors)) {
            try {
                error_log("Thêm khách hàng: name=$name, phone=$phone_number, email=$email, address=$address trong $shop_db");
                if ($model->addCustomer($name, $phone_number, $email, $address)) {
                    header("Location: ../view/customer.php?customer_added=success");
                } else {
                    error_log("Thêm khách hàng không thành công: Không có thay đổi dữ liệu");
                    header("Location: ../view/add_customer_view.php?error=" . urlencode("Lỗi khi thêm khách hàng."));
                }
            } catch (Exception $e) {
                error_log("Lỗi thêm khách hàng: " . $e->getMessage());
                header("Location: ../view/add_customer_view.php?error=" . urlencode($e->getMessage()));
            }
        } else {
            error_log("Lỗi validation khi thêm khách hàng: " . implode(", ", $errors));
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header("Location: ../view/add_customer_view.php");
        }
    } else {
        include '../view/add_customer_view.php';
    }
    $model->close();
    exit();
}

if ($action === 'history') {
    $customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $customer = null;
    $history = [];

    if ($customer_id > 0) {
        try {
            error_log("Lấy lịch sử mua hàng khách hàng ID: $customer_id trong $shop_db");
            $customer = $model->getCustomerById($customer_id);
            if (!$customer) {
                error_log("Không tìm thấy khách hàng ID: $customer_id");
                header("Location: ../view/customer.php?error=" . urlencode("Khách hàng không tồn tại."));
                exit();
            }
            $history = $model->getCustomerHistory($customer_id);
        } catch (Exception $e) {
            error_log("Lỗi lấy lịch sử khách hàng ID $customer_id: " . $e->getMessage());
            header("Location: ../view/customer.php?error=" . urlencode("Lỗi khi lấy thông tin lịch sử mua hàng."));
        }
    } else {
        error_log("Lỗi xem lịch sử: ID không hợp lệ ($customer_id)");
        header("Location: ../view/customer.php?error=" . urlencode("ID khách hàng không hợp lệ."));
    }

    include '../view/customer_history_view.php';
    $model->close();
    exit();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Chuyển hướng đến login_view.php do chưa đăng nhập.");
    header("Location: ../login_view.php");
    exit();
}

// Nếu không có action cụ thể, chuyển hướng đến customer.php
header("Location: ../view/customer.php");
exit();
?>