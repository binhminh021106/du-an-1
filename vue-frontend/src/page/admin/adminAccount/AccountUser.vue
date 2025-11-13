<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

const customers = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

// State Modal
const customerModalRef = ref(null);
const customerModalInstance = ref(null);
const viewModalRef = ref(null);
const viewModalInstance = ref(null);

const isEditMode = ref(false);
const viewingCustomer = ref({});

// Form data
const formData = reactive({
  id: null,
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  address: '',
  avatar: '', 
  status: 'active', 
});

const avatarFile = ref(null);
const avatarPreview = ref(null);

const errors = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
});

// --- COMPUTED ---
const filteredCustomers = computed(() => {
  if (!searchQuery.value) {
    return customers.value;
  }
  const query = searchQuery.value.toLowerCase();
  return customers.value.filter(customer =>
    customer.name.toLowerCase().includes(query) ||
    customer.email.toLowerCase().includes(query) ||
    (customer.phone && customer.phone.includes(query))
  );
});

const totalPages = computed(() => {
  return Math.ceil(filteredCustomers.value.length / itemsPerPage.value);
});

const paginatedCustomers = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  const end = start + itemsPerPage.value;
  return filteredCustomers.value.slice(start, end);
});

// --- WATCHERS ---
watch(searchQuery, () => {
  currentPage.value = 1; // Reset to first page on search
});

