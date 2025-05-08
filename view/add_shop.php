<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

// Kiểm tra quyền truy cập
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=access_denied");
    exit();
}

// Kết nối database chính
include '../config/db_connect.php';
$conn = $conn_main;

// Xử lý thêm cơ sở mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shop'])) {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name) || empty($address)) {
        $error = "Vui lòng nhập đầy đủ tên và địa chỉ cơ sở!";
    } else {
        // Thêm cơ sở vào bảng shop
        $sql = "INSERT INTO shop (name, address, db_name) VALUES (?, ?, '')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $name, $address);
        if ($stmt->execute()) {
            $shop_id = $stmt->insert_id;
            $stmt->close();

            // Tạo tên cơ sở dữ liệu (shop_ + shop_id)
            $db_name = "shop_$shop_id";
            $sql_update = "UPDATE shop SET db_name = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param('si', $db_name, $shop_id);
            $stmt_update->execute();
            $stmt_update->close();

            // Tạo cơ sở dữ liệu mới
            $sql_create_db = "CREATE DATABASE IF NOT EXISTS `$db_name`";
            if ($conn->query($sql_create_db) === TRUE) {
                // Kết nối đến cơ sở dữ liệu mới
                $conn_new = new mysqli($host, $username, $password, $db_name);
                if ($conn_new->connect_error) {
                    $error = "Lỗi kết nối cơ sở dữ liệu mới: " . $conn_new->connect_error;
                } else {
                    // Tạo bảng category
                    $sql_create_category = "
                        CREATE TABLE category (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(255) NOT NULL,
                            description TEXT NOT NULL,
                            icon VARCHAR(255) NOT NULL,
                            `order` INT NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_category);

                    // Sao chép dữ liệu category từ fashion_shop
                    $sql_copy_category = "INSERT INTO `$db_name`.category (id, name, description, icon, `order`)
                                          SELECT id, name, description, icon, `order` FROM fashion_shop.category";
                    $conn->query($sql_copy_category);

                    // Tạo bảng customer
                    $sql_create_customer = "
                        CREATE TABLE customer (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(255) NOT NULL,
                            phone_number VARCHAR(11) NOT NULL,
                            email VARCHAR(100) DEFAULT NULL,
                            address TEXT DEFAULT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY phone_number (phone_number),
                            UNIQUE KEY email (email)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_customer);

                    // Tạo bảng employee
                    $sql_create_employee = "
                        CREATE TABLE employee (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(255) NOT NULL,
                            phone_number VARCHAR(11) DEFAULT NULL,
                            email VARCHAR(100) NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY email (email)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_employee);

                    // Sao chép dữ liệu employee từ fashion_shop.users
                    $sql_copy_employees = "INSERT INTO `$db_name`.employee (id, name, phone_number, email)
                                           SELECT id, name, phone_number, email FROM fashion_shop.users WHERE role = 'employee'";
                    $conn->query($sql_copy_employees);

                    // Tạo bảng flash_sale
                    $sql_create_flash_sale = "
                        CREATE TABLE flash_sale (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(100) NOT NULL,
                            discount DECIMAL(5,2) NOT NULL,
                            start_date DATETIME NOT NULL,
                            end_date DATETIME NOT NULL,
                            status TINYINT(1) NOT NULL DEFAULT 1,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_flash_sale);

                    // Tạo bảng product
                    $sql_create_product = "
                        CREATE TABLE product (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            name VARCHAR(255) NOT NULL,
                            description TEXT NOT NULL,
                            create_date DATETIME NOT NULL,
                            update_date DATETIME NOT NULL,
                            employee_id INT NOT NULL,
                            image VARCHAR(255) NOT NULL,
                            category_id INT NOT NULL,
                            type VARCHAR(50) NOT NULL DEFAULT 'general',
                            unit VARCHAR(50) NOT NULL,
                            price DECIMAL(10,2) NOT NULL,
                            flash_sale_id INT DEFAULT NULL,
                            cost_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (employee_id) REFERENCES employee(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (category_id) REFERENCES category(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (flash_sale_id) REFERENCES flash_sale(id) ON DELETE SET NULL ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_product);

                    // Tạo bảng product_flash_sale
                    $sql_create_product_flash_sale = "
                        CREATE TABLE product_flash_sale (
                            product_id INT NOT NULL,
                            flash_sale_id INT NOT NULL,
                            PRIMARY KEY (product_id, flash_sale_id),
                            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE,
                            FOREIGN KEY (flash_sale_id) REFERENCES flash_sale(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_product_flash_sale);

                    // Tạo bảng image_product
                    $sql_create_image_product = "
                        CREATE TABLE image_product (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            product_id INT NOT NULL,
                            image_url VARCHAR(255) NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_image_product);

                    // Tạo bảng product_option
                    $sql_create_product_option = "
                        CREATE TABLE product_option (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            product_id INT NOT NULL,
                            size VARCHAR(10) NOT NULL,
                            color VARCHAR(50) NOT NULL,
                            image_url VARCHAR(255) NOT NULL,
                            stock_quantity INT NOT NULL DEFAULT 0,
                            price DECIMAL(10,2) NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_product_option);

                    // Tạo bảng inventory (bỏ cột last_updated)
                    $sql_create_inventory = "
                        CREATE TABLE inventory (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            product_id INT NOT NULL,
                            quantity INT NOT NULL DEFAULT 0,
                            unit VARCHAR(50) NOT NULL,
                            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_inventory);

                    // Tạo bảng order
                    $sql_create_order = "
                        CREATE TABLE `order` (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            customer_id INT NULL,
                            employee_id INT DEFAULT NULL,
                            order_date DATETIME NOT NULL,
                            total_price DECIMAL(10,2) NOT NULL,
                            status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (customer_id) REFERENCES customer(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (employee_id) REFERENCES employee(id) ON DELETE SET NULL ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_order);

                    // Tạo bảng order_detail
                    $sql_create_order_detail = "
                        CREATE TABLE order_detail (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            order_id INT NOT NULL,
                            product_id INT NOT NULL,
                            product_option_id INT DEFAULT NULL,
                            quantity INT NOT NULL,
                            unit_price DECIMAL(10,2) NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (order_id) REFERENCES `order`(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (product_option_id) REFERENCES product_option(id) ON DELETE SET NULL ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_order_detail);

                    // Tạo bảng customer_purchase
                    $sql_create_customer_purchase = "
                        CREATE TABLE customer_purchase (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            customer_id INT NOT NULL,
                            product_id INT NOT NULL,
                            purchase_date DATETIME NOT NULL,
                            quantity INT NOT NULL,
                            total_price DECIMAL(10,2) NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (customer_id) REFERENCES customer(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_customer_purchase);

                    // Tạo bảng import_goods
                    $sql_create_import_goods = "
                        CREATE TABLE import_goods (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            product_id INT NOT NULL,
                            supplier_id INT NOT NULL,
                            quantity INT NOT NULL,
                            unit_price DECIMAL(10,2) NOT NULL,
                            total_price DECIMAL(10,2) NOT NULL,
                            import_date DATETIME NOT NULL,
                            employee_id INT NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (supplier_id) REFERENCES fashion_shop.supplier(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (employee_id) REFERENCES employee(id) ON DELETE CASCADE ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_import_goods);

                    // Tạo bảng export_goods
                    $sql_create_export_goods = "
                        CREATE TABLE export_goods (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            product_id INT NOT NULL,
                            quantity INT NOT NULL,
                            unit_price DECIMAL(10,2) NOT NULL,
                            total_price DECIMAL(10,2) NOT NULL,
                            export_date DATETIME NOT NULL,
                            employee_id INT NOT NULL,
                            reason TEXT DEFAULT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (employee_id) REFERENCES employee(id) ON DELETE CASCADE ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_export_goods);

                    // Tạo bảng transfer_stock
                    $sql_create_transfer_stock = "
                        CREATE TABLE transfer_stock (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            product_id INT NOT NULL,
                            from_shop_id INT NOT NULL,
                            to_shop_id INT NOT NULL,
                            quantity INT NOT NULL,
                            transfer_date DATETIME NOT NULL,
                            employee_id INT NOT NULL,
                            status ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (from_shop_id) REFERENCES fashion_shop.shop(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (to_shop_id) REFERENCES fashion_shop.shop(id) ON DELETE CASCADE ON UPDATE CASCADE,
                            FOREIGN KEY (employee_id) REFERENCES employee(id) ON DELETE CASCADE ON UPDATE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $conn_new->query($sql_create_transfer_stock);

                    $conn_new->close();
                    header("Location: add_shop.php?shop_added=success");
                    exit();
                }
            } else {
                $error = "Lỗi khi tạo cơ sở dữ liệu mới: " . $conn->error;
            }
        } else {
            $error = "Lỗi khi thêm cơ sở: " . $stmt->error;
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm cơ sở mới</title>
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
            <h1>Thêm cơ sở mới</h1>
        </header>

        <!-- Thông báo -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif (isset($_GET['shop_added']) && $_GET['shop_added'] == 'success'): ?>
            <div class="alert alert-success" role="alert">
                Thêm cơ sở thành công!
            </div>
        <?php endif; ?>

        <!-- Form thêm cơ sở -->
        <div class="card mt-3">
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="add_shop" value="1">
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên cơ sở</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Nhập tên cơ sở" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Địa chỉ</label>
                        <textarea class="form-control" id="address" name="address" placeholder="Nhập địa chỉ cơ sở" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Thêm cơ sở</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>