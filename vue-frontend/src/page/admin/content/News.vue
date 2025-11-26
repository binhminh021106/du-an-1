<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CONFIG URL BACKEND ---
// Đổi thành domain thật khi deploy (ví dụ: https://api.domain.com)
const BACKEND_URL = 'http://127.0.0.1:8000';

// --- CKEDITOR 4 CONFIG ---
const ckeditorInstance = ref(null);
const editorConfig = {
    language: 'vi',
    height: 400,
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
    // Tắt các plugin gây lỗi console
    removePlugins: 'liststyle,scayt,menubutton,exportpdf,cloudservices,easyimage',
    disableNativeSpellChecker: false,
    baseFloatZIndex: 100000 
};

// --- STATE ---
const isLoading = ref(true);
const isEditMode = ref(false);
const news = ref([]);
const authors = ref([]);

// Upload Image State
const selectedFile = ref(null);
const previewImage = ref(null);

// Modals
const modalInstance = ref(null);
const modalRef = ref(null);
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingNewsItem = ref({});

// Search & Pagination
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

// Form Data
const formData = reactive({
    id: null,
    title: '',
    excerpt: '',
    content: '',
    slug: '',
    author_id: null,
    status: 'draft',
});

const errors = reactive({
    title: '',
    slug: '',
    author_id: '',
    content: ''
});

// --- HELPER: CKEditor Loader ---
function loadCKEditorScript(callback) {
    if (window.CKEDITOR) {
        callback();
        return;
    }
    const script = document.createElement('script');
    script.src = '/ckeditor/ckeditor.js'; 
    script.onload = () => {
        window.CKEDITOR.disableAutoInline = true;
        callback();
    };
    script.onerror = () => console.error("CRITICAL: Không thể tải CKEditor script.");
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

// --- MODAL & EDITOR LOGIC ---
function initializeModals() {
    nextTick(() => {
        if (modalRef.value) {
            modalInstance.value = new Modal(modalRef.value, { backdrop: 'static', keyboard: false });
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
                if (window.CKEDITOR.instances['contentEditor']) {
                    window.CKEDITOR.instances['contentEditor'].destroy(true);
                }
                
                ckeditorInstance.value = window.CKEDITOR.replace('contentEditor', editorConfig);
                ckeditorInstance.value.setData(formData.content || '');
                
                ckeditorInstance.value.on('change', () => {
                      formData.content = ckeditorInstance.value.getData();
                      if(formData.content.trim()) updateEditorValidationState(null);
                });
                
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
        try {
            ckeditorInstance.value.destroy();
        } catch(e) {}
        ckeditorInstance.value = null;
    }
}

// --- COMPUTED ---
const filteredNews = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    if (!query) return news.value;
    return news.value.filter(item => item.title.toLowerCase().includes(query));
});

const totalPages = computed(() => Math.ceil(filteredNews.value.length / itemsPerPage.value));

const paginatedNews = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return filteredNews.value.slice(start, start + itemsPerPage.value);
});

// --- WATCHERS ---
watch(searchQuery, () => currentPage.value = 1);

watch(() => formData.title, (newTitle) => {
    if (!isEditMode.value && newTitle) {
        formData.slug = slugify(newTitle);
    }
});

// --- HELPERS (Logic & Formatter) ---

// ===> HÀM QUAN TRỌNG: Bọc link ảnh để hiển thị đúng <===
const getFullImage = (path) => {
    if (!path) return 'https://placehold.co/100x100?text=No+Img';
    // Nếu là blob (ảnh preview local) hoặc link http (ảnh mạng) thì giữ nguyên
    if (path.startsWith('blob:') || path.startsWith('http')) return path;
    // Nếu là path từ Laravel lưu (/storage/...) thì nối thêm domain
    return `${BACKEND_URL}${path}`;
};

function updateEditorValidationState(errorMsg) {
    if (ckeditorInstance.value?.container) {
        ckeditorInstance.value.container.$.style.borderColor = errorMsg ? '#dc3545' : '#ced4da';
    }
}

const slugify = (text) => {
    if (!text) return '';
    return text.toString().toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, '-').replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-').replace(/^-+/, '').replace(/-+$/, '');
};

function getFormattedDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit', hour:'2-digit', minute:'2-digit' });
}

