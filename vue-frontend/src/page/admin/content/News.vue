<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import html2pdf from 'html2pdf.js';

// ==========================================
// CONFIGURATION
// ==========================================
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
const BACKEND_URL = API_BASE_URL.endsWith('/api') ? API_BASE_URL.slice(0, -4) : API_BASE_URL;
const FRONTEND_URL = window.location.origin;

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
    if (token) {
        apiService.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }

    const storedAdmin = localStorage.getItem('adminData');
    const storedUser = localStorage.getItem('user_info');
    let userData = null;

    try {
        if (storedAdmin) userData = JSON.parse(storedAdmin);
        else if (storedUser) userData = JSON.parse(storedUser);
    } catch (e) { console.error("Parse user error", e); }

    if (userData) {
        updateCurrentUser(userData);
        return true;
    }

    if (token) {
        try {
            const response = await apiService.get('/user');
            let data = response.data;
            if (data.data && !data.id) data = data.data;

            updateCurrentUser(data);
            localStorage.setItem('adminData', JSON.stringify(currentUser.value));
            return true;
        } catch (error) {
            console.error("Auth API Error:", error);
            return false;
        }
    }
    return false;
};

const updateCurrentUser = (data) => {
    currentUser.value = {
        ...data,
        role_id: Number(data.role_id),
        name: data.fullname || data.full_name || data.name || 'Admin'
    };
};

const requireLogin = () => {
    if (!currentUser.value.id) {
        Swal.fire({
            icon: 'error',
            title: 'Truy cập bị từ chối',
            text: 'Phiên làm việc hết hạn.',
            confirmButtonText: 'Đăng nhập ngay',
        });
        return false;
    }
    return true;
};

// ==========================================
// STATE MANAGEMENT
// ==========================================
const isLoading = ref(true);
const isEditMode = ref(false);
const news = ref([]);
const searchQuery = ref('');

// [NEW] Tab & Sorting State
const currentTab = ref('pending'); // Mặc định tab đầu tiên là 'pending'
const sortOption = ref('newest');  // Mặc định mới nhất

// File Handling
const selectedFile = ref(null);
const previewImage = ref(null);

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(5);

// Modals
const modalInstance = ref(null);
const modalRef = ref(null);
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingNewsItem = ref({});

// Form Data
const formData = reactive({
    id: null, title: '', excerpt: '', content: '', slug: '',
    status: 'pending', author_name: ''
});

const errors = reactive({ title: '', slug: '', content: '', author_name: '' });

// Quill Editor Config
const quillInstance = ref(null);
const quillToolbar = [
    ['bold', 'italic', 'underline', 'strike'], ['blockquote', 'code-block'],
    [{ 'header': 1 }, { 'header': 2 }], [{ 'list': 'ordered' }, { 'list': 'bullet' }],
    [{ 'script': 'sub' }, { 'script': 'super' }], [{ 'indent': '-1' }, { 'indent': '+1' }],
    [{ 'direction': 'rtl' }], [{ 'size': ['small', false, 'large', 'huge'] }],
    [{ 'header': [1, 2, 3, 4, 5, 6, false] }], [{ 'color': [] }, { 'background': [] }],
    [{ 'font': [] }], [{ 'align': [] }], ['clean'], ['link', 'image', 'video']
];

// ==========================================
// COMPUTED & WATCHERS
// ==========================================

// [NEW] Tính toán số lượng bài viết cho từng trạng thái để hiện lên Tab
const statusCounts = computed(() => {
    const list = news.value;
    return {
        all: list.length,
        pending: list.filter(i => i.status === 'pending').length,
        published: list.filter(i => i.status === 'published').length,
        draft: list.filter(i => i.status === 'draft').length
    };
});

const processedNews = computed(() => {
    let result = news.value;

    // 1. Filter by Search Query
    const query = searchQuery.value.toLowerCase().trim();
    if (query) {
        result = result.filter(item => item.title.toLowerCase().includes(query));
    }

    // 2. Filter by Tab Status
    if (currentTab.value !== 'all') {
        result = result.filter(item => item.status === currentTab.value);
    }

    // 3. Sorting
    result = [...result].sort((a, b) => {
        if (sortOption.value === 'newest') {
            return new Date(b.created_at) - new Date(a.created_at);
        } else if (sortOption.value === 'oldest') {
            return new Date(a.created_at) - new Date(b.created_at);
        } else if (sortOption.value === 'a-z') {
            return a.title.localeCompare(b.title);
        } else if (sortOption.value === 'z-a') {
            return b.title.localeCompare(a.title);
        }
        return 0;
    });

    return result;
});

