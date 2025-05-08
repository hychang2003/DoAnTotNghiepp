<?php
class ReportModel {
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

    // Doanh thu theo ngày (30 ngày gần nhất)
    public function getDailyRevenue() {
        $sql = "SELECT DATE(order_date) AS order_day, SUM(total_price) AS total_revenue
                FROM `order`
                WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND status = 'completed'
                GROUP BY DATE(order_date)
                ORDER BY order_date ASC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn doanh thu theo ngày: " . $this->conn->error);
        }
        $labels = [];
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $labels[] = date('d/m/Y', strtotime($row['order_day']));
            $data[] = floatval($row['total_revenue']);
        }
        $result->free();
        return ['labels' => $labels, 'data' => $data];
    }

    // Doanh thu theo tháng (trong năm hiện tại)
    public function getMonthlyRevenue() {
        $data = array_fill(1, 12, 0); // Mảng 12 tháng, mặc định là 0
        $labels = ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
        $sql = "SELECT MONTH(order_date) AS order_month, SUM(total_price) AS total_revenue
                FROM `order`
                WHERE YEAR(order_date) = YEAR(CURDATE())
                AND status = 'completed'
                GROUP BY MONTH(order_date)";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn doanh thu theo tháng: " . $this->conn->error);
        }
        while ($row = $result->fetch_assoc()) {
            $data[$row['order_month']] = floatval($row['total_revenue']);
        }
        $result->free();
        return ['labels' => $labels, 'data' => array_values($data)];
    }

    // Doanh thu theo năm
    public function getYearlyRevenue() {
        $sql = "SELECT YEAR(order_date) AS order_year, SUM(total_price) AS total_revenue
                FROM `order`
                WHERE status = 'completed'
                GROUP BY YEAR(order_date)
                ORDER BY order_year ASC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn doanh thu theo năm: " . $this->conn->error);
        }
        $labels = [];
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['order_year'];
            $data[] = floatval($row['total_revenue']);
        }
        $result->free();
        return ['labels' => $labels, 'data' => $data];
    }

    // Thống kê hàng tồn kho
    public function getInventory() {
        $sql = "SELECT p.id, p.name, COALESCE(i.quantity, 0) AS quantity
                FROM product p
                LEFT JOIN inventory i ON p.id = i.product_id";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn tồn kho: " . $this->conn->error);
        }
        $inventory = [];
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }
        $result->free();
        return $inventory;
    }

    // Lấy danh sách năm
    public function getYears() {
        $sql = "SELECT DISTINCT YEAR(order_date) AS year FROM `order` ORDER BY year DESC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn danh sách năm: " . $this->conn->error);
        }
        $years = [];
        while ($row = $result->fetch_assoc()) {
            $years[] = $row['year'];
        }
        $result->free();
        return $years;
    }

    // Lợi nhuận theo năm
    public function getProfitByYear() {
        $sql = "SELECT YEAR(o.order_date) AS year, SUM((od.unit_price - COALESCE(p.cost_price, 0)) * od.quantity) AS total_profit
                FROM `order` o
                JOIN `order_detail` od ON o.id = od.order_id
                JOIN `product` p ON od.product_id = p.id
                WHERE o.status = 'completed'
                GROUP BY YEAR(o.order_date)
                ORDER BY year DESC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn lợi nhuận theo năm: " . $this->conn->error);
        }
        $profits = [];
        while ($row = $result->fetch_assoc()) {
            $profits[] = $row;
        }
        $result->free();
        return $profits;
    }

    // Lợi nhuận theo tháng
    public function getProfitByMonth($year) {
        $sql = "SELECT YEAR(o.order_date) AS year, MONTH(o.order_date) AS month, SUM((od.unit_price - COALESCE(p.cost_price, 0)) * od.quantity) AS total_profit
                FROM `order` o
                JOIN `order_detail` od ON o.id = od.order_id
                JOIN `product` p ON od.product_id = p.id
                WHERE o.status = 'completed' AND YEAR(o.order_date) = ?
                GROUP BY YEAR(o.order_date), MONTH(o.order_date)
                ORDER BY year DESC, month DESC";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn lợi nhuận theo tháng: " . $this->conn->error);
        }
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $profits = [];
        while ($row = $result->fetch_assoc()) {
            $profits[] = $row;
        }
        $result->free();
        $stmt->close();
        return $profits;
    }

    // Lợi nhuận theo ngày
    public function getProfitByDay($year, $month) {
        $sql = "SELECT DATE(o.order_date) AS order_date, SUM((od.unit_price - COALESCE(p.cost_price, 0)) * od.quantity) AS total_profit
                FROM `order` o
                JOIN `order_detail` od ON o.id = od.order_id
                JOIN `product` p ON od.product_id = p.id
                WHERE o.status = 'completed' AND YEAR(o.order_date) = ? AND MONTH(o.order_date) = ?
                GROUP BY DATE(o.order_date)
                ORDER BY order_date DESC";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn lợi nhuận theo ngày: " . $this->conn->error);
        }
        $stmt->bind_param('ii', $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();
        $profits = [];
        while ($row = $result->fetch_assoc()) {
            $profits[] = $row;
        }
        $result->free();
        $stmt->close();
        return $profits;
    }

    // Đóng kết nối
    public function close() {
        $this->conn->close();
    }
}
?>