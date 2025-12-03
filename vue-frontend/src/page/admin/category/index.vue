<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';
import draggable from 'vuedraggable'; // [NEW] Import thư viện kéo thả

// CONFIGURATION
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';

// ICON LIBRARY (Giữ nguyên)
const commonIcons = [
    { name: 'Điện thoại', code: '<i class="fa-solid fa-mobile-screen"></i>' },
    { name: 'Laptop', code: '<i class="fa-solid fa-laptop"></i>' },
    { name: 'Máy tính bảng', code: '<i class="fa-solid fa-tablet-screen-button"></i>' },
    { name: 'PC / Máy bàn', code: '<i class="fa-solid fa-desktop"></i>' },
    { name: 'Đồng hồ thông minh', code: '<i class="fa-solid fa-clock"></i>' },
    { name: 'Máy ảnh', code: '<i class="fa-solid fa-camera"></i>' },
    { name: 'TV / Màn hình', code: '<i class="fa-solid fa-tv"></i>' },
    { name: 'Máy in', code: '<i class="fa-solid fa-print"></i>' },
    { name: 'Tai nghe chụp', code: '<i class="fa-solid fa-headphones"></i>' },
    { name: 'Tai nghe nhỏ', code: '<i class="fa-solid fa-ear-listen"></i>' },
    { name: 'Loa / Âm thanh', code: '<i class="fa-solid fa-volume-high"></i>' },
    { name: 'Micro', code: '<i class="fa-solid fa-microphone"></i>' },
    { name: 'Chuột máy tính', code: '<i class="fa-solid fa-computer-mouse"></i>' },
    { name: 'Bàn phím', code: '<i class="fa-solid fa-keyboard"></i>' },
    { name: 'Tay cầm game', code: '<i class="fa-solid fa-gamepad"></i>' },
    { name: 'Sạc / Pin dự phòng', code: '<i class="fa-solid fa-battery-full"></i>' },
    { name: 'Dây cáp / Củ sạc', code: '<i class="fa-solid fa-plug"></i>' },
    { name: 'Vi xử lý / CPU', code: '<i class="fa-solid fa-microchip"></i>' },
    { name: 'RAM / Thẻ nhớ', code: '<i class="fa-solid fa-memory"></i>' },
    { name: 'Ổ cứng (HDD/SSD)', code: '<i class="fa-solid fa-hard-drive"></i>' },
    { name: 'USB', code: '<i class="fa-brands fa-usb"></i>' },
    { name: 'Thẻ SD', code: '<i class="fa-solid fa-sd-card"></i>' },
    { name: 'Quạt tản nhiệt', code: '<i class="fa-solid fa-fan"></i>' },
    { name: 'Wifi', code: '<i class="fa-solid fa-wifi"></i>' },
    { name: 'Router / Mạng', code: '<i class="fa-solid fa-network-wired"></i>' },
    { name: 'Server', code: '<i class="fa-solid fa-server"></i>' },
    { name: 'Đám mây / Cloud', code: '<i class="fa-solid fa-cloud"></i>' },
    { name: 'Robot hút bụi', code: '<i class="fa-solid fa-robot"></i>' },
    { name: 'Bi Laptop', code: '<i class="bi bi-laptop"></i>' },
    { name: 'Bi Phone', code: '<i class="bi bi-phone"></i>' },
    { name: 'Bi CPU', code: '<i class="bi bi-cpu"></i>' },
    { name: 'Bi GPU', code: '<i class="bi bi-gpu-card"></i>' },
    { name: 'Bi HDD', code: '<i class="bi bi-hdd"></i>' },
    { name: 'Bi Webcam', code: '<i class="bi bi-webcam"></i>' },
    { name: 'Bi Speaker', code: '<i class="bi bi-speaker"></i>' },
    { name: 'Bi Router', code: '<i class="bi bi-router"></i>' },
    { name: 'Bi Joystick', code: '<i class="bi bi-joystick"></i>' },
];

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
    if (!hasRole(['admin', 'staff'])) {
        Swal.fire({ icon: 'warning', title: 'Quyền hạn', text: 'Bạn không có quyền quản lý danh mục.' });
        return false;
    }
    return true;
};

