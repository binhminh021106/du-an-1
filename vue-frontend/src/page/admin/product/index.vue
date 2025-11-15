<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---
const products = ref([]);
const categories = ref([]);
const isLoading = ref(true);
const isEditMode = ref(false);

// State Modal Thêm/Sửa
const modalInstance = ref(null);
const modalRef = ref(null);

// State Modal Xem
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingProduct = ref({});

// State Tìm kiếm & Phân trang
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10); // Hiển thị 10 sản phẩm/trang

// Dữ liệu cho form sản phẩm
const formData = reactive({
  id: null,
  name: '',
  description: '',
  category_id: null,
  status: 'active', // Thêm trường trạng thái
  variants: reactive([]),
  existing_images: reactive([]),
  new_images: [],
  images_to_delete: []
});

const newImagePreviews = ref([]);

// Lỗi validation
const errors = reactive({
  name: '',
  category_id: '',
  images: '',
  variants: ''
});

// --- COMPUTED ---

const filteredProducts = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return products.value; // Trả về danh sách gốc (đã sắp xếp)
  }
  return products.value.filter(product =>
    product.name.toLowerCase().includes(query) ||
    // Sửa lỗi: Tìm kiếm trực tiếp trên 'product.category.name' nếu nó tồn tại
    (product.category?.name && product.category.name.toLowerCase().includes(query))
  );
});

const totalPages = computed(() => {
  return Math.ceil(filteredProducts.value.length / itemsPerPage.value);
});

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  const end = start + itemsPerPage.value;
  return filteredProducts.value.slice(start, end);
});

