<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CKEDITOR 4 ---
const ckeditorInstance = ref(null);
const editorConfig = {
    language: 'vi',
    toolbar: [
        { name: 'document', items: ['Source', '-', 'Preview'] },
        { name: 'clipboard', items: ['Cut', 'Copy', 'PasteText', '-', 'Undo', 'Redo'] },
        { name: 'editing', items: ['Find', 'Replace', '-', 'SelectAll'] },
        '/',
        { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', '-', 'RemoveFormat'] },
        { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
        { name: 'links', items: ['Link', 'Unlink'] },
        { name: 'insert', items: ['Image', 'Table', 'HorizontalRule', 'SpecialChar'] },
        '/',
        { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
        { name: 'colors', items: ['TextColor', 'BGColor'] },
        { name: 'tools', items: ['Maximize'] }
    ],
    removePlugins: 'liststyle,scayt,menubutton',
    disableNativeSpellChecker: false,
};

// --- STATE (Giống categories.vue) ---
const isLoading = ref(true);
const isEditMode = ref(false);
const news = ref([]);
const authors = ref([]);

// State cho Modal Thêm/Sửa
const modalInstance = ref(null);
const modalRef = ref(null);

// State cho Modal Xem
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingNewsItem = ref({});

// State cho Tìm kiếm và Phân trang
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10); // Nhất quán 10 mục/trang

// Form data & Validation
const formData = reactive({
    id: null,
    title: '',
    excerpt: '',
    content: '',
    image_url: '',
    slug: '',
    author_id: null,
    status: 'draft',
    created_at: null,
    updated_at: null
});
const errors = reactive({
    title: '',
    slug: '',
    author_id: '',
    content: ''
});

// --- HELPERS (Load CKEditor) ---
function loadCKEditorScript(callback) {
    if (window.CKEDITOR) {
        callback();
        return;
    }
    const script = document.createElement('script');
    script.src = '/ckeditor/ckeditor.js';
    script.onload = () => {
        window.CKEDITOR.disableAutoInline = true;
        window.CKEDITOR.basePath = '/ckeditor/';
        callback();
    };
    script.onerror = () => console.error("Không thể tải CKEditor script.");
    document.body.appendChild(script);
}

// --- LIFECYCLE ---
onMounted(() => {
    initializeModals();
    fetchAuthors();
    fetchNews();
});

onBeforeUnmount(() => {
    destroyCKEditor();
    if (modalRef.value) {
        modalRef.value.removeEventListener('shown.bs.modal', initializeCKEditor);
        modalRef.value.removeEventListener('hidden.bs.modal', destroyCKEditor);
    }
});

// --- MODALS & CKEDITOR ---
function initializeModals() {
    nextTick(() => {
        if (modalRef.value) {
            modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
            modalRef.value.addEventListener('shown.bs.modal', initializeCKEditor);
            modalRef.value.addEventListener('hidden.bs.modal', destroyCKEditor);
        }
        if (viewModalRef.value) {
            viewModalInstance.value = new Modal(viewModalRef.value);
        }
    });
}

function initializeCKEditor() {
    loadCKEditorScript(() => {
        if (window.CKEDITOR && !ckeditorInstance.value) {
            try {
                ckeditorInstance.value = window.CKEDITOR.replace('contentEditor', editorConfig);
                ckeditorInstance.value.setData(formData.content || '');
                ckeditorInstance.value.on('instanceReady', () => {
                    updateEditorValidationState(errors.content);
                });
            } catch (error) {
                console.error("Lỗi khởi tạo CKEditor 4:", error);
            }
        }
    });
}

function destroyCKEditor() {
    if (ckeditorInstance.value) {
        ckeditorInstance.value.destroy();
        ckeditorInstance.value = null;
    }
}

// --- COMPUTED ---
const filteredNews = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) {
        return news.value;
    }
    return news.value.filter(item =>
        item.title.toLowerCase().includes(query)
    );
});

const totalPages = computed(() => {
    return Math.ceil(filteredNews.value.length / itemsPerPage.value);
});

const paginatedNews = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredNews.value.slice(start, end);
});

// --- WATCHERS ---
watch(searchQuery, () => {
    currentPage.value = 1;
});

watch(() => formData.title, (newTitle) => {
    if (!isEditMode.value) {
        formData.slug = slugify(newTitle);
    }
});

