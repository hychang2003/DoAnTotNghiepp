<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh'); // Thiết lập múi giờ Việt Nam

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
$conn = getShopConnection($host, $username, $password, $shop_db);

// Truy vấn danh sách danh mục
$sql_categories = "SELECT id, name FROM category";
$result_categories = $conn->query($sql_categories);
if ($result_categories === false) {
    die("Lỗi truy vấn danh mục: " . $conn->error);
}

// Truy vấn danh sách chương trình khuyến mãi (lấy tất cả để hiển thị)
$sql_flash_sales = "SELECT id, name FROM flash_sale";
$result_flash_sales = $conn->query($sql_flash_sales);
if ($result_flash_sales === false) {
    die("Lỗi truy vấn flash_sale: " . $conn->error);
}

// Xử lý thêm sản phẩm
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $type = trim($_POST['type'] ?? 'general');
    $unit = trim($_POST['unit'] ?? '');
    $flash_sale_id = !empty($_POST['flash_sale_id']) ? intval($_POST['flash_sale_id']) : null;
    $image = '';
    $create_date = date('Y-m-d H:i:s');
    $update_date = date('Y-m-d H:i:s');
    $employee_id = $_SESSION['user_id'] ?? 1;

    // Xử lý upload ảnh
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_file_name = time() . '_' . rand(1, 1000) . '.' . $file_extension;
        $image = 'assets/images/' . $new_file_name;
        $upload_path = '../' . $image;
        $upload_dir = '../assets/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $error = "Lỗi khi upload ảnh sản phẩm!";
        }
    }

    // Kiểm tra dữ liệu đầu vào
    if (empty($name) || $price <= 0 || $category_id <= 0 || empty($unit)) {
        $error = "Vui lòng nhập đầy đủ thông tin sản phẩm!";
    } else {
        // Thêm sản phẩm vào bảng product
        $sql = "INSERT INTO product (name, description, create_date, update_date, employee_id, image, category_id, type, unit, price, cost_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssisissdd', $name, $description, $create_date, $update_date, $employee_id, $image, $category_id, $type, $unit, $price, $cost_price);
        if ($stmt->execute()) {
            $product_id = $stmt->insert_id;

            // Nếu có chọn chương trình khuyến mãi, lưu vào bảng product_flash_sale
            if ($flash_sale_id) {
                $sql_check_flash_sale = "SELECT id FROM flash_sale WHERE id = ? AND start_date <= NOW() AND end_date >= NOW()";
                $stmt_check_flash_sale = $conn->prepare($sql_check_flash_sale);
                $stmt_check_flash_sale->bind_param('i', $flash_sale_id);
                $stmt_check_flash_sale->execute();
                $result_check_flash_sale = $stmt_check_flash_sale->get_result();

                if ($result_check_flash_sale->num_rows > 0) {
                    $sql_insert_flash_sale = "INSERT INTO product_flash_sale (product_id, flash_sale_id) VALUES (?, ?)";
                    $stmt_flash_sale = $conn->prepare($sql_insert_flash_sale);
                    $stmt_flash_sale->bind_param('ii', $product_id, $flash_sale_id);
                    if (!$stmt_flash_sale->execute()) {
                        $error = "Lỗi khi áp dụng chương trình khuyến mãi: " . $stmt_flash_sale->error;
                    }
                    $stmt_flash_sale->close();
                } else {
                    $error = "Chương trình khuyến mãi không hợp lệ hoặc đã hết hạn!";
                }
                $stmt_check_flash_sale->close();
            }

            if (empty($error)) {
                $success = "Thêm sản phẩm thành công!";
                header("Location: products_list.php?added=success");
                exit();
            }
        } else {
            $error = "Lỗi khi thêm sản phẩm: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sản phẩm mới</title>
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
            <h1>Thêm sản phẩm mới</h1>
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

        <!-- Form thêm sản phẩm -->
        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên sản phẩm</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="description" name="description" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Giá bán (VNĐ)</label>
                        <input type="number" step="0.01" class="form-control" id="price" name="price" required>
                    </div>
                    <div class="mb-3">
                        <label for="cost_price" class="form-label">Giá nhập (VNĐ)</label>
                        <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Danh mục</label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">-- Chọn danh mục --</option>
                            <?php while ($category = $result_categories->fetch_assoc()): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="unit" class="form-label">Đơn vị</label>
                        <input type="text" class="form-control" id="unit" name="unit" required>
                    </div>
                    <div class="mb-3">
                        <label for="flash_sale_id" class="form-label">Chương trình khuyến mãi</label>
                        <select class="form-control" id="flash_sale_id" name="flash_sale_id">
                            <option value="">-- Không áp dụng --</option>
                            <?php while ($flash_sale = $result_flash_sales->fetch_assoc()): ?>
                                <option value="<?php echo $flash_sale['id']; ?>">
                                    <?php echo htmlspecialchars($flash_sale['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Hình ảnh</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-primary">Thêm sản phẩm</button>
                    <a href="products_list.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$result_categories->free();
$result_flash_sales->free();
$conn->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>