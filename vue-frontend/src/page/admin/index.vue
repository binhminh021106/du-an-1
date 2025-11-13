<script setup>
import { ref, computed, onMounted } from 'vue';
import apiService from '../../apiService.js';
import { useRouter } from 'vue-router';

// Giả định VueApexCharts được đăng ký toàn cục trong main.js
// import VueApexCharts from 'vue3-apexcharts';

// --- STATE QUẢN LÝ ---
const API_URL = import.meta.env.VITE_API_BASE_URL;
const router = useRouter();

// State lưu trữ dữ liệu
const orders = ref([]);
const products = ref([]);
const customers = ref([]);
const reviews = ref([]); // Thêm state cho reviews
const isLoading = ref(true);

// --- TẢI DỮ LIỆU ---
onMounted(() => {
  fetchDashboardData();
});

async function fetchDashboardData() {
  isLoading.value = true;
  try {
    // Tải song song các nguồn dữ liệu
    const [orderRes, productRes, customerRes, reviewRes] = await Promise.all([ // Thêm reviewRes
      apiService.get(`/orders?_sort=id&_order=desc`),
      apiService.get(`/products`),
      // THAY ĐỔI: Gọi đến /users?role=user thay vì /account_user
      apiService.get(`/users?role=user&_sort=id&_order=desc`),
      // Lấy 5 đánh giá mới nhất, expand product và user
      apiService.get(`/reviews?_sort=id&_order=desc&_limit=5&_expand=product&_expand=user`)
    ]);

    orders.value = orderRes.data;
    products.value = productRes.data;
    customers.value = customerRes.data;
    reviews.value = reviewRes.data; // Gán dữ liệu reviews

  } catch (error) {
    console.error("Lỗi khi tải dữ liệu Dashboard:", error);
    // Có thể thêm thông báo lỗi ở đây
  } finally {
    isLoading.value = false;
  }
}

// --- HÀM HELPER ---
const formatCurrency = (value) => {
  if (!value) return '0 đ';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};

const getStatusBadge = (status) => {
  switch (status) {
    case 'pending': return { class: 'text-bg-warning', text: 'Chờ xử lý' };
    case 'approved': return { class: 'text-bg-info', text: 'Đã duyệt' };
    case 'shipping': return { class: 'text-bg-primary', text: 'Đang giao' };
    case 'completed': return { class: 'text-bg-success', text: 'Hoàn thành' };
    case 'cancelled': return { class: 'text-bg-danger', text: 'Đã hủy' };
    case 'returning': return { class: 'text-bg-secondary', text: 'Đang hoàn' };
    case 'returned': return { class: 'text-bg-dark', text: 'Đã hoàn' };
    default: return { class: 'text-bg-light', text: status };
  }
};

// Hàm helper render sao (mới)
const renderStars = (rating) => {
  let stars = '';
  for (let i = 0; i < 5; i++) {
    stars += (i < rating) ? '★' : '☆';
  }
  return stars;
};

// --- COMPUTED PROPERTIES ---

// 1. Info Box: Tổng đơn hàng
const totalOrders = computed(() => orders.value.length);

// 2. Info Box: Tổng doanh thu (chỉ tính đơn "completed")
const totalRevenue = computed(() => {
  return orders.value
    .filter(order => order.status === 'completed')
    .reduce((acc, order) => acc + (order.totalAmount || 0), 0);
});

// 3. Info Box: Tổng sản phẩm
const totalProducts = computed(() => products.value.length);

// 4. Info Box: Tổng khách hàng
const totalCustomers = computed(() => customers.value.length);

// 5. Bảng: Đơn hàng mới nhất
const latestOrders = computed(() => orders.value.slice(0, 7));

// 6. Danh sách: Người dùng mới nhất (như hình ảnh)
const latestCustomers = computed(() => customers.value.slice(0, 8));

// 7. Danh sách: Đánh giá mới nhất (mới)
const latestReviews = computed(() => reviews.value);


// --- DỮ LIỆU BIỂU ĐỒ (Giữ nguyên phần giao diện) ---

// Biểu đồ Sales (Giữ nguyên dữ liệu mẫu vì cần logic backend phức tạp hơn)
const salesChartSeries = ref([
  { name: "Doanh thu", data: [28, 48, 40, 19, 86, 27, 90] },
  { name: "Đơn hàng", data: [65, 59, 80, 81, 56, 55, 40] }
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
      "2025-01-01", "2025-02-01", "2025-03-01", "2025-04-01",
      "2025-05-01", "2025-06-01", "2025-07-01"
    ]
  },
  tooltip: { x: { format: "MMMM yyyy" } }
});

