<script setup>
import { ref, reactive } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import apiService from '../../apiService';

const router = useRouter();

// Khai báo biến cho form
const formData = reactive({
    loginId: '',
    password: ''
});

const error = reactive({
    loginId: '',
    password: ''
});

const isLoading = ref(false);
const passwordFieldType = ref('password');

// --- HÀM MỚI: QUAY VỀ TRANG CHỦ ---
const goHome = () => {
    router.push({ name: 'home' }); // Đảm bảo route 'home' đã tồn tại trong router của bạn
};

const loginWithGoogle = () => {
    window.location.href = 'http://127.0.0.1:8000/api/auth/google';
};

// Hàm xử lý đăng nhập chuẩn Backend
const handleLogin = async () => {
    // 1. Reset lỗi cũ
    error.loginId = '';
    error.password = '';
    let isValid = true;

    // Validate sơ bộ ở client
    if (!formData.loginId) {
        error.loginId = 'Vui lòng nhập email hoặc số điện thoại';
        isValid = false;
    }
    if (!formData.password) {
        error.password = 'Vui lòng nhập mật khẩu';
        isValid = false;
    }
    if (!isValid) return;

    isLoading.value = true;

    const payload = {
        login_id: formData.loginId,
        password: formData.password
    };

    try {
        const res = await apiService.post('/login', payload);

        if (res.status === 200) {
            const { user, token } = res.data;

            // Lưu lại chìa khóa (token) và thông tin user
            localStorage.setItem('authToken', token);
            localStorage.setItem('userData', JSON.stringify(user));

            // Thông báo cho toàn bộ web biết là đã đăng nhập
            window.dispatchEvent(new CustomEvent('login-success', {
                detail: { user: user }
            }));

            // Hiện thông báo thành công
            await Swal.fire({
                icon: 'success',
                title: 'Đăng nhập thành công!',
                text: `Chào mừng quay trở lại, ${user.fullName || user.email}!`,
                timer: 1500,
                showConfirmButton: 'Đi đến trang chủ',
                confirmButtonColor: '#009981',
            });

            // Chuyển về trang chủ
            router.push({ name: 'home' });
        }

    } catch (apiError) {
        // 5. Xử lý nếu Server báo lỗi (401, 422...)
        console.log(apiError);

        const response = apiError.response;
        let msg = 'Đã có lỗi xảy ra. Vui lòng thử lại.';

        if (response) {
            if (response.status === 422) {
                if (response.data.errors?.login_id) error.loginId = response.data.errors.login_id[0];
                if (response.data.errors?.password) error.password = response.data.errors.password[0];
                msg = 'Vui lòng kiểm tra lại thông tin nhập vào.';
            } else if (response.status === 401) {
                msg = 'Sai Email (SĐT) hoặc Mật khẩu.';
            } else if (response.data?.message) {
                msg = response.data.message;
            }
        }

        if (msg !== 'Vui lòng kiểm tra lại thông tin nhập vào.') {
            Swal.fire({
                icon: 'error',
                title: 'Đăng nhập thất bại',
                text: msg,
                confirmButtonColor: '#009981',
            });
        }
    } finally {
        isLoading.value = false;
    }
};

const togglePasswordVisibility = () => {
    passwordFieldType.value = passwordFieldType.value === 'password' ? 'text' : 'password';
};
</script>

<template>
    <div class="login-page-wrapper">
        <div class="login-container">
            
            <!-- NÚT X ĐỂ VỀ TRANG CHỦ -->
            <button class="close-btn" @click="goHome" title="Về trang chủ">
                <i class="fa-solid fa-xmark"></i> <!-- Sử dụng icon Xmark -->
            </button>

            <div class="promo-section">
                <h2>Nhập hội khách hàng thành viên TMEMBER</h2>
                <p>Để không bỏ lỡ các ưu đãi hấp dẫn từ ThinkHub</p>

                <div class="benefits-box">
                    <ul class="benefits-list">
                        <li>Chiết khấu đến 5% khi mua các sản phẩm tại ThinkHub</li>
                        <li>Miễn phí giao hàng cho thành viên TMEM, TVIP và cho đơn hàng từ 300.000đ</li>
                        <li>Tặng voucher sinh nhật đến 500.000đ cho khách hàng thành viên</li>
                        <li>Trợ giá thu cũ lên đời đến 1 triệu</li>
                        <li>Thăng hạng nhận voucher đến 300.000đ</li>
                        <li>Đặc quyền T-Student/T-Teacher ưu đãi thêm đến 10%</li>
                    </ul>
                </div>

                <a href="#" class="details-link">Xem chi tiết chính sách ưu đãi Tmember ></a>

            </div>

            <div class="login-section">
                <h2>Đăng nhập</h2>

                <form class="login-form" @submit.prevent="handleLogin">
                    <div class="form-group">
                        <label for="phone">Email hoặc số điện thoại</label>
                        <input type="text" id="phone" v-model="formData.loginId"
                            placeholder="Nhập Email hoặc số điện thoại"
                            :class="['form-control', error.loginId ? 'is-invalid' : '']">
                        <div v-if="error.loginId" class="invalid-feedback d-block">{{ error.loginId }}</div>
                    </div>

                    <div class="form-group">
                        <label for="password">Mật khẩu</label>
                        <div class="password-wrapper">
                            <input v-model="formData.password" id="password" name="password"
                                placeholder="Nhập mật khẩu của bạn" :type="passwordFieldType">
                            <span @click="togglePasswordVisibility" class="toggle-password"><i
                                    :class="passwordFieldType === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash'"></i></span>
                        </div>
                        <div v-if="error.password" class="invalid-feedback d-block">{{ error.password }}</div>
                    </div>

                    <button type="submit" class="btn-login" :disabled="isLoading">
                        {{ isLoading ? 'Đang xử lý...' : 'Đăng nhập' }}
                    </button>
                </form>

                <a href="#" class="forgot-password">Quên mật khẩu?</a>

                <div class="separator">Hoặc đăng nhập bằng</div>

                <div class="social-login">
                    <button class="social-btn" @click="loginWithGoogle" type="button">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg"
                            alt="Google">
                        Google
                    </button>
                    
                    <button class="social-btn" type="button">
                        <img src="../../assets/facebook-svgrepo-com.svg" width="500px">
                        Facebook
                    </button>
                </div>

                <p class="register-link">
                    Bạn chưa có tài khoản? <router-link :to="{ name: 'register' }">Đăng kí ngay</router-link>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

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
    /* QUAN TRỌNG: Để nút Close định vị tuyệt đối theo khung này */
    position: relative; 
}

/* --- STYLE CHO NÚT CLOSE (DẤU X) --- */
.close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: transparent;
    border: none;
    font-size: 1.5rem;
    color: #999;
    cursor: pointer;
    z-index: 10;
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
    transform: rotate(90deg); /* Hiệu ứng xoay nhẹ khi hover */
}

/* --- END STYLE CLOSE BTN --- */

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
}

.password-wrapper .toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #888;
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
    color: #fff;
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

/* Responsive cho điện thoại */
@media (max-width: 768px) {
    .login-container {
        grid-template-columns: 1fr;
    }

    .promo-section {
        display: none;
    }

    .login-section {
        border-left: none;
        padding-top: 60px; /* Thêm padding top để tránh đè lên nút X trên mobile */
    }
}
</style>