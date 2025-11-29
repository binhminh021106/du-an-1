<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import apiService from '../../apiService'
import Swal from 'sweetalert2';

const router = useRouter();

// --- LOGIC MENU DANH MỤC ---
const isMenuActive = ref(false);
const menuContainer = ref(null);
const categories = ref([]);
const isLoadingCategories = ref(false); // [NEW] Trạng thái loading

// --- LOGIC MENU USER ---
const isUserMenuActive = ref(false);
const userMenuContainer = ref(null);
const user = ref(null);

// --- LOGIC CHUNG & GIỎ HÀNG ---
const searchQuery = ref('');
const cartItemCount = ref(0); // [NEW] Biến đếm số item trong giỏ

// Fetch data danh mục (Đã nâng cấp loading)
const fetchCategories = async () => {
  isLoadingCategories.value = true;
  try {
    const response = await apiService.get(`/categories`);
    categories.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải danh mục:", error);
  } finally {
    isLoadingCategories.value = false;
  }
};

// [NEW] Hàm tính số lượng item trong giỏ hàng
const updateCartCount = () => {
  try {
    // Giả sử bạn lưu giỏ hàng trong localStorage với key 'cart'
    // Nếu bạn dùng Pinia/Vuex, hãy thay thế logic này bằng store
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    // Đếm số lượng phần tử (unique items) thay vì cộng dồn quantity
    cartItemCount.value = Array.isArray(cart) ? cart.length : 0;
  } catch (e) {
    cartItemCount.value = 0;
  }
};

// --- HÀM XỬ LÝ MENU ---
const toggleMenu = () => {
  isMenuActive.value = !isMenuActive.value;
  isUserMenuActive.value = false;
};

const toggleUserMenu = () => {
  isUserMenuActive.value = !isUserMenuActive.value;
  isMenuActive.value = false;
};

// Đóng menu khi click ra ngoài
const handleClickOutside = (event) => {
  if (menuContainer.value && !menuContainer.value.contains(event.target)) {
    isMenuActive.value = false;
  }
  if (userMenuContainer.value && !userMenuContainer.value.contains(event.target)) {
    isUserMenuActive.value = false;
  }
};

// Xử lý tìm kiếm
const handleSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ name: 'search', query: { q: searchQuery.value } });
  }
};

const handleLogout = () => {
  Swal.fire({
    title: 'Bạn có chắc muốn đăng xuất?',
    text: "Bạn sẽ cần đăng nhập lại vào lần sau.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#009981',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Đồng ý, Đăng xuất',
    cancelButtonText: 'Hủy'
  }).then((result) => {
    if (result.isConfirmed) {
      localStorage.removeItem('userData');
      user.value = null;
      window.location.reload();
    }
  });
};

const handleLoginSuccess = (event) => {
  console.log('Đã nhận được sự kiện login-success!', event.detail.user);
  user.value = event.detail.user;
};

// [NEW] Lắng nghe sự kiện thêm vào giỏ hàng (nếu có)
const handleCartUpdate = () => {
  updateCartCount();
};

onMounted(() => {
  user.value = JSON.parse(localStorage.getItem('userData') || null);
  document.addEventListener('click', handleClickOutside);
  window.addEventListener('login-success', handleLoginSuccess);
  
  // [NEW] Lắng nghe sự kiện storage để cập nhật giỏ hàng khi có thay đổi tab khác
  window.addEventListener('storage', handleCartUpdate);
  // [NEW] Lắng nghe sự kiện custom (nếu bạn dispatch sự kiện này khi add to cart)
  window.addEventListener('cart-updated', handleCartUpdate);
  
  fetchCategories();
  updateCartCount(); // Gọi lần đầu
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('login-success', handleLoginSuccess);
  window.removeEventListener('storage', handleCartUpdate);
  window.removeEventListener('cart-updated', handleCartUpdate);
});
</script>

