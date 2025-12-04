<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// CONFIGURATION
const rawApiUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';
const API_BASE_URL = rawApiUrl.replace(/\/api\/?$/, '');
const MAX_FILE_SIZE_MB = 5;

// AUTHENTICATION & PERMISSIONS
const currentUser = ref({});

const hasRole = (allowedRoles) => {
    const userRoleId = Number(currentUser.value?.role_id);
    let userRoleName = '';

    if (userRoleId === 1) userRoleName = 'admin';
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
        return true;
    }

    if (token) {
        try {
            const response = await apiService.get('/user');
            let data = response.data;
            if (data.data && !data.id) data = data.data;
            
            currentUser.value = { ...data, role_id: Number(data.role_id) };
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
    // Admin và Staff được quản lý user
    if (!hasRole(['admin', 'staff'])) {
        Swal.fire({ icon: 'warning', title: 'Quyền hạn', text: 'Bạn không có quyền quản lý khách hàng.' });
        return false;
    }
    return true;
};

// STATE MANAGEMENT
const allCustomers = ref([]);
const isLoading = ref(true);
const isSaving = ref(false);
const isEditMode = ref(false);
const searchQuery = ref('');
const activeTab = ref('active'); // active | inactive

// Modals
const modalInstance = ref(null);
const modalRef = ref(null);
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingCustomer = ref({});

// Pagination
const pagination = reactive({
    active: { currentPage: 1, itemsPerPage: 10 },
    inactive: { currentPage: 1, itemsPerPage: 10 }
});

// Form Data
const formData = reactive({
    id: null,
    fullName: '', 
    email: '',
    phone: '',
    address: '',
    sex: 'other',      // [NEW] Giới tính
    birthday: '',      // [NEW] Ngày sinh
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

// COMPUTED & LOGIC
function getImageUrl(path) {
    if (!path) return 'https://placehold.co/150x150?text=User';
    if (path.startsWith('blob:') || path.startsWith('http')) return path;
    const baseUrl = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL;
    const cleanPath = path.startsWith('/') ? path : '/' + path;
    return `${baseUrl}${cleanPath}`;
}

// 1. Filter & Sort
const processedCustomers = computed(() => {
    let result = [...allCustomers.value];
    const query = searchQuery.value.toLowerCase().trim();

    if (query) {
        result = result.filter(c =>
            (c.fullName && c.fullName.toLowerCase().includes(query)) ||
            (c.email && c.email.toLowerCase().includes(query)) ||
            (c.phone && c.phone.includes(query))
        );
    }

    // Sort mới nhất trước
    result.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    return result;
});

// 2. Split Lists
const activeList = computed(() => processedCustomers.value.filter(c => c.status === 'active'));
const inactiveList = computed(() => processedCustomers.value.filter(c => c.status !== 'active'));

// 3. Status Counts
const statusCounts = computed(() => ({
    active: activeList.value.length,
    inactive: inactiveList.value.length
}));

// 4. Pagination Helper
function getPaginatedData(list, type) {
    const pageInfo = pagination[type];
    const totalPages = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));
    if (pageInfo.currentPage > totalPages) pageInfo.currentPage = 1;

    const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
    return { 
        data: list.slice(start, start + pageInfo.itemsPerPage), 
        totalPages 
    };
}

const pagedActive = computed(() => getPaginatedData(activeList.value, 'active'));
const pagedInactive = computed(() => getPaginatedData(inactiveList.value, 'inactive'));

// Watchers
watch(searchQuery, () => {
    pagination.active.currentPage = 1;
    pagination.inactive.currentPage = 1;
});

// HELPER FUNCTIONS
const changePage = (type, page) => { pagination[type].currentPage = page; };
const setActiveTab = (tabName) => activeTab.value = tabName;

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('vi-VN');
};

// Định dạng ngày cho input date (YYYY-MM-DD)
const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    try {
        return new Date(dateString).toISOString().split('T')[0];
    } catch (e) { return ''; }
};

const getGenderText = (sex) => {
    if (sex === 'male') return 'Nam';
    if (sex === 'female') return 'Nữ';
    return 'Khác';
};

const getGenderBadge = (sex) => {
    if (sex === 'male') return 'bg-info text-dark';
    if (sex === 'female') return 'bg-danger bg-opacity-75';
    return 'bg-secondary';
};

