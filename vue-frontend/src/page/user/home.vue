<template>
  <div id="app">
    <main class="container">

      <!-- ===== TOP SECTION: CATEGORIES + SLIDER + UTILITY ===== -->
      <section class="top-section-layout">
        <!-- Cột trái: DANH MỤC -->
        <nav class="categories-sidebar">
          <h3 class="sidebar-title">Danh mục</h3>
          <div
            class="category-item-sodo"
            v-for="category in categories"
            :key="category.id"
            :class="{ active: category.id === activeCategoryId }"
            @click="setActiveCategory(category.id)"
          >
            <i :class="getCategoryIcon(category.name)" class="icon"></i>
            <span>{{ category.name }}</span>
          </div>
        </nav>

        <!-- Cột giữa: SLIDER -->
        <section class="slider" @mouseenter="stopAutoSlide" @mouseleave="startAutoSlide">
          <div class="slider-wrapper" :style="{ transform: 'translateX(-' + currentSlide * 100 + '%)' }">
            <div
              class="slide"
              v-for="(slide, index) in slides"
              :key="slide.id"
              :style="{ backgroundImage: 'url(' + slide.imageUrl + ')' }"
            >
              <div class="slide-content">
                <h2>{{ slide.title }}</h2>
                <p>{{ slide.description }}</p>
              </div>
            </div>
          </div>

          <button class="slider-control prev" @click="prevSlide">
            <i class="fas fa-chevron-left"></i>
          </button>
          <button class="slider-control next" @click="nextSlide">
            <i class="fas fa-chevron-right"></i>
          </button>

          <div class="slider-nav">
            <span
              v-for="(slide, index) in slides"
              :key="slide.id"
              class="slider-nav-dot"
              :class="{ active: index === currentSlide }"
              @click="goToSlide(index)"
            ></span>
          </div>
        </section>

        <!-- Cột phải: TIỆN ÍCH -->
        <aside class="utility-sidebar">
          <div class="user-info-card" v-if="users.length">
            <p class="user-name">{{ users[0].username }}</p>
            <p class="user-tier">⭐ {{ users[0].role || 'Null' }}</p>
          </div>
        </aside>
      </section>

      <!-- BRAND BANNER -->
      <section class="brand-banner" style="margin-top: 15px;">
        <a href="#">
          <img
            src="#"
            alt="Brand Banner"
          >
        </a>
      </section>

      <!-- TRUST BLOCK -->
      <section class="trust-block">
        <div class="trust-item"><span>✔️ Bảo hành chính hãng</span></div>
        <div class="trust-item"><span>🚚 Giao hàng miễn phí</span></div>
        <div class="trust-item"><span>🔄 Đổi trả 30 ngày</span></div>
        <div class="trust-item"><span>🏪 Hơn 100+ cửa hàng</span></div>
      </section>

      <!-- ==========================
            SẢN PHẨM THEO NHÓM
      =========================== -->
      <section class="product-section">
  <!-- ===== HÀNG 1: ĐIỆN THOẠI NỔI BẬT ===== -->
  <div class="section-block">
    <div class="section-header">
      <h2 class="section-title">📱 Điện thoại nổi bật nhất</h2>
      <a href="#" class="see-more">Xem tất cả</a>
    </div>
    <div class="product-grid">
      <div
        class="product-card"
        v-for="product in topPhones"
        :key="product.id"
      >
        <img :src="product.image_url || 'https://placehold.co/200x200?text=No+Image'" :alt="product.name">
        <h3 class="product-name">{{ product.name }}</h3>
        <div class="product-price">
          <span class="new-price">{{ formatCurrency(getMinPrice(product.variants)) }}</span>
        </div>
        <div class="card-actions-small">
          <button class="btn-view" @click="openQuickView(product)">
            <i class="fas fa-eye"></i> Xem
          </button>
          <button class="btn-add-cart" @click="addToCart(product)">
            <i class="fas fa-plus"></i> Thêm giỏ
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== HÀNG 2: LAPTOP BÁN CHẠY ===== -->
  <div class="section-block">
    <div class="section-header">
      <h2 class="section-title">💻 Laptop bán chạy nhất</h2>
      <a href="#" class="see-more">Xem tất cả</a>
    </div>
    <div class="product-grid">
      <div
        class="product-card"
        v-for="product in topLaptops"
        :key="product.id"
      >
        <img :src="product.image_url || 'https://placehold.co/200x200?text=No+Image'" :alt="product.name">
        <h3 class="product-name">{{ product.name }}</h3>
        <div class="product-price">
          <span class="new-price">{{ formatCurrency(getMinPrice(product.variants)) }}</span>
        </div>
        <div class="card-actions-small">
          <button class="btn-view" @click="openQuickView(product)">
            <i class="fas fa-eye"></i> Xem
          </button>
          <button class="btn-add-cart" @click="addToCart(product)">
            <i class="fas fa-plus"></i> Thêm giỏ
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== HÀNG 3: TIN TỨC CÔNG NGHỆ ===== -->
  <div class="section-block">
    <div class="section-header">
      <h2 class="section-title">📰 Tin tức công nghệ</h2>
      <a href="#" class="see-more">Xem tất cả</a>
    </div>
    <div class="news-grid">
      <div class="news-card" v-for="news in newsList" :key="news.id">
        <img :src="news.image || 'https://placehold.co/300x150?text=No+Image'" :alt="news.title">
        <h3 class="news-title">{{ news.title }}</h3>
        <p class="news-excerpt">{{ news.excerpt }}</p>
        <a href="#" class="read-more">Đọc thêm</a>
      </div>
    </div>
  </div>
