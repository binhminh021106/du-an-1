<template>
  <div>
    <div class="app-content-header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-sm-6">
            <h3 class="mb-0">Admin ThinkHub</h3>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
              <li class="breadcrumb-item"><a href="#">Trang Chủ</a></li>
              <li class="breadcrumb-item active" aria-current="page">
                Admin ThinkHub
              </li>
            </ol>
          </div>
        </div>
      </div>
    </div>
    
    <div class="app-content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon text-bg-primary shadow-sm">
                <i class="bi bi-gear-fill"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Lưu Lượng CPU</span>
                <span class="info-box-number">10<small>%</small></span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon text-bg-danger shadow-sm">
                <i class="bi bi-hand-thumbs-up-fill"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Lượt Thích</span>
                <span class="info-box-number">41,410</span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon text-bg-success shadow-sm">
                <i class="bi bi-cart-fill"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Doanh Số</span>
                <span class="info-box-number">760</span>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon text-bg-warning shadow-sm">
                <i class="bi bi-people-fill"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Thành Viên Mới</span>
                <span class="info-box-number">2,000</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="row">
          <div class="col-md-8">
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="card-title">Báo Cáo Tóm Tắt Hàng Tháng</h5>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-8">
                    <p class="text-center">
                      <strong>Doanh Số: 1 Thg 1, 2023 - 30 Thg 7, 2023</strong>
                    </p>
                    <VueApexCharts
                      type="area"
                      height="180"
                      :options="salesChartOptions"
                      :series="salesChartSeries"
                    />
                  </div>
                  <div class="col-md-4">
                    <p class="text-center">
                      <strong>Hoàn Thành Mục Tiêu</strong>
                    </p>
                    <div class="progress-group">
                      Thêm Sản Phẩm vào Giỏ
                      <span class="float-end"><b>160</b>/200</span>
                      <div class="progress progress-sm">
                        <div class="progress-bar text-bg-primary" style="width: 80%"></div>
                      </div>
                    </div>
                    <div class="progress-group">
                      Hoàn Tất Mua Hàng
                      <span class="float-end"><b>310</b>/400</span>
                      <div class="progress progress-sm">
                        <div class="progress-bar text-bg-danger" style="width: 75%"></div>
                      </div>
                    </div>
                    <div class="progress-group">
                      <span class="progress-text">Truy Cập Trang Cao Cấp</span>
                      <span class="float-end"><b>480</b>/800</span>
                      <div class="progress progress-sm">
                        <div class="progress-bar text-bg-success" style="width: 60%"></div>
                      </div>
                    </div>
                    <div class="progress-group">
                      Gửi Yêu Cầu
                      <span class="float-end"><b>250</b>/500</span>
                      <div class="progress progress-sm">
                        <div class="progress-bar text-bg-warning" style="width: 50%"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card-footer">
                <div class="row">
                  <div class="col-md-3 col-6">
                    <div class="text-center border-end">
                      <span class="text-success"><i class="bi bi-caret-up-fill"></i> 17%</span>
                      <h5 class="fw-bold mb-0">$35,210.43</h5>
                      <span class="text-uppercase">TỔNG DOANH THU</span>
                    </div>
                  </div>
                  <div class="col-md-3 col-6">
                    <div class="text-center border-end">
                      <span class="text-info"><i class="bi bi-caret-left-fill"></i> 0%</span>
                      <h5 class="fw-bold mb-0">$10,390.90</h5>
                      <span class="text-uppercase">TỔNG CHI PHÍ</span>
                    </div>
                  </div>
                  <div class="col-md-3 col-6">
                    <div class="text-center border-end">
                      <span class="text-success"><i class="bi bi-caret-up-fill"></i> 20%</span>
                      <h5 class="fw-bold mb-0">$24,813.53</h5>
                      <span class="text-uppercase">TỔNG LỢI NHUẬN</span>
                    </div>
                  </div>
                  <div class="col-md-3 col-6">
                    <div class="text-center">
                      <span class="text-danger"><i class="bi bi-caret-down-fill"></i> 18%</span>
                      <h5 class="fw-bold mb-0">1200</h5>
                      <span class="text-uppercase">HOÀN THÀNH MỤC TIÊU</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Đơn Hàng Mới Nhất</h3>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table m-0">
                    <thead>
                      <tr>
                        <th>Mã ĐH</th>
                        <th>Sản Phẩm</th>
                        <th>Trạng Thái</th>
                        <th>Độ Phổ Biến</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="order in latestOrders" :key="order.id">
                        <td><a href="#">{{ order.id }}</a></td>
                        <td>{{ order.item }}</td>
                        <td><span :class="getStatusClass(order.status)">{{ mapStatus(order.status) }}</span></td>
                        <td>
                          <VueApexCharts
                            type="line"
                            height="30"
                            width="150"
                            :options="sparklineOptions"
                            :series="[{ data: order.sparklineData }]"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="card-footer clearfix">
                <a href="#" class="btn btn-sm btn-primary float-start">Đặt Hàng Mới</a>
                <a href="#" class="btn btn-sm btn-secondary float-end">Xem Tất Cả Đơn Hàng</a>
              </div>
            </div>
          </div>
          
          <div class="col-md-4">
            <div class="info-box mb-3 text-bg-warning">
              <span class="info-box-icon"><i class="bi bi-tag-fill"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Kho Hàng</span>
                <span class="info-box-number">5,200</span>
              </div>
            </div>
            <div class="info-box mb-3 text-bg-success">
              <span class="info-box-icon"><i class="bi bi-heart-fill"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Lượt Đề Cập</span>
                <span class="info-box-number">92,050</span>
              </div>
            </div>
            <div class="info-box mb-3 text-bg-danger">
              <span class="info-box-icon"><i class="bi bi-cloud-download"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Lượt Tải Xuống</span>
                <span class="info-box-number">114,381</span>
              </div>
            </div>
            <div class="info-box mb-3 text-bg-info">
              <span class="info-box-icon"><i class="bi bi-chat-fill"></i></span>
              <div class="info-box-content">
                <span class="info-box-text">Tin Nhắn Trực Tiếp</span>
                <span class="info-box-number">163,921</span>
              </div>
            </div>

            <div class="card mb-4">
              <div class="card-header">
                <h3 class="card-title">Sử Dụng Trình Duyệt</h3>
              </div>
              <div class="card-body">
                <VueApexCharts
                  type="donut"
                  :options="pieChartOptions"
                  :series="pieChartSeries"
                />
              </div>
              <div class="card-footer p-0">
                <ul class="nav nav-pills flex-column">
                  <li v-for="item in browserUsage" :key="item.name" class="nav-item">
                    <a href="#" class="nav-link">
                      {{ item.name }}
                      <span :class="`float-end text-${item.color}`">
                        <i :class="`bi bi-arrow-${item.arrow} fs-7`"></i>
                        {{ item.value }}%
                      </span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

