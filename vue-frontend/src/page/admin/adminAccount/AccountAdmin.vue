<script setup>
import { ref, reactive, onMounted, computed, nextTick, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// ==========================================
// CONFIGURATION
// ==========================================
const rawApiUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';
const API_BASE_URL = rawApiUrl.replace(/\/api\/?$/, '');
const MAX_FILE_SIZE_MB = 5;

// ==========================================
// AUTHENTICATION & PERMISSIONS
// ==========================================
const currentUser = ref({});

const hasRole = (allowedRoles) => {
    const userRoleId = Number(currentUser.value?.role_id);
    let userRoleName = '';

    if (userRoleId === 11) userRoleName = 'admin';
    else if (userRoleId === 12) userRoleName = 'staff';
    else if (userRoleId === 13) userRoleName = 'blogger';

    if (!userRoleName) return false;
    if (userRoleName === 'admin') return true;

    return allowedRoles.includes(userRoleName);
};

const checkAuthState = async () => {
    const token = localStorage.getItem('adminToken');
    if (token) apiService.defaults.headers.common['Authorization'] = `Bearer ${token}`;

    const storedAdmin = localStorage.getItem('adminData');
    const storedUser = localStorage.getItem('user_info');
    let userData = null;

    try {
        if (storedAdmin) userData = JSON.parse(storedAdmin);
        else if (storedUser) userData = JSON.parse(storedUser);
    } catch (e) { console.error("Parse error", e); }

    if (userData) {
        currentUser.value = { ...userData, role_id: Number(userData.role_id) };
        if (currentUser.value.id) currentAdminId.value = currentUser.value.id; // Set ID
        return true;
    }

    if (token) {
        try {
            const response = await apiService.get('/user');
            let data = response.data;
            if (data.data && !data.id) data = data.data;
            
            currentUser.value = { ...data, role_id: Number(data.role_id) };
            if (currentUser.value.id) currentAdminId.value = currentUser.value.id; // Set ID
            localStorage.setItem('adminData', JSON.stringify(currentUser.value));
            return true;
        } catch (error) {
            console.error("Auth API Error:", error);
            return false;
        }
    }
    return false;
};

const requireLogin = () => {
    if (!currentUser.value.id) {
        Swal.fire({ icon: 'error', title: 'Truy cập bị từ chối', text: 'Phiên làm việc hết hạn.', confirmButtonText: 'Đăng nhập ngay' });
        return false;
    }
    // Chỉ Admin mới được quản lý tài khoản nội bộ
    if (!hasRole(['admin'])) {
        Swal.fire({ icon: 'warning', title: 'Quyền hạn', text: 'Bạn không có quyền quản lý nhân sự.' });
        return false;
    }
    return true;
};

// ==========================================
// STATE MANAGEMENT
// ==========================================
const admins = ref([]); // Đổi tên biến users -> admins cho rõ nghĩa
const roles = ref([]);
const isLoading = ref(true);
const isSaving = ref(false);
const searchQuery = ref('');
const selectedRoleFilter = ref(''); 
const currentAdminId = ref(null);
const processingStatusIds = ref([]);
const activeTab = ref(''); 
const sortOption = ref('newest'); // [NEW] Sort option

// Pagination State
const paginationState = reactive({});
const itemsPerPage = 8;

// Modals
const userModalInstance = ref(null);
const userModalRef = ref(null);
const isEditMode = ref(false);

const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingUser = ref({});

const roleModalInstance = ref(null);
const roleModalRef = ref(null);
const isRoleEditMode = ref(false);

// Form Data
const fileInputRef = ref(null);
const previewImage = ref(null);
const avatarFile = ref(null);

const formData = reactive({
    id: null, fullname: '', email: '', phone: '', address: '',
    role_id: '', status: 'active', password: '', password_confirmation: ''
});

const errors = reactive({
    fullname: '', email: '', phone: '', address: '',
    password: '', password_confirmation: '', role_id: ''
});

const roleFormData = reactive({
    id: null, value: '', label: '', badgeClass: 'text-bg-secondary',
});
const roleErrors = reactive({ value: '', label: '' });

// ==========================================
// COMPUTED & LOGIC
// ==========================================
const getRoleObj = (roleId) => roles.value.find(r => r.id == roleId) || {};
const getRoleLabel = (roleId) => getRoleObj(roleId).label || 'Chưa phân quyền';
const getRoleBadge = (roleId) => getRoleObj(roleId).badgeClass || 'text-bg-secondary';

function getAvatarUrl(path, name = 'User') {
    if (!path) return `https://placehold.co/150x150/EBF4FF/1D62F0?text=${name.charAt(0).toUpperCase()}`;
    if (path.startsWith('blob:') || path.startsWith('http')) return path;
    const baseUrl = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL;
    const cleanPath = path.startsWith('/') ? path : '/' + path;
    return `${baseUrl}${cleanPath}`;
}

// [UPDATED] Dynamic Tabs Logic
const dynamicTabs = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    let filtered = [...admins.value];

    // 1. Filter
    if (selectedRoleFilter.value) {
        filtered = filtered.filter(user => user.role_id === selectedRoleFilter.value);
    }

    if (query) {
        filtered = filtered.filter(user =>
            (user.fullname && user.fullname.toLowerCase().includes(query)) ||
            (user.email && user.email.toLowerCase().includes(query)) ||
            (user.phone && user.phone.includes(query)) ||
            getRoleLabel(user.role_id).toLowerCase().includes(query)
        );
    }

    // 2. Sort
    filtered.sort((a, b) => {
        // Admin hiện tại luôn lên đầu trong mọi list
        if (a.id === currentAdminId.value) return -1;
        if (b.id === currentAdminId.value) return 1;

        switch (sortOption.value) {
            case 'name_asc': return a.fullname.localeCompare(b.fullname);
            case 'name_desc': return b.fullname.localeCompare(a.fullname);
            case 'oldest': return new Date(a.created_at) - new Date(b.created_at);
            case 'newest': 
            default: return new Date(b.created_at) - new Date(a.created_at);
        }
    });

    const tabs = [];

    // 3. Create Tabs by Role (Active Users)
    roles.value.forEach(role => {
        if (selectedRoleFilter.value && selectedRoleFilter.value !== role.id) return;
        const roleUsers = filtered.filter(u => u.role_id === role.id && u.status === 'active');
        if (roleUsers.length > 0 || (!selectedRoleFilter.value && !query)) { // Show tab if data exists OR default state
             // Chỉ push tab nếu có data hoặc đang ở trạng thái mặc định để tránh tab trống khi search
             if(roleUsers.length > 0) {
                tabs.push({
                    key: `role_${role.id}`,
                    label: role.label,
                    badgeClass: role.badgeClass,
                    data: roleUsers,
                    type: 'role'
                });
             }
        }
    });

    // 4. Tab Inactive (All Roles)
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

// Current Data for Pagination
const currentTabData = computed(() => {
    let tab = dynamicTabs.value.find(t => t.key === activeTab.value);
    if (!tab && dynamicTabs.value.length > 0) tab = dynamicTabs.value[0];

    if (!tab) return { data: [], totalPages: 0, currentPage: 1, totalItems: 0 };

    if (!paginationState[tab.key]) paginationState[tab.key] = 1;

    const currentPage = paginationState[tab.key];
    const totalPages = Math.max(1, Math.ceil(tab.data.length / itemsPerPage));

    if (currentPage > totalPages) paginationState[tab.key] = 1;

    const start = (paginationState[tab.key] - 1) * itemsPerPage;
    const end = start + itemsPerPage;

    return {
        data: tab.data.slice(start, end),
        totalPages,
        currentPage: paginationState[tab.key],
        totalItems: tab.data.length,
        key: tab.key
    };
});

// Watchers
watch(dynamicTabs, (newTabs) => {
    if (newTabs.length > 0 && !newTabs.find(t => t.key === activeTab.value)) {
        activeTab.value = newTabs[0].key;
    }
});

watch([searchQuery, selectedRoleFilter, sortOption], () => {
    for (const key in paginationState) paginationState[key] = 1;
});

// ==========================================
// HELPER FUNCTIONS
// ==========================================
const changePage = (page) => {
    if (activeTab.value && paginationState[activeTab.value] !== undefined) {
        paginationState[activeTab.value] = page;
    }
};
const setActiveTab = (key) => activeTab.value = key;
const getFormattedDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('vi-VN');
};