const getAuthorName = (authorId) => {
    const author = authors.value.find(a => a.id === authorId);
    return author ? author.name : 'Không rõ';
};

const getStatusInfo = (status) => {
    switch (status) {
        case 'published': return { text: 'Xuất bản', class: 'text-bg-success' };
        case 'draft': return { text: 'Bản nháp', class: 'text-bg-warning' };
        case 'pending': return { text: 'Chờ duyệt', class: 'text-bg-info' };
        default: return { text: 'Không rõ', class: 'text-bg-secondary' };
    }
};


// --- FILE UPLOAD HANDLER ---
const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        if (!file.type.match('image.*')) {
            Swal.fire('Lỗi', 'Vui lòng chọn file hình ảnh', 'error');
            return;
        }
        if (file.size > 2 * 1024 * 1024) { // 2MB
             Swal.fire('Lỗi', 'Dung lượng ảnh tối đa 2MB', 'error');
             return;
        }
        selectedFile.value = file;
        previewImage.value = URL.createObjectURL(file);
    }
};

const resetImageSelect = () => {
    selectedFile.value = null;
    previewImage.value = null;
    const fileInput = document.getElementById('imageInput');
    if(fileInput) fileInput.value = '';
};

function resetForm() {
    Object.assign(formData, {
        id: null, title: '', excerpt: '', content: '',
        slug: '', author_id: null, status: 'draft'
    });
    // Reset file
    resetImageSelect();
    
    Object.assign(errors, { title: '', slug: '', author_id: '', content: '' });
    updateEditorValidationState(null);
}

function validateForm() {
    if (ckeditorInstance.value) {
        formData.content = ckeditorInstance.value.getData();
    }
    Object.assign(errors, { title: '', slug: '', author_id: '', content: '' });
    let isValid = true;

    if (!formData.title.trim()) { errors.title = 'Tiêu đề là bắt buộc.'; isValid = false; }
    if (!formData.slug.trim()) { errors.slug = 'Slug là bắt buộc.'; isValid = false; }
    if (!formData.author_id) { errors.author_id = 'Vui lòng chọn tác giả.'; isValid = false; }
    if (!formData.content.trim()) { errors.content = 'Nội dung không được để trống.'; isValid = false; }

    updateEditorValidationState(errors.content);
    return isValid;
}


// --- ACTIONS ---
function openCreateModal() {
    resetForm();
    isEditMode.value = false;
    modalInstance.value?.show();
}

function openEditModal(newsItem) {
    const itemCopy = JSON.parse(JSON.stringify(newsItem));
    
    resetForm();
    
    Object.assign(formData, {
        id: itemCopy.id,
        title: itemCopy.title,
        excerpt: itemCopy.excerpt || '',
        content: itemCopy.content || '',
        slug: itemCopy.slug,
        author_id: itemCopy.author_id,
        status: itemCopy.status
    });
    
    // Xử lý preview ảnh từ server
    previewImage.value = itemCopy.image_url || null;
    selectedFile.value = null;
    
    isEditMode.value = true;
    
    if (viewModalRef.value && viewModalRef.value.classList.contains('show')) {
        viewModalInstance.value?.hide();
    }
    
    modalInstance.value?.show();
}

function openViewModal(newsItem) {
    viewingNewsItem.value = newsItem;
    viewModalInstance.value?.show();
}

// --- API ---
async function fetchAuthors() {
    try {
        const response = await apiService.get(`admin/users`);
        authors.value = response.data.map(u => ({ id: u.id, name: u.fullName || u.name || 'User' }));
    } catch (error) {
        console.error("Error loading authors:", error);
    }
}

async function fetchNews() {
    isLoading.value = true;
    try {
        const response = await apiService.get(`admin/news`);
        news.value = response.data; 
    } catch (error) {
        console.error("Error loading news:", error);
        Swal.fire('Lỗi', 'Không thể tải danh sách tin tức.', 'error');
    } finally {
        isLoading.value = false;
    }
}

