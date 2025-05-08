<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bao gồm file kết nối cơ sở dữ liệu và model
include_once '../config/db_connect.php';
include_once '../models/ProductModel.php';

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$session_username = $_SESSION['username'] ?? 'Khách';

// Kiểm tra session_username để ngăn truy cập không hợp lệ
if (!isset($session_username) || empty($session_username)) {
    header("Location: ../login_view.php");
    exit();
}

// Khởi tạo Model và lấy dữ liệu nếu $products chưa được định nghĩa
if (!isset($products)) {
    $model = new ProductModel($host, $username, $password, $shop_db);
    $products = [];
    $error = '';
    $success = '';

    // Xử lý thông báo từ query string
    if (isset($_GET['added']) && $_GET['added'] === 'success') {
        $success = "Thêm sản phẩm thành công!";
    } elseif (isset($_GET['product_deleted']) && $_GET['product_deleted'] === 'success') {
        $success = "Xóa sản phẩm thành công!";
    } elseif (isset($_GET['updated']) && $_GET['updated'] === 'success') {
        $success = "Cập nhật sản phẩm thành công!";
    } elseif (isset($_GET['error'])) {
        $error = $_GET['error'];
    }

    try {
        $products = $model->getProducts();
    } catch (Exception $e) {
        $error = "Lỗi khi lấy danh sách sản phẩm: " . $e->getMessage();
    }
    $model->close();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách sản phẩm</title>
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
            <h1>Danh sách sản phẩm</h1>
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
                <a href="../controllers/ProductController.php" class="btn btn-primary mb-3">Thêm sản phẩm mới</a>
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên sản phẩm</th>
                        <th>Giá (VNĐ)</th>
                        <th>Số lượng</th>
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
                                <td><?php echo htmlspecialchars($product['quantity'] ?? '0'); ?></td>
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