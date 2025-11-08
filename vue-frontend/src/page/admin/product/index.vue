<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---
const API_URL = import.meta.env.VITE_API_BASE_URL;
const products = ref([]);
const categories = ref([]);
const isLoading = ref(true);
const modalInstance = ref(null);
const modalRef = ref(null);
const isEditMode = ref(false);

// Dữ liệu cho form sản phẩm
const formData = reactive({
  id: null,
  name: '',
  description: '',
  category_id: null,
  variants: reactive([]),

  // --- THAY ĐỔI CHO NHIỀU ẢNH ---
  existing_images: reactive([]), // Ảnh đã có trên server (khi edit)
  new_images: [],                 // File ảnh mới chọn
  images_to_delete: []            // Mảng ID ảnh cũ cần xóa
});

// Dùng để hiển thị preview ảnh MỚI
const newImagePreviews = ref([]);

// Lỗi validation
const errors = reactive({
  name: '',
  category_id: '',
  images: '', // Lỗi chung cho phần ảnh
  variants: ''
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchProducts();
  fetchCategories();
  if (modalRef.value) {
    modalInstance.value = new Modal(modalRef.value);
  }
});

// --- CÁC HÀM TẢI DỮ LIỆU ---

async function fetchProducts() {
  isLoading.value = true;
  try {
    const response = await axios.get(`${API_URL}/products`);
    products.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải sản phẩm:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách sản phẩm.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function fetchCategories() {
  try {
    const response = await axios.get(`${API_URL}/categories`);
    categories.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải danh mục:", error);
  }
}

// --- CÁC HÀM HELPER ---

function formatCurrency(value) {
  if (!value) return 'N/A';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}

function calculateTotalStock(variants) {
  if (!variants || variants.length === 0) return 0;
  return variants.reduce((acc, variant) => acc + (parseInt(variant.stock) || 0), 0);
}

// --- CÁC HÀM QUẢN LÝ BIẾN THỂ ---

function addVariantRow() {
  formData.variants.push(reactive({
    color: '',
    size: '',
    price: 0,
    stock: 0
  }));
}

function removeVariantRow(index) {
  formData.variants.splice(index, 1);
}

// --- CÁC HÀM QUẢN LÝ ẢNH (ĐÃ CẬP NHẬT) ---

/**
 * Xử lý khi người dùng chọn nhiều file ảnh
 */
function handleImageUpload(event) {
  // Xóa lỗi cũ
  errors.images = '';

  // Lấy danh sách file (FileList) và chuyển thành mảng (Array)
  formData.new_images = Array.from(event.target.files);

  // Hủy các URL blob cũ để tránh rò rỉ bộ nhớ
  newImagePreviews.value.forEach(url => URL.revokeObjectURL(url));

  // Tạo URL preview cho các ảnh mới
  newImagePreviews.value = formData.new_images.map(file => URL.createObjectURL(file));
}

/**
 * Xóa một ảnh MỚI (chưa upload) khỏi danh sách preview
 */
function removeNewImage(index) {
  // Hủy URL blob
  URL.revokeObjectURL(newImagePreviews.value[index]);

  // Xóa khỏi cả 2 mảng
  newImagePreviews.value.splice(index, 1);
  formData.new_images.splice(index, 1);
}

/**
 * Xóa một ảnh CŨ (đã có trên server)
 */
function removeExistingImage(index) {
  // Lấy ảnh bị xóa ra khỏi mảng existing_images
  const removedImage = formData.existing_images.splice(index, 1);

  // Thêm ID của nó vào mảng "cần xóa"
  if (removedImage[0]?.id) {
    formData.images_to_delete.push(removedImage[0].id);
  }
}

// --- CÁC HÀM CRUD MODAL ---

function resetForm() {
  formData.id = null;
  formData.name = '';
  formData.description = '';
  formData.category_id = null;
  formData.variants = reactive([]);

  // Hủy các URL blob cũ
  newImagePreviews.value.forEach(url => URL.revokeObjectURL(url));

  // Reset các state ảnh
  formData.existing_images = reactive([]);
  formData.new_images = [];
  formData.images_to_delete = [];
  newImagePreviews.value = [];

  // Xóa file input
  const fileInput = document.getElementById('product_images');
  if (fileInput) fileInput.value = '';

  Object.keys(errors).forEach(key => errors[key] = '');
}

function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  addVariantRow();
  modalInstance.value.show();
}

function openEditModal(product) {
  resetForm();
  isEditMode.value = true;

  formData.id = product.id;
  formData.name = product.name;
  formData.description = product.description;
  formData.category_id = product.category_id;

  // Giả sử API trả về product.images là một mảng [{id: 1, url: '...'}, ...]
  formData.existing_images = reactive(product.images ? product.images.map(img => ({ ...img })) : []);

  formData.variants = reactive(
    product.variants.map(v => ({ ...v }))
  );

  modalInstance.value.show();
}

/**
 * Xác thực form (ĐÃ CẬP NHẬT)
 */
function validateForm() {
  Object.keys(errors).forEach(key => errors[key] = '');
  let isValid = true;

  if (!formData.name.trim()) {
    errors.name = 'Vui lòng nhập tên sản phẩm.';
    isValid = false;
  }
  if (!formData.category_id) {
    errors.category_id = 'Vui lòng chọn danh mục.';
    isValid = false;
  }

  // Kiểm tra ảnh
  // Khi tạo mới, bắt buộc phải có ảnh mới
  if (!isEditMode.value && formData.new_images.length === 0) {
    errors.images = 'Vui lòng chọn ít nhất 1 ảnh sản phẩm.';
    isValid = false;
  }
  // Khi sửa, (tổng ảnh cũ + ảnh mới) phải > 0
  if (isEditMode.value && formData.existing_images.length === 0 && formData.new_images.length === 0) {
    errors.images = 'Sản phẩm phải có ít nhất 1 ảnh.';
    isValid = false;
  }

  if (formData.variants.length === 0) {
    errors.variants = 'Sản phẩm phải có ít nhất 1 biến thể (SKU).';
    isValid = false;
  } else {
    for (const variant of formData.variants) {
      if ((!variant.color && !variant.size) || !variant.price || !variant.stock) {
        errors.variants = 'Thông tin biến thể (Màu/Cỡ, Giá, Số lượng) không được để trống.';
        isValid = false;
        break;
      }
    }
  }
  return isValid;
}

/**
 * Xử lý lưu (ĐÃ CẬP NHẬT)
 */
async function handleSave() {
  if (!validateForm()) {
    return;
  }

  isLoading.value = true;
  const payload = new FormData();

  // Thêm các trường cơ bản
  payload.append('name', formData.name);
  payload.append('description', formData.description);
  payload.append('category_id', formData.category_id);

  // Gửi mảng variants dưới dạng JSON string
  payload.append('variants', JSON.stringify(formData.variants));

  // --- LOGIC ẢNH MỚI ---
  // Thêm tất cả file ảnh mới vào FormData
  // Sử dụng 'new_images[]' để báo cho backend (Laravel/PHP) biết đây là 1 mảng
  formData.new_images.forEach((file) => {
    payload.append('new_images[]', file);
  });

  try {
    if (isEditMode.value) {
      // --- CHẾ ĐỘ CẬP NHẬT (UPDATE) ---
      payload.append('_method', 'PUT');

      // Thêm mảng ID ảnh cần xóa
      payload.append('images_to_delete', JSON.stringify(formData.images_to_delete));

      await axios.post(`${API_URL}/products/${formData.id}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã cập nhật sản phẩm!', 'success');
    } else {
      // --- CHẾ ĐỘ TẠO MỚI (CREATE) ---
      await axios.post(`${API_URL}/products`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã tạo sản phẩm mới!', 'success');
    }

    modalInstance.value.hide();
    fetchProducts();
  } catch (apiError) {
    console.error("Lỗi khi lưu:", apiError);
    if (apiError.response?.data?.errors) {
      const serverErrors = apiError.response.data.errors;
      if (serverErrors.name) errors.name = serverErrors.name[0];
      if (serverErrors.category_id) errors.category_id = serverErrors.category_id[0];
      if (serverErrors.new_images) errors.images = serverErrors.new_images[0];
    } else {
      Swal.fire('Thất bại', 'Đã có lỗi xảy ra. Vui lòng thử lại.', 'error');
    }
  } finally {
    isLoading.value = false;
  }
}

/**
 * Xử lý xóa sản phẩm
 */
async function handleDelete(product) {
  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn sẽ xóa vĩnh viễn sản phẩm "${product.name}"!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Đồng ý xóa!',
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`${API_URL}/products/${product.id}`);
      Swal.fire('Đã xóa!', 'Sản phẩm đã được xóa.', 'success');
      fetchProducts();
    } catch (error) {
      console.error("Lỗi khi xóa sản phẩm:", error);
      Swal.fire('Lỗi', 'Không thể xóa sản phẩm này.', 'error');
    }
  }
}
</script>

<template>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Sản phẩm</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">Sản phẩm</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Danh sách Sản phẩm</h3>
              <div class="card-tools">
                <button type="button" class="btn btn-primary" @click="openCreateModal">
                  <i class="bi bi-plus-lg"></i> Thêm mới
                </button>
              </div>
            </div>
            <div class="card-body">
              <div v-if="isLoading" class="text-center">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>
              <table v-else class="table table-bordered table-hover">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Ảnh</th>
                    <th>Tên sản phẩm</th>
                    <th>Danh mục</th>
                    <th>Giá (cơ bản)</th>
                    <th>Tổng tồn kho</th>
                    <th>Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="products.length === 0">
                    <td colspan="7" class="text-center">Không có sản phẩm nào</td>
                  </tr>
                  <tr v-for="product in products" :key="product.id">
                    <td>{{ product.id }}</td>
                    <td>
                      <img :src="product.images?.[0]?.url || '/assets/img/default-150x150.png'" alt="Ảnh SP"
                        class="img-thumbnail" width="60">
                    </td>
                    <td>{{ product.name }}</td>
                    <td>{{ product.category?.name || 'N/A' }}</td>
                    <td>{{ formatCurrency(product.variants[0]?.price) }}</td>
                    <td>{{ calculateTotalStock(product.variants) }}</td>
                    <td>
                      <button class="btn btn-warning btn-sm me-2" @click="openEditModal(product)">
                        <i class="bi bi-pencil"></i> Sửa
                      </button>
                      <button class="btn btn-danger btn-sm" @click="handleDelete(product)">
                        <i class="bi bi-trash"></i> Xóa
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
  </div>

  <div class="modal fade" id="productModal" ref="modalRef" tabindex="-1" aria-labelledby="productModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="productModalLabel">
            {{ isEditMode ? 'Chỉnh sửa Sản phẩm' : 'Tạo Sản phẩm mới' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSave">

            <div class="row">
              <div class="col-md-7">
                <div class="mb-3">
                  <label for="name" class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" id="name"
                    v-model="formData.name">
                  <div class="invalid-feedback" v-if="errors.name">{{ errors.name }}</div>
                </div>
                <div class="mb-3">
                  <label for="description" class="form-label">Mô tả</label>
                  <textarea class="form-control" id="description" rows="5" v-model="formData.description"></textarea>
                </div>
              </div>

              <div class="col-md-5">
                <div class="mb-3">
                  <label for="category_id" class="form-label">Danh mục <span class="text-danger">*</span></label>
                  <select class="form-select" :class="{ 'is-invalid': errors.category_id }" id="category_id"
                    v-model="formData.category_id">
                    <option :value="null" disabled>-- Chọn danh mục --</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                  </select>
                  <div class="invalid-feedback" v-if="errors.category_id">{{ errors.category_id }}</div>
                </div>

                <div class="mb-3">
                  <label for="product_images" class="form-label">Ảnh sản phẩm <span class="text-danger">*</span></label>
                  <input type="file" class="form-control" :class="{ 'is-invalid': errors.images }" id="product_images"
                    @change="handleImageUpload" accept="image/*" multiple>
                  <div class="form-text" v-if="isEditMode">(Chọn ảnh mới sẽ thay thế toàn bộ ảnh cũ)</div>
                  <div class="invalid-feedback" v-if="errors.images">{{ errors.images }}</div>

                  <div class="image-preview-container mt-2">
                    <div v-for="(image, index) in formData.existing_images" :key="`exist-${image.id}`"
                      class="image-preview-item">
                      <img :src="image.url" class="img-thumbnail" alt="Ảnh cũ">
                      <button class_button="btn btn-danger btn-sm btn-remove-image"
                        @click.prevent="removeExistingImage(index)">
                        &times;
                      </button>
                    </div>
                    <div v-for="(url, index) in newImagePreviews" :key="`new-${index}`" class="image-preview-item">
                      <img :src="url" class="img-thumbnail" alt="Ảnh mới">
                      <button class_button="btn btn-danger btn-sm btn-remove-image"
                        @click.prevent="removeNewImage(index)">
                        &times;
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <hr>

            <h5>Biến thể sản phẩm (SKUs) <span class="text-danger">*</span></h5>
            <div class="alert alert-danger" v-if="errors.variants">
              {{ errors.variants }}
            </div>

            <div class="row g-3 align-items-center mb-2 d-none d-md-flex">
              <div class="col-md-3"><label class="form-label">Màu sắc</label></div>
              <div class="col-md-3"><label class="form-label">Kích cỡ</label></div>
              <div class="col-md-2"><label class="form-label">Giá (VND)</label></div>
              <div class="col-md-2"><label class="form-label">Số lượng</label></div>
              <div class="col-md-2"></div>
            </div>

            <div v-for="(variant, index) in formData.variants" :key="index"
              class="row g-3 align-items-center mb-3 variant-row">
              <div class="col-md-3">
                <label class="form-label d-md-none">Màu sắc</label>
                <input type="text" class="form-control" placeholder="ví dụ: Đỏ, Xanh" v-model="variant.color">
              </div>
              <div class="col-md-3">
                <label class="form-label d-md-none">Kích cỡ</label>
                <input type="text" class="form-control" placeholder="ví dụ: M, L, XL" v-model="variant.size">
              </div>
              <div class="col-md-2">
                <label class="form-label d-md-none">Giá (VND)</label>
                <input type="number" class="form-control" placeholder="Giá" v-model.number="variant.price" min="0">
              </div>
              <div class="col-md-2">
                <label class="form-label d-md-none">Số lượng</label>
                <input type="number" class="form-control" placeholder="SL" v-model.number="variant.stock" min="0">
              </div>
              <div class="col-md-2 text-md-end">
                <button class="btn btn-danger btn-sm" @click.prevent="removeVariantRow(index)">
                  <i class="bi bi-trash"></i> <span class="d-md-none">Xóa</span>
                </button>
              </div>
            </div>

            <button class="btn btn-success btn-sm mt-2" @click.prevent="addVariantRow">
              <i class="bi bi-plus-lg"></i> Thêm biến thể
            </button>

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
/* Thêm CSS cho phần preview ảnh */
.image-preview-container {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
}

.image-preview-item {
  position: relative;
  width: 100px;
  height: 100px;
}

.image-preview-item .img-thumbnail {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.image-preview-item .btn-remove-image {
  position: absolute;
  top: -5px;
  right: -5px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background-color: red;
  color: white;
  border: none;
  font-weight: bold;
  font-size: 12px;
  line-height: 1;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

/* Style cho hàng biến thể trên di động */
@media (max-width: 767.98px) {
  .variant-row {
    border: 1px solid #dee2e6;
    border-radius: .375rem;
    padding: 1rem;
    margin-bottom: 1rem !important;
  }

  .variant-row .col-md-2 {
    text-align: left !important;
    /* Reset cho di động */
  }
}

.table td .btn {
  margin-top: 2px;
  margin-bottom: 2px;
}
</style>