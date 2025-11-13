<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---
const isLoading = ref(true);
const isSaving = ref(false);

// State cho Modal Thêm/Sửa
const couponModalRef = ref(null);
const couponModalInstance = ref(null);

// State cho Modal Xem
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingCoupon = ref({});

// State cho dữ liệu
const allCoupons = ref([]); // Tải tất cả coupons
const searchQuery = ref(''); // State cho tìm kiếm

// State cho phân trang (cục bộ)
const localPagination = reactive({
  currentPage: 1,
  itemsPerPage: 10,
});

// State cho form
const couponForm = reactive({
  id: null,
  name: '',
  code: '',
  type: 'percent',
  value: 0,
  expiresAt: '',
  usageLimit: null,
  usageCount: 0,
  limitPerUser: 1
});

// --- COMPUTED ---

// Lọc coupons dựa trên tìm kiếm
const filteredCoupons = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return allCoupons.value;
  }
  return allCoupons.value.filter(coupon =>
    coupon.code.toLowerCase().includes(query) ||
    coupon.name.toLowerCase().includes(query)
  );
});

// Tính tổng số trang
const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredCoupons.value.length / localPagination.itemsPerPage));
});

// Lấy danh sách coupons đã phân trang
const paginatedCoupons = computed(() => {
  if (localPagination.currentPage > totalPages.value) {
    localPagination.currentPage = 1;
  }
  const start = (localPagination.currentPage - 1) * localPagination.itemsPerPage;
  const end = start + localPagination.itemsPerPage;
  return filteredCoupons.value.slice(start, end);
});

