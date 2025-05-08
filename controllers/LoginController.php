<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ngăn cache trình duyệt
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Kết nối cơ sở dữ liệu
include '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Kiểm tra dữ liệu đầu vào
    if (empty($username)) {
        error_log("Lỗi đăng nhập: Tên đăng nhập trống.");
        $_SESSION['error'] = "Vui lòng nhập tên đăng nhập.";
        header("Location: ../login_view.php");
        exit();
    }
    if (empty($password)) {
        error_log("Lỗi đăng nhập: Mật khẩu trống.");
        $_SESSION['error'] = "Vui lòng nhập mật khẩu.";
        header("Location: ../login_view.php");
        exit();
    }

    // Truy vấn người dùng từ bảng users
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Lỗi chuẩn bị truy vấn đăng nhập: " . $conn->error);
        $_SESSION['error'] = "Lỗi hệ thống: Không thể chuẩn bị truy vấn đăng nhập.";
        header("Location: ../login_view.php");
        exit();
    }
    $stmt->bind_param('s', $username);
    if (!$stmt->execute()) {
        error_log("Lỗi thực thi truy vấn đăng nhập: " . $stmt->error);
        $_SESSION['error'] = "Lỗi hệ thống: Không thể thực thi truy vấn đăng nhập.";
        $stmt->close();
        header("Location: ../login_view.php");
        exit();
    }
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Ghi log dữ liệu người dùng lấy được
    error_log("Dữ liệu người dùng từ DB: " . print_r($user, true));

    // Kiểm tra thông tin đăng nhập
    if ($user) {
        // So sánh mật khẩu trực tiếp (giả sử chưa mã hóa)
        if ($password === $user['password']) {
            // Thiết lập session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['loggedin'] = true;

            error_log("Đăng nhập thành công: user_id={$user['id']}, username={$user['username']}, role={$user['role']}");
            error_log("Session sau đăng nhập: " . print_r($_SESSION, true));
            header("Location: ../index.php");
            exit();
        } else {
            error_log("Lỗi đăng nhập: Mật khẩu không đúng cho username=$username. Nhập: $password, DB: {$user['password']}");
            $_SESSION['error'] = "Mật khẩu không đúng.";
            header("Location: ../login_view.php");
            exit();
        }
    } else {
        error_log("Lỗi đăng nhập: Không tìm thấy người dùng với username=$username");
        $_SESSION['error'] = "Tên đăng nhập không tồn tại.";
        header("Location: ../login_view.php");
        exit();
    }
}

// Đóng kết nối
$conn->close();
?>