const resetForm = () => {
    Object.assign(formData, {
        id: null, fullName: '', email: '', phone: '', address: '',
        sex: 'other', birthday: '',
        avatar_url: '', status: 'active', password: '', password_confirmation: ''
    });
    avatarFile.value = null;
    avatarPreview.value = null;
    Object.keys(errors).forEach(key => errors[key] = '');
};

// Validate Image
const onFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
            Swal.fire('Lỗi', `Kích thước ảnh quá lớn (tối đa ${MAX_FILE_SIZE_MB}MB).`, 'warning');
            e.target.value = ''; 
            return;
        }
        if (!['image/jpeg', 'image/png', 'image/webp', 'image/jpg'].includes(file.type)) {
            Swal.fire('Lỗi', 'Chỉ chấp nhận file ảnh (JPG, PNG, WEBP).', 'warning');
            e.target.value = '';
            return;
        }
        avatarFile.value = file;
        avatarPreview.value = URL.createObjectURL(file);
    }
};

const validateForm = () => {
    Object.keys(errors).forEach(key => errors[key] = '');
    let isValid = true;

    if (!formData.fullName.trim()) { errors.fullName = 'Vui lòng nhập họ và tên.'; isValid = false; }
    
    if (!formData.email) { errors.email = 'Vui lòng nhập email.'; isValid = false; }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) { errors.email = 'Email không đúng định dạng.'; isValid = false; }
    
    if (formData.phone && !/^[0-9]{10,11}$/.test(formData.phone)) { errors.phone = 'Số điện thoại không hợp lệ (10-11 số).'; isValid = false; }

    if (!isEditMode.value) {
        if (!formData.password) { errors.password = 'Vui lòng nhập mật khẩu.'; isValid = false; }
        else if (formData.password.length < 6) { errors.password = 'Mật khẩu phải có ít nhất 6 ký tự.'; isValid = false; }
        
        if (formData.password !== formData.password_confirmation) { 
            errors.password_confirmation = 'Mật khẩu xác nhận không khớp.'; isValid = false; 
        }
    }
    return isValid;
};

// ACTIONS (API)
async function fetchCustomers() {
    if (allCustomers.value.length === 0) isLoading.value = true;
    try {
        const response = await apiService.get(`admin/users`);
        
        // Map dữ liệu từ API
        allCustomers.value = response.data.map(customer => ({
            ...customer,
            fullName: customer.fullName || customer.name || 'Không tên', 
            status: customer.status || 'active',
            avatar_url: getImageUrl(customer.avatar_url || customer.avatar),
            sex: customer.sex || 'other', // Default sex
            birthday: customer.birthday || null
        }));
    } catch (error) {
        console.error("Lỗi tải danh sách khách hàng:", error);
        Swal.fire('Lỗi', 'Không thể tải danh sách khách hàng.', 'error');
    } finally {
        isLoading.value = false;
    }
}

async function handleSave() {
    if (!requireLogin()) return;
    if (!validateForm()) return;

    isSaving.value = true;
    const formDataPayload = new FormData();
    formDataPayload.append('name', formData.fullName); // Controller dùng 'name' map sang 'fullName'
    formDataPayload.append('email', formData.email);
    
    if (formData.phone) formDataPayload.append('phone', formData.phone);
    if (formData.address) formDataPayload.append('address', formData.address);
    if (formData.status) formDataPayload.append('status', formData.status);
    if (formData.sex) formDataPayload.append('sex', formData.sex);
    if (formData.birthday) formDataPayload.append('birthday', formData.birthday);

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
        modalInstance.value?.hide();
        fetchCustomers(); 
    } catch (error) {
        console.error("Lỗi lưu:", error);
        const errorsData = error.response?.data?.errors;
        // Hiển thị lỗi cụ thể từ Laravel (VD: Email trùng)
        if (errorsData) {
             if (errorsData.email) errors.email = errorsData.email[0];
             if (errorsData.phone) errors.phone = errorsData.phone[0];
             if (errorsData.password) errors.password = errorsData.password[0];
             Swal.fire('Lỗi dữ liệu', 'Vui lòng kiểm tra lại thông tin nhập vào.', 'warning');
        } else {
             Swal.fire('Lỗi', error.response?.data?.message || 'Đã có lỗi xảy ra.', 'error');
        }
    } finally {
        isSaving.value = false;
    }
}

