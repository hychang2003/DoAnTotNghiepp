<?php
class ImportGoodsModel {
    private $host;
    private $username;
    private $password;
    private $main_dbname;
    private $shop_dbname;
    private $conn_main;
    private $conn_shop;

    public function __construct($host, $username, $password, $main_dbname, $shop_dbname) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->main_dbname = $main_dbname;
        $this->shop_dbname = $shop_dbname;
        $this->connect();
    }

    private function connect() {
        $this->conn_main = new mysqli($this->host, $this->username, $this->password, $this->main_dbname);
        if ($this->conn_main->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu chính: " . $this->conn_main->connect_error);
        }
        $this->conn_main->set_charset("utf8mb4");

        $this->conn_shop = new mysqli($this->host, $this->username, $this->password, $this->shop_dbname);
        if ($this->conn_shop->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop: " . $this->conn_shop->connect_error);
        }
        $this->conn_shop->set_charset("utf8mb4");
    }

    public function getShopInfo($shop_db) {
        $sql = "SELECT id, name FROM shop WHERE db_name = ?";
        $stmt = $this->conn_main->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn shop: " . $this->conn_main->error);
        }
        $stmt->bind_param('s', $shop_db);
        $stmt->execute();
        $result = $stmt->get_result();
        $shop = $result->fetch_assoc();
        $stmt->close();
        return $shop ?: ['id' => 0, 'name' => $shop_db];
    }

    public function getOtherShops($shop_db) {
        $sql = "SELECT id, name, db_name FROM shop WHERE db_name != ?";
        $stmt = $this->conn_main->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn danh sách shop: " . $this->conn_main->error);
        }
        $stmt->bind_param('s', $shop_db);
        $stmt->execute();
        $result = $stmt->get_result();
        $shops = [];
        while ($row = $result->fetch_assoc()) {
            $shops[] = $row;
        }
        $stmt->close();
        return $shops;
    }

    public function getUsers() {
        $sql = "SELECT id, username AS name FROM `$this->shop_dbname`.users";
        $result = $this->conn_shop->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn danh sách người dùng: " . $this->conn_shop->error);
        }
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->free();
        return $users;
    }

    public function getFromShopDbName($shop_id) {
        $sql = "SELECT db_name FROM shop WHERE id = ?";
        $stmt = $this->conn_main->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn db_name: " . $this->conn_main->error);
        }
        $stmt->bind_param('i', $shop_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['db_name'] ?? null;
    }

    public function checkInventory($from_shop_db, $import_details) {
        $conn = new mysqli($this->host, $this->username, $this->password, $from_shop_db);
        if ($conn->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop xuất: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");

        $errors = [];
        foreach ($import_details as $detail) {
            $sql = "SELECT COALESCE(quantity, 0) as quantity FROM `$from_shop_db`.inventory WHERE product_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $detail['product_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $available_quantity = $row['quantity'] ?? 0;

            if ($available_quantity < $detail['quantity']) {
                $sql_product = "SELECT name FROM `$from_shop_db`.product WHERE id = ?";
                $stmt_product = $conn->prepare($sql_product);
                $stmt_product->bind_param('i', $detail['product_id']);
                $stmt_product->execute();
                $result_product = $stmt_product->get_result();
                $product = $result_product->fetch_assoc();
                $errors[] = "Sản phẩm {$product['name']} không đủ tồn kho. Yêu cầu: {$detail['quantity']}, Có sẵn: {$available_quantity}";
                $stmt_product->close();
            }
            $stmt->close();
        }

        $conn->close();
        return $errors;
    }

    public function getDefaultCategoryId() {
        $sql = "SELECT id FROM `$this->main_dbname`.category LIMIT 1";
        $result = $this->conn_main->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn danh mục: " . $this->conn_main->error);
        }
        $row = $result->fetch_assoc();
        $result->free();
        return $row['id'] ?? null;
    }

    public function syncProduct($from_shop_db, $product_id, $user_id, $default_category_id) {
        $conn_from = new mysqli($this->host, $this->username, $this->password, $from_shop_db);
        if ($conn_from->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop xuất: " . $conn_from->connect_error);
        }
        $conn_from->set_charset("utf8mb4");

        $sql = "SELECT name, price, description, image, category_id, type, unit, cost_price FROM `$from_shop_db`.product WHERE id = ?";
        $stmt = $conn_from->prepare($sql);
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        $stmt->close();
        $conn_from->close();

        if (!$product) {
            throw new Exception("Sản phẩm ID {$product_id} không tồn tại trong shop xuất.");
        }

        $sql_check = "SELECT id FROM `$this->shop_dbname`.product WHERE id = ?";
        $stmt_check = $this->conn_shop->prepare($sql_check);
        $stmt_check->bind_param('i', $product_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $existing_product = $result_check->fetch_assoc();
        $stmt_check->close();

        if (!$existing_product) {
            $sql_insert = "INSERT INTO `$this->shop_dbname`.product (id, name, price, description, image, category_id, type, unit, cost_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_insert = $this->conn_shop->prepare($sql_insert);
            $stmt_insert->bind_param('isdssissi', $product_id, $product['name'], $product['price'], $product['description'], $product['image'], $default_category_id, $product['type'], $product['unit'], $product['cost_price']);
            if (!$stmt_insert->execute()) {
                throw new Exception("Lỗi khi thêm sản phẩm vào shop nhập: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        }
    }

    private function getDefaultUserId($from_shop_db) {
        $conn = new mysqli($this->host, $this->username, $this->password, $from_shop_db);
        if ($conn->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop xuất: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");

        $sql = "SELECT id FROM `$from_shop_db`.users LIMIT 1";
        $result = $conn->query($sql);
        if ($result === false) {
            $conn->close();
            throw new Exception("Lỗi truy vấn người dùng trong $from_shop_db: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $result->free();
        $conn->close();

        if (!$row) {
            throw new Exception("Không tìm thấy người dùng nào trong $from_shop_db.users");
        }
        return $row['id'];
    }

    public function addImportGoods($from_shop_db, $current_shop_id, $from_shop_id, $user_id, $import_date, $import_details, $note) {
        error_log("addImportGoods: from_shop_db=$from_shop_db, current_shop_id=$current_shop_id, from_shop_id=$from_shop_id, user_id=$user_id, import_date=$import_date, import_details=" . print_r($import_details, true));

        // Kiểm tra tồn kho của shop xuất
        $inventory_errors = $this->checkInventory($from_shop_db, $import_details);
        if (!empty($inventory_errors)) {
            throw new Exception(implode("; ", $inventory_errors));
        }

        // Lấy user_id mặc định từ shop xuất
        $from_user_id = $this->getDefaultUserId($from_shop_db);

        // Kết nối đến cơ sở dữ liệu shop xuất
        $conn_from = new mysqli($this->host, $this->username, $this->password, $from_shop_db);
        if ($conn_from->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop xuất: " . $conn_from->connect_error);
        }
        $conn_from->set_charset("utf8mb4");

        $this->conn_shop->begin_transaction();
        $conn_from->begin_transaction();
        try {
            foreach ($import_details as $detail) {
                error_log("Processing detail: " . print_r($detail, true));
                // Đồng bộ sản phẩm
                $default_category_id = $this->getDefaultCategoryId();
                $this->syncProduct($from_shop_db, $detail['product_id'], $user_id, $default_category_id);

                // Chèn vào import_goods (shop nhập)
                $total_price = $detail['quantity'] * $detail['unit_price'];
                $supplier_id = null;
                $transfer_id = null;
                $sql_import = "INSERT INTO `$this->shop_dbname`.import_goods (product_id, supplier_id, quantity, unit_price, total_price, import_date, user_id, created_at, transfer_id) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                $stmt_import = $this->conn_shop->prepare($sql_import);
                if ($stmt_import === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn import_goods: " . $this->conn_shop->error);
                }
                $stmt_import->bind_param('iiiddsii', $detail['product_id'], $supplier_id, $detail['quantity'], $detail['unit_price'], $total_price, $import_date, $user_id, $transfer_id);
                if (!$stmt_import->execute()) {
                    throw new Exception("Lỗi khi tạo đơn nhập hàng: " . $stmt_import->error);
                }
                $import_id = $this->conn_shop->insert_id;
                $stmt_import->close();

                // Chèn vào transfer_stock (shop nhập - shop_11)
                $sql_transfer_shop = "INSERT INTO `$this->shop_dbname`.transfer_stock (product_id, from_shop_id, to_shop_id, user_id, quantity, transfer_date, note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                $stmt_transfer_shop = $this->conn_shop->prepare($sql_transfer_shop);
                if ($stmt_transfer_shop === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn transfer_stock (shop nhập): " . $this->conn_shop->error);
                }
                $stmt_transfer_shop->bind_param('iiiisss', $detail['product_id'], $from_shop_id, $current_shop_id, $user_id, $detail['quantity'], $import_date, $note);
                if (!$stmt_transfer_shop->execute()) {
                    throw new Exception("Lỗi khi tạo đơn chuyển kho (shop nhập): " . $stmt_transfer_shop->error);
                }
                $transfer_id = $this->conn_shop->insert_id;
                $stmt_transfer_shop->close();

                // Chèn vào transfer_stock (shop xuất - fashion_shopp)
                $sql_transfer_from = "INSERT INTO `$from_shop_db`.transfer_stock (product_id, from_shop_id, to_shop_id, user_id, quantity, transfer_date, note, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
                $stmt_transfer_from = $conn_from->prepare($sql_transfer_from);
                if ($stmt_transfer_from === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn transfer_stock (shop xuất): " . $conn_from->error);
                }
                $stmt_transfer_from->bind_param('iiiisss', $detail['product_id'], $from_shop_id, $current_shop_id, $from_user_id, $detail['quantity'], $import_date, $note);
                if (!$stmt_transfer_from->execute()) {
                    throw new Exception("Lỗi khi tạo đơn chuyển kho (shop xuất): " . $stmt_transfer_from->error);
                }
                $from_transfer_id = $conn_from->insert_id; // Lưu ID để tham khảo nếu cần
                $stmt_transfer_from->close();

                // Cập nhật transfer_id trong import_goods (dùng transfer_id từ shop_11)
                $sql_update_import = "UPDATE `$this->shop_dbname`.import_goods SET transfer_id = ? WHERE id = ?";
                $stmt_update_import = $this->conn_shop->prepare($sql_update_import);
                if ($stmt_update_import === false) {
                    throw new Exception("Lỗi chuẩn bị truy vấn update import_goods: " . $this->conn_shop->error);
                }
                $stmt_update_import->bind_param('ii', $transfer_id, $import_id);
                if (!$stmt_update_import->execute()) {
                    throw new Exception("Lỗi khi cập nhật transfer_id: " . $stmt_update_import->error);
                }
                $stmt_update_import->close();
            }

            $this->conn_shop->commit();
            $conn_from->commit();
        } catch (Exception $e) {
            $this->conn_shop->rollback();
            $conn_from->rollback();
            error_log("Lỗi trong addImportGoods: " . $e->getMessage());
            throw $e;
        } finally {
            $conn_from->close();
        }
    }

    public function close() {
        if ($this->conn_main) {
            $this->conn_main->close();
        }
        if ($this->conn_shop) {
            $this->conn_shop->close();
        }
    }
}
?>