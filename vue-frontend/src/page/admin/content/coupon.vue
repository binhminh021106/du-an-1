<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---
const API_URL = "http://localhost:3000/coupons"; 
const isLoading = ref(true);      
const isSaving = ref(false);        

// State cho Modal
const couponModalRef = ref(null);
const couponModalInstance = ref(null);

// State cho dữ liệu
const coupons = ref([]);
const pagination = reactive({
  currentPage: 1,
  itemsPerPage: 10,
  totalItems: 0,
  totalPages: 1
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
  limitPerUser: 1 // Mặc định mỗi người được dùng 1 lần
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchCoupons(1);
  
  if (couponModalRef.value) {
    couponModalInstance.value = new Modal(couponModalRef.value);
  }
});

// --- CÁC HÀM TẢI DỮ LIỆU (READ) ---
async function fetchCoupons(page = 1) {
  isLoading.value = true;
  if (page < 1 || (page > pagination.totalPages && pagination.totalItems > 0)) {
    isLoading.value = false;
    return;
  }
  pagination.currentPage = page;

  try {
    const response = await axios.get(
      `${API_URL}?_page=${pagination.currentPage}&_limit=${pagination.itemsPerPage}&_sort=id&_order=desc`
    );
    
    coupons.value = response.data;
    pagination.totalItems = parseInt(response.headers['x-total-count'] || 0);
    pagination.totalPages = Math.ceil(pagination.totalItems / pagination.itemsPerPage);
    
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
  if (!dateString) return 'N/A';
  try {
    return new Date(dateString).toISOString().split('T')[0];
  } catch (e) {
    console.error("Lỗi formatDate:", e);
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
  // Kiểm tra nếu là % thì không được vượt quá 100
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
      await axios.patch(`${API_URL}/${dataToSave.id}`, dataToSave);
    } else { 
      // --- TẠO MỚI (CREATE) ---
      delete dataToSave.id; 
      dataToSave.usageCount = 0; 
      await axios.post(API_URL, dataToSave);
    }

    couponModalInstance.value.hide();
    Swal.fire('Thành công', 'Đã lưu mã giảm giá.', 'success');
    
    if (dataToSave.id) {
        fetchCoupons(pagination.currentPage);
    } else {
        fetchCoupons(1); 
    }
    
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
      await axios.delete(`${API_URL}/${coupon.id}`); 
      Swal.fire('Đã xóa!', 'Mã giảm giá đã được xóa.', 'success');
      
      if (coupons.value.length === 1 && pagination.currentPage > 1) {
        fetchCoupons(pagination.currentPage - 1);
      } else {
        fetchCoupons(pagination.currentPage);
      }
      
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
              <h3 class="card-title">Danh sách Mã giảm giá</h3>
              <div class="card-tools">
                <button class="btn btn-success btn-sm" @click="openCreateModal">
                  <i class="bi bi-plus-lg"></i> Thêm mới
                </button>
              </div>
            </div>
            
            <div class="card-body p-0">
              <div v-if="isLoading && coupons.length === 0" class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>

              <table v-else class="table table-hover table-striped">
                <thead>
                  <tr>
                    <th style="width: 150px">Mã Code</th>
                    <th>Tên/Mô tả</th>
                    <th style="width: 110px">Giá trị</th>
                    <th style="width: 160px">Lượt dùng (Tổng)</th>
                    <th style="width: 130px">Lượt dùng/User</th>
                    <th style="width: 140px">Ngày hết hạn</th>
                    <th style="width: 140px">Trạng thái</th>
                    <th style="width: 150px">Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="coupons.length === 0 && !isLoading">
                    <td colspan="8" class="text-center">Không có mã giảm giá nào.</td>
                  </tr>
                  <tr v-for="coupon in coupons" :key="coupon.id">
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
                      <button class="btn btn-warning btn-sm me-2" @click="openEditModal(coupon)">
                        <i class="bi bi-pencil-square"></i> Sửa
                      </button>
                      <button class="btn btn-danger btn-sm" @click="handleDelete(coupon)">
                        <i class="bi bi-trash"></i> Xóa
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Footer Phân trang -->
            <div class="card-footer clearfix" v-if="!isLoading && pagination.totalPages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <li class="page-item" :class="{ disabled: pagination.currentPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="fetchCoupons(pagination.currentPage - 1)">&laquo;</a>
                </li>
                <li v-for="page in pagination.totalPages" :key="page" 
                    class="page-item" 
                    :class="{ active: pagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="fetchCoupons(page)">{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: pagination.currentPage === pagination.totalPages }">
                  <a class="page-link" href="#" @click.prevent="fetchCoupons(pagination.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal (Dùng chung cho Tạo mới & Cập nhật) -->
  <div class="modal fade" id="couponModal" ref="couponModalRef" tabindex="-1" aria-labelledby="couponModalLabel" aria-hidden="true">
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
                <label for="couponName" class="form-label">Tên/Mô tả</label>
                <input type="text" class="form-control" id="couponName" v-model="couponForm.name" placeholder="V.dụ: Giảm giá 30/4" required>
              </div>
              <div class="col-md-6 mb-3">
                <label for="couponCode" class="form-label">Mã Code</label>
                <input type="text" class="form-control" id="couponCode" v-model="couponForm.code" placeholder="V.dụ: 30THANG4" required>
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
                <label for="couponValue" class="form-label">Giá trị</label>
                <input type="number" class="form-control" id="couponValue" v-model.number="couponForm.value" required min="0">
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
                <input type="number" class="form-control" id="couponUsageLimit" v-model.number="couponForm.usageLimit" min="0">
                <small class="form-text text-muted">Bỏ trống hoặc 0 = vô hạn.</small>
              </div>
              <div class="col-md-4 mb-3">
                <label for="couponLimitPerUser" class="form-label">Giới hạn lượt dùng (User)</label>
                <input type="number" class="form-control" id="couponLimitPerUser" v-model.number="couponForm.limitPerUser" required min="1">
                <small class="form-text text-muted">V.dụ: 1 (mỗi người 1 lần)</small>
              </div>
            </div>

             <div v-if="couponForm.id" class="mb-3">
              <label for="couponUsageCount" class="form-label">Số lần đã sử dụng (Tổng)</label>
              <input type="number" class="form-control" id="couponUsageCount" v-model.number="couponForm.usageCount" min="0">
              <small class="form-text text-muted">Admin có thể chỉnh sửa số lần đã dùng.</small>
            </div>
            
            <button type="submit" style="display: none;"></button>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="couponFormElement" class="btn btn-primary" :disabled="isSaving">
            <span v-if="isSaving" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            Lưu thay đổi
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
</style>