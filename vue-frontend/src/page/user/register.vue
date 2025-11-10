<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const API_URL = import.meta.env.VITE_API_BASE_URL;
const router = useRouter();

const formData = reactive({
    fullName: '',
    email: '',
    password: '',
    phone: '',
    confirmPassword: ''
});

const error = reactive({
    fullName: '',
    email: '',
    phone: '',
    password: '',
    confirmPassword: '',
    general: '',
    terms: ''
});

const isLoading = ref(false);
const agreedToTerms = ref(false);
const passwordFieldType = ref('password');
const confirmPasswordFieldType = ref('password');

const togglePasswordVisibility = (field) => {
    if (field === 'password') {
        passwordFieldType.value = passwordFieldType.value === 'password' ? 'text' : 'password';
    } else if (field === 'confirm') {
        confirmPasswordFieldType.value = confirmPasswordFieldType.value === 'password' ? 'text' : 'password';
    }
};

const validateForm = () => {
    Object.keys(error).forEach(key => error[key] = '');
    let isValid = true;
    const phoneRegex = /^(0[3|5|7|8|9])+([0-9]{8})$/;

    if (!formData.fullName.trim()) {
        error.fullName = 'Vui lòng nhập họ và tên.';
        isValid = false;
    }

    if (!formData.email) {
        error.email = 'Vui lòng nhập email.';
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
        error.email = 'Email không đúng định dạng.';
        isValid = false;
    }

    if (!formData.phone) {
        error.phone = 'Vui lòng nhập số điện thoại.';
        isValid = false;
    } else if (!phoneRegex.test(formData.phone)) {
        error.phone = 'Số điện thoại không đúng định dạng.';
        isValid = false;
    }

    if (!formData.password) {
        error.password = 'Vui lòng nhập mật khẩu.';
        isValid = false;
    } else if (formData.password.length < 8) {
        error.password = 'Mật khẩu phải có ít nhất 8 ký tự.';
        isValid = false;
    }

    if (!formData.confirmPassword) {
        error.confirmPassword = 'Vui lòng xác nhận mật khẩu.';
        isValid = false;
    } else if (formData.password !== formData.confirmPassword) {
        error.confirmPassword = 'Mật khẩu xác nhận không khớp!';
        isValid = false;
    }

    if (!agreedToTerms.value) {
        error.terms = 'Bạn phải đồng ý với điều khoản để tiếp tục.';
        isValid = false;
    }

    return isValid;
};

