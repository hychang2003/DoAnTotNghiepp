<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
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

// Lấy tên cơ sở từ bảng shop
$conn_main = getConnection($host, $username, $password, 'fashion_shop');
$sql_shop_name = "SELECT name FROM shop WHERE db_name = ?";
$stmt_shop_name = $conn_main->prepare($sql_shop_name);
if ($stmt_shop_name === false) {
    die("Lỗi chuẩn bị truy vấn name: " . $conn_main->error);
}
$stmt_shop_name->bind_param('s', $shop_db);
$stmt_shop_name->execute();
$result_shop_name = $stmt_shop_name->get_result();
$shop_row = $result_shop_name->fetch_assoc();
$shop_name = $shop_row['name'] ?? $shop_db;
if (!$shop_row) {
    error_log("Không tìm thấy name cho db_name = '$shop_db' trong bảng shop.");
}
$stmt_shop_name->close();

// Lấy danh sách nhà cung cấp, nhân viên và sản phẩm
$conn = getConnection($host, $username, $password, $shop_db);

// Lấy danh sách nhà cung cấp từ fashion_shop.supplier
$sql_suppliers = "SELECT id, name FROM `fashion_shop`.supplier";
$result_suppliers = $conn_main->query($sql_suppliers);
if ($result_suppliers === false) {
    die("Lỗi truy vấn nhà cung cấp: " . $conn_main->error);
}

// Lấy danh sách nhân viên
$sql_employees = "SELECT id, name FROM `$shop_db`.employee";
$result_employees = $conn->query($sql_employees);
if ($result_employees === false) {
    die("Lỗi truy vấn nhân viên: " . $conn->error);
}

// Lấy danh sách sản phẩm và số lượng tồn kho
$sql_products = "SELECT p.id, p.name, p.price, IFNULL(i.quantity, 0) AS stock_quantity 
                 FROM `$shop_db`.product p 
                 LEFT JOIN `$shop_db`.inventory i ON p.id = i.product_id";
$result_products = $conn->query($sql_products);
if ($result_products === false) {
    die("Lỗi truy vấn sản phẩm: " . $conn->error);
}

