<script setup>
import { ref, computed, watch, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import apiService from '../../apiService.js'
import Swal from 'sweetalert2' // [NEW] Import SweetAlert2

// --- CẤU HÌNH ---
const SERVER_URL = 'http://127.0.0.1:8000'
const USE_STORAGE = false

const route = useRoute()
const router = useRouter()
const store = useStore()

// --- [NEW] CẤU HÌNH TOAST (Thông báo xịn xò) ---
const Toast = Swal.mixin({
    toast: true,
    position: 'bottom-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

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

// Hàm kiểm tra sản phẩm mới
const isNewProduct = (createdAt, isNewFlag) => {
  if (isNewFlag) return true;
  if (!createdAt) return false;
  const createdDate = new Date(createdAt);
  const diffTime = Math.abs(new Date() - createdDate);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  return diffDays <= 30;
}

// [NEW] Hàm helper lấy rating an toàn cho mọi loại object sản phẩm
const getRating = (product) => {
  // Ưu tiên rating_average (từ API gốc), fallback sang rating (nếu đã qua xử lý), mặc định là 5
  return Number(product.rating_average || product.rating || 5);
}

// --- STATE ---
const allProducts = ref([])
const categories = ref([])
const hotSaleProducts = ref([])
const loading = ref(false)

// [NEW] Ref cho container cuộn ngang
const hotSaleScrollRef = ref(null)

// [NEW] Pagination State
const currentPage = ref(1)
const itemsPerPage = 24

const filters = reactive({
  keyword: route.query.search || '',
  categoryId: route.query.categoryId || null,
  priceMin: 0,
  priceMax: 50000000,
  brands: [],
  minRating: 0,
  inStockOnly: false,
  newArrivalsOnly: false,
  sortBy: 'default'
})

const setPriceFilter = (min, max) => {
  filters.priceMin = min
  filters.priceMax = max
}

const searchInput = ref(filters.keyword)

// Countdown
const saleEndTime = new Date()
saleEndTime.setDate(saleEndTime.getDate() + 1)
const countdownDisplay = ref('00 : 00 : 00 : 00')
const countdownInterval = ref(null)

// --- CORE: LOGIC "TRẢI PHẲNG" BIẾN THỂ (FLATTEN VARIANTS) ---
const flattenedShopItems = computed(() => {
  if (!allProducts.value) return []

  let items = []

  allProducts.value.forEach(product => {
    // Xử lý logic tên thương hiệu an toàn
    let brandName = 'No Brand';
    if (product.brand && typeof product.brand === 'object') {
        brandName = product.brand.name || 'No Brand';
    } else if (product.brand) {
        brandName = product.brand; // Trường hợp cũ nếu brand là string
    } else if (product.brand_name) {
        brandName = product.brand_name;
    }

    // Nếu sản phẩm có biến thể
    if (product.variants && product.variants.length > 0) {
      product.variants.forEach((variant, index) => {
        let variantSuffix = ''
        // Kiểm tra an toàn cả snake_case và camelCase
        const attrs = variant.attribute_values || variant.attributeValues || [];
        
        if (attrs && Array.isArray(attrs) && attrs.length > 0) {
          variantSuffix = attrs.map(av => av.value).join(' - ')
        } else if (variant.sku) {
          variantSuffix = variant.sku
        } else {
          variantSuffix = `Mẫu ${index + 1}`
        }

        items.push({
          unique_key: `${product.id}_v_${variant.id}`,
          is_variant: true,
          id: product.id,
          variant_id: variant.id,
          name: `${product.name} | ${variantSuffix}`,
          pure_name: product.name,
          price: Number(variant.price) || 0,
          original_price: Number(variant.original_price) || Number(variant.price) || 0,
          stock: Number(variant.stock) || 0,
          image: variant.image || product.thumbnail_url || product.image_url,
          rating: product.rating_average || product.rating || 5,
          sold_count: product.sold_count || 0,
          brand: brandName,
          category: product.category,
          category_id: product.category_id,
          created_at: product.created_at,
          is_new: product.is_new,
          discount: product.discount,
          raw_product: product,
          raw_variant: variant
        })
      })
    } else {
      items.push({
        unique_key: `${product.id}_single`,
        is_variant: false,
        id: product.id,
        variant_id: null,
        name: product.name,
        pure_name: product.name,
        price: Number(product.price) || 0,
        original_price: Number(product.original_price) || Number(product.price) || 0,
        stock: Number(product.stock) || 0,
        image: product.thumbnail_url || product.image_url,
        rating: product.rating_average || product.rating || 5,
        sold_count: product.sold_count || 0,
        brand: brandName,
        category: product.category,
        category_id: product.category_id,
        created_at: product.created_at,
        is_new: product.is_new,
        discount: product.discount,
        raw_product: product,
        raw_variant: null
      })
    }
  })

  return items
})

// --- COMPUTED: AVAILABLE BRANDS ---
const availableBrands = computed(() => {
  const brands = new Set()
  // Flattened items đã xử lý chuẩn hóa tên brand rồi, lấy từ đó cho đồng bộ
  flattenedShopItems.value.forEach(item => {
    if (item.brand && item.brand !== 'No Brand') {
        brands.add(item.brand)
    }
  })
  return Array.from(brands).sort()
})

const currentCategoryName = computed(() => {
  if (!filters.categoryId) return 'Tất cả sản phẩm'
  const cat = categories.value.find(c => String(c.id) === String(filters.categoryId))
  return cat ? cat.name : 'Danh mục không xác định'
})

// --- CORE: LOGIC LỌC VÀ TÌM KIẾM ---
const filteredProducts = computed(() => {
  let result = [...flattenedShopItems.value]

  // 1. Danh mục
  if (filters.categoryId) {
    result = result.filter(item => {
      const pCatId = item.category?.id || item.category_id
      return String(pCatId) === String(filters.categoryId)
    })
  }

  // 2. Tìm kiếm
  if (filters.keyword.trim()) {
    const keywordRaw = filters.keyword.toLowerCase().trim()
    const keywordNoAccent = removeAccents(keywordRaw)

    result = result.filter(item => {
      const name = item.name.toLowerCase()
      const pureName = item.pure_name.toLowerCase()
      const catName = item.category ? item.category.name.toLowerCase() : ''
      const nameNoAccent = removeAccents(name)
      const pureNameNoAccent = removeAccents(pureName)

      return name.includes(keywordRaw) || nameNoAccent.includes(keywordNoAccent) ||
        pureName.includes(keywordRaw) || pureNameNoAccent.includes(keywordNoAccent) ||
        catName.includes(keywordRaw)
    })
  }

  // 3. Thương hiệu
  if (filters.brands.length > 0) {
    result = result.filter(item => filters.brands.includes(item.brand))
  }

  // 4. Giá
  result = result.filter(item => {
    return item.price >= filters.priceMin && item.price <= filters.priceMax
  })

  // 5. Đánh giá
  if (filters.minRating > 0) {
    result = result.filter(item => item.rating >= filters.minRating)
  }

  // 6. Tồn kho
  if (filters.inStockOnly) {
    result = result.filter(item => item.stock > 0)
  }

  // 7. MỚI
  if (filters.newArrivalsOnly) {
    result = result.filter(item => isNewProduct(item.created_at, item.is_new))
  }

  // 8. Sắp xếp
  switch (filters.sortBy) {
    case 'price_asc': result.sort((a, b) => a.price - b.price); break;
    case 'price_desc': result.sort((a, b) => b.price - a.price); break;
    case 'newest': result.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0)); break;
    case 'best_sell': result.sort((a, b) => (b.sold_count || 0) - (a.sold_count || 0)); break;
    case 'name_asc': result.sort((a, b) => a.name.localeCompare(b.name)); break;
    // [UPDATE] Thêm sắp xếp theo đánh giá
    case 'rating_desc': result.sort((a, b) => (b.rating || 0) - (a.rating || 0)); break;
  }

  return result
})

// [NEW] Computed Pagination
const totalPages = computed(() => {
    return Math.ceil(filteredProducts.value.length / itemsPerPage)
})

const paginatedProducts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage
    const end = start + itemsPerPage
    return filteredProducts.value.slice(start, end)
})