const totalPages = computed(() => Math.ceil(processedNews.value.length / itemsPerPage.value));

const paginatedNews = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    // Fix lỗi nếu đang ở trang xa mà đổi tab bị trắng trang
    if (start >= processedNews.value.length && currentPage.value > 1) {
        currentPage.value = 1;
        return processedNews.value.slice(0, itemsPerPage.value);
    }
    return processedNews.value.slice(start, start + itemsPerPage.value);
});

// Reset trang về 1 khi đổi tab hoặc search
watch([searchQuery, currentTab, sortOption], () => currentPage.value = 1);

watch(() => formData.title, (newTitle) => {
    if (!isEditMode.value && newTitle) formData.slug = slugify(newTitle);
});

// ==========================================
// HELPER FUNCTIONS
// ==========================================
const getFullImage = (path) => {
    if (!path) return 'https://placehold.co/800x400?text=No+Image';
    if (path.startsWith('blob:') || path.startsWith('http')) return path;
    return `${BACKEND_URL}${path.startsWith('/') ? '' : '/'}${path}`;
};

const slugify = (text) => text ? text.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, '-').replace(/[^\w\-]+/g, '').replace(/\-\-+/g, '-').replace(/^-+/, '').replace(/-+$/, '') : '';

const getFormattedDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('vi-VN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit'
    });
};

const getDisplayAuthor = (item) => item.author_name || 'Không rõ';

const getStatusInfo = (status) => {
    const map = {
        'published': { text: 'Xuất bản', class: 'text-bg-success', icon: 'bi-check-circle' },
        'pending': { text: 'Đợi duyệt', class: 'text-bg-warning', icon: 'bi-hourglass-split' },
        'draft': { text: 'Đã ẩn', class: 'text-bg-secondary', icon: 'bi-eye-slash' }
    };
    return map[status] || { text: 'Không rõ', class: 'text-bg-light', icon: 'bi-question-circle' };
};

const validateImageFile = async (file) => {
    if (file.size > 10 * 1024 * 1024) return { valid: false, msg: 'Dung lượng tối đa 10MB.' };
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onloadend = (e) => {
            const arr = (new Uint8Array(e.target.result)).subarray(0, 4);
            let header = "";
            for (let i = 0; i < arr.length; i++) header += arr[i].toString(16);
            const isValid = header.startsWith('ffd8ff') || header.startsWith('89504e47') || header.startsWith('47494638') || header.startsWith('52494646');
            resolve(isValid ? { valid: true } : { valid: false, msg: 'File không hợp lệ.' });
        };
        reader.onerror = () => resolve({ valid: false, msg: 'Lỗi đọc file.' });
        reader.readAsArrayBuffer(file.slice(0, 4));
    });
};

function resetForm() {
    Object.assign(formData, {
        id: null, title: '', excerpt: '', content: '', slug: '',
        status: 'pending',
        author_name: currentUser.value.name || ''
    });
    selectedFile.value = null;
    previewImage.value = null;
    const fileInput = document.getElementById('imageInput');
    if (fileInput) fileInput.value = '';
    Object.assign(errors, { title: '', slug: '', content: '', author_name: '' });
}

function validateForm() {
    Object.assign(errors, { title: '', slug: '', content: '', author_name: '' });
    let isValid = true;
    if (!formData.title.trim()) { errors.title = 'Tiêu đề là bắt buộc.'; isValid = false; }
    else if (formData.title.length > 255) { errors.title = 'Quá 255 ký tự.'; isValid = false; }
    if (!formData.slug.trim()) { errors.slug = 'Slug là bắt buộc.'; isValid = false; }
    else if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(formData.slug)) { errors.slug = 'Slug không hợp lệ.'; isValid = false; }
    if (!formData.author_name || !formData.author_name.trim()) { errors.author_name = 'Tên tác giả bắt buộc.'; isValid = false; }
    else if (formData.author_name.length > 100) { errors.author_name = 'Quá 100 ký tự.'; isValid = false; }
    const strippedContent = formData.content.replace(/<[^>]*>/g, '').trim();
    if (!strippedContent && !formData.content.includes('<img')) { errors.content = 'Nội dung trống.'; isValid = false; }
    return isValid;
}

