<template>
    <div id="app">
        <main class="container">

            <section class="top-section-layout">
                <nav class="categories-sidebar">
                    <h3 class="sidebar-title">Danh mục</h3>
                    <router-link 
                        v-for="category in categories" 
                        :key="category.id" 
                        :to="{ path: '/Shop', query: { categoryId: category.id } }"
                        class="category-item-sodo" 
                        :class="{ active: String(category.id) === String(activeCategoryId) }"
                        @click="setActiveCategory(category.id)"
                    >
                        <span v-html="category.icon" class="icon"></span>
                        <span>{{ category.name }}</span>
                    </router-link>
                    </nav>

                <section class="slider" @mouseenter="stopAutoSlide" @mouseleave="startAutoSlide">
                    <div class="slider-wrapper" :style="{ transform: 'translateX(-' + currentSlide * 100 + '%)' }">
                        <div class="slide" v-for="slide in slides" :key="slide.id"
                            :style="{ backgroundImage: 'url(' + slide.imageUrl + ')' }">
                            <a :href="slide.link || '#'" style="display: block; width: 100%; height: 100%;" aria-label="Xem chi tiết"></a>
                        </div>
                    </div>

                    <button class="slider-control prev" @click="prevSlide"><i class="fas fa-chevron-left"></i></button>
                    <button class="slider-control next" @click="nextSlide"><i class="fas fa-chevron-right"></i></button>

                    <div class="slider-nav">
                        <span v-for="(slide, index) in slides" :key="slide.id" class="slider-nav-dot"
                            :class="{ active: index === currentSlide }" @click="goToSlide(index)"></span>
                    </div>
                </section>

                <aside class="utility-sidebar">
                    <div class="user-info-card" v-if="users.length">
                        <p class="user-name">{{ users[0].username }}</p>
                        <p class="user-tier">⭐ {{ getUserRoleLabel(users[0].role) }}</p>
                    </div>
                </aside>
            </section>

            <section class="brand-banner" style="margin-top: 15px;">
                <router-link to="/khuyen-mai">
                    <img src="#" alt="Brand Banner"
                        style="width: 100%; height: 200px; background: #eee; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #aaa; font-style: italic;">
                </router-link>
            </section>
            
            <section class="trust-block">
                <div class="trust-item"><span>✔️ Bảo hành chính hãng</span></div>
                <div class="trust-item"><span>🚚 Giao hàng miễn phí</span></div>
                <div class="trust-item"><span>🔄 Đổi trả 30 ngày</span></div>
                <div class="trust-item"><span>🏪 Hơn 100+ cửa hàng</span></div>
            </section>

            <section class="product-section-container">

                <section class="product-group hot-products">
                    <h2 class="section-title">❤️ Sản phẩm được yêu thích nhất</h2>
                    <div class="product-grid">
                        <div class="product-card" v-for="product in topFavoriteProducts" :key="product.id">
                            <img :src="product.image_url || '#'" :alt="product.name">
                            <h3 class="product-name">{{ product.name }}</h3>

                            <div class="product-stats">
                                <span class="rating">
                                    <i class="fas fa-star text-warning"></i> {{ product.average_rating || 0 }}
                                </span>
                                <span class="favorite-count ms-3">
                                    <i class="fas fa-heart text-danger"></i> {{ product.favorite_count || 0 }}
                                </span>
                            </div>

                            <div class="product-price">
                                <span class="new-price">{{ formatCurrency(getMinPrice(product.variants)) }}</span>
                            </div>
                            <div class="card-actions-small">
                             <button class="btn-view" @click="$router.push({ name: 'ProductDetail', params: { id: product.id } })">
  <i class="fas fa-eye"></i> Xem
</button>

                                <button class="btn-add-cart" @click="addToCart(product)"><i class="fas fa-plus"></i>
                                    Thêm</button>
                            </div>
                        </div>
                    </div>
                </section>

                <template v-for="category in categoriesWithProducts" :key="category.id">
                    <section class="product-group category-group" :id="'cat-' + category.id">
                        <h2 class="section-title">
                            <span v-html="category.icon" style="margin-right: 10px;"></span>
                            {{ category.name }} nổi bật
                        </h2>

                        <div class="product-grid">
                            <div class="product-card" v-for="product in category.products" :key="product.id">
                                <img :src="product.image_url || '#'" :alt="product.name">
                                <h3 class="product-name">{{ product.name }}</h3>

                                <div class="product-stats">
                                    <span class="rating">
                                        <i class="fas fa-star text-warning"></i> {{ product.average_rating || 0 }}
                                    </span>
                                    <span class="sold-count ms-2" style="font-size: 0.8em; color: #888;">
                                        (Đã bán: {{ product.sold_count || 0 }})
                                    </span>
                                </div>

                                <div class="product-price">
                                    <span class="new-price">{{ formatCurrency(getMinPrice(product.variants)) }}</span>
                                </div>
                                <div class="card-actions-small">
                                   <button
  class="btn-view"
  @click="$router.push({ name: 'ProductDetail', params: { id: product.id } })"
