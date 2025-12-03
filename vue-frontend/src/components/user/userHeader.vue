<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useStore } from 'vuex';
import apiService from '../../apiService'; // Đảm bảo đường dẫn này đúng
import Swal from 'sweetalert2';

const router = useRouter();
const store = useStore();

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
// Lấy trực tiếp từ getter của Vuex cho chuẩn
const cartItemCount = computed(() => store.getters.cartCount || 0);

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

const handleLogout = () => {
  Swal.fire({
    title: 'Đăng xuất?',
    text: 'Bạn có chắc muốn đăng xuất khỏi tài khoản?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#009981',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Đăng xuất',
    cancelButtonText: 'Ở lại'
  }).then((result) => {
    if (result.isConfirmed) {
      // Xóa dữ liệu đăng nhập
      localStorage.removeItem('userData');
      localStorage.removeItem('auth_token'); // Quan trọng: Xóa token
      
      // Update Store
      store.commit('SET_LOGIN_STATUS', false);
      store.commit('CLEAR_CART'); // Tùy chọn: Xóa giỏ hàng khi logout nếu muốn
      
      user.value = null;
      
      // Chuyển hướng hoặc reload
      router.push({ name: 'login' });
      // window.location.reload(); // Không cần reload trang nếu SPA làm tốt
    }
  });
};

const handleLoginSuccess = (event) => {
  user.value = event.detail.user;
  // Khi login thành công, gọi lại init cart để merge giỏ hàng (nếu có logic đó)
  // Dùng .then().catch() để tránh lỗi unhandled promise rejection
  store.dispatch('initializeCart').catch(err => console.warn("Init cart error:", err));
};

