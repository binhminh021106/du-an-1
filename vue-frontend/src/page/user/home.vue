<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import apiService from '../../apiService.js';
import { useRouter } from 'vue-router';
import { addToCart } from "./cartStore.js"; 

// Khởi tạo router
const router = useRouter();

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

// Ref cho carousel sản phẩm mới
const productContainer = ref(null);

// Fake dữ liệu Voucher
const vouchers = ref([
    { id: 1, code: 'GIAM10K', desc: 'Giảm 10k đơn 0đ', percent: 10 },
    { id: 2, code: 'FREESHIP', desc: 'Miễn phí vận chuyển', percent: 100 },
    { id: 3, code: 'SALE50', desc: 'Giảm 50% tối đa 50k', percent: 50 },
]);

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

        categories.value = catRes.data;
        slides.value = slideRes.data;
        products.value = prodRes.data;
        users.value = userRes.data;
        newsList.value = newsRes.data;
        brands.value = brandRes.data;

    } catch (err) {
        console.error("Lỗi tải dữ liệu:", err);
    }
};

// --- COMPUTED PROPERTIES ---
const newProducts = computed(() => {
    // Lấy 15 sản phẩm để slide cho thoải mái
    return [...products.value].slice(0, 15);
});

const categoriesWithProducts = computed(() => {
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


// --- PRODUCT CAROUSEL LOGIC ---
const scrollProducts = (direction) => {
    if (!productContainer.value) return;
    // Tính toán scroll 1 lần 5 sản phẩm (khoảng 20% width container * 5)
    // Hoặc scroll khoảng 80% container width để người dùng thấy sản phẩm tiếp theo
    const containerWidth = productContainer.value.clientWidth;
    const scrollAmount = containerWidth * 0.8; 
    
    if (direction === 'left') {
        productContainer.value.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        productContainer.value.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
};


// --- HELPER FUNCTIONS ---
const setActiveCategory = (id) => { activeCategoryId.value = String(id); };

const getMinPrice = (variants) => {
    if (!variants || !variants.length) return 0;
    return Math.min(...variants.map(v => v.price));
};
const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

// --- ACTIONS ---
const onAddToWishlist = (product) => {
    alert(`Đã thêm ${product.name} vào danh sách yêu thích! ❤️`);
};

const saveVoucher = (code) => {
    alert(`Đã lưu mã giảm giá: ${code}`);
}


// --- LIFECYCLE HOOKS ---
onMounted(async () => {
    await fetchData();
    startAutoSlide();
});
onBeforeUnmount(stopAutoSlide);
</script>


<template>
    <div id="app">
        <!-- Import Font Montserrat -->
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <main class="container">

            <!-- TOP SECTION: Sidebar - Slider - Voucher -->
            <section class="top-section-layout">
                
                <!-- 1. Categories -->
                <nav class="categories-sidebar">
                    <h3 class="sidebar-title">DANH MỤC</h3>
                    <div class="category-list">
                        <router-link v-for="category in categories" :key="category.id"
                            :to="{ path: '/Shop', query: { categoryId: category.id } }" 
                            class="category-item-clean"
                            :class="{ active: String(category.id) === String(activeCategoryId) }"
                            @click="setActiveCategory(category.id)">
                            <span>{{ category.name }}</span>
                        </router-link>
                    </div>
                </nav>

                <!-- 2. Slider (FIXED: Overflow hidden + Width 100%) -->
                <section class="slider" @mouseenter="stopAutoSlide" @mouseleave="startAutoSlide">
                    <div class="slider-wrapper" :style="{ transform: 'translateX(-' + currentSlide * 100 + '%)' }">
                        <div class="slide" v-for="slide in slides" :key="slide.id">
                            <img :src="slide.imageUrl || slide.image_url" alt="Slide">
                            <a :href="slide.linkUrl || '#'" class="slide-link-overlay"></a>
                        </div>
                    </div>
                    <button class="slider-control prev" @click="prevSlide"><i class="fas fa-chevron-left"></i></button>
                    <button class="slider-control next" @click="nextSlide"><i class="fas fa-chevron-right"></i></button>
                    <div class="slider-nav">
                        <span v-for="(slide, index) in slides" :key="slide.id" class="slider-nav-dot"
                            :class="{ active: index === currentSlide }" @click="goToSlide(index)"></span>
                    </div>
                </section>

                <!-- 3. Voucher Storage -->
                <section class="voucher-sidebar">
                    <h3 class="sidebar-title">MÃ GIẢM GIÁ</h3>
                    <div class="voucher-list">
                        <div class="voucher-card" v-for="v in vouchers" :key="v.id">
                            <div class="voucher-info">
                                <span class="voucher-code">{{ v.code }}</span>
                                <span class="voucher-desc">{{ v.desc }}</span>
                            </div>
                            <button class="btn-save" @click="saveVoucher(v.code)">Lưu</button>
                        </div>
                    </div>
                </section>

            </section>

            <!-- Brand Banner (FIXED: Height Reduced) -->
            <section class="brand-banner" v-if="brands.length > 0">
                <a :href="brands[0].linkUrl || '#'">
                    <img :src="brands[0].imageUrl" alt="Brand Banner">
                </a>
            </section>

            <!-- Trust Block -->
            <section class="trust-block">
                <div class="trust-item"><i class="fas fa-check-circle"></i> Chính hãng</div>
                <div class="trust-item"><i class="fas fa-truck"></i> FreeShip</div>
                <div class="trust-item"><i class="fas fa-sync-alt"></i> Đổi trả 30d</div>
                <div class="trust-item"><i class="fas fa-headset"></i> Hỗ trợ 24/7</div>
            </section>

            <section class="product-section-container">

                <!-- SẢN PHẨM MỚI (CAROUSEL 5 ITEM) -->
                <section class="product-group hot-products">
                    <div class="section-header">
                        <h2 class="section-title">SẢN PHẨM MỚI</h2>
                        <div class="carousel-nav">
                            <button @click="scrollProducts('left')"><i class="fas fa-chevron-left"></i></button>
                            <button @click="scrollProducts('right')"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    
                    <div class="product-carousel-wrapper" ref="productContainer">
                        <!-- QUAY LẠI THẺ BASIC -->
                        <div class="product-card-basic" v-for="product in newProducts" :key="product.id">
                            <div class="image-wrapper">
                                <img :src="product.image_url || '#'" :alt="product.name">
                                <!-- Hover Actions -->
                                <div class="hover-overlay">
                                    <button class="action-btn view" title="Xem chi tiết" 
                                        @click="$router.push({ name: 'ProductDetail', params: { id: product.id } })">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn heart" title="Yêu thích" 
                                        @click="onAddToWishlist(product)">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </div>
                                <div class="badge-new">New</div>
                            </div>
                            
                            <div class="product-info">
                                <h3 class="product-name">{{ product.name }}</h3>
                                <div class="price-row">
                                    <span class="price">{{ formatCurrency(getMinPrice(product.variants)) }}</span>
                                    <span class="sold">Đã bán {{ product.sold_count || 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Category Products (Grid Layout) -->
                <template v-for="category in categoriesWithProducts" :key="category.id">
                    <section class="product-group" :id="'cat-' + category.id">
                        <h2 class="section-title text-uppercase">{{ category.name }}</h2>
                        <div class="product-grid">
                            <!-- QUAY LẠI THẺ BASIC -->
                            <div class="product-card-basic" v-for="product in category.products" :key="product.id">
                                <div class="image-wrapper">
                                    <img :src="product.image_url || '#'" :alt="product.name">
                                    <div class="hover-overlay">
                                        <button class="action-btn view" 
                                            @click="$router.push({ name: 'ProductDetail', params: { id: product.id } })">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn heart" 
                                            @click="onAddToWishlist(product)">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name">{{ product.name }}</h3>
                                    <div class="price-row">
                                        <span class="price">{{ formatCurrency(getMinPrice(product.variants)) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </template>

                <!-- News -->
                <section class="product-group news-group">
                    <h2 class="section-title">TIN TỨC CÔNG NGHỆ</h2>
                    <div class="news-grid">
                        <div class="news-card-basic" v-for="news in newsList" :key="news.id">
                            <div class="news-img-wrap">
                                <img :src="news.image_url || news.image || '#'" :alt="news.title">
                            </div>
                            <div class="news-content">
                                <h3 class="news-title">{{ news.title }}</h3>
                                <router-link to="/tin-tuc" class="read-more-link">Xem thêm &rarr;</router-link>
                            </div>
                        </div>
                    </div>
                </section>

            </section>
        </main>
    </div>
</template>

<style scoped>
:root {
    --primary-color: #d70018; 
    --text-color: #333;
    --border-color: #e5e7eb;
}

* {
    box-sizing: border-box;
    font-family: 'Montserrat', sans-serif;
}

.text-uppercase { text-transform: uppercase; }
.text-warning { color: #f59e0b; }

.container {
    max-width: 1280px;
    margin: 20px auto;
    padding: 0 15px;
}

/* --- TOP LAYOUT --- */
.top-section-layout {
    display: grid;
    grid-template-columns: 240px 1fr 260px; 
    gap: 15px;
    align-items: stretch;
    margin-bottom: 20px;
}

/* Sidebar Danh mục */
.categories-sidebar {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 2px 0 rgba(60,64,67,.1), 0 2px 6px 2px rgba(60,64,67,.15);
    padding: 10px;
    height: 100%; 
}

.sidebar-title {
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 10px;
    padding-left: 10px;
    color: #444;
}

.category-item-clean {
    display: block;
    padding: 8px 12px;
    color: #333;
    font-size: 13px;
    font-weight: 500;
    border-radius: 5px;
    transition: 0.2s;
}

.category-item-clean:hover {
    background: #f3f4f6;
    font-weight: 600;
}

/* --- FIX: SLIDER (QUAN TRỌNG) --- */
.slider {
    position: relative;
    overflow: hidden; /* Che phần hình ảnh thừa */
    border-radius: 8px;
    box-shadow: 0 1px 2px 0 rgba(60,64,67,.1);
    /* Set width 100% để chắc chắn nó ko bị co */
    width: 100%;
    /* Min height để tránh giật layout */
    min-height: 300px;
}

.slider-wrapper {
    display: flex;
    height: 100%;
    transition: transform 0.5s ease-in-out;
    width: 100%; /* Wrapper full width */
}

.slide {
    /* FIX: Bắt buộc mỗi slide phải rộng 100% container */
    min-width: 100%; 
    width: 100%;
    flex: 0 0 100%;
    position: relative;
}

.slide img {
    width: 100%;
    height: 100%;
    object-fit: cover; /* Đảm bảo ảnh full khung */
    display: block;
}

.slide-link-overlay {
    position: absolute;
    top:0; left:0; width:100%; height:100%;
    z-index: 2;
}

.slider-control {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.3);
    color: #fff;
    border: none;
    width: 40px; height: 40px;
    border-radius: 50%;
    cursor: pointer;
    transition: 0.3s;
    z-index: 3;
    display: flex; align-items: center; justify-content: center;
}
.slider-control:hover { background: rgba(0,0,0,0.4); }
.prev { left: 10px; }
.next { right: 10px; }

.slider-nav {
    position: absolute;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 3;
}
.slider-nav-dot {
    width: 8px; height: 8px;
    background: rgba(255,255,255,0.5);
    border-radius: 50%;
    cursor: pointer;
}
.slider-nav-dot.active { background: #fff; }

/* Voucher Sidebar */
.voucher-sidebar {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 1px 2px 0 rgba(60,64,67,.1);
    height: 100%;
    overflow: hidden;
}

.voucher-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.voucher-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    border: 1px dashed var(--primary-color);
    padding: 10px;
    border-radius: 6px;
}

.voucher-code {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 12px;
}
.voucher-desc { font-size: 10px; color: #666; display: block; }
.btn-save {
    background: var(--primary-color);
    color: #fff;
    border: none;
    padding: 4px 10px;
    font-size: 10px;
    border-radius: 4px;
    cursor: pointer;
}

/* --- FIX: BRAND BANNER HEIGHT (Giảm chiều cao) --- */
.brand-banner {
    margin-bottom: 20px;
}
.brand-banner img {
    width: 100%;
    height: 120px; /* Đã giảm từ 200px xuống 120px cho gọn */
    object-fit: cover;
    border-radius: 10px;
    display: block;
}

/* Trust Block */
.trust-block {
    display: flex;
    justify-content: space-between;
    padding: 15px 30px;
    margin-bottom: 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.trust-item { font-size: 12px; font-weight: 600; color: #555; display: flex; align-items: center; gap: 5px; }


/* --- PRODUCT SECTION --- */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #333;
    text-transform: uppercase;
}

/* Carousel Buttons */
.carousel-nav button {
    background: #fff;
    border: 1px solid #ddd;
    width: 30px; height: 30px;
    border-radius: 50%;
    cursor: pointer;
    margin-left: 5px;
    color: #555;
    transition: 0.2s;
}
.carousel-nav button:hover { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }

/* CAROUSEL CONTAINER (5 ITEMS) */
.product-carousel-wrapper {
    display: flex;
    overflow-x: auto;
    scroll-behavior: smooth;
    gap: 15px; /* Khoảng cách giữa các thẻ */
    padding-bottom: 10px;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.product-carousel-wrapper::-webkit-scrollbar { display: none; }


/* --- THẺ SẢN PHẨM BASIC (KHÔI PHỤC LẠI) --- */
.product-card-basic {
    background: transparent;
    transition: 0.3s ease;
    
    /* Config cho Carousel (5 Items) */
    /* 100% / 5 = 20%, trừ đi gap */
    flex: 0 0 calc(20% - 12px); 
    min-width: 200px;
}

/* Config cho Grid (Ghi đè lại flex của Carousel) */
.product-grid .product-card-basic {
    flex: unset;
    min-width: unset;
    width: 100%;
}
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.image-wrapper {
    position: relative;
    width: 100%;
    padding-top: 100%; /* Vuông */
    background: #fff;
    overflow: hidden;
    border-radius: 6px;
    margin-bottom: 15px;
    border: 1px solid #eee;
}

.image-wrapper img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain; /* Contain để thấy hết sp */
    padding: 10px;
    transition: transform 0.5s ease;
}

.product-card-basic:hover .image-wrapper img {
    transform: scale(1.05); 
}

/* Badge New */
.badge-new {
    position: absolute;
    top: 10px;
    left: 10px;
    background: var(--primary-color);
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 2px;
    text-transform: uppercase;
}

/* HOVER OVERLAY */
.hover-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 15px;
    display: flex;
    justify-content: center;
    gap: 10px;
    opacity: 0; 
    transform: translateY(20px);
    transition: all 0.3s ease;
    background: linear-gradient(to top, rgba(0,0,0,0.05), transparent);
}

.product-card-basic:hover .hover-overlay {
    opacity: 1;
    transform: translateY(0);
}

.action-btn {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    border: none;
    background: #fff;
    color: #333;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: 0.2s;
    font-size: 14px;
}

.action-btn:hover {
    background: var(--primary-color);
    color: #fff;
}

/* INFO */
.product-info {
    text-align: center;
}

.product-name {
    font-size: 14px;
    font-weight: 500;
    color: #333;
    margin-bottom: 8px;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.price-row {
    display: flex;
    justify-content: center;
    align-items: baseline;
    gap: 10px;
}

.price {
    color: var(--primary-color);
    font-weight: 700;
    font-size: 15px;
}

.sold {
    font-size: 11px;
    color: #999;
}


/* --- NEWS --- */
/* Cập nhật phần này để tạo khoảng cách */
.news-group {
    margin-top: 80px; /* Tạo khoảng cách xa với sản phẩm bên trên */
    padding-top: 40px; /* Thêm đệm bên trong */
    border-top: 1px solid #e5e7eb; /* Thêm đường kẻ mờ để phân tách */
}

.news-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
}

.news-card-basic {
    display: flex;
    flex-direction: column;
}

.news-img-wrap {
    width: 100%;
    height: 180px;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 12px;
}
.news-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: 0.3s;
}
.news-card-basic:hover .news-img-wrap img { transform: scale(1.05); }

.news-title {
    font-size: 15px;
    font-weight: 600;
    line-height: 1.4;
    margin-bottom: 8px;
}

.read-more-link {
    font-size: 12px;
    font-weight: 700;
    color: var(--primary-color);
}


/* RESPONSIVE */
@media (max-width: 1024px) {
    .top-section-layout { grid-template-columns: 200px 1fr; }
    .voucher-sidebar { display: none; }
    /* Tablet: hiện 3 hoặc 4 sản phẩm */
    .product-card-basic { flex: 0 0 calc(33.333% - 10px); } 
}

@media (max-width: 768px) {
    .top-section-layout { display: block; }
    .categories-sidebar { display: none; }
    .slider { height: 200px; margin-bottom: 15px;}
    /* Mobile: hiện 2 sản phẩm */
    .product-card-basic { flex: 0 0 calc(50% - 10px); } 
    
    .news-grid {
        grid-template-columns: 1fr; /* News trên mobile thành 1 cột */
    }
}
</style>