<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CẤU HÌNH & STATE ---
const isLoading = ref(true);
const isEditMode = ref(false);
const categories = ref([]); // Dữ liệu gốc
const activeTab = ref('active'); // Tab mặc định: Hoạt động

// State cho Modal Thêm/Sửa
const modalInstance = ref(null);
const modalRef = ref(null);

// State cho Modal Xem
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingCategory = ref({});

// State Tìm kiếm
const searchQuery = ref('');

// State Phân trang (2 danh sách riêng biệt)
const pagination = reactive({
  active: { currentPage: 1, itemsPerPage: 10 },
  disabled: { currentPage: 1, itemsPerPage: 10 }
});

// Form data & Validation
const formData = reactive({ id: null, name: '', description: '', icon: '', order: 1, status: 'active' });
const errors = reactive({ name: '', description: '', icon: '', order: '' });

// --- COMPUTED: LỌC & CHIA DANH SÁCH ---

// 1. Lọc Search trước trên toàn bộ dữ liệu
const searchResults = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return categories.value;

  return categories.value.filter(category =>
    category.name.toLowerCase().includes(query) ||
    (category.description && category.description.toLowerCase().includes(query))
  );
});

// 2. Chia danh sách theo trạng thái
const activeList = computed(() => searchResults.value.filter(c => c.status === 'active'));
const disabledList = computed(() => searchResults.value.filter(c => c.status === 'disabled'));

// 3. Helper tính toán phân trang
function getPaginatedData(list, type) {
  const pageInfo = pagination[type];
  const totalPages = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));
  
  // Reset về trang 1 nếu trang hiện tại vượt quá tổng số trang
  if (pageInfo.currentPage > totalPages) pageInfo.currentPage = 1;

  const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
  const end = start + pageInfo.itemsPerPage;
  
  return {
    data: list.slice(start, end),
    totalPages: totalPages,
    totalItems: list.length
  };
}

// 4. Dữ liệu hiển thị cho từng tab (Đã phân trang)
const pagedActive = computed(() => getPaginatedData(activeList.value, 'active'));
const pagedDisabled = computed(() => getPaginatedData(disabledList.value, 'disabled'));

// --- WATCHERS ---
watch(searchQuery, () => {
  // Reset trang về 1 khi tìm kiếm
  pagination.active.currentPage = 1;
  pagination.disabled.currentPage = 1;
});

// --- HELPER FUNCTIONS ---

function changePage(type, page) {
  pagination[type].currentPage = page;
}

function setActiveTab(tabName) {
  activeTab.value = tabName;
}

// Lấy ngày đã định dạng
function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
}

// Tính toán số thứ tự tiếp theo (MAX + 1)
function calculateNextOrder() {
  if (categories.value.length === 0) return 1;
  const maxOrder = Math.max(...categories.value.map(c => parseInt(c.order) || 0));
  return maxOrder + 1;
}

// Reset form
function resetForm() {
  const nextOrder = calculateNextOrder();
  Object.assign(formData, { id: null, name: '', description: '', icon: '', order: nextOrder, status: 'active' });
  Object.assign(errors, { name: '', description: '', icon: '', order: '' });
}

// Validate form
function validateForm() {
  Object.assign(errors, { name: '', description: '', icon: '', order: '' });
  let isValid = true;
  const orderValue = formData.order;

  if (!formData.name.trim()) {
    errors.name = 'Vui lòng nhập tên danh mục.';
    isValid = false;
  }

  if (!formData.icon.trim()) {
    errors.icon = 'Vui lòng nhập mã Icon.';
    isValid = false;
  }

  if (orderValue === '' || isNaN(orderValue) || orderValue === null || Number(orderValue) < 1) {
    errors.order = 'Thứ tự phải là số nguyên và lớn hơn hoặc bằng 1.';
    isValid = false;
  } else if (!Number.isInteger(Number(orderValue))) {
    errors.order = 'Thứ tự phải là số nguyên.';
    isValid = false;
  }

  return isValid;
}

function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  modalInstance.value.show();
}

function openEditModal(category) {
  Object.assign(formData, {
    id: category.id,
    name: category.name,
    description: category.description,
    icon: category.icon,
    order: category.order || 1,
    status: category.status || 'active'
  });
  Object.assign(errors, { name: '', description: '', icon: '', order: '' });
  isEditMode.value = true;
  modalInstance.value.show();
}

