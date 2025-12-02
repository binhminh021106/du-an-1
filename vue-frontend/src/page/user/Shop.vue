<script setup>
import { ref, computed, watch, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import apiService from '../../apiService.js'

// --- CẤU HÌNH ---
const SERVER_URL = 'http://127.0.0.1:8000'
const USE_STORAGE = false

const route = useRoute()
const router = useRouter()
const store = useStore()

// --- UTILS ---
const removeAccents = (str) => {
  if (!str) return ''
  return str.normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd').replace(/Đ/g, 'D')
    .toLowerCase()
}

const formatCurrency = (value) =>
  new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)

const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/300x300?text=No+Img'
  if (path.startsWith('http')) return path
  const cleanPath = path.startsWith('/') ? path.substring(1) : path
  return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`
}

const getProductPrice = (product) => {
  if (product.variants && product.variants.length > 0) {
    return Math.min(...product.variants.map(v => Number(v.price)))
  }
  return Number(product.price) || 0
}

const getProductStock = (product) => {
  if (product.variants && product.variants.length > 0) {
    return product.variants.reduce((acc, v) => acc + (Number(v.stock) || Number(v.quantity) || 0), 0)
  }
  return Number(product.stock) || Number(product.quantity) || 0
}

// Hàm kiểm tra sản phẩm mới (trong vòng 30 ngày hoặc có flag is_new)
const isNewProduct = (product) => {
  if (product.is_new) return true;
  if (!product.created_at) return false;
  const createdDate = new Date(product.created_at);
  const diffTime = Math.abs(new Date() - createdDate);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  return diffDays <= 30; // Coi là mới nếu nhập trong 30 ngày
}

// --- STATE ---
const allProducts = ref([])
const categories = ref([])
const hotSaleProducts = ref([])
const loading = ref(false)

// --- KHU VỰC LỌC GIÁ NHANH (QUICK TAGS) ---
const priceRanges = [
  { label: 'Dưới 1 triệu', min: 0, max: 1000000 },
  { label: '1 - 5 triệu', min: 1000000, max: 5000000 },
  { label: '5 - 10 triệu', min: 5000000, max: 10000000 },
  { label: '10 - 20 triệu', min: 10000000, max: 20000000 },
  { label: 'Trên 20 triệu', min: 20000000, max: 1000000000 } // Max số lớn
];

const selectPriceRange = (range) => {
  filters.priceMin = range.min;
  filters.priceMax = range.max;
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const isActivePriceRange = (range) => {
  return filters.priceMin === range.min && filters.priceMax === range.max;
};

// --- FILTERS STATE ---
const filters = reactive({
  keyword: route.query.search || '',
  categoryId: route.query.categoryId || null,
  priceMin: 0,
  priceMax: 100000000, // Giá trị mặc định lớn
  brands: [], 
  minRating: 0, 
  inStockOnly: false,
  newArrivalsOnly: false,
  sortBy: 'default' 
})

const searchInput = ref(filters.keyword)

// Countdown
const saleEndTime = new Date()
saleEndTime.setDate(saleEndTime.getDate() + 1)
const countdownDisplay = ref('00 : 00 : 00 : 00')
const countdownInterval = ref(null)

// --- COMPUTED ---
const availableBrands = computed(() => {
  const brands = new Set()
  allProducts.value.forEach(p => {
    const brand = p.brand || p.brand_name || 'No Brand'
    if (brand) brands.add(brand)
  })
  return Array.from(brands)
})

const currentCategoryName = computed(() => {
  if (!filters.categoryId) return 'Tất cả sản phẩm'
  const cat = categories.value.find(c => String(c.id) === String(filters.categoryId))
  return cat ? cat.name : 'Danh mục không xác định'
})

// --- CORE: LOGIC LỌC VÀ TÌM KIẾM ---
const filteredProducts = computed(() => {
  if (!allProducts.value) return []
  
  let result = [...allProducts.value]

  // 1. Danh mục
  if (filters.categoryId) {
    result = result.filter(p => {
      const pCatId = p.category?.id || p.category_id
      return String(pCatId) === String(filters.categoryId)
    })
  }

  // 2. Tìm kiếm (Có dấu hoặc Không dấu)
  if (filters.keyword.trim()) {
    const keywordRaw = filters.keyword.toLowerCase().trim()
    const keywordNoAccent = removeAccents(keywordRaw)

    result = result.filter(p => {
      const name = p.name ? p.name.toLowerCase() : ''
      const desc = p.description ? p.description.toLowerCase() : ''
      const nameNoAccent = removeAccents(name)
      const descNoAccent = removeAccents(desc)

      return name.includes(keywordRaw) || nameNoAccent.includes(keywordNoAccent) ||
             desc.includes(keywordRaw) || descNoAccent.includes(keywordNoAccent)
    })
  }

  // 3. Thương hiệu
  if (filters.brands.length > 0) {
    result = result.filter(p => {
      const pBrand = p.brand || p.brand_name || 'No Brand'
      return filters.brands.includes(pBrand)
    })
  }

  // 4. Giá
  result = result.filter(p => {
    const price = getProductPrice(p)
    return price >= filters.priceMin && price <= filters.priceMax
  })

  // 5. Đánh giá
  if (filters.minRating > 0) {
    result = result.filter(p => {
      const rating = p.rating_average || p.rating || 5 
      return rating >= filters.minRating
    })
  }

  // 6. Tồn kho
  if (filters.inStockOnly) {
    result = result.filter(p => getProductStock(p) > 0)
  }

  // 7. Hàng mới về
  if (filters.newArrivalsOnly) {
    result = result.filter(p => isNewProduct(p))
  }

  // 8. Sắp xếp
  switch (filters.sortBy) {
    case 'price_asc': result.sort((a, b) => getProductPrice(a) - getProductPrice(b)); break;
    case 'price_desc': result.sort((a, b) => getProductPrice(b) - getProductPrice(a)); break;
    case 'newest': result.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0)); break;
    case 'best_sell': result.sort((a, b) => (b.sold_count || 0) - (a.sold_count || 0)); break;
    case 'name_asc': result.sort((a, b) => a.name.localeCompare(b.name)); break;
  }

  return result
})

// --- ACTIONS ---
const updateCountdown = () => {
  const now = new Date()
  const distance = saleEndTime - now
  if (distance < 0) {
    clearInterval(countdownInterval.value)
    countdownDisplay.value = 'Hết hạn Sale!'
    return
  }
  const days = Math.floor(distance / (1000 * 60 * 60 * 24))
  const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
  const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))
  const seconds = Math.floor((distance % (1000 * 60)) / 1000)
  const pad = (num) => String(num).padStart(2, '0')
  countdownDisplay.value = `${pad(days)} : ${pad(hours)} : ${pad(minutes)} : ${pad(seconds)}`
}

const fetchData = async () => {
  loading.value = true
  try {
    const [prodRes, catRes] = await Promise.all([
      apiService.get(`/products`),
      apiService.get(`/categories?status=active`)
    ])
   allProducts.value = prodRes.data.data || prodRes.data || []
 categories.value = catRes.data.data || catRes.data || []
 
 hotSaleProducts.value = allProducts.value.slice(0, 5).map(p => ({
      ...p,
      sale_price: getProductPrice(p) * 0.85, // Giảm 15%
      old_price: getProductPrice(p),
      discount: 15
    }))
  } catch (err) {
    console.error('Error fetching data:', err)
  } finally {
    loading.value = false
  }
}

const onAddToCart = async (product) => {
  let variant = null
  if (product.variants && product.variants.length > 0) {
    const minPrice = getProductPrice(product)
    variant = product.variants.find(v => Number(v.price) === minPrice) || product.variants[0]
  }
  
  try {
    await store.dispatch('addToCart', { product, quantity: 1, variant })
    alert(`✅ Đã thêm "${product.name}" vào giỏ hàng!`)
  } catch (error) {
    console.error(error)
    alert('❌ Có lỗi khi thêm vào giỏ hàng')
  }
}

const goToProduct = (productId) => {
  if (!productId) return
  router.push(`/products/${productId}`)
}

const handleSearch = () => {
  filters.keyword = searchInput.value
  applyFiltersToRoute()
}

let debounceTimer = null
watch(searchInput, (newVal) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    filters.keyword = newVal
  }, 300) 
})

const selectCategory = (id) => {
  filters.categoryId = id
  filters.keyword = ''
  searchInput.value = ''
  applyFiltersToRoute()
}

const toggleBrand = (brand) => {
  if (filters.brands.includes(brand)) {
    filters.brands = filters.brands.filter(b => b !== brand)
  } else {
    filters.brands.push(brand)
  }
}

const clearAllFilters = () => {
  searchInput.value = ''
  filters.keyword = ''
  filters.categoryId = null
  filters.priceMin = 0
  filters.priceMax = 100000000
  filters.brands = []
  filters.minRating = 0
  filters.inStockOnly = false
  filters.newArrivalsOnly = false
  filters.sortBy = 'default'
  router.push({ query: {} })
}

const applyFiltersToRoute = () => {
  const query = {}
  if (filters.keyword) query.search = filters.keyword
  if (filters.categoryId) query.categoryId = filters.categoryId
  if (filters.sortBy !== 'default') query.sort = filters.sortBy
  router.push({ query })
}

onMounted(() => {
  fetchData()
  countdownInterval.value = setInterval(updateCountdown, 1000)
  if(route.query.sort) filters.sortBy = route.query.sort
})

watch(() => route.query, (newQuery) => {
  if ((newQuery.search || '') !== filters.keyword) {
     filters.keyword = newQuery.search || ''
     searchInput.value = newQuery.search || ''
  }
  filters.categoryId = newQuery.categoryId || null
})
</script>

<template>
  <div class="shop-wrapper container">
    <div class="shop-page">
      
      <div class="shop-layout">
        
        <!-- SIDEBAR -->
        <aside class="sidebar">
          
          <!-- 1. TÌM KIẾM -->
          <div class="filter-section">
            <h3><i class="fas fa-search"></i> Tìm kiếm</h3>
            <div class="search-input-wrapper">
              <input 
                v-model="searchInput" 
                @keyup.enter="handleSearch" 
                type="text" 
                placeholder="Tên sp, mô tả..." 
                class="search-box" 
              />
              <button @click="handleSearch" class="btn-search-abs"><i class="fas fa-search"></i></button>
            </div>
          </div>

          <!-- 2. DANH MỤC -->
          <h2 class="sidebar-title">Danh mục</h2>
          <ul class="category-list">
            <li :class="{ active: !filters.categoryId }" @click="selectCategory(null)">
              Tất cả sản phẩm
            </li>
            <li v-for="cat in categories" :key="cat.id" :class="{ active: String(filters.categoryId) === String(cat.id) }"
              @click="selectCategory(cat.id)">
              {{ cat.name }}
            </li>
          </ul>

          <!-- 3. LỌC THEO GIÁ (QUICK TAGS + MANUAL INPUT) -->
          <div class="filter-section mt-4">
            <h5><i class="fas fa-wallet"></i> Khoảng giá</h5>
            
            <!-- Các mức giá định sẵn (Chips) -->
            <div class="price-tags">
              <span 
                v-for="(range, index) in priceRanges" 
                :key="index"
                class="price-tag"
                :class="{ active: isActivePriceRange(range) }"
                @click="selectPriceRange(range)"
              >
                {{ range.label }}
              </span>
            </div>

            <!-- Nhập thủ công -->
            <div class="price-manual-input">
              <div class="manual-row">
                <input 
                  type="number" 
                  v-model.number="filters.priceMin" 
                  placeholder="Từ" 
                  min="0"
                  @change="applyFiltersToRoute"
                >
                <span class="sep">-</span>
                <input 
                  type="number" 
                  v-model.number="filters.priceMax" 
                  placeholder="Đến" 
                  min="0"
                  @change="applyFiltersToRoute"
                >
              </div>
            </div>
            <div class="current-range-display" v-if="filters.priceMax < 100000000">
                Đang lọc: {{ formatCurrency(filters.priceMin) }} - {{ formatCurrency(filters.priceMax) }}
            </div>
          </div>


          <!-- 4. THƯƠNG HIỆU -->
          <div class="filter-section mt-4" v-if="availableBrands.length > 0">
            <h5><i class="fas fa-tags"></i> Thương hiệu</h5>
            <div class="brand-list-container">
               <label v-for="brand in availableBrands" :key="brand" class="brand-item">
                 <input type="checkbox" :value="brand" :checked="filters.brands.includes(brand)" @change="toggleBrand(brand)">
                 {{ brand }}
               </label>
            </div>
          </div>

          <!-- 5. LỌC KHÁC -->
          <div class="filter-section mt-4">
            <h5><i class="fas fa-sliders-h"></i> Bộ lọc khác</h5>
            <div class="other-filters">
              <select v-model.number="filters.minRating" class="search-box mb-2">
                  <option value="0">Tất cả đánh giá</option>
                  <option value="5">5 sao</option>
                  <option value="4">4 sao trở lên</option>
              </select>
              
              <label class="stock-check">
                  <input type="checkbox" v-model="filters.newArrivalsOnly"> 
                  <span class="ml-2">🆕 Hàng mới về</span>
              </label>

              <label class="stock-check mt-2">
                  <input type="checkbox" v-model="filters.inStockOnly"> 
                  <span class="ml-2">📦 Chỉ hiện hàng có sẵn</span>
              </label>
            </div>
          </div>

          <button @click="clearAllFilters" class="btn-reset-all">
            <i class="fas fa-undo"></i> Reset bộ lọc
          </button>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
          <div class="shop-header">
            <div class="header-left">
              <h1 class="page-title">{{ currentCategoryName }}</h1>
              <p v-if="filters.categoryId" class="category-desc">
                Danh mục: <b>{{ currentCategoryName }}</b>
              </p>
            </div>
            
            <div class="header-right">
               <select v-model="filters.sortBy" class="sort-dropdown">
                  <option value="default">Sắp xếp: Mặc định</option>
                  <option value="newest">Hàng mới về</option>
                  <option value="best_sell">Bán chạy nhất</option>
                  <option value="price_asc">Giá tăng dần</option>
                  <option value="price_desc">Giá giảm dần</option>
               </select>
            </div>
          </div>

          <div v-if="loading" class="text-center py-5">Đang tải...</div>

          <div v-else-if="filteredProducts.length === 0" class="no-products">
            😔 Không tìm thấy sản phẩm phù hợp.
          </div>

          <section v-else class="product-listing">
            <div class="product-grid">
              <div class="product-card" 
                v-for="product in filteredProducts" 
                :key="product.id"
                @click="goToProduct(product.id)">
                
                <div class="product-image">
                  <img 
                    :src="getImageUrl(product.thumbnail_url || product.image_url)" 
                    :alt="product.name" 
                    @error="$event.target.src='https://placehold.co/300x300?text=Product'"
                  />
                  <span v-if="isNewProduct(product)" class="new-tag">NEW</span>
                  <span v-else-if="product.discount" class="discount-tag">-{{ product.discount }}%</span>
                </div>
                <div class="product-info">
 <h3 class="product-name" :title="product.name">{{ product.name }}</h3>
<p class="product-price">
 {{ formatCurrency(getProductPrice(product)) }}
 </p>
                  
                  <button class="btn-add-cart" 
                    @click.stop="onAddToCart(product)" 
                    :disabled="getProductStock(product) <= 0">
                    <i class="fas fa-cart-plus"></i> 
                    {{ getProductStock(product) > 0 ? 'Thêm vào giỏ' : 'Hết hàng' }}
                  </button>
                </div>
              </div>
            </div>
          </section>
        </main>
      </div>

      <!-- BOTTOM SECTIONS -->
      <div>
        <section class="hot-sale-section" v-if="hotSaleProducts.length > 0">
          <div class="hot-sale-header">
            <h2><i class="fas fa-fire"></i> HOT SALE <span>Cuối tuần</span></h2>
            <div class="countdown">
              Kết thúc sau: <b class="timer">{{ countdownDisplay }}</b>
            </div>
          </div>
          <div class="hot-sale-scroll">
            <div class="hot-sale-card" 
              v-for="product in hotSaleProducts" 
              :key="product.id"
              @click="goToProduct(product.id)">
              
              <div class="discount-badge">Giảm {{ product.discount || 10 }}%</div>
              <div class="hot-sale-image">
                <img :src="getImageUrl(product.thumbnail_url || product.image_url)" @error="$event.target.src='https://placehold.co/250x250?text=Sale'" />
              </div>
              <h3 class="hot-sale-name">{{ product.name }}</h3>
 <p class="hot-sale-price">{{ formatCurrency(product.sale_price) }}</p>
 <p class="hot-sale-old-price">{{ formatCurrency(product.old_price) }}</p>
              <div class="hot-sale-actions">
                <button class="btn-love hot-sale-btn"><i class="fas fa-heart"></i></button>
                <button class="btn-cart hot-sale-btn" @click.stop="onAddToCart(product)"> <i class="fas fa-cart-plus"></i></button>
              </div>
            </div>
          </div>
        </section>

        <section class="promo-section-wrapper">
          <div class="promo-grid">
            <div class="promo-column">
              <h3>ƯU ĐÃI SINH VIÊN</h3>
              <div class="banner-grid">
                <a href="#" class="banner-item"><img src="https://intphcm.com/data/upload/banner-la-gi.jpg" alt="Banner 1"></a>
                <a href="#" class="banner-item"><img src="https://truonggiang.vn/wp-content/uploads/2021/07/banner-laptop-sinh-vien-scaled.jpg" alt="Banner 2"></a>
                <a href="#" class="banner-item"><img src="https://img.pikbest.com/origin/09/05/73/13npIkbEsT8MI.jpg!w700wp" alt="Banner 3"></a>
                <a href="#" class="banner-item"><img src="https://marketplace.canva.com/EAGbDiUQ-wQ/1/0/1600w/canva-%C4%91%E1%BA%A7y-m%C3%A0u-s%E1%BA%AFc-r%E1%BB%B1c-r%E1%BB%A1-minh-h%E1%BB%8Da-khung-sale-khuy%E1%BA%BFn-m%C3%A3i-s%E1%BA%A3n-ph%E1%BA%A9m-banner-qnv0_ENRCWE.jpg" alt="Banner 4"></a>
              </div>
            </div>
            <div class="promo-column">
              <h3>ƯU ĐÃI THANH TOÁN</h3>
              <div class="banner-grid">
                <a href="#" class="banner-item"><img src="https://cdn.tgdd.vn/hoi-dap/1355217/banner-tgdd-800x300.jpg" alt="Banner 5"></a>
                <a href="#" class="banner-item"><img src="https://img.pikbest.com/origin/09/02/27/61IpIkbEsTsYE.jpg!w700wp" alt="Banner 6"></a>
                <a href="#" class="banner-item"><img src="https://img.pikbest.com/templates/20240425/spirited-mothers-day-holiday-wishes-222024-png-images-png_10534920.jpg!w700wp" alt="Banner 7"></a>
                <a href="#" class="banner-item"><img src="https://marketplace.canva.com/EAGsR-bwGFg/1/0/800w/canva-v%C3%A0ng-xanh-hi%E1%BB%87n-%C4%91%E1%BA%A1i-ng%C3%A0y-%C4%91%C3%B4i-8.8-sale-deal-%C6%B0u-%C4%91%E1%BA%A3i-s%E1%BA%A3n-ph%E1%BA%A9m-banner-ngang-TeXwbgwuYoc.jpg" alt="Banner 8"></a>
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

/* --- LAYOUT --- */
.shop-layout {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 25px;
  align-items: flex-start;
}

/* --- SIDEBAR --- */
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

.filter-section h3, .filter-section h5 {
  font-size: 1.1em;
  color: #222;
  font-weight: 600;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 5px;
}

.category-list {
  list-style: none;
  padding: 0;
  margin: 0 0 20px 0;
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

/* Search */
.search-input-wrapper { position: relative; }
.search-box {
  width: 100%;
  padding: 10px 35px 10px 10px;
  border: 1px solid #d0d8d7;
  border-radius: 8px;
  margin-bottom: 8px;
  transition: border-color 0.2s;
}
.search-box:focus {
  border-color: var(--primary-color);
  outline: none;
}
.btn-search-abs {
  position: absolute; right: 8px; top: 10px;
  background: none; border: none; color: #888; cursor: pointer;
}

/* Brand & Other Filters */
.brand-list-container {
  max-height: 150px; overflow-y: auto; display: flex; flex-direction: column; gap: 5px;
}
.brand-item {
  display: flex; align-items: center; gap: 8px; font-size: 0.9em; cursor: pointer; color: #444;
}
.mt-4 { margin-top: 1.5rem; }
.mb-2 { margin-bottom: 0.5rem; }
.stock-check { font-size: 0.9em; display: flex; align-items: center; gap: 6px; cursor: pointer; }
.ml-2 { margin-left: 8px; }

/* Reset Button */
.btn-reset-all {
  width: 100%; padding: 11px; border-radius: 8px; border: 1px solid #d0d8d7;
  font-weight: 600; cursor: pointer; background: #ecf1ef; color: #333; margin-top: 20px;
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-reset-all:hover { background: #dce7e4; }

/* --- CSS LỌC GIÁ QUICK TAGS (ĐÃ TỐI ƯU) --- */
.price-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 15px;
}

.price-tag {
  font-size: 13px;
  background: #f5f5f5;
  color: #555;
  padding: 6px 12px;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid transparent;
  display: inline-block;
  white-space: nowrap; /* Giữ các tag không bị vỡ dòng */
}

.price-tag:hover {
  background: #e0e0e0;
  color: #333;
}

/* Trạng thái đang chọn */
.price-tag.active {
  background: var(--primary-color); 
  color: white;
  border-color: var(--primary-color);
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(0, 153, 129, 0.3);
}

/* Phần nhập thủ công */
.price-manual-input {
  background: #f9f9f9;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #eee;
  margin-top: 10px;
}

.manual-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 5px;
}

.manual-row input {
  width: 100%;
  padding: 6px 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 13px;
  outline: none;
  transition: border 0.2s;
  text-align: center;
}

.manual-row input:focus {
  border-color: var(--primary-color);
  background: #fff;
}

.manual-row .sep {
  color: #999;
  font-weight: bold;
}

.current-range-display {
  margin-top: 8px;
  font-size: 12px;
  color: var(--primary-color);
  text-align: center;
  font-weight: 600;
  background: #fff;
  padding: 4px;
  border-radius: 4px;
  border: 1px dashed var(--primary-color);
}
/* --- MAIN CONTENT --- */
.main-content {
  background: white; border-radius: 12px; padding: 25px;
  border: 1px solid #e3e3e3; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
}

.shop-header {
  display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;
  border-bottom: 1px solid #eee; padding-bottom: 10px;
}
.page-title { font-size: 1.6em; font-weight: 700; color: #111; margin: 0; }
.category-desc { color: #555; font-size: 0.95em; margin-top: 5px; }
.sort-dropdown {
  padding: 8px; border: 1px solid #ddd; border-radius: 6px; color: #333; cursor: pointer;
}

/* Grid & Card */
.product-listing { margin-top: 10px; }
.product-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 18px;
}
.product-card {
  background: #fdfdfd; border: 1px solid #eee; border-radius: 12px; overflow: hidden;
  transition: all 0.25s ease; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  cursor: pointer; display: flex; flex-direction: column; position: relative;
}
.product-card:hover { transform: translateY(-6px); box-shadow: 0 8px 15px var(--shadow-color); }
.product-image { height: 180px; background: #f5f8f7; display: flex; align-items: center; justify-content: center; position: relative; }
.product-image img { max-height: 100%; object-fit: contain; }

.discount-tag {
  position: absolute; top: 10px; left: 10px; background: #ff4d4d; color: white;
  padding: 2px 6px; font-size: 0.75em; border-radius: 4px; font-weight: bold;
}
.new-tag {
  position: absolute; top: 10px; left: 10px; background: #3b82f6; color: white;
  padding: 2px 6px; font-size: 0.75em; border-radius: 4px; font-weight: bold;
}

.product-info { padding: 12px 15px 18px; display: flex; flex-direction: column; flex-grow: 1; }
.product-name { font-size: 1em; font-weight: 600; color: #333; margin-bottom: 6px; min-height: 2.8em; }
.product-price { font-size: 1.1em; font-weight: 700; color: var(--primary-color); margin-bottom: 10px; }
.btn-add-cart {
  width: 100%; background: var(--primary-color); color: white; border: none; padding: 10px;
  border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: auto; transition: all 0.2s ease;
}
.btn-add-cart:hover { background-color: #c82333; }
.btn-add-cart:disabled { background-color: #ccc; cursor: not-allowed; }

.no-products { text-align: center; padding: 40px 0; color: #777; font-size: 1.1em; }

/* --- HOT SALE & PROMO --- */
.hot-sale-section {
  padding: 25px; background: linear-gradient(to right, #fff8f8, #f5f8f7);
  border: 1px solid #ffd6d6; border-radius: 12px; margin-bottom: 30px; margin-top: 30px;
  box-shadow: 0 3px 10px rgba(255, 77, 77, 0.08);
}
.hot-sale-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.hot-sale-header h2 {
  color: var(--hot-sale-color); font-size: 1.6em; font-weight: 800; display: flex; align-items: center; gap: 10px;
}
.hot-sale-header span { font-size: 1.0em; background: var(--hot-sale-color); color: white; padding: 3px 8px; border-radius: 5px; }
.countdown { font-weight: 600; color: #333; }
.countdown .timer { color: var(--hot-sale-color); background: white; padding: 6px 10px; border-radius: 6px; box-shadow: 0 1px 4px rgba(255, 77, 77, 0.15); }
.hot-sale-scroll { display: flex; overflow-x: auto; gap: 16px; padding-bottom: 5px; scrollbar-width: none; }
.hot-sale-scroll::-webkit-scrollbar { display: none; }
.hot-sale-card {
  background: white; border-radius: 12px; border: 1px solid #eee; padding: 12px;
  text-align: center; min-width: 210px; box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
  transition: all 0.25s ease; flex-shrink: 0; position: relative;
  cursor: pointer;
}
.hot-sale-card:hover { transform: translateY(-5px); box-shadow: 0 8px 18px rgba(255, 77, 77, 0.2); }
.discount-badge {
  position: absolute; top: 10px; left: 10px; background: var(--hot-sale-color); color: white;
  padding: 3px 8px; border-radius: 20px; font-size: 0.8em; font-weight: bold;
}
.hot-sale-image { height: 150px; display: flex; justify-content: center; align-items: center; }
.hot-sale-image img { max-width: 100%; max-height: 100%; object-fit: contain; }
.hot-sale-name { font-size: 1em; font-weight: 600; margin: 8px 0 5px; height: 40px; overflow: hidden; }
.hot-sale-price { color: var(--hot-sale-color); font-weight: bold; }
.hot-sale-old-price { text-decoration: line-through; color: #999; font-size: 0.9em; }
.hot-sale-actions { display: flex; justify-content: center; gap: 10px; margin-top: 8px; }
.hot-sale-btn {
  background: #f3f6f5; border: none; color: var(--primary-color); width: 38px; height: 38px;
  border-radius: 50%; cursor: pointer; transition: all 0.2s ease;
}
.hot-sale-btn:hover { transform: scale(1.1); background: var(--primary-color); color: white; }

.promo-section-wrapper {
  background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px;
  border: 1px solid #e3e3e3; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
}
.promo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
.promo-column h3 { font-size: 1.2em; font-weight: 700; margin-bottom: 15px; color: #333; text-transform: uppercase; }
.banner-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
.banner-item {
  display: block; border-radius: 8px; overflow: hidden; transition: transform 0.2s ease;
  border: 1px solid #f0f0f0; aspect-ratio: 2.2 / 1;
}
.banner-item:hover { transform: scale(1.03); }
.banner-item img { width: 100%; height: 100%; display: block; object-fit: cover; }

@media (max-width: 992px) {
  .shop-layout { grid-template-columns: 1fr; }
  .sidebar { position: relative; top: 0; margin-bottom: 20px; }
  .promo-grid { grid-template-columns: 1fr; }
}
</style>