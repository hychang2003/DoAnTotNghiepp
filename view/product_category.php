<?php
include '../config/session_check.php';
include '../config/db_connect.php';
include '../models/CategoryModel.php';

// Debug session
error_log("product_category.php - Session ID: " . session_id());
error_log("product_category.php - Logged in: " . (isset($_SESSION['loggedin']) ? 'true' : 'false'));

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khởi tạo các biến
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$session_username = $_SESSION['username'] ?? 'Khách';
$role = $_SESSION['role'] ?? 'user';

// Khởi tạo Model
$model = new CategoryModel($host, $username, $password, $shop_db);

// Lấy tên cửa hàng
try {
    $shop_name = $model->getShopName('fashion_shopp', $shop_db);
} catch (Exception $e) {
    error_log("Lỗi khi lấy tên cửa hàng: " . $e->getMessage());
    $shop_name = $shop_db;
}

// Lấy danh sách danh mục
try {
    $categories = $model->getCategories();
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách danh mục: " . $e->getMessage());
    $categories = [];
}

// Đóng kết nối
$model->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh mục sản phẩm - <?php echo htmlspecialchars($shop_name); ?></title>
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
                <input type="text" id="searchInput" class="form-control" placeholder="Tìm kiếm danh mục...">
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
            <h1>Danh mục sản phẩm - Cơ sở: <?php echo htmlspecialchars($shop_name); ?> (DB: <?php echo htmlspecialchars($shop_db); ?>)</h1>
        </header>

        <!-- Thông báo -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Danh sách danh mục -->
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">Danh sách danh mục</h5>
                <a href="../controllers/CategoryController.php?action=add" class="btn btn-primary mb-3">Thêm danh mục</a>
                <table class="table table-bordered" id="categoryTable">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên danh mục</th>
                        <th>Icon</th>
                        <th>Hành động</th>
                    </tr>
                    </thead>
                    <tbody id="categoryTableBody">
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="4" class="text-center">Chưa có danh mục sản phẩm nào.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['id']); ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td>
                                    <?php if (!empty($category['icon']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/datn/' . $category['icon'])): ?>
                                        <img src="/datn/<?php echo htmlspecialchars($category['icon']); ?>" alt="Icon danh mục" width="50">
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../controllers/CategoryController.php?action=update&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-primary">Sửa</a>
                                    <a href="../controllers/CategoryController.php?action=delete&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa danh mục này?');">Xóa</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');
        const categoryTableBody = document.getElementById('categoryTableBody');

        function searchCategories(query) {
            console.log('Gửi yêu cầu tìm kiếm danh mục với query:', query);
            fetch('../controllers/CategoryController.php?action=search&query=' + encodeURIComponent(query))
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
                    categoryTableBody.innerHTML = '';
                    if (data.error) {
                        categoryTableBody.innerHTML = `<tr><td colspan="4" class="text-center">${data.error}</td></tr>`;
                        console.error('Lỗi từ server:', data.error);
                    } else if (data.categories && data.categories.length > 0) {
                        data.categories.forEach(category => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                            <td>${category.id}</td>
                            <td>${category.name}</td>
                            <td>
                                ${category.icon && category.icon_exists ? `<img src="/datn/${category.icon}" alt="Icon danh mục" width="50">` : 'N/A'}
                            </td>
                            <td>
                                <a href="../controllers/CategoryController.php?action=update&id=${category.id}" class="btn btn-sm btn-primary">Sửa</a>
                                <a href="../controllers/CategoryController.php?action=delete&id=${category.id}" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa danh mục này?');">Xóa</a>
                            </td>
                        `;
                            categoryTableBody.appendChild(row);
                        });
                    } else {
                        categoryTableBody.innerHTML = '<tr><td colspan="4" class="text-center">Không tìm thấy danh mục nào.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Lỗi tìm kiếm danh mục:', error);
                    categoryTableBody.innerHTML = '<tr><td colspan="4" class="text-center">Lỗi khi tìm kiếm danh mục: ' + error.message + '</td></tr>';
                });
        }

        searchBtn.addEventListener('click', () => {
            const query = searchInput.value.trim();
            searchCategories(query);
        });

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim();
            searchCategories(query);
        });
    });
</script>
</body>
</html>