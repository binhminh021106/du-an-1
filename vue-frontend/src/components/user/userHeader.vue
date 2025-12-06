<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useStore } from 'vuex';
import apiService from '../../apiService'; // Đảm bảo đường dẫn này đúng
import Swal from 'sweetalert2';

const router = useRouter();
const store = useStore();

// [ĐỒNG BỘ] Định nghĩa URL gốc của Server giống trang Profile
const BACKEND_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

// --- STATE QUẢN LÝ MENU ---
const isMenuActive = ref(false);
const menuContainer = ref(null);
const categories = ref([]);
const isLoadingCategories = ref(false);

const isUserMenuActive = ref(false);
const userMenuContainer = ref(null);
const user = ref(null);
const searchQuery = ref('');

// --- COMPUTED: SỐ LƯỢNG GIỎ HÀNG ---
const cartItemCount = computed(() => store.getters.cartCount || 0);

// --- COMPUTED: LẤY CHỮ CÁI ĐẦU CỦA TÊN (Hiển thị khi không có ảnh) ---
const userInitial = computed(() => {
  if (!user.value || !user.value.fullName) return 'U';
  return user.value.fullName.charAt(0).toUpperCase();
});

// [FIX - ĐỒNG BỘ VỚI PROFILE] HÀM XỬ LÝ URL ẢNH
const getFullImageUrl = (url) => {
  if (!url) return null; // Trả về null để hiển thị Initials (chữ cái đầu)

  // Nếu là ảnh online (google, facebook) hoặc đã full URL thì giữ nguyên
  if (url.startsWith("http") || url.startsWith("https")) return url;

  // Xử lý path tương đối: nối thêm domain backend vào
  const cleanPath = url.startsWith('/') ? url.substring(1) : url;
  return `${BACKEND_URL}/${cleanPath}`;
};

// --- FETCH DATA ---
const fetchCategories = async () => {
  isLoadingCategories.value = true;
  try {
    const response = await apiService.get(`/categories`);
    categories.value = response.data;
  } catch (error) {
    console.error("Lỗi tải danh mục:", error);
  } finally {
    isLoadingCategories.value = false;
  }
};

// --- ACTION HANDLERS ---
const toggleMenu = () => {
  isMenuActive.value = !isMenuActive.value;
  isUserMenuActive.value = false;
};

const toggleUserMenu = () => {
  isUserMenuActive.value = !isUserMenuActive.value;
  isMenuActive.value = false;
};

const handleClickOutside = (event) => {
  if (menuContainer.value && !menuContainer.value.contains(event.target)) {
    isMenuActive.value = false;
  }
  if (userMenuContainer.value && !userMenuContainer.value.contains(event.target)) {
    isUserMenuActive.value = false;
  }
};

const handleSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ name: 'search', query: { q: searchQuery.value } });
  }
};

// [UPDATED] Logout với giao diện đẹp hơn
const handleLogout = () => {
  Swal.fire({
    title: 'Đăng xuất?',
    text: 'Bạn có chắc muốn đăng xuất khỏi tài khoản?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#009981',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Đăng xuất',
    cancelButtonText: 'Ở lại',
    // [NEW] Custom classes for elegant style
    customClass: {
        popup: 'elegant-popup',
        confirmButton: 'elegant-confirm-btn',
        cancelButton: 'elegant-cancel-btn',
        title: 'elegant-title'
    }
  }).then((result) => {
    if (result.isConfirmed) {
      localStorage.removeItem('userData');
      localStorage.removeItem('auth_token');
      store.commit('SET_LOGIN_STATUS', false);
      store.commit('CLEAR_CART');
      user.value = null;
      router.push({ name: 'login' });
    }
  });
};

const handleLoginSuccess = (event) => {
  user.value = event.detail.user;
  store.dispatch('initializeCart').catch(err => console.warn("Init cart error:", err));
};

