<?php
class OrderModel {
    private $host;
    private $username;
    private $password;
    private $shop_dbname;
    private $conn;

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
            error_log("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error);
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
        $this->conn->query("SET time_zone = '+07:00'");
    }

    public function getOrders() {
        $sql = "SELECT o.id, o.customer_id, c.name AS customer_name, o.employee_id, u.username AS user_name, 
                       o.order_date, o.total_price, o.status, o.created_at 
                FROM `order` o 
                LEFT JOIN customer c ON o.customer_id = c.id 
                LEFT JOIN users u ON o.employee_id = u.id 
                ORDER BY o.order_date DESC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            error_log("Lỗi truy vấn danh sách đơn hàng: " . $this->conn->error);
            throw new Exception("Lỗi truy vấn danh sách đơn hàng: " . $this->conn->error);
        }
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $result->free();
        return $orders;
    }

    public function getOrderById($order_id) {
        $sql = "SELECT o.id, o.customer_id, c.name AS customer_name, o.employee_id, u.username AS user_name, 
                       o.order_date, o.total_price, o.status, o.created_at 
                FROM `order` o 
                LEFT JOIN customer c ON o.customer_id = c.id 
                LEFT JOIN users u ON o.employee_id = u.id 
                WHERE o.id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn đơn hàng theo ID: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn đơn hàng theo ID: " . $this->conn->error);
        }
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        $stmt->close();
        return $order;
    }

    public function getActiveFlashSale() {
        $sql = "SELECT discount FROM flash_sale WHERE start_date <= NOW() AND end_date >= NOW() AND status = 1 LIMIT 1";
        $result = $this->conn->query($sql);
        if ($result === false) {
            error_log("Lỗi truy vấn flash_sale: " . $this->conn->error);
            throw new Exception("Lỗi truy vấn flash_sale: " . $this->conn->error);
        }
        $row = $result->fetch_assoc();
        $result->free();
        return $row ? floatval($row['discount']) : 0;
    }

    public function getCustomers() {
        $sql = "SELECT id, name FROM customer";
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
        return $customers;
    }

    public function getUsers() {
        $sql = "SELECT id, username AS name FROM users";
        $result = $this->conn->query($sql);
        if ($result === false) {
            error_log("Lỗi truy vấn người dùng: " . $this->conn->error);
            throw new Exception("Lỗi truy vấn người dùng: " . $this->conn->error);
        }
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->free();
        return $users;
    }

    public function getProducts() {
        $sql = "SELECT id, name, price, quantity FROM product";
        $result = $this->conn->query($sql);
        if ($result === false) {
            error_log("Lỗi truy vấn sản phẩm: " . $this->conn->error);
            throw new Exception("Lỗi truy vấn sản phẩm: " . $this->conn->error);
        }
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $result->free();
        return $products;
    }

    public function addOrder($customer_id, $employee_id, $order_date, $total_price, $status, $products) {
        $this->conn->begin_transaction();
        try {
            if ($employee_id !== null) {
                $sql_check_user = "SELECT id FROM users WHERE id = ?";
                $stmt_check_user = $this->conn->prepare($sql_check_user);
                $stmt_check_user->bind_param('i', $employee_id);
                $stmt_check_user->execute();
                $result_check_user = $stmt_check_user->get_result();
                if ($result_check_user->num_rows === 0) {
                    throw new Exception("ID người dùng không hợp lệ: $employee_id");
                }
                $stmt_check_user->close();
            }

            $sql_order = "INSERT INTO `order` (customer_id, employee_id, order_date, total_price, status, created_at) 
                          VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt_order = $this->conn->prepare($sql_order);
            if ($stmt_order === false) {
                error_log("Lỗi chuẩn bị truy vấn đơn hàng: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn đơn hàng: " . $this->conn->error);
            }
            $stmt_order->bind_param('iisds', $customer_id, $employee_id, $order_date, $total_price, $status);
            $stmt_order->execute();
            $order_id = $this->conn->insert_id;
            $stmt_order->close();

            $sql_detail = "INSERT INTO `order_detail` (order_id, product_id, quantity, unit_price, discount, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt_detail = $this->conn->prepare($sql_detail);
            if ($stmt_detail === false) {
                error_log("Lỗi chuẩn bị truy vấn chi tiết đơn hàng: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn chi tiết đơn hàng: " . $this->conn->error);
            }

            $sql_inventory = "UPDATE `product` SET quantity = quantity - ? WHERE id = ?";
            $stmt_inventory = $this->conn->prepare($sql_inventory);
            if ($stmt_inventory === false) {
                error_log("Lỗi chuẩn bị truy vấn cập nhật tồn kho: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật tồn kho: " . $this->conn->error);
            }

            foreach ($products as $product) {
                $product_id = intval($product['id']);
                $quantity = intval($product['quantity']);
                $unit_price = floatval($product['price']);
                $discount_percent = floatval($product['discount'] ?? 0);
                $discount_amount = ($unit_price * $discount_percent) / 100;

                if ($quantity > 0) {
                    $stmt_detail->bind_param('iiidd', $order_id, $product_id, $quantity, $unit_price, $discount_amount);
                    $stmt_detail->execute();

                    $stmt_inventory->bind_param('ii', $quantity, $product_id);
                    $stmt_inventory->execute();

                    $sql_check = "SELECT quantity FROM `product` WHERE id = ?";
                    $stmt_check = $this->conn->prepare($sql_check);
                    $stmt_check->bind_param('i', $product_id);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();
                    $inventory = $result_check->fetch_assoc();
                    if ($inventory === null || $inventory['quantity'] < 0) {
                        error_log("Số lượng tồn kho không đủ cho sản phẩm ID $product_id!");
                        throw new Exception("Số lượng tồn kho không đủ cho sản phẩm ID $product_id!");
                    }
                    $stmt_check->close();
                }
            }
            $stmt_detail->close();
            $stmt_inventory->close();

            $this->conn->commit();
            return $order_id;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Lỗi khi thêm đơn hàng: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateOrder($order_id, $customer_id, $employee_id, $order_date, $total_price, $status) {
        $sql = "UPDATE `order` SET customer_id = ?, employee_id = ?, order_date = ?, total_price = ?, status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn cập nhật đơn hàng: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn cập nhật đơn hàng: " . $this->conn->error);
        }
        $stmt->bind_param('iissdi', $customer_id, $employee_id, $order_date, $total_price, $status, $order_id);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn cập nhật đơn hàng: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn cập nhật đơn hàng: " . $stmt->error);
        }
        $stmt->close();
        return $result;
    }

    public function deleteOrder($order_id) {
        $this->conn->begin_transaction();
        try {
            $sql_delete_details = "DELETE FROM order_detail WHERE order_id = ?";
            $stmt_details = $this->conn->prepare($sql_delete_details);
            if ($stmt_details === false) {
                error_log("Lỗi chuẩn bị truy vấn xóa chi tiết đơn hàng: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn xóa chi tiết đơn hàng: " . $this->conn->error);
            }
            $stmt_details->bind_param('i', $order_id);
            $stmt_details->execute();
            $stmt_details->close();

            $sql_delete_order = "DELETE FROM `order` WHERE id = ?";
            $stmt_order = $this->conn->prepare($sql_delete_order);
            if ($stmt_order === false) {
                error_log("Lỗi chuẩn bị truy vấn xóa đơn hàng: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn xóa đơn hàng: " . $this->conn->error);
            }
            $stmt_order->bind_param('i', $order_id);
            $stmt_order->execute();
            $stmt_order->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Lỗi khi xóa đơn hàng: " . $e->getMessage());
            throw $e;
        }
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }
}
?>