<?php
session_start();
include '../config/db_connect.php';

function getConnection($host, $username, $password, $dbname) {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Lỗi kết nối đến cơ sở dữ liệu: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

$action = $_GET['action'] ?? '';
$transfer_id = $_GET['transfer_id'] ?? 0;
$shop_db = $_SESSION['shop_db'] ?? 'fashion_shopp';

$conn = getConnection($host, $username, $password, $shop_db);

if ($action === 'approve' && $transfer_id) {
    $conn->begin_transaction();
    try {
        $sql_transfer = "SELECT product_id, quantity, from_shop_id, to_shop_id FROM `$shop_db`.transfer_stock WHERE id = ?";
        $stmt_transfer = $conn->prepare($sql_transfer);
        $stmt_transfer->bind_param('i', $transfer_id);
        $stmt_transfer->execute();
        $result_transfer = $stmt_transfer->get_result();
        $transfer = $result_transfer->fetch_assoc();
        $stmt_transfer->close();

        if (!$transfer) {
            throw new Exception("Không tìm thấy đơn chuyển kho.");
        }

        $product_id = $transfer['product_id'];
        $quantity = $transfer['quantity'];
        $from_shop_id = $transfer['from_shop_id'];
        $to_shop_id = $transfer['to_shop_id'];

        $sql_to_shop = "SELECT db_name FROM `fashion_shopp`.shop WHERE id = ?";
        $stmt_to_shop = $conn->prepare($sql_to_shop);
        $stmt_to_shop->bind_param('i', $to_shop_id);
        $stmt_to_shop->execute();
        $result_to_shop = $stmt_to_shop->get_result();
        $to_shop = $result_to_shop->fetch_assoc();
        $stmt_to_shop->close();

        if (!$to_shop) {
            throw new Exception("Không tìm thấy cơ sở nhập.");
        }

        $to_shop_db = $to_shop['db_name'];
        $conn_to = getConnection($host, $username, $password, $to_shop_db);

        $sql_transfer_to = "SELECT id FROM `$to_shop_db`.transfer_stock WHERE product_id = ? AND from_shop_id = ? AND to_shop_id = ? AND quantity = ? AND status = 'pending'";
        $stmt_transfer_to = $conn_to->prepare($sql_transfer_to);
        $stmt_transfer_to->bind_param('iiii', $product_id, $from_shop_id, $to_shop_id, $quantity);
        $stmt_transfer_to->execute();
        $result_transfer_to = $stmt_transfer_to->get_result();
        $transfer_to = $result_transfer_to->fetch_assoc();
        $stmt_transfer_to->close();

        if (!$transfer_to) {
            throw new Exception("Không tìm thấy đơn chuyển kho tương ứng trong cơ sở nhập.");
        }

        $to_transfer_id = $transfer_to['id'];

        $sql_inventory_from = "UPDATE `$shop_db`.inventory SET quantity = quantity - ? WHERE product_id = ? AND shop_id = ?";
        $stmt_inventory_from = $conn->prepare($sql_inventory_from);
        $stmt_inventory_from->bind_param('iii', $quantity, $product_id, $from_shop_id);
        if (!$stmt_inventory_from->execute()) {
            throw new Exception("Lỗi khi giảm tồn kho cơ sở xuất: " . $stmt_inventory_from->error);
        }
        $stmt_inventory_from->close();

        $unit = 'Cái';
        $sql_inventory_to = "INSERT INTO `$to_shop_db`.inventory (product_id, shop_id, quantity, unit) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?";
        $stmt_inventory_to = $conn_to->prepare($sql_inventory_to);
        $stmt_inventory_to->bind_param('iiisi', $product_id, $to_shop_id, $quantity, $unit, $quantity);
        if (!$stmt_inventory_to->execute()) {
            throw new Exception("Lỗi khi tăng tồn kho cơ sở nhập: " . $stmt_inventory_to->error);
        }
        $stmt_inventory_to->close();

        $sql_update_from = "UPDATE `$shop_db`.transfer_stock SET status = 'approved' WHERE id = ?";
        $stmt_update_from = $conn->prepare($sql_update_from);
        $stmt_update_from->bind_param('i', $transfer_id);
        if (!$stmt_update_from->execute()) {
            throw new Exception("Lỗi khi cập nhật trạng thái (shop xuất): " . $stmt_update_from->error);
        }
        $stmt_update_from->close();

        $sql_update_to = "UPDATE `$to_shop_db`.transfer_stock SET status = 'approved' WHERE id = ?";
        $stmt_update_to = $conn_to->prepare($sql_update_to);
        $stmt_update_to->bind_param('i', $to_transfer_id);
        if (!$stmt_update_to->execute()) {
            throw new Exception("Lỗi khi cập nhật trạng thái (shop nhập): " . $stmt_update_to->error);
        }
        $stmt_update_to->close();

        $conn->commit();
        $conn_to->commit();
        header("Location: ../view/export_goods.php?approved=success");
    } catch (Exception $e) {
        $conn->rollback();
        $conn_to->rollback();
        error_log("Lỗi khi duyệt đơn chuyển kho: " . $e->getMessage());
        header("Location: ../view/export_goods.php?error=" . urlencode($e->getMessage()));
    } finally {
        $conn_to->close();
    }
} elseif ($action === 'reject' && $transfer_id) {
    $conn->begin_transaction();
    try {
        $sql_transfer = "SELECT product_id, quantity, from_shop_id, to_shop_id FROM `$shop_db`.transfer_stock WHERE id = ?";
        $stmt_transfer = $conn->prepare($sql_transfer);
        $stmt_transfer->bind_param('i', $transfer_id);
        $stmt_transfer->execute();
        $result_transfer = $stmt_transfer->get_result();
        $transfer = $result_transfer->fetch_assoc();
        $stmt_transfer->close();

        if (!$transfer) {
            throw new Exception("Không tìm thấy đơn chuyển kho.");
        }

        $product_id = $transfer['product_id'];
        $quantity = $transfer['quantity'];
        $from_shop_id = $transfer['from_shop_id'];
        $to_shop_id = $transfer['to_shop_id'];

        $sql_to_shop = "SELECT db_name FROM `fashion_shopp`.shop WHERE id = ?";
        $stmt_to_shop = $conn->prepare($sql_to_shop);
        $stmt_to_shop->bind_param('i', $to_shop_id);
        $stmt_to_shop->execute();
        $result_to_shop = $stmt_to_shop->get_result();
        $to_shop = $result_to_shop->fetch_assoc();
        $stmt_to_shop->close();

        if (!$to_shop) {
            throw new Exception("Không tìm thấy cơ sở nhập.");
        }

        $to_shop_db = $to_shop['db_name'];
        $conn_to = getConnection($host, $username, $password, $to_shop_db);

        $sql_transfer_to = "SELECT id FROM `$to_shop_db`.transfer_stock WHERE product_id = ? AND from_shop_id = ? AND to_shop_id = ? AND quantity = ? AND status = 'pending'";
        $stmt_transfer_to = $conn_to->prepare($sql_transfer_to);
        $stmt_transfer_to->bind_param('iiii', $product_id, $from_shop_id, $to_shop_id, $quantity);
        $stmt_transfer_to->execute();
        $result_transfer_to = $stmt_transfer_to->get_result();
        $transfer_to = $result_transfer_to->fetch_assoc();
        $stmt_transfer_to->close();

        if (!$transfer_to) {
            throw new Exception("Không tìm thấy đơn chuyển kho tương ứng trong cơ sở nhập.");
        }

        $to_transfer_id = $transfer_to['id'];

        $sql_update_from = "UPDATE `$shop_db`.transfer_stock SET status = 'rejected' WHERE id = ?";
        $stmt_update_from = $conn->prepare($sql_update_from);
        $stmt_update_from->bind_param('i', $transfer_id);
        if (!$stmt_update_from->execute()) {
            throw new Exception("Lỗi khi từ chối đơn (shop xuất): " . $stmt_update_from->error);
        }
        $stmt_update_from->close();

        $sql_update_to = "UPDATE `$to_shop_db`.transfer_stock SET status = 'rejected' WHERE id = ?";
        $stmt_update_to = $conn_to->prepare($sql_update_to);
        $stmt_update_to->bind_param('i', $to_transfer_id);
        if (!$stmt_update_to->execute()) {
            throw new Exception("Lỗi khi từ chối đơn (shop nhập): " . $stmt_update_to->error);
        }
        $stmt_update_to->close();

        $conn->commit();
        $conn_to->commit();
        header("Location: ../view/export_goods.php?rejected=success");
    } catch (Exception $e) {
        $conn->rollback();
        $conn_to->rollback();
        error_log("Lỗi khi từ chối đơn chuyển kho: " . $e->getMessage());
        header("Location: ../view/export_goods.php?error=" . urlencode($e->getMessage()));
    } finally {
        $conn_to->close();
    }
} elseif ($action === 'delete' && $transfer_id) {
    $conn->begin_transaction();
    try {
        // Ghi log thông tin đầu vào
        error_log("Xóa đơn chuyển kho: transfer_id=$transfer_id, shop_db=$shop_db");

        // Lấy thông tin đơn chuyển kho từ shop xuất
        $sql_transfer = "SELECT product_id, quantity, from_shop_id, to_shop_id FROM `$shop_db`.transfer_stock WHERE id = ?";
        $stmt_transfer = $conn->prepare($sql_transfer);
        if ($stmt_transfer === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn transfer_stock (shop xuất): " . $conn->error);
        }
        $stmt_transfer->bind_param('i', $transfer_id);
        $stmt_transfer->execute();
        $result_transfer = $stmt_transfer->get_result();
        $transfer = $result_transfer->fetch_assoc();
        $stmt_transfer->close();

        if (!$transfer) {
            throw new Exception("Không tìm thấy đơn chuyển kho với ID $transfer_id trong $shop_db.");
        }

        $product_id = $transfer['product_id'];
        $quantity = $transfer['quantity'];
        $from_shop_id = $transfer['from_shop_id'];
        $to_shop_id = $transfer['to_shop_id'];

        error_log("Thông tin đơn: product_id=$product_id, quantity=$quantity, from_shop_id=$from_shop_id, to_shop_id=$to_shop_id");

        // Lấy thông tin cơ sở nhập
        $sql_to_shop = "SELECT db_name FROM `fashion_shopp`.shop WHERE id = ?";
        $stmt_to_shop = $conn->prepare($sql_to_shop);
        if ($stmt_to_shop === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn shop: " . $conn->error);
        }
        $stmt_to_shop->bind_param('i', $to_shop_id);
        $stmt_to_shop->execute();
        $result_to_shop = $stmt_to_shop->get_result();
        $to_shop = $result_to_shop->fetch_assoc();
        $stmt_to_shop->close();

        if (!$to_shop) {
            throw new Exception("Không tìm thấy cơ sở nhập với ID $to_shop_id.");
        }

        $to_shop_db = $to_shop['db_name'];
        error_log("Cơ sở nhập: $to_shop_db");

        // Kết nối đến cơ sở nhập
        $conn_to = getConnection($host, $username, $password, $to_shop_db);

        // Tìm transfer_id trong shop nhập
        $sql_transfer_to = "SELECT id FROM `$to_shop_db`.transfer_stock WHERE product_id = ? AND from_shop_id = ? AND to_shop_id = ? AND quantity = ? AND status = 'pending'";
        $stmt_transfer_to = $conn_to->prepare($sql_transfer_to);
        if ($stmt_transfer_to === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn transfer_stock (shop nhập): " . $conn_to->error);
        }
        $stmt_transfer_to->bind_param('iiii', $product_id, $from_shop_id, $to_shop_id, $quantity);
        $stmt_transfer_to->execute();
        $result_transfer_to = $stmt_transfer_to->get_result();
        $transfer_to = $result_transfer_to->fetch_assoc();
        $stmt_transfer_to->close();

        if ($transfer_to) {
            $to_transfer_id = $transfer_to['id'];
            error_log("Tìm thấy đơn chuyển kho trong $to_shop_db: to_transfer_id=$to_transfer_id");

            // Xóa import_goods liên kết với transfer_id trong shop nhập
            $sql_delete_import = "DELETE FROM `$to_shop_db`.import_goods WHERE transfer_id = ?";
            $stmt_delete_import = $conn_to->prepare($sql_delete_import);
            if ($stmt_delete_import === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa import_goods: " . $conn_to->error);
            }
            $stmt_delete_import->bind_param('i', $to_transfer_id);
            if (!$stmt_delete_import->execute()) {
                throw new Exception("Lỗi khi xóa đơn nhập hàng: " . $stmt_delete_import->error);
            }
            $stmt_delete_import->close();
            error_log("Đã xóa import_goods với transfer_id=$to_transfer_id trong $to_shop_db");

            // Xóa transfer_stock trong shop nhập
            $sql_delete_to = "DELETE FROM `$to_shop_db`.transfer_stock WHERE id = ?";
            $stmt_delete_to = $conn_to->prepare($sql_delete_to);
            if ($stmt_delete_to === false) {
                throw new Exception("Lỗi chuẩn bị truy vấn xóa transfer_stock (shop nhập): " . $conn_to->error);
            }
            $stmt_delete_to->bind_param('i', $to_transfer_id);
            if (!$stmt_delete_to->execute()) {
                throw new Exception("Lỗi khi xóa đơn chuyển kho (shop nhập): " . $stmt_delete_to->error);
            }
            $stmt_delete_to->close();
            error_log("Đã xóa transfer_stock với id=$to_transfer_id trong $to_shop_db");
        } else {
            error_log("Không tìm thấy đơn chuyển kho trong $to_shop_db với product_id=$product_id, from_shop_id=$from_shop_id, to_shop_id=$to_shop_id, quantity=$quantity. Tiếp tục xóa trong $shop_db.");
        }

        // Xóa transfer_stock trong shop xuất
        $sql_delete_from = "DELETE FROM `$shop_db`.transfer_stock WHERE id = ?";
        $stmt_delete_from = $conn->prepare($sql_delete_from);
        if ($stmt_delete_from === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn xóa transfer_stock (shop xuất): " . $conn->error);
        }
        $stmt_delete_from->bind_param('i', $transfer_id);
        if (!$stmt_delete_from->execute()) {
            throw new Exception("Lỗi khi xóa đơn chuyển kho (shop xuất): " . $stmt_delete_from->error);
        }
        $stmt_delete_from->close();
        error_log("Đã xóa transfer_stock với id=$transfer_id trong $shop_db");

        $conn->commit();
        $conn_to->commit();
        header("Location: ../view/export_goods.php?deleted=success");
    } catch (Exception $e) {
        $conn->rollback();
        $conn_to->rollback();
        error_log("Lỗi khi xóa đơn chuyển kho: " . $e->getMessage());
        header("Location: ../view/export_goods.php?error=" . urlencode($e->getMessage()));
    } finally {
        $conn_to->close();
    }
}

$conn->close();
?>