<script setup>
import { ref, reactive, onMounted } from 'vue';
import apiService from '../../../apiService';
import Swal from 'sweetalert2';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();

const formData = reactive({
    token: '',
    email: '',
    password: '',
    password_confirmation: ''
});

const errors = reactive({
    password: '',
    general: ''
});

const isLoading = ref(false);

// Lấy token và email từ URL khi trang load
onMounted(() => {
    formData.token = route.query.token || '';
    formData.email = route.query.email || '';

    if (!formData.token || !formData.email) {
        errors.general = 'Liên kết không hợp lệ hoặc thiếu thông tin.';
    }
});

const handleResetPassword = async () => {
    errors.password = '';
    errors.general = '';

    if (formData.password !== formData.password_confirmation) {
        errors.password = 'Mật khẩu nhập lại không khớp.';
        return;
    }

    if (formData.password.length < 8) {
        errors.password = 'Mật khẩu phải có ít nhất 8 ký tự.';
        return;
    }

    isLoading.value = true;

    try {
        const response = await apiService.post('/admin/reset-password', formData);

        await Swal.fire({
            icon: 'success',
            title: 'Thành công!',
            text: response.data.message,
            confirmButtonText: 'Đăng nhập ngay',
            confirmButtonColor: '#009981'
        });

        router.push({ name: 'admin-login' });

    } catch (error) {
        if (error.response && error.response.data) {
            if (error.response.data.errors && error.response.data.errors.password) {
                errors.password = error.response.data.errors.password[0];
            } else {
                errors.general = error.response.data.message || 'Liên kết đã hết hạn hoặc không hợp lệ.';
            }
        } else {
            errors.general = 'Lỗi kết nối máy chủ.';
        }
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light p-4">
        <div class="container-fluid" style="max-width: 500px;">
            <div class="card shadow-lg border-0 rounded-3 overflow-hidden">
                <div class="p-4 p-md-5 bg-white">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-dark mb-2">Đặt lại mật khẩu</h2>
                        <p class="text-muted">Nhập mật khẩu mới cho tài khoản {{ formData.email }}</p>
                    </div>

                    <form @submit.prevent="handleResetPassword">
                        
                        <div v-if="errors.general" class="alert alert-danger" role="alert">
                            {{ errors.general }}
                        </div>

                        <!-- Mật khẩu mới -->
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu mới</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input 
                                    type="password" 
                                    v-model="formData.password" 
                                    class="form-control"
                                    :class="{ 'is-invalid': errors.password }"
                                    placeholder="Tối thiểu 8 ký tự"
                                    required
                                />
                            </div>
                            <div v-if="errors.password" class="invalid-feedback d-block">
                                {{ errors.password }}
                            </div>
                        </div>

                        <!-- Nhập lại mật khẩu -->
                        <div class="mb-4">
                            <label class="form-label">Xác nhận mật khẩu</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-check-lg"></i></span>
                                <input 
                                    type="password" 
                                    v-model="formData.password_confirmation" 
                                    class="form-control"
                                    placeholder="Nhập lại mật khẩu mới"
                                    required
                                />
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" :disabled="isLoading || !!errors.general" class="btn btn-primary-custom btn-lg fw-medium">
                                <span v-if="isLoading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                <span>Xác nhận đổi mật khẩu</span>
                            </button>
                        </div>
                    </form>
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
.btn-primary-custom:hover {
    background-color: #007d6a;
    border-color: #007d6a;
}
.btn-primary-custom:disabled {
    background-color: #009981;
    opacity: 0.7;
}
.form-control:focus {
    border-color: #009981;
    box-shadow: 0 0 0 0.25rem rgba(0, 153, 129, 0.25);
}
</style>