</section>

    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const API_URL = 'http://localhost:3000';

const categories = ref([]);
const slides = ref([]);
const products = ref([]);
const users = ref([]);
const newsList = ref([]);

const activeCategoryId = ref(null);
const currentSlide = ref(0);
const interval = ref(null);

// 🟢 Fetch data từ json-server
const fetchData = async () => {
  try {
    // Dùng Promise.allSettled để tránh lỗi dừng toàn bộ khi 1 API lỗi
    const [catRes, slideRes, prodRes, userRes, newsRes] = await Promise.allSettled([
      fetch(`${API_URL}/categories`),
      fetch(`${API_URL}/slides`),
      fetch(`${API_URL}/products`),
      fetch(`${API_URL}/account_admin`),
      fetch(`${API_URL}/news`) // 📰 Tin tức công nghệ
    ]);

    // Hàm phụ: kiểm tra response có hợp lệ không
    const parseJSON = async (res) => {
      if (res && res.status === "fulfilled" && res.value.ok) {
        try {
          return await res.value.json();
        } catch {
          return [];
        }
      }
      return [];
    };

    // Gán dữ liệu an toàn
    categories.value = await parseJSON(catRes);
    slides.value = await parseJSON(slideRes);
    products.value = await parseJSON(prodRes);
    users.value = await parseJSON(userRes);
    newsList.value = await parseJSON(newsRes);

  } catch (err) {
    console.error("Lỗi khi lấy dữ liệu:", err);
  }
};


// 🌀 Slider controls
const startAutoSlide = () => {
  if (!slides.value.length) return;
  interval.value = setInterval(() => {
    currentSlide.value = (currentSlide.value + 1) % slides.value.length;
  }, 4000);
};
const stopAutoSlide = () => clearInterval(interval.value);
const nextSlide = () => { currentSlide.value = (currentSlide.value + 1) % slides.value.length; };
const prevSlide = () => { currentSlide.value = (currentSlide.value - 1 + slides.value.length) % slides.value.length; };
const goToSlide = (index) => { currentSlide.value = index; };

// 📦 Category controls
const setActiveCategory = (id) => { activeCategoryId.value = String(id); };
const getCategoryIcon = (name) => {
  if(name.includes('Điện thoại')) return 'fas fa-mobile-alt';
  if(name.includes('Laptop')) return 'fas fa-laptop';
  if(name.includes('Watch')) return 'fas fa-watch';
  if(name.includes('Phụ kiện')) return 'fas fa-headphones';
  if(name.includes('Máy tính bảng')) return 'fas fa-tablet';
  return 'fas fa-box';
};

