<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login_view.php");
    exit();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

include_once '../config/db_connect.php';
include_once '../models/OrderModel.php';

$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$session_username = $_SESSION['username'] ?? 'Khách';

// Kết nối database
$conn = new mysqli($host, $username, $password, $shop_db);
$conn_main = new mysqli($host, $username, $password, 'fashion_shopp');
if ($conn->connect_error || $conn_main->connect_error) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
$conn_main->set_charset("utf8mb4");

$action = $_GET['action'] ?? '';

if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $debug_messages = [];
        $debug_messages[] = "POST data: " . json_encode($_POST);
        $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
        $order_date = date('Y-m-d H:i:s');
        $total_price = floatval($_POST['total_price_raw'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $products = $_POST['products'] ?? [];

        $debug_messages[] = "customer_id: $customer_id, employee_id: $employee_id, total_price: $total_price, status: $status";
        $debug_messages[] = "Products: " . json_encode($products);

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
            $calculated_total = 0;
            $valid_products = [];
            foreach ($products as $product) {
                if (isset($product['selected']) && $product['selected'] == '1' && isset($product['quantity']) && $product['quantity'] > 0) {
                    $product_id = intval($product['id']);
                    $quantity = intval($product['quantity']);
                    $unit_price = floatval($product['price']);
                    $discount_percent = floatval($product['discount'] ?? 0);
                    $discount_amount = ($unit_price * $discount_percent) / 100;
                    $subtotal = ($unit_price - $discount_amount) * $quantity;
                    $calculated_total += $subtotal;
                    $valid_products[$product_id] = [
                        'id' => $product_id,
                        'quantity' => $quantity,
                        'price' => $unit_price,
                        'discount' => $discount_percent
                    ];
                }
            }

            $debug_messages[] = "Calculated total: $calculated_total, Submitted total: $total_price";

            if (abs($calculated_total - $total_price) > 0.01) {
                $errors[] = "Tổng tiền không khớp, vui lòng kiểm tra lại! (Tổng tính lại: " . number_format($calculated_total, 0, ',', '.') . ")";
            }
        }

        if (empty($errors)) {
            try {
                $debug_messages[] = "Gọi addOrder với valid_products: " . json_encode($valid_products);
                $model = new OrderModel($host, $username, $password, $shop_db);
                $order_id = $model->addOrder($customer_id, $employee_id, $order_date, $total_price, $status, $valid_products);
                $model->close();

                $debug_messages[] = "Thêm hóa đơn thành công, order_id: $order_id";
                $_SESSION['success'] = "Thêm hóa đơn thành công!";
                $_SESSION['debug_messages'] = $debug_messages;
                header("Location: ../view/order.php");
                exit();
            } catch (Exception $e) {
                $debug_messages[] = "Lỗi khi thêm đơn hàng: " . $e->getMessage();
                $_SESSION['form_errors'] = ["Lỗi khi thêm đơn hàng: " . $e->getMessage()];
                $_SESSION['form_data'] = $_POST;
                $_SESSION['debug_messages'] = $debug_messages;
                header("Location: ../view/add_order_view.php");
                exit();
            }
        } else {
            $debug_messages[] = "Lỗi đầu vào: " . json_encode($errors);
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            $_SESSION['debug_messages'] = $debug_messages;
            header("Location: ../view/add_order_view.php");
            exit();
        }
    } else {
        header("Location: ../view/add_order_view.php");
        exit();
    }
}

if ($action === 'update') {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['error'] = "Không tìm thấy ID đơn hàng.";
        header("Location: ../view/order.php");
        exit();
    }

    $order_id = (int)$_GET['id'];
    try {
        $model = new OrderModel($host, $username, $password, $shop_db);
        $order = $model->getOrderById($order_id);
        if (!$order) {
            $_SESSION['error'] = "Đơn hàng không tồn tại.";
            header("Location: ../view/order.php");
            exit();
        }
        $customers = $model->getCustomers();
        $users = $model->getUsers();
        $model->close();
        include '../view/update_order_view.php';
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Lỗi khi lấy thông tin đơn hàng: " . $e->getMessage();
        header("Location: ../view/order.php");
        exit();
    }
}

if ($action === 'save_update') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $order_id = (int)($_POST['id'] ?? 0);
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
        $order_date = $_POST['order_date'] ?? '';
        $total_price = floatval($_POST['total_price'] ?? 0);
        $status = $_POST['status'] ?? 'pending';

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
                $model = new OrderModel($host, $username, $password, $shop_db);
                $model->updateOrder($order_id, $customer_id, $employee_id, $order_date, $total_price, $status);
                $model->close();
                $_SESSION['success'] = "Cập nhật hóa đơn thành công!";
                header("Location: ../view/order.php");
                exit();
            } catch (Exception $e) {
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
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['error'] = "Không tìm thấy ID đơn hàng.";
        header("Location: ../view/order.php");
        exit();
    }

    $order_id = (int)$_GET['id'];
    try {
        $model = new OrderModel($host, $username, $password, $shop_db);
        $model->deleteOrder($order_id);
        $model->close();
        $_SESSION['success'] = "Xóa hóa đơn thành công!";
        header("Location: ../view/order.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Lỗi khi xóa đơn hàng: " . $e->getMessage();
        header("Location: ../view/order.php");
        exit();
    }
}

try {
    $model = new OrderModel($host, $username, $password, $shop_db);
    $orders = $model->getOrders();
    $model->close();
} catch (Exception $e) {
    $orders = [];
    $_SESSION['error'] = "Lỗi khi lấy danh sách đơn hàng: " . $e->getMessage();
}

$conn->close();
$conn_main->close();
include '../view/order.php';
?>