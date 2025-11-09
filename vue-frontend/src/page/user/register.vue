<template>
    <div class="container" style="max-width: 900px;">
        <div class="card shadow-lg border-0 rounded-4 overflow-hidden my-5">
            <div class="row g-0">

                <div class="col-lg-5 d-none d-lg-flex flex-column justify-content-center text-white p-5 rounded-start-4"
                    style="background-color: #009981;">
                    <h1 class="fw-bolder display-4 mb-4">Chào mừng!</h1>
                    <p class="fs-5 opacity-90">
                        Đăng ký để bắt đầu quản lý công việc của bạn một cách hiệu quả nhất.
                    </p>
                    <div class="mt-5 text-center">
                        <span class="d-inline-block rounded-pill mx-1"
                            style="width: 25px; height: 5px; background-color: rgba(255, 255, 255, 0.5);"></span>
                        <span class="d-inline-block rounded-pill mx-1"
                            style="width: 25px; height: 5px; background-color: rgba(255, 255, 255, 0.7);"></span>
                        <span class="d-inline-block rounded-pill mx-1"
                            style="width: 25px; height: 5px; background-color: rgba(255, 255, 255, 0.5);"></span>
                    </div>
                </div>

                <div class="col-lg-7 bg-white p-4 p-md-5 rounded-end-4">
                    <h2 class="fw-bold text-dark mb-5 text-center text-lg-start h1">Tạo tài khoản mới</h2>

                    <form @submit.prevent="handleRegister">
                        <div class="mb-4">
                            <label for="registerUsername" class="form-label fw-medium text-muted">Tên hiển thị</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text border-end-0 bg-transparent"><i
                                        class="bi bi-person-fill text-secondary"></i></span>
                                <input id="registerUsername" type="text" v-model="formData.username"
                                    placeholder="Nhập tên hiển thị của bạn"
                                    :class="['form-control border-start-0', errors.username ? 'is-invalid' : '']" />
                            </div>
                            <div v-if="errors.username" class="invalid-feedback d-block mt-1">{{ errors.username }}
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="registerEmail" class="form-label fw-medium text-muted">Email</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text border-end-0 bg-transparent"><i
                                        class="bi bi-envelope-fill text-secondary"></i></span>
                                <input id="registerEmail" type="email" v-model="formData.email"
                                    placeholder="Nhập địa chỉ email của bạn"
                                    :class="['form-control border-start-0', errors.email ? 'is-invalid' : '']" />
                            </div>
                            <div v-if="errors.email" class="invalid-feedback d-block mt-1">{{ errors.email }}</div>
                        </div>

                        <div class="mb-4">
                            <label for="registerPassword" class="form-label fw-medium text-muted">Mật khẩu</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text border-end-0 bg-transparent"><i
                                        class="bi bi-lock-fill text-secondary"></i></span>
                                <input id="registerPassword" :type="passwordFieldType" v-model="formData.password"
                                    placeholder="Tối thiểu 8 ký tự"
                                    :class="['form-control border-start-0', errors.password ? 'is-invalid' : '']" />
                                <button type="button" @click="togglePasswordVisibility('password')"
                                    class="btn btn-outline-secondary border-start-0 bg-transparent">
                                    <i
                                        :class="passwordFieldType === 'password' ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill'"></i>
                                </button>
                            </div>
                            <div v-if="errors.password" class="invalid-feedback d-block mt-1">{{ errors.password }}
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="confirmPassword" class="form-label fw-medium text-muted">Xác nhận mật
                                khẩu</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text border-end-0 bg-transparent"><i
                                        class="bi bi-lock-fill text-secondary"></i></span>
                                <input id="confirmPassword" :type="confirmPasswordFieldType"
                                    v-model="formData.confirmPassword" placeholder="Nhập lại mật khẩu của bạn"
                                    :class="['form-control border-start-0', errors.confirmPassword ? 'is-invalid' : '']" />
                                <button type="button" @click="togglePasswordVisibility('confirm')"
                                    class="btn btn-outline-secondary border-start-0 bg-transparent">
                                    <i
                                        :class="confirmPasswordFieldType === 'password' ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill'"></i>
                                </button>
                            </div>
                            <div v-if="errors.confirmPassword" class="invalid-feedback d-block mt-1">{{
                                errors.confirmPassword }}</div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input id="agreeTerms" type="checkbox" v-model="agreedToTerms"
                                    :class="['form-check-input', errors.terms ? 'is-invalid' : '']" />
                                <label for="agreeTerms" class="form-check-label text-muted">
                                    Tôi đồng ý với
                                    <a href="#" class="text-decoration-none fw-medium" style="color: #009981;">điều
                                        khoản dịch vụ</a>
                                </label>
                            </div>
                            <div v-if="errors.terms" class="invalid-feedback d-block mt-1">{{ errors.terms }}</div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" :disabled="isLoading"
                                class="btn btn-primary-custom btn-lg fw-medium rounded-pill">
                                <span v-if="isLoading" class="spinner-border spinner-border-sm me-2" role="status"
                                    aria-hidden="true"></span>
                                <span>{{ isLoading ? 'Đang xử lý...' : 'Đăng ký' }}</span>
                            </button>
                        </div>
                    </form>

                    <p class="text-center text-muted mt-5 mb-0">
                        Đã có tài khoản?
                        <router-link :to="{ name: 'admin-login' }" class="fw-bold text-decoration-none"
                            style="color: #009981;">
                            Đăng nhập ngay
                        </router-link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'RegisterUser',
    data() {
        return {
            formData: {
                username: '',
                email: '',
                password: '',
                confirmPassword: ''
            },
            errors: {},
            agreedToTerms: false,
            isLoading: false,
            passwordFieldType: 'password',
            confirmPasswordFieldType: 'password'
        };
    },
    methods: {
        validateForm() {
            this.errors = {};
            if (!this.formData.username) {
                this.errors.username = 'Tên hiển thị không được để trống.';
            }
            if (!this.formData.email) {
                this.errors.email = 'Email không được để trống.';
            } else if (!/\S+@\S+\.\S+/.test(this.formData.email)) {
                this.errors.email = 'Email không hợp lệ.';
            }
            if (!this.formData.password) {
                this.errors.password = 'Mật khẩu không được để trống.';
            } else if (this.formData.password.length < 8) {
                this.errors.password = 'Mật khẩu phải có ít nhất 8 ký tự.';
            }
            if (!this.formData.confirmPassword) {
                this.errors.confirmPassword = 'Xác nhận mật khẩu không được để trống.';
            } else if (this.formData.password !== this.formData.confirmPassword) {
                this.errors.confirmPassword = 'Mật khẩu xác nhận không khớp.';
            }
            if (!this.agreedToTerms) {
                this.errors.terms = 'Bạn phải đồng ý với điều khoản dịch vụ.';
            }
            return Object.keys(this.errors).length === 0;
        },
        handleRegister() {
            if (this.validateForm()) {
                this.isLoading = true;
                // Simulate API call
                setTimeout(() => {
                    alert('Đăng ký thành công!');
                    this.isLoading = false;
                    // You would typically redirect here
                    // this.$router.push({ name: 'admin-dashboard' });
                }, 2000);
            }
        },
        togglePasswordVisibility(field) {
            if (field === 'password') {
                this.passwordFieldType = this.passwordFieldType === 'password' ? 'text' : 'password';
            } else if (field === 'confirm') {
                this.confirmPasswordFieldType = this.confirmPasswordFieldType === 'password' ? 'text' : 'password';
            }
        }
    }
}
</script>

