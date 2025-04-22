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
$shop_db = $_SESSION['shop_db'] ?? 'shop_2';

// Kết nối đến cơ sở dữ liệu chính
$conn_main = getConnection($host, $username, $password, 'fashion_shop');

// Lấy thông tin shop hiện tại
$sql_shop = "SELECT id, name FROM shop WHERE db_name = ?";
$stmt_shop = $conn_main->prepare($sql_shop);
if ($stmt_shop === false) {
    die("Lỗi chuẩn bị truy vấn shop: " . $conn_main->error);
}
$stmt_shop->bind_param('s', $shop_db);
$stmt_shop->execute();
$result_shop = $stmt_shop->get_result();
$shop_row = $result_shop->fetch_assoc();
$shop_name = $shop_row['name'] ?? $shop_db;
$current_shop_id = $shop_row['id'] ?? 0;
$stmt_shop->close();

// Lấy danh sách các shop khác (trừ shop hiện tại)
$sql_shops = "SELECT id, name, db_name FROM shop WHERE db_name != ?";
$stmt_shops = $conn_main->prepare($sql_shops);
if ($stmt_shops === false) {
    die("Lỗi chuẩn bị truy vấn danh sách shop: " . $conn_main->error);
}
$stmt_shops->bind_param('s', $shop_db);
$stmt_shops->execute();
$result_shops = $stmt_shops->get_result();

// Kết nối đến cơ sở dữ liệu của shop hiện tại (shop_2)
$conn = getConnection($host, $username, $password, $shop_db);

// Lấy danh sách nhân viên
$sql_employees = "SELECT id, name FROM `$shop_db`.employee";
$result_employees = $conn->query($sql_employees);
if ($result_employees === false) {
    die("Lỗi truy vấn nhân viên: " . $conn->error);
}