// Xử lý form tạo đơn xuất hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_export_goods'])) {
    $supplier_id = $_POST['supplier_id'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    $export_date = $_POST['export_date'] ?? date('Y-m-d H:i:s');
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $unit_prices = $_POST['unit_prices'] ?? [];
    $note = $_POST['note'] ?? '';
    $code = $_POST['code'] ?? '';
    $reference = $_POST['reference'] ?? '';

    // Kiểm tra dữ liệu đầu vào
    if (empty($supplier_id) || empty($employee_id) || empty($products) || empty($quantities) || empty($unit_prices)) {
        $error = "Vui lòng điền đầy đủ thông tin: nhà cung cấp, nhân viên phụ trách, và ít nhất một sản phẩm.";
    } else {
        $total_price = 0;
        $export_details = [];

        foreach ($products as $index => $product_id) {
            if (empty($quantities[$index]) || $quantities[$index] <= 0 || empty($unit_prices[$index]) || $unit_prices[$index] <= 0) {
                continue; // Bỏ qua nếu số lượng hoặc giá không hợp lệ
            }

            // Kiểm tra số lượng tồn kho
            $sql_check_stock = "SELECT IFNULL(quantity, 0) AS stock_quantity 
                                FROM `$shop_db`.inventory 
                                WHERE product_id = ?";
            $stmt_check_stock = $conn->prepare($sql_check_stock);
            $stmt_check_stock->bind_param('i', $product_id);
            $stmt_check_stock->execute();
            $result_check_stock = $stmt_check_stock->get_result();
            $stock = $result_check_stock->fetch_assoc()['stock_quantity'];
            $stmt_check_stock->close();

            $quantity = $quantities[$index];
            if ($quantity > $stock) {
                $error = "Số lượng xuất cho sản phẩm ID $product_id vượt quá tồn kho ($stock).";
                break;
            }

            $unit_price = $unit_prices[$index];
            $subtotal = $quantity * $unit_price;
            $total_price += $subtotal;

            $export_details[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'subtotal' => $subtotal
            ];
        }

        if (isset($error)) {
            // Nếu có lỗi, không tiếp tục xử lý
        } elseif (empty($export_details)) {
            $error = "Vui lòng chọn ít nhất một sản phẩm với số lượng và giá xuất hợp lệ.";
        } else {
            // Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
            $conn->begin_transaction();

            try {
                foreach ($export_details as $detail) {
                    // Thêm vào bảng export_goods
                    $sql_export = "INSERT INTO `$shop_db`.export_goods (product_id, quantity, unit_price, total_price, export_date, employee_id, note, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt_export = $conn->prepare($sql_export);
                    if ($stmt_export === false) {
                        throw new Exception("Lỗi chuẩn bị truy vấn export_goods: " . $conn->error);
                    }
                    $stmt_export->bind_param('iiddsis', $detail['product_id'], $detail['quantity'], $detail['unit_price'], $detail['subtotal'], $export_date, $employee_id, $note);
                    $stmt_export->execute();
                    $stmt_export->close();

                    // Cập nhật tồn kho trong bảng inventory
                    $sql_inventory = "UPDATE `$shop_db`.inventory 
                                     SET quantity = quantity - ?, last_updated = NOW() 
                                     WHERE product_id = ?";
                    $stmt_inventory = $conn->prepare($sql_inventory);
                    if ($stmt_inventory === false) {
                        throw new Exception("Lỗi chuẩn bị truy vấn inventory: " . $conn->error);
                    }
                    $stmt_inventory->bind_param('ii', $detail['quantity'], $detail['product_id']);
                    $stmt_inventory->execute();
                    $stmt_inventory->close();
                }

                $conn->commit();
                header("Location: export_goods.php?export_added=success");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Lỗi khi tạo đơn xuất hàng: " . $e->getMessage();
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
    <title>Tạo đơn xuất hàng - <?php echo htmlspecialchars($shop_name); ?></title>
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
            <h1>Tạo đơn xuất hàng - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
            <div class="actions">
                <a href="export_goods.php" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i> Quay lại</a>
            </div>
        </header>

        <!-- Thông báo lỗi -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Form tạo đơn xuất hàng -->
        <section class="add-export-goods-form">
            <form method="POST" action="">
                <input type="hidden" name="add_export_goods" value="1">
                <div class="row">
                    <!-- Cột bên trái -->
                    <div class="col-md-8">
                        <!-- Sản phẩm -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Sản phẩm</h2>
                            </div>
                            <div class="card-body">
                                <div class="product-search mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                                        <input type="text" class="form-control" id="product-search" placeholder="Tìm theo tên">
                                    </div>
                                </div>
                                <table class="table table-bordered" id="product-table">
                                    <thead>
                                    <tr>
                                        <th>Chọn</th>
                                        <th>Tên sản phẩm</th>
                                        <th>Tồn kho</th>
                                        <th>Số lượng xuất</th>
                                        <th>Giá xuất</th>
                                        <th>Thành tiền</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php while ($row = $result_products->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="products[]" value="<?php echo $row['id']; ?>" class="product-checkbox">
                                            </td>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td class="stock-quantity"><?php echo $row['stock_quantity']; ?></td>
                                            <td>
                                                <input type="number" name="quantities[]" min="0" value="0" class="form-control product-quantity">
                                            </td>
                                            <td>
                                                <input type="number" name="unit_prices[]" min="0" value="<?php echo $row['price']; ?>" class="form-control product-unit-price">
                                            </td>
                                            <td class="subtotal">0</td>
                                        </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Thanh toán -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Thanh toán</h2>
                            </div>
                            <div class="card-body">
                                <div class="payment-item">
                                    <p>Tổng tiền</p>
                                    <p id="total-price">0₫</p>
                                </div>
                                <div class="payment-item">
                                    <p>Thêm giảm giá (F6)</p>
                                    <p>0₫</p>
                                </div>
                                <div class="payment-item">
                                    <p>Chi phí xuất hàng (F7)</p>
                                    <p>0₫</p>
                                </div>
                                <div class="payment-item total">
                                    <p>Tiền cần trả NCC</p>
                                    <p id="final-price">0₫</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cột bên phải -->
                    <div class="col-md-4">
                        <!-- Nhà cung cấp -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Nhà cung cấp</h2>
                            </div>
                            <div class="card-body">
                                <select class="form-select" name="supplier_id" required>
                                    <option value="">Chọn nhà cung cấp</option>
                                    <?php while ($row = $result_suppliers->fetch_assoc()): ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Chi nhánh xuất -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Chi nhánh xuất</h2>
                            </div>
                            <div class="card-body">
                                <select class="form-select" name="location_id" disabled>
                                    <option selected><?php echo htmlspecialchars($shop_name); ?></option>
                                </select>
                            </div>
                        </div>

                        <!-- Thông tin bổ sung -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Thông tin bổ sung</h2>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="employee_id" class="form-label">Nhân viên phụ trách</label>
                                    <select class="form-select" id="employee_id" name="employee_id" required>
                                        <option value="">Chọn nhân viên</option>
                                        <?php while ($row = $result_employees->fetch_assoc()): ?>
                                            <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="export_date" class="form-label">Ngày xuất dự kiến</label>
                                    <input type="datetime-local" class="form-control" id="export_date" name="export_date" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="exportCode" class="form-label">Mã đơn xuất</label>
                                    <input type="text" class="form-control" id="exportCode" name="code" placeholder="Nhập mã đơn">
                                </div>
                                <div class="mb-3">
                                    <label for="reference" class="form-label">Tham chiếu</label>
                                    <input type="text" class="form-control" id="reference" name="reference" placeholder="Nhập mã tham chiếu">
                                </div>
                            </div>
                        </div>

                        <!-- Ghi chú -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Ghi chú</h2>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" name="note" rows="3" placeholder="VD: Chỉ xuất hàng trong giờ hành chính"></textarea>
                            </div>
                        </div>

                        <!-- Tag -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Tag</h2>
                                <button type="button" class="btn btn-sm btn-outline-secondary">Danh sách tag</button>
                            </div>
                            <div class="card-body">
                                <input type="text" class="form-control" name="tags" placeholder="Tìm kiếm hoặc thêm mới tag">
                            </div>
                        </div>

                        <!-- Hành động -->
                        <div class="actions">
                            <button type="submit" class="btn btn-primary w-100">Tạo đơn xuất hàng</button>
                        </div>
                    </div>
                </div>
            </form>
        </section>
    </div>
</div>

<?php
// Đóng kết nối
$result_suppliers->free();
$result_employees->free();
$result_products->free();
$conn->close();
$conn_main->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Hàm định dạng số tiền
    function formatNumber(number) {
        return Math.round(number).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") + "₫";
    }

    // Hàm tính tổng tiền
    function calculateTotalPrice() {
        let totalPrice = 0;
        const rows = document.querySelectorAll("#product-table tbody tr");

        rows.forEach(row => {
            const checkbox = row.querySelector(".product-checkbox");
            if (checkbox.checked) {
                const stockQuantity = parseInt(row.querySelector(".stock-quantity").textContent) || 0;
                const quantity = parseInt(row.querySelector(".product-quantity").value) || 0;
                const unitPrice = parseFloat(row.querySelector(".product-unit-price").value) || 0;

                if (quantity > stockQuantity) {
                    alert(`Số lượng xuất vượt quá tồn kho (${stockQuantity}) cho sản phẩm ${row.cells[1].textContent}.`);
                    row.querySelector(".product-quantity").value = stockQuantity;
                    return;
                }

                const subtotal = quantity * unitPrice;
                totalPrice += subtotal;
                row.querySelector(".subtotal").textContent = formatNumber(subtotal);
            } else {
                row.querySelector(".subtotal").textContent = "0";
            }
        });

        document.getElementById("total-price").textContent = formatNumber(totalPrice);
        document.getElementById("final-price").textContent = formatNumber(totalPrice);
    }

    // Hàm định dạng ngày giờ cho input datetime-local
    function formatDateTime(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
    }

    // Cập nhật ngày giờ thực tế
    function updateExportDate() {
        const now = new Date();
        document.getElementById("export_date").value = formatDateTime(now);
    }

    // Cập nhật ngày giờ ngay khi trang tải
    updateExportDate();

    // Cập nhật ngày giờ mỗi giây
    setInterval(updateExportDate, 1000);

    // Gắn sự kiện cho checkbox, số lượng và giá xuất
    document.querySelectorAll(".product-checkbox, .product-quantity, .product-unit-price").forEach(element => {
        element.addEventListener("change", calculateTotalPrice);
    });

    // Tính tổng tiền ban đầu
    calculateTotalPrice();

    // Tìm kiếm sản phẩm
    document.getElementById("product-search").addEventListener("input", function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll("#product-table tbody tr");

        rows.forEach(row => {
            const productName = row.cells[1].textContent.toLowerCase();
            if (productName.includes(searchValue)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    });
</script>
</body>
</html>