// [MỚI] Hàm xử lý khi thông tin user thay đổi từ Profile
const handleUserUpdate = (event) => {
  // Cập nhật lại biến user reactive để giao diện tự thay đổi
  if (event.detail) {
    user.value = event.detail;
  } else {
    // Nếu không có dữ liệu chi tiết, thử load lại từ localStorage
    const userData = localStorage.getItem('userData');
    if (userData) {
      user.value = JSON.parse(userData);
    }
  }
};

// [NEW] Load Lordicon Script
const loadLordicon = () => {
    if (!document.querySelector('script[src="https://cdn.lordicon.com/lordicon.js"]')) {
        const script = document.createElement('script')
        script.src = 'https://cdn.lordicon.com/lordicon.js'
        script.async = true
        document.head.appendChild(script)
    }
}

// --- LIFECYCLE HOOKS ---
onMounted(() => {
  loadLordicon(); // Load script icon
  try {
    const userData = localStorage.getItem('userData');
    if (userData) {
      user.value = JSON.parse(userData);
    }
  } catch (e) {
    console.error("Lỗi parse userData:", e);
    localStorage.removeItem('userData');
  }

  document.addEventListener('click', handleClickOutside);
  window.addEventListener('login-success', handleLoginSuccess);

  // [MỚI] Đăng ký sự kiện lắng nghe cập nhật user
  window.addEventListener('user-info-updated', handleUserUpdate);

  fetchCategories();

  if (store._actions && store._actions.initializeCart) {
    store.dispatch('initializeCart').catch(err => {
      console.warn("Lỗi khi khởi tạo giỏ hàng:", err);
    });
  } else {
    const token = localStorage.getItem('auth_token');
    store.commit('SET_LOGIN_STATUS', !!token);
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('login-success', handleLoginSuccess);

  // [MỚI] Hủy đăng ký sự kiện khi component bị hủy
  window.removeEventListener('user-info-updated', handleUserUpdate);
});
</script>

<template>
  <header class="site-header">
    <!-- Top Bar -->
    <div class="top-bar">
      <div class="container">
        <div class="top-bar-links">
          <ul>
            <li><router-link :to="{ name: 'admin-login' }">Kênh Người Bán</router-link></li>
            <li><router-link :to="{ name: 'about' }">Kết nối</router-link></li>
            <li><router-link :to="{ name: 'policy' }">Chính sách</router-link></li>
            <li><router-link :to="{ name: 'contact' }">Liên hệ</router-link></li>
            <li><router-link :to="{ name: 'FAQ' }">FAQ</router-link></li>
            <li><router-link :to="{ name: 'blog' }">Blog/Tin tức</router-link></li>
          </ul>
        </div>
        <div class="top-bar-info">
          <ul>
            <li><a href="#"><i class="fa-regular fa-bell"></i> Thông báo</a></li>
            <li><a href="#"><i class="fa-regular fa-circle-question"></i> Hỗ trợ</a></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Main Header -->
    <div class="main-header">
      <div class="container relative-container">

        <!-- Logo -->
        <router-link :to="{ name: 'home' }" class="logo">
          <img src="../img/LogoThinkHub.png" alt="ThinkHub" @error="$event.target.style.display = 'none'">
        </router-link>

        <!-- Danh mục Dropdown -->
        <div class="side-menu-container" ref="menuContainer">
          <button @click="toggleMenu" class="category-button">
            <i class="fa-solid fa-bars"></i>
            <span>Danh mục</span>
          </button>

          <div class="dropdown-menu" :class="{ active: isMenuActive }">
            <div v-if="isLoadingCategories" class="p-3 text-center text-muted small">
              <i class="fa-solid fa-spinner fa-spin"></i> Đang tải...
            </div>

            <ul class="menu-list" v-else-if="categories.length">
              <li v-for="category in categories" :key="category.id">
                <router-link :to="{ name: 'Shop', query: { categoryId: category.id } }">
                  <span v-html="category.icon" class="icon-placeholder"></span>
                  {{ category.name }}
                </router-link>
              </li>
            </ul>
            <div v-else class="p-3 text-center text-muted small">Không có danh mục.</div>
          </div>
        </div>

        <!-- Search Bar -->
        <form class="search-bar" @submit.prevent="handleSearch">
          <input type="text" placeholder="Tìm kiếm sản phẩm..." v-model="searchQuery">
          <button type="submit" class="search-btn">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </form>

        <!-- Header Actions -->
        <div class="header-actions">

          <!-- Giỏ hàng -->
          <router-link :to="{ name: 'cart' }" class="action-item side-menu-container cart-action">
            <div class="cart-icon-wrapper">
              <i class="fa-solid fa-cart-shopping"></i>
              <span v-if="cartItemCount > 0" class="cart-badge">{{ cartItemCount }}</span>
            </div>
            <span>Giỏ hàng</span>
          </router-link>

          <!-- Nút Đăng nhập (Hiện khi chưa login) -->
          <router-link v-if="!user" :to="{ name: 'login' }" class="action-item login-btn">
            <i class="fa-regular fa-user"></i>
            <span>Đăng nhập</span>
          </router-link>

          <!-- User Menu (Hiện khi đã login) -->
          <div v-else class="side-menu-container user-menu" ref="userMenuContainer">
            <button @click="toggleUserMenu" class="action-item user-menu-trigger">
              <!-- [UPDATED] Logic hiển thị Avatar đồng bộ với Profile -->

              <!-- Trường hợp 1: Có ảnh avatar -> Dùng getFullImageUrl -->
              <img v-if="user.avatar_url" :src="getFullImageUrl(user.avatar_url)" class="user-avatar-mini" alt="Avatar"
                @error="user.avatar_url = null">

              <!-- Trường hợp 2: Không có ảnh hoặc ảnh lỗi -> Hiển thị Initials -->
              <div v-else class="user-avatar-placeholder">
                {{ userInitial }}
              </div>

              <span class="user-name-truncate">{{ user.fullName || user.email }}</span>
            </button>

            <!-- DROPDOWN MENU USER - LORDICON UPDATED -->
            <div class="dropdown-menu user-dropdown" :class="{ active: isUserMenuActive }">
              <div class="user-dropdown-header">
                <strong>{{ user.fullName || 'Người dùng' }}</strong>
                <small>{{ user.email }}</small>
              </div>
              
              <ul class="menu-list">
                <!-- 1. TÀI KHOẢN -->
                <li>
                    <router-link :to="{ name: 'profile' }" class="lordicon-link profile-target">
                        <div class="icon-wrap">
                            <lord-icon
                                src="https://cdn.lordicon.com/bhfjfgqz.json"
                                trigger="hover"
                                target=".profile-target"
                                colors="primary:#4b5563"
                                style="width:20px;height:20px">
                            </lord-icon>
                        </div>
                        Tài khoản
                    </router-link>
                </li>

                <!-- 2. ĐƠN MUA -->
                <li>
                    <router-link :to="{ name: 'OrderList' }" class="lordicon-link order-target">
                        <div class="icon-wrap">
                            <lord-icon
                                src="https://cdn.lordicon.com/pbrgppbb.json"
                                trigger="hover"
                                target=".order-target"
                                colors="primary:#4b5563"
                                style="width:20px;height:20px">
                            </lord-icon>
                        </div>
                        Đơn mua
                    </router-link>
                </li>

                <!-- 3. YÊU THÍCH -->
                <li>
                    <router-link :to="{ name: 'wishlist' }" class="lordicon-link wishlist-target">
                        <div class="icon-wrap">
                            <lord-icon
                                src="https://cdn.lordicon.com/rjzlnunf.json"
                                trigger="hover"
                                target=".wishlist-target"
                                colors="primary:#4b5563,secondary:#e91e63"
                                style="width:20px;height:20px">
                            </lord-icon>
                        </div>
                        Yêu thích
                    </router-link>
                </li>

                <li class="divider">
                  <hr>
                </li>

                <!-- 4. ĐĂNG XUẤT -->
                <li>
                  <a href="#" class="logout-link lordicon-link logout-target" @click.prevent="handleLogout">
                    <div class="icon-wrap">
                        <lord-icon
                            src="https://cdn.lordicon.com/moscwhoj.json"
                            trigger="hover"
                            target=".logout-target"
                            colors="primary:#d9534f,secondary:#d9534f"
                            style="width:20px;height:20px">
                        </lord-icon>
                    </div>
                    Đăng xuất
                  </a>
                </li>
              </ul>
            </div>
          </div>

        </div>
      </div>
    </div>
  </header>
</template>

<!-- [NEW] Global Style for SweetAlert (Elegant Theme) -->
<style>
/* Style cho Popup Confirm Dialog */
.elegant-popup {
    border-radius: 16px !important;
    font-family: Arial, sans-serif !important;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15) !important;
    padding: 1.5rem !important;
}

