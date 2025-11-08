<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---
const API_URL = import.meta.env.VITE_API_BASE_URL;

const slides = ref([]);     // Danh sách slide trên trang hiện tại
const isLoading = ref(true);

// State cho phân trang
const pagination = reactive({
    currentPage: 1,
    itemsPerPage: 5, // Hiển thị 5 slide mỗi trang
    totalItems: 0,
    totalPages: 1
});

// State cho Modal
const modalInstance = ref(null);
const modalRef = ref(null);
const isEditMode = ref(false);

// Dữ liệu cho form
const formData = reactive({
    id: null,
    title: '',
    description: '',
    imageUrl: '',
    linkUrl: '',
    status: 'published',
    order: 0
});

// Lỗi validation
const errors = reactive({
    title: '',
    imageUrl: '',
    order: ''
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
    fetchSlides(1); // Tải trang đầu tiên
    // Khởi tạo Modal
    if (modalRef.value) {
        modalInstance.value = new Modal(modalRef.value);
    }
});

// --- CÁC HÀM TẢI DỮ LIỆU ---

/**
 * Tải danh sách slide (Có phân trang)
 * @param {number} page - Trang cần tải
 */
async function fetchSlides(page = 1) {
    isLoading.value = true;
    if (page < 1 || (page > pagination.totalPages && pagination.totalItems > 0)) {
        isLoading.value = false;
        return;
    }

    pagination.currentPage = page;

    try {
        // Sắp xếp theo 'order' (thứ tự) tăng dần
        const response = await axios.get(
            `${API_URL}/slides?_page=${pagination.currentPage}&_limit=${pagination.itemsPerPage}&_sort=order&_order=asc`
        );

        slides.value = response.data;

        pagination.totalItems = parseInt(response.headers['x-total-count'] || 0);
        pagination.totalPages = Math.ceil(pagination.totalItems / pagination.itemsPerPage);

    } catch (error) {
        console.error("Lỗi khi tải slide:", error);
        Swal.fire('Lỗi', 'Không thể tải danh sách slide.', 'error');
    } finally {
        isLoading.value = false;
    }
}

// --- CÁC HÀM HELPER ---

function getStatusBadge(status) {
    if (status === 'published') return 'text-bg-success';
    return 'text-bg-secondary';
}

// --- CÁC HÀM CRUD ---

function resetForm() {
    formData.id = null;
    formData.title = '';
    formData.description = '';
    formData.imageUrl = '';
    formData.linkUrl = '';
    formData.status = 'published';
    formData.order = 0;

    Object.keys(errors).forEach(key => errors[key] = '');
}

function openCreateModal() {
    resetForm();
    isEditMode.value = false;
    modalInstance.value.show();
}

function openEditModal(slide) {
    resetForm();
    isEditMode.value = true;

    // Điền dữ liệu của slide vào form
    formData.id = slide.id;
    formData.title = slide.title;
    formData.description = slide.description;
    formData.imageUrl = slide.imageUrl;
    formData.linkUrl = slide.linkUrl;
    formData.status = slide.status;
    formData.order = slide.order;

    modalInstance.value.show();
}

function validateForm() {
    Object.keys(errors).forEach(key => errors[key] = '');
    let isValid = true;

    if (!formData.title.trim()) {
        errors.title = 'Vui lòng nhập tiêu đề slide.';
        isValid = false;
    }
    if (!formData.imageUrl.trim()) {
        errors.imageUrl = 'Vui lòng nhập URL hình ảnh.';
        isValid = false;
    } else {
        try {
            new URL(formData.imageUrl);
        } catch (_) {
            errors.imageUrl = 'URL hình ảnh không hợp lệ.';
            isValid = false;
        }
    }
    if (formData.order === null || formData.order < 0) {
        errors.order = 'Thứ tự phải là một số không âm.';
        isValid = false;
    }

    return isValid;
}