// 💰 Helpers
const getMinPrice = (variants) => {
  if(!variants || !variants.length) return 0;
  return Math.min(...variants.map(v => v.price));
};
const formatCurrency = (value) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

// 🧠 Dữ liệu đã lọc theo nhóm
const topPhones = computed(() =>
  products.value.filter(p => p.category?.name?.includes('Điện thoại')).slice(0, 8)
);
const topLaptops = computed(() =>
  products.value.filter(p => p.category?.name?.includes('Laptop')).slice(0, 8)
);

// 🔍 Actions
const openQuickView = (product) => { alert(`Xem chi tiết ${product.name}`); };
const addToCart = (product) => { alert(`Đã thêm ${product.name} vào giỏ`); };

onMounted(async () => {
  await fetchData();
  startAutoSlide();
});
onBeforeUnmount(stopAutoSlide);
</script>




<style scoped>
/* ------------------- Global & Variables ------------------- */
:root {
    --primary-color: #dc3545;
    /* Đỏ (CellphoneS) */
    --secondary-color: #f8f9fa;
    /* Xám nhạt */
    --text-color: #333;
    --border-radius: 8px;
    --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --transition-speed: 0.3s;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background-color: var(--secondary-color);
    color: var(--text-color);
    line-height: 1.6;
}

a {
    text-decoration: none;
    color: var(--text-color);
}

/* ------------------- Header ------------------- */
.header {
    background-color: var(--primary-color);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    color: white;
}

.header a,
.logo a {
    color: white;
}

.logo a {
    font-size: 24px;
    font-weight: bold;
}

.search-bar {
    flex-grow: 1;
    max-width: 500px;
    margin: 0 30px;
}

.search-bar input {
    width: 100%;
    padding: 10px 15px;
    border: none;
    border-radius: var(--border-radius);
    font-size: 16px;
    color: var(--text-color);
}

.user-actions a {
    margin-left: 20px;
    font-size: 16px;
    color: white;
    padding: 8px 10px;
    border-radius: var(--border-radius);
    transition: background-color var(--transition-speed);
}

.user-actions a:hover {
    background-color: #a72832;
}

/* ------------------- Layout ------------------- */
.container {
    max-width: 1280px;
    margin: 20px auto;
    padding: 0 20px;
}

.top-section-layout {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

/* ------------------- Sidebar & Slider Bases ------------------- */
.categories-sidebar,
.utility-sidebar {
    flex-shrink: 0;
    background-color: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    max-height: 400px;
    overflow-y: auto;
}

.categories-sidebar {
    width: 250px;
    padding: 10px 0;
}

.utility-sidebar {
    width: 280px;
    padding: 15px;
}

/* ------------------- Categories Sidebar ------------------- */
.category-item-sodo {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    cursor: pointer;
    transition: background-color var(--transition-speed), color var(--transition-speed);
    font-size: 14px;
    font-weight: 500;
}

.category-item-sodo:hover,
.category-item-sodo.active {
    background-color: #f0f0f0;
}

.category-item-sodo.active {
    color: var(--primary-color);
    font-weight: bold;
}

.category-item-sodo i {
    font-size: 18px;
    width: 30px;
    text-align: center;
    margin-right: 10px;
    color: #777;
    transition: color var(--transition-speed);
}

.category-item-sodo.active i {
    color: var(--primary-color);
}

/* ------------------- Slider ------------------- */
.slider {
    flex-grow: 1;
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    box-shadow: var(--box-shadow);
}

.slider-wrapper {
    display: flex;
    transition: transform var(--transition-speed) ease-in-out;
}

.slide {
    min-width: 100%;
    height: 400px;
    background-size: cover;
    background-position: center;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
    padding: 20px;
    position: relative;
}

.slide::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
}

.slide-content {
    position: relative;
    z-index: 2;
    text-align: center;
}

.slide-content h2 {
    font-size: 3em;
    margin-bottom: 10px;
}