<template>
  <header class="site-header">
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

    <div class="main-header">
      <div class="container relative-container">

        <router-link :to="{ name: 'home' }" class="logo">
          <img src="../img/LogoThinkHub.png" alt="ThinkHub Logo" @error="$event.target.style.display = 'none'">
        </router-link>

        <div class="side-menu-container" ref="menuContainer">
          <button @click="toggleMenu" class="category-button">
            <i class="fa-solid fa-bars"></i>
            <span>Danh mục</span>
          </button>

          <div class="dropdown-menu" :class="{ active: isMenuActive }">
            <!-- [NEW] Trạng thái Loading -->
            <div v-if="isLoadingCategories" class="p-3 text-center text-muted small" style="padding: 15px; color: #666;">
              <i class="fa-solid fa-spinner fa-spin"></i> Đang tải...
            </div>

            <ul class="menu-list" v-else-if="categories.length">
              <li v-for="category in categories" :key="category.id">
                <!-- [NEW] Dùng Object syntax cho router-link an toàn hơn -->
                <router-link :to="{ name: 'Shop', query: { categoryId: category.id } }">
                  <!-- [NEW] Thêm logic kiểm tra icon an toàn -->
                  <span v-html="category.icon" class="icon-placeholder"></span>
                  {{ category.name }}
                </router-link>
              </li>
            </ul>
            <div v-else class="p-3 text-center text-muted small" style="padding: 15px; color: #666;">
                Không có danh mục nào.
            </div>
          </div>
        </div>

        <form class="search-bar" @submit.prevent="handleSearch">
          <input type="text" placeholder="Tìm kiếm sản phẩm trên ThinkHub..." v-model="searchQuery">
          <button type="submit" class="search-btn">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </form>

        <div class="header-actions">
          <!-- [NEW] Nâng cấp Giỏ hàng: Thêm class cart-action để style riêng -->
          <router-link :to="{ name: 'cart' }" class="action-item side-menu-container cart-action">
            <div class="cart-icon-wrapper">
                <i class="fa-solid fa-cart-shopping"></i>
                <!-- [NEW] Badge hiển thị số lượng item -->
                <span v-if="cartItemCount > 0" class="cart-badge">{{ cartItemCount }}</span>
            </div>
            <span>Giỏ hàng</span>
          </router-link>

          <router-link v-if="!user" :to="{ name: 'login' }" class="action-item login-btn">
            <i class="fa-regular fa-user"></i>
            <span>Đăng nhập</span>
          </router-link>

          <div v-else class="side-menu-container user-menu" ref="userMenuContainer">
            <button @click="toggleUserMenu" class="action-item user-menu-trigger">
              <i class="fa-solid fa-circle-user"></i>
              <span>{{ user.fullName || user.email }}</span>
            </button>

            <div class="dropdown-menu user-dropdown" :class="{ active: isUserMenuActive }">
              <div class="user-dropdown-header">
                <strong>{{ user.name }}</strong>
                <small>{{ user.email }}</small>
              </div>
              <ul class="menu-list">
                <li><router-link :to="{ name: 'profile' }"><i class="fa-solid fa-address-card"></i> Tài khoản của
                    tôi</router-link></li>
                <li><router-link :to="{ name: 'OrderList' }"><i class="fa-solid fa-box-archive"></i> Đơn hàng</router-link>
                </li>
                <li><router-link :to="{ name: 'wishlist' }"><i class="fa-solid fa-heart"></i> Yêu thích</router-link>
                </li>
                <li class="divider">
                  <hr>
                </li>
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
/* Xóa :root vì không hoạt động tốt trong scoped styles ở một số môi trường.
   Thay thế bằng mã màu trực tiếp:
   --primary-color: #009981;
   --primary-light: #DBF9EB;
   --primary-dark: #00483D;
   --text-white: #ffffff;
   --text-dark: #333333;
   --bg-gray: #f8f9fa;
   --danger-color: #d9534f;
   --danger-light: #fdf2f2;
   --danger-dark: #b92c28;
*/

.site-header {
  font-family: Arial, sans-serif;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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

.top-bar {
  background-color: #009981; /* --primary-color */
  color: #ffffff; /* --text-white */
  font-size: 13px;
  padding: 8px 0;
}

.top-bar-links ul,
.top-bar-info ul {
  display: flex;
  gap: 20px;
  color: #ffffff;
}

.top-bar a:hover {
  text-decoration: underline;
  opacity: 0.9;
}

.main-header {
  background-color: #ffffff; /* --text-white */
  padding: 15px 0;
  color: #333333; /* --text-dark */
  border-bottom: 1px solid #eee;
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #009981; /* --primary-color */
  font-weight: bold;
  font-size: 24px;
}

.logo img {
  height: 40px;
}

.category-button {
  background-color: #f8f9fa; /* --bg-gray */
  border: 1px solid #eee;
  color: #333333; /* --text-dark */
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

/* [EDIT] Cập nhật màu hover của nút Danh mục cho giống nút User/Account */
.category-button:hover {
  background-color: #DBF9EB; /* --primary-light */
  color: #00483D; /* --primary-dark */
}
/* [EDIT] Đổi màu icon bên trong nút Danh mục khi hover */
.category-button:hover i {
  color: #00483D; /* --primary-dark */
}

.side-menu-container {
  position: relative;
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 10px);
  left: 0;
  width: 250px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  display: none;
  z-index: 999;
  border: 1px solid #eee;
  padding: 5px 0;
  overflow: hidden;
}

.dropdown-menu.active {
  display: block;
}

.menu-list li a {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 15px;
  color: #333333; /* --text-dark */
  font-size: 14px;
  transition: all 0.2s;
  /* Thêm viền trái trong suốt để chuẩn bị cho hiệu ứng hover */
  border-left: 3px solid transparent; 
}

/* [NEW] Hiệu ứng hover cho danh mục: Nền xanh nhạt + Viền xanh đậm */
.menu-list li a:hover {
  background-color: #DBF9EB; /* --primary-light */
  color: #00483D; /* --primary-dark */
  padding-left: 18px;
  font-weight: 500;
  border-left: 3px solid #009981; /* --primary-color */
}

/* Đảm bảo icon có khoảng cách và màu sắc */
.icon-placeholder {
  width: 25px;
  text-align: center;
  color: #999;
  font-size: 16px;
  display: inline-block;
}

.menu-list li a:hover .icon-placeholder {
  color: #009981; /* --primary-color */
}

.search-bar {
  flex-grow: 1;
  position: relative;
}

.search-bar input {
  width: 100%;
  padding: 10px 15px;
  border: 2px solid #009981; /* --primary-color */
  border-radius: 8px;
  font-size: 14px;
  box-sizing: border-box;
  background-color: #f8f9fa; /* --bg-gray */
  outline: none;
}

.search-bar input:focus {
  background-color: #ffffff; /* --text-white */
}

.search-btn {
  position: absolute;
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: #009981; /* --primary-color */
  color: white;
  padding: 6px 15px;
  border-radius: 6px;
  cursor: pointer;
  height: 80%;
  transition: background-color 0.2s;
}

.search-btn:hover {
  background-color: #00483D; /* --primary-dark */
}

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
  background-color: #f8f9fa; /* --bg-gray */
  color: #333333; /* --text-dark */
  font-size: 14px;
  white-space: nowrap;
  transition: all 0.2s;
}

