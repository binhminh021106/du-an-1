<script setup>
import { ref, reactive } from 'vue';
import apiService from '../../../apiService';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const router = useRouter();
const formData = reactive({
    login_id: '',
    password: '',
});

const errors = reactive({
    login_id: '',
    password: '',
    general: ''
});

const isLoading = ref(false);
const passwordFieldType = ref('password');

const togglePasswordVisibility = () => {
    passwordFieldType.value = passwordFieldType.value === 'password' ? 'text' : 'password';
};

const handleLogin = async () => {
    errors.login_id = '';
    errors.password = '';
    errors.general = '';

    if (!formData.login_id) {
        errors.login_id = 'Vui lòng nhập tên hiển thị hoặc email.';
        return;
    }
    if (!formData.password) {
        errors.password = 'Vui lòng nhập mật khẩu.';
        return;
    }

    isLoading.value = true;

    try {
        const response = await apiService.post('/admin/login', formData);
        const { token, user } = response.data;

        localStorage.setItem('adminToken', token);
        localStorage.setItem('adminData', JSON.stringify(user));

        console.log('Đã lưu adminToken:', token);

        await Swal.fire({
            icon: 'success',
            title: 'Đăng nhập thành công!',
            text: `Chào mừng ${user.fullname || 'Admin'}`,
            timer: 1500,
            confirmButtonText: 'Đi đến trang dashboard',
            confirmButtonColor: '#009981'
        });

        router.push({ name: 'admin-dashboard' });

    } catch (error) {
        if (error.response) {
            const status = error.response.status;
            const data = error.response.data;

            if (status === 422 && data.errors) {
                errors.login_id = data.errors.login_id ? data.errors.login_id[0] : '';
                errors.password = data.errors.password ? data.errors.password[0] : '';
            } else if (status === 401 || status === 403) {
                errors.general = data.message || 'Email/SĐT hoặc mật khẩu không chính xác.';
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
                                    <label for="loginUsername" class="form-label">Email hoặc số điện thoại</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                        <input id="loginUsername" type="text" v-model="formData.login_id"
                                            placeholder="Nhập email hoặc số điện thoại"
                                            :class="['form-control', errors.login_id ? 'is-invalid' : '']" />
                                    </div>
                                    <div v-if="errors.login_id" class="invalid-feedback d-block">{{ errors.login_id }}
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