// Watch filters change to reset page
watch(() => filters, () => {
    currentPage.value = 1
}, { deep: true })

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page
        // Scroll to top of grid
        const grid = document.querySelector('.main-content')
        if (grid) grid.scrollIntoView({ behavior: 'smooth' })
    }
}

// [NEW] Scroll Logic for Hot Sale
const scrollHotSale = (direction) => {
  if (!hotSaleScrollRef.value) return
  
  // Cuộn mỗi lần khoảng 1 card + gap (240px + 16px)
  const scrollAmount = 260 
  
  if (direction === 'left') {
    hotSaleScrollRef.value.scrollBy({ left: -scrollAmount, behavior: 'smooth' })
  } else {
    hotSaleScrollRef.value.scrollBy({ left: scrollAmount, behavior: 'smooth' })
  }
}

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

    // [UPDATE] Tăng số lượng lên 7 sản phẩm
    hotSaleProducts.value = allProducts.value.slice(0, 7).map(p => {
      let minPrice = Number(p.price) || 0;
      let displayVariant = null;
      let displayName = p.name;
      let displayImage = p.thumbnail_url || p.image_url;

      if (p.variants && p.variants.length > 0) {
        const sortedVariants = [...p.variants].sort((a, b) => Number(a.price) - Number(b.price));
        displayVariant = sortedVariants[0];
        minPrice = Number(displayVariant.price);

        if (displayVariant.image) {
            displayImage = displayVariant.image;
        }

        let variantSuffix = '';
        // [FIX] Kiểm tra cả 2 trường hợp: attribute_values (Laravel mặc định) và attributeValues (nếu có custom serializer)
        // Điều này đảm bảo dữ liệu luôn được lấy đúng dù backend trả về format nào
        const attrs = displayVariant.attribute_values || displayVariant.attributeValues || [];

        if (attrs && attrs.length > 0) {
              variantSuffix = attrs.map(av => av.value).join(' - ');
        } else if (displayVariant.sku) {
              variantSuffix = displayVariant.sku;
        } else {
              variantSuffix = 'Mẫu tiêu chuẩn';
        }
        displayName = `${p.name} | ${variantSuffix}`;
      }

      return {
        ...p,
        name: displayName,
        image: displayImage,
        sale_price: minPrice * 0.85,
        old_price: minPrice,
        discount: 15,
        stock: displayVariant ? Number(displayVariant.stock) : Number(p.stock),
        raw_product: p,
        raw_variant: displayVariant,
        unique_key: `hot_sale_${p.id}`
      }
    })
  } catch (err) {
    console.error('Error fetching data:', err)
  } finally {
    loading.value = false
  }
}

