<?php
class ReportModel {
    private $host;
    private $username;
    private $password;
    private $shop_dbname;
    private $conn_common;
    private $conn_shop;

    public function __construct($host, $username, $password, $shop_dbname) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->shop_dbname = $shop_dbname;
        $this->connect();
    }

    private function connect() {
        $this->conn_common = new mysqli($this->host, $this->username, $this->password, 'fashion_shopp');
        if ($this->conn_common->connect_error) {
            throw new Exception("Lỗi kết nối đến fashion_shopp: " . $this->conn_common->connect_error);
        }
        $this->conn_common->set_charset("utf8mb4");

        $this->conn_shop = new mysqli($this->host, $this->username, $this->password, $this->shop_dbname);
        if ($this->conn_shop->connect_error) {
            throw new Exception("Lỗi kết nối đến cơ sở dữ liệu shop: " . $this->conn_shop->connect_error);
        }
        $this->conn_shop->set_charset("utf8mb4");
    }

    public function getShopName() {
        $sql = "SELECT name FROM shop WHERE id = 11";
        $result = $this->conn_common->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn tên cửa hàng: " . $this->conn_common->error);
        }
        $shop_name = ($result->num_rows > 0) ? $result->fetch_assoc()['name'] : 'Cửa hàng mặc định';
        $result->free();
        return $shop_name;
    }

    public function getDailyRevenue() {
        $sql = "SELECT DATE(order_date) AS order_day, SUM(total_price) AS total_revenue
                FROM `$this->shop_dbname`.`order`
                WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND status = 'completed'
                GROUP BY DATE(order_date)
                ORDER BY order_date ASC";
        $result = $this->conn_shop->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn doanh thu theo ngày: " . $this->conn_shop->error);
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

    public function getMonthlyRevenue($year = null) {
        if ($year === null) {
            $year = date('Y');
        }
        $data = array_fill(1, 12, 0);
        $labels = ['Tháng 1', 'Tháng 2', 'Tháng 3', 'Tháng 4', 'Tháng 5', 'Tháng 6', 'Tháng 7', 'Tháng 8', 'Tháng 9', 'Tháng 10', 'Tháng 11', 'Tháng 12'];
        $sql = "SELECT MONTH(order_date) AS order_month, SUM(total_price) AS total_revenue
                FROM `$this->shop_dbname`.`order`
                WHERE YEAR(order_date) = ? AND status = 'completed'
                GROUP BY MONTH(order_date)";
        $stmt = $this->conn_shop->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn doanh thu theo tháng: " . $this->conn_shop->error);
        }
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[$row['order_month']] = floatval($row['total_revenue']);
        }
        $result->free();
        $stmt->close();
        return ['labels' => $labels, 'data' => array_values($data)];
    }

    public function getYearlyRevenue() {
        $sql = "SELECT YEAR(order_date) AS order_year, SUM(total_price) AS total_revenue
                FROM `$this->shop_dbname`.`order`
                WHERE status = 'completed'
                GROUP BY YEAR(order_date)
                ORDER BY order_year ASC";
        $result = $this->conn_shop->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn doanh thu theo năm: " . $this->conn_shop->error);
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

    public function getInventory() {
        $sql = "SELECT p.id, p.name, COALESCE(i.quantity, 0) AS quantity
                FROM `$this->shop_dbname`.product p
                LEFT JOIN `$this->shop_dbname`.inventory i ON p.id = i.product_id
                WHERE i.shop_id = 11 OR i.shop_id IS NULL";
        $result = $this->conn_shop->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn tồn kho: " . $this->conn_shop->error);
        }
        $inventory = [];
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }
        $result->free();
        return $inventory;
    }

    public function getYears() {
        $sql = "SELECT DISTINCT YEAR(order_date) AS year FROM `$this->shop_dbname`.`order` ORDER BY year DESC";
        $result = $this->conn_shop->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn danh sách năm: " . $this->conn_shop->error);
        }
        $years = [];
        while ($row = $result->fetch_assoc()) {
            $years[] = $row['year'];
        }
        $result->free();
        return $years;
    }

    public function getProfitByYear() {
        $sql = "SELECT YEAR(o.order_date) AS year, SUM((od.unit_price - od.discount - COALESCE(p.cost_price, 0)) * od.quantity) AS total_profit
                FROM `$this->shop_dbname`.`order` o
                JOIN `$this->shop_dbname`.order_detail od ON o.id = od.order_id
                JOIN `$this->shop_dbname`.product p ON od.product_id = p.id
                WHERE o.status = 'completed'
                GROUP BY YEAR(o.order_date)
                ORDER BY year DESC";
        $result = $this->conn_shop->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi truy vấn lợi nhuận theo năm: " . $this->conn_shop->error);
        }
        $profits = [];
        while ($row = $result->fetch_assoc()) {
            $profits[] = $row;
        }
        $result->free();
        return $profits;
    }

    public function getProfitByMonth($year) {
        $sql = "SELECT MONTH(o.order_date) AS month, SUM((od.unit_price - od.discount - COALESCE(p.cost_price, 0)) * od.quantity) AS total_profit
                FROM `$this->shop_dbname`.`order` o
                JOIN `$this->shop_dbname`.order_detail od ON o.id = od.order_id
                JOIN `$this->shop_dbname`.product p ON od.product_id = p.id
                WHERE o.status = 'completed' AND YEAR(o.order_date) = ?
                GROUP BY MONTH(o.order_date)
                ORDER BY month";
        $stmt = $this->conn_shop->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn lợi nhuận theo tháng: " . $this->conn_shop->error);
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

    public function getProfitByDay($year, $month) {
        $sql = "SELECT DATE(o.order_date) AS order_date, SUM((od.unit_price - od.discount - COALESCE(p.cost_price, 0)) * od.quantity) AS total_profit
                FROM `$this->shop_dbname`.`order` o
                JOIN `$this->shop_dbname`.order_detail od ON o.id = od.order_id
                JOIN `$this->shop_dbname`.product p ON od.product_id = p.id
                WHERE o.status = 'completed' AND YEAR(o.order_date) = ? AND MONTH(o.order_date) = ?
                GROUP BY DATE(o.order_date)
                ORDER BY order_date";
        $stmt = $this->conn_shop->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Lỗi chuẩn bị truy vấn lợi nhuận theo ngày: " . $this->conn_shop->error);
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

    public function close() {
        if ($this->conn_common) {
            $this->conn_common->close();
        }
        if ($this->conn_shop) {
            $this->conn_shop->close();
        }
    }
}
?>