async function toggleStatus(customer) {
    if (!requireLogin()) return;

    const oldStatus = customer.status;
    const newStatus = customer.status === 'active' ? 'inactive' : 'active';
    const actionText = newStatus === 'inactive' ? 'KHÓA' : 'MỞ KHÓA';
    const confirmColor = newStatus === 'inactive' ? '#d33' : '#009981';

    // Hiển thị SweetAlert xác nhận trước
    const result = await Swal.fire({
        title: `Xác nhận ${actionText}?`,
        text: `Bạn muốn ${actionText.toLowerCase()} tài khoản "${customer.fullName}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        confirmButtonText: 'Đồng ý', 
        cancelButtonText: 'Hủy'
    });

    // Chỉ thực hiện nếu người dùng bấm "Đồng ý"
    if (result.isConfirmed) {
        // Optimistic Update
        customer.status = newStatus;

        try {
            await apiService.patch(`admin/users/${customer.id}`, { status: newStatus });
            
            // Toast thông báo thành công
            const Toast = Swal.mixin({
                toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true
            });
            Toast.fire({ icon: 'success', title: `Đã ${actionText.toLowerCase()} tài khoản` });

            fetchCustomers(); // Refresh list
        } catch (error) {
            customer.status = oldStatus; // Rollback UI nếu lỗi
            Swal.fire('Lỗi', `Không thể ${actionText.toLowerCase()} tài khoản này.`, 'error');
        }
    }
}

async function handleDelete(customer) {
    if (!requireLogin()) return;

    const result = await Swal.fire({
        title: 'Xác nhận xóa?',
        text: `Bạn có chắc muốn xóa vĩnh viễn "${customer.fullName}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa ngay', 
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`admin/users/${customer.id}`);
            allCustomers.value = allCustomers.value.filter(c => c.id !== customer.id);
            Swal.fire('Đã xóa!', 'Tài khoản đã được xóa thành công.', 'success');
        } catch (error) {
            console.error("Lỗi khi xóa khách hàng:", error);
            Swal.fire('Lỗi', 'Không thể xóa khách hàng này.', 'error');
        }
    }
}

// Modal Helpers
const openCreateModal = () => { if (!requireLogin()) return; resetForm(); isEditMode.value = false; modalInstance.value?.show(); };
const openEditModal = (customer) => { 
    if (!requireLogin()) return;
    resetForm();
    isEditMode.value = true;
    Object.assign(formData, {
        id: customer.id,
        fullName: customer.fullName,
        email: customer.email,
        phone: customer.phone,
        address: customer.address,
        sex: customer.sex,
        birthday: formatDateForInput(customer.birthday),
        status: customer.status || 'active'
    });
    avatarPreview.value = customer.avatar_url; 
    modalInstance.value?.show(); 
};
const openViewModal = (customer) => { viewingCustomer.value = customer; viewModalInstance.value?.show(); };

