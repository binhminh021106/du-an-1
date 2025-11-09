<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CẤU HÌNH & STATE ---
const API_URL = import.meta.env.VITE_API_BASE_URL;
const isLoading = ref(true);
const isEditMode = ref(false);
const categories = ref([]);

// Modal Bootstrap
const modalInstance = ref(null);
const modalRef = ref(null);

// Form data & Validation
// Thêm trường 'icon', 'order', và 'status' vào formData (mặc định là 'active')
const formData = reactive({ id: null, name: '', description: '', icon: '', order: 1, status: 'active' }); 
const errors = reactive({ name: '', description: '', icon: '', order: '' });

// --- LIFECYCLE ---
onMounted(() => {
  fetchCategories();
  if (modalRef.value) {
    modalInstance.value = new Modal(modalRef.value);
  }
});

// --- HELPER FUNCTIONS ---

// Tính toán số thứ tự tiếp theo (MAX + 1)
function calculateNextOrder() {
  if (categories.value.length === 0) return 1;
  // Lấy ra giá trị order lớn nhất và cộng thêm 1
  const maxOrder = Math.max(...categories.value.map(c => parseInt(c.order) || 0));
  return maxOrder + 1;
}

// Reset form về mặc định (order tự động điền MAX + 1 khi tạo mới, status là active)
function resetForm() {
  const nextOrder = calculateNextOrder();
  Object.assign(formData, { id: null, name: '', description: '', icon: '', order: nextOrder, status: 'active' });
  Object.assign(errors, { name: '', description: '', icon: '', order: '' });
}

// Validate form (Tên, Icon, Order không được rỗng, Order phải là số nguyên >= 1)
function validateForm() {
  // Reset all errors
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
  
  // Order phải là số nguyên và >= 1
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
  resetForm(); // Order tự động là MAX + 1
  isEditMode.value = false;
  modalInstance.value.show();
}

// Mở modal (Chỉnh sửa)
function openEditModal(category) {
  // Gán tất cả các trường, bao gồm cả status
  Object.assign(formData, {
    id: category.id,
    name: category.name,
    description: category.description,
    icon: category.icon, 
    order: category.order || 1,
    status: category.status || 'active'
  });
  Object.assign(errors, { name: '', description: '', icon: '', order: '' }); // Reset errors
  isEditMode.value = true;
  modalInstance.value.show();
}


// --- API METHODS ---

