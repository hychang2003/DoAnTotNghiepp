<?php
class ExportGoodsModel {
    private $host;
    private $username;
    private $password;
    private $main_dbname;
    private $shop_dbname;
    private $conn_main;
    private $conn_shop;

    // Khởi tạo kết nối cơ sở dữ liệu
    public function __construct($host, $username, $password, $main_dbname, $shop_dbname) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->main_dbname = $main_dbname;
        $this->shop_dbname = $shop_dbname;
        $this->connect();
    }

    // Thiết lập kết nối
    private function connect() {
        // Kết nối đến cơ sở dữ liệu chính (fashion_shop)
        $this->conn_main = new mysqli($this->host, $this->username, $this->password, $this->main_dbname);
        if ($this->conn_main->connect_error) {
            die("Lỗi kết nối đến cơ sở dữ liệu chính: " . $this->conn_main->connect_error);
        }
        $this->conn_main->set_charset("utf8mb4");

        // Kết nối đến cơ sở dữ liệu cửa hàng
        $this->conn_shop = new mysqli($this->host, $this->username, $this->password, $this->shop_dbname);
        if ($this->conn_shop->connect_error) {
            die("Lỗi kết nối đến cơ sở dữ liệu cửa hàng: " . $this->conn_shop->connect_error);
        }
        $this->conn_shop->set_charset("utf8mb4");
    }

    // Lấy tên cửa hàng từ bảng shop
    public function getShopName($shop_db) {
        $sql = "SELECT name FROM shop WHERE db_name = ?";
        $stmt = $this->conn_main->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn name: " . $this->conn_main->error);
        }
        $stmt->bind_param('s', $shop_db);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['name'] ?? $shop_db;
    }

    // Lấy danh sách nhà cung cấp từ fashion_shop.supplier
    public function getSuppliers() {
        $sql = "SELECT id, name FROM `{$this->main_dbname}`.supplier";
        $result = $this->conn_main->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn nhà cung cấp: " . $this->conn_main->error);
        }
        $suppliers = [];
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }
        $result->free();
        return $suppliers;
    }

    // Lấy danh sách nhân viên từ shop.employee
    public function getEmployees() {
        $sql = "SELECT id, name FROM `{$this->shop_dbname}`.employee";
        $result = $this->conn_shop->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn nhân viên: " . $this->conn_shop->error);
        }
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        $result->free();
        return $employees;
    }

    // Lấy danh sách sản phẩm và số lượng tồn kho
    public function getProducts() {
        $sql = "SELECT p.id, p.name, p.price, IFNULL(i.quantity, 0) AS stock_quantity 
                FROM `{$this->shop_dbname}`.product p 
                LEFT JOIN `{$this->shop_dbname}`.inventory i ON p.id = i.product_id";
        $result = $this->conn_shop->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn sản phẩm: " . $this->conn_shop->error);
        }
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $result->free();
        return $products;
    }

    // Kiểm tra số lượng tồn kho của sản phẩm
    public function checkStock($product_id) {
        $sql = "SELECT IFNULL(quantity, 0) AS stock_quantity 
                FROM `{$this->shop_dbname}`.inventory 
                WHERE product_id = ?";
        $stmt = $this->conn_shop->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra tồn kho: " . $this->conn_shop->error);
        }
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stock = $result->fetch_assoc()['stock_quantity'];
        $stmt->close();
        return $stock;
    }

    // Thêm đơn xuất hàng và cập nhật tồn kho
    public function addExportGoods($export_details, $export_date, $employee_id, $note) {
        $this->conn_shop->begin_transaction();
        try {
            foreach ($export_details as $detail) {
                // Thêm vào bảng export_goods
                $sql_export = "INSERT INTO `{$this->shop_dbname}`.export_goods (product_id, quantity, unit_price, total_price, export_date, employee_id, note, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt_export = $this->conn_shop->prepare($sql_export);
                if ($stmt_export === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn export_goods: " . $this->conn_shop->error);
                }
                $stmt_export->bind_param('iiddsis', $detail['product_id'], $detail['quantity'], $detail['unit_price'], $detail['subtotal'], $export_date, $employee_id, $note);
                $stmt_export->execute();
                $stmt_export->close();

                // Cập nhật tồn kho
                $sql_inventory = "UPDATE `{$this->shop_dbname}`.inventory 
                                 SET quantity = quantity - ?, last_updated = NOW() 
                                 WHERE product_id = ?";
                $stmt_inventory = $this->conn_shop->prepare($sql_inventory);
                if ($stmt_inventory === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn inventory: " . $this->conn_shop->error);
                }
                $stmt_inventory->bind_param('ii', $detail['quantity'], $detail['product_id']);
                $stmt_inventory->execute();
                $stmt_inventory->close();
            }
            $this->conn_shop->commit();
            return true;
        } catch (Exception $e) {
            $this->conn_shop->rollback();
            throw $e;
        }
    }

    // Đóng kết nối
    public function close() {
        $this->conn_main->close();
        $this->conn_shop->close();
    }
}
?>