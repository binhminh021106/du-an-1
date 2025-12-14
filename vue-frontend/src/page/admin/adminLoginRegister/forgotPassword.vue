<script setup>
import { ref, reactive } from 'vue';
import apiService from '../../../apiService'; // Đảm bảo đường dẫn đúng
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const router = useRouter();
const email = ref('');
const isLoading = ref(false);
const errorMsg = ref('');

const handleForgotPassword = async () => {
    errorMsg.value = '';
    
    if (!email.value) {
        errorMsg.value = 'Vui lòng nhập địa chỉ email.';
        return;
    }

    isLoading.value = true;

    try {
        const response = await apiService.post('/admin/forgot-password', { email: email.value });
        
        await Swal.fire({
            icon: 'success',
            title: 'Đã gửi email!',
            text: response.data.message,
            confirmButtonColor: '#009981'
        });

        // Tùy chọn: Chuyển về trang login hoặc giữ nguyên
        // router.push({ name: 'admin-login' });

    } catch (error) {
        if (error.response && error.response.data) {
             // Xử lý lỗi validate trả về từ Laravel
            if (error.response.data.errors && error.response.data.errors.email) {
                errorMsg.value = error.response.data.errors.email[0];
            } else {
                errorMsg.value = error.response.data.message || 'Có lỗi xảy ra.';
            }
        } else {
            errorMsg.value = 'Không thể kết nối đến máy chủ.';
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
                        <h2 class="fw-bold text-dark mb-2">Quên mật khẩu?</h2>
                        <p class="text-muted">Nhập email của bạn để nhận liên kết đặt lại mật khẩu.</p>
                    </div>

                    <form @submit.prevent="handleForgotPassword">
                        
                        <div v-if="errorMsg" class="alert alert-danger" role="alert">
                            {{ errorMsg }}
                        </div>

                        <div class="mb-4">
                            <label for="email" class="form-label">Email đăng ký</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                                <input 
                                    id="email" 
                                    type="email" 
                                    v-model="email" 
                                    class="form-control" 
                                    :class="{ 'is-invalid': errorMsg }"
                                    placeholder="name@example.com" 
                                    required
                                />
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" :disabled="isLoading" class="btn btn-primary-custom btn-lg fw-medium">
                                <span v-if="isLoading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                                <span>{{ isLoading ? 'Đang gửi...' : 'Gửi liên kết' }}</span>
                            </button>
                            
                            <router-link :to="{ name: 'admin-login' }" class="btn btn-outline-secondary btn-lg fw-medium mt-2">
                                Quay lại đăng nhập
                            </router-link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Tái sử dụng style từ login.vue để đồng bộ */
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

.form-control:focus {
    border-color: #009981;
    box-shadow: 0 0 0 0.25rem rgba(0, 153, 129, 0.25);
}

.input-group-text {
    width: 42px;
    justify-content: center;
}
</style>