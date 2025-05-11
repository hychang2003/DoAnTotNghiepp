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
$shop_db = $_SESSION['shop_db'] ?? 'shop_11';

// Lấy tên cơ sở và ID từ bảng shop
$conn_main = getConnection($host, $username, $password, 'fashion_shopp');
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
$current_shop_id = $shop_row['id'] ?? 0;
$stmt_shop->close();

// Kết nối đến cơ sở dữ liệu của shop
$conn = getConnection($host, $username, $password, $shop_db);

// Xử lý phân trang
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Xử lý tìm kiếm và lọc
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = "AND (p.name LIKE '%$search%' OR c.name LIKE '%$search%')";
}

// Đếm tổng số bản ghi tồn kho
$sql_count = "SELECT COUNT(DISTINCT p.id) as total
              FROM `$shop_db`.product p
              LEFT JOIN `fashion_shopp`.category c ON p.category_id = c.id
              LEFT JOIN `$shop_db`.inventory i ON p.id = i.product_id
              WHERE i.shop_id = ? $search_condition";
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    error_log("Lỗi chuẩn bị truy vấn count inventory: " . $conn->error);
    die("Lỗi chuẩn bị truy vấn count: " . $conn->error);
}
$stmt_count->bind_param('i', $current_shop_id);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_records = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// Lấy danh sách tồn kho
$sql_inventory = "SELECT p.id, p.name, p.image, p.price, c.name AS category_name, SUM(i.quantity) AS total_quantity, i.unit
                  FROM `$shop_db`.product p
                  LEFT JOIN `fashion_shopp`.category c ON p.category_id = c.id
                  LEFT JOIN `$shop_db`.inventory i ON p.id = i.product_id
                  WHERE i.shop_id = ? $search_condition
                  GROUP BY p.id, p.name, p.image, p.price, c.name, i.unit
                  ORDER BY p.id ASC
                  LIMIT ? OFFSET ?";
$stmt_inventory = $conn->prepare($sql_inventory);
if ($stmt_inventory === false) {
    error_log("Lỗi chuẩn bị truy vấn inventory: " . $conn->error);
    die("Lỗi chuẩn bị truy vấn inventory: " . $conn->error);
}
$stmt_inventory->bind_param('iii', $current_shop_id, $limit, $offset);
$stmt_inventory->execute();
$result_inventory = $stmt_inventory->get_result();
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
            <h1>Quản lý tồn kho - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
        </header>

        <!-- Filters -->
        <section class="filters">
            <div class="search-filter">
                <form method="GET" action="">
                    <input type="text" name="search" placeholder="Tìm kiếm theo mã, tên sản phẩm hoặc danh mục" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-filter">Tìm kiếm</button>
                </form>
            </div>
        </section>

        <!-- Inventory Table -->
        <section class="inventory-table">
            <table class="table table-bordered">
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
                <tbody>
                <?php if ($result_inventory->num_rows > 0): ?>
                    <?php while ($row = $result_inventory->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if ($row['image']): ?>
                                    <img src="../<?php echo htmlspecialchars($row['image']); ?>" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover;">
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo 'SP' . sprintf('%03d', $row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($row['price'], 0, ',', '.'); ?>₫</td>
                            <td><?php echo $row['total_quantity'] ?? 0; ?></td>
                            <td><?php echo ($row['total_quantity'] > 0) ? 'Còn hàng' : 'Hết hàng'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">Không có sản phẩm nào trong kho.</td>
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
$result_inventory->free();
$conn->close();
$conn_main->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>