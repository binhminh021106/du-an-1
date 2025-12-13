<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useStore } from 'vuex';
import apiService from '../../apiService';
import Swal from 'sweetalert2';
import Chatbot from '../../page/user/Chatbot.vue';

const router = useRouter();
const store = useStore();

// [FIX] Đồng bộ URL server với Product Detail
const BACKEND_URL = 'http://127.0.0.1:8000';

// --- STATE QUẢN LÝ MENU ---
const isMenuActive = ref(false);
const menuContainer = ref(null);
const categories = ref([]);
const products = ref([]); // Chứa danh sách sản phẩm để lọc thương hiệu/sản phẩm cho menu
const isLoadingCategories = ref(false);
const hoveredCategoryId = ref(null); // Biến theo dõi category ID đang hover

const isUserMenuActive = ref(false);
const userMenuContainer = ref(null);
const user = ref(null);

// --- SEARCH STATE ---
const searchQuery = ref('');
const searchResults = ref([]);
const categoryResults = ref([]);
const showSearchResults = ref(false);
const searchContainer = ref(null);
const isCategoryFallback = ref(false);
let searchDebounce = null;
const isFetchingSearch = ref(false);

// --- COMPUTED ---
const cartItemCount = computed(() => store.getters.cartCount || 0);

const userInitial = computed(() => {
  if (!user.value || !user.value.fullName) return 'U';
  return user.value.fullName.charAt(0).toUpperCase();
});

// --- HELPER FUNCTIONS ---
const getFullImageUrl = (path) => {
  if (!path) return 'https://placehold.co/50x50?text=No+Img';
  if (path.startsWith('http') || path.startsWith('https') || path.startsWith('blob:')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  return `${BACKEND_URL}/${cleanPath}`;
};

const formatCurrency = (value) => {
  if (value === null || value === undefined) return "0 ₫";
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};

// [NEW] Helper lấy rating an toàn
const getRating = (product) => {
  return Number(product.rating_average || product.rating || 5);
};

// [UPDATED] Logic lấy dữ liệu Mega Menu
const getMegaDataForCategory = (categoryId) => {
  if (!categoryId || products.value.length === 0) return null;

  // 1. Lọc sản phẩm CHỈ thuộc danh mục này VÀ có status là 'active'
  const categoryProducts = products.value.filter(p =>
    String(p.category_id) === String(categoryId) &&
    p.status === 'active'
  );

  if (categoryProducts.length === 0) return null;

  // 2. Trích xuất thương hiệu
  const brandsSet = new Set();
  categoryProducts.forEach(p => {
    let brandName = '';
    if (p.brand && typeof p.brand === 'object') {
      brandName = p.brand.name;
    } else if (p.brand) {
      brandName = p.brand;
    } else if (p.brand_name) {
      brandName = p.brand_name;
    }

    if (brandName && brandName !== 'No Brand') {
      brandsSet.add(brandName);
    }
  });

  // 3. Lấy 5 sản phẩm nổi bật (Bán chạy nhất)
  const displayProducts = [...categoryProducts]
    .sort((a, b) => (b.sold_count || 0) - (a.sold_count || 0))
    .slice(0, 5);

  // 4. Lấy 5 sản phẩm đánh giá cao (Rating cao nhất) -> "Có thể bạn thích"
  const topRatedProducts = [...categoryProducts]
    .sort((a, b) => getRating(b) - getRating(a))
    .slice(0, 5);

  return {
    brands: Array.from(brandsSet).sort(),
    products: displayProducts,
    topRated: topRatedProducts
  };
};

// --- FETCH DATA ---
const fetchData = async () => {
  isLoadingCategories.value = true;
  try {
    const [catRes, prodRes] = await Promise.all([
      apiService.get(`/categories`),
      apiService.get(`/products?per_page=200`)
    ]);

    categories.value = catRes.data || [];

    if (prodRes.data && prodRes.data.data) {
      products.value = prodRes.data.data;
    } else if (Array.isArray(prodRes.data)) {
      products.value = prodRes.data;
    } else {
      products.value = [];
    }

  } catch (error) {
    console.error("Lỗi tải dữ liệu header:", error);
  } finally {
    isLoadingCategories.value = false;
  }
};

// --- ACTION HANDLERS ---
const toggleMenu = () => {
  isMenuActive.value = !isMenuActive.value;
  isUserMenuActive.value = false;
  hoveredCategoryId.value = null;
};

// Xử lý Hover Mega Menu
const handleMouseEnterCategory = (categoryId) => {
  hoveredCategoryId.value = categoryId;
};

// Khi chuột rời khỏi toàn bộ dropdown thì mới tắt hover
const handleMouseLeaveDropdown = () => {
  hoveredCategoryId.value = null;
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
  if (searchContainer.value && !searchContainer.value.contains(event.target)) {
    showSearchResults.value = false;
  }
};

// --- SEARCH LOGIC ---
const performSearch = async (query) => {
  if (!query) {
    searchResults.value = [];
    categoryResults.value = [];
    isCategoryFallback.value = false;
    return;
  }
  isFetchingSearch.value = true;
  isCategoryFallback.value = false;

  try {
    const productPromise = apiService.get('/products', {
      params: { keyword: query, per_page: 5 }
    });
    const catMatches = categories.value.filter(cat =>
      cat.name.toLowerCase().includes(query.toLowerCase())
    ).slice(0, 3);
    const [pResponse] = await Promise.all([productPromise]);

    let foundProducts = [];
    if (pResponse.data && pResponse.data.data) foundProducts = pResponse.data.data;
    else if (Array.isArray(pResponse.data)) foundProducts = pResponse.data;

    if (foundProducts.length === 0 && catMatches.length > 0) {
      const firstCatId = catMatches[0].id;
      try {
        const catProductRes = await apiService.get('/products', {
          params: { category_id: firstCatId, per_page: 5 }
        });
        if (catProductRes.data && catProductRes.data.data) foundProducts = catProductRes.data.data;
        else if (Array.isArray(catProductRes.data)) foundProducts = catProductRes.data;

        if (foundProducts.length > 0) isCategoryFallback.value = true;
      } catch (err) { console.error(err); }
    }
    searchResults.value = foundProducts;
    categoryResults.value = catMatches;
  } catch (error) { console.error(error); }
  finally { isFetchingSearch.value = false; }
};

const onSearchInput = (event) => {
  const newVal = event.target.value;
  searchQuery.value = newVal;
  const query = newVal ? newVal.trim() : '';
  if (!query) {
    showSearchResults.value = false;
    searchResults.value = [];
    categoryResults.value = [];
    return;
  }
  showSearchResults.value = true;
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => { performSearch(query); }, 50);
};

