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

// Khai báo các biến
$session_username = $_SESSION['username'] ?? 'Khách';
$shop_name = $_SESSION['shop_name'] ?? 'Cửa hàng mặc định';
$errors = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : (isset($_GET['error']) ? [$_GET['error']] : []);
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];

// Xóa dữ liệu tạm sau khi sử dụng
unset($_SESSION['form_errors']);
unset($_SESSION['form_data']);

// Kiểm tra biến $flash_sale được truyền từ controller
if (!isset($flash_sale)) {
    header("Location: ../view/flash_sale_view.php?error=" . urlencode("Không tìm thấy thông tin chương trình khuyến mãi."));
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật chương trình khuyến mãi - <?php echo htmlspecialchars($shop_name); ?></title>
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
            <h1>Cập nhật chương trình khuyến mãi - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
        </header>

        <!-- Thông báo lỗi -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Form cập nhật chương trình khuyến mãi -->
        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="../controllers/FlashSaleController.php?action=save_update">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($flash_sale['id']); ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên chương trình <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($form_data['name'] ?? $flash_sale['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="discount" class="form-label">Giảm giá (%) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="discount" name="discount" value="<?php echo htmlspecialchars($form_data['discount'] ?? $flash_sale['discount']); ?>" step="0.01" min="0" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Ngày bắt đầu <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($form_data['start_date'] ?? date('Y-m-d\TH:i', strtotime($flash_sale['start_date']))); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_date" class="form-label">Ngày kết thúc <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($form_data['end_date'] ?? date('Y-m-d\TH:i', strtotime($flash_sale['end_date']))); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Trạng thái</label>
                        <select class="form-control" id="status" name="status">
                            <option value="1" <?php echo ($form_data['status'] ?? $flash_sale['status']) == 1 ? 'selected' : ''; ?>>Hoạt động</option>
                            <option value="0" <?php echo ($form_data['status'] ?? $flash_sale['status']) == 0 ? 'selected' : ''; ?>>Không hoạt động</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Cập nhật chương trình</button>
                    <a href="flash_sale_view.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
?>