<?php
session_start();

// Kết nối database chính
include './config/db_connect.php';

// Kiểm tra xem $conn có được khởi tạo không
if (!isset($conn) || $conn->connect_error) {
    die("Không thể kết nối đến cơ sở dữ liệu. Vui lòng kiểm tra file db_connect.php.");
}

// Xử lý đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!";
    } else {
        // Kiểm tra thông tin đăng nhập
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $error = "Lỗi khi chuẩn bị truy vấn: " . $conn->error;
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                // Kiểm tra mật khẩu
                if ($user['username'] === 'admin' && $password === '123456') { // Mật khẩu của admin chưa mã hóa
                    $_SESSION['loggedin'] = true;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    header("Location: ./index.php");
                    exit();
                } elseif (password_verify($password, $user['password'])) { // Mật khẩu của nhân viên đã mã hóa
                    $_SESSION['loggedin'] = true;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    header("Location: ./index.php");
                    exit();
                } else {
                    $error = "Mật khẩu không đúng!";
                }
            } else {
                $error = "Tên đăng nhập không tồn tại!";
            }
            $stmt->close();
        }
    }
}

// Đóng kết nối nếu $conn tồn tại
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="./assets/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="text-center">Đăng nhập</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mật khẩu</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="./assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>