const onSearchFocus = () => {
  if (searchQuery.value.trim()) {
    showSearchResults.value = true;
    if (searchResults.value.length === 0 && categoryResults.value.length === 0) performSearch(searchQuery.value);
  }
};

const handleSearch = () => {
  showSearchResults.value = false;
  if (searchQuery.value.trim()) router.push({ name: 'search', query: { q: searchQuery.value } });
};

const goToProduct = (productId) => {
  showSearchResults.value = false;
  isMenuActive.value = false;
  router.push(`/products/${productId}`);
};

const goToCategory = (categoryId) => {
  showSearchResults.value = false;
  isMenuActive.value = false;
  router.push({ name: 'Shop', query: { categoryId: categoryId } });
};

// Link đến Shop với query 'brand'
const goToShopWithBrand = (categoryId, brandName) => {
  isMenuActive.value = false;
  router.push({
    name: 'Shop',
    query: {
      categoryId: categoryId,
      brand: brandName
    }
  });
};

// --- AUTH LOGIC ---
const handleLogout = () => {
  Swal.fire({
    title: 'Đăng xuất?',
    text: 'Bạn có chắc muốn đăng xuất?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#009981',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Đăng xuất',
    cancelButtonText: 'Ở lại',
  }).then((result) => {
    if (result.isConfirmed) {
      localStorage.removeItem('userData');
      localStorage.removeItem('authToken');
      localStorage.removeItem('auth_token');
      store.commit('SET_LOGIN_STATUS', false);
      store.dispatch('clearCart');
      user.value = null;
      router.push({ name: 'login' });
    }
  });
};

const handleLoginSuccess = (event) => {
  user.value = event.detail.user;
  store.commit('SET_LOGIN_STATUS', true);
  store.dispatch('initializeCart').catch(err => console.warn(err));
};

const handleUserUpdate = (event) => {
  if (event.detail) user.value = event.detail;
  else {
    const userData = localStorage.getItem('userData');
    if (userData) user.value = JSON.parse(userData);
  }
};

