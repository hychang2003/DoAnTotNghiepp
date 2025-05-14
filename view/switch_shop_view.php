<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit();
}

include '../config/db_connect.php';
$conn = new mysqli($host, $username, $password, 'fashion_shopp');
if ($conn->connect_error) {
    $error = "Lỗi kết nối tới cơ sở dữ liệu: " . $conn->connect_error;
} else {
    $conn->set_charset("utf8mb4");
}

$shops = [];
if (!isset($error)) {
    $sql = "SELECT id, name, db_name FROM shop";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $shops[] = $row;
        }
        $result->free();
    } else {
        $error = "Lỗi khi lấy danh sách cơ sở: " . $conn->error;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_shop'])) {
    $shop_db = trim($_POST['shop_db'] ?? '');

    if (empty($shop_db)) {
        $error = "Vui lòng chọn một cơ sở!";
    } else {
        $valid_shop = false;
        foreach ($shops as $shop) {
            if ($shop['db_name'] === $shop_db) {
                $valid_shop = true;
                break;
            }
        }

        if ($valid_shop) {
            $_SESSION['shop_db'] = $shop_db;
            header("Location: ../index.php?shop_switched=success");
            exit();
        } else {
            $error = "Cơ sở không hợp lệ!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chuyển đổi cơ sở</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSB7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Fallback cục bộ cho Font Awesome -->
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css" onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css';">
</head>
<body>
<div id="main">
    <div id="sidebar" class="shadow">
        <div class="logo">
            <img src="../img/logo/logo.png" alt="Logo">
        </div>
        <button id="sidebarToggle"><i class="fas fa-arrow-left"></i></button>
        <ul class="list-unstyled p-3">
            <li><a href="../index.php"><i class="fas fa-chart-line"></i> Tổng quan</a></li>
            <li class="has-dropdown">
                <a href="#" id="productMenu"><i class="fas fa-box"></i> Sản phẩm <i class="fas fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="products_list_view.php">Danh sách sản phẩm</a></li>
                    <li><a href="../view/product_category.php">Danh mục sản phẩm</a></li>
                </ul>
            </li>
            <li><a href="../view/order.php"><i class="fas fa-file-invoice-dollar"></i> Hóa đơn</a></li>
            <li class="has-dropdown">
                <a href="#" id="shopMenu"><i class="fas fa-store"></i> Quản lý shop <i class="fas fa-chevron-down ms-auto"></i></a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="inventory_stock_view.php">Tồn kho</a></li>
                    <li><a href="../view/import_goods.php">Nhập hàng</a></li>
                    <li><a href="../view/export_goods.php">Xuất hàng</a></li>
                </ul>
            </li>
            <li><a href="../view/customer.php"><i class="fas fa-users"></i> Khách hàng</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="../view/employee.php"><i class="fas fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="flash_sale_view.php"><i class="fas fa-tags"></i> Khuyến mại</a></li>
            <li><a href="report_view.php"><i class="fas fa-chart-bar"></i> Báo cáo</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="../view/switch_shop_view.php"><i class="fas fa-exchange-alt"></i> Switch Cơ Sở</a></li>
                <li><a href="../view/add_shop.php"><i class="fas fa-plus-circle"></i> Thêm Cơ Sở</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div id="header" class="bg-light py-2 shadow-sm">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="input-group w-50">
                <input type="text" class="form-control" placeholder="Tìm kiếm...">
                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="../img/avatar/avatar.png" alt="Avatar" class="rounded-circle me-2" width="40" height="40">
                    <span class="fw-bold"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Khách'; ?></span>
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
            <h1>Chuyển đổi cơ sở</h1>
        </header>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="switch_shop" value="1">
                    <div class="mb-3">
                        <label for="shop_db" class="form-label">Chọn cơ sở</label>
                        <select class="form-control" id="shop_db" name="shop_db" required>
                            <option value="">Chọn cơ sở</option>
                            <?php foreach ($shops as $shop): ?>
                                <option value="<?php echo htmlspecialchars($shop['db_name']); ?>" <?php echo (isset($_SESSION['shop_db']) && $shop['db_name'] === $_SESSION['shop_db']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($shop['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Chuyển đổi</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>