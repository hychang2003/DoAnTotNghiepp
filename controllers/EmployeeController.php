<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

error_log("Bắt đầu EmployeeController.php. Session hiện tại: " . print_r($_SESSION, true));

// Kết nối cơ sở dữ liệu và model
include_once '../config/db_connect.php';
include_once '../models/EmployeeModel.php';

// Kiểm tra kết nối cơ sở dữ liệu
if ($conn->connect_error) {
    error_log("Lỗi kết nối cơ sở dữ liệu trong EmployeeController: " . $conn->connect_error);
    if (isset($_GET['action']) && in_array($_GET['action'], ['record_attendance', 'check_attendance'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Lỗi hệ thống: Không thể kết nối cơ sở dữ liệu.']);
        exit();
    }
    header("Location: ../index.php?error=" . urlencode("Lỗi kết nối cơ sở dữ liệu."));
    exit();
}
error_log("Kết nối cơ sở dữ liệu được xác nhận trong EmployeeController.");

// Khởi tạo Model
try {
    $model = new EmployeeModel($host, $username, $password, 'fashion_shopp');
    error_log("Khởi tạo EmployeeModel thành công.");
} catch (Exception $e) {
    error_log("Lỗi khởi tạo EmployeeModel: " . $e->getMessage());
    if (isset($_GET['action']) && in_array($_GET['action'], ['record_attendance', 'check_attendance'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Lỗi hệ thống: Không thể khởi tạo mô hình dữ liệu.']);
        exit();
    }
    header("Location: ../index.php?error=" . urlencode("Lỗi khởi tạo mô hình dữ liệu."));
    exit();
}

// Xử lý các hành động
$action = $_GET['action'] ?? '';
error_log("Hành động được yêu cầu: $action");

if ($action === 'record_attendance') {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    error_log("Xử lý chấm công. Session: " . print_r($_SESSION, true));
    try {
        // Kiểm tra session
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            error_log("Lỗi chấm công: Phiên đăng nhập không hợp lệ.");
            echo json_encode(['error' => 'Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập lại.']);
            exit();
        }
        error_log("Phiên đăng nhập hợp lệ.");

        if (!isset($_SESSION['user_id'])) {
            error_log("Lỗi chấm công: Biến session user_id không được thiết lập.");
            echo json_encode(['error' => 'ID nhân viên không hợp lệ: Biến session user_id không tồn tại.']);
            exit();
        }
        $employee_id = (int)$_SESSION['user_id'];
        error_log("Employee ID từ session: $employee_id");

        if ($employee_id <= 0) {
            error_log("Lỗi chấm công: user_id không hợp lệ (giá trị: $employee_id).");
            echo json_encode(['error' => "ID nhân viên không hợp lệ: Giá trị user_id ($employee_id) không phải số dương."]);
            exit();
        }

        // Kiểm tra người dùng trong bảng users
        error_log("Kiểm tra người dùng trong bảng users với ID: $employee_id");
        $sql = "SELECT id, role, username FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chấm công: Không thể chuẩn bị truy vấn kiểm tra user: " . $conn->error);
            echo json_encode(['error' => 'Lỗi hệ thống: Không thể kiểm tra thông tin người dùng.']);
            exit();
        }
        $stmt->bind_param('i', $employee_id);
        if (!$stmt->execute()) {
            error_log("Lỗi chấm công: Không thể thực thi truy vấn kiểm tra user: " . $stmt->error);
            echo json_encode(['error' => 'Lỗi hệ thống: Không thể thực thi truy vấn kiểm tra người dùng.']);
            $stmt->close();
            exit();
        }
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        error_log("Kết quả kiểm tra người dùng: " . print_r($user, true));

        if (!$user) {
            error_log("Lỗi chấm công: Không tìm thấy người dùng với ID: $employee_id trong bảng users.");
            echo json_encode(['error' => "Người dùng không tồn tại: Không tìm thấy ID $employee_id trong cơ sở dữ liệu."]);
            exit();
        }
        if (!in_array($user['role'], ['employee', 'admin'])) {
            error_log("Lỗi chấm công: Người dùng ID $employee_id có vai trò không hợp lệ: " . $user['role']);
            echo json_encode(['error' => "Bạn không có quyền chấm công: Vai trò {$user['role']} không được phép."]);
            exit();
        }

        error_log("Gọi recordAttendance cho nhân viên ID: $employee_id, username: {$user['username']}");
        $result = $model->recordAttendance($employee_id);
        error_log("Kết quả recordAttendance: " . ($result ? "Thành công" : "Thất bại"));
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Lỗi chấm công nhân viên ID $employee_id: " . $e->getMessage());
        echo json_encode(['error' => 'Lỗi khi ghi chấm công: ' . $e->getMessage()]);
    }
    exit();
}

if ($action === 'check_attendance') {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    error_log("Xử lý kiểm tra chấm công. Session: " . print_r($_SESSION, true));
    try {
        if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
            error_log("Lỗi kiểm tra chấm công: Phiên đăng nhập không hợp lệ.");
            echo json_encode(['error' => 'Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập lại.']);
            exit();
        }
        if (!isset($_SESSION['user_id'])) {
            error_log("Lỗi kiểm tra chấm công: Biến session user_id không được thiết lập.");
            echo json_encode(['error' => 'ID nhân viên không hợp lệ: Biến session user_id không tồn tại.']);
            exit();
        }
        $employee_id = (int)$_SESSION['user_id'];
        if ($employee_id <= 0) {
            error_log("Lỗi kiểm tra chấm công: user_id không hợp lệ (giá trị: $employee_id).");
            echo json_encode(['error' => "ID nhân viên không hợp lệ: Giá trị user_id ($employee_id) không phải số dương."]);
            exit();
        }
        error_log("Kiểm tra người dùng trong bảng users với ID: $employee_id");
        $sql = "SELECT id, role FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi kiểm tra chấm công: Không thể chuẩn bị truy vấn kiểm tra user: " . $conn->error);
            echo json_encode(['error' => 'Lỗi hệ thống: Không thể kiểm tra thông tin người dùng.']);
            exit();
        }
        $stmt->bind_param('i', $employee_id);
        if (!$stmt->execute()) {
            error_log("Lỗi kiểm tra chấm công: Không thể thực thi truy vấn kiểm tra user: " . $stmt->error);
            echo json_encode(['error' => 'Lỗi hệ thống: Không thể thực thi truy vấn kiểm tra người dùng.']);
            $stmt->close();
            exit();
        }
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        if (!$user) {
            error_log("Lỗi kiểm tra chấm công: Không tìm thấy người dùng với ID: $employee_id trong bảng users.");
            echo json_encode(['error' => "Người dùng không tồn tại: Không tìm thấy ID $employee_id trong cơ sở dữ liệu."]);
            exit();
        }
        if (!in_array($user['role'], ['employee', 'admin'])) {
            error_log("Lỗi kiểm tra chấm công: Người dùng ID $employee_id có vai trò không hợp lệ: " . $user['role']);
            echo json_encode(['error' => "Bạn không có quyền kiểm tra chấm công: Vai trò {$user['role']} không được phép."]);
            exit();
        }
        error_log("Gọi hasAttendedToday cho nhân viên ID: $employee_id");
        $hasAttended = $model->hasAttendedToday($employee_id);
        error_log("Kiểm tra chấm công cho ID $employee_id: " . ($hasAttended ? "Đã chấm công hôm nay" : "Chưa chấm công hôm nay"));
        echo json_encode(['hasAttended' => $hasAttended]);
    } catch (Exception $e) {
        error_log("Lỗi kiểm tra chấm công nhân viên ID $employee_id: " . $e->getMessage());
        echo json_encode(['error' => 'Lỗi khi kiểm tra chấm công: ' . $e->getMessage()]);
    }
    exit();
}

