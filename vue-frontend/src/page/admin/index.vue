<script setup>
import { ref, reactive, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'; // Thêm onUnmounted
import apiService from '../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';
import draggable from 'vuedraggable';

// ==========================================
// CONFIGURATION
// ==========================================
// (Phần Icon Library giữ nguyên để tiết kiệm chỗ hiển thị)
const commonIcons = [
    { name: 'Điện thoại', code: '<i class="fa-solid fa-mobile-screen"></i>' },
    { name: 'Laptop', code: '<i class="fa-solid fa-laptop"></i>' },
    // ... (Giữ nguyên danh sách icon của bạn)
    { name: 'Bi Joystick', code: '<i class="bi bi-joystick"></i>' },
];

// ==========================================
// AUTHENTICATION & PERMISSIONS
// ==========================================
const currentUser = ref({});

const hasRole = (allowedRoles) => {
    const userRoleId = Number(currentUser.value?.role_id);
    // Role logic tùy chỉnh của bạn
    if (userRoleId === 11) return true; // Admin
    
    let userRoleName = '';
    if (userRoleId === 12) userRoleName = 'staff';
    else if (userRoleId === 13) userRoleName = 'blogger';

    return allowedRoles.includes(userRoleName);
};

// Check Auth chỉ để lấy thông tin User hiển thị, việc Token đã có Interceptor lo ở apiService
const checkAuthState = async () => {
    const storedAdmin = localStorage.getItem('adminData');
    if (storedAdmin) {
        try {
            currentUser.value = JSON.parse(storedAdmin);
            return true;
        } catch (e) { console.error("Parse user data error", e); }
    }
    
    // Nếu không có data local, gọi API lấy info
    try {
        const response = await apiService.get('/user');
        // API trả về user info nếu token hợp lệ
        let data = response.data;
        if (data.data) data = data.data; 
        
        currentUser.value = data;
        localStorage.setItem('adminData', JSON.stringify(data));
        return true;
    } catch (error) {
        // Lỗi 401 sẽ được apiService chặn và redirect
        return false;
    }
};

const requireLogin = () => {
    if (!localStorage.getItem('adminToken')) {
        // Nếu không có token, redirect luôn
        window.location.href = '/admin/login'; 
        return false;
    }
    // Check quyền (nếu cần thiết hiển thị UI)
    return true;
};

// ==========================================
// STATE MANAGEMENT
// ==========================================
const categories = ref([]);
const sortableList = ref([]); 
const isLoading = ref(true);
const isSaving = ref(false);
const isEditMode = ref(false);
const searchQuery = ref('');
const activeTab = ref('active');
const drag = ref(false);

// Icon Picker & Modals
const isIconModalOpen = ref(false);
const iconSearchQuery = ref('');
const iconModalInstance = ref(null);
const modalInstance = ref(null);
const viewModalInstance = ref(null);

const modalRef = ref(null);
const viewModalRef = ref(null);
const iconModalRef = ref(null);

const viewingCategory = ref({});

// Pagination
const pagination = reactive({
    disabled: { currentPage: 1, itemsPerPage: 10 }
});

// Form Data
const formData = reactive({ 
    id: null, name: '', description: '', icon: '', order_number: 1, status: 'active' 
});
const errors = reactive({ name: '', description: '', icon: '', order_number: '' });

// ==========================================
// COMPUTED & LOGIC
// ==========================================

const filteredIcons = computed(() => {
    const query = iconSearchQuery.value.toLowerCase().trim();
    if (!query) return commonIcons;
    return commonIcons.filter(icon => 
        icon.name.toLowerCase().includes(query) || 
        icon.code.toLowerCase().includes(query)
    );
});

// Logic Filter cho Tab Active
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

// Logic Filter cho Tab Disabled
const processedDisabledList = computed(() => {
    let result = categories.value.filter(c => c.status !== 'active');
    const query = searchQuery.value.toLowerCase().trim();
    if (query) {
        result = result.filter(c =>
            c.name.toLowerCase().includes(query) ||
            (c.description && c.description.toLowerCase().includes(query))
        );
    }
    result.sort((a, b) => (a.order_number || 0) - (b.order_number || 0));
    return result;
});

const statusCounts = computed(() => ({
    active: categories.value.filter(c => c.status === 'active').length,
    disabled: categories.value.filter(c => c.status !== 'active').length
}));

function getPaginatedData(list, type) {
    const pageInfo = pagination[type];
    const totalPages = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));
    if (pageInfo.currentPage > totalPages) pageInfo.currentPage = 1;
    const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
    return { data: list.slice(start, start + pageInfo.itemsPerPage), totalPages };
}

const pagedDisabled = computed(() => getPaginatedData(processedDisabledList.value, 'disabled'));