>
  <i class="fas fa-eye"></i> Xem
</button>
                                    <button class="btn-add-cart" @click="addToCart(product)"><i class="fas fa-plus"></i>
                                        Thêm</button>
                                </div>
                            </div>
                        </div>
                    </section>
                </template>

                <section class="product-group news-group" style="margin-top: 60px;">
                    <h2 class="section-title">📰 Tin tức công nghệ</h2>
                    <div class="news-grid">
                        <div class="news-card" v-for="news in newsList" :key="news.id">
                            <img :src="news.image_url || news.image || '#'" :alt="news.title">
                            <h3 class="news-title">{{ news.title }}</h3>
                            <p class="news-excerpt">
                                {{ (news.content || news.excerpt || '').substring(0, 120) + '...' }}
                            </p>
                            <router-link to="/tin-tuc" class="read-more">Đọc thêm</router-link>
                        </div>
                    </div>
                </section>

            </section> </main>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
// SỬA ĐỔI: Thêm import Vue Router để sử dụng trong script (nếu cần logic điều hướng phức tạp)
import { useRouter } from 'vue-router'; 

const API_URL = 'http://localhost:3000';
// Khởi tạo router
const router = useRouter();

// State
const categories = ref([]);
const slides = ref([]);
const products = ref([]);
const users = ref([]);
const newsList = ref([]);
const roles = ref([]);
const activeCategoryId = ref(null); // Giữ lại để quản lý trạng thái active CSS
const currentSlide = ref(0);
let interval = null;

// ... (Các hàm fetchData, computed properties, slider logic giữ nguyên) ...

// --- FETCH DATA ---
const fetchData = async () => {
    try {
        const [catRes, slideRes, prodRes, userRes, newsRes, rolesRes] = await Promise.all([
            axios.get(`${API_URL}/categories?_sort=order&_order=asc&status=active`),
            axios.get(`${API_URL}/slides`),
            axios.get(`${API_URL}/products`),
            // SỬA ĐỔI: Thay đổi endpoint từ 'account_admin' thành 'users?role_ne=user'
            axios.get(`${API_URL}/users?role_ne=user`),
            axios.get(`${API_URL}/news`),
            axios.get(`${API_URL}/roles`)
        ]);

        categories.value = catRes.data;
        slides.value = slideRes.data;
        products.value = prodRes.data;
        users.value = userRes.data;
        newsList.value = newsRes.data;
        roles.value = rolesRes.data;

    } catch (err) {
        console.error("Lỗi tải dữ liệu:", err);
    }
};

