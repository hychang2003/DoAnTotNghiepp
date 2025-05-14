<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

include_once '../config/db_connect.php';
include_once '../models/OrderModel.php';

$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$session_username = $_SESSION['username'] ?? 'Khách';
$shop_name = $_SESSION['shop_name'] ?? 'Cửa hàng mặc định';
$user_id = $_SESSION['user_id'] ?? null;

$model = new OrderModel($host, $username, $password, $shop_db);

$debug_messages = [];
$debug_messages[] = "SESSION user_id: " . ($user_id ?? 'null');
try {
    $discount = $model->getActiveFlashSale();
    $customers = $model->getCustomers();
    $users = $model->getUsers();
    $products = $model->getProducts();
    $debug_messages[] = "Dữ liệu ban đầu: customers=" . count($customers) . ", users=" . count($users) . ", products=" . count($products) . ", discount=$discount";
} catch (Exception $e) {
    $debug_messages[] = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
    $discount = 0;
    $customers = [];
    $users = [];
    $products = [];
}

$error = isset($_SESSION['form_errors']) ? implode('<br>', $_SESSION['form_errors']) : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$debug_output = isset($_SESSION['debug_messages']) ? implode('<br>', $_SESSION['debug_messages']) : '';
unset($_SESSION['form_errors']);
unset($_SESSION['success']);
unset($_SESSION['debug_messages']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm hóa đơn mới - <?php echo htmlspecialchars($shop_name); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSB7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Fallback cục bộ cho Font Awesome -->
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css" onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css';">
</head>
<body>
<div id="main">
    <div id="sidebar" class="shadow">
        <div class="logo">
            <img src="../img/logo/logo.png" alt="Logo">
        </div>
        <button id="sidebarToggle"><i class="fas fa-arrow-left"></i></button>
        <ul class="list-unstyled p-3">
            <li><a href="../index.php"><i class="fas fa-chart-line"></i> Tổng quan</a></li>
            <li class="has-dropdown">
                <a href="#" id="productMenu"><i class="fas fa-box"></i> Sản phẩm <i class="fas fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="products_list_view.php">Danh sách sản phẩm</a></li>
                    <li><a href="product_category.php">Danh mục sản phẩm</a></li>
                </ul>
            </li>
            <li><a href="order.php"><i class="fas fa-file-invoice-dollar"></i> Hóa đơn</a></li>
            <li class="has-dropdown">
                <a href="#" id="shopMenu"><i class="fas fa-store"></i> Quản lý shop <i class="fas fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="inventory_stock_view.php">Tồn kho</a></li>
                    <li><a href="import_goods.php">Nhập hàng</a></li>
                    <li><a href="export_goods.php">Xuất hàng</a></li>
                </ul>
            </li>
            <li><a href="customer.php"><i class="fas fa-users"></i> Khách hàng</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="employee.php"><i class="fas fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="flash_sale_view.php"><i class="fas fa-tags"></i> Khuyến mại</a></li>
            <li><a href="report_view.php"><i class="fas fa-chart-bar"></i> Báo cáo</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="switch_shop_view.php"><i class="fas fa-exchange-alt"></i> Switch Cơ Sở</a></li>
                <li><a href="add_shop.php"><i class="fas fa-plus-circle"></i> Thêm Cơ Sở</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div id="header" class="bg-light py-2 shadow-sm">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="input-group w-50">
                <input type="text" class="form-control" placeholder="Tìm kiếm...">
                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
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

    <div class="content">
        <header class="header">
            <h1>Thêm hóa đơn mới</h1>
        </header>

        <!-- Comment lại phần hiển thị debug để bật lại khi cần -->
        <!--
        <?php if (!empty($debug_messages) || !empty($debug_output)): ?>
            <div class="alert alert-info">
                <strong>Debug:</strong><br>
                <?php echo implode('<br>', $debug_messages); ?>
                <?php if (!empty($debug_output)): ?>
                    <br><?php echo $debug_output; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        -->

        <!-- Thông báo -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="../controllers/OrderController.php?action=add">
                    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($user_id); ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Khách hàng</label>
                            <select class="form-control" id="customer_id" name="customer_id">
                                <option value="">-- Không chọn --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Chọn sản phẩm</label>
                        <table class="table table-bordered" id="productTable">
                            <thead>
                            <tr>
                                <th>Chọn</th>
                                <th>Tên sản phẩm</th>
                                <th>Giá (VNĐ)</th>
                                <th>Giảm giá (%)</th>
                                <th>Số lượng</th>
                                <th>Thành tiền (VNĐ)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($products as $product): ?>
                                <?php $cleaned_name = htmlspecialchars(strip_tags($product['name']), ENT_QUOTES, 'UTF-8'); ?>
                                <tr data-product-id="<?php echo $product['id']; ?>">
                                    <td>
                                        <input type="checkbox" class="product-checkbox" name="products[<?php echo $product['id']; ?>][selected]" value="1">
                                    </td>
                                    <td><?php echo $cleaned_name; ?></td>
                                    <td class="product-price" data-price="<?php echo $product['price']; ?>">
                                        <?php echo number_format($product['price'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="product-discount" data-discount="<?php echo $discount; ?>">
                                        <?php echo number_format($discount, 2, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control product-quantity" name="products[<?php echo $product['id']; ?>][quantity]" value="0" min="0" disabled>
                                    </td>
                                    <td class="subtotal">0</td>
                                    <input type="hidden" name="products[<?php echo $product['id']; ?>][id]" value="<?php echo $product['id']; ?>">
                                    <input type="hidden" name="products[<?php echo $product['id']; ?>][price]" value="<?php echo $product['price']; ?>">
                                    <input type="hidden" name="products[<?php echo $product['id']; ?>][discount]" value="<?php echo $discount; ?>">
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tổng tiền (VNĐ)</label>
                        <input type="text" class="form-control" id="totalPrice" readonly value="0">
                        <input type="hidden" name="total_price_raw" id="totalPriceRaw" value="0">
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Trạng thái</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="pending">Đang xử lý</option>
                            <option value="completed">Hoàn thành</option>
                            <option value="cancelled">Hủy</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Thêm hóa đơn</button>
                    <a href="order.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const productTableBody = document.querySelector('#productTable tbody');
        const totalPriceInput = document.getElementById('totalPrice');
        const totalPriceRawInput = document.getElementById('totalPriceRaw');

        productTableBody.addEventListener('change', function (e) {
            if (e.target.classList.contains('product-checkbox')) {
                const row = e.target.closest('tr');
                const quantityInput = row.querySelector('.product-quantity');
                if (e.target.checked) {
                    quantityInput.value = 1;
                    quantityInput.disabled = false;
                } else {
                    quantityInput.value = 0;
                    quantityInput.disabled = true;
                }
                calculateTotal();
            }
        });

        productTableBody.addEventListener('input', function (e) {
            if (e.target.classList.contains('product-quantity')) {
                const row = e.target.closest('tr');
                const quantity = parseInt(e.target.value) || 0;
                const checkbox = row.querySelector('.product-checkbox');
                if (quantity > 0) {
                    checkbox.checked = true;
                    e.target.disabled = false;
                } else {
                    checkbox.checked = false;
                    e.target.disabled = true;
                }
                calculateTotal();
            }
        });

        function calculateTotal() {
            let total = 0;
            const rows = productTableBody.querySelectorAll('tr');
            rows.forEach(row => {
                const checkbox = row.querySelector('.product-checkbox');
                const price = parseFloat(row.querySelector('.product-price').dataset.price);
                const discountPercent = parseFloat(row.querySelector('.product-discount').dataset.discount);
                const quantity = parseInt(row.querySelector('.product-quantity').value) || 0;
                const discountAmount = (price * discountPercent) / 100;
                const subtotal = (price - discountAmount) * quantity;
                row.querySelector('.subtotal').textContent = subtotal.toLocaleString('vi-VN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                if (checkbox.checked && quantity > 0) {
                    total += subtotal;
                }
            });
            totalPriceInput.value = total.toLocaleString('vi-VN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            totalPriceRawInput.value = total;
        }
    });
</script>
</body>
</html>