<script setup>
import { reactive, ref, onMounted, watch, computed } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";
// Đảm bảo đường dẫn import apiService đúng với cấu trúc dự án của bạn
import apiService from "../../apiService"; 

const fileInput = ref(null);
const router = useRouter();
const user = ref(null);
const isLoading = ref(false); 
const selectedFile = ref(null); // Biến lưu file ảnh thực tế để upload

// [FIX]: Định nghĩa URL gốc của Server (Backend)
// Nếu bạn dùng Vite, hãy dùng import.meta.env.VITE_API_URL, nếu không thì hardcode tạm localhost:8000
const BACKEND_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

// modal địa chỉ
const showModal = ref(false);
const isEditingIndex = ref(-1);
const locationData = reactive({ provinces: [], districts: [], wards: [] });

// --- LẤY NGÀY HIỆN TẠI ĐỂ CHẶN TƯƠNG LAI ---
const maxDate = computed(() => {
  const today = new Date();
  return today.toISOString().split("T")[0];
});

const tempAddress = reactive({
  id: null, 
  customer_name: "",
  customer_phone: "",
  city: "",
  district: "",
  ward: "",
  shipping_address: "",
  is_default: false,
});

const editUser = reactive({
  avatar: "https://via.placeholder.com/150",
  name: "",
  email: "",
  birthday: "",
  sex: "",
  phone: "",
  addresses: [],
});

const errors = reactive({ name: "", phone: "", modal: {} });

// --- TÍNH TUỔI TỰ ĐỘNG ---
const calculatedAge = computed(() => {
  if (!editUser.birthday) return null;
  const birthDate = new Date(editUser.birthday);
  const today = new Date();
  
  if (birthDate > today) return 0;

  let age = today.getFullYear() - birthDate.getFullYear();
  const m = today.getMonth() - birthDate.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  return age < 0 ? 0 : age;
});

// [FIX]: Hàm xử lý hiển thị ảnh
// Giúp hiển thị đúng dù DB lưu đường dẫn tương đối (/storage/...) hay tuyệt đối (http://...)
const getFullImageUrl = (url) => {
  if (!url) return "https://via.placeholder.com/150";
  // Nếu là ảnh online (google, facebook) hoặc đã full URL thì giữ nguyên
  if (url.startsWith("http") || url.startsWith("https")) return url;
  
  // Nếu là đường dẫn tương đối, nối thêm domain backend vào
  // Loại bỏ dấu / ở đầu nếu có để tránh double slash
  const cleanPath = url.startsWith('/') ? url.substring(1) : url;
  return `${BACKEND_URL}/${cleanPath}`;
};

// --- HÀM LOAD DỮ LIỆU TỪ SERVER ---
const fetchUserData = async () => {
  try {
    isLoading.value = true;
    
    // 1. Gọi API lấy thông tin User
    const responseUser = await apiService.get("/user");
    const userData = responseUser.data;
    user.value = userData;

    // Map dữ liệu từ Server (DB) vào Form Vue
    editUser.name = userData.fullName || ""; 
    editUser.email = userData.email || "";
    editUser.phone = userData.phone || "";
    
    // [FIX]: Sử dụng hàm getFullImageUrl
    editUser.avatar = getFullImageUrl(userData.avatar_url);

    editUser.birthday = userData.birthday || "";
    
    // Xử lý giới tính
    const sexVal = userData.sex;
    if (sexVal === "Nam" || sexVal === "Male" || sexVal === "1") editUser.sex = "Nam";
    else if (sexVal === "Nữ" || sexVal === "Female" || sexVal === "0") editUser.sex = "Nữ";
    else editUser.sex = "Khác";

    // 2. Gọi API lấy danh sách địa chỉ
    await fetchAddressList();

  } catch (error) {
    console.error("Lỗi tải dữ liệu:", error);
    if (error.response && error.response.status === 401) {
      Swal.fire("Phiên đăng nhập hết hạn", "Vui lòng đăng nhập lại", "warning");
    }
  } finally {
    isLoading.value = false;
  }
};

const fetchAddressList = async () => {
  try {
    const res = await apiService.get("/user/addresses");
    editUser.addresses = res.data;
  } catch (error) {
    console.error("Lỗi lấy danh sách địa chỉ:", error);
  }
};

