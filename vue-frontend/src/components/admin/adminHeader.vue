<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import '@fortawesome/fontawesome-free/css/all.min.css'

const router = useRouter();

// --- CẤU HÌNH URL ---
const ENV_API_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
const BACKEND_URL = ENV_API_URL.replace(/\/api\/?$/, ''); // Loại bỏ /api ở cuối để lấy root domain

// --- STATE ---
const adminUser = ref({}); 
const isUserMenuActive = ref(false);
const userMenuContainer = ref(null);

// --- HELPER XỬ LÝ ẢNH ---
const getAvatarUrl = (path) => {
    if (!path) return null; // Để template xử lý fallback
    if (path.startsWith('http') || path.startsWith('blob:')) return path;
    // Nối domain backend nếu là đường dẫn tương đối
    return `${BACKEND_URL}${path.startsWith('/') ? '' : '/'}${path}`;
};

// --- LOGIC TOGGLE MENU ---
const toggleUserMenu = () => {
    isUserMenuActive.value = !isUserMenuActive.value;
};

const closeUserMenu = (event) => {
    if (userMenuContainer.value && !userMenuContainer.value.contains(event.target)) {
        isUserMenuActive.value = false;
    }
};

// --- XỬ LÝ ĐĂNG XUẤT ---
const handleLogout = () => {
    Swal.fire({
        title: 'Đăng xuất?',
        text: "Bạn muốn thoát khỏi hệ thống quản trị?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#009981',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Đăng xuất ngay',
        cancelButtonText: 'Ở lại'
    }).then((result) => {
        if (result.isConfirmed) {
            localStorage.removeItem('adminToken'); 
            localStorage.removeItem('adminData');
            
            // 2. Reset biến
            adminUser.value = {};

            // 3. Chuyển hướng
            router.push({ name: 'admin-login' });
        }
    });
};

// --- LIFECYCLE ---
onMounted(() => {
    const storedAdmin = localStorage.getItem('adminData');
    if (storedAdmin) {
        try {
            adminUser.value = JSON.parse(storedAdmin);
        } catch (e) {
            console.error("Lỗi parse adminData", e);
        }
    }
    document.addEventListener('click', closeUserMenu);
});

onUnmounted(() => {
    document.removeEventListener('click', closeUserMenu);
});
</script>

<template>
    <nav class="app-header navbar navbar-expand bg-white shadow-sm border-bottom">
        <div class="container-fluid px-4">
            <!-- Left: Toggle Sidebar -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link text-secondary btn mb-2" data-lte-toggle="sidebar" href="#" role="button">
                        <i class="bi bi-list fs-4"></i>
                    </a>
                </li>
            </ul>

            <!-- Right: User Menu -->
            <ul class="navbar-nav ms-auto">
                <!-- Login Button if not logged in -->
                <li class="nav-item" v-if="!adminUser || !adminUser.fullname">
                    <router-link :to="{ name: 'admin-login' }" class="btn btn-outline-primary btn-sm mt-1">
                        <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
                    </router-link>
                </li>

                <!-- Authenticated User Dropdown -->
                <li class="nav-item dropdown user-menu" ref="userMenuContainer" v-else>
                    <!-- Trigger -->
                    <a href="#" class="nav-link d-flex align-items-center gap-2" 
                       :class="{ 'active': isUserMenuActive }" 
                       @click.prevent="toggleUserMenu">
                        
                        <!-- SỬ DỤNG HÀM HELPER getAvatarUrl TẠI ĐÂY -->
                        <img :src="getAvatarUrl(adminUser.avatar_url) || `https://ui-avatars.com/api/?name=${adminUser.fullname}&background=009981&color=fff`"
                             class="user-image rounded-circle object-fit-cover border" 
                             alt="User Image">
                        
                        <div class="d-none d-md-block text-start lh-1">
                            <span class="d-block fw-semibold text-dark small">{{ adminUser.fullname }}</span>
                            <small class="text-muted" style="font-size: 11px;">Administrator</small>
                        </div>

                        <i class="bi bi-chevron-down ms-1 transition-icon" 
                           :class="{ 'rotate-180': isUserMenuActive }"></i>
                    </a>
                    
                    <!-- Dropdown Content -->
                    <div class="dropdown-menu dropdown-menu-end mt-2 p-0 shadow-lg border-0 overflow-hidden" 
                         :class="{ 'show': isUserMenuActive }">
                        
                        <!-- Header Section with Gradient -->
                        <div class="user-header-modern text-center p-4 text-white">
                            <div class="position-relative d-inline-block mb-2">
                                <!-- CŨNG SỬ DỤNG HELPER TẠI ĐÂY -->
                                <img :src="getAvatarUrl(adminUser.avatar_url) || `https://ui-avatars.com/api/?name=${adminUser.fullname}&background=fff&color=009981`"
                                     class="rounded-circle border border-3 border-white shadow-sm" 
                                     style="width: 80px; height: 80px; object-fit: cover;" 
                                     alt="User Image">
                                <span class="position-absolute bottom-0 end-0 bg-success border border-2 border-white rounded-circle p-2"></span>
                            </div>
                            <h6 class="mb-0 fw-bold">{{ adminUser.fullname }}</h6>
                            <small class="opacity-75">{{ adminUser.email }}</small>
                        </div>
                        
                        <!-- Menu Items -->
                        <div class="p-2">
                            <!-- <a href="#" class="dropdown-item rounded-2 px-3 py-2 d-flex align-items-center gap-2">
                                <i class="bi bi-person text-muted"></i> Hồ sơ cá nhân
                            </a>
                            <a href="#" class="dropdown-item rounded-2 px-3 py-2 d-flex align-items-center gap-2">
                                <i class="bi bi-gear text-muted"></i> Cài đặt tài khoản
                            </a> -->
                            <div class="dropdown-divider my-2"></div>
                            <a href="#" @click.prevent="handleLogout" 
                               class="dropdown-item rounded-2 px-3 py-2 d-flex align-items-center gap-2 text-danger hover-bg-danger-light">
                                <i class="bi bi-box-arrow-right"></i> Đăng xuất
                            </a>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </nav>
</template>

<style scoped>
@import '@fortawesome/fontawesome-free/css/all.min.css';
/* Tinh chỉnh Navbar */
.app-header {
    min-height: 60px;
}

/* User Image trên Navbar */
.user-image {
    width: 36px;
    height: 36px;
    transition: transform 0.2s;
}

.nav-link:hover .user-image {
    transform: scale(1.05);
}

/* Icon mũi tên xoay */
.transition-icon {
    transition: transform 0.3s ease;
    font-size: 12px;
    opacity: 0.6;
}
.rotate-180 {
    transform: rotate(180deg);
}

/* Dropdown Menu Styles */
.dropdown-menu {
    width: 280px;
    border-radius: 12px;
    animation: slideInUp 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    transform-origin: top right;
    right: 1rem !important; 
}

/* Phần Header Gradient */
.user-header-modern {
    background: linear-gradient(135deg, #009981 0%, #00cba9 100%);
}

/* Hover effect cho item */
.dropdown-item {
    font-size: 14px;
    font-weight: 500;
    color: #4b5563;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background-color: #f3f4f6;
    color: #009981;
}

.dropdown-item:hover i {
    color: #009981 !important;
}

/* Logout đặc biệt */
.dropdown-item.text-danger:hover {
    background-color: #fee2e2; /* Đỏ rất nhạt */
    color: #dc2626;
}
.dropdown-item.text-danger:hover i {
    color: #dc2626 !important;
}

/* Animation */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>