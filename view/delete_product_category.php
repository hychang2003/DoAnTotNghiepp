<?php
session_start();

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Bao gồm file kết nối cơ sở dữ liệu
include '../config/db_connect.php';

// Sử dụng $conn_main để kết nối đến cơ sở dữ liệu chính fashion_shop
$conn = $conn_main;

// Hàm đồng bộ danh mục với các cơ sở (đã định nghĩa trong add_category.php, nhưng tôi sẽ lặp lại để đảm bảo)
function syncCategories($conn_main, $host, $username, $password) {
    $sql_shops = "SELECT db_name FROM shop";
    $result_shops = $conn_main->query($sql_shops);
    if ($result_shops === false) {
        return "Lỗi truy vấn danh sách cơ sở: " . $conn_main->error;
    }

    $sql_categories = "SELECT * FROM category";
    $result_categories = $conn_main->query($sql_categories);
    if ($result_categories === false) {
        return "Lỗi truy vấn danh mục: " . $conn_main->error;
    }

    $categories = [];
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }

    while ($shop = $result_shops->fetch_assoc()) {
        $shop_db = $shop['db_name'];
        $conn_shop = new mysqli($host, $username, $password, $shop_db);
        if ($conn_shop->connect_error) {
            continue;
        }

        $conn_shop->query("TRUNCATE TABLE category");

        foreach ($categories as $category) {
            $sql_insert = "INSERT INTO category (id, name, description, icon, `order`, created_at)
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn_shop->prepare($sql_insert);
            $stmt->bind_param('isssis', $category['id'], $category['name'], $category['description'], $category['icon'], $category['order'], $category['created_at']);
            $stmt->execute();
            $stmt->close();
        }

        $conn_shop->close();
    }

    $result_shops->free();
    $result_categories->free();
    return true;
}

// Xử lý xóa danh mục
if (isset($_GET['id'])) {
    $category_id = $_GET['id'];

    // Sử dụng transaction để đảm bảo tính toàn vẹn dữ liệu
    $conn->begin_transaction();

    try {
        // Xóa danh mục từ fashion_shop
        $sql = "DELETE FROM category WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $category_id);

        if ($stmt->execute()) {
            $stmt->close();

            // Đồng bộ danh mục với các cơ sở
            $sync_result = syncCategories($conn, $host, $username, $password);
            if ($sync_result !== true) {
                throw new Exception($sync_result);
            }

            $conn->commit();
            header("Location: product_category.php?category_deleted=success");
            exit();
        } else {
            throw new Exception("Lỗi khi xóa danh mục: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: product_category.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: product_category.php?error=" . urlencode("Không tìm thấy ID danh mục."));
    exit();
}

$conn->close();
?>