<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Kết nối cơ sở dữ liệu
if (!file_exists('../config/db_connect.php')) {
    $_SESSION['error'] = "Lỗi hệ thống: Không tìm thấy file db_connect.php tại C:\xampp\htdocs\datn\config\db_connect.php";
    header("Location: ../login_view.php");
    exit();
}
include '../config/db_connect.php';
include '../models/UserModel.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_username = trim($_POST['username'] ?? '');
    $input_password = trim($_POST['password'] ?? '');

    // Kiểm tra dữ liệu đầu vào
    if (empty($input_username)) {
        $_SESSION['error'] = "Vui lòng nhập tên đăng nhập.";
        header("Location: ../login_view.php");
        exit();
    }
    if (empty($input_password)) {
        $_SESSION['error'] = "Vui lòng nhập mật khẩu.";
        header("Location: ../login_view.php");
        exit();
    }

    try {
        // Kết nối fashion_shopp để lấy danh sách cơ sở dữ liệu
        $conn_fashion = new mysqli($host, $username, $password, 'fashion_shopp');
        if ($conn_fashion->connect_error) {
            throw new Exception("Lỗi kết nối tới fashion_shopp: " . $conn_fashion->connect_error);
        }
        $conn_fashion->set_charset("utf8mb4");

        // Danh sách cơ sở dữ liệu: fashion_shopp + các shop_* từ fashion_shopp.shop
        $databases = [['name' => 'fashion_shopp', 'shop_id' => null]];
        $sql_shops = "SELECT id, db_name FROM shop WHERE db_name != ''";
        $result_shops = $conn_fashion->query($sql_shops);
        if ($result_shops === false) {
            throw new Exception("Lỗi truy vấn danh sách shop: " . $conn_fashion->error);
        }
        while ($row = $result_shops->fetch_assoc()) {
            $databases[] = ['name' => $row['db_name'], 'shop_id' => $row['id']];
        }
        $conn_fashion->close();

        // Tìm username trong các cơ sở dữ liệu
        $user = null;
        $selected_db = null;
        $selected_shop_id = null;
        foreach ($databases as $db) {
            $model = new UserModel($host, $username, $password, $db['name']);
            $user = $model->authenticate($input_username, $input_password);
            $model->close();
            if ($user) {
                $selected_db = $db['name'];
                $selected_shop_id = $db['shop_id'];
                break;
            }
        }

        if ($user) {
            // Thiết lập session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['loggedin'] = true;
            $_SESSION['shop_id'] = $selected_shop_id ?? 0; // 0 nếu là fashion_shopp
            $_SESSION['shop_db'] = $selected_db;

            header("Location: ../index.php");
            exit();
        } else {
            $_SESSION['error'] = "Tên đăng nhập hoặc mật khẩu không đúng.";
            header("Location: ../login_view.php");
            exit();
        }
    } catch (Exception $e) {
        // Hiển thị lỗi chi tiết trên giao diện
        $error_message = "Lỗi hệ thống: " . htmlspecialchars($e->getMessage()) . " (File: " . $e->getFile() . ", Line: " . $e->getLine() . ")";
        $_SESSION['error'] = $error_message;
        header("Location: ../login_view.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Phương thức không hợp lệ.";
    header("Location: ../login_view.php");
    exit();
}
?>