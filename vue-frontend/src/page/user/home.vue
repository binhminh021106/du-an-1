<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
// [FIX 1] Import useRouter
import { useRouter } from 'vue-router'; 
import { useStore } from 'vuex';
import apiService from '../../apiService.js';
import { addToCart } from '../../store/cartStore.js';

// --- INIT STORE ---
const store = useStore();
const router = useRouter(); // Giờ lệnh này sẽ hoạt động

// --- CẤU HÌNH ---
const SERVER_URL = 'http://127.0.0.1:8000'; 
const USE_STORAGE = false; 
const CHATBOT_API_URL = 'http://127.0.0.1:8000/api/chatbot'; // URL Chatbot

const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/300x300?text=No+Img';
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`;
};

// --- ICON DANH MỤC ---
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
  return '<i class="fas fa-box-open" style="color: #bdc3c7;"></i>';
};

const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

// --- MAIN STATE ---
const categories = ref([]);
const brands = ref([]);
const slides = ref([]);
const products = ref([]); 
const newsList = ref([]);
const vouchers = ref([]);
const users = ref([]); // [FIX 2] Khai báo biến users

const activeCategoryId = ref(null);
const currentSlide = ref(0);
let interval = null;
const productContainer = ref(null);

// --- CHATBOT STATE (FIX Lỗi thiếu biến) ---
const isChatOpen = ref(false);
const chatInput = ref("");
const chatMessages = ref([]);
const isChatLoading = ref(false);
const chatBodyRef = ref(null);

// --- FETCH DATA ---
const fetchData = async () => {
  try {
    const [catRes, slideRes, prodRes, userRes, newsRes, brandRes] = await Promise.all([
      apiService.get(`/categories`),
      apiService.get(`/slides`),
      apiService.get(`/products?include=category,variants`),
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

const getDisplayPrice = (product) => {
  if (product.variants && product.variants.length > 0) {
    const minPrice = Math.min(...product.variants.map(v => Number(v.price || 0)));
    return minPrice;
  }
  return Number(product.price || 0); 
};

// Hàm dùng cho template (đổi tên để khớp với code bạn dùng trong template)
const getProductPrice = (product) => getDisplayPrice(product);

const getMinPrice = (variants) => {
  if (!variants || variants.length === 0) return 0;
  return Math.min(...variants.map(v => Number(v.price || 0)));
}

// --- ACTIONS ---
const onAddToCart = (product) => {
  let variant = null;
  let priceToUse = getDisplayPrice(product);

  if (product.variants && product.variants.length > 0) {
    variant = product.variants.find(v => Number(v.price) === priceToUse);
    if (!variant) {
      variant = product.variants[0];
      priceToUse = Number(variant.price);
    }
  } else {
    variant = { id: 'default', name: 'Mặc định', price: priceToUse, stock: 999 };
  }

  if (!variant) return alert('Lỗi: Không tìm thấy biến thể sản phẩm.');
  addToCart(product, variant, 1);
  alert(`Đã thêm vào giỏ: ${product.name} (Giá: ${formatCurrency(priceToUse)})`);
};

const saveVoucher = (code) => {
    navigator.clipboard.writeText(code).then(() => {
        alert(`Đã sao chép mã: ${code}`);
    });
};

// --- CHATBOT FUNCTIONS ---
const toggleChat = () => {
    isChatOpen.value = !isChatOpen.value;
    if (isChatOpen.value) scrollToBottom();
};

const scrollToBottom = () => {
    nextTick(() => {
        if (chatBodyRef.value) chatBodyRef.value.scrollTop = chatBodyRef.value.scrollHeight;
    });
};

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

        if (!response.ok) throw new Error("Lỗi kết nối Server AI");
        const data = await response.json();
        const filters = data.ai_data || {}; 
        
        let realProducts = products.value;

        if (filters.keyword) {
            const k = filters.keyword.toLowerCase();
            realProducts = realProducts.filter(p => {
                const nameMatch = (p.name || '').toLowerCase().includes(k);
                const category = categories.value.find(c => String(c.id) === String(p.category_id || p.category?.id));
                const catMatch = category ? category.name.toLowerCase().includes(k) : false;
                const brand = brands.value.find(b => String(b.id) === String(p.brand_id || p.brand?.id));
                const brandMatch = brand ? brand.name.toLowerCase().includes(k) : false;
                return nameMatch || catMatch || brandMatch;
            });
        }
        
        if (filters.min_price) realProducts = realProducts.filter(p => getDisplayPrice(p) >= filters.min_price);
        if (filters.max_price) realProducts = realProducts.filter(p => getDisplayPrice(p) <= filters.max_price);

        chatMessages.value.push({
            role: 'ai',
            text: realProducts.length > 0 ? `Tìm thấy ${realProducts.length} sản phẩm:` : "Không tìm thấy sản phẩm phù hợp.",
            products: realProducts 
        });

    } catch (error) {
        console.error(error);
        chatMessages.value.push({ role: 'ai', text: "Lỗi kết nối Server AI hoặc xử lý dữ liệu.", products: [] });
    } finally {
        isChatLoading.value = false;
        scrollToBottom();
    }
};

onMounted(async () => {
  await fetchData();
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
                        <router-link v-for="category in categories" :key="category.id"
                            :to="{ path: '/Shop', query: { categoryId: category.id } }"
                            class="category-item-clean text-uppercase"
                            :class="{ active: String(category.id) === String(activeCategoryId) }"
                            @click="setActiveCategory(category.id)">
                            <span v-if="category.icon" v-html="category.icon" class="cat-icon"></span>
                            <span>{{ category.name }}</span>
                        </router-link>
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
                    <img :src="getImageUrl(brands[0].imageUrl)" alt="Brand Banner">
                </a>
            </section>

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
                        <div class="product-card-basic" v-for="product in products" :key="product.id">
                            
                            <div class="image-wrapper">
                                <span class="badge-new">MỚI</span>
                                <img :src="getImageUrl(product.thumbnail_url || product.image_url)" :alt="product.name">
                                
                                <div class="hover-overlay">
                                    <button class="action-btn" @click="$router.push(`/products/${product.id}`)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="action-btn" @click="onAddToWishlist(product)">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                    <button class="action-btn" @click="onAddToCart(product)">
                                        <i class="fas fa-cart-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="product-info">
                                <h3 class="product-name" @click="$router.push(`/products/${product.id}`)" style="cursor: pointer;">
                                    {{ product.name }}
                                </h3>
                                <div class="price-row">
                                    <span class="price">{{ formatCurrency(getMinPrice(product.variants)) }}</span>
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
                                <img :src="getImageUrl(news.image_url || news.image)" :alt="news.title"
                                    onerror="this.src='https://placehold.co/400x300?text=Lỗi+Ảnh'">
                            </div>
                            <div class="news-content">
                                <h3 class="news-title">{{ news.title }}</h3>
                                <p class="news-excerpt">{{ (news.excerpt || news.content || '').slice(0, 100) }}...</p>
                                <router-link :to="`/blog/${news.slug}`" class="read-more-link">Xem thêm &rarr;</router-link>
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
                                    <img :src="getImageUrl(p.thumbnail_url)" alt="img">
                                    <div class="cp-info"><div class="cp-name">{{ p.name }}</div><div class="cp-price">{{ formatCurrency(getProductPrice(p)) }}</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="isChatLoading" class="chat-message ai"><div class="chat-avatar"><i class="fas fa-robot"></i></div><div class="msg-content typing"><span>.</span><span>.</span><span>.</span></div></div>
                </div>
                <div class="chat-footer"><input type="text" v-model="chatInput" placeholder="Nhập yêu cầu..." @keyup.enter="sendChatMessage"><button @click="sendChatMessage" :disabled="isChatLoading"><i class="fas fa-paper-plane"></i></button></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* (Giữ nguyên phần CSS của bạn vì không có lỗi ở đây) */
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
.image-wrapper { position: relative; width: 100%; padding-top: 100%; background: #fff; overflow: hidden; border-radius: 6px; margin-bottom: 15px; border: 1px solid #eee; }
.image-wrapper img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; padding: 10px; transition: transform 0.5s ease; }
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