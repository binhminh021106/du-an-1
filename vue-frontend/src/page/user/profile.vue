<script setup>
import { reactive, ref, onMounted, watch, computed } from "vue";
import { useRouter } from "vue-router";
import Swal from "sweetalert2";
import "@fortawesome/fontawesome-free/css/all.min.css";

const fileInput = ref(null);
const router = useRouter();
const user = ref(null);

// modal địa chỉ
const showModal = ref(false);
const isEditingIndex = ref(-1);
const locationData = reactive({ provinces: [], districts: [], wards: [] });

const tempAddress = reactive({
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
  sex: "Nam",
  phone: "",
  addresses: [],
});

const errors = reactive({ name: "", phone: "", modal: {} });

// --- TÍNH TUỔI TỰ ĐỘNG ---
const calculatedAge = computed(() => {
  if (!editUser.birthday) return null;
  const birthDate = new Date(editUser.birthday);
  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const m = today.getMonth() - birthDate.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  return age < 0 ? 0 : age;
});

// lấy dữ liệu user từ local
onMounted(async () => {
  const rawData = localStorage.getItem("userData");
  console.log("Dữ liệu thô từ LocalStorage:", rawData);

  if (rawData) {
    let parsedData;
    try {
      parsedData = JSON.parse(rawData);
    } catch (e) {
      console.error("Lỗi parse JSON:", e);
      return;
    }

    const coreData = parsedData.user || parsedData.data || parsedData;
    console.log("Dữ liệu User cốt lõi:", coreData);
    user.value = coreData;

    editUser.name = coreData.fullName || coreData.name || "Chưa cập nhật tên";
    editUser.email = coreData.email || "";
    editUser.phone = coreData.phone || "";
    editUser.avatar = coreData.avatar_url || coreData.avatar || "https://via.placeholder.com/150";

    // --- MAP DATA CHO BIRTHDAY VÀ SEX ---
    editUser.birthday = coreData.birthday || "";

    // Ưu tiên lấy trường 'sex', nếu không có thì tìm 'gender' để fallback
    const sexVal = coreData.sex !== undefined ? coreData.sex : coreData.gender;

    if (sexVal !== undefined) {
      // Xử lý các trường hợp lưu 1/0 hoặc chuỗi
      if (sexVal === 1 || sexVal === "1" || sexVal === "Nam" || sexVal === "Male")
        editUser.sex = "Nam";
      else if (sexVal === 0 || sexVal === "0" || sexVal === "Nữ" || sexVal === "Female")
        editUser.sex = "Nữ";
      else
        editUser.sex = "Khác";
    }

    const listAddr = coreData.user_addresses || coreData.addresses || [];
    if (Array.isArray(listAddr)) {
      editUser.addresses = listAddr;
    } else {
      editUser.addresses = [];
    }
  } else {
    console.warn("Đang dùng dữ liệu giả lập...");
    user.value = { id: 1 };
    editUser.name = "Nguyễn Văn Test (Mock)";
    editUser.email = "test@gmail.com";
    editUser.addresses = [];
  }

  try {
    const res = await fetch("https://provinces.open-api.vn/api/?depth=3");
    locationData.provinces = await res.json();
  } catch (e) {
    console.error("Lỗi load địa chỉ:", e);
  }
});

// xử lý đc tỉnh/quận/huyện/...
watch(
  () => tempAddress.city,
  (newVal) => {
    const province = locationData.provinces.find((p) => p.name === newVal);
    locationData.districts = province ? province.districts : [];
    if (!locationData.districts.some((d) => d.name === tempAddress.district)) {
      tempAddress.district = "";
      tempAddress.ward = "";
    }
  }
);

watch(
  () => tempAddress.district,
  (newVal) => {
    const province = locationData.provinces.find(
      (p) => p.name === tempAddress.city
    );
    const district = province?.districts.find((d) => d.name === newVal);
    locationData.wards = district ? district.wards : [];
    if (!locationData.wards.some((w) => w.name === tempAddress.ward)) {
      tempAddress.ward = "";
    }
  }
);