// Lifecycle
onMounted(async () => {
    await checkAuthState();
    if (!requireLogin()) { isLoading.value = false; return; }
    
    nextTick(() => {
        if (modalRef.value) modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
        if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value);
    });
    fetchCustomers();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0 text-brand">Quản lý Khách hàng</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active">Khách hàng</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4 shadow-sm border-0">
                <!-- TABS -->
                <div class="card-header border-bottom-0 pb-0 bg-white">
                    <ul class="nav nav-tabs card-header-tabs custom-tabs">
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'active' }" href="#" @click.prevent="setActiveTab('active')">
                                <i class="bi bi-check-circle me-1 text-success"></i> Đang hoạt động
                                <span class="badge rounded-pill bg-success ms-2">{{ statusCounts.active }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'inactive' }" href="#" @click.prevent="setActiveTab('inactive')">
                                <i class="bi bi-lock-fill me-1 text-danger"></i> Đã khóa
                                <span class="badge rounded-pill bg-danger ms-2">{{ statusCounts.inactive }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- FILTER BAR -->
                <div class="card-body bg-light border-bottom py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-5 col-12">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm tên, email, SĐT..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-7 col-12 text-end">
                            <button class="btn btn-primary px-4 shadow-sm" @click="openCreateModal">
                                <i class="bi bi-person-plus-fill me-1"></i> Thêm Khách hàng
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="card-body p-0">
                    <div class="tab-content">
                        <template v-for="tab in ['active', 'inactive']" :key="tab">
                            <div class="tab-pane fade show active" v-if="activeTab === tab">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 custom-table">
                                        <thead class="bg-light text-secondary">
                                            <tr>
                                                <th class="ps-3">Khách hàng</th>
                                                <th>Liên hệ</th>
                                                <th>Giới tính</th>
                                                <th>Ngày tạo</th>
                                                <th class="text-center">Trạng thái</th>
                                                <th class="text-center pe-3">Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="isLoading"><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                                            <tr v-else-if="(tab === 'active' ? pagedActive : pagedInactive).data.length === 0">
                                                <td colspan="6" class="text-center py-5 text-muted fst-italic">Không có tài khoản nào trong mục này.</td>
                                            </tr>
                                            <tr v-else v-for="customer in (tab === 'active' ? pagedActive : pagedInactive).data" :key="customer.id">
                                                <td class="ps-3">
                                                    <div class="d-flex align-items-center">
                                                        <img :src="customer.avatar_url" class="rounded-circle me-3 shadow-sm border" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <div>
                                                            <div class="fw-bold text-dark">{{ customer.fullName }}</div>
                                                            <div class="small text-muted">ID: #{{ customer.id }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column small">
                                                        <span class="text-truncate" style="max-width: 200px;"><i class="bi bi-envelope me-1 text-primary"></i> {{ customer.email }}</span>
                                                        <span class="text-muted mt-1"><i class="bi bi-telephone me-1"></i> {{ customer.phone || '---' }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge rounded-pill fw-normal border" :class="getGenderBadge(customer.sex)">{{ getGenderText(customer.sex) }}</span>
                                                </td>
                                                <td class="small text-muted">{{ formatDate(customer.created_at) }}</td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                        <input class="form-check-input custom-switch cursor-pointer" type="checkbox" role="switch"
                                                            :checked="customer.status === 'active'" 
                                                            @click.prevent="toggleStatus(customer)" 
                                                            :title="customer.status === 'active' ? 'Nhấn để khóa' : 'Nhấn để mở khóa'">
                                                    </div>
                                                </td>
                                                <td class="text-center pe-3">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button class="btn btn-sm btn-light text-secondary border" @click="openViewModal(customer)" title="Xem"><i class="bi bi-eye"></i></button>
                                                        <button class="btn btn-sm btn-light text-primary border" @click="openEditModal(customer)" title="Sửa"><i class="bi bi-pencil"></i></button>
                                                        <button class="btn btn-sm btn-light text-danger border" @click="handleDelete(customer)" title="Xóa"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <div class="card-footer bg-white border-top-0 py-3" v-if="(tab === 'active' ? pagedActive : pagedInactive).totalPages > 1">
                                    <ul class="pagination pagination-sm m-0 justify-content-end">
                                        <li class="page-item" :class="{ disabled: pagination[tab].currentPage === 1 }"><button class="page-link border-0" @click="changePage(tab, pagination[tab].currentPage - 1)">&laquo;</button></li>
                                        <li v-for="p in (tab === 'active' ? pagedActive : pagedInactive).totalPages" :key="p" class="page-item" :class="{ active: pagination[tab].currentPage === p }"><button class="page-link border-0 rounded-circle mx-1" @click="changePage(tab, p)">{{ p }}</button></li>
                                        <li class="page-item" :class="{ disabled: pagination[tab].currentPage === (tab === 'active' ? pagedActive : pagedInactive).totalPages }"><button class="page-link border-0" @click="changePage(tab, pagination[tab].currentPage + 1)">&raquo;</button></li>
                                    </ul>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div class="modal fade" id="customerModal" ref="modalRef" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-brand">{{ isEditMode ? 'Cập nhật Khách hàng' : 'Thêm mới Tài khoản' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="handleSave" id="customerForm">
                        <div class="row g-4">
                            <!-- Left: Avatar & Status -->
                            <div class="col-md-4 text-center border-end">
                                <div class="mb-3 position-relative d-inline-block">
                                    <img :src="avatarPreview || 'https://placehold.co/150x150?text=Avatar'" class="img-thumbnail rounded-circle shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                                    <label for="avatarInput" class="position-absolute bottom-0 end-0 btn btn-sm btn-primary rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 4px;" title="Đổi ảnh">
                                        <i class="bi bi-camera-fill"></i>
                                    </label>
                                    <input type="file" class="d-none" id="avatarInput" accept="image/*" @change="onFileChange">
                                </div>
                                <div class="text-muted small mb-3">Chấp nhận: JPG, PNG, WEBP (Max 5MB)</div>
                                
                                <div class="mb-3 text-start px-2" v-if="isEditMode">
                                    <label class="form-label fw-bold small text-uppercase text-secondary">Trạng thái</label>
                                    <select class="form-select" v-model="formData.status">
                                        <option value="active">Hoạt động</option>
                                        <option value="inactive">Đã khóa</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Right: Info -->
                            <div class="col-md-8 ps-md-4">
                                <div class="mb-3">
                                    <label class="form-label required fw-bold">Họ và tên</label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.fullName }" v-model="formData.fullName" placeholder="Nhập họ tên...">
                                    <div class="invalid-feedback">{{ errors.fullName }}</div>
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

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Giới tính</label>
                                        <select class="form-select" v-model="formData.sex">
                                            <option value="male">Nam</option>
                                            <option value="female">Nữ</option>
                                            <option value="other">Khác</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Ngày sinh</label>
                                        <input type="date" class="form-control" v-model="formData.birthday">
                                    </div>
                                </div>

                                <div class="row" v-if="!isEditMode">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required fw-bold">Mật khẩu</label>
                                        <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" v-model="formData.password" placeholder="******">
                                        <div class="invalid-feedback">{{ errors.password }}</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required fw-bold">Xác nhận MK</label>
                                        <input type="password" class="form-control" :class="{ 'is-invalid': errors.password_confirmation }" v-model="formData.password_confirmation" placeholder="******">
                                        <div class="invalid-feedback">{{ errors.password_confirmation }}</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Địa chỉ</label>
                                    <textarea class="form-control" rows="2" v-model="formData.address" placeholder="Số nhà, phường/xã..."></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" form="customerForm" class="btn btn-primary px-4" :disabled="isSaving">
                        <span v-if="isSaving" class="spinner-border spinner-border-sm me-1"></span> {{ isEditMode ? 'Lưu thay đổi' : 'Tạo tài khoản' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-0 position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    <div class="text-center bg-light p-4 rounded-top">
                        <img :src="viewingCustomer.avatar_url" class="rounded-circle shadow-sm mb-3 border border-3 border-white" style="width: 100px; height: 100px; object-fit: cover;">
                        <h4 class="fw-bold text-dark mb-1">{{ viewingCustomer.fullName }}</h4>
                        <span :class="['badge', viewingCustomer.status === 'active' ? 'bg-success' : 'bg-danger']">
                            {{ viewingCustomer.status === 'active' ? 'Đang hoạt động' : 'Đã khóa' }}
                        </span>
                    </div>
                    <div class="p-4">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-envelope me-2"></i> Email</span>
                                <span class="fw-medium text-dark">{{ viewingCustomer.email }}</span>
                            </div>
                            <div class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-telephone me-2"></i> Số điện thoại</span>
                                <span class="fw-medium text-dark">{{ viewingCustomer.phone || '---' }}</span>
                            </div>
                            <div class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-gender-ambiguous me-2"></i> Giới tính</span>
                                <span class="fw-medium text-dark">{{ getGenderText(viewingCustomer.sex) }}</span>
                            </div>
                            <div class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-cake2 me-2"></i> Ngày sinh</span>
                                <span class="fw-medium text-dark">{{ formatDate(viewingCustomer.birthday) }}</span>
                            </div>
                            <div class="list-group-item px-0">
                                <div class="text-muted mb-1"><i class="bi bi-geo-alt me-2"></i> Địa chỉ</div>
                                <div class="small text-dark fst-italic ps-4">{{ viewingCustomer.address || 'Chưa cập nhật' }}</div>
                            </div>
                             <div class="list-group-item px-0 d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-calendar-check me-2"></i> Tham gia ngày</span>
                                <span class="fw-medium text-dark">{{ formatDate(viewingCustomer.created_at) }}</span>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                             <button class="btn btn-primary w-100" @click="() => { viewModalInstance.hide(); openEditModal(viewingCustomer); }">
                                <i class="bi bi-pencil me-2"></i> Chỉnh sửa thông tin
                            </button>
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
.grayscale { filter: grayscale(100%); opacity: 0.6; }
</style>