<style scoped>
.btn-primary-custom {
    background-color: #009981;
    border-color: #009981;
    color: white;
    transition: all 0.3s ease;
}

.btn-primary-custom:hover,
.btn-primary-custom:focus {
    background-color: #007d6a;
    border-color: #007d6a;
    color: white;
    box-shadow: 0 4px 8px rgba(0, 153, 129, 0.2);
}

.btn-primary-custom:disabled,
.btn-primary-custom.disabled {
    background-color: #009981;
    border-color: #009981;
    opacity: 0.6;
    cursor: not-allowed;
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
    background-color: transparent;
    border-color: #ced4da;
}

.input-group-text i {
    color: #6c757d;
}

.form-control {
    border-left: none;
    padding-left: 0.75rem;
}

.form-control:not(.is-invalid):focus {
    border-color: #009981;
    box-shadow: 0 0 0 0.25rem rgba(0, 153, 129, 0.25);
}

.btn-outline-secondary {
    border-color: #ced4da;
}

.btn-outline-secondary:hover {
    background-color: #f8f9fa;
    border-color: #009981;
    color: #009981;
}

.btn-outline-secondary:focus {
    box-shadow: 0 0 0 0.25rem rgba(0, 153, 129, 0.25);
}

.card {
    border-radius: 1.5rem !important;
}

.rounded-start-4 {
    border-top-left-radius: 1.5rem !important;
    border-bottom-left-radius: 1.5rem !important;
}

.rounded-end-4 {
    border-top-right-radius: 1.5rem !important;
    border-bottom-right-radius: 1.5rem !important;
}
</style>