.elegant-title {
    font-size: 1.5rem !important;
    color: #333 !important;
    font-weight: 700 !important;
}

.elegant-confirm-btn {
    border-radius: 8px !important;
    padding: 12px 24px !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 12px rgba(0, 153, 129, 0.3) !important;
    background-color: #009981 !important; /* Primary color */
    border: none !important;
    transition: transform 0.2s !important;
}

.elegant-confirm-btn:hover {
    transform: translateY(-2px);
    background-color: #00806b !important;
}

.elegant-cancel-btn {
    border-radius: 8px !important;
    padding: 12px 24px !important;
    font-weight: 600 !important;
    background-color: #f3f4f6 !important;
    color: #4b5563 !important;
    border: 1px solid #e5e7eb !important;
    transition: background-color 0.2s !important;
}

.elegant-cancel-btn:hover {
    background-color: #e5e7eb !important;
    color: #374151 !important;
}
</style>

<style scoped>
/* CSS Variables giả lập */
.site-header {
  --primary-color: #009981;
  --primary-light: #DBF9EB;
  --primary-dark: #00483D;
  --text-white: #ffffff;
  --text-dark: #333333;
  --bg-gray: #f8f9fa;
  --danger-color: #d9534f;

  font-family: Arial, sans-serif;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  background-color: #fff;
  z-index: 1000;
  position: relative;
}