// Các hành động khác (giữ nguyên)
if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'employee';
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');

        $errors = [];

        if (empty($username)) {
            $errors[] = "Tên đăng nhập không được để trống.";
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = "Tên đăng nhập phải từ 3 đến 50 ký tự.";
        }
        if (empty($password)) {
            $errors[] = "Mật khẩu không được để trống.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Mật khẩu phải có ít nhất 6 ký tự.";
        }
        if (empty($name)) {
            $errors[] = "Họ và tên không được để trống.";
        } elseif (strlen($name) > 100) {
            $errors[] = "Họ và tên không được dài quá 100 ký tự.";
        }
        if (empty($email)) {
            $errors[] = "Email không được để trống.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email không hợp lệ.";
        }
        if (empty($phone_number)) {
            $errors[] = "Phải nhập số điện thoại.";
        } elseif (!preg_match('/^[0-9]{10,15}$/', $phone_number)) {
            $errors[] = "Số điện thoại không hợp lệ (phải có 10-15 chữ số).";
        }
        if (!in_array($role, ['admin', 'employee'])) {
            $errors[] = "Vai trò không hợp lệ.";
        }

        if (empty($errors)) {
            try {
                error_log("Thêm nhân viên: username=$username, role=$role");
                $model->addEmployee($username, $password, $role, $name, $email, $phone_number);
                header("Location: ../view/employee.php?add_success=true");
                exit();
            } catch (Exception $e) {
                error_log("Lỗi thêm nhân viên: " . $e->getMessage());
                header("Location: ../view/add_employee_view.php?error=" . urlencode($e->getMessage()));
                exit();
            }
        } else {
            error_log("Lỗi validation khi thêm nhân viên: " . implode(", ", $errors));
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header("Location: ../view/add_employee_view.php");
            exit();
        }
    }
}

