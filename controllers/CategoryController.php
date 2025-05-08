<?php
include '../config/session_check.php';
include '../config/db_connect.php';
include '../models/CategoryModel.php';

// Debug session và thời gian xử lý
$start_time = microtime(true);
error_log("CategoryController.php - Session ID: " . session_id());
error_log("CategoryController.php - Logged in: " . (isset($_SESSION['loggedin']) ? 'true' : 'false'));

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khởi tạo các biến mặc định
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';
$shop_name = $shop_db;
$errors = [];
$success = '';
$role = $_SESSION['role'] ?? '';
$session_username = $_SESSION['username'] ?? 'Khách';

// Kiểm tra $shop_db
if ($shop_db !== 'fashion_shopp') {
    error_log("Sai shop_db: $shop_db. Chuyển về fashion_shopp.");
    $shop_db = 'fashion_shopp';
    $_SESSION['shop_db'] = 'fashion_shopp';
}

// Khởi tạo Model
$model = new CategoryModel($host, $username, $password, $shop_db);

// Xử lý các hành động
$action = $_GET['action'] ?? '';

if ($action === 'delete') {
    // Xóa danh mục
    $category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($category_id > 0) {
        try {
            $model->deleteCategory($category_id);
            header("Location: ../view/product_category.php?success=" . urlencode("Xóa danh mục thành công."));
        } catch (Exception $e) {
            error_log("Lỗi xóa danh mục ID $category_id: " . $e->getMessage());
            header("Location: ../view/product_category.php?error=" . urlencode($e->getMessage()));
        }
    } else {
        error_log("ID danh mục không hợp lệ: $category_id");
        header("Location: ../view/product_category.php?error=" . urlencode("ID danh mục không hợp lệ."));
    }
    $model->close();
    exit();
} elseif ($action === 'update') {
    // Cập nhật danh mục
    $category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $category = null;

    if ($category_id > 0) {
        try {
            $category = $model->getCategoryById($category_id);
            if (!$category) {
                $errors[] = "Danh mục không tồn tại.";
            }
        } catch (Exception $e) {
            error_log("Lỗi lấy danh mục ID $category_id: " . $e->getMessage());
            $errors[] = "Lỗi khi lấy thông tin danh mục.";
        }
    } else {
        $errors[] = "ID danh mục không hợp lệ.";
    }

    // Xử lý biểu mẫu cập nhật
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
        $name = trim($_POST['name'] ?? '');

        // Kiểm tra dữ liệu đầu vào
        if (empty($name)) {
            $errors[] = "Tên danh mục không được để trống.";
        } elseif (strlen($name) > 100) {
            $errors[] = "Tên danh mục không được dài quá 100 ký tự.";
        }

        // Cập nhật nếu không có lỗi
        if (empty($errors)) {
            try {
                if ($model->updateCategory($category_id, $name)) {
                    header("Location: ../view/product_category.php?success=" . urlencode("Cập nhật danh mục thành công."));
                } else {
                    $errors[] = "Lỗi khi cập nhật danh mục.";
                }
            } catch (Exception $e) {
                error_log("Lỗi cập nhật danh mục ID $category_id: " . $e->getMessage());
                $errors[] = $e->getMessage();
            }
        }
    }

    // Lấy tên cửa hàng
    try {
        $shop_name = $model->getShopName('fashion_shopp', $shop_db);
    } catch (Exception $e) {
        error_log("Lỗi khi lấy tên cửa hàng: " . $e->getMessage());
        $errors[] = "Không thể lấy tên cửa hàng.";
    }

    // Tải View cập nhật
    include '../view/update_category_view.php';
    $model->close();
    exit();
} elseif ($action === 'add') {
    // Xử lý thêm danh mục
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $icon = $_FILES['icon'] ?? null;

        // Kiểm tra dữ liệu đầu vào
        if (empty($name)) {
            $errors[] = "Tên danh mục không được để trống.";
        } elseif (strlen($name) > 100) {
            $errors[] = "Tên danh mục không được dài quá 100 ký tự.";
        }

        // Xử lý tải lên icon
        $icon_path = null;
        if ($icon && $icon['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/png', 'image/jpeg', 'image/gif'];

            if (!in_array($icon['type'], $allowed_types)) {
                $errors[] = "Icon phải là file PNG, JPEG hoặc GIF.";
            } else {
                $upload_dir = '../assets/icons/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_extension = pathinfo($icon['name'], PATHINFO_EXTENSION);
                $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($icon['tmp_name'], $destination)) {
                    $icon_path = 'assets/icons/' . $new_filename;
                } else {
                    $errors[] = "Lỗi khi tải lên icon.";
                }
            }
        }

        // Thêm danh mục nếu không có lỗi
        if (empty($errors)) {
            try {
                if ($model->addCategory($name, $icon_path)) {
                    header("Location: ../view/product_category.php?success=" . urlencode("Thêm danh mục thành công."));
                } else {
                    $errors[] = "Lỗi khi thêm danh mục.";
                }
            } catch (Exception $e) {
                error_log("Lỗi thêm danh mục: " . $e->getMessage());
                $errors[] = $e->getMessage();
            }
        }
    }

    // Lấy tên cửa hàng
    try {
        $shop_name = $model->getShopName('fashion_shopp', $shop_db);
    } catch (Exception $e) {
        error_log("Lỗi khi lấy tên cửa hàng: " . $e->getMessage());
        $errors[] = "Không thể lấy tên cửa hàng.";
    }

    // Tải View thêm danh mục
    include '../view/add_category_view.php';
} else {
    // Lấy tên cửa hàng
    try {
        $shop_name = $model->getShopName('fashion_shopp', $shop_db);
    } catch (Exception $e) {
        error_log("Lỗi khi lấy tên cửa hàng: " . $e->getMessage());
        $errors[] = "Không thể lấy tên cửa hàng.";
    }

    // Tải View thêm danh mục mặc định
    include '../view/add_category_view.php';
}

// Đóng kết nối
$model->close();

// Debug thời gian xử lý
$end_time = microtime(true);
error_log("CategoryController.php - Thời gian xử lý: " . ($end_time - $start_time) . " giây");
?>