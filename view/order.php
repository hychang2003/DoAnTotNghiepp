<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu của cơ sở hiện tại
function getShopConnection($host, $username, $password, $shop_db) {
    $conn = new mysqli($host, $username, $password, $shop_db);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại
$conn = getShopConnection($host, $username, $password, $shop_db);

// Hàm định dạng tiền tệ
function formatCurrency($number) {
    // Chuyển số thành số thực
    $number = floatval($number);
    if (floor($number) == $number) {
        // Nếu là số nguyên, không hiển thị phần thập phân
        return number_format($number, 0, ',', '.');
    } else {
        // Nếu có phần thập phân, hiển thị 2 chữ số thập phân
        return number_format($number, 2, ',', '.');
    }
}

// Truy vấn danh sách hóa đơn
$sql = "SELECT o.id, o.order_date, o.total_price, o.status, c.name AS customer_name, e.name AS employee_name
        FROM `order` o
        LEFT JOIN customer c ON o.customer_id = c.id
        LEFT JOIN employee e ON o.employee_id = e.id";
$result = $conn->query($sql);

// Kiểm tra và cảnh báo nếu tổng tiền không hợp lý
$max_allowed_total = 100000000; // Giới hạn tối đa 100 triệu
$min_allowed_total = 1000; // Giới hạn tối thiểu 1000 VNĐ
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách hóa đơn</title>
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
            <h1>Danh sách hóa đơn</h1>
        </header>

        <div class="card mt-3">
            <div class="card-body">
                <a href="add_order.php" class="btn btn-primary mb-3">Thêm hóa đơn mới</a>
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ngày đặt hàng</th>
                        <th>Khách hàng</th>
                        <th>Nhân viên</th>
                        <th>Tổng tiền (VNĐ)</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($order = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['employee_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $total_price = $order['total_price'];
                                    echo formatCurrency($total_price);
                                    if ($total_price > $max_allowed_total) {
                                        echo ' <span class="badge bg-warning">Cảnh báo: Tổng tiền quá lớn</span>';
                                    } elseif ($total_price < $min_allowed_total) {
                                        echo ' <span class="badge bg-danger">Cảnh báo: Tổng tiền quá nhỏ</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status = $order['status'];
                                    if ($status == 'pending') {
                                        echo '<span class="badge bg-warning">Đang xử lý</span>';
                                    } elseif ($status == 'completed') {
                                        echo '<span class="badge bg-success">Hoàn thành</span>';
                                    } else {
                                        echo '<span class="badge bg-danger">Hủy</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="update_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">Sửa</a>
                                    <a href="delete_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa hóa đơn này?');">Xóa</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Không có hóa đơn nào.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$result->free();
$conn->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>