// --- HELPER FUNCTIONS ---
function updateEditorValidationState(errorMsg) {
    if (ckeditorInstance.value?.container) {
        ckeditorInstance.value.container.$.style.borderColor = errorMsg ? '#dc3545' : '';
    }
}

const slugify = (text) => {
    if (!text) return '';
    return text.toString().toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-')
        .replace(/^-+/, '')
        .replace(/-+$/, '');
};

function getFormattedDate(dateString) {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
    return new Date(dateString).toLocaleDateString('vi-VN', options);
}

const getAuthorName = (authorId) => {
    const author = authors.value.find(a => a.id === authorId);
    return author ? author.name : 'Không rõ';
};

// Trả về Text và Class màu nhất quán BS5+
const getStatusInfo = (status) => {
    switch (status) {
        case 'published': return { text: 'Xuất bản', class: 'text-bg-success' };
        case 'draft': return { text: 'Bản nháp', class: 'text-bg-warning' };
        case 'pending': return { text: 'Chờ duyệt', class: 'text-bg-info' };
        default: return { text: 'Không rõ', class: 'text-bg-secondary' };
    }
};

function resetForm() {
    Object.assign(formData, {
        id: null, title: '', excerpt: '', content: '', image_url: '',
        slug: '', author_id: null, status: 'draft',
        created_at: null, updated_at: null
    });
    Object.assign(errors, { title: '', slug: '', author_id: '', content: '' });
    updateEditorValidationState(null);
}

function validateForm() {
    if (ckeditorInstance.value) {
        formData.content = ckeditorInstance.value.getData();
    }
    Object.assign(errors, { title: '', slug: '', author_id: '', content: '' });
    let isValid = true;

    if (!formData.title.trim()) { errors.title = 'Vui lòng nhập tiêu đề.'; isValid = false; }
    if (!formData.slug.trim()) { errors.slug = 'Vui lòng nhập đường dẫn (slug).'; isValid = false; }
    if (!formData.author_id) { errors.author_id = 'Vui lòng chọn tác giả.'; isValid = false; }
    if (!formData.content.trim()) { errors.content = 'Vui lòng nhập nội dung.'; isValid = false; }
    
    updateEditorValidationState(errors.content);
    return isValid;
}

// --- MODAL TRIGGERS ---
function openCreateModal() {
    resetForm();
    isEditMode.value = false;
    modalInstance.value?.show();
}

function openEditModal(newsItem) {
    const itemCopy = JSON.parse(JSON.stringify(newsItem));
    Object.assign(formData, itemCopy);
    Object.assign(errors, { title: '', slug: '', author_id: '', content: '' });
    isEditMode.value = true;
    modalInstance.value?.show();
}

function openViewModal(newsItem) {
    viewingNewsItem.value = newsItem;
    viewModalInstance.value?.show();
}

// --- API METHODS ---
async function fetchAuthors() {
    try {
        const response = await apiService.get(`/users`);
        authors.value = response.data.map(u => ({ id: u.id, name: u.name }));
    } catch (error) {
        console.error("Lỗi tải tác giả:", error);
        authors.value = [{ id: 1, name: 'Admin (Mock)' }]; // Fallback
    }
}

async function fetchNews() {
    isLoading.value = true;
    try {
        // Sắp xếp theo ID giảm dần (mới nhất trước)
        const response = await apiService.get(`/news?_sort=id&_order=desc`);
        news.value = response.data.map(item => ({
            ...item,
            created_at: item.created_at || new Date().toISOString()
        }));
    } catch (error) {
        console.error("Lỗi tải tin tức:", error);
        Swal.fire('Lỗi', 'Không thể tải danh sách tin tức.', 'error');
    } finally {
        isLoading.value = false;
    }
}

async function handleSave() {
    if (!validateForm()) return;

    isLoading.value = true;
    let payload = JSON.parse(JSON.stringify(formData));
    payload.updated_at = new Date().toISOString();

    try {
        if (isEditMode.value) {
            await apiService.put(`/news/${payload.id}`, payload);
            Swal.fire('Thành công', 'Đã cập nhật tin tức!', 'success');
        } else {
            delete payload.id;
            payload.created_at = new Date().toISOString();
            await apiService.post(`/news`, payload);
            Swal.fire('Thành công', 'Đã tạo tin tức mới!', 'success');
        }
        modalInstance.value?.hide();
        fetchNews();
    } catch (apiError) {
        console.error("Lỗi lưu tin tức:", apiError);
        Swal.fire('Thất bại', 'Đã có lỗi xảy ra khi lưu.', 'error');
    } finally {
        isLoading.value = false;
    }
}

