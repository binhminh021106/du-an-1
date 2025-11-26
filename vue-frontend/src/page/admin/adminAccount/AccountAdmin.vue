<script setup>
import { ref, reactive, onMounted, computed, nextTick, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CẤU HÌNH ---
const ENV_API_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
const BACKEND_URL = ENV_API_URL.replace(/\/api\/?$/, '');

// --- STATE QUẢN LÝ ---
const users = ref([]); // Tất cả user
const roles = ref([]); // Danh sách vai trò
const isLoading = ref(true);
const searchQuery = ref('');
const selectedRoleFilter = ref(''); // State cho bộ lọc Role mới
const currentAdminId = ref(null);
const processingStatusIds = ref([]);
const activeTab = ref(''); // Tab đang active (Key của role hoặc 'inactive')

// --- PAGINATION STATE ---
// Lưu trạng thái phân trang cho từng tab: { 'role_1': { page: 1 }, 'inactive': { page: 1 } }
const paginationState = reactive({});
const itemsPerPage = 8; // Limit 8 theo yêu cầu

// --- MODALS STATE ---
const userModalInstance = ref(null);
const userModalRef = ref(null);
const isEditMode = ref(false);

const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingUser = ref({});

const roleModalInstance = ref(null);
const roleModalRef = ref(null);
const isRoleEditMode = ref(false);

// --- FORM DATA ---
const fileInputRef = ref(null);
const previewImage = ref(null);
const avatarFile = ref(null);

const formData = reactive({
  id: null, fullname: '', email: '', phone: '', address: '',
  role_id: '', status: 'active', password: '', password_confirmation: ''
});

const errors = reactive({
  fullname: '', email: '', phone: '', address: '',
  password: '', password_confirmation: ''
});

const roleFormData = reactive({
  id: null, value: '', label: '', badgeClass: 'text-bg-secondary',
});
const roleErrors = reactive({ value: '', label: '' });

// --- COMPUTED: XỬ LÝ DANH SÁCH & TABS ---

// 1. Helper lấy thông tin Role
const getRoleObj = (roleId) => roles.value.find(r => r.id == roleId) || {};
const getRoleLabel = (roleId) => getRoleObj(roleId).label || 'Chưa phân quyền';
const getRoleBadge = (roleId) => getRoleObj(roleId).badgeClass || 'text-bg-secondary';

// 2. Tạo danh sách Tabs động dựa trên dữ liệu
const dynamicTabs = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();

  // Lọc dữ liệu trước
  let filtered = users.value;

  // A. Lọc theo Role Dropdown (Mới)
  if (selectedRoleFilter.value) {
    filtered = filtered.filter(user => user.role_id === selectedRoleFilter.value);
  }

  // B. Lọc theo Search Query
  if (query) {
    filtered = filtered.filter(user =>
      (user.fullname && user.fullname.toLowerCase().includes(query)) ||
      (user.email && user.email.toLowerCase().includes(query)) ||
      (user.phone && user.phone.includes(query)) ||
      getRoleLabel(user.role_id).toLowerCase().includes(query)
    );
  }

  // Sắp xếp: Admin hiện tại lên đầu
  filtered.sort((a, b) => {
    if (a.id === currentAdminId.value) return -1;
    if (b.id === currentAdminId.value) return 1;
    return 0;
  });

  const tabs = [];

  // C. Tạo tab cho từng Role (Chỉ chứa user ACTIVE)
  roles.value.forEach(role => {
    // Nếu đang lọc theo role khác thì không tạo tab cho role này (trừ khi trùng khớp)
    if (selectedRoleFilter.value && selectedRoleFilter.value !== role.id) return;

    const roleUsers = filtered.filter(u => u.role_id === role.id && u.status === 'active');
    if (roleUsers.length > 0) {
      tabs.push({
        key: `role_${role.id}`,
        label: role.label,
        badgeClass: role.badgeClass,
        data: roleUsers,
        type: 'role'
      });
    }
  });

  // D. Tạo tab cho user bị KHÓA (INACTIVE) - gom tất cả role
  const inactiveUsers = filtered.filter(u => u.status !== 'active');
  if (inactiveUsers.length > 0) {
    tabs.push({
      key: 'inactive',
      label: 'Đã vô hiệu hóa',
      badgeClass: 'text-bg-secondary',
      data: inactiveUsers,
      type: 'inactive'
    });
  }

  return tabs;
});

