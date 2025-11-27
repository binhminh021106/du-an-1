<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import apiService from '../../apiService.js';
// SỬA: Import đúng đường dẫn (cùng cấp thư mục)
import { addToCart } from "./user/cartStore.js"; 

const router = useRouter();

// --- 1. CẤU HÌNH HIỂN THỊ ẢNH (Đồng bộ với Cart/Shop) ---
const SERVER_URL = 'http://127.0.0.1:8000';   
const USE_STORAGE = false; 

const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/300x300?text=No+Img';
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`;
};

// --- 2. HÀM TẠO ICON ĐẸP CHO DANH MỤC ---
const getCategoryIcon = (name) => {
    const n = name.toLowerCase();
    if (n.includes('điện thoại') || n.includes('iphone')) return '<i class="fas fa-mobile-alt" style="color: #3498db;"></i>';
    if (n.includes('laptop') || n.includes('macbook')) return '<i class="fas fa-laptop" style="color: #e74c3c;"></i>';
    if (n.includes('tablet') || n.includes('ipad')) return '<i class="fas fa-tablet-alt" style="color: #f39c12;"></i>';
    if (n.includes('âm thanh') || n.includes('loa') || n.includes('tai nghe')) return '<i class="fas fa-headphones-alt" style="color: #9b59b6;"></i>';
    if (n.includes('đồng hồ')) return '<i class="fas fa-clock" style="color: #2ecc71;"></i>';
    if (n.includes('camera') || n.includes('chụp')) return '<i class="fas fa-camera" style="color: #e67e22;"></i>';
    if (n.includes('phụ kiện')) return '<i class="fas fa-plug" style="color: #34495e;"></i>';
    if (n.includes('màn hình')) return '<i class="fas fa-desktop" style="color: #1abc9c;"></i>';
    if (n.includes('chuột') || n.includes('phím')) return '<i class="fas fa-keyboard" style="color: #7f8c8d;"></i>';
    
    // Icon mặc định nếu không tìm thấy từ khóa
    return '<i class="fas fa-box-open" style="color: #bdc3c7;"></i>';
};

const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

// State
const categories = ref([]);
const brands = ref([]);
const slides = ref([]);
const products = ref([]);
const users = ref([]);
const newsList = ref([]);
const activeCategoryId = ref(null);
const currentSlide = ref(0);
let interval = null;

// --- FETCH DATA ---
const fetchData = async () => {
    try {
        const [catRes, slideRes, prodRes, userRes, newsRes, brandRes] = await Promise.all([
            apiService.get(`/categories`),
            apiService.get(`/slides`),
            apiService.get(`/products`),
            apiService.get(`/users`),
            apiService.get(`/news`),
            apiService.get(`/brands`),
        ]);

        categories.value = catRes.data.data || catRes.data || [];
        slides.value = slideRes.data.data || slideRes.data || [];
        products.value = prodRes.data.data || prodRes.data || [];
        users.value = userRes.data.data || userRes.data || [];
        newsList.value = newsRes.data.data || newsRes.data || [];
        brands.value = brandRes.data.data || brandRes.data || [];

    } catch (err) {
        console.error("Lỗi tải dữ liệu:", err);
    }
};

// --- COMPUTED PROPERTIES ---
const topFavoriteProducts = computed(() => {
    if (!Array.isArray(products.value)) return [];
    return [...products.value]
        .sort((a, b) => (b.favorite_count || 0) - (a.favorite_count || 0))
        .slice(0, 8);
});

const categoriesWithProducts = computed(() => {
    if (!Array.isArray(categories.value) || !Array.isArray(products.value)) return [];
    
    return categories.value.map(category => {
        const categoryProducts = products.value.filter(p =>
            String(p.category?.id) === String(category.id)
        );
        return {
            ...category,
            products: categoryProducts.slice(0, 8)
        };
    }).filter(category => category.products.length > 0);
});

// --- SLIDER LOGIC ---
const startAutoSlide = () => {
    if (!slides.value.length) return;
    clearInterval(interval);
    interval = setInterval(() => {
        currentSlide.value = (currentSlide.value + 1) % slides.value.length;
    }, 4000);
};
const stopAutoSlide = () => clearInterval(interval);
const nextSlide = () => { stopAutoSlide(); currentSlide.value = (currentSlide.value + 1) % slides.value.length; };
const prevSlide = () => { stopAutoSlide(); currentSlide.value = (currentSlide.value - 1 + slides.value.length) % slides.value.length; };
const goToSlide = (index) => { stopAutoSlide(); currentSlide.value = index; };


// --- HELPER FUNCTIONS ---
const setActiveCategory = (id) => { activeCategoryId.value = String(id); };

const getMinPrice = (variants) => {
    if (!variants || !variants.length) return 0;
    return Math.min(...variants.map(v => Number(v.price)));
};

// --- ACTIONS ---
const onAddToCart = (product) => {
    const minPrice = getMinPrice(product.variants);
    const variant = (product.variants && product.variants.length > 0)
        ? (product.variants.find(v => Number(v.price) === minPrice) || product.variants[0])
        : { id: 'default', name: 'Mặc định', price: minPrice || product.price || 0, stock: 999 };

    addToCart(product, variant, 1);
    alert(`Đã thêm vào giỏ: ${product.name}`);
};


// --- LIFECYCLE HOOKS ---
onMounted(async () => {
    await fetchData();
    startAutoSlide();
});
onBeforeUnmount(stopAutoSlide);
</script>

<template>
    <div id="app">
        <main class="container">

            <section class="top-section-layout">
                <nav class="categories-sidebar">
                    <h3 class="sidebar-title">
                        <i class="fas fa-bars me-2"></i> Danh mục
                    </h3>
                    <div v-if="categories.length === 0" class="p-3 text-muted text-center">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                        Đang tải...
                    </div>
                    <router-link v-for="category in categories" :key="category.id"
                        :to="{ path: '/Shop', query: { categoryId: category.id } }" class="category-item-sodo"
                        :class="{ active: String(category.id) === String(activeCategoryId) }"
                        @click="setActiveCategory(category.id)">
                        
                        <!-- FIX: Hiển thị icon đẹp dựa trên tên danh mục -->
                        <span class="icon" v-html="getCategoryIcon(category.name)"></span>
                        
                        <span class="cat-name">{{ category.name }}</span>
                        <i class="fas fa-chevron-right arrow-icon"></i>
                    </router-link>
                </nav>

                <section class="slider" @mouseenter="stopAutoSlide" @mouseleave="startAutoSlide">
                    <div class="slider-wrapper" :style="{ transform: 'translateX(-' + currentSlide * 100 + '%)' }">
                        <div class="slide" v-for="slide in slides" :key="slide.id">
                            <!-- FIX: Dùng getImageUrl cho Slider -->
                            <img :src="getImageUrl(slide.imageUrl || slide.image_url)" alt="Slide"
                                style="width: 100%; height: 100%; object-fit: cover;"
                                @error="$event.target.src='https://placehold.co/800x400?text=Slide+Image'">

                            <a :href="slide.linkUrl || '#'" class="slide-link"></a>
                        </div>
                    </div>
                    
                    <template v-if="slides.length > 1">
                        <button class="slider-control prev" @click="prevSlide"><i class="fas fa-chevron-left"></i></button>
                        <button class="slider-control next" @click="nextSlide"><i class="fas fa-chevron-right"></i></button>
                        <div class="slider-nav">
                            <span v-for="(slide, index) in slides" :key="slide.id" class="slider-nav-dot"
                                :class="{ active: index === currentSlide }" @click="goToSlide(index)"></span>
                        </div>
                    </template>
                </section>
            </section>

            <section class="brand-banner" style="margin-top: 20px;" v-if="brands.length > 0">
                <a :href="brands[0].linkUrl || '#'">
                    <!-- FIX: Dùng getImageUrl cho Banner -->
                    <img :src="getImageUrl(brands[0].imageUrl)" alt="Brand Banner"
                        style="width: 100%; height: 200px; object-fit: cover; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);"
                         @error="$event.target.style.display='none'">
                </a>
            </section>

            <section class="trust-block">
                <div class="trust-item">
                    <i class="fas fa-shield-alt text-primary me-2" style="font-size: 1.2em;"></i>
                    <span>Bảo hành chính hãng</span>
                </div>
                <div class="trust-item">
                    <i class="fas fa-truck text-success me-2" style="font-size: 1.2em;"></i>
                    <span>Giao hàng miễn phí</span>
                </div>
                <div class="trust-item">
                    <i class="fas fa-undo text-warning me-2" style="font-size: 1.2em;"></i>
                    <span>Đổi trả 30 ngày</span>
                </div>
                <div class="trust-item">
                    <i class="fas fa-headset text-danger me-2" style="font-size: 1.2em;"></i>
                    <span>Hỗ trợ 24/7</span>
                </div>
            </section>

            <section class="product-section-container">

                <section class="product-group hot-products" v-if="topFavoriteProducts.length > 0">
                    <h2 class="section-title"><i class="fas fa-heart text-danger me-2"></i> Sản phẩm yêu thích</h2>
                    <div class="product-grid">
                        <div class="product-card" v-for="product in topFavoriteProducts" :key="product.id">
                            <!-- FIX: Dùng getImageUrl cho Sản phẩm -->
                            <img :src="getImageUrl(product.thumbnail_url || product.image_url)" :alt="product.name" 
                                @error="$event.target.src='https://placehold.co/300x300?text=Product'">
                            
                            <h3 class="product-name">{{ product.name }}</h3>

                            <div class="product-stats">
                                <span class="rating">
                                    <i class="fas fa-star text-warning"></i> {{ product.average_rating || 5 }}
                                </span>
                                <span class="favorite-count ms-3">
                                    <i class="fas fa-heart text-danger"></i> {{ product.favorite_count || 0 }}
                                </span>
                            </div>

                            <div class="product-price">
                                <span class="new-price">{{ formatCurrency(getMinPrice(product.variants)) }}</span>
                            </div>
                            <div class="card-actions-small">
                                <button class="btn-view"
                                    @click="$router.push({ path: `/products/${product.id}` })">
                                    <i class="fas fa-eye"></i> Xem
                                </button>

                                <button class="btn-add-cart" @click="onAddToCart(product)">
                                    <i class="fas fa-cart-plus"></i> Thêm
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <template v-for="category in categoriesWithProducts" :key="category.id">
                    <section class="product-group category-group" :id="'cat-' + category.id">
                        <h2 class="section-title">
                            <!-- Icon cho tiêu đề Section -->
                            <span class="me-2" v-html="getCategoryIcon(category.name)"></span>
                            {{ category.name }} nổi bật
                        </h2>

                        <div class="product-grid">
                            <div class="product-card" v-for="product in category.products" :key="product.id">
                                <img :src="getImageUrl(product.thumbnail_url || product.image_url)" :alt="product.name" 
                                    @error="$event.target.src='https://placehold.co/300x300?text=Product'">
                                <h3 class="product-name">{{ product.name }}</h3>

                                <div class="product-stats">
                                    <span class="rating">
                                        <i class="fas fa-star text-warning"></i> {{ product.average_rating || 5 }}
                                    </span>
                                    <span class="sold-count ms-2" style="font-size: 0.8em; color: #888;">
                                        (Đã bán: {{ product.sold_count || 0 }})
                                    </span>
                                </div>

                                <div class="product-price">
                                    <span class="new-price">{{ formatCurrency(getMinPrice(product.variants)) }}</span>
                                </div>
                                <div class="card-actions-small">
                                    <button class="btn-view"
                                        @click="$router.push({ path: `/products/${product.id}` })">
                                        <i class="fas fa-eye"></i> Xem
                                    </button>
                                    <button class="btn-add-cart" @click="onAddToCart(product)">
                                        <i class="fas fa-cart-plus"></i> Thêm
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </template>

                <section class="product-group news-group" style="margin-top: 60px;" v-if="newsList.length > 0">
                    <h2 class="section-title"><i class="far fa-newspaper me-2"></i> Tin tức công nghệ</h2>
                    <div class="news-grid">
                        <div class="news-card" v-for="news in newsList" :key="news.id">
                            <!-- FIX: Dùng getImageUrl cho Tin tức -->
                            <img :src="getImageUrl(news.image_url || news.image)" :alt="news.title" @error="$event.target.src='https://placehold.co/300x200?text=News'">
                            <h3 class="news-title">{{ news.title }}</h3>
                            <p class="news-excerpt">
                                {{ (news.content || news.excerpt || '').substring(0, 100) + '...' }}
                            </p>
                            <router-link to="/tin-tuc" class="read-more">Đọc thêm <i class="fas fa-arrow-right"></i></router-link>
                        </div>
                    </div>
                </section>

            </section>
        </main>
    </div>
</template>

<style scoped>
/* === CẤU HÌNH CSS CHUNG === */
:root {
    --primary-color: #dc3545;
    --secondary-color: #f8f9fa;
    --text-color: #333;
    --border-radius: 8px;
    --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    --transition-speed: 0.3s;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Roboto', Arial, sans-serif; /* Thêm font Roboto nếu có */
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

.container {
    max-width: 1280px;
    margin: 20px auto;
    padding: 0 20px;
}

.top-section-layout {
    display: grid;
    grid-template-columns: 260px 1fr; /* Bỏ cột phải thừa */
    gap: 25px;
    align-items: stretch; /* Kéo dãn chiều cao bằng nhau */
}

/* === SIDEBAR DANH MỤC (ĐÃ NÂNG CẤP) === */
.categories-sidebar {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    padding: 15px 10px;
    height: 100%;
    max-height: 480px; /* Giới hạn chiều cao */
    overflow-y: auto;
}

.sidebar-title {
    font-size: 1.1em;
    font-weight: 700;
    padding: 0 10px 10px;
    margin-bottom: 5px;
    border-bottom: 2px solid #f1f1f1;
    color: #2c3e50;
}

.category-item-sodo {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-weight: 500;
    color: #555;
    margin-bottom: 4px;
}

.category-item-sodo:hover,
.category-item-sodo.active {
    background-color: #f0f9ff; /* Màu nền hover nhẹ nhàng hơn */
    color: #009981;
    transform: translateX(5px); /* Hiệu ứng trượt nhẹ */
}

.category-item-sodo .icon {
    width: 35px; /* Tăng kích thước vùng icon */
    text-align: center;
    margin-right: 12px;
    font-size: 1.1em;
    display: flex;
    align-items: center;
    justify-content: center;
}

.category-item-sodo .cat-name {
    flex-grow: 1;
}

.category-item-sodo .arrow-icon {
    font-size: 0.8em;
    color: #ccc;
    opacity: 0;
    transition: opacity 0.2s;
}

.category-item-sodo:hover .arrow-icon {
    opacity: 1;
}

/* === SLIDER === */
.slider {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    height: 100%;
    min-height: 380px; /* Tăng chiều cao tối thiểu */
    box-shadow: var(--box-shadow);
}

.slider-wrapper {
    display: flex;
    height: 100%;
    transition: transform 0.5s ease-in-out;
}

.slide {
    min-width: 100%;
    background-size: cover;
    background-position: center;
}

.slider-control {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.8); /* Nền trắng mờ đẹp hơn */
    color: #333;
    border: none;
    cursor: pointer;
    font-size: 18px;
    border-radius: 50%;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.2s;
    opacity: 0; /* Ẩn mặc định */
}

.slider:hover .slider-control {
    opacity: 1; /* Hiện khi hover slider */
}

.slider-control:hover {
    background: #fff;
    color: var(--primary-color);
}

.slider-control.prev { left: 20px; }
.slider-control.next { right: 20px; }

.slider-nav {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
}

.slider-nav-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid rgba(0,0,0,0.1);
}

.slider-nav-dot.active {
    background: #fff;
    width: 30px;
    border-radius: 6px;
}

/* === TRUST BLOCK === */
.trust-block {
    display: flex;
    justify-content: space-between;
    background: #fff;
    padding: 25px 40px;
    margin: 30px 0;
    border-radius: 12px;
    box-shadow: var(--box-shadow);
    font-weight: 600;
    color: #555;
}

.trust-item {
    display: flex;
    align-items: center;
    font-size: 0.95em;
}

/* === PRODUCTS SECTION === */
.product-section-container {
    margin-top: 40px;
}

.product-group {
    margin-bottom: 60px;
}

.section-title {
    font-size: 1.6em;
    color: #2c3e50;
    font-weight: 800;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    border-left: 5px solid var(--primary-color);
    padding-left: 15px;
    text-transform: uppercase;
}

.product-grid,
.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 20px;
}

/* === PRODUCT CARD === */
.product-card,
.news-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
    padding: 15px;
    text-align: left;
    position: relative;
    border: 1px solid #f0f0f0;
    display: flex;
    flex-direction: column;
}

.product-card:hover,
.news-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
    border-color: #e0e0e0;
}

.product-card img {
    width: 100%;
    height: 180px;
    object-fit: contain; /* Giữ tỉ lệ ảnh sản phẩm */
    margin-bottom: 15px;
    transition: transform 0.3s;
}

.product-card:hover img {
    transform: scale(1.05);
}

.product-name {
    font-size: 1em;
    min-height: 44px; /* Đủ cho 2 dòng text */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 8px;
    color: #333;
    font-weight: 600;
    line-height: 1.4;
}

.product-price {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.product-price .new-price {
    color: #d70018; /* Màu đỏ giá */
    font-size: 1.2em;
    font-weight: 800;
}

.product-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    font-size: 0.85em;
    color: #777;
    margin-bottom: 12px;
}

.card-actions-small {
    display: flex;
    gap: 8px;
    margin-top: auto; /* Đẩy nút xuống đáy */
}

.card-actions-small button {
    border: none;
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9em;
    cursor: pointer;
    color: #fff;
    transition: 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.btn-view {
    background: #f3f4f6;
    color: #333 !important;
}

.btn-view:hover {
    background: #e5e7eb;
}

.btn-add-cart {
    background: #009981; /* Màu xanh chủ đạo */
}

.btn-add-cart:hover {
    background: #007f6b;
    transform: translateY(-2px);
}

/* === NEWS CARD === */
.news-card img {
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
}

.news-title {
    font-size: 1.05em;
    margin: 15px 0 8px;
    font-weight: 700;
    line-height: 1.4;
    height: 44px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.news-excerpt {
    color: #666;
    font-size: 0.9em;
    margin-bottom: 15px;
    height: 60px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
}

.read-more {
    display: block;
    text-align: right;
    color: var(--primary-color);
    font-weight: 600;
    font-size: 0.9em;
}
.read-more:hover {
    text-decoration: underline;
}

/* === RESPONSIVE === */
@media (max-width: 1200px) {
    .top-section-layout {
        grid-template-columns: 220px 1fr;
    }
}

@media (max-width: 992px) {
    .top-section-layout {
        grid-template-columns: 1fr; /* Dạng cột trên tablet dọc */
    }
    .categories-sidebar {
        display: none; /* Ẩn danh mục trên mobile/tablet nhỏ */
    }
    .slider {
        min-height: auto;
        height: 300px;
    }
}

@media (max-width: 768px) {
    .slider {
        height: 200px;
    }
    .trust-block {
        flex-wrap: wrap;
        gap: 15px;
        padding: 15px;
    }
    .trust-item {
        width: 48%;
        font-size: 0.8em;
    }
    .section-title {
        font-size: 1.3em;
    }
}
</style>