// STATE MANAGEMENT
const categories = ref([]);
const sortableList = ref([]); // [NEW] Danh sách dùng riêng cho Drag & Drop
const isLoading = ref(true);
const isSaving = ref(false);
const isEditMode = ref(false);
const searchQuery = ref('');
const activeTab = ref('active');
const drag = ref(false); // [NEW] State trạng thái kéo

// State cho Icon Picker
const isIconModalOpen = ref(false);
const iconSearchQuery = ref('');
const iconModalInstance = ref(null);
const iconModalRef = ref(null);

// Modals
const modalInstance = ref(null);
const modalRef = ref(null);
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingCategory = ref({});

// Pagination
const pagination = reactive({
    // active: { currentPage: 1, itemsPerPage: 10 }, // [REMOVED] Active tab dùng list full
    disabled: { currentPage: 1, itemsPerPage: 10 }
});

// Form Data
const formData = reactive({ 
    id: null, 
    name: '', 
    description: '', 
    icon: '', 
    order_number: 1, 
    status: 'active' 
});

const errors = reactive({ name: '', description: '', icon: '', order_number: '' });

// COMPUTED & LOGIC

// Filter Icon List
const filteredIcons = computed(() => {
    const query = iconSearchQuery.value.toLowerCase().trim();
    if (!query) return commonIcons;
    return commonIcons.filter(icon => 
        icon.name.toLowerCase().includes(query) || 
        icon.code.toLowerCase().includes(query)
    );
});

// [NEW] Logic mới cho Drag & Drop:
// Tab Active: Sử dụng sortableList (đã lọc và sort). 
// Khi search: sortableList sẽ được filter lại (nhưng lúc search thì không nên drag để tránh lỗi logic vị trí).
const processedActiveList = computed(() => {
    let result = sortableList.value;
    const query = searchQuery.value.toLowerCase().trim();
    if (query) {
        result = result.filter(c => 
            c.name.toLowerCase().includes(query) || 
            (c.description && c.description.toLowerCase().includes(query))
        );
    }
    return result;
});

// Tab Disabled: Vẫn giữ logic cũ (Pagination)
const processedDisabledList = computed(() => {
    let result = categories.value.filter(c => c.status !== 'active');
    const query = searchQuery.value.toLowerCase().trim();
    
    if (query) {
        result = result.filter(c =>
            c.name.toLowerCase().includes(query) ||
            (c.description && c.description.toLowerCase().includes(query))
        );
    }
    // Sắp xếp disabled list theo order cũ
    result.sort((a, b) => (a.order_number || 0) - (b.order_number || 0));
    return result;
});

const statusCounts = computed(() => ({
    active: sortableList.value.length, // Lấy length từ list active
    disabled: categories.value.filter(c => c.status !== 'active').length
}));

// Pagination Helper (Chỉ dùng cho tab Disabled)
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

const pagedDisabled = computed(() => getPaginatedData(processedDisabledList.value, 'disabled'));

// Watchers
watch(searchQuery, () => {
    // pagination.active.currentPage = 1; // [REMOVED]
    pagination.disabled.currentPage = 1;
});

// HELPER FUNCTIONS
const changePage = (type, page) => { pagination[type].currentPage = page; };
const setActiveTab = (tabName) => activeTab.value = tabName;

const getFormattedDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('vi-VN');
};

const calculateNextOrder = () => {
    if (categories.value.length === 0) return 1;
    const maxOrder = Math.max(...categories.value.map(c => parseInt(c.order_number) || 0));
    return maxOrder + 1;
};

const resetForm = () => {
    const nextOrder = calculateNextOrder();
    Object.assign(formData, { id: null, name: '', description: '', icon: '', order_number: nextOrder, status: 'active' });
    Object.keys(errors).forEach(k => errors[k] = '');
};

