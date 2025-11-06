<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const API_URL = import.meta.env.VITE_API_BASE_URL;

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
const agreedToTerms = ref(false)
const router = useRouter()

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
    errors.username = '';
    errors.email = '';
    errors.password = '';
    errors.confirmPassword = '';
    errors.general = '';
    errors.terms = '';

    let isValid = true;
    const re = /^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]+$/;

    if (!formData.username.trim()) {
        errors.username = 'Vui lòng nhập tên hiển thị.';
        isValid = false;
    } else if (!re.test(formData.username)) {
        errors.username = 'Tên hiển thị chỉ được dùng chữ và số.'
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
    } else if (formData.password && formData.password !== formData.confirmPassword) {
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
    if (!validateForm()) {
        return;
    }

    isLoading.value = true;
    const payload = {
        username: formData.username,
        email: formData.email,
        password: formData.password,
        password_confirmation: formData.confirmPassword,
        role: formData.role,
    };

    

    try {
        const res = await axios.post(`${API_URL}/admin_Account`, payload)
        if (res.status === 201 || res.status === 200) {
            Swal.fire({
                icon: 'success',
                title: 'Đã tạo tài khoản thành công',
                text: `Tài khoản ${payload.username} đã được tạo!`,
                showConfirmButton: true,
                confirmButtonText: 'Đi đến trang đăng nhập',
                confirmButtonColor: '#000'
            }).then(() => {
                // router.push('/login')
                formData.username = "";
                formData.email = "";
                formData.password = "";
                formData.confirmPassword = "";
                agreedToTerms.value = false;
            })
        }
    } catch (apiError) {
        console.error("Lỗi đăng ký:", apiError);
        if (apiError.response && apiError.response.data && apiError.response.data.errors) {
            const serverErrors = apiError.response.data.errors;
            if (serverErrors.email) {
                errors.email = serverErrors.email[0];
            }
            if (serverErrors.username) {
                errors.username = serverErrors.username[0];
            }
        } else {
            Swal.fire({
                title: 'Thất bại!',
                text: 'Không thể kết nối đến máy chủ. Vui lòng thử lại sau.',
                icon: 'error',
                confirmButtonText: 'Đóng',
                confirmButtonColor: '#000'
            });
        }
    } finally {
        isLoading.value = false;
    }
};

</script>

<template>
    <div class="register-layout">

        <header
            class="header-nav container-fluid d-flex justify-content-between align-items-center p-3 border-bottom bg-white shadow-sm">
            <div class="logo fw-bold fs-5 text-dark">
                <img src="../../../../public/icon.png" alt="" width="70px">
            </div>
            <div class="login-link">
                <span class="text-muted me-2">Đã có tài khoản?</span>
                <a href="#" class="text-dark fw-bold text-decoration-none">Đăng nhập</a>
            </div>
        </header>

        <div class="container-fluid main-content">
            <div class="row g-0 h-100">

                <div class="col-md-6 d-none d-md-block image-column">
                </div>

                <div class="col-md-6 col-12 form-column bg-light">

                    <div class="form-wrapper">

                        <h2 class="fw-bold mb-4">Tạo tài khoản Admin</h2>

                        <form @submit.prevent="handleRegister" novalidate>

                            <div class="form-group-wrapper">
                                <label for="username" class="form-label fw-medium">Tên hiển thị</label>
                                <input type="text" id="username" class="form-control"
                                    :class="{ 'is-invalid': errors.username }" v-model="formData.username"
                                    placeholder="admin123" required />
                                <div class="form-field-error">
                                    {{ errors.username }}
                                </div>
                            </div>

                            <div class="form-group-wrapper">
                                <label for="email" class="form-label fw-medium">Email</label>
                                <input type="email" id="email" class="form-control"
                                    :class="{ 'is-invalid': errors.email }" v-model="formData.email"
                                    placeholder="admin@email.com" required />
                                <div class="form-field-error">
                                    {{ errors.email }}
                                </div>
                            </div>

                            <div class="form-group-wrapper">
                                <label for="password" class="form-label fw-medium">Mật khẩu</label>
                                <div class="input-group">
                                    <input :type="passwordFieldType" id="password" class="form-control"
                                        :class="{ 'is-invalid': errors.password }" v-model="formData.password"
                                        placeholder="••••••••" required />
                                    <button class="btn btn-outline-secondary" type="button"
                                        @click="togglePasswordVisibility('password')"
                                        :class="{ 'border-danger': errors.password }">
                                        <i
                                            :class="passwordFieldType === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                    </button>
                                </div>
                                <div class="form-field-error">
                                    {{ errors.password }}
                                </div>
                            </div>

                            <div class="form-group-wrapper">
                                <label for="confirmPassword" class="form-label fw-medium">Xác nhận mật khẩu</label>
                                <div class="input-group">
                                    <input :type="confirmPasswordFieldType" id="confirmPassword" class="form-control"
                                        :class="{ 'is-invalid': errors.confirmPassword }"
                                        v-model="formData.confirmPassword" placeholder="••••••••" required />
                                    <button class="btn btn-outline-secondary" type="button"
                                        @click="togglePasswordVisibility('confirm')"
                                        :class="{ 'border-danger': errors.confirmPassword }">
                                        <i
                                            :class="confirmPasswordFieldType === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                    </button>
                                </div>
                                <div class="form-field-error">
                                    {{ errors.confirmPassword }}
                                </div>
                            </div>

                            <p v-if="errors.general" class="text-danger small pt-2 mb-3">{{ errors.general }}</p>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="termsCheckbox"
                                    v-model="agreedToTerms" :class="{ 'is-invalid': errors.terms }">
                                <label class="form-check-label small" for="termsCheckbox">
                                    Tôi đã đọc và đồng ý với
                                    <a href="/terms" target="_blank" class="text-dark">Thỏa thuận người dùng</a> và
                                    <a href="/privacy" target="_blank" class="text-dark">Chính sách bảo mật</a>.
                                </label>
                                <div class="form-field-error">
                                    {{ errors.terms }}
                                </div>
                            </div>

                            <button type="submit" class="btn btn-dark w-100 fw-bold"
                                :disabled="isLoading || !agreedToTerms">
                                <span v-if="isLoading" class="spinner-border spinner-border-sm"
                                    aria-hidden="true"></span>
                                <span role="status">{{ isLoading ? ' Đang xử lý...' : 'Tạo tài khoản' }}</span>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.register-layout {
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