// --- LIFECYCLE HOOKS ---
onMounted(() => {
  // 1. Load User từ LocalStorage
  try {
      const userData = localStorage.getItem('userData');
      if (userData) {
          user.value = JSON.parse(userData);
      }
  } catch (e) {
      console.error("Lỗi parse userData:", e);
      localStorage.removeItem('userData');
  }

  // 2. Lắng nghe sự kiện
  document.addEventListener('click', handleClickOutside);
  window.addEventListener('login-success', handleLoginSuccess);
  
  // 3. Tải danh mục
  fetchCategories();
  
  // 4. Khởi tạo giỏ hàng (Check login, load DB/Local)
  // Đảm bảo file store/index.js của bạn ĐÃ CÓ action 'initializeCart'
  // FIX: Thêm kiểm tra action có tồn tại không trước khi dispatch để tránh lỗi màn hình đỏ
  if (store._actions && store._actions.initializeCart) {
      store.dispatch('initializeCart').catch(err => {
          console.warn("Lỗi khi khởi tạo giỏ hàng:", err);
      });
  } else {
      console.warn("⚠️ Action 'initializeCart' chưa được định nghĩa trong Vuex Store. Vui lòng kiểm tra file store/index.js");
      // Fallback: Tự cập nhật trạng thái login nếu action thiếu
      const token = localStorage.getItem('auth_token');
      store.commit('SET_LOGIN_STATUS', !!token);
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('login-success', handleLoginSuccess);
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
                <!-- Avatar hoặc Icon mặc định -->
                <img v-if="user.avatar_url" :src="user.avatar_url" class="user-avatar-mini" alt="Avatar">
                <i v-else class="fa-solid fa-circle-user"></i>
                
                <span class="user-name-truncate">{{ user.fullName || user.email }}</span>
            </button>

            <div class="dropdown-menu user-dropdown" :class="{ active: isUserMenuActive }">
              <div class="user-dropdown-header">
                <strong>{{ user.fullName || 'Người dùng' }}</strong>
                <small>{{ user.email }}</small>
              </div>
              <ul class="menu-list">
                <li><router-link :to="{ name: 'profile' }"><i class="fa-solid fa-address-card"></i> Tài khoản</router-link></li>
                <li><router-link :to="{ name: 'OrderList' }"><i class="fa-solid fa-box-archive"></i> Đơn mua</router-link></li>
                <li><router-link :to="{ name: 'wishlist' }"><i class="fa-solid fa-heart"></i> Yêu thích</router-link></li>
                <li class="divider"><hr></li>
                <li>
                  <a href="#" class="logout-link" @click.prevent="handleLogout">
                    <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
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

<style scoped>
/* CSS Variables giả lập (Vì scoped không hỗ trợ :root tốt) */
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
  position: relative; /* Đảm bảo z-index hoạt động */
}

a { text-decoration: none; color: inherit; }
ul { list-style: none; margin: 0; padding: 0; }

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 15px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.relative-container { position: relative; gap: 20px; }

/* TOP BAR */
.top-bar { background-color: var(--primary-color); color: var(--text-white); font-size: 13px; padding: 8px 0; }
.top-bar-links ul, .top-bar-info ul { display: flex; gap: 20px; }
.top-bar a:hover { text-decoration: underline; opacity: 0.9; }

/* MAIN HEADER */
.main-header { padding: 15px 0; border-bottom: 1px solid #eee; background-color: #fff; }

/* LOGO */
.logo img { height: 40px; display: block; }

/* CATEGORY BUTTON */
.category-button {
  background-color: var(--bg-gray); border: 1px solid #eee; color: var(--text-dark);
  padding: 10px 15px; border-radius: 8px; cursor: pointer;
  display: flex; align-items: center; gap: 8px; font-size: 14px; white-space: nowrap; transition: all 0.2s;
}
.category-button:hover { background-color: var(--primary-light); color: var(--primary-dark); }
.category-button:hover i { color: var(--primary-dark); }

/* DROPDOWN MENU */
.dropdown-menu {
  position: absolute; top: calc(100% + 10px); left: 0; width: 250px;
  background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  display: none; z-index: 1001; border: 1px solid #eee; padding: 5px 0;
}
.dropdown-menu.active { display: block; }

.menu-list li a {
  display: flex; align-items: center; gap: 12px; padding: 12px 15px;
  color: var(--text-dark); font-size: 14px; transition: all 0.2s;
  border-left: 3px solid transparent;
}
.menu-list li a:hover {
  background-color: var(--primary-light); color: var(--primary-dark);
  padding-left: 18px; border-left-color: var(--primary-color); font-weight: 500;
}
.icon-placeholder { width: 25px; text-align: center; color: #999; }
.menu-list li a:hover .icon-placeholder { color: var(--primary-color); }

/* SEARCH BAR */
.search-bar { flex-grow: 1; position: relative; }
.search-bar input {
  width: 100%; padding: 10px 15px; border: 2px solid var(--primary-color);
  border-radius: 8px; font-size: 14px; outline: none; background-color: var(--bg-gray);
}
.search-bar input:focus { background-color: #fff; }
.search-btn {
  position: absolute; right: 5px; top: 50%; transform: translateY(-50%);
  border: none; background: var(--primary-color); color: white;
  padding: 6px 15px; border-radius: 6px; cursor: pointer; height: 80%;
}
.search-btn:hover { background-color: var(--primary-dark); }

/* HEADER ACTIONS */
.header-actions { display: flex; align-items: center; gap: 15px; }
.action-item {
  display: flex; align-items: center; gap: 8px; padding: 8px 12px;
  border-radius: 8px; background-color: var(--bg-gray); color: var(--text-dark);
  font-size: 14px; white-space: nowrap; cursor: pointer; transition: all 0.2s;
}
.action-item:hover { background-color: var(--primary-light); color: var(--primary-dark); }
.action-item i { font-size: 20px; color: var(--primary-color); }
.action-item:hover i { color: var(--primary-dark); }

/* Cart Specifics */
.cart-action { position: relative; } /* Thêm position relative cho nút cha */
.cart-action:hover .cart-icon-wrapper i { animation: bounce 0.5s; }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }

.cart-icon-wrapper {
    position: relative; /* Wrapper để định vị badge */
    display: flex;
    align-items: center;
    margin-right: 5px; /* Tạo khoảng cách với chữ "Giỏ hàng" nếu cần */
}

/* CHỈNH SỬA VỊ TRÍ BADGE */
.cart-badge {
  position: absolute;
  top: -8px; /* Đẩy lên trên một chút so với icon */
  right: -8px; /* Đẩy sang phải một chút */
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
  z-index: 10; /* Đảm bảo nổi lên trên */
}

/* Login Button */
.login-btn { background-color: var(--primary-color); color: white !important; font-weight: bold; }
.login-btn i { color: white !important; }
.login-btn:hover { background-color: var(--primary-dark); opacity: 1; }

/* User Menu */
.user-menu-trigger { border: none; font-family: inherit; }
.user-avatar-mini { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
.user-name-truncate { max-width: 120px; overflow: hidden; text-overflow: ellipsis; }
.user-dropdown { left: auto; right: 0; width: 260px; }
.user-dropdown-header { padding: 12px 15px; border-bottom: 1px solid #eee; background-color: #fcfcfc; display: flex; flex-direction: column; }
.user-dropdown-header strong { font-size: 15px; }
.user-dropdown-header small { font-size: 13px; color: #666; }

.logout-link { color: var(--danger-color) !important; }
.logout-link i { color: var(--danger-color) !important; }
.logout-link:hover { background-color: #fdf2f2 !important; color: #b92c28 !important; }
.logout-link:hover i { color: #b92c28 !important; }

.divider hr { margin: 4px 0; border: none; border-top: 1px solid #eee; }
</style>