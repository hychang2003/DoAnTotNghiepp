<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Phiên đăng nhập không hợp lệ: " . print_r($_SESSION, true));
    header("Location: ../login_view.php");
    exit();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

include_once '../config/db_connect.php';
include_once '../models/ProductModel.php';

$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("session_username được gán: " . $session_username . ", shop_db: " . $shop_db);

if (!isset($session_username) || empty($session_username)) {
    error_log("session_username không được thiết lập hoặc rỗng");
    header("Location: ../login_view.php");
    exit();
}

$conn_common = new mysqli($host, $username, $password, 'fashion_shopp');
if ($conn_common->connect_error) {
    error_log("Lỗi kết nối đến fashion_shopp: " . $conn_common->connect_error);
    $shop_id = 1;
} else {
    $conn_common->set_charset("utf8mb4");
    $sql = "SELECT id FROM shop WHERE db_name = ?";
    $stmt = $conn_common->prepare($sql);
    $stmt->bind_param('s', $shop_db);
    $stmt->execute();
    $result = $stmt->get_result();
    $shop_id = $result->num_rows > 0 ? $result->fetch_assoc()['id'] : 1;
    $stmt->close();
    $conn_common->close();
}

$model = new ProductModel($host, $username, $password, $shop_db);

$action = $_GET['action'] ?? '';

if ($action === 'delete') {
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($product_id > 0) {
        try {
            $model->deleteProduct($product_id);
            $model->close();
            header("Location: ../view/products_list_view.php?product_deleted=success");
            exit();
        } catch (Exception $e) {
            error_log("Lỗi khi xóa sản phẩm: " . $e->getMessage());
            $model->close();
            header("Location: ../view/products_list_view.php?error=" . urlencode("Lỗi khi xóa sản phẩm: " . $e->getMessage()));
            exit();
        }
    } else {
        $model->close();
        header("Location: ../view/products_list_view.php?error=" . urlencode("Không tìm thấy ID sản phẩm."));
        exit();
    }
}

if ($action === 'fetch') {
    header('Content-Type: application/json');
    $response = ['error' => '', 'products' => []];
    try {
        $response['products'] = $model->fetchProducts($shop_id);
    } catch (Exception $e) {
        $response['error'] = "Lỗi khi lấy danh sách sản phẩm: " . $e->getMessage();
        error_log("Lỗi fetch products: " . $e->getMessage());
    }
    $model->close();
    echo json_encode($response);
    exit();
}

if ($action === 'list') {
    $products = [];
    $error = '';
    $success = '';

    if (isset($_GET['added']) && $_GET['added'] === 'success') {
        $success = "Thêm sản phẩm thành công!";
    } elseif (isset($_GET['product_deleted']) && $_GET['product_deleted'] === 'success') {
        $success = "Xóa sản phẩm thành công!";
    } elseif (isset($_GET['updated']) && $_GET['updated'] === 'success') {
        $success = "Cập nhật sản phẩm thành công!";
    } elseif (isset($_GET['error'])) {
        $error = $_GET['error'];
    }

    try {
        $products = $model->getProducts($shop_id);
    } catch (Exception $e) {
        error_log("Lỗi khi lấy danh sách sản phẩm: " . $e->getMessage());
        $error = "Lỗi khi lấy danh sách sản phẩm: " . $e->getMessage();
    }
    include '../view/products_list_view.php';
} else {
    $categories = [];
    $flash_sales = [];
    $error = '';
    try {
        $categories = $model->getCategories();
        $flash_sales = $model->getFlashSales();
        if (empty($categories)) {
            $error = "Không tìm thấy danh mục nào trong bảng 'category'. Vui lòng kiểm tra cơ sở dữ liệu hoặc thêm danh mục.";
        }
    } catch (Exception $e) {
        error_log("Lỗi khi lấy dữ liệu từ cơ sở dữ liệu {$shop_db}: " . $e->getMessage());
        $error = "Lỗi khi lấy dữ liệu danh mục hoặc khuyến mãi: " . $e->getMessage();
    }

    $success = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $cost_price = floatval($_POST['cost_price'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $type = trim($_POST['type'] ?? 'general');
        $unit = trim($_POST['unit'] ?? '');
        $flash_sale_id = !empty($_POST['flash_sale_id']) ? intval($_POST['flash_sale_id']) : null;
        $image = '';

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowed_types)) {
                $error = "Chỉ chấp nhận file JPEG, PNG hoặc GIF!";
            } else {
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $new_file_name = time() . '_' . rand(1, 1000) . '.' . $file_extension;
                $image = 'assets/images/' . $new_file_name;
                $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/datn/' . $image;
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/datn/assets/images/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                    error_log("Created upload directory: $upload_dir");
                }
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $error = "Lỗi khi upload ảnh sản phẩm!";
                    error_log("Upload error: " . print_r($_FILES['image'], true) . " to $upload_path");
                } else {
                    error_log("Uploaded image to: $upload_path");
                }
            }
        }

        if (empty($name) || $price <= 0 || $cost_price < 0 || $quantity < 0 || $category_id <= 0 || empty($unit)) {
            $error = "Vui lòng nhập đầy đủ và chính xác thông tin sản phẩm!";
        } else {
            try {
                $model->addProduct($name, $description, $image, $category_id, $type, $unit, $price, $cost_price, $quantity, $flash_sale_id);
                $success = "Thêm sản phẩm thành công!";
                header("Location: ../controllers/ProductController.php?action=list&added=success");
                exit();
            } catch (Exception $e) {
                $error = "Lỗi khi thêm sản phẩm: " . $e->getMessage();
                error_log("Lỗi: " . $e->getMessage());
            }
        }
    }

    include '../view/add_product_view.php';
}

$model->close();
?>