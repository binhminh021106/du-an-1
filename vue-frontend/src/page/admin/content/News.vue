<template>
    <!-- HEADER -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Quản lý Tin tức</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active">Tin tức</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">
            <div class="card card-primary card-outline">
                <!-- Card Header -->
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <input type="text" class="form-control" style="max-width: 300px;"
                            placeholder="Tìm kiếm theo tiêu đề..." v-model="searchQuery">
                        <button type="button" class="btn btn-primary" @click="openCreateModal">
                            <i class="fas fa-plus"></i> Thêm mới
                        </button>
                    </div>
                </div>

                <!-- Card Body - Bảng -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="thead-light">
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
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Empty State -->
                                <tr v-else-if="filteredNews.length === 0">
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                        {{ searchQuery ? 'Không tìm thấy tin tức nào.' : 'Chưa có tin tức nào.' }}
                                    </td>
                                </tr>
                                <!-- Data Rows -->
                                <tr v-for="newsItem in paginatedNews" :key="newsItem.id">
                                    <td>{{ newsItem.id }}</td>
                                    <td>
                                        <img :src="newsItem.image_url || 'https://placehold.co/100x50/eeeeee/333333?text=No+Image'"
                                            alt="News Image"
                                            style="width: 100%; height: auto; max-width: 100px; border-radius: 5px;"
                                            onerror="this.src='https://placehold.co/100x50/eeeeee/333333?text=Error'">
                                    </td>
                                    <td class="font-weight-bold" style="white-space: normal; min-width: 250px;">{{
                                        newsItem.title }}</td>
                                    <td class="d-none d-md-table-cell">{{ getAuthorName(newsItem.author_id) }}</td>
                                    <td>
                                        <span
                                            :class="['badge', newsItem.status === 'published' ? 'badge-success' : (newsItem.status === 'draft' ? 'badge-warning' : 'badge-info')]">
                                            {{ getStatusText(newsItem.status) }}
                                        </span>
                                    </td>
                                    <td class="d-none d-lg-table-cell">{{ getFormattedDate(newsItem.created_at) }}</td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary" title="Xem chi tiết"
                                                @click="openViewModal(newsItem)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-primary" title="Chỉnh sửa"
                                                @click="openEditModal(newsItem)">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" title="Xóa"
                                                @click="handleDelete(newsItem)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Card Footer - Phân Trang -->
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
    </section>

    <!-- MODAL (Create/Edit) -->
    <div class="modal fade" id="newsModal" ref="modalRef" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
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
                            <!-- CỘT TRÁI -->
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="title" class="required">Tiêu đề</label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.title }"
                                        id="title" v-model="formData.title" placeholder="Nhập tiêu đề">
                                    <div class="invalid-feedback" v-if="errors.title">{{ errors.title }}</div>
                                </div>

                                <div class="form-group">
                                    <label for="slug" class="required">Đường dẫn (Slug)</label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.slug }"
                                        id="slug" v-model="formData.slug" placeholder="duong-dan-bai-viet">
                                    <div class="invalid-feedback" v-if="errors.slug">{{ errors.slug }}</div>
                                </div>

                                <div class="form-group">
                                    <label for="excerpt">Mô tả ngắn (Excerpt)</label>
                                    <textarea class="form-control" id="excerpt" rows="3" v-model="formData.excerpt"
                                        placeholder="Mô tả ngắn gọn về bài viết..."></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="contentEditor">Nội dung</label>
                                    <textarea id="contentEditor" name="contentEditor" rows="10"></textarea>
                                    <div class="invalid-feedback d-block" v-if="errors.content">{{ errors.content }}</div>
                                </div>

                            </div>

                            <!-- CỘT PHẢI -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status" class="required">Trạng thái</label>
                                    <select class="form-control" id="status" v-model="formData.status">
                                        <option value="published">Xuất bản</option>
                                        <option value="draft">Bản nháp</option>
                                        <option value="pending">Chờ duyệt</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="author_id" class="required">Tác giả</label>
                                    <select class="form-control" :class="{ 'is-invalid': errors.author_id }"
                                        id="author_id" v-model.number="formData.author_id">
                                        <option :value="null" disabled>-- Chọn tác giả --</option>
                                        <option v-for="author in authors" :key="author.id" :value="author.id">
                                            {{ author.name }}
                                        </option>
                                    </select>
                                    <div class="invalid-feedback" v-if="errors.author_id">{{ errors.author_id }}</div>
                                </div>

                                <div class="form-group">
                                    <label for="image_url">URL Hình ảnh đại diện</label>
                                    <input type="text" class="form-control" id="image_url" v-model="formData.image_url"
                                        placeholder="https://...">
                                </div>

                                <div class="form-group">
                                    <label>Xem trước hình ảnh</label>
                                    <img v-if="formData.image_url" :src="formData.image_url"
                                        class="img-fluid mt-2 rounded" alt="Preview"
                                        onerror="this.src='https://placehold.co/300x200/eeeeee/333333?text=Invalid+Image'">
                                    <div v-else
                                        class="img-thumbnail d-flex align-items-center justify-content-center bg-light"
                                        style="height: 150px; width: 100%;">
                                        <span class="text-muted">Chưa có hình ảnh</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="submit" form="newsForm" class="btn btn-primary" :disabled="isLoading">
                        <span v-if="isLoading" class="spinner-border spinner-border-sm mr-1" role="status"
                            aria-hidden="true"></span>
                        {{ isEditMode ? 'Lưu thay đổi' : 'Tạo tin tức' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL (Xem Chi Tiết) -->
    <div class="modal fade" id="viewNewsModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ viewingNewsItem.title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <span
                        :class="['badge', viewingNewsItem.status === 'published' ? 'badge-success' : (viewingNewsItem.status === 'draft' ? 'badge-warning' : 'badge-info')]">
                        {{ getStatusText(viewingNewsItem.status) }}
                    </span>
                    <span class="text-muted mx-2">|</span>
                    <span class="text-muted">Tác giả: <strong>{{ getAuthorName(viewingNewsItem.author_id)
                    }}</strong></span>
                    <span class="text-muted mx-2">|</span>
                    <span class="text-muted">Ngày tạo: {{ getFormattedDate(viewingNewsItem.created_at) }}</span>

                    <hr>

                    <img v-if="viewingNewsItem.image_url" :src="viewingNewsItem.image_url"
                        class="img-fluid rounded mb-3" alt="News Image">

                    <p class="font-italic text-muted">
                        <strong>Mô tả ngắn:</strong> {{ viewingNewsItem.excerpt ? viewingNewsItem.excerpt : 'Không có.'
                        }}
                    </p>

                    <hr>

                    <div classs="news-content-view" v-html="viewingNewsItem.content || '<p>Không có nội dung.</p>'">
                    </div>

                    <hr>
                    <p class="text-muted small"><strong>Slug:</strong> <code>{{ viewingNewsItem.slug }}</code></p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary"
                        @click="() => { viewModalInstance.value.hide(); openEditModal(viewingNewsItem); }">
                        <i class="fas fa-pen mr-2"></i> Chỉnh sửa
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CKEDITOR 4 ---
const ckeditorInstance = ref(null);
const editorConfig = {
    language: 'vi',
    toolbar: [
        { name: 'document', items: ['Source', '-', 'NewPage', 'Preview', '-', 'Templates'] },
        { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo'] },
        { name: 'editing', items: ['Find', 'Replace', '-', 'SelectAll'] },
        '/',
        { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'RemoveFormat'] },
        { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
        { name: 'links', items: ['Link', 'Unlink', 'Anchor'] },
        { name: 'insert', items: ['Image', 'Table', 'HorizontalRule', 'SpecialChar', 'PageBreak'] },
        '/',
        { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
        { name: 'colors', items: ['TextColor', 'BGColor'] },
        { name: 'tools', items: ['Maximize', 'ShowBlocks'] }
    ],
    removePlugins: 'liststyle,scayt,menubutton',
    disableNativeSpellChecker: false,
};


// --- STATE ---
const API_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:3000';
const isLoading = ref(true);
const isEditMode = ref(false);
const news = ref([]);
const authors = ref([]);
const modalInstance = ref(null);
const modalRef = ref(null);
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingNewsItem = ref({});
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

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

// --- HELPERS ---
function loadCKEditorScript(callback) {
    if (window.CKEDITOR) {
        callback();
        return;
    }
    const script = document.createElement('script');
    script.src = 'https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js';
    script.onload = () => {
        window.CKEDITOR.disableAutoInline = true;
        callback();
    };
    script.onerror = () => {
        console.error("Không thể tải CKEditor 4 script.");
        Swal.fire('Lỗi', 'Không thể tải trình soạn thảo. Vui lòng tải lại trang.', 'error');
    };
    document.body.appendChild(script);
}

// --- LIFECYCLE ---
onMounted(() => {
    loadCKEditorScript(() => {
        console.log('CKEditor 4 script đã được tải.');
    });
    initializeModals();
    fetchAuthors();
    fetchNews();
});

onBeforeUnmount(() => {
    if (ckeditorInstance.value) {
        ckeditorInstance.value.destroy();
        ckeditorInstance.value = null;
    }
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
    if (window.CKEDITOR && !ckeditorInstance.value) {
        try {
            ckeditorInstance.value = window.CKEDITOR.replace('contentEditor', editorConfig);
            ckeditorInstance.value.setData(formData.content || '');
            ckeditorInstance.value.on('change', () => {
                if (ckeditorInstance.value) {
                    formData.content = ckeditorInstance.value.getData();
                    if (errors.content) {
                        errors.content = '';
                    }
                }
            });
            ckeditorInstance.value.on('instanceReady', () => {
                updateEditorValidationState(errors.content);
            });
        } catch (error) {
            console.error("Lỗi khởi tạo CKEditor 4:", error);
            Swal.fire('Lỗi', 'Không thể khởi tạo trình soạn thảo.', 'error');
        }
    }
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

watch(() => errors.content, (newError) => {
    updateEditorValidationState(newError);
});

// --- HELPERS (FORMATTING & VALIDATION) ---
function updateEditorValidationState(errorMsg) {
    if (ckeditorInstance.value) {
        const editorContainer = ckeditorInstance.value.container;
        if (editorContainer) {
            editorContainer.$.style.borderColor = errorMsg ? '#dc3545' : '';
        }
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

const getStatusText = (status) => {
    switch (status) {
        case 'published': return 'Xuất bản';
        case 'draft': return 'Bản nháp';
        case 'pending': return 'Chờ duyệt';
        default: return 'Không xác định';
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

    if (!formData.title.trim()) {
        errors.title = 'Vui lòng nhập tiêu đề.';
        isValid = false;
    }
    if (!formData.slug.trim()) {
        errors.slug = 'Vui lòng nhập đường dẫn (slug).';
        isValid = false;
    }
    if (!formData.author_id) {
        errors.author_id = 'Vui lòng chọn tác giả.';
        isValid = false;
    }
    if (!formData.content.trim()) {
        errors.content = 'Vui lòng nhập nội dung.';
        isValid = false;
    }
    updateEditorValidationState(errors.content);
    return isValid;
}

// --- MODAL TRIGGERS ---
function openCreateModal() {
    resetForm();
    isEditMode.value = false;
    if (modalInstance.value) {
        modalInstance.value.show();
    }
}

function openEditModal(newsItem) {
    const itemCopy = JSON.parse(JSON.stringify(newsItem));
    Object.assign(formData, itemCopy);
    Object.assign(errors, { title: '', slug: '', author_id: '', content: '' });
    isEditMode.value = true;
    
    if (modalInstance.value) {
        modalInstance.value.show();
    }
}

function openViewModal(newsItem) {
    viewingNewsItem.value = newsItem;
    if (viewModalInstance.value) {
        viewModalInstance.value.show();
    }
}


// --- API METHODS (MOCK) ---
async function fetchAuthors() {
    try {
        const response = await axios.get(`${API_URL}/users`);
        authors.value = response.data.filter(u => u.role === 'admin' || u.role === 'editor').map(u => ({ id: u.id, name: u.name }));
        if (authors.value.length === 0) {
            authors.value = [
                { id: 1, name: 'Admin (Mock)' },
                { id: 2, name: 'Biên tập viên (Mock)' },
            ];
        }
    } catch (error) {
        console.error("Lỗi tải tác giả, dùng mock data:", error);
        authors.value = [
            { id: 1, name: 'Admin (Mock)' },
            { id: 2, name: 'Biên tập viên (Mock)' },
        ];
    }
}

async function fetchNews() {
    isLoading.value = true;
    try {
        const response = await axios.get(`${API_URL}/news?_sort=id&_order=desc`);
        news.value = response.data.map(item => ({
            ...item,
            created_at: item.created_at || new Date().toISOString()
        }));
    } catch (error) {
        console.error("Lỗi tải tin tức:", error);
        Swal.fire('Lỗi', 'Không thể tải danh sách tin tức. Vui lòng kiểm tra db.json.', 'error');
        if (news.value.length === 0) {
            news.value = [
                { id: 1, title: 'Bài viết tiêu chuẩn về TCPDF (Mock)', excerpt: 'Mô tả ngắn...', content: '<p>Nội dung <strong>TCPDF</strong>.</p>', image_url: 'https://placehold.co/600x400/3498db/ffffff?text=TCPDF+PHP', slug: 'bai-viet-tcpdf', author_id: 1, status: 'published', created_at: '2025-11-13T19:00:00Z' },
                { id: 2, title: 'Tin tức thị trường (Mock)', excerpt: 'Giá cả tăng...', content: '<p>Chi tiết...</p>', image_url: 'https://placehold.co/600x400/2ecc71/ffffff?text=Market+News', slug: 'tin-tuc-thi-truong', author_id: 2, status: 'draft', created_at: '2025-11-12T10:30:00Z' }
            ];
        }
    } finally {
        isLoading.value = false;
    }
}

async function handleSave() {
    if (!validateForm()) return;

    isLoading.value = true;
    let payload = JSON.parse(JSON.stringify(formData));

    if (!isEditMode.value) {
        delete payload.id;
        payload.created_at = new Date().toISOString();
        payload.updated_at = new Date().toISOString();
    } else {
        payload.updated_at = new Date().toISOString();
    }

    try {
        if (isEditMode.value) {
            await axios.put(`${API_URL}/news/${payload.id}`, payload);
            Swal.fire('Thành công', 'Đã cập nhật tin tức!', 'success');
        } else {
            await axios.post(`${API_URL}/news`, payload);
            Swal.fire('Thành công', 'Đã tạo tin tức mới!', 'success');
        }
        if (modalInstance.value) {
            modalInstance.value.hide();
        }
        fetchNews();
    } catch (apiError) {
        console.error("Lỗi lưu tin tức:", apiError);
        Swal.fire('Thất bại', 'Đã có lỗi xảy ra khi lưu.', 'error');
    } finally {
        isLoading.value = false;
    }
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
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await axios.delete(`${API_URL}/news/${newsItem.id}`);
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

<style scoped>
/* Thêm CSS cho label bắt buộc */
.required::after {
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
    border: 1px solid #eee;
    padding: 15px;
    border-radius: 5px;
    background: #fdfdfd;
}

.news-content-view :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 5px;
}
</style>