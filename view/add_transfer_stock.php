<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

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
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Kết nối đến cơ sở dữ liệu chính và shop
$conn_main = getConnection($host, $username, $password, 'fashion_shop');
$conn = getConnection($host, $username, $password, $shop_db);

// Lấy ID của shop hiện tại
$sql_from_shop = "SELECT id FROM shop WHERE db_name = ?";
$stmt_from_shop = $conn_main->prepare($sql_from_shop);
$stmt_from_shop->bind_param('s', $shop_db);
$stmt_from_shop->execute();
$result_from_shop = $stmt_from_shop->get_result();
$from_shop_row = $result_from_shop->fetch_assoc();
$from_shop_id = $from_shop_row['id'] ?? 0;
$stmt_from_shop->close();

// Lấy danh sách shop (trừ shop hiện tại)
$sql_shops = "SELECT id, name, db_name FROM shop WHERE db_name != ?";
$stmt_shops = $conn_main->prepare($sql_shops);
$stmt_shops->bind_param('s', $shop_db);
$stmt_shops->execute();
$result_shops = $stmt_shops->get_result();

// Lấy danh sách sản phẩm và tồn kho
$sql_products = "SELECT p.id, p.name, p.price, i.quantity 
                FROM `$shop_db`.product p 
                LEFT JOIN `$shop_db`.inventory i ON p.id = i.product_id";
$result_products = $conn->query($sql_products);
if ($result_products === false) {
    die("Lỗi truy vấn sản phẩm: " . $conn->error);
}

// Lấy danh sách nhân viên
$sql_employees = "SELECT id, name FROM `$shop_db`.employee";
$result_employees = $conn->query($sql_employees);
if ($result_employees === false) {
    die("Lỗi truy vấn nhân viên: " . $conn->error);
}