function initializeModals() {
    nextTick(() => {
        if (modalRef.value) modalInstance.value = new Modal(modalRef.value, { backdrop: 'static', keyboard: false });
        if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value, { keyboard: true });
    });
}

// ==========================================
// ACTIONS (HANDLERS)
// ==========================================
const handleFileChange = async (event) => {
    const file = event.target.files[0];
    if (file) {
        const checkResult = await validateImageFile(file);
        if (!checkResult.valid) { Swal.fire('Lỗi File', checkResult.msg, 'error'); event.target.value = null; return; }
        selectedFile.value = file; previewImage.value = URL.createObjectURL(file);
    }
};

const handleCopyLink = (slug) => {
    const url = `${FRONTEND_URL}/news/${slug}`;
    navigator.clipboard.writeText(url).then(() => Swal.fire({ toast: true, position: 'top', icon: 'success', title: 'Đã copy link!', showConfirmButton: false, timer: 1500 }));
};

const handleShare = async (newsItem) => {
    const shareData = { title: newsItem.title, text: newsItem.excerpt, url: `${FRONTEND_URL}/news/${newsItem.slug}` };
    if (navigator.share) { try { await navigator.share(shareData); } catch (err) { } } else { handleCopyLink(newsItem.slug); }
};

const openCreateModal = () => {
    if (!requireLogin()) return;
    resetForm();
    isEditMode.value = false;
    formData.status = 'pending';
    modalInstance.value?.show();
};

const openEditModal = (newsItem) => {
    if (!requireLogin()) return;
    const itemCopy = JSON.parse(JSON.stringify(newsItem));
    resetForm();
    Object.assign(formData, { ...itemCopy, content: itemCopy.content || '' });
    if (!formData.author_name) formData.author_name = currentUser.value.name;
    previewImage.value = itemCopy.image_url || null;
    isEditMode.value = true;
    if (viewModalRef.value?.classList.contains('show')) viewModalInstance.value?.hide();
    modalInstance.value?.show();
};

const openViewModal = (newsItem) => {
    viewingNewsItem.value = newsItem;
    viewModalInstance.value?.show();
};

const exportToPDF = (item = null) => {
    const sourceData = item || formData;
    if (!sourceData.content) { Swal.fire('Thông báo', 'Nội dung trống', 'warning'); return; }
    const displayAuthor = sourceData.author_name || 'Không rõ';
    const element = document.createElement('div');
    element.innerHTML = `
        <h1 style="text-align: center; font-size: 24pt; margin-bottom: 20px;">${sourceData.title || 'Bài viết'}</h1>
        <div style="text-align: center; margin-bottom: 20px; color: #666;">
            <strong>Tác giả:</strong> ${displayAuthor} <br/>
            <strong>Ngày tạo:</strong> ${getFormattedDate(sourceData.created_at || new Date())}
        </div>
        <p style="font-style: italic; color: #555; padding-left: 10px; border-left: 3px solid #ccc;">${sourceData.excerpt || ''}</p>
        <hr style="margin: 20px 0;" />
        ${sourceData.content}
    `;
    element.style.fontFamily = 'Times New Roman, serif';
    const images = element.getElementsByTagName('img');
    for (let img of images) { img.style.maxWidth = '100%'; img.style.height = 'auto'; }
    const opt = { margin: 15, filename: `${sourceData.slug || 'document'}.pdf`, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };
    Swal.fire({ title: 'Đang tạo PDF...', didOpen: () => Swal.showLoading() });
    html2pdf().set(opt).from(element).save().then(() => Swal.close()).catch(() => Swal.fire('Lỗi', 'Không thể tạo PDF', 'error'));
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) currentPage.value = page;
};

// ==========================================
// API CALLS
// ==========================================
async function fetchNews() {
    isLoading.value = true;
    try {
        const response = await apiService.get(`admin/news`);
        news.value = response.data;
    } catch (e) {
        if (e.response?.status === 401) Swal.fire('Hết phiên', 'Vui lòng đăng nhập lại.', 'warning');
        else Swal.fire('Lỗi', 'Không thể tải danh sách tin tức.', 'error');
    } finally {
        isLoading.value = false;
    }
}