// --- LIFECYCLE ---
onMounted(() => {
  fetchCustomers();
  if (customerModalRef.value) {
    customerModalInstance.value = new Modal(customerModalRef.value, { backdrop: 'static' });
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- METHODS ---

async function fetchCustomers() {
  isLoading.value = true;
  try {
    // THAY ĐỔI 1: Gọi /users?role=user để CHỈ lấy khách hàng
    const response = await apiService.get(`/users?role=user`);
    
    // Sắp xếp theo ID mới nhất
    customers.value = response.data.map(customer => ({
      ...customer,
      status: customer.status || 'active' 
    })).sort((a, b) => (b.id > a.id) ? 1 : -1); // Sắp xếp mới nhất lên đầu
    
  } catch (error) {
    console.error("Lỗi tải danh sách khách hàng:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách khách hàng.', 'error');
  } finally {
    isLoading.value = false;
  }
}

function resetForm() {
  formData.id = null;
  formData.name = '';
  formData.email = '';
  formData.phone = '';
  formData.password = '';
  formData.password_confirmation = '';
  formData.address = '';
  formData.avatar = '';
  formData.status = 'active';
  avatarFile.value = null;
  avatarPreview.value = null;
  Object.keys(errors).forEach(key => errors[key] = '');
}

function onFileChange(e) {
  const file = e.target.files[0];
  if (file) {
    if (file.size > 2 * 1024 * 1024) { // 2MB limit
      Swal.fire('Lỗi', 'Kích thước ảnh quá lớn (tối đa 2MB).', 'error');
      e.target.value = ''; // Clear input
      return;
    }
    avatarFile.value = file;
    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      avatarPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function validateForm() {
  Object.keys(errors).forEach(key => errors[key] = '');
  let isValid = true;

  if (!formData.name.trim()) {
    errors.name = 'Vui lòng nhập họ và tên.';
    isValid = false;
  }

  if (!formData.email) {
    errors.email = 'Vui lòng nhập email.';
    isValid = false;
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
    errors.email = 'Email không đúng định dạng.';
    isValid = false;
  }

  if (formData.phone && !/^[0-9]{10,11}$/.test(formData.phone)) {
    errors.phone = 'Số điện thoại không hợp lệ (10-11 số).';
    isValid = false;
  }

  if (!isEditMode.value) { // Khi tạo mới
    if (!formData.password) {
      errors.password = 'Vui lòng nhập mật khẩu.';
      isValid = false;
    } else if (formData.password.length < 6) {
      errors.password = 'Mật khẩu phải có ít nhất 6 ký tự.';
      isValid = false;
    }
    if (formData.password !== formData.password_confirmation) {
      errors.password_confirmation = 'Mật khẩu xác nhận không khớp.';
      isValid = false;
    }
  } else { // Khi chỉnh sửa
    if (formData.password) { // Nếu có nhập mật khẩu mới
      if (formData.password.length < 6) {
        errors.password = 'Mật khẩu phải có ít nhất 6 ký tự.';
        isValid = false;
      }
      if (formData.password !== formData.password_confirmation) {
        errors.password_confirmation = 'Mật khẩu xác nhận không khớp.';
        isValid = false;
      }
    }
  }

  return isValid;
}

function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  customerModalInstance.value.show();
}

function openEditModal(customer) {
  resetForm();
  isEditMode.value = true;
  // Populate form
  formData.id = customer.id;
  formData.name = customer.name;
  formData.email = customer.email;
  formData.phone = customer.phone;
  formData.address = customer.address;
  formData.avatar = customer.avatar;
  formData.status = customer.status || 'active';
  avatarPreview.value = customer.avatar; // Show existing avatar if any

  customerModalInstance.value.show();
}

function openViewModal(customer) {
  viewingCustomer.value = customer;
  viewModalInstance.value.show();
}

async function handleSave() {
  if (!validateForm()) return;

  isLoading.value = true;
  try {
    const payload = {
      name: formData.name,
      email: formData.email,
      phone: formData.phone,
      address: formData.address,
      avatar: formData.avatar,
      avatar: formData.avatar, // Default to existing URL
      status: formData.status,
    };

    if (avatarPreview.value && avatarFile.value) {
       payload.avatar = avatarPreview.value;
    }

    if (isEditMode.value) {
      if (formData.password) {
        payload.password = formData.password;
        // Bỏ qua password_confirmation vì db.json không cần
      }
      // THAY ĐỔI 2: PATCH đến /users/:id
      await apiService.patch(`/users/${formData.id}`, payload);
      Swal.fire('Thành công', 'Đã cập nhật thông tin khách hàng!', 'success');
    } else {
      payload.password = formData.password;
      
      // THAY ĐỔI 3: Thêm các trường chuẩn hóa cho bảng /users
      payload.role = 'user'; // Gán vai trò là user
      // Tạo username giả lập từ email
      payload.username = formData.email.split('@')[0] + `_${Math.random().toString(36).substr(2, 4)}`;
      payload.created_at = new Date().toISOString();
      
      // THAY ĐỔI 4: POST đến /users
      await apiService.post(`/users`, payload);
      Swal.fire('Thành công', 'Đã thêm khách hàng mới!', 'success');
    }

    customerModalInstance.value.hide();
    fetchCustomers();
  } catch (error) {
    console.error("Lỗi khi lưu khách hàng:", error);
    Swal.fire('Lỗi', 'Đã có lỗi xảy ra khi lưu thông tin.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function toggleCustomerStatus(customer) {
  const newStatus = customer.status === 'active' ? 'inactive' : 'active';
  const actionText = newStatus === 'inactive' ? 'khóa' : 'mở khóa';
  const confirmText = newStatus === 'inactive' ? 'Đồng ý khóa!' : 'Đồng ý mở khóa!';

  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn muốn ${actionText} tài khoản khách hàng "${customer.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: newStatus === 'inactive' ? '#d33' : '#3085d6',
    cancelButtonColor: '#6c757d',
    confirmButtonText: confirmText,
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    isLoading.value = true;
    try {
      // THAY ĐỔI 5: PATCH đến /users/:id
      awapiService.patch(`/users/${customer.id}`, { status: newStatus });
      Swal.fire(
        'Thành công!',
        `Đã ${actionText} tài khoản khách hàng.`,
        'success'
      );
      customer.status = newStatus; // Cập nhật UI ngay
    } catch (error) {
      console.error(`Lỗi khi ${actionText} khách hàng:`, error);
      Swal.fire('Lỗi', `Không thể ${actionText} tài khoản này.`, 'error');
      fetchCustomers(); // Tải lại nếu lỗi
    } finally {
      isLoading.value = false;
    }
  }
}

async function handleDelete(customer) {
  const result = await Swal.fire({
    title: 'Xác nhận xóa?',
    text: `Bạn có chắc muốn xóa khách hàng "${customer.name}"? Hành động này không thể hoàn tác.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Xóa ngay',
    cancelButtonText: 'Hủy'
  });

  if (result.isConfirmed) {
    isLoading.value = true;
    try {
      // THAY ĐỔI 6: DELETE đến /users/:id
      awapiService.delete(`/users/${customer.id}`);
      Swal.fire('Đã xóa!', 'Khách hàng đã được xóa thành công.', 'success');
      
      if (paginatedCustomers.value.length === 1 && currentPage.value > 1) {
        currentPage.value--;
      }
      fetchCustomers();
    } catch (error) {
      console.error("Lỗi khi xóa khách hàng:", error);
      Swal.fire('Lỗi', 'Không thể xóa khách hàng này.', 'error');
    } finally {
      isLoading.value = false;
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
          <h3 class="mb-0">Quản lý Khách hàng</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">Khách hàng</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card mb-4">
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col-md-6 col-12 mb-2 mb-md-0">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm kiếm theo tên, email, SĐT..." v-model="searchQuery">
              </div>
            </div>
            <div class="col-md-6 col-12 text-md-end">
              <button class="btn btn-primary" @click="openCreateModal">
                <i class="bi bi-person-plus-fill me-1"></i> Thêm Khách hàng
              </button>
            </div>
          </div>
        </div>

        <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Khách hàng</th>
                  <th>Liên hệ</th>
                  <th>Trạng thái</th>
                  <th class="text-center">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="isLoading">
                  <td colspan="4" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                  </td>
                </tr>
                <tr v-else-if="filteredCustomers.length === 0">
                  <td colspan="4" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    {{ searchQuery ? 'Không tìm thấy khách hàng nào.' : 'Không có khách hàng nào.' }}
                  </td>
                </tr>
                <!-- THAY ĐỔI 7: Thêm class binding và đổi badge -->
                <tr v-for="customer in paginatedCustomers" :key="customer.id" 
                    :class="{ 'inactive-row': customer.status !== 'active' }">
                  <td class="ps-3">
                    <div class="d-flex align-items-center">
                      <img
                        :src="customer.avatar || `https://placehold.co/40x40/EBF4FF/1D62F0?text=${customer.name.charAt(0).toUpperCase()}`"
                        class="rounded-circle me-3" alt="Avatar"
                        style="width: 40px; height: 40px; object-fit: cover;">
                      <div>
                        <div class="fw-bold">{{ customer.name }}</div>
                        <div class="small text-muted">ID: {{ customer.id }}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div v-if="customer.email"><i class="bi bi-envelope me-1 text-muted"></i> {{ customer.email }}</div>
                    <div v-if="customer.phone"><i class="bi bi-telephone me-1 text-muted"></i> {{ customer.phone }}</div>
                  </td>
                  <td>
                    <span :class="['badge', customer.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
                      {{ customer.status === 'active' ? 'Hoạt động' : 'Đã khóa' }}
                    </span>
                  </td>
                  <td class="text-center">
                    <div class="d-flex justify-content-center align-items-center">
                      <!-- Toggle Switch -->
                      <div class="form-check form-switch me-3" title="Khóa/Mở khóa tài khoản">
                        <input class="form-check-input" type="checkbox" role="switch"
                          style="width: 2.5em; height: 1.25em; cursor: pointer;"
                          :checked="customer.status === 'active'" @click.prevent="toggleCustomerStatus(customer)">
                      </div>

                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" title="Xem chi tiết" @click="openViewModal(customer)">
                          <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditModal(customer)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(customer)">
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

        <div class="card-footer clearfix" v-if="totalPages > 1">
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">
              Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} đến
              {{ Math.min(currentPage * itemsPerPage, filteredCustomers.length) }}
              trong tổng số {{ filteredCustomers.length }} khách hàng
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

  <!-- Create/Edit Modal -->
  <div class="modal fade" id="customerModal" ref="customerModalRef" tabindex="-1" aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ isEditMode ? 'Cập nhật Khách hàng' : 'Thêm mới Khách hàng' }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSave" id="customerForm">
            <div class="row">
              <!-- Left Column: Avatar & Basic Info -->
              <div class="col-md-4 text-center mb-3">
                <div class="mb-3">
                  <img :src="avatarPreview || 'https://placehold.co/150x150?text=Avatar'"
                    class="img-thumbnail rounded-circle" alt="Avatar Preview"
                    style="width: 150px; height: 150px; object-fit: cover;">
                </div>
                <div class="mb-3">
                  <label for="avatarInput" class="form-label btn btn-sm btn-outline-primary">
                    <i class="bi bi-camera-fill"></i> Chọn ảnh
                  </label>
                  <input type="file" class="d-none" id="avatarInput" accept="image/*" @change="onFileChange">
                </div>
                <!-- Status in Edit Mode -->
                <div class="mb-3" v-if="isEditMode">
                  <label for="status" class="form-label fw-bold">Trạng thái</label>
                  <select class="form-select" id="status" v-model="formData.status">
                    <option value="active">Hoạt động</option>
                    <option value="inactive">Đã khóa</option>
                  </select>
                </div>
              </div>

              <!-- Right Column: Personal Details -->
              <div class="col-md-8">
                <div class="mb-3">
                  <label for="name" class="form-label required">Họ và tên</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" id="name"
                    v-model="formData.name">
                  <div class="invalid-feedback">{{ errors.name }}</div>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label for="email" class="form-label required">Email</label>
                    <input type="email" class="form-control" :class="{ 'is-invalid': errors.email }" id="email"
                      v-model="formData.email" :readonly="isEditMode">
                    <div class="invalid-feedback">{{ errors.email }}</div>
                    <div v-if="isEditMode" class="form-text">Không thể thay đổi email.</div>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Số điện thoại</label>
                    <input type="tel" class="form-control" :class="{ 'is-invalid': errors.phone }" id="phone"
                      v-model="formData.phone">
                    <div class="invalid-feedback">{{ errors.phone }}</div>
                  </div>
                </div>
                <div class="mb-3">
                  <label for="address" class="form-label">Địa chỉ</label>
                  <textarea class="form-control" id="address" v-model="formData.address" rows="2"></textarea>
                </div>
              </div>
            </div>

            <hr class="my-4">
            <h6 class="mb-3"><i class="bi bi-shield-lock me-2"></i>Bảo mật</h6>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="password" class="form-label" :class="{ 'required': !isEditMode }">Mật khẩu</label>
                <input type="password" class="form-control" :class="{ 'is-invalid': errors.password }" id="password"
                  v-model="formData.password" autocomplete="new-password">
                <div class="form-text" v-if="isEditMode">Chỉ nhập nếu muốn đổi mật khẩu mới.</div>
                <div class="invalid-feedback">{{ errors.password }}</div>
              </div>
              <div class="col-md-6 mb-3">
                <label for="password_confirmation" class="form-label"
                  :class="{ 'required': !isEditMode || formData.password }">Xác nhận mật khẩu</label>
                <input type="password" class="form-control" :class="{ 'is-invalid': errors.password_confirmation }"
                  id="password_confirmation" v-model="formData.password_confirmation" autocomplete="new-password">
                <div class="invalid-feedback">{{ errors.password_confirmation }}</div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="submit" form="customerForm" class="btn btn-primary" :disabled="isLoading">
            <span v-if="isLoading" class="spinner-border spinner-border-sm me-1" role="status"
              aria-hidden="true"></span>
            {{ isEditMode ? 'Lưu thay đổi' : 'Tạo khách hàng' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Details Modal -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <!-- Status Badge in View Modal -->
          <div class="position-absolute top-0 start-0 m-3">
            <!-- THAY ĐỔI 8: Cập nhật badge trong modal xem -->
            <span :class="['badge', viewingCustomer.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
              {{ viewingCustomer.status === 'active' ? 'Đang hoạt động' : 'Đã khóa' }}
            </span>
          </div>

          <div class="text-center mb-4 mt-3">
            <img
              :src="viewingCustomer.avatar || 'https://placehold.co/120x120?text=' + (viewingCustomer.name ? viewingCustomer.name.charAt(0).toUpperCase() : 'U')"
              class="rounded-circle img-thumbnail shadow-sm" alt="Avatar"
              style="width: 120px; height: 120px; object-fit: cover;">
            <h4 class="mt-3 mb-1">{{ viewingCustomer.name }}</h4>
            <p class="text-muted mb-0">ID: {{ viewingCustomer.id }}</p>
          </div>
          <div class="list-group list-group-flush">
            <div class="list-group-item px-0">
              <i class="bi bi-envelope me-3 text-primary"></i> {{ viewingCustomer.email }}
            </div>
            <div class="list-group-item px-0">
              <i class="bi bi-telephone me-3 text-success"></i> {{ viewingCustomer.phone || 'Chưa cập nhật' }}
            </div>
            <div class="list-group-item px-0">
              <i class="bi bi-geo-alt me-3 text-danger"></i> {{ viewingCustomer.address || 'Chưa cập nhật địa chỉ' }}
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-primary px-4"
            @click="() => { viewModalInstance.hide(); openEditModal(viewingCustomer); }">
            <i class="bi bi-pencil me-2"></i> Chỉnh sửa thông tin
          </button>
        </div>
      </div>
    </div>
  </div>

</template>

<style scoped>
.required::after {
  content: " *";
  color: red;
}
/* Smooth transition for table rows */
.table-hover tbody tr {
  transition: background-color 0.2s;
}
.form-check-input {
  width: 2.5em; 
  height: 1.25em;
}

/* (MỚI) Thêm style để làm mờ hàng bị vô hiệu hóa */
.inactive-row {
  opacity: 0.65;
  background-color: #f8f9fa; /* Màu bg-light của Bootstrap */
  transition: opacity 0.2s ease-in-out, background-color 0.2s ease-in-out;
}
.inactive-row:hover {
  opacity: 1; /* Hiển thị rõ khi hover */
  background-color: #f1f3f5;
}
</style>