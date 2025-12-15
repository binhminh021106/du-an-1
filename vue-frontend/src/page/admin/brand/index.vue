<script setup>
import { ref, reactive, onMounted, computed, nextTick, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CONFIG ---
const rawApiUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';
const API_BASE_URL = rawApiUrl.replace(/\/api\/?$/, '');

// --- REGEX VALIDATION ---
// Chỉ cho phép chữ cái (có dấu), số và khoảng trắng
const validNameRegex = /^[a-zA-Z0-9\sàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸĐ]+$/u;

// --- STATE ---
const brands = ref([]);
const isLoading = ref(true);
const isSaving = ref(false);
const isEditMode = ref(false);
const searchQuery = ref('');

// Modal
const modalRef = ref(null);
const modalInstance = ref(null);

// Form Data
const formData = reactive({
    id: null,
    name: '',
    slug: '',
    description: '',
    status: 'active',
    order_number: 0
});
const logoFile = ref(null);
const logoPreview = ref(null);
const fileInputRef = ref(null);
// Thêm error field cho description
const errors = reactive({ name: '', slug: '', description: '' });

// --- COMPUTED ---
const filteredBrands = computed(() => {
    let result = brands.value;
    const query = searchQuery.value.toLowerCase().trim();
    if (query) {
        result = result.filter(b => b.name.toLowerCase().includes(query));
    }
    // Sắp xếp theo thứ tự order_number
    return result.sort((a, b) => (a.order_number || 0) - (b.order_number || 0));
});

// Tính toán độ dài mô tả để hiển thị
const descriptionLength = computed(() => formData.description ? formData.description.length : 0);

// --- ACTIONS ---
async function fetchBrands() {
    isLoading.value = true;
    try {
        const response = await apiService.get('/admin/brands');
        brands.value = response.data;
    } catch (e) {
        console.error("Lỗi tải brands:", e);
    } finally {
        isLoading.value = false;
    }
}

// Hàm xử lý đường dẫn ảnh nâng cao
function getImageUrl(path) {
    if (!path) return 'https://placehold.co/150x150?text=No+Logo';
    if (path.startsWith('blob:') || path.startsWith('http')) return path;

    let cleanPath = path.startsWith('/') ? path : '/' + path;
    if (!cleanPath.startsWith('/storage') && !cleanPath.startsWith('/uploads')) {
        cleanPath = '/storage' + cleanPath;
    }

    const baseUrl = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL;
    return `${baseUrl}${cleanPath}`;
}

function handleFileChange(event) {
    const file = event.target.files[0];
    if (file) {
        if (!file.type.startsWith('image/')) {
            Swal.fire('Lỗi', 'Vui lòng chọn file định dạng ảnh (jpg, png, webp...)', 'warning');
            event.target.value = null;
            return;
        }
        logoFile.value = file;
        logoPreview.value = URL.createObjectURL(file);
    }
}

function resetForm() {
    Object.assign(formData, { id: null, name: '', slug: '', description: '', status: 'active', order_number: 0 });
    logoFile.value = null;
    logoPreview.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
    // Reset toàn bộ errors
    errors.name = '';
    errors.slug = '';
    errors.description = '';
}

function openCreateModal() {
    resetForm();
    isEditMode.value = false;
    const maxOrder = brands.value.length > 0 ? Math.max(...brands.value.map(b => Number(b.order_number) || 0)) : 0;
    formData.order_number = maxOrder + 1;
    modalInstance.value?.show();
}

function openEditModal(brand) {
    resetForm();
    isEditMode.value = true;
    Object.assign(formData, brand);
    logoPreview.value = getImageUrl(brand.logo_url || brand.imageUrl); 
    modalInstance.value?.show();
}

// Hàm kiểm tra trùng tên
function checkDuplicateName(name, currentId = null) {
    const nameToCheck = name.toLowerCase().trim();
    return brands.value.some(brand => {
        // Nếu đang edit thì bỏ qua chính nó
        if (currentId && brand.id === currentId) return false;
        return brand.name.toLowerCase().trim() === nameToCheck;
    });
}

async function handleSave() {
    // 1. Reset lỗi cũ
    errors.name = '';
    errors.description = '';
    
    // 2. Validate Rỗng
    if (!formData.name.trim()) { 
        errors.name = 'Vui lòng nhập tên thương hiệu'; 
        return; 
    }

    // 3. Validate Ký tự đặc biệt
    if (!validNameRegex.test(formData.name)) {
        errors.name = 'Tên thương hiệu không được chứa ký tự đặc biệt';
        return;
    }

    // 4. Validate Trùng tên
    if (checkDuplicateName(formData.name, formData.id)) {
        errors.name = 'Tên thương hiệu đã tồn tại';
        return;
    }

    // 5. Validate Mô tả quá dài
    if (formData.description && formData.description.length > 255) {
        errors.description = `Mô tả quá dài (${formData.description.length}/255 ký tự)`;
        return;
    }
    
    isSaving.value = true;
    const payload = new FormData();
    payload.append('name', formData.name);
    // payload.append('slug', formData.slug); // Uncomment nếu muốn gửi slug từ client
    payload.append('description', formData.description || '');
    payload.append('status', formData.status);
    payload.append('order_number', formData.order_number);
    
    if (logoFile.value) {
        payload.append('logo', logoFile.value); 
    }

    try {
        if (isEditMode.value) {
            // [SỬA LỖI Ở ĐÂY]: Đổi 'POST' thành 'PUT'
            // Laravel quy định multipart form spoofing phải dùng _method=PUT hoặc PATCH
            payload.append('_method', 'PUT'); 
            
            await apiService.post(`/admin/brands/${formData.id}`, payload, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
        } else {
            await apiService.post('/admin/brands', payload, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
        }
        
        Swal.fire({
            icon: 'success',
            title: 'Thành công',
            text: `Đã ${isEditMode.value ? 'cập nhật' : 'thêm'} thương hiệu!`,
            timer: 1500,
            showConfirmButton: false
        });
        
        modalInstance.value?.hide();
        fetchBrands();
    } catch (e) {
        console.error(e);
        const errorMsg = e.response?.data?.message || 'Có lỗi xảy ra khi lưu dữ liệu';
        Swal.fire('Lỗi', errorMsg, 'error');
    } finally {
        isSaving.value = false;
    }
}

async function handleDelete(brand) {
    // Nếu nút bị disable rồi thì thôi, nhưng chặn thêm ở đây cho chắc chắn
    if (brand.products_count > 0) {
        Swal.fire('Không thể xóa', `Thương hiệu này đang có ${brand.products_count} sản phẩm sử dụng.`, 'warning');
        return;
    }

    const result = await Swal.fire({
        title: 'Xóa thương hiệu?',
        text: `Bạn có chắc muốn xóa "${brand.name}"? Hành động này không thể hoàn tác.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`/admin/brands/${brand.id}`);
            fetchBrands();
            Swal.fire('Đã xóa', 'Thương hiệu đã được xóa thành công.', 'success');
        } catch (e) {
            Swal.fire('Lỗi', 'Không thể xóa (có thể đang có sản phẩm thuộc thương hiệu này).', 'error');
        }
    }
}

// Auto slug watcher
watch(() => formData.name, (val) => {
    // Chỉ cập nhật slug khi KHÔNG có lỗi ký tự đặc biệt để tránh slug bị lỗi
    if (!isEditMode.value && val && validNameRegex.test(val)) {
        formData.slug = val.toLowerCase()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
            .replace(/đ/g, "d").replace(/[^a-z0-9\s-]/g, "")
            .trim().replace(/\s+/g, "-");
    }
});

onMounted(() => {
    fetchBrands();
    nextTick(() => { if (modalRef.value) modalInstance.value = new Modal(modalRef.value); });
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0 text-brand">Quản lý Thương hiệu</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active">Thương hiệu</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-body bg-light border-bottom py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm kiếm thương hiệu..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-7 text-end">
                            <button class="btn btn-primary px-4 shadow-sm" @click="openCreateModal">
                                <i class="bi bi-plus-lg me-1"></i> Thêm Thương hiệu
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 custom-table">
                            <thead class="bg-light text-secondary">
                                <tr>
                                    <th class="ps-3 text-center" style="width: 60px;">#</th>
                                    <th style="width: 120px;">Logo</th>
                                    <th>Tên thương hiệu</th>
                                    <th class="d-none d-md-table-cell">Mô tả</th>
                                    <th class="text-center">Trạng thái</th>
                                    <th class="text-center pe-3">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="isLoading"><td colspan="6" class="text-center py-5"><div class="spinner-border text-primary"></div></td></tr>
                                <tr v-else-if="filteredBrands.length === 0"><td colspan="6" class="text-center py-5 text-muted fst-italic">Chưa có dữ liệu thương hiệu.</td></tr>
                                <tr v-else v-for="brand in filteredBrands" :key="brand.id">
                                    <td class="text-center text-muted">{{ brand.order_number }}</td>
                                    <td>
                                        <div class="p-1 border rounded bg-white d-inline-block shadow-sm">
                                            <img :src="getImageUrl(brand.logo_url || brand.imageUrl)" 
                                                 style="width: 80px; height: 50px; object-fit: contain;"
                                                 onerror="this.src='https://placehold.co/80x50?text=Error'">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ brand.name }}</div>
                                        <div class="small text-muted">{{ brand.slug }}</div>
                                        <!-- Hiển thị số lượng sản phẩm để debug/thông tin -->
                                        <div v-if="brand.products_count > 0" class="badge bg-info bg-opacity-10 text-info border border-info rounded-pill mt-1">
                                            <i class="bi bi-box-seam me-1"></i>{{ brand.products_count }} sản phẩm
                                        </div>
                                    </td>
                                    <td class="d-none d-md-table-cell small text-muted text-truncate" style="max-width: 250px;">
                                        {{ brand.description || 'Chưa có mô tả' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge" :class="brand.status === 'active' ? 'bg-success' : 'bg-secondary'">
                                            {{ brand.status === 'active' ? 'Hiển thị' : 'Đã ẩn' }}
                                        </span>
                                    </td>
                                    <td class="text-center pe-3">
                                        <button class="btn btn-sm btn-light text-primary border me-1" @click="openEditModal(brand)" title="Sửa"><i class="bi bi-pencil"></i></button>
                                        
                                        <!-- NÚT XOÁ ĐƯỢC CHỈNH SỬA TẠI ĐÂY -->
                                        <button 
                                            class="btn btn-sm btn-light border" 
                                            :class="brand.products_count > 0 ? 'text-muted cursor-not-allowed opacity-50' : 'text-danger'"
                                            :disabled="brand.products_count > 0"
                                            @click="handleDelete(brand)" 
                                            :title="brand.products_count > 0 ? `Không thể xóa: Đang có ${brand.products_count} sản phẩm` : 'Xóa'"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CREATE/EDIT -->
    <div class="modal fade" id="brandModal" ref="modalRef" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-brand">
                        {{ isEditMode ? 'Cập nhật Thương hiệu' : 'Thêm mới Thương hiệu' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form @submit.prevent="handleSave" id="brandForm">
                        <!-- Logo Upload -->
                        <div class="mb-4 text-center">
                            <label class="form-label fw-bold d-block text-start mb-2">Logo thương hiệu</label>
                            <div class="position-relative d-inline-block mb-3 bg-light border rounded p-2" style="width: 180px; height: 120px;">
                                <img :src="logoPreview || 'https://placehold.co/180x120?text=Preview+Logo'" 
                                     class="img-fluid w-100 h-100 object-fit-contain">
                                <label class="position-absolute bottom-0 end-0 m-1 btn btn-sm btn-primary rounded-circle shadow-sm" 
                                       style="width: 32px; height: 32px; padding: 4px; cursor: pointer;">
                                    <i class="bi bi-camera-fill"></i>
                                    <input type="file" class="d-none" ref="fileInputRef" accept="image/*" @change="handleFileChange">
                                </label>
                            </div>
                            <div class="text-muted small fst-italic">Hỗ trợ JPG, PNG, WEBP. Tối đa 2MB.</div>
                        </div>

                        <!-- Info Inputs -->
                        <div class="mb-3">
                            <label class="form-label required fw-bold">Tên thương hiệu</label>
                            <input type="text" class="form-control" v-model="formData.name" :class="{ 'is-invalid': errors.name }" placeholder="VD: Samsung, Apple...">
                            <!-- Hiển thị lỗi Name -->
                            <div class="invalid-feedback">{{ errors.name }}</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small">Slug (URL thân thiện)</label>
                            <input type="text" class="form-control form-control-sm bg-light text-muted" v-model="formData.slug" readonly placeholder="tu-dong-tao">
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label class="form-label fw-bold">Mô tả</label>
                                <!-- Bộ đếm ký tự -->
                                <span class="small" :class="descriptionLength > 255 ? 'text-danger fw-bold' : 'text-muted'">
                                    {{ descriptionLength }}/255
                                </span>
                            </div>
                            <textarea class="form-control" 
                                      rows="2" 
                                      v-model="formData.description" 
                                      :class="{ 'is-invalid': errors.description || descriptionLength > 255 }"
                                      placeholder="Mô tả ngắn gọn..."></textarea>
                            <!-- Hiển thị lỗi Description -->
                            <div class="invalid-feedback">{{ errors.description }}</div>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Thứ tự hiển thị</label>
                                <input type="number" class="form-control" v-model="formData.order_number" min="0">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label fw-bold">Trạng thái</label>
                                <select class="form-select" v-model="formData.status">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Ẩn tạm thời</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-top-0 bg-light">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Hủy bỏ</button>
                    <!-- Disable nút Lưu nếu mô tả quá dài -->
                    <button type="submit" form="brandForm" class="btn btn-primary px-4" :disabled="isSaving || descriptionLength > 255">
                        <span v-if="isSaving" class="spinner-border spinner-border-sm me-1"></span>
                        {{ isSaving ? 'Đang lưu...' : (isEditMode ? 'Cập nhật' : 'Lưu lại') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.text-brand { color: #009981 !important; }
.btn-primary { background-color: #009981 !important; border-color: #009981 !important; color: white !important; }
.btn-primary:hover { background-color: #007a67 !important; border-color: #007a67 !important; }
.form-label.required::after { content: " *"; color: red; }
.custom-table th { font-weight: 600; font-size: 0.9rem; text-transform: uppercase; }
.cursor-not-allowed { cursor: not-allowed !important; }
</style>