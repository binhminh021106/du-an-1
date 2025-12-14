<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// CONFIGURATION
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
const BACKEND_URL = API_BASE_URL.endsWith('/api') ? API_BASE_URL.slice(0, -4) : API_BASE_URL;

// AUTHENTICATION & PERMISSIONS
const currentUser = ref({});

const hasRole = (allowedRoles) => {
    const userRoleId = Number(currentUser.value?.role_id);
    let userRoleName = '';

    if (userRoleId === 1) userRoleName = 'admin';
    else if (userRoleId === 12) userRoleName = 'staff';
    else if (userRoleId === 13) userRoleName = 'blogger';

    if (!userRoleName) return false;
    if (userRoleName === 'admin') return true; // Admin chấp hết

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
        Swal.fire({ icon: 'error', title: 'Lỗi truy cập', text: 'Phiên làm việc hết hạn.', confirmButtonText: 'Đăng nhập ngay' });
        return false;
    }
    // Cho phép Admin và Staff quản lý đánh giá
    if (!hasRole(['admin', 'staff'])) {
        Swal.fire({ icon: 'warning', title: 'Quyền hạn', text: 'Bạn không có quyền quản lý đánh giá.' });
        return false;
    }
    return true;
};

// STATE MANAGEMENT
const allReviews = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const sortOption = ref('newest'); 
const activeTab = ref('pending'); 

// Modals
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingReview = ref({});

// Pagination (Riêng biệt cho 3 tab để giữ trạng thái trang khi chuyển tab)
const pagination = reactive({
    pending: { currentPage: 1, itemsPerPage: 8 },
    approved: { currentPage: 1, itemsPerPage: 8 },
    rejected: { currentPage: 1, itemsPerPage: 8 }
});

// COMPUTED & LOGIC

// 1. Lọc và Sắp xếp tổng
const processedReviews = computed(() => {
    let result = [...allReviews.value];

    // Search
    const query = searchQuery.value.toLowerCase().trim();
    if (query) {
        result = result.filter(r =>
            // FIX: Đổi username thành fullName
            (r.user?.fullName && r.user.fullName.toLowerCase().includes(query)) ||
            (r.product?.name && r.product.name.toLowerCase().includes(query)) ||
            (r.content && r.content.toLowerCase().includes(query))
        );
    }

    // Sort
    result.sort((a, b) => {
        switch (sortOption.value) {
            case 'rating_desc': return b.rating - a.rating;
            case 'rating_asc': return a.rating - b.rating;
            case 'oldest': return new Date(a.created_at) - new Date(b.created_at);
            case 'newest': 
            default: return new Date(b.created_at) - new Date(a.created_at);
        }
    });

    return result;
});

// 2. Chia danh sách theo Tab
const pendingList = computed(() => processedReviews.value.filter(r => r.status === 'pending'));
const approvedList = computed(() => processedReviews.value.filter(r => r.status === 'approved'));
const rejectedList = computed(() => processedReviews.value.filter(r => r.status === 'rejected'));

// 3. Đếm số lượng cho Tabs Badge
const statusCounts = computed(() => ({
    pending: pendingList.value.length,
    approved: approvedList.value.length,
    rejected: rejectedList.value.length
}));

// 4. Helper Phân trang
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

const pagedPending = computed(() => getPaginatedData(pendingList.value, 'pending'));
const pagedApproved = computed(() => getPaginatedData(approvedList.value, 'approved'));
const pagedRejected = computed(() => getPaginatedData(rejectedList.value, 'rejected'));

// Watchers
watch([searchQuery, sortOption], () => {
    pagination.pending.currentPage = 1;
    pagination.approved.currentPage = 1;
    pagination.rejected.currentPage = 1;
});

// HELPER FUNCTIONS
const changePage = (type, page) => { pagination[type].currentPage = page; };
const setActiveTab = (tabName) => activeTab.value = tabName;

const getProductImageUrl = (path) => {
    if (!path) return 'https://placehold.co/120x120/EBF4FF/1D62F0?text=No+Img';
    if (path.startsWith('http')) return path;
    return `${BACKEND_URL}${path.startsWith('/') ? '' : '/'}${path}`;
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('vi-VN', { 
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' 
    });
};

