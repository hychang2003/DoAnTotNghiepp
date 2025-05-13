<?php
class CustomerModel {
    private $host;
    private $username;
    private $password;
    private $dbname;
    private $conn;

    public function __construct($host, $username, $password, $dbname) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->dbname = $dbname;
        error_log("Khởi tạo CustomerModel: host=$host, username=$username, dbname=$dbname");
        $this->connect();
    }

    private function connect() {
        error_log("Thử kết nối cơ sở dữ liệu: {$this->host}, {$this->dbname}");
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->dbname);
        if ($this->conn->connect_error) {
            error_log("Lỗi kết nối cơ sở dữ liệu: " . $this->conn->connect_error);
            throw new Exception("Lỗi kết nối cơ sở dữ liệu: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
        error_log("Kết nối cơ sở dữ liệu thành công: {$this->dbname}");
    }

    public function getShopName($main_db, $shop_db) {
        error_log("Lấy tên cửa hàng từ $main_db cho db_name=$shop_db");
        $conn_main = new mysqli($this->host, $this->username, $this->password, $main_db);
        if ($conn_main->connect_error) {
            error_log("Lỗi kết nối đến $main_db: " . $conn_main->connect_error);
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu chính: " . $conn_main->connect_error);
        }
        $conn_main->set_charset("utf8mb4");

        $sql = "SELECT name FROM shop WHERE db_name = ?";
        $stmt = $conn_main->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn name: " . $conn_main->error);
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
        error_log("Lấy tên cửa hàng thành công: $shop_name");
        return $shop_name;
    }

    public function addCustomer($name, $phone_number, $email, $address) {
        error_log("Thêm khách hàng: name=$name, phone_number=$phone_number, email=$email, address=$address trong {$this->dbname}");
        $sql = "INSERT INTO customer (name, phone_number, email, address) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn thêm khách hàng: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn thêm khách hàng: " . $this->conn->error);
        }
        $email = $email ?: null;
        $address = $address ?: null;
        $phone_number = $phone_number ?: null;
        $stmt->bind_param('ssss', $name, $phone_number, $email, $address);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn thêm khách hàng: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn thêm khách hàng: " . $stmt->error);
        }
        $stmt->close();
        error_log("Thêm khách hàng thành công trong {$this->dbname}");
        return $result;
    }

    public function updateCustomer($customer_id, $name, $email, $phone_number, $address) {
        error_log("Cập nhật khách hàng ID $customer_id: name=$name, email=$email, phone_number=$phone_number, address=$address trong {$this->dbname}");
        $sql = "UPDATE customer SET name = ?, phone_number = ?, email = ?, address = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn cập nhật khách hàng: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn cập nhật khách hàng: " . $this->conn->error);
        }
        $email = $email ?: null;
        $address = $address ?: null;
        $phone_number = $phone_number ?: null;
        $stmt->bind_param('ssssi', $name, $phone_number, $email, $address, $customer_id);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn cập nhật khách hàng: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn cập nhật khách hàng: " . $stmt->error);
        }
        $stmt->close();
        error_log("Cập nhật khách hàng ID $customer_id thành công trong {$this->dbname}");
        return $result;
    }

    public function getCustomers() {
        error_log("Lấy danh sách khách hàng từ {$this->dbname}");
        $sql = "SELECT id, name, phone_number, email, address FROM customer ORDER BY name ASC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            error_log("Lỗi truy vấn khách hàng: " . $this->conn->error);
            throw new Exception("Lỗi truy vấn khách hàng: " . $this->conn->error);
        }
        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        $result->free();
        error_log("Lấy danh sách khách hàng thành công: " . count($customers) . " khách hàng");
        return $customers;
    }

    public function searchCustomers($query) {
        error_log("Tìm kiếm khách hàng với query: $query trong {$this->dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }
        $query = $this->conn->real_escape_string($query);
        $sql = "SELECT id, name, phone_number, email, address 
                FROM customer 
                WHERE name LIKE ? OR phone_number LIKE ? 
                ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn tìm kiếm khách hàng: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn tìm kiếm khách hàng: " . $this->conn->error);
        }
        $searchTerm = $query . '%';
        $stmt->bind_param('ss', $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $customers = [];
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        $result->free();
        $stmt->close();
        error_log("Tìm kiếm khách hàng thành công: " . count($customers) . " khách hàng");
        return $customers;
    }

    public function getCustomerById($customer_id) {
        error_log("Lấy thông tin khách hàng ID: $customer_id từ {$this->dbname}");
        $sql = "SELECT id, name, phone_number, email, address FROM customer WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn khách hàng: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn khách hàng: " . $this->conn->error);
        }
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $customer = $result->fetch_assoc();
        $stmt->close();
        error_log($customer ? "Lấy thông tin khách hàng ID $customer_id thành công" : "Không tìm thấy khách hàng ID $customer_id");
        return $customer;
    }

    public function getCustomerHistory($customer_id) {
        error_log("Lấy lịch sử mua hàng khách hàng ID $customer_id từ {$this->dbname}");
        $conn_common = new mysqli($this->host, $this->username, $this->password, 'fashion_shopp');
        if ($conn_common->connect_error) {
            error_log("Lỗi kết nối đến fashion_shopp: " . $conn_common->connect_error);
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu chính: " . $conn_common->connect_error);
        }
        $conn_common->set_charset("utf8mb4");

        $sql = "SELECT o.id AS order_id, o.order_date, od.quantity, od.unit_price, od.discount, p.name AS product_name
                FROM `$this->dbname`.`order` o
                LEFT JOIN `$this->dbname`.order_detail od ON o.id = od.order_id
                LEFT JOIN `fashion_shopp`.product p ON od.product_id = p.id
                WHERE o.customer_id = ?
                ORDER BY o.order_date DESC";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn lịch sử mua hàng: " . $this->conn->error);
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
        $conn_common->close();
        error_log("Lấy lịch sử mua hàng thành công: " . count($history) . " đơn hàng");
        return $history;
    }

    public function deleteCustomer($customer_id) {
        error_log("Xóa khách hàng ID: $customer_id từ {$this->dbname}");
        $sql_check_orders = "SELECT COUNT(*) as order_count FROM `order` WHERE customer_id = ?";
        $stmt_check_orders = $this->conn->prepare($sql_check_orders);
        if ($stmt_check_orders === false) {
            error_log("Lỗi chuẩn bị truy vấn kiểm tra đơn hàng: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra đơn hàng: " . $this->conn->error);
        }
        $stmt_check_orders->bind_param('i', $customer_id);
        $stmt_check_orders->execute();
        $result_check_orders = $stmt_check_orders->get_result();
        $order_count = $result_check_orders->fetch_assoc()['order_count'] ?? 0;
        $stmt_check_orders->close();

        if ($order_count > 0) {
            error_log("Không thể xóa khách hàng ID $customer_id: Có $order_count đơn hàng liên quan");
            throw new Exception("Không thể xóa khách hàng vì vẫn còn $order_count đơn hàng liên quan.");
        }

        $sql = "DELETE FROM customer WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn xóa khách hàng: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn xóa khách hàng: " . $this->conn->error);
        }
        $stmt->bind_param('i', $customer_id);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn xóa khách hàng: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn xóa khách hàng: " . $stmt->error);
        }
        $stmt->close();
        error_log("Xóa khách hàng ID $customer_id thành công");
        return $result;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
            error_log("Đóng kết nối cơ sở dữ liệu {$this->dbname} thành công");
        }
    }
}
?>