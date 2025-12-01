<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// CONFIGURATION
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';

// AUTHENTICATION & PERMISSIONS
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
    } catch (e) { console.error("Parse user error", e); }

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
    // Chỉ Admin mới được quản lý Coupon (theo Sidebar của bạn)
    if (!hasRole(['admin'])) {
        Swal.fire({ icon: 'warning', title: 'Quyền hạn', text: 'Bạn không có quyền truy cập trang này.' });
        return false;
    }
    return true;
};

// STATE MANAGEMENT
const isLoading = ref(true);
const isSaving = ref(false);
const allCoupons = ref([]);
const trashedCoupons = ref([]);
const searchQuery = ref('');
const activeTab = ref('active'); // active | expired | trashed
const sortOption = ref('newest'); // [NEW] Sort state

// Modals
const couponModalRef = ref(null);
const couponModalInstance = ref(null);
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingCoupon = ref({});

// Pagination
const pagination = reactive({
    active: { currentPage: 1, itemsPerPage: 8 },
    expired: { currentPage: 1, itemsPerPage: 8 },
    trashed: { currentPage: 1, itemsPerPage: 8 }
});

// Form Data
const couponForm = reactive({
    id: null, name: '', code: '', type: 'percent', value: 0,
    expiresAt: '', usageLimit: null, usageCount: 0, limitPerUser: null
});

// COMPUTED & WATCHERS
// Helper check hết hạn
const checkIsExpired = (coupon) => {
    if (!coupon.expires_at) return false;
    const today = new Date();
    const expiryDate = new Date(coupon.expires_at);
    expiryDate.setHours(23, 59, 59, 999);
    return expiryDate < today;
};

// [NEW] Helper Sắp xếp chung
const sortList = (list) => {
    return [...list].sort((a, b) => {
        switch (sortOption.value) {
            case 'newest': return new Date(b.created_at || 0) - new Date(a.created_at || 0);
            case 'oldest': return new Date(a.created_at || 0) - new Date(b.created_at || 0);
            case 'code-asc': return a.code.localeCompare(b.code);
            case 'code-desc': return b.code.localeCompare(a.code);
            case 'value-desc': return b.value - a.value;
            case 'value-asc': return a.value - b.value;
            default: return 0;
        }
    });
};

// Filter Lists
const activeCouponsList = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    let list = allCoupons.value.filter(c => 
        (c.code.toLowerCase().includes(query) || c.name.toLowerCase().includes(query)) && !checkIsExpired(c)
    );
    return sortList(list);
});

const expiredCouponsList = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    let list = allCoupons.value.filter(c => 
        (c.code.toLowerCase().includes(query) || c.name.toLowerCase().includes(query)) && checkIsExpired(c)
    );
    return sortList(list);
});

const trashedCouponsList = computed(() => { // Filter cho thùng rác nếu cần search
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return sortList(trashedCoupons.value);
    let list = trashedCoupons.value.filter(c => 
        c.code.toLowerCase().includes(query) || c.name.toLowerCase().includes(query)
    );
    return sortList(list);
});

// Status Counts for Tabs
const statusCounts = computed(() => ({
    active: activeCouponsList.value.length,
    expired: expiredCouponsList.value.length,
    trashed: trashedCouponsList.value.length
}));

// Pagination Logic Helper
function getPaginatedData(list, pageInfo) {
    const total = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));
    if (pageInfo.currentPage > total) pageInfo.currentPage = 1;
    const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
    return { data: list.slice(start, start + pageInfo.itemsPerPage), totalPages: total };
}

const pagedActive = computed(() => getPaginatedData(activeCouponsList.value, pagination.active));
const pagedExpired = computed(() => getPaginatedData(expiredCouponsList.value, pagination.expired));
const pagedTrashed = computed(() => getPaginatedData(trashedCouponsList.value, pagination.trashed));

const isExpiryInPast = computed(() => {
    if (!couponForm.expiresAt) return false;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return new Date(couponForm.expiresAt) < today;
});

// Watchers
watch([searchQuery, sortOption], () => { // [UPDATED] Watch cả sortOption
    pagination.active.currentPage = 1;
    pagination.expired.currentPage = 1;
    pagination.trashed.currentPage = 1;
});

