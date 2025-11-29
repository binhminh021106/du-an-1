<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js'; // Đảm bảo apiService hỗ trợ gửi FormData
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE CHUNG ---
const isLoading = ref(true);
const isLoadingBrands = ref(true);
const isUploading = ref(false); // Trạng thái đang upload ảnh

// --- STATE SLIDES ---
const allSlides = ref([]);
const searchQuery = ref('');
const slideModalRef = ref(null);
const slideModalInstance = ref(null);
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingSlide = ref({});
const isEditMode = ref(false);

// State xử lý file ảnh Slide
const slideFile = ref(null);
const slidePreviewImage = ref('');
const slideFileInputRef = ref(null); // Để reset input file

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

// State xử lý file ảnh Brand
const brandFile = ref(null);
const brandPreviewImage = ref('');
const brandFileInputRef = ref(null);

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
  let slides = allSlides.value;
  if (query) {
    slides = slides.filter(s =>
      s.title.toLowerCase().includes(query) ||
      s.description.toLowerCase().includes(query)
    );
  }
  // Sắp xếp theo order tăng dần để hiển thị đúng thứ tự
  return slides.sort((a, b) => a.order - b.order);
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
  let brands = allBrands.value;
  if (query) {
    brands = brands.filter(b => b.name.toLowerCase().includes(query));
  }
  return brands.sort((a, b) => a.order - b.order);
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
    const response = await apiService.get(`admin/slides`);
    allSlides.value = response.data.map(s => ({ ...s, created_at: s.created_at || new Date().toISOString() }));
  } catch (error) {
    console.error("Lỗi khi tải slide:", error);
  } finally {
    isLoading.value = false;
  }
}

async function fetchAllBrands() {
  isLoadingBrands.value = true;
  try {
    const response = await apiService.get(`admin/brands`);
    allBrands.value = response.data.map(b => ({ ...b, status: b.status || 'published' }));
  } catch (error) {
    console.error("Lỗi khi tải brand:", error);
  } finally {
    isLoadingBrands.value = false;
  }
}

// --- CÁC HÀM HELPER ---
function getStatusBadge(status) {
  if (status === 'published') return { class: 'text-bg-success', text: 'Hiển thị' };
  return { class: 'text-bg-secondary', text: 'Ẩn' };
}
function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
}

// --- HÀM XỬ LÝ URL ẢNH ---
function onSlideFileChange(event) {
  const file = event.target.files[0];
  if (file) {
    if (!file.type.startsWith('image/')) {
      Swal.fire('Lỗi', 'Vui lòng chọn file hình ảnh.', 'error');
      return;
    }
    if (file.size > 5 * 1024 * 1024) { // 5MB limit
      Swal.fire('Lỗi', 'Kích thước ảnh không được quá 5MB.', 'error');
      return;
    }
    slideFile.value = file;
    slidePreviewImage.value = URL.createObjectURL(file);
    slideErrors.imageUrl = '';
  }
}

// --- CRUD SLIDES ---
function resetSlideForm() {
  Object.assign(slideFormData, {
    id: null, title: '', description: '', imageUrl: '', linkUrl: '', status: 'published', order: 0
  });
  slideFile.value = null;
  slidePreviewImage.value = '';
  if (slideFileInputRef.value) slideFileInputRef.value.value = '';
  Object.keys(slideErrors).forEach(key => slideErrors[key] = '');
}