// [UPDATED] HÀM THÊM VÀO GIỎ VỚI THÔNG BÁO XỊN
const onAddToCart = async (item) => {
  try {
    await store.dispatch('addToCart', {
      product: item.raw_product,
      quantity: 1,
      variant: item.raw_variant
    })
    // [FIX] Dùng Toast thay cho alert
    Toast.fire({
        icon: 'success',
        title: `Đã thêm "${item.name}" vào giỏ hàng!`
    })
  } catch (error) {
    console.error(error)
    Toast.fire({
        icon: 'error',
        title: 'Có lỗi khi thêm vào giỏ hàng'
    })
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
  currentPage.value = 1
  router.push({ query: {} })
}

const applyFiltersToRoute = () => {
  const query = {}
  if (filters.keyword) query.search = filters.keyword
  if (filters.categoryId) query.categoryId = filters.categoryId
  if (filters.sortBy !== 'default') query.sort = filters.sortBy
  router.push({ query })
}

// [NEW] Load Lordicon Script dynamically
const loadLordicon = () => {
  if (!document.querySelector('script[src="https://cdn.lordicon.com/lordicon.js"]')) {
    const script = document.createElement('script')
    script.src = 'https://cdn.lordicon.com/lordicon.js'
    script.async = true
    document.head.appendChild(script)
  }
}

onMounted(() => {
  loadLordicon() // Load icon script
  fetchData()
  countdownInterval.value = setInterval(updateCountdown, 1000)
  if (route.query.sort) filters.sortBy = route.query.sort
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
          
          <!-- [NEW] Wrapper chịu trách nhiệm Sticky -->
          <div class="sidebar-sticky-area">

            <!-- 1. TÌM KIẾM -->
            <div class="filter-section">
              <h3 class="flex items-center gap-2">
                  <!-- [ICON] Search -->
                  <lord-icon
                      src="https://cdn.lordicon.com/fkdzyfle.json"
                      trigger="hover"
                      colors="primary:#009981"
                      style="width:24px;height:24px">
                  </lord-icon>
                  Tìm kiếm
              </h3>
              <div class="search-input-wrapper">
                <input v-model="searchInput" @keyup.enter="handleSearch" type="text" placeholder="Tên sp, màu sắc..."
                  class="search-box" />
                <button @click="handleSearch" class="btn-search-abs">
                  <lord-icon
                      src="https://cdn.lordicon.com/fkdzyfle.json"
                      trigger="hover"
                      colors="primary:#888888"
                      style="width:20px;height:20px">
                  </lord-icon>
                </button>
              </div>
            </div>

            <!-- 2. DANH MỤC -->
            <h2 class="sidebar-title">Danh mục</h2>
            <ul class="category-list">
              <li :class="{ active: !filters.categoryId }" @click="selectCategory(null)">
                Tất cả sản phẩm
              </li>
              <li v-for="cat in categories" :key="cat.id"
                :class="{ active: String(filters.categoryId) === String(cat.id) }" @click="selectCategory(cat.id)">
                {{ cat.name }}
              </li>
            </ul>

            

            <!-- 4. THƯƠNG HIỆU -->
            <div class="filter-section mt-2" v-if="availableBrands.length > 0">
              <h2 class="sidebar-title">Thương hiệu</h2>
              <div class="brand-list-container">
                <label v-for="brand in availableBrands" :key="brand" class="brand-item">
                  <input type="checkbox" :value="brand" :checked="filters.brands.includes(brand)"
                    @change="toggleBrand(brand)">
                  {{ brand }}
                </label>
              </div>
            </div>


            <!-- [BUTTON] Reset Filter - Lordicon added and styled -->
            <button @click="clearAllFilters" class="btn-reset-all btn-hover-target">
              <lord-icon
                  src="https://cdn.lordicon.com/akuwjdzh.json"
                  trigger="hover"
                  target=".btn-hover-target"
                  colors="primary:#333333"
                  style="width:20px;height:20px">
              </lord-icon>
              Reset bộ lọc
            </button>

          </div> <!-- END Sticky Wrapper -->
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
          <div class="shop-header">
            <div class="header-left">
              <h1 class="page-title">{{ currentCategoryName }}</h1>
              <p v-if="filters.categoryId" class="category-desc">
                Danh mục: <b>{{ currentCategoryName }}</b>
              </p>
              <!-- Info pagination -->
              <p class="mt-2 text-sm text-gray-500" v-if="filteredProducts.length > 0">
                 Hiển thị <b>{{ paginatedProducts.length }}</b> / <b>{{ filteredProducts.length }}</b> sản phẩm
              </p>
            </div>

            <div class="header-right">
              <select v-model="filters.sortBy" class="sort-dropdown">
                <option value="default">Sắp xếp: Mặc định</option>
                <option value="newest">Hàng mới về</option>
                <option value="best_sell">Bán chạy nhất</option>
                <option value="rating_desc">Đánh giá cao nhất</option> <!-- [NEW] -->
                <option value="price_asc">Giá tăng dần</option>
                <option value="price_desc">Giá giảm dần</option>
              </select>
            </div>
          </div>

          <div v-if="loading" class="text-center py-5">Đang tải...</div>

          <div v-else-if="filteredProducts.length === 0" class="no-products">
            Không tìm thấy sản phẩm phù hợp.
          </div>

          <section v-else class="product-listing">
            <!-- [UPDATE] Sử dụng paginatedProducts thay vì filteredProducts -->
            <div class="product-grid">
              <div class="product-card" v-for="item in paginatedProducts" :key="item.unique_key"
                @click="goToProduct(item.id)">

                <div class="product-image mt-sm-1">
                  <img :src="getImageUrl(item.image)" :alt="item.name"
                    @error="$event.target.src = 'https://placehold.co/300x300?text=Product'" />
                  <!-- Badges -->
                  <span v-if="isNewProduct(item.created_at, item.is_new)" class="new-tag">NEW</span>
                  <span v-else-if="item.discount" class="discount-tag">-{{ item.discount }}%</span>
                </div>

                <div class="product-info">
                  <h3 class="product-name" :title="item.name">{{ item.name }}</h3>

                  <p class="product-price">
                    {{ formatCurrency(item.price) }}
                    <span v-if="item.original_price > item.price" class="old-price-small">
                      {{ formatCurrency(item.original_price) }}
                    </span>
                  </p>
                  
                  <!-- Rating star visual (Optional) -->
                  <div class="product-rating" v-if="item.rating" style="font-size: 0.85em; color: #ffb400; margin-bottom: 8px;">
                      <i class="fas fa-star"></i> {{ Number(item.rating).toFixed(1) }}
                  </div>

                  <div class="product-actions-group">
                    <!-- [MODIFIED] Gán class định danh unique cho target -->
                    <button class="btn-add-cart btn-variant-add" :class="`btn-target-${item.unique_key}`"
                      @click.stop="onAddToCart(item)" :disabled="item.stock <= 0">

                      <!-- [NEW] LORDICON with TARGET -->
                      <div class="lord-icon-wrapper">
                        <lord-icon src="https://cdn.lordicon.com/evyuuwna.json" trigger="hover"
                          :target="`.btn-target-${item.unique_key}`" colors="primary:#ffffff,secondary:#ffffff"
                          style="width:24px;height:24px">
                        </lord-icon>
                      </div>

                      {{ item.stock > 0 ? 'Thêm ngay' : 'Hết hàng' }}
                    </button>
                  </div>

                </div>
              </div>
            </div>

            <!-- [NEW] PAGINATION CONTROLS with Lordicon -->
            <div class="pagination-controls" v-if="totalPages > 1">
                <button 
                    class="btn-page btn-page-prev" 
                    :disabled="currentPage === 1" 
                    @click="changePage(currentPage - 1)"
                >
                    <lord-icon
                        src="https://cdn.lordicon.com/vduvxizq.json"
                        trigger="hover"
                        target=".btn-page-prev"
                        colors="primary:#333333"
                        style="width:20px;height:20px;transform: rotate(180deg);">
                    </lord-icon>
                </button>

                <span class="page-info">
                    Trang {{ currentPage }} / {{ totalPages }}
                </span>

                <button 
                    class="btn-page btn-page-next" 
                    :disabled="currentPage === totalPages" 
                    @click="changePage(currentPage + 1)"
                >
                    <lord-icon
                        src="https://cdn.lordicon.com/vduvxizq.json"
                        trigger="hover"
                        target=".btn-page-next"
                        colors="primary:#333333"
                        style="width:20px;height:20px">
                    </lord-icon>
                </button>
            </div>
          </section>
        </main>
      </div>

      <!-- BOTTOM SECTIONS (Consistent Design) -->
      <div class="mt-4">
        <!-- HOT SALE SECTION -->
        <section class="consistent-section hot-sale-section" v-if="hotSaleProducts.length > 0">
          <div class="section-header">
            <h2 class="section-title">
                <!-- [ICON] FIRE -->
                <lord-icon
                    src="https://cdn.lordicon.com/tqywkdcz.json"
                    trigger="loop"
                    colors="primary:#ff4d4d,secondary:#ff4d4d"
                    style="width:36px;height:36px">
                </lord-icon>
                HOT SALE <span>Cuối tuần</span>
            </h2>
            <div class="countdown">
              Kết thúc sau: <b class="timer">{{ countdownDisplay }}</b>
            </div>
          </div>
          
          <!-- [UPDATE] Thêm wrapper và nút scroll trái/phải -->
          <div class="hot-sale-container-relative">
            <button class="scroll-btn btn-prev" @click="scrollHotSale('left')">
                <i class="fas fa-chevron-left"></i>
            </button>

            <!-- Gán ref vào div cuộn -->
            <div class="hot-sale-scroll" ref="hotSaleScrollRef">
                <div class="product-card hot-sale-card-item" v-for="product in hotSaleProducts" :key="product.unique_key"
                @click="goToProduct(product.id)">

                <div class="product-image">
                    <img :src="getImageUrl(product.image)" :alt="product.name"
                    @error="$event.target.src = 'https://placehold.co/300x300?text=Product'" />
                    <span class="discount-tag">-{{ product.discount || 15 }}%</span>
                </div>
                
                <div class="product-info">
                    <h3 class="product-name" :title="product.name">{{ product.name }}</h3>
                    
                    <p class="product-price">
                        {{ formatCurrency(product.sale_price) }}
                        <span class="old-price-small">{{ formatCurrency(product.old_price) }}</span>
                    </p>

                    <!-- [MODIFIED] Use helper function for rating -->
                    <div class="product-rating" style="font-size: 0.85em; color: #ffb400; margin-bottom: 8px;">
                        <i class="fas fa-star"></i> {{ getRating(product).toFixed(1) }}
                    </div>
                    
                    <div class="product-actions-group">
                        <button class="btn-add-cart btn-variant-add" :class="`hs-target-${product.id}`"
                        @click.stop="onAddToCart(product)" :disabled="product.stock <= 0">
                        
                        <div class="lord-icon-wrapper">
                            <lord-icon
                                src="https://cdn.lordicon.com/evyuuwna.json"
                                trigger="hover"
                                :target="`.hs-target-${product.id}`"
                                colors="primary:#ffffff,secondary:#ffffff"
                                style="width:24px;height:24px">
                            </lord-icon>
                        </div>
                        {{ product.stock > 0 ? 'Thêm ngay' : 'Hết hàng' }}
                        </button>
                    </div>
                </div>
                </div>
            </div>

            <button class="scroll-btn btn-next" @click="scrollHotSale('right')">
                <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </section>

        <!-- PROMO SECTION -->
        <section class="consistent-section promo-section-wrapper">
          <div class="promo-grid">
            <div class="promo-column">
              <h3 class="section-subtitle">ƯU ĐÃI SINH VIÊN</h3>
              <div class="banner-grid">
                <a href="#" class="banner-item"><img src="https://intphcm.com/data/upload/banner-la-gi.jpg"
                    alt="Banner 1"></a>
                <a href="#" class="banner-item"><img
                    src="https://truonggiang.vn/wp-content/uploads/2021/07/banner-laptop-sinh-vien-scaled.jpg"
                    alt="Banner 2"></a>
                <a href="#" class="banner-item"><img
                    src="https://img.pikbest.com/origin/09/05/73/13npIkbEsT8MI.jpg!w700wp" alt="Banner 3"></a>
                <a href="#" class="banner-item"><img
                    src="https://marketplace.canva.com/EAGbDiUQ-wQ/1/0/1600w/canva-%C4%91%E1%BA%A7y-m%C3%A0u-s%E1%BA%AFc-r%E1%BB%B1c-r%E1%BB%A1-minh-h%E1%BB%8Da-khung-sale-khuy%E1%BA%BFn-m%C3%A3i-s%E1%BA%A3n-ph%E1%BA%A9m-banner-qnv0_ENRCWE.jpg"
                    alt="Banner 4"></a>
              </div>
            </div>
            <div class="promo-column">
              <h3 class="section-subtitle">ƯU ĐÃI THANH TOÁN</h3>
              <div class="banner-grid">
                <a href="#" class="banner-item"><img src="https://cdn.tgdd.vn/hoi-dap/1355217/banner-tgdd-800x300.jpg"
                    alt="Banner 5"></a>
                <a href="#" class="banner-item"><img
                    src="https://img.pikbest.com/origin/09/02/27/61IpIkbEsTsYE.jpg!w700wp" alt="Banner 6"></a>
                <a href="#" class="banner-item"><img
                    src="https://img.pikbest.com/templates/20240425/spirited-mothers-day-holiday-wishes-222024-png-images-png_10534920.jpg!w700wp"
                    alt="Banner 7"></a>
                <a href="#" class="banner-item"><img
                    src="https://marketplace.canva.com/EAGsR-bwGFg/1/0/800w/canva-v%C3%A0ng-xanh-hi%E1%BB%87n-%C4%91%E1%BA%A1i-ng%C3%A0y-%C4%91%C3%B4i-8.8-sale-deal-%C6%B0u-%C4%91%E1%BA%A3i-s%E1%BA%A3n-ph%E1%BA%A9m-banner-ngang-TeXwbgwuYoc.jpg"
                    alt="Banner 8"></a>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* [MODIFIED] Chuyển biến vào scope của wrapper để nhận diện đúng */
.shop-wrapper {
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
  /* [MODIFIED] Bỏ flex-start để Grid tự động stretch 2 cột bằng nhau */
  /* align-items: flex-start; */ 
  position: relative;
}

/* --- SIDEBAR [MODIFIED] --- */
.sidebar {
  /* [MODIFIED] Đây là lớp vỏ: Trong suốt và chỉ đóng vai trò giữ chỗ trong Grid */
  /* background: white; */ 
  /* border... */
  
  height: 100%; /* Đảm bảo nó chiếm đủ chiều cao của grid cell */
  display: block; 
}

/* [NEW] Lớp ruột bên trong: Chịu trách nhiệm hiển thị visual và Sticky */
.sidebar-sticky-area {
  /* [MOVED] Chuyển visual styles vào đây */
  background: white;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #e4e4e4;
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);

  /* Sticky Logic */
  position: -webkit-sticky;
  position: sticky;
  top: 20px; /* Offset khi cuộn */
  
  max-height: calc(100vh - 40px); /* Giới hạn chiều cao */
  overflow-y: auto; /* Scroll nội bộ khi nội dung dài */
  
  /* [IMPORTANT] Giúp sidebar co ngắn lại vừa khít nội dung */
  height: fit-content; 

  padding-right: 5px; /* Tránh nội dung sát scrollbar */
}

/* Custom Scrollbar cho phần ruột Sticky */
.sidebar-sticky-area::-webkit-scrollbar {
  width: 5px;
}
.sidebar-sticky-area::-webkit-scrollbar-thumb {
  background-color: #d1d1d1;
  border-radius: 4px;
}
.sidebar-sticky-area::-webkit-scrollbar-track {
  background: transparent;
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
.search-input-wrapper {
  position: relative;
}

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
  position: absolute;
  right: 8px;
  top: 10px;
  background: none;
  border: none;
  color: #888;
  cursor: pointer;
  display: flex; /* Centering Lordicon */
  align-items: center;
  justify-content: center;
}

/* Brand & Other Filters */
/* [MODIFIED] Làm danh sách brand trông "đầy đặn" hơn */
.brand-list-container {
  max-height: 200px; /* Tăng chiều cao một chút */
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 4px; /* Khoảng cách nhỏ giữa các dòng */
  padding-right: 5px; /* Chừa chỗ cho scrollbar */
}

/* Scrollbar đẹp hơn cho list brand */
.brand-list-container::-webkit-scrollbar {
  width: 4px;
}
.brand-list-container::-webkit-scrollbar-thumb {
  background-color: #e0e0e0;
  border-radius: 4px;
}

.brand-item {
  display: flex;
  align-items: center;
  gap: 12px; /* Tăng khoảng cách giữa checkbox và chữ */
  font-size: 0.95em;
  cursor: pointer;
  color: #555;
  padding: 8px 12px; /* Thêm padding để tạo khối */
  border-radius: 6px; /* Bo góc nhẹ */
  transition: all 0.2s ease;
  width: 100%; /* Chiếm hết chiều ngang */
}

.brand-item:hover {
  background-color: #f3f4f6; /* Nền xám nhẹ khi hover */
  color: var(--primary-color); /* Chữ đổi màu */
}

.brand-item input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: var(--primary-color);
  margin: 0; /* Xóa margin mặc định */
}

.mt-4 {
  margin-top: 1.5rem;
}

/* Reset Button */
.btn-reset-all {
  width: 100%;
  padding: 11px;
  border-radius: 8px;
  border: 1px solid #d0d8d7;
  font-weight: 600;
  cursor: pointer;
  background: #ecf1ef;
  color: #333;
  margin-top: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.btn-reset-all:hover {
  background: #dce7e4;
  transform: translateY(-2px);
  box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}


/* --- MAIN CONTENT & CONSISTENT SECTIONS --- */
.main-content, .consistent-section {
  background: white;
  border-radius: 12px;
  padding: 25px;
  border: 1px solid #e3e3e3;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 30px; /* Spacing between sections */
}

/* Header Styles */
.shop-header, .section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 20px;
  border-bottom: 1px solid #eee;
  padding-bottom: 10px;
}

.section-header {
    align-items: center; /* Center vertically for sections */
}

.page-title, .section-title {
  font-size: 1.6em;
  font-weight: 700;
  color: #111;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-subtitle {
    font-size: 1.2em;
    font-weight: 700;
    margin-bottom: 15px;
    color: #333;
    text-transform: uppercase;
    border-left: 4px solid var(--primary-color);
    padding-left: 10px;
}

.category-desc {
  color: #555;
  font-size: 0.95em;
  margin-top: 5px;
}

.sort-dropdown {
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 6px;
  color: #333;
  cursor: pointer;
}

/* --- PRODUCT CARDS (Unified Style) --- */
.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 18px;
}

