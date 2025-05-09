<?php
class ProductModel {
    private $host;
    private $username;
    private $password;
    private $shop_dbname;
    private $conn;
    private $conn_common; // Kết nối đến fashion_shopp để lấy shop_id và bảng chung

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
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu $this->shop_dbname: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");

        $this->conn_common = new mysqli($this->host, $this->username, $this->password, 'fashion_shopp');
        if ($this->conn_common->connect_error) {
            throw new Exception("Lỗi kết nối đến fashion_shopp: " . $this->conn_common->connect_error);
        }
        $this->conn_common->set_charset("utf8mb4");
    }

    private function getShopId() {
        $sql = "SELECT id FROM shop WHERE db_name = ?";
        $stmt = $this->conn_common->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn shop_id: " . $this->conn_common->error);
        }
        $stmt->bind_param('s', $this->shop_dbname);
        $stmt->execute();
        $result = $stmt->get_result();
        $shop_id = $result->num_rows > 0 ? $result->fetch_assoc()['id'] : 1;
        $stmt->close();
        return $shop_id;
    }

    public function getCategories() {
        $sql = "SELECT id, name FROM fashion_shopp.category";
        $result = $this->conn_common->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn danh mục: " . $this->conn_common->error);
        }
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        $result->free();
        return $categories;
    }

    public function getFlashSales() {
        $sql = "SELECT id, name FROM fashion_shopp.flash_sale";
        $result = $this->conn_common->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn flash_sale: " . $this->conn_common->error);
        }
        $flash_sales = [];
        while ($row = $result->fetch_assoc()) {
            $flash_sales[] = $row;
        }
        $result->free();
        return $flash_sales;
    }

    public function isValidFlashSale($flash_sale_id) {
        $sql = "SELECT id FROM fashion_shopp.flash_sale WHERE id = ? AND start_date <= NOW() AND end_date >= NOW()";
        $stmt = $this->conn_common->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn flash_sale: " . $this->conn_common->error);
        }
        $stmt->bind_param('i', $flash_sale_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $is_valid = $result->num_rows > 0;
        $stmt->close();
        return $is_valid;
    }

    public function addProduct($name, $description, $image, $category_id, $type, $unit, $price, $cost_price, $quantity, $flash_sale_id = null) {
        $this->conn->begin_transaction();
        try {
            // Thêm sản phẩm vào product của cơ sở hiện tại
            $sql = "INSERT INTO `$this->shop_dbname`.product (name, description, image, category_id, type, unit, price, cost_price, flash_sale_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn sản phẩm: " . $this->conn->error);
            }
            $flash_sale_id = $flash_sale_id !== null ? $flash_sale_id : null;
            $stmt->bind_param('sssisssdi', $name, $description, $image, $category_id, $type, $unit, $price, $cost_price, $flash_sale_id);
            $stmt->execute();
            $product_id = $this->conn->insert_id;
            $stmt->close();

            // Thêm vào product_flash_sale nếu có
            if ($flash_sale_id && $this->isValidFlashSale($flash_sale_id)) {
                $sql_flash_sale = "INSERT INTO `$this->shop_dbname`.product_flash_sale (product_id, flash_sale_id) VALUES (?, ?)";
                $stmt_flash_sale = $this->conn->prepare($sql_flash_sale);
                if ($stmt_flash_sale === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn product_flash_sale: " . $this->conn->error);
                }
                $stmt_flash_sale->bind_param('ii', $product_id, $flash_sale_id);
                $stmt_flash_sale->execute();
                $stmt_flash_sale->close();
            }

            // Thêm số lượng vào inventory
            $shop_id = $this->getShopId();
            $sql_inventory = "INSERT INTO `$this->shop_dbname`.inventory (product_id, shop_id, quantity, unit) VALUES (?, ?, ?, ?)";
            $stmt_inventory = $this->conn->prepare($sql_inventory);
            if ($stmt_inventory === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn inventory: " . $this->conn->error);
            }
            $stmt_inventory->bind_param('iiis', $product_id, $shop_id, $quantity, $unit);
            $stmt_inventory->execute();
            $stmt_inventory->close();

            $this->conn->commit();
            return $product_id;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function getProductById($product_id) {
        $sql = "SELECT id, name, description, image, category_id, type, unit, price, cost_price, flash_sale_id 
                FROM `$this->shop_dbname`.product WHERE id = ?";
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

    public function updateProduct($product_id, $name, $description, $image, $category_id, $type, $unit, $price, $cost_price, $quantity, $flash_sale_id = null) {
        $this->conn->begin_transaction();
        try {
            // Cập nhật sản phẩm
            $sql = "UPDATE `$this->shop_dbname`.product 
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
            $sql_delete_flash_sale = "DELETE FROM `$this->shop_dbname`.product_flash_sale WHERE product_id = ?";
            $stmt_delete_flash_sale = $this->conn->prepare($sql_delete_flash_sale);
            if ($stmt_delete_flash_sale === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa product_flash_sale: " . $this->conn->error);
            }
            $stmt_delete_flash_sale->bind_param('i', $product_id);
            $stmt_delete_flash_sale->execute();
            $stmt_delete_flash_sale->close();

            if ($flash_sale_id && $this->isValidFlashSale($flash_sale_id)) {
                $sql_flash_sale = "INSERT INTO `$this->shop_dbname`.product_flash_sale (product_id, flash_sale_id) VALUES (?, ?)";
                $stmt_flash_sale = $this->conn->prepare($sql_flash_sale);
                if ($stmt_flash_sale === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn product_flash_sale: " . $this->conn->error);
                }
                $stmt_flash_sale->bind_param('ii', $product_id, $flash_sale_id);
                $stmt_flash_sale->execute();
                $stmt_flash_sale->close();
            }

            // Cập nhật inventory
            $shop_id = $this->getShopId();
            $sql_inventory = "INSERT INTO `$this->shop_dbname`.inventory (product_id, shop_id, quantity, unit) 
                             VALUES (?, ?, ?, ?) 
                             ON DUPLICATE KEY UPDATE quantity = ?, unit = ?";
            $stmt_inventory = $this->conn->prepare($sql_inventory);
            if ($stmt_inventory === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật inventory: " . $this->conn->error);
            }
            $stmt_inventory->bind_param('iiisis', $product_id, $shop_id, $quantity, $unit, $quantity, $unit);
            $stmt_inventory->execute();
            $stmt_inventory->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function getProducts($shop_id) {
        $sql = "SELECT p.id, p.name, p.price, COALESCE(i.quantity, 0) AS quantity, p.image, c.name AS category_name, f.name AS flash_sale_name
                FROM `$this->shop_dbname`.product p
                LEFT JOIN fashion_shopp.category c ON p.category_id = c.id
                LEFT JOIN `$this->shop_dbname`.product_flash_sale pf ON p.id = pf.product_id
                LEFT JOIN fashion_shopp.flash_sale f ON pf.flash_sale_id = f.id
                LEFT JOIN `$this->shop_dbname`.inventory i ON p.id = i.product_id AND i.shop_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn sản phẩm: " . $this->conn->error);
        }
        $stmt->bind_param('i', $shop_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $result->free();
        $stmt->close();
        return $products;
    }

    public function fetchProducts($shop_id) {
        $sql = "SELECT p.id, p.name, p.price, COALESCE(i.quantity, 0) AS quantity
                FROM `$this->shop_dbname`.product p 
                LEFT JOIN `$this->shop_dbname`.inventory i ON p.id = i.product_id AND i.shop_id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn sản phẩm: " . $this->conn->error);
        }
        $stmt->bind_param('i', $shop_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name']),
                'price' => floatval($row['price']),
                'quantity' => intval($row['quantity'])
            ];
        }
        $result->free();
        $stmt->close();
        return $products;
    }

    public function deleteProduct($product_id) {
        $this->conn->begin_transaction();
        try {
            $sql_flash_sale = "DELETE FROM `$this->shop_dbname`.product_flash_sale WHERE product_id = ?";
            $stmt_flash_sale = $this->conn->prepare($sql_flash_sale);
            if ($stmt_flash_sale === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa product_flash_sale: " . $this->conn->error);
            }
            $stmt_flash_sale->bind_param('i', $product_id);
            $stmt_flash_sale->execute();
            $stmt_flash_sale->close();

            $sql_product = "DELETE FROM `$this->shop_dbname`.product WHERE id = ?";
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

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
        if ($this->conn_common) {
            $this->conn_common->close();
        }
    }
}
?>