<?php
session_start();

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Chuyển hướng đến login_view.php do không đăng nhập");
    header("Location: ../login_view.php");
    exit();
}

// Kiểm tra quyền truy cập
if ($_SESSION['role'] !== 'admin') {
    error_log("Chuyển hướng đến index.php do không phải admin");
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Kiểm tra id trong query parameters
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    error_log("ID nhân viên không hợp lệ");
    header("Location: ../view/employee.php?error=invalid_employee_id");
    exit();
}
$employee_id = (int)$_GET['id'];

// Bao gồm file kết nối cơ sở dữ liệu và model
include_once '../config/db_connect.php';
include_once '../models/EmployeeModel.php';

// Khởi tạo Model
$model = new EmployeeModel($host, $username, $password, 'fashion_shopp');

// Lấy thông tin nhân viên
try {
    $employee = $model->getEmployeeById($employee_id);
    if (!$employee) {
        error_log("Không tìm thấy nhân viên ID $employee_id");
        header("Location: ../view/employee.php?error=employee_not_found");
        exit();
    }
} catch (Exception $e) {
    error_log("Lỗi lấy thông tin nhân viên ID $employee_id: " . $e->getMessage());
    header("Location: ../view/employee.php?error=" . urlencode($e->getMessage()));
    exit();
}

// Đóng kết nối
$model->close();

// Lấy lỗi từ query string (nếu có)
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
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
                    <li><a href="products_list_view.php">Danh sách sản phẩm</a></li>
                    <li><a href="../view/product_category.php">Danh mục sản phẩm</a></li>
                </ul>
            </li>
            <li><a href="../view/order.php"><i class="fa fa-file-invoice-dollar"></i> Hóa đơn</a></li>
            <li class="has-dropdown">
                <a href="#" id="shopMenu"><i class="fa fa-store"></i> Quản lý shop <i class="fa fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="inventory_stock_view.php">Tồn kho</a></li>
                    <li><a href="../view/import_goods.php">Nhập hàng</a></li>
                    <li><a href="../view/export_goods.php">Xuất hàng</a></li>
                </ul>
            </li>
            <li><a href="../view/customer.php"><i class="fa fa-users"></i> Khách hàng</a></li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="../view/employee.php"><i class="fa fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="flash_sale_view.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
            <li><a href="report_view.php"><i class="fa fa-chart-bar"></i> Báo cáo</a></li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="switch_shop_view.php"><i class="fa fa-exchange-alt"></i> Switch Cơ Sở</a></li>
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
            <div class="d-flex align-items-center">

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
    </div>

    <!-- Main Content -->
    <div id="container">
        <div class="content" style="margin-top: 0px">
            <!-- Thông báo -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Breadcrumb and Actions -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <a href="../view/employee.php" class="btn btn-link"><i class="fa fa-arrow-left"></i></a>
                    <h1 class="h4 mb-0 ms-2" id="employee-title">Nhân viên: <?php echo htmlspecialchars($employee['name']); ?></h1>
                </div>
                <div class="d-flex gap-2">
                    <a href="../controllers/EmployeeController.php?action=delete&id=<?php echo $employee_id; ?>" class="btn btn-outline-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa nhân viên này?');">Xóa nhân viên</a>
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
                    <form id="updateEmployeeForm" method="POST" action="../controllers/EmployeeController.php?action=save_update">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($employee['id']); ?>">
                        <div class="mb-3">
                            <label for="employeeName" class="form-label"><strong>Tên:</strong></label>
                            <input type="text" class="form-control" id="employeeName" name="full_name" value="<?php echo htmlspecialchars($employee['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="employeeEmail" class="form-label"><strong>Email:</strong></label>
                            <input type="email" class="form-control" id="employeeEmail" name="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="employeePhone" class="form-label"><strong>Điện thoại:</strong></label>
                            <input type="text" class="form-control" id="employeePhone" name="phone_number" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="employeeUsername" class="form-label"><strong>Username:</strong></label>
                            <input type="text" class="form-control" id="employeeUsername" value="<?php echo htmlspecialchars($employee['username']); ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label for="employeePassword" class="form-label"><strong>Mật khẩu mới (để trống nếu không thay đổi):</strong></label>
                            <input type="password" class="form-control" id="employeePassword" name="password" placeholder="Nhập mật khẩu mới">
                        </div>
                        <div class="mb-3">
                            <label for="employeeRole" class="form-label"><strong>Vai trò:</strong></label>
                            <select class="form-control" id="employeeRole" name="role" required>
                                <option value="employee" <?php echo $employee['role'] === 'employee' ? 'selected' : ''; ?>>Nhân viên</option>
                                <option value="admin" <?php echo $employee['role'] === 'admin' ? 'selected' : ''; ?>>Quản trị viên</option>
                            </select>
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