a {
  text-decoration: none;
  color: inherit;
}

ul {
  list-style: none;
  margin: 0;
  padding: 0;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 15px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.relative-container {
  position: relative;
  gap: 20px;
}

/* TOP BAR */
.top-bar {
  background-color: var(--primary-color);
  color: var(--text-white);
  font-size: 13px;
  padding: 8px 0;
}

.top-bar-links ul,
.top-bar-info ul {
  display: flex;
  gap: 20px;
}

.top-bar a:hover {
  text-decoration: underline;
  opacity: 0.9;
}

/* MAIN HEADER */
.main-header {
  padding: 15px 0;
  border-bottom: 1px solid #eee;
  background-color: #fff;
}

/* LOGO */
.logo img {
  height: 40px;
  display: block;
}

/* CATEGORY BUTTON */
.category-button {
  background-color: var(--bg-gray);
  border: 1px solid #eee;
  color: var(--text-dark);
  padding: 10px 15px;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  white-space: nowrap;
  transition: all 0.2s;
}

.category-button:hover {
  background-color: var(--primary-light);
  color: var(--primary-dark);
}

.category-button:hover i {
  color: var(--primary-dark);
}

/* DROPDOWN MENU */
.dropdown-menu {
  position: absolute;
  top: calc(100% + 10px);
  left: 0;
  width: 250px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  display: none;
  z-index: 1001;
  border: 1px solid #eee;
  padding: 5px 0;
}

.dropdown-menu.active {
  display: block;
}

.menu-list li a {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 15px;
  color: var(--text-dark);
  font-size: 14px;
  transition: all 0.2s;
  border-left: 3px solid transparent;
}

.menu-list li a:hover {
  background-color: var(--primary-light);
  color: var(--primary-dark);
  padding-left: 18px;
  border-left-color: var(--primary-color);
  font-weight: 500;
}

.icon-placeholder {
  width: 25px;
  text-align: center;
  color: #999;
}

.menu-list li a:hover .icon-placeholder {
  color: var(--primary-color);
}

/* SEARCH BAR */
.search-bar {
  flex-grow: 1;
  position: relative;
}

.search-bar input {
  width: 100%;
  padding: 10px 15px;
  border: 2px solid var(--primary-color);
  border-radius: 8px;
  font-size: 14px;
  outline: none;
  background-color: var(--bg-gray);
}

.search-bar input:focus {
  background-color: #fff;
}

.search-btn {
  position: absolute;
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: var(--primary-color);
  color: white;
  padding: 6px 15px;
  border-radius: 6px;
  cursor: pointer;
  height: 80%;
}

.search-btn:hover {
  background-color: var(--primary-dark);
}

/* HEADER ACTIONS */
.header-actions {
  display: flex;
  align-items: center;
  gap: 15px;
}

.action-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 8px;
  background-color: var(--bg-gray);
  color: var(--text-dark);
  font-size: 14px;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.2s;
}