// *** HÀM TOGGLE "THẬT" - Giống 100% logic categories.vue ***
async function handleToggleStatus(newsItem) {
    const originalStatus = newsItem.status;
    const newStatus = originalStatus === 'published' ? 'draft' : 'published';
    
    const actionText = newStatus === 'published' ? 'XUẤT BẢN' : 'CHUYỂN VỀ BẢN NHÁP';
    const confirmText = `Bạn có chắc chắn muốn ${actionText} bài viết "${newsItem.title}" không?`;

    const result = await Swal.fire({
        title: 'Thay đổi trạng thái',
        text: confirmText,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        // Cập nhật trạng thái mới và ngày update
        const payload = { 
            status: newStatus, 
            updated_at: new Date().toISOString() 
        };

        try {
            // Dùng PATCH chỉ để update 2 trường này (giống categories.vue)
            await apiService.patch(`/news/${newsItem.id}`, payload);
            
            // Cập nhật UI
            newsItem.status = newStatus; 
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Đã cập nhật trạng thái!',
                showConfirmButton: false,
                timer: 2000
            });
        } catch (error) {
            console.error("Lỗi cập nhật trạng thái:", error);
            Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
            // Không cần fetchNews() vì optimistic UI đã bị hủy bỏ (chưa đổi)
            // Hoặc có thể fetch lại nếu logic của bạn cần
        }
    }
    // Nếu hủy, không làm gì cả, @click.prevent sẽ giữ nguyên checkbox
}

