<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router'; 
import { useStore } from "vuex"; 
import apiService from '../../apiService.js';
import Swal from 'sweetalert2'; 
import { toggleWishlist } from "../../store/wishlistStore.js";

const router = useRouter();
const store = useStore(); 

// --- CẤU HÌNH ---
const SERVER_URL = 'http://127.0.0.1:8000';
const CHATBOT_API_URL = 'http://localhost:3000/api/chat-search'; 
const USE_STORAGE = false;

const toSlug = (str) => {
    if (!str) return '';
    str = str.toLowerCase();
    str = str.replace(/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/g, 'a');
    str = str.replace(/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/g, 'e');
    str = str.replace(/(ì|í|ị|ỉ|ĩ)/g, 'i');
    str = str.replace(/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/g, 'o');
    str = str.replace(/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/g, 'u');
    str = str.replace(/(ỳ|ý|ỵ|ỷ|ỹ)/g, 'y');
    str = str.replace(/(đ)/g, 'd');
    str = str.replace(/([^0-9a-z-\s])/g, '');
    str = str.replace(/(\s+)/g, '-');
    str = str.replace(/^-+/g, '');
    str = str.replace(/-+$/g, '');
    return str;
};

// --- TOAST CONFIG (UPDATED) ---
const Toast = Swal.mixin({
    toast: true,
    position: 'bottom-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: '#fff',
    color: '#333',
    iconColor: '#10b981', // Màu xanh chủ đạo
    // Class tùy chỉnh để style CSS
    customClass: {
        popup: 'elegant-toast', 
        title: 'elegant-toast-title',
        timerProgressBar: 'elegant-toast-progress'
    },
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
    if (product.variants && Array.isArray(product.variants) && product.variants.length > 0) {
        const prices = product.variants.map(v => Number(v.price)).filter(p => !isNaN(p) && p > 0);
        if (prices.length > 0) return Math.min(...prices);
    }
    return Number(product.price) || 0;
};

const getRating = (product) => {
  return Number(product.rating_average || product.rating || 5);
}

const getExcerpt = (text, limit = 100) => {
    if (!text) return '';
    const cleanText = String(text).replace(/<[^>]*>?/gm, '');
    if (cleanText.length <= limit) return cleanText;
    return cleanText.substring(0, limit) + '...';
};

// --- KHAI BÁO BIẾN STATE ---
const categories = ref([]);
const brands = ref([]);
const slides = ref([]);
const products = ref([]);
const newsList = ref([]);
const vouchers = ref([]);
const activeCategoryId = ref(null);
const currentSlide = ref(0);
// [FIX] Thêm biến loading để sửa lỗi "loading is not defined"
const loading = ref(true);
let interval = null;
const scrollRefs = ref({}) 

// --- CHATBOT STATE ---
const isChatOpen = ref(false);
const chatInput = ref("");
const isChatLoading = ref(false);
const chatMessages = ref([
    { role: 'ai', text: 'Xin chào! Bạn cần tìm sản phẩm gì?', products: [] }
]);
const chatBodyRef = ref(null);

// --- FETCH DATA ---
const fetchData = async () => {
    // [FIX] Bật loading khi bắt đầu gọi API
    loading.value = true;
    try {
        const [catRes, slideRes, prodRes, brandRes, couponRes, newsRes] = await Promise.all([
            apiService.get(`/categories`).catch(e => null),
            apiService.get(`/slides`).catch(e => null),
            apiService.get(`/products?include=variants`).catch(e => null), 
            apiService.get(`/brands`).catch(e => null),
            apiService.get(`/coupons`).catch(e => null),
            apiService.get(`/news`).catch(e => null),
        ]);

        categories.value = catRes?.data?.data || catRes?.data || [];
        slides.value = slideRes?.data?.data || slideRes?.data || [];
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
    } finally {
        // [FIX] Tắt loading khi tải xong (dù thành công hay thất bại)
        loading.value = false;
    }
};

// [NEW] Logic gom nhóm sản phẩm theo danh mục và BUNG BIẾN THỂ
const categoriesWithProducts = computed(() => {
  if (!categories.value.length || !products.value.length) return []

  return categories.value.map(cat => {
    const productsInCat = products.value.filter(p => p.category_id === cat.id)
    
    let displayItems = [];

    productsInCat.forEach(p => {
        if (p.variants && p.variants.length > 0) {
            p.variants.forEach(v => {
                displayItems.push({
                    unique_id: `${p.id}_${v.id}`, 
                    id: p.id, 
                    name: p.name,
                    variant_name: v.name, 
                    image: v.image || p.thumbnail_url || p.image_url, 
                    sale_price: Number(v.price), 
                    old_price: Number(v.price) * 1.1,
                    discount: 10,
                    rating: getRating(p),
                    sold_count: p.sold_count || 0,
                    raw_variant: v, 
                    raw_product: p
                });
            });
        } else {
            displayItems.push({
                unique_id: `${p.id}_main`,
                id: p.id,
                name: p.name,
                variant_name: '', 
                image: p.thumbnail_url || p.image_url,
                sale_price: Number(p.price) || 0,
                old_price: (Number(p.price) || 0) * 1.1,
                discount: 10,
                rating: getRating(p),
                sold_count: p.sold_count || 0,
                raw_variant: null,
                raw_product: p
            });
        }
    });

    const sortedDisplayItems = displayItems
        .sort((a, b) => (b.sold_count || 0) - (a.sold_count || 0));

    return {
      ...cat,
      products: sortedDisplayItems
    }
  }).filter(cat => cat.products.length > 0)
})

const categoryPairs = computed(() => {
    const pairs = [];
    const source = categoriesWithProducts.value;
    for (let i = 0; i < source.length; i += 2) {
        pairs.push(source.slice(i, i + 2));
    }
    return pairs;
});

const activeTabs = ref({});

watch(categoryPairs, (newPairs) => {
    newPairs.forEach((pair, index) => {
        if (pair.length > 0 && !activeTabs.value[index]) {
            activeTabs.value[index] = pair[0].id;
        }
    });
}, { immediate: true });

const switchTab = (pairIndex, categoryId) => {
    activeTabs.value[pairIndex] = categoryId;
};

const getActiveProducts = (pair, pairIndex) => {
    const activeId = activeTabs.value[pairIndex];
    const category = pair.find(c => c.id === activeId) || pair[0];
    return category ? category.products.slice(0, 10) : [];
};
const getActiveCategory = (pair, pairIndex) => {
    const activeId = activeTabs.value[pairIndex];
    return pair.find(c => c.id === activeId) || pair[0];
}

// --- LOGIC SLIDER & NAV ---
const navigateToCategory = (id) => {
    activeCategoryId.value = String(id);
    router.push({ path: '/Shop', query: { categoryId: id } });
};

const goToProduct = (id) => router.push(`/products/${id}`); 

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


const onAddToCart = async (item) => {
    try {
        console.log("🖱️ User clicked Add to Cart:", item);

        let product = item.raw_product ? JSON.parse(JSON.stringify(item.raw_product)) : {
            id: item.id,
            name: item.name,
            price: item.sale_price,
        };

        if (!product.image && !product.thumbnail_url) {
            product.image = item.image; 
        }

        let variantToAdd = item.raw_variant ? JSON.parse(JSON.stringify(item.raw_variant)) : null;

        if (!variantToAdd) {
             variantToAdd = {
                id: product.id, 
                product_id: product.id,
                name: '', 
                price: product.price || item.sale_price,
                stock: 9999, 
                image: product.image || product.thumbnail_url 
            };
        }

        await store.dispatch('addToCart', {
            product: product,
            variant: variantToAdd,
            quantity: 1
        });

        const variantName = variantToAdd.name ? `(${variantToAdd.name})` : '';
        Toast.fire({ icon: 'success', title: `Đã thêm ${product.name} ${variantName} vào giỏ!` });

        try {
            const payload = {
                product_id: product.id,
                variant_id: (variantToAdd.id === product.id && !variantToAdd.sku) ? null : variantToAdd.id, 
                quantity: 1
            };

            await apiService.post('/cart/add', payload);
            console.log("✅ Đã đồng bộ giỏ hàng với Database");

        } catch (apiError) {
            console.warn("⚠️ Chưa thể lưu vào DB (có thể do chưa đăng nhập):", apiError);
        }

    } catch (error) {
        console.error("🚨 Lỗi thêm vào giỏ hàng:", error);
        Toast.fire({ icon: 'error', title: 'Có lỗi khi thêm vào giỏ hàng!' });
    }
};

const wishlistIds = ref(new Set()); 

const updateWishlistState = () => {
    try {
        const stored = JSON.parse(localStorage.getItem('wishlist') || '[]');
        wishlistIds.value = new Set(stored.map(p => p.id));
    } catch (e) {
        wishlistIds.value = new Set();
    }
};

const isLiked = (product) => wishlistIds.value.has(product.id);

const onAddToWishlist = (product) => {
    const isAdded = toggleWishlist(product);

    if (isAdded) {
        wishlistIds.value.add(product.id); 
        Toast.fire({ icon: 'success', title: `Đã thích ${product.name} ❤️` });
    } else {
        wishlistIds.value.delete(product.id); 
        Toast.fire({ icon: 'info', title: `Đã bỏ thích ${product.name}` });
    }

    wishlistIds.value = new Set(wishlistIds.value);
};

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
    if (!document.querySelector('script[src="https://cdn.lordicon.com/lordicon.js"]')) {
        const script = document.createElement('script')
        script.src = 'https://cdn.lordicon.com/lordicon.js'
        script.async = true
        document.head.appendChild(script)
    }
    await fetchData();
    updateWishlistState();
    startAutoSlide();
});
onBeforeUnmount(stopAutoSlide);
</script>

