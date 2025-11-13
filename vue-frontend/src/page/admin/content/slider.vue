<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE CHUNG ---
const isLoading = ref(true);
const isLoadingBrands = ref(true);

// --- STATE SLIDES ---
const allSlides = ref([]);
const searchQuery = ref('');
const slideModalRef = ref(null);
const slideModalInstance = ref(null);
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingSlide = ref({});
const isEditMode = ref(false);
const slidePagination = reactive({
  currentPage: 1,
  itemsPerPage: 5,
});
const slideFormData = reactive({
  id: null, title: '', description: '', imageUrl: '', linkUrl: '', status: 'published', order: 0
});
const slideErrors = reactive({
  title: '', imageUrl: '', order: ''
});

// --- STATE BRANDS ---
const allBrands = ref([]);
const brandSearchQuery = ref('');
const brandModalRef = ref(null);
const brandModalInstance = ref(null);
const isBrandEditMode = ref(false);
const brandPagination = reactive({
  currentPage: 1,
  itemsPerPage: 5,
});
const brandFormData = reactive({
  id: null, name: '', imageUrl: '', linkUrl: '', order: 0, status: 'published'
});
const brandErrors = reactive({
  name: '', imageUrl: '', order: ''
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchAllSlides();
  fetchAllBrands();
  
  if (slideModalRef.value) {
    slideModalInstance.value = new Modal(slideModalRef.value, { backdrop: 'static' });
  }
  if (brandModalRef.value) {
    brandModalInstance.value = new Modal(brandModalRef.value, { backdrop: 'static' });
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- LOGIC TÌM KIẾM & PHÂN TRANG (SLIDES) ---
watch(searchQuery, () => {
  slidePagination.currentPage = 1;
});
const filteredSlides = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return allSlides.value;
  return allSlides.value.filter(s =>
    s.title.toLowerCase().includes(query) ||
    s.description.toLowerCase().includes(query)
  );
});
const totalSlidePages = computed(() => Math.max(1, Math.ceil(filteredSlides.value.length / slidePagination.itemsPerPage)));
const paginatedSlides = computed(() => {
  const start = (slidePagination.currentPage - 1) * slidePagination.itemsPerPage;
  const end = start + slidePagination.itemsPerPage;
  return filteredSlides.value.slice(start, end);
});
function goToSlidePage(page) {
  if (page >= 1 && page <= totalSlidePages.value) {
    slidePagination.currentPage = page;
  }
}

// --- LOGIC TÌM KIẾM & PHÂN TRANG (BRANDS) ---
watch(brandSearchQuery, () => {
  brandPagination.currentPage = 1;
});
const filteredBrands = computed(() => {
  const query = brandSearchQuery.value.toLowerCase().trim();
  if (!query) return allBrands.value;
  return allBrands.value.filter(b => b.name.toLowerCase().includes(query));
});
const totalBrandPages = computed(() => Math.max(1, Math.ceil(filteredBrands.value.length / brandPagination.itemsPerPage)));
const paginatedBrands = computed(() => {
  const start = (brandPagination.currentPage - 1) * brandPagination.itemsPerPage;
  const end = start + brandPagination.itemsPerPage;
  return filteredBrands.value.slice(start, end);
});
function goToBrandPage(page) {
  if (page >= 1 && page <= totalBrandPages.value) {
    brandPagination.currentPage = page;
  }
}

// --- CÁC HÀM TẢI DỮ LIỆU ---
async function fetchAllSlides() {
  isLoading.value = true;
  try {
    const response = await apiService.get(`/slides?_sort=order&_order=asc`);
    allSlides.value = response.data.map(s => ({ ...s, created_at: s.created_at || new Date().toISOString() }));
  } catch (error) {
    console.error("Lỗi khi tải slide:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách slide.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function fetchAllBrands() {
  isLoadingBrands.value = true;
  try {
    const response = await apiService.get(`/brands?_sort=order&_order=asc`);
    allBrands.value = response.data.map(b => ({ ...b, status: b.status || 'published' }));
  } catch (error) {
    console.error("Lỗi khi tải brand:", error);
    // Không báo lỗi, có thể endpoint chưa tồn tại
  } finally {
    isLoadingBrands.value = false;
  }
}

// --- CÁC HÀM HELPER ---
function getStatusBadge(status) {
  if (status === 'published') return { class: 'text-bg-success', text: 'Hiển thị' };
  return { class: 'text-bg-secondary', text: 'Nháp' };
}
function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
}

// --- CRUD SLIDES ---
function resetSlideForm() {
  Object.assign(slideFormData, {
    id: null, title: '', description: '', imageUrl: '', linkUrl: '', status: 'published', order: 0
  });
  Object.keys(slideErrors).forEach(key => slideErrors[key] = '');
}

function openCreateSlideModal() {
  resetSlideForm();
  isEditMode.value = false;
  slideModalInstance.value.show();
}

function openEditSlideModal(slide) {
  isEditMode.value = true;
  Object.assign(slideFormData, {
    id: slide.id,
    title: slide.title,
    description: slide.description,
    imageUrl: slide.imageUrl,
    linkUrl: slide.linkUrl,
    status: slide.status,
    order: slide.order
  });
  Object.keys(slideErrors).forEach(key => slideErrors[key] = '');
  slideModalInstance.value.show();
}

function openViewSlideModal(slide) {
  viewingSlide.value = slide;
  viewModalInstance.value.show();
}

function validateSlideForm() {
  Object.keys(slideErrors).forEach(key => slideErrors[key] = '');
  let isValid = true;
  if (!slideFormData.title.trim()) {
    slideErrors.title = 'Vui lòng nhập tiêu đề slide.';
    isValid = false;
  }
  if (!slideFormData.imageUrl.trim()) {
    slideErrors.imageUrl = 'Vui lòng nhập URL hình ảnh.';
    isValid = false;
  }
  if (slideFormData.order === null || slideFormData.order < 0) {
    slideErrors.order = 'Thứ tự phải là một số không âm.';
    isValid = false;
  }
  return isValid;
}

async function handleSaveSlide() {
  if (!validateSlideForm()) return;
  isLoading.value = true;
  const payload = { ...slideFormData, order: parseInt(slideFormData.order) || 0 };

  try {
    if (isEditMode.value) {
      await apiService.put(`/slides/${payload.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật slide!', 'success');
    } else {
      delete payload.id;
      payload.created_at = new Date().toISOString();
      await apiService.post(`/slides`, payload);
      Swal.fire('Thành công', 'Đã tạo slide mới!', 'success');
    }
    slideModalInstance.value.hide();
    fetchAllSlides();
  } catch (error) {
    console.error("Lỗi khi lưu slide:", error);
    Swal.fire('Thất bại', 'Đã có lỗi xảy ra.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function handleDeleteSlide(slide) {
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
      await apiService.delete(`/slides/${slide.id}`);
      Swal.fire('Đã xóa!', 'Slide đã được xóa.', 'success');
      fetchAllSlides();
    } catch (error) {
      console.error("Lỗi khi xóa slide:", error);
      Swal.fire('Lỗi', 'Không thể xóa slide này.', 'error');
    }
  }
}

// --- CRUD BRANDS (MỚI) ---
function resetBrandForm() {
  Object.assign(brandFormData, {
    id: null, name: '', imageUrl: '', linkUrl: '', order: 0, status: 'published'
  });
  Object.keys(brandErrors).forEach(key => brandErrors[key] = '');
}

function openCreateBrandModal() {
  resetBrandForm();
  isBrandEditMode.value = false;
  brandModalInstance.value.show();
}

function openEditBrandModal(brand) {
  isBrandEditMode.value = true;
  Object.assign(brandFormData, {
    id: brand.id,
    name: brand.name,
    imageUrl: brand.imageUrl,
    linkUrl: brand.linkUrl,
    status: brand.status,
    order: brand.order
  });
  Object.keys(brandErrors).forEach(key => brandErrors[key] = '');
  brandModalInstance.value.show();
}

function validateBrandForm() {
  Object.keys(brandErrors).forEach(key => brandErrors[key] = '');
  let isValid = true;
  if (!brandFormData.name.trim()) {
    brandErrors.name = 'Vui lòng nhập tên brand.';
    isValid = false;
  }
  if (!brandFormData.imageUrl.trim()) {
    brandErrors.imageUrl = 'Vui lòng nhập URL hình ảnh.';
    isValid = false;
  }
  if (brandFormData.order === null || brandFormData.order < 0) {
    brandErrors.order = 'Thứ tự phải là một số không âm.';
    isValid = false;
  }
  return isValid;
}

async function handleSaveBrand() {
  if (!validateBrandForm()) return;
  isLoadingBrands.value = true;
  const payload = { ...brandFormData, order: parseInt(brandFormData.order) || 0 };

  try {
    if (isBrandEditMode.value) {
      await apiService.put(`/brands/${payload.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật brand!', 'success');
    } else {
      delete payload.id;
      await apiService.post(`/brands`, payload);
      Swal.fire('Thành công', 'Đã tạo brand mới!', 'success');
    }
    brandModalInstance.value.hide();
    fetchAllBrands();
  } catch (error) {
    console.error("Lỗi khi lưu brand:", error);
    Swal.fire('Thất bại', 'Đã có lỗi xảy ra.', 'error');
  } finally {
    isLoadingBrands.value = false;
  }
}

async function handleBrandToggleStatus(brand) {
  const newStatus = brand.status === 'published' ? 'draft' : 'published';
  const actionText = newStatus === 'published' ? 'hiển thị' : 'ẩn';
  try {
    brand.status = newStatus; // Cập nhật UI trước
    await apiService.patch(`/brands/${brand.id}`, { status: newStatus });
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: `Đã ${actionText} brand!`,
      showConfirmButton: false,
      timer: 2000
    });
  } catch (error) {
    console.error("Lỗi cập nhật trạng thái brand:", error);
    brand.status = newStatus === 'published' ? 'draft' : 'published'; // Hoàn nguyên
    Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
  }
}

async function handleDeleteBrand(brand) {
  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn sẽ xóa vĩnh viễn brand "${brand.name}"!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Đồng ý xóa!',
    cancelButtonText: 'Hủy bỏ'
  });
  if (result.isConfirmed) {
    try {
      await apiService.delete(`/brands/${brand.id}`);
      Swal.fire('Đã xóa!', 'Brand đã được xóa.', 'success');
      fetchAllBrands();
    } catch (error) {
      console.error("Lỗi khi xóa brand:", error);
      Swal.fire('Lỗi', 'Không thể xóa brand này.', 'error');
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
          <h3 class="mb-0">Quản lý Slide & Banner</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">
              Slide & Banner
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <!-- 2. Nội dung chính của trang -->
  <div class="app-content">
    <div class="container-fluid">
      <!-- Card Slides -->
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <div class="row align-items-center">
                <div class="col-md-4 col-12 mb-2 mb-md-0">
                  <h3 class="card-title mb-0">Danh sách Slide</h3>
                </div>
                <div class="col-md-5 col-12 mb-2 mb-md-0">
                  <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm slide theo tiêu đề..."
                      v-model="searchQuery">
                  </div>
                </div>
                <div class="col-md-3 col-12 text-md-end">
                  <button type="button" class="btn btn-primary" @click="openCreateSlideModal">
                    <i class="bi bi-plus-lg"></i> Thêm mới Slide
                  </button>
                </div>
              </div>
            </div>

            <div class="card-body p-0">
              <div v-if="isLoading && paginatedSlides.length === 0" class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>
              <div v-else class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                  <thead>
                    <tr>
                      <th style="width: 100px">Thứ tự</th>
                      <th style="width: 250px">Ảnh</th>
                      <th>Tiêu đề</th>
                      <th>Link liên kết</th>
                      <th style="width: 120px">Trạng thái</th>
                      <th style="width: 180px" class="text-center">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="paginatedSlides.length === 0">
                      <td colspan="6" class="text-center">
                        {{ searchQuery ? 'Không tìm thấy slide nào.' : 'Không có slide nào.' }}
                      </td>
                    </tr>
                    <tr v-for="slide in paginatedSlides" :key="slide.id">
                      <td>
                        <span class="badge text-bg-dark" style="font-size: 0.9rem;">{{ slide.order }}</span>
                      </td>
                      <td>
                        <img :src="slide.imageUrl || 'https://placehold.co/100x50?text=N/A'"
                          alt="Ảnh slide" class="img-thumbnail" >
                      </td>
                      <td>
                        <strong>{{ slide.title }}</strong>
                        <p class="text-muted small mb-0">{{ slide.description }}</p>
                      </td>
                      <td>
                        <a :href="slide.linkUrl" target="_blank" v-if="slide.linkUrl">{{ slide.linkUrl }}</a>
                        <span v-else class="text-muted">N/A</span>
                      </td>
                      <td>
                        <span class="badge" :class="getStatusBadge(slide.status).class">
                          {{ getStatusBadge(slide.status).text }}
                        </span>
                      </td>
                      <td class="text-center gap-2">
                        <button class="btn btn-outline-info btn-sm me-1" @click="openViewSlideModal(slide)" title="Xem chi tiết">
                          <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning btn-sm me-1" @click="openEditSlideModal(slide)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm" @click="handleDeleteSlide(slide)">
                          <i class="bi bi-trash"></i> 
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card-footer clearfix" v-if="!isLoading && totalPages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <li class="page-item" :class="{ disabled: slidePagination.currentPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="goToSlidePage(slidePagination.currentPage - 1)">&laquo;</a>
                </li>
                <li v-for="page in totalPages" :key="page" class="page-item"
                  :class="{ active: slidePagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="goToSlidePage(page)">{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: slidePagination.currentPage === totalPages }">
                  <a class="page-link" href="#" @click.prevent="goToSlidePage(slidePagination.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Card Brand Banners (MỚI) -->
      <div class="row mt-4">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <div class="row align-items-center">
                <div class="col-md-4 col-12 mb-2 mb-md-0">
                  <h3 class="card-title mb-0">Danh sách Brand Banner</h3>
                </div>
                 <div class="col-md-5 col-12 mb-2 mb-md-0">
                   <div class="input-group">
                     <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                     <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm brand theo tên..."
                       v-model="brandSearchQuery">
                   </div>
                 </div>
                <div class="col-md-3 col-12 text-md-end">
                  <button type="button" class="btn btn-success" @click="openCreateBrandModal">
                    <i class="bi bi-plus-lg"></i> Thêm mới Brand
                  </button>
                </div>
              </div>
            </div>

            <div class="card-body p-0">
              <div v-if="isLoadingBrands && paginatedBrands.length === 0" class="text-center p-5">
                <div class="spinner-border text-success" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>
              <div v-else class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                  <thead>
                    <tr>
                      <th style="width: 100px">Thứ tự</th>
                      <th style="width: 250px">Ảnh</th>
                      <th>Tên Brand</th>
                      <th>Link liên kết</th>
                      <th style="width: 120px">Trạng thái</th>
                      <th style="width: 220px" class="text-center">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="paginatedBrands.length === 0">
                      <td colspan="6" class="text-center">
                        {{ brandSearchQuery ? 'Không tìm thấy brand nào.' : 'Không có brand nào.' }}
                      </td>
                    </tr>
                    <tr v-for="brand in paginatedBrands" :key="brand.id">
                      <td>
                        <span class="badge text-bg-dark" style="font-size: 0.9rem;">{{ brand.order }}</span>
                      </td>
                      <td>
                        <img :src="brand.imageUrl || 'https://placehold.co/100x50?text=N/A'"
                          alt="Ảnh brand" class="img-thumbnail" width="120">
                      </td>
                      <td><strong>{{ brand.name }}</strong></td>
                      <td>
                        <a :href="brand.linkUrl" target="_blank" v-if="brand.linkUrl">{{ brand.linkUrl }}</a>
                        <span v-else class="text-muted">N/A</span>
                      </td>
                      <td>
                        <span class="badge" :class="getStatusBadge(brand.status).class">
                          {{ getStatusBadge(brand.status).text }}
                        </span>
                      </td>
                      <td class="text-center">
                         <div class="d-flex justify-content-center align-items-center">
                            <div class="form-check form-switch d-inline-block align-middle me-3" title="Kích hoạt/Vô hiệu hóa">
                              <input class="form-check-input" type="checkbox" role="switch"
                                style="width: 2.5em; height: 1.25em; cursor: pointer;"
                                :id="'brandStatusSwitch-' + brand.id"
                                :checked="brand.status === 'published'"
                                @click.prevent="handleBrandToggleStatus(brand)">
                            </div>
                           <div class="btn-group btn-group-sm">
                              <button class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditBrandModal(brand)">
                                <i class="bi bi-pencil"></i>
                              </button>
                              <button class="btn btn-outline-danger" title="Xóa" @click="handleDeleteBrand(brand)">
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
             <div class="card-footer clearfix" v-if="!isLoadingBrands && totalBrandPages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <li class="page-item" :class="{ disabled: brandPagination.currentPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="goToBrandPage(brandPagination.currentPage - 1)">&laquo;</a>
                </li>
                <li v-for="page in totalBrandPages" :key="page" class="page-item"
                  :class="{ active: brandPagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="goToBrandPage(page)">{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: brandPagination.currentPage === totalBrandPages }">
                  <a class="page-link" href="#" @click.prevent="goToBrandPage(brandPagination.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Thêm/Sửa Slide (Cập nhật layout) -->
  <div class="modal fade" id="slideModal" ref="slideModalRef" tabindex="-1" aria-labelledby="slideModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"> <!-- Thay đổi: modal-lg -> modal-xl -->
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="slideModalLabel">
            {{ isEditMode ? 'Chỉnh sửa Slide' : 'Tạo Slide mới' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSaveSlide" id="slideForm">
            <div class="row">
              <!-- Cột thông tin (col-md-6) -->
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="title" class="form-label required">Tiêu đề</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': slideErrors.title }"
                    id="title" v-model="slideFormData.title">
                  <div class="invalid-feedback" v-if="slideErrors.title">{{ slideErrors.title }}</div>
                </div>

                <div class="mb-3">
                  <label for="description" class="form-label">Mô tả ngắn</label>
                  <textarea class="form-control" id="description" rows="3"
                    v-model="slideFormData.description"></textarea>
                </div>

                <div class="mb-3">
                  <label for="linkUrl" class="form-label">Link liên kết</label>
                  <input type="text" class="form-control" id="linkUrl" v-model="slideFormData.linkUrl"
                    placeholder="ví dụ: /san-pham/ao-thun">
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="order" class="form-label required">Thứ tự</label>
                      <input type="number" class="form-control"
                        :class="{ 'is-invalid': slideErrors.order }" id="order"
                        v-model.number="slideFormData.order" min="0">
                      <div class="invalid-feedback" v-if="slideErrors.order">{{ slideErrors.order }}</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="status" class="form-label">Trạng thái</label>
                      <select class="form-select" id="status" v-model="slideFormData.status">
                        <option value="published">Hiển thị (Published)</option>
                        <option value="draft">Nháp (Draft)</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Cột Ảnh (col-md-6) -->
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="imageUrl" class="form-label required">URL Hình ảnh</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': slideErrors.imageUrl }"
                    id="imageUrl" v-model="slideFormData.imageUrl" placeholder="https://...">
                  <div class="invalid-feedback" v-if="slideErrors.imageUrl">{{ slideErrors.imageUrl }}</div>
                </div>

                <label class="form-label">Xem trước:</label>
                <div class="image-preview-container-lg">
                  <img v-if="slideFormData.imageUrl" :src="slideFormData.imageUrl" class="img-thumbnail"
                    alt="Preview" @error="(e) => e.target.src = 'https://placehold.co/600x300?text=Image+Error'">
                  <img v-else src="https://placehold.co/600x300?text=Preview" class="img-thumbnail"
                    alt="Chưa có ảnh">
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="slideForm" class="btn btn-primary" :disabled="isLoading">
            <span v-if="isLoading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            Lưu lại
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Thêm/Sửa Brand (MỚI) -->
  <div class="modal fade" id="brandModal" ref="brandModalRef" tabindex="-1" aria-labelledby="brandModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="brandModalLabel">
            {{ isBrandEditMode ? 'Chỉnh sửa Brand' : 'Tạo Brand mới' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSaveBrand" id="brandForm">
            <div class="row">
              <!-- Cột thông tin (col-md-6) -->
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="brandName" class="form-label required">Tên Brand</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': brandErrors.name }"
                    id="brandName" v-model="brandFormData.name">
                  <div class="invalid-feedback" v-if="brandErrors.name">{{ brandErrors.name }}</div>
                </div>

                <div class="mb-3">
                  <label for="brandLinkUrl" class="form-label">Link liên kết</label>
                  <input type="text" class="form-control" id="brandLinkUrl" v-model="brandFormData.linkUrl"
                    placeholder="ví dụ: /thuong-hieu/apple">
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="brandOrder" class="form-label required">Thứ tự</label>
                      <input type="number" class="form-control"
                        :class="{ 'is-invalid': brandErrors.order }" id="brandOrder"
                        v-model.number="brandFormData.order" min="0">
                      <div class="invalid-feedback" v-if="brandErrors.order">{{ brandErrors.order }}</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="brandStatus" class="form-label">Trạng thái</label>
                      <select class="form-select" id="brandStatus" v-model="brandFormData.status">
                        <option value="published">Hiển thị (Published)</option>
                        <option value="draft">Nháp (Draft)</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Cột Ảnh (col-md-6) -->
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="brandImageUrl" class="form-label required">URL Hình ảnh</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': brandErrors.imageUrl }"
                    id="brandImageUrl" v-model="brandFormData.imageUrl" placeholder="https://...">
                  <div class="invalid-feedback" v-if="brandErrors.imageUrl">{{ brandErrors.imageUrl }}</div>
                </div>

                <label class="form-label">Xem trước:</label>
                <div class="image-preview-container-lg">
                  <img v-if="brandFormData.imageUrl" :src="brandFormData.imageUrl" class="img-thumbnail"
                    alt="Preview" @error="(e) => e.target.src = 'https://placehold.co/600x300?text=Image+Error'">
                  <img v-else src="https://placehold.co/600x300?text=Preview" class="img-thumbnail"
                    alt="Chưa có ảnh">
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="brandForm" class="btn btn-primary" :disabled="isLoadingBrands">
            <span v-if="isLoadingBrands" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            Lưu lại
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Xem Chi Tiết Slide (MỚI) -->
  <div class="modal fade" id="slideViewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <div class="position-absolute top-0 start-0 m-3">
            <span class="badge" :class="getStatusBadge(viewingSlide.status).class">
              {{ getStatusBadge(viewingSlide.status).text }}
            </span>
          </div>

          <div class="text-center mb-4 mt-3">
            <img :src="viewingSlide.imageUrl || 'https://placehold.co/600x300?text=N/A'"
              class="img-thumbnail shadow-sm" alt="Slide Image"
              style="width: 100%; height: auto; max-height: 300px; object-fit: cover; border-radius: 8px;">
            <h4 class="mt-3 mb-1">{{ viewingSlide.title }}</h4>
            <p class="text-muted mb-0">ID: {{ viewingSlide.id }}</p>
          </div>

          <div class="list-group list-group-flush">
            <div class="list-group-item px-0">
              <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1"><i class="bi bi-list-ol me-3 text-primary"></i>Thứ tự</h6>
                <span class="badge bg-primary rounded-pill">{{ viewingSlide.order }}</span>
              </div>
            </div>
            <div class="list-group-item px-0">
              <h6 class="mb-2"><i class="bi bi-card-text me-3 text-muted"></i>Mô tả</h6>
              <p class="mb-1 text-muted small">{{ viewingSlide.description || 'Không có mô tả.' }}</p>
            </div>
            <div class="list-group-item px-0">
              <h6 class="mb-2"><i class="bi bi-link-45deg me-3 text-info"></i>Link liên kết</h6>
              <a :href="viewingSlide.linkUrl" target="_blank" class="mb-1 text-info small" v-if="viewingSlide.linkUrl">{{ viewingSlide.linkUrl }}</a>
              <p v-else class="mb-1 text-muted small">Không có.</p>
            </div>
            <div class="list-group-item px-0">
              <h6 class="mb-2"><i class="bi bi-calendar-event me-3 text-muted"></i>Ngày tạo</h6>
              <p class="mb-1 text-muted small">{{ getFormattedDate(viewingSlide.created_at) }}</p>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-primary px-4"
            @click="() => { viewModalInstance.hide(); openEditSlideModal(viewingSlide); }">
            <i class="bi bi-pencil me-2"></i> Chỉnh sửa Slide
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
.image-preview-container-lg .img-thumbnail {
  width: 100%;
  max-height: 300px;
  object-fit: contain;
  background-color: #f8f9fa;
}
.required::after {
  content: " *";
  color: red;
}
.form-check-input {
  width: 2.5em;
  height: 1.25em;
  cursor: pointer;
}
</style>