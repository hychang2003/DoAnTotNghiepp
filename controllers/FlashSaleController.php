<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập và quyền admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    error_log("Phiên đăng nhập không hợp lệ: " . print_r($_SESSION, true));
    header("Location: ../login_view.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Bao gồm file kết nối cơ sở dữ liệu và model
include_once '../config/db_connect.php';
include_once '../models/FlashSaleModel.php';

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$session_username = $_SESSION['username'] ?? 'Khách';
error_log("session_username được gán: " . $session_username);

// Khởi tạo Model
try {
    $model = new FlashSaleModel($host, $username, $password, $shop_db);
    error_log("Khởi tạo FlashSaleModel thành công với shop_db=$shop_db.");
} catch (Exception $e) {
    error_log("Lỗi khởi tạo FlashSaleModel: " . $e->getMessage());
    header("Location: ../view/flash_sale_view.php?error=" . urlencode("Lỗi khởi tạo mô hình dữ liệu."));
    exit();
}

// Xử lý các hành động
$action = $_GET['action'] ?? '';
error_log("Hành động được yêu cầu: $action");

if ($action === 'add') {
    // Xử lý thêm chương trình khuyến mãi
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $discount = trim($_POST['discount'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');

        // Kiểm tra dữ liệu
        $errors = [];
        if (empty($name)) {
            $errors[] = "Vui lòng nhập tên chương trình khuyến mãi!";
        }
        if (empty($discount)) {
            $errors[] = "Vui lòng nhập giá trị giảm giá!";
        } elseif (!is_numeric($discount) || $discount < 0 || $discount > 100) {
            $errors[] = "Giảm giá phải là một số từ 0 đến 100!";
        }
        if (empty($start_date)) {
            $errors[] = "Vui lòng nhập ngày bắt đầu!";
        } else {
            $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        }
        if (empty($end_date)) {
            $errors[] = "Vui lòng nhập ngày kết thúc!";
        } else {
            $end_date = date('Y-m-d H:i:s', strtotime($end_date));
            if (strtotime($start_date) >= strtotime($end_date)) {
                $errors[] = "Ngày kết thúc phải sau ngày bắt đầu!";
            }
        }

        if (empty($errors)) {
            try {
                error_log("Thêm chương trình khuyến mãi: name=$name, discount=$discount");
                $model->addFlashSale($name, floatval($discount), $start_date, $end_date);
                header("Location: ../view/flash_sale_view.php?added=success");
                exit();
            } catch (Exception $e) {
                error_log("Lỗi khi thêm chương trình khuyến mãi: " . $e->getMessage());
                $_SESSION['form_errors'] = ["Lỗi khi thêm chương trình khuyến mãi: " . $e->getMessage()];
                $_SESSION['form_data'] = $_POST;
                header("Location: ../view/add_flash_sale_view.php");
                exit();
            }
        } else {
            error_log("Lỗi validation: " . implode(", ", $errors));
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header("Location: ../view/add_flash_sale_view.php");
            exit();
        }
    }
    // Tải view thêm chương trình khuyến mãi
    include '../view/add_flash_sale_view.php';
    exit();
}

if ($action === 'update') {
    // Lấy thông tin chương trình khuyến mãi để chỉnh sửa
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        error_log("Lỗi: Không tìm thấy ID chương trình khuyến mãi.");
        header("Location: ../view/flash_sale_view.php?error=" . urlencode("Không tìm thấy ID chương trình khuyến mãi."));
        exit();
    }

    $flash_sale_id = (int)$_GET['id'];
    try {
        error_log("Lấy thông tin chương trình khuyến mãi ID: $flash_sale_id");
        $flash_sale = $model->getFlashSaleById($flash_sale_id);
        if (!$flash_sale) {
            error_log("Chương trình khuyến mãi ID $flash_sale_id không tồn tại.");
            header("Location: ../view/flash_sale_view.php?error=" . urlencode("Chương trình khuyến mãi không tồn tại."));
            exit();
        }
        include '../view/update_flash_sale_view.php';
        exit();
    } catch (Exception $e) {
        error_log("Lỗi khi lấy thông tin khuyến mãi: " . $e->getMessage());
        header("Location: ../view/flash_sale_view.php?error=" . urlencode("Lỗi khi lấy thông tin khuyến mãi: " . $e->getMessage()));
        exit();
    }
}

if ($action === 'save_update') {
    // Xử lý cập nhật chương trình khuyến mãi
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $flash_sale_id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $discount = trim($_POST['discount'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $status = (int)($_POST['status'] ?? 0);

        // Kiểm tra dữ liệu
        $errors = [];
        if ($flash_sale_id <= 0) {
            $errors[] = "ID chương trình khuyến mãi không hợp lệ.";
        }
        if (empty($name)) {
            $errors[] = "Vui lòng nhập tên chương trình khuyến mãi!";
        }
        if (empty($discount)) {
            $errors[] = "Vui lòng nhập giá trị giảm giá!";
        } elseif (!is_numeric($discount) || $discount < 0 || $discount > 100) {
            $errors[] = "Giảm giá phải là một số từ 0 đến 100!";
        }
        if (empty($start_date)) {
            $errors[] = "Vui lòng nhập ngày bắt đầu!";
        } else {
            $start_date = date('Y-m-d H:i:s', strtotime($start_date));
        }
        if (empty($end_date)) {
            $errors[] = "Vui lòng nhập ngày kết thúc!";
        } else {
            $end_date = date('Y-m-d H:i:s', strtotime($end_date));
            if (strtotime($start_date) >= strtotime($end_date)) {
                $errors[] = "Ngày kết thúc phải sau ngày bắt đầu!";
            }
        }

        if (empty($errors)) {
            try {
                error_log("Cập nhật chương trình khuyến mãi ID $flash_sale_id: name=$name, discount=$discount, status=$status");
                $model->updateFlashSale($flash_sale_id, $name, floatval($discount), $start_date, $end_date, $status);
                header("Location: ../view/flash_sale_view.php?flash_sale_updated=success");
                exit();
            } catch (Exception $e) {
                error_log("Lỗi khi cập nhật chương trình khuyến mãi: " . $e->getMessage());
                $_SESSION['form_errors'] = ["Lỗi khi cập nhật chương trình khuyến mãi: " . $e->getMessage()];
                $_SESSION['form_data'] = $_POST;
                header("Location: ../view/update_flash_sale_view.php?id=$flash_sale_id");
                exit();
            }
        } else {
            error_log("Lỗi validation: " . implode(", ", $errors));
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            header("Location: ../view/update_flash_sale_view.php?id=$flash_sale_id");
            exit();
        }
    }
}

if ($action === 'delete') {
    // Xử lý xóa chương trình khuyến mãi
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        error_log("Lỗi: Không tìm thấy ID chương trình khuyến mãi.");
        header("Location: ../view/flash_sale_view.php?error=" . urlencode("Không tìm thấy ID chương trình khuyến mãi."));
        exit();
    }

    $flash_sale_id = (int)$_GET['id'];
    try {
        error_log("Xóa chương trình khuyến mãi ID: $flash_sale_id");
        $model->deleteFlashSale($flash_sale_id);
        header("Location: ../view/flash_sale_view.php?flash_sale_deleted=success");
        exit();
    } catch (Exception $e) {
        error_log("Lỗi khi xóa chương trình khuyến mãi: " . $e->getMessage());
        header("Location: ../view/flash_sale_view.php?error=" . urlencode("Lỗi khi xóa chương trình khuyến mãi: " . $e->getMessage()));
        exit();
    }
}

if ($action === 'get_discount') {
    // Xử lý lấy thông tin giảm giá qua AJAX
    header('Content-Type: application/json');
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
    $response = ['discount' => 0, 'is_active' => false];
    if ($product_id > 0) {
        try {
            error_log("Lấy giảm giá cho sản phẩm ID: $product_id");
            $response = $model->getProductDiscount($product_id);
            error_log("Kết quả giảm giá: " . print_r($response, true));
        } catch (Exception $e) {
            error_log("Lỗi get discount: " . $e->getMessage());
            $response['error'] = "Lỗi khi lấy thông tin giảm giá: " . $e->getMessage();
        }
    } else {
        error_log("Lỗi: ID sản phẩm không hợp lệ ($product_id)");
        $response['error'] = "ID sản phẩm không hợp lệ.";
    }
    echo json_encode($response);
    exit();
}

// Lấy danh sách chương trình khuyến mãi
try {
    error_log("Lấy danh sách chương trình khuyến mãi từ fashion_shopp.");
    $flash_sales = $model->getFlashSales();
    error_log("Lấy danh sách thành công: " . count($flash_sales) . " chương trình khuyến mãi.");
} catch (Exception $e) {
    error_log("Lỗi khi lấy danh sách khuyến mãi: " . $e->getMessage());
    $flash_sales = [];
    header("Location: ../view/flash_sale_view.php?error=" . urlencode("Lỗi khi lấy danh sách khuyến mãi: " . $e->getMessage()));
    exit();
}

// Đóng kết nối
$model->close();
error_log("Đóng kết nối FlashSaleModel.");

// Tải View danh sách chương trình khuyến mãi
include '../view/flash_sale_view.php';
error_log("Tải flash_sale_view.php thành công.");
?>