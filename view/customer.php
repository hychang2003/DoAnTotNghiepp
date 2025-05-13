<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Chuyển hướng đến login_view.php do không đăng nhập");
    header("Location: ../login_view.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

date_default_timezone_set('Asia/Ho_Chi_Minh');

include_once '../config/db_connect.php';
include_once '../models/CustomerModel.php';

$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';

$model = new CustomerModel($host, $username, $password, $shop_db);

try {
    $customers = $model->getCustomers();
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách khách hàng: " . $e->getMessage());
    $customers = [];
}

try {
    $conn_common = new mysqli($host, $username, $password, 'fashion_shopp');
    if ($conn_common->connect_error) {
        error_log("Lỗi kết nối đến fashion_shopp: " . $conn_common->connect_error);
        $shop_name = $shop_db;
    } else {
        $conn_common->set_charset("utf8mb4");
        $sql = "SELECT name FROM shop WHERE db_name = ?";
        $stmt = $conn_common->prepare($sql);
        $stmt->bind_param('s', $shop_db);
        $stmt->execute();
        $result = $stmt->get_result();
        $shop_name = ($result->num_rows > 0) ? $result->fetch_assoc()['name'] : $shop_db;
        $stmt->close();
        $conn_common->close();
    }
} catch (Exception $e) {
    error_log("Lỗi khi lấy tên cửa hàng: " . $e->getMessage());
    $shop_name = $shop_db;
}

$model->close();

$session_username = $_SESSION['username'] ?? 'Khách';

error_log("customer.php: Using $shop_db for customer, shop_name = $shop_name");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách khách hàng - <?php echo htmlspecialchars($shop_name); ?></title>
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
                <input type="text" id="searchInput" class="form-control" placeholder="Tìm kiếm theo tên hoặc số điện thoại...">
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
            <h1>Danh sách khách hàng - Cơ sở: <?php echo htmlspecialchars($shop_name); ?> (DB: <?php echo htmlspecialchars($shop_db); ?>)</h1>
        </header>

        <!-- Thông báo -->
        <?php if (isset($_GET['customer_added']) && $_GET['customer_added'] === 'success'): ?>
            <div class="alert alert-success">Thêm khách hàng thành công!</div>
        <?php elseif (isset($_GET['customer_deleted']) && $_GET['customer_deleted'] === 'success'): ?>
            <div class="alert alert-success">Xóa khách hàng thành công!</div>
        <?php elseif (isset($_GET['customer_updated']) && $_GET['customer_updated'] === 'success'): ?>
            <div class="alert alert-success">Cập nhật khách hàng thành công!</div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-body">
                <a href="../controllers/CustomerController.php?action=add" class="btn btn-primary mb-3">Thêm khách hàng mới</a>
                <table class="table table-bordered" id="customerTable">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên khách hàng</th>
                        <th>Số điện thoại</th>
                        <th>Email</th>
                        <th>Địa chỉ</th>
                        <th>Hành động</th>
                    </tr>
                    </thead>
                    <tbody id="customerTableBody">
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['id']); ?></td>
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($customer['address'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="../controllers/CustomerController.php?action=update&id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary">Sửa</a>
                                    <a href="../controllers/CustomerController.php?action=delete&id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa khách hàng này?');">Xóa</a>
                                    <a href="../controllers/CustomerController.php?action=history&id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-info">Xem</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Không có khách hàng nào.</td>
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');
        const customerTableBody = document.getElementById('customerTableBody');

        function searchCustomers(query) {
            console.log('Gửi yêu cầu tìm kiếm khách hàng với query:', query);
            fetch('../controllers/CustomerController.php?action=search&query=' + encodeURIComponent(query))
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
                    customerTableBody.innerHTML = '';
                    if (data.error) {
                        customerTableBody.innerHTML = `<tr><td colspan="6" class="text-center">${data.error}</td></tr>`;
                        console.error('Lỗi từ server:', data.error);
                    } else if (data.customers && data.customers.length > 0) {
                        data.customers.forEach(customer => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                            <td>${customer.id}</td>
                            <td>${customer.name}</td>
                            <td>${customer.phone_number || 'N/A'}</td>
                            <td>${customer.email || 'N/A'}</td>
                            <td>${customer.address || 'N/A'}</td>
                            <td>
                                <a href="../controllers/CustomerController.php?action=update&id=${customer.id}" class="btn btn-sm btn-primary">Sửa</a>
                                <a href="../controllers/CustomerController.php?action=delete&id=${customer.id}" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa khách hàng này?');">Xóa</a>
                                <a href="../controllers/CustomerController.php?action=history&id=${customer.id}" class="btn btn-sm btn-info">Xem</a>
                            </td>
                        `;
                            customerTableBody.appendChild(row);
                        });
                    } else {
                        customerTableBody.innerHTML = '<tr><td colspan="6" class="text-center">Không tìm thấy khách hàng nào.</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Lỗi tìm kiếm khách hàng:', error);
                    customerTableBody.innerHTML = '<tr><td colspan="6" class="text-center">Lỗi khi tìm kiếm khách hàng: ' + error.message + '</td></tr>';
                });
        }

        searchBtn.addEventListener('click', () => {
            const query = searchInput.value.trim();
            searchCustomers(query);
        });

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim();
            searchCustomers(query);
        });
    });
</script>
</body>
</html>