async function handleSave() {
    if (!validateForm()) {
        return;
    }

    isLoading.value = true;

    // Payload là dữ liệu JSON thuần
    const payload = {
        id: formData.id,
        title: formData.title,
        description: formData.description,
        imageUrl: formData.imageUrl,
        linkUrl: formData.linkUrl,
        status: formData.status,
        order: parseInt(formData.order) || 0
    };

    try {
        if (isEditMode.value) {
            // --- CHẾ ĐỘ CẬP NHẬT (UPDATE) ---
            await axios.put(`${API_URL}/slides/${formData.id}`, payload);
            Swal.fire('Thành công', 'Đã cập nhật slide!', 'success');
        } else {
            // --- CHẾ ĐỘ TẠO MỚI (CREATE) ---
            delete payload.id; // Xóa ID khi tạo mới
            await axios.post(`${API_URL}/slides`, payload);
            Swal.fire('Thành công', 'Đã tạo slide mới!', 'success');
        }

        modalInstance.value.hide();
        fetchSlides(pagination.currentPage);
    } catch (error) {
        console.error("Lỗi khi lưu:", error);
        Swal.fire('Thất bại', 'Đã có lỗi xảy ra. Vui lòng thử lại.', 'error');
    } finally {
        isLoading.value = false;
    }
}

/**
 * Xử lý xóa slide
 * @param {object} slide - Slide cần xóa
 */
