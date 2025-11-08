<script setup>
import { reactive, ref } from "vue";

// Dữ liệu demo, có thể lấy từ API/store
const user = ref({
  avatar: "../img/default-avatar.png",
  name: "Nguyễn Văn A",
  email: "nguyenvana@example.com",
  phone: "0123456789",
  address: "123 Đường ABC, Quận 1, TP.HCM"
});

// Form editable
const editUser = reactive({ ...user.value });

// Lưu thông tin
const saveProfile = () => {
  Object.assign(user.value, editUser);
  alert("Thông tin đã được lưu!");
};

// Hủy chỉnh sửa, reset form về dữ liệu cũ
const cancelEdit = () => {
  Object.assign(editUser, user.value);
};
</script>

<template>
  <div class="profile-page">
    <div class="profile-content container">
      <div class="profile-card">
        <div class="avatar">
          <img :src="editUser.avatar" alt="User Avatar" />
        </div>
        <div class="profile-form">
          <h2>Thông tin cá nhân</h2>
          <form @submit.prevent="saveProfile">
            <div class="form-group">
              <label>Avatar URL</label>
              <input type="text" v-model="editUser.avatar" />
            </div>
            <div class="form-group">
              <label>Họ và tên</label>
              <input type="text" v-model="editUser.name" />
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="email" v-model="editUser.email" readonly />
            </div>
            <div class="form-group">
              <label>Số điện thoại</label>
              <input type="text" v-model="editUser.phone" />
            </div>
            <div class="form-group">
              <label>Địa chỉ</label>
              <input type="text" v-model="editUser.address" />
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
  box-shadow: 0 6px 20px rgba(0,0,0,0.1);
  max-width: 800px;
  width: 100%;
  align-items: flex-start;
}

.avatar img {
  width: 150px;
  height: 150px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #009981;
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
}

.form-group input {
  padding: 10px 15px;
  border-radius: 8px;
  border: 1px solid #ccc;
  font-size: 14px;
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