// ==========================================
// ACTIONS & API
// ==========================================
async function fetchCurrentUser() {
    try {
        const response = await apiService.get('/user');
        if (response.data?.id) currentAdminId.value = response.data.id;
    } catch (e) { console.error("Error fetching current user", e); }
}

async function fetchAdmins() {
    if (admins.value.length === 0) isLoading.value = true;
    try {
        const response = await apiService.get(`admin/admins`);
        admins.value = response.data.map(user => ({
            ...user,
            fullname: user.fullname || 'No Name',
            created_at: user.created_at || new Date().toISOString(),
            status: user.status || 'active'
        }));
    } catch (error) {
        console.error("Error loading admins:", error);
        Swal.fire('Lỗi', 'Không thể tải danh sách nhân sự.', 'error');
    } finally {
        isLoading.value = false;
    }
}

async function fetchRoles() {
    try {
        const response = await apiService.get(`admin/roles`);
        roles.value = response.data;
    } catch (error) { console.error("Error roles:", error); roles.value = []; }
}

// FORM HANDLERS
function resetForm() {
    Object.assign(formData, { id: null, fullname: '', email: '', phone: '', address: '', role_id: roles.value[0]?.id || '', status: 'active', password: '', password_confirmation: '' });
    previewImage.value = null; avatarFile.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
    Object.keys(errors).forEach(key => errors[key] = '');
}

