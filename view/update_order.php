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
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Kết nối đến cơ sở dữ liệu của cơ sở hiện tại
$conn = getShopConnection($host, $username, $password, $shop_db);

// Hàm định dạng tiền tệ
function formatCurrency($number) {
    $formatted = number_format($number, 2, ',', '.');
    return rtrim(rtrim($formatted, '0'), ',');
}

// Lấy danh sách khách hàng
$sql_customers = "SELECT id, name FROM customer";
$result_customers = $conn->query($sql_customers);

// Lấy danh sách nhân viên
$sql_employees = "SELECT id, name FROM employee";
$result_employees = $conn->query($sql_employees);

// Lấy thông tin đơn hàng cần sửa
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$sql_order = "SELECT * FROM `order` WHERE id = ?";
$stmt_order = $conn->prepare($sql_order);
if ($stmt_order === false) {
    die("Lỗi chuẩn bị truy vấn đơn hàng: " . $conn->error);
}
$stmt_order->bind_param('i', $order_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();
$order = $result_order->fetch_assoc();

// Xử lý cập nhật đơn hàng
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $order_date = $_POST['order_date'] ?? '';
    $total_price = floatval($_POST['total_price'] ?? 0);
    $status = $_POST['status'] ?? 'pending';

    // Kiểm tra dữ liệu đầu vào
    if ($customer_id <= 0 || empty($order_date) || $total_price <= 0) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } else {
        // Cập nhật đơn hàng
        $sql_update = "UPDATE `order` SET customer_id = ?, employee_id = ?, order_date = ?, total_price = ?, status = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update === false) {
            $error = "Lỗi chuẩn bị truy vấn cập nhật: " . $conn->error;
        } else {
            $stmt_update->bind_param('iissdi', $customer_id, $employee_id, $order_date, $total_price, $status, $order_id);
            if ($stmt_update->execute()) {
                $success = "Cập nhật đơn hàng thành công!";
            } else {
                $error = "Lỗi khi cập nhật đơn hàng: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật đơn hàng</title>
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
            <h1>Cập nhật đơn hàng</h1>
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
                <?php if ($order): ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="customer_id" class="form-label">Khách hàng</label>
                            <select class="form-control" id="customer_id" name="customer_id" required>
                                <?php while ($customer = $result_customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['id']; ?>" <?php echo $customer['id'] == $order['customer_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Nhân viên</label>
                            <select class="form-control" id="employee_id" name="employee_id">
                                <option value="">-- Không chọn --</option>
                                <?php while ($employee = $result_employees->fetch_assoc()): ?>
                                    <option value="<?php echo $employee['id']; ?>" <?php echo $employee['id'] == $order['employee_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="order_date" class="form-label">Ngày đặt hàng</label>
                            <input type="datetime-local" class="form-control" id="order_date" name="order_date" value="<?php echo date('Y-m-d\TH:i', strtotime($order['order_date'])); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="total_price" class="form-label">Tổng tiền (VNĐ)</label>
                            <input type="number" step="0.01" class="form-control" id="total_price" name="total_price" value="<?php echo $order['total_price']; ?>" required>
                            <small class="form-text text-muted">Hiển thị: <?php echo formatCurrency($order['total_price']); ?> VNĐ</small>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Trạng thái</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Đang xử lý</option>
                                <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Hủy</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Cập nhật</button>
                        <a href="order.php" class="btn btn-secondary">Quay lại</a>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">Không tìm thấy đơn hàng!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$result_customers->free();
$result_employees->free();
$result_order->free();
$conn->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>