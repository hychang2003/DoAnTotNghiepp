<?php
session_start();
include '../config/db_connect.php';
include '../models/EmployeeModel.php';

// Kiểm tra trạng thái đăng nhập và quyền admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    error_log("Phiên đăng nhập không hợp lệ: " . print_r($_SESSION, true));
    header("Location: ../login.php");
    exit();
}

// Gỡ lỗi: Kiểm tra $_SESSION
error_log("SESSION in EmployeeController: " . print_r($_SESSION, true));

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khởi tạo các biến mặc định
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';
$shop_name = $shop_db; // Giá trị mặc định nếu không lấy được tên cửa hàng
$errors = [];
$role = $_SESSION['role'] ?? ''; // Giá trị mặc định cho role
$session_username = $_SESSION['username'] ?? 'Khách'; // Tên người dùng ứng dụng
error_log("session_username được gán: " . $session_username);

// Khởi tạo Model với cơ sở dữ liệu chính (fashion_shop)
$model = new EmployeeModel($host, $username, $password, 'fashion_shop');

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

// Xử lý thêm nhân viên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    error_log("Nhận yêu cầu POST thêm nhân viên tại EmployeeController.php");

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'employee';

    // Kiểm tra dữ liệu đầu vào
    if (empty($username)) {
        $errors[] = "Tên đăng nhập là bắt buộc.";
    }
    if (empty($password)) {
        $errors[] = "Mật khẩu là bắt buộc.";
    }
    if (empty($name)) {
        $errors[] = "Họ và tên là bắt buộc.";
    }
    if (empty($email)) {
        $errors[] = "Email là bắt buộc.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Mật khẩu phải có ít nhất 6 ký tự.";
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ.";
    }
    if ($phone && !preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors[] = "Số điện thoại phải là số và có từ 10 đến 15 chữ số.";
    }
    if (!in_array($role, ['employee', 'admin'])) {
        $errors[] = "Vai trò không hợp lệ.";
    }

    // Ghi log dữ liệu đầu vào
    error_log("Dữ liệu thêm nhân viên: username=$username, name=$name, email=$email, phone=$phone, role=$role");

    // Thêm nhân viên nếu không có lỗi
    if (empty($errors)) {
        try {
            // Kiểm tra username và email
            if ($model->isUsernameExists($username)) {
                $errors[] = "Tên đăng nhập đã tồn tại.";
            } elseif ($model->isEmailExists($email)) {
                $errors[] = "Email đã tồn tại.";
            } else {
                // Mã hóa mật khẩu
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                if ($model->addEmployee($name, $phone, $email, $username, $hashed_password, $role)) {
                    error_log("Thêm nhân viên thành công, chuyển hướng đến employee.php");
                    header("Location: ../view/employee.php?employee_added=success");
                    exit();
                } else {
                    $errors[] = "Lỗi khi thêm nhân viên.";
                }
            }
        } catch (Exception $e) {
            error_log("Lỗi thêm nhân viên: " . $e->getMessage());
            $errors[] = "Lỗi khi thêm nhân viên: " . $e->getMessage();
        }
    }
}

// Đóng kết nối
$model->close();

// Tải View
include '../view/add_employee_view.php';
?>