// ACTIONS (API)
async function fetchCategories() {
    if (categories.value.length === 0) isLoading.value = true;
    try {
        const response = await apiService.get(`admin/categories`);
        const rawData = response.data.map(cat => ({
            ...cat,
            order_number: cat.order_number !== undefined ? parseInt(cat.order_number) : (parseInt(cat.order) || 0)
        }));
        
        categories.value = rawData;

        // [NEW] Tách list Active ra riêng để làm Drag & Drop
        // Sắp xếp ban đầu theo order_number
        sortableList.value = rawData
            .filter(c => c.status === 'active')
            .sort((a, b) => a.order_number - b.order_number);

    } catch (error) {
        console.error("Lỗi tải danh mục:", error);
        Swal.fire('Lỗi', 'Không thể tải danh sách danh mục.', 'error');
    } finally {
        isLoading.value = false;
    }
}

// [NEW] Xử lý khi thả chuột (Drag End)
async function onDragEnd(event) {
    drag.value = false;
    
    // Nếu chỉ là click nhầm, không di chuyển thì thôi
    if (event.newIndex === event.oldIndex) return;

    // Lấy danh sách ID theo thứ tự mới
    const orderedIds = sortableList.value.map(item => item.id);

    try {
        // Gọi API cập nhật
        await apiService.post('admin/categories/update-order', { ids: orderedIds });
        
        // Cập nhật lại order_number hiển thị ở client cho khớp (để nhìn đẹp mắt ngay lập tức)
        sortableList.value.forEach((item, index) => {
            item.order_number = index + 1;
        });

        // Đồng bộ ngược lại vào main categories list nếu cần thiết (optional)
        // Nhưng vì sortableList là object reference nên thay đổi thuộc tính cũng sẽ phản ánh vào categories gốc

        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: 'Đã cập nhật vị trí' });

    } catch (error) {
        console.error("Sort API error:", error);
        Swal.fire('Lỗi', 'Không thể lưu thứ tự sắp xếp.', 'error');
        fetchCategories(); // Revert lại nếu lỗi
    }
}

// Validate dựa trên DB Schema
function validateForm() {
    Object.keys(errors).forEach(k => errors[k] = '');
    let isValid = true;

    if (!formData.name.trim()) { errors.name = 'Vui lòng nhập tên danh mục.'; isValid = false; }
    else if (formData.name.length > 100) { errors.name = 'Tên danh mục không được quá 100 ký tự.'; isValid = false; }

    if (!formData.icon || !formData.icon.trim()) { errors.icon = 'Vui lòng nhập hoặc chọn mã icon.'; isValid = false; }
    else if (formData.icon.length > 100) { errors.icon = 'Mã icon quá dài (tối đa 100 ký tự).'; isValid = false; }

    if (formData.order_number === '' || isNaN(formData.order_number) || formData.order_number === null) {
        errors.order_number = 'Thứ tự phải là số.'; isValid = false;
    } else if (!Number.isInteger(Number(formData.order_number)) || Number(formData.order_number) < 1) {
        errors.order_number = 'Thứ tự phải là số nguyên dương.'; isValid = false;
    }

    return isValid;
}

async function handleSave() {
    if (!requireLogin()) return;
    if (!validateForm()) return;

    isSaving.value = true;
    const payload = { ...formData };
    
    try {
        if (isEditMode.value) {
            await apiService.put(`admin/categories/${formData.id}`, payload);
            Swal.fire('Thành công', 'Đã cập nhật danh mục!', 'success');
        } else {
            await apiService.post(`admin/categories`, payload);
            Swal.fire('Thành công', 'Đã tạo danh mục mới!', 'success');
        }
        modalInstance.value?.hide();
        fetchCategories();
    } catch (e) {
        const msg = e.response?.data?.message || 'Có lỗi xảy ra khi lưu.';
        if (e.response?.status === 422 && e.response.data.errors) {
             if (e.response.data.errors.name) errors.name = e.response.data.errors.name[0];
             if (e.response.data.errors.icon) errors.icon = e.response.data.errors.icon[0];
             if (e.response.data.errors.order_number) errors.order_number = e.response.data.errors.order_number[0];
        } else {
             Swal.fire('Lỗi', msg, 'error');
        }
    } finally {
        isSaving.value = false;
    }
}

