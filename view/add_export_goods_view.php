<?php
// Ngăn truy cập trực tiếp vào View
if (!isset($session_username)) {
    header("Location: ../controllers/ExportGoodsController.php");
    exit();
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
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="../view/employee.php"><i class="fa fa-user-tie"></i> Nhân viên</a></li>
            <?php endif; ?>
            <li><a href="flash_sale_view.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
            <li><a href="report_view.php"><i class="fa fa-chart-bar"></i> Báo cáo</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
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
            <form method="POST" action="../controllers/ExportGoodsController.php">
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
                                    <?php foreach ($products as $row): ?>
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
                                    <?php endforeach; ?>
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
                                    <?php foreach ($suppliers as $row): ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                                    <?php endforeach; ?>
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
                                        <?php foreach ($employees as $row): ?>
                                            <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
                                        <?php endforeach; ?>
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