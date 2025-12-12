<script setup>
import { onMounted, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import apiService from '../../apiService';

const router = useRouter();
const route = useRoute();
const processingText = ref('Đang xử lý đăng nhập...');

onMounted(async () => {
  // 1. Get Params
  const { token, origin } = route.query;

  // Update UI Text
  if (origin === 'facebook') processingText.value = 'Đang kết nối với Facebook...';
  if (origin === 'google') processingText.value = 'Đang kết nối với Google...';

  if (!token) return handleError();

  try {
    // 2. Save Token
    localStorage.setItem('authToken', token);

    // 3. Get User Info
    const res = await apiService.get('/user', {
      headers: { Authorization: `Bearer ${token}` }
    });
    const user = res.data;

    // 4. Save User & Update Global State
    localStorage.setItem('userData', JSON.stringify(user));
    window.dispatchEvent(new CustomEvent('login-success', { detail: { user } }));

    // 5. Success Alert
    await Swal.fire({
      icon: 'success',
      title: 'Đăng nhập thành công!',
      text: `Chào mừng ${user.full_name || user.name} đến với ThinkHub`,
      timer: 1500,
      showConfirmButton: false,
      confirmButtonColor: '#009981'
    });

    // 6. Redirect Home
    router.push({ name: 'home' });

  } catch (e) {
    console.error("Social Login Error:", e);
    handleError();
  }
});

const handleError = () => {
  Swal.fire({
    icon: 'error',
    title: 'Lỗi đăng nhập',
    text: 'Không thể xác thực tài khoản. Vui lòng thử lại.',
    confirmButtonColor: '#009981'
  }).then(() => router.push({ name: 'login' }));
};
</script>

<template>
  <div class="callback-container">
    <div class="loader-content">
      <div class="spinner"></div>
      <p class="loading-text">{{ processingText }}</p>
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

.loading-text {
  margin-top: 15px;
  font-family: 'Arial', sans-serif;
  color: #555;
  font-weight: 500;
}

.spinner {
  border: 4px solid #f3f3f3;
  border-top: 4px solid #009981;
  border-radius: 50%;
  width: 50px;
  height: 50px;
  animation: spin 0.8s linear infinite;
  margin: 0 auto;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }

  100% {
    transform: rotate(360deg);
  }
}
</style>