document.addEventListener("DOMContentLoaded", function () {
    // Xử lý sidebar
    let sidebarToggle = document.getElementById("sidebarToggle");
    let sidebar = document.getElementById("sidebar");
    let main = document.getElementById("main");

    if (sidebarToggle && sidebar && main) {
        sidebarToggle.addEventListener("click", function () {
            main.classList.toggle("collapsed");
            sidebar.classList.toggle("collapsed");
            this.innerHTML = sidebar.classList.contains("collapsed")
                ? '<i class="fa fa-arrow-right"></i>'
                : '<i class="fa fa-arrow-left"></i>';

            if (sidebar.classList.contains("collapsed")) {
                document.querySelectorAll("#sidebar ul li.active").forEach((li) => {
                    li.classList.remove("active");
                    let menu = li.querySelector(".dropdown-menu");
                    if (menu) {
                        menu.style.maxHeight = "0px";
                    }
                });
            }
        });
    }

    document.querySelectorAll("#sidebar .has-dropdown > a").forEach((menu) => {
        menu.addEventListener("click", function (event) {
            console.log("Dropdown clicked:", menu); // Debug
            event.preventDefault();

            // Kiểm tra trạng thái sidebar
            const sidebar = document.getElementById("sidebar");
            if (sidebar.classList.contains("collapsed")) {
                console.log("Sidebar is collapsed, dropdown will not open."); // Debug
                return; // Không mở dropdown nếu sidebar thu gọn
            }

            let parentLi = this.parentElement;
            let dropdownMenu = parentLi.querySelector(".sidebar-dropdown-menu");

            if (dropdownMenu) {
                let isActive = parentLi.classList.contains("active");

                // Đóng tất cả dropdown trong sidebar
                document.querySelectorAll("#sidebar .has-dropdown").forEach((li) => {
                    li.classList.remove("active");
                    let menu = li.querySelector(".sidebar-dropdown-menu");
                    if (menu) {
                        menu.style.maxHeight = "0px";
                    }
                });

                // Mở/đóng dropdown hiện tại
                if (!isActive) {
                    parentLi.classList.add("active");
                    dropdownMenu.style.maxHeight = dropdownMenu.scrollHeight + "px";
                } else {
                    parentLi.classList.remove("active");
                    dropdownMenu.style.maxHeight = "0px";
                }
            } else {
                console.log("Dropdown menu not found!"); // Debug
            }
        });
    });

    // Xử lý biểu đồ doanh thu
    let currentMonth = new Date().getMonth() + 1;
    const monthTitle = document.getElementById("month-title");
    if (monthTitle) {
        monthTitle.innerText = `Kết quả kinh doanh tháng ${currentMonth}`;
    }

    const revenueChartElement = document.getElementById('revenueChart');
    if (revenueChartElement) {
        const ctx = revenueChartElement.getContext('2d');
        const today = new Date();
        const currentYear = today.getFullYear();
        const currentMonthIndex = today.getMonth();

        function daysInMonth(year, month) {
            return new Date(year, month + 1, 0).getDate();
        }

        const last5Days = [];
        const prevMonthDates = [];
        const currentMonthData = [];
        const prevMonthData = [];
        const labels = [];

        let daysCollected = 0;
        let i = 0;
        while (daysCollected < 5) {
            const date = new Date(today);
            date.setDate(today.getDate() - i);
            const month = date.getMonth();
            if (month === currentMonthIndex) {
                const day = date.getDate();
                labels.push(day.toString());
                last5Days.push(date);
                currentMonthData.push(Math.floor(Math.random() * 10000));

                const prevMonth = month - 1 < 0 ? 11 : month - 1;
                const prevYear = month - 1 < 0 ? currentYear - 1 : currentYear;
                const prevDate = new Date(prevYear, prevMonth, day);

                if (prevDate.getMonth() === prevMonth) {
                    prevMonthDates.push(prevDate);
                    prevMonthData.push(Math.floor(Math.random() * 10000));
                } else {
                    prevMonthDates.push(null);
                    prevMonthData.push(0);
                }

                daysCollected++;
            }
            i++;
        }

        const revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Tháng này',
                        data: currentMonthData,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Tháng trước',
                        data: prevMonthData,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Doanh thu (₫)' } },
                    x: { title: { display: true, text: 'Ngày' } }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                const index = context[0].dataIndex;
                                const datasetIndex = context[0].datasetIndex;
                                let date = datasetIndex === 0 ? last5Days[index] : prevMonthDates[index];
                                return date ? date.toLocaleDateString('vi-VN') : 'Ngày không hợp lệ';
                            },
                            label: function(context) {
                                return `Doanh thu: ${context.parsed.y}₫`;
                            }
                        }
                    },
                    legend: { display: true, position: 'top' }
                }
            }
        });
    }

    // Xử lý chuyển đổi tab (nếu có)
    const tabs = document.querySelectorAll('.tab');
    if (tabs.length > 0) {
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                const targetPane = document.getElementById(tabId);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
    }

    // Xử lý hiển thị/ẩn modal (nếu có)
    const createDiscountBtn = document.getElementById('createDiscountBtn');
    const createDiscountModal = document.getElementById('createDiscountModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');

    if (createDiscountBtn && createDiscountModal && closeModalBtn && cancelModalBtn) {
        createDiscountBtn.addEventListener('click', () => {
            createDiscountModal.style.display = 'block';
        });

        closeModalBtn.addEventListener('click', () => {
            createDiscountModal.style.display = 'none';
        });

        cancelModalBtn.addEventListener('click', () => {
            createDiscountModal.style.display = 'none';
        });

        // Xử lý chọn loại khuyến mại
        document.querySelectorAll('.discount-type-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.discount-type-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }
});