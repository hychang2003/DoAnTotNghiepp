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

// --- Doanh thu theo ngày (30 ngày gần nhất) ---
$daily_revenue_data = [];
$daily_revenue_labels = [];
$sql_daily = "SELECT DATE(order_date) AS order_day, SUM(total_price) AS total_revenue
              FROM `order`
              WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND status = 'completed'
              GROUP BY DATE(order_date)
              ORDER BY order_date ASC";
$result_daily = $conn->query($sql_daily);
if ($result_daily) {
    while ($row = $result_daily->fetch_assoc()) {
        $daily_revenue_labels[] = date('d/m/Y', strtotime($row['order_day']));
        $daily_revenue_data[] = $row['total_revenue'];
    }
}

// --- Doanh thu theo tháng (trong năm hiện tại) ---
$monthly_revenue_data = array_fill(1, 12, 0); // Mảng 12 tháng, mặc định là 0
$monthly_revenue_labels = ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
$sql_monthly = "SELECT MONTH(order_date) AS order_month, SUM(total_price) AS total_revenue
                FROM `order`
                WHERE YEAR(order_date) = YEAR(CURDATE())
                AND status = 'completed'
                GROUP BY MONTH(order_date)";
$result_monthly = $conn->query($sql_monthly);
if ($result_monthly) {
    while ($row = $result_monthly->fetch_assoc()) {
        $monthly_revenue_data[$row['order_month']] = $row['total_revenue'];
    }
}

// --- Doanh thu theo năm ---
$yearly_revenue_data = [];
$yearly_revenue_labels = [];
$sql_yearly = "SELECT YEAR(order_date) AS order_year, SUM(total_price) AS total_revenue
               FROM `order`
               WHERE status = 'completed'
               GROUP BY YEAR(order_date)
               ORDER BY order_year ASC";
$result_yearly = $conn->query($sql_yearly);
if ($result_yearly) {
    while ($row = $result_yearly->fetch_assoc()) {
        $yearly_revenue_labels[] = $row['order_year'];
        $yearly_revenue_data[] = $row['total_revenue'];
    }
}

// --- Thống kê hàng tồn kho ---
$sql_inventory = "SELECT p.id, p.name, i.quantity
                 FROM product p
                 LEFT JOIN inventory i ON p.id = i.product_id";
$result_inventory = $conn->query($sql_inventory);

// --- Báo cáo lợi nhuận ---
// Lấy năm và tháng từ form (nếu có)
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

// Lấy danh sách năm để hiển thị trong dropdown
$sql_years = "SELECT DISTINCT YEAR(order_date) AS year FROM `order` ORDER BY year DESC";
$result_years = $conn->query($sql_years);

// 1. Báo cáo lợi nhuận theo năm
$sql_profit_by_year = "
    SELECT 
        YEAR(o.order_date) AS year,
        SUM((od.unit_price - p.cost_price) * od.quantity) AS total_profit
    FROM `order` o
    JOIN `order_detail` od ON o.id = od.order_id
    JOIN `product` p ON od.product_id = p.id
    WHERE o.status = 'completed'
    GROUP BY YEAR(o.order_date)
    ORDER BY year DESC";
$result_profit_by_year = $conn->query($sql_profit_by_year);

// 2. Báo cáo lợi nhuận theo tháng (trong năm được chọn)
$sql_profit_by_month = "
    SELECT 
        YEAR(o.order_date) AS year,
        MONTH(o.order_date) AS month,
        SUM((od.unit_price - p.cost_price) * od.quantity) AS total_profit
    FROM `order` o
    JOIN `order_detail` od ON o.id = od.order_id
    JOIN `product` p ON od.product_id = p.id
    WHERE o.status = 'completed'
        AND YEAR(o.order_date) = ?
    GROUP BY YEAR(o.order_date), MONTH(o.order_date)
    ORDER BY year DESC, month DESC";
$stmt_profit_by_month = $conn->prepare($sql_profit_by_month);
$stmt_profit_by_month->bind_param('i', $selected_year);
$stmt_profit_by_month->execute();
$result_profit_by_month = $stmt_profit_by_month->get_result();

// 3. Báo cáo lợi nhuận theo ngày (trong tháng và năm được chọn)
$sql_profit_by_day = "
    SELECT 
        DATE(o.order_date) AS order_date,
        SUM((od.unit_price - p.cost_price) * od.quantity) AS total_profit
    FROM `order` o
    JOIN `order_detail` od ON o.id = od.order_id
    JOIN `product` p ON od.product_id = p.id
    WHERE o.status = 'completed'
        AND YEAR(o.order_date) = ?
        AND MONTH(o.order_date) = ?
    GROUP BY DATE(o.order_date)
    ORDER BY order_date DESC";
