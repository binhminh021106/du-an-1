<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import apiService from '../../apiService';

const router = useRouter();
const email = ref('');
const error = ref('');
const isLoading = ref(false);
const goHome = () => {
    router.push({ name: 'home' });
};

const sendLink = async () => {

    // Reset lỗi
    error.value = '';

    if (!email.value) {
        error.value = 'Vui lòng nhập email của bạn';
        return;
    }

    isLoading.value = true;

    try {
        // Gọi API Backend
        const res = await apiService.post('/forgot-password', { email: email.value });

        if (res.status === 200) {
            // Hiển thị SweetAlert thành công
            await Swal.fire({
                icon: 'success',
                title: 'Đã gửi email!',
                text: 'Vui lòng kiểm tra hộp thư đến (hoặc spam) để lấy link đặt lại mật khẩu.',
                confirmButtonColor: '#009981',
            });
            // Có thể chuyển hướng về login hoặc giữ nguyên
            // router.push({ name: 'login' });
        }

    } catch (apiError) {
        console.log(apiError);
        const response = apiError.response;
        let msg = 'Có lỗi xảy ra, vui lòng thử lại.';

        if (response?.data?.errors?.email) {
            msg = response.data.errors.email[0];
        } else if (response?.data?.message) {
            msg = response.data.message;
        }

        error.value = msg;

        Swal.fire({
            icon: 'error',
            title: 'Gửi thất bại',
            text: msg,
            confirmButtonColor: '#009981',
        });
    } finally {
        isLoading.value = false;
    }
};

</script>

<template>
    <div class="login-page-wrapper">
        <div class="login-container">
            
            <!-- NÚT X ĐỂ VỀ TRANG CHỦ -->
            <button class="close-btn" @click="goHome" title="Về trang chủ">
                <!-- Dùng icon text tạm nếu chưa có font-awesome, hoặc giữ nguyên thẻ i cũ -->
                <i class="fa-solid fa-xmark"></i> 
            </button>

            <!-- PHẦN QUẢNG CÁO -->
            <div class="promo-section">
                <h2>Khôi phục tài khoản TMEMBER</h2>
                <p>Đừng lo lắng, chúng tôi sẽ giúp bạn lấy lại mật khẩu dễ dàng.</p>

                <div class="benefits-box">
                    <ul class="benefits-list">
                        <li>Kiểm tra kỹ email trong hộp thư đến</li>
                        <li>Kiểm tra cả thư mục Spam/Rác nếu không thấy</li>
                        <li>Link đặt lại mật khẩu có hiệu lực trong 60 phút</li>
                        <li>Liên hệ CSKH nếu gặp khó khăn: 1900 xxxx</li>
                    </ul>
                </div>
            </div>

            <!-- PHẦN FORM -->
            <div class="login-section">
                <h2>Quên mật khẩu?</h2>

                <form class="login-form" @submit.prevent="sendLink">
                    <div class="form-group">
                        <label for="email">Email đăng ký</label>
                        <input 
                            type="email" 
                            id="email" 
                            v-model="email"
                            placeholder="Nhập địa chỉ Email của bạn"
                            :class="['form-control', error ? 'is-invalid' : '']"
                        >
                        <div v-if="error" class="invalid-feedback">{{ error }}</div>
                    </div>

                    <button type="submit" class="btn-login" :disabled="isLoading">
                        {{ isLoading ? 'Đang gửi...' : 'Gửi link reset' }}
                    </button>
                </form>

                <router-link :to="{ name: 'login'}" class="separator">Quay lại</router-link>

                <p class="register-link">
                    Bạn đã nhớ mật khẩu? <router-link :to="{ name: 'login' }">Đăng nhập ngay</router-link>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

/* FIX QUAN TRỌNG: Thay :root bằng class bao ngoài cùng của component */
.login-page-wrapper {
    --primary-color: #009981;
    --text-color: #333;
    --border-color: #ddd;
    --bg-light: #f9f9f9;

    font-family: 'Roboto', sans-serif;
    background-color: var(--bg-light);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    width: 100%;
    margin: 0;
    color: var(--text-color);
    
    /* Đảm bảo vị trí tương đối để tránh lỗi layout khi lồng trong container khác */
    position: relative; 
    box-sizing: border-box;
}