// 3. Lấy dữ liệu cho Tab hiện tại (đã phân trang)
const currentTabData = computed(() => {
  // Tìm tab đang active
  let tab = dynamicTabs.value.find(t => t.key === activeTab.value);

  // Nếu không tìm thấy (do search/filter làm mất tab hiện tại), mặc định lấy tab đầu tiên
  if (!tab && dynamicTabs.value.length > 0) {
    tab = dynamicTabs.value[0];
  }

  if (!tab) return { data: [], totalPages: 0, currentPage: 1, totalItems: 0 };

  // Khởi tạo state phân trang nếu chưa có
  if (!paginationState[tab.key]) {
    paginationState[tab.key] = 1;
  }

  const currentPage = paginationState[tab.key];
  const totalPages = Math.max(1, Math.ceil(tab.data.length / itemsPerPage));

  // Fix lỗi nếu đang ở trang 2 mà lọc dữ liệu chỉ còn 1 trang
  if (currentPage > totalPages) {
    paginationState[tab.key] = 1;
  }

  const start = (paginationState[tab.key] - 1) * itemsPerPage;
  const end = start + itemsPerPage;

  return {
    data: tab.data.slice(start, end),
    totalPages,
    currentPage: paginationState[tab.key],
    totalItems: tab.data.length,
    key: tab.key // Trả về key để biết đang ở tab nào
  };
});

// --- WATCHERS ---
watch(dynamicTabs, (newTabs) => {
  // Nếu tab hiện tại biến mất (do search/filter), chuyển về tab đầu tiên
  if (newTabs.length > 0 && !newTabs.find(t => t.key === activeTab.value)) {
    activeTab.value = newTabs[0].key;
  }
});

watch([searchQuery, selectedRoleFilter], () => {
  // Reset phân trang tất cả các tab về 1 khi tìm kiếm hoặc lọc
  for (const key in paginationState) {
    paginationState[key] = 1;
  }
});

// --- LIFECYCLE ---
onMounted(() => {
  fetchCurrentUser();
  fetchRoles().then(() => {
    fetchUsers();
  });

  if (userModalRef.value) userModalInstance.value = new Modal(userModalRef.value, { backdrop: 'static' });
  if (roleModalRef.value) roleModalInstance.value = new Modal(roleModalRef.value);
  if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value);
});

// --- API FUNCTIONS ---

async function fetchCurrentUser() {
  try {
    const response = await apiService.get('/user');
    if (response.data?.id) currentAdminId.value = response.data.id;
  } catch (e) { console.error("Lỗi lấy user hiện tại", e); }
}

async function fetchUsers() {
  if (users.value.length === 0) isLoading.value = true;
  try {
    const response = await apiService.get(`admin/admins`);
    users.value = response.data.map(user => ({
      ...user,
      fullname: user.fullname || 'No Name',
      created_at: user.created_at || new Date().toISOString(),
      status: user.status || 'active'
    }));
  } catch (error) {
    console.error("Lỗi tải users:", error);
  } finally {
    isLoading.value = false;
  }
}

async function fetchRoles() {
  try {
    const response = await apiService.get(`admin/roles`);
    roles.value = response.data;
  } catch (error) { console.error("Lỗi tải roles:", error); roles.value = []; }
}

// --- ACTIONS HANDLERS ---

function changePage(page) {
  if (activeTab.value && paginationState[activeTab.value] !== undefined) {
    paginationState[activeTab.value] = page;
  }
}

function setActiveTab(key) {
  activeTab.value = key;
}

