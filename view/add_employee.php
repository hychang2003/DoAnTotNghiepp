<?php
session_start();

// Kiểm tra trạng thái đăng nhập và quyền admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ cho PHP
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Debug: Kiểm tra username được sử dụng
echo "Username được sử dụng: " . $username . "<br>";

// Hàm lấy kết nối đến cơ sở dữ liệu
function getConnection($host, $username, $password, $dbname) {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Lỗi: Không thể kết nối đến database: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Kết nối đến cơ sở dữ liệu chính (fashion_shop)
$conn_main = getConnection($host, $username, $password, 'fashion_shop');

// Xử lý thêm nhân viên
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'employee';

    // Kiểm tra dữ liệu đầu vào
    if (empty($username) || empty($password) || empty($name) || empty($email)) {
        $error = "Vui lòng nhập đầy đủ thông tin bắt buộc (Tên đăng nhập, Mật khẩu, Họ và tên, Email).";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ.";
    } else {
        // Bắt đầu giao dịch
        $conn_main->begin_transaction();
        try {
            // Kiểm tra username đã tồn tại chưa trong bảng users
            $sql_check = "SELECT id FROM users WHERE username = ?";
            $stmt_check = $conn_main->prepare($sql_check);
            if ($stmt_check === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra username: " . $conn_main->error);
            }
            $stmt_check->bind_param('s', $username);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                throw new Exception("Tên đăng nhập đã tồn tại.");
            }
            $stmt_check->close();

            // Kiểm tra email đã tồn tại chưa trong bảng users
            $sql_check_email = "SELECT id FROM users WHERE email = ?";
            $stmt_check_email = $conn_main->prepare($sql_check_email);
            if ($stmt_check_email === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra email: " . $conn_main->error);
            }
            $stmt_check_email->bind_param('s', $email);
            $stmt_check_email->execute();
            $result_check_email = $stmt_check_email->get_result();
            if ($result_check_email->num_rows > 0) {
                throw new Exception("Email đã tồn tại trong bảng users.");
            }
            $stmt_check_email->close();

            // Thêm vào bảng users (trong fashion_shop)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql_user = "INSERT INTO users (name, phone_number, email, username, password, role) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_user = $conn_main->prepare($sql_user);
            if ($stmt_user === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn users: " . $conn_main->error);
            }
            $stmt_user->bind_param('ssssss', $name, $phone, $email, $username, $hashed_password, $role);
            if (!$stmt_user->execute()) {
                throw new Exception("Lỗi khi thêm vào bảng users: " . $stmt_user->error);
            }
            $stmt_user->close();

            // Commit giao dịch
            $conn_main->commit();
            header("Location: employee.php?employee_added=success");
            exit();
        } catch (Exception $e) {
            $conn_main->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm nhân viên mới</title>
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
            <h1>Thêm nhân viên mới</h1>
        </header>

        <!-- Thông báo lỗi -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Form thêm nhân viên -->
        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="add_employee" value="1">
                    <div class="mb-3">
                        <label for="username" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Nhập tên đăng nhập" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Nhập mật khẩu" required>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Nhập họ và tên" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Nhập email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Số điện thoại</label>
                        <input type="text" class="form-control" id="phone" name="phone" placeholder="Nhập số điện thoại" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Vai trò</label>
                        <select class="form-control" id="role" name="role">
                            <option value="employee" <?php echo (isset($_POST['role']) && $_POST['role'] == 'employee') ? 'selected' : ''; ?>>Nhân viên</option>
                            <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Quản trị viên</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Thêm nhân viên</button>
                    <a href="employee.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$conn_main->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>