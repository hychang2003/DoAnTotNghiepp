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

// Thiết lập múi giờ cho MySQL
$conn->query("SET time_zone = '+07:00'");

// Lấy thông tin chương trình khuyến mãi đang hoạt động
$discount = 0; // Giá trị giảm giá mặc định (phần trăm)
$sql_flash_sale = "SELECT discount FROM flash_sale 
                   WHERE start_date <= NOW() 
                   AND end_date >= NOW() 
                   AND status = 1 
                   LIMIT 1";
$result_flash_sale = $conn->query($sql_flash_sale);
if ($result_flash_sale === false) {
    die("Lỗi truy vấn flash_sale: " . $conn->error);
}
if ($result_flash_sale->num_rows > 0) {
    $flash_sale = $result_flash_sale->fetch_assoc();
    $discount = floatval($flash_sale['discount']);
}

// Lấy danh sách khách hàng
$sql_customers = "SELECT id, name FROM customer";
$result_customers = $conn->query($sql_customers);
if ($result_customers === false) {
    die("Lỗi truy vấn khách hàng: " . $conn->error);
}

// Lấy danh sách nhân viên
$sql_employees = "SELECT id, name FROM employee";
$result_employees = $conn->query($sql_employees);
if ($result_employees === false) {
    die("Lỗi truy vấn nhân viên: " . $conn->error);
}

// Lấy danh sách sản phẩm
$sql_products = "SELECT id, name, price FROM product";
$result_products = $conn->query($sql_products);
if ($result_products === false) {
    die("Lỗi truy vấn sản phẩm: " . $conn->error);
}