async function handleSave() {
    if (!requireLogin()) return;
    if (!formData.excerpt?.trim() && formData.content) {
        const plainText = formData.content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        formData.excerpt = plainText.slice(0, 160) + (plainText.length > 160 ? '...' : '');
    }
    if (!validateForm()) return;
    isLoading.value = true;
    const payload = new FormData();
    Object.keys(formData).forEach(key => { if (key !== 'author_id') payload.append(key, formData[key]); });
    if (selectedFile.value) payload.append('image', selectedFile.value);
    if (isEditMode.value) payload.append('_method', 'PUT');
    const url = isEditMode.value ? `admin/news/${formData.id}` : `admin/news`;
    try {
        await apiService.post(url, payload, { headers: { 'Content-Type': 'multipart/form-data' } });
        Swal.fire('Thành công', isEditMode.value ? 'Đã cập nhật!' : 'Đã đăng bài!', 'success');
        modalInstance.value?.hide();
        await fetchNews();
    } catch (error) {
        if (error.response?.status === 422) {
            const errs = error.response.data.errors;
            let html = '<ul>' + Object.values(errs).map(e => `<li class="text-danger text-start">${e[0]}</li>`).join('') + '</ul>';
            Swal.fire({ title: 'Dữ liệu lỗi', html: html, icon: 'warning' });
        } else {
            Swal.fire('Lỗi Server', error.response?.data?.message || error.message, 'error');
        }
    } finally { isLoading.value = false; }
}