// --- ON MOUNTED ---
onMounted(async () => {
  try {
    const res = await fetch("https://provinces.open-api.vn/api/?depth=3");
    locationData.provinces = await res.json();
  } catch (e) {
    console.error("Lỗi load tỉnh thành:", e);
  }

  await fetchUserData();
});

// Xử lý chọn địa chỉ
watch(() => tempAddress.city, (newVal) => {
  const province = locationData.provinces.find((p) => p.name === newVal);
  locationData.districts = province ? province.districts : [];
  if (!locationData.districts.some((d) => d.name === tempAddress.district)) {
    tempAddress.district = "";
    tempAddress.ward = "";
  }
});

watch(() => tempAddress.district, (newVal) => {
  const province = locationData.provinces.find((p) => p.name === tempAddress.city);
  const district = province?.districts.find((d) => d.name === newVal);
  locationData.wards = district ? district.wards : [];
  if (!locationData.wards.some((w) => w.name === tempAddress.ward)) {
    tempAddress.ward = "";
  }
});

// --- MODAL ACTIONS ---
const openAddModal = () => {
  isEditingIndex.value = -1;
  Object.assign(tempAddress, {
    id: null,
    customer_name: editUser.name,
    customer_phone: editUser.phone,
    city: "",
    district: "",
    ward: "",
    shipping_address: "",
    is_default: false,
  });
  errors.modal = {};
  showModal.value = true;
};

const openEditModal = (index) => {
  isEditingIndex.value = index;
  const item = { ...editUser.addresses[index] };
  Object.assign(tempAddress, item);
  showModal.value = true;
};

// --- CRUD ADDRESS ---
const deleteAddress = async (index) => {
  const addressId = editUser.addresses[index].id;
  
  Swal.fire({
    title: 'Bạn có chắc muốn xóa?',
    text: "Địa chỉ sẽ bị xóa vĩnh viễn!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#009981',
    cancelButtonColor: '#eb5353',
    confirmButtonText: 'Đồng ý',
    cancelButtonText: 'Hủy'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        await apiService.delete(`/user/addresses/${addressId}`);
        await fetchAddressList(); 
        Swal.fire('Đã xóa!', 'Địa chỉ đã được xóa.', 'success');
      } catch (error) {
        Swal.fire('Lỗi', error.response?.data?.message || 'Không thể xóa địa chỉ này.', 'error');
      }
    }
  });
};

const validateModal = () => {
  errors.modal = {};
  let isValid = true;
  if (!tempAddress.customer_name) {
    errors.modal.name = "Nhập tên người nhận";
    isValid = false;
  }
  if (!tempAddress.customer_phone) {
    errors.modal.phone = "Nhập số điện thoại";
    isValid = false;
  } else if (!/(0)[0-9]{9}/.test(tempAddress.customer_phone)) {
    errors.modal.phone = "SĐT không hợp lệ (10 số)";
    isValid = false;
  }
  if (!tempAddress.city || !tempAddress.district || !tempAddress.ward) {
    errors.modal.location = "Chưa chọn đủ địa chỉ";
    isValid = false;
  }
  if (!tempAddress.shipping_address) {
    errors.modal.address = "Nhập địa chỉ cụ thể";
    isValid = false;
  }
  return isValid;
};

const saveAddressFromModal = async () => {
  if (!validateModal()) return;

  try {
    if (isEditingIndex.value === -1) {
      await apiService.post('/user/addresses', tempAddress);
      Swal.fire({
        icon: 'success',
        title: 'Thêm địa chỉ thành công!',
        showConfirmButton: false, timer: 1500, background: '#f0fff4', color: '#009981'
      });
    } else {
      const addressId = tempAddress.id;
      await apiService.put(`/user/addresses/${addressId}`, tempAddress);
      Swal.fire({
        icon: 'success',
        title: 'Cập nhật địa chỉ thành công!',
        showConfirmButton: false, timer: 1500, background: '#f0fff4', color: '#009981'
      });
    }
    showModal.value = false;
    await fetchAddressList();
  } catch (error) {
    console.error(error);
    Swal.fire('Lỗi', 'Có lỗi xảy ra khi lưu địa chỉ. Vui lòng thử lại.', 'error');
  }
};