// Xử lý thêm đơn hàng mới
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    $order_date = $_POST['order_date'] ?? '';
    $total_price = floatval($_POST['total_price_raw'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    $products = $_POST['products'] ?? [];

    // Kiểm tra dữ liệu đầu vào
    if (empty($order_date) || $total_price <= 0 || empty($products)) {
        $error = "Vui lòng nhập đầy đủ thông tin (ngày đặt hàng, tổng tiền và ít nhất một sản phẩm)!";
    } else {
        // Tính lại tổng tiền từ danh sách sản phẩm để kiểm tra
        $calculated_total = 0;
        foreach ($products as $product) {
            $product_id = intval($product['id']);
            $quantity = intval($product['quantity']);
            $unit_price = floatval($product['price']);
            $discount_percent = floatval($product['discount'] ?? 0); // Giảm giá là phần trăm
            if ($quantity > 0) {
                $discount_amount = ($unit_price * $discount_percent) / 100; // Tính số tiền giảm giá
                $subtotal = ($unit_price - $discount_amount) * $quantity; // Thành tiền sau giảm giá
                $calculated_total += $subtotal;
            }
        }

        // So sánh tổng tiền từ client với tổng tiền tính lại
        if (abs($calculated_total - $total_price) > 0.01) {
            $error = "Tổng tiền không khớp, vui lòng kiểm tra lại! (Tổng tính lại: " . number_format($calculated_total, 0, ',', '.') . ")";
        } else {
            // Bắt đầu giao dịch
            $conn->begin_transaction();
            try {
                // Thêm đơn hàng mới vào bảng order
                $sql_insert_order = "INSERT INTO `order` (customer_id, employee_id, order_date, total_price, status, created_at) 
                                    VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt_insert_order = $conn->prepare($sql_insert_order);
                if ($stmt_insert_order === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn đơn hàng: " . $conn->error);
                }
                $stmt_insert_order->bind_param('iisds', $customer_id, $employee_id, $order_date, $total_price, $status);
                $stmt_insert_order->execute();
                $order_id = $conn->insert_id;

                // Thêm chi tiết đơn hàng vào bảng order_detail
                $sql_insert_detail = "INSERT INTO `order_detail` (order_id, product_id, quantity, unit_price, discount, created_at) 
                                     VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt_insert_detail = $conn->prepare($sql_insert_detail);
                if ($stmt_insert_detail === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn chi tiết đơn hàng: " . $conn->error);
                }

                foreach ($products as $product) {
                    $product_id = intval($product['id']);
                    $quantity = intval($product['quantity']);
                    $unit_price = floatval($product['price']);
                    $discount_percent = floatval($product['discount'] ?? 0);
                    $discount_amount = ($unit_price * $discount_percent) / 100; // Lưu số tiền giảm giá vào cột discount
                    if ($quantity > 0) {
                        $stmt_insert_detail->bind_param('iiidd', $order_id, $product_id, $quantity, $unit_price, $discount_amount);
                        $stmt_insert_detail->execute();
                    }
                }

                // Cập nhật số lượng tồn kho
                $sql_update_inventory = "UPDATE `inventory` 
                                        SET quantity = quantity - ? 
                                        WHERE product_id = ?";
                $stmt_update_inventory = $conn->prepare($sql_update_inventory);
                if ($stmt_update_inventory === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn cập nhật tồn kho: " . $conn->error);
                }

                foreach ($products as $product) {
                    $product_id = intval($product['id']);
                    $quantity = intval($product['quantity']);
                    if ($quantity > 0) {
                        $stmt_update_inventory->bind_param('ii', $quantity, $product_id);
                        $stmt_update_inventory->execute();

                        // Kiểm tra số lượng tồn kho sau khi cập nhật
                        $sql_check_inventory = "SELECT quantity FROM `inventory` WHERE product_id = ?";
                        $stmt_check_inventory = $conn->prepare($sql_check_inventory);
                        $stmt_check_inventory->bind_param('i', $product_id);
                        $stmt_check_inventory->execute();
                        $result_check_inventory = $stmt_check_inventory->get_result();
                        $inventory = $result_check_inventory->fetch_assoc();
                        if ($inventory['quantity'] < 0) {
                            throw new Exception("Số lượng tồn kho không đủ cho sản phẩm ID $product_id!");
                        }
                    }
                }

                // Commit giao dịch
                $conn->commit();
                $success = "Thêm đơn hàng thành công!";
                header("Location: order.php");
                exit();
            } catch (Exception $e) {
                // Rollback giao dịch nếu có lỗi
                $conn->rollback();
                $error = "Lỗi khi thêm đơn hàng: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm hóa đơn mới</title>
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
            <h1>Thêm hóa đơn mới</h1>
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
                            <label for="customer_id" class="form-label">Khách hàng</label>
                            <select class="form-control" id="customer_id" name="customer_id">
                                <option value="">-- Không chọn --</option>
                                <?php while ($customer = $result_customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['id']; ?>">
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="employee_id" class="form-label">Nhân viên</label>
                            <select class="form-control" id="employee_id" name="employee_id">
                                <option value="">-- Không chọn --</option>
                                <?php while ($employee = $result_employees->fetch_assoc()): ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="order_date" class="form-label">Ngày đặt hàng</label>
                        <input type="datetime-local" class="form-control" id="order_date" name="order_date" required>
                    </div>

                    <!-- Bảng danh sách sản phẩm để chọn -->
                    <div class="mb-3">
                        <label class="form-label">Chọn sản phẩm</label>
                        <table class="table table-bordered" id="productTable">
                            <thead>
                            <tr>
                                <th>Chọn</th>
                                <th>Tên sản phẩm</th>
                                <th>Giá (VNĐ)</th>
                                <th>Giảm giá (%)</th>
                                <th>Số lượng</th>
                                <th>Thành tiền (VNĐ)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php while ($product = $result_products->fetch_assoc()): ?>
                                <?php
                                $product_id = $product['id'];
                                $cleaned_name = htmlspecialchars(strip_tags($product['name']), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr data-product-id="<?php echo $product_id; ?>">
                                    <td>
                                        <input type="checkbox" class="product-checkbox" name="products[<?php echo $product_id; ?>][selected]" value="1">
                                    </td>
                                    <td><?php echo $cleaned_name; ?></td>
                                    <td class="product-price" data-price="<?php echo $product['price']; ?>">
                                        <?php echo number_format($product['price'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="product-discount" data-discount="<?php echo $discount; ?>">
                                        <?php echo number_format($discount, 2, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control product-quantity" name="products[<?php echo $product_id; ?>][quantity]" value="0" min="0" disabled>
                                    </td>
                                    <td class="subtotal">0</td>
                                    <input type="hidden" name="products[<?php echo $product_id; ?>][id]" value="<?php echo $product_id; ?>">
                                    <input type="hidden" name="products[<?php echo $product_id; ?>][price]" value="<?php echo $product['price']; ?>">
                                    <input type="hidden" name="products[<?php echo $product_id; ?>][discount]" value="<?php echo $discount; ?>">
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

                    <div class="mb-3">
                        <label for="status" class="form-label">Trạng thái</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="pending">Đang xử lý</option>
                            <option value="completed">Hoàn thành</option>
                            <option value="cancelled">Hủy</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Thêm hóa đơn</button>
                    <a href="order.php" class="btn btn-secondary">Quay lại</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$result_customers->free();
$result_employees->free();
$result_products->free();
if (isset($result_flash_sale)) {
    $result_flash_sale->free();
}
$conn->close();
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
                const discountPercent = parseFloat(row.querySelector('.product-discount').dataset.discount);
                const quantity = parseInt(row.querySelector('.product-quantity').value) || 0;
                const discountAmount = (price * discountPercent) / 100; // Tính số tiền giảm giá (theo phần trăm)
                const subtotal = (price - discountAmount) * quantity; // Thành tiền sau khi giảm giá
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