.slide-content p {
    font-size: 1.5em;
}

/* Slider Controls */
.slider-control {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    padding: 15px 10px;
    cursor: pointer;
    z-index: 10;
    font-size: 20px;
    opacity: 0.7;
    transition: opacity var(--transition-speed), background-color var(--transition-speed);
}

.slider-control:hover {
    opacity: 1;
    background-color: rgba(0, 0, 0, 0.7);
}

.slider-control.prev {
    left: 0;
    border-top-right-radius: var(--border-radius);
    border-bottom-right-radius: var(--border-radius);
}

.slider-control.next {
    right: 0;
    border-top-left-radius: var(--border-radius);
    border-bottom-left-radius: var(--border-radius);
}

/* ------------------- Utility Sidebar ------------------- */
.user-info-card {
    padding-bottom: 10px;
    text-align: center;
}

.user-name {
    font-size: 1.1em;
    font-weight: bold;
    color: var(--primary-color);
}

.user-tier {
    font-size: 0.9em;
    color: #8b4513;
    margin-top: 5px;
    font-weight: bold;
}

.user-loyalty-points {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 10px 0;
    flex-direction: column;
    border: 1px solid #f0f0f0;
    padding: 5px;
    border-radius: 5px;
}

.loyalty-icon {
    font-size: 1.5em;
    margin-right: 5px;
    /* Giữ lại nếu muốn icon và text cùng hàng */
}

.loyalty-value {
    font-size: 1.2em;
    font-weight: bold;
    color: #333;
}

.loyalty-text,
.view-store-link {
    font-size: 0.8em;
    color: #6c757d;
}

.view-store-link {
    cursor: pointer;
    font-weight: bold;
    color: #007bff;
    display: block;
    margin-top: 5px;
    text-align: center;
}

.utility-menu {
    list-style: none;
    padding: 0;
    margin: 15px 0;
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.utility-menu li {
    padding: 8px 0;
    font-size: 1em;
    cursor: pointer;
    border-bottom: 1px dotted #eee;
    color: #555;
}

.utility-menu li i {
    margin-right: 8px;
    color: var(--primary-color);
}

.quick-links-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    text-align: center;
    padding: 10px 0;
}

.quick-link-item {
    background-color: #f8f9fa;
    padding: 8px 5px;
    border-radius: 5px;
    font-size: 0.8em;
    color: #555;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.quick-link-item i {
    font-size: 1.5em;
    display: block;
    margin-bottom: 3px;
    color: #007bff;
}

/* ------------------- Product Grid ------------------- */
.product-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.product-card {
    background-color: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    padding: 15px;
    text-align: center;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.product-image {
    height: 150px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f0f0f0;
    border-radius: var(--border-radius);
    font-size: 14px;
    color: #999;
}

.product-name {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 8px;
    height: 40px;
    overflow: hidden;
}

.product-price {
    font-size: 18px;
    color: var(--primary-color);
    font-weight: bold;
    margin-bottom: 5px;
}

.product-stock {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 15px;
}

.card-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.card-actions button {
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    transition: background-color var(--transition-speed), opacity var(--transition-speed);
    color: white;
    /* Gộp màu chữ chung */
}

.btn-view {
    background-color: #007bff;
}

.btn-add-cart {
    background-color: var(--primary-color);
}

/* ------------------- Modal Styles ------------------- */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 2000;
    opacity: 0;
    visibility: hidden;
    transition: opacity var(--transition-speed), visibility var(--transition-speed);
}

.modal-overlay.open {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background-color: white;
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    width: 90%;
    max-width: 700px;
    position: relative;
    transform: scale(0.9);
    transition: transform var(--transition-speed);
}

.modal-overlay.open .modal-content {
    transform: scale(1);
}

.modal-close-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #333;
}

.modal-body {
    display: flex;
    gap: 20px;
}

.modal-image {
    width: 40%;
    height: 200px;
    background-color: #f0f0f0;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.modal-details {
    width: 60%;
}

.modal-details h4 {
    font-size: 1.5em;
    margin-bottom: 10px;
    color: var(--text-color);
}

