<?php
class CustomerModel {
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

    // Thêm khách hàng mới
    public function addCustomer($name, $phone_number, $email, $address) {
        $sql = "INSERT INTO customer (name, phone_number, email, address) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn thêm khách hàng: " . $this->conn->error);
        }
        // Chuyển chuỗi rỗng thành NULL cho email và address
        $email = $email ?: null;
        $address = $address ?: null;
        $stmt->bind_param('ssss', $name, $phone_number, $email, $address);
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception("Lỗi thực thi truy vấn thêm khách hàng: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    // Đóng kết nối
    public function close() {
        $this->conn->close();
    }
}
?>