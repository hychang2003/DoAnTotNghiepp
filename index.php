<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ cho PHP
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bao gồm file kết nối cơ sở dữ liệu
include 'config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu
function getConnection($host, $username, $password, $dbname) {
    // Tạo kết nối mới
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    // Thiết lập mã hóa UTF-8
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Lấy tên cơ sở từ bảng shop
$conn_main = getConnection($host, $username, $password, 'fashion_shop'); // Kết nối đến cơ sở dữ liệu chính (fashion_shop)
$sql_shop_name = "SELECT name FROM shop WHERE db_name = ?";
$stmt_shop_name = $conn_main->prepare($sql_shop_name);
if ($stmt_shop_name === false) {
    die("Lỗi chuẩn bị truy vấn name: " . $conn_main->error);
}
$stmt_shop_name->bind_param('s', $shop_db);
$stmt_shop_name->execute();
$result_shop_name = $stmt_shop_name->get_result();
$shop_row = $result_shop_name->fetch_assoc();
$shop_name = $shop_row['name'] ?? $shop_db; // Nếu không tìm thấy, dùng $shop_db làm mặc định
if (!$shop_row) {
    error_log("Không tìm thấy name cho db_name = '$shop_db' trong bảng shop.");
}
$stmt_shop_name->close();
$conn_main->close(); // Đóng kết nối đến fashion_shop

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại
$conn = getConnection($host, $username, $password, $shop_db);

// Lấy thời gian hiện tại
$current_year = date('Y'); // Năm hiện tại: 2025
$current_month = date('m'); // Tháng hiện tại: 04
$month_start = "$current_year-$current_month-01 00:00:00"; // Ngày đầu tháng
$month_end = date('Y-m-d H:i:s'); // Thời điểm hiện tại

// 1. Tổng doanh thu tháng (month-to-date)
$sql_revenue = "SELECT SUM(total_price) as total_revenue 
               FROM `$shop_db`.`order` 
               WHERE order_date >= ? AND order_date <= ?";
$stmt_revenue = $conn->prepare($sql_revenue);
$stmt_revenue->bind_param('ss', $month_start, $month_end);
$stmt_revenue->execute();
$result_revenue = $stmt_revenue->get_result();
$total_revenue = $result_revenue->fetch_assoc()['total_revenue'] ?? 0;
$total_revenue = round($total_revenue); // Chỉ làm tròn, không chia cho 100
$stmt_revenue->close();

// 2. Tổng đơn hàng trong tháng
$sql_orders = "SELECT COUNT(*) as total_orders 
               FROM `$shop_db`.`order` 
               WHERE order_date >= ? AND order_date <= ?";
$stmt_orders = $conn->prepare($sql_orders);
$stmt_orders->bind_param('ss', $month_start, $month_end);
$stmt_orders->execute();
$result_orders = $stmt_orders->get_result();
$total_orders = $result_orders->fetch_assoc()['total_orders'] ?? 0;
$stmt_orders->close();

// 3. Tổng khách hàng trong tháng (số khách hàng duy nhất đặt hàng)
$sql_customers = "SELECT COUNT(DISTINCT customer_id) as total_customers 
                 FROM `$shop_db`.`order` 
                 WHERE order_date >= ? AND order_date <= ?";
$stmt_customers = $conn->prepare($sql_customers);
$stmt_customers->bind_param('ss', $month_start, $month_end);
$stmt_customers->execute();
$result_customers = $stmt_customers->get_result();
$total_customers = $result_customers->fetch_assoc()['total_customers'] ?? 0;
$stmt_customers->close();

// 4. Top 5 mặt hàng bán chạy nhất trong tháng (dựa trên số lượng bán ra)
$sql_top_products = "SELECT p.name, SUM(od.quantity) as total_quantity 
                    FROM `$shop_db`.order_detail od 
                    JOIN `$shop_db`.`order` o ON od.order_id = o.id 
                    JOIN `$shop_db`.product p ON od.product_id = p.id 
                    WHERE o.order_date >= ? AND o.order_date <= ?
                    GROUP BY p.id, p.name 
                    ORDER BY total_quantity DESC 
                    LIMIT 5";
$stmt_top_products = $conn->prepare($sql_top_products);
$stmt_top_products->bind_param('ss', $month_start, $month_end);
$stmt_top_products->execute();
$result_top_products = $stmt_top_products->get_result();
$top_products = [];
while ($row = $result_top_products->fetch_assoc()) {
    $top_products[] = $row;
}
$stmt_top_products->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tổng quan - <?php echo htmlspecialchars($shop_name); ?></title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div id="main">
    <!-- Sidebar -->
    <div id="sidebar" class="shadow">
        <div class="logo">
            <img src="img/logo/logo.png" alt="Logo">
        </div>
        <button id="sidebarToggle"><i class="fa fa-arrow-left"></i></button>
        <ul class="list-unstyled p-3">
            <li><a href="index.php"><i class="fa fa-chart-line"></i> Tổng quan</a></li>
            <li class="has-dropdown">
                <a href="#" id="productMenu"><i class="fa fa-box"></i> Sản phẩm <i class="fa fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="./view/products_list.php">Danh sách sản phẩm</a></li>
                    <li><a href="./view/product_category.php">Danh mục sản phẩm</a></li>
                </ul>
            </li>
            <li><a href="view/order.php"><i class="fa fa-file-invoice-dollar"></i> Hóa đơn</a></li>
            <li class="has-dropdown">
                <a href="#" id="shopMenu"><i class="fa fa-store"></i> Quản lý shop <i class="fa fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="view/inventory_stock.php">Tồn kho</a></li>
                    <li><a href="view/import_goods.php">Nhập hàng</a></li>
                    <li><a href="view/export_goods.php">Xuất hàng</a></li>
                </ul>
            </li>
            <li><a href="view/customer.php"><i class="fa fa-users"></i> Khách hàng</a></li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="view/employee.php"><i class="fa fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="view/flash_sale.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
            <li><a href="view/report.php"><i class="fa fa-chart-bar"></i> Báo cáo</a></li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="view/switch_shop.php"><i class="fa fa-exchange-alt"></i> Switch Cơ Sở</a></li>
                <li><a href="view/add_shop.php"><i class="fa fa-plus-circle"></i> Thêm Cơ Sở</a></li>
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
                    <img src="img/avatar/avatar.png" alt="Avatar" class="rounded-circle me-2" width="40" height="40">
                    <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="#">Thông tin tài khoản</a></li>
                    <li><a class="dropdown-item" href="logout.php">Đăng xuất</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Nội dung chính -->
    <div class="content">
        <header class="header">
            <h1>Tổng quan - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
            <p>Tháng <?php echo $current_month; ?> năm <?php echo $current_year; ?> (tính đến <?php echo date('d/m/Y H:i:s'); ?>)</p>
        </header>

        <!-- Thẻ thông tin -->
        <div class="row mt-3">
            <!-- Tổng doanh thu tháng -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Tổng doanh thu tháng</h5>
                        <p class="card-text"><?php echo number_format($total_revenue, 0, ',', '.'); ?> VNĐ</p>
                    </div>
                </div>
            </div>
            <!-- Tổng đơn hàng trong tháng -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Tổng đơn hàng trong tháng</h5>
                        <p class="card-text"><?php echo $total_orders; ?> đơn</p>
                    </div>
                </div>
            </div>
            <!-- Tổng khách hàng trong tháng -->
            <div class="col-md-4 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Tổng khách hàng trong tháng</h5>
                        <p class="card-text"><?php echo $total_customers; ?> khách</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 5 mặt hàng bán chạy nhất -->
        <div class="card mt-3">
            <div class="card-body">
                <h5 class="card-title">Top 5 mặt hàng bán chạy nhất trong tháng</h5>
                <?php if (empty($top_products)): ?>
                    <p>Chưa có dữ liệu bán hàng trong tháng này.</p>
                <?php else: ?>
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>Tên sản phẩm</th>
                            <th>Số lượng bán</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($top_products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo $product['total_quantity']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$conn->close();
?>

<script src="./assets/js/script.js"></script>
<script src="./assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>