<?php
session_start();

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Kiểm tra quyền truy cập
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Kiểm tra id trong query parameters
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ./employee.php?error=invalid_employee_id");
    exit();
}
$employee_id = (int)$_GET['id'];

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu
function getConnection($host, $username, $password, $dbname) {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        error_log("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Kết nối đến cơ sở dữ liệu chính (fashion_shop)
$conn = getConnection($host, $username, $password, 'fashion_shop');

// Xử lý cập nhật thông tin nhân viên
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');

    // Kiểm tra dữ liệu
    if (empty($name)) {
        $error = "Vui lòng nhập tên nhân viên!";
    } elseif (empty($email)) {
        $error = "Vui lòng nhập email!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ!";
    } elseif (empty($phone_number)) {
        $error = "Vui lòng nhập số điện thoại!";
    } else {
        // Cập nhật thông tin nhân viên trong bảng users
        $sql_update = "UPDATE users SET name = ?, email = ?, phone_number = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update === false) {
            error_log("Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error);
            $error = "Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error;
        } else {
            $stmt_update->bind_param('sssi', $name, $email, $phone_number, $employee_id);
            if ($stmt_update->execute()) {
                // Kiểm tra số hàng bị ảnh hưởng
                if ($stmt_update->affected_rows > 0) {
                    $stmt_update->close();
                    $conn->close();
                    header("Location: ./employee.php?updated=success");
                    exit();
                } else {
                    $stmt_update->close();
                    $error = "Không có thay đổi nào được thực hiện.";
                }
            } else {
                error_log("Lỗi khi cập nhật nhân viên: " . $stmt_update->error);
                $error = "Lỗi khi cập nhật nhân viên: " . $stmt_update->error;
                $stmt_update->close();
            }
        }
    }
}

// Lấy thông tin nhân viên từ bảng users
$sql_employee = "SELECT name, email, phone_number, username, role FROM users WHERE id = ?";
$stmt_employee = $conn->prepare($sql_employee);
if ($stmt_employee === false) {
    error_log("Lỗi chuẩn bị truy vấn nhân viên: " . $conn->error);
    $error = "Lỗi chuẩn bị truy vấn nhân viên: " . $conn->error;
} else {
    $stmt_employee->bind_param('i', $employee_id);
    $stmt_employee->execute();
    $result_employee = $stmt_employee->get_result();

    if ($result_employee->num_rows === 0) {
        $stmt_employee->close();
        $conn->close();
        header("Location: ./employee.php?error=employee_not_found");
        exit();
    }

    $employee = $result_employee->fetch_assoc();
    $stmt_employee->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin nhân viên</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
<div id="main">
    <!-- Sidebar -->
    <div id="sidebar" class="shadow">
        <div class="logo">
            <img src="../img/logo/logo.png" alt="Logo">
        </div>
        <button id="sidebarToggle"><i class="fa fa-arrow-left"></i></button>
        <ul class="list-unstyled p-3">
            <li><a href="../index.php"><i class="fa fa-chart-line"></i> Tổng quan</a></li>
            <li class="has-dropdown">
                <a href="#" id="productMenu"><i class="fa fa-box"></i> Sản phẩm <i class="fa fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="../view/products_list.php">Danh sách sản phẩm</a></li>
                    <li><a href="../view/product_category.php">Danh mục sản phẩm</a></li>
                </ul>
            </li>
            <li><a href="../view/order.php"><i class="fa fa-file-invoice-dollar"></i> Hóa đơn</a></li>
            <li class="has-dropdown">
                <a href="#" id="shopMenu"><i class="fa fa-store"></i> Quản lý shop <i class="fa fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="../view/inventory_stock.php">Tồn kho</a></li>
                    <li><a href="../view/import_goods.php">Nhập hàng</a></li>
                    <li><a href="../view/export_goods.php">Xuất hàng</a></li>
                </ul>
            </li>
            <li><a href="../view/customer.php"><i class="fa fa-users"></i> Khách hàng</a></li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="../view/employee.php"><i class="fa fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="../view/flash_sale.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
            <li><a href="../view/report.php"><i class="fa fa-chart-bar"></i> Báo cáo</a></li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="../view/switch_shop.php"><i class="fa fa-exchange-alt"></i> Switch Cơ Sở</a></li>
                <li><a href="../view/add_shop.php"><i class="fa fa-plus-circle"></i> Thêm Cơ Sở</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Header -->
    <div id="header" class="bg-light py-2 shadow-sm">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="input-group w-50">
                <input type="text" class="form-control" placeholder="Tìm kiếm...">
                <button class="btn btn-primary"><i class="fa fa-search"></i></button>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="../img/avatar/avatar.png" alt="Avatar" class="rounded-circle me-2" width="40" height="40">
                    <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="#">Thông tin tài khoản</a></li>
                    <li><a class="dropdown-item" href="../logout.php">Đăng xuất</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div id="container">
        <div class="content" style="margin-top: 0px">
            <!-- Thông báo -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Breadcrumb and Actions -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <a href="employee.php" class="btn btn-link"><i class="fa fa-arrow-left"></i></a>
                    <h1 class="h4 mb-0 ms-2" id="employee-title">Nhân viên: <?php echo htmlspecialchars($employee['name']); ?></h1>
                </div>
                <div class="d-flex gap-2">
                    <a href="delete_employee.php?id=<?php echo $employee_id; ?>" class="btn btn-outline-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa nhân viên này?');">Xóa nhân viên</a>
                    <button type="submit" form="updateEmployeeForm" class="btn btn-primary">Lưu</button>
                </div>
            </div>

            <!-- Employee Overview -->
            <div class="card mb-4">
                <div class="card-body d-flex align-items-center">
                    <img src="../img/avatar/avatar.png" alt="Avatar" class="rounded-circle me-3" width="50" height="50">
                    <div>
                        <h3 class="mb-0" id="employee-name"><?php echo htmlspecialchars($employee['name']); ?></h3>
                        <p class="text-muted mb-0" id="last-login">Không có thông tin đăng nhập lần cuối</p>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Thông tin tài khoản</h5>
                </div>
                <div class="card-body">
                    <form id="updateEmployeeForm" method="POST" action="">
                        <div class="mb-3">
                            <label for="employeeName" class="form-label"><strong>Tên:</strong></label>
                            <input type="text" class="form-control" id="employeeName" name="name" value="<?php echo htmlspecialchars($employee['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="employeeEmail" class="form-label"><strong>Email:</strong></label>
                            <input type="email" class="form-control" id="employeeEmail" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="employeePhone" class="form-label"><strong>Điện thoại:</strong></label>
                            <input type="text" class="form-control" id="employeePhone" name="phone_number" value="<?php echo htmlspecialchars($employee['phone_number'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="employeeUsername" class="form-label"><strong>Username:</strong></label>
                            <input type="text" class="form-control" id="employeeUsername" value="<?php echo htmlspecialchars($employee['username']); ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label for="employeeRole" class="form-label"><strong>Vai trò:</strong></label>
                            <input type="text" class="form-control" id="employeeRole" value="<?php echo htmlspecialchars($employee['role']); ?>" disabled>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="../assets/js/script.js"></script>
</body>
</html>