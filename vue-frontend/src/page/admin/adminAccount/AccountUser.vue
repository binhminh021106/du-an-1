<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE MANAGEMENT ---
const allCustomers = ref([]); // Store all data
const isLoading = ref(true);
const searchQuery = ref('');
const activeTab = ref('active'); // 'active' | 'inactive'

// State Modal
const customerModalRef = ref(null);
const customerModalInstance = ref(null);
const viewModalRef = ref(null);
const viewModalInstance = ref(null);

const isEditMode = ref(false);
const viewingCustomer = ref({});

// Pagination Config (Independent for each tab, limit 8)
const pagination = reactive({
  active: { currentPage: 1, itemsPerPage: 8 },
  inactive: { currentPage: 1, itemsPerPage: 8 }
});

// Form data
const formData = reactive({
  id: null,
  fullName: '', 
  email: '',
  phone: '',
  address: '',
  avatar_url: '',
  status: 'active',
  password: '', 
  password_confirmation: '' 
});

const avatarFile = ref(null);
const avatarPreview = ref(null);

const errors = reactive({
  fullName: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: ''
});

// --- COMPUTED: FILTER & SPLIT LISTS ---

// 1. Search Filter
const searchResults = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return allCustomers.value;

  return allCustomers.value.filter(customer =>
    (customer.fullName && customer.fullName.toLowerCase().includes(query)) ||
    (customer.email && customer.email.toLowerCase().includes(query)) ||
    (customer.phone && customer.phone.includes(query))
  );
});

// 2. Split Lists
const activeList = computed(() => searchResults.value.filter(c => c.status === 'active'));
const inactiveList = computed(() => searchResults.value.filter(c => c.status !== 'active'));

// 3. Pagination Helper
function getPaginatedData(list, type) {
  const pageInfo = pagination[type];
  const totalPages = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));
  
  if (pageInfo.currentPage > totalPages) pageInfo.currentPage = 1;

  const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
  const end = start + pageInfo.itemsPerPage;
  
  return {
    data: list.slice(start, end),
    totalPages: totalPages,
    totalItems: list.length
  };
}

// 4. Paged Data
const pagedActive = computed(() => getPaginatedData(activeList.value, 'active'));
const pagedInactive = computed(() => getPaginatedData(inactiveList.value, 'inactive'));

// --- WATCHERS ---
watch(searchQuery, () => {
  pagination.active.currentPage = 1;
  pagination.inactive.currentPage = 1;
});

// --- LIFECYCLE ---
onMounted(() => {
  fetchCustomers();
  if (customerModalRef.value) {
    customerModalInstance.value = new Modal(customerModalRef.value, { backdrop: 'static' });
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- API METHODS ---

async function fetchCustomers() {
  // Smart Spinner: Only show big spinner on initial load
  if (allCustomers.value.length === 0) {
    isLoading.value = true;
  }
  try {
    const response = await apiService.get(`admin/users`);
    
    allCustomers.value = response.data.map(customer => ({
      ...customer,
      fullName: customer.fullName || customer.name || 'Không tên', 
      status: customer.status || 'active',
      avatar_url: customer.avatar_url || customer.avatar || ''
    })).sort((a, b) => b.id - a.id); // Sort new to old

  } catch (error) {
    console.error("Lỗi tải danh sách khách hàng:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách khách hàng.', 'error');
  } finally {
    isLoading.value = false;
  }
}

// --- HELPER FUNCTIONS ---

function changePage(type, page) {
  pagination[type].currentPage = page;
}

function setActiveTab(tabName) {
  activeTab.value = tabName;
}

function formatDate(dateString) {
  if (!dateString) return 'Chưa cập nhật';
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  }).format(date);
}

function resetForm() {
  Object.assign(formData, {
    id: null, fullName: '', email: '', phone: '', address: '',
    avatar_url: '', status: 'active', password: '', password_confirmation: ''
  });
  avatarFile.value = null;
  avatarPreview.value = null;
  Object.keys(errors).forEach(key => errors[key] = '');
}

function onFileChange(e) {
  const file = e.target.files[0];
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      Swal.fire('Lỗi', 'Kích thước ảnh quá lớn (tối đa 5MB).', 'error');
      e.target.value = ''; 
      return;
    }
    avatarFile.value = file;
    const reader = new FileReader();
    reader.onload = (e) => { avatarPreview.value = e.target.result; };
    reader.readAsDataURL(file);
  }
}