const handleRegister = async () => {
    if (!validateForm()) return;

    isLoading.value = true;
    const payload = {
        name: formData.fullName, 
        email: formData.email,
        phone: formData.phone,
        password: formData.password,
        password_confirmation: formData.confirmPassword,
    };

    try {
        const res = await axios.post(`${API_URL}/account_user`, payload);

        if (res.status === 201 || res.status === 200) {
            Swal.fire({
                icon: 'success',
                title: 'Đăng ký thành công',
                text: `Chào mừng ${payload.name}! Vui lòng đăng nhập để tiếp tục.`,
                confirmButtonText: 'Đăng nhập ngay',
                confirmButtonColor: '#009981',
            }).then(() => {
                router.push({ name: 'login' });
                Object.assign(formData, { fullName: '', email: '', password: '', phone: '', confirmPassword: '' });
                agreedToTerms.value = false;
            });
        }

    } catch (apiError) {
        if (apiError.response?.data?.errors) {
            const serverErrors = apiError.response.data.errors;
            if (serverErrors.email) error.email = serverErrors.email[0];
            if (serverErrors.name) error.fullName = serverErrors.name[0];
            if (serverErrors.phone) error.phone = serverErrors.phone[0];
            if (serverErrors.password) error.password = serverErrors.password[0];
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Đăng ký thất bại',
                text: apiError.response?.data?.message || 'Có lỗi xảy ra. Vui lòng thử lại.',
            });
        }
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div class="login-page-wrapper">
        <div class="login-container">

            <div class="promo-section">
                <h2>Chào mừng bạn mới!</h2>
                <p>Tạo tài khoản để quản lý đơn hàng và nhận ưu đãi độc quyền.</p>

                <div class="benefits-box">
                    <ul class="benefits-list">
                        <li>Quản lý và theo dõi đơn hàng tiện lợi.</li>
                        <li>Nhận thông báo khuyến mãi sớm nhất.</li>
                        <li>Lưu danh sách sản phẩm yêu thích.</li>
                        <li>Tích điểm đổi quà và thăng hạng thành viên.</li>
                        <li>Bảo hành điện tử nhanh chóng, tiện lợi.</li>
                        <li>Đặc quyền T-Student/T-Teacher ưu đãi thêm đến 10%</li>
                    </ul>
                </div>

                <a href="#" class="details-link">Xem chi tiết chính sách ưu đãi Tmember ></a>

            </div>

            <div class="login-section">
                <h2>Đăng ký</h2>

                <form action="#" method="POST" class="login-form" @submit.prevent="handleRegister">
                    <div class="form-group">
                        <label for="fullname">Họ và tên</label>
                        <input type="text" id="fullname" v-model="formData.fullName" name="fullname"
                            placeholder="Nhập họ và tên của bạn"
                            :class="['form-control', error.fullName ? 'is-invalid' : '']">
                        <div v-if="error.fullName" class="invalid-feedback d-block">{{ error.fullName }}</div>
                    </div>

                    <div class="form-group">
                        <label for="Email">Email</label>
                        <input type="text" id="Email" v-model="formData.email" name="Email"
                            placeholder="Nhập email của bạn" :class="['form-control', error.email ? 'is-invalid' : '']">
                        <div v-if="error.email" class="invalid-feedback d-block">{{ error.email }}</div>
                    </div>

                    <div class="form-group">
                        <label for="phone">Số điện thoại</label>
                        <input type="text" id="phone" v-model="formData.phone" name="phone"
                            placeholder="Nhập số điện thoại của bạn"
                            :class="['form-control', error.phone ? 'is-invalid' : '']">
                        <div v-if="error.phone" class="invalid-feedback d-block">{{ error.phone }}</div>
                    </div>

                    <div class="form-group">
                        <label for="password">Mật khẩu</label>
                        <div class="password-wrapper">
                            <input :type="passwordFieldType" v-model="formData.password" id="password" name="password"
                                placeholder="Nhập mật khẩu của bạn"
                                :class="['form-control', error.password ? 'is-invalid' : '']">
                            <button type="button" @click="togglePasswordVisibility('password')"
                                class="btn btn-outline-secondary">
                                <i
                                    :class="passwordFieldType === 'password' ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill'"></i>
                            </button>
                        </div>
                        <div v-if="error.password" class="invalid-feedback d-block">{{ error.password }} </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">Xác nhận mật khẩu</label>
                        <div class="password-wrapper">
                            <input :type="confirmPasswordFieldType" v-model="formData.confirmPassword"
                                id="confirm-password" name="confirm-password" placeholder="Nhập lại mật khẩu của bạn"
                                :class="['form-control', error.confirmPassword ? 'is-invalid' : '']">
                            <button type="button" @click="togglePasswordVisibility('confirm')"
                                class="btn btn-outline-secondary">
                                <i
                                    :class="confirmPasswordFieldType === 'password' ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill'"></i>
                            </button>
                        </div>
                        <div v-if="error.confirmPassword" class="invalid-feedback d-block">{{
                            error.confirmPassword }}</div>
                    </div>

                    <div class="form-group">
                        <input type="checkbox" v-model="agreedToTerms"/>
                        <label for="agreeTerms" class="form-check-label">
                            Tôi đồng ý với
                            <a href="#" class="text-decoration-none fw-medium" style="color: #009981;">điều khoản
                                dịch vụ</a>
                        </label>
                    </div>
                    <div v-if="error.terms" class="invalid-feedback d-block">{{ error.terms }}</div>

                    <button type="submit" class="btn-login" :disabled="isLoading">
                        <span v-if="isLoading" class="spinner-border spinner-border-sm me-2" role="status"
                            aria-hidden="true"></span>
                        <span>{{ isLoading ? 'Đang đăng ký...' : 'Đăng ký' }}</span>
                    </button>
                </form>

                <div class="separator">Hoặc đăng ký bằng</div>
                <div class="social-login">
                    <button class="social-btn">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg"
                            alt="Google">
                        Google
                    </button>
                    <button class="social-btn">
                        <img src="../../assets/facebook-svgrepo-com.svg" alt="Facebook">
                        Facebook
                    </button>
                </div>

                <p class="register-link">
                    Bạn đã có tài khoản? <router-link :to="{ name: 'login' }">Đăng nhập ngay</router-link>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

