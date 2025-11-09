<template>
  <header class="site-header">
    <div class="top-bar">
      <div class="container">
        <div class="top-bar-links">
          <ul>
            <li><router-link :to="{name: 'admin-login'}">Kênh Người Bán</router-link></li>
            <li><a href="#">Tải ứng dụng</a></li>
            <li><a href="#">Kết nối</a></li>
            <li><router-link :to="{name: 'contact'}">Liên hệ</router-link></li>
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

        <router-link :to="{name: 'home'}" class="logo">
          <img src="../img/LogoThinkHub.png" alt="ThinkHub Logo" @error="$event.target.style.display = 'none'">
        </router-link>

        <div class="side-menu-container" ref="menuContainer">
          <button @click="toggleMenu" class="category-button">
            <i class="fa-solid fa-bars"></i>
            <span>Danh mục</span>
          </button>

          <div class="dropdown-menu" :class="{ active: isMenuActive }">
            <ul class="menu-list">
              <li>
                <router-link to="/category/phone"><i class="fa-solid fa-mobile-screen"></i> Điện thoại & Phụ
                  kiện</router-link>
              </li>
              <li><router-link to="/category/laptop"><i class="fa-solid fa-laptop"></i> Máy tính & Laptop</router-link>
              </li>
              <li><router-link to="/category/watch"><i class="fa-solid fa-clock"></i> Đồng hồ thông minh</router-link>
              </li>
              <li><router-link to="/category/audio"><i class="fa-solid fa-headphones"></i> Thiết bị âm
                  thanh</router-link></li>
              <li><router-link to="/category/gaming"><i class="fa-solid fa-gamepad"></i> Gaming Gear</router-link></li>

            </ul>
          </div>
        </div>

        <form class="search-bar" @submit.prevent="handleSearch">
          <input type="text" placeholder="Tìm kiếm sản phẩm trên ThinkHub..." v-model="searchQuery">
          <button type="submit" class="search-btn">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </form>

        <div class="header-actions">
          <router-link :to="{name: ''}" class="action-item">
            <i class="fa-solid fa-cart-shopping"></i>
            <span>Giỏ hàng</span>
          </router-link>
          <router-link :to="{name: 'login'}" class="action-item login-btn">
            <i class="fa-regular fa-user"></i>
            <span>Đăng nhập</span>
          </router-link>
        </div>

      </div>
    </div>
  </header>
</template>
<style>
:root {
  --primary-color: #009981;
  --primary-light: #DBF9EB;
  /* Đảm bảo dòng này có ở đây */
  --primary-dark: #00483D;
  --text-white: #ffffff;
  --text-dark: #333333;
  --bg-gray: #f8f9fa;
}
</style>
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const isMenuActive = ref(false);
const menuContainer = ref(null);
const searchQuery = ref('');

// Bật tắt menu
const toggleMenu = () => {
  isMenuActive.value = !isMenuActive.value;
};

// Đóng menu khi click ra ngoài
const handleClickOutside = (event) => {
  if (menuContainer.value && !menuContainer.value.contains(event.target)) {
    isMenuActive.value = false;
  }
};

// Xử lý tìm kiếm
const handleSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ name: 'search', query: { q: searchQuery.value } });
    // Hoặc: window.location.href = `/search?q=${searchQuery.value}`;
  }
};

// Gắn sự kiện click global khi component được mount
onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

// Gỡ sự kiện khi component bị hủy
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
/* Import font awesome nếu chưa có trong index.html chính */
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

:root {
  --primary-color: #009981;
  --primary-light: #DBF9EB;
  --primary-dark: #00483D;
  --text-white: #ffffff;
  --text-dark: #333333;
  --bg-gray: #f8f9fa;
  /* Màu nền nhẹ cho các nút trên nền trắng */
}

.site-header {
  font-family: Arial, sans-serif;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  /* Thêm bóng nhẹ cho header trắng */
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

/* Thanh trên cùng - Giữ màu chính */
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

/* Header chính - Đổi sang nền trắng */
.main-header {
  background-color: var(--text-white);
  padding: 15px 0;
  color: var(--text-dark);
  /* Chữ đổi sang màu tối */
  border-bottom: 1px solid #eee;
}

/* Logo - Đổi sang màu chính để nổi trên nền trắng */
.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--primary-color);
  font-weight: bold;
  font-size: 24px;
}

.logo img {
  height: 40px;
}

/* Nút danh mục - Cần nền tối hơn một chút để thấy trên nền trắng */
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
  background-color: #e9ecef;
}

/* Menu dropdown */
.side-menu-container {
  position: relative;
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 10px);
  /* Đẩy xuống một chút cho đẹp */
  left: 0;
  width: 250px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  display: none;
  z-index: 999;
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
}

/* Hiệu ứng hover menu dùng màu phụ */
.menu-list li a:hover {
  background-color: var(--primary-light);
  color: var(--primary-dark);
  padding-left: 18px;
  font-weight: 500;
}

.menu-list li a i {
  width: 25px;
  text-align: center;
  color: #999;
}

.menu-list li a:hover i {
  color: var(--primary-color);
}

/* Thanh tìm kiếm */
.search-bar {
  flex-grow: 1;
  position: relative;
}

.search-bar input {
  width: 100%;
  padding: 10px 15px;
  border: 2px solid var(--primary-color);
  /* Viền màu chính */
  border-radius: 8px;
  font-size: 14px;
  box-sizing: border-box;
  background-color: var(--bg-gray);
  outline: none;
}

.search-bar input:focus {
  background-color: var(--text-white);
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
  transition: background-color 0.2s;
}

.search-btn:hover {
  background-color: var(--primary-dark);
}

/* Nút hành động */
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
  /* Nền xám nhẹ */
  color: var(--text-dark);
  font-size: 14px;
  white-space: nowrap;
  transition: all 0.2s;
}

.action-item:hover {
  background-color: var(--primary-light);
  color: var(--primary-dark);
}

.action-item i {
  font-size: 20px;
  color: var(--primary-color);
  /* Icon màu chính cho nổi bật */
}

/* Nút đăng nhập nổi bật */
.login-btn {
  background-color: var(--primary-color);
  color: var(--text-white) !important;
  font-weight: bold;
}

.login-btn i {
  color: var(--text-white) !important;
}

.login-btn:hover {
  background-color: var(--primary-dark);
  opacity: 1;
}
</style>