const loadLordicon = () => {
  if (!document.querySelector('script[src="https://cdn.lordicon.com/lordicon.js"]')) {
    const script = document.createElement('script')
    script.src = 'https://cdn.lordicon.com/lordicon.js'
    script.async = true
    document.head.appendChild(script)
  }
}

// --- LIFECYCLE ---
onMounted(() => {
  loadLordicon();
  try {
    const userData = localStorage.getItem('userData');
    if (userData) user.value = JSON.parse(userData);
  } catch (e) { localStorage.removeItem('userData'); }

  document.addEventListener('click', handleClickOutside);
  window.addEventListener('login-success', handleLoginSuccess);
  window.addEventListener('user-info-updated', handleUserUpdate);

  fetchData();

  const token = localStorage.getItem('authToken') || localStorage.getItem('auth_token');
  store.commit('SET_LOGIN_STATUS', !!token);

  if (store._actions && store._actions.initializeCart) {
    store.dispatch('initializeCart').catch(err => console.warn(err));
  }
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  window.removeEventListener('login-success', handleLoginSuccess);
  window.removeEventListener('user-info-updated', handleUserUpdate);
});
</script>

<template>
 <Chatbot :products="products" />

  <header class="site-header">
    <!-- Top Bar -->
    <div class="top-bar">
      <div class="container d-flex justify-content-between align-items-center">
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
      <div class="container d-flex justify-content-between align-items-center relative-container">

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

          <!-- Dropdown Container -->
          <div class="dropdown-menu" :class="{ active: isMenuActive }" @mouseleave="handleMouseLeaveDropdown">
            <div v-if="isLoadingCategories" class="p-3 text-center text-muted small">
              <i class="fa-solid fa-spinner fa-spin"></i> Đang tải...
            </div>

            <!-- Danh sách Category -->
            <ul class="menu-list" v-else-if="categories.length">
              <li v-for="category in categories" :key="category.id" class="menu-item-has-children"
                @mouseenter="handleMouseEnterCategory(category.id)">
                <router-link :to="{ name: 'Shop', query: { categoryId: category.id } }">
                  <div class="d-flex align-items-center">
                    <span v-html="category.icon" class="icon-placeholder"></span>
                    {{ category.name }}
                  </div>
                  <i v-if="getMegaDataForCategory(category.id)" class="fa-solid fa-chevron-right arrow-icon"></i>
                </router-link>

                <!-- MEGA MENU PANEL -->
                <div class="mega-menu-panel"
                  v-if="hoveredCategoryId === category.id && getMegaDataForCategory(category.id)">

                  <div class="mega-content-wrapper">
                    <!-- Cột 1: Thương hiệu (20%) -->
                    <div class="mega-column brands-column" v-if="getMegaDataForCategory(category.id).brands.length > 0">
                      <h4 class="mega-title">Thương hiệu</h4>
                      <div class="brands-list-vertical">
                        <a href="#" v-for="brand in getMegaDataForCategory(category.id).brands" :key="brand"
                          class="brand-link" @click.prevent="goToShopWithBrand(category.id, brand)">
                          {{ brand }}
                        </a>
                      </div>
                    </div>

                    <!-- Cột 2: Nổi bật (40%) -->
                    <div class="mega-column products-column border-end">
                      <h4 class="mega-title">Nổi bật nhất</h4>
                      <ul class="mega-product-list">
                        <li v-for="prod in getMegaDataForCategory(category.id).products" :key="prod.id"
                          @click.stop="goToProduct(prod.id)">
                          <div class="search-item">
                            <!-- Ảnh to hơn -->
                            <img :src="getFullImageUrl(prod.thumbnail_url || prod.image_url)"
                              class="search-item-img big-img" alt="img"
                              @error="$event.target.src = 'https://placehold.co/50x50?text=No+Img'">
                            <div class="search-item-info">
                              <div class="search-item-name">{{ prod.name }}</div>
                              <!-- Hiển thị Giá + Đã bán -->
                              <div class="d-flex align-items-center gap-2 mt-1">
                                <div class="search-item-price text-danger fw-bold">
                                  {{ formatCurrency(prod.price || prod.min_price) }}
                                </div>
                                <small class="text-muted ms-auto" style="font-size: 11px;">Đã bán {{ prod.sold_count ||
                                  0 }}</small>
                              </div>
                              <!-- Hiển thị Rating -->
                              <div class="d-flex align-items-center gap-1 mt-1">
                                <div class="text-warning small" style="font-size: 11px;">
                                  {{ getRating(prod).toFixed(1) }} <i class="fa-solid fa-star"></i>
                                </div>
                              </div>
                            </div>
                          </div>
                        </li>
                      </ul>
                    </div>

                    <!-- Cột 3: Có thể bạn thích (40%) - [UPDATED: Đồng nhất hiển thị] -->
                    <div class="mega-column products-column">
                      <h4 class="mega-title">Có thể bạn thích</h4>
                      <ul class="mega-product-list">
                        <li v-for="prod in getMegaDataForCategory(category.id).topRated" :key="prod.id"
                          @click.stop="goToProduct(prod.id)">
                          <div class="search-item">
                            <!-- Ảnh to hơn -->
                            <img :src="getFullImageUrl(prod.thumbnail_url || prod.image_url)"
                              class="search-item-img big-img" alt="img"
                              @error="$event.target.src = 'https://placehold.co/50x50?text=No+Img'">
                            <div class="search-item-info">
                              <div class="search-item-name">{{ prod.name }}</div>
                              <!-- Hiển thị Giá + Đã bán -->
                              <div class="d-flex align-items-center gap-2 mt-1">
                                <div class="search-item-price text-danger fw-bold">
                                  {{ formatCurrency(prod.price || prod.min_price) }}
                                </div>
                                <small class="text-muted ms-auto" style="font-size: 11px;">Đã bán {{ prod.sold_count ||
                                  0 }}</small>
                              </div>
                              <!-- Hiển thị Rating -->
                              <div class="d-flex align-items-center gap-1 mt-1">
                                <div class="text-warning small" style="font-size: 11px;">
                                  {{ getRating(prod).toFixed(1) }} <i class="fa-solid fa-star"></i>
                                </div>
                              </div>
                            </div>
                          </div>
                        </li>
                      </ul>
                    </div>

                  </div>

                </div>
              </li>
            </ul>

            <div v-else class="p-3 text-center text-muted small">Không có danh mục.</div>
          </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar-wrapper" ref="searchContainer">
          <form class="search-bar" @submit.prevent="handleSearch">
            <input type="text" placeholder="Tìm kiếm sản phẩm, danh mục..." :value="searchQuery" @input="onSearchInput"
              @focus="onSearchFocus">
            <button type="submit" class="search-btn">
              <i v-if="isFetchingSearch" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-magnifying-glass"></i>
            </button>
          </form>

          <div class="search-results-dropdown" v-if="showSearchResults && searchQuery.length > 0">
            <div v-if="categoryResults.length > 0" class="category-suggestions">
              <div class="search-header-label">Danh mục phù hợp</div>
              <ul class="search-list">
                <li v-for="cat in categoryResults" :key="cat.id" @click="goToCategory(cat.id)">
                  <div class="search-item category-item">
                    <span class="cat-icon"><i class="fa-solid fa-folder-open"></i></span>
                    <span class="cat-name">{{ cat.name }}</span>
                  </div>
                </li>
              </ul>
            </div>
            <div v-if="searchResults.length > 0">
              <div class="search-header-label">
                {{ isCategoryFallback ? 'Gợi ý từ danh mục' : 'Sản phẩm gợi ý' }}
              </div>
              <ul class="search-list">
                <li v-for="prod in searchResults" :key="prod.id" @click="goToProduct(prod.id)">
                  <div class="search-item">
                    <img :src="getFullImageUrl(prod.thumbnail_url || prod.image_url)" class="search-item-img" alt="img"
                      @error="$event.target.src = 'https://placehold.co/50x50?text=No+Img'">
                    <div class="search-item-info">
                      <div class="search-item-name"
                        v-html="!isCategoryFallback ? prod.name.replace(new RegExp(`(${searchQuery})`, 'gi'), '<mark>$1</mark>') : prod.name">
                      </div>
                      <div class="search-item-price">
                        {{ formatCurrency(prod.price || prod.min_price) }}
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
              <div class="search-footer text-center p-2 border-top">
                <a href="#" @click.prevent="handleSearch" class="text-primary small fw-bold">
                  Xem tất cả kết quả cho "{{ searchQuery }}" <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
              </div>
            </div>
            <div
              v-else-if="!isFetchingSearch && categoryResults.length === 0 && searchResults.length === 0 && searchQuery.length > 1"
              class="p-3 text-center text-muted">
              <span class="d-block mb-2"><i class="fa-regular fa-face-frown-open fs-4"></i></span>
              Không tìm thấy kết quả nào cho "<strong>{{ searchQuery }}</strong>"
            </div>
          </div>
        </div>

        <!-- Header Actions -->
        <div class="header-actions">
          <router-link :to="{ name: 'cart' }" class="action-item side-menu-container cart-action">
            <div class="cart-icon-wrapper">
              <i class="fa-solid fa-cart-shopping"></i>
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
              <img v-if="user.avatar_url" :src="getFullImageUrl(user.avatar_url)" class="user-avatar-mini" alt="Avatar"
                @error="user.avatar_url = null">
              <div v-else class="user-avatar-placeholder">
                {{ userInitial }}
              </div>
              <span class="user-name-truncate">{{ user.fullName || user.email }}</span>
            </button>
            <div class="dropdown-menu user-dropdown" :class="{ active: isUserMenuActive }">
              <div class="user-dropdown-header">
                <strong>{{ user.fullName || 'Người dùng' }}</strong>
                <small>{{ user.email }}</small>
              </div>
              <ul class="menu-list">
                <li><router-link :to="{ name: 'profile' }" class="lordicon-link profile-target">
                    <div class="icon-wrap"><lord-icon src="https://cdn.lordicon.com/bhfjfgqz.json" trigger="hover"
                        target=".profile-target" colors="primary:#4b5563" style="width:20px;height:20px"></lord-icon>
                    </div>Tài khoản
                  </router-link></li>
                <li><router-link :to="{ name: 'OrderList' }" class="lordicon-link order-target">
                    <div class="icon-wrap"><lord-icon src="https://cdn.lordicon.com/pbrgppbb.json" trigger="hover"
                        target=".order-target" colors="primary:#4b5563" style="width:20px;height:20px"></lord-icon>
                    </div>Đơn mua
                  </router-link></li>
                <li><router-link :to="{ name: 'wishlist' }" class="lordicon-link wishlist-target">
                    <div class="icon-wrap"><lord-icon src="https://cdn.lordicon.com/rjzlnunf.json" trigger="hover"
                        target=".wishlist-target" colors="primary:#4b5563,secondary:#e91e63"
                        style="width:20px;height:20px"></lord-icon></div>Yêu thích
                  </router-link></li>
                <li class="divider">
                  <hr>
                </li>
                <li><a href="#" class="logout-link lordicon-link logout-target" @click.prevent="handleLogout">
                    <div class="icon-wrap"><lord-icon src="https://cdn.lordicon.com/moscwhoj.json" trigger="hover"
                        target=".logout-target" colors="primary:#d9534f,secondary:#d9534f"
                        style="width:20px;height:20px"></lord-icon></div>Đăng xuất
                  </a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<style>
