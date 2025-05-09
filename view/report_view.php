<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Chuyển hướng đến login_view.php do chưa đăng nhập.");
    header("Location: ../login_view.php");
    exit();
}

include_once '../config/db_connect.php';
include_once '../models/ReportModel.php';

$shop_db = $_SESSION['shop_db'] ?? 'shop_11';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("session_username được gán: " . $session_username);

try {
    $model = new ReportModel($host, $username, $password, $shop_db);
    $shop_name = $model->getShopName();
} catch (Exception $e) {
    error_log("Lỗi khởi tạo ReportModel hoặc lấy tên cửa hàng: " . $e->getMessage());
    $_SESSION['form_errors'] = ["Lỗi khởi tạo mô hình dữ liệu: " . $e->getMessage()];
    $shop_name = 'Cửa hàng mặc định';
    $daily_revenue = ['labels' => [], 'data' => []];
    $monthly_revenue = ['labels' => [], 'data' => []];
    $yearly_revenue = ['labels' => [], 'data' => []];
    $inventory = [];
    $years = [date('Y')];
    $profit_by_year = [];
    $profit_by_month = [];
    $profit_by_day = [];
    $selected_year = date('Y');
    $selected_month = date('m');
}

function formatCurrency($number) {
    return number_format(floatval($number), 0, ',', '.') . ' VNĐ';
}

$selected_year = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_month = isset($_GET['month']) && is_numeric($_GET['month']) && $_GET['month'] >= 1 && $_GET['month'] <= 12 ? intval($_GET['month']) : date('m');

try {
    $daily_revenue = $model->getDailyRevenue();
    $monthly_revenue = $model->getMonthlyRevenue($selected_year);
    $yearly_revenue = $model->getYearlyRevenue();
    $inventory = $model->getInventory();
    $years = $model->getYears();
    if (empty($years)) {
        $years = [date('Y')];
    }
    $profit_by_year = $model->getProfitByYear();
    $profit_by_month = $model->getProfitByMonth($selected_year);
    $profit_by_day = $model->getProfitByDay($selected_year, $selected_month);
} catch (Exception $e) {
    error_log("Lỗi khi lấy dữ liệu báo cáo: " . $e->getMessage());
    $_SESSION['form_errors'] = ["Lỗi khi lấy dữ liệu báo cáo: " . $e->getMessage()];
    $daily_revenue = ['labels' => [], 'data' => []];
    $monthly_revenue = ['labels' => [], 'data' => []];
    $yearly_revenue = ['labels' => [], 'data' => []];
    $inventory = [];
    $years = [date('Y')];
    $profit_by_year = [];
    $profit_by_month = [];
    $profit_by_day = [];
}

$model->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo - <?php echo htmlspecialchars($shop_name); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div id="main">
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
            <li><a href="../controllers/FlashSaleController.php"><i class="fa fa-tags"></i> Khuyến mại</a></li>
            <li><a href="../view/report_view.php"><i class="fa fa-chart-bar"></i> Báo cáo</a></li>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="switch_shop_view.php"><i class="fa fa-exchange-alt"></i> Switch Cơ Sở</a></li>
                <li><a href="../view/add_shop.php"><i class="fa fa-plus-circle"></i> Thêm Cơ Sở</a></li>
            <?php endif; ?>
        </ul>
    </div>

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

    <div class="content">
        <header class="header">
            <h1>Báo cáo - Cơ sở: <?php echo htmlspecialchars($shop_name); ?></h1>
        </header>

        <?php if (!empty($_SESSION['form_errors'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php foreach ($_SESSION['form_errors'] as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
                <?php unset($_SESSION['form_errors']); ?>
            </div>
        <?php endif; ?>

        <div class="card mt-3">
            <div class="card-body">
                <h3>Doanh thu theo ngày (30 ngày gần nhất)</h3>
                <canvas id="dailyRevenueChart" height="100"></canvas>

                <h3 class="mt-5">Doanh thu theo tháng (Năm <?php echo htmlspecialchars($selected_year); ?>)</h3>
                <canvas id="monthlyRevenueChart" height="100"></canvas>

                <h3 class="mt-5">Doanh thu theo năm</h3>
                <canvas id="yearlyRevenueChart" height="100"></canvas>

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
                    <?php if (!empty($inventory)): ?>
                        <?php foreach ($inventory as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['quantity'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">Không có dữ liệu tồn kho.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <h3 class="mt-5">Báo cáo lợi nhuận</h3>

                <div class="card mt-3">
                    <div class="card-body">
                        <form method="GET" action="../view/report_view.php">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="year" class="form-label">Chọn năm</label>
                                    <select class="form-control" id="year" name="year" onchange="this.form.submit()">
                                        <?php foreach ($years as $year): ?>
                                            <option value="<?php echo $year; ?>" <?php echo ($year == $selected_year) ? 'selected' : ''; ?>>
                                                <?php echo $year; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="month" class="form-label">Chọn tháng</label>
                                    <select class="form-control" id="month" name="month" onchange="this.form.submit()">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo ($m == $selected_month) ? 'selected' : ''; ?>>
                                                Tháng <?php echo $m; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

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
                            <?php if (!empty($profit_by_year)): ?>
                                <?php foreach ($profit_by_year as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['year']); ?></td>
                                        <td><?php echo formatCurrency($row['total_profit'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">Không có dữ liệu lợi nhuận.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h5>Lợi nhuận theo tháng (Năm <?php echo htmlspecialchars($selected_year); ?>)</h5>
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Tháng</th>
                                <th>Tổng lợi nhuận (VNĐ)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($profit_by_month)): ?>
                                <?php foreach ($profit_by_month as $row): ?>
                                    <tr>
                                        <td>Tháng <?php echo htmlspecialchars($row['month']); ?></td>
                                        <td><?php echo formatCurrency($row['total_profit'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">Không có dữ liệu lợi nhuận.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h5>Lợi nhuận theo ngày (Tháng <?php echo htmlspecialchars($selected_month); ?> / <?php echo htmlspecialchars($selected_year); ?>)</h5>
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Tổng lợi nhuận (VNĐ)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($profit_by_day)): ?>
                                <?php foreach ($profit_by_day as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['order_date']); ?></td>
                                        <td><?php echo formatCurrency($row['total_profit'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">Không có dữ liệu lợi nhuận.</td>
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

<script src="../assets/js/script.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Debug dữ liệu biểu đồ
    console.log('Daily Revenue:', <?php echo json_encode($daily_revenue); ?>);
    console.log('Monthly Revenue:', <?php echo json_encode($monthly_revenue); ?>);
    console.log('Yearly Revenue:', <?php echo json_encode($yearly_revenue); ?>);

    const dailyRevenueChart = new Chart(document.getElementById('dailyRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($daily_revenue['labels'] ?? []); ?>,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: <?php echo json_encode($daily_revenue['data'] ?? []); ?>,
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

    const monthlyRevenueChart = new Chart(document.getElementById('monthlyRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_revenue['labels'] ?? []); ?>,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: <?php echo json_encode($monthly_revenue['data'] ?? []); ?>,
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

    const yearlyRevenueChart = new Chart(document.getElementById('yearlyRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($yearly_revenue['labels'] ?? []); ?>,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: <?php echo json_encode($yearly_revenue['data'] ?? []); ?>,
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