// Xử lý form tạo đơn nhập hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_import_goods'])) {
    $from_shop_id = $_POST['from_shop_id'] ?? 0;
    $employee_id = $_POST['employee_id'] ?? '';
    $import_date = $_POST['import_date'] ?? date('Y-m-d H:i:s');
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $unit_prices = $_POST['unit_prices'] ?? [];
    $note = $_POST['note'] ?? '';

    // Lấy db_name của shop xuất
    $sql_from_shop = "SELECT db_name FROM shop WHERE id = ?";
    $stmt_from_shop = $conn_main->prepare($sql_from_shop);
    $stmt_from_shop->bind_param('i', $from_shop_id);
    $stmt_from_shop->execute();
    $result_from_shop = $stmt_from_shop->get_result();
    $from_shop_row = $result_from_shop->fetch_assoc();
    $from_shop_db = $from_shop_row['db_name'] ?? '';
    $stmt_from_shop->close();

    // Kiểm tra dữ liệu đầu vào
    if (empty($from_shop_id) || empty($from_shop_db) || empty($employee_id) || empty($products) || empty($quantities) || empty($unit_prices)) {
        $error = "Vui lòng điền đầy đủ thông tin: shop xuất, nhân viên phụ trách, và ít nhất một sản phẩm.";
    } else {
        $total_price = 0;
        $import_details = [];

        foreach ($products as $index => $product_id) {
            if (empty($quantities[$index]) || $quantities[$index] <= 0 || empty($unit_prices[$index]) || $unit_prices[$index] <= 0) {
                continue; // Bỏ qua nếu số lượng hoặc giá không hợp lệ
            }

            $quantity = $quantities[$index];
            $unit_price = $unit_prices[$index];
            $subtotal = $quantity * $unit_price;
            $total_price += $subtotal;

            $import_details[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'subtotal' => $subtotal
            ];
        }

        if (empty($import_details)) {
            $error = "Vui lòng chọn ít nhất một sản phẩm với số lượng và giá nhập hợp lệ.";
        } else {
            // Kết nối đến cơ sở dữ liệu của shop xuất (shop_1)
            $conn_from_shop = getConnection($host, $username, $password, $from_shop_db);

            // Kiểm tra tồn kho trước transaction
            $inventory_check_passed = true;
            $inventory_check_errors = [];
            foreach ($import_details as $detail) {
                $product_id = $detail['product_id'];
                $quantity = $detail['quantity'];

                $sql_check_inventory = "SELECT quantity FROM `$from_shop_db`.inventory WHERE product_id = ?";
                $stmt_check_inventory = $conn_from_shop->prepare($sql_check_inventory);
                $stmt_check_inventory->bind_param('i', $product_id);
                $stmt_check_inventory->execute();
                $result_check_inventory = $stmt_check_inventory->get_result();
                $inventory = $result_check_inventory->fetch_assoc();
                if (!$inventory || $inventory['quantity'] < $quantity) {
                    $inventory_check_passed = false;
                    $inventory_check_errors[] = "Số lượng tồn kho không đủ cho sản phẩm ID $product_id tại shop xuất!";
                }
                $stmt_check_inventory->close();
            }

            if (!$inventory_check_passed) {
                $error = implode("<br>", $inventory_check_errors);
            } else {
                // Lấy danh sách category_id hợp lệ trong shop nhập (shop_2)
                $valid_category_ids = [];
                $sql_get_valid_categories = "SELECT id FROM `$shop_db`.category";
                $result_valid_categories = $conn->query($sql_get_valid_categories);
                if ($result_valid_categories) {
                    while ($row = $result_valid_categories->fetch_assoc()) {
                        $valid_category_ids[] = $row['id'];
                    }
                    $result_valid_categories->free();
                }
                $default_category_id = !empty($valid_category_ids) ? $valid_category_ids[0] : null;

                if ($default_category_id === null) {
                    $error = "Không tìm thấy danh mục nào trong $shop_db.category. Vui lòng thêm ít nhất một danh mục trước khi nhập hàng.";
                    error_log("Lỗi: Không tìm thấy danh mục trong $shop_db.category");
                } else {
                    // Đồng bộ sản phẩm vào bảng product của shop nhập (chỉ khi cần thiết)
                    foreach ($import_details as $detail) {
                        $product_id = $detail['product_id'];

                        // Kiểm tra xem product_id có tồn tại trong bảng product của shop nhập không
                        $sql_check_product = "SELECT id FROM `$shop_db`.product WHERE id = ?";
                        $stmt_check_product = $conn->prepare($sql_check_product);
                        $stmt_check_product->bind_param('i', $product_id);
                        $stmt_check_product->execute();
                        $result_check_product = $stmt_check_product->get_result();
                        $product_exists = $result_check_product->num_rows > 0;
                        $stmt_check_product->close();

                        if (!$product_exists) {
                            // Lấy thông tin sản phẩm từ shop xuất, bao gồm category_id và các trường khác
                            $sql_get_product = "SELECT name, description, create_date, update_date, category_id, type, unit, price, cost_price FROM `$from_shop_db`.product WHERE id = ?";
                            $stmt_get_product = $conn_from_shop->prepare($sql_get_product);
                            $stmt_get_product->bind_param('i', $product_id);
                            $stmt_get_product->execute();
                            $result_get_product = $stmt_get_product->get_result();
                            $product = $result_get_product->fetch_assoc();
                            $stmt_get_product->close();

                            if ($product) {
                                // Lấy category_id của sản phẩm
                                $category_id = $product['category_id'];
                                error_log("Sản phẩm ID $product_id trong $from_shop_db có category_id = $category_id");

                                // Kiểm tra xem category_id có tồn tại trong bảng category của shop xuất không
                                $sql_check_category_from_shop = "SELECT id FROM `$from_shop_db`.category WHERE id = ?";
                                $stmt_check_category_from_shop = $conn_from_shop->prepare($sql_check_category_from_shop);
                                $stmt_check_category_from_shop->bind_param('i', $category_id);
                                $stmt_check_category_from_shop->execute();
                                $result_check_category_from_shop = $stmt_check_category_from_shop->get_result();
                                $category_exists_in_from_shop = $result_check_category_from_shop->num_rows > 0;
                                $stmt_check_category_from_shop->close();

                                if (!$category_exists_in_from_shop) {
                                    $error = "Sản phẩm ID $product_id trong shop xuất ($from_shop_db) có category_id = $category_id, nhưng category_id này không tồn tại trong bảng category của shop xuất. Sử dụng category_id mặc định ($default_category_id).";
                                    error_log("Cảnh báo: category_id $category_id của sản phẩm ID $product_id không tồn tại trong $from_shop_db.category. Sử dụng category_id mặc định ($default_category_id)");
                                    $category_id = $default_category_id;
                                }

                                // Kiểm tra xem category_id có tồn tại trong bảng category của shop nhập không
                                $sql_check_category = "SELECT id FROM `$shop_db`.category WHERE id = ?";
                                $stmt_check_category = $conn->prepare($sql_check_category);
                                $stmt_check_category->bind_param('i', $category_id);
                                $stmt_check_category->execute();
                                $result_check_category = $stmt_check_category->get_result();
                                $category_exists = $result_check_category->num_rows > 0;
                                $stmt_check_category->close();

                                if (!$category_exists) {
                                    $error = "category_id $category_id của sản phẩm ID $product_id không tồn tại trong $shop_db.category. Sử dụng category_id mặc định ($default_category_id).";
                                    error_log("Cảnh báo: category_id $category_id của sản phẩm ID $product_id không tồn tại trong $shop_db.category. Sử dụng category_id mặc định ($default_category_id)");
                                    $category_id = $default_category_id;
                                }

                                // Log category_id trước khi chèn
                                error_log("Chèn sản phẩm ID $product_id vào $shop_db với category_id = $category_id");

                                // Sau khi đảm bảo category_id hợp lệ, chèn sản phẩm vào bảng product của shop nhập
                                $sql_insert_product = "INSERT INTO `$shop_db`.product (id, name, description, create_date, update_date, employee_id, category_id, type, unit, price, cost_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                                $stmt_insert_product = $conn->prepare($sql_insert_product);
                                $stmt_insert_product->bind_param('issssisisdd',
                                    $product_id,
                                    $product['name'],
                                    $product['description'],
                                    $product['create_date'],
                                    $product['update_date'],
                                    $employee_id,
                                    $category_id,
                                    $product['type'],
                                    $product['unit'],
                                    $product['price'],
                                    $product['cost_price']
                                );
                                if (!$stmt_insert_product->execute()) {
                                    $error = "Lỗi khi đồng bộ sản phẩm ID $product_id vào shop nhập: " . $stmt_insert_product->error;
                                    error_log("Lỗi khi đồng bộ sản phẩm: " . $stmt_insert_product->error);
                                    break;
                                }
                                $stmt_insert_product->close();
                            } else {
                                $error = "Không tìm thấy sản phẩm ID $product_id trong shop xuất ($from_shop_db). Vui lòng kiểm tra dữ liệu trong bảng product của shop xuất.";
                                error_log("Lỗi: Không tìm thấy sản phẩm ID $product_id trong $from_shop_db.product");
                                break;
                            }
                        }
                        // Nếu sản phẩm đã tồn tại, không cần chèn mới, transaction sẽ tự động cộng số lượng vào inventory
                    }

                    if (!isset($error)) {
                        // Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
                        $conn->begin_transaction();
                        $conn_from_shop->begin_transaction();

                        try {
                            // Chuẩn bị các giá trị cho truy vấn gộp
                            $transfer_values_from = []; // Cho shop xuất (shop_1)
                            $transfer_values_to = [];   // Cho shop nhập (shop_2)
                            $export_values = [];
                            $import_values = [];
                            $inventory_from_updates = [];
                            $inventory_to_updates = [];

                            foreach ($import_details as $detail) {
                                $product_id = $detail['product_id'];
                                $quantity = $detail['quantity'];
                                $unit_price = $detail['unit_price'];
                                $subtotal = $detail['subtotal'];

                                // Tạo bản ghi trong transfer_stock cho cả shop xuất và shop nhập
                                $transfer_values_from[] = "($product_id, $from_shop_id, $current_shop_id, $quantity, '$import_date', $employee_id, 'completed', NOW())";
                                $transfer_values_to[] = "($product_id, $from_shop_id, $current_shop_id, $quantity, '$import_date', $employee_id, 'completed', NOW())";

                                // Tạo bản ghi trong export_goods và import_goods (sẽ cần transfer_id sau khi insert transfer_stock)
                                $export_values[] = [
                                    'product_id' => $product_id,
                                    'quantity' => $quantity,
                                    'unit_price' => $unit_price,
                                    'subtotal' => $subtotal
                                ];
                                $import_values[] = [
                                    'product_id' => $product_id,
                                    'quantity' => $quantity,
                                    'unit_price' => $unit_price,
                                    'subtotal' => $subtotal
                                ];

                                // Cập nhật tồn kho
                                $inventory_from_updates[] = "UPDATE `$from_shop_db`.inventory SET quantity = quantity - $quantity WHERE product_id = $product_id";
                                $inventory_to_updates[] = "INSERT INTO `$shop_db`.inventory (product_id, quantity, unit, last_updated) VALUES ($product_id, $quantity, 'Chiếc', NOW()) ON DUPLICATE KEY UPDATE quantity = quantity + $quantity, last_updated = NOW()";
                            }

                            // Thực hiện gộp INSERT cho transfer_stock trong shop xuất (shop_1)
                            if (!empty($transfer_values_from)) {
                                $sql_transfer_from = "INSERT INTO `$from_shop_db`.transfer_stock (product_id, from_shop_id, to_shop_id, quantity, transfer_date, employee_id, status, created_at) VALUES " . implode(',', $transfer_values_from);
                                if (!$conn_from_shop->query($sql_transfer_from)) {
                                    throw new Exception("Lỗi khi tạo bản ghi transfer_stock trong shop xuất: " . $conn_from_shop->error);
                                }

                                // Lấy các transfer_id vừa tạo trong shop xuất
                                $first_transfer_id_from = $conn_from_shop->insert_id;
                                $transfer_ids_from = range($first_transfer_id_from, $first_transfer_id_from + count($transfer_values_from) - 1);
                            }

                            // Thực hiện gộp INSERT cho transfer_stock trong shop nhập (shop_2)
                            if (!empty($transfer_values_to)) {
                                $sql_transfer_to = "INSERT INTO `$shop_db`.transfer_stock (product_id, from_shop_id, to_shop_id, quantity, transfer_date, employee_id, status, created_at) VALUES " . implode(',', $transfer_values_to);
                                if (!$conn->query($sql_transfer_to)) {
                                    throw new Exception("Lỗi khi tạo bản ghi transfer_stock trong shop nhập: " . $conn->error);
                                }

                                // Lấy các transfer_id vừa tạo trong shop nhập
                                $first_transfer_id_to = $conn->insert_id;
                                $transfer_ids_to = range($first_transfer_id_to, $first_transfer_id_to + count($transfer_values_to) - 1);
                            }

                            // Thực hiện gộp INSERT cho export_goods trong shop xuất (shop_1)
                            $export_sql_values = [];
                            foreach ($export_values as $index => $value) {
                                $transfer_id = $transfer_ids_from[$index]; // Sử dụng transfer_id từ shop_1
                                $export_sql_values[] = "('$import_date', {$value['subtotal']}, {$value['quantity']}, {$value['unit_price']}, $employee_id, {$value['product_id']}, $transfer_id, NOW())";
                            }
                            if (!empty($export_sql_values)) {
                                $sql_export = "INSERT INTO `$from_shop_db`.export_goods (export_date, total_price, quantity, unit_price, employee_id, product_id, transfer_id, created_at) VALUES " . implode(',', $export_sql_values);
                                if (!$conn_from_shop->query($sql_export)) {
                                    throw new Exception("Lỗi khi tạo bản ghi export_goods: " . $conn_from_shop->error);
                                }
                            }

                            // Thực hiện gộp INSERT cho import_goods trong shop nhập (shop_2)
                            $import_sql_values = [];
                            foreach ($import_values as $index => $value) {
                                $transfer_id = $transfer_ids_to[$index]; // Sử dụng transfer_id từ shop_2
                                $import_sql_values[] = "({$value['product_id']}, {$value['quantity']}, {$value['unit_price']}, {$value['subtotal']}, '$import_date', $employee_id, $transfer_id, NOW())";
                            }
                            if (!empty($import_sql_values)) {
                                $sql_import = "INSERT INTO `$shop_db`.import_goods (product_id, quantity, unit_price, total_price, import_date, employee_id, transfer_id, created_at) VALUES " . implode(',', $import_sql_values);
                                if (!$conn->query($sql_import)) {
                                    throw new Exception("Lỗi khi tạo bản ghi import_goods: " . $conn->error);
                                }
                            }

                            // Cập nhật tồn kho của shop xuất
                            foreach ($inventory_from_updates as $update_sql) {
                                if (!$conn_from_shop->query($update_sql)) {
                                    throw new Exception("Lỗi khi cập nhật tồn kho (shop xuất): " . $conn_from_shop->error);
                                }
                            }

                            // Cập nhật tồn kho của shop nhập
                            foreach ($inventory_to_updates as $update_sql) {
                                if (!$conn->query($update_sql)) {
                                    throw new Exception("Lỗi khi cập nhật tồn kho (shop nhập): " . $conn->error);
                                }
                            }

                            $conn->commit();
                            $conn_from_shop->commit();
                            header("Location: import_goods.php?import_added=success");
                            exit();
                        } catch (Exception $e) {
                            $conn->rollback();
                            $conn_from_shop->rollback();
                            $error = "Lỗi khi tạo đơn nhập hàng: " . $e->getMessage();
                            error_log("Lỗi khi tạo đơn nhập hàng: " . $e->getMessage());
                        } finally {
                            $conn->close();
                            $conn_from_shop->close();
                        }
                    }
                }
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
    <title>Tạo đơn nhập hàng - <?php echo htmlspecialchars($shop_name); ?></title>
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
            <h1>Tạo đơn nhập hàng - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
            <div class="actions">
                <a href="import_goods.php" class="btn btn-secondary"><i class="fa fa-arrow-left me-1"></i> Quay lại</a>
            </div>
        </header>

        <!-- Thông báo lỗi -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Form tạo đơn nhập hàng -->
        <section class="add-import-goods-form" style="width: 100%; margin: 0px 100px; max-width: 1200px">
            <form method="POST" action="">
                <input type="hidden" name="add_import_goods" value="1">
                <div class="row">
                    <!-- Cột bên trái -->
                    <div class="col-md-9">
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
                                    <button type="button" class="btn btn-secondary mt-2" id="select-multiple">Chọn nhiều</button>
                                </div>
                                <table class="table table-bordered" id="product-table">
                                    <thead>
                                    <tr>
                                        <th>Chọn</th>
                                        <th>Tên sản phẩm</th>
                                        <th>Tồn kho</th>
                                        <th>Số lượng</th>
                                        <th>Giá nhập</th>
                                        <th>Thành tiền</th>
                                    </tr>
                                    </thead>
                                    <tbody id="product-table-body">
                                    <!-- Sản phẩm sẽ được tải động qua AJAX -->
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
                                <div class="payment-item total">
                                    <p>Tiền cần trả</p>
                                    <p id="final-price">0₫</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cột bên phải -->
                    <div class="col-md-3">
                        <!-- Shop xuất -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Shop xuất</h2>
                            </div>
                            <div class="card-body">
                                <select class="form-select" name="from_shop_id" id="from-shop-id" required>
                                    <option value="">-- Chọn shop xuất --</option>
                                    <?php while ($shop = $result_shops->fetch_assoc()): ?>
                                        <option value="<?php echo $shop['id']; ?>" data-db-name="<?php echo $shop['db_name']; ?>">
                                            <?php echo htmlspecialchars($shop['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Nhân viên phụ trách -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Nhân viên phụ trách</h2>
                            </div>
                            <div class="card-body">
                                <select class="form-select" name="employee_id" required>
                                    <option value="">-- Chọn nhân viên --</option>
                                    <?php while ($employee = $result_employees->fetch_assoc()): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Ngày nhập hàng -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Ngày nhập hàng</h2>
                            </div>
                            <div class="card-body">
                                <input type="datetime-local" class="form-control" name="import_date" id="import_date" required>
                            </div>
                        </div>

                        <!-- Ghi chú -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Ghi chú</h2>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" name="note" rows="3" placeholder="VD: Chỉ nhận hàng trong giờ hành chính"></textarea>
                            </div>
                        </div>

                        <!-- Hành động -->
                        <div class="actions">
                            <button type="submit" class="btn btn-primary w-100">Tạo đơn nhập hàng</button>
                        </div>
                    </div>
                </div>
            </form>
        </section>
    </div>
</div>

<?php
// Đóng kết nối
$result_shops->free();
$result_employees->free();
$conn->close();
$conn_main->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                const quantity = parseInt(row.querySelector(".product-quantity").value) || 0;
                const unitPrice = parseFloat(row.querySelector(".product-unit-price").value) || 0;
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

    // Hàm định dạng ngày giờ cho input datetime-local (không bao gồm giây)
    function formatDateTime(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    // Đặt giá trị mặc định cho ngày nhập hàng là thời gian hiện tại
    document.addEventListener('DOMContentLoaded', function() {
        const now = new Date();
        const importDateInput = document.getElementById("import_date");
        importDateInput.value = formatDateTime(now);
    });

    // Hàm debounce để giảm số lần gọi hàm tìm kiếm
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Tìm kiếm sản phẩm
    const searchProducts = debounce(function() {
        const searchValue = document.getElementById("product-search").value.toLowerCase();
        const rows = document.querySelectorAll("#product-table tbody tr");

        rows.forEach(row => {
            const productName = row.cells[1].textContent.toLowerCase();
            row.style.display = productName.includes(searchValue) ? "" : "none";
        });
    }, 300);

    document.getElementById("product-search").addEventListener("input", searchProducts);

    // Tải danh sách sản phẩm từ shop được chọn
    document.getElementById("from-shop-id").addEventListener("change", function() {
        const shopId = this.value;
        const dbName = this.options[this.selectedIndex].getAttribute("data-db-name");

        if (!dbName) {
            document.getElementById("product-table-body").innerHTML = "";
            calculateTotalPrice();
            return;
        }

        // Gửi yêu cầu AJAX để lấy danh sách sản phẩm
        $.ajax({
            url: "fetch_products.php",
            method: "POST",
            data: { db_name: dbName },
            dataType: "json",
            success: function(response) {
                const tbody = document.getElementById("product-table-body");
                const fragment = document.createDocumentFragment();

                if (response.error) {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td colspan="6" class="text-center">${response.error}</td>`;
                    fragment.appendChild(row);
                } else {
                    response.products.forEach(product => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td><input type="checkbox" name="products[]" value="${product.id}" class="product-checkbox"></td>
                            <td>${product.name}</td>
                            <td>${product.quantity || 0}</td>
                            <td><input type="number" name="quantities[]" min="0" value="0" class="form-control product-quantity"></td>
                            <td><input type="number" name="unit_prices[]" min="0" value="${product.price}" class="form-control product-unit-price"></td>
                            <td class="subtotal">0</td>
                        `;
                        fragment.appendChild(row);
                    });
                }

                tbody.innerHTML = '';
                tbody.appendChild(fragment);

                // Gắn lại sự kiện cho các input mới
                document.querySelectorAll(".product-checkbox, .product-quantity, .product-unit-price").forEach(element => {
                    element.addEventListener("change", calculateTotalPrice);
                });

                calculateTotalPrice();
            },
            error: function() {
                const row = document.createElement('tr');
                row.innerHTML = `<td colspan="6" class="text-center">Lỗi khi tải danh sách sản phẩm.</td>`;
                document.getElementById("product-table-body").innerHTML = '';
                document.getElementById("product-table-body").appendChild(row);
                calculateTotalPrice();
            }
        });
    });

    // Gắn sự kiện cho các input ban đầu (nếu có)
    document.querySelectorAll(".product-checkbox, .product-quantity, .product-unit-price").forEach(element => {
        element.addEventListener("change", calculateTotalPrice);
    });
</script>
</body>
</html>