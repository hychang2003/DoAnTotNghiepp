<?php
session_start();
include '../config/db_connect.php';
include 'CategoryModel.php';

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Lấy cơ sở hiện tại từ session
$shop_db = $_SESSION['shop_db'] ?? 'shop_1';

// Khởi tạo Model
$model = new CategoryModel($host, $username, $password, $shop_db);
$shop_name = $model->getShopName('fashion_shop', $shop_db);

// Xử lý thêm danh mục
$errors = [];
$success = '';

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
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($icon['type'], $allowed_types)) {
            $errors[] = "Icon phải là file PNG, JPEG hoặc GIF.";
        } elseif ($icon['size'] > $max_size) {
            $errors[] = "Icon không được lớn hơn 2MB.";
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
                $success = "Thêm danh mục thành công.";
            } else {
                $errors[] = "Lỗi khi thêm danh mục.";
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Đóng kết nối
$model->close();

// Tải View
include 'add_category_view.php';
?>