watch(searchQuery, () => { pagination.disabled.currentPage = 1; });

// ==========================================
// HELPER FUNCTIONS
// ==========================================
const changePage = (type, page) => { pagination[type].currentPage = page; };
const setActiveTab = (tabName) => activeTab.value = tabName;

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

// ==========================================
// ACTIONS (API)
// ==========================================
async function fetchCategories() {
    isLoading.value = true;
    try {
        const response = await apiService.get(`admin/categories`);
        const rawData = response.data.map(cat => ({
            ...cat,
            order_number: cat.order_number !== undefined ? parseInt(cat.order_number) : (parseInt(cat.order) || 0)
        }));
        
        categories.value = rawData;
        // Cập nhật list cho Draggable
        sortableList.value = rawData
            .filter(c => c.status === 'active')
            .sort((a, b) => a.order_number - b.order_number);

    } catch (error) {
        console.error("Lỗi tải danh mục:", error);
        // Không cần Swal lỗi 401 ở đây nữa vì Interceptor đã lo
    } finally {
        isLoading.value = false;
    }
}

async function onDragEnd(event) {
    drag.value = false;
    if (event.newIndex === event.oldIndex) return;

    // Hiển thị loading ảo hoặc toast ngay lập tức để tăng trải nghiệm
    const Toast = Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 1000
    });
    Toast.fire({ icon: 'info', title: 'Đang lưu vị trí...' });

    const orderedIds = sortableList.value.map(item => item.id);

    try {
        await apiService.post('admin/categories/update-order', { ids: orderedIds });
        
        // Cập nhật lại số thứ tự hiển thị UI
        sortableList.value.forEach((item, index) => {
            item.order_number = index + 1;
        });
        Toast.fire({ icon: 'success', title: 'Đã cập nhật vị trí' });
    } catch (error) {
        console.error("Sort API error:", error);
        Swal.fire('Lỗi', 'Không thể lưu thứ tự.', 'error');
        fetchCategories(); // Revert
    }
}

function validateForm() {
    Object.keys(errors).forEach(k => errors[k] = '');
    let isValid = true;
    if (!formData.name.trim()) { errors.name = 'Tên danh mục bắt buộc.'; isValid = false; }
    if (!formData.icon || !formData.icon.trim()) { errors.icon = 'Icon bắt buộc.'; isValid = false; }
    if (formData.order_number === '' || formData.order_number === null) { errors.order_number = 'Thứ tự bắt buộc.'; isValid = false; }
    return isValid;
}

async function handleSave() {
    if (!requireLogin() || !validateForm()) return;

    isSaving.value = true;
    try {
        if (isEditMode.value) {
            await apiService.put(`admin/categories/${formData.id}`, formData);
            Swal.fire('Thành công', 'Đã cập nhật!', 'success');
        } else {
            await apiService.post(`admin/categories`, formData);
            Swal.fire('Thành công', 'Đã tạo mới!', 'success');
        }
        modalInstance.value?.hide();
        fetchCategories();
    } catch (e) {
        if (e.response?.data?.errors) {
            // Map lỗi từ backend Laravel trả về
            const errs = e.response.data.errors;
            if(errs.name) errors.name = errs.name[0];
            if(errs.icon) errors.icon = errs.icon[0];
            if(errs.order_number) errors.order_number = errs.order_number[0];
        } else {
            Swal.fire('Lỗi', 'Không thể lưu dữ liệu.', 'error');
        }
    } finally {
        isSaving.value = false;
    }
}

async function toggleStatus(category) {
    if (!requireLogin()) return;
    const newStatus = category.status === 'active' ? 'disabled' : 'active';
    
    try {
        // Cập nhật Optimistic UI (cập nhật giao diện trước khi gọi API)
        const oldStatus = category.status;
        category.status = newStatus;

        await apiService.put(`admin/categories/${category.id}`, { status: newStatus });
        
        // Load lại để danh sách active/disabled được phân chia lại đúng
        fetchCategories();
        
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Cập nhật thành công', timer: 1500, showConfirmButton: false });
    } catch (error) {
        category.status = category.status === 'active' ? 'disabled' : 'active'; // Revert
        Swal.fire('Lỗi', 'Không thể đổi trạng thái.', 'error');
    }
}

async function handleDelete(category) {
    if (!requireLogin()) return;
    const result = await Swal.fire({
        title: 'Xóa danh mục?',
        text: "Hành động này không thể hoàn tác!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa', cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`admin/categories/${category.id}`);
            categories.value = categories.value.filter(c => c.id !== category.id);
            sortableList.value = sortableList.value.filter(c => c.id !== category.id);
            Swal.fire('Đã xóa!', '', 'success');
        } catch (error) {
            Swal.fire('Lỗi', 'Không thể xóa (có thể có sản phẩm liên quan).', 'error');
        }
    }
}