.elegant-popup {
  border-radius: 16px !important;
  font-family: Arial, sans-serif !important;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15) !important;
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
  background-color: #009981 !important;
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

mark {
  background-color: transparent;
  color: #009981;
  padding: 0;
  font-weight: 800;
  text-decoration: underline;
}
</style>

<style scoped>
/* Biến màu */
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

/* --- DROPDOWN & MEGA MENU STYLING --- */
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
  /* QUAN TRỌNG: overflow visible để menu con hiển thị ra ngoài */
  overflow: visible;
}

.dropdown-menu.active {
  display: block;
}

.menu-list {
  position: relative;
}

/* Giúp định vị các item */

.menu-list li {
  /* [QUAN TRỌNG] Đổi thành STATIC để Mega Menu thoát khỏi dòng này, căn theo .dropdown-menu */
  position: static;
}

.menu-list li>a {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 15px;
  color: var(--text-dark);
  font-size: 14px;
  transition: all 0.2s;
  border-left: 3px solid transparent;
}

/* FIX RIÊNG CHO USER MENU: Đưa icon và text về cùng phía trái */
.user-dropdown .menu-list li > a {
  justify-content: flex-start;
}

.menu-list li:hover>a {
  background-color: var(--primary-light);
  color: var(--primary-dark);
  padding-left: 18px;
  border-left-color: var(--primary-color);
  font-weight: 500;
}

