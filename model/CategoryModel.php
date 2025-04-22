<?php
class CategoryModel {
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

    // Lấy tên cửa hàng từ db_name
    public function getShopName($main_db, $shop_db) {
        $conn_main = new mysqli($this->host, $this->username, $this->password, $main_db);
        if ($conn_main->connect_error) {
            die("Lỗi kết nối đến cơ sở dữ liệu chính: " . $conn_main->connect_error);
        }
        $conn_main->set_charset("utf8mb4");

        $sql = "SELECT name FROM shop WHERE db_name = ?";
        $stmt = $conn_main->prepare($sql);
        if ($stmt === false) {
            die("Lỗi chuẩn bị truy vấn name: " . $conn_main->error);
        }
        $stmt->bind_param('s', $shop_db);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $shop_name = $row['name'] ?? $shop_db;
        if (!$row) {
            error_log("Không tìm thấy name cho db_name = '$shop_db' trong bảng shop.");
        }
        $stmt->close();
        $conn_main->close();
        return $shop_name;
    }

    // Thêm danh mục mới
    public function addCategory($name, $icon_path) {
        $sql = "INSERT INTO category (name, icon) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn thêm danh mục: " . $this->conn->error);
        }
        $stmt->bind_param('ss', $name, $icon_path);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    // Đóng kết nối
    public function close() {
        $this->conn->close();
    }
}
?>