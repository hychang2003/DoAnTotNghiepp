<?php
class FlashSaleModel {
    private $host;
    private $username;
    private $password;
    private $shop_dbname;
    private $conn_common; // Kết nối đến fashion_shopp
    private $conn_shop;   // Kết nối đến shop_dbname

    // Khởi tạo kết nối cơ sở dữ liệu
    public function __construct($host, $username, $password, $shop_dbname) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->shop_dbname = $shop_dbname;
        $this->connect();
    }

    // Thiết lập kết nối
    private function connect() {
        // Kết nối đến fashion_shopp (cho bảng flash_sale)
        $this->conn_common = new mysqli($this->host, $this->username, $this->password, 'fashion_shopp');
        if ($this->conn_common->connect_error) {
            error_log("Lỗi kết nối đến fashion_shopp: " . $this->conn_common->connect_error);
            throw new Exception("Lỗi kết nối đến fashion_shopp: " . $this->conn_common->connect_error);
        }
        $this->conn_common->set_charset("utf8mb4");

        // Kết nối đến shop_dbname (cho bảng product)
        $this->conn_shop = new mysqli($this->host, $this->username, $this->password, $this->shop_dbname);
        if ($this->conn_shop->connect_error) {
            error_log("Lỗi kết nối đến cơ sở dữ liệu shop: " . $this->conn_shop->connect_error);
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop: " . $this->conn_shop->connect_error);
        }
        $this->conn_shop->set_charset("utf8mb4");
    }

    // Lấy danh sách chương trình khuyến mãi
    public function getFlashSales() {
        $sql = "SELECT * FROM flash_sale ORDER BY start_date DESC";
        $result = $this->conn_common->query($sql);
        if ($result === false) {
            error_log("Lỗi truy vấn danh sách khuyến mãi: " . $this->conn_common->error);
            throw new Exception("Lỗi truy vấn danh sách khuyến mãi: " . $this->conn_common->error);
        }
        $flash_sales = [];
        while ($row = $result->fetch_assoc()) {
            $flash_sales[] = $row;
        }
        $result->free();
        return $flash_sales;
    }

    // Lấy thông tin chương trình khuyến mãi theo ID
    public function getFlashSaleById($flash_sale_id) {
        $sql = "SELECT * FROM flash_sale WHERE id = ?";
        $stmt = $this->conn_common->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn khuyến mãi theo ID: " . $this->conn_common->error);
            throw new Exception("Lỗi chuẩn bị truy vấn khuyến mãi theo ID: " . $this->conn_common->error);
        }
        $stmt->bind_param('i', $flash_sale_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $flash_sale = $result->fetch_assoc();
        $stmt->close();
        return $flash_sale;
    }

    // Thêm chương trình khuyến mãi
    public function addFlashSale($name, $discount, $start_date, $end_date) {
        $sql = "INSERT INTO flash_sale (name, discount, start_date, end_date) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn_common->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn thêm khuyến mãi: " . $this->conn_common->error);
            throw new Exception("Lỗi chuẩn bị truy vấn thêm khuyến mãi: " . $this->conn_common->error);
        }
        $stmt->bind_param('sdss', $name, $discount, $start_date, $end_date);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn thêm khuyến mãi: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn thêm khuyến mãi: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    // Cập nhật chương trình khuyến mãi
    public function updateFlashSale($flash_sale_id, $name, $discount, $start_date, $end_date, $status) {
        $sql = "UPDATE flash_sale SET name = ?, discount = ?, start_date = ?, end_date = ?, status = ? WHERE id = ?";
        $stmt = $this->conn_common->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn cập nhật khuyến mãi: " . $this->conn_common->error);
            throw new Exception("Lỗi chuẩn bị truy vấn cập nhật khuyến mãi: " . $this->conn_common->error);
        }
        $stmt->bind_param('sdssii', $name, $discount, $start_date, $end_date, $status, $flash_sale_id);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn cập nhật khuyến mãi: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn cập nhật khuyến mãi: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    // Xóa chương trình khuyến mãi
    public function deleteFlashSale($flash_sale_id) {
        $this->conn_common->begin_transaction();
        try {
            // Xóa liên kết chương trình khuyến mãi khỏi sản phẩm trong shop_dbname
            $sql_update = "UPDATE `$this->shop_dbname`.product SET flash_sale_id = NULL WHERE flash_sale_id = ?";
            $stmt_update = $this->conn_shop->prepare($sql_update);
            if ($stmt_update === false) {
                error_log("Lỗi chuẩn bị truy vấn cập nhật sản phẩm: " . $this->conn_shop->error);
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật sản phẩm: " . $this->conn_shop->error);
            }
            $stmt_update->bind_param('i', $flash_sale_id);
            $stmt_update->execute();
            $stmt_update->close();

            // Xóa chương trình khuyến mãi trong fashion_shopp
            $sql_delete = "DELETE FROM flash_sale WHERE id = ?";
            $stmt_delete = $this->conn_common->prepare($sql_delete);
            if ($stmt_delete === false) {
                error_log("Lỗi chuẩn bị truy vấn xóa khuyến mãi: " . $this->conn_common->error);
                throw new Exception("Lỗi chuẩn bị truy vấn xóa khuyến mãi: " . $this->conn_common->error);
            }
            $stmt_delete->bind_param('i', $flash_sale_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            $this->conn_common->commit();
            return true;
        } catch (Exception $e) {
            $this->conn_common->rollback();
            error_log("Lỗi khi xóa khuyến mãi: " . $e->getMessage());
            throw $e;
        }
    }

    // Lấy thông tin giảm giá của sản phẩm
    public function getProductDiscount($product_id) {
        $sql = "SELECT p.flash_sale_id, f.discount, f.start_date, f.end_date, f.status 
                FROM `$this->shop_dbname`.product p 
                LEFT JOIN fashion_shopp.flash_sale f ON p.flash_sale_id = f.id 
                WHERE p.id = ?";
        $stmt = $this->conn_shop->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn giảm giá: " . $this->conn_shop->error);
            throw new Exception("Lỗi chuẩn bị truy vấn giảm giá: " . $this->conn_shop->error);
        }
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();

        $response = ['discount' => 0, 'is_active' => false];

        if ($product && $product['flash_sale_id'] && $product['status'] == 1) {
            $current_date = date('Y-m-d H:i:s');
            if ($current_date >= $product['start_date'] && $current_date <= $product['end_date']) {
                $response['discount'] = $product['discount'];
                $response['is_active'] = true;
            }
        }

        return $response;
    }

    // Đóng kết nối
    public function close() {
        if ($this->conn_common) {
            $this->conn_common->close();
            $this->conn_common = null;
        }
        if ($this->conn_shop) {
            $this->conn_shop->close();
            $this->conn_shop = null;
        }
    }
}
?>