// HELPER FUNCTIONS
const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
const formatDateForDisplay = (dateString) => {
    if (!dateString) return 'Vô hạn';
    try { return new Date(dateString).toLocaleDateString('vi-VN'); } catch (e) { return 'N/A'; }
};
const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    try { return new Date(dateString).toISOString().split('T')[0]; } catch (e) { return ''; }
};
const formatValue = (coupon) => coupon.type === 'percent' ? `${coupon.value}%` : formatCurrency(coupon.value);
const formatUsage = (coupon) => {
    const limit = coupon.usage_limit;
    return (!limit || limit === 0) ? `${coupon.usage_count || 0} / ∞` : `${coupon.usage_count || 0} / ${limit}`;
};

const getStatus = (coupon) => {
    if (checkIsExpired(coupon)) return { text: 'Hết hạn', class: 'text-bg-secondary' };
    if (coupon.usage_limit > 0 && (coupon.usage_count || 0) >= coupon.usage_limit) return { text: 'Hết lượt', class: 'text-bg-warning' };
    return { text: 'Hoạt động', class: 'text-bg-success' };
};

const changePage = (listName, page) => { if (pagination[listName]) pagination[listName].currentPage = page; };
const setActiveTab = (tabName) => activeTab.value = tabName;

// ACTIONS
function resetForm() {
    Object.assign(couponForm, {
        id: null, name: '', code: '', type: 'percent', value: 0,
        expiresAt: '', usageLimit: null, usageCount: 0, limitPerUser: null
    });
}

function initializeModals() {
    nextTick(() => {
        if (couponModalRef.value) couponModalInstance.value = new Modal(couponModalRef.value, { backdrop: 'static' });
        if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value);
    });
}

function openCreateModal() {
    if (!requireLogin()) return;
    resetForm();
    couponModalInstance.value?.show();
}

function openEditModal(coupon) {
    if (!requireLogin()) return;
    Object.assign(couponForm, {
        id: coupon.id, name: coupon.name, code: coupon.code, type: coupon.type, value: coupon.value,
        expiresAt: formatDateForInput(coupon.expires_at),
        usageLimit: coupon.usage_limit, usageCount: coupon.usage_count || 0, limitPerUser: coupon.usage_limit_per_user
    });
    couponModalInstance.value?.show();
}

function openViewModal(coupon) {
    viewingCoupon.value = coupon;
    viewModalInstance.value?.show();
}

// API CALLS
async function fetchCoupons() {
    isLoading.value = true;
    try {
        const response = await apiService.get(`admin/coupons`);
        allCoupons.value = response.data;
    } catch (error) {
        console.error("Lỗi tải coupons:", error);
    } finally {
        isLoading.value = false;
    }
}

async function fetchTrashedCoupons() {
    try {
        const response = await apiService.get(`admin/coupons/trashed`);
        trashedCoupons.value = response.data;
    } catch (error) { console.error("Lỗi tải thùng rác:", error); }
}

async function handleSave() {
    if (!requireLogin()) return;
    // Validate
    if (!couponForm.name.trim() || !couponForm.code.trim()) {
        return Swal.fire('Thiếu thông tin', 'Tên và Mã Code là bắt buộc.', 'warning');
    }
    if (couponForm.value <= 0) {
        return Swal.fire('Lỗi giá trị', 'Giá trị giảm giá phải lớn hơn 0.', 'warning');
    }
    if (couponForm.type === 'percent' && couponForm.value > 100) {
        return Swal.fire('Lỗi giá trị', 'Phần trăm giảm giá không được quá 100%.', 'warning');
    }

    isSaving.value = true;
    const dataToSave = { ...couponForm };
    // Cleanup data
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
        couponModalInstance.value?.hide();
        Swal.fire('Thành công', 'Đã lưu mã giảm giá.', 'success');
        fetchCoupons();
    } catch (error) {
        if (error.response?.status === 422) {
            const errors = error.response.data.errors;
            let html = '<ul class="text-start text-danger">';
            for (const key in errors) html += `<li>${errors[key][0]}</li>`;
            html += "</ul>";
            Swal.fire({ title: 'Dữ liệu không hợp lệ', html: html, icon: 'warning' });
        } else {
            Swal.fire('Lỗi', error.response?.data?.message || 'Không thể lưu mã giảm giá.', 'error');
        }
    } finally {
        isSaving.value = false;
    }
}