// --- WATCHERS ---
watch(searchQuery, () => {
  localPagination.currentPage = 1; // Reset về trang 1 khi tìm kiếm
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchCoupons(); // Tải tất cả
  if (couponModalRef.value) {
    couponModalInstance.value = new Modal(couponModalRef.value, { backdrop: 'static' });
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- CÁC HÀM TẢI DỮ LIỆU (READ) ---
async function fetchCoupons() {
  isLoading.value = true;
  try {
    // Tải tất cả, sắp xếp theo ID
    const response = await apiService.get(
      `?_sort=id&_order=desc`
    );
    allCoupons.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải mã giảm giá:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách mã giảm giá.', 'error');
  } finally {
    isLoading.value = false;
  }
}

// --- CÁC HÀM HELPER ---

function formatCurrency(value) {
  if (!value) return '0 đ';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}

function formatDateForDisplay(dateString) {
  if (!dateString) return 'Vô hạn';
  try {
    return new Date(dateString).toISOString().split('T')[0];
  } catch (e) {
    return 'Ngày không hợp lệ';
  }
}

function formatDateForInput(dateString) {
  if (!dateString) return '';
  try {
    return new Date(dateString).toISOString().split('T')[0];
  } catch (e) {
    return '';
  }
}

function formatValue(coupon) {
  if (coupon.type === 'percent') {
    return `${coupon.value}%`;
  }
  return formatCurrency(coupon.value);
}

function formatUsage(coupon) {
  const count = coupon.usageCount || 0;
  const limit = coupon.usageLimit;

  if (!limit || limit === 0) {
    return `${count} / Không giới hạn`;
  }
  return `${count} / ${limit}`;
}

function getStatus(coupon) {
  if (coupon.expiresAt) {
    const today = new Date();
    const expiryDate = new Date(coupon.expiresAt);
    expiryDate.setHours(23, 59, 59, 999);
    if (expiryDate < today) {
      return { text: 'Đã hết hạn', class: 'text-bg-danger' };
    }
  }
  const count = coupon.usageCount || 0;
  const limit = coupon.usageLimit;
  if (limit && limit > 0 && count >= limit) {
    return { text: 'Hết lượt dùng', class: 'text-bg-warning' };
  }
  return { text: 'Còn hạn', class: 'text-bg-success' };
}

// Chuyển trang (cục bộ)
function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    localPagination.currentPage = page;
  }
}

// --- CÁC HÀM CRUD (CREATE, UPDATE, DELETE) ---

function resetForm() {
  couponForm.id = null;
  couponForm.name = '';
  couponForm.code = '';
  couponForm.type = 'percent';
  couponForm.value = 0;
  couponForm.expiresAt = '';
  couponForm.usageLimit = null;
  couponForm.usageCount = 0;
  couponForm.limitPerUser = 1;
}

function openCreateModal() {
  resetForm();
  couponModalInstance.value.show();
}

function openEditModal(coupon) {
  couponForm.id = coupon.id;
  couponForm.name = coupon.name;
  couponForm.code = coupon.code;
  couponForm.type = coupon.type;
  couponForm.value = coupon.value;
  couponForm.expiresAt = formatDateForInput(coupon.expiresAt);
  couponForm.usageLimit = coupon.usageLimit;
  couponForm.usageCount = coupon.usageCount || 0;
  couponForm.limitPerUser = coupon.limitPerUser || 1;
  couponModalInstance.value.show();
}

// Mở Modal Xem
function openViewModal(coupon) {
  viewingCoupon.value = coupon;
  viewModalInstance.value.show();
}

async function handleSave() {
  // (VALIDATE)
  if (!couponForm.name.trim() || !couponForm.code.trim()) {
    Swal.fire('Lỗi', 'Tên/Mô tả và Mã Code không được để trống.', 'error');
    return;
  }
  if (couponForm.value < 0) {
    Swal.fire('Lỗi', 'Giá trị giảm giá không được là số âm.', 'error');
    return;
  }
  if (couponForm.type === 'percent' && couponForm.value > 100) {
    Swal.fire('Lỗi', 'Giá trị phần trăm không được vượt quá 100%.', 'error');
    return;
  }
  if (couponForm.limitPerUser < 1) {
    Swal.fire('Lỗi', 'Giới hạn mỗi User phải ít nhất là 1.', 'error');
    return;
  }
  // --- Hết Validate ---

  isSaving.value = true;
  const dataToSave = { ...couponForm };

  if (!dataToSave.expiresAt) {
    dataToSave.expiresAt = null;
  }
  if (!dataToSave.usageLimit || dataToSave.usageLimit <= 0) {
    dataToSave.usageLimit = null;
  }
  if (!dataToSave.limitPerUser || dataToSave.limitPerUser <= 0) {
    dataToSave.limitPerUser = 1;
  }

  try {
    if (dataToSave.id) {
      // --- CẬP NHẬT (UPDATE) ---
      await apiService.patch(`/${dataToSave.id}`, dataToSave);
    } else {
      // --- TẠO MỚI (CREATE) ---
      delete dataToSave.id;
      dataToSave.usageCount = 0;
      await apiService.post('/', dataToSave);
    }

    couponModalInstance.value.hide();
    Swal.fire('Thành công', 'Đã lưu mã giảm giá.', 'success');
    
    fetchCoupons(); // Tải lại tất cả

  } catch (error) {
    console.error("Lỗi khi lưu mã giảm giá:", error);
    Swal.fire('Lỗi', 'Không thể lưu mã giảm giá.', 'error');
  } finally {
    isSaving.value = false;
  }
}

async function handleDelete(coupon) {

  if (coupon.id === null || coupon.id === undefined) {
    Swal.fire('Lỗi Dữ Liệu', 'Coupon này bị lỗi (ID=null) và không thể xóa. Vui lòng kiểm tra db.json.', 'error');
    return;
  }

  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn sẽ xóa vĩnh viễn mã "${coupon.code}" (ID: ${coupon.id})!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Đồng ý xóa!',
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`/${coupon.id}`);
      Swal.fire('Đã xóa!', 'Mã giảm giá đã được xóa.', 'success');
      
      fetchCoupons(); // Tải lại tất cả

    } catch (error) {
      console.error(`Lỗi khi xóa mã giảm giá (ID: ${coupon.id}):`, error);
      Swal.fire('Lỗi', 'Không thể xóa mã này. Chi tiết: ' + error.message, 'error');
    }
  }
}

</script>

