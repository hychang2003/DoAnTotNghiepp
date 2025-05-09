<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Hàm lấy kết nối đến cơ sở dữ liệu
function getConnection($host, $username, $password, $db) {
    $conn = new mysqli($host, $username, $password, $db);
    if ($conn->connect_error) {
        error_log("Lỗi kết nối đến $db: " . $conn->connect_error);
        die("Lỗi kết nối cơ sở dữ liệu.");
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';

// Kết nối đến cơ sở dữ liệu
$conn = getConnection($host, $username, $password, $shop_db);
$conn_common = getConnection($host, $username, $password, 'fashion_shopp');

// Lấy thông tin sản phẩm
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: products_list_view.php?error=" . urlencode("ID sản phẩm không hợp lệ."));
    exit();
}

$product_id = (int)$_GET['id'];
$sql_product = "SELECT * FROM product WHERE id = ?";
$stmt_product = $conn->prepare($sql_product);
$stmt_product->bind_param('i', $product_id);
$stmt_product->execute();
$result_product = $stmt_product->get_result();
$product = $result_product->fetch_assoc();
$stmt_product->close();

if (!$product) {
    header("Location: products_list_view.php?error=" . urlencode("Sản phẩm không tồn tại."));
    exit();
}

// Lấy danh sách danh mục từ fashion_shopp
$sql_categories = "SELECT id, name FROM category ORDER BY name";
$result_categories = $conn_common->query($sql_categories);
if ($result_categories === false) {
    error_log("Lỗi truy vấn danh mục: " . $conn_common->error);
    $error = "Lỗi khi lấy danh mục.";
}

// Lấy danh sách flash sale từ fashion_shopp
$sql_flash_sales = "SELECT id, name FROM flash_sale ORDER BY name";
$result_flash_sales = $conn_common->query($sql_flash_sales);
if ($result_flash_sales === false) {
    error_log("Lỗi truy vấn flash sale: " . $conn_common->error);
    $error = "Lỗi khi lấy chương trình khuyến mãi.";
}

// Xử lý cập nhật sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $type = trim($_POST['type'] ?? 'general');
    $flash_sale_id = !empty($_POST['flash_sale_id']) ? (int)$_POST['flash_sale_id'] : null;

    // Xử lý ảnh
    $image = $product['image'];
    if (!empty($_FILES['image']['name'])) {
        $target_dir = "../assets/images/";
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $target_file = $target_dir . $image_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image = "assets/images/" . $image_name;
            // Xóa ảnh cũ nếu có
            if ($product['image'] && file_exists("../" . $product['image'])) {
                unlink("../" . $product['image']);
            }
        } else {
            $error = "Lỗi khi tải lên ảnh.";
        }
    }

    // Kiểm tra dữ liệu đầu vào
    if (empty($name) || $category_id <= 0 || $price <= 0 || empty($unit)) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc.";
    } else {
        // Cập nhật sản phẩm
        $sql = "UPDATE product SET name = ?, category_id = ?, price = ?, cost_price = ?, description = ?, unit = ?, type = ?, flash_sale_id = ?, image = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('siddsssisi', $name, $category_id, $price, $cost_price, $description, $unit, $type, $flash_sale_id, $image, $product_id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: products_list_view.php?success=" . urlencode("Cập nhật sản phẩm thành công."));
            exit();
        } else {
            $error = "Lỗi khi cập nhật sản phẩm: " . $stmt->error;
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật sản phẩm</title>
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
                    <li><a href="product_category.php">Danh mục sản phẩm</a></li>
                </ul>
            </li>
            <li><a href="order.php"><i class="fa fa-file-invoice-dollar"></i> Hóa đơn</a></li>
            <li class="has-dropdown">
                <a href="#" id="shopMenu"><i class="fa fa-store"></i> Quản lý shop <i class="fa fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="inventory_stock_view.php">Tồn kho</a></li>
                    <li><a href="import_goods.php">Nhập hàng</a></li>
                    <li><a href="export_goods.php">Xuất hàng</a></li>
                </ul>
            </li>
            <li><a href="customer.php"><i class="fa fa-users"></i> Khách hàng</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="employee.php"><i class="fa fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="flash_sale_view.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
            <li><a href="report_view.php"><i class="fa fa-chart-bar"></i> Báo cáo</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="switch_shop_view.php"><i class="fa fa-exchange-alt"></i> Switch Cơ Sở</a></li>
                <li><a href="add_shop.php"><i class="fa fa-plus-circle"></i> Thêm Cơ Sở</a></li>
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
            <h1>Cập nhật sản phẩm</h1>
        </header>

        <!-- Thông báo lỗi -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Form cập nhật sản phẩm -->
        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="update_product" value="1">
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên sản phẩm</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Danh mục</label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Chọn danh mục</option>
                            <?php while ($row = $result_categories->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo $row['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Giá (VNĐ)</label>
                        <input type="number" class="form-control" id="price" name="price" value="<?php echo $product['price']; ?>" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="cost_price" class="form-label">Giá vốn (VNĐ)</label>
                        <input type="number" class="form-control" id="cost_price" name="cost_price" value="<?php echo $product['cost_price']; ?>" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label for="unit" class="form-label">Đơn vị</label>
                        <input type="text" class="form-control" id="unit" name="unit" value="<?php echo htmlspecialchars($product['unit']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Loại sản phẩm</label>
                        <select class="form-control" id="type" name="type">
                            <option value="general" <?php echo $product['type'] == 'general' ? 'selected' : ''; ?>>General</option>
                            <option value="special" <?php echo $product['type'] == 'special' ? 'selected' : ''; ?>>Special</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="flash_sale_id" class="form-label">Chương trình khuyến mãi</label>
                        <select class="form-control" id="flash_sale_id" name="flash_sale_id">
                            <option value="">Không áp dụng</option>
                            <?php while ($row = $result_flash_sales->fetch_assoc()): ?>
                                <option value="<?php echo $row['id']; ?>" <?php echo $row['id'] == $product['flash_sale_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="description" name="description"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Hình ảnh</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <?php if ($product['image']): ?>
                            <img src="/datn/<?php echo htmlspecialchars($product['image']); ?>" alt="Hình ảnh sản phẩm" width="100" class="mt-2">
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary">Cập nhật sản phẩm</button>
                    <a href="products_list_view.php" class="btn btn-secondary">Hủy</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$result_categories->free();
if ($result_flash_sales) $result_flash_sales->free();
$conn->close();
$conn_common->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>