async function handleToggleStatus(newsItem) {
    if (!requireLogin()) return;
    if (!hasRole(['admin'])) return Swal.fire('Quyền hạn', 'Bạn không có quyền duyệt bài.', 'warning');
    const newStatus = newsItem.status === 'published' ? 'draft' : 'published';
    const actionName = newStatus === 'published' ? 'XUẤT BẢN' : 'ẨN BÀI VIẾT';
    const result = await Swal.fire({ title: 'Thay đổi trạng thái?', text: `Bạn có muốn ${actionName} này?`, icon: 'question', showCancelButton: true, confirmButtonText: 'Đồng ý', cancelButtonText: 'Hủy' });
    if (result.isConfirmed) {
        try {
            await apiService.patch(`admin/news/${newsItem.id}`, { status: newStatus });
            newsItem.status = newStatus;
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Cập nhật thành công', timer: 1500, showConfirmButton: false });
        } catch (e) { Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error'); }
    }
}

async function handleDelete(newsItem) {
    if (!requireLogin()) return;
    const isAuthor = newsItem.author_name === currentUser.value.name;
    if (!hasRole(['admin']) && !isAuthor) return Swal.fire('Quyền hạn', 'Bạn không có quyền xóa bài này.', 'error');
    const result = await Swal.fire({ title: 'Xóa bài viết?', text: "Hành động này không thể hoàn tác!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Xóa ngay', cancelButtonText: 'Hủy' });
    if (result.isConfirmed) {
        try {
            await apiService.delete(`admin/news/${newsItem.id}`);
            Swal.fire('Đã xóa!', 'Bài viết đã bị xóa.', 'success');
            if (paginatedNews.value.length === 1 && currentPage.value > 1) currentPage.value--;
            fetchNews();
        } catch (e) { Swal.fire('Lỗi', 'Không thể xóa bài viết.', 'error'); }
    }
}

onMounted(async () => {
    await checkAuthState();
    if (!requireLogin()) { isLoading.value = false; return; }
    initializeModals();
    fetchNews();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-brand">Quản lý Tin tức</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active">Tin tức</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4 shadow-sm border-0">
                <!-- [NEW] CUSTOM TABS WITH COUNTS -->
                <div class="card-header border-bottom-0 pb-0 bg-white">
                    <ul class="nav nav-tabs card-header-tabs custom-tabs">
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: currentTab === 'pending' }"
                                href="#" @click.prevent="currentTab = 'pending'">
                                <i class="bi bi-hourglass-split me-1 text-warning"></i> Đợi duyệt
                                <span class="badge rounded-pill ms-2"
                                    :class="currentTab === 'pending' ? 'bg-warning text-dark' : 'bg-light text-secondary border'">{{
                                    statusCounts.pending }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center"
                                :class="{ active: currentTab === 'published' }" href="#"
                                @click.prevent="currentTab = 'published'">
                                <i class="bi bi-check-circle me-1 text-success"></i> Xuất bản
                                <span class="badge rounded-pill ms-2"
                                    :class="currentTab === 'published' ? 'bg-success' : 'bg-light text-secondary border'">{{
                                    statusCounts.published }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: currentTab === 'draft' }"
                                href="#" @click.prevent="currentTab = 'draft'">
                                <i class="bi bi-eye-slash me-1 text-secondary"></i> Đã ẩn
                                <span class="badge rounded-pill ms-2"
                                    :class="currentTab === 'draft' ? 'bg-secondary' : 'bg-light text-secondary border'">{{
                                    statusCounts.draft }}</span>
                            </a>
                        </li>
                        <li class="nav-item ms-auto">
                            <a class="nav-link text-muted small d-flex align-items-center"
                                :class="{ active: currentTab === 'all' }" href="#" @click.prevent="currentTab = 'all'">
                                <i class="bi bi-layers me-1"></i> Tất cả
                                <span class="badge rounded-pill bg-light text-secondary border ms-2">{{ statusCounts.all
                                    }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- [NEW] FILTER BAR BIGGER -->
                <div class="card-body bg-light border-bottom py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-4 col-12">
                            <!-- Removed input-group-sm for bigger size -->
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0"
                                    placeholder="Tìm kiếm theo tên..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <!-- Removed form-select-sm -->
                            <select class="form-select" v-model="sortOption">
                                <option value="newest">⏱️ Mới nhất</option>
                                <option value="oldest">⏳ Cũ nhất</option>
                                <option value="a-z">🔤 Tên (A-Z)</option>
                                <option value="z-a">🔤 Tên (Z-A)</option>
                            </select>
                        </div>
                        <div class="col-md-5 col-6 text-end">
                            <!-- Increased padding and remove btn-sm -->
                            <button class="btn btn-primary px-4 shadow-sm" @click="openCreateModal"><i
                                    class="bi bi-pen me-1"></i> Viết bài mới</button>
                        </div>
                    </div>
                </div>

                <!-- MAIN TABLE -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 custom-table">
                            <thead class="bg-light text-secondary">
                                <tr>
                                    <th class="ps-3" style="width: 50px;">ID</th>
                                    <th style="width: 100px;">Ảnh</th>
                                    <th>Tiêu đề</th>
                                    <th class="d-none d-md-table-cell" style="width: 150px;">Tác giả</th>
                                    <th style="width: 120px;">Trạng thái</th>
                                    <th class="d-none d-lg-table-cell" style="width: 130px;">Ngày tạo</th>
                                    <th style="width: 160px;" class="text-center pe-3">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="isLoading">
                                    <td colspan="7" class="text-center py-5">
                                        <div class="spinner-border text-primary"></div>
                                    </td>
                                </tr>
                                <tr v-else-if="paginatedNews.length === 0">
                                    <td colspan="7" class="text-center py-5 text-muted fst-italic">
                                        <img src="https://cdn-icons-png.flaticon.com/512/7486/7486754.png" width="60"
                                            class="mb-2 opacity-50"><br>
                                        Không tìm thấy bài viết nào trong mục này.
                                    </td>
                                </tr>
                                <tr v-for="item in paginatedNews" :key="item.id">
                                    <td class="ps-3 fw-bold text-muted">{{ item.id }}</td>
                                    <td>
                                        <div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm"
                                            style="width: 80px;">
                                            <img :src="getFullImage(item.image_url)" class="object-fit-cover"
                                                onerror="this.src='https://placehold.co/80x45?text=Img'">
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold d-block text-dark text-truncate" style="max-width: 280px;"
                                            :title="item.title">{{ item.title }}</span>
                                        <small class="text-muted d-block text-truncate" style="max-width: 280px;">{{
                                            item.excerpt || 'Chưa có mô tả ngắn...' }}</small>
                                    </td>
                                    <td class="d-none d-md-table-cell text-muted small">
                                        <i class="bi bi-person-circle me-1"></i>{{ getDisplayAuthor(item) }}
                                    </td>
                                    <td>
                                        <span
                                            :class="['badge rounded-pill fw-normal', getStatusInfo(item.status).class]">
                                            <i class="bi me-1" :class="getStatusInfo(item.status).icon"></i>{{
                                            getStatusInfo(item.status).text }}
                                        </span>
                                    </td>
                                    <td class="d-none d-lg-table-cell small text-muted">{{
                                        getFormattedDate(item.created_at) }}</td>
                                    <td class="text-center pe-3">
                                        <div class="d-flex justify-content-center align-items-center gap-1">
                                            <div class="form-check form-switch m-0 d-flex align-items-center"
                                                v-if="hasRole(['admin'])" title="Bật/Tắt xuất bản">
                                                <input class="form-check-input custom-switch" type="checkbox"
                                                    role="switch" :checked="item.status === 'published'"
                                                    @click.prevent="handleToggleStatus(item)">
                                            </div>
                                            <button class="btn btn-sm btn-light text-danger border" title="Xuất PDF"
                                                @click="exportToPDF(item)"><i
                                                    class="bi bi-file-earmark-pdf"></i></button>
                                            <button class="btn btn-sm btn-light text-secondary border" title="Xem"
                                                @click="openViewModal(item)"><i class="bi bi-eye"></i></button>
                                            <button class="btn btn-sm btn-light text-primary border" title="Sửa"
                                                @click="openEditModal(item)"><i class="bi bi-pencil"></i></button>
                                            <button class="btn btn-sm btn-light text-danger border" title="Xóa"
                                                v-if="hasRole(['admin']) || item.author_name === currentUser.name"
                                                @click="handleDelete(item)"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PAGINATION -->
                <div class="card-footer bg-white border-top-0 py-3" v-if="!isLoading && totalPages > 1">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Hiển thị {{ paginatedNews.length }} / {{ processedNews.length }} bài
                            viết</small>
                        <ul class="pagination pagination-sm m-0">
                            <li class="page-item" :class="{ disabled: currentPage === 1 }"><button
                                    class="page-link border-0" @click="goToPage(currentPage - 1)"><i
                                        class="bi bi-chevron-left"></i></button></li>
                            <li v-for="page in totalPages" :key="page" class="page-item"
                                :class="{ active: currentPage === page }"><button
                                    class="page-link border-0 rounded-circle mx-1" @click="goToPage(page)">{{ page
                                    }}</button></li>
                            <li class="page-item" :class="{ disabled: currentPage === totalPages }"><button
                                    class="page-link border-0" @click="goToPage(currentPage + 1)"><i
                                        class="bi bi-chevron-right"></i></button></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CREATE/EDIT MODAL -->
    <div class="modal fade" id="newsModal" ref="modalRef" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-brand"><i class="bi"
                            :class="isEditMode ? 'bi-pencil-square' : 'bi-plus-circle'"></i> {{ isEditMode ? 'Chỉnh sửa bài viết' : 'Viết bài mới' }}
                        </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light p-0">
                    <div class="container-fluid bg-white h-100 p-4">
                        <form @submit.prevent="handleSave" id="newsForm">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="mb-3"><label class="form-label required fw-bold text-brand">Tiêu đề bài
                                            viết</label><input type="text" class="form-control form-control-lg"
                                            :class="{ 'is-invalid': errors.title }" v-model="formData.title"
                                            placeholder="Nhập tiêu đề hấp dẫn...">
                                        <div class="invalid-feedback">{{ errors.title }}</div>
                                    </div>
                                    <div class="mb-3"><label class="form-label required fw-bold text-muted small">Slug
                                            (URL)</label><input type="text"
                                            class="form-control form-control-sm bg-light text-muted"
                                            :class="{ 'is-invalid': errors.slug }" v-model="formData.slug" readonly>
                                        <div class="invalid-feedback">{{ errors.slug }}</div>
                                    </div>
                                    <div class="mb-3"><label class="form-label fw-bold text-brand">Mô tả
                                            ngắn</label><textarea class="form-control" rows="3"
                                            v-model="formData.excerpt"
                                            placeholder="Tóm tắt nội dung chính..."></textarea>
                                        <div class="form-text small">Tự động lấy 160 ký tự đầu nếu để trống.</div>
                                    </div>
                                    <div class="mb-3"><label class="form-label required fw-bold mb-2 text-brand">Nội
                                            dung chi tiết</label>
                                        <div class="quill-wrapper" :class="{ 'is-invalid-border': errors.content }">
                                            <QuillEditor ref="quillInstance" v-model:content="formData.content"
                                                contentType="html" theme="snow" :toolbar="quillToolbar"
                                                style="min-height: 500px;" />
                                        </div>
                                        <div class="text-danger small mt-1" v-if="errors.content">{{ errors.content }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="card mb-3 border-0 shadow-sm">
                                        <div class="card-header fw-bold bg-white text-brand border-bottom">Thông tin
                                            xuất bản</div>
                                        <div class="card-body">
                                            <div class="mb-3" v-if="hasRole(['admin'])">
                                                <label class="form-label required">Trạng thái</label>
                                                <select class="form-select border-warning" v-model="formData.status">
                                                    <option value="pending">Đợi duyệt</option>
                                                    <option value="published">Xuất bản</option>
                                                    <option value="draft">Đã ẩn</option>
                                                </select>
                                            </div>
                                            <div class="mb-3" v-else><label class="form-label">Trạng thái hiện
                                                    tại</label>
                                                <div class="form-control bg-light"><span class="badge"
                                                        :class="getStatusInfo(formData.status).class">{{
                                                        getStatusInfo(formData.status).text }}</span></div>
                                            </div>
                                            <div class="mb-3"><label class="form-label required fw-bold">Bút danh tác
                                                    giả</label><input type="text" class="form-control"
                                                    :class="{ 'is-invalid': errors.author_name }"
                                                    v-model="formData.author_name" placeholder="VD: Ban Biên Tập">
                                                <div class="invalid-feedback">{{ errors.author_name }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-3 border-0 shadow-sm">
                                        <div class="card-header fw-bold bg-white text-brand border-bottom">Ảnh đại diện
                                        </div>
                                        <div class="card-body">
                                            <div class="text-center position-relative border rounded p-2 bg-light mb-2"
                                                style="min-height: 180px; display: flex; align-items: center; justify-content: center;">
                                                <img v-if="previewImage" :src="getFullImage(previewImage)"
                                                    class="img-fluid rounded" style="max-height: 200px;"><span v-else
                                                    class="text-muted"><i class="bi bi-image fs-1 d-block"></i>Chưa có
                                                    ảnh</span><button type="button"
                                                    class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 rounded-circle"
                                                    v-if="selectedFile" @click="resetImageSelect" title="Xóa ảnh"><i
                                                        class="bi bi-x"></i></button></div>
                                            <input type="file" class="form-control" id="imageInput" accept="image/*"
                                                @change="handleFileChange">
                                            <div class="form-text small">Hỗ trợ JPG, PNG, WEBP. Max 10MB.</div>
                                        </div>
                                    </div>
                                    <div class="d-grid"><button type="button" @click="exportToPDF(null)"
                                            class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> Xem
                                            trước PDF</button></div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top shadow-sm"><button type="button"
                        class="btn btn-danger px-4" data-bs-dismiss="modal">Hủy bỏ</button><button type="submit"
                        form="newsForm" class="btn btn-primary px-4" :disabled="isLoading"><i
                            class="bi bi-save me-1"></i> {{ isEditMode ? 'Lưu thay đổi' : 'Đăng bài viết' }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- VIEW MODAL -->
    <div class="modal fade" id="viewNewsModal" ref="viewModalRef" tabindex="-1">
        <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
            <div class="modal-content bg-light">
                <div class="modal-header border-bottom-0 bg-white shadow-sm sticky-top">
                    <div class="container-fluid d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="modal-title fw-bold text-brand mb-0 text-truncate" style="max-width: 400px;">{{
                                viewingNewsItem.title }}</h5><span
                                :class="['badge', getStatusInfo(viewingNewsItem.status).class]">{{
                                    getStatusInfo(viewingNewsItem.status).text }}</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm"
                                @click="handleCopyLink(viewingNewsItem.slug)"><i class="bi bi-link-45deg"></i> Copy
                                Link</button>
                            <button class="btn btn-outline-primary btn-sm" @click="handleShare(viewingNewsItem)"><i
                                    class="bi bi-share"></i> Chia sẻ</button>
                            <button class="btn btn-primary btn-sm ms-2" @click="openEditModal(viewingNewsItem)"><i
                                    class="bi bi-pencil"></i> Sửa bài</button>
                            <button type="button" class="btn-close ms-3" data-bs-dismiss="modal"></button>
                        </div>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <div class="article-container mx-auto bg-white p-4 p-md-5 my-4 shadow-sm rounded">
                        <div class="text-center mb-5">
                            <h1 class="display-5 fw-bold text-dark mb-3">{{ viewingNewsItem.title }}</h1>
                            <div class="d-flex justify-content-center align-items-center text-muted gap-3"><span><i
                                        class="bi bi-person-circle text-primary"></i> {{
                                    getDisplayAuthor(viewingNewsItem) }}</span><span>&bull;</span><span><i
                                        class="bi bi-calendar3"></i> {{ getFormattedDate(viewingNewsItem.created_at)
                                    }}</span></div>
                        </div>
                        <div class="mb-5 text-center" v-if="viewingNewsItem.image_url"><img
                                :src="getFullImage(viewingNewsItem.image_url)" class="article-image shadow-sm rounded"
                                alt="Ảnh bài viết"></div>
                        <div
                            class="excerpt-box p-4 bg-light border-start border-4 border-primary rounded mb-4 fst-italic text-secondary">
                            <i class="bi bi-quote fs-3 text-primary opacity-50 me-2"></i>{{ viewingNewsItem.excerpt }}
                        </div>
                        <div class="article-content ql-editor px-0" v-html="viewingNewsItem.content"></div>
                        <hr class="my-5">
                        <div class="text-center"><button class="btn btn-outline-secondary rounded-pill px-4"
                                @click="handleShare(viewingNewsItem)"><i class="bi bi-share-fill me-2"></i> Chia sẻ bài
                                viết này</button></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* CUSTOM TABS STYLE */
.custom-tabs .nav-link {
    color: #6c757d;
    border: none;
    font-weight: 500;
    padding: 10px 15px;
    border-bottom: 3px solid transparent;
}

.custom-tabs .nav-link:hover {
    color: #009981;
}

.custom-tabs .nav-link.active {
    color: #009981;
    background: transparent;
    border-bottom: 3px solid #009981;
}

/* THEME COLORS */
.text-brand {
    color: #009981 !important;
}

.btn-primary {
    background-color: #009981 !important;
    border-color: #009981 !important;
    color: white !important;
}

.btn-primary:hover,
.btn-primary:active {
    background-color: #007a67 !important;
    border-color: #007a67 !important;
}

.btn-outline-primary {
    color: #009981 !important;
    border-color: #009981 !important;
}

.btn-outline-primary:hover {
    background-color: #009981 !important;
    color: white !important;
}

.text-primary {
    color: #009981 !important;
}

.border-primary {
    border-color: #009981 !important;
}

/* [NEW] Cursor Pointer for Switch */
.custom-switch {
    cursor: pointer;
}

.custom-switch:checked {
    background-color: #009981;
    border-color: #009981;
}

/* COMPONENTS */
.form-label.required::after {
    content: " *";
    color: red;
}

.is-invalid-border {
    border: 1px solid #dc3545;
    border-radius: 4px;
}

:deep(.ql-toolbar) {
    background: #f8f9fa;
    border-radius: 4px 4px 0 0;
    border-bottom: 1px solid #dee2e6;
}

:deep(.ql-container) {
    border-radius: 0 0 4px 4px;
    font-size: 16px;
    background: white;
}

.modal-fullscreen .modal-body {
    overflow-y: auto;
}

.article-container {
    max-width: 900px;
    min-height: 100%;
}

.article-image {
    max-width: 800px;
    width: 100%;
    height: auto;
    object-fit: contain;
}

.excerpt-box {
    font-size: 1.1rem;
    line-height: 1.6;
}

.article-content {
    font-size: 1.125rem;
    line-height: 1.8;
    color: #2c3e50;
}

.page-item.active .page-link {
    background-color: #009981 !important;
    color: white !important;
}

.page-link {
    color: #666;
}

@media (max-width: 768px) {
    .article-container {
        padding: 1.5rem !important;
        margin: 0 !important;
        border-radius: 0 !important;
    }

    .display-5 {
        font-size: 2rem;
    }
}
</style>