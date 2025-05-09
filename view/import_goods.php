<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ cho PHP
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu
function getConnection($host, $username, $password, $dbname) {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';

// Kết nối đến cơ sở dữ liệu chính
$conn_main = getConnection($host, $username, $password, 'fashion_shopp');

// Lấy thông tin shop hiện tại
$sql_shop = "SELECT id, name FROM shop WHERE db_name = ?";
$stmt_shop = $conn_main->prepare($sql_shop);
if ($stmt_shop === false) {
    die("Lỗi chuẩn bị truy vấn shop: " . $conn_main->error);
}
$stmt_shop->bind_param('s', $shop_db);
$stmt_shop->execute();
$result_shop = $stmt_shop->get_result();
$shop_row = $result_shop->fetch_assoc();
$shop_name = $shop_row['name'] ?? $shop_db;
$stmt_shop->close();

// Kết nối đến cơ sở dữ liệu của shop hiện tại
$conn = getConnection($host, $username, $password, $shop_db);

// Lấy danh sách hóa đơn nhập
$sql_imports = "SELECT ig.id, ig.import_date, ig.total_price, e.name AS employee_name, COUNT(ig.product_id) AS product_count
                FROM `$shop_db`.import_goods ig
                JOIN `$shop_db`.employee e ON ig.employee_id = e.id
                GROUP BY ig.id, ig.import_date, ig.total_price, e.name
                ORDER BY ig.created_at DESC";
$result_imports = $conn->query($sql_imports);
if ($result_imports === false) {
    die("Lỗi truy vấn hóa đơn nhập: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sách hóa đơn nhập - <?php echo htmlspecialchars($shop_name); ?></title>
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
            <h1>Danh sách hóa đơn nhập - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
            <div class="actions">
                <a href="add_import_goods_view.php" class="btn btn-primary"><i class="fa fa-plus me-1"></i> Thêm đơn nhập hàng</a>
            </div>
        </header>

        <!-- Thông báo thành công -->
        <?php if (isset($_GET['import_added']) && $_GET['import_added'] === 'success'): ?>
            <div class="alert alert-success" role="alert">
                Đơn nhập hàng đã được tạo thành công!
            </div>
        <?php endif; ?>

        <!-- Danh sách hóa đơn nhập -->
        <section class="import-goods-list">
            <div class="card">
                <div class="card-header">
                    <h2>Danh sách hóa đơn nhập</h2>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ngày nhập</th>
                            <th>Tổng tiền</th>
                            <th>Nhân viên</th>
                            <th>Số lượng sản phẩm</th>
                            <th>Hành động</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($result_imports->num_rows > 0): ?>
                            <?php while ($row = $result_imports->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['import_date']); ?></td>
                                    <td><?php echo number_format($row['total_price'], 0, ',', '.') . '₫'; ?></td>
                                    <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['product_count']); ?></td>
                                    <td>
                                        <a href="view_import_details.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm"><i class="fa fa-eye"></i> Xem chi tiết</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Không có hóa đơn nhập nào.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<?php
// Đóng kết nối
$result_imports->free();
$conn->close();
$conn_main->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>