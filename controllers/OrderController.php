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

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Bao gồm file kết nối cơ sở dữ liệu và model
include_once '../config/db_connect.php';
include_once '../models/OrderModel.php';

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("session_username được gán: " . $session_username);

// Khởi tạo Model
$model = new OrderModel($host, $username, $password, $shop_db);

// Xử lý các hành động
$action = $_GET['action'] ?? '';

if ($action === 'add') {
    // Xử lý thêm đơn hàng
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
        $order_date = date('Y-m-d H:i:s'); // Gán thời gian thực tế
        $total_price = floatval($_POST['total_price_raw'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $products = $_POST['products'] ?? [];

        // Kiểm tra dữ liệu đầu vào
        $errors = [];
        if ($total_price <= 0) {
            $errors[] = "Tổng tiền phải lớn hơn 0!";
        }
        if (empty($products)) {
            $errors[] = "Vui lòng chọn ít nhất một sản phẩm!";
        }
        if (!$employee_id) {
            $errors[] = "Không tìm thấy thông tin người dùng. Vui lòng đăng nhập lại!";
        }

        if (empty($errors)) {
            // Tính lại tổng tiền
            $calculated_total = 0;
            $valid_products = [];
            foreach ($products as $product) {
                $product_id = intval($product['id']);
                $quantity = intval($product['quantity']);
                $unit_price = floatval($product['price']);
                $discount_percent = floatval($product['discount'] ?? 0);
                if ($quantity > 0) {
                    $discount_amount = ($unit_price * $discount_percent) / 100;
                    $subtotal = ($unit_price - $discount_amount) * $quantity;
                    $calculated_total += $subtotal;
                    $valid_products[$product_id] = $product;
                }
            }

            // So sánh tổng tiền
            if (abs($calculated_total - $total_price) > 0.01) {
                $errors[] = "Tổng tiền không khớp, vui lòng kiểm tra lại! (Tổng tính lại: " . number_format($calculated_total, 0, ',', '.') . ")";
            }
        }

        if (empty($errors)) {
            try {
                $model->addOrder($customer_id, $employee_id, $order_date, $total_price, $status, $valid_products);
                $_SESSION['success'] = "Thêm hóa đơn thành công!";
                header("Location: ../view/order.php");
                exit();
            } catch (Exception $e) {
                error_log("Lỗi khi thêm đơn hàng: " . $e->getMessage());
                $_SESSION['form_errors'] = ["Lỗi khi thêm đơn hàng: " . $e->getMessage()];
                $_SESSION['form_data'] = $_POST;
                header("Location: ../view/add_order_view.php");
                exit();
            }
        } else {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header("Location: ../view/add_order_view.php");
            exit();
        }
    }

    // Tải dữ liệu ban đầu và view thêm đơn hàng
    try {
        $discount = $model->getActiveFlashSale();
        $customers = $model->getCustomers();
        $users = $model->getUsers();
        $products = $model->getProducts();
    } catch (Exception $e) {
        error_log("Lỗi khi lấy dữ liệu: " . $e->getMessage());
        $_SESSION['form_errors'] = ["Lỗi khi lấy dữ liệu: " . $e->getMessage()];
        $discount = 0;
        $customers = [];
        $users = [];
        $products = [];
    }
    include '../view/add_order_view.php';
    exit();
}

if ($action === 'update') {
    // Lấy thông tin đơn hàng để chỉnh sửa
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header("Location: ../view/order.php?error=" . urlencode("Không tìm thấy ID đơn hàng."));
        exit();
    }

    $order_id = (int)$_GET['id'];
    try {
        $order = $model->getOrderById($order_id);
        if (!$order) {
            header("Location: ../view/order.php?error=" . urlencode("Đơn hàng không tồn tại."));
            exit();
        }
        $customers = $model->getCustomers();
        $users = $model->getUsers();
        include '../view/update_order_view.php';
        exit();
    } catch (Exception $e) {
        error_log("Lỗi khi lấy thông tin đơn hàng: " . $e->getMessage());
        header("Location: ../view/order.php?error=" . urlencode("Lỗi khi lấy thông tin đơn hàng: " . $e->getMessage()));
        exit();
    }
}

if ($action === 'save_update') {
    // Xử lý cập nhật đơn hàng
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $order_id = (int)($_POST['id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
        $order_date = $_POST['order_date'] ?? '';
        $total_price = floatval($_POST['total_price'] ?? 0);
        $status = $_POST['status'] ?? 'pending';

        // Kiểm tra dữ liệu đầu vào
        $errors = [];
        if ($order_id <= 0) {
            $errors[] = "ID đơn hàng không hợp lệ.";
        }
        if ($customer_id <= 0) {
            $errors[] = "Vui lòng chọn khách hàng!";
        }
        if (empty($order_date)) {
            $errors[] = "Vui lòng nhập ngày đặt hàng!";
        }
        if ($total_price <= 0) {
            $errors[] = "Tổng tiền phải lớn hơn 0!";
        }

        if (empty($errors)) {
            try {
                $model->updateOrder($order_id, $customer_id, $employee_id, $order_date, $total_price, $status);
                header("Location: ../view/order.php?updated=success");
                exit();
            } catch (Exception $e) {
                error_log("Lỗi khi cập nhật đơn hàng: " . $e->getMessage());
                $_SESSION['form_errors'] = ["Lỗi khi cập nhật đơn hàng: " . $e->getMessage()];
                $_SESSION['form_data'] = $_POST;
                header("Location: ../view/update_order_view.php?id=$order_id");
                exit();
            }
        } else {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header("Location: ../view/update_order_view.php?id=$order_id");
            exit();
        }
    }
}

if ($action === 'delete') {
    // Xử lý xóa đơn hàng
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header("Location: ../view/order.php?error=" . urlencode("Không tìm thấy ID đơn hàng."));
        exit();
    }

    $order_id = (int)$_GET['id'];
    try {
        $model->deleteOrder($order_id);
        header("Location: ../view/order.php?order_deleted=success");
        exit();
    } catch (Exception $e) {
        error_log("Lỗi khi xóa đơn hàng: " . $e->getMessage());
        header("Location: ../view/order.php?error=" . urlencode("Lỗi khi xóa đơn hàng: " . $e->getMessage()));
        exit();
    }
}

// Lấy danh sách đơn hàng
try {
    $orders = $model->getOrders();
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách đơn hàng: " . $e->getMessage());
    $orders = [];
}

// Đóng kết nối
$model->close();

// Tải View danh sách đơn hàng
include '../view/order.php';
?>