const triggerImageUpload = () => fileInput.value.click();

const handleImageChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    // [CẬP NHẬT] Kiểm tra 5MB (5 * 1024 * 1024 bytes)
    if (file.size > 5 * 1024 * 1024) return Swal.fire("Lỗi", "File quá lớn (>5MB)", "error");
    
    // Lưu file vào biến state để gửi lên server khi save
    selectedFile.value = file;

    // Tạo preview ảnh ngay lập tức
    const reader = new FileReader();
    reader.onload = (event) => (editUser.avatar = event.target.result);
    reader.readAsDataURL(file);
  }
};

// --- HÀM SAVE PROFILE ---
const saveProfile = async () => {
  if (!editUser.name) return Swal.fire("Lỗi", "Tên không được để trống", "error");

  // Validate ngày sinh
  if (editUser.birthday) {
      if (editUser.birthday > maxDate.value) {
          return Swal.fire("Lỗi", "Ngày sinh không được là ngày tương lai!", "error");
      }
  }

  try {
    // 1. Tạo FormData
    const formData = new FormData();
    formData.append('fullName', editUser.name);
    formData.append('phone', editUser.phone);
    if (editUser.sex) formData.append('sex', editUser.sex);
    if (editUser.birthday) formData.append('birthday', editUser.birthday);
    
    // 2. Nếu người dùng có chọn file mới
    if (selectedFile.value) {
        formData.append('avatar', selectedFile.value);
    }

    // 3. Spoof Method PUT
    formData.append('_method', 'PUT');

    // 4. Gửi request POST
    const res = await apiService.post('/user/profile', formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    });

    // 5. Cập nhật lại UI sau khi thành công
    if (res.data && res.data.user) {
        user.value = res.data.user;
        // [FIX]: Sử dụng getFullImageUrl cho ảnh mới trả về
        if (res.data.user.avatar_url) {
            editUser.avatar = getFullImageUrl(res.data.user.avatar_url);
        }
        selectedFile.value = null;
    }

    Swal.fire({
      icon: 'success',
      title: 'Cập nhật hồ sơ thành công!',
      showConfirmButton: false,
      timer: 1500,
      background: '#f0fff4',
      color: '#009981',
    });
  } catch (error) {
    console.error(error);
    const msg = error.response?.data?.message || 'Cập nhật thất bại.';
    Swal.fire('Lỗi', msg, 'error');
  }
};
</script>

