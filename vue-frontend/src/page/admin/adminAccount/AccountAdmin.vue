<script setup>
import { ref, reactive, onMounted, computed, nextTick } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// Global state
const API_URL = import.meta.env.VITE_API_BASE_URL;
const users = ref([]);
const isLoading = ref(true);

// User modal state
const userModalInstance = ref(null);
const userModalRef = ref(null);
const isEditMode = ref(false);

// User form data
const formData = reactive({
  id: null,
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
  role: 'nhanvien',
  status: 'active'
});

// User form errors
const errors = reactive({
  username: '',
  email: '',
  password: '',
  confirmPassword: '',
});

// Role state
const roles = ref([]);
const roleModalInstance = ref(null);
const roleModalRef = ref(null);
const isRoleEditMode = ref(false);

// Role form data
const roleFormData = reactive({
  id: null,
  value: '', // Backend value
  label: '', // Display label
  badgeClass: 'text-bg-secondary', // Badge CSS class
});

// Role form errors
const roleErrors = reactive({
  value: '',
  label: '',
});

// Pagination state for non-admin users
const otherUsersCurrentPage = ref(1);
const otherUsersItemsPerPage = ref(5);

// Computed properties
const adminUsers = computed(() => {
  return users.value.filter(user => user.role === 'admin');
});

const otherUsers = computed(() => {
  return users.value.filter(user => user.role !== 'admin');
});

const otherUsersTotalPages = computed(() => {
  return Math.ceil(otherUsers.value.length / otherUsersItemsPerPage.value);
});

const paginatedOtherUsers = computed(() => {
  const start = (otherUsersCurrentPage.value - 1) * otherUsersItemsPerPage.value;
  const end = start + otherUsersItemsPerPage.value;
  return otherUsers.value.slice(start, end);
});

// Get role badge class
function getRoleBadge(roleValue) {
  const role = roles.value.find(r => r.value === roleValue);
  return role ? role.badgeClass : 'text-bg-secondary';
}

// Get role label
function getRoleLabel(roleValue) {
  const role = roles.value.find(r => r.value === roleValue);
  return role ? role.label : roleValue;
}

// Lifecycle hooks
onMounted(() => {
  fetchUsers();
  fetchRoles();

  if (userModalRef.value) {
    userModalInstance.value = new Modal(userModalRef.value);
  }
  if (roleModalRef.value) {
    roleModalInstance.value = new Modal(roleModalRef.value);
  }
});

