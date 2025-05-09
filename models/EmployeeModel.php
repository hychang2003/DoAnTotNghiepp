<?php
class EmployeeModel {
    private $host;
    private $username;
    private $password;
    private $shop_dbname;
    private $conn;
    private $conn_common;

    public function __construct($host, $username, $password, $shop_dbname) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->shop_dbname = $shop_dbname;
        error_log("Khởi tạo EmployeeModel: host=$host, username=$username, shop_dbname=$shop_dbname");
        $this->connect();
    }

    private function connect() {
        error_log("Thử kết nối cơ sở dữ liệu: {$this->host}, {$this->shop_dbname}");
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->shop_dbname);
        if ($this->conn->connect_error) {
            error_log("Lỗi kết nối cơ sở dữ liệu: " . $this->conn->connect_error);
            throw new Exception("Lỗi kết nối cơ sở dữ liệu: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
        error_log("Kết nối cơ sở dữ liệu thành công: {$this->shop_dbname}");

        $this->conn_common = new mysqli($this->host, $this->username, $this->password, 'fashion_shopp');
        if ($this->conn_common->connect_error) {
            error_log("Lỗi kết nối đến fashion_shopp: " . $this->conn_common->connect_error);
            throw new Exception("Lỗi kết nối đến fashion_shopp: " . $this->conn_common->connect_error);
        }
        $this->conn_common->set_charset("utf8mb4");
        error_log("Kết nối fashion_shopp thành công.");
    }

    private function getShopId() {
        $sql = "SELECT id FROM shop WHERE db_name = ?";
        $stmt = $this->conn_common->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn shop_id: " . $this->conn_common->error);
            throw new Exception("Lỗi chuẩn bị truy vấn shop_id: " . $this->conn_common->error);
        }
        $stmt->bind_param('s', $this->shop_dbname);
        $stmt->execute();
        $result = $stmt->get_result();
        $shop_id = $result->num_rows > 0 ? $result->fetch_assoc()['id'] : 1;
        $stmt->close();
        error_log("Lấy shop_id thành công: shop_id=$shop_id cho db_name={$this->shop_dbname}");
        return $shop_id;
    }

    public function getEmployees() {
        error_log("Lấy danh sách nhân viên từ bảng users trong {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }
        $sql = "SELECT id, username, role, name, email, phone_number AS phone FROM `$this->shop_dbname`.users WHERE role IN ('employee', 'admin') ORDER BY username ASC";
        $result = $this->conn->query($sql);
        if ($result === false) {
            error_log("Lỗi truy vấn danh sách nhân viên: " . $this->conn->error);
            throw new Exception("Lỗi truy vấn danh sách nhân viên: " . $this->conn->error);
        }
        $employees = [];
        while ($row = $result->fetch_assoc()) {
            $employees[] = $row;
        }
        $result->free();
        error_log("Lấy danh sách nhân viên thành công: " . count($employees) . " nhân viên.");
        return $employees;
    }

    public function getEmployeeById($employee_id) {
        error_log("Lấy thông tin nhân viên ID: $employee_id từ {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }
        $sql = "SELECT id, username, role, name, email, phone_number AS phone, password FROM `$this->shop_dbname`.users WHERE id = ? AND role IN ('employee', 'admin')";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn nhân viên ID $employee_id: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn nhân viên theo ID: " . $this->conn->error);
        }
        $stmt->bind_param('i', $employee_id);
        if (!$stmt->execute()) {
            error_log("Lỗi thực thi truy vấn nhân viên ID $employee_id: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn nhân viên theo ID: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        $stmt->close();
        error_log($employee ? "Lấy thông tin nhân viên ID $employee_id thành công: " . print_r($employee, true) : "Không tìm thấy nhân viên ID $employee_id.");
        return $employee;
    }

    public function addEmployee($username, $password, $role, $name, $email, $phone) {
        error_log("Thêm nhân viên: username=$username, role=$role, name=$name, email=$email, phone=$phone trong {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }

        $sql = "SELECT id FROM `$this->shop_dbname`.users WHERE username = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn kiểm tra username $username: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra username.");
        }
        $stmt->bind_param('s', $username);
        if (!$stmt->execute()) {
            error_log("Lỗi thực thi truy vấn kiểm tra username $username: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn kiểm tra username.");
        }
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $stmt->close();
            error_log("Lỗi: Tên đăng nhập đã tồn tại: $username");
            throw new Exception("Tên đăng nhập đã tồn tại.");
        }
        $stmt->close();

        $sql = "INSERT INTO `$this->shop_dbname`.users (username, password, role, name, email, phone_number) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn thêm nhân viên: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn thêm nhân viên.");
        }
        $stmt->bind_param('ssssss', $username, $password, $role, $name, $email, $phone);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn thêm nhân viên: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn thêm nhân viên: " . $stmt->error);
        }
        $stmt->close();
        error_log("Thêm nhân viên $username thành công trong {$this->shop_dbname}.");
        return $result;
    }

    public function updateEmployee($employee_id, $role, $name, $email, $phone, $password = null) {
        error_log("Cập nhật nhân viên ID $employee_id: role=$role, name=$name, email=$email, phone=$phone trong {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }

        if ($password) {
            $sql = "UPDATE `$this->shop_dbname`.users SET role = ?, name = ?, email = ?, phone_number = ?, password = ? WHERE id = ? AND role IN ('employee', 'admin')";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                error_log("Lỗi chuẩn bị truy vấn cập nhật nhân viên ID $employee_id: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật nhân viên.");
            }
            $stmt->bind_param('sssssi', $role, $name, $email, $phone, $password, $employee_id);
        } else {
            $sql = "UPDATE `$this->shop_dbname`.users SET role = ?, name = ?, email = ?, phone_number = ? WHERE id = ? AND role IN ('employee', 'admin')";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                error_log("Lỗi chuẩn bị truy vấn cập nhật nhân viên ID $employee_id: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn cập nhật nhân viên.");
            }
            $stmt->bind_param('ssssi', $role, $name, $email, $phone, $employee_id);
        }

        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn cập nhật nhân viên ID $employee_id: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn cập nhật nhân viên: " . $stmt->error);
        }
        $stmt->close();
        error_log("Cập nhật nhân viên ID $employee_id thành công trong {$this->shop_dbname}.");
        return $result;
    }

    public function deleteEmployee($employee_id) {
        error_log("Xóa nhân viên ID: $employee_id từ {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }
        $sql = "DELETE FROM `$this->shop_dbname`.users WHERE id = ? AND role IN ('employee', 'admin')";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn xóa nhân viên ID $employee_id: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn xóa nhân viên.");
        }
        $stmt->bind_param('i', $employee_id);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn xóa nhân viên ID $employee_id: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn xóa nhân viên: " . $stmt->error);
        }
        error_log("Xóa nhân viên ID $employee_id thành công từ {$this->shop_dbname}.");
        $stmt->close();
        return $result;
    }

    public function getSalaryByEmployeeId($employee_id, $month) {
        error_log("Lấy thông tin lương nhân viên ID $employee_id cho tháng $month từ {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }
        $sql = "SELECT work_days, salary_per_day, total_salary, status, payment_date 
                FROM `$this->shop_dbname`.employee_salary 
                WHERE employee_id = ? AND month = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn lương nhân viên ID $employee_id: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn lương nhân viên: " . $this->conn->error);
        }
        $stmt->bind_param('is', $employee_id, $month);
        if (!$stmt->execute()) {
            error_log("Lỗi thực thi truy vấn lương nhân viên ID $employee_id: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn lương nhân viên: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $salary = $result->fetch_assoc();
        $stmt->close();

        if (!$salary) {
            error_log("Không tìm thấy bản ghi lương cho nhân viên ID $employee_id tháng $month. Tạo bản ghi mặc định.");
            $salary = [
                'work_days' => 0.0,
                'salary_per_day' => 0.00,
                'total_salary' => 0.00,
                'status' => 'unpaid',
                'payment_date' => null
            ];
            $sql = "INSERT INTO `$this->shop_dbname`.employee_salary (employee_id, month, work_days, salary_per_day, total_salary, status) 
                    VALUES (?, ?, 0.0, 0.00, 0.00, 'unpaid')";
            $stmt = $this->conn->prepare($sql);
            if ($stmt === false) {
                error_log("Lỗi chuẩn bị truy vấn chèn bản ghi lương mặc định ID $employee_id: " . $this->conn->error);
                throw new Exception("Lỗi chuẩn bị truy vấn chèn bản ghi lương mặc định: " . $this->conn->error);
            }
            $stmt->bind_param('is', $employee_id, $month);
            if (!$stmt->execute()) {
                error_log("Lỗi thực thi truy vấn chèn bản ghi lương mặc định ID $employee_id: " . $stmt->error);
                throw new Exception("Lỗi thực thi truy vấn chèn bản ghi lương mặc định: " . $stmt->error);
            }
            $stmt->close();
            error_log("Tạo bản ghi lương mặc định thành công cho ID $employee_id tháng $month.");
        } else {
            error_log("Lấy bản ghi lương thành công cho ID $employee_id tháng $month: " . print_r($salary, true));
        }
        return $salary;
    }

    public function updateSalary($employee_id, $month, $work_days, $salary_per_day, $total_salary) {
        error_log("Cập nhật lương nhân viên ID $employee_id, tháng $month: work_days=$work_days, salary_per_day=$salary_per_day, total_salary=$total_salary trong {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }

        $current_salary = $this->getSalaryByEmployeeId($employee_id, $month);
        error_log("Bản ghi lương hiện tại: " . print_r($current_salary, true));

        if ($current_salary['work_days'] == $work_days &&
            $current_salary['salary_per_day'] == $salary_per_day &&
            $current_salary['total_salary'] == $total_salary) {
            error_log("Không cần cập nhật: Dữ liệu lương không thay đổi cho ID $employee_id, tháng $month.");
            return 0;
        }

        $sql = "INSERT INTO `$this->shop_dbname`.employee_salary (employee_id, month, work_days, salary_per_day, total_salary, status) 
                VALUES (?, ?, ?, ?, ?, 'unpaid') 
                ON DUPLICATE KEY UPDATE work_days = ?, salary_per_day = ?, total_salary = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn cập nhật lương ID $employee_id: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn cập nhật lương: " . $this->conn->error);
        }
        $stmt->bind_param('isdddddd', $employee_id, $month, $work_days, $salary_per_day, $total_salary, $work_days, $salary_per_day, $total_salary);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn cập nhật lương ID $employee_id: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn cập nhật lương: " . $stmt->error);
        }
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        error_log("Cập nhật lương nhân viên ID $employee_id thành công. Affected rows: $affected_rows");
        if ($affected_rows == 0) {
            error_log("Cảnh báo: Không có bản ghi nào được cập nhật cho ID $employee_id, tháng $month. Kiểm tra khóa chính hoặc dữ liệu.");
        } else {
            $updated_salary = $this->getSalaryByEmployeeId($employee_id, $month);
            error_log("Bản ghi lương sau khi cập nhật: " . print_r($updated_salary, true));
        }
        return $affected_rows;
    }

    public function updatePaymentStatus($employee_id, $month, $status) {
        error_log("Cập nhật trạng thái lương nhân viên ID $employee_id, tháng $month: status=$status trong {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }

        $payment_date = ($status === 'paid') ? date('Y-m-d H:i:s') : null;
        $sql = "UPDATE `$this->shop_dbname`.employee_salary SET status = ?, payment_date = ? WHERE employee_id = ? AND month = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn cập nhật trạng thái lương ID $employee_id: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn cập nhật trạng thái lương: " . $this->conn->error);
        }
        $stmt->bind_param('ssis', $status, $payment_date, $employee_id, $month);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn cập nhật trạng thái lương ID $employee_id: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn cập nhật trạng thái lương: " . $stmt->error);
        }
        $stmt->close();
        error_log("Cập nhật trạng thái lương nhân viên ID $employee_id thành công trong {$this->shop_dbname}.");
        return $result;
    }

    public function hasAttendedToday($employee_id) {
        $today = date('Y-m-d');
        error_log("Kiểm tra chấm công hôm nay cho nhân viên ID $employee_id, ngày $today trong {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }
        $sql = "SELECT id FROM `$this->shop_dbname`.attendance WHERE employee_id = ? AND DATE(attendance_date) = ?";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn kiểm tra chấm công ID $employee_id: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn kiểm tra chấm công: " . $this->conn->error);
        }
        $stmt->bind_param('is', $employee_id, $today);
        if (!$stmt->execute()) {
            error_log("Lỗi thực thi truy vấn kiểm tra chấm công ID $employee_id: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn kiểm tra chấm công: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $hasAttended = $result->num_rows > 0;
        $stmt->close();
        error_log("Kết quả kiểm tra chấm công ID $employee_id: " . ($hasAttended ? "Đã chấm công" : "Chưa chấm công"));
        return $hasAttended;
    }

    public function recordAttendance($employee_id) {
        $month = date('Y-m');
        $today = date('Y-m-d H:i:s');
        error_log("Bắt đầu chấm công cho nhân viên ID $employee_id vào $today trong {$this->shop_dbname}");
        if (!$this->conn || $this->conn->connect_error) {
            error_log("Lỗi: Kết nối cơ sở dữ liệu không khả dụng: " . $this->conn->connect_error);
            throw new Exception("Kết nối cơ sở dữ liệu không khả dụng.");
        }

        error_log("Kiểm tra chấm công hôm nay cho ID $employee_id");
        if ($this->hasAttendedToday($employee_id)) {
            error_log("Lỗi chấm công: Nhân viên ID $employee_id đã chấm công hôm nay.");
            throw new Exception("Bạn đã chấm công hôm nay rồi!");
        }
        error_log("Chưa chấm công hôm nay, tiếp tục ghi chấm công.");

        error_log("Ghi chấm công vào bảng attendance cho ID $employee_id");
        $sql = "INSERT INTO `$this->shop_dbname`.attendance (employee_id, attendance_date) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            error_log("Lỗi chuẩn bị truy vấn chấm công ID $employee_id: " . $this->conn->error);
            throw new Exception("Lỗi chuẩn bị truy vấn chấm công: " . $this->conn->error);
        }
        $stmt->bind_param('is', $employee_id, $today);
        $result = $stmt->execute();
        if ($result === false) {
            error_log("Lỗi thực thi truy vấn chấm công ID $employee_id: " . $stmt->error);
            throw new Exception("Lỗi thực thi truy vấn chấm công: " . $stmt->error);
        }
        $stmt->close();
        error_log("Ghi chấm công thành công cho ID $employee_id vào $today");

        error_log("Lấy thông tin lương hiện tại cho ID $employee_id, tháng $month");
        $salary = $this->getSalaryByEmployeeId($employee_id, $month);
        error_log("Lương hiện tại: " . print_r($salary, true));
        $work_days = $salary['work_days'] + 1.0;
        $total_salary = $work_days * $salary['salary_per_day'];
        error_log("Cập nhật số ngày công: work_days=$work_days, total_salary=$total_salary");

        error_log("Gọi updateSalary cho ID $employee_id, tháng $month");
        $affected_rows = $this->updateSalary($employee_id, $month, $work_days, $salary['salary_per_day'], $total_salary);
        if ($affected_rows == 0) {
            error_log("Lỗi: Cập nhật lương thất bại cho ID $employee_id tháng $month: Không có bản ghi nào được cập nhật.");
            throw new Exception("Lỗi cập nhật số ngày công trong bảng lương: Không có bản ghi nào được cập nhật.");
        }
        error_log("Hoàn tất chấm công và cập nhật lương cho ID $employee_id. Affected rows: $affected_rows");
        return true;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
            error_log("Đóng kết nối cơ sở dữ liệu {$this->shop_dbname} thành công.");
        }
        if ($this->conn_common) {
            $this->conn_common->close();
            $this->conn_common = null;
            error_log("Đóng kết nối fashion_shopp thành công.");
        }
    }
}
?>