<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'; // 1. Import Vuex
import apiService from '../../apiService.js';

// --- CẤU HÌNH & KHỞI TẠO ---
const route = useRoute()
const router = useRouter()
const store = useStore() // 2. Khởi tạo store

// --- CẤU HÌNH XỬ LÝ ẢNH ---
const SERVER_URL = 'http://127.0.0.1:8000';   
const USE_STORAGE = false; 

const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/300x300?text=No+Img';
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  if (USE_STORAGE) {
    return `${SERVER_URL}/storage/${cleanPath}`; 
  } else {
    return `${SERVER_URL}/${cleanPath}`;         
  }
};

const clearAllFilters = () => {
  searchKeyword.value = "";
  searchTerm.value = "";
  priceMax.value = 100000000;
  router.push({ query: {} });
};

const searchKeyword = ref("")  
const searchTerm = ref(route.query.search || '')

// --- STATE ---
const allProducts = ref([])
const categories = ref([])
const news = ref([])

const priceMin = ref(0); 
const priceMax = ref(100000000); 

const hotSaleProducts = ref([])

// Countdown State
const saleEndTime = new Date();
saleEndTime.setDate(saleEndTime.getDate() + 1); 
const countdownInterval = ref(null);
const countdownDisplay = ref('00 : 00 : 00 : 00');

// --- HELPER MỚI: TÍNH GIÁ SẢN PHẨM ---
const getProductPrice = (product) => {
  // 1. Nếu có biến thể, lấy giá thấp nhất
  if (product.variants && product.variants.length > 0) {
    return Math.min(...product.variants.map(v => Number(v.price)));
  }
  // 2. Nếu không, lấy giá gốc
  return Number(product.price) || 0;
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)

// --- ACTION THÊM GIỎ HÀNG (VUEX) ---
const onAddToCart = (product) => {
  // Tìm variant có giá thấp nhất để thêm mặc định (nếu có)
  let variant = null;
  if (product.variants && product.variants.length > 0) {
     const minPrice = getProductPrice(product);
     variant = product.variants.find(v => Number(v.price) === minPrice) || product.variants[0];
  }

  // Dispatch Vuex Action
  store.dispatch('addToCart', { 
    product: product, 
    quantity: 1, 
    variant: variant 
  });
  
  alert(`Đã thêm ${product.name} vào giỏ hàng.`);
}

const updateCountdown = () => {
  const now = new Date();
  const distance = saleEndTime - now;

  if (distance < 0) {
    clearInterval(countdownInterval.value);
    countdownDisplay.value = 'Hết hạn Sale!';
    return;
  }

  const days = Math.floor(distance / (1000 * 60 * 60 * 24));
  const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((distance % (1000 * 60)) / 1000);

  const pad = (num) => String(num).padStart(2, '0');

  countdownDisplay.value = `${pad(days)} : ${pad(hours)} : ${pad(minutes)} : ${pad(seconds)}`;
};

const fetchData = async () => {
  try {
    const [prodRes, catRes, newsRes] = await Promise.all([
      apiService.get(`/products`),
      apiService.get(`/categories?status=active`),
      apiService.get(`/news?_limit=5`)
    ])
    
    const productsData = prodRes.data.data || prodRes.data || [];
    allProducts.value = productsData;
    
    categories.value = catRes.data.data || catRes.data || [];
    news.value = newsRes.data.data || newsRes.data || [];

    hotSaleProducts.value = productsData.slice(0, 5).map(p => {
      const price = getProductPrice(p);
      return {
        ...p,
        sale_price: price * 0.85,
        old_price: price,
        discount: 15
      }
    })

  } catch (err) {
    console.error('Lỗi tải dữ liệu cửa hàng:', err)
  }
}

const currentCategoryId = computed(() => route.query.categoryId ? String(route.query.categoryId) : null)
const currentCategoryName = computed(() => {
  if (!currentCategoryId.value) return 'Tất cả sản phẩm'
  const category = categories.value.find(c => String(c.id) === currentCategoryId.value)
  return category ? category.name : 'Danh mục không xác định'
})

const selectCategory = (id) => {
  const query = { ...route.query, categoryId: id || undefined };
  if (!id) delete query.categoryId;
  router.push({ query });
}

