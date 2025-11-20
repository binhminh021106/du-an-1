<script setup>
import { reactive, ref, onMounted, watch, computed } from "vue";
import { useRouter } from "vue-router";

const fileInput = ref(null);
const router = useRouter();
const user = ref(null);

// --- STATE QUẢN LÝ ĐỊA CHỈ ---
const showModal = ref(false); // Hiển thị modal thêm/sửa
const isEditingIndex = ref(-1); // -1 là thêm mới, >=0 là sửa index đó
const locationData = reactive({ provinces: [], districts: [], wards: [] }); // Data từ API

// Form tạm trong Modal (Map đúng với cột Database của bạn)
const tempAddress = reactive({
  customer_name: "",
  customer_phone: "",
  city: "",      // Tương ứng DB: city
  district: "",  // Tương ứng DB: district
  ward: "",      // Tương ứng DB: ward
  shipping_address: "", // Tương ứng DB: shipping_address
  is_default: false     // Tương ứng DB: is_default (tinyint)
});

// Thông tin user chính (Profile)
const editUser = reactive({
  avatar: "https://via.placeholder.com/150",
  name: "",
  email: "",
  age: null,
  gender: "",
  phone: "",
  addresses: [], // Mảng chứa các object địa chỉ
});

const errors = reactive({ name: "", age: "", phone: "", modal: {} });

// --- 1. KHỞI TẠO DỮ LIỆU ---
onMounted(async () => {
  // Load thông tin user
  const userData = JSON.parse(localStorage.getItem("userData"));
  if (userData) {
    user.value = userData;
    Object.assign(editUser, userData);
    
    // Đảm bảo addresses là mảng
    if (!Array.isArray(editUser.addresses)) {
      editUser.addresses = [];
    }
  } else {
    router.push({ name: "home" });
  }

  // Load API Địa chính (Chỉ load 1 lần dùng cho Modal)
  try {
    const res = await fetch("https://provinces.open-api.vn/api/?depth=3");
    locationData.provinces = await res.json();
  } catch (e) {
    console.error("Lỗi load địa chỉ:", e);
  }
});

// --- 2. XỬ LÝ LOGIC TRONG MODAL (Địa chính) ---

// Khi chọn Tỉnh -> Load Huyện
watch(() => tempAddress.city, (newVal) => {
  const province = locationData.provinces.find(p => p.name === newVal);
  locationData.districts = province ? province.districts : [];
  
  // Nếu đang sửa mà đổi tỉnh thì reset huyện/xã, nếu mới mở modal thì giữ nguyên
  if (!locationData.districts.some(d => d.name === tempAddress.district)) {
      tempAddress.district = "";
      tempAddress.ward = "";
  }
});

// Khi chọn Huyện -> Load Xã
watch(() => tempAddress.district, (newVal) => {
  const province = locationData.provinces.find(p => p.name === tempAddress.city);
  const district = province?.districts.find(d => d.name === newVal);
  locationData.wards = district ? district.wards : [];
  
   if (!locationData.wards.some(w => w.name === tempAddress.ward)) {
      tempAddress.ward = "";
  }
});

// --- 3. CÁC HÀM THAO TÁC ĐỊA CHỈ ---

// Mở modal thêm mới
const openAddModal = () => {
  isEditingIndex.value = -1;
  // Reset form về rỗng
  Object.assign(tempAddress, {
    customer_name: editUser.name, // Mặc định lấy tên user chính
    customer_phone: editUser.phone, // Mặc định lấy sdt user chính
    city: "", district: "", ward: "", shipping_address: "", is_default: false
  });
  errors.modal = {};
  showModal.value = true;
};

// Mở modal sửa
const openEditModal = (index) => {
  isEditingIndex.value = index;
  // Copy dữ liệu từ list vào form tạm
  Object.assign(tempAddress, JSON.parse(JSON.stringify(editUser.addresses[index])));
  
  // Trigger để load lại districts/wards cho đúng với địa chỉ đang sửa
  // (Logic watch sẽ tự chạy, nhưng cần đảm bảo data list đã có)
  showModal.value = true;
};

// Xóa địa chỉ
const deleteAddress = (index) => {
  if(confirm("Bạn có chắc muốn xóa địa chỉ này?")) {
    editUser.addresses.splice(index, 1);
  }
};

// Validate Modal Form
const validateModal = () => {
  errors.modal = {};
  let isValid = true;
  if (!tempAddress.customer_name) { errors.modal.name = "Nhập tên người nhận"; isValid = false; }
  if (!tempAddress.customer_phone) { errors.modal.phone = "Nhập số điện thoại"; isValid = false; }
  else if (!/^0\d{9}$/.test(tempAddress.customer_phone)) { errors.modal.phone = "SĐT không hợp lệ"; isValid = false; }
  
  if (!tempAddress.city || !tempAddress.district || !tempAddress.ward) {
     errors.modal.location = "Vui lòng chọn đầy đủ Tỉnh/Huyện/Xã"; isValid = false; 
  }
  if (!tempAddress.shipping_address) { errors.modal.address = "Nhập địa chỉ cụ thể"; isValid = false; }
  
  return isValid;
};

