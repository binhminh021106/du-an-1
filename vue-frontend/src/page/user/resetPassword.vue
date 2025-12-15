<script setup>

import { ref, reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import apiService from '../../apiService';

const route = useRoute();
const router = useRouter();

// Lấy token và email từ URL

const token = route.query.token;
const email = route.query.email;

const form = reactive({
    password: '',
    password_confirmation: ''
});

const error = ref('');
const isLoading = ref(false);
// Trạng thái ẩn hiện password
const showPass = ref(false);
const showConfirmPass = ref(false);

const goHome = () => {
    router.push({ name: 'home' });
};

const handleReset = async () => {

    error.value = '';

    if (!form.password || form.password.length < 8) {
        error.value = 'Mật khẩu mới phải có ít nhất 8 ký tự';
        return;
    }

    if (form.password !== form.password_confirmation) {
        error.value = 'Mật khẩu nhập lại không khớp';
        return;
    }

    isLoading.value = true;

    try {
        const payload = {
            token: token,
            email: email,
            password: form.password,
            password_confirmation: form.password_confirmation
        };

        const res = await apiService.post('/reset-password', payload);

        if (res.status === 200) {
            await Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: 'Mật khẩu đã được thay đổi. Vui lòng đăng nhập lại.',
                confirmButtonColor: '#009981',
                timer: 2000
            });
            router.push({ name: 'login' });
        }
    } catch (apiError) {
        console.log(apiError);
        const response = apiError.response;
        let msg = 'Token không hợp lệ hoặc đã hết hạn.';

        if (response?.data?.errors) {
            // Lấy lỗi đầu tiên tìm thấy
            const firstKey = Object.keys(response.data.errors)[0];
            msg = response.data.errors[firstKey][0];
        } else if (response?.data?.message) {
            msg = response.data.message;
        }

        error.value = msg;
        Swal.fire({
            icon: 'error',
            title: 'Lỗi',
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

            <button class="close-btn" @click="goHome" title="Về trang chủ">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="promo-section">
                <h2>Bảo mật tài khoản TMEMBER</h2>
                <p>Hãy đặt một mật khẩu mạnh để bảo vệ quyền lợi thành viên của bạn.</p>

                <div class="benefits-box">
                    <ul class="benefits-list">
                        <li>Mật khẩu tối thiểu 8 ký tự</li>
                        <li>Nên bao gồm chữ hoa, chữ thường và số</li>
                        <li>Không chia sẻ mật khẩu cho người khác</li>
                        <li>Đăng nhập lại ngay sau khi đổi thành công</li>
                    </ul>
                </div>
            </div>

            <div class="login-section">
                <h2>Đặt lại mật khẩu</h2>

                <form class="login-form" @submit.prevent="handleReset">

                    <!-- Hiển thị email (Readonly) -->
                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" :value="email" readonly class="input-readonly">
                    </div>

                    <!-- Mật khẩu mới -->
                    <div class="form-group">
                        <label for="password">Mật khẩu mới</label>
                        <div class="password-wrapper">
                            <input :type="showPass ? 'text' : 'password'" v-model="form.password"
                                placeholder="Mật khẩu mới (min 8 ký tự)">
                            <span @click="showPass = !showPass" class="toggle-password">
                                <i :class="showPass ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash'"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Nhập lại mật khẩu -->
                    <div class="form-group">
                        <label for="password_confirmation">Xác nhận mật khẩu</label>
                        <div class="password-wrapper">
                            <input :type="showConfirmPass ? 'text' : 'password'" v-model="form.password_confirmation"
                                placeholder="Nhập lại mật khẩu mới">
                            <span @click="showConfirmPass = !showConfirmPass" class="toggle-password">
                                <i :class="showConfirmPass ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash'"></i>
                            </span>
                        </div>
                        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
                    </div>

                    <button type="submit" class="btn-login" :disabled="isLoading">
                        {{ isLoading ? 'Đang xử lý...' : 'Đổi mật khẩu' }}
                    </button>
                </form>

                <div class="separator">Quay lại</div>
                <p class="register-link">
                    <router-link :to="{ name: 'login' }">Trở về trang Đăng nhập</router-link>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

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
    position: relative;
    box-sizing: border-box;
}

.login-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 1000px;
    width: 90%;
    background-color: #fff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
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
    z-index: 100;
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

.promo-section {
    padding: 40px;
    background-color: #fff;
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
    background-color: #f0fffd;
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
    content: '🔐';
    position: absolute;
    left: 0;
    top: -2px;
    font-size: 1.1rem;
}

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
    width: 100%;
}

.login-form label {
    display: none;
}

.login-form input {
    width: 100%;
    padding: 12px 15px;
    padding-right: 40px;
    /* Thêm padding phải để icon mắt không đè chữ */
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 1rem;
    box-sizing: border-box;
    outline: none;
    transition: border-color 0.3s;
}

.login-form input:focus {
    border-color: var(--primary-color);
}

/* Style cho input readonly */
.input-readonly {
    background-color: #e9ecef;
    cursor: not-allowed;
    color: #6c757d;
}

.password-wrapper {
    position: relative;
    width: 100%;
}

.password-wrapper .toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #888;
    z-index: 5;
    background: transparent;
    padding: 5px;
    /* Tăng diện tích bấm */
}

.invalid-feedback {
    color: #dc3545;
    font-size: 0.875em;
    margin-top: 0.5rem;
}

.btn-login {
    width: 100%;
    padding: 14px;
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
    background-color: #007a67;
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
    width: 35%;
    height: 1px;
    background-color: var(--border-color);
}

.separator::before {
    left: 0;
}

.separator::after {
    right: 0;
}

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

@media (max-width: 768px) {
    .login-container {
        grid-template-columns: 1fr;
        max-width: 400px;
    }

    .promo-section {
        display: none;
    }

    .login-section {
        border-left: none;
        padding: 40px 20px;
    }
}
</style>