const renderStars = (rating) => '★'.repeat(Math.floor(rating)) + '☆'.repeat(5 - Math.floor(rating));

const openViewModal = (review) => {
    viewingReview.value = review;
    viewModalInstance.value?.show();
};

// ACTIONS (API)
async function fetchReviews() {
    if (allReviews.value.length === 0) isLoading.value = true;
    try {
        const response = await apiService.get(`admin/reviews`);
        // Kiểm tra xem dữ liệu trả về có nằm trong wrapper 'data' không (Laravel Resource thường trả về { data: [...] })
        allReviews.value = Array.isArray(response.data) ? response.data : (response.data.data || []);
    } catch (error) {
        console.error("Lỗi tải đánh giá:", error);
    } finally {
        isLoading.value = false;
    }
}

async function handleUpdateStatus(review, newStatus) {
    if (!requireLogin()) return;

    const oldStatus = review.status;
    const actionText = newStatus === 'approved' ? 'Duyệt' : 'Từ chối';
    
    // Optimistic Update (Cập nhật giao diện trước)
    review.status = newStatus;

    // Toast thông báo
    Swal.fire({
        toast: true, position: 'top-end', icon: newStatus === 'approved' ? 'success' : 'info',
        title: `Đã ${actionText} đánh giá`, showConfirmButton: false, timer: 1500
    });

    try {
        await apiService.patch(`admin/reviews/${review.id}`, { status: newStatus });
    } catch (error) {
        // Rollback nếu lỗi
        review.status = oldStatus;
        Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
    }
}

async function handleDelete(review) {
    if (!requireLogin()) return;
    
    const result = await Swal.fire({
        title: 'Xóa đánh giá?',
        text: "Hành động này không thể hoàn tác!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`admin/reviews/${review.id}`);
            allReviews.value = allReviews.value.filter(r => r.id !== review.id);
            Swal.fire('Đã xóa!', 'Đánh giá đã được xóa.', 'success');
        } catch (error) {
            Swal.fire('Lỗi', 'Không thể xóa.', 'error');
        }
    }
}