// --- HÀM SAVE CHÍNH THỨC (Đã clean duplicate) ---
async function handleSave() {
    if (!validateForm()) return;
    isLoading.value = true;

    console.log("File đang chọn:", selectedFile.value); 

    const payload = new FormData();
    payload.append('title', formData.title);
    payload.append('slug', formData.slug);
    payload.append('excerpt', formData.excerpt || '');
    payload.append('content', formData.content);
    payload.append('author_id', formData.author_id);
    payload.append('status', formData.status);

    // Append File
    if (selectedFile.value) {
        payload.append('image', selectedFile.value);
    }

    // Config Header Multipart
    const config = {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    };

    try {
        if (isEditMode.value) {
            payload.append('_method', 'PUT'); 
            await apiService.post(`admin/news/${formData.id}`, payload, config);
            Swal.fire('Thành công', 'Đã cập nhật tin tức!', 'success');
        } else {
            await apiService.post(`admin/news`, payload, config);
            Swal.fire('Thành công', 'Đã tạo tin tức mới!', 'success');
        }
        
        modalInstance.value?.hide();
        await fetchNews(); 
    } catch (error) {
        console.error("Lỗi chi tiết:", error);
        
        if (error.response && error.response.status === 422) {
            const errors = error.response.data.errors;
            let errorHtml = '<ul style="text-align: left;">';
            for (const key in errors) {
                errorHtml += `<li class="text-danger">${errors[key][0]}</li>`;
            }
            errorHtml += '</ul>';
            Swal.fire({ title: 'Dữ liệu lỗi', html: errorHtml, icon: 'warning' });
        } else {
            Swal.fire('Lỗi Server', error.response?.data?.message || error.message, 'error');
        }
    } finally {
        isLoading.value = false;
    }
}

