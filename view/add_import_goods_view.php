<?php
// Ngăn truy cập trực tiếp vào View
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}
$session_username = $_SESSION['username'] ?? 'Khách';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo đơn nhập hàng - <?php echo htmlspecialchars($shop_name); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSB7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Fallback cục bộ cho Font Awesome -->
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css" onerror="this.onerror=null;this.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css';">
</head>
<body>
<div id="main">
    <!-- Sidebar -->
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
                <li><a href="switch_shop_view.php"><i class="fas fa-exchange-alt"></i> Switch Cơ Sở</a></li>
                <li><a href="../view/add_shop.php"><i class="fas fa-plus-circle"></i> Thêm Cơ Sở</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Header -->
    <div id="header" class="bg-light py-2 shadow-sm">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="input-group w-50">
                <input type="text" class="form-control" placeholder="Tìm kiếm...">
                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="../img/avatar/avatar.png" alt="Avatar" class="rounded-circle me-2" width="40" height="40">
                    <span class="fw-bold"><?php echo htmlspecialchars($session_username); ?></span>
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
                <a href="../view/import_goods.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Quay lại</a>
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
            <form method="POST" action="../controllers/ImportGoodsController.php">
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
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
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
                                    <?php foreach ($shops as $shop): ?>
                                        <option value="<?php echo $shop['id']; ?>" data-db-name="<?php echo $shop['db_name']; ?>">
                                            <?php echo htmlspecialchars($shop['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Nhân viên phụ trách -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h2>Nhân viên phụ trách</h2>
                            </div>
                            <div class="card-body">
                                <select class="form-select" name="user_id" required>
                                    <option value="">-- Chọn nhân viên --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
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
            if (checkbox && checkbox.checked) {
                const quantityInput = row.querySelector(".product-quantity");
                const unitPriceInput = row.querySelector(".product-unit-price");
                const quantity = parseInt(quantityInput.value) || 0;
                const unitPrice = parseFloat(unitPriceInput.dataset.price) || 0;
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

    // Hàm định dạng ngày giờ
    function formatDateTime(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    // Đặt giá trị mặc định cho ngày nhập hàng
    document.addEventListener('DOMContentLoaded', function() {
        const now = new Date();
        const importDateInput = document.getElementById("import_date");
        importDateInput.value = formatDateTime(now);
    });

    // Hàm debounce
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
            url: "../controllers/ImportGoodsController.php",
            method: "POST",
            data: { fetch_products: true, db_name: dbName },
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
                        <td><input type="number" name="unit_prices[]" min="0" value="${product.price}" class="form-control product-unit-price" data-price="${product.price}"></td>
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

    // Gắn sự kiện cho các input ban đầu
    document.querySelectorAll(".product-checkbox, .product-quantity, .product-unit-price").forEach(element => {
        element.addEventListener("change", calculateTotalPrice);
    });

    // Chọn nhiều sản phẩm
    document.getElementById("select-multiple").addEventListener("click", function() {
        const checkboxes = document.querySelectorAll(".product-checkbox");
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
        calculateTotalPrice();
    });
</script>
</body>
</html>