/* [EDIT] Cập nhật hover cho các nút action (như Giỏ hàng) */
.action-item:hover {
  background-color: #DBF9EB; /* --primary-light */
  color: #00483D; /* --primary-dark */
}
/* [EDIT] Đảm bảo icon cũng đổi màu khi hover vào nút cha */
.action-item:hover i {
  color: #00483D; /* --primary-dark */
}

.action-item i {
  font-size: 20px;
  color: #009981; /* --primary-color */
  transition: color 0.2s; /* Thêm transition cho mượt */
}

/* [NEW] STYLE CHO GIỎ HÀNG */
.cart-action {
  position: relative;
  /* Đảm bảo icon giỏ hàng khi hover nổi bật hơn */
}

.cart-icon-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

/* [NEW] Hiệu ứng hover cho giỏ hàng: Nảy nhẹ icon */
.cart-action:hover .cart-icon-wrapper i {
    animation: bounce 0.5s;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}

/* [NEW] Badge hiển thị số lượng */
.cart-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: #d9534f; /* --danger-color */
    color: white;
    font-size: 10px;
    font-weight: bold;
    min-width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff; /* Viền trắng tách biệt với icon */
    padding: 0 2px;
}

.login-btn {
  background-color: #009981; /* --primary-color */
  color: white !important;
  font-weight: bold;
}

.login-btn i {
  color: white !important;
}

.login-btn:hover {
  background-color: #00483D; /* --primary-dark */
  opacity: 1;
  color: white !important;
}

/* ===================================== */
/* CSS USER MENU (GIỮ NGUYÊN CẤU TRÚC) */
/* ===================================== */

.user-menu .user-menu-trigger {
  background-color: #DBF9EB; /* --primary-light */
  color: #00483D; /* --primary-dark */
  font-weight: 500;
  cursor: pointer;
  border: none;
  font-family: Arial, sans-serif;
}

.user-menu .user-menu-trigger i {
  color: #009981; /* --primary-color */
}

.user-menu .user-menu-trigger:hover {
  background-color: #cceee7; /* Màu hover của user hơi đậm hơn xíu */
}

.user-dropdown {
  left: auto;
  right: 0;
  width: 260px;
  padding: 0;
}

.user-dropdown-header {
  padding: 12px 15px;
  border-bottom: 1px solid #eee;
  display: flex;
  flex-direction: column;
  line-height: 1.4;
  background-color: #fcfcfc;
}

.user-dropdown-header strong {
  font-size: 15px;
  color: #333333; /* --text-dark */
}

.user-dropdown-header small {
  font-size: 13px;
  color: #666;
  word-break: break-all;
}

.user-dropdown .menu-list {
  padding: 5px 0;
}

.user-dropdown .menu-list li a i {
  width: 20px;
  text-align: center;
  color: #888;
  font-size: 16px;
  margin-right: 5px;
  flex-shrink: 0;
}

.user-dropdown .menu-list li a:hover i {
  color: #009981; /* --primary-color */
}

.divider hr {
  border: none;
  border-top: 1px solid #f0f0f0;
  margin: 4px 0;
}

.user-dropdown .menu-list li a.logout-link {
  color: #d9534f; /* --danger-color */
}

.user-dropdown .menu-list li a.logout-link i {
  color: #d9534f; /* --danger-color */
}

.user-dropdown .menu-list li a.logout-link:hover {
  background-color: #fdf2f2; /* --danger-light */
  color: #b92c28; /* --danger-dark */
  font-weight: 500;
}

.user-dropdown .menu-list li a.logout-link:hover i {
  color: #b92c28; /* --danger-dark */
}
</style>