// Lấy danh sách danh mục (Sắp xếp theo trường 'order')
async function fetchCategories() {
  isLoading.value = true;
  try {
    // Sắp xếp theo thứ tự (order) tăng dần
    const response = await axios.get(`${API_URL}/categories?_sort=order&_order=asc`);
    categories.value = response.data;
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

  // 1. Kiểm tra trùng SỐ THỨ TỰ (BẮT BUỘC DUY NHẤT)
  const isOrderDuplicate = categories.value.some(cat => 
    parseInt(cat.order) === newOrderValue && cat.id !== currentId
  );
  
  if (isOrderDuplicate) {
    errors.order = `Số thứ tự (${newOrderValue}) đã bị sử dụng. Vui lòng chọn số khác.`;
    Swal.fire('Lỗi', errors.order, 'error');
    return;
  }

  // 2. Kiểm tra trùng TÊN (Nếu trùng, hỏi xác nhận)
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

  // Nếu đã qua kiểm tra, tiến hành lưu
  isLoading.value = true;
  
  let payload = {
    name: formData.name,
    description: formData.description,
    icon: formData.icon,
    order: newOrderValue,
    status: formData.status // Thêm trạng thái
  };
  
  try {
    if (isEditMode.value) {
      // --- CHẾ ĐỘ CẬP NHẬT (UPDATE) ---
      await axios.put(`${API_URL}/categories/${formData.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật danh mục!', 'success');
    } else {
      // --- CHẾ ĐỘ TẠO MỚI (CREATE) ---
      payload.created_at = new Date().toISOString(); 
      
      await axios.post(`${API_URL}/categories`, payload);
      Swal.fire('Thành công', 'Đã tạo danh mục mới!', 'success');
    }
    modalInstance.value.hide();
    fetchCategories();
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
        // Cập nhật trạng thái trong bộ nhớ trước (Optimistic update)
        category.status = newStatus; 
        
        try {
            // Gửi yêu cầu cập nhật API
            await axios.patch(`${API_URL}/categories/${category.id}`, { status: newStatus });
            Swal.fire('Thành công', `Đã ${newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa'} danh mục.`, 'success');
        } catch (error) {
            console.error("Lỗi cập nhật trạng thái:", error);
            // Nếu lỗi, hoàn nguyên trạng thái
            category.status = newStatus === 'active' ? 'disabled' : 'active'; 
            Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
            // Tải lại dữ liệu để đảm bảo đồng bộ
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
      await axios.delete(`${API_URL}/categories/${category.id}`);
      Swal.fire('Đã xóa!', 'Danh mục đã bị xóa.', 'success');
      fetchCategories();
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
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Danh sách Danh mục (Đang sắp xếp theo Thứ tự)</h3>
              <div class="card-tools">
                <button type="button" class="btn btn-primary" @click="openCreateModal">
                  <i class="bi bi-plus-lg"></i> Thêm mới
                </button>
              </div>
            </div>
            <div class="card-body">
              <!-- Loading State -->
              <div v-if="isLoading" class="text-center">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>

              <!-- Data Table -->
              <table v-else class="table table-bordered table-hover align-middle">
                <thead>
                  <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 80px;" class="text-center">Icon</th>
                    <th>Tên danh mục</th>
                    <th style="width: 80px;">Thứ tự</th>
                    <th style="width: 120px;">Trạng thái</th>
                    <th style="width: 150px;">Ngày tạo</th>
                    <th style="width: 150px;">Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Empty State -->
                  <tr v-if="categories.length === 0">
                    <td colspan="7" class="text-center">Không có dữ liệu</td>
                  </tr>
                  <!-- Data Rows -->
                  <tr v-for="category in categories" :key="category.id">
                    <td>{{ category.id }}</td>
                    <td class="text-center fs-4 text-primary">
                      <span v-if="category.icon" v-html="category.icon"></span>
                      <span v-else class="text-muted fs-6 small">(Trống)</span>
                    </td>
                    <td class="fw-bold">{{ category.name }}</td>
                    <!-- Cột thứ tự sắp xếp -->
                    <td class="text-center">{{ category.order }}</td>
                    
                    <!-- Cột Trạng thái (Sử dụng Bootstrap Switch) -->
                    <td>
                        <div class="form-check form-switch">
                            <input 
                                class="form-check-input" 
                                type="checkbox" 
                                :id="'statusSwitch-' + category.id"
                                :checked="category.status === 'active'"
                                @change="toggleStatus(category)"
                            >
                            <label class="form-check-label" :for="'statusSwitch-' + category.id">
                                <span :class="category.status === 'active' ? 'text-success' : 'text-danger'">
                                    {{ category.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
                                </span>
                            </label>
                        </div>
                    </td>
                    
                    <td>
                      {{ category.created_at ? new Date(category.created_at).toLocaleDateString('vi-VN') : 'N/A' }}
                    </td>
                    <td>
                      <button class="btn btn-warning btn-sm me-2" @click="openEditModal(category)">
                        <i class="bi bi-pencil"></i> Sửa
                      </button>
                      <button class="btn btn-danger btn-sm" @click="handleDelete(category)">
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

  <!-- MODAL (Create/Edit) -->
  <div class="modal fade" id="categoryModal" ref="modalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{ isEditMode ? 'Chỉnh sửa Danh mục' : 'Tạo Danh mục mới' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSave">
            
            <!-- Tên danh mục -->
            <div class="mb-3">
              <label for="name" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
              <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" id="name"
                v-model="formData.name" placeholder="Ví dụ: Điện thoại">
              <div class="invalid-feedback" v-if="errors.name">{{ errors.name }}</div>
            </div>

            <!-- Icon HTML -->
            <div class="mb-3">
              <label for="icon" class="form-label">Icon (Mã HTML) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">
                  <!-- Preview icon ngay trong form nếu có nhập -->
                  <span v-if="formData.icon" v-html="formData.icon"></span>
                  <i v-else class="bi bi-image"></i>
                </span>
                <input type="text" class="form-control" :class="{ 'is-invalid': errors.icon }" id="icon" v-model="formData.icon"
                  placeholder='Ví dụ: <i class="fa-solid fa-mobile"></i>'>
                <div class="invalid-feedback" v-if="errors.icon">{{ errors.icon }}</div>
              </div>
              <div class="form-text">Nhập đoạn mã HTML icon từ FontAwesome hoặc Bootstrap Icons.</div>
            </div>
            
            <!-- Thứ tự sắp xếp (Order) -->
            <div class="mb-3">
              <label for="order" class="form-label">Thứ tự sắp xếp <span class="text-danger">*</span></label>
              <input type="number" class="form-control" :class="{ 'is-invalid': errors.order }" id="order"
                v-model="formData.order" placeholder="Ví dụ: 1, 2, 3...">
              <div class="invalid-feedback" v-if="errors.order">{{ errors.order }}</div>
            </div>

            <!-- Mô tả -->
            <div class="mb-3">
              <label for="description" class="form-label">Mô tả</label>
              <textarea class="form-control" id="description" rows="3" v-model="formData.description"
                placeholder="Mô tả ngắn về danh mục..."></textarea>
            </div>
            
            <!-- Trạng thái (Chỉ hiện khi chỉnh sửa, mặc định là Active) -->
             <div class="mb-3" v-if="isEditMode">
                <label class="form-label">Trạng thái</label>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="modalStatusSwitch" 
                        v-model="formData.status"
                        true-value="active"
                        false-value="disabled"
                    >
                    <label class="form-check-label" for="modalStatusSwitch">
                        <span :class="formData.status === 'active' ? 'text-success' : 'text-danger'">
                            {{ formData.status === 'active' ? 'Hoạt động (Hiển thị)' : 'Vô hiệu hóa (Ẩn)' }}
                        </span>
                    </label>
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
  margin: 2px 0;
}
/* Đảm bảo switch hiển thị đúng */
.form-switch .form-check-input {
    width: 2.5em; 
    height: 1.5em;
    margin-right: 0.5em;
}
</style>