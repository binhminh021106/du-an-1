<script setup>
import { reactive, ref } from "vue";

const fileInput = ref(null);

// test git checkout

const user = ref({
  avatar: "https://via.placeholder.com/150",
  name: "Nguyễn Văn A",
  email: "nguyenvana@example.com",
  age: 22,
  gender: "Nam",
  phone: "0123456789",
  address: "Quận 1, TP.HCM",
});

const editUser = reactive({ ...user.value });
const errors = reactive({ name: "", age: "", phone: "", address: "" });

// Mở hộp thoại chọn ảnh
const triggerImageUpload = () => {
  fileInput.value.click();
};

// Xử lý ảnh được chọn
const handleImageChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = (event) => {
      editUser.avatar = event.target.result;
    };
    reader.readAsDataURL(file);
  }
};

// vld họ tên
const validateName = () => {
  const regex = /^[A-Za-zÀ-ỹ\s]+$/;
  if (!editUser.name.trim()) errors.name = "Họ và tên không được để trống.";
  else if (!regex.test(editUser.name))
    errors.name = "Họ và tên chỉ được chứa chữ cái.";
  else errors.name = "";
};

// vld tuổi
const validateAge = () => {
  if (!editUser.age) errors.age = "Tuổi không được để trống.";
  else if (editUser.age < 1) errors.age = "Tuổi phải lớn hơn 1.";
  else errors.age = "";
};

// vld sdt
const validatePhone = () => {
  const regex = /^0\d{9}$/;
  if (!editUser.phone.trim()) errors.phone = "Số điện thoại không được để trống.";
  else if (!regex.test(editUser.phone))
    errors.phone = "Số điện thoại phải gồm 10 chữ số và bắt đầu bằng số 0.";
  else errors.phone = "";
};

// vld địa chỉ
const validateAddress = () => {
  const parts = editUser.address.split(",").map((p) => p.trim());
  if (!editUser.address.trim()) errors.address = "Địa chỉ không được để trống.";
  else if (parts.length < 2)
    errors.address = "Địa chỉ phải có ít nhất Huyện, Tỉnh hoặc Quận, Thành phố.";
  else errors.address = "";
};

// vld all
const validateForm = () => {
  validateName();
  validateAge();
  validatePhone();
  validateAddress();
  return !errors.name && !errors.age && !errors.phone && !errors.address;
};

const saveProfile = () => {
  if (!validateForm()) {
    alert("Vui lòng kiểm tra lại thông tin!");
    return;
  }
  Object.assign(user.value, editUser);
  alert("Thông tin đã được lưu thành công!");
};

const cancelEdit = () => {
  Object.assign(editUser, user.value);
};
</script>

<template>
  <div class="profile-page">
    <div class="profile-content container">
      <div class="profile-card">
        <div class="avatar" @click="triggerImageUpload">
          <img :src="editUser.avatar" alt="User Avatar" />
          <div class="overlay"><i class="fas fa-camera"></i></div>
          <input type="file" ref="fileInput" accept="image/*" @change="handleImageChange" style="display:none" />
        </div>

        <div class="profile-form">
          <h2>Thông tin cá nhân</h2>
          <form @submit.prevent="saveProfile">
            <div class="form-group">
              <label>Họ và tên</label>
              <input type="text" v-model="editUser.name" @blur="validateName" />
              <p v-if="errors.name" class="error">{{ errors.name }}</p>
            </div>

            <div class="form-group">
              <label>Email</label>
              <input type="email" v-model="editUser.email" readonly />
            </div>

            <div class="form-group">
              <label>Tuổi</label>
              <input type="number" v-model="editUser.age" @blur="validateAge" />
              <p v-if="errors.age" class="error">{{ errors.age }}</p>
            </div>

            <div class="form-group">
              <label>Giới tính</label>
              <select v-model="editUser.gender">
                <option value="">-- Chọn giới tính --</option>
                <option value="Nam">Nam</option>
                <option value="Nữ">Nữ</option>
                <option value="Khác">Khác</option>
              </select>
            </div>

            <div class="form-group">
              <label>Số điện thoại</label>
              <input type="text" v-model="editUser.phone" @blur="validatePhone" />
              <p v-if="errors.phone" class="error">{{ errors.phone }}</p>
            </div>

            <div class="form-group">
              <label>Địa chỉ</label>
              <input type="text" v-model="editUser.address" @blur="validateAddress" />
              <p v-if="errors.address" class="error">{{ errors.address }}</p>
            </div>

            <div class="form-buttons">
              <button type="submit" class="save-btn">Lưu</button>
              <button type="button" class="cancel-btn" @click="cancelEdit">Hủy</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

.profile-page {
  font-family: Arial, sans-serif;
  padding: 50px 15px;
}

.profile-content {
  display: flex;
  justify-content: center;
}

.profile-card {
  display: flex;
  gap: 40px;
  background-color: #fff;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
  max-width: 800px;
  width: 100%;
  align-items: flex-start;
}

.avatar {
  position: relative;
  cursor: pointer;
}
.avatar img {
  width: 150px;
  height: 150px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #009981;
  transition: 0.3s;
}
.avatar:hover img {
  filter: brightness(0.8);
}
.overlay {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  display: flex; justify-content: center; align-items: center;
  color: white;
  background: rgba(0, 0, 0, 0.4);
  opacity: 0; transition: 0.3s;
  border-radius: 50%;
}
.avatar:hover .overlay {
  opacity: 1;
}

.profile-form {
  flex-grow: 1;
}

.profile-form h2 {
  color: #009981;
  margin-bottom: 20px;
}

.form-group {
  margin-bottom: 15px;
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: bold;
  margin-bottom: 5px;
  font-size: 18px;
}

.form-group input,
.form-group select {
  padding: 10px 15px;
  border-radius: 8px;
  border: 1px solid #ccc;
  font-size: 14px;
}

.error {
  color: red;
  font-size: 15px;
  margin-top: 5px;
  font-weight: bold;

}

.form-buttons {
  margin-top: 20px;
  display: flex;
  gap: 15px;
}

.save-btn {
  background-color: #009981;
  color: #fff;
  border: none;
  padding: 10px 25px;
  border-radius: 8px;
  font-weight: bold;
  cursor: pointer;
}

.save-btn:hover {
  background-color: #00483d;
}

.cancel-btn {
  background-color: #f8f9fa;
  color: #333;
  border: 1px solid #ccc;
  padding: 10px 25px;
  border-radius: 8px;
  cursor: pointer;
}

.cancel-btn:hover {
  background-color: #e9ecef;
}
</style>