// User CRUD operations
async function fetchUsers() {
  isLoading.value = true;
  try {
    const response = await axios.get(`${API_URL}/account_admin`);
    users.value = response.data.map(user => ({
      ...user,
      // Ensure created_at exists
      created_at: user.created_at || new Date().toISOString(),
      status: user.status || 'active'
    }));
    otherUsersCurrentPage.value = 1;
  } catch (error) {
    console.error("Lỗi khi tải danh sách người dùng:", error);
    Swal.fire({
      icon: 'error',
      title: 'Lỗi',
      text: 'Không thể tải danh sách người dùng. Vui lòng thử lại sau.',
    });
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
  formData.role = 'nhanvien';
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

  // Populate form data
  formData.id = user.id;
  formData.username = user.username;
  formData.email = user.email;
  formData.role = user.role;
  formData.status = user.status;

  userModalInstance.value.show();
}

function validateForm() {
  Object.keys(errors).forEach(key => errors[key] = '');
  let isValid = true;
  const re = /^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]+$/;

  if (!formData.username.trim()) {
    errors.username = 'Vui lòng nhập tên hiển thị.';
    isValid = false;
  } else if (!re.test(formData.username)) {
    errors.username = 'Tên hiển thị chỉ được dùng chữ và số.'
    isValid = false;
  }

  if (!formData.email) {
    errors.email = 'Vui lòng nhập email.';
    isValid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    errors.email = 'Email không đúng định dạng.';
    isValid = false;
  }

  // Validate password only if it's a new user OR if password field is not empty in edit mode
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

// Handle save (create/update user)
async function handleSave() {
  if (!validateForm()) {
    return;
  }

  isLoading.value = true;

  const payload = {
    username: formData.username,
    email: formData.email,
    role: formData.role,
    status: formData.status,
  };

  // Only include password in payload if it's provided
  if (formData.password) {
    payload.password = formData.password;
  }

  try {
    if (isEditMode.value) {
      // For update, if password is not provided, it won't be sent, preserving the old one
      await axios.patch(`${API_URL}/account_admin/${formData.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật người dùng!', 'success');
    } else {
      // Add created_at for demo
      payload.created_at = new Date().toISOString();
      await axios.post(`${API_URL}/account_admmin`, payload);
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

// Toggle user status (active/inactive)
async function toggleUserStatus(user) {
  const newStatus = user.status === 'active' ? 'inactive' : 'active';
  const actionText = newStatus === 'inactive' ? 'vô hiệu hóa' : 'kích hoạt';
  const confirmText = newStatus === 'inactive' ? 'Đồng ý vô hiệu hóa!' : 'Đồng ý kích hoạt!';

  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn muốn ${actionText} tài khoản "${user.username}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: confirmText,
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    isLoading.value = true;
    try {
      // Use PATCH for partial update
      await axios.patch(`${API_URL}/account_admin/${user.id}`, { status: newStatus });
      Swal.fire(
        'Thành công!',
        `Đã ${actionText} người dùng ${user.username}.`,
        'success'
      );
      fetchUsers();
    } catch (error) {
      console.error(`Lỗi khi ${actionText} người dùng:`, error);
      Swal.fire('Lỗi', `Không thể ${actionText} người dùng này.`, 'error');
    } finally {
      isLoading.value = false;
    }
  }
}

// Delete user
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
      await axios.delete(`${API_URL}/account_admin/${user.id}`);
      Swal.fire(
        'Đã xóa!',
        'Người dùng đã được xóa.',
        'success'
      );
      fetchUsers();
    } catch (error) {
      console.error("Lỗi khi xóa người dùng:", error);
      Swal.fire('Lỗi', 'Không thể xóa người dùng này.', 'error');
    }
  }
}

// Role CRUD operations
async function fetchRoles() {
  try {
    const response = await axios.get(`${API_URL}/roles`);
    roles.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải danh sách vai trò:", error);
    // Fallback default roles for demo
    roles.value = [
      { value: 'admin', label: 'Quản trị viên (Admin)', badgeClass: 'text-bg-danger', id: 1 },
      { value: 'manager', label: 'Quản lý', badgeClass: 'text-bg-info', id: 2 },
      { value: 'nhanvien', label: 'Nhân viên', badgeClass: 'text-bg-success', id: 3 },
      { value: 'ketoan', label: 'Kế toán', badgeClass: 'text-bg-primary', id: 4 },
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
  // Ensure user modal is hidden
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

  // Ensure user modal is hidden
  userModalInstance.value?.hide();
  // Wait for DOM update before showing modal
  nextTick(() => {
    roleModalInstance.value.show();
  });
}

async function handleSaveRole() {
  if (!validateRoleForm()) {
    return;
  }

  const payload = {
    // Ensure value is lowercase
    value: roleFormData.value.toLowerCase(),
    label: roleFormData.label,
    badgeClass: roleFormData.badgeClass,
  };

  try {
    if (isRoleEditMode.value) {
      await axios.put(`${API_URL}/roles/${roleFormData.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật vai trò!', 'success');
    } else {
      await axios.post(`${API_URL}/roles`, payload);
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
      await axios.delete(`${API_URL}/roles/${role.id}`);
      Swal.fire(
        'Đã xóa!',
        'Vai trò đã được xóa.',
        'success'
      );
      fetchRoles();
    } catch (error) {
      console.error("Lỗi khi xóa vai trò:", error);
      Swal.fire('Lỗi', 'Không thể xóa vai trò này.', 'error');
    }
  }
}

// Pagination handlers
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
          <h3 class="mb-0">Quản lý Người dùng và Vai trò</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">
              Người dùng
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">

      <div class="row mb-3">
        <div class="col-12 text-end">
          <button type="button" class="btn btn-secondary me-2" @click="openCreateRoleModal">
            <i class="bi bi-people-fill"></i> Quản lý Vai trò
          </button>
          <button type="button" class="btn btn-primary" @click="openCreateModal">
            <i class="bi bi-plus-lg"></i> Thêm mới Tài khoản
          </button>
        </div>
      </div>

      <div v-if="isLoading" class="text-center my-5">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>

      <div v-else>
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Danh sách Quản trị viên</h3>
              </div>
              <div class="card-body">
                <table class="table table-bordered table-hover align-middle">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Tên đăng nhập</th>
                      <th>Email</th>
                      <th>Vai trò</th>
                      <th>Trạng thái</th>
                      <th>Ngày tạo</th>
                      <th>Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="adminUsers.length === 0">
                      <td colspan="7" class="text-center">Không có quản trị viên nào</td>
                    </tr>
                    <tr v-for="user in adminUsers" :key="user.id">
                      <td>{{ user.id }}</td>
                      <td>{{ user.username }}</td>
                      <td>{{ user.email }}</td>
                      <td>
                        <span :class="['badge', getRoleBadge(user.role)]">{{ getRoleLabel(user.role) }}</span>
                      </td>
                      <td>
                        <span :class="['badge', user.status === 'active' ? 'text-bg-success' : 'text-bg-warning']">
                          {{ user.status === 'active' ? 'Đang hoạt động' : 'Vô hiệu hóa' }}
                        </span>
                      </td>
                      <td>{{ user.created_at ? new Date(user.created_at).toLocaleDateString('vi-VN') : 'N/A' }}</td>
                      <td>
                        <!-- Toggle Switch -->
                        <div class="form-check form-switch d-inline-block align-middle me-2" title="Kích hoạt/Vô hiệu hóa">
                          <input class="form-check-input" type="checkbox" role="switch"
                            style="width: 2.5em; height: 1.25em; cursor: pointer;"
                            :checked="user.status === 'active'"
                            @click.prevent="toggleUserStatus(user)">
                        </div>

                        <button class="btn btn-info btn-sm me-2" @click="openEditModal(user)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" @click="handleDelete(user)">
                          <i class="bi bi-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-4">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Danh sách Người dùng khác</h3>
              </div>
              <div class="card-body">
                <table class="table table-bordered table-hover align-middle">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Tên đăng nhập</th>
                      <th>Email</th>
                      <th>Vai trò</th>
                      <th>Trạng thái</th>
                      <th>Ngày tạo</th>
                      <th>Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="otherUsers.length === 0">
                      <td colspan="7" class="text-center">Không có người dùng nào khác</td>
                    </tr>
                    <tr v-for="user in paginatedOtherUsers" :key="user.id">
                      <td>{{ user.id }}</td>
                      <td>{{ user.username }}</td>
                      <td>{{ user.email }}</td>
                      <td>
                        <span :class="['badge', getRoleBadge(user.role)]">{{ getRoleLabel(user.role) }}</span>
                      </td>
                      <td>
                        <span :class="['badge', user.status === 'active' ? 'text-bg-success' : 'text-bg-warning']">
                          {{ user.status === 'active' ? 'Đang hoạt động' : 'Vô hiệu hóa' }}
                        </span>
                      </td>
                      <td>{{ user.created_at ? new Date(user.created_at).toLocaleDateString('vi-VN') : 'N/A' }}</td>
                      <td>
                        <!-- Toggle Switch -->
                        <div class="form-check form-switch d-inline-block align-middle me-2" title="Kích hoạt/Vô hiệu hóa">
                          <input class="form-check-input" type="checkbox" role="switch"
                            style="width: 2.5em; height: 1.25em; cursor: pointer;"
                            :checked="user.status === 'active'"
                            @click.prevent="toggleUserStatus(user)">
                        </div>

                        <button class="btn btn-info btn-sm me-2" @click="openEditModal(user)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" @click="handleDelete(user)">
                          <i class="bi bi-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

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

  <div class="modal fade" id="userModal" ref="userModalRef" tabindex="-1" aria-labelledby="userModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="userModalLabel">
            {{ isEditMode ? 'Chỉnh sửa Tài khoản' : 'Tạo Tài khoản mới' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSave">
            <div class="mb-3">
              <label for="username" class="form-label">Tên đăng nhập</label>
              <input type="text" class="form-control" :class="{ 'is-invalid': errors.username }" id="username"
                v-model="formData.username">
              <div class="invalid-feedback" v-if="errors.username">{{ errors.username }}</div>
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" id="email"
                v-model="formData.email" :readonly="isEditMode">
              <div class="invalid-feedback" v-if="errors.email">{{ errors.email }}</div>
            </div>

            <div class="mb-3">
              <label for="role" class="form-label">Vai trò</label>
              <select class="form-select" id="role" v-model="formData.role">
                <option v-for="role in roles" :key="role.value" :value="role.value">
                  {{ role.label }}
                </option>
              </select>
            </div>

            <div class="mb-3" v-if="isEditMode">
              <label for="status" class="form-label">Trạng thái tài khoản</label>
              <select class="form-select" id="status" v-model="formData.status">
                <option value="active">Đang hoạt động</option>
                <option value="inactive">Vô hiệu hóa</option>
              </select>
            </div>

            <hr>

            <div class="mb-3">
              <label for="password" class="form-label">Mật khẩu</label>
              <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" id="password"
                v-model="formData.password">
              <div class="form-text" v-if="isEditMode">(Để trống nếu không muốn thay đổi)</div>
              <div class="invalid-feedback" v-if="errors.password">{{ errors.password }}</div>
            </div>

            <div class="mb-3">
              <label for="confirmPassword" class="form-label">Xác nhận mật khẩu</label>
              <input type="password" class="form-control" :class="{ 'is-invalid': errors.confirmPassword }"
                id="confirmPassword" v-model="formData.confirmPassword">
              <div class="invalid-feedback" v-if="errors.confirmPassword">{{ errors.confirmPassword }}</div>
            </div>

          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="button" class="btn btn-primary" @click="handleSave" :disabled="isLoading">
            <span v-if="isLoading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            Lưu lại
          </button>
        </div>
      </div>
    </div>
  </div>

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
                  <button class="btn btn-sm btn-outline-danger" @click="handleDeleteRole(role)">Xóa</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Button spacing in tables */
.table td .btn {
  margin-top: 2px;
  margin-bottom: 2px;
}
</style>