// --- WATCHERS ---
watch(searchQuery, () => {
  currentPage.value = 1; // Reset về trang 1 khi tìm kiếm
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchProducts();
  fetchCategories();
  if (modalRef.value) {
    modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- CÁC HÀM TẢI DỮ LIỆU ---

async function fetchProducts() {
  isLoading.value = true;
  try {
    // Sắp xếp theo ID mới nhất VÀ expand category để lấy về object
    // Điều này đảm bảo 'product.category.name' luôn tồn tại nếu 'category_id' có
    const response = await apiService.get(`/products?_sort=id&_order=desc&_expand=category`);
    products.value = response.data.map(p => ({
      ...p,
      status: p.status || 'active', // Đảm bảo status luôn tồn tại
      created_at: p.created_at || new Date().toISOString() // Giả lập ngày tạo
    }));
  } catch (error) {
    console.error("Lỗi khi tải sản phẩm:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách sản phẩm.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function fetchCategories() {
  try {
    const response = await apiService.get(`/categories?_sort=order&_order=asc`);
    categories.value = response.data.filter(c => c.status === 'active'); // Chỉ lấy danh mục hoạt động
  } catch (error) {
    console.error("Lỗi khi tải danh mục:", error);
  }
}

// --- CÁC HÀM HELPER ---

function formatCurrency(value) {
  if (value === undefined || value === null) return 'N/A';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}

function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
}

function calculateTotalStock(variants) {
  if (!variants || variants.length === 0) return 0;
  return variants.reduce((acc, variant) => acc + (parseInt(variant.stock) || 0), 0);
}

// Lấy tên danh mục
function getCategoryName(categoryId) {
  const category = categories.value.find(c => c.id === categoryId);
  return category ? category.name : 'N/A';
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

// --- CÁC HÀM QUẢN LÝ ẢNH ---

function handleImageUpload(event) {
  errors.images = '';
  formData.new_images = Array.from(event.target.files);
  newImagePreviews.value.forEach(url => URL.revokeObjectURL(url));
  newImagePreviews.value = formData.new_images.map(file => URL.createObjectURL(file));
}

function removeNewImage(index) {
  URL.revokeObjectURL(newImagePreviews.value[index]);
  newImagePreviews.value.splice(index, 1);
  formData.new_images.splice(index, 1);
}

function removeExistingImage(index) {
  const removedImage = formData.existing_images.splice(index, 1);
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
  formData.status = 'active'; // Reset trạng thái
  formData.variants = reactive([]);

  newImagePreviews.value.forEach(url => URL.revokeObjectURL(url));
  formData.existing_images = reactive([]);
  formData.new_images = [];
  formData.images_to_delete = [];
  newImagePreviews.value = [];

  const fileInput = document.getElementById('product_images');
  if (fileInput) fileInput.value = '';

  Object.keys(errors).forEach(key => errors[key] = '');
}

function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  addVariantRow(); // Thêm 1 hàng biến thể mặc định
  modalInstance.value.show();
}

function openEditModal(product) {
  resetForm();
  isEditMode.value = true;

  formData.id = product.id;
  formData.name = product.name;
  formData.description = product.description;
  // Sửa lỗi: Lấy 'id' từ 'product.category' (object) thay vì 'product.category_id'
  formData.category_id = product.category?.id || product.category_id;
  formData.status = product.status || 'active'; // Gán trạng thái

  // Giả sử API trả về product.images là một mảng [{id: 1, url: '...'}, ...]
  formData.existing_images = reactive(product.images ? product.images.map(img => ({ ...img })) : []);

  formData.variants = reactive(
    product.variants.map(v => ({ ...v }))
  );

  modalInstance.value.show();
}

// Mở Modal Xem
function openViewModal(product) {
  viewingProduct.value = {
    ...product,
    // Sửa lỗi: Lấy 'name' trực tiếp từ 'product.category' (object)
    categoryName: product.category?.name || 'N/A'
  };
  viewModalInstance.value.show();
}


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

  if (!isEditMode.value && formData.new_images.length === 0) {
    errors.images = 'Vui lòng chọn ít nhất 1 ảnh sản phẩm.';
    isValid = false;
  }
  if (isEditMode.value && formData.existing_images.length === 0 && formData.new_images.length === 0) {
    errors.images = 'Sản phẩm phải có ít nhất 1 ảnh.';
    isValid = false;
  }

  if (formData.variants.length === 0) {
    errors.variants = 'Sản phẩm phải có ít nhất 1 biến thể (SKU).';
    isValid = false;
  } else {
    for (const variant of formData.variants) {
      if ((!variant.color && !variant.size) || !variant.price || (variant.stock === null || variant.stock < 0) ) {
        errors.variants = 'Thông tin biến thể (Màu/Cỡ, Giá, Số lượng) không hợp lệ.';
        isValid = false;
        break;
      }
    }
  }
  return isValid;
}

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
  payload.append('status', formData.status); // Thêm status vào payload
  payload.append('variants', JSON.stringify(formData.variants));
  
  formData.new_images.forEach((file) => {
    payload.append('new_images[]', file);
  });

  try {
    if (isEditMode.value) {
      payload.append('_method', 'PUT');
      payload.append('images_to_delete', JSON.stringify(formData.images_to_delete));

      await apiService.post(`/products/${formData.id}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã cập nhật sản phẩm!', 'success');
    } else {
      payload.append('created_at', new Date().toISOString());
      await apiService.post(`/products`, payload, {
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

// Toggle Trạng thái
async function toggleStatus(product) {
  const newStatus = product.status === 'active' ? 'disabled' : 'active';
  const confirmText = `Bạn có chắc chắn muốn ${newStatus === 'active' ? 'KÍCH HOẠT' : 'VÔ HIỆU HÓA'} sản phẩm "${product.name}"?`;

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
    product.status = newStatus; // Cập nhật UI trước
    try {
      await apiService.patch(`/products/${product.id}`, { status: newStatus });
      Swal.fire('Thành công', `Đã ${newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa'} sản phẩm.`, 'success');
    } catch (error) {
      console.error("Lỗi cập nhật trạng thái:", error);
      product.status = newStatus === 'active' ? 'disabled' : 'active'; // Hoàn nguyên nếu lỗi
      Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
    }
  }
}

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
      await apiService.delete(`/products/${product.id}`);
      Swal.fire('Đã xóa!', 'Sản phẩm đã được xóa.', 'success');
      if (paginatedProducts.value.length === 1 && currentPage.value > 1) {
        currentPage.value--;
      }
      fetchProducts();
    } catch (error) {
      console.error("Lỗi khi xóa sản phẩm:", error);
      Swal.fire('Lỗi', 'Không thể xóa sản phẩm này.', 'error');
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
      <div class="card mb-4">
        <!-- Card Header: Tìm kiếm và Thêm mới -->
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col-md-6 col-12 mb-2 mb-md-0">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm kiếm theo tên sản phẩm, danh mục..." v-model="searchQuery">
              </div>
            </div>
            <div class="col-md-6 col-12 text-md-end">
              <button type="button" class="btn btn-primary" @click="openCreateModal">
                <i class="bi bi-plus-lg"></i> Thêm mới Sản phẩm
              </button>
            </div>
          </div>
        </div>

        <!-- Card Body: Bảng sản phẩm -->
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 50px;">ID</th>
                  <th style="width: 80px;">Ảnh</th>
                  <th>Tên sản phẩm</th>
                  <th>Danh mục</th>
                  <th>Giá (cơ bản)</th>
                  <th>Tổng kho</th>
                  <th style="width: 120px;">Trạng thái</th>
                  <th style="width: 180px;" class="text-center">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="isLoading">
                  <td colspan="8" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                  </td>
                </tr>
                <tr v-else-if="filteredProducts.length === 0">
                  <td colspan="8" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    {{ searchQuery ? 'Không tìm thấy sản phẩm nào.' : 'Chưa có sản phẩm nào.' }}
                  </td>
                </tr>
                <!-- Dùng paginatedProducts thay vì products -->
                <tr v-for="product in paginatedProducts" :key="product.id">
                  <td>{{ product.id }}</td>
                  <td>
                    <img :src="product.images?.[0]?.url || 'https://placehold.co/60x60?text=N/A'"
                      alt="Ảnh SP" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                  </td>
                  <td>{{ product.name }}</td>
                  <!-- Sửa lỗi: Hiển thị trực tiếp 'product.category.name' -->
                  <td>{{ product.category?.name || 'N/A' }}</td>
                  <td>{{ formatCurrency(product.variants[0]?.price) }}</td>
                  <td>{{ calculateTotalStock(product.variants) }}</td>
                  <td>
                    <!-- Cột trạng thái -->
                    <span :class="['badge', product.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
                      {{ product.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
                    </span>
                  </td>
                  <td class="text-center">
                    <!-- Cột hành động -->
                    <div class="d-flex justify-content-center align-items-center">
                      <div class="form-check form-switch d-inline-block align-middle me-3" title="Kích hoạt/Vô hiệu hóa">
                        <input class="form-check-input" type="checkbox" role="switch"
                          style="width: 2.5em; height: 1.25em; cursor: pointer;"
                          :id="'statusSwitch-' + product.id"
                          :checked="product.status === 'active'"
                          @click.prevent="toggleStatus(product)">
                      </div>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" title="Xem chi tiết" @click="openViewModal(product)">
                          <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditModal(product)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(product)">
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

        <!-- Card Footer: Phân trang -->
        <div class="card-footer clearfix" v-if="totalPages > 1">
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
              Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} đến
              {{ Math.min(currentPage * itemsPerPage, filteredProducts.length) }}
              trong tổng số {{ filteredProducts.length }} sản phẩm
            </small>
            <ul class="pagination pagination-sm m-0">
              <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button class="page-link" @click="goToPage(currentPage - 1)">&laquo;</button>
              </li>
              <li v-for="page in totalPages" :key="page" class="page-item" :class="{ active: currentPage === page }">
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

  <!-- Modal Thêm/Sửa (Chuyển sang modal-xl và static) -->
  <div class="modal fade" id="productModal" ref="modalRef" tabindex="-1" aria-labelledby="productModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
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
                  <label for="name" class="form-label required">Tên sản phẩm</label>
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
                  <label for="category_id" class="form-label required">Danh mục</label>
                  <select class="form-select" :class="{ 'is-invalid': errors.category_id }" id="category_id"
                    v-model="formData.category_id">
                    <option :value="null" disabled>-- Chọn danh mục --</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                  </select>
                  <div class="invalid-feedback" v-if="errors.category_id">{{ errors.category_id }}</div>
                </div>

                <!-- Thêm trường Trạng thái khi Edit -->
                <div class="mb-3" v-if="isEditMode">
                  <label for="status" class="form-label fw-bold">Trạng thái</label>
                  <select class="form-select" id="status" v-model="formData.status">
                    <option value="active">Hoạt động (Hiển thị)</option>
                    <option value="disabled">Vô hiệu hóa (Ẩn)</option>
                  </select>
                </div>

                <div class="mb-3">
                  <label for="product_images" class="form-label required">Ảnh sản phẩm</label>
                  <input type="file" class="form-control" :class="{ 'is-invalid': errors.images }" id="product_images"
                    @change="handleImageUpload" accept="image/*" multiple>
                  <div class="form-text" v-if="isEditMode">(Chọn ảnh mới sẽ ghi đè ảnh cũ)</div>
                  <div class="invalid-feedback" v-if="errors.images">{{ errors.images }}</div>

                  <div class="image-preview-container mt-2">
                    <!-- Sửa lỗi: class_button -> class -->
                    <div v-for="(image, index) in formData.existing_images" :key="`exist-${image.id}`"
                      class="image-preview-item">
                      <img :src="image.url" class="img-thumbnail" alt="Ảnh cũ">
                      <button class="btn btn-danger btn-sm btn-remove-image"
                        @click.prevent="removeExistingImage(index)">
                        &times;
                      </button>
                    </div>
                    <div v-for="(url, index) in newImagePreviews" :key="`new-${index}`" class="image-preview-item">
                      <img :src="url" class="img-thumbnail" alt="Ảnh mới">
                      <button class="btn btn-danger btn-sm btn-remove-image"
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
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="button" class="btn btn-primary" @click="handleSave" :disabled="isLoading">
            <span v-if="isLoading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            {{ isEditMode ? 'Lưu thay đổi' : 'Tạo sản phẩm' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Xem Chi Tiết (Mới) -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <!-- Status Badge -->
          <div class="position-absolute top-0 start-0 m-3">
            <span :class="['badge', viewingProduct.status === 'active' ? 'bg-success' : 'bg-secondary']">
              {{ viewingProduct.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
            </span>
          </div>

          <!-- Thông tin chung -->
          <div class="row">
            <div class="col-md-4 text-center">
              <img :src="viewingProduct.images?.[0]?.url || 'https://placehold.co/150x150?text=N/A'"
                class="img-thumbnail shadow-sm" alt="Ảnh sản phẩm"
                style="width: 100%; height: auto; max-height: 250px; object-fit: cover;">
              <h5 class="mt-3 mb-1">{{ viewingProduct.name }}</h5>
              <p class="text-muted mb-0">ID: {{ viewingProduct.id }}</p>
            </div>
            <div class="col-md-8">
              <div class="list-group list-group-flush">
                <div class="list-group-item px-0">
                  <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1"><i class="bi bi-tags me-3 text-primary"></i>Danh mục</h6>
                    <span>{{ viewingProduct.categoryName }}</span>
                  </div>
                </div>
                <div class="list-group-item px-0">
                  <h6 class="mb-2"><i class="bi bi-calendar-event me-3 text-muted"></i>Ngày tạo</h6>
                  <p class="mb-1 text-muted small">{{ getFormattedDate(viewingProduct.created_at) }}</p>
                </div>
                <div class="list-group-item px-0">
                  <h6 class="mb-2"><i class="bi bi-card-text me-3 text-muted"></i>Mô tả</h6>
                  <p class="mb-1 text-muted small" style="white-space: pre-wrap;">{{ viewingProduct.description || 'Không có mô tả.' }}</p>
                </div>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <!-- Bảng biến thể -->
          <h5 class="mb-3">Các biến thể (SKUs)</h5>
          <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
            <table class="table table-sm table-striped table-bordered">
              <thead class="table-light sticky-top">
                <tr>
                  <th>Màu sắc</th>
                  <th>Kích cỡ</th>
                  <th>Giá</th>
                  <th>Tồn kho</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!viewingProduct.variants || viewingProduct.variants.length === 0">
                    <td colspan="4" class="text-center text-muted">Không có biến thể</td>
                </tr>
                <tr v-for="(variant, index) in viewingProduct.variants" :key="index">
                  <td>{{ variant.color || 'N/A' }}</td>
                  <td>{{ variant.size || 'N/A' }}</td>
                  <td>{{ formatCurrency(variant.price) }}</td>
                  <td>{{ variant.stock }}</td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-primary px-4"
            @click="() => { viewModalInstance.hide(); openEditModal(viewingProduct); }">
            <i class="bi bi-pencil me-2"></i> Chỉnh sửa sản phẩm
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
  max-height: 200px; /* Giới hạn chiều cao */
  overflow-y: auto; /* Thêm cuộn dọc */
  padding: 5px;
  border: 1px solid #dee2e6;
  border-radius: .375rem;
}

.image-preview-item {
  position: relative;
  width: 100px;
  height: 100px;
  flex-shrink: 0; /* Ngăn co lại */
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
  z-index: 10;
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
  }
}

.table td .btn {
  margin-top: 2px;
  margin-bottom: 2px;
}

/* Thêm CSS cho label bắt buộc */
.required::after {
  content: " *";
  color: red;
}
</style>