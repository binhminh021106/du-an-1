<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import apiService from '../../apiService.js';
import { useRouter } from 'vue-router';
import { addToCart } from "../../store/cartStore.js";
import Swal from 'sweetalert2'; // Import SweetAlert2

// --- CONFIG ---
const BACKEND_URL = 'http://127.0.0.1:8000';

const router = useRouter();

// --- TOAST CONFIG (Thông báo góc dưới phải) ---
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

// State
const categories = ref([]);
const brands = ref([]);
const slides = ref([]);
const products = ref([]);
const users = ref([]);
const newsList = ref([]);
const vouchers = ref([]); // Danh sách voucher từ API
const activeCategoryId = ref(null);
const currentSlide = ref(0);
let interval = null;
const productContainer = ref(null);

// --- FETCH DATA ---
const fetchData = async () => {
    try {
        // 1. Tải các dữ liệu quan trọng (Sản phẩm, Slide, Danh mục...)
        // Sử dụng .catch(e => null) cho từng API để nếu 1 cái lỗi, các cái khác vẫn chạy
        const [catRes, slideRes, prodRes, userRes, brandRes, couponRes] = await Promise.all([
            apiService.get(`/categories`).catch(() => null),
            apiService.get(`/slides`).catch(() => null),
            apiService.get(`/products`).catch(() => null),
            apiService.get(`/users`).catch(() => null),
            apiService.get(`/brands`).catch(() => null),
            apiService.get(`/coupons`).catch(() => null), 
        ]);

        // 2. Gán dữ liệu an toàn (Sử dụng Optional Chaining ?. và Default Value [])
        categories.value = catRes?.data || [];
        slides.value = slideRes?.data || [];
        products.value = prodRes?.data || [];
        users.value = userRes?.data || [];
        brands.value = brandRes?.data || [];
        
        // Map dữ liệu coupon từ DB
        if (couponRes && couponRes.data && Array.isArray(couponRes.data)) {
            vouchers.value = couponRes.data.map(c => ({
                id: c.id,
                code: c.code,
                name: c.name,
                min_spend: c.min_spend,
                value: c.value,
                desc: `Giảm ${formatCurrency(c.value)} cho đơn từ ${formatCurrency(c.min_spend)}`
            }));
        } else {
            vouchers.value = [];
        }

        // 3. Tải News riêng lẻ (Bọc trong try-catch riêng)
        // Lý do: API News đang không ổn định (lỗi 500), tách ra để không ảnh hưởng trang chủ
        try {
            const newsRes = await apiService.get(`/news`);
            newsList.value = newsRes?.data || [];
        } catch (newsError) {
            console.warn("Chưa tải được tin tức (Có thể do lỗi Backend):", newsError);
            newsList.value = []; // Gán mảng rỗng để giao diện không bị lỗi
        }

    } catch (err) {
        console.error("Lỗi nghiêm trọng khi tải trang chủ:", err);
        Toast.fire({
            icon: 'error',
            title: 'Không thể tải dữ liệu trang chủ'
        });
    }
};

// --- COMPUTED ---
const newProducts = computed(() => {
    if (!Array.isArray(products.value)) return [];
    return [...products.value].slice(0, 15);
});

