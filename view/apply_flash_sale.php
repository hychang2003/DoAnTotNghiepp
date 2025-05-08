<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

// Kiểm tra quyền truy cập
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

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
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại
$conn = getShopConnection($host, $username, $password, $shop_db);

// Truy vấn danh sách sản phẩm từ bảng product
$sql_product = "SELECT p.id, p.name, p.price, c.name AS category_name, p.image 
                FROM product p 
                LEFT JOIN category c ON p.category_id = c.id";
$result_product = $conn->query($sql_product);
if ($result_product === false) {
    die("Lỗi truy vấn sản phẩm: " . $conn->error);
}

// Truy vấn danh sách chương trình khuyến mãi đang hoạt động
$current_date = date('Y-m-d H:i:s');
$sql_flash_sales = "SELECT id, name 
                    FROM flash_sale 
                    WHERE start_date <= ? AND end_date >= ?";
$stmt_flash_sales = $conn->prepare($sql_flash_sales);
$stmt_flash_sales->bind_param('ss', $current_date, $current_date);
$stmt_flash_sales->execute();
$result_flash_sales = $stmt_flash_sales->get_result();

// Lấy danh sách chương trình khuyến mãi đã áp dụng cho từng sản phẩm
$applied_flash_sales = [];
$sql_applied = "SELECT product_id, flash_sale_id 
                FROM product_flash_sale";
$result_applied = $conn->query($sql_applied);
if ($result_applied) {
    while ($row = $result_applied->fetch_assoc()) {
        $applied_flash_sales[$row['product_id']] = $row['flash_sale_id'];
    }
}

// Xử lý áp dụng chương trình khuyến mãi
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['product_ids']) || !isset($_POST['flash_sale_id']) || empty($_POST['product_ids'])) {
        $error = "Vui lòng chọn ít nhất một sản phẩm và một chương trình khuyến mãi!";
    } else {
        $product_ids = array_map('intval', $_POST['product_ids']); // Chuyển các product_id thành số nguyên
        $flash_sale_id = (int)$_POST['flash_sale_id'];

        // Xóa các chương trình khuyến mãi cũ của các sản phẩm được chọn
        $sql_delete = "DELETE FROM product_flash_sale WHERE product_id IN (" . implode(',', array_fill(0, count($product_ids), '?')) . ")";
        $stmt_delete = $conn->prepare($sql_delete);
        if ($stmt_delete === false) {
            $error = "Lỗi chuẩn bị truy vấn xóa: " . $conn->error;
        } else {
            $stmt_delete->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
            if (!$stmt_delete->execute()) {
                $error = "Lỗi khi xóa chương trình khuyến mãi cũ: " . $stmt_delete->error;
            }
            $stmt_delete->close();
        }

        // Cập nhật flash_sale_id trong bảng product thành NULL cho các sản phẩm được chọn
        if (empty($error)) {
            $sql_update_product = "UPDATE product SET flash_sale_id = NULL WHERE id IN (" . implode(',', array_fill(0, count($product_ids), '?')) . ")";
            $stmt_update_product = $conn->prepare($sql_update_product);
            if ($stmt_update_product === false) {
                $error = "Lỗi chuẩn bị truy vấn cập nhật product: " . $conn->error;
            } else {
                $stmt_update_product->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
                if (!$stmt_update_product->execute()) {
                    $error = "Lỗi khi cập nhật flash_sale_id trong bảng product: " . $stmt_update_product->error;
                }
                $stmt_update_product->close();
            }
        }

        // Nếu không có lỗi, tiếp tục thêm chương trình khuyến mãi mới
        if (empty($error)) {
            $sql_insert = "INSERT INTO product_flash_sale (product_id, flash_sale_id) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert === false) {
                $error = "Lỗi chuẩn bị truy vấn thêm: " . $conn->error;
            } else {
                $success_count = 0;
                foreach ($product_ids as $product_id) {
                    $stmt_insert->bind_param('ii', $product_id, $flash_sale_id);
                    if ($stmt_insert->execute()) {
                        $success_count++;

                        // Cập nhật flash_sale_id trong bảng product
                        $sql_update_flash_sale = "UPDATE product SET flash_sale_id = ? WHERE id = ?";
                        $stmt_update_flash_sale = $conn->prepare($sql_update_flash_sale);
                        $stmt_update_flash_sale->bind_param('ii', $flash_sale_id, $product_id);
                        if (!$stmt_update_flash_sale->execute()) {
                            $error = "Lỗi khi cập nhật flash_sale_id cho sản phẩm ID $product_id: " . $stmt_update_flash_sale->error;
                            break;
                        }
                        $stmt_update_flash_sale->close();
                    } else {
                        $error = "Lỗi khi thêm chương trình khuyến mãi cho sản phẩm ID $product_id: " . $stmt_insert->error;
                        break;
                    }
                }
                $stmt_insert->close();

                // Kiểm tra xem có sản phẩm nào được áp dụng thành công không
                if ($success_count > 0 && empty($error)) {
                    header("Location: apply_flash_sale.php?success=1");
                    exit();
                }
            }
        }
    }
}

// Kiểm tra thông báo thành công từ query string
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Áp dụng chương trình khuyến mãi thành công!";
    // Cập nhật lại mảng $applied_flash_sales để hiển thị dữ liệu mới
    $result_applied = $conn->query($sql_applied);
    if ($result_applied) {
        $applied_flash_sales = [];
        while ($row = $result_applied->fetch_assoc()) {
            $applied_flash_sales[$row['product_id']] = $row['flash_sale_id'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Áp dụng chương trình khuyến mãi</title>
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
            <h1>Áp dụng chương trình khuyến mãi</h1>
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
                                            $sql_flash_sale_name = "SELECT name FROM flash_sale WHERE id = ?";
                                            $stmt_flash_sale_name = $conn->prepare($sql_flash_sale_name);
                                            $stmt_flash_sale_name->bind_param('i', $flash_sale_id);
                                            $stmt_flash_sale_name->execute();
                                            $result_flash_sale_name = $stmt_flash_sale_name->get_result();
                                            if ($row = $result_flash_sale_name->fetch_assoc()) {
                                                echo htmlspecialchars($row['name']);
                                            } else {
                                                echo 'N/A';
                                            }
                                            $stmt_flash_sale_name->close();
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
                    <a href="flash_sale_view.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$result_product->free();
$result_flash_sales->free();
$conn->close();
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