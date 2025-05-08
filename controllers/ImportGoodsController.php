<?php
session_start();
include '../config/db_connect.php';
include '../models/ImportGoodsModel.php';

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
$shop_db = $_SESSION['shop_db'] ?? 'shop_2';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("session_username được gán: " . $session_username);

// Khởi tạo Model
$model = new ImportGoodsModel($host, $username, $password, 'fashion_shop', $shop_db);

// Lấy thông tin shop hiện tại
try {
    $shop_info = $model->getShopInfo($shop_db);
    $shop_name = $shop_info['name'];
    $current_shop_id = $shop_info['id'];
} catch (Exception $e) {
    error_log("Lỗi khi lấy thông tin shop: " . $e->getMessage());
    $error = "Không thể lấy thông tin shop: " . $e->getMessage();
    $shop_name = $shop_db;
    $current_shop_id = 0;
}

// Lấy danh sách các shop khác và nhân viên
try {
    $shops = $model->getOtherShops($shop_db);
    $employees = $model->getEmployees();
} catch (Exception $e) {
    error_log("Lỗi khi lấy dữ liệu: " . $e->getMessage());
    $error = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
    $shops = [];
    $employees = [];
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
    try {
        $from_shop_db = $model->getFromShopDbName($from_shop_id);
    } catch (Exception $e) {
        $error = "Lỗi khi lấy thông tin shop xuất: " . $e->getMessage();
        error_log("Lỗi: " . $e->getMessage());
    }

    // Kiểm tra dữ liệu đầu vào
    if (empty($from_shop_id) || empty($from_shop_db) || empty($employee_id) || empty($products) || empty($quantities) || empty($unit_prices)) {
        $error = "Vui lòng điền đầy đủ thông tin: shop xuất, nhân viên phụ trách, và ít nhất một sản phẩm.";
    } else {
        $total_price = 0;
        $import_details = [];

        foreach ($products as $index => $product_id) {
            if (empty($quantities[$index]) || $quantities[$index] <= 0 || empty($unit_prices[$index]) || $unit_prices[$index] <= 0) {
                continue;
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
            // Kiểm tra tồn kho tại shop xuất
            try {
                $inventory_errors = $model->checkInventory($from_shop_db, $import_details);
                if (!empty($inventory_errors)) {
                    $error = implode("<br>", $inventory_errors);
                } else {
                    // Lấy danh mục mặc định
                    $default_category_id = $model->getDefaultCategoryId();
                    if ($default_category_id === null) {
                        $error = "Không tìm thấy danh mục nào trong {$shop_db}.category. Vui lòng thêm ít nhất một danh mục.";
                        error_log("Lỗi: Không tìm thấy danh mục trong {$shop_db}.category");
                    } else {
                        // Đồng bộ sản phẩm
                        foreach ($import_details as $detail) {
                            try {
                                $model->syncProduct($from_shop_db, $detail['product_id'], $employee_id, $default_category_id);
                            } catch (Exception $e) {
                                $error = "Lỗi khi đồng bộ sản phẩm ID {$detail['product_id']}: " . $e->getMessage();
                                error_log("Lỗi: " . $e->getMessage());
                                break;
                            }
                        }

                        // Thêm đơn nhập hàng
                        if (!isset($error)) {
                            try {
                                $model->addImportGoods($from_shop_db, $current_shop_id, $from_shop_id, $employee_id, $import_date, $import_details, $note);
                                header("Location: ../view/import_goods.php?import_added=success");
                                exit();
                            } catch (Exception $e) {
                                $error = "Lỗi khi tạo đơn nhập hàng: " . $e->getMessage();
                                error_log("Lỗi: " . $e->getMessage());
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $error = "Lỗi khi kiểm tra tồn kho: " . $e->getMessage();
                error_log("Lỗi: " . $e->getMessage());
            }
        }
    }
}

// Đóng kết nối
$model->close();

// Tải View
include '../view/add_import_goods_view.php';
?>