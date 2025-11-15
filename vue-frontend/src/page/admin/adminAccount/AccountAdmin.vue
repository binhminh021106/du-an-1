<script setup>
import { ref, reactive, onMounted, computed, nextTick, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

const users = ref([]); // Chỉ chứa admin/nhanvien (không chứa user)
const isLoading = ref(true);
const searchQuery = ref('');

// --- State Modals ---
const userModalInstance = ref(null);
const userModalRef = ref(null);
const isEditMode = ref(false);

const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingUser = ref({});

const roleModalInstance = ref(null);
const roleModalRef = ref(null);
const isRoleEditMode = ref(false);

// --- State Forms ---
const formData = reactive({
  id: null,
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
  role: 'nhanvien',
  status: 'active'
});

const errors = reactive({
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
});

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

// --- State Vai trò & Phân trang ---
const roles = ref([]); // Chứa TẤT CẢ vai trò (để lấy nhãn)
const otherUsersCurrentPage = ref(1);
const otherUsersItemsPerPage = ref(5);

// --- Computed ---

// Lọc người dùng dựa trên tìm kiếm
const filteredUsers = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return users.value; // users đã được lọc ở fetchUsers
  }
  return users.value.filter(user =>
    user.username.toLowerCase().includes(query) ||
    user.email.toLowerCase().includes(query) ||
    getRoleLabel(user.role).toLowerCase().includes(query)
  );
});

// Lọc admin từ danh sách đã lọc
const adminUsers = computed(() => {
  return filteredUsers.value.filter(user => user.role === 'admin');
});

// Lọc user khác (nhanvien, ketoan...)
const otherUsers = computed(() => {
  return filteredUsers.value.filter(user => user.role !== 'admin');
});

// Lọc các vai trò có thể chọn (không bao gồm 'user')
const availableRoles = computed(() => {
  return roles.value.filter(r => r.value !== 'user');
});

// Tính tổng số trang cho user khác
const otherUsersTotalPages = computed(() => {
  return Math.ceil(otherUsers.value.length / otherUsersItemsPerPage.value);
});

// Lấy danh sách user khác đã phân trang
const paginatedOtherUsers = computed(() => {
  const start = (otherUsersCurrentPage.value - 1) * otherUsersItemsPerPage.value;
  const end = start + otherUsersItemsPerPage.value;
  return otherUsers.value.slice(start, end);
});

// Reset về trang 1 khi tìm kiếm
watch(searchQuery, () => {
  otherUsersCurrentPage.value = 1;
});

// --- Hàm Helper ---

function getRoleBadge(roleValue) {
  const role = roles.value.find(r => r.value === roleValue);
  return role ? role.badgeClass : 'text-bg-secondary';
}

function getRoleLabel(roleValue) {
  const role = roles.value.find(r => r.value === roleValue);
  return role ? role.label : roleValue;
}

function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
}