function openViewModal(category) {
  viewingCategory.value = category;
  viewModalInstance.value.show();
}

// --- LIFECYCLE ---
onMounted(() => {
  fetchCategories();
  if (modalRef.value) {
    modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- API METHODS ---

// Lấy danh sách danh mục
async function fetchCategories() {
  // Smart Spinner: Chỉ hiện loading lớn nếu chưa có dữ liệu
  if (categories.value.length === 0) {
    isLoading.value = true;
  }
  
  try {
    const response = await apiService.get(`admin/categories`);
    categories.value = response.data.map(cat => ({
      ...cat,
      created_at: cat.created_at || new Date().toISOString()
    })).sort((a, b) => a.order - b.order); // Sắp xếp theo thứ tự
  } catch (error) {
    console.error("Lỗi tải danh mục:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách danh mục.', 'error');
  } finally {
    isLoading.value = false;
  }
}

// Lưu danh mục (Tạo mới / Cập nhật)
async function handleSave() {
  if (!validateForm()) return;

  const currentId = formData.id;
  const newNameUpper = formData.name.trim().toUpperCase();
  const newOrderValue = parseInt(formData.order);

  // Check trùng thứ tự (chỉ cảnh báo)
  const isOrderDuplicate = categories.value.some(cat =>
    parseInt(cat.order) === newOrderValue && cat.id !== currentId
  );
  if (isOrderDuplicate) {
    errors.order = `Số thứ tự (${newOrderValue}) đã bị sử dụng. Vui lòng chọn số khác.`;
    Swal.fire('Lỗi', errors.order, 'error');
    return;
  }

  // Check trùng tên
  const isNameDuplicate = categories.value.some(cat =>
    cat.name.toUpperCase() === newNameUpper && cat.id !== currentId
  );
  if (isNameDuplicate) {
    const result = await Swal.fire({
      title: 'Tên Danh mục bị trùng!',
      text: `Danh mục "${formData.name}" đã tồn tại. Tiếp tục?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Đồng ý',
      cancelButtonText: 'Hủy'
    });
    if (!result.isConfirmed) return;
  }

  // Optimistic Update Logic could be complex here due to IDs, 
  // so we stick to standard flow but manage loading state smartly.
  
  let payload = {
    name: formData.name,
    description: formData.description,
    icon: formData.icon,
    order: newOrderValue,
    status: formData.status
  };

  try {
    if (isEditMode.value) {
      await apiService.put(`admin/categories/${formData.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật danh mục!', 'success');
    } else {
      await apiService.post(`admin/categories`, payload);
      Swal.fire('Thành công', 'Đã tạo danh mục mới!', 'success');
    }
    modalInstance.value.hide();
    fetchCategories(); // Silent refresh
  } catch (apiError) {
    console.error("Lỗi lưu:", apiError);
    if (apiError.response?.data?.errors?.name) {
      errors.name = apiError.response.data.errors.name[0];
    } else {
      Swal.fire('Thất bại', 'Đã có lỗi xảy ra.', 'error');
    }
  }
}

// Xử lý bật/tắt trạng thái (Optimistic UI)
async function toggleStatus(category) {
  const oldStatus = category.status;
  const newStatus = category.status === 'active' ? 'disabled' : 'active';
  const confirmText = `Bạn muốn chuyển sang ${newStatus === 'active' ? 'HOẠT ĐỘNG' : 'VÔ HIỆU HÓA'}?`;

  const result = await Swal.fire({
    title: 'Thay đổi trạng thái',
    text: `Danh mục: "${category.name}"`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Đồng ý',
    cancelButtonText: 'Hủy'
  });

  if (result.isConfirmed) {
    // 1. Update UI immediately (Item sẽ tự động bay sang tab kia)
    category.status = newStatus;

    // 2. Call API
    try {
      await apiService.patch(`admin/categories/${category.id}`, { status: newStatus });
      
      // Toast thông báo nhỏ
      const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
      });
      Toast.fire({ icon: 'success', title: 'Cập nhật thành công' });

    } catch (error) {
      // Rollback nếu lỗi
      category.status = oldStatus;
      Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
    }
  }
}

// Xóa danh mục
async function handleDelete(category) {
  const result = await Swal.fire({
    title: 'Bạn chắc chắn chứ?',
    text: `Sẽ xóa vĩnh viễn "${category.name}"!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Xóa',
    cancelButtonText: 'Hủy'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`admin/categories/${category.id}`);
      
      // Xóa khỏi list local
      categories.value = categories.value.filter(c => c.id !== category.id);
      
      Swal.fire('Đã xóa!', 'Danh mục đã bị xóa.', 'success');
    } catch (error) {
      console.error("Lỗi xóa:", error);
      Swal.fire('Lỗi', 'Không thể xóa danh mục này.', 'error');
    }
  }
}
</script>

<template>
  <!-- HEADER -->
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Danh mục</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">Danh mục</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="app-content">
    <div class="container-fluid">
      <div class="card shadow-sm">
        <div class="card-header border-bottom-0 pb-0">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h3 class="card-title">Danh sách Danh mục</h3>
            
            <!-- TOOLBAR -->
            <div class="d-flex gap-2 flex-grow-1 justify-content-end" style="max-width: 600px;">
              <div class="input-group" style="min-width: 200px;">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm kiếm theo tên..." v-model="searchQuery">
              </div>
              <button type="button" class="btn btn-primary text-nowrap" @click="openCreateModal">
                <i class="bi bi-plus-lg"></i> Thêm mới
              </button>
            </div>
          </div>

          <!-- TABS NAVIGATION -->
          <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'active' }" href="#" @click.prevent="setActiveTab('active')">
                <i class="bi bi-check-circle me-1 text-success"></i> Hoạt động
                <span class="badge rounded-pill bg-success ms-1">{{ activeList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'disabled' }" href="#" @click.prevent="setActiveTab('disabled')">
                <i class="bi bi-dash-circle me-1 text-secondary"></i> Vô hiệu hóa
                <span class="badge rounded-pill bg-secondary ms-1">{{ disabledList.length }}</span>
              </a>
            </li>
          </ul>
        </div>

        <div class="card-body p-0">
          
          <!-- Loading Spinner (Chỉ hiện lần đầu) -->
          <div v-if="isLoading && categories.length === 0" class="text-center p-5">
            <div class="spinner-border text-primary" role="status"></div>
          </div>

          <div v-else class="tab-content p-0">
            
            <!-- TAB: ACTIVE -->
            <div class="tab-pane fade show active" v-if="activeTab === 'active'">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 50px;">ID</th>
                      <th style="width: 80px;" class="text-center">Icon</th>
                      <th>Tên danh mục</th>
                      <th class="d-none d-md-table-cell">Mô tả</th>
                      <th style="width: 80px;" class="text-center">Thứ tự</th>
                      <th style="width: 120px;" class="text-center">Trạng thái</th>
                      <th style="width: 180px;" class="text-center">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedActive.data.length === 0">
                      <td colspan="7" class="text-center py-4 text-muted">Không có danh mục hoạt động nào.</td>
                    </tr>
                    <tr v-for="category in pagedActive.data" :key="category.id">
                      <td>{{ category.id }}</td>
                      <td class="text-center fs-4 text-primary">
                        <span v-if="category.icon" v-html="category.icon"></span>
                        <span v-else class="text-muted fs-6 small">(Trống)</span>
                      </td>
                      <td class="fw-bold">{{ category.name }}</td>
                      <td class="d-none d-md-table-cell text-truncate" style="max-width: 200px;" :title="category.description">
                        {{ category.description || '...' }}
                      </td>
                      <td class="text-center">{{ category.order }}</td>
                      <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                          <input class="form-check-input" type="checkbox" role="switch"
                            style="width: 2.5em; height: 1.25em; cursor: pointer;"
                            :checked="true"
                            @click.prevent="toggleStatus(category)" title="Nhấn để vô hiệu hóa">
                        </div>
                      </td>
                      <td class="text-center">
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-secondary" title="Xem" @click="openViewModal(category)"><i class="bi bi-eye"></i></button>
                          <button class="btn btn-outline-primary" title="Sửa" @click="openEditModal(category)"><i class="bi bi-pencil"></i></button>
                          <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(category)"><i class="bi bi-trash"></i></button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination Active -->
              <div v-if="pagedActive.totalPages > 1" class="d-flex justify-content-end p-3">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: pagination.active.currentPage === 1 }">
                    <a class="page-link" href="#" @click.prevent="changePage('active', pagination.active.currentPage - 1)">&laquo;</a>
                  </li>
                  <li v-for="p in pagedActive.totalPages" :key="p" class="page-item" :class="{ active: pagination.active.currentPage === p }">
                    <a class="page-link" href="#" @click.prevent="changePage('active', p)">{{ p }}</a>
                  </li>
                  <li class="page-item" :class="{ disabled: pagination.active.currentPage === pagedActive.totalPages }">
                    <a class="page-link" href="#" @click.prevent="changePage('active', pagination.active.currentPage + 1)">&raquo;</a>
                  </li>
                </ul>
              </div>
            </div>

            <!-- TAB: DISABLED -->
            <div class="tab-pane fade show active" v-if="activeTab === 'disabled'">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 50px;">ID</th>
                      <th style="width: 80px;" class="text-center">Icon</th>
                      <th>Tên danh mục</th>
                      <th class="d-none d-md-table-cell">Mô tả</th>
                      <th style="width: 80px;" class="text-center">Thứ tự</th>
                      <th style="width: 120px;" class="text-center">Trạng thái</th>
                      <th style="width: 180px;" class="text-center">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedDisabled.data.length === 0">
                      <td colspan="7" class="text-center py-4 text-muted">Không có danh mục bị vô hiệu hóa.</td>
                    </tr>
                    <tr v-for="category in pagedDisabled.data" :key="category.id" class="text-muted bg-light">
                      <td>{{ category.id }}</td>
                      <td class="text-center fs-4 text-secondary grayscale">
                        <span v-if="category.icon" v-html="category.icon"></span>
                      </td>
                      <td class="fw-bold">{{ category.name }}</td>
                      <td class="d-none d-md-table-cell text-truncate" style="max-width: 200px;">
                        {{ category.description || '...' }}
                      </td>
                      <td class="text-center">{{ category.order }}</td>
                      <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                          <input class="form-check-input" type="checkbox" role="switch"
                            style="width: 2.5em; height: 1.25em; cursor: pointer;"
                            :checked="false"
                            @click.prevent="toggleStatus(category)" title="Nhấn để kích hoạt lại">
                        </div>
                      </td>
                      <td class="text-center">
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-secondary" title="Xem" @click="openViewModal(category)"><i class="bi bi-eye"></i></button>
                          <button class="btn btn-outline-primary" title="Sửa" @click="openEditModal(category)"><i class="bi bi-pencil"></i></button>
                          <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(category)"><i class="bi bi-trash"></i></button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination Disabled -->
              <div v-if="pagedDisabled.totalPages > 1" class="d-flex justify-content-end p-3">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: pagination.disabled.currentPage === 1 }">
                    <a class="page-link" href="#" @click.prevent="changePage('disabled', pagination.disabled.currentPage - 1)">&laquo;</a>
                  </li>
                  <li v-for="p in pagedDisabled.totalPages" :key="p" class="page-item" :class="{ active: pagination.disabled.currentPage === p }">
                    <a class="page-link" href="#" @click.prevent="changePage('disabled', p)">{{ p }}</a>
                  </li>
                  <li class="page-item" :class="{ disabled: pagination.disabled.currentPage === pagedDisabled.totalPages }">
                    <a class="page-link" href="#" @click.prevent="changePage('disabled', pagination.disabled.currentPage + 1)">&raquo;</a>
                  </li>
                </ul>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL (Create/Edit) -->
  <div class="modal fade" id="categoryModal" ref="modalRef" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{ isEditMode ? 'Chỉnh sửa Danh mục' : 'Tạo Danh mục mới' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSave" id="categoryForm">
            <div class="row">
              <!-- CỘT BÊN TRÁI: ICON PREVIEW & TRẠNG THÁI -->
              <div class="col-md-4 text-center mb-3">
                <div class="mb-3">
                  <label class="form-label fw-bold">Xem trước Icon</label>
                  <div class="img-thumbnail d-flex align-items-center justify-content-center bg-light"
                    style="width: 150px; height: 150px; font-size: 4rem; margin: auto;">
                    <span v-if="formData.icon" v-html="formData.icon"></span>
                    <i v-else class="bi bi-image text-muted"></i>
                  </div>
                </div>

                <div class="mb-3" v-if="isEditMode">
                  <label for="modalStatus" class="form-label fw-bold">Trạng thái</label>
                  <select class="form-select" id="modalStatus" v-model="formData.status">
                    <option value="active">Hoạt động (Hiển thị)</option>
                    <option value="disabled">Vô hiệu hóa (Ẩn)</option>
                  </select>
                </div>
              </div>

              <!-- CỘT BÊN PHẢI: THÔNG TIN CHÍNH -->
              <div class="col-md-8">
                <div class="mb-3">
                  <label for="name" class="form-label required">Tên danh mục</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" id="name"
                    v-model="formData.name" placeholder="Ví dụ: Điện thoại">
                  <div class="invalid-feedback" v-if="errors.name">{{ errors.name }}</div>
                </div>

                <div class="mb-3">
                  <label for="icon" class="form-label required">Icon (Mã HTML)</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.icon }" id="icon"
                    v-model="formData.icon" placeholder='Ví dụ: <i class="fa-solid fa-mobile"></i>'>
                  <div class="invalid-feedback" v-if="errors.icon">{{ errors.icon }}</div>
                  <div class="form-text">Nhập đoạn mã HTML từ FontAwesome hoặc Bootstrap Icons.</div>
                </div>

                <div class="mb-3">
                  <label for="order" class="form-label required">Thứ tự sắp xếp</label>
                  <input type="number" class="form-control" :class="{ 'is-invalid': errors.order }" id="order"
                    v-model="formData.order" placeholder="Ví dụ: 1, 2, 3...">
                  <div class="invalid-feedback" v-if="errors.order">{{ errors.order }}</div>
                  <div class="form-text">Số nhỏ hơn sẽ hiển thị trước.</div>
                </div>

                <div class="mb-3">
                  <label for="description" class="form-label">Mô tả</label>
                  <textarea class="form-control" id="description" rows="3" v-model="formData.description"
                    placeholder="Mô tả ngắn về danh mục..."></textarea>
                </div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="categoryForm" class="btn btn-primary" :disabled="isLoading">
            <span v-if="isLoading" class="spinner-border spinner-border-sm me-1" role="status"
              aria-hidden="true"></span>
            {{ isEditMode ? 'Lưu thay đổi' : 'Tạo danh mục' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL (Xem Chi Tiết) -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <!-- Status Badge -->
          <div class="position-absolute top-0 start-0 m-3">
            <span :class="['badge', viewingCategory.status === 'active' ? 'bg-success' : 'bg-secondary']">
              {{ viewingCategory.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
            </span>
          </div>

          <!-- Icon Preview -->
          <div class="text-center mb-4 mt-3">
            <div
              class="img-thumbnail d-flex align-items-center justify-content-center bg-light shadow-sm rounded-circle"
              style="width: 120px; height: 120px; font-size: 3rem; margin: auto;">
              <span v-if="viewingCategory.icon" v-html="viewingCategory.icon"></span>
              <i v-else class="bi bi-image text-muted"></i>
            </div>
            <h4 class="mt-3 mb-1">{{ viewingCategory.name }}</h4>
            <p class="text-muted mb-0">ID: {{ viewingCategory.id }}</p>
          </div>

          <!-- Details List -->
          <div class="list-group list-group-flush">
            <div class="list-group-item px-0">
              <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1"><i class="bi bi-list-ol me-3 text-primary"></i>Thứ tự</h6>
                <span class="badge bg-primary rounded-pill">{{ viewingCategory.order }}</span>
              </div>
            </div>
             <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-card-text me-3 text-muted"></i>Mô tả</h6>
               <p class="mb-1 text-muted small">{{ viewingCategory.description || 'Không có mô tả.' }}</p>
            </div>
            <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-code-slash me-3 text-info"></i>Mã Icon</h6>
               <code class="mb-1 text-dark small">{{ viewingCategory.icon || 'Chưa có icon.' }}</code>
            </div>
            <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-calendar-event me-3 text-muted"></i>Ngày tạo</h6>
               <p class="mb-1 text-muted small">{{ getFormattedDate(viewingCategory.created_at) }}</p>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-primary px-4"
            @click="() => { viewModalInstance.hide(); openEditModal(viewingCategory); }">
            <i class="bi bi-pencil me-2"></i> Chỉnh sửa danh mục
          </button>
        </div>
      </div>
    </div>
  </div>

</template>

<style scoped>
.nav-tabs .nav-link {
  cursor: pointer;
  color: #6c757d;
  font-weight: 500;
}
.nav-tabs .nav-link.active {
  color: #0d6efd;
  border-bottom-color: #fff;
}
.pagination .page-link {
  cursor: pointer;
}
.table td .btn {
  margin: 2px;
}
.required::after {
  content: " *";
  color: red;
}
.grayscale {
  filter: grayscale(100%);
  opacity: 0.6;
}
</style>