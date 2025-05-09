<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Chuyển hướng đến login_view.php do chưa đăng nhập.");
    header("Location: ../login_view.php");
    exit();
}

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bao gồm file kết nối cơ sở dữ liệu
include_once '../config/db_connect.php';

// Lấy thông tin từ session
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$session_username = $_SESSION['username'] ?? 'Khách';
$role = $_SESSION['role'] ?? 'user';

// Kiểm tra username
if (empty($session_username)) {
    error_log("Username không hợp lệ, chuyển hướng đến login_view.php.");
    header("Location: ../login_view.php");
    exit();
}

// Lấy tên cửa hàng
$conn_common = new mysqli($host, $username, $password, 'fashion_shopp');
if ($conn_common->connect_error) {
    error_log("Lỗi kết nối đến fashion_shopp: " . $conn_common->connect_error);
    $shop_name = $shop_db;
} else {
    $conn_common->set_charset("utf8mb4");
    $sql = "SELECT name FROM shop WHERE db_name = ?";
    $stmt = $conn_common->prepare($sql);
    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn tên cửa hàng: " . $conn_common->error);
        $shop_name = $shop_db;
    } else {
        $stmt->bind_param('s', $shop_db);
        $stmt->execute();
        $result = $stmt->get_result();
        $shop_name = $result->num_rows > 0 ? $result->fetch_assoc()['name'] : $shop_db;
        $stmt->close();
    }
    $conn_common->close();
}

// Kết nối đến cơ sở dữ liệu shop_11
$conn = new mysqli($host, $username, $password, $shop_db);
if ($conn->connect_error) {
    error_log("Lỗi kết nối đến $shop_db: " . $conn->connect_error);
    $error = "Lỗi kết nối đến cơ sở dữ liệu.";
    $products = [];
} else {
    $conn->set_charset("utf8mb4");

    // Truy vấn danh sách sản phẩm
    $products = [];
    $sql = "SELECT p.id, p.name, p.price, p.image, p.category_id, p.flash_sale_id, 
                   COALESCE(i.quantity, 0) AS quantity,
                   c.name AS category_name, f.name AS flash_sale_name
            FROM `$shop_db`.product p
            LEFT JOIN `fashion_shopp`.category c ON p.category_id = c.id
            LEFT JOIN `fashion_shopp`.flash_sale f ON p.flash_sale_id = f.id
            LEFT JOIN `$shop_db`.inventory i ON p.id = i.product_id
            ORDER BY p.created_at DESC";
    $result = $conn->query($sql);
    if ($result === false) {
        error_log("Lỗi truy vấn sản phẩm: " . $conn->error);
        $error = "Lỗi khi lấy danh sách sản phẩm.";
    } else {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $result->free();
    }
    $conn->close();
}

// Xử lý thông báo
$error = $error ?? '';
$success = $success ?? '';
if (isset($_SESSION['form_errors'])) {
    $error = implode('<br>', $_SESSION['form_errors']);
    unset($_SESSION['form_errors']);
}
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách sản phẩm - <?php echo htmlspecialchars($shop_name); ?></title>
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
                    <li><a href="product_category.php">Danh mục sản phẩm</a></li>
                </ul>
            </li>
            <li><a href="order.php"><i class="fa fa-file-invoice-dollar"></i> Hóa đơn</a></li>
            <li class="has-dropdown">
                <a href="#" id="shopMenu"><i class="fa fa-store"></i> Quản lý shop <i class="fa fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="inventory_stock_view.php">Tồn kho</a></li>
                    <li><a href="import_goods.php">Nhập hàng</a></li>
                    <li><a href="export_goods.php">Xuất hàng</a></li>
                </ul>
            </li>
            <li><a href="customer.php"><i class="fa fa-users"></i> Khách hàng</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="employee.php"><i class="fa fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="flash_sale_view.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
            <li><a href="report_view.php"><i class="fa fa-chart-bar"></i> Báo cáo</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="switch_shop_view.php"><i class="fa fa-exchange-alt"></i> Switch Cơ Sở</a></li>
                <li><a href="add_shop.php"><i class="fa fa-plus-circle"></i> Thêm Cơ Sở</a></li>
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
                    <span class="fw-bold"><?php echo htmlspecialchars($session_username); ?></span>
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
            <h1>Danh sách sản phẩm - Cơ sở: <?php echo htmlspecialchars($shop_name); ?> (DB: <?php echo htmlspecialchars($shop_db); ?>)</h1>
        </header>

        <!-- Thông báo -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-body">
                <!-- Nút thêm sản phẩm -->
                <a href="../controllers/ProductController.php" class="btn btn-primary mb-3">Thêm sản phẩm mới</a>
                <!-- Bảng danh sách sản phẩm -->
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên sản phẩm</th>
                        <th>Giá (VNĐ)</th>
                        <th>Số lượng tồn kho</th>
                        <th>Danh mục</th>
                        <th>Hình ảnh</th>
                        <th>Chương trình khuyến mãi</th>
                        <th>Hành động</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['id']); ?></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($product['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $image_paths = [
                                        $_SERVER['DOCUMENT_ROOT'] . '/datn/' . $product['image'],
                                        $_SERVER['DOCUMENT_ROOT'] . '/datn/images/' . basename($product['image'])
                                    ];
                                    $image_exists = false;
                                    $valid_image_path = '';
                                    foreach ($image_paths as $path) {
                                        if (file_exists($path)) {
                                            $image_exists = true;
                                            $valid_image_path = str_replace($_SERVER['DOCUMENT_ROOT'] . '/datn/', '', $path);
                                            break;
                                        }
                                    }
                                    if (!empty($product['image']) && $image_exists): ?>
                                        <img src="/datn/<?php echo htmlspecialchars($valid_image_path); ?>" alt="Hình ảnh sản phẩm" width="50">
                                    <?php else: ?>
                                        <img src="/datn/assets/images/default.png" alt="Không có ảnh" width="50">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($product['flash_sale_name'] ?? 'Chưa áp dụng'); ?>
                                </td>
                                <td>
                                    <a href="update_product_list.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">Sửa</a>
                                    <a href="../controllers/ProductController.php?action=delete&id=<?php echo $product['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này?');">Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Không có sản phẩm nào.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>