async function handleDelete(newsItem) {
    const result = await Swal.fire({
        title: 'Bạn chắc chắn chứ?',
        text: `Sẽ xóa vĩnh viễn "${newsItem.title}"!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
        // Thêm icon nhất quán
        customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`/news/${newsItem.id}`);
            Swal.fire('Đã xóa!', 'Tin tức đã bị xóa.', 'success');
            if (paginatedNews.value.length === 1 && currentPage.value > 1) {
                currentPage.value--;
            }
            fetchNews();
        } catch (error) {
            console.error("Lỗi xóa:", error);
            Swal.fire('Lỗi', 'Không thể xóa tin tức này.', 'error');
        }
    }
}

// --- PAGINATION ---
function goToPage(page) {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
}
</script>

<template>
    <!-- HEADER (Giống categories.vue) -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Quản lý Tin tức</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active" aria-current="page">Tin tức</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT (Giống categories.vue) -->
    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4">
                <!-- Card Header (Giống categories.vue) -->
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6 col-12 mb-2 mb-md-0">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0"
                                    placeholder="Tìm kiếm theo tiêu đề..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-6 col-12 text-md-end">
                            <button type="button" class="btn btn-primary" @click="openCreateModal">
                                <i class="bi bi-plus-lg"></i> Thêm mới Tin tức
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Card Body - Bảng Dữ Liệu -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th style="width: 100px;">Hình ảnh</th>
                                    <th>Tiêu đề</th>
                                    <th class="d-none d-md-table-cell" style="width: 150px;">Tác giả</th>
                                    <th style="width: 120px;">Trạng thái</th>
                                    <th class="d-none d-lg-table-cell" style="width: 130px;">Ngày tạo</th>
                                    <th style="width: 180px;" class="text-center">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Loading State -->
                                <tr v-if="isLoading">
                                    <td colspan="7" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Empty State -->
                                <tr v-else-if="filteredNews.length === 0">
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        {{ searchQuery ? 'Không tìm thấy tin tức nào.' : 'Chưa có tin tức nào.' }}
                                    </td>
                                </tr>
                                <!-- Data Rows -->
                                <tr v-for="newsItem in paginatedNews" :key="newsItem.id">
                                    <td>{{ newsItem.id }}</td>
                                    <td>
                                        <img :src="newsItem.image_url || 'https://placehold.co/100x50/eeeeee/333333?text=No+Img'"
                                            alt="News Image"
                                            style="width: 100%; height: auto; max-width: 100px; border-radius: 5px;"
                                            onerror="this.src='https://placehold.co/100x50/eeeeee/333333?text=Error'">
                                    </td>
                                    <td class="fw-bold" style="white-space: normal; min-width: 250px;">
                                        {{ newsItem.title }}
                                    </td>
                                    <td class="d-none d-md-table-cell">{{ getAuthorName(newsItem.author_id) }}</td>
                                    <td>
                                        <!-- Badge Trạng thái (Giống categories.vue BS5+) -->
                                        <span :class="['badge', getStatusInfo(newsItem.status).class]">
                                            {{ getStatusInfo(newsItem.status).text }}
                                        </span>
                                    </td>
                                    <td class="d-none d-lg-table-cell">{{ getFormattedDate(newsItem.created_at) }}</td>
                                    <td class="text-center">
                                        <!-- d-flex (Giống categories.vue) -->
                                        <div class="d-flex justify-content-center align-items-center">
                                            
                                            <!-- Toggle Switch (Giống categories.vue) -->
                                            <div class="form-check form-switch d-inline-block align-middle me-3" title="Kích hoạt/Vô hiệu hóa">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                    style="width: 2.5em; height: 1.25em; cursor: pointer;"
                                                    :id="'statusSwitch-' + newsItem.id"
                                                    :checked="newsItem.status === 'published'"
                                                    @click.prevent="handleToggleStatus(newsItem)">
                                            </div>

                                            <!-- Nhóm nút (Giống categories.vue) -->
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-secondary" title="Xem chi tiết" @click="openViewModal(newsItem)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditModal(newsItem)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(newsItem)">
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

                <!-- Card Footer - Phân Trang (Giống categories.vue) -->
                <div class="card-footer clearfix" v-if="!isLoading && totalPages > 1">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} đến
                            {{ Math.min(currentPage * itemsPerPage, filteredNews.length) }}
                            trong tổng số {{ filteredNews.length }} tin tức
                        </small>
                        <ul class="pagination pagination-sm m-0">
                            <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                <button class="page-link" @click="goToPage(currentPage - 1)">&laquo;</button>
                            </li>
                            <li v-for="page in totalPages" :key="page" class="page-item"
                                :class="{ active: currentPage === page }">
                                <button class="page-link" @click="goToPage(page)">{{ page }}</button>
                            </li>
                            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                                <button class="page-link" @click="goToPage(currentPage + 1)">&raquo;</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL (Create/Edit) -->
    <div class="modal fade" id="newsModal" ref="modalRef" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <!-- Dùng modal-xl vì tin tức cần nhiều không gian cho CKEditor -->
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ isEditMode ? 'Chỉnh sửa Tin tức' : 'Tạo Tin tức mới' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="handleSave" id="newsForm">
                        <div class="row">
                            <!-- CỘT TRÁI: NỘI DUNG CHÍNH -->
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label required">Tiêu đề</label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.title }"
                                        id="title" v-model="formData.title" placeholder="Nhập tiêu đề">
                                    <div class="invalid-feedback" v-if="errors.title">{{ errors.title }}</div>
                                </div>

                                <div class="mb-3">
                                    <label for="slug" class="form-label required">Đường dẫn (Slug)</label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.slug }"
                                        id="slug" v-model="formData.slug" placeholder="duong-dan-bai-viet">
                                    <div class="invalid-feedback" v-if="errors.slug">{{ errors.slug }}</div>
                                </div>

                                <div class="mb-3">
                                    <label for="excerpt" class="form-label">Mô tả ngắn (Excerpt)</label>
                                    <textarea class="form-control" id="excerpt" rows="3" v-model="formData.excerpt"
                                        placeholder="Mô tả ngắn gọn về bài viết..."></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="contentEditor" class="form-label required">Nội dung</label>
                                    <textarea class="form-control" name="contentEditor" id="contentEditor"></textarea>
                                    <div class="text-danger small mt-1" v-if="errors.content">{{ errors.content }}</div>
                                </div>
                            </div>

                            <!-- CỘT PHẢI: THÔNG TIN PHỤ -->
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label required">Trạng thái</label>
                                    <select class="form-select" id="status" v-model="formData.status">
                                        <option value="published">Xuất bản</option>
                                        <option value="draft">Bản nháp</option>
                                        <option value="pending">Chờ duyệt</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="author_id" class="form-label required">Tác giả</label>
                                    <select class="form-select" :class="{ 'is-invalid': errors.author_id }"
                                        id="author_id" v-model.number="formData.author_id">
                                        <option :value="null" disabled>-- Chọn tác giả --</option>
                                        <option v-for="author in authors" :key="author.id" :value="author.id">
                                            {{ author.name }}
                                        </option>
                                    </select>
                                    <div class="invalid-feedback" v-if="errors.author_id">{{ errors.author_id }}</div>
                                </div>

                                <div class="mb-3">
                                    <label for="image_url" class="form-label">URL Hình ảnh đại diện</label>
                                    <input type="text" class="form-control" id="image_url" v-model="formData.image_url"
                                        placeholder="https://...">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Xem trước hình ảnh</label>
                                    <img v-if="formData.image_url" :src="formData.image_url"
                                        class="img-fluid mt-2 rounded" alt="Preview"
                                        onerror="this.src='https://placehold.co/300x200/eeeeee/333333?text=Invalid+Image'">
                                    <div v-else
                                        class="img-thumbnail d-flex align-items-center justify-content-center bg-light"
                                        style="height: 150px; width: 100%;">
                                        <i class="bi bi-image text-muted fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!-- Footer (Giống categories.vue) -->
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" form="newsForm" class="btn btn-primary" :disabled="isLoading">
                        <span v-if="isLoading" class="spinner-border spinner-border-sm me-1" role="status"
                            aria-hidden="true"></span>
                        {{ isEditMode ? 'Lưu thay đổi' : 'Tạo tin tức' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL (Xem Chi Tiết) - Style giống categories.vue -->
    <div class="modal fade" id="viewNewsModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                 <div class="modal-body p-4 position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
                        aria-label="Close"></button>

                    <!-- Status Badge -->
                    <div class="position-absolute top-0 start-0 m-3">
                        <span :class="['badge', getStatusInfo(viewingNewsItem.status).class]">
                            {{ getStatusInfo(viewingNewsItem.status).text }}
                        </span>
                    </div>

                    <!-- Header -->
                    <div class="text-center mb-4 mt-4">
                         <h3 class="mt-3 mb-1">{{ viewingNewsItem.title }}</h3>
                         <p class="text-muted mb-0">ID: {{ viewingNewsItem.id }}</p>
                    </div>

                    <!-- Image -->
                    <img v-if="viewingNewsItem.image_url" :src="viewingNewsItem.image_url"
                        class="img-fluid rounded mb-3" alt="News Image">
                    
                    <!-- Details List -->
                    <div class="list-group list-group-flush mb-3">
                         <div class="list-group-item px-0">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="bi bi-person-circle me-3 text-primary"></i>Tác giả</h6>
                                <span class="text-muted">{{ getAuthorName(viewingNewsItem.author_id) }}</span>
                            </div>
                        </div>
                         <div class="list-group-item px-0">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><i class="bi bi-calendar-event me-3 text-muted"></i>Ngày tạo</h6>
                                <span class="text-muted">{{ getFormattedDate(viewingNewsItem.created_at) }}</span>
                            </div>
                        </div>
                        <div class="list-group-item px-0">
                            <h6 class="mb-2"><i class="bi bi-link-45deg me-3 text-muted"></i>Đường dẫn (Slug)</h6>
                            <code class="mb-1 text-dark small">{{ viewingNewsItem.slug }}</code>
                        </div>
                         <div class="list-group-item px-0">
                            <h6 class="mb-2"><i class="bi bi-card-text me-3 text-muted"></i>Mô tả ngắn</h6>
                            <p class="mb-1 text-muted small fst-italic">{{ viewingNewsItem.excerpt || 'Không có mô tả ngắn.' }}</p>
                        </div>
                    </div>

                    <!-- Content -->
                    <h5 class="mb-3">Nội dung chi tiết</h5>
                    <div class="news-content-view p-3 rounded border bg-light" v-html="viewingNewsItem.content || '<p>Không có nội dung.</p>'">
                    </div>

                 </div>
                 <!-- Footer (Giống categories.vue) -->
                <div class="modal-footer bg-light justify-content-center">
                    <button type="button" class="btn btn-primary px-4"
                        @click="() => { viewModalInstance.hide(); openEditModal(viewingNewsItem); }">
                        <i class="bi bi-pencil me-2"></i> Chỉnh sửa tin tức
                    </button>
                </div>
            </div>
        </div>
    </div>

</template>

<style scoped>
/* Thêm CSS cho label bắt buộc (Giống categories.vue) */
.form-label.required::after {
    content: " *";
    color: red;
}

/* Đảm bảo bảng hiển thị đẹp trên mobile */
.table-responsive {
    overflow-x: auto;
}

/* Tùy chỉnh nội dung xem trước */
.news-content-view {
    max-height: 400px;
    overflow-y: auto;
}

.news-content-view :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 5px;
}
</style>