/* Shared Card Styles */
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
  position: relative;
  /* Ensure consistent height in grid/flex */
  height: 100%; 
}

/* Add specialized class for hot sale scrolling item */
.hot-sale-card-item {
    width: 240px; /* Width fixed for scrolling card */
    max-width: 240px; /* Prevent expansion */
    flex-shrink: 0;
    margin-right: 5px;
    height: auto; /* Let flex stretch handle height */
}

.product-card:hover {
  box-shadow: 0 8px 15px var(--shadow-color);
  transform: translateY(-5px); /* Gentle lift */
}

/* Image Area */
.product-image {
  height: 180px;
  background: #f5f8f7;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

.product-image img {
  height: 100%; /* [UPDATE] Force full height per user request */
  width: auto;  /* Width adapts */
  object-fit: contain;
  transition: transform 0.5s ease;
}

.product-card:hover .product-image img {
  transform: scale(1.05);
}

/* Badges */
.discount-tag, .new-tag {
  position: absolute;
  top: 10px;
  left: 10px;
  color: white;
  padding: 2px 8px;
  font-size: 0.75em;
  border-radius: 20px; /* Rounder consistent look */
  font-weight: bold;
  z-index: 2;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.discount-tag { background: #ff4d4d; }
.new-tag { background: #3b82f6; }


/* Product Info */
.product-info {
  padding: 12px 15px 18px;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
  z-index: 3;
  background: #fff;
}

.product-name {
  font-size: 1em;
  font-weight: 600;
  color: #333;
  margin-bottom: 6px;
  min-height: 2.8em; /* 2 lines */
  line-height: 1.4em;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-price {
  font-size: 1.1em;
  font-weight: 700;
  color: var(--primary-color);
  margin-bottom: 5px;
}

.old-price-small {
  font-size: 0.8em;
  text-decoration: line-through;
  color: #999;
  font-weight: 400;
  margin-left: 5px;
}

/* Buttons */
.product-actions-group {
  display: flex;
  gap: 8px;
  margin-top: auto;
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
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.btn-add-cart:hover {
  background-color: var(--primary-hover-color);
  box-shadow: 0 4px 8px rgba(0, 153, 129, 0.25);
}

.btn-add-cart:disabled {
  background-color: #ccc;
  cursor: not-allowed;
  box-shadow: none;
}

/* LORDICON WRAPPER */
.lord-icon-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
}

.no-products {
  text-align: center;
  padding: 40px 0;
  color: #777;
  font-size: 1.1em;
}

/* --- HOT SALE SPECIFIC --- */
/* Remove old hot-sale-section styles that conflicted */
.hot-sale-section h2 span {
  font-size: 0.7em;
  background: var(--hot-sale-color);
  color: white;
  padding: 3px 8px;
  border-radius: 6px;
  margin-left: 8px;
  transform: translateY(-2px); /* Align visual */
}

.countdown {
  font-weight: 600;
  color: #333;
}

.countdown .timer {
  color: var(--hot-sale-color);
  background: #fff0f0; /* Light red bg */
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid #ffd6d6;
}

/* [NEW] Styling for Hot Sale Wrapper & Buttons */
.hot-sale-container-relative {
    position: relative;
    padding: 0 20px; /* Space for buttons if they were outside, keeps flow inside */
}

.hot-sale-scroll {
  display: flex;
  overflow-x: auto;
  gap: 16px;
  padding: 5px 2px 15px 2px; /* Bottom padding for shadow */
  scrollbar-width: none; /* Hide standard scrollbar */
  align-items: stretch;
  scroll-behavior: smooth;
}

.hot-sale-scroll::-webkit-scrollbar {
    display: none;
}

.scroll-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    background: white;
    border-radius: 50%;
    border: 1px solid #eee;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    z-index: 10;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #333;
    transition: all 0.2s ease;
}

.scroll-btn:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
    transform: translateY(-50%) scale(1.1);
}

.btn-prev { left: -15px; } /* Adjust to stick out slightly */
.btn-next { right: -15px; }

/* --- PROMO SPECIFIC --- */
.promo-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 25px;
}

.banner-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.banner-item {
  display: block;
  border-radius: 12px; /* Match global radius */
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border: 1px solid #eee; /* Match global border */
  aspect-ratio: 2.2 / 1;
}

.banner-item:hover {
  transform: scale(1.02);
  box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.banner-item img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

/* [NEW] Pagination Styles */
.pagination-controls {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 30px;
    gap: 15px;
}

.btn-page {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
    background: white;
    color: #333;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-page:hover:not(:disabled) {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.btn-page:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f5f5f5;
}

.page-info {
    font-weight: 600;
    color: #555;
    font-size: 0.95em;
}

@media (max-width: 992px) {
  .shop-layout {
    grid-template-columns: 1fr;
  }

  .sidebar {
    position: relative;
    top: 0;
    margin-bottom: 20px;
    height: auto !important; /* Mobile thì bỏ stretch */
  }

  .promo-grid {
    grid-template-columns: 1fr;
  }
}
</style>