.modal-details .price {
    font-size: 2em;
    color: var(--primary-color);
    font-weight: bold;
    margin-bottom: 15px;
}

.modal-details .stock {
    font-size: 0.9em;
    color: #6c757d;
    margin-bottom: 15px;
}

.variant-selector {
    margin-bottom: 20px;
}

.variant-selector label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.variant-selector select {
    width: 100%;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.btn-buy-now {
    width: 100%;
    padding: 15px;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    margin-top: 15px;
    transition: background-color var(--transition-speed);
}

.btn-buy-now:hover {
    background-color: #218838;
}


/* ------------------- Cart Sidebar Styles ------------------- */
.cart-sidebar {
    position: fixed;
    top: 0;
    right: 0;
    width: 350px;
    height: 100%;
    background-color: white;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
    z-index: 3000;
    transform: translateX(100%);
    transition: transform var(--transition-speed) ease-in-out;
    display: flex;
    flex-direction: column;
}

.cart-sidebar.open {
    transform: translateX(0);
}

.cart-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-header h3 {
    margin: 0;
    font-size: 1.2em;
}

.cart-close-btn {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #333;
}

.cart-items {
    flex-grow: 1;
    overflow-y: auto;
    padding: 15px;
}

.cart-items p {
    text-align: center;
    color: #999;
    margin-top: 20px;
}

.cart-item {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px dotted #eee;
}

.cart-item-image {
    width: 50px;
    height: 50px;
    background-color: #f0f0f0;
    margin-right: 10px;
    flex-shrink: 0;
}

.cart-item-name {
    font-weight: bold;
    font-size: 0.9em;
}

.cart-item-price {
    color: var(--primary-color);
    font-size: 0.8em;
    margin-top: 5px;
}

.cart-footer {
    padding: 15px;
    border-top: 1px solid #eee;
    flex-shrink: 0;
}

.cart-total {
    display: flex;
    justify-content: space-between;
    font-size: 1.2em;
    font-weight: bold;
    margin-bottom: 10px;
}

.btn-checkout {
    width: 100%;
    padding: 15px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 1em;
    font-weight: bold;
    cursor: pointer;
    transition: background-color var(--transition-speed);
}

.btn-checkout:hover {
    background-color: #b82c39;
}


/* ------------------- Responsive Adjustments ------------------- */
@media (max-width: 992px) {
    .product-grid {
        grid-template-columns: repeat(3, 1fr);
    }

    .top-section-layout {
        flex-direction: column;
    }

    .categories-sidebar,
    .utility-sidebar {
        width: 100%;
        max-height: none;
    }

    .categories-sidebar {
        order: 1;
        white-space: nowrap;
        overflow-x: auto;
        padding: 10px 0;
        display: flex;
        gap: 10px;
    }

    .category-item-sodo {
        flex-direction: column;
        justify-content: center;
        text-align: center;
        min-width: 100px;
        padding: 10px 5px;
        flex-shrink: 0;
        border: 1px solid #eee;
        border-radius: var(--border-radius);
    }

    .category-item-sodo:hover,
    .category-item-sodo.active {
        background-color: #f0f0f0;
    }

    .category-item-sodo span {
        font-size: 12px;
    }

    .category-item-sodo i {
        margin-right: 0;
    }

    .slider {
        order: 2;
    }

    .utility-sidebar {
        order: 3;
    }
}

@media (max-width: 768px) {
    .product-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .header {
        flex-wrap: wrap;
    }

    .search-bar {
        order: 3;
        margin: 10px 0 0;
        max-width: 100%;
    }

    .slide {
        height: 250px;
    }

    .slider-control {
        padding: 10px 5px;
        font-size: 16px;
    }

    .modal-body {
        flex-direction: column;
    }

    .modal-image,
    .modal-details {
        width: 100%;
    }

    .cart-sidebar {
        width: 100%;
    }
}

.trust-block {
    display: flex;
    justify-content: space-around;
    align-items: center;
    background-color: #fff;
    padding: 15px 0;
    margin: 30px 0;
    border-radius: 10px;
    box-shadow: var(--box-shadow);
    text-align: center;
    flex-wrap: wrap;
}

.trust-item {
    flex: 1;
    min-width: 150px;
    font-size: 15px;
    color: #444;
    font-weight: 600;
    transition: transform var(--transition-speed);
}

.trust-item:hover {
    transform: translateY(-3px);
    color: var(--primary-color);
}

/* ------------------- PRODUCT SECTIONS ------------------- */
.product-section {
    margin: 50px 0;
}

.section-title {
    font-size: 1.8em;
    color: var(--primary-color);
    font-weight: bold;
    text-align: left;
    margin-bottom: 25px;
    border-left: 6px solid var(--primary-color);
    padding-left: 10px;
}

/* Card layout */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.product-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    text-align: center;
    padding: 15px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
    position: relative;
}