<template>
  <!-- Content Header (Page header) -->
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Mã giảm giá</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">
              Mã giảm giá
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <!-- Main content -->
  <div class="app-content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <!-- Bảng danh sách -->
          <div class="card">
            <div class="card-header">
              <!-- Cập nhật Header với Tìm kiếm -->
              <div class="row align-items-center">
                <div class="col-md-6 col-12 mb-2 mb-md-0">
                  <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                      <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0"
                      placeholder="Tìm theo Mã Code, Tên/Mô tả..." v-model="searchQuery">
                  </div>
                </div>
                <div class="col-md-6 col-12 text-md-end">
                  <button class="btn btn-success btn-sm" @click="openCreateModal">
                    <i class="bi bi-plus-lg"></i> Thêm mới
                  </button>
                </div>
              </div>
            </div>

            <div class="card-body p-0">
              <div v-if="isLoading && allCoupons.length === 0" class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>

              <div v-else class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                  <thead>
                    <tr>
                      <th style="width: 150px">Mã Code</th>
                      <th>Tên/Mô tả</th>
                      <th style="width: 110px">Giá trị</th>
                      <th style="width: 160px">Lượt dùng (Tổng)</th>
                      <th style="width: 130px">Lượt dùng/User</th>
                      <th style="width: 140px">Ngày hết hạn</th>
                      <th style="width: 140px">Trạng thái</th>
                      <th style="width: 200px">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="paginatedCoupons.length === 0 && !isLoading">
                      <td colspan="8" class="text-center">
                        {{ searchQuery ? 'Không tìm thấy mã nào.' : 'Không có mã giảm giá nào.' }}
                      </td>
                    </tr>
                    <tr v-for="coupon in paginatedCoupons" :key="coupon.id">
                      <td>
                        <strong class="text-primary">{{ coupon.code }}</strong>
                      </td>
                      <td>{{ coupon.name }}</td>
                      <td>{{ formatValue(coupon) }}</td>
                      <td>{{ formatUsage(coupon) }}</td>
                      <td>{{ coupon.limitPerUser || 1 }} lần</td>
                      <td>{{ formatDateForDisplay(coupon.expiresAt) }}</td>
                      <td>
                        <span class="badge" :class="getStatus(coupon).class">
                          {{ getStatus(coupon).text }}
                        </span>
                      </td>
                      <td>
                        <!-- Thêm nút Xem -->
                        <button class="btn btn-outline-info btn-sm me-1" @click="openViewModal(coupon)" title="Xem chi tiết">
                           <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning btn-sm me-1" @click="openEditModal(coupon)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm" @click="handleDelete(coupon)">
                          <i class="bi bi-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Footer Phân trang (Cập nhật logic) -->
            <div class="card-footer clearfix" v-if="!isLoading && totalPages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <li class="page-item" :class="{ disabled: localPagination.currentPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="goToPage(localPagination.currentPage - 1)">&laquo;</a>
                </li>
                <li v-for="page in totalPages" :key="page" class="page-item"
                  :class="{ active: localPagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="goToPage(page)">{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: localPagination.currentPage === totalPages }">
                  <a class="page-link" href="#" @click.prevent="goToPage(localPagination.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal (Dùng chung cho Tạo mới & Cập nhật) -->
  <div class="modal fade" id="couponModal" ref="couponModalRef" tabindex="-1" aria-labelledby="couponModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="couponModalLabel">
            {{ couponForm.id ? 'Cập nhật' : 'Tạo mới' }} Mã giảm giá
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="couponFormElement" @submit.prevent="handleSave">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="couponName" class="form-label required">Tên/Mô tả</label>
                <input type="text" class="form-control" id="couponName" v-model="couponForm.name"
                  placeholder="V.dụ: Giảm giá 30/4" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="couponCode" class="form-label required">Mã Code</label>
                <input type="text" class="form-control" id="couponCode" v-model="couponForm.code"
                  placeholder="V.dụ: 30THANG4" required>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="couponType" class="form-label">Loại giảm giá</label>
                <select class="form-select" id="couponType" v-model="couponForm.type">
                  <option value="percent">Theo phần trăm (%)</option>
                  <option value="fixed">Số tiền cố định (VND)</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label for="couponValue" class="form-label required">Giá trị</label>
                <input type="number" class="form-control" id="couponValue" v-model.number="couponForm.value" required
                  min="0">
              </div>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="couponExpiry" class="form-label">Ngày hết hạn</label>
                <input type="date" class="form-control" id="couponExpiry" v-model="couponForm.expiresAt">
                <small class="form-text text-muted">Bỏ trống nếu vô hạn.</small>
              </div>
              <div class="col-md-4 mb-3">
                <label for="couponUsageLimit" class="form-label">Giới hạn lượt dùng (Tổng)</label>
                <input type="number" class="form-control" id="couponUsageLimit" v-model.number="couponForm.usageLimit"
                  min="0">
                <small class="form-text text-muted">Bỏ trống hoặc 0 = vô hạn.</small>
              </div>
              <div class="col-md-4 mb-3">
                <label for="couponLimitPerUser" class="form-label required">Giới hạn lượt dùng (User)</label>
                <input type="number" class="form-control" id="couponLimitPerUser"
                  v-model.number="couponForm.limitPerUser" required min="1">
                <small class="form-text text-muted">V.dụ: 1 (mỗi người 1 lần)</small>
              </div>
            </div>

            <div v-if="couponForm.id" class="mb-3">
              <label for="couponUsageCount" class="form-label">Số lần đã sử dụng (Tổng)</label>
              <input type="number" class="form-control" id="couponUsageCount" v-model.number="couponForm.usageCount"
                min="0">
              <small class="form-text text-muted">Admin có thể chỉnh sửa số lần đã dùng.</small>
            </div>

            <button type="submit" style="display: none;"></button>
          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="couponFormElement" class="btn btn-primary" :disabled="isSaving">
            <span v-if="isSaving" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            Lưu thay đổi
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Modal Xem Chi Tiết (MỚI) -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <!-- Status Badge -->
          <div class="position-absolute top-0 start-0 m-3">
            <span class="badge" :class="getStatus(viewingCoupon).class">
              {{ getStatus(viewingCoupon).text }}
            </span>
          </div>

          <!-- Thông tin chính -->
          <div class="text-center mb-4 mt-3">
             <div class="img-thumbnail d-flex align-items-center justify-content-center bg-light shadow-sm"
              style="width: 120px; height: 120px; font-size: 2rem; margin: auto; border-radius: .5rem;">
                <!-- Thay đổi icon từ bootstrap sang font awesome -->
                <i class="fas fa-ticket text-primary"></i>
            </div>
            <h4 class="mt-3 mb-1">{{ viewingCoupon.code }}</h4>
            <p class="text-muted mb-0">{{ viewingCoupon.name }}</p>
          </div>

          <!-- Chi tiết -->
          <div class="list-group list-group-flush">
            <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-tag me-3 text-success"></i>Giá trị</h6>
               <p class="mb-1 text-dark fw-bold" style="font-size: 1.2rem;">{{ formatValue(viewingCoupon) }}</p>
            </div>
             <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-pie-chart me-3 text-muted"></i>Lượt sử dụng (Tổng)</h6>
               <p class="mb-1 text-muted small">{{ formatUsage(viewingCoupon) }}</p>
            </div>
             <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-person me-3 text-muted"></i>Lượt sử dụng (User)</h6>
               <p class="mb-1 text-muted small">{{ viewingCoupon.limitPerUser || 1 }} lần / người</p>
            </div>
            <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-calendar-event me-3 text-muted"></i>Ngày hết hạn</h6>
               <p class="mb-1 text-muted small">{{ formatDateForDisplay(viewingCoupon.expiresAt) }}</p>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-primary px-4"
            @click="() => { viewModalInstance.hide(); openEditModal(viewingCoupon); }">
            <i class="bi bi-pencil me-2"></i> Chỉnh sửa
          </button>
        </div>
      </div>
    </div>
  </div>
  
</template>

<style scoped>
.table td .btn {
  margin-top: 2px;
  margin-bottom: 2px;
}
.card-body.p-0 .table {
  margin-bottom: 0;
}
.card-header .card-tools {
  float: right;
}
/* Thêm CSS cho label bắt buộc */
.required::after {
  content: " *";
  color: red;
}
</style>