/* MEGA MENU PANEL - POP OUT */
.mega-menu-panel {
  position: absolute;
  left: 100%;
  top: 0;
  /* Luôn bắt đầu từ đỉnh menu cha */

  width: 1070px;
  /* [UPDATED] Tăng độ rộng mega menu */
  min-height: 100%;

  background: #fff;
  border: 1px solid #eee;
  border-radius: 0 8px 8px 8px;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
  padding: 0;
  /* Padding xử lý bên trong */
  z-index: 1002;
  overflow: hidden;
  /* Bo góc con */
}

/* Layout 3 cột bên trong Mega Menu */
.mega-content-wrapper {
  display: flex;
  min-height: 350px;
  /* Đảm bảo chiều cao tối thiểu */
}

.mega-column {
  padding: 15px;
}

/* Cột Thương hiệu */
.brands-column {
  width: 20%;
  /* Chiếm 20% */
  border-right: 1px solid #f0f0f0;
  background-color: #fcfcfc;
}

.brands-list-vertical {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.brand-link {
  font-size: 13px;
  color: #555;
  padding: 6px 10px;
  border-radius: 6px;
  transition: all 0.2s;
  background: #fff;
  border: 1px solid #eee;
}

.brand-link:hover {
  color: var(--primary-color);
  border-color: var(--primary-color);
  background: #f0fdfa;
  padding-left: 14px;
  /* Hiệu ứng đẩy nhẹ */
}

/* Cột Sản phẩm */
.products-column {
  width: 40%;
  /* Chiếm 40% mỗi cột */
  background: #fff;
}

.mega-product-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.mega-product-list li {
  border-bottom: 1px solid #f5f5f5;
  cursor: pointer;
  transition: background 0.2s;
}

.mega-product-list li:last-child {
  border-bottom: none;
}

.mega-product-list li:hover {
  background-color: #fafafa;
}

/* Mega Title */
.mega-title {
  font-size: 14px;
  font-weight: 700;
  color: #333;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 2px solid var(--primary-light);
  text-transform: uppercase;
}

.icon-placeholder {
  width: 25px;
  text-align: center;
  color: #999;
}

.menu-list li a:hover .icon-placeholder {
  color: var(--primary-color);
}

.arrow-icon {
  font-size: 10px;
  color: #ccc;
}

/* SEARCH BAR */
.search-bar-wrapper {
  flex-grow: 1;
  position: relative;
  margin-left: 20px;
}

.search-bar {
  width: 100%;
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
  box-shadow: 0 0 0 3px rgba(0, 153, 129, 0.1);
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
  transition: 0.2s;
}

.search-btn:hover {
  background-color: var(--primary-dark);
}

/* SEARCH RESULTS DROPDOWN (INSTANT) */
.search-results-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #eee;
  border-radius: 8px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  z-index: 1002;
  margin-top: 8px;
  overflow: hidden;
}

