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

    // Lấy tên cửa hàng từ db_name
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

    // Thêm khách hàng mới
    public function addCustomer($name, $phone_number, $email, $address) {
        $sql = "INSERT INTO customer (name, phone_number, email, address) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn thêm khách hàng: " . $this->conn->error);
        }
        $email = $email ?: null;
        $address = $address ?: null;
        $phone_number = $phone_number ?: null;
        $stmt->bind_param('ssss', $name, $phone_number, $email, $address);
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception("Lỗi thực thi truy vấn thêm khách hàng: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    // Cập nhật khách hàng
    public function updateCustomer($customer_id, $name, $email, $phone_number, $address) {
        $sql = "UPDATE customer SET name = ?, phone_number = ?, email = ?, address = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn cập nhật khách hàng: " . $this->conn->error);
        }
        $email = $email ?: null;
        $address = $address ?: null;
        $phone_number = $phone_number ?: null;
        $stmt->bind_param('ssssi', $name, $phone_number, $email, $address, $customer_id);
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception("Lỗi thực thi truy vấn cập nhật khách hàng: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    // Lấy danh sách khách hàng
    public function getCustomers() {
        $sql = "SELECT id, name, phone_number, email, address FROM customer ORDER BY name ASC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn khách hàng: " . $this->conn->error);
        }
        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        $result->free();
        return $customers;
    }

    // Lấy thông tin khách hàng theo ID
    public function getCustomerById($customer_id) {
        $sql = "SELECT id, name, phone_number, email, address FROM customer WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn khách hàng: " . $this->conn->error);
        }
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $customer = $result->fetch_assoc();
        $stmt->close();
        return $customer;
    }

    // Lấy lịch sử mua hàng của khách hàng
    public function getCustomerHistory($customer_id) {
        $sql = "SELECT o.id AS order_id, o.order_date, od.quantity, od.unit_price, od.discount, p.name AS product_name
                FROM `order` o
                LEFT JOIN order_detail od ON o.id = od.order_id
                LEFT JOIN product p ON od.product_id = p.id
                WHERE o.customer_id = ?
                ORDER BY o.order_date DESC";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn lịch sử mua hàng: " . $this->conn->error);
        }
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $result->free();
        $stmt->close();
        return $history;
    }

    // Xóa khách hàng
    public function deleteCustomer($customer_id) {
        // Kiểm tra đơn hàng liên quan
        $sql_check_orders = "SELECT COUNT(*) as order_count FROM `order` WHERE customer_id = ?";
        $stmt_check_orders = $this->conn->prepare($sql_check_orders);
        if ($stmt_check_orders === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra đơn hàng: " . $this->conn->error);
        }
        $stmt_check_orders->bind_param('i', $customer_id);
        $stmt_check_orders->execute();
        $result_check_orders = $stmt_check_orders->get_result();
        $order_count = $result_check_orders->fetch_assoc()['order_count'] ?? 0;
        $stmt_check_orders->close();

        if ($order_count > 0) {
            throw new Exception("Không thể xóa khách hàng vì vẫn còn $order_count đơn hàng liên quan.");
        }

        // Xóa khách hàng
        $sql = "DELETE FROM customer WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn xóa khách hàng: " . $this->conn->error);
        }
        $stmt->bind_param('i', $customer_id);
        $result = $stmt->execute();
        if ($result === false) {
            throw new Exception("Lỗi thực thi truy vấn xóa khách hàng: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    // Đóng kết nối
    public function close() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }
}
?>