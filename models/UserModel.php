<?php
class UserModel {
    private $host;
    private $username;
    private $password;
    private $dbname;
    private $conn;

    // Khởi tạo kết nối cơ sở dữ liệu
    public function __construct($host, $username, $password, $dbname) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->dbname = $dbname;
        $this->connect();
    }

    // Thiết lập kết nối
    private function connect() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->dbname);
        if ($this->conn->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error . " (Host: $this->host, DB: $this->dbname)");
        }
        $this->conn->set_charset("utf8mb4");
    }

    // Kiểm tra thông tin đăng nhập
    public function authenticate($input_username, $input_password) {
        $sql = "SELECT id, username, password, role, shop_id FROM users WHERE username = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi khi chuẩn bị truy vấn: " . $this->conn->error . " (SQL: $sql)");
        }
        $stmt->bind_param('s', $input_username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if ($input_password === $user['password']) {
                $stmt->close();
                return $user;
            }
            $stmt->close();
            return false;
        }
        $stmt->close();
        return false;
    }

    // Đóng kết nối
    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>