$stmt_profit_by_day = $conn->prepare($sql_profit_by_day);
$stmt_profit_by_day->bind_param('ii', $selected_year, $selected_month);
$stmt_profit_by_day->execute();
$result_profit_by_day = $stmt_profit_by_day->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Thêm Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <h1>Báo cáo</h1>
        </header>

        <div class="card mt-3">
            <div class="card-body">
                <!-- Biểu đồ doanh thu theo ngày -->
                <h3>Doanh thu theo ngày (30 ngày gần nhất)</h3>
                <canvas id="dailyRevenueChart" height="100"></canvas>

                <!-- Biểu đồ doanh thu theo tháng -->
                <h3 class="mt-5">Doanh thu theo tháng (Năm <?php echo date('Y'); ?>)</h3>
                <canvas id="monthlyRevenueChart" height="100"></canvas>

                <!-- Biểu đồ doanh thu theo năm -->
                <h3 class="mt-5">Doanh thu theo năm</h3>
                <canvas id="yearlyRevenueChart" height="100"></canvas>

                <!-- Thống kê hàng tồn kho -->
                <h3 class="mt-5">Thống kê hàng tồn kho</h3>
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th>ID Sản phẩm</th>
                        <th>Tên sản phẩm</th>
                        <th>Số lượng tồn</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($result_inventory->num_rows > 0): ?>
                        <?php while ($row = $result_inventory->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['quantity'] ?? 0); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">Không có dữ liệu tồn kho.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <!-- Báo cáo lợi nhuận -->
                <h3 class="mt-5">Báo cáo lợi nhuận</h3>

                <!-- Form lọc năm và tháng -->
                <div class="card mt-3">
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="year" class="form-label">Chọn năm</label>
                                    <select class="form-control" id="year" name="year" onchange="this.form.submit()">
                                        <?php while ($year = $result_years->fetch_assoc()): ?>
                                            <option value="<?php echo $year['year']; ?>" <?php echo $year['year'] == $selected_year ? 'selected' : ''; ?>>
                                                <?php echo $year['year']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="month" class="form-label">Chọn tháng</label>
                                    <select class="form-control" id="month" name="month" onchange="this.form.submit()">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                                Tháng <?php echo $m; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Báo cáo lợi nhuận theo năm -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5>Lợi nhuận theo năm</h5>
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Năm</th>
                                <th>Tổng lợi nhuận (VNĐ)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($result_profit_by_year->num_rows > 0): ?>
                                <?php while ($row = $result_profit_by_year->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['year']); ?></td>
                                        <td><?php echo formatCurrency($row['total_profit']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">Không có dữ liệu.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Báo cáo lợi nhuận theo tháng -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5>Lợi nhuận theo tháng (Năm <?php echo $selected_year; ?>)</h5>
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Tháng</th>
                                <th>Tổng lợi nhuận (VNĐ)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($result_profit_by_month->num_rows > 0): ?>
                                <?php while ($row = $result_profit_by_month->fetch_assoc()): ?>
                                    <tr>
                                        <td>Tháng <?php echo htmlspecialchars($row['month']); ?></td>
                                        <td><?php echo formatCurrency($row['total_profit']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">Không có dữ liệu.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Báo cáo lợi nhuận theo ngày -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5>Lợi nhuận theo ngày (Tháng <?php echo $selected_month; ?> / <?php echo $selected_year; ?>)</h5>
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Tổng lợi nhuận (VNĐ)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($result_profit_by_day->num_rows > 0): ?>
                                <?php while ($row = $result_profit_by_day->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['order_date']); ?></td>
                                        <td><?php echo formatCurrency($row['total_profit']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">Không có dữ liệu.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Đóng kết nối
$result_daily->free();
$result_monthly->free();
$result_yearly->free();
$result_inventory->free();
$result_years->free();
$result_profit_by_year->free();
$result_profit_by_month->free();
$result_profit_by_day->free();
$conn->close();
?>

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Biểu đồ doanh thu theo ngày
    const dailyRevenueChart = new Chart(document.getElementById('dailyRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($daily_revenue_labels); ?>,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: <?php echo json_encode($daily_revenue_data); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Doanh thu (VNĐ)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('vi-VN') + ' VNĐ';
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Ngày'
                    }
                }
            }
        }
    });

    // Biểu đồ doanh thu theo tháng
    const monthlyRevenueChart = new Chart(document.getElementById('monthlyRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_revenue_labels); ?>,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: <?php echo json_encode(array_values($monthly_revenue_data)); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Doanh thu (VNĐ)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('vi-VN') + ' VNĐ';
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Tháng'
                    }
                }
            }
        }
    });

    // Biểu đồ doanh thu theo năm
    const yearlyRevenueChart = new Chart(document.getElementById('yearlyRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($yearly_revenue_labels); ?>,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: <?php echo json_encode($yearly_revenue_data); ?>,
                backgroundColor: 'rgba(153, 102, 255, 0.6)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Doanh thu (VNĐ)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('vi-VN') + ' VNĐ';
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Năm'
                    }
                }
            }
        }
    });
</script>
</body>
</html>