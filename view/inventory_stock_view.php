<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

date_default_timezone_set('Asia/Ho_Chi_Minh');

include '../config/db_connect.php';
include '../models/InventoryModel.php';

$shop_db = $_SESSION['shop_db'] ?? 'shop_11';
$session_username = $_SESSION['username'] ?? 'Khách';
$role = $_SESSION['role'] ?? 'user';

$model = new InventoryModel($host, $username, $password, $shop_db);

try {
    $shop_name = $model->getShopName('fashion_shopp', $shop_db);
    $current_shop_id = $model->getShopId('fashion_shopp', $shop_db);
} catch (Exception $e) {
    error_log("Lỗi khi lấy tên cơ sở hoặc ID: " . $e->getMessage());
    $shop_name = $shop_db;
    $current_shop_id = 0;
}

try {
    $inventory = $model->getInventory();
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách tồn kho: " . $e->getMessage());
    $inventory = [];
}

$model->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tồn kho - <?php echo htmlspecialchars($shop_name); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
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
            <?php if ($role === 'admin'): ?>
                <li><a href="employee.php"><i class="fa fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="flash_sale_view.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
            <li><a href="report_view.php"><i class="fa fa-chart-bar"></i> Báo cáo</a></li>
            <?php if ($role === 'admin'): ?>
                <li><a href="switch_shop_view.php"><i class="fa fa-exchange-alt"></i> Switch Cơ Sở</a></li>
                <li><a href="add_shop.php"><i class="fa fa-plus-circle"></i> Thêm Cơ Sở</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Header -->
    <div id="header" class="bg-light py-2 shadow-sm">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="input-group w-50">
                <input type="text" id="searchInput" class="form-control" placeholder="Tìm kiếm theo tên sản phẩm hoặc danh mục...">
                <button id="searchBtn" class="btn btn-primary"><i class="fa fa-search"></i></button>
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
            <h1>Quản lý tồn kho - Cơ sở: <?php echo htmlspecialchars($shop_name); ?> (DB: <?php echo htmlspecialchars($shop_db); ?>)</h1>
        </header>

        <!-- Inventory Table -->
        <section class="inventory-table">
            <table class="table table-bordered" id="inventoryTable">
                <thead>
                <tr>
                    <th>Ảnh sản phẩm</th>
                    <th>Mã sản phẩm</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Giá bán</th>
                    <th>Tồn kho</th>
                    <th>Trạng thái</th>
                </tr>
                </thead>
                <tbody id="inventoryTableBody">
                <?php if (!empty($inventory)): ?>
                    <?php foreach ($inventory as $row): ?>
                        <tr>
                            <td>
                                <?php if (!empty($row['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/datn/' . $row['image'])): ?>
                                    <img src="/datn/<?php echo htmlspecialchars($row['image']); ?>" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo 'SP' . sprintf('%03d', $row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($row['price'], 0, ',', '.'); ?>₫</td>
                            <td><?php echo $row['stock_quantity'] ?? 0; ?></td>
                            <td><?php echo ($row['stock_quantity'] > 0) ? 'Còn hàng' : 'Hết hàng'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Không có sản phẩm nào trong kho.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');
        const inventoryTableBody = document.getElementById('inventoryTableBody');

        function searchInventory(query) {
            console.log('Gửi yêu cầu tìm kiếm tồn kho với query:', query);
            fetch('../controllers/InventoryController.php?action=search&query=' + encodeURIComponent(query))
                .then(response => {
                    console.log('Phản hồi HTTP:', response.status, response.statusText);
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error('Phản hồi mạng không ổn: ' + response.statusText + ' - Nội dung: ' + text);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Dữ liệu nhận được:', data);
                    inventoryTableBody.innerHTML = '';
                    if (data.error) {
                        inventoryTableBody.innerHTML = `<tr><td colspan="7" class="text-center">${data.error}</td></tr>`;
                        console.error('Lỗi từ server:', data.error);
                    } else if (data.inventory && data.inventory.length > 0) {
                        data.inventory.forEach(item => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                            <td>
                                ${item.image && item.image_exists ? `<img src="/datn/${item.image}" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover;">` : 'N/A'}
                            </td>
                            <td>SP${String(item.id).padStart(3, '0')}</td>
                            <td>${item.product_name}</td>
                            <td>${item.category_name || 'N/A'}</td>
                            <td>${Number(item.price).toLocaleString('vi-VN')}₫</td>
                            <td>${item.total_quantity || 0}</td>
                            <td>${item.total_quantity > 0 ? 'Còn hàng' : 'Hết hàng'}</td>
                        `;
                            inventoryTableBody.appendChild(row);
                        });
                    } else {
                        inventoryTableBody.innerHTML = '<tr><td colspan="7" class="text-center">Không tìm thấy sản phẩm nào trong kho.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Lỗi tìm kiếm tồn kho:', error);
                    inventoryTableBody.innerHTML = '<tr><td colspan="7" class="text-center">Lỗi khi tìm kiếm tồn kho: ' + error.message + '</td></tr>';
                });
        }

        searchBtn.addEventListener('click', () => {
            const query = searchInput.value.trim();
            searchInventory(query);
        });

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim();
            searchInventory(query);
        });
    });
</script>
</body>
</html>