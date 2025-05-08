<?php
class FlashSaleModel {
    private $host;
    private $username;
    private $password;
    private $shop_dbname;
    private $conn;

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
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->shop_dbname);
        if ($this->conn->connect_error) {
            error_log("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error);
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    // Lấy danh sách chương trình khuyến mãi
    public function getFlashSales() {
        $sql = "SELECT * FROM flash_sale ORDER BY start_date DESC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            error_log("Lỗi truy vấn danh sách khuyến mãi: " . $this->conn->error);
            throw new Exception("Lỗi truy vấn danh sách khuyến mãi: " . $this->conn->error);
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
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn khuyến mãi theo ID: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn khuyến mãi theo ID: " . $this->conn->error);
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
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn thêm khuyến mãi: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn thêm khuyến mãi: " . $this->conn->error);
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
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn cập nhật khuyến mãi: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn cập nhật khuyến mãi: " . $this->conn->error);
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
        $this->conn->begin_transaction();
        try {
            // Xóa liên kết chương trình khuyến mãi khỏi sản phẩm
            $sql_update = "UPDATE product SET flash_sale_id = NULL WHERE flash_sale_id = ?";
            $stmt_update = $this->conn->prepare($sql_update);
            if ($stmt_update === false) {
                error_log("Lỗi chuẩn bị truy vấn cập nhật sản phẩm: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật sản phẩm: " . $this->conn->error);
            }
            $stmt_update->bind_param('i', $flash_sale_id);
            $stmt_update->execute();
            $stmt_update->close();

            // Xóa chương trình khuyến mãi
            $sql_delete = "DELETE FROM flash_sale WHERE id = ?";
            $stmt_delete = $this->conn->prepare($sql_delete);
            if ($stmt_delete === false) {
                error_log("Lỗi chuẩn bị truy vấn xóa khuyến mãi: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn xóa khuyến mãi: " . $this->conn->error);
            }
            $stmt_delete->bind_param('i', $flash_sale_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Lỗi khi xóa khuyến mãi: " . $e->getMessage());
            throw $e;
        }
    }

    // Lấy thông tin giảm giá của sản phẩm
    public function getProductDiscount($product_id) {
        $sql = "SELECT p.flash_sale_id, f.discount, f.start_date, f.end_date, f.status 
                FROM product p 
                LEFT JOIN flash_sale f ON p.flash_sale_id = f.id 
                WHERE p.id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn giảm giá: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn giảm giá: " . $this->conn->error);
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
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }
}
?>