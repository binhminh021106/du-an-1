<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router'; // Đảm bảo đã import useRouter

// import AdminHeader from '../../../components/admin/adminHeader.vue';
// import AdminFooter from '../../../components/admin/adminFooter.vue';

const router = useRouter();

// --- Logic cũ của bạn ---
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
        localStorage.setItem('authToken', token);

        await Swal.fire({
            icon: 'success',
            title: 'Đăng nhập thành công!',
            text: 'Đang chuyển hướng...',
            timer: 1500,
            showConfirmButton: false,
        });

        router.push({name: 'admin-dashboard'}); 

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
    <div class="login-page bg-body-secondary app-loaded">
        <div class="login-box">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h1 class="mb-0 text-center link-offset-2"><b>Đăng nhập</b></h1>
                </div>
                <div class="card-body login-card-body">
                    <p class="login-box-msg">Đăng nhập để bắt đầu phiên làm việc</p>

                    <form @submit.prevent="handleLogin">
                        <div class="input-group mb-3">
                            <div class="form-floating">
                                <input id="loginUsername" type="text" class="form-control"
                                    :class="{ 'is-invalid': errors.username }" v-model="formData.username" placeholder="" />
                                <label for="loginUsername">Email hoặc Tên đăng nhập</label>
                            </div>
                            <div class="input-group-text">
                                <span class="bi bi-envelope"></span>
                            </div>
                            <div class="invalid-feedback" v-if="errors.username">{{ errors.username }}</div>
                        </div>

                        <div class="input-group mb-3">
                            <div class="form-floating">
                                <input :type="passwordFieldType" id="loginPassword" class="form-control"
                                    :class="{ 'is-invalid': errors.password }" v-model="formData.password" placeholder="" />
                                <label for="loginPassword">Mật khẩu</label>
                            </div>
                            <button type="button" class="input-group-text" @click="togglePasswordVisibility">
                                <span :class="passwordFieldType === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye'"></span>
                            </button>
                            <div class="invalid-feedback" v-if="errors.password">{{ errors.password }}</div>
                        </div>

                        <div v-if="errors.general" class="alert alert-danger" role="alert">
                            {{ errors.general }}
                        </div>

                        <div class="row">
                            <div class="col-8 d-inline-flex align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault" />
                                    <label class="form-check-label" for="flexCheckDefault">
                                        Ghi nhớ tôi
                                    </label>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary" :disabled="isLoading">
                                        <span v-if="isLoading" class="spinner-border spinner-border-sm"
                                            aria-hidden="true"></span>
                                        <span v-else>Đăng nhập</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <p class="mb-1 mt-3">
                        <a href="#">Quên mật khẩu</a>
                    </p>
                    <p class="mb-0">
                        <router-link :to="{name: 'admin-register'}" class="text-center">
                            Đăng ký thành viên mới
                        </router-link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Đảm bảo login-page chiếm toàn màn hình để căn giữa */
.login-page {
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>