const handleFileUpload = async (event) => {
    const file = event.target.files[0];
    if (file) {
        if (!['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type)) return Swal.fire('Lỗi file', 'Chỉ chấp nhận ảnh JPG, PNG, WEBP.', 'warning');
        if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) return Swal.fire('Lỗi kích thước', `Ảnh tối đa ${MAX_FILE_SIZE_MB}MB.`, 'warning');
        avatarFile.value = file;
        previewImage.value = URL.createObjectURL(file);
    }
};

function openCreateModal() { 
    if(!requireLogin()) return;
    resetForm(); isEditMode.value = false; userModalInstance.value?.show(); 
}

function openEditModal(user) {
    if(!requireLogin()) return;
    resetForm(); isEditMode.value = true;
    Object.assign(formData, user);
    formData.phone = user.phone || ''; formData.address = user.address || '';
    previewImage.value = getAvatarUrl(user.avatar_url, user.fullname);
    userModalInstance.value?.show();
}

function openViewModal(user) { viewingUser.value = user; viewModalInstance.value?.show(); }

function validateForm() {
    Object.keys(errors).forEach(k => errors[k] = '');
    let isValid = true;
    if (!formData.fullname.trim()) { errors.fullname = 'Vui lòng nhập họ tên.'; isValid = false; }
    if (!formData.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) { errors.email = 'Email không hợp lệ.'; isValid = false; }
    if (!formData.role_id) { errors.role_id = 'Vui lòng chọn vai trò.'; isValid = false; }
    if (formData.phone && !/^(0|\+84)\d{9,10}$/.test(formData.phone)) { errors.phone = 'Số điện thoại không hợp lệ.'; isValid = false; }
    
    if (!isEditMode.value) {
        if (!formData.password || formData.password.length < 6) { errors.password = 'Mật khẩu tối thiểu 6 ký tự.'; isValid = false; }
        if (formData.password !== formData.password_confirmation) { errors.password_confirmation = 'Mật khẩu xác nhận không khớp.'; isValid = false; }
    } else {
        // Edit mode: nếu nhập pass mới thì mới check
        if (formData.password && formData.password.length < 6) { errors.password = 'Mật khẩu mới tối thiểu 6 ký tự.'; isValid = false; }
        if (formData.password && formData.password !== formData.password_confirmation) { errors.password_confirmation = 'Mật khẩu xác nhận không khớp.'; isValid = false; }
    }
    return isValid;
}

async function handleSave() {
    if (!requireLogin()) return;
    if (!validateForm()) return;
    
    isSaving.value = true;
    const data = new FormData();
    Object.keys(formData).forEach(key => data.append(key, formData[key] || ''));
    if (avatarFile.value) data.append('avatar', avatarFile.value);
    if (isEditMode.value) data.append('_method', 'PATCH');

    try {
        const url = isEditMode.value ? `admin/admins/${formData.id}` : `admin/admins`;
        await apiService.post(url, data, { headers: { 'Content-Type': 'multipart/form-data' } });
        Swal.fire('Thành công', isEditMode.value ? 'Đã cập nhật thông tin!' : 'Đã tạo tài khoản mới!', 'success');
        userModalInstance.value?.hide();
        fetchAdmins();
    } catch (e) {
        console.error(e);
        const errs = e.response?.data?.errors;
        if (errs) {
            Object.keys(errs).forEach(k => { if (errors[k] !== undefined) errors[k] = errs[k][0]; });
            Swal.fire('Lỗi dữ liệu', 'Vui lòng kiểm tra lại thông tin.', 'warning');
        }
        else Swal.fire('Lỗi Server', e.response?.data?.message || 'Không thể lưu dữ liệu.', 'error');
    } finally { isSaving.value = false; }
}

async function toggleUserStatus(user) {
    if (!requireLogin()) return;
    if (user.id === currentAdminId.value) return Swal.fire('Cảnh báo', 'Bạn không thể tự khóa tài khoản của chính mình.', 'warning');
    
    const newStatus = user.status === 'active' ? 'inactive' : 'active';
    const actionName = newStatus === 'active' ? 'KÍCH HOẠT' : 'VÔ HIỆU HÓA';
    const confirmColor = newStatus === 'active' ? '#009981' : '#d33';

    const result = await Swal.fire({
        title: `Xác nhận ${actionName}?`,
        text: `Tài khoản "${user.fullname}" sẽ được chuyển sang trạng thái ${newStatus}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        confirmButtonText: 'Đồng ý', cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        // Optimistic Update
        const oldStatus = user.status;
        user.status = newStatus;
        processingStatusIds.value.push(user.id);

        try {
            await apiService.patch(`admin/admins/${user.id}`, { status: newStatus });
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Cập nhật thành công', timer: 1500, showConfirmButton: false });
        } catch (e) {
            user.status = oldStatus; // Rollback
            Swal.fire('Lỗi', 'Cập nhật thất bại.', 'error');
        } finally {
            processingStatusIds.value = processingStatusIds.value.filter(id => id !== user.id);
        }
    }
}

async function handleDelete(user) {
    if (!requireLogin()) return;
    if (user.id === currentAdminId.value) return Swal.fire('Cảnh báo', 'Không thể xóa tài khoản đang đăng nhập.', 'warning');
    
    const res = await Swal.fire({ 
        title: 'Xóa vĩnh viễn?', 
        text: `Tài khoản "${user.fullname}" sẽ bị xóa hoàn toàn khỏi hệ thống!`, 
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#d33', 
        confirmButtonText: 'Xóa ngay' 
    });

    if (res.isConfirmed) {
        try {
            await apiService.delete(`admin/admins/${user.id}`);
            admins.value = admins.value.filter(u => u.id !== user.id);
            Swal.fire('Đã xóa', 'Tài khoản đã được xóa thành công.', 'success');
        } catch (e) { Swal.fire('Lỗi', 'Không thể xóa tài khoản này.', 'error'); }
    }
}

// Role Management Logic
function resetRoleForm() { Object.assign(roleFormData, { id: null, value: '', label: '', badgeClass: 'text-bg-secondary' }); }
function openCreateRoleModal() { if(!requireLogin()) return; resetRoleForm(); isRoleEditMode.value = false; roleModalInstance.value?.show(); }
function openEditRoleModal(role) { if(!requireLogin()) return; resetRoleForm(); isRoleEditMode.value = true; Object.assign(roleFormData, role); roleModalInstance.value?.show(); }

async function handleSaveRole() {
    if (!roleFormData.label.trim() || !roleFormData.value.trim()) return Swal.fire('Thiếu thông tin', 'Vui lòng nhập Tên và Mã vai trò.', 'warning');
    
    const payload = { ...roleFormData, value: roleFormData.value.toLowerCase() };
    try {
        if (isRoleEditMode.value) await apiService.put(`admin/roles/${payload.id}`, payload);
        else await apiService.post(`admin/roles`, payload);
        
        roleModalInstance.value?.hide(); 
        fetchRoles(); 
        Swal.fire('Thành công', 'Đã lưu vai trò.', 'success');
    } catch (e) { Swal.fire('Lỗi', 'Lưu vai trò thất bại.', 'error'); }
}

async function handleDeleteRole(role) {
    if (!requireLogin()) return;
    // Chặn xóa role hệ thống
    if (['admin', 'user', 'staff', 'blogger'].includes(role.value)) return Swal.fire('Cấm', 'Không thể xóa vai trò mặc định của hệ thống.', 'error');
    
    const res = await Swal.fire({ title: 'Xóa vai trò?', text: 'Hành động này không thể hoàn tác.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' });
    if (res.isConfirmed) {
        try { await apiService.delete(`admin/roles/${role.id}`); fetchRoles(); Swal.fire('Đã xóa', '', 'success'); }
        catch (e) { Swal.fire('Lỗi', 'Xóa thất bại.', 'error'); }
    }
}

// Lifecycle
onMounted(async () => {
    await checkAuthState();
    if (!requireLogin()) { isLoading.value = false; return; }
    
    fetchRoles().then(() => fetchAdmins());

    nextTick(() => {
        if (userModalRef.value) userModalInstance.value = new Modal(userModalRef.value, { backdrop: 'static' });
        if (roleModalRef.value) roleModalInstance.value = new Modal(roleModalRef.value);
        if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value);
    });
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0 text-brand">Quản lý Nhân sự (Nội bộ)</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active">Nhân sự</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4 shadow-sm border-0">
                
                <!-- HEADER & TOOLBAR -->
                <div class="card-header border-bottom-0 pb-0 bg-white">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="card-title text-secondary mb-0 fw-bold">Danh sách Nhân sự</h5>
                            <button type="button" class="btn btn-sm btn-outline-info text-nowrap ms-2 shadow-sm" @click="openCreateRoleModal" title="Quản lý Role">
                                <i class="bi bi-shield-lock-fill me-1"></i> Cấu hình Vai trò
                            </button>
                        </div>

                        <div class="d-flex gap-2 flex-grow-1 justify-content-end" style="max-width: 700px;">
                            <!-- Sort Dropdown -->
                            <select class="form-select w-auto" v-model="sortOption">
                                <option value="newest">Mới nhất</option>
                                <option value="oldest">Cũ nhất</option>
                                <option value="name_asc">Tên (A-Z)</option>
                                <option value="name_desc">Tên (Z-A)</option>
                            </select>

                            <!-- Role Filter -->
                            <select class="form-select w-auto" v-model="selectedRoleFilter">
                                <option value="">Tất cả vai trò</option>
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.label }}</option>
                            </select>

                            <!-- Search -->
                            <div class="input-group" style="min-width: 200px;">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm tên, email..." v-model="searchQuery">
                            </div>
                            
                            <!-- Add Button -->
                            <button class="btn btn-primary text-nowrap shadow-sm" @click="openCreateModal">
                                <i class="bi bi-person-plus-fill me-1"></i> Thêm mới
                            </button>
                        </div>
                    </div>

                    <!-- DYNAMIC TABS -->
                    <ul class="nav nav-tabs card-header-tabs custom-tabs">
                        <li class="nav-item" v-for="tab in dynamicTabs" :key="tab.key">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === tab.key || (!activeTab && tab === dynamicTabs[0]) }" href="#" @click.prevent="setActiveTab(tab.key)">
                                <span v-if="tab.key === 'inactive'"><i class="bi bi-lock-fill me-1 text-danger"></i></span>
                                <span v-else><i class="bi bi-person-badge me-1"></i></span>
                                {{ tab.label }}
                                <span class="badge rounded-pill ms-2" :class="tab.badgeClass || 'bg-secondary'">{{ tab.data.length }}</span>
                            </a>
                        </li>
                        <li class="nav-item" v-if="dynamicTabs.length === 0 && !isLoading">
                            <a class="nav-link disabled text-muted">Không có dữ liệu</a>
                        </li>
                    </ul>
                </div>

                <!-- TABLE CONTENT -->
                <div class="card-body p-0">
                    <div v-if="isLoading && admins.length === 0" class="text-center p-5">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>

                    <div v-else>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 custom-table">
                                <thead class="bg-light text-secondary">
                                    <tr>
                                        <th style="width: 50px;" class="ps-3">ID</th>
                                        <th>Nhân viên</th>
                                        <th>Liên hệ</th>
                                        <th>Vai trò</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th class="text-end pe-3">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="currentTabData.data.length === 0">
                                        <td colspan="7" class="text-center py-5 text-muted fst-italic">
                                            {{ searchQuery ? 'Không tìm thấy kết quả phù hợp.' : 'Danh sách trống.' }}
                                        </td>
                                    </tr>
                                    <tr v-for="user in currentTabData.data" :key="user.id" :class="{ 'bg-light opacity-75': user.status !== 'active' && currentTabData.key !== 'inactive' }">
                                        <td class="ps-3 text-muted fw-bold">#{{ user.id }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img :src="getAvatarUrl(user.avatar_url, user.fullname)" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                                <div>
                                                    <div class="fw-bold text-dark">
                                                        {{ user.fullname }}
                                                        <span v-if="user.id === currentAdminId" class="badge bg-primary ms-1" style="font-size: 0.65rem;">Bạn</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column small">
                                                <span class="text-truncate" style="max-width: 200px;"><i class="bi bi-envelope me-1 text-muted"></i> {{ user.email }}</span>
                                                <span v-if="user.phone" class="text-muted mt-1"><i class="bi bi-telephone me-1"></i> {{ user.phone }}</span>
                                            </div>
                                        </td>
                                        <td><span :class="['badge', getRoleBadge(user.role_id)]">{{ getRoleLabel(user.role_id) }}</span></td>
                                        <td>
                                            <span :class="['badge', user.status === 'active' ? 'bg-success' : 'bg-danger']">
                                                {{ user.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
                                            </span>
                                        </td>
                                        <td class="small text-muted">{{ getFormattedDate(user.created_at) }}</td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex justify-content-end align-items-center gap-2">
                                                <div v-if="processingStatusIds.includes(user.id)" class="spinner-border text-primary spinner-border-sm"></div>
                                                <div v-else class="form-check form-switch m-0" title="Bật/Tắt trạng thái">
                                                    <input class="form-check-input custom-switch cursor-pointer" type="checkbox" role="switch"
                                                        :checked="user.status === 'active'" 
                                                        :disabled="user.id === currentAdminId" 
                                                        @click.prevent="toggleUserStatus(user)">
                                                </div>

                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-light text-secondary border" title="Xem" @click="openViewModal(user)"><i class="bi bi-eye"></i></button>
                                                    <button class="btn btn-light text-primary border" title="Sửa" @click="openEditModal(user)"><i class="bi bi-pencil"></i></button>
                                                    <button v-if="user.id !== currentAdminId" class="btn btn-light text-danger border" title="Xóa" @click="handleDelete(user)"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="currentTabData.totalPages > 1" class="card-footer bg-white border-top-0 py-3">
                            <div class="d-flex justify-content-end">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item" :class="{ disabled: currentTabData.currentPage === 1 }"><button class="page-link border-0" @click="changePage(currentTabData.currentPage - 1)">&laquo;</button></li>
                                    <li v-for="p in currentTabData.totalPages" :key="p" class="page-item" :class="{ active: currentTabData.currentPage === p }"><button class="page-link border-0 rounded-circle mx-1" @click="changePage(p)">{{ p }}</button></li>
                                    <li class="page-item" :class="{ disabled: currentTabData.currentPage === currentTabData.totalPages }"><button class="page-link border-0" @click="changePage(currentTabData.currentPage + 1)">&raquo;</button></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL USER -->
    <div class="modal fade" id="userModal" ref="userModalRef" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-brand">{{ isEditMode ? 'Chỉnh sửa Tài khoản' : 'Thêm mới Nhân sự' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="handleSave" id="userForm">
                        <div class="row g-4">
                            <!-- Avatar -->
                            <div class="col-md-4 text-center border-end">
                                <div class="mb-3 position-relative d-inline-block">
                                    <img :src="previewImage || `https://placehold.co/150x150/EBF4FF/1D62F0?text=${formData.fullname ? formData.fullname.charAt(0).toUpperCase() : '?'}`" class="img-thumbnail rounded-circle shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                                    <input type="file" class="d-none" ref="fileInputRef" accept="image/*" @change="handleFileUpload">
                                    <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" @click="$refs.fileInputRef.click()"><i class="bi bi-camera-fill"></i></button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold required">Vai trò</label>
                                    <select class="form-select" v-model="formData.role_id" :disabled="isEditMode && formData.id === currentAdminId" :class="{'is-invalid': errors.role_id}">
                                        <option value="" disabled>-- Chọn --</option>
                                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.label }}</option>
                                    </select>
                                    <div class="invalid-feedback">{{ errors.role_id }}</div>
                                </div>
                                <div class="mb-3" v-if="isEditMode">
                                    <label class="form-label fw-bold">Trạng thái</label>
                                    <select class="form-select" v-model="formData.status" :disabled="formData.id === currentAdminId">
                                        <option value="active">Hoạt động</option>
                                        <option value="inactive">Vô hiệu hóa</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Info -->
                            <div class="col-md-8 ps-md-4">
                                <div class="mb-3">
                                    <label class="form-label required fw-bold">Họ và tên</label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.fullname }" v-model="formData.fullname" placeholder="Nguyễn Văn A">
                                    <div class="invalid-feedback">{{ errors.fullname }}</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required fw-bold">Email</label>
                                        <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" v-model="formData.email" :readonly="isEditMode">
                                        <div class="invalid-feedback">{{ errors.email }}</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Số điện thoại</label>
                                        <input type="tel" class="form-control" :class="{ 'is-invalid': errors.phone }" v-model="formData.phone" placeholder="09xxxxxxxx">
                                        <div class="invalid-feedback">{{ errors.phone }}</div>
                                    </div>
                                </div>
                                <div class="mb-3" v-if="!isEditMode || formData.password">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold" :class="{'required': !isEditMode}">Mật khẩu</label>
                                            <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" v-model="formData.password" placeholder="******">
                                            <div class="invalid-feedback">{{ errors.password }}</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold" :class="{'required': !isEditMode}">Xác nhận</label>
                                            <input type="password" class="form-control" :class="{ 'is-invalid': errors.password_confirmation }" v-model="formData.password_confirmation" placeholder="******">
                                            <div class="invalid-feedback">{{ errors.password_confirmation }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Địa chỉ</label>
                                    <textarea class="form-control" v-model="formData.address" rows="2" placeholder="Nhập địa chỉ..."></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" form="userForm" class="btn btn-primary px-4" :disabled="isSaving">
                        <span v-if="isSaving" class="spinner-border spinner-border-sm me-1"></span> {{ isEditMode ? 'Lưu thay đổi' : 'Tạo tài khoản' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ROLE CONFIG -->
    <div class="modal fade" id="roleModal" ref="roleModalRef" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold text-white">Cấu hình Vai trò Hệ thống</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="card border-0 shadow-sm mb-4 bg-light">
                        <div class="card-body">
                            <h6 class="fw-bold text-brand mb-3">{{ isRoleEditMode ? 'Chỉnh sửa Vai trò' : 'Thêm Vai trò Mới' }}</h6>
                            <form @submit.prevent="handleSaveRole">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="small text-muted">Mã (VD: admin)</label>
                                        <input type="text" class="form-control" v-model="roleFormData.value" :disabled="isRoleEditMode" required placeholder="Mã vai trò...">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small text-muted">Tên hiển thị (VD: Quản trị)</label>
                                        <input type="text" class="form-control" v-model="roleFormData.label" required placeholder="Tên hiển thị...">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-muted">Màu sắc</label>
                                        <select class="form-select" v-model="roleFormData.badgeClass">
                                            <option value="text-bg-primary">Xanh</option>
                                            <option value="text-bg-success">Lục</option>
                                            <option value="text-bg-danger">Đỏ</option>
                                            <option value="text-bg-warning">Vàng</option>
                                            <option value="text-bg-info">Lam</option>
                                            <option value="text-bg-dark">Đen</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex gap-1">
                                        <button type="submit" class="btn btn-primary w-100">Lưu</button>
                                        <button type="button" class="btn btn-secondary w-100" @click="resetRoleForm" v-if="isRoleEditMode">Hủy</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr><th class="ps-3">ID</th><th>Mã</th><th>Tên hiển thị</th><th class="text-end pe-3">Hành động</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in roles" :key="r.id">
                                    <td class="ps-3 text-muted fw-bold">#{{ r.id }}</td>
                                    <td><code class="text-primary">{{ r.value }}</code></td>
                                    <td><span class="badge" :class="r.badgeClass">{{ r.label }}</span></td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-light text-primary border me-1" @click="openEditRoleModal(r)">Sửa</button>
                                        <button class="btn btn-sm btn-light text-danger border" @click="handleDeleteRole(r)" :disabled="['admin', 'user', 'staff'].includes(r.value)">Xóa</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL VIEW DETAIL -->
    <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-0 position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    <div class="text-center bg-light p-5 rounded-top">
                        <img :src="getAvatarUrl(viewingUser.avatar_url, viewingUser.fullname)" class="rounded-circle shadow mb-3 border border-3 border-white" width="120" height="120">
                        <h4 class="fw-bold text-dark mb-1">{{ viewingUser.fullname }}</h4>
                        <div class="text-muted small mb-3">{{ viewingUser.email }}</div>
                        <div class="d-flex justify-content-center gap-2">
                            <span class="badge" :class="getRoleBadge(viewingUser.role_id)">{{ getRoleLabel(viewingUser.role_id) }}</span>
                            <span class="badge" :class="viewingUser.status === 'active' ? 'bg-success' : 'bg-danger'">{{ viewingUser.status === 'active' ? 'Hoạt động' : 'Đã khóa' }}</span>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="row g-3">
                            <div class="col-12 d-flex align-items-center p-2 border-bottom">
                                <div class="bg-light p-2 rounded me-3"><i class="bi bi-telephone text-primary"></i></div>
                                <div><div class="small text-muted">Điện thoại</div><div class="fw-medium">{{ viewingUser.phone || 'Chưa cập nhật' }}</div></div>
                            </div>
                            <div class="col-12 d-flex align-items-center p-2 border-bottom">
                                <div class="bg-light p-2 rounded me-3"><i class="bi bi-geo-alt text-danger"></i></div>
                                <div><div class="small text-muted">Địa chỉ</div><div class="fw-medium">{{ viewingUser.address || 'Chưa cập nhật' }}</div></div>
                            </div>
                            <div class="col-12 d-flex align-items-center p-2">
                                <div class="bg-light p-2 rounded me-3"><i class="bi bi-calendar-event text-secondary"></i></div>
                                <div><div class="small text-muted">Ngày tham gia</div><div class="fw-medium">{{ getFormattedDate(viewingUser.created_at) }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* COLORS */
.text-brand { color: #009981 !important; }
.text-primary { color: #009981 !important; }
.bg-primary { background-color: #009981 !important; }
.btn-primary { background-color: #009981 !important; border-color: #009981 !important; color: white !important; }
.btn-primary:hover { background-color: #007a67 !important; border-color: #007a67 !important; }
.btn-outline-primary { color: #009981 !important; border-color: #009981 !important; }
.btn-outline-primary:hover { background-color: #009981 !important; color: white !important; }

/* TABS */
.custom-tabs .nav-link { color: #6c757d; border: none; font-weight: 500; padding: 12px 20px; border-bottom: 3px solid transparent; cursor: pointer; }
.custom-tabs .nav-link:hover { color: #009981; }
.custom-tabs .nav-link.active { color: #009981; background: transparent; border-bottom: 3px solid #009981; }

/* PAGINATION */
.page-item.active .page-link { background-color: #009981 !important; border-color: #009981 !important; color: white !important; }
.page-link { color: #666; cursor: pointer; }

/* COMPONENTS */
.form-label.required::after { content: " *"; color: red; }
.cursor-pointer { cursor: pointer; }
.custom-switch:checked { background-color: #009981; border-color: #009981; }
:deep(.table-striped-columns) tbody tr td:nth-child(even) { background-color: rgba(0, 0, 0, 0.02); }
.custom-table th { font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
</style>