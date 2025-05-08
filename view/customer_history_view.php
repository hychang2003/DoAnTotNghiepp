<?php
include_once '../config/session_check.php';
include_once '../controllers/CustomerController.php';

// Debug session
error_log("customer_history_view.php - Session ID: " . session_id());
error_log("customer_history_view.php - Logged in: " . (isset($_SESSION['loggedin']) ? 'true' : 'false'));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử mua hàng - <?php echo htmlspecialchars($customer['name'] ?? 'Khách hàng'); ?></title>
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
                    <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Khách'); ?></span>
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
            <h1>Lịch sử mua hàng - <?php echo htmlspecialchars($customer['name'] ?? 'Khách hàng'); ?> - Cơ sở: <?php echo htmlspecialchars($shop_name ?? 'Cửa hàng mặc định'); ?></h1>
            <div class="actions">
                <a href="../view/customer.php" class="btn btn-secondary">Quay lại</a>
            </div>
        </header>

        <!-- Thông báo lỗi -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Thông tin khách hàng -->
        <div class="card mt-3">
            <div class="card-body">
                <h5>Thông tin khách hàng</h5>
                <p><strong>Tên:</strong> <?php echo htmlspecialchars($customer['name'] ?? 'N/A'); ?></p>
                <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($customer['phone_number'] ?? 'N/A'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></p>
                <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($customer['address'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <!-- Lịch sử mua hàng -->
        <div class="card mt-3">
            <div class="card-body">
                <h5>Lịch sử mua hàng</h5>
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>Mã hóa đơn</th>
                        <th>Tên sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Giá đơn vị</th>
                        <th>Giảm giá</th>
                        <th>Tổng giá</th>
                        <th>Ngày mua</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($history)): ?>
                        <?php foreach ($history as $row): ?>
                            <?php
                            // Tính tổng giá: (unit_price * quantity) - discount
                            $total_price = ($row['unit_price'] * $row['quantity']) - ($row['discount'] ?? 0);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['order_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['product_name'] ?? 'Sản phẩm không tồn tại'); ?></td>
                                <td><?php echo htmlspecialchars($row['quantity'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($row['unit_price'] ?? 0, 0, ',', '.') . '₫'; ?></td>
                                <td><?php echo number_format($row['discount'] ?? 0, 0, ',', '.') . '₫'; ?></td>
                                <td><?php echo number_format($total_price, 0, ',', '.') . '₫'; ?></td>
                                <td><?php echo htmlspecialchars($row['order_date'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Khách hàng chưa có lịch sử mua hàng.</td>
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
?>