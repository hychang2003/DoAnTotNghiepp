<?php
class CategoryModel {
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
        $this->connect();
    }

    private function connect() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->dbname);
        if ($this->conn->connect_error) {
            error_log("Lỗi kết nối đến cơ sở dữ liệu {$this->dbname}: " . $this->conn->connect_error);
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
        $this->conn->query("SET SESSION wait_timeout = 30");
    }

    public function getShopName($main_db, $shop_db) {
        $conn_main = new mysqli($this->host, $this->username, $this->password, $main_db);
        if ($conn_main->connect_error) {
            error_log("Lỗi kết nối đến cơ sở dữ liệu chính {$main_db}: " . $conn_main->connect_error);
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
        return $shop_name;
    }

    public function addCategory($name, $icon_path) {
        $sql = "INSERT INTO category (name, icon) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn thêm danh mục: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn thêm danh mục: " . $this->conn->error);
        }
        $stmt->bind_param('ss', $name, $icon_path);
        $result = $stmt->execute();
        if (!$result) {
            error_log("Lỗi thực thi truy vấn thêm danh mục: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    public function getCategories() {
        $sql = "SELECT id, name, icon FROM category ORDER BY name ASC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            error_log("Lỗi truy vấn danh mục: " . $this->conn->error);
            throw new Exception("Lỗi truy vấn danh mục: " . $this->conn->error);
        }
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        $result->free();
        return $categories;
    }

    public function searchCategories($query) {
        $query = $this->conn->real_escape_string($query);
        $sql = "SELECT id, name, icon FROM category WHERE name LIKE ? ORDER BY name ASC";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn tìm kiếm danh mục: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn tìm kiếm danh mục: " . $this->conn->error);
        }
        $searchTerm = $query . '%';
        $stmt->bind_param('s', $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $row['icon_exists'] = !empty($row['icon']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/datn/' . $row['icon']);
            $categories[] = $row;
        }
        $result->free();
        $stmt->close();
        return $categories;
    }

    public function deleteCategory($category_id) {
        $this->conn->begin_transaction();
        try {
            $sql_check_products = "SELECT COUNT(*) as product_count FROM product WHERE category_id = ?";
            $stmt_check_products = $this->conn->prepare($sql_check_products);
            if ($stmt_check_products === false) {
                error_log("Lỗi chuẩn bị truy vấn kiểm tra sản phẩm: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra sản phẩm: " . $this->conn->error);
            }
            $stmt_check_products->bind_param('i', $category_id);
            $stmt_check_products->execute();
            $result_check_products = $stmt_check_products->get_result();
            $product_count = $result_check_products->fetch_assoc()['product_count'] ?? 0;
            $stmt_check_products->close();

            if ($product_count > 0) {
                throw new Exception("Không thể xóa danh mục vì vẫn còn $product_count sản phẩm thuộc danh mục này.");
            }

            $sql_delete = "DELETE FROM category WHERE id = ?";
            $stmt_delete = $this->conn->prepare($sql_delete);
            if ($stmt_delete === false) {
                error_log("Lỗi chuẩn bị truy vấn xóa danh mục: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn xóa danh mục: " . $this->conn->error);
            }
            $stmt_delete->bind_param('i', $category_id);
            $result = $stmt_delete->execute();
            if (!$result) {
                error_log("Lỗi thực thi truy vấn xóa danh mục: " . $stmt_delete->error);
            }
            $stmt_delete->close();

            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Lỗi xóa danh mục ID $category_id: " . $e->getMessage());
            throw $e;
        }
    }

    public function getCategoryById($category_id) {
        $sql = "SELECT id, name, icon FROM category WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn lấy danh mục: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn lấy danh mục: " . $this->conn->error);
        }
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $category = $result->fetch_assoc();
        $stmt->close();
        return $category;
    }

    public function updateCategory($category_id, $name) {
        $sql = "UPDATE category SET name = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn cập nhật danh mục: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn cập nhật danh mục: " . $this->conn->error);
        }
        $stmt->bind_param('si', $name, $category_id);
        $result = $stmt->execute();
        if (!$result) {
            error_log("Lỗi thực thi truy vấn cập nhật danh mục: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>