async function toggleStatus(category) {
    if (!requireLogin()) return;

    const oldStatus = category.status;
    const newStatus = category.status === 'active' ? 'disabled' : 'active';
    const actionText = newStatus === 'active' ? 'KÍCH HOẠT' : 'VÔ HIỆU HÓA';

    const result = await Swal.fire({
        title: `Xác nhận ${actionText}?`,
        text: `Danh mục "${category.name}" sẽ chuyển sang trạng thái ${newStatus}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: newStatus === 'active' ? '#009981' : '#d33',
        confirmButtonText: 'Đồng ý', cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        category.status = newStatus;
        try {
            await apiService.put(`admin/categories/${category.id}`, { status: newStatus });
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Cập nhật thành công', timer: 1500, showConfirmButton: false });
            fetchCategories(); // Load lại để update list sortable
        } catch (error) {
            category.status = oldStatus;
            Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
        }
    }
}

async function handleDelete(category) {
    if (!requireLogin()) return;

    const result = await Swal.fire({
        title: 'Xóa danh mục?',
        text: `Sẽ xóa vĩnh viễn "${category.name}". Không thể hoàn tác!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa ngay', cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`admin/categories/${category.id}`);
            categories.value = categories.value.filter(c => c.id !== category.id);
            // Update sortable list local
            sortableList.value = sortableList.value.filter(c => c.id !== category.id);
            Swal.fire('Đã xóa!', 'Danh mục đã bị xóa.', 'success');
        } catch (error) {
            Swal.fire('Lỗi', 'Không thể xóa danh mục này (có thể đang chứa sản phẩm).', 'error');
        }
    }
}

// Modal Functions
const openCreateModal = () => { if(!requireLogin()) return; resetForm(); isEditMode.value = false; modalInstance.value?.show(); };
const openEditModal = (cat) => { 
    if(!requireLogin()) return;
    Object.assign(formData, { 
        id: cat.id, name: cat.name, description: cat.description, 
        icon: cat.icon, order_number: cat.order_number, status: cat.status 
    });
    Object.keys(errors).forEach(k => errors[k] = '');
    isEditMode.value = true; 
    modalInstance.value?.show(); 
};
const openViewModal = (cat) => { viewingCategory.value = cat; viewModalInstance.value?.show(); };

// Icon Picker Functions
const openIconModal = () => {
    iconSearchQuery.value = '';
    iconModalInstance.value?.show();
};

const selectIcon = (iconCode) => {
    formData.icon = iconCode;
    errors.icon = ''; // Clear error if any
    iconModalInstance.value?.hide();
};

