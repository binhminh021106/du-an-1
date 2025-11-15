<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CẤU HÌNH & STATE ---
const isLoading = ref(true);
const isEditMode = ref(false);
const categories = ref([]);

// State cho Modal Thêm/Sửa
const modalInstance = ref(null);
const modalRef = ref(null);

// State cho Modal Xem
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingCategory = ref({});

// State cho Tìm kiếm và Phân trang
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10); // Hiển thị 10 mục mỗi trang

// Form data & Validation
const formData = reactive({ id: null, name: '', description: '', icon: '', order: 1, status: 'active' });
const errors = reactive({ name: '', description: '', icon: '', order: '' });

// --- LIFECYCLE ---
onMounted(() => {
  fetchCategories();
  if (modalRef.value) {
    modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
  }
  // Khởi tạo modal xem
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- COMPUTED ---

// Lọc danh mục dựa trên tìm kiếm
const filteredCategories = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return categories.value; // Trả về danh sách đã sắp xếp nếu không tìm kiếm
  }
  return categories.value.filter(category =>
    category.name.toLowerCase().includes(query) ||
    (category.description && category.description.toLowerCase().includes(query))
  );
});

// Tính tổng số trang
const totalPages = computed(() => {
  return Math.ceil(filteredCategories.value.length / itemsPerPage.value);
});

// Lấy danh sách danh mục đã phân trang
const paginatedCategories = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  const end = start + itemsPerPage.value;
  return filteredCategories.value.slice(start, end);
});

// --- WATCHERS ---

// Reset về trang 1 khi tìm kiếm
watch(searchQuery, () => {
  currentPage.value = 1;
});


// --- HELPER FUNCTIONS ---

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

// Mở modal (Thêm mới)
function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  modalInstance.value.show();
}

// Mở modal (Chỉnh sửa)
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

// Mở modal (Xem)
function openViewModal(category) {
  viewingCategory.value = category;
  viewModalInstance.value.show();
}


// --- API METHODS ---

