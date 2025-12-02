<script setup>
import { onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import apiService from '../../apiService'; // Đảm bảo import đúng đường dẫn

const router = useRouter();
const route = useRoute();

onMounted(async () => {
  // 1. Lấy token từ URL
  const token = route.query.token;

  if (token) {
    try {
      // 2. Lưu token vào LocalStorage
      localStorage.setItem('authToken', token);

      // 3. Gọi API lấy thông tin User (Truyền token trực tiếp để tránh lỗi interceptor chưa cập nhật)
      const res = await apiService.get('/user', {
        headers: {
            Authorization: `Bearer ${token}`
        }
      });
      
      const user = res.data;

      // 4. Lưu thông tin User
      localStorage.setItem('userData', JSON.stringify(user));

      // 5. Thông báo cho Header cập nhật (kèm dữ liệu user)
      window.dispatchEvent(new CustomEvent('login-success', {
        detail: { user: user }
      }));

      // 6. Thông báo đẹp
      await Swal.fire({
          icon: 'success',
          title: 'Đăng nhập thành công!',
          text: `Chào mừng ${user.full_name || user.name || user.email || 'bạn'} đến với ThinkHub`,
          timer: 1500,
          showConfirmButton: false
      });

      // 7. Chuyển hướng về trang chủ
      router.push({ name: 'home' }); 

    } catch (e) {
      console.error("Lỗi chi tiết:", e); // Xem lỗi này trong Console (F12) nếu vẫn bị
      
      // Nếu lỗi lấy user nhưng token có vẻ vẫn đúng, ta vẫn cho vào nhưng báo lỗi nhẹ
      // Hoặc gọi handleError() để bắt đăng nhập lại. 
      // Ở đây mình gọi handleError để an toàn.
      handleError();
    }
  } else {
    handleError();
  }
});

const handleError = () => {
    Swal.fire({
        icon: 'error',
        title: 'Lỗi đăng nhập',
        text: 'Không thể xác thực tài khoản Google. Vui lòng thử lại.',
    }).then(() => {
        router.push({ name: 'login' }); 
    });
};
</script>

<template>
  <div class="callback-container">
    <div class="loader-content">
      <div class="spinner"></div>
      <p>Đang xử lý đăng nhập Google...</p>
    </div>
  </div>
</template>

<style scoped>
.callback-container {
  height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  background-color: #f9f9f9;
}
.loader-content {
    text-align: center;
}
.spinner {
  border: 4px solid #f3f3f3;
  border-top: 4px solid #009981;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto 10px;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>