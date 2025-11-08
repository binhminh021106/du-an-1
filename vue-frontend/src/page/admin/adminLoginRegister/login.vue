<script setup>
import { ref, reactive } from 'vue';
// Giả sử bạn import các thư viện này ở file main.js hoặc chúng có sẵn
// import axios from 'axios';
// import Swal from 'sweetalert2';
// import { useRouter } from 'vue-router';

// --- BẮT ĐẦU LOGIC GỐC CỦA BẠN ---

// const router = useRouter(); // Giả lập router
const router = { push: (route) => console.log('Chuyển hướng đến:', route.name) };

// Giả lập axios và Swal để demo
const axios = {
    post: (url, payload) => {
        console.log('AXIOS POST:', url, payload);
        return new Promise((resolve, reject) => {
            setTimeout(() => {
                if (payload.username === "error" || payload.password !== "12345678") {
                    reject({
                        response: {
                            status: 401,
                            data: { message: "Tên đăng nhập hoặc mật khẩu không chính xác." }
                        }
                    });
                } else {
                    resolve({ status: 200, data: { token: 'mock-jwt-token-12345' } });
                }
            }, 1500);
        });
    }
};

const Swal = {
    fire: (options) => {
        console.log('SWAL:', options.title);
        // Thay thế alert() bằng console.log() để tránh bị chặn
        console.log(`SWAL: ${options.title}\n${options.text || ''}`);
        return Promise.resolve({ isConfirmed: true });
    }
};
// --- KẾT THÚC GIẢ LẬP ---


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

const togglePasswordVisibility = () => {
    passwordFieldType.value = passwordFieldType.value === 'password' ? 'text' : 'password';
};

const handleLogin = async () => {
    errors.username = '';
    errors.password = '';
    errors.general = '';

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
        // Thay thế URL API thực tế của bạn ở đây
        const response = await axios.post('/api/login', formData);
        const token = response.data.token;
        // Tạm thời comment out localStorage để tránh lỗi trong môi trường demo
        // localStorage.setItem('authToken', token);
        console.log('Đã nhận token:', token);

        await Swal.fire({
            icon: 'success',
            title: 'Đăng nhập thành công!',
            text: 'Đang chuyển hướng...',
            timer: 1500,
            showConfirmButton: false,
        });

        router.push({ name: 'admin-dashboard' });

    } catch (error) {
        if (error.response) {
            const status = error.response.status;
            const data = error.response.data;

            if (status === 422 && data.errors) {
                errors.username = data.errors.username ? data.errors.username[0] : '';
                errors.password = data.errors.password ? data.errors.password[0] : '';
            } else if (status === 401 || status === 403) {
                errors.general = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
            } else {
                errors.general = 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
            }
        } else {
            errors.general = 'Không thể kết nối đến máy chủ.';
        }
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light p-4">
        <div class="container-fluid" style="max-width: 1100px;">
            <div class="card shadow-lg border-0 rounded-3 overflow-hidden">
                <div class="row g-0">

                    <div class="col-lg-5 d-none d-lg-flex flex-column justify-content-center text-white p-5"
                        style="background-color: #009981;">
                        <h1 class="fw-bolder display-5 mb-4">Chào mừng trở lại!</h1>
                        <p class="fs-5" style="opacity: 0.9;">
                            Đăng nhập để tiếp tục phiên làm việc của bạn.
                        </p>
                        <div class="mt-5">
                            <span class="d-block rounded-pill"
                                style="width: 80px; height: 5px; background-color: rgba(255, 255, 255, 0.5);"></span>
                        </div>
                    </div>

                    <div class="col-lg-7 bg-white">
                        <div class="p-4 p-md-5">
                            <h2 class="fw-bold text-dark mb-4 text-center text-lg-start h1">Đăng nhập</h2>

                            <form @submit.prevent="handleLogin">

                                <div v-if="errors.general" class="alert alert-danger" role="alert">
                                    {{ errors.general }}
                                </div>

                                <div class="mb-3">
                                    <label for="loginUsername" class="form-label">Email hoặc Tên đăng nhập</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                        <input id="loginUsername" type="text" v-model="formData.username"
                                            placeholder="Nhập email hoặc tên đăng nhập"
                                            :class="['form-control', errors.username ? 'is-invalid' : '']" />
                                    </div>
                                    <div v-if="errors.username" class="invalid-feedback d-block">{{ errors.username }}
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="loginPassword" class="form-label">Mật khẩu</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                        <input id="loginPassword" :type="passwordFieldType" v-model="formData.password"
                                            placeholder="Nhập mật khẩu của bạn"
                                            :class="['form-control', errors.password ? 'is-invalid' : '']" />
                                        <button type="button" @click="togglePasswordVisibility"
                                            class="btn btn-outline-secondary">
                                            <i
                                                :class="passwordFieldType === 'password' ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill'"></i>
                                        </button>
                                    </div>
                                    <div v-if="errors.password" class="invalid-feedback d-block">{{ errors.password }}
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="rememberMe">
                                        <label class="form-check-label" for="rememberMe">
                                            Ghi nhớ tôi
                                        </label>
                                    </div>
                                    <a href="#" class="text-decoration-none fw-medium" style="color: #009981;">Quên mật
                                        khẩu?</a>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" :disabled="isLoading"
                                        class="btn btn-primary-custom btn-lg fw-medium">
                                        <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"
                                            role="status" aria-hidden="true"></span>
                                        <span>{{ isLoading ? 'Đang xử lý...' : 'Đăng nhập' }}</span>
                                    </button>
                                </div>
                            </form>

                            <p class="text-center text-muted mt-4 mb-0">
                                Chưa có tài khoản?
                                <router-link :to="{ name: 'admin-register' }" class="fw-medium text-decoration-none"
                                    style="color: #009981;">
                                    Đăng ký thành viên mới
                                </router-link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.btn-primary-custom {
    background-color: #009981;
    border-color: #009981;
    color: white;
}

.btn-primary-custom:hover,
.btn-primary-custom:focus {
    background-color: #007d6a;
    border-color: #007d6a;
    color: white;
}

.btn-primary-custom:disabled,
.btn-primary-custom.disabled {
    background-color: #009981;
    border-color: #009981;
    opacity: 0.75;
}

.form-control:focus,
.form-check-input:focus {
    border-color: #009981;
    box-shadow: 0 0 0 0.25rem rgba(0, 153, 129, 0.25);
}

.form-check-input:checked {
    background-color: #009981;
    border-color: #009981;
}

.input-group-text {
    width: 42px;
    justify-content: center;
}
</style>