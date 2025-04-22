<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ cho PHP
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu
function getConnection($host, $username, $password, $dbname) {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Lấy tên cơ sở từ bảng shop
$conn_main = getConnection($host, $username, $password, 'fashion_shop');
$sql_shop_name = "SELECT name FROM shop WHERE db_name = ?";
$stmt_shop_name = $conn_main->prepare($sql_shop_name);
if ($stmt_shop_name === false) {
    die("Lỗi chuẩn bị truy vấn name: " . $conn_main->error);
}
$stmt_shop_name->bind_param('s', $shop_db);
$stmt_shop_name->execute();
$result_shop_name = $stmt_shop_name->get_result();
$shop_row = $result_shop_name->fetch_assoc();
$shop_name = $shop_row['name'] ?? $shop_db;
if (!$shop_row) {
    error_log("Không tìm thấy name cho db_name = '$shop_db' trong bảng shop.");
}
$stmt_shop_name->close();
$conn_main->close();

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại
$conn = getConnection($host, $username, $password, $shop_db);

// Lấy ID danh mục từ URL
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category = null;
$errors = [];

// Lấy thông tin danh mục hiện tại
if ($category_id > 0) {
    $sql_category = "SELECT * FROM `$shop_db`.category WHERE id = ?";
    $stmt_category = $conn->prepare($sql_category);
    if ($stmt_category === false) {
        die("Lỗi chuẩn bị truy vấn danh mục: " . $conn->error);
    }
    $stmt_category->bind_param('i', $category_id);
    $stmt_category->execute();
    $result_category = $stmt_category->get_result();
    $category = $result_category->fetch_assoc();
    $stmt_category->close();

    if (!$category) {
        $errors[] = "Danh mục không tồn tại.";
    }
} else {
    $errors[] = "ID danh mục không hợp lệ.";
}

// Xử lý cập nhật danh mục khi biểu mẫu được gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $name = trim($_POST['name'] ?? '');

    // Kiểm tra dữ liệu đầu vào
    if (empty($name)) {
        $errors[] = "Tên danh mục không được để trống.";
    } elseif (strlen($name) > 100) {
        $errors[] = "Tên danh mục không được dài quá 100 ký tự.";
    }

    // Nếu không có lỗi, tiến hành cập nhật
    if (empty($errors)) {
        $sql_update = "UPDATE `$shop_db`.category SET name = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update === false) {
            die("Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error);
        }
        $stmt_update->bind_param('si', $name, $category_id);
        if ($stmt_update->execute()) {
            // Chuyển hướng về trang danh sách danh mục với thông báo thành công
            header("Location: product_category.php?success=" . urlencode("Cập nhật danh mục thành công."));
            exit();
        } else {
            $errors[] = "Lỗi khi cập nhật danh mục: " . $stmt_update->error;
        }
        $stmt_update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật danh mục - <?php echo htmlspecialchars($shop_name); ?></title>
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
            <h1>Cập nhật danh mục - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
        </header>

        <!-- Biểu mẫu cập nhật danh mục -->
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">Cập nhật danh mục</h5>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <p><?php echo htmlspecialchars($_GET['success']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($category): ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">Tên danh mục</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Cập nhật</button>
                        <a href="product_category.php" class="btn btn-secondary">Quay lại</a>
                    </form>
                <?php else: ?>
                    <p>Không thể tải thông tin danh mục để chỉnh sửa.</p>
                    <a href="product_category.php" class="btn btn-secondary">Quay lại</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$conn->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>