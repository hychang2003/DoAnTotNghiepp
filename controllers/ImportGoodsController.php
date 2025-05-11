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
$shop_db = $_SESSION['shop_db'] ?? 'shop_11';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("session_username được gán: " . $session_username);

// Khởi tạo Model
$model = new ImportGoodsModel($host, $username, $password, 'fashion_shopp', $shop_db);

// Xử lý yêu cầu AJAX để lấy danh sách sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_products']) && isset($_POST['db_name'])) {
    header('Content-Type: application/json');
    $db_name = $_POST['db_name'];

    // Kết nối đến cơ sở dữ liệu của shop xuất
    $conn = new mysqli($host, $username, $password, $db_name);
    if ($conn->connect_error) {
        echo json_encode(['error' => 'Lỗi kết nối cơ sở dữ liệu: ' . $conn->connect_error]);
        exit;
    }
    $conn->set_charset("utf8mb4");

    // Truy vấn sản phẩm
    $sql = "SELECT p.id, p.name, p.price, COALESCE(i.quantity, 0) as quantity
            FROM `$db_name`.product p
            LEFT JOIN `$db_name`.inventory i ON p.id = i.product_id";
    $result = $conn->query($sql);
    if ($result === false) {
        echo json_encode(['error' => 'Lỗi truy vấn: ' . $conn->error]);
        $conn->close();
        exit;
    }

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    echo json_encode(['products' => $products]);
    $result->free();
    $conn->close();
    exit;
}

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
    $users = $model->getUsers();
} catch (Exception $e) {
    error_log("Lỗi khi lấy dữ liệu: " . $e->getMessage());
    $error = "Lỗi khi lấy dữ liệu: " . $e->getMessage();
    $shops = [];
    $users = [];
}

// Xử lý form tạo đơn nhập hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_import_goods'])) {
    $from_shop_id = $_POST['from_shop_id'] ?? 0;
    $user_id = $_POST['user_id'] ?? '';
    $import_date = $_POST['import_date'] ?? date('Y-m-d H:i:s');
    $products = $_POST['products'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $unit_prices = $_POST['unit_prices'] ?? [];
    $note = $_POST['note'] ?? '';

    // Ghi log dữ liệu gửi từ form
    error_log("Dữ liệu form: products=" . print_r($products, true) . ", quantities=" . print_r($quantities, true) . ", unit_prices=" . print_r($unit_prices, true));

    // Lấy db_name của shop xuất
    try {
        $from_shop_db = $model->getFromShopDbName($from_shop_id);
    } catch (Exception $e) {
        $error = "Lỗi khi lấy thông tin shop xuất: " . $e->getMessage();
        error_log("Lỗi: " . $e->getMessage());
    }

    // Kiểm tra dữ liệu đầu vào
    if (empty($from_shop_id) || empty($from_shop_db) || empty($user_id)) {
        $error = "Vui lòng điền đầy đủ thông tin: shop xuất, nhân viên phụ trách.";
    } else {
        $total_price = 0;
        $import_details = [];

        foreach ($products as $index => $product_id) {
            // Chuyển đổi số lượng và giá nhập thành số
            $quantity = isset($quantities[$index]) ? (int)$quantities[$index] : 0;
            $unit_price = isset($unit_prices[$index]) ? (float)str_replace(',', '', $unit_prices[$index]) : 0;

            // Kiểm tra sản phẩm được chọn và có số lượng/giá hợp lệ
            if ($quantity > 0 && $unit_price > 0) {
                $subtotal = $quantity * $unit_price;
                $total_price += $subtotal;

                $import_details[] = [
                    'product_id' => (int)$product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'subtotal' => $subtotal
                ];
            }
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
                        $error = "Không tìm thấy danh mục nào trong fashion_shopp.category. Vui lòng thêm ít nhất một danh mục.";
                        error_log("Lỗi: Không tìm thấy danh mục trong fashion_shopp.category");
                    } else {
                        // Đồng bộ sản phẩm
                        foreach ($import_details as $detail) {
                            try {
                                $model->syncProduct($from_shop_db, $detail['product_id'], $user_id, $default_category_id);
                            } catch (Exception $e) {
                                $error = "Lỗi khi đồng bộ sản phẩm ID {$detail['product_id']}: " . $e->getMessage();
                                error_log("Lỗi: " . $e->getMessage());
                                break;
                            }
                        }

                        // Thêm đơn nhập hàng
                        if (!isset($error)) {
                            try {
                                // Ép kiểu các tham số
                                $from_shop_id = (int)$from_shop_id;
                                $current_shop_id = (int)$current_shop_id;
                                $user_id = (int)$user_id;
                                $model->addImportGoods($from_shop_db, $current_shop_id, $from_shop_id, $user_id, $import_date, $import_details, $note);
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