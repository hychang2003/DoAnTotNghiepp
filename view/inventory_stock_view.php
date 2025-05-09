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

function getConnection($host, $username, $password, $dbname) {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';

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
$shop_id = $shop_row['id'] ?? 1;
if (!$shop_row) {
    error_log("Không tìm thấy shop cho db_name = '$shop_db' trong bảng shop.");
}
$stmt_shop->close();

$conn = getConnection($host, $username, $password, $shop_db);

$sql_inventory = "SELECT p.id, p.name AS product_name, p.price, p.image, COALESCE(i.quantity, 0) AS stock_quantity, c.name AS category_name
                  FROM `$shop_db`.product p
                  LEFT JOIN fashion_shopp.category c ON p.category_id = c.id
                  LEFT JOIN `$shop_db`.inventory i ON p.id = i.product_id AND i.shop_id = ?";
$stmt_inventory = $conn->prepare($sql_inventory);
if ($stmt_inventory === false) {
    die("Lỗi chuẩn bị truy vấn tồn kho: " . $conn->error);
}
$stmt_inventory->bind_param('i', $shop_id);
$stmt_inventory->execute();
$result_inventory = $stmt_inventory->get_result();

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

$image_base_url = "/assets/images/";
$image_default_url = "/datn/assets/images/default.jpg";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tồn kho - <?php echo htmlspecialchars($shop_name); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<div id="main">
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

    <div class="content">
        <header class="header">
            <h1>Quản lý tồn kho - Cơ sở: <?php echo htmlspecialchars($shop_name); ?> (DB: <?php echo htmlspecialchars($shop_db); ?>)</h1>
            <div class="actions">
            </div>
        </header>

        <div class="tabs">
            <a href="?tab=all" class="tab <?php echo $tab === 'all' ? 'active' : ''; ?>" data-tab="all">Tất cả</a>
            <a href="?tab=in-stock" class="tab <?php echo $tab === 'in-stock' ? 'active' : ''; ?>" data-tab="in-stock">Còn hàng</a>
            <a href="?tab=out-of-stock" class="tab <?php echo $tab === 'out-of-stock' ? 'active' : ''; ?>" data-tab="out-of-stock">Hết hàng</a>
        </div>

        <div class="tab-content">
            <div id="all" class="tab-pane <?php echo $tab === 'all' ? 'active' : ''; ?>">
                <table class="table table-hover">
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
                    <?php while ($row = $result_inventory->fetch_assoc()):
                        $stock_quantity = $row['stock_quantity'];
                        $image_value = $row['image'];
                        if (!empty($image_value)) {
                            $image_url = '/datn/' . $image_value;
                        } else {
                            $image_url = $image_default_url;
                        }
                        $image_file_path = $_SERVER['DOCUMENT_ROOT'] . '/datn' . str_replace('/datn', '', $image_url);
                        ?>
                        <tr>
                            <td>
                                <?php if (file_exists($image_file_path)): ?>
                                    <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($row['product_name']); ?>" class="product-image">
                                <?php else: ?>
                                    <span class="text-danger">Ảnh không tồn tại</span>
                                    <?php error_log("Ảnh không tồn tại: $image_url (Đường dẫn: $image_file_path)"); ?>
                                <?php endif; ?>
                            </td>
                            <td>SP<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?: 'Chưa có danh mục'); ?></td>
                            <td><?php echo number_format($row['price'], 0, ',', '.') . '₫'; ?></td>
                            <td><?php echo $stock_quantity; ?></td>
                            <td>
                                <span class="badge <?php echo $stock_quantity > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $stock_quantity > 0 ? 'Còn hàng' : 'Hết hàng'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div id="in-stock" class="tab-pane <?php echo $tab === 'in-stock' ? 'active' : ''; ?>">
                <table class="table table-hover">
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
                    <?php
                    $result_inventory->data_seek(0);
                    while ($row = $result_inventory->fetch_assoc()):
                        $stock_quantity = $row['stock_quantity'];
                        if ($stock_quantity <= 0) continue;
                        $image_value = $row['image'];
                        if (!empty($image_value)) {
                            $image_url = '/datn/' . $image_value;
                        } else {
                            $image_url = $image_default_url;
                        }
                        $image_file_path = $_SERVER['DOCUMENT_ROOT'] . '/datn' . str_replace('/datn', '', $image_url);
                        ?>
                        <tr>
                            <td>
                                <?php if (file_exists($image_file_path)): ?>
                                    <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($row['product_name']); ?>" class="product-image">
                                <?php else: ?>
                                    <span class="text-danger">Ảnh không tồn tại</span>
                                    <?php error_log("Ảnh không tồn tại: $image_url (Đường dẫn: $image_file_path)"); ?>
                                <?php endif; ?>
                            </td>
                            <td>SP<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?: 'Chưa có danh mục'); ?></td>
                            <td><?php echo number_format($row['price'], 0, ',', '.') . '₫'; ?></td>
                            <td><?php echo $stock_quantity; ?></td>
                            <td><span class="badge bg-success">Còn hàng</span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div id="out-of-stock" class="tab-pane <?php echo $tab === 'out-of-stock' ? 'active' : ''; ?>">
                <table class="table table-hover">
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
                    <?php
                    $result_inventory->data_seek(0);
                    while ($row = $result_inventory->fetch_assoc()):
                        $stock_quantity = $row['stock_quantity'];
                        if ($stock_quantity > 0) continue;
                        $image_value = $row['image'];
                        if (!empty($image_value)) {
                            $image_url = '/datn/' . $image_value;
                        } else {
                            $image_url = $image_default_url;
                        }
                        $image_file_path = $_SERVER['DOCUMENT_ROOT'] . '/datn' . str_replace('/datn', '', $image_url);
                        ?>
                        <tr>
                            <td>
                                <?php if (file_exists($image_file_path)): ?>
                                    <img src="<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($row['product_name']); ?>" class="product-image">
                                <?php else: ?>
                                    <span class="text-danger">Ảnh không tồn tại</span>
                                    <?php error_log("Ảnh không tồn tại: $image_url (Đường dẫn: $image_file_path)"); ?>
                                <?php endif; ?>
                            </td>
                            <td>SP<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?: 'Chưa có danh mục'); ?></td>
                            <td><?php echo number_format($row['price'], 0, ',', '.') . '₫'; ?></td>
                            <td><?php echo $stock_quantity; ?></td>
                            <td><span class="badge bg-danger">Hết hàng</span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$result_inventory->free();
$stmt_inventory->close();
$conn->close();
$conn_main->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>