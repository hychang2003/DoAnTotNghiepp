<?php
class EmployeeModel {
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
            die("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    // Kiểm tra username đã tồn tại
    public function isUsernameExists($username) {
        $sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra username: " . $this->conn->error);
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    // Kiểm tra email đã tồn tại
    public function isEmailExists($email) {
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra email: " . $this->conn->error);
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    // Thêm nhân viên mới
    public function addEmployee($name, $phone_number, $email, $username, $password, $role) {
        $sql = "INSERT INTO users (name, phone_number, email, username, password, role) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn thêm nhân viên: " . $this->conn->error);
        }
        // Chuyển chuỗi rỗng thành NULL cho phone_number
        $phone_number = $phone_number ?: null;
        $stmt->bind_param('ssssss', $name, $phone_number, $email, $username, $password, $role);
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception("Lỗi thực thi truy vấn thêm nhân viên: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    // Lấy danh sách nhân viên
    public function getAllEmployees() {
        $sql = "SELECT id, name, phone_number, email, username, role FROM users";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn danh sách nhân viên: " . $this->conn->error);
        }
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        $result->free();
        return $employees;
    }

    // Đóng kết nối
    public function close() {
        $this->conn->close();
    }
}
?>