<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Phiên đăng nhập không hợp lệ: " . print_r($_SESSION, true));
    header("Location: ../login_view.php");
    exit();
}

// Bao gồm file kết nối cơ sở dữ liệu và model
include_once '../config/db_connect.php';
include_once '../models/ReportModel.php';

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("session_username được gán: " . $session_username);

// Khởi tạo Model
$model = new ReportModel($host, $username, $password, $shop_db);

// Hàm định dạng tiền tệ
function formatCurrency($number) {
    $number = floatval($number);
    if (floor($number) == $number) {
        return number_format($number, 0, ',', '.');
    }
    return number_format($number, 2, ',', '.');
}

// Lấy năm và tháng từ query string, mặc định là năm và tháng hiện tại
$selected_year = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_month = isset($_GET['month']) && is_numeric($_GET['month']) && $_GET['month'] >= 1 && $_GET['month'] <= 12 ? intval($_GET['month']) : date('m');

// Lấy dữ liệu báo cáo
try {
    $daily_revenue = $model->getDailyRevenue();
    $monthly_revenue = $model->getMonthlyRevenue();
    $yearly_revenue = $model->getYearlyRevenue();
    $inventory = $model->getInventory();
    $years = $model->getYears();
    if (empty($years)) {
        $years = [date('Y')]; // Mặc định năm hiện tại nếu không có dữ liệu
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

// Đóng kết nối
$model->close();

// Tải View
include '../view/report_view.php';
?>