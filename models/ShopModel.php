<?php
class ShopModel {
    private $host;
    private $username;
    private $password;
    private $main_dbname;
    private $conn;

    // Khởi tạo kết nối cơ sở dữ liệu
    public function __construct($host, $username, $password, $main_dbname) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->main_dbname = $main_dbname;
        $this->connect();
    }

    // Thiết lập kết nối
    private function connect() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->main_dbname);
        if ($this->conn->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    // Lấy danh sách các cơ sở
    public function getShops() {
        $sql = "SELECT db_name, name FROM shop";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn danh sách cơ sở: " . $this->conn->error);
        }
        $shops = [];
        while ($row = $result->fetch_assoc()) {
            $shops[] = $row;
        }
        $result->free();
        return $shops;
    }

    // Kiểm tra tính hợp lệ của cơ sở
    public function isValidShop($shop_db) {
        $sql = "SELECT db_name FROM shop WHERE db_name = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra cơ sở: " . $this->conn->error);
        }
        $stmt->bind_param('s', $shop_db);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_valid = $result->num_rows > 0;
        $stmt->close();
        return $is_valid;
    }

    // Đóng kết nối
    public function close() {
        $this->conn->close();
    }
}
?>