// Xử lý tạo đơn chuyển kho
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_shop_id = intval($_POST['to_shop_id'] ?? 0);
    $employee_id = intval($_POST['employee_id'] ?? 0);
    $transfer_date = $_POST['transfer_date'] ?? '';
    $products = $_POST['products'] ?? [];

    // Lấy db_name của shop nhận
    $sql_to_shop = "SELECT db_name FROM shop WHERE id = ?";
    $stmt_to_shop = $conn_main->prepare($sql_to_shop);
    $stmt_to_shop->bind_param('i', $to_shop_id);
    $stmt_to_shop->execute();
    $result_to_shop = $stmt_to_shop->get_result();
    $to_shop_row = $result_to_shop->fetch_assoc();
    $to_shop_db = $to_shop_row['db_name'] ?? '';
    $stmt_to_shop->close();

    // Kiểm tra dữ liệu đầu vào
    if (empty($to_shop_id) || empty($to_shop_db) || empty($employee_id) || empty($transfer_date) || empty($products)) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } else {
        // Kết nối đến cơ sở dữ liệu của shop nhận
        $conn_to_shop = getConnection($host, $username, $password, $to_shop_db);

        // Bắt đầu giao dịch
        $conn->begin_transaction();
        $conn_to_shop->begin_transaction();

        try {
            // Tạo đơn chuyển kho
            $sql_insert_transfer = "INSERT INTO `$shop_db`.transfer_stock (product_id, from_shop_id, to_shop_id, quantity, transfer_date, employee_id, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())";
            $stmt_insert_transfer = $conn->prepare($sql_insert_transfer);
            if ($stmt_insert_transfer === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn chuyển kho: " . $conn->error);
            }

            // Tạo đơn xuất hàng trong shop hiện tại
            $sql_insert_export = "INSERT INTO `$shop_db`.export_goods (export_date, total_price, quantity, unit_price, employee_id, product_id, transfer_id, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt_insert_export = $conn->prepare($sql_insert_export);
            if ($stmt_insert_export === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xuất hàng: " . $conn->error);
            }

            // Tạo đơn nhập hàng trong shop nhận
            $sql_insert_import = "INSERT INTO `$to_shop_db`.import_goods (import_date, total_price, quantity, unit_price, employee_id, product_id, transfer_id, supplier_id, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NOW())";
            $stmt_insert_import = $conn_to_shop->prepare($sql_insert_import);
            if ($stmt_insert_import === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn nhập hàng: " . $conn_to_shop->error);
            }

            // Cập nhật tồn kho
            $sql_update_inventory_from = "UPDATE `$shop_db`.inventory SET quantity = quantity - ? WHERE product_id = ?";
            $stmt_update_inventory_from = $conn->prepare($sql_update_inventory_from);
            if ($stmt_update_inventory_from === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật tồn kho (shop xuất): " . $conn->error);
            }

            $sql_update_inventory_to = "INSERT INTO `$to_shop_db`.inventory (product_id, quantity, unit, created_at) 
                                       VALUES (?, ?, '', NOW()) 
                                       ON DUPLICATE KEY UPDATE quantity = quantity + ?";
            $stmt_update_inventory_to = $conn_to_shop->prepare($sql_update_inventory_to);
            if ($stmt_update_inventory_to === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật tồn kho (shop nhập): " . $conn_to_shop->error);
            }

            foreach ($products as $product) {
                $product_id = intval($product['id']);
                $quantity = intval($product['quantity']);
                $unit_price = floatval($product['price']);
                if ($quantity <= 0) continue;

                // Kiểm tra tồn kho của shop xuất
                $sql_check_inventory = "SELECT quantity FROM `$shop_db`.inventory WHERE product_id = ?";
                $stmt_check_inventory = $conn->prepare($sql_check_inventory);
                $stmt_check_inventory->bind_param('i', $product_id);
                $stmt_check_inventory->execute();
                $result_check_inventory = $stmt_check_inventory->get_result();
                $inventory = $result_check_inventory->fetch_assoc();
                if (!$inventory || $inventory['quantity'] < $quantity) {
                    throw new Exception("Số lượng tồn kho không đủ cho sản phẩm ID $product_id!");
                }

                // Tính tổng giá trị
                $subtotal = $quantity * $unit_price;

                // Tạo đơn chuyển kho
                $stmt_insert_transfer->bind_param('iiiisi', $product_id, $from_shop_id, $to_shop_id, $quantity, $transfer_date, $employee_id);
                $stmt_insert_transfer->execute();
                $transfer_id = $conn->insert_id;

                // Tạo đơn xuất hàng
                $stmt_insert_export->bind_param('sdiidii', $transfer_date, $subtotal, $quantity, $unit_price, $employee_id, $product_id, $transfer_id);
                $stmt_insert_export->execute();

                // Tạo đơn nhập hàng
                $stmt_insert_import->bind_param('sdiidii', $transfer_date, $subtotal, $quantity, $unit_price, $employee_id, $product_id, $transfer_id);
                $stmt_insert_import->execute();

                // Cập nhật tồn kho của shop xuất
                $stmt_update_inventory_from->bind_param('ii', $quantity, $product_id);
                $stmt_update_inventory_from->execute();

                // Cập nhật tồn kho của shop nhập
                $stmt_update_inventory_to->bind_param('iii', $product_id, $quantity, $quantity);
                $stmt_update_inventory_to->execute();
            }

            // Commit giao dịch
            $conn->commit();
            $conn_to_shop->commit();
            $success = "Chuyển kho thành công!";
            header("Location: transfer_stock.php");
            exit();
        } catch (Exception $e) {
            // Rollback giao dịch nếu có lỗi
            $conn->rollback();
            $conn_to_shop->rollback();
            $error = "Lỗi khi chuyển kho: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm đơn chuyển kho</title>
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
            <h1>Thêm đơn chuyển kho</h1>
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="to_shop_id" class="form-label">Chuyển đến Shop</label>
                            <select class="form-control" id="to_shop_id" name="to_shop_id" required>
                                <option value="">-- Chọn Shop --</option>
                                <?php while ($shop = $result_shops->fetch_assoc()): ?>
                                    <option value="<?php echo $shop['id']; ?>">
                                        <?php echo htmlspecialchars($shop['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="employee_id" class="form-label">Nhân viên</label>
                            <select class="form-control" id="employee_id" name="employee_id" required>
                                <option value="">-- Chọn nhân viên --</option>
                                <?php while ($employee = $result_employees->fetch_assoc()): ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="transfer_date" class="form-label">Ngày chuyển kho</label>
                        <input type="datetime-local" class="form-control" id="transfer_date" name="transfer_date" required>
                    </div>

                    <!-- Bảng danh sách sản phẩm để chọn -->
                    <div class="mb-3">
                        <label class="form-label">Chọn sản phẩm</label>
                        <table class="table table-bordered" id="productTable">
                            <thead>
                            <tr>
                                <th>Chọn</th>
                                <th>Tên sản phẩm</th>
                                <th>Tồn kho</th>
                                <th>Giá (VNĐ)</th>
                                <th>Số lượng</th>
                                <th>Thành tiền (VNĐ)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php while ($product = $result_products->fetch_assoc()): ?>
                                <?php
                                $product_id = $product['id'];
                                $cleaned_name = htmlspecialchars(strip_tags($product['name']), ENT_QUOTES, 'UTF-8');
                                $price = floatval($product['price']);
                                ?>
                                <tr data-product-id="<?php echo $product_id; ?>">
                                    <td>
                                        <input type="checkbox" class="product-checkbox" name="products[<?php echo $product_id; ?>][selected]" value="1">
                                    </td>
                                    <td><?php echo $cleaned_name; ?></td>
                                    <td><?php echo $product['quantity'] ?? 0; ?></td>
                                    <td class="product-price" data-price="<?php echo $price; ?>">
                                        <?php echo number_format($price, 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control product-quantity" name="products[<?php echo $product_id; ?>][quantity]" value="0" min="0" disabled>
                                    </td>
                                    <td class="subtotal">0</td>
                                    <input type="hidden" name="products[<?php echo $product_id; ?>][id]" value="<?php echo $product_id; ?>">
                                    <input type="hidden" name="products[<?php echo $product_id; ?>][price]" value="<?php echo $price; ?>">
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tổng tiền -->
                    <div class="mb-3">
                        <label class="form-label">Tổng tiền (VNĐ)</label>
                        <input type="text" class="form-control" id="totalPrice" readonly value="0">
                        <input type="hidden" name="total_price_raw" id="totalPriceRaw" value="0">
                    </div>

                    <button type="submit" class="btn btn-primary">Chuyển kho</button>
                    <a href="transfer_stock.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$result_shops->free();
$result_products->free();
$result_employees->free();
$conn->close();
$conn_main->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const productTableBody = document.querySelector('#productTable tbody');
        const totalPriceInput = document.getElementById('totalPrice');
        const totalPriceRawInput = document.getElementById('totalPriceRaw');

        // Xử lý khi checkbox thay đổi
        productTableBody.addEventListener('change', function (e) {
            if (e.target.classList.contains('product-checkbox')) {
                const row = e.target.closest('tr');
                const quantityInput = row.querySelector('.product-quantity');
                if (e.target.checked) {
                    quantityInput.value = 1; // Đặt số lượng mặc định là 1 khi chọn
                    quantityInput.disabled = false;
                } else {
                    quantityInput.value = 0; // Đặt số lượng về 0 khi bỏ chọn
                    quantityInput.disabled = true;
                }
                calculateTotal();
            }
        });

        // Xử lý thay đổi số lượng
        productTableBody.addEventListener('input', function (e) {
            if (e.target.classList.contains('product-quantity')) {
                const row = e.target.closest('tr');
                const quantity = parseInt(e.target.value) || 0;
                const checkbox = row.querySelector('.product-checkbox');
                if (quantity > 0) {
                    checkbox.checked = true;
                    e.target.disabled = false;
                } else {
                    checkbox.checked = false;
                    e.target.disabled = true;
                }
                calculateTotal();
            }
        });

        // Tính tổng tiền
        function calculateTotal() {
            let total = 0;
            const rows = productTableBody.querySelectorAll('tr');
            rows.forEach(row => {
                const checkbox = row.querySelector('.product-checkbox');
                const price = parseFloat(row.querySelector('.product-price').dataset.price);
                const quantity = parseInt(row.querySelector('.product-quantity').value) || 0;
                const subtotal = price * quantity;
                row.querySelector('.subtotal').textContent = subtotal.toLocaleString('vi-VN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                if (checkbox.checked && quantity > 0) {
                    total += subtotal;
                }
            });
            totalPriceInput.value = total.toLocaleString('vi-VN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            totalPriceRawInput.value = total; // Lưu giá trị số thực
        }
    });
</script>
</body>
</html>