function getAvatarUrl(path, name = 'User') {
  if (!path) return `https://placehold.co/150x150/EBF4FF/1D62F0?text=${name.charAt(0).toUpperCase()}`;
  if (path.startsWith('blob:') || path.startsWith('http')) return path;
  return `${BACKEND_URL}${path.startsWith('/') ? '' : '/'}${path}`;
}

function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
}

// ... (Giữ nguyên các hàm xử lý Form, Modal, Validate, API Save/Delete/Toggle như cũ) ...
function resetForm() {
  Object.assign(formData, { id: null, fullname: '', email: '', phone: '', address: '', role_id: roles.value[0]?.id || '', status: 'active', password: '', password_confirmation: '' });
  previewImage.value = null; avatarFile.value = null;
  if (fileInputRef.value) fileInputRef.value.value = '';
  Object.keys(errors).forEach(key => errors[key] = '');
}

function handleFileUpload(event) {
  const file = event.target.files[0];
  if (file) {
    if (!['image/jpeg', 'image/jpg', 'image/png', 'image/gif'].includes(file.type)) return Swal.fire('Lỗi', 'File không hợp lệ', 'warning');
    if (file.size > 10 * 1024 * 1024) return Swal.fire('Lỗi', 'Ảnh quá lớn (>10MB)', 'warning');
    avatarFile.value = file;
    previewImage.value = URL.createObjectURL(file);
  }
}

function openCreateModal() { resetForm(); isEditMode.value = false; userModalInstance.value.show(); }
function openEditModal(user) {
  resetForm(); isEditMode.value = true;
  Object.assign(formData, user);
  formData.phone = user.phone || ''; formData.address = user.address || '';
  previewImage.value = getAvatarUrl(user.avatar_url, user.fullname);
  userModalInstance.value.show();
}
function openViewModal(user) { viewingUser.value = user; viewModalInstance.value.show(); }

function validateForm() {
  Object.keys(errors).forEach(k => errors[k] = '');
  let isValid = true;
  if (!formData.fullname.trim()) { errors.fullname = 'Nhập họ tên.'; isValid = false; }
  if (!formData.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) { errors.email = 'Email không hợp lệ.'; isValid = false; }
  if (!formData.role_id) { Swal.fire('Lỗi', 'Chọn vai trò.', 'warning'); isValid = false; }
  if (formData.phone && !/^0\d{9}$/.test(formData.phone)) { errors.phone = 'SĐT 10 số bắt đầu bằng 0.'; isValid = false; }
  if (!isEditMode.value) {
    if (!formData.password || formData.password.length < 6) { errors.password = 'Mật khẩu >= 6 ký tự.'; isValid = false; }
    if (formData.password !== formData.password_confirmation) { errors.password_confirmation = 'Mật khẩu không khớp.'; isValid = false; }
  }
  return isValid;
}

async function handleSave() {
  if (!validateForm()) return;
  isLoading.value = true;
  const data = new FormData();
  Object.keys(formData).forEach(key => data.append(key, formData[key] || ''));
  if (avatarFile.value) data.append('avatar', avatarFile.value);
  if (isEditMode.value) data.append('_method', 'PATCH');

  try {
    const url = isEditMode.value ? `admin/admins/${formData.id}` : `admin/admins`;
    await apiService.post(url, data, { headers: { 'Content-Type': 'multipart/form-data' } });
    Swal.fire('Thành công', isEditMode.value ? 'Đã cập nhật!' : 'Đã tạo mới!', 'success');
    userModalInstance.value.hide();
    fetchUsers();
  } catch (e) {
    console.error(e);
    const errs = e.response?.data?.errors;
    if (errs) Object.keys(errs).forEach(k => { if (errors[k] !== undefined) errors[k] = errs[k][0]; });
    else Swal.fire('Lỗi', e.response?.data?.message || 'Lỗi server', 'error');
  } finally { isLoading.value = false; }
}

