<script setup>
import { reactive, ref } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const router = useRouter();

const formData = reactive({
    username: '',
    email: '',
    password: '',
    confirmPassword: '',
    role: 'nhanvien'
});

const errors = reactive({
    username: '',
    email: '',
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
    Object.keys(errors).forEach(key => errors[key] = '');
    let isValid = true;
    const re = /^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]+$/;

    if (!formData.username.trim()) {
        errors.username = 'Vui lòng nhập tên hiển thị.';
        isValid = false;
    } else if (!re.test(formData.username)) {
        errors.username = 'Tên hiển thị chỉ được dùng chữ và số.';
        isValid = false;
    }

    if (!formData.email) {
        errors.email = 'Vui lòng nhập email.';
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
        errors.email = 'Email không đúng định dạng.';
        isValid = false;
    }

    if (!formData.password) {
        errors.password = 'Vui lòng nhập mật khẩu.';
        isValid = false;
    } else if (formData.password.length < 8) {
        errors.password = 'Mật khẩu phải có ít nhất 8 ký tự.';
        isValid = false;
    }

    if (!formData.confirmPassword) {
        errors.confirmPassword = 'Vui lòng xác nhận mật khẩu.';
        isValid = false;
    } else if (formData.password !== formData.confirmPassword) {
        errors.confirmPassword = 'Mật khẩu xác nhận không khớp!';
        isValid = false;
    }

    if (!agreedToTerms.value) {
        errors.terms = 'Bạn phải đồng ý với điều khoản để tiếp tục.';
        isValid = false;
    }

    return isValid;
};

const handleRegister = async () => {
    if (!validateForm()) return;

    isLoading.value = true;
    const payload = {
        username: formData.username,
        email: formData.email,
        password: formData.password,
        role: formData.role,
    };

    try {
        const res = await apiService.post(`/users`, payload);
        if (res.status === 201 || res.status === 200) {
            Swal.fire({
                icon: 'success',
                title: 'Đăng ký thành công',
                text: `Tài khoản ${payload.username} đã được tạo!`,
                confirmButtonText: 'Đăng nhập ngay',
            }).then(() => {
                router.push({ name: 'admin-login' });
                Object.assign(formData, { username: '', email: '', password: '', confirmPassword: '' });
                agreedToTerms.value = false;
            });
        }

    } catch (apiError) {

        if (apiError.response?.data?.errors) {
            const serverErrors = apiError.response.data.errors;
            if (serverErrors.email) errors.email = serverErrors.email[0];
            if (serverErrors.username) errors.username = serverErrors.username[0];
        } else {

            Swal.fire({
                icon: 'error',
                title: 'Thất bại',
                text: 'Không thể kết nối đến máy chủ.',
            });
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
                        <h1 class="fw-bolder display-5 mb-4">Chào mừng!</h1>
                        <p class="fs-5" style="opacity: 0.9;">
                            Đăng ký để bắt đầu quản lý công việc của bạn một cách hiệu quả nhất.
                        </p>
                        <div class="mt-5">
                            <span class="d-block rounded-pill"
                                style="width: 80px; height: 5px; background-color: rgba(255, 255, 255, 0.5);"></span>
                        </div>
                    </div>

                    <div class="col-lg-7 bg-white">
                        <div class="p-4 p-md-5">
                            <h2 class="fw-bold text-dark mb-4 text-center text-lg-start h1">Tạo tài khoản mới</h2>

                            <form @submit.prevent="handleRegister">

                                <div class="mb-3">
                                    <label for="registerUsername" class="form-label">Tên hiển thị</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                        <input id="registerUsername" type="text" v-model="formData.username"
                                            placeholder="VD: abc123"
                                            :class="['form-control', errors.username ? 'is-invalid' : '']" />
                                    </div>
                                    <div v-if="errors.username" class="invalid-feedback d-block">{{ errors.username }}
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="registerEmail" class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                                        <input id="registerEmail" type="email" v-model="formData.email"
                                            placeholder="abc123@gmail.com"
                                            :class="['form-control', errors.email ? 'is-invalid' : '']" />
                                    </div>
                                    <div v-if="errors.email" class="invalid-feedback d-block">{{ errors.email }}</div>
                                </div>

                                <div class="mb-3">
                                    <label for="registerPassword" class="form-label">Mật khẩu</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                        <input id="registerPassword" :type="passwordFieldType"
                                            v-model="formData.password" placeholder="Tối thiểu 8 ký tự"
                                            :class="['form-control', errors.password ? 'is-invalid' : '']" />
                                        <button type="button" @click="togglePasswordVisibility('password')"
                                            class="btn btn-outline-secondary">
                                            <i
                                                :class="passwordFieldType === 'password' ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill'"></i>
                                        </button>
                                    </div>
                                    <div v-if="errors.password" class="invalid-feedback d-block">{{ errors.password }}
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirmPassword" class="form-label">Xác nhận mật khẩu</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                        <input id="confirmPassword" :type="confirmPasswordFieldType"
                                            v-model="formData.confirmPassword" placeholder="Nhập lại mật khẩu"
                                            :class="['form-control', errors.confirmPassword ? 'is-invalid' : '']" />
                                        <button type="button" @click="togglePasswordVisibility('confirm')"
                                            class="btn btn-outline-secondary">
                                            <i
                                                :class="confirmPasswordFieldType === 'password' ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill'"></i>
                                        </button>
                                    </div>
                                    <div v-if="errors.confirmPassword" class="invalid-feedback d-block">{{
                                        errors.confirmPassword }}</div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input id="agreeTerms" type="checkbox" v-model="agreedToTerms"
                                            :class="['form-check-input', errors.terms ? 'is-invalid' : '']" />
                                        <label for="agreeTerms" class="form-check-label">
                                            Tôi đồng ý với
                                            <a href="#" class="text-decoration-none fw-medium"
                                                style="color: #009981;">điều khoản dịch vụ</a>
                                        </label>
                                    </div>
                                    <div v-if="errors.terms" class="invalid-feedback d-block">{{ errors.terms }}</div>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" :disabled="isLoading"
                                        class="btn btn-primary-custom btn-lg fw-medium">
                                        <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"
                                            role="status" aria-hidden="true"></span>
                                        <span>{{ isLoading ? 'Đang xử lý...' : 'Đăng ký' }}</span>
                                    </button>
                                </div>
                            </form>

                            <p class="text-center text-muted mt-4 mb-0">
                                Đã có tài khoản?
                                <router-link :to="{ name: 'admin-login' }" class="fw-medium text-decoration-none"
                                    style="color: #009981;">
                                    Đăng nhập ngay
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