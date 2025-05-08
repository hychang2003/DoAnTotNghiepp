<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Chuyển hướng đến login_view.php do không đăng nhập");
    header("Location: ../login_view.php");
    exit();
}

// Kiểm tra quyền admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("Chuyển hướng đến index.php do không phải admin");
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

$session_username = $_SESSION['username'] ?? 'Khách';
$shop_name = $_SESSION['shop_name'] ?? 'Cửa hàng mặc định';

// Lấy thông báo từ query string
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin lương - <?php echo htmlspecialchars($employee['name']); ?> - <?php echo htmlspecialchars($shop_name); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .btn-attendance:disabled {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            cursor: not-allowed;
        }
        #attendanceMessage {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
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
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="../view/employee.php"><i class="fa fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="flash_sale_view.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
            <li><a href="report_view.php"><i class="fa fa-chart-bar"></i> Báo cáo</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
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
                <button id="attendanceBtn" class="btn btn-success me-2" onclick="recordAttendance()">Chấm Công</button>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="../img/avatar/avatar.png" alt="Avatar" class="rounded-circle me-2" width="40" height="40">
                        <span class="fw-bold"><?php echo htmlspecialchars($session_username); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="#">Thông tin tài khoản</a></li>
                        <li><a class="dropdown-item" href="../logout.php">Đăng xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Nội dung chính -->
    <div class="content">
        <!-- Thông báo chấm công -->
        <div id="attendanceMessage" class="alert alert-success" role="alert">
            Chấm công thành công!
        </div>

        <header class="header">
            <h1>Thông tin lương - Nhân viên: <?php echo htmlspecialchars($employee['name']); ?> - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
        </header>

        <!-- Thông báo -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="../controllers/EmployeeController.php?action=update_salary">
                    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee['id']); ?>">
                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($month); ?>">
                    <div class="mb-3">
                        <label for="month" class="form-label">Tháng</label>
                        <input type="text" class="form-control" id="month" value="<?php echo htmlspecialchars($month); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="work_days" class="form-label">Số ngày công <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="work_days" value="<?php echo htmlspecialchars($salary['work_days']); ?>" disabled>
                        <input type="hidden" name="work_days" value="<?php echo htmlspecialchars($salary['work_days']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="salary_per_day" class="form-label">Lương một ngày (VNĐ) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="salary_per_day" name="salary_per_day" value="<?php echo htmlspecialchars($salary['salary_per_day']); ?>" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_salary" class="form-label">Tổng lương (VNĐ)</label>
                        <input type="text" class="form-control" id="total_salary" value="<?php echo number_format($salary['total_salary'], 2, ',', '.'); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Trạng thái trả lương</label>
                        <select class="form-control" id="status" onchange="updatePaymentStatus(this.value)">
                            <option value="unpaid" <?php echo $salary['status'] === 'unpaid' ? 'selected' : ''; ?>>Chưa trả</option>
                            <option value="paid" <?php echo $salary['status'] === 'paid' ? 'selected' : ''; ?>>Đã trả</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Ngày trả lương</label>
                        <input type="text" class="form-control" id="payment_date" value="<?php echo $salary['payment_date'] ? htmlspecialchars(date('d/m/Y H:i:s', strtotime($salary['payment_date']))) : 'Chưa trả'; ?>" disabled>
                    </div>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    <a href="../view/employee.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    function updatePaymentStatus(status) {
        if (confirm('Bạn có chắc chắn muốn thay đổi trạng thái trả lương?')) {
            window.location.href = '../controllers/EmployeeController.php?action=update_payment_status&id=<?php echo $employee['id']; ?>&month=<?php echo urlencode($month); ?>&status=' + status;
        }
    }

    function recordAttendance() {
        fetch('../controllers/EmployeeController.php?action=record_attendance', {
            method: 'GET'
        })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                } else {
                    const attendanceBtn = document.getElementById('attendanceBtn');
                    const attendanceMessage = document.getElementById('attendanceMessage');

                    // Hiển thị thông báo thành công
                    attendanceMessage.style.display = 'block';
                    setTimeout(() => {
                        attendanceMessage.style.display = 'none';
                    }, 3000);

                    // Vô hiệu hóa nút
                    attendanceBtn.disabled = true;
                    attendanceBtn.classList.add('btn-attendance');

                    // Lưu trạng thái vào localStorage
                    localStorage.setItem('attendanceDate', new Date().toDateString());
                }
            })
            .catch(error => {
                console.error('Lỗi:', error);
                alert('Đã xảy ra lỗi khi chấm công.');
            });
    }

    // Kiểm tra trạng thái chấm công khi tải trang
    document.addEventListener('DOMContentLoaded', function() {
        const attendanceBtn = document.getElementById('attendanceBtn');
        const lastAttendance = localStorage.getItem('attendanceDate');
        const today = new Date().toDateString();

        // Kiểm tra xem đã chấm công hôm nay chưa
        fetch('../controllers/EmployeeController.php?action=check_attendance')
            .then(response => response.json())
            .then(data => {
                if (data.hasAttended || (lastAttendance === today)) {
                    attendanceBtn.disabled = true;
                    attendanceBtn.classList.add('btn-attendance');
                }
            })
            .catch(error => {
                console.error('Lỗi kiểm tra trạng thái chấm công:', error);
            });
    });
</script>
</body>
</html>