<?php
// Kiểm tra xem session đã được khởi tạo chưa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    error_log("Session không hợp lệ hoặc đã hết hạn. Chuyển hướng đến login_view.php");
    header("Location: /datn/login_view.php");
    exit();
}
?>