function openCreateSlideModal() {
  resetSlideForm();
  // Tự động set order = max + 1
  const maxOrder = allSlides.value.length > 0 ? Math.max(...allSlides.value.map(s => s.order)) : -1;
  slideFormData.order = maxOrder + 1;
  
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
  slidePreviewImage.value = slide.imageUrl;
  slideFile.value = null;
  if (slideFileInputRef.value) slideFileInputRef.value.value = '';

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

  if (!isEditMode.value && !slideFile.value) {
    slideErrors.imageUrl = 'Vui lòng chọn hình ảnh.';
    isValid = false;
  } else if (isEditMode.value && !slideFile.value && !slideFormData.imageUrl) {
    slideErrors.imageUrl = 'Slide chưa có hình ảnh.';
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
  isUploading.value = true;

  try {
    const formData = new FormData();
    formData.append('title', slideFormData.title);
    formData.append('description', slideFormData.description || '');
    formData.append('linkUrl', slideFormData.linkUrl || '');
    formData.append('order', slideFormData.order);
    formData.append('status', slideFormData.status);

    if (slideFile.value) {
      formData.append('image', slideFile.value);
    }

    if (isEditMode.value) {
      formData.append('_method', 'PUT');
      await apiService.post(`admin/slides/${slideFormData.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã cập nhật slide!', 'success');
    } else {
      await apiService.post(`admin/slides`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã tạo slide mới!', 'success');
    }

    slideModalInstance.value.hide();
    fetchAllSlides();

  } catch (error) {
    console.error("Lỗi khi lưu slide:", error);
    if (error.response && error.response.data && error.response.data.errors) {
      const errs = error.response.data.errors;
      if (errs.title) slideErrors.title = errs.title[0];
      if (errs.image) slideErrors.imageUrl = errs.image[0];
      Swal.fire('Lỗi nhập liệu', 'Vui lòng kiểm tra lại thông tin.', 'warning');
    } else {
      Swal.fire('Thất bại', 'Đã có lỗi xảy ra phía Server.', 'error');
    }
  } finally {
    isLoading.value = false;
    isUploading.value = false;
  }
}

async function handleSlideToggleStatus(slide) {
  const newStatus = slide.status === 'published' ? 'draft' : 'published';
  const actionText = newStatus === 'published' ? 'hiển thị' : 'ẩn';
  try {
    // Optimistic Update
    const oldStatus = slide.status;
    slide.status = newStatus;

    // Sử dụng FormData để gửi PUT request (Laravel sometimes prefers POST with _method for updates)
    const formData = new FormData();
    formData.append('title', slide.title); // API require title
    formData.append('status', newStatus);
    formData.append('_method', 'PUT');

    await apiService.post(`admin/slides/${slide.id}`, formData);
    
    // Thông báo lớn ở giữa màn hình thay vì toast
    Swal.fire('Thành công', `Đã ${actionText} slide "${slide.title}"`, 'success');
  } catch (error) {
    slide.status = slide.status === 'published' ? 'draft' : 'published'; // Revert
    console.error("Lỗi cập nhật trạng thái slide:", error);
    Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
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
      await apiService.delete(`admin/slides/${slide.id}`);
      Swal.fire('Đã xóa!', 'Slide đã được xóa.', 'success');
      fetchAllSlides();
    } catch (error) {
      console.error("Lỗi khi xóa slide:", error);
      Swal.fire('Lỗi', 'Không thể xóa slide này.', 'error');
    }
  }
}

// --- LOGIC SẮP XẾP NHANH (REORDERING) ---
async function moveItem(item, direction, list, type) {
  // type: 'slide' | 'brand'
  const sortedList = [...list].sort((a, b) => a.order - b.order);
  const currentIndex = sortedList.findIndex(i => i.id === item.id);
  
  if (currentIndex === -1) return;

  let targetItem = null;
  let newOrder = item.order;

  // Xác định item mục tiêu để hoán đổi hoặc giá trị order mới
  if (direction === 'up') {
    if (currentIndex > 0) targetItem = sortedList[currentIndex - 1];
  } else if (direction === 'down') {
    if (currentIndex < sortedList.length - 1) targetItem = sortedList[currentIndex + 1];
  } else if (direction === 'top') {
    if (currentIndex > 0) targetItem = sortedList[0];
  } else if (direction === 'bottom') {
    if (currentIndex < sortedList.length - 1) targetItem = sortedList[sortedList.length - 1];
  }

  if (!targetItem) return; // Không thể di chuyển

  try {
    const endpoint = type === 'slide' ? 'admin/slides' : 'admin/brands';
    
    // Hoán đổi giá trị Order
    const tempOrder = item.order;
    const targetOrder = targetItem.order;

    // Update Item hiện tại
    const form1 = new FormData();
    form1.append('order', targetOrder);
    if(type === 'slide') form1.append('title', item.title); // required field
    if(type === 'brand') form1.append('name', item.name); // required field
    form1.append('status', item.status);
    form1.append('_method', 'PUT');

    // Update Item mục tiêu
    const form2 = new FormData();
    form2.append('order', tempOrder);
    if(type === 'slide') form2.append('title', targetItem.title);
    if(type === 'brand') form2.append('name', targetItem.name);
    form2.append('status', targetItem.status);
    form2.append('_method', 'PUT');

    if(type === 'slide') isLoading.value = true;
    else isLoadingBrands.value = true;

    await Promise.all([
      apiService.post(`${endpoint}/${item.id}`, form1),
      apiService.post(`${endpoint}/${targetItem.id}`, form2)
    ]);

    // Tải lại dữ liệu
    if (type === 'slide') await fetchAllSlides();
    else await fetchAllBrands();

  } catch (error) {
    console.error(`Lỗi sắp xếp ${type}:`, error);
    Swal.fire('Lỗi', 'Không thể thay đổi thứ tự.', 'error');
  } finally {
    if(type === 'slide') isLoading.value = false;
    else isLoadingBrands.value = false;
  }
}


// --- XỬ LÝ ẢNH BRAND ---
function onBrandFileChange(event) {
  const file = event.target.files[0];
  if (file) {
    if (!file.type.startsWith('image/')) {
      Swal.fire('Lỗi', 'Vui lòng chọn file hình ảnh.', 'error');
      return;
    }
    brandFile.value = file;
    brandPreviewImage.value = URL.createObjectURL(file);
    brandErrors.imageUrl = '';
  }
}

// --- CRUD BRANDS ---
function resetBrandForm() {
  Object.assign(brandFormData, {
    id: null, name: '', imageUrl: '', linkUrl: '', order: 0, status: 'published'
  });
  brandFile.value = null;
  brandPreviewImage.value = '';
  if (brandFileInputRef.value) brandFileInputRef.value.value = '';
  Object.keys(brandErrors).forEach(key => brandErrors[key] = '');
}

function openCreateBrandModal() {
  resetBrandForm();
  const maxOrder = allBrands.value.length > 0 ? Math.max(...allBrands.value.map(b => b.order)) : -1;
  brandFormData.order = maxOrder + 1;
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
  brandPreviewImage.value = brand.imageUrl;
  brandFile.value = null;
  if (brandFileInputRef.value) brandFileInputRef.value.value = '';

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

  if (!isBrandEditMode.value && !brandFile.value) {
    brandErrors.imageUrl = 'Vui lòng chọn hình ảnh.';
    isValid = false;
  } else if (isBrandEditMode.value && !brandFile.value && !brandFormData.imageUrl) {
    brandErrors.imageUrl = 'Brand chưa có hình ảnh.';
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

  try {
    const formData = new FormData();
    formData.append('name', brandFormData.name);
    formData.append('linkUrl', brandFormData.linkUrl || '');
    formData.append('order', brandFormData.order);
    formData.append('status', brandFormData.status);

    if (brandFile.value) {
      formData.append('image', brandFile.value);
    }

    if (isBrandEditMode.value) {
      formData.append('_method', 'PUT');
      await apiService.post(`admin/brands/${brandFormData.id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã cập nhật brand!', 'success');
    } else {
      await apiService.post(`admin/brands`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã tạo brand mới!', 'success');
    }

    brandModalInstance.value.hide();
    fetchAllBrands();
  } catch (error) {
    console.error("Lỗi lưu brand:", error);
    Swal.fire('Thất bại', 'Đã có lỗi xảy ra.', 'error');
  } finally {
    isLoadingBrands.value = false;
    if (brandFileInputRef.value) brandFileInputRef.value.value = '';
  }
}

async function handleBrandToggleStatus(brand) {
  const newStatus = brand.status === 'published' ? 'draft' : 'published';
  const actionText = newStatus === 'published' ? 'hiển thị' : 'ẩn';
  try {
    brand.status = newStatus;
    
    // Sửa: Thêm name bắt buộc nếu API yêu cầu
    const formData = new FormData();
    formData.append('name', brand.name); 
    formData.append('status', newStatus);
    formData.append('_method', 'PUT');

    await apiService.post(`admin/brands/${brand.id}`, formData);
    
    // Thông báo lớn
    Swal.fire('Thành công', `Đã ${actionText} brand "${brand.name}"!`, 'success');
  } catch (error) {
    console.error("Lỗi cập nhật trạng thái brand:", error);
    brand.status = newStatus === 'published' ? 'draft' : 'published';
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
      await apiService.delete(`admin/brands/${brand.id}`);
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

  <div class="app-content">
    <div class="container-fluid">
      <!-- SLIDE SECTION -->
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
                <table class="table table-hover table-bordered align-middle">
                  <thead>
                    <tr>
                      <th style="width: 200px" class="text-center">Sắp xếp</th>
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
                    <tr v-for="(slide, index) in paginatedSlides" :key="slide.id">
                      <td class="text-center">
                        <!-- ORDER CONTROLS -->
                        <div class="btn-group btn-group-sm" role="group">
                          <button type="button" class="btn btn-outline-secondary" title="Lên đầu" 
                            @click="moveItem(slide, 'top', allSlides, 'slide')">
                            <i class="bi bi-chevron-double-up"></i>
                          </button>
                          <button type="button" class="btn btn-outline-secondary" title="Lên"
                            @click="moveItem(slide, 'up', allSlides, 'slide')">
                            <i class="bi bi-chevron-up"></i>
                          </button>
                          <button type="button" class="btn btn-light disabled border-secondary" style="width: 40px; font-weight: bold; color: #333">
                            {{ slide.order }}
                          </button>
                          <button type="button" class="btn btn-outline-secondary" title="Xuống"
                            @click="moveItem(slide, 'down', allSlides, 'slide')">
                            <i class="bi bi-chevron-down"></i>
                          </button>
                          <button type="button" class="btn btn-outline-secondary" title="Xuống cuối"
                            @click="moveItem(slide, 'bottom', allSlides, 'slide')">
                            <i class="bi bi-chevron-double-down"></i>
                          </button>
                        </div>
                      </td>
                      <td>
                        <img :src="slide.imageUrl || 'https://placehold.co/100x50?text=N/A'" alt="Ảnh slide"
                          class="img-thumbnail">
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
                      <td class="text-center">
                        <div class="d-flex justify-content-center align-items-center">
                          <!-- TOGGLE SWITCH CHO SLIDE -->
                          <div class="form-check form-switch d-inline-block align-middle me-3" title="Kích hoạt/Vô hiệu hóa">
                            <input class="form-check-input" type="checkbox" role="switch"
                              style="width: 2.5em; height: 1.25em; cursor: pointer;"
                              :checked="slide.status === 'published'"
                              @change="handleSlideToggleStatus(slide)">
                          </div>

                          <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info" @click="openViewSlideModal(slide)" title="Xem chi tiết">
                              <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-warning" @click="openEditSlideModal(slide)" title="Sửa">
                              <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" @click="handleDeleteSlide(slide)" title="Xóa">
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
            <div class="card-footer clearfix" v-if="!isLoading && totalSlidePages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <li class="page-item" :class="{ disabled: slidePagination.currentPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="goToSlidePage(slidePagination.currentPage - 1)">&laquo;</a>
                </li>
                <li v-for="page in totalSlidePages" :key="page" class="page-item"
                  :class="{ active: slidePagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="goToSlidePage(page)">{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: slidePagination.currentPage === totalSlidePages }">
                  <a class="page-link" href="#" @click.prevent="goToSlidePage(slidePagination.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- BRAND SECTION -->
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
                <table class="table table-hover table-bordered align-middle">
                  <thead>
                    <tr>
                      <th style="width: 200px" class="text-center">Sắp xếp</th>
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
                      <td class="text-center">
                          <!-- ORDER CONTROLS FOR BRAND -->
                          <div class="btn-group btn-group-sm" role="group">
                          <button type="button" class="btn btn-outline-secondary" title="Lên đầu" 
                            @click="moveItem(brand, 'top', allBrands, 'brand')">
                            <i class="bi bi-chevron-double-up"></i>
                          </button>
                          <button type="button" class="btn btn-outline-secondary" title="Lên"
                            @click="moveItem(brand, 'up', allBrands, 'brand')">
                            <i class="bi bi-chevron-up"></i>
                          </button>
                          <button type="button" class="btn btn-light disabled border-secondary" style="width: 40px; font-weight: bold; color: #333">
                            {{ brand.order }}
                          </button>
                          <button type="button" class="btn btn-outline-secondary" title="Xuống"
                            @click="moveItem(brand, 'down', allBrands, 'brand')">
                            <i class="bi bi-chevron-down"></i>
                          </button>
                          <button type="button" class="btn btn-outline-secondary" title="Xuống cuối"
                            @click="moveItem(brand, 'bottom', allBrands, 'brand')">
                            <i class="bi bi-chevron-double-down"></i>
                          </button>
                        </div>
                      </td>
                      <td>
                        <img :src="brand.imageUrl || 'https://placehold.co/100x50?text=N/A'" alt="Ảnh brand"
                          class="img-thumbnail" width="120">
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
                          <div class="form-check form-switch d-inline-block align-middle me-3"
                            title="Kích hoạt/Vô hiệu hóa">
                            <input class="form-check-input" type="checkbox" role="switch"
                              style="width: 2.5em; height: 1.25em; cursor: pointer;"
                              :id="'brandStatusSwitch-' + brand.id" :checked="brand.status === 'published'"
                              @change="handleBrandToggleStatus(brand)"> 
                          </div>
                          <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" title="Chỉnh sửa"
                              @click="openEditBrandModal(brand)">
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
                  <a class="page-link" href="#"
                    @click.prevent="goToBrandPage(brandPagination.currentPage - 1)">&laquo;</a>
                </li>
                <li v-for="page in totalBrandPages" :key="page" class="page-item"
                  :class="{ active: brandPagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="goToBrandPage(page)">{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: brandPagination.currentPage === totalBrandPages }">
                  <a class="page-link" href="#"
                    @click.prevent="goToBrandPage(brandPagination.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODALS (Giữ nguyên như cũ, chỉ hiển thị lại để đảm bảo tính toàn vẹn) -->
  <div class="modal fade" id="slideModal" ref="slideModalRef" tabindex="-1" aria-labelledby="slideModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
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
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="title" class="form-label required">Tiêu đề</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': slideErrors.title }" id="title"
                    v-model="slideFormData.title">
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
                      <input type="number" class="form-control" :class="{ 'is-invalid': slideErrors.order }" id="order"
                        v-model.number="slideFormData.order" min="0">
                      <div class="invalid-feedback" v-if="slideErrors.order">{{ slideErrors.order }}</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="status" class="form-label">Trạng thái</label>
                      <select class="form-select" id="status" v-model="slideFormData.status">
                        <option value="published">Hiển thị (Published)</option>
                        <option value="draft">Ẩn (Hidden)</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="mb-3">
                  <label for="slideFile" class="form-label required">Hình ảnh</label>
                  <input type="file" class="form-control" :class="{ 'is-invalid': slideErrors.imageUrl }" id="slideFile"
                    ref="slideFileInputRef" accept="image/*" @change="onSlideFileChange">
                  <div class="invalid-feedback" v-if="slideErrors.imageUrl">{{ slideErrors.imageUrl }}</div>
                  <small class="text-muted" v-if="isEditMode">Để trống nếu không muốn thay đổi ảnh.</small>
                </div>

                <label class="form-label">Xem trước:</label>
                <div class="image-preview-container-lg position-relative">
                  <div v-if="isUploading"
                    class="position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center bg-light bg-opacity-75"
                    style="z-index: 5;">
                    <div class="spinner-border text-primary" role="status">
                      <span class="visually-hidden">Uploading...</span>
                    </div>
                  </div>

                  <img v-if="slidePreviewImage" :src="slidePreviewImage" class="img-thumbnail" alt="Preview"
                    @error="(e) => e.target.src = 'https://placehold.co/600x300?text=Image+Error'">
                  <img v-else src="https://placehold.co/600x300?text=Chưa+có+ảnh" class="img-thumbnail"
                    alt="Chưa có ảnh">
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="slideForm" class="btn btn-primary" :disabled="isLoading || isUploading">
            <span v-if="isLoading || isUploading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            {{ isUploading ? 'Đang tải ảnh...' : 'Lưu lại' }}
          </button>
        </div>
      </div>
    </div>
  </div>

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
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="brandName" class="form-label required">Tên Brand</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': brandErrors.name }" id="brandName"
                    v-model="brandFormData.name">
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
                      <input type="number" class="form-control" :class="{ 'is-invalid': brandErrors.order }"
                        id="brandOrder" v-model.number="brandFormData.order" min="0">
                      <div class="invalid-feedback" v-if="brandErrors.order">{{ brandErrors.order }}</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label for="brandStatus" class="form-label">Trạng thái</label>
                      <select class="form-select" id="brandStatus" v-model="brandFormData.status">
                        <option value="published">Hiển thị (Published)</option>
                        <option value="draft">Ẩn (Hidden)</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <div class="mb-3">
                  <label for="brandFile" class="form-label required">Hình ảnh</label>
                  <input type="file" class="form-control" :class="{ 'is-invalid': brandErrors.imageUrl }" id="brandFile"
                    ref="brandFileInputRef" accept="image/*" @change="onBrandFileChange">
                  <div class="invalid-feedback" v-if="brandErrors.imageUrl">{{ brandErrors.imageUrl }}</div>
                  <small class="text-muted" v-if="isBrandEditMode">Để trống nếu không muốn thay đổi ảnh.</small>
                </div>

                <label class="form-label">Xem trước:</label>
                <div class="image-preview-container-lg">
                  <img v-if="brandPreviewImage" :src="brandPreviewImage" class="img-thumbnail" alt="Preview"
                    @error="(e) => e.target.src = 'https://placehold.co/600x300?text=Image+Error'">
                  <img v-else src="https://placehold.co/600x300?text=Chưa+có+ảnh" class="img-thumbnail"
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
            <img :src="viewingSlide.imageUrl || 'https://placehold.co/600x300?text=N/A'" class="img-thumbnail shadow-sm"
              alt="Slide Image"
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
              <a :href="viewingSlide.linkUrl" target="_blank" class="mb-1 text-info small"
                v-if="viewingSlide.linkUrl">{{ viewingSlide.linkUrl }}</a>
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
/* Color Palette Overrides */
:root {
    --brand-primary: #009981;
    --brand-dark: #00483D;
}

/* Headings */
h3, .card-title, .modal-title {
    color: #00483D;
    font-weight: 700;
}

/* Primary Button (Use #009981) */
.btn-primary {
    background-color: #009981 !important;
    border-color: #009981 !important;
}
.btn-primary:hover, .btn-primary:focus, .btn-primary:active {
    background-color: #00483D !important;
    border-color: #00483D !important;
}

/* Success Button (Mapped to Dark Teal #00483D for Brand Section) */
.btn-success {
    background-color: #00483D !important;
    border-color: #00483D !important;
}
.btn-success:hover, .btn-success:focus, .btn-success:active {
    background-color: #00332b !important;
    border-color: #00332b !important;
}

/* Outline Buttons */
.btn-outline-primary {
    color: #009981 !important;
    border-color: #009981 !important;
}
.btn-outline-primary:hover {
    background-color: #009981 !important;
    color: #fff !important;
}

/* Text Colors */
.text-primary {
    color: #009981 !important;
}
.text-success {
    color: #00483D !important;
}

/* Badges */
.bg-primary {
    background-color: #009981 !important;
}

/* Pagination */
.page-item.active .page-link {
    background-color: #009981 !important;
    border-color: #009981 !important;
}
.page-link {
    color: #00483D !important;
}

/* Form Controls */
.form-control:focus, .form-select:focus {
    border-color: #009981;
    box-shadow: 0 0 0 0.25rem rgba(0, 153, 129, 0.25);
}

/* Switch */
.form-check-input:checked {
    background-color: #009981;
    border-color: #009981;
}

/* Loading Spinners */
.spinner-border.text-primary {
    color: #009981 !important;
}
.spinner-border.text-success {
    color: #00483D !important;
}

/* --- EXISTING STYLES --- */
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