// Lưu địa chỉ (Từ Modal -> List)
const saveAddressFromModal = () => {
  if (!validateModal()) return;

  // Xử lý Logic "Mặc định" (Nếu cái này là default thì các cái khác phải bỏ default)
  if (tempAddress.is_default) {
    editUser.addresses.forEach(addr => addr.is_default = false);
  }
  
  // Nếu danh sách đang trống, cái đầu tiên auto là default
  if (editUser.addresses.length === 0) tempAddress.is_default = true;

  if (isEditingIndex.value === -1) {
    // Thêm mới
    editUser.addresses.push({ ...tempAddress });
  } else {
    // Cập nhật
    editUser.addresses[isEditingIndex.value] = { ...tempAddress };
  }

  showModal.value = false;
};

// --- 4. CÁC HÀM CŨ (AVATAR, SAVE PROFILE) ---
const triggerImageUpload = () => fileInput.value.click();
const handleImageChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (event) => editUser.avatar = event.target.result;
    reader.readAsDataURL(file);
  }
};

const saveProfile = () => {
    // Validate cơ bản thông tin chính
    if(!editUser.name) return alert("Tên không được để trống");
    
    // Map dữ liệu để lưu (giả lập gửi lên server)
    Object.assign(user.value, JSON.parse(JSON.stringify(editUser)));
    localStorage.setItem("userData", JSON.stringify(user.value));
    alert("Cập nhật hồ sơ thành công!");
};

const cancelEdit = () => {
    Object.assign(editUser, user.value);
};

</script>

<template>
  <div class="profile-page" v-if="user">
    <div class="profile-content container">
      
      <div class="profile-left">
        <div class="profile-card">
            <div class="avatar-section">
                <div class="avatar" @click="triggerImageUpload">
                    <img :src="editUser.avatar" alt="User Avatar" />
                    <div class="overlay"><i class="fas fa-camera"></i></div>
                    <input type="file" ref="fileInput" accept="image/*" @change="handleImageChange" style="display:none" />
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
                    <label>Số điện thoại (Chính)</label>
                    <input type="text" v-model="editUser.phone" />
                </div>
                 <div class="form-row">
                    <div class="form-group half">
                        <label>Tuổi</label>
                        <input type="number" v-model="editUser.age" />
                    </div>
                    <div class="form-group half">
                        <label>Giới tính</label>
                        <select v-model="editUser.gender">
                            <option value="Nam">Nam</option>
                            <option value="Nữ">Nữ</option>
                        </select>
                    </div>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="save-btn">Lưu thông tin</button>
                </div>
            </form>
        </div>
      </div>

      <div class="profile-right">
          <div class="address-book-card">
              <div class="card-header">
                  <h2><i class="fas fa-map-marker-alt"></i> Sổ địa chỉ</h2>
                  <button type="button" class="add-new-btn" @click="openAddModal">
                      <i class="fas fa-plus"></i> Thêm địa chỉ mới
                  </button>
              </div>

              <div class="address-list">
                  <div v-if="editUser.addresses.length === 0" class="empty-address">
                      <p>Bạn chưa lưu địa chỉ nào.</p>
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
                          <button type="button" class="text-blue" @click="openEditModal(index)">Cập nhật</button>
                          <button type="button" class="text-red" @click="deleteAddress(index)" :disabled="addr.is_default">Xóa</button>
                      </div>
                  </div>
              </div>
          </div>
      </div>

    </div>

    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal-content">
            <div class="modal-header">
                <h3>{{ isEditingIndex === -1 ? 'Thêm địa chỉ mới' : 'Cập nhật địa chỉ' }}</h3>
                <button @click="showModal = false">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group half">
                        <label>Tên người nhận</label>
                        <input type="text" v-model="tempAddress.customer_name" placeholder="Tên người nhận" />
                        <span class="error-msg">{{ errors.modal.name }}</span>
                    </div>
                    <div class="form-group half">
                        <label>Số điện thoại</label>
                        <input type="text" v-model="tempAddress.customer_phone" placeholder="Số điện thoại" />
                        <span class="error-msg">{{ errors.modal.phone }}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tỉnh/Thành phố</label>
                    <select v-model="tempAddress.city">
                        <option value="">Chọn Tỉnh/Thành</option>
                        <option v-for="p in locationData.provinces" :key="p.code" :value="p.name">{{ p.name }}</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group half">
                        <label>Quận/Huyện</label>
                        <select v-model="tempAddress.district" :disabled="!tempAddress.city">
                            <option value="">Chọn Quận/Huyện</option>
                            <option v-for="d in locationData.districts" :key="d.code" :value="d.name">{{ d.name }}</option>
                        </select>
                    </div>
                    <div class="form-group half">
                        <label>Phường/Xã</label>
                        <select v-model="tempAddress.ward" :disabled="!tempAddress.district">
                            <option value="">Chọn Phường/Xã</option>
                            <option v-for="w in locationData.wards" :key="w.code" :value="w.name">{{ w.name }}</option>
                        </select>
                    </div>
                </div>
                <span class="error-msg">{{ errors.modal.location }}</span>

                <div class="form-group mt-2">
                    <label>Địa chỉ cụ thể (Số nhà, đường)</label>
                    <textarea v-model="tempAddress.shipping_address" rows="2" placeholder="Ví dụ: 123 Nguyễn Văn A"></textarea>
                    <span class="error-msg">{{ errors.modal.address }}</span>
                </div>

                <div class="form-group checkbox-group">
                    <input type="checkbox" id="defaultAddr" v-model="tempAddress.is_default">
                    <label for="defaultAddr">Đặt làm địa chỉ mặc định</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" @click="showModal = false">Trở lại</button>
                <button class="btn-primary" @click="saveAddressFromModal">Hoàn thành</button>
            </div>
        </div>
    </div>

  </div>