.product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
}

/* Image */
.product-card img {
    width: 100%;
    height: 180px;
    object-fit: contain;
    margin-bottom: 10px;
    transition: transform 0.3s ease;
}

.product-card:hover img {
    transform: scale(1.05);
}

/* Name + Price */
.product-name {
    font-size: 1em;
    font-weight: 600;
    color: #222;
    margin: 10px 0 5px;
    min-height: 40px;
}

.product-price {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.new-price {
    color: var(--primary-color);
    font-weight: bold;
    font-size: 1.1em;
}

.old-price {
    color: #999;
    text-decoration: line-through;
    font-size: 0.9em;
}

/* Promo */
.product-promo {
    background-color: #ffeaea;
    color: var(--primary-color);
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 0.9em;
    display: inline-block;
    margin-bottom: 10px;
}

/* Action buttons */
.card-actions-small {
    display: flex;
    justify-content: center;
    gap: 10px;
}

.card-actions-small button {
    border: none;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 0.9em;
    font-weight: 600;
    cursor: pointer;
    color: white;
    transition: all 0.3s ease;
}

.btn-view {
    background-color: #007bff;
}

.btn-add-cart {
    background-color: var(--primary-color);
}

.card-actions-small button:hover {
    opacity: 0.9;
    transform: scale(1.05);
}

/* ------------------- NEWS SECTION ------------------- */
.news-section {
    margin: 60px 0;
}

.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.news-card {
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}

.news-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
}

.news-card img {
    width: 100%;
    height: 150px;
    object-fit: cover;
}

.news-title {
    font-size: 1.1em;
    font-weight: 600;
    color: #222;
    padding: 10px 15px 0;
    min-height: 48px;
}

.news-excerpt {
    color: #666;
    font-size: 0.9em;
    padding: 0 15px 10px;
    line-height: 1.4em;
}

.read-more {
    display: block;
    color: var(--primary-color);
    font-weight: bold;
    font-size: 0.9em;
    padding: 0 15px 15px;
    text-align: right;
    transition: color var(--transition-speed);
}

.read-more:hover {
    color: #b82c39;
}

/* ------------------- Responsive ------------------- */
@media (max-width: 768px) {
    .trust-block {
        flex-direction: column;
        gap: 10px;
    }

    .section-title {
        text-align: center;
    }
}
.top-section-layout {
  display: grid;
  grid-template-columns: 200px 1fr 250px;
  gap: 20px;
  align-items: start;
}

.categories-sidebar {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  padding: 10px;
}

.category-item-sodo {
  display: flex;
  align-items: center;
  padding: 8px;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.2s;
}

.category-item-sodo:hover,
.category-item-sodo.active {
  background-color: #f0f8ff;
}

.slider {
  position: relative;
  overflow: hidden;
  border-radius: 12px;
}

.slider-wrapper {
  display: flex;
  transition: transform 0.5s ease-in-out;
}

.slide {
  min-width: 100%;
  background-size: cover;
  background-position: center;
  height: 250px;
  display: flex;
  align-items: flex-end;
  color: white;
  padding: 20px;
}

.utility-sidebar {
  background: #fff;
  border-radius: 12px;
  padding: 15px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

</style>