function validateForm() {
  Object.keys(errors).forEach(key => errors[key] = '');
  let isValid = true;

  if (!formData.fullName.trim()) { errors.fullName = 'Vui lòng nhập họ và tên.'; isValid = false; }
  if (!formData.email) { errors.email = 'Vui lòng nhập email.'; isValid = false; }
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) { errors.email = 'Email không đúng định dạng.'; isValid = false; }
  
  if (formData.phone && !/^[0-9]{10,11}$/.test(formData.phone)) { errors.phone = 'Số điện thoại không hợp lệ (10-11 số).'; isValid = false; }

  if (!isEditMode.value) {
    if (!formData.password) { errors.password = 'Vui lòng nhập mật khẩu.'; isValid = false; }
    else if (formData.password.length < 6) { errors.password = 'Mật khẩu phải có ít nhất 6 ký tự.'; isValid = false; }
    if (formData.password !== formData.password_confirmation) { errors.password_confirmation = 'Mật khẩu xác nhận không khớp.'; isValid = false; }
  }
  return isValid;
}

// --- ACTIONS ---

function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  customerModalInstance.value.show();
}

function openEditModal(customer) {
  resetForm();
  isEditMode.value = true;
  
  Object.assign(formData, {
    id: customer.id,
    fullName: customer.fullName,
    email: customer.email,
    phone: customer.phone,
    address: customer.address,
    avatar_url: customer.avatar_url,
    status: customer.status || 'active'
  });
  
  avatarPreview.value = customer.avatar_url; 
  customerModalInstance.value.show();
}

function openViewModal(customer) {
  viewingCustomer.value = customer;
  viewModalInstance.value.show();
}

async function handleSave() {
  if (!validateForm()) return;

  const formDataPayload = new FormData();
  formDataPayload.append('fullName', formData.fullName); 
  formDataPayload.append('name', formData.fullName); 
  formDataPayload.append('email', formData.email);
  if (formData.phone) formDataPayload.append('phone', formData.phone);
  if (formData.address) formDataPayload.append('address', formData.address);
  if (formData.status) formDataPayload.append('status', formData.status);

  if (!isEditMode.value && formData.password) {
    formDataPayload.append('password', formData.password);
    formDataPayload.append('password_confirmation', formData.password_confirmation);
  }
  if (avatarFile.value) {
    formDataPayload.append('avatar', avatarFile.value);
  }

  try {
    if (isEditMode.value) {
      formDataPayload.append('_method', 'PATCH');
      await apiService.post(`admin/users/${formData.id}`, formDataPayload);
      Swal.fire('Thành công', 'Đã cập nhật thông tin khách hàng!', 'success');
    } else {
      await apiService.post(`admin/users`, formDataPayload);
      Swal.fire('Thành công', 'Đã thêm khách hàng mới!', 'success');
    }
    customerModalInstance.value.hide();
    fetchCustomers(); 
  } catch (error) {
    console.error("Lỗi khi lưu khách hàng:", error);
    const errorsData = error.response?.data?.errors;
    const firstError = errorsData ? Object.values(errorsData)[0][0] : (error.response?.data?.message || 'Đã có lỗi xảy ra.');
    Swal.fire('Lỗi', firstError, 'error');
  }
}

