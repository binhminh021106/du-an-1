<script setup>
import { ref, reactive, onMounted, computed, nextTick, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

const users = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const currentAdminId = ref(null); // Lưu ID người đăng nhập để check quyền

// --- Modals ---
const userModalInstance = ref(null);
const userModalRef = ref(null);
const isEditMode = ref(false);

const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingUser = ref({});

const roleModalInstance = ref(null);
const roleModalRef = ref(null);
const isRoleEditMode = ref(false);

// --- Upload Ảnh ---
const fileInputRef = ref(null);
const previewImage = ref(null);
const avatarFile = ref(null);

// --- Forms ---
const formData = reactive({
  id: null,
  fullname: '',
  email: '',
  phone: '',
  address: '',
  role_id: '',
  status: 'active',
  password: '',
  password_confirmation: ''
});

const errors = reactive({
  fullname: '',
  email: '',
  phone: '',
  address: '',
  password: '',
  password_confirmation: ''
});

// Role Form
const roleFormData = reactive({
  id: null,
  value: '',
  label: '',
  badgeClass: 'text-bg-secondary',
});

const roleErrors = reactive({
  value: '',
  label: '',
});

// --- Roles & Pagination ---
const roles = ref([]);
const otherUsersCurrentPage = ref(1);
const otherUsersItemsPerPage = ref(5);

// --- Computed ---
const getRoleObj = (roleId) => {
  if (!roleId) return {};
  return roles.value.find(r => r.id == roleId) || {};
};

// 1. CẬP NHẬT: Sắp xếp danh sách, đưa user hiện tại lên đầu
const filteredUsers = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  let result = users.value;

  // Lọc theo từ khóa
  if (query) {
    result = users.value.filter(user =>
      (user.fullname && user.fullname.toLowerCase().includes(query)) ||
      (user.email && user.email.toLowerCase().includes(query)) ||
      (user.phone && user.phone.toLowerCase().includes(query)) ||
      getRoleLabel(user.role_id).toLowerCase().includes(query)
    );
  }

  // Sắp xếp: Đưa người dùng hiện tại lên đầu danh sách
  // Sử dụng [...result] để tạo bản sao trước khi sort
  return [...result].sort((a, b) => {
      if (a.id === currentAdminId.value) return -1; // a lên trước
      if (b.id === currentAdminId.value) return 1;  // b lên trước
      return 0; // Giữ nguyên thứ tự (theo created_at từ API)
  });
});

const adminUsers = computed(() => {
  return filteredUsers.value.filter(user => {
    const roleObj = getRoleObj(user.role_id);
    return roleObj.value === 'admin';
  });
});

const otherUsers = computed(() => {
  return filteredUsers.value.filter(user => {
    const roleObj = getRoleObj(user.role_id);
    return roleObj.value !== 'admin';
  });
});

const otherUsersTotalPages = computed(() => {
  return Math.ceil(otherUsers.value.length / otherUsersItemsPerPage.value);
});

const paginatedOtherUsers = computed(() => {
  const start = (otherUsersCurrentPage.value - 1) * otherUsersItemsPerPage.value;
  const end = start + otherUsersItemsPerPage.value;
  return otherUsers.value.slice(start, end);
});

watch(searchQuery, () => {
  otherUsersCurrentPage.value = 1;
});

// --- Hàm Helper ---
function getRoleBadge(roleId) {
  const role = roles.value.find(r => r.id == roleId);
  return role ? role.badgeClass : 'text-bg-secondary';
}

function getRoleLabel(roleId) {
  const role = roles.value.find(r => r.id == roleId);
  return role ? role.label : 'Chưa phân quyền';
}

function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
}

