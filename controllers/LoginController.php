<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Khởi tạo kết nối tới fashion_shopp
$conn = new mysqli($host, $username, $password, 'fashion_shopp');
if ($conn->connect_error) {
    error_log("Lỗi kết nối tới fashion_shopp: " . $conn->connect_error);
    $_SESSION['error'] = "Lỗi hệ thống: Không thể kết nối tới cơ sở dữ liệu.";
    header("Location: ../view/login_view.php");
    exit();
}
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Kiểm tra dữ liệu đầu vào
    if (empty($username)) {
        error_log("Lỗi đăng nhập: Tên đăng nhập trống.");
        $_SESSION['error'] = "Vui lòng nhập tên đăng nhập.";
        header("Location: ../view/login_view.php");
        exit();
    }
    if (empty($password)) {
        error_log("Lỗi đăng nhập: Mật khẩu trống.");
        $_SESSION['error'] = "Vui lòng nhập mật khẩu.";
        header("Location: ../view/login_view.php");
        exit();
    }

    // Truy vấn người dùng từ bảng users
    $sql = "SELECT id, username, password, role, shop_id FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn đăng nhập: " . $conn->error);
        $_SESSION['error'] = "Lỗi hệ thống: Không thể chuẩn bị truy vấn đăng nhập.";
        header("Location: ../view/login_view.php");
        exit();
    }
    $stmt->bind_param('s', $username);
    if (!$stmt->execute()) {
        error_log("Lỗi thực thi truy vấn đăng nhập: " . $stmt->error);
        $_SESSION['error'] = "Lỗi hệ thống: Không thể thực thi truy vấn đăng nhập.";
        $stmt->close();
        header("Location: ../view/login_view.php");
        exit();
    }
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Ghi log dữ liệu người dùng lấy được
    error_log("Dữ liệu người dùng từ DB: " . print_r($user, true));

    // Kiểm tra thông tin đăng nhập
    if ($user) {
        // So sánh mật khẩu trực tiếp (thay bằng password_verify nếu mật khẩu được mã hóa)
        if ($password === $user['password']) {
            // Thiết lập session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['loggedin'] = true;
            $_SESSION['shop_id'] = $user['shop_id'];

            // Đặt shop_db dựa trên shop_id
            if ($user['shop_id'] !== null) {
                $sql_shop = "SELECT db_name FROM shop WHERE id = ?";
                $stmt_shop = $conn->prepare($sql_shop);
                if ($stmt_shop === false) {
                    error_log("Lỗi chuẩn bị truy vấn shop: " . $conn->error);
                    $_SESSION['error'] = "Lỗi hệ thống: Không thể lấy thông tin cơ sở.";
                    header("Location: ../view/login_view.php");
                    exit();
                }
                $stmt_shop->bind_param('i', $user['shop_id']);
                $stmt_shop->execute();
                $shop_result = $stmt_shop->get_result();
                if ($shop_result->num_rows === 1) {
                    $shop = $shop_result->fetch_assoc();
                    $_SESSION['shop_db'] = $shop['db_name'];
                } else {
                    error_log("Lỗi: Không tìm thấy cơ sở với shop_id={$user['shop_id']}");
                    $_SESSION['error'] = "Không tìm thấy cơ sở của nhân viên.";
                    $stmt_shop->close();
                    header("Location: ../view/login_view.php");
                    exit();
                }
                $stmt_shop->close();
            } else {
                $_SESSION['shop_db'] = 'fashion_shopp'; // Admin mặc định dùng cơ sở chính
            }

            error_log("Đăng nhập thành công: user_id={$user['id']}, username={$user['username']}, role={$user['role']}, shop_id={$user['shop_id']}, shop_db={$_SESSION['shop_db']}");
            error_log("Session sau đăng nhập: " . print_r($_SESSION, true));
            header("Location: ../index.php");
            exit();
        } else {
            error_log("Lỗi đăng nhập: Mật khẩu không đúng cho username=$username. Nhập: $password, DB: {$user['password']}");
            $_SESSION['error'] = "Mật khẩu không đúng.";
            header("Location: ../view/login_view.php");
            exit();
        }
    } else {
        error_log("Lỗi đăng nhập: Không tìm thấy người dùng với username=$username");
        $_SESSION['error'] = "Tên đăng nhập không tồn tại.";
        header("Location: ../view/login_view.php");
        exit();
    }
}

// Đóng kết nối
$conn->close();
?>