// Lấy danh sách danh mục (Sắp xếp theo 'order')
async function fetchCategories() {
  isLoading.value = true;
  try {
    const response = await apiService.get(`/categories?_sort=order&_order=asc`);
    // Gán thêm created_at nếu chưa có
    categories.value = response.data.map(cat => ({
      ...cat,
      created_at: cat.created_at || new Date().toISOString()
    }));
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

  // 1. Kiểm tra trùng SỐ THỨ TỰ
  const isOrderDuplicate = categories.value.some(cat =>
    parseInt(cat.order) === newOrderValue && cat.id !== currentId
  );

  if (isOrderDuplicate) {
    errors.order = `Số thứ tự (${newOrderValue}) đã bị sử dụng. Vui lòng chọn số khác.`;
    Swal.fire('Lỗi', errors.order, 'error');
    return;
  }

  // 2. Kiểm tra trùng TÊN
  const isNameDuplicate = categories.value.some(cat =>
    cat.name.toUpperCase() === newNameUpper && cat.id !== currentId
  );

  if (isNameDuplicate) {
    const result = await Swal.fire({
      title: 'Tên Danh mục bị trùng!',
      text: `Danh mục "${formData.name}" đã tồn tại. Bạn có chắc chắn muốn tạo/cập nhật danh mục trùng tên này không?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Đồng ý lưu',
      cancelButtonText: 'Hủy bỏ'
    });

    if (!result.isConfirmed) {
      return;
    }
  }

  isLoading.value = true;

  let payload = {
    name: formData.name,
    description: formData.description,
    icon: formData.icon,
    order: newOrderValue,
    status: formData.status
  };

  try {
    if (isEditMode.value) {
      await apiService.put(`/categories/${formData.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật danh mục!', 'success');
    } else {
      payload.created_at = new Date().toISOString();
      await apiService.post(`/categories`, payload);
      Swal.fire('Thành công', 'Đã tạo danh mục mới!', 'success');
    }
    modalInstance.value.hide();
    fetchCategories(); // Tải lại danh sách
  } catch (apiError) {
    console.error("Lỗi lưu:", apiError);
    if (apiError.response?.data?.errors?.name) {
      errors.name = apiError.response.data.errors.name[0];
    } else {
      Swal.fire('Thất bại', 'Đã có lỗi xảy ra.', 'error');
    }
  } finally {
    isLoading.value = false;
  }
}

// Xử lý bật/tắt trạng thái
async function toggleStatus(category) {
  const newStatus = category.status === 'active' ? 'disabled' : 'active';
  const confirmText = `Bạn có chắc chắn muốn ${newStatus === 'active' ? 'KÍCH HOẠT' : 'VÔ HIỆU HÓA'} danh mục "${category.name}"?`;

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
    category.status = newStatus;
    try {
      await apiService.patch(`/categories/${category.id}`, { status: newStatus });
      Swal.fire('Thành công', `Đã ${newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa'} danh mục.`, 'success');
    } catch (error) {
      console.error("Lỗi cập nhật trạng thái:", error);
      category.status = newStatus === 'active' ? 'disabled' : 'active';
      Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
      fetchCategories();
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
      await apiService.delete(`/categories/${category.id}`);
      Swal.fire('Đã xóa!', 'Danh mục đã bị xóa.', 'success');
      // Nếu trang hiện tại trống sau khi xóa, lùi về trang trước
      if (paginatedCategories.value.length === 1 && currentPage.value > 1) {
        currentPage.value--;
      }
      fetchCategories(); // Tải lại danh sách
    } catch (error) {
      console.error("Lỗi xóa:", error);
      Swal.fire('Lỗi', 'Không thể xóa danh mục này.', 'error');
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
      <div class="card mb-4">
        <!-- Card Header với Tìm kiếm và Nút Thêm -->
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col-md-6 col-12 mb-2 mb-md-0">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm kiếm theo tên, mô tả..." v-model="searchQuery">
              </div>
            </div>
            <div class="col-md-6 col-12 text-md-end">
              <button type="button" class="btn btn-primary" @click="openCreateModal">
                <i class="bi bi-plus-lg"></i> Thêm mới Danh mục
              </button>
            </div>
          </div>
        </div>

        <!-- Card Body - Bảng Dữ Liệu -->
        <div class="card-body p-0">
          <div class="table-responsive">
            <table v-if="!isLoading" class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 50px;">ID</th>
                  <th style="width: 80px;" class="text-center">Icon</th>
                  <th>Tên danh mục</th>
                  <th class="d-none d-md-table-cell">Mô tả</th>
                  <th style="width: 80px;">Thứ tự</th>
                  <th style="width: 120px;">Trạng thái</th>
                  <th style="width: 150px;" class="text-center">Hành động</th>
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
                <tr v-else-if="filteredCategories.length === 0">
                  <td colspan="7" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    {{ searchQuery ? 'Không tìm thấy danh mục nào.' : 'Chưa có danh mục nào.' }}
                  </td>
                </tr>
                <!-- Data Rows -->
                <tr v-for="category in paginatedCategories" :key="category.id">
                  <td>{{ category.id }}</td>
                  <td class="text-center fs-4 text-primary">
                    <span v-if="category.icon" v-html="category.icon"></span>
                    <span v-else class="text-muted fs-6 small">(Trống)</span>
                  </td>
                  <td class="fw-bold">{{ category.name }}</td>
                  <td class="d-none d-md-table-cell" style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" :title="category.description">
                    {{ category.description || '...' }}
                  </td>
                  <td class="text-center">{{ category.order }}</td>
                  <td>
                    <!-- Đổi thành badge hiển thị trạng thái -->
                    <span :class="['badge', category.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
                      {{ category.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
                    </span>
                  </td>
                  <td class="text-center">
                    <!-- Bọc trong d-flex để căn chỉnh -->
                    <div class="d-flex justify-content-center align-items-center">
                      <!-- Thêm Toggle Switch vào đây -->
                      <div class="form-check form-switch d-inline-block align-middle me-3" title="Kích hoạt/Vô hiệu hóa">
                        <input class="form-check-input" type="checkbox" role="switch"
                          style="width: 2.5em; height: 1.25em; cursor: pointer;"
                          :id="'statusSwitch-' + category.id"
                          :checked="category.status === 'active'"
                          @click.prevent="toggleStatus(category)">
                      </div>
                      <!-- Nhóm nút -->
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" title="Xem chi tiết" @click="openViewModal(category)">
                          <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditModal(category)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(category)">
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </div> <!-- <- Thẻ </div> này đã bị thiếu. Thêm nó vào đây -->
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Card Footer - Phân Trang -->
        <div class="card-footer clearfix" v-if="totalPages > 1">
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
              Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} đến
              {{ Math.min(currentPage * itemsPerPage, filteredCategories.length) }}
              trong tổng số {{ filteredCategories.length }} danh mục
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
.table td .btn {
  margin: 2px 0;
}
/* Xóa style cho form-switch vì đã dùng inline-style để nhất quán */
/* .form-switch .form-check-input {
  width: 2.5em;
  height: 1.5em;
  margin-right: 0.5em;
} */

/* Thêm CSS cho label bắt buộc */
.required::after {
  content: " *";
  color: red;
}
</style>