const applyFilters = () => {
  const query = { ...route.query };
  if (searchTerm.value) {
    query.search = searchTerm.value;
  } else {
    delete query.search;
  }
  delete query.price_min;
  if (priceMax.value > 0 && priceMax.value < 100000000) {
    query.price_max = priceMax.value;
  } else {
    delete query.price_max;
  }
  router.push({ query });
}

const goToProduct = (productId) => {
  if (!productId) return;
  router.push(`/products/${productId}`);
}

// --- BỘ LỌC SẢN PHẨM (ĐÃ SỬA) ---
const filteredProducts = computed(() => {
  if (!Array.isArray(allProducts.value)) return [];
  
  let products = [...allProducts.value]

  // 1. Lọc theo Danh mục (Hỗ trợ cả object category và category_id)
  if (currentCategoryId.value) {
    products = products.filter(p => {
        // Kiểm tra an toàn: p.category.id hoặc p.category_id
        const pCatId = p.category?.id || p.category_id;
        return String(pCatId) === currentCategoryId.value;
    })
  }

  // 2. Lọc theo Tìm kiếm
  if (searchTerm.value && searchTerm.value.trim()) {
    const term = searchTerm.value.toLowerCase()
    products = products.filter(p => p.name.toLowerCase().includes(term))
  }

  // 3. Lọc theo Khoảng giá
  products = products.filter(p => {
    const price = getProductPrice(p);
    if (priceMax.value && price > priceMax.value) return false
    return true
  })
  return products
})

// --- LIFECYCLE HOOKS & WATCHERS ---
onMounted(() => {
  fetchData();
  countdownInterval.value = setInterval(updateCountdown, 1000);
});

let debounceTimer = null;
const debouncedApplyFilters = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    applyFilters();
  }, 500); 
};

watch(searchKeyword, (newVal) => {
  searchTerm.value = newVal; 
  debouncedApplyFilters(); 
});

watch(route, (newRoute) => {
  searchTerm.value = newRoute.query.search || '';
  priceMax.value = Number(newRoute.query.price_max) || 100000000; 
  if (!searchKeyword.value) {
      searchKeyword.value = searchTerm.value;
  }
}, { deep: true, immediate: true }); 
</script>

