<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
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
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Lấy tên cơ sở từ bảng shop
$conn_main = getConnection($host, $username, $password, 'fashion_shop');
$sql_shop_name = "SELECT name FROM shop WHERE db_name = ?";
$stmt_shop_name = $conn_main->prepare($sql_shop_name);
if ($stmt_shop_name === false) {
    die("Lỗi chuẩn bị truy vấn name: " . $conn_main->error);
}
$stmt_shop_name->bind_param('s', $shop_db);
$stmt_shop_name->execute();
$result_shop_name = $stmt_shop_name->get_result();
$shop_row = $result_shop_name->fetch_assoc();
$shop_name = $shop_row['name'] ?? $shop_db;
if (!$shop_row) {
    error_log("Không tìm thấy name cho db_name = '$shop_db' trong bảng shop.");
}
$stmt_shop_name->close();

// Kết nối đến cơ sở dữ liệu của shop
$conn = getConnection($host, $username, $password, $shop_db);

// Xử lý phân trang
$limit = 10; // Số bản ghi trên mỗi trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Xử lý tìm kiếm và lọc
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = "WHERE (eg.id LIKE '%$search%' OR e.name LIKE '%$search%' OR p.name LIKE '%$search%' OR to_shop.name LIKE '%$search%')";
}

// Đếm tổng số bản ghi
$sql_count = "SELECT COUNT(*) as total 
              FROM `$shop_db`.export_goods eg
              LEFT JOIN `$shop_db`.employee e ON eg.employee_id = e.id
              LEFT JOIN `$shop_db`.product p ON eg.product_id = p.id
              LEFT JOIN `$shop_db`.transfer_stock ts ON eg.transfer_id = ts.id
              LEFT JOIN `fashion_shop`.shop to_shop ON ts.to_shop_id = to_shop.id
              $search_condition";
$result_count = $conn->query($sql_count);
$total_records = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Lấy danh sách đơn xuất hàng
$sql_exports = "SELECT eg.id, eg.export_date, eg.total_price, eg.quantity, eg.employee_id, eg.product_id, 
                       e.name AS employee_name, p.name AS product_name,
                       ts.to_shop_id, to_shop.name AS to_shop_name
                FROM `$shop_db`.export_goods eg
                LEFT JOIN `$shop_db`.employee e ON eg.employee_id = e.id
                LEFT JOIN `$shop_db`.product p ON eg.product_id = p.id
                LEFT JOIN `$shop_db`.transfer_stock ts ON eg.transfer_id = ts.id
                LEFT JOIN `fashion_shop`.shop to_shop ON ts.to_shop_id = to_shop.id
                $search_condition
                ORDER BY eg.created_at DESC
                LIMIT $limit OFFSET $offset";
$result_exports = $conn->query($sql_exports);
if ($result_exports === false) {
    die("Lỗi truy vấn đơn xuất hàng: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý xuất hàng - <?php echo htmlspecialchars($shop_name); ?></title>
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
            <h1>Danh sách đơn xuất hàng - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
            <div class="actions">
                <a href="add_export_goods.php" class="btn btn-primary">Tạo đơn xuất hàng</a>
            </div>
        </header>

        <!-- Filters -->
        <section class="filters">
            <div class="tabs">
                <button class="tab active">Tất cả</button>
            </div>
            <div class="search-filter">
                <form method="GET" action="">
                    <input type="text" name="search" placeholder="Tìm kiếm theo mã đơn xuất, sản phẩm, nhân viên, shop nhập" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-filter">Tìm kiếm</button>
                </form>
            </div>
        </section>

        <!-- Export Goods Table -->
        <section class="export-goods-table">
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày tạo</th>
                    <th>Shop nhập</th>
                    <th>Nhân viên tạo</th>
                    <th>Sản phẩm</th>
                    <th>Số lượng xuất</th>
                    <th>Giá trị xuất</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($result_exports->num_rows > 0): ?>
                    <?php while ($row = $result_exports->fetch_assoc()): ?>
                        <tr>
                            <td><a href="view_export_goods.php?id=<?php echo $row['id']; ?>">SRT<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></a></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['export_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['to_shop_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td><?php echo $row['quantity']; ?></td>
                            <td><?php echo number_format($row['total_price'], 0, ',', '.') . '₫'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Không có đơn xuất hàng nào.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>

        <!-- Pagination -->
        <footer class="pagination">
            <span>Từ <?php echo $offset + 1; ?> đến <?php echo min($offset + $limit, $total_records); ?> trên tổng <?php echo $total_records; ?></span>
            <div class="pagination-controls">
                <a href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>">◄</a>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-secondary <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <a href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm btn-outline-secondary <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">►</a>
            </div>
        </footer>
    </div>
</div>

<?php
// Đóng kết nối
$result_exports->free();
$conn->close();
$conn_main->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>