async function toggleUserStatus(user) {
  if (user.id === currentAdminId.value) return Swal.fire('Cảnh báo', 'Không thể tự khóa mình.', 'warning');
  const newStatus = user.status === 'active' ? 'inactive' : 'active';

  // Optimistic Update (Cập nhật UI trước)
  const oldStatus = user.status;
  user.status = newStatus;
  processingStatusIds.value.push(user.id); // Hiện spinner nhỏ

  try {
    await apiService.patch(`admin/admins/${user.id}`, { status: newStatus });
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
    Toast.fire({ icon: 'success', title: 'Cập nhật thành công' });
  } catch (e) {
    user.status = oldStatus; // Rollback
    Swal.fire('Lỗi', 'Cập nhật thất bại.', 'error');
  } finally {
    processingStatusIds.value = processingStatusIds.value.filter(id => id !== user.id);
  }
}

async function handleDelete(user) {
  if (user.id === currentAdminId.value) return Swal.fire('Cảnh báo', 'Không thể xóa chính mình.', 'warning');
  const res = await Swal.fire({ title: 'Xóa?', text: `Xóa vĩnh viễn ${user.fullname}?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Xóa' });
  if (res.isConfirmed) {
    try {
      await apiService.delete(`admin/admins/${user.id}`);
      users.value = users.value.filter(u => u.id !== user.id);
      Swal.fire('Đã xóa', '', 'success');
    } catch (e) { Swal.fire('Lỗi', 'Không thể xóa.', 'error'); }
  }
}

// Role Management
function resetRoleForm() { Object.assign(roleFormData, { id: null, value: '', label: '', badgeClass: 'text-bg-secondary' }); }
function openCreateRoleModal() { resetRoleForm(); isRoleEditMode.value = false; roleModalInstance.value.show(); }
function openEditRoleModal(role) { resetRoleForm(); isRoleEditMode.value = true; Object.assign(roleFormData, role); roleModalInstance.value.show(); }
async function handleSaveRole() {
  const payload = { ...roleFormData, value: roleFormData.value.toLowerCase() };
  try {
    if (isRoleEditMode.value) await apiService.put(`admin/roles/${payload.id}`, payload);
    else await apiService.post(`admin/roles`, payload);
    roleModalInstance.value.hide(); fetchRoles(); Swal.fire('Thành công', '', 'success');
  } catch (e) { Swal.fire('Lỗi', 'Lưu vai trò thất bại', 'error'); }
}
async function handleDeleteRole(role) {
  if (['admin', 'user'].includes(role.value)) return Swal.fire('Cấm', 'Không thể xóa role hệ thống', 'error');
  const res = await Swal.fire({ title: 'Xóa role?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' });
  if (res.isConfirmed) {
    try { await apiService.delete(`admin/roles/${role.id}`); fetchRoles(); Swal.fire('Đã xóa', '', 'success'); }
    catch (e) { Swal.fire('Lỗi', 'Xóa thất bại', 'error'); }
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
            <li class="breadcrumb-item active">Tài khoản nội bộ</li>
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
            <div class="d-flex align-items-center gap-2">
              <h3 class="card-title text-nowrap mb-0">Danh sách Nhân sự</h3>
              <!-- ĐÃ SỬA: Nút to hơn, có chữ -->
              <button type="button" class="btn btn-outline-info text-nowrap ms-2" @click="openCreateRoleModal"
                title="Quản lý Role">
                <i class="bi bi-gear-fill me-1"></i> Quản lý Vai trò
              </button>
            </div>

            <!-- TOOLBAR -->
            <div class="d-flex gap-2 flex-grow-1 justify-content-end" style="max-width: 600px;">
              <!-- ĐÃ THÊM: Dropdown lọc role -->
              <select class="form-select w-auto" v-model="selectedRoleFilter">
                <option value="">Tất cả vai trò</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.label }}</option>
              </select>

              <div class="input-group" style="min-width: 200px;">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm tên, email..."
                  v-model="searchQuery">
              </div>
              <button class="btn btn-primary text-nowrap" @click="openCreateModal"><i class="bi bi-plus-lg"></i> Thêm
                mới</button>
            </div>
          </div>

          <!-- DYNAMIC TABS NAVIGATION -->
          <ul class="nav nav-tabs card-header-tabs">
            <!-- Tabs Role -->
            <li class="nav-item" v-for="tab in dynamicTabs" :key="tab.key">
              <a class="nav-link" :class="{ active: activeTab === tab.key || (!activeTab && tab === dynamicTabs[0]) }"
                href="#" @click.prevent="setActiveTab(tab.key)">
                <span v-if="tab.key === 'inactive'"><i class="bi bi-lock-fill me-1 text-danger"></i></span>
                <span v-else><i class="bi bi-person-badge me-1"></i></span>
                {{ tab.label }}
                <span class="badge rounded-pill ms-1" :class="tab.badgeClass || 'bg-secondary'">{{ tab.data.length
                  }}</span>
              </a>
            </li>
            <!-- Empty State cho Tabs -->
            <li class="nav-item" v-if="dynamicTabs.length === 0 && !isLoading">
              <a class="nav-link disabled">Không có dữ liệu</a>
            </li>
          </ul>
        </div>

        <div class="card-body p-0">
          <!-- Loading -->
          <div v-if="isLoading && users.length === 0" class="text-center p-5">
            <div class="spinner-border text-primary" role="status"></div>
          </div>

          <div v-else>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Nhân viên</th>
                    <th>Liên hệ</th>
                    <th>Vai trò</th>
                    <th>Trạng thái</th>
                    <th>Ngày tạo</th>
                    <th class="text-end">Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="currentTabData.data.length === 0">
                    <td colspan="7" class="text-center py-4 text-muted">
                      {{ searchQuery ? 'Không tìm thấy kết quả phù hợp.' : 'Danh sách trống.' }}
                    </td>
                  </tr>
                  <tr v-for="user in currentTabData.data" :key="user.id"
                    :class="{ 'bg-light opacity-75': user.status !== 'active' && currentTabData.key !== 'inactive' }">
                    <td>{{ user.id }}</td>
                    <td>
                      <div class="d-flex align-items-center">
                        <img :src="getAvatarUrl(user.avatar_url, user.fullname)" class="rounded-circle me-3 border"
                          style="width: 40px; height: 40px; object-fit: cover;">
                        <div>
                          <div class="fw-bold text-dark">
                            {{ user.fullname }}
                            <span v-if="user.id === currentAdminId" class="badge bg-primary ms-1"
                              style="font-size: 0.65rem;">BẠN</span>
                          </div>
                          <div class="small text-muted">ID: {{ user.id }}</div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="d-flex flex-column small">
                        <span class="text-truncate" style="max-width: 200px;"><i
                            class="bi bi-envelope me-1 text-muted"></i> {{ user.email }}</span>
                        <span v-if="user.phone" class="text-muted"><i class="bi bi-telephone me-1"></i> {{ user.phone
                          }}</span>
                      </div>
                    </td>
                    <td><span :class="['badge', getRoleBadge(user.role_id)]">{{ getRoleLabel(user.role_id) }}</span>
                    </td>
                    <td>
                      <span :class="['badge', user.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
                        {{ user.status === 'active' ? 'Hoạt động' : 'Đã khóa' }}
                      </span>
                    </td>
                    <td class="small text-muted">{{ getFormattedDate(user.created_at) }}</td>
                    <td class="text-end">
                      <div class="d-flex justify-content-end align-items-center">
                        <!-- Spinner Local -->
                        <div v-if="processingStatusIds.includes(user.id)"
                          class="spinner-border text-primary spinner-border-sm me-2"></div>
                        <!-- Toggle Switch -->
                        <div v-else class="form-check form-switch me-2" title="Bật/Tắt trạng thái">
                          <input class="form-check-input" type="checkbox" role="switch"
                            style="width: 2.5em; height: 1.25em; cursor: pointer;" :checked="user.status === 'active'"
                            :disabled="user.id === currentAdminId" @click.prevent="toggleUserStatus(user)">
                        </div>

                        <!-- Nút Hành động (Edit luôn hiện) -->
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-secondary" title="Xem" @click="openViewModal(user)"><i
                              class="bi bi-eye"></i></button>
                          <button class="btn btn-outline-primary" title="Sửa" @click="openEditModal(user)"><i
                              class="bi bi-pencil"></i></button>
                          <button v-if="user.id !== currentAdminId" class="btn btn-outline-danger" title="Xóa"
                            @click="handleDelete(user)"><i class="bi bi-trash"></i></button>
                        </div>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div v-if="currentTabData.totalPages > 1" class="d-flex justify-content-end p-3">
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item" :class="{ disabled: currentTabData.currentPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="changePage(currentTabData.currentPage - 1)">&laquo;</a>
                </li>
                <li v-for="p in currentTabData.totalPages" :key="p" class="page-item"
                  :class="{ active: currentTabData.currentPage === p }">
                  <a class="page-link" href="#" @click.prevent="changePage(p)">{{ p }}</a>
                </li>
                <li class="page-item" :class="{ disabled: currentTabData.currentPage === currentTabData.totalPages }">
                  <a class="page-link" href="#" @click.prevent="changePage(currentTabData.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal User -->
  <div class="modal fade" id="userModal" ref="userModalRef" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ isEditMode ? 'Chỉnh sửa Tài khoản' : 'Thêm mới Tài khoản' }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSave" id="userForm">
            <div class="row">
              <div class="col-md-4 text-center mb-3">
                <div class="mb-3">
                  <img
                    :src="previewImage || `https://placehold.co/150x150/EBF4FF/1D62F0?text=${formData.fullname ? formData.fullname.charAt(0).toUpperCase() : '?'}`"
                    class="img-thumbnail rounded-circle mb-2" style="width: 150px; height: 150px; object-fit: cover;">
                  <div>
                    <input type="file" class="d-none" ref="fileInputRef" accept="image/*" @change="handleFileUpload">
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="$refs.fileInputRef.click()"><i
                        class="bi bi-upload"></i> Chọn ảnh</button>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-bold required">Vai trò</label>
                  <select class="form-select" v-model="formData.role_id"
                    :disabled="isEditMode && formData.id === currentAdminId">
                    <option value="" disabled>-- Chọn vai trò --</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.label }}</option>
                  </select>
                </div>
                <div class="mb-3" v-if="isEditMode">
                  <label class="form-label fw-bold">Trạng thái</label>
                  <select class="form-select" v-model="formData.status" :disabled="formData.id === currentAdminId">
                    <option value="active">Hoạt động</option>
                    <option value="inactive">Vô hiệu hóa</option>
                  </select>
                </div>
              </div>
              <div class="col-md-8">
                <div class="mb-3"><label class="form-label required">Họ và tên</label><input type="text"
                    class="form-control" :class="{ 'is-invalid': errors.fullname }" v-model="formData.fullname">
                  <div class="invalid-feedback">{{ errors.fullname }}</div>
                </div>
                <div class="mb-3"><label class="form-label required">Email</label><input type="email"
                    class="form-control" :class="{ 'is-invalid': errors.email }" v-model="formData.email"
                    :readonly="isEditMode">
                  <div class="invalid-feedback">{{ errors.email }}</div>
                </div>
                <template v-if="!isEditMode">
                  <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label required">Mật khẩu</label><input type="password"
                        class="form-control" :class="{ 'is-invalid': errors.password }" v-model="formData.password">
                      <div class="invalid-feedback">{{ errors.password }}</div>
                    </div>
                    <div class="col-md-6 mb-3"><label class="form-label required">Xác nhận</label><input type="password"
                        class="form-control" :class="{ 'is-invalid': errors.password_confirmation }"
                        v-model="formData.password_confirmation">
                      <div class="invalid-feedback">{{ errors.password_confirmation }}</div>
                    </div>
                  </div>
                </template>
                <div class="row">
                  <div class="col-md-6 mb-3"><label class="form-label">Số điện thoại</label><input type="tel"
                      class="form-control" :class="{ 'is-invalid': errors.phone }" v-model="formData.phone">
                    <div class="invalid-feedback">{{ errors.phone }}</div>
                  </div>
                  <div class="col-md-6 mb-3"><label class="form-label">Địa chỉ</label><input type="text"
                      class="form-control" v-model="formData.address"></div>
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="userForm" class="btn btn-primary" :disabled="isLoading">Lưu thay đổi</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Role & View Modal (Giữ nguyên cấu trúc nhưng ẩn bớt code cho gọn, vẫn đầy đủ logic) -->
  <div class="modal fade" id="roleModal" ref="roleModalRef" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Quản lý Vai trò</h5><button type="button" class="btn-close"
            data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <button class="btn btn-success mb-3" @click="resetRoleForm(); isRoleEditMode = false;">+ Thêm Vai trò</button>
          <div class="card p-3 mb-4 bg-light" v-if="!isRoleEditMode || roleFormData.id">
            <h6>{{ isRoleEditMode ? 'Sửa Vai trò' : 'Tạo Vai trò' }}</h6>
            <form @submit.prevent="handleSaveRole">
              <div class="row">
                <div class="col-md-4"><input type="text" class="form-control mb-2" v-model="roleFormData.value"
                    placeholder="Giá trị (admin)" :disabled="isRoleEditMode" required></div>
                <div class="col-md-4"><input type="text" class="form-control mb-2" v-model="roleFormData.label"
                    placeholder="Tên hiển thị (Quản trị)" required></div>
                <div class="col-md-4">
                  <select class="form-select mb-2" v-model="roleFormData.badgeClass">
                    <option value="text-bg-primary">Xanh dương</option>
                    <option value="text-bg-success">Xanh lá</option>
                    <option value="text-bg-danger">Đỏ</option>
                    <option value="text-bg-warning">Vàng</option>
                    <option value="text-bg-info">Lam</option>
                    <option value="text-bg-secondary">Xám</option>
                  </select>
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-sm">Lưu</button> <button type="button"
                class="btn btn-secondary btn-sm" @click="resetRoleForm">Hủy</button>
            </form>
          </div>
          <table class="table table-bordered table-sm">
            <thead>
              <tr>
                <th>ID</th>
                <th>Value</th>
                <th>Label</th>
                <th>Hành động</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in roles" :key="r.id">
                <td>{{ r.id }}</td>
                <td>{{ r.value }}</td>
                <td><span class="badge" :class="r.badgeClass">{{ r.label }}</span></td>
                <td>
                  <button class="btn btn-xs btn-info me-1" @click="openEditRoleModal(r)">Sửa</button>
                  <button class="btn btn-xs btn-danger" @click="handleDeleteRole(r)"
                    :disabled="['admin', 'user'].includes(r.value)">Xóa</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Xem -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 text-center">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
          <img :src="getAvatarUrl(viewingUser.avatar_url, viewingUser.fullname)" class="rounded-circle mb-3" width="100"
            height="100">
          <h4>{{ viewingUser.fullname }}</h4>
          <p class="text-muted">{{ viewingUser.email }}</p>
          <div class="d-flex justify-content-center gap-2 mb-3">
            <span class="badge" :class="getRoleBadge(viewingUser.role_id)">{{ getRoleLabel(viewingUser.role_id)
              }}</span>
            <span class="badge" :class="viewingUser.status === 'active' ? 'bg-success' : 'bg-danger'">{{
              viewingUser.status === 'active' ? 'Hoạt động' : 'Đã khóa' }}</span>
          </div>
          <ul class="list-group text-start">
            <li class="list-group-item"><i class="bi bi-telephone me-2"></i> {{ viewingUser.phone || '---' }}</li>
            <li class="list-group-item"><i class="bi bi-geo-alt me-2"></i> {{ viewingUser.address || '---' }}</li>
            <li class="list-group-item"><i class="bi bi-calendar me-2"></i> {{ getFormattedDate(viewingUser.created_at)
              }}
            </li>
          </ul>
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

.required::after {
  content: " *";
  color: red;
}

.table td .btn {
  margin: 2px;
}
</style>