// Biểu đồ Sales
const salesChartSeries = ref([
  { name: "Hàng Kỹ Thuật Số", data: [28, 48, 40, 19, 86, 27, 90] },
  { name: "Thiết Bị Điện Tử", data: [65, 59, 80, 81, 56, 55, 40] }
]);
const salesChartOptions = ref({
  chart: { height: 180, type: "area", toolbar: { show: false } },
  legend: { show: false },
  colors: ["#0d6efd", "#20c997"],
  dataLabels: { enabled: false },
  stroke: { curve: "smooth" },
  xaxis: {
    type: "datetime",
    categories: [
      "2023-01-01", "2023-02-01", "2023-03-01", "2023-04-01",
      "2023-05-01", "2023-06-01", "2023-07-01"
    ]
  },
  tooltip: { x: { format: "MMMM yyyy" } }
});

// Biểu đồ Trình duyệt (Pie)
const pieChartSeries = ref([700, 500, 400, 600, 300, 100]);
const pieChartOptions = ref({
  chart: { type: "donut" },
  labels: ["Chrome", "Edge", "FireFox", "Safari", "Opera", "IE"], 
  dataLabels: { enabled: false },
  colors: ["#0d6efd", "#20c997", "#ffc107", "#d63384", "#6f42c1", "#adb5bd"]
});

// Danh sách Trình duyệt
const browserUsage = ref([
  { name: 'Hoa Kỳ', value: 12, color: 'danger', arrow: 'down' },
  { name: 'Ấn Độ', value: 4, color: 'success', arrow: 'up' },
  { name: 'Trung Quốc', value: 0, color: 'info', arrow: 'left' }
]);

// Bảng Đơn hàng mới nhất
const latestOrders = ref([
  { id: 'OR9842', item: 'Call of Duty IV', status: 'Shipped', sparklineData: [25, 66, 41, 89, 63, 25, 44, 12, 36, 9, 54] },
  { id: 'OR1848', item: 'TV Thông Minh Samsung', status: 'Pending', sparklineData: [12, 56, 21, 39, 73, 45, 64, 52, 36, 59, 44] },
  { id: 'OR7429', item: 'iPhone 6 Plus', status: 'Delivered', sparklineData: [15, 46, 21, 59, 33, 15, 34, 42, 56, 19, 64] },
  { id: 'OR7429', item: 'TV Thông Minh Samsung', status: 'Processing', sparklineData: [30, 56, 31, 69, 43, 35, 24, 32, 46, 29, 64] },
  { id: 'OR1848', item: 'TV Thông Minh Samsung', status: 'Pending', sparklineData: [20, 76, 51, 79, 53, 35, 54, 22, 36, 49, 64] },
  { id: 'OR7429', item: 'iPhone 6 Plus', status: 'Delivered', sparklineData: [5, 36, 11, 69, 23, 15, 14, 42, 26, 19, 44] },
  { id: 'OR9842', item: 'Call of Duty IV', status: 'Shipped', sparklineData: [12, 56, 21, 39, 73, 45, 64, 52, 36, 59, 74] }
]);

// Cấu hình Sparklines
const sparklineOptions = ref({
  chart: { type: "line", width: 150, height: 30, sparkline: { enabled: true } },
  colors: ["var(--bs-primary)"],
  stroke: { width: 2 },
  tooltip: {
    fixed: { enabled: false },
    x: { show: false },
    y: { title: { formatter: () => '' } },
    marker: { show: false }
  }
});

// Helpers
const mapStatus = (status) => {
  switch (status) {
    case 'Shipped': return 'Đã Giao';
    case 'Pending': return 'Đang Chờ';
    case 'Delivered': return 'Đã Giao';
    case 'Processing': return 'Đang Xử Lý';
    default: return 'Khác';
  }
};
const getStatusClass = (status) => {
  switch (status) {
    case 'Shipped': return 'badge text-bg-success';
    case 'Pending': return 'badge text-bg-warning';
    case 'Delivered': return 'badge text-bg-danger';
    case 'Processing': return 'badge text-bg-info';
    default: return 'badge text-bg-secondary';
  }
};
</script>

<style scoped>
</style>