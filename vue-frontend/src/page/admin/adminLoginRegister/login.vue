<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
// import { useRouter } from 'vue-router'; // Bỏ comment nếu bạn dùng vue-router để điều hướng

// const router = useRouter(); // Bỏ comment nếu bạn dùng vue-router

// --- Bổ sung logic còn thiếu ---
const formData = reactive({
    username: '',
    password: '',
});

const errors = reactive({
    username: '',
    password: '',
    general: ''
});

const isLoading = ref(false);
const passwordFieldType = ref('password');

/**
 * Chuyển đổi qua lại giữa hiển thị mật khẩu và ẩn mật khẩu
 */
const togglePasswordVisibility = () => {
    passwordFieldType.value = passwordFieldType.value === 'password' ? 'text' : 'password';
};

/**
 * Xử lý logic khi người dùng nhấn nút Đăng nhập
 */
const handleLogin = async () => {
    // Reset lỗi cũ
    errors.username = '';
    errors.password = '';
    errors.general = '';

    // Kiểm tra validation cơ bản
    if (!formData.username) {
        errors.username = 'Vui lòng nhập tên hiển thị hoặc email.';
        return;
    }
    if (!formData.password) {
        errors.password = 'Vui lòng nhập mật khẩu.';
        return;
    }

    isLoading.value = true;

    try {
        // Gửi request đến API login
        // !!! THAY THẾ '/api/login' BẰNG ENDPOINT LOGIN CỦA BẠN
        const response = await axios.post('/api/login', formData);

        // Giả sử API trả về token khi đăng nhập thành công
        const token = response.data.token;
        localStorage.setItem('authToken', token); // Lưu token (ví dụ)

        // Thông báo thành công
        await Swal.fire({
            icon: 'success',
            title: 'Đăng nhập thành công!',
            text: 'Đang chuyển hướng đến trang chủ...',
            timer: 2000,
            showConfirmButton: false,
        });

        // Điều hướng đến trang chủ (hoặc dashboard)
        // router.push('/'); // Bỏ comment nếu bạn dùng vue-router
        window.location.href = '/'; // Hoặc reload trang

    } catch (error) {
        if (error.response) {
            const status = error.response.status;
            const data = error.response.data;

            if (status === 422) {
                // Lỗi validation từ server
                if (data.errors) {
                    errors.username = data.errors.username ? data.errors.username[0] : '';
                    errors.password = data.errors.password ? data.errors.password[0] : '';
                }
            } else if (status === 401 || status === 403) {
                // Sai thông tin đăng nhập
                errors.general = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
            } else {
                // Lỗi chung
                errors.general = 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
            }
        } else {
            // Lỗi mạng hoặc lỗi không xác định
            errors.general = 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại đường truyền.';
        }
    } finally {
        isLoading.value = false;
    }
};
// --- Hết phần bổ sung logic ---
</script>

<template>
    
    <div class="login-layout">
        <header
            class="header-nav container-fluid d-flex justify-content-between align-items-center p-3 border-bottom bg-white shadow-sm">
            <div class="logo fw-bold fs-5 text-dark">
                <img src="/icon.png" alt="Logo OutfitVN" width="200px">
            </div>
            <div class="login-link">
                <span class="text-muted me-2">Chưa có tài khoản?</span>
                <router-link class="text-dark fw-bold text-decoration-none" to="/admin/register">Đăng ký</router-link>
            </div>
        </header>

        <div class="container-fluid main-content">
            <div class="row g-0 h-100">

                <div class="col-md-6 d-none d-md-block image-column">
                </div>

                <div class="col-md-6 col-12 form-column bg-light">

                    <div class="form-wrapper">

                        <h2 class="fw-bold mb-4">Đăng nhập</h2>
                        <form @submit.prevent="handleLogin" novalidate>

                            <div class="form-group-wrapper">
                                <label for="username" class="form-label fw-medium">Tên hiển thị hoặc email</label>
                                <input type="text" id="username" class="form-control"
                                    :class="{ 'is-invalid': errors.username }" v-model="formData.username"
                                    placeholder="admin123" required />
                                <div class="form-field-error">
                                    {{ errors.username }}
                                </div>
                            </div>

                            <div class="form-group-wrapper">
                                <label for="password" class="form-label fw-medium">Mật khẩu</label>
                                <div class="input-group">
                                    <input :type="passwordFieldType" id="password" class="form-control"
                                        :class="{ 'is-invalid': errors.password }" v-model="formData.password"
                                        placeholder="••••••••" required />
                                    <button class="btn btn-outline-secondary" type="button"
                                        @click="togglePasswordVisibility" :class="{ 'border-danger': errors.password }">
                                        <i
                                            :class="passwordFieldType === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                    </button>
                                </div>
                                <div class="form-field-error">
                                    {{ errors.password }}
                                </div>
                            </div>

                            <p v-if="errors.general" class="text-danger small pt-2 mb-3">{{ errors.general }}</p>

                            <button type="submit" class="btn btn-dark w-100 fw-bold" :disabled="isLoading"> <span
                                    v-if="isLoading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                <span role="status">{{ isLoading ? ' Đang xử lý...' : 'Đăng nhập' }}</span>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Sửa tên class cho khớp với template */
.login-layout {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.main-content {
    flex-grow: 1;
}

.row {
    flex-grow: 1;
}

.image-column {
    background-image: url('https://images.unsplash.com/photo-1556761175-5973dc0f32e7?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto-format&fit=crop&w=1932&q=80');
    background-size: cover;
    background-position: center;
    min-height: 100%;
}

.form-column {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem;
}

.form-wrapper {
    width: 100%;
    max-width: 440px;
}

.form-group-wrapper {
    position: relative;
    margin-bottom: 0;
    padding-bottom: 1.5rem;
}

.form-field-error {
    position: absolute;
    bottom: 0.15rem;
    left: 0;
    width: 100%;
    font-size: 0.875em;
    color: var(--bs-danger);
    height: 1.25rem;
    padding-top: 0.1rem;
}

.input-group .form-control.is-invalid+.btn.btn-outline-secondary {
    border-color: var(--bs-danger);
}
</style>