<template>
    <div id="app">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

        <main class="container">
            <section class="top-section-layout">
                <nav class="categories-sidebar">
                    <h3 class="sidebar-title">DANH MỤC</h3>
                    <div class="category-list">
                        <div v-for="category in categories" :key="category.id"
                            class="category-item-clean text-uppercase" @click="navigateToCategory(category.id)"
                            style="cursor: pointer;">
                            <span v-if="category.icon" v-html="category.icon" class="cat-icon"></span>
                            <span v-else class="cat-icon"><i class="fas fa-box"></i></span>
                            <span>{{ category.name }}</span>
                            <i class="fas fa-chevron-right ms-auto"
                                style="font-size: 10px; color: #ccc; margin-left: auto;"></i>
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
                <div v-if="loading" class="text-center py-5">Đang tải sản phẩm...</div>

                <div v-else v-for="(pair, index) in categoryPairs" :key="index" class="category-section">
                    
                    <div class="tabs-header" :class="{ 'single-tab-mode': pair.length === 1 }">
                        <div v-for="cat in pair" :key="cat.id" 
                            class="tab-item"
                            :class="{ active: activeTabs[index] === cat.id }"
                            @click="switchTab(index, cat.id)">
                            {{ cat.name }}
                        </div>
                    </div>

                    <div class="view-all-wrapper text-end mb-3 px-3">
                            <a href="javascript:void(0)" class="view-all-link" @click="navigateToCategory(activeTabs[index])">
                            Xem tất cả {{ getActiveCategory(pair, index)?.name }} <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>

                    <div class="product-grid-layout">
                        <div class="product-card" v-for="item in getActiveProducts(pair, index)" :key="item.unique_id"
                            @click="goToProduct(item.id)">

                            <div class="product-image">
                                <img :src="getImageUrl(item.image)" :alt="item.name"
                                    @error="$event.target.src = 'https://placehold.co/300x300?text=Product'" />
                                <span class="discount-tag" v-if="item.discount">-{{ item.discount }}%</span>
                            </div>

                            <div class="product-info">
                                <h3 class="product-name" :title="item.name">{{ item.name }}</h3>

                                <div class="variant-name">
                                    <i class="fas fa-cube" style="font-size: 10px; margin-right: 4px;" v-if="item.variant_name"></i>
                                    {{ item.variant_name }}
                                </div>

                                <div class="product-meta">
                                    <div class="rating">
                                        <i class="fas fa-star"></i> {{ item.rating.toFixed(1) }}
                                    </div>
                                    <div class="sold-count">
                                        Đã bán {{ item.sold_count || 0 }}
                                    </div>
                                </div>

                                <p class="product-price">
                                    {{ formatCurrency(item.sale_price) }}
                                    <span class="old-price-small" v-if="item.old_price > item.sale_price">
                                        {{ formatCurrency(item.old_price) }}
                                    </span>
                                </p>

                                <div class="product-actions-group">
                                    <button class="btn-add-cart" @click.stop="onAddToCart(item)">
                                        <div class="lord-icon-wrapper">
                                            <lord-icon
                                                src="https://cdn.lordicon.com/evyuuwna.json"
                                                trigger="hover"
                                                target="closest button"
                                                colors="primary:#ffffff,secondary:#ffffff"
                                                style="width:24px;height:24px">
                                            </lord-icon>
                                        </div>
                                        Thêm Ngay
                                    </button>
                                </div>
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
                                <img :src="getImageUrl(news.image_url || news.image)" :alt="news.title"
                                    onerror="this.src='https://placehold.co/400x300?text=News'">
                            </div>
                            <div class="news-content">
                                <h3 class="news-title">{{ news.title }}</h3>
                                <p class="news-excerpt">{{ getExcerpt(news.excerpt || news.content, 100) }}</p>

                                <router-link :to="{
                                    name: 'PostDetailt',
                                    params: {
                                        id: news.id,
                                        slug: news.slug ? news.slug : toSlug(news.title)
                                    }
                                }" class="read-more-link">
                                    Xem thêm &rarr;
                                </router-link>

                            </div>
                        </div>
                    </div>
                </section>
        </main>

        <div class="chatbot-wrapper">
            <button class="chat-toggle-btn" @click="toggleChat"><i class="fas"
                    :class="isChatOpen ? 'fa-times' : 'fa-comment-dots'"></i></button>
            <div class="chat-window" v-if="isChatOpen">
                <div class="chat-header"><span><i class="fas fa-robot"></i> Trợ lý AI</span><button
                        @click="toggleChat"><i class="fas fa-minus"></i></button></div>
                <div class="chat-body" ref="chatBodyRef">
                    <div v-for="(msg, index) in chatMessages" :key="index" class="chat-message" :class="msg.role">
                        <div class="chat-avatar" v-if="msg.role === 'ai'"><i class="fas fa-robot"></i></div>
                        <div class="msg-content-wrapper">
                            <div class="msg-content">
                                <p>{{ msg.text }}</p>
                            </div>
                            <div v-if="msg.products && msg.products.length > 0" class="chat-product-list">
                                <div v-for="p in msg.products" :key="p.id" class="chat-product-item"
                                    @click="$router.push({ name: 'ProductDetail', params: { id: p.id } })">
                                    <img :src="getImageUrl(p.thumbnail_url || p.image)" alt="img">
                                    <div class="cp-info">
                                        <div class="cp-name">{{ p.name }}</div>
                                        <div class="cp-price">{{ formatCurrency(getProductPrice(p)) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="isChatLoading" class="chat-message ai">
                        <div class="chat-avatar"><i class="fas fa-robot"></i></div>
                        <div class="msg-content typing"><span>.</span><span>.</span><span>.</span></div>
                    </div>
                </div>
                <div class="chat-footer"><input type="text" v-model="chatInput"
                        placeholder="Hỏi AI (VD: Tìm laptop dưới 20tr)..." @keyup.enter="sendChatMessage"><button
                        @click="sendChatMessage" :disabled="isChatLoading"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Style riêng cho Toast để thanh thoát hơn (Copy from CartPage) */
.elegant-toast {
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.15) !important;
    border-radius: 12px !important;
    padding: 10px 16px !important;
    border-left: 4px solid #10b981 !important; /* Điểm nhấn màu xanh bên trái */
    background: #ffffff !important;
}

.elegant-toast-title {
    font-family: 'Montserrat', sans-serif !important; /* Dùng Font của trang Home */
    font-weight: 600 !important;
    font-size: 0.95rem !important;
    color: #333 !important;
    margin-left: 5px !important;
}

.elegant-toast-progress {
    background-color: #10b981 !important;
    height: 3px !important; /* Thanh progress mảnh hơn */
}
</style>

<style scoped>
:root {
    --primary-color: #009981;
    --text-color: #333;
    --border-color: #e5e7eb;
    --shadow-color: rgba(0, 153, 129, 0.15); 
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
    text-decoration: none;
}

.cat-icon {
    width: 20px;
    text-align: center;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
}

.category-item-clean:hover {
    background: #DBF9EB;
    color: #00483D;
    font-weight: 600;
}

.category-item-clean:hover .cat-icon {
    color: #00483D;
}

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

.trust-block {
    display: flex;
    justify-content: space-between;
    padding: 25px 30px;
    margin-bottom: 30px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border-top: 3px solid var(--primary-color);
}

.trust-item {
    font-size: 15px;
    font-weight: 700;
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 12px;
}

.trust-item i {
    font-size: 24px;
    color: var(--primary-color);
}

.category-section {
    background: white;
    border-radius: 12px;
    padding: 20px 25px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    border: 1px solid #eee;
}

.tabs-header {
    display: flex;
    justify-content: center;
    gap: 0;
    margin-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.tabs-header.single-tab-mode {
    justify-content: flex-start; 
}

.tab-item {
    flex: 1;
    text-align: center;
    padding: 15px 0;
    font-size: 1.2em;
    font-weight: 600;
    color: #777;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    position: relative;
    max-width: 50%;
}

.tabs-header.single-tab-mode .tab-item {
    max-width: 100%;
    text-align: left;
    cursor: default;
    color: var(--primary-color);
    font-weight: 800; 
}

.tab-item:hover {
    color: var(--primary-color);
    background-color: #f9f9f9;
}

.tabs-header.single-tab-mode .tab-item:hover {
    background-color: transparent;
}

.tab-item.active {
    color: var(--primary-color);
    font-weight: 800;
}

.tab-item.active::after {
    content: '';
    position: absolute;
    bottom: -2px; 
    left: 0;
    width: 100%;
    height: 3px;
    background-color: var(--primary-color);
}

.view-all-link {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9em;
    transition: color 0.2s;
}
.view-all-link:hover { color: #007563; text-decoration: underline; }

.product-grid-layout {
    display: grid;
    grid-template-columns: repeat(5, 1fr); 
    gap: 15px;
    margin-bottom: 10px;
}

.product-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    position: relative;
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

.product-card:hover {
    box-shadow: 0 8px 20px var(--shadow-color);
    border-color: #ccece6;
}

.product-image {
    height: 180px; 
    background: #f9f9f9;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 10px;
}
.product-image img {
    height: 100%; 
    width: auto; 
    object-fit: contain;
    transition: transform 0.4s ease;
}
.product-card:hover .product-image img { transform: scale(1.08); }

.discount-tag {
    position: absolute;
    top: 10px; left: 10px;
    background: #ff4d4d;
    color: white;
    font-size: 0.75em;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: bold;
}

.product-info {
    padding: 12px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.product-name {
    font-size: 0.95em;
    font-weight: 600;
    color: #333;
    margin-bottom: 3px; 
    line-height: 1.4em;
    height: 2.8em; 
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.variant-name {
    font-size: 11px;
    color: #666;
    margin-bottom: 6px;
    font-style: italic;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: #f5f5f5;
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    max-width: 100%;
}

.product-price {
    color: var(--primary-color);
    font-weight: 700;
    font-size: 1.1em;
    margin-bottom: 5px;
}
.old-price-small {
    font-size: 0.8em;
    color: #999;
    text-decoration: line-through;
    margin-left: 5px;
    font-weight: 400;
}

.product-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px; 
    font-size: 0.8em;
}
.rating { color: #ffb400; }
.sold-count { color: #777; font-weight: 500; }

.product-actions-group { margin-top: auto; }
.btn-add-cart {
    width: 100%;
    background: var(--primary-color);
    color: white; 
    border: none;
    padding: 8px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-transform: uppercase;
    font-size: 12px;
}
.btn-add-cart:hover { 
    background: #007563; 
    color: white !important; 
}

.lord-icon-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
}

.news-group {
    margin-top: 40px;
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

.chatbot-wrapper {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9999;
    font-family: 'Montserrat', sans-serif;
}

.chat-toggle-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #009981, #006b5a);
    color: white;
    border: none;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 153, 129, 0.4);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-toggle-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0, 153, 129, 0.6);
}

.chat-window {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 360px;
    height: 520px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.05);
    animation: slideUp 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.chat-header {
    background: linear-gradient(135deg, #009981, #007563);
    color: white;
    padding: 15px 20px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.chat-header button {
    background: transparent;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 16px;
    opacity: 0.8;
    transition: 0.2s;
}

.chat-header button:hover {
    opacity: 1;
}

.chat-body {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    background: #f4f6f8;
    display: flex;
    flex-direction: column;
    gap: 15px;
    scroll-behavior: smooth;
}

.chat-message {
    display: flex;
    gap: 10px;
    max-width: 100%;
}

.chat-message.user {
    justify-content: flex-end;
}

.chat-avatar {
    width: 32px;
    height: 32px;
    background: #e0e0e0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    font-size: 14px;
    flex-shrink: 0;
}

.chat-message.ai .chat-avatar {
    background: #009981;
    color: white;
}

.msg-content-wrapper {
    max-width: 80%;
    display: flex;
    flex-direction: column;
}

.msg-content {
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.5;
    position: relative;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.chat-message.user .msg-content {
    background: #009981;
    color: white;
    border-bottom-right-radius: 2px;
}

.chat-message.ai .msg-content {
    background: white;
    color: #333;
    border-bottom-left-radius: 2px;
    border: 1px solid #eee;
}

.chat-product-list {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.chat-product-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.2s;
    border: 1px solid #eee;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
}

.chat-product-item:hover {
    border-color: #009981;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 153, 129, 0.15);
}

.chat-product-item img {
    width: 45px;
    height: 45px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #f0f0f0;
}

.cp-info {
    flex: 1;
    overflow: hidden;
}

.cp-name {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #333;
}

.cp-price {
    font-size: 12px;
    color: #d0021b;
    font-weight: 700;
}

.chat-footer {
    padding: 12px;
    border-top: 1px solid #eee;
    background: white;
    display: flex;
    gap: 8px;
    align-items: center;
}

.chat-footer input {
    flex: 1;
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
    border-radius: 25px;
    outline: none;
    font-size: 13px;
    transition: 0.2s;
    background: #f9f9f9;
}

.chat-footer input:focus {
    border-color: #009981;
    background: white;
}

.chat-footer button {
    background: #009981;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
    flex-shrink: 0;
}

.chat-footer button:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.chat-footer button:hover:not(:disabled) {
    background: #007563;
    transform: scale(1.05);
}

.typing span {
    animation: blink 1.4s infinite both;
    margin: 0 2px;
    font-weight: bold;
    font-size: 16px;
    color: #888;
}

.typing span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes blink {
    0% {
        opacity: 0.2;
    }

    20% {
        opacity: 1;
    }

    100% {
        opacity: 0.2;
    }
}

@media (max-width: 1024px) {
    .top-section-layout {
        grid-template-columns: 200px 1fr;
    }
    
    .product-grid-layout {
        grid-template-columns: repeat(4, 1fr); 
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
    
    .product-grid-layout {
        grid-template-columns: repeat(2, 1fr); 
    }
    
    .tab-item {
        font-size: 1em; 
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

    .chatbot-wrapper {
        bottom: 20px;
        right: 20px;
    }

    .chat-window {
        position: fixed;
        bottom: 0;
        right: 0;
        width: 100%;
        height: 80vh;
        border-radius: 20px 20px 0 0;
    }
}
</style>