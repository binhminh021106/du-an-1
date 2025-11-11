<template>
  <header class="site-header">
    <div class="top-bar">
      <div class="container">
        <div class="top-bar-links">
          <ul>
            <li><router-link :to="{name: 'admin-login'}">Kênh Người Bán</router-link></li>
            <li><router-link :to="{name: 'wishlist'}">Yêu thích</router-link></li>
            <li><a href="#">Kết nối</a></li>
            <li><router-link :to="{name: 'policy'}">Chính sách</router-link></li>
            <li><router-link :to="{name: 'contact'}">Liên hệ</router-link></li>
            <li><router-link :to="{name: 'FAQ'}">FAQ</router-link></li>
            <li><router-link :to="{name: 'blog'}">Blog/Tin tức</router-link></li>
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
            <ul class="menu-list" v-if="categories.length">
              <!-- BẮT ĐẦU VÒNG LẶP DỮ LIỆU ĐỘNG -->
              <li v-for="category in categories" :key="category.id">
                <!-- 
                  - Dùng v-html để render icon (do admin nhập)
                  - Link tới /category/[name]
                -->
                <router-link :to="'/category/' + category.name">
                  <span v-html="category.icon" class="icon-placeholder"></span>
                  {{ category.name }}
                </router-link>
              </li>
              <!-- KẾT THÚC VÒNG LẶP -->
            </ul>
            <div v-else class="p-3 text-center text-muted small">Đang tải danh mục...</div>
          </div>
        </div>

        <form class="search-bar" @submit.prevent="handleSearch">
          <input type="text" placeholder="Tìm kiếm sản phẩm trên ThinkHub..." v-model="searchQuery">
          <button type="submit" class="search-btn">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </form>

        <div class="header-actions">
          <router-link :to="{name: 'cart'}" class="action-item side-menu-container">
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

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios'; // Import Axios

const API_URL = 'http://localhost:3000'; // Đảm bảo URL này đúng với JSON Server của bạn

const router = useRouter();
const isMenuActive = ref(false);
const menuContainer = ref(null);
const searchQuery = ref('');

// Dữ liệu danh mục từ API
const categories = ref([]);

// Fetch data
const fetchCategories = async () => {
    try {
        // Lấy danh mục, chỉ lấy các danh mục "active" và sắp xếp theo "order"
        const response = await axios.get(`${API_URL}/categories?status=active&_sort=order&_order=asc`);
        categories.value = response.data;
    } catch (error) {
        console.error("Lỗi khi tải danh mục:", error);
    }
};

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
  }
};

// Gắn sự kiện click global và Fetch data khi component được mount
onMounted(() => {
  document.addEventListener('click', handleClickOutside);
  fetchCategories();
});

// Gỡ sự kiện khi component bị hủy
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>

:root {
  --primary-color: #009981;
  --primary-light: #DBF9EB;
  --primary-dark: #00483D;
  --text-white: #ffffff;
  --text-dark: #333333;
  --bg-gray: #f8f9fa;
}

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

.main-header {
  background-color: var(--text-white);
  padding: 15px 0;
  color: var(--text-dark);
  border-bottom: 1px solid #eee;
}

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

.menu-list li a:hover {
  background-color: var(--primary-light);
  color: var(--primary-dark);
  padding-left: 18px;
  font-weight: 500;
}

/* Đảm bảo icon có khoảng cách và màu sắc */
.icon-placeholder {
    width: 25px;
    text-align: center;
    color: #999;
    font-size: 16px; /* Kích thước icon */
    display: inline-block;
}

.menu-list li a:hover .icon-placeholder {
    color: var(--primary-color);
}


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
  color: white !important;
}
</style>