.invalid-feedback {
    color: #dc3545;
    font-size: 0.875em;
    margin-top: 0.25rem;
}

.is-invalid {
    border-color: #dc3545 !important;
}

:root {
    --primary-color: #009981;
    --text-color: #333;
    --border-color: #ddd;
    --bg-light: #f9f9f9;
}

.login-page-wrapper {
    font-family: 'Roboto', sans-serif;
    background-color: var(--bg-light);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    width: 100%;
    margin: 0;
    color: var(--text-color);
}

.login-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 1000px;
    width: 100%;
    background-color: #fff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    border-radius: 12px;
    overflow: hidden;
    margin: 20px;
}

.promo-section {
    padding: 40px;
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
}

.benefits-box {
    border: 2px dashed var(--primary-color);
    border-radius: 10px;
    padding: 20px;
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
}

.benefits-list li::before {
    content: '🎁';
    position: absolute;
    left: 0;
    top: 0;
    font-size: 1.2rem;
}

.details-link {
    display: inline-block;
    margin-top: 20px;
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 700;
    font-size: 0.9rem;
}

.details-link:hover {
    text-decoration: underline;
}

.login-section {
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border-left: 1px solid var(--border-color);
}

.login-section h2 {
    font-size: 1.75rem;
    font-weight: 700;
    text-align: left;
    margin-bottom: 25px;
}

.login-form .form-group {
    margin-bottom: 20px;
}

.login-form label {
    display: none;
}

/* Hiện label cho checkbox */
.login-form .form-group label.form-check-label {
    display: inline-block;
    margin-left: 8px;
    font-size: 0.9rem;
}

.login-form .form-group input[type="checkbox"] {
    width: auto;
}


.login-form input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid black;
    border-radius: 8px;
    font-size: 1rem;
    box-sizing: border-box;
}

.password-wrapper {
    position: relative;
    display: flex;
    /* Thêm để button nằm trong input */
}

.password-wrapper input {
    border-right: none;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

/* Style cho button xem mật khẩu */
.password-wrapper .btn-outline-secondary {
    border: 1px solid black;
    border-left: none;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border-top-right-radius: 8px;
    border-bottom-right-radius: 8px;
    background: white;
    cursor: pointer;
}

.password-wrapper .btn-outline-secondary:hover {
    background: #f1f1f1;
}

.password-wrapper .btn-outline-secondary i {
    color: #555;
    font-size: 1.2rem;
    /* Cần import Bootstrap Icons */
}


.btn-login {
    width: 100%;
    padding: 12px;
    background-color: var(--primary-color);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.btn-login:hover {
    opacity: 0.9;
}

/* Style cho nút khi disabled */
.btn-login:disabled {
    background-color: #aaa;
    cursor: not-allowed;
    opacity: 0.7;
}

.forgot-password {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

.forgot-password:hover {
    text-decoration: underline;
}

.separator {
    text-align: center;
    margin: 25px 0;
    color: #aaa;
    font-size: 0.85rem;
    position: relative;
}

.separator::before,
.separator::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 40%;
    height: 1px;
    background-color: var(--border-color);
}

.separator::before {
    left: 0;
}

.separator::after {
    right: 0;
}

.social-login {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.social-btn {
    padding: 10px;
    border: 1px solid #009981;
    border-radius: 8px;
    background-color: #fff;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.social-btn img {
    width: 20px;
    height: 20px;
}

.social-btn:hover {
    background-color: #009981;
    color: white;
}

.register-link {
    text-align: center;
    margin-top: 30px;
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
    }

    .promo-section {
        display: none;
    }

    .login-section {
        border-left: none;
    }
}
</style>