async function toggleCustomerStatus(customer) {
  const oldStatus = customer.status;
  const newStatus = customer.status === 'active' ? 'inactive' : 'active';
  const actionText = newStatus === 'inactive' ? 'khóa' : 'mở khóa';

  const result = await Swal.fire({
    title: 'Xác nhận thay đổi',
    text: `Bạn muốn ${actionText} tài khoản "${customer.fullName}"?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: newStatus === 'inactive' ? '#d33' : '#3085d6',
    confirmButtonText: 'Đồng ý',
    cancelButtonText: 'Hủy'
  });

  if (result.isConfirmed) {
    // 1. Optimistic Update (Cập nhật UI ngay lập tức)
    customer.status = newStatus;

    // 2. API Call
    try {
      await apiService.patch(`admin/users/${customer.id}`, { status: newStatus });
      
      const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true
      });
      Toast.fire({ icon: 'success', title: `Đã ${actionText} tài khoản` });

    } catch (error) {
      // Rollback nếu lỗi
      customer.status = oldStatus;
      console.error(`Lỗi khi ${actionText} khách hàng:`, error);
      Swal.fire('Lỗi', `Không thể ${actionText} tài khoản này.`, 'error');
    }
  }
}

async function handleDelete(customer) {
  const result = await Swal.fire({
    title: 'Xác nhận xóa?',
    text: `Bạn có chắc muốn xóa "${customer.fullName}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Xóa ngay'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`admin/users/${customer.id}`);
      
      // Xóa khỏi list local
      allCustomers.value = allCustomers.value.filter(c => c.id !== customer.id);
      
      Swal.fire('Đã xóa!', 'Tài khoản đã được xóa thành công.', 'success');
    } catch (error) {
      console.error("Lỗi khi xóa khách hàng:", error);
      Swal.fire('Lỗi', 'Không thể xóa khách hàng này.', 'error');
    }
  }
}
</script>