<template>
  <div class="profile-page">
    <div v-if="isLoading" class="loading-state">
        <i class="fas fa-spinner fa-spin"></i> Đang tải dữ liệu...
    </div>
    
    <div class="container py-4" v-if="user && !isLoading">
      <!-- Bố cục Grid Bootstrap: 4 cột trái, 8 cột phải -->
      <div class="row g-4">
        
        <!-- CỘT TRÁI: THÔNG TIN CÁ NHÂN -->
        <div class="col-lg-4">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-body text-center p-4">
              <!-- Avatar Section -->
              <div class="avatar-wrapper mb-3">
                 <div class="avatar" @click="triggerImageUpload">
                    <img :src="editUser.avatar" alt="User Avatar" class="avatar-img" @error="editUser.avatar = 'https://via.placeholder.com/150'"/>
                    <div class="avatar-overlay">
                        <i class="fas fa-camera text-white fs-4"></i>
                    </div>
                 </div>
                 <input type="file" ref="fileInput" accept="image/*" @change="handleImageChange" style="display: none" />
              </div>
              
              <h5 class="fw-bold mb-1">{{ editUser.name }}</h5>
              <p class="text-muted small mb-4">{{ editUser.email }}</p>

              <hr class="my-4 opacity-10">

              <!-- Form Thông tin chính -->
              <form @submit.prevent="saveProfile" class="text-start">
                <div class="mb-3">
                  <label class="form-label small fw-bold text-secondary">Họ và tên</label>
                  <input type="text" class="form-control" v-model="editUser.name" placeholder="Nhập họ tên" />
                </div>
                
                <div class="mb-3">
                  <label class="form-label small fw-bold text-secondary">Số điện thoại</label>
                  <input type="text" class="form-control" v-model="editUser.phone" placeholder="Nhập số điện thoại" />
                </div>

                <div class="row g-2 mb-4">
                   <div class="col-6">
                      <label class="form-label small fw-bold text-secondary">
                        Ngày sinh 
                        <span v-if="calculatedAge !== null" class="text-primary-custom ms-1">({{ calculatedAge }})</span>
                      </label>
                      <input type="date" class="form-control" v-model="editUser.birthday" :max="maxDate" />
                   </div>
                   <div class="col-6">
                      <label class="form-label small fw-bold text-secondary">Giới tính</label>
                      <select class="form-select" v-model="editUser.sex">
                        <option value="Nam">Nam</option>
                        <option value="Nữ">Nữ</option>
                        <option value="Khác">Khác</option>
                      </select>
                   </div>
                </div>

                <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold">
                    <i class="fas fa-save me-2"></i> Lưu thay đổi
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- CỘT PHẢI: SỔ ĐỊA CHỈ -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-map-marker-alt text-primary-custom me-2"></i> Sổ địa chỉ</h5>
                    <button type="button" class="btn btn-outline-primary-custom btn-sm" @click="openAddModal">
                        <i class="fas fa-plus me-1"></i> Thêm mới
                    </button>
                </div>

                <div class="card-body p-0">
                    <div v-if="!editUser.addresses || editUser.addresses.length === 0" class="text-center py-5 text-muted">
                        <img src="https://cdni.iconscout.com/illustration/premium/thumb/empty-address-2977473-2476566.png" alt="Empty" style="width: 120px; opacity: 0.6">
                        <p class="mt-3">Chưa có địa chỉ nào được lưu.</p>
                    </div>

                    <div v-else class="list-group list-group-flush">
                        <div v-for="(addr, index) in editUser.addresses" :key="addr.id" class="list-group-item p-4 border-bottom-hover">
                            <div class="d-flex justify-content-between align-items-start">
                                <!-- Info Block -->
                                <div>
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="fw-bold text-dark me-2">{{ addr.customer_name }}</span>
                                        <span class="text-secondary small border-start ps-2">{{ addr.customer_phone }}</span>
                                        <span v-if="addr.is_default" class="badge bg-danger-subtle text-danger ms-3 border border-danger-subtle">Mặc định</span>
                                    </div>
                                    <p class="mb-1 text-dark">{{ addr.shipping_address }}</p>
                                    <p class="mb-0 text-muted small">{{ addr.ward }}, {{ addr.district }}, {{ addr.city }}</p>
                                </div>
                                
                                <!-- Action Block -->
                                <div class="d-flex gap-2">
                                    <button class="btn btn-light btn-sm text-primary" @click="openEditModal(index)" title="Sửa">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button class="btn btn-light btn-sm text-danger" @click="deleteAddress(index)" :disabled="addr.is_default" title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

      </div>
    </div>

    <!-- MODAL -->
    <div v-if="showModal" class="modal-backdrop-custom">
      <div class="modal-dialog-custom">
        <div class="modal-content-custom">
          <div class="modal-header-custom">
            <h5 class="mb-0 fw-bold">{{ isEditingIndex === -1 ? "Thêm địa chỉ mới" : "Cập nhật địa chỉ" }}</h5>
            <button class="btn-close-custom" @click="showModal = false"><i class="fas fa-times"></i></button>
          </div>
          
          <div class="modal-body-custom">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Người nhận <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" v-model="tempAddress.customer_name" placeholder="Tên người nhận" />
                    <small class="text-danger" v-if="errors.modal.name">{{ errors.modal.name }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Số điện thoại <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" v-model="tempAddress.customer_phone" placeholder="Số điện thoại" />
                    <small class="text-danger" v-if="errors.modal.phone">{{ errors.modal.phone }}</small>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Tỉnh/Thành phố</label>
                <select class="form-select" v-model="tempAddress.city">
                    <option value="">Chọn Tỉnh/Thành</option>
                    <option v-for="p in locationData.provinces" :key="p.code" :value="p.name">{{ p.name }}</option>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Quận/Huyện</label>
                    <select class="form-select" v-model="tempAddress.district" :disabled="!tempAddress.city">
                        <option value="">Chọn Quận/Huyện</option>
                        <option v-for="d in locationData.districts" :key="d.code" :value="d.name">{{ d.name }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Phường/Xã</label>
                    <select class="form-select" v-model="tempAddress.ward" :disabled="!tempAddress.district">
                        <option value="">Chọn Phường/Xã</option>
                        <option v-for="w in locationData.wards" :key="w.code" :value="w.name">{{ w.name }}</option>
                    </select>
                </div>
                <div class="col-12" v-if="errors.modal.location">
                     <small class="text-danger">{{ errors.modal.location }}</small>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Địa chỉ chi tiết</label>
                <textarea class="form-control" v-model="tempAddress.shipping_address" rows="3" placeholder="Số nhà, tên đường..."></textarea>
                <small class="text-danger" v-if="errors.modal.address">{{ errors.modal.address }}</small>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="defaultAddr" v-model="tempAddress.is_default" />
                <label class="form-check-label user-select-none" for="defaultAddr">Đặt làm địa chỉ mặc định</label>
            </div>
          </div>

          <div class="modal-footer-custom">
             <button class="btn btn-light text-secondary fw-bold" @click="showModal = false">Hủy bỏ</button>
             <button class="btn btn-primary-custom px-4 fw-bold" @click="saveAddressFromModal">Lưu Địa Chỉ</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* --- BASE STYLES --- */
.profile-page {
  background-color: #f5f5fa;
  min-height: 100vh;
  font-family: "Arial", sans-serif;
}

.text-primary-custom { color: #009981 !important; }
.bg-danger-subtle { background-color: #fff0f0 !important; }
.border-danger-subtle { border-color: #ffcccc !important; }

/* --- BUTTONS --- */
.btn-primary-custom {
    background-color: #009981;
    border-color: #009981;
    color: #fff;
    transition: all 0.3s;
}
.btn-primary-custom:hover {
    background-color: #007f6b;
    border-color: #007f6b;
}

.btn-outline-primary-custom {
    color: #009981;
    border-color: #009981;
    background: transparent;
}
.btn-outline-primary-custom:hover {
    background-color: #009981;
    color: #fff;
}

/* --- AVATAR --- */
.avatar-wrapper {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto;
}
.avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    border: 3px solid #fff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.avatar-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.avatar:hover .avatar-overlay { opacity: 1; }
.avatar:hover .avatar-img { transform: scale(1.05); }

/* --- CARD TWEAKS --- */
.card { border-radius: 12px; }
.list-group-item { border: none; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; }
.list-group-item:last-child { border-bottom: none; }
.border-bottom-hover:hover { background-color: #fcfcfc; }

/* --- LOADING --- */
.loading-state {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 80vh;
    font-size: 1.1rem;
    color: #666;
    gap: 10px;
}

/* --- CUSTOM MODAL (Mô phỏng Bootstrap Modal nhưng không cần JS của BS) --- */
.modal-backdrop-custom {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-dialog-custom {
    width: 100%;
    max-width: 600px;
    margin: 1.75rem;
    position: relative;
    pointer-events: none;
}
.modal-content-custom {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border: none;
    max-height: 90vh; /* Giới hạn chiều cao */
}
.modal-header-custom {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    background-color: #fff;
}
.btn-close-custom {
    background: transparent;
    border: none;
    font-size: 1.2rem;
    color: #aaa;
    cursor: pointer;
    transition: color 0.2s;
}
.btn-close-custom:hover { color: #333; }

.modal-body-custom {
    position: relative;
    flex: 1 1 auto;
    padding: 1.5rem;
    overflow-y: auto; /* Scroll nếu nội dung dài */
}
.modal-footer-custom {
    display: flex;
    flex-wrap: wrap;
    flex-shrink: 0;
    align-items: center;
    justify-content: flex-end;
    padding: 0.75rem 1.5rem;
    border-top: 1px solid #dee2e6;
    border-bottom-right-radius: 12px;
    border-bottom-left-radius: 12px;
    gap: 0.5rem;
    background-color: #f8f9fa;
}

/* RESPONSIVE */
@media (max-width: 992px) {
    .profile-page { padding: 20px 0; }
}
</style>