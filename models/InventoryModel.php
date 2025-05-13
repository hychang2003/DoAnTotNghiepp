<?php
class InventoryModel {
    private $host;
    private $username;
    private $password;
    private $shop_dbname;
    private $conn;

    public function __construct($host, $username, $password, $shop_dbname) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->shop_dbname = $shop_dbname;
        $this->connect();
    }

    private function connect() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->shop_dbname);
        if ($this->conn->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    public function getInventory() {
        $sql = "SELECT p.id, p.name AS product_name, p.price, p.image, c.name AS category_name, IFNULL(i.quantity, 0) AS stock_quantity, i.unit
                FROM `$this->shop_dbname`.product p
                LEFT JOIN `fashion_shopp`.category c ON p.category_id = c.id
                LEFT JOIN `$this->shop_dbname`.inventory i ON p.id = i.product_id";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn tồn kho: " . $this->conn->error);
        }
        $inventory = [];
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }
        $result->free();
        return $inventory;
    }

    public function searchInventory($shop_id, $query) {
        $query = $this->conn->real_escape_string($query);
        $sql = "SELECT p.id, p.name AS product_name, p.price, p.image, c.name AS category_name, 
                       SUM(i.quantity) AS total_quantity, i.unit
                FROM `$this->shop_dbname`.product p
                LEFT JOIN `fashion_shopp`.category c ON p.category_id = c.id
                LEFT JOIN `$this->shop_dbname`.inventory i ON p.id = i.product_id
                WHERE i.shop_id = ? AND (p.name LIKE ? OR c.name LIKE ?)
                GROUP BY p.id, p.name, p.image, p.price, c.name, i.unit
                ORDER BY p.id ASC";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn tìm kiếm tồn kho: " . $this->conn->error);
        }
        $searchTerm = $query . '%';
        $stmt->bind_param('iss', $shop_id, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $inventory = [];
        while ($row = $result->fetch_assoc()) {
            $row['image_exists'] = !empty($row['image']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/datn/' . $row['image']);
            $inventory[] = $row;
        }
        $result->free();
        $stmt->close();
        return $inventory;
    }

    public function getShopName($main_db, $shop_db) {
        $conn_main = new mysqli($this->host, $this->username, $this->password, $main_db);
        if ($conn_main->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu chính: " . $conn_main->connect_error);
        }
        $conn_main->set_charset("utf8mb4");

        $sql = "SELECT name FROM shop WHERE db_name = ?";
        $stmt = $conn_main->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn name: " . $conn_main->error);
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

    public function getShopId($main_db, $shop_db) {
        $conn_main = new mysqli($this->host, $this->username, $this->password, $main_db);
        if ($conn_main->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu chính: " . $conn_main->connect_error);
        }
        $conn_main->set_charset("utf8mb4");

        $sql = "SELECT id FROM shop WHERE db_name = ?";
        $stmt = $conn_main->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn shop_id: " . $conn_main->error);
        }
        $stmt->bind_param('s', $shop_db);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $shop_id = $row['id'] ?? 0;
        if (!$row) {
            error_log("Không tìm thấy id cho db_name = '$shop_db' trong bảng shop.");
        }
        $stmt->close();
        $conn_main->close();
        return $shop_id;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>