.action-item:hover {
  background-color: var(--primary-light);
  color: var(--primary-dark);
}

.action-item i {
  font-size: 20px;
  color: var(--primary-color);
}

.action-item:hover i {
  color: var(--primary-dark);
}

/* Cart Specifics */
.cart-action {
  position: relative;
}

.cart-action:hover .cart-icon-wrapper i {
  animation: bounce 0.5s;
}

@keyframes bounce {

  0%,
  100% {
    transform: translateY(0);
  }

  50% {
    transform: translateY(-3px);
  }
}

.cart-icon-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  margin-right: 5px;
}

.cart-badge {
  position: absolute;
  top: -8px;
  right: -8px;
  background-color: var(--danger-color);
  color: white;
  font-size: 10px;
  font-weight: bold;
  min-width: 16px;
  height: 16px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #fff;
  padding: 0 2px;
  z-index: 10;
}

/* Login Button */
.login-btn {
  background-color: var(--primary-color);
  color: white !important;
  font-weight: bold;
}

.login-btn i {
  color: white !important;
}

.login-btn:hover {
  background-color: var(--primary-dark);
  opacity: 1;
}

/* User Menu */
.user-menu-trigger {
  border: none;
  font-family: inherit;
}

.user-avatar-mini {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
  border: 1px solid #eee;
}

/* [NEW STYLE] Avatar Placeholder (Initials) */
.user-avatar-placeholder {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background-color: var(--primary-light);
  color: var(--primary-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 14px;
  border: 1px solid var(--primary-color);
}

.user-name-truncate {
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-dropdown {
  left: auto;
  right: 0;
  width: 260px;
}

.user-dropdown-header {
  padding: 12px 15px;
  border-bottom: 1px solid #eee;
  background-color: #fcfcfc;
  display: flex;
  flex-direction: column;
}

.user-dropdown-header strong {
  font-size: 15px;
}

.user-dropdown-header small {
  font-size: 13px;
  color: #666;
}

/* [NEW] Style for Lordicon links in dropdown */
.icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
    width: 24px;
}

.logout-link {
  color: var(--danger-color) !important;
}

/* Fix icon color on hover for logout */
.logout-link:hover {
  background-color: #fdf2f2 !important;
  color: #b92c28 !important;
}

.divider hr {
  margin: 4px 0;
  border: none;
  border-top: 1px solid #eee;
}
</style>