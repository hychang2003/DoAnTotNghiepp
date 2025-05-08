<?php
session_start();
include '../config/db_connect.php';
include '../models/ExportGoodsModel.php';

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khởi tạo các biến mặc định
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';
$session_username = $_SESSION['username'] ?? 'Khách'; // Tên người dùng ứng dụng
error_log("session_username được gán: " . $session_username);

// Khởi tạo Model
$model = new ExportGoodsModel($host, $username, $password, 'fashion_shop', $shop_db);

// Lấy tên cửa hàng
try {
    $shop_name = $model->getShopName($shop_db);
} catch (Exception $e) {
    error_log("Lỗi khi lấy tên cửa hàng: " . $e->getMessage());
    $error = "Không thể lấy tên cửa hàng.";
    $shop_name = $shop_db;
}

// Lấy danh sách nhà cung cấp, nhân viên và sản phẩm
try {
    $suppliers = $model->getSuppliers();
    $employees = $model->getEmployees();
    $products = $model->getProducts();
} catch (Exception $e) {
    error_log("Lỗi khi lấy dữ liệu: " . $e->getMessage());
    $error = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
    $suppliers = [];
    $employees = [];
    $products = [];
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
            try {
                $stock = $model->checkStock($product_id);
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
            } catch (Exception $e) {
                error_log("Lỗi kiểm tra tồn kho: " . $e->getMessage());
                $error = "Lỗi kiểm tra tồn kho: " . $e->getMessage();
                break;
            }
        }

        if (!isset($error) && empty($export_details)) {
            $error = "Vui lòng chọn ít nhất một sản phẩm với số lượng và giá xuất hợp lệ.";
        } elseif (!isset($error)) {
            try {
                $model->addExportGoods($export_details, $export_date, $employee_id, $note);
                header("Location: ../view/export_goods.php?export_added=success");
                exit();
            } catch (Exception $e) {
                error_log("Lỗi khi tạo đơn xuất hàng: " . $e->getMessage());
                $error = "Lỗi khi tạo đơn xuất hàng: " . $e->getMessage();
            }
        }
    }
}

// Đóng kết nối
$model->close();

// Tải View
include '../view/add_export_goods_view.php';
?>