<?php
session_start();

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
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại
$conn = getShopConnection($host, $username, $password, $shop_db);

// Lấy thông tin chương trình khuyến mãi để hiển thị trong form
if (!isset($_GET['id'])) {
    header("Location: flash_sale.php?error=" . urlencode("Không tìm thấy ID chương trình khuyến mãi."));
    exit();
}

$flash_sale_id = $_GET['id'];
$sql_flash_sale = "SELECT * FROM flash_sale WHERE id = ?";
$stmt_flash_sale = $conn->prepare($sql_flash_sale);
$stmt_flash_sale->bind_param('i', $flash_sale_id);
$stmt_flash_sale->execute();
$result_flash_sale = $stmt_flash_sale->get_result();
$flash_sale = $result_flash_sale->fetch_assoc();
$stmt_flash_sale->close();

if (!$flash_sale) {
    header("Location: flash_sale.php?error=" . urlencode("Chương trình khuyến mãi không tồn tại."));
    exit();
}

// Xử lý cập nhật chương trình khuyến mãi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_flash_sale'])) {
    $name = $_POST['name'] ?? '';
    $discount = $_POST['discount'] ?? 0;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? 0;

    // Kiểm tra dữ liệu đầu vào
    if (empty($name) || $discount <= 0 || empty($start_date) || empty($end_date)) {
        $error = "Vui lòng nhập đầy đủ thông tin chương trình khuyến mãi.";
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $error = "Ngày kết thúc phải sau ngày bắt đầu.";
    } else {
        // Cập nhật chương trình khuyến mãi
        $sql = "UPDATE flash_sale SET name = ?, discount = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sdssii', $name, $discount, $start_date, $end_date, $status, $flash_sale_id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: flash_sale.php?flash_sale_updated=success");
            exit();
        } else {
            $error = "Lỗi khi cập nhật chương trình khuyến mãi: " . $stmt->error;
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
    <title>Cập nhật chương trình khuyến mãi</title>
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
            <h1>Cập nhật chương trình khuyến mãi</h1>
        </header>

        <!-- Thông báo lỗi -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Form cập nhật chương trình khuyến mãi -->
        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="update_flash_sale" value="1">
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên chương trình</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($flash_sale['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="discount" class="form-label">Giảm giá (%)</label>
                        <input type="number" class="form-control" id="discount" name="discount" value="<?php echo $flash_sale['discount']; ?>" step="0.01" min="0" max="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Ngày bắt đầu</label>
                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" value="<?php echo date('Y-m-d\TH:i', strtotime($flash_sale['start_date'])); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_date" class="form-label">Ngày kết thúc</label>
                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" value="<?php echo date('Y-m-d\TH:i', strtotime($flash_sale['end_date'])); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Trạng thái</label>
                        <select class="form-control" id="status" name="status">
                            <option value="1" <?php echo $flash_sale['status'] == 1 ? 'selected' : ''; ?>>Hoạt động</option>
                            <option value="0" <?php echo $flash_sale['status'] == 0 ? 'selected' : ''; ?>>Không hoạt động</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Cập nhật chương trình</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$conn->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>