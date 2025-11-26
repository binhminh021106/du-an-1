<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---
const isLoading = ref(true); // Loading cho danh sách chính (Active/Expired)
const isLoadingTrashed = ref(true); // Loading riêng cho Thùng rác
const isSaving = ref(false);
const activeTab = ref('active'); // Tab mặc định: Đang hoạt động

// State cho Modal Thêm/Sửa
const couponModalRef = ref(null);
const couponModalInstance = ref(null);

// State cho Modal Xem
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingCoupon = ref({});

// State cho dữ liệu
const allCoupons = ref([]); // Tất cả coupons (Active + Expired)
const trashedCoupons = ref([]); // Coupons đã xóa
const searchQuery = ref('');

// --- CẤU HÌNH PHÂN TRANG RIÊNG BIỆT ---
const pagination = reactive({
  active: { currentPage: 1, itemsPerPage: 8 },
  expired: { currentPage: 1, itemsPerPage: 8 },
  trashed: { currentPage: 1, itemsPerPage: 8 }
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
  limitPerUser: null
});

// --- HELPER CHECK EXPIRY ---
function checkIsExpired(coupon) {
  if (!coupon.expires_at) return false;
  const today = new Date();
  const expiryDate = new Date(coupon.expires_at);
  expiryDate.setHours(23, 59, 59, 999);
  return expiryDate < today;
}

// --- COMPUTED & FILTERING ---

// 1. Danh sách Active (Gốc)
const activeCouponsList = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  return allCoupons.value.filter(coupon => {
    const matchesSearch = coupon.code.toLowerCase().includes(query) ||
      coupon.name.toLowerCase().includes(query);
    const notExpired = !checkIsExpired(coupon);
    return matchesSearch && notExpired;
  });
});

// 2. Danh sách Expired (Gốc)
const expiredCouponsList = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  return allCoupons.value.filter(coupon => {
    const matchesSearch = coupon.code.toLowerCase().includes(query) ||
      coupon.name.toLowerCase().includes(query);
    const isExpired = checkIsExpired(coupon);
    return matchesSearch && isExpired;
  });
});

// --- PHÂN TRANG LOGIC ---

// Helper tính trang & cắt mảng
function getPaginatedData(list, pageInfo) {
  const total = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));
  // Nếu trang hiện tại lớn hơn tổng số trang (do xóa bớt item), reset về 1
  if (pageInfo.currentPage > total) pageInfo.currentPage = 1;
  
  const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
  const end = start + pageInfo.itemsPerPage;
  return {
    data: list.slice(start, end),
    totalPages: total
  };
}

// Computed Active có phân trang
const pagedActive = computed(() => getPaginatedData(activeCouponsList.value, pagination.active));
// Computed Expired có phân trang
const pagedExpired = computed(() => getPaginatedData(expiredCouponsList.value, pagination.expired));
// Computed Trashed có phân trang
const pagedTrashed = computed(() => getPaginatedData(trashedCoupons.value, pagination.trashed));

// Check expiry form input
const isExpiryInPast = computed(() => {
  if (!couponForm.expiresAt) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const expiry = new Date(couponForm.expiresAt);
  return expiry < today;
});

// --- WATCHERS ---
watch(searchQuery, () => {
  // Reset trang về 1 khi tìm kiếm
  pagination.active.currentPage = 1;
  pagination.expired.currentPage = 1;
  pagination.trashed.currentPage = 1;
});