<template>
  <div class="shop-wrapper container">

    <div class="shop-page">
      <div class="shop-layout">
        <aside class="sidebar">
          <div class="filter-section">
            <h3><i class="fas fa-search"></i> Tìm kiếm sản phẩm</h3>
            <input v-model="searchKeyword" type="text" placeholder="Nhập tên sản phẩm..." class="search-box" />
          </div>
          <h2 class="sidebar-title">Danh mục sản phẩm</h2>
          <ul class="category-list">
            <li :class="{ active: !currentCategoryId }" @click="selectCategory(null)">
              Tất cả sản phẩm
            </li>
            <li v-for="cat in categories" :key="cat.id" :class="{ active: currentCategoryId == String(cat.id) }"
              @click="selectCategory(cat.id)">
              {{ cat.name }}
            </li>
          </ul>

          <div class="filter-price">
            <h5><i class="fas fa-filter"></i> Lọc theo giá</h5>
            <div class="price-range">
              <label>Tối đa: {{ priceMax.toLocaleString() }}đ</label>
              <input type="range" min="100000" max="100000000" v-model.number="priceMax" step="100000"
                class="range-slider" @change="applyFilters" />
            </div>
          </div>

          <button @click="clearAllFilters" class="btn-reset-all">
            <i class="fas fa-undo"></i> Reset tất cả bộ lọc
          </button>

        </aside>

        <main class="main-content">
          <div class="shop-header">
            <h1 class="page-title">
              {{ currentCategoryName || 'Tất cả sản phẩm' }}
            </h1>
            <p v-if="currentCategoryId" class="category-desc">
              Hiển thị sản phẩm trong danh mục
              <b>{{ currentCategoryName }}</b>
            </p>
          </div>

          <div v-if="filteredProducts.length === 0" class="no-products">
            😔 Không tìm thấy sản phẩm phù hợp.
          </div>

          <section v-else class="product-listing">
            <div class="product-grid">

              <div class="product-card" v-for="product in filteredProducts" :key="product.id"
                @click="goToProduct(product.id)">
                <div class="product-image">
                  <img 
                    :src="getImageUrl(product.thumbnail_url || product.image_url)" 
                    :alt="product.name" 
                    @error="$event.target.src='https://placehold.co/300x300?text=Product'"
                  />
                </div>
                <div class="product-info">
                  <h3 class="product-name">{{ product.name }}</h3>
                  
                  <!-- FIX: Hiển thị giá đúng cho cả SP đơn giản và có biến thể -->
                  <p class="product-price">
                    {{ formatCurrency(getProductPrice(product)) }}
                  </p>
                  
                  <button class="btn-add-cart" @click.stop="onAddToCart(product)">
                    <i class="fas fa-cart-plus"></i> Thêm vào giỏ
                  </button>
                </div>
              </div>
            </div>
          </section>

        </main>
      </div>
      <div>
        <section class="hot-sale-section" v-if="hotSaleProducts.length > 0">
          <div class="hot-sale-header">
            <h2><i class="fas fa-fire"></i> HOT SALE <span>Cuối tuần</span></h2>
            <div class="countdown">
              Kết thúc sau:
              <b class="timer">{{ countdownDisplay }}</b>
            </div>
          </div>

          <div class="hot-sale-scroll">
            <div class="hot-sale-card" v-for="product in hotSaleProducts" :key="product.id">
              <div class="discount-badge">Giảm {{ product.discount || 10 }}%</div>
              <div class="hot-sale-image">
                <img 
                    :src="getImageUrl(product.thumbnail_url || product.image_url)" 
                    :alt="product.name" 
                    @error="$event.target.src='https://placehold.co/250x250?text=Sale+Item'"
                />
              </div>
              <h3 class="hot-sale-name">{{ product.name }}</h3>
              <p class="hot-sale-price">{{ formatCurrency(product.sale_price) }}</p>
              <p class="hot-sale-old-price">
                {{ formatCurrency(product.old_price) }}
              </p>
              <div class="hot-sale-actions">
                <button class="btn-love hot-sale-btn">
                  <i class="fas fa-heart"></i>
                </button>
                <button class="btn-cart hot-sale-btn" @click.stop="onAddToCart(product)"> <i class="fas fa-cart-plus"></i>
                </button>
              </div>
            </div>
          </div>
        </section>

        <section class="promo-section-wrapper">
          <div class="promo-grid">

            <div class="promo-column">
              <h3>ƯU ĐÃI SINH VIÊN</h3>
              <div class="banner-grid">
                <a href="#" class="banner-item">
                  <img src="https://intphcm.com/data/upload/banner-la-gi.jpg" alt="Banner 1">
                </a>
                <a href="#" class="banner-item">
                  <img src="https://truonggiang.vn/wp-content/uploads/2021/07/banner-laptop-sinh-vien-scaled.jpg"
                    alt="Banner 2">
                </a>
                <a href="#" class="banner-item">
                  <img src="https://img.pikbest.com/origin/09/05/73/13npIkbEsT8MI.jpg!w700wp" alt="Banner 3">
                </a>
                <a href="#" class="banner-item">
                  <img
                    src="https://marketplace.canva.com/EAGbDiUQ-wQ/1/0/1600w/canva-%C4%91%E1%BA%A7y-m%C3%A0u-s%E1%BA%AFc-r%E1%BB%B1c-r%E1%BB%A1-minh-h%E1%BB%8Da-khung-sale-khuy%E1%BA%BFn-m%C3%A3i-s%E1%BA%A3n-ph%E1%BA%A9m-banner-qnv0_ENRCWE.jpg"
                    alt="Banner 4">
                </a>
              </div>
            </div>

            <div class="promo-column">
              <h3>ƯU ĐÃI THANH TOÁN</h3>
              <div class="banner-grid">
                <a href="#" class="banner-item">
                  <img src="https://cdn.tgdd.vn/hoi-dap/1355217/banner-tgdd-800x300.jpg" alt="Banner 5">
                </a>
                <a href="#" class="banner-item">
                  <img src="https://img.pikbest.com/origin/09/02/27/61IpIkbEsTsYE.jpg!w700wp" alt="Banner 6">
                </a>
                <a href="#" class="banner-item">
                  <img
                    src="https://img.pikbest.com/templates/20240425/spirited-mothers-day-holiday-wishes-222024-png-images-png_10534920.jpg!w700wp"
                    alt="Banner 7">
                </a>
                <a href="#" class="banner-item">
                  <img
                    src="https://marketplace.canva.com/EAGsR-bwGFg/1/0/800w/canva-v%C3%A0ng-xanh-hi%E1%BB%87n-%C4%91%E1%BA%A1i-ng%C3%A0y-%C4%91%C3%B4i-8.8-sale-deal-%C6%B0u-%C4%91%E1%BA%A3i-s%E1%BA%A3n-ph%E1%BA%A9m-banner-ngang-TeXwbgwuYoc.jpg"
                    alt="Banner 8">
                </a>
              </div>
            </div>

          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
