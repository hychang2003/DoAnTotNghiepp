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
    $search_condition = "AND (ts.id LIKE '%$search%' OR u.name LIKE '%$search%' OR p.name LIKE '%$search%' OR to_shop.name LIKE '%$search%')";
}

// Đếm tổng số đơn nhập hàng chờ duyệt
$sql_count = "SELECT COUNT(*) as total 
              FROM `$shop_db`.transfer_stock ts
              LEFT JOIN `$shop_db`.users u ON ts.user_id = u.id
              LEFT JOIN `$shop_db`.product p ON ts.product_id = p.id
              LEFT JOIN `fashion_shopp`.shop to_shop ON ts.to_shop_id = to_shop.id
              WHERE ts.from_shop_id = ? AND ts.status = 'pending' $search_condition";
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    error_log("Lỗi chuẩn bị truy vấn count transfer_stock: " . $conn->error);
    die("Lỗi chuẩn bị truy vấn count: " . $conn->error);
}
$stmt_count->bind_param('i', $current_shop_id);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_records = $result_count->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// Lấy danh sách đơn nhập hàng chờ duyệt
$sql_transfers = "SELECT ts.id, ts.transfer_date, ts.quantity, ts.product_id, ts.user_id, ts.to_shop_id, ts.note,
                         u.name AS user_name, p.name AS product_name, to_shop.name AS to_shop_name
                  FROM `$shop_db`.transfer_stock ts
                  LEFT JOIN `$shop_db`.users u ON ts.user_id = u.id
                  LEFT JOIN `$shop_db`.product p ON ts.product_id = p.id
                  LEFT JOIN `fashion_shopp`.shop to_shop ON ts.to_shop_id = to_shop.id
                  WHERE ts.from_shop_id = ? AND ts.status = 'pending' $search_condition
                  ORDER BY ts.created_at DESC
                  LIMIT ? OFFSET ?";
$stmt_transfers = $conn->prepare($sql_transfers);
if ($stmt_transfers === false) {
    error_log("Lỗi chuẩn bị truy vấn transfers: " . $conn->error);
    die("Lỗi chuẩn bị truy vấn transfers: " . $conn->error);
}
$stmt_transfers->bind_param('iii', $current_shop_id, $limit, $offset);
$stmt_transfers->execute();
$result_transfers = $stmt_transfers->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý xuất hàng - <?php echo htmlspecialchars($shop_name); ?></title>
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
        <div class="container d-flex align-items-center justify-content-between" style="margin-left: 70%">
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
            <h1>Danh sách đơn nhập hàng chờ duyệt - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
        </header>

        <!-- Thông báo -->
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] === 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Xóa đơn chuyển kho thành công!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['approved']) && $_GET['approved'] === 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Duyệt đơn chuyển kho thành công!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['rejected']) && $_GET['rejected'] === 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Từ chối đơn chuyển kho thành công!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Transfer Requests Table -->
        <section class="transfer-requests-table">
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày yêu cầu</th>
                    <th>Shop nhập</th>
                    <th>Nhân viên tạo</th>
                    <th>Sản phẩm</th>
                    <th>Số lượng</th>
                    <th>Ghi chú</th>
                    <th>Hành động</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($result_transfers->num_rows > 0): ?>
                    <?php while ($row = $result_transfers->fetch_assoc()): ?>
                        <tr>
                            <td>TR<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['transfer_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['to_shop_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['user_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $row['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($row['note'] ?? ''); ?></td>
                            <td>
                                <a href="../controllers/ProcessTransferController.php?action=approve&transfer_id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">Đồng ý</a>
                                <a href="../controllers/ProcessTransferController.php?action=reject&transfer_id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm">Từ chối</a>
                                <a href="../controllers/ProcessTransferController.php?action=delete&transfer_id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Bạn có chắc chắn muốn xóa đơn chuyển kho TR<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?>?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Không có đơn nhập hàng nào chờ duyệt.</td>
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
$result_transfers->free();
$conn->close();
$conn_main->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>