async function handleDelete(coupon) {
    if (!requireLogin()) return;
    const result = await Swal.fire({
        title: 'Chuyển vào thùng rác?',
        text: `Mã "${coupon.code}" sẽ bị vô hiệu hóa.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        confirmButtonText: 'Đồng ý', cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`admin/coupons/${coupon.id}`);
            Swal.fire('Đã xóa', 'Mã đã chuyển vào thùng rác.', 'success');
            fetchCoupons();
            fetchTrashedCoupons();
        } catch (error) { Swal.fire('Lỗi', 'Không thể xóa mã giảm giá.', 'error'); }
    }
}

async function handleRestore(id) {
    if (!requireLogin()) return;
    try {
        await apiService.post(`admin/coupons/${id}/restore`);
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Đã khôi phục', timer: 1500, showConfirmButton: false });
        fetchCoupons();
        fetchTrashedCoupons();
    } catch (error) { Swal.fire('Lỗi', 'Không thể khôi phục.', 'error'); }
}

async function handleForceDelete(id) {
    if (!requireLogin()) return;
    const result = await Swal.fire({
        title: 'Xóa vĩnh viễn?',
        text: "Hành động này KHÔNG THỂ hoàn tác!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa ngay'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`admin/coupons/${id}/force`);
            Swal.fire('Đã xóa', 'Mã đã bị xóa vĩnh viễn.', 'success');
            fetchTrashedCoupons();
        } catch (error) { Swal.fire('Lỗi', 'Không thể xóa vĩnh viễn.', 'error'); }
    }
}

onMounted(async () => {
    await checkAuthState();
    if (!requireLogin()) { isLoading.value = false; return; }
    
    initializeModals();
    fetchCoupons();
    fetchTrashedCoupons();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0 text-brand">Quản lý Coupon</h3></div>
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
            <div class="card mb-4 shadow-sm border-0">
                <!-- TABS -->
                <div class="card-header border-bottom-0 pb-0 bg-white">
                    <ul class="nav nav-tabs card-header-tabs custom-tabs">
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'active' }" href="#" @click.prevent="setActiveTab('active')">
                                <i class="bi bi-check-circle me-1 text-primary"></i> Khả dụng
                                <span class="badge rounded-pill bg-primary ms-2">{{ statusCounts.active }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'expired' }" href="#" @click.prevent="setActiveTab('expired')">
                                <i class="bi bi-clock-history me-1 text-secondary"></i> Hết hạn
                                <span class="badge rounded-pill bg-secondary ms-2">{{ statusCounts.expired }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'trashed' }" href="#" @click.prevent="setActiveTab('trashed')">
                                <i class="bi bi-trash3 me-1 text-danger"></i> Thùng rác
                                <span class="badge rounded-pill bg-danger ms-2">{{ statusCounts.trashed }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- FILTER BAR [UPDATED] -->
                <div class="card-body bg-light border-bottom py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-4 col-12">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm kiếm mã code, tên..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <select class="form-select" v-model="sortOption">
                                <option value="newest">⏱️ Mới nhất</option>
                                <option value="oldest">⏳ Cũ nhất</option>
                                <option value="code-asc">🔤 Code (A-Z)</option>
                                <option value="code-desc">🔤 Code (Z-A)</option>
                                <option value="value-desc">💰 Giá trị giảm dần</option>
                                <option value="value-asc">💰 Giá trị tăng dần</option>
                            </select>
                        </div>
                        <div class="col-md-5 col-6 text-end">
                            <button class="btn btn-primary px-4 shadow-sm" @click="openCreateModal">
                                <i class="bi bi-plus-lg me-1"></i> Tạo mã mới
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABLE CONTENT -->
                <div class="card-body p-0">
                    <div class="tab-content">
                        
                        <!-- TAB 1 & 2: ACTIVE & EXPIRED -->
                        <div class="tab-pane fade show active" v-if="activeTab === 'active' || activeTab === 'expired'">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 custom-table">
                                    <thead class="bg-light text-secondary">
                                        <tr>
                                            <th class="ps-3">Mã Code</th>
                                            <th>Tên chương trình</th>
                                            <th>Giá trị</th>
                                            <th>Lượt dùng</th>
                                            <th>Hạn dùng</th>
                                            <th>Trạng thái</th>
                                            <th class="text-end pe-3">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="isLoading"><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                                        <tr v-else-if="(activeTab === 'active' ? pagedActive : pagedExpired).data.length === 0">
                                            <td colspan="7" class="text-center py-5 text-muted fst-italic">Không có dữ liệu.</td>
                                        </tr>
                                        <tr v-else v-for="coupon in (activeTab === 'active' ? pagedActive.data : pagedExpired.data)" :key="coupon.id">
                                            <td class="ps-3"><span class="badge bg-light text-dark border fw-bold font-monospace px-2 py-1">{{ coupon.code }}</span></td>
                                            <td class="fw-bold text-dark">{{ coupon.name }}</td>
                                            <td class="text-primary fw-bold">{{ formatValue(coupon) }}</td>
                                            <td>{{ formatUsage(coupon) }}</td>
                                            <td class="text-muted small">{{ formatDateForDisplay(coupon.expires_at) }}</td>
                                            <td><span class="badge rounded-pill fw-normal" :class="getStatus(coupon).class">{{ getStatus(coupon).text }}</span></td>
                                            <td class="text-end pe-3">
                                                <button class="btn btn-sm btn-light text-secondary border me-1" @click="openViewModal(coupon)"><i class="bi bi-eye"></i></button>
                                                <button class="btn btn-sm btn-light text-primary border me-1" @click="openEditModal(coupon)"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-sm btn-light text-danger border" @click="handleDelete(coupon)"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination -->
                            <div class="card-footer bg-white border-top-0 py-3" v-if="(activeTab === 'active' ? pagedActive : pagedExpired).totalPages > 1">
                                <ul class="pagination pagination-sm m-0 justify-content-end">
                                    <li class="page-item" :class="{ disabled: pagination[activeTab].currentPage === 1 }"><button class="page-link border-0" @click="changePage(activeTab, pagination[activeTab].currentPage - 1)"><i class="bi bi-chevron-left"></i></button></li>
                                    <li v-for="p in (activeTab === 'active' ? pagedActive : pagedExpired).totalPages" :key="p" class="page-item" :class="{ active: pagination[activeTab].currentPage === p }"><button class="page-link border-0 rounded-circle mx-1" @click="changePage(activeTab, p)">{{ p }}</button></li>
                                    <li class="page-item" :class="{ disabled: pagination[activeTab].currentPage === (activeTab === 'active' ? pagedActive : pagedExpired).totalPages }"><button class="page-link border-0" @click="changePage(activeTab, pagination[activeTab].currentPage + 1)"><i class="bi bi-chevron-right"></i></button></li>
                                </ul>
                            </div>
                        </div>

                        <!-- TAB 3: TRASHED -->
                        <div class="tab-pane fade show active" v-if="activeTab === 'trashed'">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 custom-table">
                                    <thead class="bg-light text-secondary">
                                        <tr><th class="ps-3">Mã Code</th><th>Tên</th><th>Ngày xóa</th><th class="text-end pe-3">Hành động</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="pagedTrashed.data.length === 0"><td colspan="4" class="text-center py-5 text-muted">Thùng rác trống.</td></tr>
                                        <tr v-for="coupon in pagedTrashed.data" :key="coupon.id" class="text-muted">
                                            <td class="ps-3 text-decoration-line-through">{{ coupon.code }}</td>
                                            <td>{{ coupon.name }}</td>
                                            <td>{{ coupon.deleted_at ? formatDateForDisplay(coupon.deleted_at) : 'N/A' }}</td>
                                            <td class="text-end pe-3">
                                                <button class="btn btn-sm btn-success me-1" @click="handleRestore(coupon.id)"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                <button class="btn btn-sm btn-outline-danger" @click="handleForceDelete(coupon.id)"><i class="bi bi-x-lg"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination Trashed -->
                            <div class="card-footer bg-white border-top-0 py-3" v-if="pagedTrashed.totalPages > 1">
                                <ul class="pagination pagination-sm m-0 justify-content-end">
                                    <li class="page-item" :class="{ disabled: pagination.trashed.currentPage === 1 }"><button class="page-link border-0" @click="changePage('trashed', pagination.trashed.currentPage - 1)"><i class="bi bi-chevron-left"></i></button></li>
                                    <li v-for="p in pagedTrashed.totalPages" :key="p" class="page-item" :class="{ active: pagination.trashed.currentPage === p }"><button class="page-link border-0 rounded-circle mx-1" @click="changePage('trashed', p)">{{ p }}</button></li>
                                    <li class="page-item" :class="{ disabled: pagination.trashed.currentPage === pagedTrashed.totalPages }"><button class="page-link border-0" @click="changePage('trashed', pagination.trashed.currentPage + 1)"><i class="bi bi-chevron-right"></i></button></li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CREATE/EDIT -->
    <div class="modal fade" id="couponModal" ref="couponModalRef" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-brand"><i class="bi bi-ticket-perforated me-2"></i> {{ couponForm.id ? 'Cập nhật mã giảm giá' : 'Tạo mã giảm giá mới' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="couponFormElement" @submit.prevent="handleSave">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required fw-bold">Tên chương trình</label>
                                <input type="text" class="form-control" v-model="couponForm.name" placeholder="Vd: Sale 30/4" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required fw-bold">Mã Code</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-upc-scan"></i></span>
                                    <input type="text" class="form-control text-uppercase font-monospace fw-bold" v-model="couponForm.code" placeholder="SALE3004" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Loại giảm giá</label>
                                <select class="form-select" v-model="couponForm.type">
                                    <option value="percent">Phần trăm (%)</option>
                                    <option value="fixed">Số tiền cố định (VND)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required fw-bold">Giá trị giảm</label>
                                <input type="number" class="form-control text-primary fw-bold" v-model.number="couponForm.value" required min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Ngày hết hạn</label>
                                <input type="date" class="form-control" v-model="couponForm.expiresAt">
                                <div v-if="isExpiryInPast" class="text-danger small mt-1"><i class="bi bi-exclamation-circle"></i> Ngày này đã qua.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Tổng lượt dùng</label>
                                <input type="number" class="form-control" v-model.number="couponForm.usageLimit" min="0" placeholder="∞">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Lượt dùng / User</label>
                                <input type="number" class="form-control" v-model.number="couponForm.limitPerUser" min="1" placeholder="∞">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" form="couponFormElement" class="btn btn-primary px-4" :disabled="isSaving">
                        <span v-if="isSaving" class="spinner-border spinner-border-sm me-1"></span> Lưu lại
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL VIEW -->
    <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-0">
                    <div class="position-relative bg-light p-4 text-center rounded-top">
                        <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                        <div class="bg-white rounded-circle shadow-sm d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="bi bi-ticket-perforated-fill fs-1 text-primary"></i>
                        </div>
                        <h4 class="mb-1 font-monospace fw-bold text-dark">{{ viewingCoupon.code }}</h4>
                        <p class="text-muted small mb-2">{{ viewingCoupon.name }}</p>
                        <span class="badge" :class="getStatus(viewingCoupon).class">{{ getStatus(viewingCoupon).text }}</span>
                    </div>
                    <div class="p-4">
                        <div class="row g-3 text-center">
                            <div class="col-4 border-end">
                                <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">Giá trị</small>
                                <span class="fw-bold text-primary fs-5">{{ formatValue(viewingCoupon) }}</span>
                            </div>
                            <div class="col-4 border-end">
                                <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">Đã dùng</small>
                                <span class="fw-bold fs-5">{{ formatUsage(viewingCoupon) }}</span>
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">Hết hạn</small>
                                <span class="fw-bold fs-5">{{ formatDateForDisplay(viewingCoupon.expiresAt) }}</span>
                            </div>
                        </div>
                        <hr class="my-4">
                        <button class="btn btn-primary w-100" @click="() => { viewModalInstance.hide(); openEditModal(viewingCoupon); }">
                            <i class="bi bi-pencil me-2"></i> Chỉnh sửa mã này
                        </button>
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
:deep(.table-striped-columns) tbody tr td:nth-child(even) { background-color: rgba(0, 0, 0, 0.02); }
@media (max-width: 768px) { .display-5 { font-size: 2rem; } }
</style>