// thêm địa chỉ
const openAddModal = () => {
  isEditingIndex.value = -1;
  Object.assign(tempAddress, {
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
  const item = JSON.parse(JSON.stringify(editUser.addresses[index]));
  Object.assign(tempAddress, item);
  showModal.value = true;
};

const deleteAddress = (index) => {
  Swal.fire({
    title: 'Bạn có chắc muốn xóa?',
    text: "Địa chỉ sẽ bị xóa vĩnh viễn!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#009981',
    cancelButtonColor: '#eb5353',
    confirmButtonText: 'Đồng ý',
    cancelButtonText: 'Hủy'
  }).then((result) => {
    if (result.isConfirmed) {
      editUser.addresses.splice(index, 1);
      Swal.fire(
        'Đã xóa!',
        'Địa chỉ đã được xóa.',
        'success'
      );
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
  } else if (!/^0\d{9}$/.test(tempAddress.customer_phone)) {
    errors.modal.phone = "SĐT không hợp lệ";
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

const saveAddressFromModal = () => {
  if (!validateModal()) return;

  if (tempAddress.is_default) {
    editUser.addresses.forEach((addr) => (addr.is_default = false));
  }
  if (editUser.addresses.length === 0) tempAddress.is_default = true;

  if (isEditingIndex.value === -1) {
    // Trường hợp thêm mới
    editUser.addresses.push({ ...tempAddress });
    Swal.fire({
      icon: 'success',
      title: 'Thêm địa chỉ thành công!',
      showConfirmButton: false,
      timer: 1500,
      background: '#f0fff4',
      color: '#009981',
    });
  } else {
    // Trường hợp cập nhật
    editUser.addresses[isEditingIndex.value] = { ...tempAddress };
    Swal.fire({
      icon: 'success',
      title: 'Cập nhật địa chỉ thành công!',
      showConfirmButton: false,
      timer: 1500,
      background: '#f0fff4',
      color: '#009981',
    });
  }
  showModal.value = false;
};

// xử lý upload ảnh đại diện
const triggerImageUpload = () => fileInput.value.click();

const handleImageChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    if (file.size > 2 * 1024 * 1024) return alert("File quá lớn (>2MB)");
    const reader = new FileReader();
    reader.onload = (event) => (editUser.avatar = event.target.result);
    reader.readAsDataURL(file);
  }
};

const saveProfile = () => {
  if (!editUser.name) return alert("Tên không được để trống");

  const updatedData = {
    ...user.value,
    fullName: editUser.name,
    phone: editUser.phone,
    avatar_url: editUser.avatar,
    // Thêm 2 trường mới vào data để lưu
    birthday: editUser.birthday,
    sex: editUser.sex
  };

  user.value = updatedData;
  const rawData = localStorage.getItem("userData");
  if (rawData) {
    const parsed = JSON.parse(rawData);
    if (parsed.user) {
      parsed.user = { ...parsed.user, ...updatedData };
      localStorage.setItem("userData", JSON.stringify(parsed));
    } else {
      localStorage.setItem("userData", JSON.stringify(updatedData));
    }
  }

  Swal.fire({
    icon: 'success',
    title: 'Cập nhật hồ sơ thành công!',
    showConfirmButton: false,
    timer: 1500,
    background: '#f0fff4',
    color: '#009981',
  });

};
</script>

<template>
  <div class="profile-page" v-if="user">
    <div class="profile-content container">
      <!-- SIDEBAR TRÁI (40%) -->
      <div class="profile-left">
        <div class="profile-card">
          <div class="avatar-section">
            <div class="avatar" @click="triggerImageUpload">
              <img :src="editUser.avatar" alt="User Avatar" />
              <div class="overlay"><i class="fas fa-camera"></i></div>
              <input type="file" ref="fileInput" accept="image/*" @change="handleImageChange" style="display: none" />
            </div>
            <h3>{{ editUser.name }}</h3>
            <p class="text-muted">{{ editUser.email }}</p>
          </div>

          <form @submit.prevent="saveProfile" class="main-form">
            <div class="form-group">
              <label>Họ và tên</label>
              <input type="text" v-model="editUser.name" />
            </div>
            <div class="form-group">
              <label>Số điện thoại</label>
              <input type="text" v-model="editUser.phone" />
            </div>
            <div class="form-row">
              <div class="form-group half">
                <!-- Hiển thị tuổi tự động tính -->
                <label>
                  Ngày sinh
                  <span v-if="calculatedAge !== null" style="color: #009981; font-weight: bold;">
                    ({{ calculatedAge }} tuổi)
                  </span>
                </label>
                <!-- Input Date -->
                <input type="date" v-model="editUser.birthday" />
              </div>
              <div class="form-group half">
                <label>Giới tính</label>
                <!-- Bind vào editUser.sex -->
                <select v-model="editUser.sex">
                  <option value="Nam">Nam</option>
                  <option value="Nữ">Nữ</option>
                  <option value="Khác">Khác</option>
                </select>
              </div>
            </div>
            <div class="form-buttons">
              <button type="submit" class="save-btn">Lưu thay đổi</button>
            </div>
          </form>
        </div>
      </div>

      <!-- SIDEBAR PHẢI (60%) -->
      <div class="profile-right">
        <div class="address-book-card">
          <div class="card-header">
            <h2><i class="fas fa-map-marker-alt"></i> Sổ địa chỉ</h2>
            <button type="button" class="add-new-btn" @click="openAddModal">
              <i class="fas fa-plus"></i> Thêm mới
            </button>
          </div>

          <div class="address-list">
            <div v-if="!editUser.addresses || editUser.addresses.length === 0" class="empty-address">
              <p>Chưa có địa chỉ nào được lưu.</p>
            </div>

            <div v-for="(addr, index) in editUser.addresses" :key="index" class="address-item">
              <div class="addr-info">
                <div class="addr-header">
                  <span class="addr-name">{{ addr.customer_name }}</span>
                  <span class="addr-phone">| {{ addr.customer_phone }}</span>
                  <span v-if="addr.is_default" class="badge-default">Mặc định</span>
                </div>
                <div class="addr-detail">
                  {{ addr.shipping_address }}
                </div>
                <div class="addr-location">
                  {{ addr.ward }}, {{ addr.district }}, {{ addr.city }}
                </div>
              </div>
              <div class="addr-actions">
                <button type="button" class="btn btn-warning" style="margin-right: 5px;" @click="openEditModal(index)">
                  Sửa
                </button>
                <button type="button" class="btn btn-danger" @click="deleteAddress(index)" :disabled="addr.is_default">
                  Xóa
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h3>{{ isEditingIndex === -1 ? "Thêm địa chỉ" : "Sửa địa chỉ" }}</h3>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group half">
              <label>Người nhận</label>
              <input type="text" v-model="tempAddress.customer_name" />
              <span class="error-msg">{{ errors.modal.name }}</span>
            </div>
            <div class="form-group half">
              <label>SĐT</label>
              <input type="text" v-model="tempAddress.customer_phone" />
              <span class="error-msg">{{ errors.modal.phone }}</span>
            </div>
          </div>
          <div class="form-group">
            <label>Tỉnh/Thành</label>
            <select v-model="tempAddress.city">
              <option value="">Chọn Tỉnh/Thành</option>
              <option v-for="p in locationData.provinces" :key="p.code" :value="p.name">
                {{ p.name }}
              </option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group half">
              <label>Quận/Huyện</label>
              <select v-model="tempAddress.district" :disabled="!tempAddress.city">
                <option value="">Chọn Quận/Huyện</option>
                <option v-for="d in locationData.districts" :key="d.code" :value="d.name">
                  {{ d.name }}
                </option>
              </select>
            </div>
            <div class="form-group half">
              <label>Phường/Xã</label>
              <select v-model="tempAddress.ward" :disabled="!tempAddress.district">
                <option value="">Chọn Phường/Xã</option>
                <option v-for="w in locationData.wards" :key="w.code" :value="w.name">
                  {{ w.name }}
                </option>
              </select>
            </div>
          </div>
          <span class="error-msg">{{ errors.modal.location }}</span>
          <div class="form-group mt-2">
            <label>Địa chỉ chi tiết</label>
            <textarea v-model="tempAddress.shipping_address" rows="2"></textarea>
            <span class="error-msg">{{ errors.modal.address }}</span>
          </div>
          <div class="form-group checkbox-group">
            <input type="checkbox" id="defaultAddr" v-model="tempAddress.is_default" />
            <label for="defaultAddr">Đặt làm mặc định</label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-danger" @click="showModal = false">Hủy</button>
          <button class="btn btn-primary" @click="saveAddressFromModal">Lưu</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>

.profile-page {
  background-color: #f5f5fa;
  min-height: 100vh;
  padding: 40px 0;
  font-family: "Arial", sans-serif;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 15px;
}

/* --- CẤU HÌNH BỐ CỤC 40/60 --- */
.profile-content {
  display: flex;
  gap: 20px;
  /* Khoảng cách giữa 2 cột */
  align-items: flex-start;
  /* Căn trên cùng */
}

.profile-left {
  /* 40% chiều rộng, trừ đi 1 nửa gap (10px) để tổng không quá 100% */
  flex: 0 0 calc(40% - 10px);
  max-width: calc(40% - 10px);
}

.profile-right {
  /* 60% chiều rộng, trừ đi 1 nửa gap (10px) */
  flex: 0 0 calc(60% - 10px);
  max-width: calc(60% - 10px);
}

/* ---------------------------- */

.profile-card,
.address-book-card {
  background: #fff;
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
  height: 100%;
}

.avatar-section {
  text-align: center;
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid #eee;
}

.avatar {
  width: 120px;
  height: 120px;
  margin: 0 auto 15px;
  position: relative;
  cursor: pointer;
}

.avatar img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #e0e0e0;
}

.overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  opacity: 0;
  transition: 0.3s;
}

.avatar:hover .overlay {
  opacity: 1;
}

.form-group {
  margin-bottom: 15px;
  display: flex;
  flex-direction: column;
}

.form-row {
  display: flex;
  gap: 15px;
}

.form-group.half {
  flex: 1;
}

label {
  font-weight: 500;
  margin-bottom: 6px;
  color: #555;
  font-size: 14px;
}

input,
select,
textarea {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  outline: none;
  transition: border 0.3s;
  font-size: 14px;
}

input:focus,
select:focus,
textarea:focus {
  border-color: #009981;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 1px solid #eee;
}

.add-new-btn {
  background: #009981;
  color: #fff;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  margin-left: auto;
}

.address-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 0;
  border-bottom: 1px solid #f0f0f0;
}

.address-item:last-child {
  border-bottom: none;
}

.addr-name {
  font-weight: bold;
}

.badge-default {
  background: #fff0f0;
  color: #ee4d2d;
  border: 1px solid #ee4d2d;
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 2px;
  margin-left: 10px;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: #fff;
  width: 500px;
  max-width: 95%;
  border-radius: 8px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}

.modal-header {
  padding: 15px 20px;
  background: #f8f9fa;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
}

.modal-footer {
  padding: 10px 20px;
  border-top: 1px solid #eee;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.save-btn {
  width: 100%;
  background: #009981;
  color: #fff;
  border: none;
  padding: 12px;
  border-radius: 4px;
  cursor: pointer;
  margin-top: 10px;
  font-weight: bold;
}

.checkbox-group {
  flex-direction: row;
  align-items: center;
  gap: 10px;
}

.error-msg {
  color: red;
  font-size: 12px;
  margin-top: 2px;
}

@media (max-width: 768px) {
  .profile-content {
    flex-direction: column;
  }

  .profile-left,
  .profile-right {
    flex: auto;
    max-width: 100%;
    width: 100%;
  }

  .address-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .addr-actions {
    width: 100%;
    display: flex;
    justify-content: flex-end;
  }
}

</style>