// --- VÒNG ĐỜI ---
onMounted(() => {
  fetchCoupons();
  fetchTrashedCoupons();
  if (couponModalRef.value) {
    couponModalInstance.value = new Modal(couponModalRef.value, { backdrop: 'static' });
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- API FUNCTIONS ---

async function fetchCoupons() {
  if (allCoupons.value.length === 0) {
    isLoading.value = true;
  }
  try {
    const response = await apiService.get(`admin/coupons`);
    allCoupons.value = response.data;
  } catch (error) {
    console.error("Lỗi tải coupons:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function fetchTrashedCoupons() {
  if (trashedCoupons.value.length === 0) {
    isLoadingTrashed.value = true;
  }
  try {
    const response = await apiService.get(`admin/coupons/trashed`);
    trashedCoupons.value = response.data;
  } catch (error) {
    console.error("Lỗi tải thùng rác:", error);
  } finally {
    isLoadingTrashed.value = false;
  }
}

async function handleRestore(id) {
  try {
    await apiService.post(`admin/coupons/${id}/restore`);
    Swal.fire('Thành công', 'Đã khôi phục mã giảm giá.', 'success');
    fetchCoupons();
    fetchTrashedCoupons();
  } catch (error) {
    Swal.fire('Lỗi', 'Không thể khôi phục.', 'error');
  }
}

async function handleForceDelete(id) {
  Swal.fire({
    title: 'Xóa vĩnh viễn?',
    text: "Hành động này không thể hoàn tác!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Xóa vĩnh viễn'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        await apiService.delete(`admin/coupons/${id}/force`);
        Swal.fire('Đã xóa', 'Mã đã bị xóa vĩnh viễn.', 'success');
        fetchTrashedCoupons();
      } catch (error) {
        Swal.fire('Lỗi', 'Không thể xóa vĩnh viễn.', 'error');
      }
    }
  });
}

// --- HELPER FORMAT ---
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
  if (coupon.type === 'percent') return `${coupon.value}%`;
  return formatCurrency(coupon.value);
}

function formatUsage(coupon) {
  const count = coupon.usage_count || 0;
  const limit = coupon.usage_limit;
  if (!limit || limit === 0) return `${count} / ∞`;
  return `${count} / ${limit}`;
}

function getStatus(coupon) {
  if (checkIsExpired(coupon)) {
    return { text: 'Đã hết hạn', class: 'text-bg-danger' };
  }
  const count = coupon.usage_count || 0;
  const limit = coupon.usage_limit;
  if (limit && limit > 0 && count >= limit) {
    return { text: 'Hết lượt dùng', class: 'text-bg-warning' };
  }
  return { text: 'Còn hạn', class: 'text-bg-success' };
}

function changePage(listName, page) {
  if (pagination[listName]) {
    pagination[listName].currentPage = page;
  }
}

function setActiveTab(tabName) {
  activeTab.value = tabName;
}

// --- CRUD ---
function resetForm() {
  couponForm.id = null;
  couponForm.name = '';
  couponForm.code = '';
  couponForm.type = 'percent';
  couponForm.value = 0;
  couponForm.expiresAt = '';
  couponForm.usageLimit = null;
  couponForm.usageCount = 0;
  couponForm.limitPerUser = null;
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
  couponForm.expiresAt = formatDateForInput(coupon.expires_at);
  couponForm.usageLimit = coupon.usage_limit;
  couponForm.usageCount = coupon.usage_count || 0;
  couponForm.limitPerUser = coupon.usage_limit_per_user;
  couponModalInstance.value.show();
}

function openViewModal(coupon) {
  viewingCoupon.value = coupon;
  viewModalInstance.value.show();
}

async function handleSave() {
  if (!couponForm.name.trim() || !couponForm.code.trim()) {
    Swal.fire('Lỗi', 'Tên/Mô tả và Mã Code không được để trống.', 'error');
    return;
  }
  isSaving.value = true;
  const dataToSave = { ...couponForm };
  if (!dataToSave.expiresAt) dataToSave.expiresAt = null;
  if (!dataToSave.usageLimit || dataToSave.usageLimit <= 0) dataToSave.usageLimit = null;
  if (!dataToSave.limitPerUser || dataToSave.limitPerUser <= 0) dataToSave.limitPerUser = null;

  try {
    if (dataToSave.id) {
      delete dataToSave.usageCount;
      await apiService.patch(`admin/coupons/${dataToSave.id}`, dataToSave);
    } else {
      delete dataToSave.id;
      dataToSave.usageCount = 0;
      await apiService.post("admin/coupons", dataToSave);
    }
    couponModalInstance.value.hide();
    Swal.fire('Thành công', 'Đã lưu mã giảm giá.', 'success');
    fetchCoupons();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      const errors = error.response.data.errors;
      let errorMessage = "<ul style='text-align: left;'>";
      for (const key in errors) {
        errorMessage += `<li>${errors[key][0]}</li>`;
      }
      errorMessage += "</ul>";
      Swal.fire({ title: 'Dữ liệu không hợp lệ', html: errorMessage, icon: 'warning' });
    } else {
      Swal.fire('Lỗi', 'Không thể lưu mã giảm giá.', 'error');
    }
  } finally {
    isSaving.value = false;
  }
}

async function handleDelete(coupon) {
  Swal.fire({
    title: 'Chuyển vào thùng rác?',
    text: `Mã "${coupon.name}" sẽ bị vô hiệu hóa.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#f0ad4e',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Xóa tạm thời',
    cancelButtonText: 'Hủy'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        await apiService.delete(`admin/coupons/${coupon.id}`);
        Swal.fire('Đã xóa!', 'Mã giảm giá đã chuyển vào thùng rác.', 'success');
        fetchCoupons();
        fetchTrashedCoupons();
      } catch (error) {
        Swal.fire('Lỗi', 'Không thể xóa mã giảm giá.', 'error');
      }
    }
  });
}
</script>

<template>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Mã giảm giá</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active">Mã giảm giá</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card shadow-sm">
        <div class="card-header border-bottom-0 pb-0">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h3 class="card-title text-nowrap">Danh sách Mã giảm giá</h3>
            
            <!-- TOOLBAR: SEARCH & ADD -->
            <div class="d-flex gap-2 flex-grow-1 justify-content-end" style="max-width: 600px;">
               <div class="input-group" style="min-width: 200px;">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm kiếm..." v-model="searchQuery">
              </div>
              <button class="btn btn-success text-nowrap" @click="openCreateModal">
                <i class="bi bi-plus-lg"></i> Thêm mới
              </button>
            </div>
          </div>

          <!-- TABS NAVIGATION -->
          <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'active' }" href="#" @click.prevent="setActiveTab('active')">
                <i class="bi bi-check-circle me-1 text-primary"></i> Khả dụng
                <span class="badge rounded-pill bg-primary ms-1">{{ activeCouponsList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'expired' }" href="#" @click.prevent="setActiveTab('expired')">
                <i class="bi bi-clock-history me-1 text-secondary"></i> Đã hết hạn
                <span class="badge rounded-pill bg-secondary ms-1">{{ expiredCouponsList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'trashed' }" href="#" @click.prevent="setActiveTab('trashed')">
                <i class="bi bi-trash3 me-1 text-danger"></i> Thùng rác
                <span class="badge rounded-pill bg-danger ms-1">{{ trashedCoupons.length }}</span>
              </a>
            </li>
          </ul>
        </div>

        <div class="card-body p-0">
          <!-- Content Area -->
          <div class="tab-content p-0">
            
            <!-- TAB 1: ACTIVE COUPONS -->
            <div class="tab-pane fade show active" v-if="activeTab === 'active'">
              <!-- Loading -->
              <div v-if="isLoading && allCoupons.length === 0" class="text-center p-5">
                <div class="spinner-border text-primary" role="status"></div>
              </div>
              <div v-else>
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Mã Code</th>
                        <th>Tên/Mô tả</th>
                        <th>Giá trị</th>
                        <th>Lượt dùng</th>
                        <th>Hạn dùng</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Hành động</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="pagedActive.data.length === 0">
                        <td colspan="7" class="text-center py-4 text-muted">
                          {{ searchQuery ? 'Không tìm thấy kết quả phù hợp.' : 'Không có mã giảm giá nào đang hoạt động.' }}
                        </td>
                      </tr>
                      <tr v-for="coupon in pagedActive.data" :key="coupon.id">
                        <td class="fw-bold text-primary">{{ coupon.code }}</td>
                        <td>{{ coupon.name }}</td>
                        <td>{{ formatValue(coupon) }}</td>
                        <td>{{ formatUsage(coupon) }}</td>
                        <td>{{ formatDateForDisplay(coupon.expires_at) }}</td>
                        <td><span class="badge" :class="getStatus(coupon).class">{{ getStatus(coupon).text }}</span></td>
                        <td class="text-end">
                          <button class="btn btn-outline-info btn-sm me-1" @click="openViewModal(coupon)"><i class="bi bi-eye"></i></button>
                          <button class="btn btn-outline-warning btn-sm me-1" @click="openEditModal(coupon)"><i class="bi bi-pencil"></i></button>
                          <button class="btn btn-outline-danger btn-sm" @click="handleDelete(coupon)"><i class="bi bi-trash"></i></button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <!-- Pagination Active -->
                <div v-if="pagedActive.totalPages > 1" class="d-flex justify-content-end p-3">
                  <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: pagination.active.currentPage === 1 }">
                      <a class="page-link" href="#" @click.prevent="changePage('active', pagination.active.currentPage - 1)">&laquo;</a>
                    </li>
                    <li v-for="page in pagedActive.totalPages" :key="page" class="page-item" :class="{ active: pagination.active.currentPage === page }">
                      <a class="page-link" href="#" @click.prevent="changePage('active', page)">{{ page }}</a>
                    </li>
                    <li class="page-item" :class="{ disabled: pagination.active.currentPage === pagedActive.totalPages }">
                      <a class="page-link" href="#" @click.prevent="changePage('active', pagination.active.currentPage + 1)">&raquo;</a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- TAB 2: EXPIRED COUPONS -->
            <div class="tab-pane fade show active" v-if="activeTab === 'expired'">
              <!-- Loading (dùng chung isLoading vì cùng nguồn API) -->
              <div v-if="isLoading && allCoupons.length === 0" class="text-center p-5">
                <div class="spinner-border text-secondary" role="status"></div>
              </div>
              <div v-else>
                <div class="table-responsive">
                  <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Mã Code</th>
                        <th>Tên</th>
                        <th>Giá trị</th>
                        <th>Ngày hết hạn</th>
                        <th class="text-end">Hành động</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="pagedExpired.data.length === 0">
                        <td colspan="5" class="text-center py-4 text-muted">Không có mã giảm giá nào đã hết hạn.</td>
                      </tr>
                      <tr v-for="coupon in pagedExpired.data" :key="coupon.id" class="text-muted opacity-75">
                        <td>{{ coupon.code }}</td>
                        <td>{{ coupon.name }}</td>
                        <td>{{ formatValue(coupon) }}</td>
                        <td class="text-danger fw-bold">{{ formatDateForDisplay(coupon.expires_at) }}</td>
                        <td class="text-end">
                          <button class="btn btn-outline-secondary btn-sm me-1" @click="openEditModal(coupon)" title="Gia hạn"><i class="bi bi-pencil"></i></button>
                          <button class="btn btn-outline-danger btn-sm" @click="handleDelete(coupon)"><i class="bi bi-trash"></i></button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <!-- Pagination Expired -->
                <div v-if="pagedExpired.totalPages > 1" class="d-flex justify-content-end p-3">
                  <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: pagination.expired.currentPage === 1 }">
                      <a class="page-link" href="#" @click.prevent="changePage('expired', pagination.expired.currentPage - 1)">&laquo;</a>
                    </li>
                    <li v-for="page in pagedExpired.totalPages" :key="page" class="page-item" :class="{ active: pagination.expired.currentPage === page }">
                      <a class="page-link" href="#" @click.prevent="changePage('expired', page)">{{ page }}</a>
                    </li>
                    <li class="page-item" :class="{ disabled: pagination.expired.currentPage === pagedExpired.totalPages }">
                      <a class="page-link" href="#" @click.prevent="changePage('expired', pagination.expired.currentPage + 1)">&raquo;</a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- TAB 3: TRASHED COUPONS -->
            <div class="tab-pane fade show active" v-if="activeTab === 'trashed'">
              <!-- Loading riêng cho thùng rác -->
              <div v-if="isLoadingTrashed && trashedCoupons.length === 0" class="text-center p-5">
                <div class="spinner-border text-danger" role="status"></div>
              </div>
              <div v-else>
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Mã Code</th>
                        <th>Tên</th>
                        <th>Ngày xóa</th>
                        <th class="text-end">Hành động</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="pagedTrashed.data.length === 0">
                        <td colspan="4" class="text-center text-muted py-4">
                          <i class="bi bi-inbox fs-4 d-block mb-2 text-secondary"></i>
                          Thùng rác trống.
                        </td>
                      </tr>
                      <tr v-for="coupon in pagedTrashed.data" :key="coupon.id">
                        <td class="text-decoration-line-through">{{ coupon.code }}</td>
                        <td>{{ coupon.name }}</td>
                        <td>{{ coupon.deleted_at ? formatDateForDisplay(coupon.deleted_at) : 'N/A' }}</td>
                        <td class="text-end">
                          <div class="d-flex gap-2 justify-content-end">
                            <button class="btn btn-success btn-sm text-nowrap" @click="handleRestore(coupon.id)">
                              <i class="bi bi-arrow-counterclockwise"></i> Khôi phục
                            </button>
                            <button class="btn btn-outline-dark btn-sm text-nowrap" @click="handleForceDelete(coupon.id)">
                              <i class="bi bi-x-lg"></i> Xóa vĩnh viễn
                            </button>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <!-- Pagination Trashed -->
                <div v-if="pagedTrashed.totalPages > 1" class="d-flex justify-content-end p-3">
                  <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: pagination.trashed.currentPage === 1 }">
                      <a class="page-link" href="#" @click.prevent="changePage('trashed', pagination.trashed.currentPage - 1)">&laquo;</a>
                    </li>
                    <li v-for="page in pagedTrashed.totalPages" :key="page" class="page-item" :class="{ active: pagination.trashed.currentPage === page }">
                      <a class="page-link" href="#" @click.prevent="changePage('trashed', page)">{{ page }}</a>
                    </li>
                    <li class="page-item" :class="{ disabled: pagination.trashed.currentPage === pagedTrashed.totalPages }">
                      <a class="page-link" href="#" @click.prevent="changePage('trashed', pagination.trashed.currentPage + 1)">&raquo;</a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal (Form) -->
  <div class="modal fade" id="couponModal" ref="couponModalRef" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ couponForm.id ? 'Cập nhật' : 'Tạo mới' }} Mã giảm giá</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="couponFormElement" @submit.prevent="handleSave">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">Tên/Mô tả</label>
                <input type="text" class="form-control" v-model="couponForm.name" placeholder="V.dụ: Giảm giá 30/4" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Mã Code</label>
                <input type="text" class="form-control" v-model="couponForm.code" placeholder="V.dụ: 30THANG4" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Loại giảm giá</label>
                <select class="form-select" v-model="couponForm.type">
                  <option value="percent">Theo phần trăm (%)</option>
                  <option value="fixed">Số tiền cố định (VND)</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Giá trị</label>
                <input type="number" class="form-control" v-model.number="couponForm.value" required min="0">
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Ngày hết hạn</label>
                <input type="date" class="form-control" v-model="couponForm.expiresAt">
                <div v-if="isExpiryInPast" class="text-warning mt-1 small"><i class="bi bi-exclamation-triangle-fill"></i> Ngày này ở trong quá khứ.</div>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Giới hạn lượt dùng (Tổng)</label>
                <input type="number" class="form-control" v-model.number="couponForm.usageLimit" min="0">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Giới hạn lượt dùng (User)</label>
                <input type="number" class="form-control" v-model.number="couponForm.limitPerUser" min="1" placeholder="Không giới hạn">
              </div>
            </div>
            <div v-if="couponForm.id" class="mb-3">
              <label class="form-label">Số lần đã sử dụng</label>
              <input type="number" class="form-control bg-light" v-model.number="couponForm.usageCount" readonly disabled>
            </div>
            <button type="submit" style="display: none;"></button>
          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="couponFormElement" class="btn btn-primary" :disabled="isSaving">
            <span v-if="isSaving" class="spinner-border spinner-border-sm" aria-hidden="true"></span> Lưu thay đổi
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Xem -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
          <div class="position-absolute top-0 start-0 m-3">
            <span class="badge" :class="getStatus(viewingCoupon).class">{{ getStatus(viewingCoupon).text }}</span>
          </div>
          <div class="text-center mb-4 mt-3">
            <div class="img-thumbnail d-flex align-items-center justify-content-center bg-light shadow-sm"
              style="width: 120px; height: 120px; font-size: 2rem; margin: auto; border-radius: .5rem;">
              <i class="fas fa-ticket text-primary"></i>
            </div>
            <h4 class="mt-3 mb-1">{{ viewingCoupon.code }}</h4>
            <p class="text-muted mb-0">{{ viewingCoupon.name }}</p>
          </div>
          <div class="list-group list-group-flush">
            <div class="list-group-item px-0">
              <h6 class="mb-2">Giá trị</h6>
              <p class="mb-1 fw-bold">{{ formatValue(viewingCoupon) }}</p>
            </div>
            <div class="list-group-item px-0">
              <h6 class="mb-2">Lượt dùng</h6>
              <p class="mb-1 small">{{ formatUsage(viewingCoupon) }}</p>
            </div>
            <div class="list-group-item px-0">
              <h6 class="mb-2">Hết hạn</h6>
              <p class="mb-1 small">{{ formatDateForDisplay(viewingCoupon.expiresAt) }}</p>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-primary px-4"
            @click="() => { viewModalInstance.hide(); openEditModal(viewingCoupon); }"><i class="bi bi-pencil me-2"></i>
            Chỉnh sửa</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.nav-tabs .nav-link {
  cursor: pointer;
  color: #6c757d;
  font-weight: 500;
}
.nav-tabs .nav-link.active {
  color: #0d6efd;
  border-bottom-color: #fff;
}
.pagination .page-link {
  cursor: pointer;
}
.table td .btn {
  margin: 2px;
}
.required::after {
  content: " *";
  color: red;
}
</style>