</template>

<style scoped>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

/* LAYOUT */
.profile-page {
    background-color: #f5f5fa;
    min-height: 100vh;
    padding: 40px 0;
    font-family: 'Arial', sans-serif;
}
.profile-content {
    display: flex;
    gap: 20px;
    max-width: 1200px;
    margin: 0 auto;
}
.profile-left { flex: 0 0 350px; }
.profile-right { flex: 1; }

/* CARD STYLES */
.profile-card, .address-book-card {
    background: #fff;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
}

/* AVATAR SECTION */
.avatar-section { text-align: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
.avatar { width: 120px; height: 120px; margin: 0 auto 15px; position: relative; cursor: pointer; }
.avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid #e0e0e0; }
.overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; opacity: 0; transition: 0.3s; }
.avatar:hover .overlay { opacity: 1; }

/* FORM STYLES */
.form-group { margin-bottom: 15px; display: flex; flex-direction: column; }
.form-row { display: flex; gap: 15px; }
.form-group.half { flex: 1; }
label { font-weight: 500; margin-bottom: 6px; color: #555; font-size: 14px; }
input, select, textarea { padding: 10px; border: 1px solid #ddd; border-radius: 4px; outline: none; transition: border 0.3s; }
input:focus, select:focus, textarea:focus { border-color: #009981; }

/* ADDRESS BOOK STYLES */
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
.card-header h2 { font-size: 18px; color: #333; display: flex; align-items: center; gap: 8px; margin: 0; }
.add-new-btn { background: #009981; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 5px; }
.add-new-btn:hover { background: #007a67; }

.address-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
.address-item:last-child { border-bottom: none; }
.addr-name { font-weight: bold; color: #333; font-size: 15px; }
.addr-phone { color: #777; margin-left: 8px; font-size: 14px; }
.addr-detail { margin-top: 5px; color: #333; }
.addr-location { color: #777; font-size: 13px; margin-top: 2px; }
.badge-default { background: #fff0f0; color: #ee4d2d; border: 1px solid #ee4d2d; font-size: 11px; padding: 2px 6px; border-radius: 2px; margin-left: 10px; }

.addr-actions button { background: none; border: none; cursor: pointer; font-size: 13px; margin-left: 10px; }
.addr-actions button:hover { text-decoration: underline; }
.text-blue { color: #0068ff; }
.text-red { color: #ff424f; }
.text-red:disabled { color: #ccc; cursor: not-allowed; }

/* MODAL STYLES */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 1000; }
.modal-content { background: #fff; width: 500px; max-width: 95%; border-radius: 8px; overflow: hidden; animation: fadeIn 0.2s; }
.modal-header { padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; font-size: 18px; }
.modal-header button { border: none; background: none; font-size: 24px; cursor: pointer; color: #999; }
.modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
.modal-footer { padding: 15px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }

.btn-primary { background: #009981; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
.btn-secondary { background: #fff; border: 1px solid #ddd; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
.save-btn { width: 100%; background: #009981; color: #fff; border: none; padding: 12px; border-radius: 4px; cursor: pointer; margin-top: 10px; font-weight: bold; }

.checkbox-group { flex-direction: row; align-items: center; gap: 10px; }
.error-msg { color: red; font-size: 12px; margin-top: 2px; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 768px) {
    .profile-content { flex-direction: column; }
    .profile-left { flex: auto; }
    .address-item { flex-direction: column; align-items: flex-start; gap: 10px; }
    .addr-actions { width: 100%; display: flex; justify-content: flex-end; }
}
</style>