<template>
  <!-- HEADER -->
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Khách hàng</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">Khách hàng</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="app-content">
    <div class="container-fluid">
      <div class="card shadow-sm">
        <div class="card-header border-bottom-0 pb-0">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h3 class="card-title">Danh sách Khách hàng</h3>
            
            <!-- TOOLBAR -->
            <div class="d-flex gap-2 flex-grow-1 justify-content-end" style="max-width: 600px;">
              <div class="input-group" style="min-width: 200px;">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm theo tên, email, SĐT..." v-model="searchQuery">
              </div>
              <button class="btn btn-primary text-nowrap" @click="openCreateModal">
                <i class="bi bi-person-plus-fill me-1"></i> Thêm Tài khoản
              </button>
            </div>
          </div>

          <!-- TABS NAVIGATION -->
          <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'active' }" href="#" @click.prevent="setActiveTab('active')">
                <i class="bi bi-check-circle me-1 text-success"></i> Đang hoạt động
                <span class="badge rounded-pill bg-success ms-1">{{ activeList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'inactive' }" href="#" @click.prevent="setActiveTab('inactive')">
                <i class="bi bi-lock-fill me-1 text-danger"></i> Đã khóa
                <span class="badge rounded-pill bg-danger ms-1">{{ inactiveList.length }}</span>
              </a>
            </li>
          </ul>
        </div>

        <div class="card-body p-0">
          
          <!-- Loading Spinner -->
          <div v-if="isLoading && allCustomers.length === 0" class="text-center p-5">
            <div class="spinner-border text-primary" role="status"></div>
          </div>

          <div v-else class="tab-content p-0">
            
            <!-- TAB: ACTIVE -->
            <div class="tab-pane fade show active" v-if="activeTab === 'active'">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Khách hàng</th>
                      <th>Liên hệ</th>
                      <th>Ngày tạo</th>
                      <th class="text-center">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedActive.data.length === 0">
                      <td colspan="4" class="text-center py-4 text-muted">Không có tài khoản đang hoạt động.</td>
                    </tr>
                    <tr v-for="customer in pagedActive.data" :key="customer.id">
                      <td class="ps-3">
                        <div class="d-flex align-items-center">
                          <img :src="customer.avatar_url || `https://placehold.co/40x40/EBF4FF/1D62F0?text=${(customer.fullName ? customer.fullName.charAt(0) : 'U').toUpperCase()}`"
                            class="rounded-circle me-3 shadow-sm" alt="Avatar" style="width: 40px; height: 40px; object-fit: cover;">
                          <div>
                            <div class="fw-bold text-dark">{{ customer.fullName }}</div>
                            <div class="small text-muted">ID: {{ customer.id }}</div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="d-flex flex-column">
                          <span class="text-truncate" style="max-width: 200px;"><i class="bi bi-envelope me-2 text-muted"></i> {{ customer.email }}</span>
                          <span class="text-muted small mt-1"><i class="bi bi-telephone me-2 text-muted"></i> {{ customer.phone || '---' }}</span>
                        </div>
                      </td>
                      <td>
                        <span class="text-muted small">{{ formatDate(customer.created_at) }}</span>
                      </td>
                      <td class="text-center">
                        <div class="d-flex justify-content-center align-items-center">
                          <!-- Nút Gạt Toggle -->
                          <div class="form-check form-switch me-3" title="Khóa tài khoản">
                            <input class="form-check-input" type="checkbox" role="switch"
                              style="width: 2.5em; height: 1.25em; cursor: pointer;"
                              :checked="true"
                              @click.prevent="toggleCustomerStatus(customer)">
                          </div>
                          <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" title="Xem" @click="openViewModal(customer)"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-outline-primary" title="Sửa" @click="openEditModal(customer)"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(customer)"><i class="bi bi-trash"></i></button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination -->
              <div v-if="pagedActive.totalPages > 1" class="d-flex justify-content-end p-3">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: pagination.active.currentPage === 1 }">
                    <a class="page-link" href="#" @click.prevent="changePage('active', pagination.active.currentPage - 1)">&laquo;</a>
                  </li>
                  <li v-for="p in pagedActive.totalPages" :key="p" class="page-item" :class="{ active: pagination.active.currentPage === p }">
                    <a class="page-link" href="#" @click.prevent="changePage('active', p)">{{ p }}</a>
                  </li>
                  <li class="page-item" :class="{ disabled: pagination.active.currentPage === pagedActive.totalPages }">
                    <a class="page-link" href="#" @click.prevent="changePage('active', pagination.active.currentPage + 1)">&raquo;</a>
                  </li>
                </ul>
              </div>
            </div>

            <!-- TAB: INACTIVE (LOCKED) -->
            <div class="tab-pane fade show active" v-if="activeTab === 'inactive'">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3">Khách hàng</th>
                      <th>Liên hệ</th>
                      <th>Trạng thái</th>
                      <th class="text-center">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedInactive.data.length === 0">
                      <td colspan="4" class="text-center py-4 text-muted">Không có tài khoản bị khóa.</td>
                    </tr>
                    <tr v-for="customer in pagedInactive.data" :key="customer.id" class="bg-light text-muted">
                      <td class="ps-3">
                        <div class="d-flex align-items-center">
                          <img :src="customer.avatar_url || `https://placehold.co/40x40/EBF4FF/1D62F0?text=${(customer.fullName ? customer.fullName.charAt(0) : 'U').toUpperCase()}`"
                            class="rounded-circle me-3 grayscale" alt="Avatar" style="width: 40px; height: 40px; object-fit: cover;">
                          <div>
                            <div class="fw-bold">{{ customer.fullName }}</div>
                            <div class="small">ID: {{ customer.id }}</div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div><i class="bi bi-envelope me-2"></i> {{ customer.email }}</div>
                      </td>
                      <td><span class="badge bg-danger">Đã khóa</span></td>
                      <td class="text-center">
                        <div class="d-flex justify-content-center align-items-center">
                          <!-- Nút Gạt Toggle (Trạng thái tắt) -->
                          <div class="form-check form-switch me-3" title="Mở khóa tài khoản">
                            <input class="form-check-input" type="checkbox" role="switch"
                              style="width: 2.5em; height: 1.25em; cursor: pointer;"
                              :checked="false"
                              @click.prevent="toggleCustomerStatus(customer)">
                          </div>
                          <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" title="Xem" @click="openViewModal(customer)"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-outline-primary" title="Sửa" @click="openEditModal(customer)"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(customer)"><i class="bi bi-trash"></i></button>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination -->
              <div v-if="pagedInactive.totalPages > 1" class="d-flex justify-content-end p-3">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: pagination.inactive.currentPage === 1 }">
                    <a class="page-link" href="#" @click.prevent="changePage('inactive', pagination.inactive.currentPage - 1)">&laquo;</a>
                  </li>
                  <li v-for="p in pagedInactive.totalPages" :key="p" class="page-item" :class="{ active: pagination.inactive.currentPage === p }">
                    <a class="page-link" href="#" @click.prevent="changePage('inactive', p)">{{ p }}</a>
                  </li>
                  <li class="page-item" :class="{ disabled: pagination.inactive.currentPage === pagedInactive.totalPages }">
                    <a class="page-link" href="#" @click.prevent="changePage('inactive', pagination.inactive.currentPage + 1)">&raquo;</a>
                  </li>
                </ul>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Create/Edit Modal -->
  <div class="modal fade" id="customerModal" ref="customerModalRef" tabindex="-1" aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ isEditMode ? 'Cập nhật Khách hàng' : 'Thêm mới Tài khoản' }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSave" id="customerForm">
            <div class="row">
              <!-- Left Column: Avatar & Basic Info -->
              <div class="col-md-4 text-center mb-3">
                <div class="mb-3">
                  <img :src="avatarPreview || 'https://placehold.co/150x150?text=Avatar'"
                    class="img-thumbnail rounded-circle" alt="Avatar Preview"
                    style="width: 150px; height: 150px; object-fit: cover;">
                </div>
                <div class="mb-3">
                  <label for="avatarInput" class="form-label btn btn-sm btn-outline-primary">
                    <i class="bi bi-camera-fill"></i> Chọn ảnh
                  </label>
                  <input type="file" class="d-none" id="avatarInput" accept="image/*" @change="onFileChange">
                </div>
                <!-- Status in Edit Mode -->
                <div class="mb-3" v-if="isEditMode">
                  <label for="status" class="form-label fw-bold">Trạng thái</label>
                  <select class="form-select" id="status" v-model="formData.status">
                    <option value="active">Hoạt động</option>
                    <option value="inactive">Đã khóa</option>
                  </select>
                </div>
              </div>

              <!-- Right Column: Personal Details -->
              <div class="col-md-8">
                <!-- Full Name -->
                <div class="mb-3">
                  <label for="fullName" class="form-label required">Họ và tên</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.fullName }" id="fullName"
                    v-model="formData.fullName">
                  <div class="invalid-feedback">{{ errors.fullName }}</div>
                </div>
                
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="email" class="form-label required">Email</label>
                    <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" id="email"
                      v-model="formData.email" :readonly="isEditMode">
                    <div class="invalid-feedback">{{ errors.email }}</div>
                    <div v-if="isEditMode" class="form-text">Không thể thay đổi email.</div>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Số điện thoại</label>
                    <input type="tel" class="form-control" :class="{ 'is-invalid': errors.phone }" id="phone"
                      v-model="formData.phone">
                    <div class="invalid-feedback">{{ errors.phone }}</div>
                  </div>
                </div>

                <!-- PASSWORD SECTION (Chỉ hiện khi thêm mới) -->
                <div class="row" v-if="!isEditMode">
                  <div class="col-md-6 mb-3">
                    <label for="password" class="form-label required">Mật khẩu</label>
                    <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" id="password"
                      v-model="formData.password" placeholder="Nhập mật khẩu...">
                    <div class="invalid-feedback">{{ errors.password }}</div>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="password_confirmation" class="form-label required">Xác nhận mật khẩu</label>
                    <input type="password" class="form-control" :class="{ 'is-invalid': errors.password_confirmation }"
                      id="password_confirmation" v-model="formData.password_confirmation" placeholder="Nhập lại mật khẩu...">
                    <div class="invalid-feedback">{{ errors.password_confirmation }}</div>
                  </div>
                </div>

                <div class="mb-3">
                  <label for="address" class="form-label">Địa chỉ</label>
                  <textarea class="form-control" id="address" v-model="formData.address" rows="2"></textarea>
                </div>
              </div>
            </div>

          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="customerForm" class="btn btn-primary" :disabled="isLoading">
            <span v-if="isLoading" class="spinner-border spinner-border-sm me-1" role="status"
              aria-hidden="true"></span>
            {{ isEditMode ? 'Lưu thay đổi' : 'Tạo tài khoản' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Details Modal -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <!-- Status Badge -->
          <div class="position-absolute top-0 start-0 m-3">
            <span :class="['badge', viewingCustomer.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
              {{ viewingCustomer.status === 'active' ? 'Đang hoạt động' : 'Đã khóa' }}
            </span>
          </div>

          <div class="text-center mb-4 mt-3">
            <img
              :src="viewingCustomer.avatar_url || 'https://placehold.co/120x120?text=' + (viewingCustomer.fullName ? viewingCustomer.fullName.charAt(0).toUpperCase() : 'U')"
              class="rounded-circle img-thumbnail shadow-sm" alt="Avatar"
              style="width: 120px; height: 120px; object-fit: cover;">
            <h4 class="mt-3 mb-1">{{ viewingCustomer.fullName }}</h4>
            <p class="text-muted mb-0">ID: {{ viewingCustomer.id }}</p>
          </div>
          
          <!-- Thông tin chi tiết đầy đủ theo Schema -->
          <div class="list-group list-group-flush">
            <div class="list-group-item px-0 d-flex justify-content-between">
              <span><i class="bi bi-envelope me-2 text-primary"></i> Email:</span>
              <span class="fw-medium">{{ viewingCustomer.email }}</span>
            </div>
            <div class="list-group-item px-0 d-flex justify-content-between">
              <span><i class="bi bi-check-circle me-2 text-info"></i> Xác thực Email:</span>
              <span class="fw-medium">{{ viewingCustomer.email_verified_at ? formatDate(viewingCustomer.email_verified_at) : 'Chưa xác thực' }}</span>
            </div>
            <div class="list-group-item px-0 d-flex justify-content-between">
              <span><i class="bi bi-telephone me-2 text-success"></i> Số điện thoại:</span>
              <span class="fw-medium">{{ viewingCustomer.phone || 'Trống' }}</span>
            </div>
            <div class="list-group-item px-0 d-flex justify-content-between">
              <span><i class="bi bi-calendar-plus me-2 text-secondary"></i> Ngày tạo:</span>
              <span class="fw-medium">{{ formatDate(viewingCustomer.created_at) }}</span>
            </div>
            <div class="list-group-item px-0 d-flex justify-content-between">
              <span><i class="bi bi-calendar-check me-2 text-secondary"></i> Cập nhật lần cuối:</span>
              <span class="fw-medium">{{ formatDate(viewingCustomer.updated_at) }}</span>
            </div>
            <div class="list-group-item px-0">
              <div><i class="bi bi-geo-alt me-2 text-danger"></i> Địa chỉ:</div>
              <div class="mt-1 text-muted fst-italic">{{ viewingCustomer.address || 'Chưa cập nhật địa chỉ' }}</div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-primary px-4"
            @click="() => { viewModalInstance.hide(); openEditModal(viewingCustomer); }">
            <i class="bi bi-pencil me-2"></i> Chỉnh sửa thông tin
          </button>
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
.grayscale {
  filter: grayscale(100%);
  opacity: 0.6;
}
</style>