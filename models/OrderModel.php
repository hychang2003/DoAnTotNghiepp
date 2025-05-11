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
        $conn_common = new mysqli($this->host, $this->username, $this->password, 'fashion_shopp');
        if ($conn_common->connect_error) {
            throw new Exception("Lỗi kết nối đến fashion_shopp: " . $conn_common->connect_error);
        }
        $conn_common->set_charset("utf8mb4");

        $sql = "SELECT discount FROM flash_sale WHERE start_date <= NOW() AND end_date >= NOW() AND status = 1 LIMIT 1";
        $result = $conn_common->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn flash_sale: " . $conn_common->error);
        }
        $row = $result->fetch_assoc();
        $result->free();
        $conn_common->close();
        return $row ? floatval($row['discount']) : 0;
    }

    public function getCustomers() {
        $sql = "SELECT id, name FROM customer";
        $result = $this->conn->query($sql);
        if ($result === false) {
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
        $sql = "SELECT id, name, price FROM product";
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

    public function addOrder($customer_id, $employee_id, $order_date, $total_price, $status, $products) {
        $debug_messages = [];
        $this->conn->begin_transaction();
        try {
            $debug_messages[] = "Bắt đầu addOrder: customer_id=$customer_id, employee_id=$employee_id, order_date=$order_date, total_price=$total_price, status=$status";

            if ($employee_id !== null) {
                $sql_check_user = "SELECT id FROM users WHERE id = ?";
                $stmt_check_user = $this->conn->prepare($sql_check_user);
                $stmt_check_user->bind_param('i', $employee_id);
                $stmt_check_user->execute();
                $result_check_user = $stmt_check_user->get_result();
                if ($result_check_user->num_rows === 0) {
                    $debug_messages[] = "ID người dùng không hợp lệ: $employee_id";
                    throw new Exception("ID người dùng không hợp lệ: $employee_id");
                }
                $stmt_check_user->close();
            }

            // Kiểm tra tồn kho
            foreach ($products as $product) {
                $product_id = intval($product['id']);
                $quantity = intval($product['quantity']);
                if ($quantity > 0) {
                    $sql_check_inventory = "SELECT quantity FROM inventory WHERE product_id = ? AND shop_id = 11";
                    $stmt_check_inventory = $this->conn->prepare($sql_check_inventory);
                    $stmt_check_inventory->bind_param('i', $product_id);
                    $stmt_check_inventory->execute();
                    $result_inventory = $stmt_check_inventory->get_result();
                    $inventory = $result_inventory->fetch_assoc();
                    $stmt_check_inventory->close();

                    if (!$inventory || $inventory['quantity'] < $quantity) {
                        $debug_messages[] = "Sản phẩm ID $product_id không đủ tồn kho: yêu cầu $quantity, còn " . ($inventory['quantity'] ?? 0);
                        throw new Exception("Sản phẩm ID $product_id không đủ tồn kho: yêu cầu $quantity, còn " . ($inventory['quantity'] ?? 0));
                    }
                }
            }

            $sql_order = "INSERT INTO `order` (customer_id, employee_id, order_date, total_price, status, created_at) 
                          VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt_order = $this->conn->prepare($sql_order);
            if ($stmt_order === false) {
                $debug_messages[] = "Lỗi chuẩn bị truy vấn đơn hàng: " . $this->conn->error;
                throw new Exception("Lỗi chuẩn bị truy vấn đơn hàng: " . $this->conn->error);
            }
            $stmt_order->bind_param('iisds', $customer_id, $employee_id, $order_date, $total_price, $status);
            $stmt_order->execute();
            $order_id = $this->conn->insert_id;
            $debug_messages[] = "Thêm order thành công, order_id: $order_id";
            $stmt_order->close();

            $sql_detail = "INSERT INTO `order_detail` (order_id, product_id, quantity, unit_price, discount, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt_detail = $this->conn->prepare($sql_detail);
            if ($stmt_detail === false) {
                $debug_messages[] = "Lỗi chuẩn bị truy vấn chi tiết đơn hàng: " . $this->conn->error;
                throw new Exception("Lỗi chuẩn bị truy vấn chi tiết đơn hàng: " . $this->conn->error);
            }

            $sql_update_inventory = "UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND shop_id = 11";
            $stmt_update_inventory = $this->conn->prepare($sql_update_inventory);
            if ($stmt_update_inventory === false) {
                $debug_messages[] = "Lỗi chuẩn bị truy vấn cập nhật tồn kho: " . $this->conn->error;
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật tồn kho: " . $this->conn->error);
            }

            foreach ($products as $product) {
                $product_id = intval($product['id']);
                $quantity = intval($product['quantity']);
                $unit_price = floatval($product['price']);
                $discount_percent = floatval($product['discount'] ?? 0);
                $discount_amount = ($unit_price * $discount_percent) / 100;

                if ($quantity > 0) {
                    // Thêm chi tiết đơn hàng
                    $debug_messages[] = "Thêm order_detail: order_id=$order_id, product_id=$product_id, quantity=$quantity, unit_price=$unit_price, discount=$discount_amount";
                    $stmt_detail->bind_param('iiidd', $order_id, $product_id, $quantity, $unit_price, $discount_amount);
                    $stmt_detail->execute();

                    // Cập nhật tồn kho
                    $debug_messages[] = "Cập nhật tồn kho: product_id=$product_id, giảm $quantity";
                    $stmt_update_inventory->bind_param('ii', $quantity, $product_id);
                    $stmt_update_inventory->execute();
                }
            }
            $stmt_detail->close();
            $stmt_update_inventory->close();

            $this->conn->commit();
            $debug_messages[] = "Commit thành công, order_id: $order_id";
            $_SESSION['debug_messages'] = array_merge($_SESSION['debug_messages'] ?? [], $debug_messages);
            return $order_id;
        } catch (Exception $e) {
            $this->conn->rollback();
            $debug_messages[] = "Lỗi khi thêm đơn hàng: " . $e->getMessage();
            $_SESSION['debug_messages'] = array_merge($_SESSION['debug_messages'] ?? [], $debug_messages);
            throw $e;
        }
    }

    public function updateOrder($order_id, $customer_id, $employee_id, $order_date, $total_price, $status) {
        $sql = "UPDATE `order` SET customer_id = ?, employee_id = ?, order_date = ?, total_price = ?, status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn cập nhật đơn hàng: " . $this->conn->error);
        }
        $stmt->bind_param('iissdi', $customer_id, $employee_id, $order_date, $total_price, $status, $order_id);
        $result = $stmt->execute();
        if ($result === false) {
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
                throw new Exception("Lỗi chuẩn bị truy vấn xóa chi tiết đơn hàng: " . $this->conn->error);
            }
            $stmt_details->bind_param('i', $order_id);
            $stmt_details->execute();
            $stmt_details->close();

            $sql_delete_order = "DELETE FROM `order` WHERE id = ?";
            $stmt_order = $this->conn->prepare($sql_delete_order);
            if ($stmt_order === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa đơn hàng: " . $this->conn->error);
            }
            $stmt_order->bind_param('i', $order_id);
            $stmt_order->execute();
            $stmt_order->close();

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
            $this->conn = null;
        }
    }
}
?>