<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Chuyển hướng đến login_view.php do chưa đăng nhập.");
    header("Location: ../login_view.php");
    exit();
}

// Kiểm tra quyền truy cập
if ($_SESSION['role'] !== 'admin') {
    error_log("Chuyển hướng đến index.php do không có quyền admin.");
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu fashion_shopp
function getCommonConnection($host, $username, $password) {
    $conn = new mysqli($host, $username, $password, 'fashion_shopp');
    if ($conn->connect_error) {
        error_log("Lỗi kết nối đến fashion_shopp: " . $conn->connect_error);
        die("Lỗi kết nối đến cơ sở dữ liệu chính: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Hàm lấy kết nối đến cơ sở dữ liệu của cơ sở hiện tại
function getShopConnection($host, $username, $password, $shop_db) {
    $conn = new mysqli($host, $username, $password, $shop_db);
    if ($conn->connect_error) {
        error_log("Lỗi kết nối đến cơ sở dữ liệu shop $shop_db: " . $conn->connect_error);
        die("Lỗi kết nối đến cơ sở dữ liệu shop: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$shop_name = $_SESSION['shop_name'] ?? 'Cửa hàng mặc định';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("Shop hiện tại: $shop_db, Tên shop: $shop_name, Username: $session_username");

// Kết nối đến cơ sở dữ liệu fashion_shopp (cho bảng category và flash_sale)
$conn_common = getCommonConnection($host, $username, $password);

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại (cho bảng product)
$conn_shop = getShopConnection($host, $username, $password, $shop_db);

// Truy vấn danh sách sản phẩm từ bảng product (shop_db) và category (fashion_shopp)
$sql_product = "SELECT p.id, p.name, p.price, c.name AS category_name, p.image 
                FROM `$shop_db`.product p 
                LEFT JOIN fashion_shopp.category c ON p.category_id = c.id";
$result_product = $conn_shop->query($sql_product);
if ($result_product === false) {
    error_log("Lỗi truy vấn sản phẩm: " . $conn_shop->error);
    die("Lỗi truy vấn sản phẩm: " . $conn_shop->error);
}

// Truy vấn danh sách chương trình khuyến mãi đang hoạt động từ fashion_shopp
$current_date = date('Y-m-d H:i:s');
$sql_flash_sales = "SELECT id, name 
                    FROM fashion_shopp.flash_sale 
                    WHERE start_date <= ? AND end_date >= ? AND status = 1";
$stmt_flash_sales = $conn_common->prepare($sql_flash_sales);
if ($stmt_flash_sales === false) {
    error_log("Lỗi chuẩn bị truy vấn flash_sale: " . $conn_common->error);
    die("Lỗi chuẩn bị truy vấn flash_sale: " . $conn_common->error);
}
$stmt_flash_sales->bind_param('ss', $current_date, $current_date);
$stmt_flash_sales->execute();
$result_flash_sales = $stmt_flash_sales->get_result();

// Lấy danh sách chương trình khuyến mãi đã áp dụng cho từng sản phẩm từ shop_db
$applied_flash_sales = [];
$sql_applied = "SELECT id, flash_sale_id 
                FROM `$shop_db`.product WHERE flash_sale_id IS NOT NULL";
$result_applied = $conn_shop->query($sql_applied);
if ($result_applied === false) {
    error_log("Lỗi truy vấn flash_sale đã áp dụng: " . $conn_shop->error);
    die("Lỗi truy vấn flash_sale đã áp dụng: " . $conn_shop->error);
}
while ($row = $result_applied->fetch_assoc()) {
    $applied_flash_sales[$row['id']] = $row['flash_sale_id'];
}

// Xử lý áp dụng chương trình khuyến mãi
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Nhận dữ liệu POST: " . print_r($_POST, true));
    if (!isset($_POST['product_ids']) || !isset($_POST['flash_sale_id']) || empty($_POST['product_ids'])) {
        $error = "Vui lòng chọn ít nhất một sản phẩm và một chương trình khuyến mãi!";
        error_log("Lỗi: Thiếu product_ids hoặc flash_sale_id.");
    } else {
        $product_ids = array_map('intval', $_POST['product_ids']); // Chuyển các product_id thành số nguyên
        $flash_sale_id = (int)$_POST['flash_sale_id'];
        error_log("Áp dụng flash_sale_id=$flash_sale_id cho product_ids=" . implode(',', $product_ids));

        // Kiểm tra xem flash_sale_id có hợp lệ không
        $sql_check_flash_sale = "SELECT id FROM fashion_shopp.flash_sale WHERE id = ? AND start_date <= ? AND end_date >= ? AND status = 1";
        $stmt_check_flash_sale = $conn_common->prepare($sql_check_flash_sale);
        if ($stmt_check_flash_sale === false) {
            $error = "Lỗi chuẩn bị truy vấn kiểm tra flash_sale: " . $conn_common->error;
            error_log("Lỗi chuẩn bị kiểm tra flash_sale: " . $conn_common->error);
        } else {
            $stmt_check_flash_sale->bind_param('iss', $flash_sale_id, $current_date, $current_date);
            $stmt_check_flash_sale->execute();
            $result_check_flash_sale = $stmt_check_flash_sale->get_result();
            if ($result_check_flash_sale->num_rows === 0) {
                $error = "Chương trình khuyến mãi không hợp lệ hoặc không hoạt động!";
                error_log("Lỗi: flash_sale_id $flash_sale_id không hợp lệ hoặc không hoạt động.");
            }
            $stmt_check_flash_sale->close();
        }

        // Nếu không có lỗi, tiếp tục cập nhật flash_sale_id trong bảng product
        if (empty($error)) {
            $sql_update_product = "UPDATE `$shop_db`.product SET flash_sale_id = ? WHERE id IN (" . implode(',', array_fill(0, count($product_ids), '?')) . ")";
            $stmt_update_product = $conn_shop->prepare($sql_update_product);
            if ($stmt_update_product === false) {
                $error = "Lỗi chuẩn bị truy vấn cập nhật product: " . $conn_shop->error;
                error_log("Lỗi chuẩn bị cập nhật product: " . $conn_shop->error);
            } else {
                $params = array_merge([$flash_sale_id], $product_ids);
                $types = 'i' . str_repeat('i', count($product_ids));
                $stmt_update_product->bind_param($types, ...$params);
                if (!$stmt_update_product->execute()) {
                    $error = "Lỗi khi cập nhật flash_sale_id trong bảng product: " . $stmt_update_product->error;
                    error_log("Lỗi cập nhật product: " . $stmt_update_product->error);
                } else {
                    $success = "Áp dụng chương trình khuyến mãi thành công cho " . count($product_ids) . " sản phẩm!";
                    error_log("Cập nhật thành công flash_sale_id=$flash_sale_id cho " . count($product_ids) . " sản phẩm.");
                    header("Location: apply_flash_sale.php?success=1");
                    exit();
                }
                $stmt_update_product->close();
            }
        }
    }
}

// Kiểm tra thông báo thành công từ query string
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Áp dụng chương trình khuyến mãi thành công!";
    // Cập nhật lại mảng $applied_flash_sales để hiển thị dữ liệu mới
    $result_applied = $conn_shop->query($sql_applied);
    if ($result_applied) {
        $applied_flash_sales = [];
        while ($row = $result_applied->fetch_assoc()) {
            $applied_flash_sales[$row['id']] = $row['flash_sale_id'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Áp dụng chương trình khuyến mãi - <?php echo htmlspecialchars($shop_name); ?></title>
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
            <li><a href="../controllers/FlashSaleController.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
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
            <h1>Áp dụng chương trình khuyến mãi - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
        </header>

        <!-- Thông báo -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Chọn chương trình khuyến mãi -->
                    <div class="mb-3">
                        <label for="flash_sale_id" class="form-label"><strong>Chọn chương trình khuyến mãi:</strong></label>
                        <select class="form-control" id="flash_sale_id" name="flash_sale_id" required>
                            <option value="">-- Chọn chương trình khuyến mãi --</option>
                            <?php while ($flash_sale = $result_flash_sales->fetch_assoc()): ?>
                                <option value="<?php echo $flash_sale['id']; ?>">
                                    <?php echo htmlspecialchars($flash_sale['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Danh sách sản phẩm -->
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>ID</th>
                            <th>Tên sản phẩm</th>
                            <th>Giá (VNĐ)</th>
                            <th>Danh mục</th>
                            <th>Hình ảnh</th>
                            <th>Chương trình khuyến mãi hiện tại</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($result_product->num_rows > 0): ?>
                            <?php while ($product = $result_product->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="product_ids[]" value="<?php echo $product['id']; ?>" class="product-checkbox">
                                    </td>
                                    <td><?php echo htmlspecialchars($product['id']); ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($product['image']); ?>" alt="Hình ảnh sản phẩm" width="50">
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (isset($applied_flash_sales[$product['id']])) {
                                            $flash_sale_id = $applied_flash_sales[$product['id']];
                                            $sql_flash_sale_name = "SELECT name FROM fashion_shopp.flash_sale WHERE id = ?";
                                            $stmt_flash_sale_name = $conn_common->prepare($sql_flash_sale_name);
                                            if ($stmt_flash_sale_name === false) {
                                                error_log("Lỗi chuẩn bị truy vấn flash_sale_name: " . $conn_common->error);
                                                echo 'Lỗi truy vấn';
                                            } else {
                                                $stmt_flash_sale_name->bind_param('i', $flash_sale_id);
                                                $stmt_flash_sale_name->execute();
                                                $result_flash_sale_name = $stmt_flash_sale_name->get_result();
                                                if ($row = $result_flash_sale_name->fetch_assoc()) {
                                                    echo htmlspecialchars($row['name']);
                                                } else {
                                                    echo 'N/A';
                                                }
                                                $stmt_flash_sale_name->close();
                                            }
                                        } else {
                                            echo 'Chưa áp dụng';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Không có sản phẩm nào.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <button type="submit" class="btn btn-primary">Áp dụng</button>
                    <a href="../controllers/FlashSaleController.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$result_product->free();
$result_flash_sales->free();
$result_applied->free();
$conn_common->close();
$conn_shop->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Chọn tất cả checkbox
    document.getElementById('select-all').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.product-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
</script>
</body>
</html>