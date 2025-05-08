<?php
class ImportGoodsModel {
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

    // Lấy thông tin shop hiện tại
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

    // Lấy danh sách các shop khác (trừ shop hiện tại)
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

    // Lấy danh sách nhân viên
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

    // Lấy db_name của shop xuất
    public function getFromShopDbName($from_shop_id) {
        $sql = "SELECT db_name FROM shop WHERE id = ?";
        $stmt = $this->conn_main->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn db_name shop xuất: " . $this->conn_main->error);
        }
        $stmt->bind_param('i', $from_shop_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['db_name'] ?? '';
    }

    // Kiểm tra tồn kho tại shop xuất
    public function checkInventory($from_shop_db, $import_details) {
        $conn_from_shop = new mysqli($this->host, $this->username, $this->password, $from_shop_db);
        if ($conn_from_shop->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop xuất: " . $conn_from_shop->connect_error);
        }
        $conn_from_shop->set_charset("utf8mb4");

        $errors = [];
        foreach ($import_details as $detail) {
            $sql = "SELECT quantity FROM `$from_shop_db`.inventory WHERE product_id = ?";
            $stmt = $conn_from_shop->prepare($sql);
            $stmt->bind_param('i', $detail['product_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $inventory = $result->fetch_assoc();
            if (!$inventory || $inventory['quantity'] < $detail['quantity']) {
                $errors[] = "Số lượng tồn kho không đủ cho sản phẩm ID {$detail['product_id']} tại shop xuất!";
            }
            $stmt->close();
        }
        $conn_from_shop->close();
        return $errors;
    }

    // Lấy danh mục mặc định của shop nhập
    public function getDefaultCategoryId() {
        $sql = "SELECT id FROM `{$this->shop_dbname}`.category LIMIT 1";
        $result = $this->conn_shop->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn danh mục: " . $this->conn_shop->error);
        }
        $row = $result->fetch_assoc();
        $result->free();
        return $row['id'] ?? null;
    }

    // Đồng bộ sản phẩm từ shop xuất sang shop nhập
    public function syncProduct($from_shop_db, $product_id, $employee_id, $default_category_id) {
        $conn_from_shop = new mysqli($this->host, $this->username, $this->password, $from_shop_db);
        if ($conn_from_shop->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop xuất: " . $conn_from_shop->connect_error);
        }
        $conn_from_shop->set_charset("utf8mb4");

        // Kiểm tra sản phẩm trong shop nhập
        $sql_check = "SELECT id FROM `{$this->shop_dbname}`.product WHERE id = ?";
        $stmt_check = $this->conn_shop->prepare($sql_check);
        $stmt_check->bind_param('i', $product_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $product_exists = $result_check->num_rows > 0;
        $stmt_check->close();

        if ($product_exists) {
            $conn_from_shop->close();
            return true;
        }

        // Lấy thông tin sản phẩm từ shop xuất
        $sql_get = "SELECT name, description, create_date, update_date, category_id, type, unit, price, cost_price 
                    FROM `$from_shop_db`.product WHERE id = ?";
        $stmt_get = $conn_from_shop->prepare($sql_get);
        $stmt_get->bind_param('i', $product_id);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        $product = $result_get->fetch_assoc();
        $stmt_get->close();

        if (!$product) {
            $conn_from_shop->close();
            throw new Exception("Không tìm thấy sản phẩm ID $product_id trong $from_shop_db.product");
        }

        // Kiểm tra category_id trong shop xuất
        $category_id = $product['category_id'];
        $sql_check_category_from = "SELECT id FROM `$from_shop_db`.category WHERE id = ?";
        $stmt_check_category_from = $conn_from_shop->prepare($sql_check_category_from);
        $stmt_check_category_from->bind_param('i', $category_id);
        $stmt_check_category_from->execute();
        $result_check_category_from = $stmt_check_category_from->get_result();
        if ($result_check_category_from->num_rows == 0) {
            error_log("Cảnh báo: category_id $category_id không tồn tại trong $from_shop_db.category. Sử dụng $default_category_id");
            $category_id = $default_category_id;
        }
        $stmt_check_category_from->close();

        // Kiểm tra category_id trong shop nhập
        $sql_check_category = "SELECT id FROM `{$this->shop_dbname}`.category WHERE id = ?";
        $stmt_check_category = $this->conn_shop->prepare($sql_check_category);
        $stmt_check_category->bind_param('i', $category_id);
        $stmt_check_category->execute();
        $result_check_category = $stmt_check_category->get_result();
        if ($result_check_category->num_rows == 0) {
            error_log("Cảnh báo: category_id $category_id không tồn tại trong {$this->shop_dbname}.category. Sử dụng $default_category_id");
            $category_id = $default_category_id;
        }
        $stmt_check_category->close();

        // Chèn sản phẩm vào shop nhập
        $sql_insert = "INSERT INTO `{$this->shop_dbname}`.product (id, name, description, create_date, update_date, employee_id, category_id, type, unit, price, cost_price, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt_insert = $this->conn_shop->prepare($sql_insert);
        $stmt_insert->bind_param('issssisisdd',
            $product_id,
            $product['name'],
            $product['description'],
            $product['create_date'],
            $product['update_date'],
            $employee_id,
            $category_id,
            $product['type'],
            $product['unit'],
            $product['price'],
            $product['cost_price']
        );
        $result = $stmt_insert->execute();
        $stmt_insert->close();
        $conn_from_shop->close();
        if (!$result) {
            throw new Exception("Lỗi khi đồng bộ sản phẩm ID $product_id: " . $this->conn_shop->error);
        }
        return true;
    }

    // Thêm đơn nhập hàng
    public function addImportGoods($from_shop_db, $current_shop_id, $from_shop_id, $employee_id, $import_date, $import_details, $note) {
        $conn_from_shop = new mysqli($this->host, $this->username, $this->password, $from_shop_db);
        if ($conn_from_shop->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop xuất: " . $conn_from_shop->connect_error);
        }
        $conn_from_shop->set_charset("utf8mb4");

        $this->conn_shop->begin_transaction();
        $conn_from_shop->begin_transaction();

        try {
            // Chuẩn bị các giá trị cho truy vấn gộp
            $transfer_values_from = [];
            $transfer_values_to = [];
            $export_values = [];
            $import_values = [];
            $inventory_from_updates = [];
            $inventory_to_updates = [];

            foreach ($import_details as $detail) {
                $product_id = $detail['product_id'];
                $quantity = $detail['quantity'];
                $unit_price = $detail['unit_price'];
                $subtotal = $detail['subtotal'];

                // Tạo bản ghi transfer_stock
                $transfer_values_from[] = "($product_id, $from_shop_id, $current_shop_id, $quantity, '$import_date', $employee_id, 'completed', NOW())";
                $transfer_values_to[] = "($product_id, $from_shop_id, $current_shop_id, $quantity, '$import_date', $employee_id, 'completed', NOW())";

                // Tạo bản ghi export_goods và import_goods
                $export_values[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'subtotal' => $subtotal
                ];
                $import_values[] = [
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'subtotal' => $subtotal
                ];

                // Cập nhật tồn kho
                $inventory_from_updates[] = "UPDATE `$from_shop_db`.inventory SET quantity = quantity - $quantity WHERE product_id = $product_id";
                $inventory_to_updates[] = "INSERT INTO `{$this->shop_dbname}`.inventory (product_id, quantity, unit, last_updated) 
                                          VALUES ($product_id, $quantity, 'Chiếc', NOW()) 
                                          ON DUPLICATE KEY UPDATE quantity = quantity + $quantity, last_updated = NOW()";
            }

            // Thêm transfer_stock cho shop xuất
            if (!empty($transfer_values_from)) {
                $sql_transfer_from = "INSERT INTO `$from_shop_db`.transfer_stock (product_id, from_shop_id, to_shop_id, quantity, transfer_date, employee_id, status, created_at) 
                                     VALUES " . implode(',', $transfer_values_from);
                if (!$conn_from_shop->query($sql_transfer_from)) {
                    throw new Exception("Lỗi khi tạo transfer_stock (shop xuất): " . $conn_from_shop->error);
                }
                $first_transfer_id_from = $conn_from_shop->insert_id;
                $transfer_ids_from = range($first_transfer_id_from, $first_transfer_id_from + count($transfer_values_from) - 1);
            }

            // Thêm transfer_stock cho shop nhập
            if (!empty($transfer_values_to)) {
                $sql_transfer_to = "INSERT INTO `{$this->shop_dbname}`.transfer_stock (product_id, from_shop_id, to_shop_id, quantity, transfer_date, employee_id, status, created_at) 
                                   VALUES " . implode(',', $transfer_values_to);
                if (!$this->conn_shop->query($sql_transfer_to)) {
                    throw new Exception("Lỗi khi tạo transfer_stock (shop nhập): " . $this->conn_shop->error);
                }
                $first_transfer_id_to = $this->conn_shop->insert_id;
                $transfer_ids_to = range($first_transfer_id_to, $first_transfer_id_to + count($transfer_values_to) - 1);
            }

            // Thêm export_goods
            $export_sql_values = [];
            foreach ($export_values as $index => $value) {
                $transfer_id = $transfer_ids_from[$index];
                $export_sql_values[] = "('$import_date', {$value['subtotal']}, {$value['quantity']}, {$value['unit_price']}, $employee_id, {$value['product_id']}, $transfer_id, NOW())";
            }
            if (!empty($export_sql_values)) {
                $sql_export = "INSERT INTO `$from_shop_db`.export_goods (export_date, total_price, quantity, unit_price, employee_id, product_id, transfer_id, created_at) 
                               VALUES " . implode(',', $export_sql_values);
                if (!$conn_from_shop->query($sql_export)) {
                    throw new Exception("Lỗi khi tạo export_goods: " . $conn_from_shop->error);
                }
            }

            // Thêm import_goods
            $import_sql_values = [];
            foreach ($import_values as $index => $value) {
                $transfer_id = $transfer_ids_to[$index];
                $import_sql_values[] = "({$value['product_id']}, {$value['quantity']}, {$value['unit_price']}, {$value['subtotal']}, '$import_date', $employee_id, $transfer_id, NOW())";
            }
            if (!empty($import_sql_values)) {
                $sql_import = "INSERT INTO `{$this->shop_dbname}`.import_goods (product_id, quantity, unit_price, total_price, import_date, employee_id, transfer_id, created_at) 
                               VALUES " . implode(',', $import_sql_values);
                if (!$this->conn_shop->query($sql_import)) {
                    throw new Exception("Lỗi khi tạo import_goods: " . $this->conn_shop->error);
                }
            }

            // Cập nhật tồn kho shop xuất
            foreach ($inventory_from_updates as $sql) {
                if (!$conn_from_shop->query($sql)) {
                    throw new Exception("Lỗi khi cập nhật tồn kho (shop xuất): " . $conn_from_shop->error);
                }
            }

            // Cập nhật tồn kho shop nhập
            foreach ($inventory_to_updates as $sql) {
                if (!$this->conn_shop->query($sql)) {
                    throw new Exception("Lỗi khi cập nhật tồn kho (shop nhập): " . $this->conn_shop->error);
                }
            }

            $this->conn_shop->commit();
            $conn_from_shop->commit();
            $conn_from_shop->close();
            return true;
        } catch (Exception $e) {
            $this->conn_shop->rollback();
            $conn_from_shop->rollback();
            $conn_from_shop->close();
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