// --- Hooks ---
onMounted(() => {
  fetchUsers(); // Tải user (admin, nhanvien...)
  fetchRoles(); // Tải tất cả role để hiển thị badge

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

// --- CRUD User ---
async function fetchUsers() {
  isLoading.value = true;
  try {
    // Gọi /users và lọc ra những ai KHÔNG PHẢI 'user'
    const response = await apiService.get(`/users?role_ne=user`);

    users.value = response.data.map(user => ({
      ...user,
      username: user.username || user.name, // Đảm bảo username tồn tại
      created_at: user.created_at || new Date().toISOString(),
      status: user.status || 'active'
    }));
    otherUsersCurrentPage.value = 1;
  } catch (error) {
    console.error("Lỗi khi tải danh sách người dùng:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách người dùng.', 'error');
  } finally {
    isLoading.value = false;
  }
}

function resetForm() {
  formData.id = null;
  formData.username = '';
  formData.email = '';
  formData.password = '';
  formData.confirmPassword = '';
  formData.role = 'nhanvien'; // Mặc định khi tạo là nhân viên
  formData.status = 'active';
  Object.keys(errors).forEach(key => errors[key] = '');
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
  formData.username = user.username;
  formData.email = user.email;
  formData.role = user.role;
  formData.status = user.status;
  userModalInstance.value.show();
}

function openViewModal(user) {
  viewingUser.value = user;
  viewModalInstance.value.show();
}

function validateForm() {
  Object.keys(errors).forEach(key => errors[key] = '');
  let isValid = true;

  if (!formData.username.trim()) {
    errors.username = 'Vui lòng nhập tên đăng nhập.';
    isValid = false;
  }

  if (!formData.email) {
    errors.email = 'Vui lòng nhập email.';
    isValid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    errors.email = 'Email không đúng định dạng.';
    isValid = false;
  }

  if (!isEditMode.value || formData.password) {
    if (!formData.password) {
      errors.password = 'Vui lòng nhập mật khẩu.';
      isValid = false;
    } else if (formData.password.length < 8) {
      errors.password = 'Mật khẩu phải có ít nhất 8 ký tự.';
      isValid = false;
    }
    if (formData.password !== formData.confirmPassword) {
      errors.confirmPassword = 'Mật khẩu xác nhận không khớp!';
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

  const payload = {
    username: formData.username,
    name: formData.username, // Đồng bộ name = username
    email: formData.email,
    role: formData.role,
    status: formData.status,
    phone: '', // Thêm các trường chuẩn hóa
    address: '',
    avatar: '',
  };

  if (formData.password) {
    payload.password = formData.password;
  }

  try {
    if (isEditMode.value) {
      await apiService.patch(`/users/${formData.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật người dùng!', 'success');
    } else {
      payload.created_at = new Date().toISOString();
      await apiService.post(`/users`, payload);
      Swal.fire('Thành công', 'Đã tạo người dùng mới!', 'success');
    }
    userModalInstance.value.hide();
    fetchUsers();
  } catch (apiError) {
    console.error("Lỗi khi lưu:", apiError);
    Swal.fire('Thất bại', 'Đã có lỗi xảy ra. Vui lòng thử lại.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function toggleUserStatus(user) {
  const newStatus = user.status === 'active' ? 'inactive' : 'active';
  const actionText = newStatus === 'inactive' ? 'vô hiệu hóa' : 'kích hoạt';

  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn muốn ${actionText} tài khoản "${user.username}"?`,
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
      await apiService.patch(`/users/${user.id}`, { status: newStatus });
      Swal.fire('Thành công!', `Đã ${actionText} người dùng ${user.username}.`, 'success');
      user.status = newStatus; // Cập nhật UI
    } catch (error) {
      console.error(`Lỗi khi ${actionText} người dùng:`, error);
      Swal.fire('Lỗi', `Không thể ${actionText} người dùng này.`, 'error');
      fetchUsers(); // Tải lại nếu lỗi
    } finally {
      isLoading.value = false;
    }
  }
}

async function handleDelete(user) {
  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn sẽ xóa vĩnh viễn người dùng "${user.username}"!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Đồng ý xóa!',
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`/users/${user.id}`);
      Swal.fire('Đã xóa!', 'Người dùng đã được xóa.', 'success');
      fetchUsers();
    } catch (error) {
      console.error("Lỗi khi xóa người dùng:", error);
      Swal.fire('Lỗi', 'Không thể xóa người dùng này.', 'error');
    }
  }
}

// --- CRUD Vai trò ---
async function fetchRoles() {
  try {
    const response = await apiService.get(`/roles`);
    roles.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải danh sách vai trò:", error);
    roles.value = [
      { value: 'admin', label: 'Quản trị viên (Admin)', badgeClass: 'text-bg-danger', id: 1 },
      { value: 'manager', label: 'Quản lý', badgeClass: 'text-bg-info', id: 2 },
      { value: 'nhanvien', label: 'Nhân viên', badgeClass: 'text-bg-success', id: 3 },
      { value: 'ketoan', label: 'Kế toán', badgeClass: 'text-bg-primary', id: 4 },
      { value: 'user', label: 'Khách hàng (User)', badgeClass: 'text-bg-light', id: 5 }
    ];
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
      await apiService.put(`/roles/${roleFormData.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật vai trò!', 'success');
    } else {
      await apiService.post(`/roles`, payload);
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
      await apiService.delete(`/roles/${role.id}`);
      Swal.fire('Đã xóa!', 'Vai trò đã được xóa.', 'success');
      fetchRoles();
    } catch (error) {
      console.error("Lỗi khi xóa vai trò:", error);
      Swal.fire('Lỗi', 'Không thể xóa vai trò này.', 'error');
    }
  }
}

// --- Xử lý phân trang ---
function goToOtherUsersPage(page) {
  if (page >= 1 && page <= otherUsersTotalPages.value) {
    otherUsersCurrentPage.value = page;
  }
}
function prevOtherUsersPage() {
  goToOtherUsersPage(otherUsersCurrentPage.value - 1);
}
function nextOtherUsersPage() {
  goToOtherUsersPage(otherUsersCurrentPage.value + 1);
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
                  placeholder="Tìm kiếm theo tên đăng nhập, email, vai trò..." v-model="searchQuery">
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
                        <th>Tên đăng nhập</th>
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
                      <!-- THAY ĐỔI 1: Thêm class binding cho <tr> -->
                      <tr v-for="user in adminUsers" :key="user.id"
                        :class="{ 'inactive-row': user.status !== 'active' }">
                        <td>{{ user.id }}</td>
                        <td>
                          <div class="d-flex align-items-center">
                            <img
                              :src="user.avatar || `https://placehold.co/40x40/EBF4FF/1D62F0?text=${user.username.charAt(0).toUpperCase()}`"
                              class="rounded-circle me-3" alt="Avatar"
                              style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                              <div class="fw-bold">{{ user.username }}</div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <div v-if="user.email"><i class="bi bi-envelope me-1 text-muted"></i> {{ user.email }}</div>
                          <div v-if="user.phone"><i class="bi bi-telephone me-1 text-muted"></i> {{ user.phone }}</div>
                        </td>
                        <td>
                          <span :class="['badge', getRoleBadge(user.role)]">{{ getRoleLabel(user.role) }}</span>
                        </td>
                        <td>
                          <!-- THAY ĐỔI 2: Đổi text-bg-warning sang text-bg-danger cho nổi bật -->
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
                                :checked="user.status === 'active'" @click.prevent="toggleUserStatus(user)">
                            </div>

                            <div class="btn-group btn-group-sm">
                              <button class="btn btn-outline-secondary" title="Xem chi tiết"
                                @click="openViewModal(user)">
                                <i class="bi bi-eye"></i>
                              </button>
                              <button class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditModal(user)">
                                <i class="bi bi-pencil"></i>
                              </button>
                              <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(user)">
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
                        <th>Tên đăng nhập</th>
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
                      <!-- THAY ĐỔI 3: Thêm class binding cho <tr> -->
                      <tr v-for="user in paginatedOtherUsers" :key="user.id"
                        :class="{ 'inactive-row': user.status !== 'active' }">
                        <td>{{ user.id }}</td>
                        <td>
                          <div class="d-flex align-items-center">
                            <img
                              :src="user.avatar || `https://placehold.co/40x40/EBF4FF/1D62F0?text=${user.username.charAt(0).toUpperCase()}`"
                              class="rounded-circle me-3" alt="Avatar"
                              style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                              <div class="fw-bold">{{ user.username }}</div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <div v-if="user.email"><i class="bi bi-envelope me-1 text-muted"></i> {{ user.email }}</div>
                          <div v-if="user.phone"><i class="bi bi-telephone me-1 text-muted"></i> {{ user.phone }}</div>
                        </td>
                        <td>
                          <span :class="['badge', getRoleBadge(user.role)]">{{ getRoleLabel(user.role) }}</span>
                        </td>
                        <td>
                          <!-- THAY ĐỔI 4: Đổi text-bg-warning sang text-bg-danger cho nổi bật -->
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
                                :checked="user.status === 'active'" @click.prevent="toggleUserStatus(user)">
                            </div>

                            <div class="btn-group btn-group-sm">
                              <button class="btn btn-outline-secondary" title="Xem chi tiết"
                                @click="openViewModal(user)">
                                <i class="bi bi-eye"></i>
                              </button>
                              <button class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditModal(user)">
                                <i class="bi bi-pencil"></i>
                              </button>
                              <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(user)">
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
              <!-- Cột trái: Avatar (placeholder), Vai trò, Trạng thái -->
              <div class="col-md-4 text-center mb-3">
                <div class="mb-3">
                  <img
                    :src="`https://placehold.co/150x150/EBF4FF/1D62F0?text=${formData.username ? formData.username.charAt(0).toUpperCase() : '?'}`"
                    class="img-thumbnail rounded-circle" alt="Avatar Placeholder"
                    style="width: 150px; height: 150px; object-fit: cover;">
                </div>
                <div class="mb-3">
                  <label for="role" class="form-label fw-bold required">Vai trò</label>
                  <select class="form-select" id="role" v-model="formData.role">
                    <option v-for="role in availableRoles" :key="role.value" :value="role.value">
                      {{ role.label }}
                    </option>
                  </select>
                </div>
                <div class="mb-3" v-if="isEditMode">
                  <label for="status" class="form-label fw-bold">Trạng thái tài khoản</label>
                  <select class="form-select" id="status" v-model="formData.status">
                    <option value="active">Đang hoạt động</option>
                    <option value="inactive">Vô hiệu hóa</option>
                  </select>
                </div>
              </div>

              <!-- Cột phải: Thông tin & Bảo mật -->
              <div class="col-md-8">
                <div class="mb-3">
                  <label for="username" class="form-label required">Tên đăng nhập</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.username }" id="username"
                    v-model="formData.username">
                  <div class="invalid-feedback" v-if="errors.username">{{ errors.username }}</div>
                </div>
                <div class="mb-3">
                  <label for="email" class="form-label required">Email</label>
                  <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" id="email"
                    v-model="formData.email" :readonly="isEditMode">
                  <div class="invalid-feedback" v-if="errors.email">{{ errors.email }}</div>
                  <div v-if="isEditMode" class="form-text">Không thể thay đổi email.</div>
                </div>

                <hr class="my-4">
                <h6 class="mb-3"><i class="bi bi-shield-lock me-2"></i>Bảo mật</h6>

                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="password" class="form-label" :class="{ 'required': !isEditMode }">Mật khẩu</label>
                    <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" id="password"
                      v-model="formData.password" autocomplete="new-password">
                    <div class="form-text" v-if="isEditMode">(Để trống nếu không muốn thay đổi)</div>
                    <div class="invalid-feedback" v-if="errors.password">{{ errors.password }}</div>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="confirmPassword" class="form-label"
                      :class="{ 'required': !isEditMode || formData.password }">Xác nhận mật khẩu</label>
                    <input type="password" class="form-control" :class="{ 'is-invalid': errors.confirmPassword }"
                      id="confirmPassword" v-model="formData.confirmPassword" autocomplete="new-password">
                    <div class="invalid-feedback" v-if="errors.confirmPassword">{{ errors.confirmPassword }}</div>
                  </div>
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
              :src="viewingUser.avatar || `https://placehold.co/120x120/EBF4FF/1D62F0?text=${viewingUser.username ? viewingUser.username.charAt(0).toUpperCase() : 'U'}`"
              class="rounded-circle img-thumbnail shadow-sm" alt="Avatar"
              style="width: 120px; height: 120px; object-fit: cover;">
            <h4 class="mt-3 mb-1">{{ viewingUser.username }}</h4>
            <p class="text-muted mb-0">ID: {{ viewingUser.id }}</p>
          </div>
          <div class="list-group list-group-flush">
            <div class="list-group-item px-0">
              <i class="bi bi-envelope me-3 text-primary"></i> {{ viewingUser.email }}
            </div>
            <div class="list-group-item px-0">
              <i class="bi bi-telephone me-3 text-success"></i> {{ viewingUser.phone || '(Chưa có SĐT)' }}
            </div>
            <div class="list-group-item px-0">
              <i class="bi bi-person-badge me-3 text-info"></i>
              <span :class="['badge', getRoleBadge(viewingUser.role)]">
                {{ getRoleLabel(viewingUser.role) }}
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
                  <th>Giá trị (Value)</th>
                  <th>Nhãn hiển thị</th>
                  <th>Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="roles.length === 0">
                  <td colspan="3" class="text-center">Chưa có vai trò nào được tạo.</td>
                </tr>
                <tr v-for="role in roles" :key="role.id">
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

/* (MỚI) Thêm style để làm mờ hàng bị vô hiệu hóa */
.inactive-row {
  opacity: 0.65;
  background-color: #f8f9fa; /* Màu bg-light của Bootstrap */
  transition: opacity 0.2s ease-in-out, background-color 0.2s ease-in-out;
}
.inactive-row:hover {
  opacity: 1; /* Hiển thị rõ khi hover */
  background-color: #f1f3f5;
}
</style>