:root {
  --primary-color: rgb(0, 153, 129);
  --primary-hover-color: rgb(0, 137, 116);
  --shadow-color: rgba(0, 153, 129, 0.15);
  --hot-sale-color: #ff4d4d;
}


.shop-wrapper {
  width: 100%;
  min-height: 100vh;
  background: #f7f9f8;
  padding: 20px 0 60px 0;
}


.hot-sale-section {
  padding: 25px;
  background: linear-gradient(to right, #fff8f8, #f5f8f7);
  border: 1px solid #ffd6d6;
  border-radius: 12px;
  margin-bottom: 30px;
  box-shadow: 0 3px 10px rgba(255, 77, 77, 0.08);
}

.hot-sale-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.hot-sale-header h2 {
  color: var(--hot-sale-color);
  font-size: 1.6em;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 10px;
}

.hot-sale-header span {
  font-size: 1.0em;

  background: var(--hot-sale-color);
  color: white;
 
  padding: 3px 8px;
  border-radius: 5px;
}

.countdown {
  font-weight: 600;
  color: #333;
}

.countdown .timer {
  color: var(--hot-sale-color);
  background: white;
  padding: 6px 10px;
  border-radius: 6px;
  box-shadow: 0 1px 4px rgba(255, 77, 77, 0.15);
}

.hot-sale-scroll {
  display: flex;
  overflow-x: auto;
  gap: 16px;
  padding-bottom: 5px;
  scrollbar-width: none;
}

.hot-sale-scroll::-webkit-scrollbar {
  display: none;
}

.hot-sale-card {
  background: white;
  border-radius: 12px;
  border: 1px solid #eee;
  padding: 12px;
  text-align: center;
  min-width: 210px;
  box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
  transition: all 0.25s ease;
  flex-shrink: 0;
}

.hot-sale-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 18px rgba(255, 77, 77, 0.2);
}

.hot-sale-image {
  height: 150px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.hot-sale-image img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.hot-sale-name {
  font-size: 1em;
  font-weight: 600;
  margin: 8px 0 5px;
  height: 40px;
  overflow: hidden;
}

.hot-sale-price {
  color: var(--hot-sale-color);
  font-weight: bold;
}

.hot-sale-old-price {
  text-decoration: line-through;
  color: #999;
  font-size: 0.9em;
}

.hot-sale-actions {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-top: 8px;
}

.hot-sale-btn {
  background: #f3f6f5;
  border: none;
  color: var(--primary-color);
  width: 38px;
  height: 38px;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.2s ease;
}

.hot-sale-btn:hover {
  transform: scale(1.1);
}

.btn-cart:hover,
.btn-love:hover {
  background-color: var(--primary-color);
  color: #fff;
}

.hot-tag:hover {
  background-color: var(--primary-color);
  color: #222;
}



.shop-layout {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 25px;
  align-items: flex-start;
}


.sidebar {
  position: sticky;
  top: 20px;

  background: white;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #e4e4e4;
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
}

.sidebar-title {
  font-size: 1.2em;
  font-weight: 700;
  color: var(--primary-color);
  margin-bottom: 14px;
  border-left: 4px solid var(--primary-color);
  padding-left: 10px;
}

.category-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.category-list li {
  padding: 10px 12px;
  margin-bottom: 6px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 500;
  color: #333;
  transition: all 0.2s ease;
}

.category-list li:hover {
  background: rgba(0, 153, 129, 0.08);
  color: var(--primary-color);
}

.category-list li.active {
  background: var(--primary-color);
  color: white;
  box-shadow: 0 4px 10px rgba(0, 153, 129, 0.35);
}

.filter-section {
  margin-bottom: 20px;

}

.filter-section h3 {
  font-size: 1.2em;
  
  color: #222;
  font-weight: 600;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.search-box,
.filter-price input {
  width: 100%;
  padding: 10px;
  border: 1px solid #d0d8d7;
  border-radius: 8px;
  margin-bottom: 8px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.search-box:focus,
.filter-price input:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(0, 153, 129, 0.2);
  outline: none;
}


.btn-reset-all {
  width: 100%;
  padding: 11px;
  border-radius: 8px;
  border: 1px solid #d0d8d7;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  background: #ecf1ef;
  color: #333;
  margin-top: 20px;
  /* Thêm space */
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 0.95em;
}

.btn-reset-all:hover {
  background: #dce7e4;
  border-color: #bccbc7;
}


.main-content {
  background: white;
  border-radius: 12px;
  padding: 25px;
  border: 1px solid #e3e3e3;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
}

.page-title {
  font-size: 1.6em;
  font-weight: 700;
  color: #111;
  margin-bottom: 6px;
}

.category-desc {
  color: #555;
  font-size: 0.95em;
  margin-bottom: 20px;
}


.product-listing {
  margin-top: 10px;
}

.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 18px;
}

.product-card {
  background: #fdfdfd;
  border: 1px solid #eee;
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.25s ease;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  cursor: pointer;

  display: flex;
  flex-direction: column;
}

.product-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 8px 15px var(--shadow-color);
}