// Modal & Picker Actions
const openCreateModal = () => { resetForm(); isEditMode.value = false; modalInstance.value?.show(); };
const openEditModal = (cat) => { 
    Object.assign(formData, { ...cat });
    Object.keys(errors).forEach(k => errors[k] = '');
    isEditMode.value = true; 
    modalInstance.value?.show(); 
};
const openViewModal = (cat) => { viewingCategory.value = cat; viewModalInstance.value?.show(); };
const openIconModal = () => { iconSearchQuery.value = ''; iconModalInstance.value?.show(); };
const selectIcon = (code) => { formData.icon = code; errors.icon = ''; iconModalInstance.value?.hide(); };

// Lifecycle
onMounted(async () => {
    // Khởi tạo Bootstrap Modals
    nextTick(() => {
        if (modalRef.value) modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
        if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value);
        if (iconModalRef.value) iconModalInstance.value = new Modal(iconModalRef.value);
    });

    // Check Auth & Fetch Data
    const isAuth = await checkAuthState();
    if (isAuth) {
        fetchCategories();
    }
});

// Clean up modals khi component bị hủy để tránh memory leak
onUnmounted(() => {
    modalInstance.value?.hide();
    viewModalInstance.value?.hide();
    iconModalInstance.value?.hide();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0 text-brand">Quản lý Danh mục</h3></div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header border-bottom-0 pb-0 bg-white">
                    <ul class="nav nav-tabs card-header-tabs custom-tabs">
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: activeTab === 'active' }" href="#" @click.prevent="setActiveTab('active')">
                                <i class="bi bi-check-circle me-1 text-success"></i> Hoạt động
                                <span class="badge rounded-pill bg-success ms-2">{{ statusCounts.active }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" :class="{ active: activeTab === 'disabled' }" href="#" @click.prevent="setActiveTab('disabled')">
                                <i class="bi bi-dash-circle me-1 text-secondary"></i> Vô hiệu hóa
                                <span class="badge rounded-pill bg-secondary ms-2">{{ statusCounts.disabled }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body bg-light border-bottom py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-5 col-12">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm kiếm..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-7 col-12 text-end">
                            <button class="btn btn-primary px-4 shadow-sm" @click="openCreateModal">
                                <i class="bi bi-plus-lg me-1"></i> Tạo mới
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content">
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
                                            <th style="width: 120px;" class="text-center">TT</th>
                                            <th style="width: 160px;" class="text-center">Hành động</th>
                                        </tr>
                                    </thead>

                                    <tbody v-if="searchQuery">
                                        <tr v-for="category in processedActiveList" :key="category.id">
                                            <td class="text-center text-muted"><i class="bi bi-search"></i></td>
                                            <td class="text-muted fw-bold">#{{ category.id }}</td>
                                            <td class="text-center fs-4 text-primary"><span v-html="category.icon"></span></td>
                                            <td class="fw-bold">{{ category.name }}</td>
                                            <td class="d-none d-md-table-cell text-muted small text-truncate" style="max-width: 200px;">{{ category.description }}</td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input custom-switch" type="checkbox" :checked="category.status === 'active'" @click.prevent="toggleStatus(category)">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button class="btn btn-sm btn-light border" @click="openViewModal(category)"><i class="bi bi-eye"></i></button>
                                                    <button class="btn btn-sm btn-light border text-primary" @click="openEditModal(category)"><i class="bi bi-pencil"></i></button>
                                                    <button class="btn btn-sm btn-light border text-danger" @click="handleDelete(category)"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="processedActiveList.length === 0"><td colspan="7" class="text-center py-4">Không tìm thấy kết quả.</td></tr>
                                    </tbody>

                                    <draggable 
                                        v-else
                                        v-model="sortableList" 
                                        tag="tbody" 
                                        item-key="id"
                                        handle=".drag-handle"
                                        @start="drag=true" 
                                        @end="onDragEnd"
                                        v-if="!isLoading && sortableList.length > 0"
                                    >
                                        <template #item="{ element: category }">
                                            <tr :class="{ 'bg-light': drag }">
                                                <td class="text-center cursor-move drag-handle"><i class="bi bi-grip-vertical text-secondary"></i></td>
                                                <td class="text-muted fw-bold">#{{ category.id }}</td>
                                                <td class="text-center fs-4 text-primary"><span v-html="category.icon"></span></td>
                                                <td class="fw-bold">
                                                    {{ category.name }}
                                                    <span class="badge bg-light text-secondary ms-2 border">Top {{ category.order_number }}</span>
                                                </td>
                                                <td class="d-none d-md-table-cell text-muted small text-truncate" style="max-width: 200px;">{{ category.description }}</td>
                                                <td class="text-center">
                                                    <div class="form-check form-switch d-flex justify-content-center">
                                                        <input class="form-check-input custom-switch" type="checkbox" :checked="category.status === 'active'" @click.prevent="toggleStatus(category)">
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button class="btn btn-sm btn-light border" @click="openViewModal(category)"><i class="bi bi-eye"></i></button>
                                                        <button class="btn btn-sm btn-light border text-primary" @click="openEditModal(category)"><i class="bi bi-pencil"></i></button>
                                                        <button class="btn btn-sm btn-light border text-danger" @click="handleDelete(category)"><i class="bi bi-trash"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </draggable>
                                    
                                    <tbody v-if="!searchQuery && (isLoading || sortableList.length === 0)">
                                         <tr v-if="isLoading"><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                                         <tr v-else><td colspan="7" class="text-center py-5 text-muted fst-italic">Danh sách trống.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade show active" v-if="activeTab === 'disabled'">
                             <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 custom-table">
                                    <thead class="bg-light text-secondary">
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>ID</th>
                                            <th>Icon</th>
                                            <th>Tên</th>
                                            <th>TT</th>
                                            <th class="text-center">Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                         <tr v-for="category in pagedDisabled.data" :key="category.id">
                                            <td class="text-center text-muted">{{ category.order_number }}</td>
                                            <td>#{{ category.id }}</td>
                                            <td class="fs-4 text-muted"><span v-html="category.icon"></span></td>
                                            <td class="text-decoration-line-through text-muted">{{ category.name }}</td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input custom-switch" type="checkbox" :checked="false" @click.prevent="toggleStatus(category)">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                 <button class="btn btn-sm btn-light border text-danger" @click="handleDelete(category)"><i class="bi bi-trash"></i></button>
                                            </td>
                                         </tr>
                                         <tr v-if="pagedDisabled.data.length === 0"><td colspan="6" class="text-center py-4">Thùng rác rỗng.</td></tr>
                                    </tbody>
                                </table>
                             </div>
                             <div class="card-footer bg-white border-top-0 py-3" v-if="pagedDisabled.totalPages > 1">
                                <ul class="pagination pagination-sm m-0 justify-content-end">
                                    <li class="page-item" :class="{ disabled: pagination.disabled.currentPage === 1 }"><button class="page-link" @click="changePage('disabled', pagination.disabled.currentPage - 1)">&laquo;</button></li>
                                    <li class="page-item disabled"><span class="page-link">{{ pagination.disabled.currentPage }} / {{ pagedDisabled.totalPages }}</span></li>
                                    <li class="page-item" :class="{ disabled: pagination.disabled.currentPage === pagedDisabled.totalPages }"><button class="page-link" @click="changePage('disabled', pagination.disabled.currentPage + 1)">&raquo;</button></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="categoryModal" ref="modalRef" tabindex="-1" data-bs-backdrop="static">
         <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-brand">{{ isEditMode ? 'Chỉnh sửa' : 'Tạo mới' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="handleSave" id="categoryForm">
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tên danh mục</label>
                                <input type="text" class="form-control" v-model="formData.name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Icon (HTML)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" v-model="formData.icon">
                                    <button type="button" class="btn btn-outline-secondary" @click="openIconModal">Chọn</button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Thứ tự</label>
                                <input type="number" class="form-control" v-model="formData.order_number">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Mô tả</label>
                                <textarea class="form-control" v-model="formData.description"></textarea>
                            </div>
                         </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" form="categoryForm" class="btn btn-primary">Lưu</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="iconModal" ref="iconModalRef" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chọn Icon</h5>
                    <input type="text" class="form-control ms-3 w-50" placeholder="Tìm kiếm..." v-model="iconSearchQuery">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-3 text-center p-2 border cursor-pointer hover-shadow" v-for="(icon, idx) in filteredIcons" :key="idx" @click="selectIcon(icon.code)">
                            <div class="fs-3 text-primary" v-html="icon.code"></div>
                            <small>{{ icon.name }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-body">...</div></div></div></div>

</template>

<style scoped>
/* COLORS */
.text-brand { color: #009981 !important; }
.text-primary { color: #009981 !important; }
.bg-primary { background-color: #009981 !important; }
.btn-primary { background-color: #009981 !important; border-color: #009981 !important; color: white !important; }
.btn-primary:hover { background-color: #007a67 !important; border-color: #007a67 !important; }

/* UTILS */
.cursor-move { cursor: move; }
.cursor-pointer { cursor: pointer; }
.hover-shadow:hover { background: #f8f9fa; border-color: #009981 !important; }
.custom-table th { font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
</style>