onMounted(async () => {
    await checkAuthState();
    if (!requireLogin()) { isLoading.value = false; return; }
    
    nextTick(() => {
        if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value);
    });
    fetchReviews();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0 text-brand">Quản lý Đánh giá</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active">Đánh giá</li>
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
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'pending' }" href="#" @click.prevent="setActiveTab('pending')">
                                <i class="bi bi-hourglass-split me-1 text-warning"></i> Chờ duyệt
                                <span class="badge rounded-pill bg-warning text-dark ms-2" v-if="statusCounts.pending > 0">{{ statusCounts.pending }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'approved' }" href="#" @click.prevent="setActiveTab('approved')">
                                <i class="bi bi-check-circle me-1 text-success"></i> Đã duyệt
                                <span class="badge rounded-pill bg-success ms-2">{{ statusCounts.approved }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'rejected' }" href="#" @click.prevent="setActiveTab('rejected')">
                                <i class="bi bi-x-circle me-1 text-danger"></i> Đã từ chối
                                <span class="badge rounded-pill bg-danger ms-2">{{ statusCounts.rejected }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- FILTER BAR -->
                <div class="card-body bg-light border-bottom py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-4 col-12">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm người dùng, sản phẩm..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <select class="form-select" v-model="sortOption">
                                <option value="newest">⏱️ Mới nhất</option>
                                <option value="oldest">⏳ Cũ nhất</option>
                                <option value="rating_desc">⭐ Sao (Cao - Thấp)</option>
                                <option value="rating_asc">⭐ Sao (Thấp - Cao)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- TABLE CONTENT -->
                <div class="card-body p-0">
                    <div class="tab-content">
                        
                        <!-- TAB: PENDING -->
                        <div class="tab-pane fade show active" v-if="activeTab === 'pending'">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 custom-table">
                                    <thead class="bg-light text-secondary">
                                        <tr>
                                            <th style="width: 50px" class="ps-3">ID</th>
                                            <th>Sản phẩm</th>
                                            <th>Người gửi</th>
                                            <th style="width: 120px">Sao</th>
                                            <th>Nội dung</th>
                                            <th style="width: 150px">Ngày gửi</th>
                                            <th style="width: 160px" class="text-end pe-3">Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="isLoading"><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                                        <tr v-else-if="pagedPending.data.length === 0"><td colspan="7" class="text-center py-5 text-muted fst-italic">Không có đánh giá chờ duyệt.</td></tr>
                                        <tr v-else v-for="review in pagedPending.data" :key="review.id">
                                            <td class="ps-3 fw-bold text-muted">{{ review.id }}</td>
                                            <td><span class="fw-bold text-dark">{{ review.product?.name }}</span></td>
                                            <!-- FIX: Đổi username thành fullName -->
                                            <td>{{ review.user?.fullName || 'Ẩn danh' }}</td>
                                            <td class="text-warning small">{{ renderStars(review.rating) }}</td>
                                            <td><span class="d-inline-block text-truncate text-muted" style="max-width: 250px;">{{ review.content }}</span></td>
                                            <td class="small text-muted">{{ formatDate(review.created_at) }}</td>
                                            <td class="text-end pe-3">
                                                <button class="btn btn-sm btn-light text-secondary border me-1" @click="openViewModal(review)"><i class="bi bi-eye"></i></button>
                                                <button class="btn btn-sm btn-success me-1" @click="handleUpdateStatus(review, 'approved')" title="Duyệt"><i class="bi bi-check-lg"></i></button>
                                                <button class="btn btn-sm btn-outline-danger" @click="handleUpdateStatus(review, 'rejected')" title="Từ chối"><i class="bi bi-x-lg"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination logic remains the same -->
                            <div class="card-footer bg-white border-top-0 py-3" v-if="pagedPending.totalPages > 1">
                                <ul class="pagination pagination-sm m-0 justify-content-end">
                                    <li class="page-item" :class="{ disabled: pagination.pending.currentPage === 1 }"><button class="page-link border-0" @click="changePage('pending', pagination.pending.currentPage - 1)">&laquo;</button></li>
                                    <li v-for="p in pagedPending.totalPages" :key="p" class="page-item" :class="{ active: pagination.pending.currentPage === p }"><button class="page-link border-0 rounded-circle mx-1" @click="changePage('pending', p)">{{ p }}</button></li>
                                    <li class="page-item" :class="{ disabled: pagination.pending.currentPage === pagedPending.totalPages }"><button class="page-link border-0" @click="changePage('pending', pagination.pending.currentPage + 1)">&raquo;</button></li>
                                </ul>
                            </div>
                        </div>

                        <!-- TAB: APPROVED -->
                        <div class="tab-pane fade show active" v-if="activeTab === 'approved'">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 custom-table">
                                    <thead class="bg-light text-secondary">
                                        <tr>
                                            <th style="width: 50px" class="ps-3">ID</th>
                                            <th>Sản phẩm</th>
                                            <th>Người gửi</th>
                                            <th style="width: 120px">Sao</th>
                                            <th>Nội dung</th>
                                            <th style="width: 150px">Ngày gửi</th>
                                            <th style="width: 160px" class="text-end pe-3">Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="isLoading"><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                                        <tr v-else-if="pagedApproved.data.length === 0"><td colspan="7" class="text-center py-5 text-muted fst-italic">Chưa có đánh giá nào được duyệt.</td></tr>
                                        <tr v-else v-for="review in pagedApproved.data" :key="review.id">
                                            <td class="ps-3 fw-bold text-muted">{{ review.id }}</td>
                                            <td><span class="fw-bold text-dark">{{ review.product?.name }}</span></td>
                                            <!-- FIX: Đổi username thành fullName -->
                                            <td>{{ review.user?.fullName || 'Ẩn danh' }}</td>
                                            <td class="text-warning small">{{ renderStars(review.rating) }}</td>
                                            <td><span class="d-inline-block text-truncate text-muted" style="max-width: 250px;">{{ review.content }}</span></td>
                                            <td class="small text-muted">{{ formatDate(review.created_at) }}</td>
                                            <td class="text-end pe-3">
                                                <button class="btn btn-sm btn-light text-secondary border me-1" @click="openViewModal(review)"><i class="bi bi-eye"></i></button>
                                                <button class="btn btn-sm btn-light text-secondary border me-1" @click="handleUpdateStatus(review, 'rejected')" title="Gỡ bỏ"><i class="bi bi-x-lg"></i></button>
                                                <button class="btn btn-sm btn-light text-danger border" @click="handleDelete(review)"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-white border-top-0 py-3" v-if="pagedApproved.totalPages > 1">
                                <ul class="pagination pagination-sm m-0 justify-content-end">
                                    <li class="page-item" :class="{ disabled: pagination.approved.currentPage === 1 }"><button class="page-link border-0" @click="changePage('approved', pagination.approved.currentPage - 1)">&laquo;</button></li>
                                    <li v-for="p in pagedApproved.totalPages" :key="p" class="page-item" :class="{ active: pagination.approved.currentPage === p }"><button class="page-link border-0 rounded-circle mx-1" @click="changePage('approved', p)">{{ p }}</button></li>
                                    <li class="page-item" :class="{ disabled: pagination.approved.currentPage === pagedApproved.totalPages }"><button class="page-link border-0" @click="changePage('approved', pagination.approved.currentPage + 1)">&raquo;</button></li>
                                </ul>
                            </div>
                        </div>

                        <!-- TAB: REJECTED -->
                        <div class="tab-pane fade show active" v-if="activeTab === 'rejected'">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 custom-table">
                                    <thead class="bg-light text-secondary">
                                        <tr>
                                            <th style="width: 50px" class="ps-3">ID</th>
                                            <th>Sản phẩm</th>
                                            <th>Người gửi</th>
                                            <th style="width: 120px">Sao</th>
                                            <th>Nội dung</th>
                                            <th style="width: 150px">Ngày gửi</th>
                                            <th style="width: 160px" class="text-end pe-3">Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="isLoading"><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                                        <tr v-else-if="pagedRejected.data.length === 0"><td colspan="7" class="text-center py-5 text-muted fst-italic">Chưa có đánh giá nào bị từ chối.</td></tr>
                                        <tr v-else v-for="review in pagedRejected.data" :key="review.id">
                                            <td class="ps-3 fw-bold text-muted">{{ review.id }}</td>
                                            <td><span class="fw-bold text-dark">{{ review.product?.name }}</span></td>
                                            <!-- FIX: Đổi username thành fullName -->
                                            <td>{{ review.user?.fullName || 'Ẩn danh' }}</td>
                                            <td class="text-warning small">{{ renderStars(review.rating) }}</td>
                                            <td><span class="d-inline-block text-truncate text-muted" style="max-width: 250px;">{{ review.content }}</span></td>
                                            <td class="small text-muted">{{ formatDate(review.created_at) }}</td>
                                            <td class="text-end pe-3">
                                                <button class="btn btn-sm btn-light text-secondary border me-1" @click="openViewModal(review)"><i class="bi bi-eye"></i></button>
                                                <button class="btn btn-sm btn-outline-success me-1" @click="handleUpdateStatus(review, 'approved')" title="Duyệt lại"><i class="bi bi-check-lg"></i></button>
                                                <button class="btn btn-sm btn-light text-danger border" @click="handleDelete(review)"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-white border-top-0 py-3" v-if="pagedRejected.totalPages > 1">
                                <ul class="pagination pagination-sm m-0 justify-content-end">
                                    <li class="page-item" :class="{ disabled: pagination.rejected.currentPage === 1 }"><button class="page-link border-0" @click="changePage('rejected', pagination.rejected.currentPage - 1)">&laquo;</button></li>
                                    <li v-for="p in pagedRejected.totalPages" :key="p" class="page-item" :class="{ active: pagination.rejected.currentPage === p }"><button class="page-link border-0 rounded-circle mx-1" @click="changePage('rejected', p)">{{ p }}</button></li>
                                    <li class="page-item" :class="{ disabled: pagination.rejected.currentPage === pagedRejected.totalPages }"><button class="page-link border-0" @click="changePage('rejected', pagination.rejected.currentPage + 1)">&raquo;</button></li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL VIEW DETAIL -->
    <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-brand"><i class="bi bi-chat-square-quote me-2"></i> Chi tiết đánh giá</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Product Info -->
                        <div class="col-md-5 text-center border-end">
                            <div class="mb-3 position-relative">
                                <img :src="getProductImageUrl(viewingReview.product?.thumbnail_url)" class="img-fluid rounded shadow-sm border" style="max-height: 200px; object-fit: contain;">
                            </div>
                            <h5 class="text-primary fw-bold mb-1">{{ viewingReview.product?.name || 'Sản phẩm không rõ' }}</h5>
                            <p class="text-muted small">ID: #{{ viewingReview.product?.id }}</p>
                            <div class="mt-3">
                                <span v-if="viewingReview.status === 'pending'" class="badge bg-warning text-dark px-3 py-2">Đang chờ duyệt</span>
                                <span v-if="viewingReview.status === 'approved'" class="badge bg-success px-3 py-2">Đã duyệt</span>
                                <span v-if="viewingReview.status === 'rejected'" class="badge bg-danger px-3 py-2">Đã từ chối</span>
                            </div>
                        </div>

                        <!-- Review Info -->
                        <div class="col-md-7">
                            <div class="d-flex align-items-center mb-4 p-3 bg-light rounded">
                                <!-- FIX: Đổi avatar thành avatar_url và fullName -->
                                <img :src="viewingReview.user?.avatar_url || `https://placehold.co/50x50/009981/ffffff?text=${viewingReview.user?.fullName?.charAt(0).toUpperCase() || 'U'}`" class="rounded-circle me-3 border" width="50" height="50">
                                <div>
                                    <!-- FIX: Đổi username thành fullName -->
                                    <div class="fw-bold fs-6">{{ viewingReview.user?.fullName || 'Người dùng ẩn danh' }}</div>
                                    <div class="text-muted small">User ID: #{{ viewingReview.user?.id }}</div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <span class="text-warning fs-5 me-2">{{ renderStars(viewingReview.rating) }}</span>
                                <span class="text-muted small fw-bold">({{ viewingReview.rating }}/5 Tuyệt vời)</span>
                            </div>
                            
                            <div class="p-3 border rounded mb-3 bg-white">
                                <p class="mb-0 text-dark">{{ viewingReview.content }}</p>
                            </div>
                            
                            <div class="text-end text-muted small">
                                <i class="bi bi-clock me-1"></i> Gửi lúc: {{ formatDate(viewingReview.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Đóng</button>
                    <div v-if="viewingReview.status === 'pending'" class="d-flex gap-2">
                        <button class="btn btn-outline-danger px-3" @click="() => { viewModalInstance.hide(); handleUpdateStatus(viewingReview, 'rejected'); }">Từ chối</button>
                        <button class="btn btn-success px-3" @click="() => { viewModalInstance.hide(); handleUpdateStatus(viewingReview, 'approved'); }">Duyệt bài</button>
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

/* Success Button (Mapped to Brand) */
.btn-success { background-color: #009981 !important; border-color: #009981 !important; color: white; }
.btn-success:hover { background-color: #007a67 !important; border-color: #007a67 !important; }

/* TABS */
.custom-tabs .nav-link { color: #6c757d; border: none; font-weight: 500; padding: 12px 20px; border-bottom: 3px solid transparent; cursor: pointer; }
.custom-tabs .nav-link:hover { color: #009981; }
.custom-tabs .nav-link.active { color: #009981; background: transparent; border-bottom: 3px solid #009981; }

/* PAGINATION */
.page-item.active .page-link { background-color: #009981 !important; border-color: #009981 !important; color: white !important; }
.page-link { color: #666; cursor: pointer; }

/* TABLE */
:deep(.table-striped-columns) tbody tr td:nth-child(even) { background-color: rgba(0, 0, 0, 0.02); }
.custom-table th { font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }

/* MODAL */
.modal-title { color: #00483D; }
</style>