.product-image {
  height: 180px;
  background: #f5f8f7;
  display: flex;
  align-items: center;
  justify-content: center;
}

.product-image img {
  max-height: 100%;
  object-fit: contain;
}

.product-info {
  padding: 12px 15px 18px;
  
  display: flex;
  flex-direction: column;
  flex-grow: 1;
 
}

.product-name {
  font-size: 1em;
  font-weight: 600;
  color: #333;
  margin-bottom: 6px;

  min-height: 2.8em;

}

.product-price {
  font-size: 1.1em;
  font-weight: 700;
  color: var(--primary-color);
  margin-bottom: 10px;
}

.btn-add-cart {
  width: 100%;
  background: var(--primary-color);
  color: white;
  border: none;
  padding: 10px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;

  margin-top: auto;

}

.btn-add-cart:hover {
  background-color:#c82333;
}



.no-products {
  text-align: center;
  padding: 40px 0;
  color: #777;
  font-size: 1.1em;
}

@media (max-width: 992px) {
  .shop-layout {
    grid-template-columns: 1fr;
  }

  .sidebar {
    position: relative;
    top: 0;
    margin-bottom: 20px;
  }
}

.price-range {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.range-slider {
  width: 100%;
  accent-color: var(--primary-color);
}


.btn-filter {
  color: rgb(255, 255, 255);
  border: none;
  padding: 8px;
  border-radius: 6px;
  cursor: pointer;
}

.btn-filter:hover {
  background: #013d2a;
}



.hot-search-section {
  margin-top: 25px;
}

.hot-search-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.hot-tag {
  background: #ecf1ef;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.9em;
  cursor: pointer;
  transition: all 0.2s ease;
}

.hot-tag:hover {
  background: var(--primary-color);
  color: rgb(255, 255, 255);
}

.promo-section-wrapper {
  background: white;
  border-radius: 12px;
  padding: 25px;
  margin-bottom: 30px;
  border: 1px solid #e3e3e3;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
}



.promo-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 25px;
}

.promo-column h3 {
  font-size: 1.2em;
  /* THAY ĐỔI: Đồng bộ font size */
  font-weight: 700;
  margin-bottom: 15px;
  color: #333;
  text-transform: uppercase;
}

.banner-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.banner-item {
  display: block;
  border-radius: 8px;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border: 1px solid #f0f0f0;
  aspect-ratio: 2.2 / 1;
}

.banner-item:hover {
  transform: scale(1.03);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.banner-item img {
  width: 100%;
  height: 100%;
  /* SỬA LỖI TỪ LẦN TRƯỚC: Thêm height 100% */
  display: block;
  object-fit: cover;
}

/* Responsive cho màn hình nhỏ */
@media (max-width: 992px) {
  .promo-grid {
    grid-template-columns: 1fr;
    /* 1 cột trên mobile */
  }
}

@media (max-width: 576px) {
  .banner-grid {
    grid-template-columns: 1fr;
    /* 1 cột banner trên mobile */
  }

  .promo-section-wrapper {
    padding: 15px;
  }

  .promo-grid {
    gap: 20px;
  }
}
</style>