async function handleDelete(slide) {
    const result = await Swal.fire({
        title: 'Bạn có chắc chắn?',
        text: `Bạn sẽ xóa vĩnh viễn slide "${slide.title}"!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Đồng ý xóa!',
        cancelButtonText: 'Hủy bỏ'
    });

    if (result.isConfirmed) {
        try {
            await axios.delete(`${API_URL}/slides/${slide.id}`);
            Swal.fire(
                'Đã xóa!',
                'Slide đã được xóa.',
                'success'
            );

            if (slides.value.length === 1 && pagination.currentPage > 1) {
                fetchSlides(pagination.currentPage - 1);
            } else {
                fetchSlides(pagination.currentPage);
            }

        } catch (error) {
            console.error("Lỗi khi xóa slide:", error);
            Swal.fire('Lỗi', 'Không thể xóa slide này.', 'error');
        }
    }
}
</script>

<template>
    <!-- 1. Header của trang -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Quản lý Slide</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            Slide
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Nội dung chính của trang -->
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <!-- Card chứa bảng -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Danh sách Slide</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-primary" @click="openCreateModal">
                                    <i class="bi bi-plus-lg"></i> Thêm mới
                                </button>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <!-- Hiển thị loading -->
                            <div v-if="isLoading && slides.length === 0" class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>

                            <!-- Hiển thị bảng dữ liệu -->
                            <table v-else class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 60px">Thứ tự</th>
                                        <th style="width: 120px">Ảnh</th>
                                        <th>Tiêu đề</th>
                                        <th>Link liên kết</th>
                                        <th style="width: 120px">Trạng thái</th>
                                        <th style="width: 150px">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="slides.length === 0">
                                        <td colspan="6" class="text-center">Không có slide nào</td>
                                    </tr>
                                    <tr v-for="slide in slides" :key="slide.id">
                                        <td>
                                            <span class="badge text-bg-dark" style="font-size: 0.9rem;">{{ slide.order
                                                }}</span>
                                        </td>
                                        <td>
                                            <img :src="slide.imageUrl || '../../../components/img/default-150x150.png'"
                                                alt="Ảnh slide" class="img-thumbnail" width="100">
                                        </td>
                                        <td>
                                            <strong>{{ slide.title }}</strong>
                                            <p class="text-muted small mb-0">{{ slide.description }}</p>
                                        </td>
                                        <td>
                                            <a :href="slide.linkUrl" target="_blank" v-if="slide.linkUrl">{{
                                                slide.linkUrl }}</a>
                                            <span v-else class="text-muted">N/A</span>
                                        </td>
                                        <td>
                                            <span class="badge" :class="getStatusBadge(slide.status)">
                                                {{ slide.status === 'published' ? 'Hiển thị' : 'Nháp' }}
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-warning btn-sm me-2" @click="openEditModal(slide)">
                                                <i class="bi bi-pencil"></i> Sửa
                                            </button>
                                            <button class="btn btn-danger btn-sm" @click="handleDelete(slide)">
                                                <i class="bi bi-trash"></i> Xóa
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->

                        <!-- 3. PHÂN TRANG (PAGINATION) -->
                        <div class="card-footer clearfix" v-if="!isLoading && pagination.totalPages > 1">
                            <ul class="pagination pagination-sm m-0 float-end">
                                <li class="page-item" :class="{ disabled: pagination.currentPage === 1 }">
                                    <a class="page-link" href="#"
                                        @click.prevent="fetchSlides(pagination.currentPage - 1)">&laquo;</a>
                                </li>
                                <li v-for="page in pagination.totalPages" :key="page" class="page-item"
                                    :class="{ active: pagination.currentPage === page }">
                                    <a class="page-link" href="#" @click.prevent="fetchSlides(page)">{{ page }}</a>
                                </li>
                                <li class="page-item"
                                    :class="{ disabled: pagination.currentPage === pagination.totalPages }">
                                    <a class="page-link" href="#"
                                        @click.prevent="fetchSlides(pagination.currentPage + 1)">&raquo;</a>
                                </li>
                            </ul>
                        </div>

                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Modal Thêm/Sửa Slide -->
    <div class="modal fade" id="slideModal" ref="modalRef" tabindex="-1" aria-labelledby="slideModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="slideModalLabel">
                        {{ isEditMode ? 'Chỉnh sửa Slide' : 'Tạo Slide mới' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="handleSave">

                        <div class="row">
                            <!-- Cột thông tin -->
                            <div class="col-md-7">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Tiêu đề <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.title }"
                                        id="title" v-model="formData.title">
                                    <div class="invalid-feedback" v-if="errors.title">{{ errors.title }}</div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Mô tả ngắn</label>
                                    <textarea class="form-control" id="description" rows="3"
                                        v-model="formData.description"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="linkUrl" class="form-label">Link liên kết</label>
                                    <input type="text" class="form-control" id="linkUrl" v-model="formData.linkUrl"
                                        placeholder="ví dụ: /san-pham/ao-thun">
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="order" class="form-label">Thứ tự <span
                                                    class="text-danger">*</span></label>
                                            <input type="number" class="form-control"
                                                :class="{ 'is-invalid': errors.order }" id="order"
                                                v-model.number="formData.order" min="0">
                                            <div class="invalid-feedback" v-if="errors.order">{{ errors.order }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Trạng thái</label>
                                            <select class="form-select" id="status" v-model="formData.status">
                                                <option value="published">Hiển thị (Published)</option>
                                                <option value="draft">Nháp (Draft)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cột Ảnh -->
                            <div class="col-md-5">
                                <div class="mb-3">
                                    <label for="imageUrl" class="form-label">URL Hình ảnh <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control" :class="{ 'is-invalid': errors.imageUrl }"
                                        id="imageUrl" v-model="formData.imageUrl" placeholder="https://...">
                                    <div class="invalid-feedback" v-if="errors.imageUrl">{{ errors.imageUrl }}</div>
                                </div>

                                <label class="form-label">Xem trước:</label>
                                <div class="image-preview-container">
                                    <img v-if="formData.imageUrl" :src="formData.imageUrl" class="img-thumbnail"
                                        alt="Preview" @error="(e) => e.target.src = '../../../components/img/default-150x150.png'">
                                    <img v-else src="../../../components/img/default-150x150.png" class="img-thumbnail"
                                        alt="Chưa có ảnh">
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                    <button type="button" class="btn btn-primary" @click="handleSave" :disabled="isLoading">
                        <span v-if="isLoading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        Lưu lại
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.table td .btn {
    margin-top: 2px;
    margin-bottom: 2px;
    font-size: 0.8rem;
}

.card-body.p-0 .table {
    margin-bottom: 0;
}

.image-preview-container .img-thumbnail {
    width: 100%;
    object-fit: cover;
}
</style>