.login-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 1000px;
    width: 90%; /* Sửa width 100% thành 90% để tránh dính sát mép màn hình mobile */
    background-color: #fff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); /* Tăng shadow lên một chút */
    border-radius: 12px;
    overflow: hidden;
    margin: 20px;
    position: relative; 
}

.close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: transparent;
    border: none;
    font-size: 1.5rem;
    color: #999;
    cursor: pointer;
    z-index: 100; /* Tăng z-index để chắc chắn nút nổi lên trên */
    transition: all 0.2s ease;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close-btn:hover {
    background-color: #f0f0f0;
    color: #333;
    transform: rotate(90deg);
}

/* Phần Promo */
.promo-section {
    padding: 40px;
    background-color: #fff; /* Đảm bảo nền trắng */
}

.promo-section h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-top: 0;
}

.promo-section p {
    font-size: 1rem;
    margin-bottom: 25px;
    line-height: 1.5;
}

.benefits-box {
    border: 2px dashed var(--primary-color);
    border-radius: 10px;
    padding: 20px;
    background-color: #f0fffd; /* Thêm màu nền nhẹ cho box đẹp hơn */
}

.benefits-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.benefits-list li {
    position: relative;
    padding-left: 30px;
    margin-bottom: 15px;
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.4;
}

.benefits-list li::before {
    content: '🛡️';
    position: absolute;
    left: 0;
    top: -2px;
    font-size: 1.1rem;
}

/* Phần Login Form */
.login-section {
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border-left: 1px solid var(--border-color);
    background-color: #fff;
}

.login-section h2 {
    font-size: 1.75rem;
    font-weight: 700;
    text-align: left;
    margin-bottom: 25px;
    color: var(--text-color);
}

.login-form .form-group {
    margin-bottom: 20px;
    width: 100%; /* Đảm bảo input full width */
}

.login-form label {
    display: none; /* Ẩn label như yêu cầu cũ */
}

.login-form input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ccc; /* Thay màu đen cứng bằng màu xám nhẹ hơn */
    border-radius: 8px;
    font-size: 1rem;
    box-sizing: border-box; /* Quan trọng để padding không làm vỡ layout */
    outline: none;
    transition: border-color 0.3s;
}

.login-form input:focus {
    border-color: var(--primary-color);
}

.form-control.is-invalid {
    border-color: #dc3545;
    background-image: none; /* Fix lỗi icon của bootstrap nếu có dùng chung */
}

.invalid-feedback {
    color: #dc3545;
    font-size: 0.875em;
    margin-top: 0.5rem;
    display: block;
}

.btn-login {
    width: 100%;
    padding: 14px; /* Tăng padding chút cho nút to hơn */
    background-color: var(--primary-color);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
}

.btn-login:hover {
    background-color: #007a67; /* Màu hover đậm hơn chút */
    transform: translateY(-1px);
}

.btn-login:disabled {
    background-color: #ccc;
    cursor: not-allowed;
    transform: none;
}

.separator {
    text-align: center;
    margin: 25px 0;
    color: #aaa;
    font-size: 0.85rem;
    position: relative;
    width: 100%;
}

.separator::before,
.separator::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 35%; /* Giới hạn độ dài đường gạch ngang */
    height: 1px;
    background-color: var(--border-color);
}

.separator::before { left: 0; }
.separator::after { right: 0; }

.register-link {
    text-align: center;
    margin-top: 10px;
    font-size: 0.9rem;
}

.register-link a {
    color: var(--primary-color);
    font-weight: 700;
    text-decoration: none;
}

.register-link a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 768px) {
    .login-container { 
        grid-template-columns: 1fr; 
        max-width: 400px; /* Giới hạn chiều rộng trên mobile */
    }
    .promo-section { 
        display: none; 
    }
    .login-section { 
        border-left: none; 
        padding: 40px 20px; /* Giảm padding ngang trên mobile */
    }
}
</style>