async function handleToggleStatus(newsItem) {
    const newStatus = newsItem.status === 'published' ? 'draft' : 'published';
    const actionText = newStatus === 'published' ? 'XUẤT BẢN' : 'GỠ BÀI';

    const result = await Swal.fire({
        title: 'Xác nhận thay đổi',
        text: `Bạn có muốn ${actionText} bài viết này?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.patch(`admin/news/${newsItem.id}`, { status: newStatus });
            newsItem.status = newStatus;
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: 'Đã cập nhật trạng thái!', showConfirmButton: false, timer: 1500
            });
        } catch (error) {
            Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
        }
    }
}

async function handleDelete(newsItem) {
    const result = await Swal.fire({
        title: 'Xóa vĩnh viễn?',
        text: `Hành động này không thể hoàn tác với "${newsItem.title}"`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`admin/news/${newsItem.id}`);
            Swal.fire('Đã xóa!', 'Tin tức đã bị xóa.', 'success');
            if (paginatedNews.value.length === 1 && currentPage.value > 1) {
                currentPage.value--;
            }
            fetchNews();
        } catch (error) {
            Swal.fire('Lỗi', 'Không thể xóa tin tức này.', 'error');
        }
    }
}

function goToPage(page) {
    if (page >= 1 && page <= totalPages.value) currentPage.value = page;
}
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Quản lý Tin tức</h3>
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
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6 col-12 mb-2 mb-md-0">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0"
                                    placeholder="Tìm kiếm tin tức..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-6 col-12 text-md-end">
                            <button type="button" class="btn btn-primary" @click="openCreateModal">
                                <i class="bi bi-plus-lg"></i> Thêm mới
                            </button>
                        </div>
                    </div>
                </div>

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
                                <tr v-if="isLoading">
                                    <td colspan="7" class="text-center py-4">
                                        <div class="spinner-border text-primary"></div>
                                    </td>
                                </tr>
                                <tr v-else-if="filteredNews.length === 0">
                                    <td colspan="7" class="text-center py-4 text-muted">Không có dữ liệu.</td>
                                </tr>
                                <tr v-for="item in paginatedNews" :key="item.id">
                                    <td>{{ item.id }}</td>
                                    <td>
                                        <img :src="getFullImage(item.image_url)" class="img-thumbnail"
                                            style="height: 50px; width: 80px; object-fit: cover;"
                                            onerror="this.src='https://placehold.co/100x50?text=No+Img'">
                                    </td>
                                    <td class="fw-bold">{{ item.title }}</td>
                                    <td class="d-none d-md-table-cell">{{ getAuthorName(item.author_id) }}</td>
                                    <td><span :class="['badge', getStatusInfo(item.status).class]">{{
                                            getStatusInfo(item.status).text }}</span></td>
                                    <td class="d-none d-lg-table-cell small">{{ getFormattedDate(item.created_at) }}
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center align-items-center">
                                            <div class="form-check form-switch me-2">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                    :checked="item.status === 'published'"
                                                    @click.prevent="handleToggleStatus(item)">
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-secondary"
                                                    @click="openViewModal(item)"><i class="bi bi-eye"></i></button>
                                                <button class="btn btn-outline-primary" @click="openEditModal(item)"><i
                                                        class="bi bi-pencil"></i></button>
                                                <button class="btn btn-outline-danger" @click="handleDelete(item)"><i
                                                        class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer clearfix" v-if="!isLoading && totalPages > 1">
                    <ul class="pagination pagination-sm m-0 justify-content-end">
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

    <div class="modal fade" id="newsModal" ref="modalRef" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ isEditMode ? 'Chỉnh sửa' : 'Thêm mới' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="handleSave" id="newsForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label required">Tiêu đề</label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.title }"
                                        v-model="formData.title">
                                    <div class="invalid-feedback">{{ errors.title }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Slug</label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.slug }"
                                        v-model="formData.slug">
                                    <div class="invalid-feedback">{{ errors.slug }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mô tả ngắn</label>
                                    <textarea class="form-control" rows="3" v-model="formData.excerpt"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Nội dung</label>
                                    <textarea id="contentEditor" name="contentEditor"></textarea>
                                    <div class="text-danger small mt-1" v-if="errors.content">{{ errors.content }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label required">Trạng thái</label>
                                    <select class="form-select" v-model="formData.status">
                                        <option value="draft">Bản nháp</option>
                                        <option value="published">Xuất bản</option>
                                        <option value="pending">Chờ duyệt</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Tác giả</label>
                                    <select class="form-select" :class="{ 'is-invalid': errors.author_id }"
                                        v-model.number="formData.author_id">
                                        <option :value="null" disabled>-- Chọn tác giả --</option>
                                        <option v-for="author in authors" :key="author.id" :value="author.id">{{
                                            author.name }}</option>
                                    </select>
                                    <div class="invalid-feedback">{{ errors.author_id }}</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Hình ảnh đại diện</label>

                                    <input type="file" class="form-control" id="imageInput" accept="image/*"
                                        @change="handleFileChange">
                                    <div class="form-text">Định dạng: jpg, png, webp. Tối đa 2MB.</div>

                                    <div class="mt-3 text-center position-relative" v-if="previewImage"
                                        style="width: fit-content; margin: 0 auto;">

                                        <img :src="getFullImage(previewImage)" class="img-fluid rounded border shadow-sm"
                                            style="max-height: 200px; object-fit: cover;">

                                        <button type="button"
                                            class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1"
                                            v-if="selectedFile" @click="resetImageSelect">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" form="newsForm" class="btn btn-primary" :disabled="isLoading">
                        {{ isEditMode ? 'Lưu thay đổi' : 'Tạo mới' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewNewsModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-body p-4">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3"
                        data-bs-dismiss="modal"></button>
                    <h3 class="mt-2">{{ viewingNewsItem.title }}</h3>
                    <div class="mb-3 text-muted">
                        <small class="me-3"><i class="bi bi-person"></i> {{ getAuthorName(viewingNewsItem.author_id)
                            }}</small>
                        <small><i class="bi bi-clock"></i> {{ getFormattedDate(viewingNewsItem.created_at) }}</small>
                    </div>
                    
                    <img v-if="viewingNewsItem.image_url" :src="getFullImage(viewingNewsItem.image_url)"
                        class="img-fluid rounded mb-3 w-100" style="max-height: 300px; object-fit: cover;">
                        
                    <div class="fw-bold mb-3 fst-italic">{{ viewingNewsItem.excerpt }}</div>
                    <hr>
                    <div v-html="viewingNewsItem.content" class="news-content"></div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button class="btn btn-primary" @click="openEditModal(viewingNewsItem)">
                        <i class="bi bi-pencil"></i> Chỉnh sửa ngay
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.form-label.required::after {
    content: " *";
    color: red;
}

.news-content :deep(img) {
    max-width: 100%;
    height: auto;
}

/* FIX Z-INDEX cho CKEditor Modal khi nằm trong Bootstrap Modal */
:global(.cke_dialog) {
    z-index: 10055 !important;
}

:global(.cke_panel) {
    z-index: 10060 !important;
}
</style>