const categoriesWithProducts = computed(() => {
    if (!Array.isArray(categories.value)) return [];
    return categories.value.map(category => {
        const categoryProducts = Array.isArray(products.value) 
            ? products.value.filter(p => String(p.category?.id) === String(category.id))
            : [];
        return { ...category, products: categoryProducts.slice(0, 8) };
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

const scrollProducts = (direction) => {
    if (!productContainer.value) return;
    const containerWidth = productContainer.value.clientWidth;
    const scrollAmount = containerWidth * 0.8;
    direction === 'left'
        ? productContainer.value.scrollBy({ left: -scrollAmount, behavior: 'smooth' })
        : productContainer.value.scrollBy({ left: scrollAmount, behavior: 'smooth' });
};

// --- HELPER FUNCTIONS ---
const setActiveCategory = (id) => { activeCategoryId.value = String(id); };

const getMinPrice = (variants) => {
    if (!variants || !variants.length) return 0;
    return Math.min(...variants.map(v => v.price));
};
const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

// Hàm xử lý ảnh
const getFullImage = (path) => {
    if (!path) return 'https://placehold.co/400x300?text=No+Image';
    if (path.startsWith('http') || path.startsWith('blob:')) return path;
    return `${BACKEND_URL}${path}`;
};

// Hàm cắt chữ & xóa HTML (Dùng cho mô tả ngắn)
const getExcerpt = (htmlContent, maxLength = 120) => {
    if (!htmlContent) return '';
    const tmp = document.createElement("DIV");
    tmp.innerHTML = htmlContent;
    let plainText = tmp.textContent || tmp.innerText || "";
    if (plainText.length > maxLength) {
        return plainText.substring(0, maxLength) + '...';
    }
    return plainText;
};

// --- ACTIONS ---
const onAddToCart = (product) => {
    const minPrice = getMinPrice(product.variants);
    const variant = product.variants
        ? product.variants.find(v => v.price === minPrice) || product.variants[0]
        : { id: 'default', name: 'Mặc định', price: minPrice || 0, stock: 999 };
    addToCart(product, variant, 1);
    
    // Sử dụng Toast cho thông báo thêm giỏ hàng thành công
    Toast.fire({
        icon: 'success',
        title: `Đã thêm ${product.name} vào giỏ!`
    });
};

const onAddToWishlist = (product) => {
    Toast.fire({
        icon: 'success',
        title: `Đã thêm ${product.name} vào yêu thích`
    });
};

// Xử lý lưu voucher
const saveVoucher = (code) => {
    // Copy code vào clipboard
    navigator.clipboard.writeText(code).then(() => {
        // Sử dụng Toast thông báo
        Toast.fire({
            icon: 'success',
            title: `Đã sao chép mã: ${code}`
        });
    }, (err) => {
        console.error('Không thể sao chép', err);
    });
};

// --- LIFECYCLE ---
onMounted(async () => {
    await fetchData();
    startAutoSlide();
});
onBeforeUnmount(stopAutoSlide);
</script>

<template>
    <div id="app">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">

        <main class="container">
            <section class="top-section-layout">
                <nav class="categories-sidebar">
                    <h3 class="sidebar-title">DANH MỤC</h3>
                    <div class="category-list">
                        <router-link v-for="category in categories" :key="category.id"
                            :to="{ path: '/Shop', query: { categoryId: category.id } }" class="category-item-clean text-uppercase"
                            :class="{ active: String(category.id) === String(activeCategoryId) }"
                            @click="setActiveCategory(category.id)">
                            
                            <!-- Hiển thị Icon nếu có -->
                            <span v-if="category.icon" v-html="category.icon" class="cat-icon"></span>
                            
                            <!-- Tên danh mục (Đã bỏ uppercase) -->
                            <span>{{ category.name }}</span>
                        </router-link>
                    </div>
                </nav>

                <section class="slider" @mouseenter="stopAutoSlide" @mouseleave="startAutoSlide">
                    <div class="slider-wrapper" :style="{ transform: 'translateX(-' + currentSlide * 100 + '%)' }">
                        <div class="slide" v-for="slide in slides" :key="slide.id">
                            <img :src="getFullImage(slide.imageUrl || slide.image_url)" alt="Slide">
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

                <section class="voucher-sidebar">
                    <h3 class="sidebar-title">MÃ GIẢM GIÁ</h3>
                    <div class="voucher-list">
                        <!-- Render Coupon từ API -->
                        <div class="voucher-card" v-for="v in vouchers" :key="v.id">
                            <div class="voucher-info">
                                <span class="voucher-code">{{ v.code }}</span>
                                <span class="voucher-desc" :title="v.desc">{{ v.desc }}</span>
                            </div>
                            <button class="btn-save text" @click="saveVoucher(v.code)">Lưu</button>
                        </div>
                        <div v-if="vouchers.length === 0" class="no-voucher">
                            Đang cập nhật...
                        </div>
                    </div>
                </section>
            </section>

            <section class="brand-banner" v-if="brands.length > 0">
                <a :href="brands[0].linkUrl || '#'">
                    <img :src="getFullImage(brands[0].imageUrl)" alt="Brand Banner">
                </a>
            </section>

            <!-- TRUST BLOCK: Cập nhật CSS để to rõ và đúng màu -->
            <section class="trust-block">
                <div class="trust-item"><i class="fas fa-check-circle"></i> Chính hãng 100%</div>
                <div class="trust-item"><i class="fas fa-truck"></i> Miễn phí vận chuyển</div>
                <div class="trust-item"><i class="fas fa-sync-alt"></i> Đổi trả trong 30 ngày</div>
                <div class="trust-item"><i class="fas fa-headset"></i> Hỗ trợ kỹ thuật 24/7</div>
            </section>

            <section class="product-section-container">
                <section class="product-group hot-products">
                    <div class="section-header">
                        <h2 class="section-title">SẢN PHẨM MỚI</h2>
                        <div class="carousel-nav">
                            <button @click="scrollProducts('left')"><i class="fas fa-chevron-left"></i></button>
                            <button @click="scrollProducts('right')"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>

                    <div class="product-carousel-wrapper" ref="productContainer">
                        <div class="product-card-basic" v-for="product in newProducts" :key="product.id">
                            <div class="image-wrapper">
                                <img :src="getFullImage(product.image || product.image_url)" :alt="product.name"
                                    onerror="this.src='https://placehold.co/400x300?text=Lỗi+Ảnh'">
                                <div class="hover-overlay">
                                    <button class="action-btn view"
                                        @click="$router.push({ name: 'ProductDetail', params: { id: product.id } })">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn heart" @click="onAddToWishlist(product)">
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

                <template v-for="category in categoriesWithProducts" :key="category.id">
                    <section class="product-group" :id="'cat-' + category.id">
                        <!-- Tên danh mục ở section sản phẩm vẫn giữ uppercase cho đẹp, hoặc bỏ class nếu muốn -->
                        <h2 class="section-title text-uppercase">{{ category.name }}</h2>
                        <div class="product-grid">
                            <div class="product-card-basic" v-for="product in category.products" :key="product.id">
                                <div class="image-wrapper">
                                    <img :src="getFullImage(product.image || product.image_url)" :alt="product.name"
                                        onerror="this.src='https://placehold.co/400x300?text=Lỗi+Ảnh'">
                                    <div class="hover-overlay">
                                        <button class="action-btn view"
                                            @click="$router.push({ name: 'ProductDetail', params: { id: product.id } })">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn heart" @click="onAddToWishlist(product)">
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

                <section class="product-group news-group">
                    <h2 class="section-title">TIN TỨC CÔNG NGHỆ</h2>
                    <div class="news-grid">
                        <div class="news-card-basic" v-for="news in newsList" :key="news.id">
                            <div class="news-img-wrap">
                                <img :src="getFullImage(news.image_url || news.image)" :alt="news.title"
                                    onerror="this.src='https://placehold.co/400x300?text=Lỗi+Ảnh'">
                            </div>
                            <div class="news-content">
                                <h3 class="news-title">{{ news.title }}</h3>
                                <p class="news-excerpt">
                                    {{ getExcerpt(news.excerpt || news.content, 100) }}
                                </p>
                                <router-link :to="`/blog/${news.slug}`" class="read-more-link">Xem thêm
                                    &rarr;</router-link>
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
    --primary-color: #009981; 
    --text-color: #333;
    --border-color: #e5e7eb;
}

* {
    box-sizing: border-box;
    font-family: 'Montserrat', sans-serif;
}

.text-uppercase {
    text-transform: uppercase;
}

.container {
    max-width: 1280px;
    margin: 20px auto;
    padding: 0 15px;
}

/* Layout & Sidebar */
.top-section-layout {
    display: grid;
    grid-template-columns: 240px 1fr 260px;
    gap: 15px;
    align-items: stretch;
    margin-bottom: 20px;
}

.categories-sidebar {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 2px 0 rgba(60, 64, 67, .1), 0 2px 6px 2px rgba(60, 64, 67, .15);
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

/* [UPDATED] Danh mục: Bỏ uppercase, bỏ gạch chân */
.category-item-clean {
    display: flex;
    align-items: center;
    gap: 10px; 
    padding: 10px 12px;
    color: #333;
    font-size: 13px;
    font-weight: 500;
    border-radius: 5px;
    transition: 0.2s;
    text-decoration: none; /* Đảm bảo không gạch chân */
    /* text-transform: uppercase;  <- Đã xóa dòng này */
}

/* Style cho icon danh mục */
.cat-icon {
    width: 20px;
    text-align: center;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Style hover giống Header */
.category-item-clean:hover {
    background: #DBF9EB; /* Xanh nhạt */
    color: #00483D; /* Xanh đậm */
    font-weight: 600;
}

.category-item-clean:hover .cat-icon {
    color: #00483D;
}

/* Slider */
.slider {
    position: relative;
    overflow: hidden;
    border-radius: 8px;
    box-shadow: 0 1px 2px 0 rgba(60, 64, 67, .1);
    width: 100%;
    min-height: 300px;
}

.slider-wrapper {
    display: flex;
    height: 100%;
    transition: transform 0.5s ease-in-out;
    width: 100%;
}

.slide {
    min-width: 100%;
    width: 100%;
    flex: 0 0 100%;
    position: relative;
}

.slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.slide-link-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 2;
}

.slider-control {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.3);
    color: #fff;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    transition: 0.3s;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
}

.slider-control:hover {
    background: rgba(0, 0, 0, 0.4);
}

.prev {
    left: 10px;
}

.next {
    right: 10px;
}

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
    width: 8px;
    height: 8px;
    background: rgba(255, 255, 255, 0.5);
    border-radius: 50%;
    cursor: pointer;
}

.slider-nav-dot.active {
    background: #fff;
}

/* Voucher */
.voucher-sidebar {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 1px 2px 0 rgba(60, 64, 67, .1);
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
    transition: all 0.2s;
}

.voucher-card:hover {
    background: #f9f9f9;
}

.voucher-code {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 13px;
    display: block;
}

.voucher-desc {
    font-size: 11px;
    color: #666;
    display: block;
    margin-top: 2px;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-save {
    background: var(--primary-color);
    color: #fff;
    border: none;
    padding: 5px 12px;
    font-size: 11px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
}

.btn-save:hover {
    opacity: 0.9;
}

.no-voucher {
    text-align: center;
    font-size: 12px;
    color: #999;
    padding: 20px 0;
}

/* Brand & Trust */
.brand-banner {
    margin-bottom: 20px;
}

.brand-banner img {
    width: 100%;
    height: 120px;
    object-fit: cover;
    border-radius: 10px;
    display: block;
}

/* [UPDATED] TRUST BLOCK: CSS mới để to rõ và đúng màu */
.trust-block {
    display: flex;
    justify-content: space-between;
    padding: 25px 30px; /* Tăng padding */
    margin-bottom: 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); /* Shadow rõ hơn chút */
    border-top: 3px solid var(--primary-color); /* Thêm viền trên màu chủ đạo */
}

.trust-item {
    font-size: 15px; /* Tăng size chữ từ 12 lên 15 */
    font-weight: 700; /* In đậm */
    color: var(--primary-color); /* Màu xanh chủ đạo */
    display: flex;
    align-items: center;
    gap: 12px; /* Khoảng cách icon và chữ rộng hơn */
}

.trust-item i {
    font-size: 24px; /* Icon to hơn (24px) */
    color: var(--primary-color);
}

/* Product Styles */
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

.carousel-nav button {
    background: #fff;
    border: 1px solid #ddd;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    margin-left: 5px;
    color: #555;
    transition: 0.2s;
}

.carousel-nav button:hover {
    background: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

.product-carousel-wrapper {
    display: flex;
    overflow-x: auto;
    scroll-behavior: smooth;
    gap: 15px;
    padding-bottom: 10px;
    scrollbar-width: none;
}

.product-carousel-wrapper::-webkit-scrollbar {
    display: none;
}

.product-card-basic {
    background: transparent;
    transition: 0.3s ease;
    flex: 0 0 calc(20% - 12px);
    min-width: 200px;
}

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
    padding-top: 100%;
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
    object-fit: contain;
    padding: 10px;
    transition: transform 0.5s ease;
}

.product-card-basic:hover .image-wrapper img {
    transform: scale(1.05);
}

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
    background: linear-gradient(to top, rgba(0, 0, 0, 0.05), transparent);
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
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: 0.2s;
    font-size: 14px;
}

.action-btn:hover {
    background: var(--primary-color);
    color: #fff;
}

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

/* News Styles */
.news-group {
    margin-top: 80px;
    padding-top: 40px;
    border-top: 1px solid #e5e7eb;
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

.news-card-basic:hover .news-img-wrap img {
    transform: scale(1.05);
}

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

/* CSS CHO MÔ TẢ NGẮN (Vừa thêm) */
.news-excerpt {
    font-size: 13px;
    color: #666;
    margin-bottom: 10px;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Responsive */
@media (max-width: 1024px) {
    .top-section-layout {
        grid-template-columns: 200px 1fr;
    }

    .voucher-sidebar {
        display: none;
    }

    .product-card-basic {
        flex: 0 0 calc(33.333% - 10px);
    }
}

@media (max-width: 768px) {
    .top-section-layout {
        display: block;
    }

    .categories-sidebar {
        display: none;
    }

    .slider {
        height: 200px;
        margin-bottom: 15px;
    }

    .product-card-basic {
        flex: 0 0 calc(50% - 10px);
    }

    .news-grid {
        grid-template-columns: 1fr;
    }
    
    .trust-block {
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .trust-item {
        width: 100%;
        justify-content: flex-start;
    }
}
</style>