// --- COMPUTED PROPERTIES ---
const topFavoriteProducts = computed(() => {
    return [...products.value]
        .sort((a, b) => (b.favorite_count || 0) - (a.favorite_count || 0))
        .slice(0, 8);
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


// --- HELPER FUNCTIONS ---

// SỬA ĐỔI: Chỉ giữ lại việc gán activeCategoryId để CSS vẫn hoạt động nếu cần.
// Việc điều hướng đã được xử lý bởi <router-link>
const setActiveCategory = (id) => { activeCategoryId.value = String(id); }; 

const getUserRoleLabel = (roleValue) => {
    if (!roles.value.length) return roleValue || 'Khách';
    const role = roles.value.find(r => r.value === roleValue);
    return role ? role.label : 'Khách';
};

const getMinPrice = (variants) => {
    if (!variants || !variants.length) return 0;
    return Math.min(...variants.map(v => v.price));
};
const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

// --- ACTIONS ---
const openQuickView = (product) => { alert(`Xem nhanh: ${product.name}`); };
const addToCart = (product) => { alert(`Đã thêm vào giỏ: ${product.name}`); };


// --- LIFECYCLE HOOKS ---
onMounted(async () => {
    await fetchData();
    startAutoSlide();
});
onBeforeUnmount(stopAutoSlide);
</script>

<style scoped>
:root {
    --primary-color: #dc3545;
    --secondary-color: #f8f9fa;
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

.container {
    max-width: 1280px;
    margin: 20px auto;
    padding: 0 20px;
}

.top-section-layout {
    display: grid;
    grid-template-columns: 250px 1fr 280px;
    gap: 20px;
    align-items: start;
}

/* Sidebar */
.categories-sidebar {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    padding: 10px;
    max-height: 450px;
    overflow-y: auto;
}

.category-item-sodo {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.2s;
    font-weight: 500;
    color: #555;
}

.category-item-sodo:hover,
.category-item-sodo.active {
    background-color: #ffebeb;
    color: var(--primary-color);
}

.category-item-sodo .icon {
    width: 30px;
    text-align: center;
    margin-right: 10px;
    color: #999;
}

.category-item-sodo:hover .icon,
.category-item-sodo.active .icon {
    color: var(--primary-color);
}

/* Slider & Utility */
.slider {
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    height: 100%;
    min-height: 350px;
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
    background: rgba(0, 0, 0, 0.3);
    color: #fff;
    border: none;
    padding: 10px 15px;
    cursor: pointer;
    font-size: 20px;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.slider-control:hover {
    background: rgba(0, 0, 0, 0.6);
}

.slider-control.prev {
    left: 15px;
}

.slider-control.next {
    right: 15px;
}

.slider-nav {
    position: absolute;
    bottom: 15px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
}

.slider-nav-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: all 0.3s;
}

.slider-nav-dot.active {
    background: var(--primary-color);
    width: 30px;
    border-radius: 5px;
}

.utility-sidebar {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    text-align: center;
}

.user-name {
    font-weight: bold;
    color: var(--primary-color);
    font-size: 1.2em;
}

/* Trust block & Banner */
.trust-block {
    display: flex;
    justify-content: space-around;
    background: #fff;
    padding: 20px;
    margin: 30px 0;
    border-radius: 12px;
    box-shadow: var(--box-shadow);
    font-weight: 600;
    color: #555;
}

/* Products Section Container */
.product-section-container {
    margin-top: 40px;
}

.product-group {
    margin-bottom: 50px;
}

.section-title {
    font-size: 1.6em;
    color: #333;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.product-grid,
.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}

/* Product Card */
.product-card,
.news-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
    padding: 15px;
    text-align: left;
    position: relative;
}

.product-card:hover,
.news-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
}

.product-card img {
    width: 100%;
    height: 200px;
    object-fit: contain;
    margin-bottom: 15px;
}

.product-name {
    font-size: 1em;
    min-height: 45px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 10px;
    color: #333;
}

.product-price {
    display: flex;
    justify-content: flex-start;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.product-price .new-price {
    color: var(--primary-color);
    font-size: 1.2em;
    font-weight: bold;
}

.product-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    font-size: 0.9em;
    color: #777;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.product-stats .rating,
.product-stats .favorite-count,
.product-stats .sold-count {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Action Buttons - Centered as requested */
.card-actions-small {
    display: flex;
    gap: 10px;
    justify-content: center;
    /* <-- ĐÃ SỬA LẠI CĂN GIỮA */
    margin-top: 15px;
    
}

.card-actions-small button {
    border: none;
    width: 100%;
    padding: 8px 15px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9em;
    cursor: pointer;
    color: #fff;
    transition: 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-view {
    background: #6c757d;
    justify-content: center;
}

.btn-view:hover {
    background: #5a6268;
}

.btn-add-cart {
    background: var(--primary-color);
    justify-content: center;
}

.btn-add-cart:hover {
    background: #c82333;
}

/* News specific */
.news-card img {
    height: 160px;
    object-fit: cover;
    border-radius: 8px;
}

.news-title {
    font-size: 1.1em;
    margin: 15px 0 10px;
    text-align: left;
    min-height: auto;
}

.news-excerpt {
    color: #666;
    font-size: 0.9em;
    text-align: left;
    margin-bottom: 15px;
    min-height: 65px;
}

.read-more {
    display: block;
    text-align: right;
    color: var(--primary-color);
    font-weight: 600;
}

/* Responsive */
@media (max-width: 1200px) {
    .top-section-layout {
        grid-template-columns: 220px 1fr;
    }

    .utility-sidebar {
        display: none;
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
        height: 250px;
        min-height: auto;
        margin-bottom: 20px;
    }

    .trust-block {
        flex-wrap: wrap;
        gap: 10px;
    }

    .trust-item {
        width: 45%;
        font-size: 0.9em;
    }
}
</style>