// Lifecycle
onMounted(async () => {
    await checkAuthState();
    if (!requireLogin()) { isLoading.value = false; return; }
    
    nextTick(() => {
        if (modalRef.value) modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
        if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value);
        if (iconModalRef.value) iconModalInstance.value = new Modal(iconModalRef.value);
    });
    fetchCategories();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0 text-brand">Quản lý Danh mục</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active">Danh mục</li>
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
                                <i class="bi bi-check-circle me-1 text-success"></i> Hoạt động
                                <span class="badge rounded-pill bg-success ms-2">{{ statusCounts.active }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'disabled' }" href="#" @click.prevent="setActiveTab('disabled')">
                                <i class="bi bi-dash-circle me-1 text-secondary"></i> Vô hiệu hóa
                                <span class="badge rounded-pill bg-secondary ms-2">{{ statusCounts.disabled }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- TOOLBAR -->
                <div class="card-body bg-light border-bottom py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-5 col-12">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm kiếm theo tên..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-7 col-12 text-end">
                            <button class="btn btn-primary px-4 shadow-sm" @click="openCreateModal">
                                <i class="bi bi-plus-lg me-1"></i> Tạo danh mục
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="card-body p-0">
                    <div class="tab-content">
                        <!-- TAB ACTIVE: DRAG & DROP ENABLED (NO PAGINATION) -->
                        <div class="tab-pane fade show active" v-if="activeTab === 'active'">
                             <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 custom-table">
                                    <thead class="bg-light text-secondary">
                                        <tr>
                                            <th style="width: 50px" class="text-center">#</th>
                                            <th style="width: 60px;">ID</th>
                                            <th style="width: 80px;" class="text-center">Icon</th>
                                            <th>Tên danh mục</th>
                                            <th class="d-none d-md-table-cell">Mô tả</th>
                                            <th style="width: 120px;" class="text-center">Trạng thái</th>
                                            <th style="width: 160px;" class="text-center pe-3">Hành động</th>
                                        </tr>
                                    </thead>
                                    <!-- DRAGGABLE COMPONENT WRAPPING TBODY -->
                                    <draggable 
                                        v-model="sortableList" 
                                        tag="tbody" 
                                        item-key="id"
                                        handle=".drag-handle"
                                        @start="drag=true" 
                                        @end="onDragEnd"
                                        v-if="!isLoading && processedActiveList.length > 0"
                                    >
                                        <template #item="{ element: category }">
                                            <tr :class="{ 'bg-light': drag }">
                                                <!-- Drag Handle -->
                                                <td class="text-center cursor-move drag-handle" title="Kéo thả để sắp xếp">
                                                    <i class="bi bi-grip-vertical text-secondary fs-5 opacity-50 hover-opacity-100"></i>
                                                </td>

                                                <td class="text-muted fw-bold">#{{ category.id }}</td>
                                                <td class="text-center fs-4 text-primary">
                                                    <span v-if="category.icon" v-html="category.icon"></span>
                                                    <i v-else class="bi bi-image text-muted opacity-50"></i>
                                                </td>
                                                <td class="fw-bold text-dark">
                                                    {{ category.name }}
                                                    <span v-if="category.order_number" class="badge bg-light text-secondary ms-2 border">Top {{ category.order_number }}</span>
                                                </td>
                                                <td class="d-none d-md-table-cell text-muted small text-truncate" style="max-width: 200px;">{{ category.description }}</td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                        <input class="form-check-input custom-switch cursor-pointer" type="checkbox" role="switch"
                                                            :checked="category.status === 'active'" 
                                                            @click.prevent="toggleStatus(category)" 
                                                            title="Nhấn để vô hiệu hóa">
                                                    </div>
                                                </td>
                                                <td class="text-center pe-3">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button class="btn btn-sm btn-light text-secondary border" @click="openViewModal(category)" title="Xem"><i class="bi bi-eye"></i></button>
                                                        <button class="btn btn-sm btn-light text-primary border" @click="openEditModal(category)" title="Sửa"><i class="bi bi-pencil"></i></button>
                                                        <button class="btn btn-sm btn-light text-danger border" @click="handleDelete(category)" title="Xóa"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </draggable>
                                    
                                    <!-- Fallback when empty or loading -->
                                    <tbody v-else>
                                         <tr v-if="isLoading"><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                                         <tr v-else><td colspan="7" class="text-center py-5 text-muted fst-italic">Không có danh mục hoạt động nào.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-white py-3 text-muted small text-center">
                                <i class="bi bi-info-circle me-1"></i> Mẹo: Nhấn giữ biểu tượng <i class="bi bi-grip-vertical"></i> để kéo thả sắp xếp vị trí hiển thị trên Menu.
                            </div>
                        </div>

                        <!-- TAB DISABLED: NORMAL TABLE WITH PAGINATION -->
                        <div class="tab-pane fade show active" v-if="activeTab === 'disabled'">
                             <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 custom-table">
                                    <thead class="bg-light text-secondary">
                                        <tr>
                                            <th style="width: 220px" class="text-center ps-3">Thứ tự</th>
                                            <th style="width: 60px;">ID</th>
                                            <th style="width: 80px;" class="text-center">Icon</th>
                                            <th>Tên danh mục</th>
                                            <th class="d-none d-md-table-cell">Mô tả</th>
                                            <th style="width: 120px;" class="text-center">Trạng thái</th>
                                            <th style="width: 160px;" class="text-center pe-3">Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="pagedDisabled.data.length === 0">
                                            <td colspan="7" class="text-center py-5 text-muted fst-italic">Thùng rác rỗng.</td>
                                        </tr>
                                        <tr v-else v-for="category in pagedDisabled.data" :key="category.id">
                                            <td class="text-center ps-3 text-muted">{{ category.order_number }}</td>
                                            <td class="text-muted fw-bold">#{{ category.id }}</td>
                                            <td class="text-center fs-4 text-primary">
                                                <span v-if="category.icon" v-html="category.icon"></span>
                                                <i v-else class="bi bi-image text-muted opacity-50"></i>
                                            </td>
                                            <td class="fw-bold text-dark">{{ category.name }}</td>
                                            <td class="d-none d-md-table-cell text-muted small text-truncate" style="max-width: 200px;">{{ category.description }}</td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input custom-switch cursor-pointer" type="checkbox" role="switch"
                                                        :checked="category.status === 'active'" 
                                                        @click.prevent="toggleStatus(category)" 
                                                        title="Nhấn để kích hoạt">
                                                </div>
                                            </td>
                                            <td class="text-center pe-3">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-sm btn-light text-secondary border" @click="openViewModal(category)" title="Xem"><i class="bi bi-eye"></i></button>
                                                    <button class="btn btn-sm btn-light text-primary border" @click="openEditModal(category)" title="Sửa"><i class="bi bi-pencil"></i></button>
                                                    <button class="btn btn-sm btn-light text-danger border" @click="handleDelete(category)" title="Xóa"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination for Disabled -->
                            <div class="card-footer bg-white border-top-0 py-3" v-if="pagedDisabled.totalPages > 1">
                                <ul class="pagination pagination-sm m-0 justify-content-end">
                                    <li class="page-item" :class="{ disabled: pagination.disabled.currentPage === 1 }"><button class="page-link border-0" @click="changePage('disabled', pagination.disabled.currentPage - 1)">&laquo;</button></li>
                                    <li v-for="p in pagedDisabled.totalPages" :key="p" class="page-item" :class="{ active: pagination.disabled.currentPage === p }"><button class="page-link border-0 rounded-circle mx-1" @click="changePage('disabled', p)">{{ p }}</button></li>
                                    <li class="page-item" :class="{ disabled: pagination.disabled.currentPage === pagedDisabled.totalPages }"><button class="page-link border-0" @click="changePage('disabled', pagination.disabled.currentPage + 1)">&raquo;</button></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CREATE/EDIT -->
    <div class="modal fade" id="categoryModal" ref="modalRef" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-brand">{{ isEditMode ? 'Chỉnh sửa Danh mục' : 'Tạo Danh mục mới' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="handleSave" id="categoryForm">
                        <div class="row g-4">
                            <!-- Left: Preview -->
                            <div class="col-md-4 text-center border-end">
                                <label class="form-label fw-bold text-secondary small text-uppercase mb-3">Xem trước Icon</label>
                                <div class="img-thumbnail d-flex align-items-center justify-content-center bg-light shadow-sm rounded-circle mb-3"
                                    style="width: 120px; height: 120px; font-size: 3rem; margin: auto;">
                                    <span v-if="formData.icon" v-html="formData.icon" class="text-primary"></span>
                                    <i v-else class="bi bi-image text-muted opacity-25"></i>
                                </div>
                                <div class="text-muted small" v-if="!formData.icon">Chọn icon bên phải để xem thử</div>
                                
                                <div class="mt-4 text-start px-2" v-if="isEditMode">
                                    <label class="form-label fw-bold small text-uppercase text-secondary">Trạng thái</label>
                                    <select class="form-select form-select-sm" v-model="formData.status">
                                        <option value="active">Hoạt động</option>
                                        <option value="disabled">Vô hiệu hóa</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Right: Inputs -->
                            <div class="col-md-8 ps-md-4">
                                <div class="mb-3">
                                    <label class="form-label required fw-bold">Tên danh mục</label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" v-model="formData.name" placeholder="VD: Điện thoại, Laptop...">
                                    <div class="invalid-feedback">{{ errors.name }}</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label required fw-bold">Mã Icon</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control font-monospace small" :class="{ 'is-invalid': errors.icon }" v-model="formData.icon" placeholder='<i class="..."></i>'>
                                            <button class="btn btn-outline-secondary" type="button" @click="openIconModal" title="Chọn từ thư viện"><i class="bi bi-grid-3x3-gap"></i> Chọn</button>
                                            <div class="invalid-feedback">{{ errors.icon }}</div>
                                        </div>
                                        <div class="form-text small">Nhập mã HTML hoặc chọn từ thư viện.</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label required fw-bold">Thứ tự</label>
                                        <input type="number" class="form-control" :class="{ 'is-invalid': errors.order_number }" v-model="formData.order_number">
                                        <div class="invalid-feedback">{{ errors.order_number }}</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Mô tả</label>
                                    <textarea class="form-control" rows="3" v-model="formData.description" placeholder="Mô tả ngắn về danh mục này..."></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" form="categoryForm" class="btn btn-primary px-4" :disabled="isSaving || isLoading">
                        <span v-if="isSaving" class="spinner-border spinner-border-sm me-1"></span> {{ isEditMode ? 'Lưu thay đổi' : 'Tạo mới' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ICON PICKER -->
    <div class="modal fade" id="iconModal" ref="iconModalRef" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">Thư viện Icon phổ biến</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" placeholder="Tìm kiếm icon (ví dụ: shop, cart, user...)" v-model="iconSearchQuery">
                    </div>
                    <div class="row g-3">
                        <div class="col-6 col-md-4 col-lg-3" v-for="(icon, idx) in filteredIcons" :key="idx">
                            <div class="card h-100 cursor-pointer hover-shadow border" @click="selectIcon(icon.code)">
                                <div class="card-body text-center p-3">
                                    <div class="fs-2 text-primary mb-2" v-html="icon.code"></div>
                                    <div class="small text-muted text-truncate">{{ icon.name }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 text-center text-muted py-4" v-if="filteredIcons.length === 0">
                            Không tìm thấy icon phù hợp.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL VIEW -->
    <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-0 position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                    <div class="text-center bg-light p-4 rounded-top">
                        <div class="img-thumbnail d-inline-flex align-items-center justify-content-center bg-white shadow-sm rounded-circle mb-3"
                            style="width: 100px; height: 100px; font-size: 2.5rem;">
                            <span v-if="viewingCategory.icon" v-html="viewingCategory.icon" class="text-primary"></span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">{{ viewingCategory.name }}</h4>
                        <span :class="['badge', viewingCategory.status === 'active' ? 'bg-success' : 'bg-secondary']">
                            {{ viewingCategory.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
                        </span>
                    </div>
                    <div class="p-4">
                        <div class="row g-3 text-center mb-4">
                            <div class="col-6 border-end">
                                <small class="text-uppercase text-muted d-block" style="font-size: 0.7rem;">ID</small>
                                <span class="fw-bold">#{{ viewingCategory.id }}</span>
                            </div>
                            <div class="col-6">
                                <small class="text-uppercase text-muted d-block" style="font-size: 0.7rem;">Thứ tự</small>
                                <span class="fw-bold text-primary">No. {{ viewingCategory.order_number }}</span>
                            </div>
                        </div>
                        <div class="bg-light p-3 rounded mb-3 border-start border-4 border-primary">
                            <h6 class="fw-bold small text-secondary mb-1">Mô tả:</h6>
                            <p class="mb-0 text-dark small">{{ viewingCategory.description || 'Chưa có mô tả.' }}</p>
                        </div>
                        <div class="text-center">
                             <button class="btn btn-primary w-100" @click="() => { viewModalInstance.hide(); openEditModal(viewingCategory); }">
                                <i class="bi bi-pencil me-2"></i> Chỉnh sửa
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

/* COMPONENTS */
.form-label.required::after { content: " *"; color: red; }
.cursor-pointer { cursor: pointer; }
.custom-switch:checked { background-color: #009981; border-color: #009981; }
:deep(.table-striped-columns) tbody tr td:nth-child(even) { background-color: rgba(0, 0, 0, 0.02); }
.custom-table th { font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
.btn-white { background-color: white; border-color: #dee2e6; color: #6c757d; }
.btn-white:hover:not(:disabled) { background-color: #f8f9fa; color: #009981; }
.hover-shadow:hover { box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; border-color: #009981 !important; }

/* DRAG HANDLE STYLE */
.cursor-move { cursor: move; }
.hover-opacity-100:hover { opacity: 1 !important; color: #009981 !important; }
</style>