// Biểu đồ Pie (Tình trạng đơn hàng - Dữ liệu thực)
const orderStatusChart = computed(() => {
  const statusCounts = {
    pending: 0,
    approved: 0,
    shipping: 0,
    completed: 0,
    cancelled: 0,
    returning: 0,
    returned: 0
  };
  const statusLabels = {
    pending: 'Chờ xử lý',
    approved: 'Đã duyệt',
    shipping: 'Đang giao',
    completed: 'Hoàn thành',
    cancelled: 'Đã hủy',
    returning: 'Đang hoàn',
    returned: 'Đã hoàn'
  };

  for (const order of orders.value) {
    if (order.status in statusCounts) {
      statusCounts[order.status]++;
    }
  }

  const labels = Object.keys(statusCounts).map(key => statusLabels[key]);
  const series = Object.values(statusCounts);

  // Lọc ra các series > 0 và label tương ứng
  const activeLabels = [];
  const activeSeries = [];
  series.forEach((count, index) => {
    if (count > 0) {
      activeSeries.push(count);
      activeLabels.push(labels[index]);
    }
  });

  return {
    series: activeSeries,
    options: {
      chart: { type: 'donut' },
      labels: activeLabels,
      colors: ['#ffc107', '#0dcaf0', '#0d6efd', '#198754', '#dc3545', '#6c757d', '#212529'],
      dataLabels: {
        enabled: true,
        formatter: (val, opts) => opts.w.globals.labels[opts.seriesIndex]
      },
      legend: { position: 'bottom' }
    }
  };
});
</script>

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
              <li class="breadcrumb-item"><router-link to="/admin">Trang Chủ</router-link></li>
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
        <!-- HÀNG 1: INFO BOXES (DỮ LIỆU THẬT) -->
        <div class="row">
          <!-- Tổng Đơn Hàng -->
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon text-bg-primary shadow-sm">
                <i class="fa-solid fa-file-invoice-dollar"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Tổng Đơn Hàng</span>
                <span class="info-box-number">{{ isLoading ? '...' : totalOrders }}</span>
              </div>
            </div>
          </div>
          <!-- Tổng Doanh Thu -->
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon text-bg-success shadow-sm">
                <i class="bi bi-cash-stack"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Tổng Doanh Thu</span>
                <span class="info-box-number">{{ isLoading ? '...' : formatCurrency(totalRevenue) }}</span>
              </div>
            </div>
          </div>
          <!-- Tổng Sản Phẩm -->
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon text-bg-danger shadow-sm">
                <i class="bi bi-box-seam-fill"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Tổng Sản Phẩm</span>
                <span class="info-box-number">{{ isLoading ? '...' : totalProducts }}</span>
              </div>
            </div>
          </div>
          <!-- Tổng Khách Hàng -->
          <div class="col-12 col-sm-6 col-md-3">
            <div class="info-box">
              <span class="info-box-icon text-bg-warning shadow-sm">
                <i class="bi bi-people-fill"></i>
              </span>
              <div class="info-box-content">
                <span class="info-box-text">Tổng Khách Hàng</span>
                <span class="info-box-number">{{ isLoading ? '...' : totalCustomers }}</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- HÀNG 2: BIỂU ĐỒ CHÍNH (Full-width) -->
        <div class="row">
          <div class="col-md-12">
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="card-title">Báo Cáo Tóm Tắt Hàng Tháng</h5>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-8">
                    <p class="text-center">
                      <strong>Doanh Số (Dữ liệu mẫu): 1 Thg 1 - 30 Thg 7, 2025</strong>
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
                      <strong>Hoàn Thành Mục Tiêu (Dữ liệu mẫu)</strong>
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
                      <span class="text-uppercase">TỔNG DOANH THU (MẪU)</span>
                    </div>
                  </div>
                  <div class="col-md-3 col-6">
                    <div class="text-center border-end">
                      <span class="text-info"><i class="bi bi-caret-left-fill"></i> 0%</span>
                      <h5 class="fw-bold mb-0">$10,390.90</h5>
                      <span class="text-uppercase">TỔNG CHI PHÍ (MẪU)</span>
                    </div>
                  </div>
                  <div class="col-md-3 col-6">
                    <div class="text-center border-end">
                      <span class="text-success"><i class="bi bi-caret-up-fill"></i> 20%</span>
                      <h5 class="fw-bold mb-0">$24,813.53</h5>
                      <span class="text-uppercase">TỔNG LỢI NHUẬN (MẪU)</span>
                    </div>
                  </div>
                  <div class="col-md-3 col-6">
                    <div class="text-center">
                      <span class="text-danger"><i class="bi bi-caret-down-fill"></i> 18%</span>
                      <h5 class="fw-bold mb-0">1200</h5>
                      <span class="text-uppercase">MỤC TIÊU (MẪU)</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- HÀNG 3: ĐƠN HÀNG MỚI, ĐÁNH GIÁ MỚI & THÀNH VIÊN MỚI -->
        <div class="row">
          <!-- Cột bên trái (8) -->
          <div class="col-md-8">
            <!-- Đơn hàng mới nhất -->
            <div class="card mb-4">
              <div class="card-header">
                <h3 class="card-title">Đơn Hàng Mới Nhất</h3>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table m-0">
                    <thead>
                      <tr>
                        <th>Mã ĐH</th>
                        <th>Khách Hàng</th>
                        <th>Trạng Thái</th>
                        <th>Tổng Tiền</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="isLoading">
                        <td colspan="4" class="text-center">Đang tải...</td>
                      </tr>
                       <tr v-else-if="latestOrders.length === 0">
                        <td colspan="4" class="text-center">Chưa có đơn hàng nào.</td>
                      </tr>
                      <tr v-for="order in latestOrders" :key="order.id">
                        <td><router-link :to="{ name: 'admin-orders' }">#{{ order.id }}</router-link></td>
                        <td>{{ order.customerName }}</td>
                        <td><span :class="getStatusBadge(order.status).class" class="badge">{{ getStatusBadge(order.status).text }}</span></td>
                        <td>{{ formatCurrency(order.totalAmount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="card-footer clearfix">
                <router-link :to="{ name: 'admin-orders' }" class="btn btn-sm btn-secondary float-end">
                  Xem Tất Cả Đơn Hàng
                </router-link>
              </div>
            </div>

            <!-- Đánh giá mới nhất (MỚI) -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Đánh Giá Mới Nhất</h3>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table m-0">
                    <thead>
                      <tr>
                        <th>Sản phẩm</th>
                        <th>Người gửi</th>
                        <th>Đánh giá</th>
                        <th>Nội dung</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="isLoading">
                        <td colspan="4" class="text-center">Đang tải...</td>
                      </tr>
                       <tr v-else-if="latestReviews.length === 0">
                        <td colspan="4" class="text-center">Chưa có đánh giá nào.</td>
                      </tr>
                      <tr v-for="review in latestReviews" :key="review.id">
                        <td>{{ review.product?.name || 'N/A' }}</td>
                        <td>{{ review.user?.username || 'N/A' }}</td>
                        <td>
                          <span class="text-warning">{{ renderStars(review.rating) }}</span>
                        </td>
                        <td :title="review.content">
                          {{ review.content.length > 30 ? review.content.substring(0, 30) + '...' : review.content }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="card-footer clearfix">
                <router-link :to="{ name: 'admin-reviews' }" class="btn btn-sm btn-secondary float-end">
                  Xem Tất Cả Đánh Giá
                </router-link>
              </div>
            </div>
            
          </div>
          
          <!-- Cột bên phải (4) -->
          <div class="col-md-4">
            <!-- Biểu đồ tình trạng đơn hàng -->
            <div class="card mb-4">
              <div class="card-header">
                <h3 class="card-title">Tình Trạng Đơn Hàng</h3>
              </div>
              <div class="card-body">
                <VueApexCharts
                  type="donut"
                  :options="orderStatusChart.options"
                  :series="orderStatusChart.series"
                />
              </div>
            </div>

            <!-- Thành viên mới -->
            <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Thành Viên Mới Nhất</h3>
                </div>
                <div class="card-body p-0">
                  <ul class="list-group list-group-flush">
                    <li v-if="isLoading" class="list-group-item text-center">Đang tải...</li>
                    <li v-else-if="latestCustomers.length === 0" class="list-group-item text-center">Chưa có khách hàng nào.</li>
                    <li v-for="customer in latestCustomers" :key="customer.id" class="list-group-item d-flex align-items-center">
                       <img :src="customer.avatar || `https://placehold.co/40x40/EBF4FF/1D62F0?text=${customer.name.charAt(0).toUpperCase()}`"
                           class="rounded-circle me-3" alt="Avatar"
                           style="width: 40px; height: 40px; object-fit: cover;">
                      <span>{{ customer.name }}</span>
                    </li>
                  </ul>
                </div>
                <div class="card-footer text-center">
                  <router-link :to="{ name: 'admin-userAccount' }" class="text-decoration-none">
                    Xem tất cả khách hàng
                  </router-link>
                </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.list-group-item {
  padding: 0.75rem 1rem;
}
.table .text-warning {
  color: #ffc107 !important;
  font-size: 1.1rem;
  letter-spacing: 1px;
}
</style>