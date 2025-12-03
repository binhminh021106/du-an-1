<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useRouter } from 'vue-router'; // Fix lỗi thiếu router
// import { useStore } from "vuex"; 
import apiService from '../../apiService.js';
import Swal from 'sweetalert2'; // Giữ lại SweetAlert đẹp của code cũ
import { toggleWishlist } from "../../store/wishlistStore.js"; 
import { addToCart } from '../../store/cartStore.js';


// const store = useStore();
const router = useRouter();

// --- CẤU HÌNH ---
const SERVER_URL = 'http://127.0.0.1:8000'; 
const CHATBOT_API_URL = 'http://localhost:3000/api/chat-search'; // Hoặc port 8000 tùy server bạn
const USE_STORAGE = false; 

// --- TOAST CONFIG (Giữ lại thông báo đẹp) ---
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


const getImageUrl = (path) => {
    if (!path) return 'https://placehold.co/400x300?text=No+Image';
    if (path.startsWith('http') || path.startsWith('data:') || path.startsWith('blob:')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`;
};

const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);


const getProductPrice = (product) => {
    if (!product) return 0;
    // Ưu tiên lấy giá nhỏ nhất từ biến thể nếu có
    if (product.variants && Array.isArray(product.variants) && product.variants.length > 0) {
        const prices = product.variants.map(v => Number(v.price)).filter(p => !isNaN(p) && p > 0);
        if (prices.length > 0) return Math.min(...prices);
    }
    // Fallback về giá gốc (nếu có)
    return Number(product.price) || 0;
};

const getExcerpt = (text, limit = 100) => {
    if (!text) return '';
    const cleanText = String(text).replace(/<[^>]*>?/gm, '');
    if (cleanText.length <= limit) return cleanText;
    return cleanText.substring(0, limit) + '...';
};

const categories = ref([]);
const brands = ref([]);
const slides = ref([]);
const products = ref([]); 
const newsList = ref([]);
const vouchers = ref([]);
const activeCategoryId = ref(null);
const currentSlide = ref(0);
let interval = null;
const productContainer = ref(null);

// --- CHATBOT STATE ---
const isChatOpen = ref(false);
const chatInput = ref("");
const isChatLoading = ref(false);
const chatMessages = ref([
    { role: 'ai', text: 'Xin chào! Bạn cần tìm sản phẩm gì?', products: [] }
]);
const chatBodyRef = ref(null);

// --- FETCH DATA (QUAN TRỌNG: Đã sửa để không bị mất dữ liệu) ---
const fetchData = async () => {
    try {
        // Sử dụng .catch(() => null) để nếu 1 API lỗi thì các API khác vẫn chạy (Tránh trắng trang)
        const [catRes, slideRes, prodRes, brandRes, couponRes, newsRes] = await Promise.all([
            apiService.get(`/categories`).catch(e => null),
            apiService.get(`/slides`).catch(e => null),
            apiService.get(`/products?include=variants`).catch(e => null), // Lấy thêm variants để tính giá đúng
            apiService.get(`/brands`).catch(e => null),
            apiService.get(`/coupons`).catch(e => null),
            apiService.get(`/news`).catch(e => null),
        ]);

        // Xử lý dữ liệu linh hoạt (chấp nhận cả data.data hoặc data)
        categories.value = catRes?.data?.data || catRes?.data || [];
        slides.value = slideRes?.data?.data || slideRes?.data || [];
        
        // FIX LỖI KHÔNG HIỆN SẢN PHẨM: Kiểm tra kỹ cấu trúc trả về
        products.value = prodRes?.data?.data || prodRes?.data || [];
        
        brands.value = brandRes?.data?.data || brandRes?.data || [];
        newsList.value = newsRes?.data?.data || newsRes?.data || [];

        const rawVouchers = couponRes?.data?.data || couponRes?.data || [];
        if (Array.isArray(rawVouchers)) {
            vouchers.value = rawVouchers.map(c => ({
                id: c.id, code: c.code, name: c.name, min_spend: c.min_spend, value: c.value,
                desc: `Giảm ${formatCurrency(c.value)} đơn ${formatCurrency(c.min_spend)}`
            }));
        } else {
            vouchers.value = [];
        }

    } catch (err) {
        console.error("Lỗi tải trang chủ:", err);
    }
};

// --- LOGIC SLIDER & NAV ---
const navigateToCategory = (id) => {
    activeCategoryId.value = String(id);
    router.push({ path: '/Shop', query: { categoryId: id } });
};

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

const scrollProducts = (direction) => {
    if (!productContainer.value) return;
    const containerWidth = productContainer.value.clientWidth;
    const scrollAmount = containerWidth * 0.8;
    direction === 'left'
        ? productContainer.value.scrollBy({ left: -scrollAmount, behavior: 'smooth' })
        : productContainer.value.scrollBy({ left: scrollAmount, behavior: 'smooth' });
};



const onAddToCart = (product, specificVariant = null) => {
 
 // 1. Nếu đã có biến thể cụ thể
 if (specificVariant) {
 // SỬA: Gọi hàm trực tiếp từ cartStore.js
 addToCart(product, specificVariant, 1);
        
 const variantName = specificVariant.name || `Mẫu ${specificVariant.id}`;
 Toast.fire({ icon: 'success', title: `Đã thêm ${product.name} (${variantName}) vào giỏ!` });
 return;
 }

    // 2. Xử lý logic khi bấm nút chung (sản phẩm không có variants)
    const currentPrice = getProductPrice(product); 

    if (currentPrice === 0 && (!product.variants || product.variants.length === 0)) {
        Toast.fire({ icon: 'info', title: 'Liên hệ để biết giá!' });
        return;
    }

    let variantToAdd;
    if (product.variants && product.variants.length > 0) {
        // Lấy variant có giá MIN
        variantToAdd = product.variants.reduce((min, v) => (Number(v.price) < Number(min.price) ? v : min), product.variants[0]);
    } else {
        // Tạo object tạm để dispatch vào giỏ hàng
        variantToAdd = { 
            id: product.id, 
            product_id: product.id, 
            name: 'Mặc định', // Tên biến thể mặc định
            price: currentPrice, 
            stock: product.stock || 100 
        };
    }
    
    // SỬA: Gọi hàm trực tiếp từ cartStore.js
    addToCart(product, variantToAdd, 1);

    Toast.fire({ icon: 'success', title: `Đã thêm ${product.name} vào giỏ!` });
};

// yêu thích
const wishlistIds = ref(new Set()); // Dùng Set để tra cứu cho nhanh

// Hàm cập nhật danh sách ID đã thích từ LocalStorage
const updateWishlistState = () => {
    try {
        const stored = JSON.parse(localStorage.getItem('wishlist') || '[]');
        // Giả sử stored là mảng object sản phẩm, ta chỉ lấy ID
        wishlistIds.value = new Set(stored.map(p => p.id));
    } catch (e) {
        wishlistIds.value = new Set();
    }
};

// Hàm kiểm tra xem sản phẩm có trong wishlist không
const isLiked = (product) => wishlistIds.value.has(product.id);


// ... giữ nguyên logic cũ ...

const onAddToWishlist = (product) => {
    const isAdded = toggleWishlist(product);
    
    // CẬP NHẬT NGAY LẬP TỨC CHO GIAO DIỆN
    if (isAdded) {
        wishlistIds.value.add(product.id); // Thêm ID vào Set
        Toast.fire({ icon: 'success', title: `Đã thích ${product.name} ❤️` });
    } else {
        wishlistIds.value.delete(product.id); // Xóa ID khỏi Set
        Toast.fire({ icon: 'info', title: `Đã bỏ thích ${product.name}` });
    }
    
    // Mẹo nhỏ: Gán lại Set mới để Vue nhận biết sự thay đổi (Reactivity)
    wishlistIds.value = new Set(wishlistIds.value);
};

// ... giữ nguyên logic cũ ...

const saveVoucher = (code) => {
    navigator.clipboard.writeText(code).then(() => {
        Toast.fire({ icon: 'success', title: `Đã copy mã: ${code}` });
    });
};

// --- CHATBOT ---
const toggleChat = () => { isChatOpen.value = !isChatOpen.value; if (isChatOpen.value) scrollToBottom(); };
const scrollToBottom = () => { nextTick(() => { if (chatBodyRef.value) chatBodyRef.value.scrollTop = chatBodyRef.value.scrollHeight; }); };

const sendChatMessage = async () => {
    if (!chatInput.value.trim()) return;
    const userText = chatInput.value;
    chatMessages.value.push({ role: 'user', text: userText });
    chatInput.value = "";
    isChatLoading.value = true;
    scrollToBottom();

    try {
        const response = await fetch(CHATBOT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: userText })
        });
        if (!response.ok) throw new Error("AI Error");
        const data = await response.json();
        const filters = data.ai_data || {}; 
        
        let realProducts = products.value;
        if (filters.keyword) {
            const k = filters.keyword.toLowerCase();
            realProducts = realProducts.filter(p => (p.name || '').toLowerCase().includes(k));
        }
        if (filters.min_price) realProducts = realProducts.filter(p => getProductPrice(p) >= filters.min_price);
        if (filters.max_price) realProducts = realProducts.filter(p => getProductPrice(p) <= filters.max_price);

        chatMessages.value.push({
            role: 'ai',
            text: realProducts.length > 0 ? `Tìm thấy ${realProducts.length} sản phẩm:` : "Không tìm thấy sản phẩm phù hợp.",
            products: realProducts 
        });
    } catch (error) {
        chatMessages.value.push({ role: 'ai', text: "Đang gặp sự cố kết nối AI.", products: [] });
    } finally {
        isChatLoading.value = false;
        scrollToBottom();
    }
};

onMounted(async () => {
    await fetchData();
    updateWishlistState(); 
    startAutoSlide();
});
onBeforeUnmount(stopAutoSlide);
</script>

<template>
    <div id="app">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

        <main class="container">
            <section class="top-section-layout">
                <nav class="categories-sidebar">
                    <h3 class="sidebar-title">DANH MỤC</h3>
                    <div class="category-list">
                        <div v-for="category in categories" :key="category.id"
                            class="category-item-clean text-uppercase"
                            @click="navigateToCategory(category.id)" 
                            style="cursor: pointer;">
                            <span v-if="category.icon" v-html="category.icon" class="cat-icon"></span>
                            <span v-else class="cat-icon"><i class="fas fa-box"></i></span>
                            <span>{{ category.name }}</span>
                            <i class="fas fa-chevron-right ms-auto" style="font-size: 10px; color: #ccc; margin-left: auto;"></i>
                        </div>
                    </div>
                </nav>

                <section class="slider" @mouseenter="stopAutoSlide" @mouseleave="startAutoSlide">
                    <div class="slider-wrapper" :style="{ transform: 'translateX(-' + currentSlide * 100 + '%)' }">
                        <div class="slide" v-for="slide in slides" :key="slide.id">
                            <img :src="getImageUrl(slide.imageUrl || slide.image_url)" alt="Slide">
                        </div>
                    </div>
                    <button class="slider-control prev" @click="prevSlide"><i class="fas fa-chevron-left"></i></button>
                    <button class="slider-control next" @click="nextSlide"><i class="fas fa-chevron-right"></i></button>
                </section>

                <section class="voucher-sidebar">
                    <h3 class="sidebar-title">MÃ GIẢM GIÁ</h3>
                    <div class="voucher-list">
                        <div class="voucher-card" v-for="v in vouchers" :key="v.id">
                            <div class="voucher-info">
                                <span class="voucher-code">{{ v.code }}</span>
                                <span class="voucher-desc" :title="v.desc">{{ v.desc }}</span>
                            </div>
                            <button class="btn-save text" @click="saveVoucher(v.code)">Lưu</button>
                        </div>
                        <div v-if="vouchers.length === 0" class="no-voucher">Đang cập nhật...</div>
                    </div>
                </section>
            </section>

            <section class="brand-banner" v-if="brands.length > 0">
                <a :href="brands[0].linkUrl || '#'">
                    <img :src="getImageUrl(brands[0].imageUrl || brands[0].image)" alt="Brand Banner">
                </a>
            </section>

            <section class="trust-block">
                <div class="trust-item"><i class="fas fa-check-circle"></i> Chính hãng 100%</div>
                <div class="trust-item"><i class="fas fa-truck"></i> Miễn phí vận chuyển</div>
                <div class="trust-item"><i class="fas fa-sync-alt"></i> Đổi trả 30 ngày</div>
                <div class="trust-item"><i class="fas fa-headset"></i> Hỗ trợ 24/7</div>
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
              <div class="product-card-basic" v-for="product in products" :key="product.id">
    <div class="image-wrapper">
        <span class="badge-new">NEW</span>
        <img :src="getImageUrl(product.thumbnail_url || product.image_url || product.image)" :alt="product.name">

        <div class="hover-overlay" :class="{ 'has-variants-layout': product.variants && product.variants.length > 0 }">

            <button class="action-btn" @click="$router.push(`/products/${product.id}`)" title="Xem chi tiết">
                <i class="fas fa-eye"></i>
            </button>

      <button 
    class="action-btn" 
    :class="{ 'active': isLiked(product) }" 
    @click="onAddToWishlist(product)" 
    title="Yêu thích">
    <i class="fas fa-heart"></i>
</button>

            <button v-if="!product.variants || product.variants.length === 0" class="action-btn" @click="onAddToCart(product)" title="Thêm vào giỏ">
                <i class="fas fa-cart-plus"></i>
            </button>

            <div v-else class="variant-quick-list">
                <div class="variant-scroll-container">
                    <button
                        v-for="variant in product.variants"
                        :key="variant.id"
                        class="variant-mini-btn"
                      @click.stop="onAddToCart(product, variant)"
            :title="`${variant.name} - ${formatCurrency(variant.price)}`"
                    >
                        {{ variant.name ? variant.name : 'Mẫu ' + variant.id }}
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="product-info">
        <h3 class="product-name" @click="$router.push(`/products/${product.id}`)" style="cursor: pointer;">
            {{ product.name }}
        </h3>
        <div class="price-row">
            <span class="price">{{ formatCurrency(getProductPrice(product)) }}</span>
            <span class="sold" v-if="product.sold_count">Đã bán {{ product.sold_count }}</span>
        </div>
    </div>
</div>
                    </div>
                </section>

                <section class="product-group news-group">
                    <h2 class="section-title">TIN TỨC CÔNG NGHỆ</h2>
                    <div class="news-grid">
                        <div class="news-card-basic" v-for="news in newsList" :key="news.id">
                            <div class="news-img-wrap">
                                <img :src="getImageUrl(news.image_url || news.image)" :alt="news.title" onerror="this.src='https://placehold.co/400x300?text=News'">
                            </div>
                            <div class="news-content">
                                <h3 class="news-title">{{ news.title }}</h3>
                                <p class="news-excerpt">{{ getExcerpt(news.excerpt || news.content, 100) }}</p>
                                <!-- SỬA Ở ĐÂY: Dùng news.id thay vì news.slug -->
                                <router-link :to="`/PostDetail/${news.id}`" class="read-more-link">Xem thêm &rarr;</router-link>
                            </div>
                        </div>
                    </div>
                </section>
            </section>
        </main>

        <div class="chatbot-wrapper">
            <button class="chat-toggle-btn" @click="toggleChat"><i class="fas" :class="isChatOpen ? 'fa-times' : 'fa-comment-dots'"></i></button>
            <div class="chat-window" v-if="isChatOpen">
                <div class="chat-header"><span><i class="fas fa-robot"></i> Trợ lý AI</span><button @click="toggleChat"><i class="fas fa-minus"></i></button></div>
                <div class="chat-body" ref="chatBodyRef">
                    <div v-for="(msg, index) in chatMessages" :key="index" class="chat-message" :class="msg.role">
                        <div class="chat-avatar" v-if="msg.role === 'ai'"><i class="fas fa-robot"></i></div>
                        <div class="msg-content-wrapper">
                            <div class="msg-content"><p>{{ msg.text }}</p></div>
                            <div v-if="msg.products && msg.products.length > 0" class="chat-product-list">
                                <div v-for="p in msg.products" :key="p.id" class="chat-product-item" @click="$router.push({ name: 'ProductDetail', params: { id: p.id } })">
                                    <img :src="getImageUrl(p.thumbnail_url || p.image)" alt="img">
                                    <div class="cp-info"><div class="cp-name">{{ p.name }}</div><div class="cp-price">{{ formatCurrency(getProductPrice(p)) }}</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="isChatLoading" class="chat-message ai"><div class="chat-avatar"><i class="fas fa-robot"></i></div><div class="msg-content typing"><span>.</span><span>.</span><span>.</span></div></div>
                </div>
                <div class="chat-footer"><input type="text" v-model="chatInput" placeholder="Hỏi AI (VD: Tìm laptop dưới 20tr)..." @keyup.enter="sendChatMessage"><button @click="sendChatMessage" :disabled="isChatLoading"><i class="fas fa-paper-plane"></i></button></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Code CSS cũ của bạn rất ổn định, giữ nguyên */
:root { --primary-color: #009981; --text-color: #333; --border-color: #e5e7eb; }
* { box-sizing: border-box; font-family: 'Montserrat', sans-serif; }
.text-uppercase { text-transform: uppercase; }
.container { max-width: 1280px; margin: 20px auto; padding: 0 15px; }
.top-section-layout { display: grid; grid-template-columns: 240px 1fr 260px; gap: 15px; align-items: stretch; margin-bottom: 20px; }
.categories-sidebar { background: #fff; border-radius: 8px; box-shadow: 0 1px 2px 0 rgba(60, 64, 67, .1), 0 2px 6px 2px rgba(60, 64, 67, .15); padding: 10px; height: 100%; }
.sidebar-title { font-size: 14px; font-weight: 700; margin-bottom: 10px; padding-left: 10px; color: #444; }
.category-item-clean { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: #333; font-size: 13px; font-weight: 500; border-radius: 5px; transition: 0.2s; text-decoration: none; }
.cat-icon { width: 20px; text-align: center; color: #666; display: flex; align-items: center; justify-content: center; }
.category-item-clean:hover { background: #DBF9EB; color: #00483D; font-weight: 600; }
.category-item-clean:hover .cat-icon { color: #00483D; }
.slider { position: relative; overflow: hidden; border-radius: 8px; box-shadow: 0 1px 2px 0 rgba(60, 64, 67, .1); width: 100%; min-height: 300px; }
.slider-wrapper { display: flex; height: 100%; transition: transform 0.5s ease-in-out; width: 100%; }
.slide { min-width: 100%; width: 100%; flex: 0 0 100%; position: relative; }
.slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
.slider-control { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.3); color: #fff; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.3s; z-index: 3; display: flex; align-items: center; justify-content: center; }
.slider-control:hover { background: rgba(0, 0, 0, 0.4); }
.prev { left: 10px; }
.next { right: 10px; }
.voucher-sidebar { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 2px 0 rgba(60, 64, 67, .1); height: 100%; overflow: hidden; }
.voucher-list { display: flex; flex-direction: column; gap: 10px; }
.voucher-card { display: flex; justify-content: space-between; align-items: center; background: #fff; border: 1px dashed var(--primary-color); padding: 10px; border-radius: 6px; transition: all 0.2s; }
.voucher-card:hover { background: #f9f9f9; }
.voucher-code { font-weight: 700; color: var(--primary-color); font-size: 13px; display: block; }
.voucher-desc { font-size: 11px; color: #666; display: block; margin-top: 2px; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.btn-save { background: var(--primary-color); color: #fff; border: none; padding: 5px 12px; font-size: 11px; border-radius: 4px; cursor: pointer; font-weight: 600; }
.btn-save:hover { opacity: 0.9; }
.no-voucher { text-align: center; font-size: 12px; color: #999; padding: 20px 0; }
.brand-banner { margin-bottom: 20px; }
.brand-banner img { width: 100%; height: 120px; object-fit: cover; border-radius: 10px; display: block; }
.trust-block { display: flex; justify-content: space-between; padding: 25px 30px; margin-bottom: 30px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); border-top: 3px solid var(--primary-color); }
.trust-item { font-size: 15px; font-weight: 700; color: var(--primary-color); display: flex; align-items: center; gap: 12px; }
.trust-item i { font-size: 24px; color: var(--primary-color); }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.section-title { font-size: 18px; font-weight: 700; color: #333; text-transform: uppercase; }
.carousel-nav button { background: #fff; border: 1px solid #ddd; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; margin-left: 5px; color: #555; transition: 0.2s; }
.carousel-nav button:hover { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
.product-carousel-wrapper { display: flex; overflow-x: auto; scroll-behavior: smooth; gap: 15px; padding-bottom: 10px; scrollbar-width: none; }
.product-carousel-wrapper::-webkit-scrollbar { display: none; }
.product-card-basic { background: transparent; transition: 0.3s ease; flex: 0 0 calc(20% - 12px); min-width: 200px; }
.product-grid .product-card-basic { flex: unset; min-width: unset; width: 100%; }
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
/* Container hình ảnh */
/* --- GIỮ NGUYÊN HOẶC CẬP NHẬT CÁC CLASS CŨ --- */
.image-wrapper {
    position: relative;
    width: 100%;
    padding-top: 100%;
    background: #fff;
    overflow: hidden;
    border-radius: 6px;
    margin-bottom: 15px;
    border: 1px solid #eee;
    /* Thêm cái này để lớp mờ không bị tràn ra ngoài góc bo tròn */
    isolation: isolate;
}

/* Hiệu ứng zoom ảnh khi hover */
.product-card-basic:hover .image-wrapper img {
    transform: scale(1.15); /* Zoom vừa phải cho đẹp */
}

.image-wrapper img {
    position: absolute;
    top: 0; left: 0; width: 100%; height: 100%;
    object-fit: contain;
    padding: 10px;
    transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    z-index: 1;
}

/* --- CẤU HÌNH LỚP PHỦ MỚI (QUAN TRỌNG) --- */
.hover-overlay {
    position: absolute;
    bottom: 0; left: 0;
    width: 100%;
    padding: 15px;
    display: flex;
    justify-content: center; /* Căn giữa các nút */
    align-items: center; /* Căn giữa theo chiều dọc */
    gap: 10px; /* Khoảng cách giữa các nút/khối */

    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
    z-index: 2;

    /* HIỆU ỨNG KÍNH MỜ (FROSTED GLASS) */
    /* Màu trắng có độ trong suốt 60% để thấy ảnh bên dưới */
    background: rgba(255, 255, 255, 0.6);
    /* Hiệu ứng làm mờ ảnh phía sau lớp nền này */
    backdrop-filter: blur(8px);
    /* Border nhẹ để làm nổi bật lớp kính */
    border-top: 1px solid rgba(255, 255, 255, 0.3);
}

.product-card-basic:hover .hover-overlay {
    opacity: 1;
    transform: translateY(0);
}

/* Style cho các nút tròn cũ (action-btn) - Giữ nguyên để đảm bảo nút Mắt và Tim đẹp như cũ */
.action-btn {
    width: 35px; height: 35px;
    border-radius: 50%; border: none;
    background: #fff; color: #333;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: 0.2s; font-size: 14px;
    flex-shrink: 0; /* Không bị co lại khi không gian hẹp */
}
.action-btn:hover {
    background: var(--primary-color); color: #fff;
}



/* Mặc định (Chưa thích): Hover vào sẽ màu xanh (theo var(--primary-color) cũ) */
.action-btn:hover {
    background: var(--primary-color); 
    color: #fff;
    border-color: var(--primary-color);
}

/* ĐÃ THÍCH: Icon màu đỏ, viền đỏ */
.action-btn.active {
    color: #d0021b; /* Màu đỏ */
    border-color: #d0021b;
    background: #fff; /* Nền trắng để nổi bật icon đỏ */
}

/* Hiệu ứng khi Hover vào sản phẩm ĐÃ THÍCH */
.action-btn.active:hover {
    background: #d0021b; /* Nền chuyển đỏ */
    color: #fff; /* Icon chuyển trắng */
    border-color: #d0021b;
}

.variant-quick-list {
    display: flex;
    flex-direction: column;
    position: relative; /* Quan trọng để làm mốc cho danh sách con */
    flex-grow: 1; /* Chiếm hết khoảng trống còn lại */
    min-width: 110px; 
    max-width: 160px; 
    height: 35px; /* Chiều cao bằng với nút Tim/Mắt bên cạnh */
}

/* 2. Container chứa danh sách biến thể */
.variant-scroll-container {
    display: flex;
    flex-direction: column;
    gap: 5px;
    width: 100%;
    
    /* CẤU HÌNH TỰ BUNG RA LUÔN */
    position: absolute; /* Nổi lên trên */
    bottom: 0; /* Neo ở đáy của khung cha */
    left: 0;
    
    max-height: 150px; /* Chiều cao tối đa (tương đương 4-5 dòng) */
    overflow-y: auto; /* Cho phép cuộn nếu danh sách dài hơn chiều cao */
    
    /* Giao diện nền trắng nổi bật */
    background: #fff;
    padding: 5px;
    border-radius: 8px;
    box-shadow: 0 -5px 20px rgba(0,0,0,0.15); /* Đổ bóng đậm hơn chút để nổi */
    z-index: 100; /* Đè lên mọi thứ */
    border: 1px solid #e5e7eb;
}

/* Tùy chỉnh thanh cuộn cho đẹp và nhỏ gọn */
.variant-scroll-container::-webkit-scrollbar {
    width: 3px;
}
.variant-scroll-container::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 10px;
}

/* 3. Nút biến thể con */
.variant-mini-btn {
    background: #f3f4f6; /* Xám nhạt */
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    
    /* Cấu hình chữ */
    color: #333 !important; /* Luôn màu đen */
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    padding: 6px 4px;
    
    width: 100%;
    cursor: pointer;
    transition: all 0.2s;
    
    /* Xử lý chữ dài */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.variant-mini-btn:hover {
    background: var(--primary-color);
    color: #fff !important;
    border-color: var(--primary-color);
}
.product-card-basic:hover .image-wrapper img { transform: scale(1.05); }
.badge-new { position: absolute; top: 10px; left: 10px; background: var(--primary-color); color: white; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 2px; text-transform: uppercase; }
.hover-overlay { position: absolute; bottom: 0; left: 0; width: 100%; padding: 15px; display: flex; justify-content: center; gap: 10px; opacity: 0; transform: translateY(20px); transition: all 0.3s ease; background: linear-gradient(to top, rgba(0, 0, 0, 0.05), transparent); }
.product-card-basic:hover .hover-overlay { opacity: 1; transform: translateY(0); }
.action-btn { width: 35px; height: 35px; border-radius: 50%; border: none; background: #fff; color: #333; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15); transition: 0.2s; font-size: 14px; }
.action-btn:hover { background: var(--primary-color); color: #fff; }
.product-info { text-align: center; }
.product-name { font-size: 14px; font-weight: 500; color: #333; margin-bottom: 8px; line-height: 1.4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.price-row { display: flex; justify-content: center; align-items: baseline; gap: 10px; }
.price { color: var(--primary-color); font-weight: 700; font-size: 15px; }
.sold { font-size: 11px; color: #999; }
.news-group { margin-top: 80px; padding-top: 40px; border-top: 1px solid #e5e7eb; }
.news-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; }
.news-card-basic { display: flex; flex-direction: column; }
.news-img-wrap { width: 100%; height: 180px; border-radius: 6px; overflow: hidden; margin-bottom: 12px; }
.news-img-wrap img { width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
.news-card-basic:hover .news-img-wrap img { transform: scale(1.05); }
.news-title { font-size: 15px; font-weight: 600; line-height: 1.4; margin-bottom: 8px; }
.read-more-link { font-size: 12px; font-weight: 700; color: var(--primary-color); }
.news-excerpt { font-size: 13px; color: #666; margin-bottom: 10px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.chatbot-wrapper { position: fixed; bottom: 30px; right: 30px; z-index: 9999; font-family: 'Montserrat', sans-serif; }
.chat-toggle-btn { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #009981, #006b5a); color: white; border: none; font-size: 24px; cursor: pointer; box-shadow: 0 4px 15px rgba(0, 153, 129, 0.4); transition: transform 0.3s, box-shadow 0.3s; display: flex; align-items: center; justify-content: center; }
.chat-toggle-btn:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(0, 153, 129, 0.6); }
.chat-window { position: absolute; bottom: 80px; right: 0; width: 360px; height: 520px; background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); display: flex; flex-direction: column; overflow: hidden; border: 1px solid rgba(0,0,0,0.05); animation: slideUp 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
.chat-header { background: linear-gradient(135deg, #009981, #007563); color: white; padding: 15px 20px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.chat-header button { background: transparent; border: none; color: white; cursor: pointer; font-size: 16px; opacity: 0.8; transition: 0.2s; }
.chat-header button:hover { opacity: 1; }
.chat-body { flex: 1; padding: 15px; overflow-y: auto; background: #f4f6f8; display: flex; flex-direction: column; gap: 15px; scroll-behavior: smooth; }
.chat-message { display: flex; gap: 10px; max-width: 100%; }
.chat-message.user { justify-content: flex-end; }
.chat-avatar { width: 32px; height: 32px; background: #e0e0e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #666; font-size: 14px; flex-shrink: 0; }
.chat-message.ai .chat-avatar { background: #009981; color: white; }
.msg-content-wrapper { max-width: 80%; display: flex; flex-direction: column; }
.msg-content { padding: 10px 14px; border-radius: 12px; font-size: 13px; line-height: 1.5; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.chat-message.user .msg-content { background: #009981; color: white; border-bottom-right-radius: 2px; }
.chat-message.ai .msg-content { background: white; color: #333; border-bottom-left-radius: 2px; border: 1px solid #eee; }
.chat-product-list { margin-top: 8px; display: flex; flex-direction: column; gap: 8px; }
.chat-product-item { display: flex; align-items: center; gap: 10px; background: white; padding: 8px 10px; border-radius: 8px; cursor: pointer; transition: 0.2s; border: 1px solid #eee; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
.chat-product-item:hover { border-color: #009981; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,153,129,0.15); }
.chat-product-item img { width: 45px; height: 45px; object-fit: cover; border-radius: 6px; border: 1px solid #f0f0f0; }
.cp-info { flex: 1; overflow: hidden; }
.cp-name { font-size: 12px; font-weight: 600; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #333; }
.cp-price { font-size: 12px; color: #d0021b; font-weight: 700; }
.chat-footer { padding: 12px; border-top: 1px solid #eee; background: white; display: flex; gap: 8px; align-items: center; }
.chat-footer input { flex: 1; border: 1px solid #e0e0e0; padding: 10px 15px; border-radius: 25px; outline: none; font-size: 13px; transition: 0.2s; background: #f9f9f9; }
.chat-footer input:focus { border-color: #009981; background: white; }
.chat-footer button { background: #009981; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; flex-shrink: 0; }
.chat-footer button:disabled { background: #ccc; cursor: not-allowed; }
.chat-footer button:hover:not(:disabled) { background: #007563; transform: scale(1.05); }
.typing span { animation: blink 1.4s infinite both; margin: 0 2px; font-weight: bold; font-size: 16px; color: #888; }
.typing span:nth-child(2) { animation-delay: 0.2s; }
.typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes blink { 0% { opacity: 0.2; } 20% { opacity: 1; } 100% { opacity: 0.2; } }
@media (max-width: 1024px) { .top-section-layout { grid-template-columns: 200px 1fr; } .voucher-sidebar { display: none; } .product-card-basic { flex: 0 0 calc(33.333% - 10px); } }
@media (max-width: 768px) { .top-section-layout { display: block; } .categories-sidebar { display: none; } .slider { height: 200px; margin-bottom: 15px; } .product-card-basic { flex: 0 0 calc(50% - 10px); } .news-grid { grid-template-columns: 1fr; } .trust-block { flex-wrap: wrap; gap: 15px; } .trust-item { width: 100%; justify-content: flex-start; } .chatbot-wrapper { bottom: 20px; right: 20px; } .chat-window { position: fixed; bottom: 0; right: 0; width: 100%; height: 80vh; border-radius: 20px 20px 0 0; } }
</style>