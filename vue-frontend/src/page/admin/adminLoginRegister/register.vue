<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const API_URL = import.meta.env.VITE_API_BASE_URL;
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
        password_confirmation: formData.confirmPassword,
        role: formData.role,
    };

    try {
        const res = await axios.post(`${API_URL}/admin_Account`, payload);
        if (res.status === 201 || res.status === 200) {
            Swal.fire({
                icon: 'success',
                title: 'Đăng ký thành công',
                text: `Tài khoản ${payload.username} đã được tạo!`,
                confirmButtonText: 'Đăng nhập ngay',
            }).then(() => {
                router.push({name: 'admin-login'});
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
    <div class="register-page bg-body-secondary">
        <div class="register-box">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h1 class="mb-0 text-center link-offset-2"><b>Đăng ký</b></h1>
                </div>
                <div class="card-body register-card-body">
                    <p class="register-box-msg">Đăng ký thành viên mới</p>

                    <form @submit.prevent="handleRegister">
                        <div class="input-group mb-3">
                            <div class="form-floating">
                                <input id="registerUsername" type="text" class="form-control"
                                    :class="{ 'is-invalid': errors.username }" v-model="formData.username" placeholder="" />
                                <label for="registerUsername">Tên hiển thị</label>
                            </div>
                            <div class="input-group-text">
                                <span class="bi bi-person"></span>
                            </div>
                            <div class="invalid-feedback" v-if="errors.username">{{ errors.username }}</div>
                        </div>

                        <div class="input-group mb-3">
                            <div class="form-floating">
                                <input id="registerEmail" type="email" class="form-control"
                                    :class="{ 'is-invalid': errors.email }" v-model="formData.email" placeholder="" />
                                <label for="registerEmail">Email</label>
                            </div>
                            <div class="input-group-text">
                                <span class="bi bi-envelope"></span>
                            </div>
                            <div class="invalid-feedback" v-if="errors.email">{{ errors.email }}</div>
                        </div>

                        <div class="input-group mb-3">
                            <div class="form-floating">
                                <input :type="passwordFieldType" id="registerPassword" class="form-control"
                                    :class="{ 'is-invalid': errors.password }" v-model="formData.password" placeholder="" />
                                <label for="registerPassword">Mật khẩu</label>
                            </div>
                            <button type="button" class="input-group-text" @click="togglePasswordVisibility('password')">
                                <span
                                    :class="passwordFieldType === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye'"></span>
                            </button>
                            <div class="invalid-feedback" v-if="errors.password">{{ errors.password }}</div>
                        </div>

                        <div class="input-group mb-3">
                            <div class="form-floating">
                                <input :type="confirmPasswordFieldType" id="confirmPassword" class="form-control"
                                    :class="{ 'is-invalid': errors.confirmPassword }" v-model="formData.confirmPassword"
                                    placeholder="" />
                                <label for="confirmPassword">Xác nhận mật khẩu</label>
                            </div>
                            <button type="button" class="input-group-text" @click="togglePasswordVisibility('confirm')">
                                <span
                                    :class="confirmPasswordFieldType === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye'"></span>
                            </button>
                            <div class="invalid-feedback" v-if="errors.confirmPassword">{{ errors.confirmPassword }}</div>
                        </div>

                        <div class="row">
                            <div class="col-8 d-inline-flex align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="agreeTerms" v-model="agreedToTerms"
                                        :class="{ 'is-invalid': errors.terms }" />
                                    <label class="form-check-label" for="agreeTerms">
                                        Tôi đồng ý với <a href="#">điều khoản</a>
                                    </label>
                                    <div class="invalid-feedback" v-if="errors.terms" style="display:block">
                                        {{ errors.terms }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary" :disabled="isLoading">
                                        <span v-if="isLoading" class="spinner-border spinner-border-sm"
                                            aria-hidden="true"></span>
                                        <span v-else>Đăng ký</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <p v-if="errors.general" class="text-danger mt-3">{{ errors.general }}</p>

                    <p class="mb-0 mt-3">
                        <router-link :to="{name: 'admin-login'}" class="text-center">
                            Tôi đã có tài khoản
                        </router-link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.register-page {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>