.search-header-label {
  padding: 10px 15px;
  font-size: 12px;
  color: #666;
  background: #f8f9fa;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #eee;
  border-top: 1px solid #eee;
}

.search-header-label:first-child {
  border-top: none;
}

.search-list {
  list-style: none;
  padding: 0;
  margin: 0;
  max-height: 400px;
  overflow-y: auto;
}

.search-list::-webkit-scrollbar {
  width: 6px;
}

.search-list::-webkit-scrollbar-thumb {
  background-color: #ccc;
  border-radius: 3px;
}

.search-list li {
  cursor: pointer;
  border-bottom: 1px solid #f1f1f1;
  transition: background 0.2s;
}

.search-list li:last-child {
  border-bottom: none;
}

.search-list li:hover {
  background-color: #f0fdfa;
}

.search-item {
  display: flex;
  align-items: center;
  padding: 10px;
}

.search-item-img {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border-radius: 4px;
  margin-right: 12px;
  border: 1px solid #eee;
  background: #fff;
}

/* [UPDATED] Style cho ảnh to hơn trong mega menu */
.search-item-img.big-img {
  width: 60px;
  height: 60px;
  margin-right: 15px;
}

.search-item-info {
  flex: 1;
  min-width: 0;
}

.search-item-name {
  font-size: 13px;
  font-weight: 500;
  color: #333;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.3;
}

.search-item-price {
  font-size: 13px;
  color: #d9534f;
  font-weight: 700;
  margin-top: 2px;
}

.category-item {
  padding: 10px 15px;
}

.cat-icon {
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e6fffa;
  color: #009981;
  border-radius: 50%;
  margin-right: 10px;
  font-size: 14px;
}

.cat-name {
  font-weight: 600;
  color: #333;
}

.search-footer {
  background-color: #fcfcfc;
}

.search-footer a:hover {
  text-decoration: underline !important;
  color: var(--primary-dark) !important;
}

/* HEADER ACTIONS */
.header-actions {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-left: 20px;
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