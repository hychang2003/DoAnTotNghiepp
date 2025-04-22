<?php
session_start();

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

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu của cơ sở hiện tại
function getShopConnection($host, $username, $password, $shop_db) {
    $conn = new mysqli($host, $username, $password, $shop_db);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại
$conn = getShopConnection($host, $username, $password, $shop_db);

// Xử lý thêm chương trình khuyến mãi
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $discount = trim($_POST['discount'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');

    // Kiểm tra dữ liệu
    if (empty($name)) {
        $error = "Vui lòng nhập tên chương trình khuyến mãi!";
    } elseif (empty($discount)) {
        $error = "Vui lòng nhập giá trị giảm giá!";
    } elseif (!is_numeric($discount) || $discount < 0 || $discount > 100) {
        $error = "Giảm giá phải là một số từ 0 đến 100!";
    } elseif (empty($start_date)) {
        $error = "Vui lòng nhập ngày bắt đầu!";
    } elseif (empty($end_date)) {
        $error = "Vui lòng nhập ngày kết thúc!";
    } elseif (strtotime($start_date) >= strtotime($end_date)) {
        $error = "Ngày kết thúc phải sau ngày bắt đầu!";
    } else {
        // Chuyển discount thành số nguyên
        $discount = intval($discount);

        // Thêm chương trình khuyến mãi vào cơ sở dữ liệu
        $sql = "INSERT INTO flash_sale (name, discount, start_date, end_date) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn: " . $conn->error);
            $error = "Lỗi chuẩn bị truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param('siss', $name, $discount, $start_date, $end_date);
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header("Location: flash_sale.php?added=success");
                exit();
            } else {
                error_log("Lỗi khi thêm chương trình khuyến mãi: " . $stmt->error);
                $error = "Lỗi khi thêm chương trình khuyến mãi: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm chương trình khuyến mãi</title>
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

    <!-- Nội dung chính -->
    <div class="content">
        <header class="header">
            <h1>Thêm chương trình khuyến mãi</h1>
        </header>

        <!-- Thông báo lỗi -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label"><strong>Tên chương trình:</strong></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="discount" class="form-label"><strong>Giảm giá (%):</strong></label>
                        <input type="number" class="form-control" id="discount" name="discount" value="<?php echo isset($_POST['discount']) ? htmlspecialchars($_POST['discount']) : ''; ?>" min="0" max="100" step="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="start_date" class="form-label"><strong>Ngày bắt đầu:</strong></label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_date" class="form-label"><strong>Ngày kết thúc:</strong></label>
                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Thêm chương trình</button>
                    <a href="flash_sale.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>