// --- Hooks ---
onMounted(() => {
  fetchCurrentUser(); 
  fetchRoles().then(() => {
    fetchUsers();
  });

  if (userModalRef.value) {
    userModalInstance.value = new Modal(userModalRef.value, { backdrop: 'static' });
  }
  if (roleModalRef.value) {
    roleModalInstance.value = new Modal(roleModalRef.value);
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// Lấy ID admin đang đăng nhập
async function fetchCurrentUser() {
  try {
    const response = await apiService.get('/user');
    if (response.data && response.data.id) {
      currentAdminId.value = response.data.id;
    }
  } catch (e) {
    console.error("Không thể lấy thông tin user hiện tại", e);
  }
}

// --- CRUD User ---
async function fetchUsers() {
  isLoading.value = true;
  try {
    const response = await apiService.get(`admin/admins`);
    users.value = response.data.map(user => ({
      ...user,
      fullname: user.fullname || 'No Name',
      role_id: user.role_id,
      created_at: user.created_at || new Date().toISOString(),
      status: user.status || 'active'
    }));
    otherUsersCurrentPage.value = 1;
  } catch (error) {
    console.error("Lỗi khi tải danh sách người dùng:", error);
  } finally {
    isLoading.value = false;
  }
}

function resetForm() {
  formData.id = null;
  formData.fullname = '';
  formData.email = '';
  formData.phone = '';
  formData.address = '';
  formData.role_id = roles.value.length > 0 ? roles.value[0].id : '';
  formData.status = 'active';
  formData.password = '';
  formData.password_confirmation = '';
  
  // Reset ảnh
  previewImage.value = null;
  avatarFile.value = null;
  if (fileInputRef.value) fileInputRef.value.value = '';

  Object.keys(errors).forEach(key => errors[key] = '');
}

// Xử lý chọn file ảnh
function handleFileUpload(event) {
  const file = event.target.files[0];
  if (file) {
    if (!file.type.match('image.*')) {
      Swal.fire('Lỗi', 'Vui lòng chọn file hình ảnh (jpg, png, gif).', 'warning');
      return;
    }
    avatarFile.value = file;
    previewImage.value = URL.createObjectURL(file);
  }
}

function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  userModalInstance.value.show();
}

function openEditModal(user) {
  resetForm();
  isEditMode.value = true;
  formData.id = user.id;
  formData.fullname = user.fullname;
  formData.email = user.email;
  formData.phone = user.phone || '';
  formData.address = user.address || '';
  formData.role_id = user.role_id;
  formData.status = user.status;
  
  previewImage.value = user.avatar_url; 
  
  userModalInstance.value.show();
}

function openViewModal(user) {
  viewingUser.value = user;
  viewModalInstance.value.show();
}

function validateForm() {
  Object.keys(errors).forEach(key => errors[key] = '');
  let isValid = true;

  if (!formData.fullname.trim()) {
    errors.fullname = 'Vui lòng nhập họ và tên.';
    isValid = false;
  }

  if (!formData.email) {
    errors.email = 'Vui lòng nhập email.';
    isValid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    errors.email = 'Email không đúng định dạng.';
    isValid = false;
  }

  if (!formData.role_id) {
    Swal.fire('Lỗi', 'Vui lòng chọn vai trò.', 'warning');
    isValid = false;
  }

  if (formData.phone && formData.phone.trim() && !/^0\d{9}$/.test(formData.phone.trim())) {
    errors.phone = 'SĐT không hợp lệ (phải đủ 10 số, bắt đầu bằng 0).';
    isValid = false;
  }

  if (formData.address && formData.address.trim().length > 255) {
    errors.address = 'Địa chỉ không được vượt quá 255 ký tự.';
    isValid = false;
  }

  if (!isEditMode.value) {
    if (!formData.password) {
      errors.password = 'Vui lòng nhập mật khẩu.';
      isValid = false;
    } else if (formData.password.length < 6) {
      errors.password = 'Mật khẩu phải có ít nhất 6 ký tự.';
      isValid = false;
    }

    if (!formData.password_confirmation) {
      errors.password_confirmation = 'Vui lòng nhập lại mật khẩu để xác nhận.';
      isValid = false;
    } else if (formData.password && formData.password_confirmation !== formData.password) {
      errors.password_confirmation = 'Mật khẩu xác nhận không khớp.';
      isValid = false;
    }
  }

  return isValid;
}

async function handleSave() {
  if (!validateForm()) {
    return;
  }
  isLoading.value = true;

  const submitData = new FormData();
  submitData.append('fullname', formData.fullname);
  submitData.append('email', formData.email);
  submitData.append('phone', formData.phone || '');
  submitData.append('role_id', formData.role_id);
  submitData.append('status', formData.status);
  submitData.append('address', formData.address || '');
  
  if (avatarFile.value) {
    submitData.append('avatar', avatarFile.value);
  }

  if (!isEditMode.value) {
    submitData.append('password', formData.password);
    submitData.append('password_confirmation', formData.password_confirmation);
  }

  try {
    if (isEditMode.value) {
      submitData.append('_method', 'PATCH');
      await apiService.post(`admin/admins/${formData.id}`, submitData, {
         headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã cập nhật thông tin!', 'success');
    } else {
      await apiService.post(`admin/admins`, submitData, {
         headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã tạo tài khoản mới!', 'success');
    }
    userModalInstance.value.hide();
    fetchUsers();
  } catch (apiError) {
    console.error("Lỗi khi lưu:", apiError);

    if (apiError.response) {
      const status = apiError.response.status;
      const data = apiError.response.data;

      if (status === 422 && data.errors) {
        if (data.errors.email) errors.email = data.errors.email[0];
        if (data.errors.fullname) errors.fullname = data.errors.fullname[0];
        if (data.errors.phone) errors.phone = data.errors.phone[0];
        if (data.errors.password) errors.password = data.errors.password[0];

        Swal.fire('Dữ liệu không hợp lệ', 'Vui lòng kiểm tra lại các trường báo đỏ.', 'warning');
      }
      else if (status === 500) {
        const errorMessage = data.message || 'Lỗi Server nội bộ.';
        Swal.fire({
          icon: 'error',
          title: 'Lỗi Server (500)',
          text: errorMessage,
          footer: '<small>Kiểm tra lại cấu trúc Database hoặc Controller</small>'
        });
      }
      else if (status === 403) {
        Swal.fire('Cảnh báo', data.message || 'Bạn không có quyền thực hiện.', 'warning');
      }
      else {
        Swal.fire('Thất bại', data.message || 'Đã có lỗi xảy ra.', 'error');
      }
    } else {
      Swal.fire('Lỗi kết nối', 'Không thể kết nối đến server.', 'error');
    }
  } finally {
    isLoading.value = false;
  }
}

async function toggleUserStatus(user) {
  if (user.id === currentAdminId.value) {
     Swal.fire('Cảnh báo', 'Bạn không thể tự vô hiệu hóa chính mình.', 'warning');
     return;
  }

  const newStatus = user.status === 'active' ? 'inactive' : 'active';
  const actionText = newStatus === 'inactive' ? 'vô hiệu hóa' : 'kích hoạt';

  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn muốn ${actionText} tài khoản của "${user.fullname}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Đồng ý',
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    isLoading.value = true;
    try {
      await apiService.patch(`admin/admins/${user.id}`, { status: newStatus });
      Swal.fire('Thành công!', `Đã ${actionText} người dùng.`, 'success');
      user.status = newStatus;
    } catch (error) {
      console.error(`Lỗi khi ${actionText} người dùng:`, error);
      Swal.fire('Lỗi', `Không thể ${actionText} người dùng này.`, 'error');
      fetchUsers();
    } finally {
      isLoading.value = false;
    }
  }
}

async function handleDelete(user) {
  if (user.id === currentAdminId.value) {
      Swal.fire('Cảnh báo', 'Bạn không thể tự xóa tài khoản chính mình.', 'warning');
      return;
  }

  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn sẽ xóa vĩnh viễn tài khoản "${user.fullname}"!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Đồng ý xóa!',
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`admin/admins/${user.id}`);
      Swal.fire('Đã xóa!', 'Tài khoản đã được xóa.', 'success');
      fetchUsers();
    } catch (error) {
      console.error("Lỗi khi xóa người dùng:", error);
      Swal.fire('Lỗi', 'Không thể xóa tài khoản này.', 'error');
    }
  }
}

// --- CRUD Vai trò ---
async function fetchRoles() {
  try {
    const response = await apiService.get(`admin/roles`);
    roles.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải danh sách vai trò:", error);
    roles.value = [];
  }
}

function resetRoleForm() {
  roleFormData.id = null;
  roleFormData.value = '';
  roleFormData.label = '';
  roleFormData.badgeClass = 'text-bg-secondary';
  Object.keys(roleErrors).forEach(key => roleErrors[key] = '');
}

function validateRoleForm() {
  Object.keys(roleErrors).forEach(key => roleErrors[key] = '');
  let isValid = true;
  if (!roleFormData.value.trim()) {
    roleErrors.value = 'Vui lòng nhập giá trị vai trò (viết liền không dấu).';
    isValid = false;
  } else if (!/^[a-z0-9]+$/.test(roleFormData.value)) {
    roleErrors.value = 'Giá trị chỉ được dùng chữ thường không dấu và số.';
    isValid = false;
  }
  if (!roleFormData.label.trim()) {
    roleErrors.label = 'Vui lòng nhập nhãn hiển thị.';
    isValid = false;
  }
  return isValid;
}

function openCreateRoleModal() {
  resetRoleForm();
  isRoleEditMode.value = false;
  userModalInstance.value?.hide();
  roleModalInstance.value.show();
}

function openEditRoleModal(role) {
  resetRoleForm();
  isRoleEditMode.value = true;
  roleFormData.id = role.id;
  roleFormData.value = role.value;
  roleFormData.label = role.label;
  roleFormData.badgeClass = role.badgeClass;
  userModalInstance.value?.hide();
  nextTick(() => {
    roleModalInstance.value.show();
  });
}

async function handleSaveRole() {
  if (!validateRoleForm()) {
    return;
  }
  const payload = {
    value: roleFormData.value.toLowerCase(),
    label: roleFormData.label,
    badgeClass: roleFormData.badgeClass,
  };
  try {
    if (isRoleEditMode.value) {
      await apiService.put(`admin/roles/${roleFormData.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật vai trò!', 'success');
    } else {
      await apiService.post(`admin/roles`, payload);
      Swal.fire('Thành công', 'Đã tạo vai trò mới!', 'success');
    }
    roleModalInstance.value.hide();
    fetchRoles();
  } catch (apiError) {
    console.error("Lỗi khi lưu vai trò:", apiError);
    Swal.fire('Thất bại', 'Đã có lỗi xảy ra khi lưu vai trò.', 'error');
  }
}

async function handleDeleteRole(role) {
  if (role.value === 'admin' || role.value === 'user') {
    Swal.fire('Không thể xóa', `Không được phép xóa vai trò "${role.label}".`, 'error');
    return;
  }
  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn sẽ xóa vĩnh viễn vai trò "${role.label}"!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Đồng ý xóa!',
    cancelButtonText: 'Hủy bỏ'
  });
  if (result.isConfirmed) {
    try {
      await apiService.delete(`admin/roles/${role.id}`);
      Swal.fire('Đã xóa!', 'Vai trò đã được xóa.', 'success');
      fetchRoles();
    } catch (error) {
      console.error("Lỗi khi xóa vai trò:", error);
      Swal.fire('Lỗi', 'Không thể xóa vai trò này.', 'error');
    }
  }
}
</script>

<template>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Nhân sự (Nội bộ)</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">
              Tài khoản nội bộ
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">

      <!-- Card điều khiển (Tìm kiếm + Nút) -->
      <div class="card mb-4">
        <div class="card-header">
          <div class="row align-items-center">
            <!-- Cột tìm kiếm bên trái -->
            <div class="col-md-6 col-12 mb-2 mb-md-0">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm theo họ tên, email, SĐT, vai trò..." v-model="searchQuery">
              </div>
            </div>

            <!-- Cột nút bên phải -->
            <div class="col-md-6 col-12 text-md-end">
              <button type="button" class="btn btn-outline-info me-2" @click="openCreateRoleModal">
                <i class="bi bi-people-fill"></i> Quản lý Vai trò
              </button>
              <button type="button" class="btn btn-primary" @click="openCreateModal">
                <i class="bi bi-plus-lg"></i> Thêm mới Tài khoản
              </button>
            </div>

          </div>
        </div>
      </div>

      <div v-if="isLoading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>

      <div v-else>
        <!-- BẢNG ADMIN -->
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Danh sách Quản trị viên</h3>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-bordered table-hover align-middle">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Họ và tên</th>
                        <th>Liên hệ</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th class="text-center">Hành động</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="adminUsers.length === 0">
                        <td colspan="7" class="text-center">
                          {{ searchQuery ? 'Không tìm thấy quản trị viên phù hợp' : 'Không có quản trị viên nào' }}
                        </td>
                      </tr>
                      <tr v-for="user in adminUsers" :key="user.id"
                        :class="{ 
                          'inactive-row': user.status !== 'active',
                          'current-user-row': user.id === currentAdminId 
                        }">
                        <td>{{ user.id }}</td>
                        <td>
                          <div class="d-flex align-items-center">
                            <img
                              :src="user.avatar_url || `https://placehold.co/40x40/EBF4FF/1D62F0?text=${(user.fullname || 'U').charAt(0).toUpperCase()}`"
                              class="rounded-circle me-3" alt="Avatar"
                              style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                              <div class="fw-bold">
                                {{ user.fullname }}
                                <span v-if="user.id === currentAdminId" class="badge bg-primary ms-1">Bạn</span>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <div v-if="user.email"><i class="bi bi-envelope me-1 text-muted"></i> {{ user.email }}</div>
                          <div v-if="user.phone"><i class="bi bi-telephone me-1 text-muted"></i> {{ user.phone }}</div>
                        </td>
                        <td>
                          <span :class="['badge', getRoleBadge(user.role_id)]">{{ getRoleLabel(user.role_id) }}</span>
                        </td>
                        <td>
                          <span :class="['badge', user.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
                            {{ user.status === 'active' ? 'Đang hoạt động' : 'Vô hiệu hóa' }}
                          </span>
                        </td>
                        <td>{{ getFormattedDate(user.created_at) }}</td>
                        <td class="text-center">
                          <div class="d-flex justify-content-center align-items-center">
                            <!-- SWITCH STATUS: Disable nếu là user hiện tại -->
                            <div class="form-check form-switch d-inline-block align-middle me-3"
                              title="Kích hoạt/Vô hiệu hóa">
                              <input class="form-check-input" type="checkbox" role="switch"
                                style="width: 2.5em; height: 1.25em; cursor: pointer;"
                                :checked="user.status === 'active'"
                                :disabled="user.id === currentAdminId"
                                @click.prevent="toggleUserStatus(user)">
                            </div>

                            <div class="btn-group btn-group-sm">
                              <button class="btn btn-outline-secondary" title="Xem chi tiết"
                                @click="openViewModal(user)">
                                <i class="bi bi-eye"></i>
                              </button>
                              <button  class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditModal(user)">
                                <i class="bi bi-pencil"></i>
                              </button>
                              <button v-if="user.id !== currentAdminId" class="btn btn-outline-danger" title="Xóa" @click="handleDelete(user)">
                                <i class="bi bi-trash"></i>
                              </button>
                            </div>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- BẢNG USER KHÁC (NHANVIEN, KETOAN...) -->
        <div class="row mt-4">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Danh sách Nhân viên & Vai trò khác</h3>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-bordered table-hover align-middle">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Họ và tên</th>
                        <th>Liên hệ</th>
                        <th>Vai trò</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th class="text-center">Hành động</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="otherUsers.length === 0">
                        <td colspan="7" class="text-center">
                          {{ searchQuery ? 'Không tìm thấy người dùng phù hợp' : 'Không có người dùng nào khác' }}
                        </td>
                      </tr>
                      <tr v-for="user in paginatedOtherUsers" :key="user.id"
                        :class="{ 
                          'inactive-row': user.status !== 'active',
                          'current-user-row': user.id === currentAdminId 
                        }">
                        <td>{{ user.id }}</td>
                        <td>
                          <div class="d-flex align-items-center">
                            <img
                              :src="user.avatar_url || `https://placehold.co/40x40/EBF4FF/1D62F0?text=${(user.fullname || 'U').charAt(0).toUpperCase()}`"
                              class="rounded-circle me-3" alt="Avatar"
                              style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                              <div class="fw-bold">
                                {{ user.fullname }}
                                <span v-if="user.id === currentAdminId" class="badge bg-primary ms-1">Bạn</span>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <div v-if="user.email"><i class="bi bi-envelope me-1 text-muted"></i> {{ user.email }}</div>
                          <div v-if="user.phone"><i class="bi bi-telephone me-1 text-muted"></i> {{ user.phone }}</div>
                        </td>
                        <td>
                          <span :class="['badge', getRoleBadge(user.role_id)]">{{ getRoleLabel(user.role_id) }}</span>
                        </td>
                        <td>
                          <span :class="['badge', user.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
                            {{ user.status === 'active' ? 'Đang hoạt động' : 'Vô hiệu hóa' }}
                          </span>
                        </td>
                        <td>{{ getFormattedDate(user.created_at) }}</td>
                        <td class="text-center">
                          <div class="d-flex justify-content-center align-items-center">
                            <div class="form-check form-switch d-inline-block align-middle me-3"
                              title="Kích hoạt/Vô hiệu hóa">
                              <input class="form-check-input" type="checkbox" role="switch"
                                style="width: 2.5em; height: 1.25em; cursor: pointer;"
                                :checked="user.status === 'active'"
                                :disabled="user.id === currentAdminId"
                                @click.prevent="toggleUserStatus(user)">
                            </div>

                            <div class="btn-group btn-group-sm">
                              <button class="btn btn-outline-secondary" title="Xem chi tiết"
                                @click="openViewModal(user)">
                                <i class="bi bi-eye"></i>
                              </button>
                              <button v-if="user.id !== currentAdminId" class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditModal(user)">
                                <i class="bi bi-pencil"></i>
                              </button>
                              <button v-if="user.id !== currentAdminId" class="btn btn-outline-danger" title="Xóa" @click="handleDelete(user)">
                                <i class="bi bi-trash"></i>
                              </button>
                            </div>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- PHÂN TRANG CHO USER KHÁC -->
              <div class="card-footer clearfix" v-if="otherUsersTotalPages > 1">
                <nav aria-label="Page navigation">
                  <ul class="pagination pagination-sm m-0 float-end">
                    <li class="page-item" :class="{ disabled: otherUsersCurrentPage === 1 }">
                      <a class="page-link" href="#" @click.prevent="prevOtherUsersPage">&laquo;</a>
                    </li>
                    <li v-for="page in otherUsersTotalPages" :key="page" class="page-item"
                      :class="{ active: page === otherUsersCurrentPage }">
                      <a class="page-link" href="#" @click.prevent="goToOtherUsersPage(page)">{{ page }}</a>
                    </li>
                    <li class="page-item" :class="{ disabled: otherUsersCurrentPage === otherUsersTotalPages }">
                      <a class="page-link" href="#" @click.prevent="nextOtherUsersPage">&raquo;</a>
                    </li>
                  </ul>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal User (Thêm/Sửa) -->
  <div class="modal fade" id="userModal" ref="userModalRef" tabindex="-1" aria-labelledby="userModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="userModalLabel">
            {{ isEditMode ? 'Chỉnh sửa Tài khoản' : 'Tạo Tài khoản mới' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSave" id="userForm">
            <div class="row">
              <!-- Cột trái: Avatar, Vai trò, Trạng thái -->
              <div class="col-md-4 text-center mb-3">
                <!-- AVATAR UPLOAD -->
                <div class="mb-3">
                  <img
                    :src="previewImage || `https://placehold.co/150x150/EBF4FF/1D62F0?text=${formData.fullname ? formData.fullname.charAt(0).toUpperCase() : '?'}`"
                    class="img-thumbnail rounded-circle mb-2" alt="Avatar Preview"
                    style="width: 150px; height: 150px; object-fit: cover; cursor: pointer;"
                    @click="$refs.fileInputRef.click()"
                  >
                  <div>
                    <input type="file" class="d-none" ref="fileInputRef" accept="image/*" @change="handleFileUpload">
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" @click="$refs.fileInputRef.click()">
                      <i class="bi bi-upload"></i> Chọn ảnh
                    </button>
                  </div>
                </div>

                <div class="mb-3">
                  <label for="role" class="form-label fw-bold required">Vai trò</label>
                  <select class="form-select" id="role" v-model="formData.role_id"
                    :disabled="isEditMode && formData.id === currentAdminId">
                    <option value="" disabled>-- Chọn vai trò --</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id">
                      {{ role.label }}
                    </option>
                  </select>
                  <div v-if="isEditMode && formData.id === currentAdminId" class="form-text text-warning">
                      <i class="bi bi-exclamation-triangle"></i> Không thể tự đổi quyền.
                  </div>
                </div>
                <div class="mb-3" v-if="isEditMode">
                  <label for="status" class="form-label fw-bold">Trạng thái tài khoản</label>
                  <select class="form-select" id="status" v-model="formData.status"
                    :disabled="isEditMode && formData.id === currentAdminId">
                    <option value="active">Đang hoạt động</option>
                    <option value="inactive">Vô hiệu hóa</option>
                  </select>
                  <div v-if="isEditMode && formData.id === currentAdminId" class="form-text text-warning">
                      <i class="bi bi-exclamation-triangle"></i> Không thể tự vô hiệu hóa.
                  </div>
                </div>
              </div>

              <!-- Cột phải: Thông tin -->
              <div class="col-md-8">
                
                <div class="mb-3">
                  <label for="fullname" class="form-label required">Họ và tên</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.fullname }" id="fullname"
                    v-model="formData.fullname" placeholder="Nhập họ tên đầy đủ">
                   <div class="invalid-feedback" v-if="errors.fullname">{{ errors.fullname }}</div>
                </div>

                <div class="mb-3">
                  <label for="email" class="form-label required">Email</label>
                  <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" id="email"
                    v-model="formData.email" :readonly="isEditMode">
                  <div class="invalid-feedback" v-if="errors.email">{{ errors.email }}</div>
                  <div v-if="isEditMode" class="form-text">Không thể thay đổi email.</div>
                </div>

                <!-- THÊM TRƯỜNG MẬT KHẨU (CHỈ KHI TẠO MỚI) -->
                <template v-if="!isEditMode">
                  <div class="mb-3">
                    <label for="password" class="form-label required">Mật khẩu</label>
                    <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }"
                      id="password" v-model="formData.password" autocomplete="new-password">
                    <div class="invalid-feedback" v-if="errors.password">{{ errors.password }}</div>
                    <div class="form-text">(Ít nhất 6 ký tự)</div>
                  </div>
                  <div class="mb-3">
                    <label for="password_confirmation" class="form-label required">Xác nhận Mật khẩu</label>
                    <input type="password" class="form-control"
                      :class="{ 'is-invalid': errors.password_confirmation }" id="password_confirmation"
                      v-model="formData.password_confirmation" autocomplete="new-password">
                    <div class="invalid-feedback" v-if="errors.password_confirmation">{{
                      errors.password_confirmation }}</div>
                  </div>
                </template>
                <!-- KẾT THÚC TRƯỜNG MẬT KHẨU -->

                <div class="mb-3">
                  <label for="phone" class="form-label">Số điện thoại</label>
                  <input type="tel" class="form-control" :class="{ 'is-invalid': errors.phone }" id="phone"
                    v-model="formData.phone" placeholder="09xxxxxxxx">
                  <div class="invalid-feedback" v-if="errors.phone">{{ errors.phone }}</div>
                  <div class="form-text">(Không bắt buộc)</div>
                </div>

                <div class="mb-3">
                  <label for="address" class="form-label">Địa chỉ</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.address }" id="address"
                    v-model="formData.address" placeholder="Nhập địa chỉ...">
                  <div class="invalid-feedback" v-if="errors.address">{{ errors.address }}</div>
                  <div class="form-text">(Không bắt buộc)</div>
                </div>

              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="userForm" class="btn btn-primary" :disabled="isLoading">
            <span v-if="isLoading" class="spinner-border spinner-border-sm me-1" role="status"
              aria-hidden="true"></span>
            {{ isEditMode ? 'Lưu thay đổi' : 'Tạo tài khoản' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Xem Chi Tiết -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <div class="position-absolute top-0 start-0 m-3">
            <span :class="['badge', viewingUser.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
              {{ viewingUser.status === 'active' ? 'Đang hoạt động' : 'Vô hiệu hóa' }}
            </span>
          </div>

          <div class="text-center mb-4 mt-3">
            <img
              :src="viewingUser.avatar_url || `https://placehold.co/120x120/EBF4FF/1D62F0?text=${viewingUser.fullname ? viewingUser.fullname.charAt(0).toUpperCase() : 'U'}`"
              class="rounded-circle img-thumbnail shadow-sm" alt="Avatar"
              style="width: 120px; height: 120px; object-fit: cover;">
            <h4 class="mt-3 mb-1">{{ viewingUser.fullname }}</h4>
            <p class="text-muted mb-0">ID: {{ viewingUser.id }}</p>
            <p class="text-muted small">{{ viewingUser.email }}</p>
          </div>
          <div class="list-group list-group-flush">
            <div class="list-group-item px-0">
              <i class="bi bi-telephone me-3 text-success"></i> {{ viewingUser.phone || '(Chưa có SĐT)' }}
            </div>
            <div class="list-group-item px-0">
              <i class="bi bi-geo-alt me-3 text-danger"></i> {{ viewingUser.address || '(Chưa có địa chỉ)' }}
            </div>
            <div class="list-group-item px-0">
              <i class="bi bi-person-badge me-3 text-info"></i>
              <span :class="['badge', getRoleBadge(viewingUser.role_id)]">
                {{ getRoleLabel(viewingUser.role_id) }}
              </span>
            </div>
            <div class="list-group-item px-0">
              <i class="bi bi-calendar-event me-3 text-muted"></i>
              Ngày tạo: {{ getFormattedDate(viewingUser.created_at) }}
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-primary px-4"
            @click="() => { viewModalInstance.hide(); openEditModal(viewingUser); }">
            <i class="bi bi-pencil me-2"></i> Chỉnh sửa tài khoản
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Role -->
  <div class="modal fade" id="roleModal" ref="roleModalRef" tabindex="-1" aria-labelledby="roleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="roleModalLabel">Quản lý Vai trò</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <button class="btn btn-success" @click="resetRoleForm(); isRoleEditMode = false;">+ Thêm Vai trò
              mới</button>
          </div>

          <div class="card p-3 mb-4" v-if="!isRoleEditMode || roleFormData.id">
            <h6>{{ isRoleEditMode ? 'Chỉnh sửa Vai trò' : 'Tạo Vai trò mới' }}</h6>
            <form @submit.prevent="handleSaveRole">
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label for="roleValue" class="form-label">Giá trị (Value)</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': roleErrors.value }" id="roleValue"
                    v-model="roleFormData.value" :disabled="isRoleEditMode">
                  <div class="form-text" v-if="!isRoleEditMode">(Viết liền không dấu, dùng cho code)</div>
                  <div class="invalid-feedback" v-if="roleErrors.value">{{ roleErrors.value }}</div>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="roleLabel" class="form-label">Nhãn hiển thị</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': roleErrors.label }" id="roleLabel"
                    v-model="roleFormData.label">
                  <div class="invalid-feedback" v-if="roleErrors.label">{{ roleErrors.label }}</div>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="roleBadgeClass" class="form-label">Màu Badge</label>
                  <select class="form-select" id="roleBadgeClass" v-model="roleFormData.badgeClass">
                    <option value="text-bg-danger">Đỏ (Admin)</option>
                    <option value="text-bg-info">Xanh dương nhạt (Quản lý)</option>
                    <option value="text-bg-success">Xanh lá (Nhân viên)</option>
                    <option value="text-bg-primary">Xanh dương đậm</option>
                    <option value="text-bg-warning">Vàng</option>
                    <option value="text-bg-secondary">Xám</option>
                    <option value="text-bg-dark">Đen</option>
                    <option value="text-bg-light">Sáng (User)</option>
                  </select>
                  <span :class="['badge', 'mt-1', roleFormData.badgeClass]">{{ roleFormData.label || 'Xem trước'
                  }}</span>
                </div>
              </div>
              <button type="submit" class="btn btn-primary me-2">{{ isRoleEditMode ? 'Lưu Thay Đổi' : 'Tạo Mới'
              }}</button>
              <button type="button" class="btn btn-secondary" @click="resetRoleForm">Hủy</button>
            </form>
          </div>

          <h6 class="mt-4">Danh sách Vai trò hiện tại</h6>
          <div class="table-responsive">
            <table class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Giá trị (Value)</th>
                  <th>Nhãn hiển thị</th>
                  <th>Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="roles.length === 0">
                  <td colspan="4" class="text-center">Chưa có vai trò nào được tạo.</td>
                </tr>
                <tr v-for="role in roles" :key="role.id">
                  <td>{{ role.id }}</td>
                  <td>{{ role.value }}</td>
                  <td><span :class="['badge', role.badgeClass]">{{ role.label }}</span></td>
                  <td>
                    <button class="btn btn-sm btn-outline-info me-2" @click="openEditRoleModal(role)">Sửa</button>
                    <button class="btn btn-sm btn-outline-danger" @click="handleDeleteRole(role)"
                      :disabled="role.value === 'admin' || role.value === 'user'">Xóa</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* CSS cho khoảng cách nút */
.table td .btn {
  margin-top: 2px;
  margin-bottom: 2px;
}
.table td .btn-group {
  min-width: 95px; /* Đảm bảo các nút căn đều */
  justify-content: center;
}
.table td .form-check-input {
    margin-right: 0.5rem; /* Tách nút switch ra khỏi btn-group */
}

/* Thêm CSS cho label bắt buộc */
.required::after {
  content: " *";
  color: red;
}
.form-check-input {
  width: 2.5em;
  height: 1.25em;
}
/* Đảm bảo bảng không bị tràn */
.card-body {
  overflow-x: auto;
}

/* Thêm style để làm mờ hàng bị vô hiệu hóa */
.inactive-row {
  opacity: 0.65;
  background-color: #f8f9fa; /* Màu bg-light của Bootstrap */
  transition: opacity 0.2s ease-in-out, background-color 0.2s ease-in-out;
}
.inactive-row:hover {
  opacity: 1; /* Hiển thị rõ khi hover */
  background-color: #f1f3f5;
}

/* Style riêng cho dòng của user hiện tại */
.current-user-row {
  background-color: #e8f4ff !important; /* Màu xanh nhạt */
  border-left: 4px solid #0d6efd; /* Viền trái màu xanh */
}
/* Đảm bảo màu nền không bị override bởi table-hover */
.table-hover .current-user-row:hover {
    background-color: #d6ebff !important;
}
</style>