if ($action === 'delete') {
    $employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($employee_id > 0) {
        try {
            error_log("Xóa nhân viên ID: $employee_id");
            $model->deleteEmployee($employee_id);
            header("Location: ../view/employee.php?delete_success=true");
            exit();
        } catch (Exception $e) {
            error_log("Lỗi xóa nhân viên ID $employee_id: " . $e->getMessage());
            header("Location: ../view/employee.php?error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        error_log("Lỗi xóa nhân viên: ID không hợp lệ ($employee_id)");
        header("Location: ../view/employee.php?error=" . urlencode("ID nhân viên không hợp lệ."));
        exit();
    }
}

if ($action === 'update') {
    $employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($employee_id > 0) {
        try {
            error_log("Lấy thông tin nhân viên ID: $employee_id để cập nhật");
            $employee = $model->getEmployeeById($employee_id);
            if (!$employee) {
                error_log("Không tìm thấy nhân viên ID: $employee_id");
                header("Location: ../view/employee.php?error=" . urlencode("Không tìm thấy nhân viên."));
                exit();
            }
            include '../view/update_employee_view.php';
            exit();
        } catch (Exception $e) {
            error_log("Lỗi lấy thông tin nhân viên ID $employee_id: " . $e->getMessage());
            header("Location: ../view/employee.php?error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        error_log("Lỗi cập nhật nhân viên: ID không hợp lệ ($employee_id)");
        header("Location: ../view/employee.php?error=" . urlencode("ID nhân viên không hợp lệ."));
        exit();
    }
}

if ($action === 'save_update') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $employee_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $role = trim($_POST['role'] ?? '');
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $password = !empty($_POST['password']) ? trim($_POST['password']) : null;

        $errors = [];

        if ($employee_id <= 0) {
            $errors[] = "ID nhân viên không hợp lệ.";
        }
        if (empty($name)) {
            $errors[] = "Họ và tên không được để trống.";
        } elseif (strlen($name) > 100) {
            $errors[] = "Họ và tên không được dài quá 100 ký tự.";
        }
        if (empty($email)) {
            $errors[] = "Email không được để trống.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email không hợp lệ.";
        }
        if (empty($phone_number)) {
            $errors[] = "Phải nhập số điện thoại.";
        } elseif (!preg_match('/^[0-9]{10,15}$/', $phone_number)) {
            $errors[] = "Số điện thoại không hợp lệ (phải có 10-15 chữ số).";
        }
        if (!in_array($role, ['admin', 'employee'])) {
            $errors[] = "Vai trò không hợp lệ.";
        }
        if ($password && strlen($password) < 6) {
            $errors[] = "Mật khẩu phải có ít nhất 6 ký tự.";
        }

        if (empty($errors)) {
            try {
                error_log("Cập nhật nhân viên ID $employee_id: role=$role, name=$name, email=$email, phone=$phone_number");
                $model->updateEmployee($employee_id, $role, $name, $email, $phone_number, $password);
                header("Location: ../view/employee.php?update_success=true");
                exit();
            } catch (Exception $e) {
                error_log("Lỗi cập nhật nhân viên ID $employee_id: " . $e->getMessage());
                header("Location: ../view/update_employee_view.php?id=$employee_id&error=" . urlencode($e->getMessage()));
                exit();
            }
        } else {
            error_log("Lỗi validation khi cập nhật nhân viên ID $employee_id: " . implode(", ", $errors));
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header("Location: ../view/update_employee_view.php?id=$employee_id");
            exit();
        }
    }
}

if ($action === 'view_salary') {
    $employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($employee_id > 0) {
        try {
            error_log("Lấy thông tin lương nhân viên ID: $employee_id");
            $employee = $model->getEmployeeById($employee_id);
            if (!$employee) {
                error_log("Không tìm thấy nhân viên ID: $employee_id");
                header("Location: ../view/employee.php?error=" . urlencode("Không tìm thấy nhân viên."));
                exit();
            }
            $month = date('Y-m');
            $salary = $model->getSalaryByEmployeeId($employee_id, $month);
            include '../view/employee_salary_view.php';
            exit();
        } catch (Exception $e) {
            error_log("Lỗi lấy thông tin lương nhân viên ID $employee_id: " . $e->getMessage());
            header("Location: ../view/employee.php?error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        error_log("Lỗi xem lương: ID không hợp lệ ($employee_id)");
        header("Location: ../view/employee.php?error=" . urlencode("ID nhân viên không hợp lệ."));
        exit();
    }
}

if ($action === 'update_salary') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("Dữ liệu POST nhận được: " . print_r($_POST, true));
        $employee_id = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
        $month = trim($_POST['month'] ?? '');
        $work_days = isset($_POST['work_days']) ? (float)$_POST['work_days'] : 0.0;
        $salary_per_day = isset($_POST['salary_per_day']) ? (float)$_POST['salary_per_day'] : 0.00;

        $errors = [];

        if ($employee_id <= 0) {
            $errors[] = "ID nhân viên không hợp lệ.";
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $errors[] = "Tháng không hợp lệ.";
        }
        if ($work_days < 0 || $work_days > 31) {
            $errors[] = "Số ngày công không hợp lệ (0-31).";
        }
        if ($salary_per_day < 0) {
            $errors[] = "Lương một ngày không được âm.";
        }

        if (empty($errors)) {
            try {
                $total_salary = $work_days * $salary_per_day;
                error_log("Cập nhật lương nhân viên ID $employee_id, tháng $month: work_days=$work_days, salary_per_day=$salary_per_day, total_salary=$total_salary");
                $affected_rows = $model->updateSalary($employee_id, $month, $work_days, $salary_per_day, $total_salary);
                if ($affected_rows > 0) {
                    error_log("Cập nhật lương thành công cho ID $employee_id, tháng $month. Affected rows: $affected_rows");
                    header("Location: ../controllers/EmployeeController.php?action=view_salary&id=$employee_id&success=" . urlencode("Cập nhật lương thành công."));
                } else {
                    error_log("Cập nhật lương không thành công cho ID $employee_id, tháng $month: Không có bản ghi nào được cập nhật.");
                    header("Location: ../controllers/EmployeeController.php?action=view_salary&id=$employee_id&error=" . urlencode("Không thể cập nhật lương: Không có thay đổi trong dữ liệu."));
                }
                exit();
            } catch (Exception $e) {
                error_log("Lỗi cập nhật lương nhân viên ID $employee_id: " . $e->getMessage());
                header("Location: ../controllers/EmployeeController.php?action=view_salary&id=$employee_id&error=" . urlencode($e->getMessage()));
                exit();
            }
        } else {
            error_log("Lỗi validation khi cập nhật lương ID $employee_id: " . implode(", ", $errors));
            header("Location: ../controllers/EmployeeController.php?action=view_salary&id=$employee_id&error=" . urlencode(implode(", ", $errors)));
            exit();
        }
    }
}

if ($action === 'update_payment_status') {
    $employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $month = trim($_GET['month'] ?? '');
    $status = trim($_GET['status'] ?? '');

    if ($employee_id > 0 && in_array($status, ['paid', 'unpaid']) && preg_match('/^\d{4}-\d{2}$/', $month)) {
        try {
            error_log("Cập nhật trạng thái lương ID $employee_id, tháng $month, status=$status");
            $model->updatePaymentStatus($employee_id, $month, $status);
            header("Location: ../controllers/EmployeeController.php?action=view_salary&id=$employee_id&success=" . urlencode("Cập nhật trạng thái lương thành công."));
            exit();
        } catch (Exception $e) {
            error_log("Lỗi cập nhật trạng thái lương nhân viên ID $employee_id: " . $e->getMessage());
            header("Location: ../controllers/EmployeeController.php?action=view_salary&id=$employee_id&error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        error_log("Lỗi cập nhật trạng thái lương: Dữ liệu không hợp lệ (ID=$employee_id, month=$month, status=$status)");
        header("Location: ../controllers/EmployeeController.php?action=view_salary&id=$employee_id&error=" . urlencode("Dữ liệu không hợp lệ."));
        exit();
    }
}

// Kiểm tra trạng thái đăng nhập và quyền admin cho các hành động khác
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Chuyển hướng đến login_view.php do chưa đăng nhập.");
    header("Location: ../login_view.php");
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("Chuyển hướng đến index.php do không có quyền admin. Role: " . ($_SESSION['role'] ?? 'không xác định'));
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Lấy danh sách nhân viên
try {
    error_log("Lấy danh sách nhân viên.");
    $employees = $model->getEmployees();
    error_log("Lấy danh sách nhân viên thành công: " . count($employees) . " nhân viên.");
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách nhân viên: " . $e->getMessage());
    $employees = [];
}

// Đóng kết nối
$model->close();
error_log("Đóng kết nối EmployeeModel.");

// Tải View
include '../view/employee.php';
error_log("Tải view employee.php thành công.");
?>