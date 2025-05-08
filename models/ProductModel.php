<?php
class ProductModel {
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
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    // Lấy danh sách danh mục
    public function getCategories() {
        $sql = "SELECT id, name FROM category";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn danh mục: " . $this->conn->error);
        }
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        $result->free();
        return $categories;
    }

    // Lấy danh sách chương trình khuyến mãi
    public function getFlashSales() {
        $sql = "SELECT id, name FROM flash_sale";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn flash_sale: " . $this->conn->error);
        }
        $flash_sales = [];
        while ($row = $result->fetch_assoc()) {
            $flash_sales[] = $row;
        }
        $result->free();
        return $flash_sales;
    }

    // Kiểm tra chương trình khuyến mãi hợp lệ
    public function isValidFlashSale($flash_sale_id) {
        $sql = "SELECT id FROM flash_sale WHERE id = ? AND start_date <= NOW() AND end_date >= NOW()";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn flash_sale: " . $this->conn->error);
        }
        $stmt->bind_param('i', $flash_sale_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_valid = $result->num_rows > 0;
        $stmt->close();
        return $is_valid;
    }

    // Thêm sản phẩm mới
    public function addProduct($name, $description, $image, $category_id, $type, $unit, $price, $cost_price, $quantity, $flash_sale_id = null) {
        $this->conn->begin_transaction();
        try {
            // Thêm sản phẩm
            $sql = "INSERT INTO product (name, description, image, category_id, type, unit, price, cost_price, quantity, flash_sale_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn sản phẩm: " . $this->conn->error);
            }
            $flash_sale_id = $flash_sale_id !== null ? $flash_sale_id : null;
            $stmt->bind_param('sssisssdi', $name, $description, $image, $category_id, $type, $unit, $price, $cost_price, $quantity, $flash_sale_id);
            $stmt->execute();
            $product_id = $this->conn->insert_id;
            $stmt->close();

            // Thêm vào product_flash_sale nếu có
            if ($flash_sale_id && $this->isValidFlashSale($flash_sale_id)) {
                $sql_flash_sale = "INSERT INTO product_flash_sale (product_id, flash_sale_id) VALUES (?, ?)";
                $stmt_flash_sale = $this->conn->prepare($sql_flash_sale);
                if ($stmt_flash_sale === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn product_flash_sale: " . $this->conn->error);
                }
                $stmt_flash_sale->bind_param('ii', $product_id, $flash_sale_id);
                $stmt_flash_sale->execute();
                $stmt_flash_sale->close();
            }

            $this->conn->commit();
            return $product_id;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Lấy thông tin sản phẩm theo ID
    public function getProductById($product_id) {
        $sql = "SELECT id, name, description, image, category_id, type, unit, price, cost_price, flash_sale_id 
                FROM product WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn sản phẩm: " . $this->conn->error);
        }
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();
        return $product;
    }

    // Cập nhật sản phẩm
    public function updateProduct($product_id, $name, $description, $image, $category_id, $type, $unit, $price, $cost_price, $flash_sale_id = null) {
        $this->conn->begin_transaction();
        try {
            // Cập nhật sản phẩm
            $sql = "UPDATE product 
                    SET name = ?, description = ?, image = ?, category_id = ?, type = ?, unit = ?, price = ?, cost_price = ?, flash_sale_id = ?
                    WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật sản phẩm: " . $this->conn->error);
            }
            $flash_sale_id = $flash_sale_id !== null ? $flash_sale_id : null;
            $stmt->bind_param('sssisssdi', $name, $description, $image, $category_id, $type, $unit, $price, $cost_price, $flash_sale_id, $product_id);
            $stmt->execute();
            $stmt->close();

            // Cập nhật product_flash_sale
            $sql_delete_flash_sale = "DELETE FROM product_flash_sale WHERE product_id = ?";
            $stmt_delete_flash_sale = $this->conn->prepare($sql_delete_flash_sale);
            if ($stmt_delete_flash_sale === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa product_flash_sale: " . $this->conn->error);
            }
            $stmt_delete_flash_sale->bind_param('i', $product_id);
            $stmt_delete_flash_sale->execute();
            $stmt_delete_flash_sale->close();

            if ($flash_sale_id && $this->isValidFlashSale($flash_sale_id)) {
                $sql_flash_sale = "INSERT INTO product_flash_sale (product_id, flash_sale_id) VALUES (?, ?)";
                $stmt_flash_sale = $this->conn->prepare($sql_flash_sale);
                if ($stmt_flash_sale === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn product_flash_sale: " . $this->conn->error);
                }
                $stmt_flash_sale->bind_param('ii', $product_id, $flash_sale_id);
                $stmt_flash_sale->execute();
                $stmt_flash_sale->close();
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Lấy danh sách sản phẩm
    public function getProducts() {
        $sql = "SELECT p.id, p.name, p.price, p.quantity, p.image, c.name AS category_name, f.name AS flash_sale_name
                FROM product p
                LEFT JOIN category c ON p.category_id = c.id
                LEFT JOIN product_flash_sale pf ON p.id = pf.product_id
                LEFT JOIN flash_sale f ON pf.flash_sale_id = f.id";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn sản phẩm: " . $this->conn->error);
        }
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $result->free();
        return $products;
    }

    // Lấy danh sách sản phẩm theo cơ sở dữ liệu
    public function fetchProducts() {
        $sql = "SELECT p.id, p.name, p.price, i.quantity 
                FROM product p 
                LEFT JOIN inventory i ON p.id = i.product_id";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn sản phẩm: " . $this->conn->error);
        }
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name']),
                'price' => floatval($row['price']),
                'quantity' => intval($row['quantity'] ?? 0)
            ];
        }
        $result->free();
        return $products;
    }

    // Xóa sản phẩm
    public function deleteProduct($product_id) {
        $this->conn->begin_transaction();
        try {
            // Xóa liên kết trong product_flash_sale
            $sql_flash_sale = "DELETE FROM product_flash_sale WHERE product_id = ?";
            $stmt_flash_sale = $this->conn->prepare($sql_flash_sale);
            if ($stmt_flash_sale === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa product_flash_sale: " . $this->conn->error);
            }
            $stmt_flash_sale->bind_param('i', $product_id);
            $stmt_flash_sale->execute();
            $stmt_flash_sale->close();

            // Xóa sản phẩm
            $sql_product = "DELETE FROM product WHERE id = ?";
            $stmt_product = $this->conn->prepare($sql_product);
            if ($stmt_product === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa sản phẩm: " . $this->conn->error);
            }
            $stmt_product->bind_param('i', $product_id);
            $stmt_product->execute();
            $stmt_product->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Đóng kết nối
    public function close() {
        $this->conn->close();
    }
}
?>