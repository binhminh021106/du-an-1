<script setup>
import { ref, watch, onMounted, computed, watchEffect } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex"; 
import apiService from '../../apiService.js';
import { isInWishlist, toggleWishlist } from "../../store/wishlistStore.js";
// Import SweetAlert2 (Đảm bảo project đã cài: npm install sweetalert2)
import Swal from 'sweetalert2';

const route = useRoute();
const router = useRouter();
const store = useStore(); 

const SERVER_URL = 'http://127.0.0.1:8000'; 
const USE_STORAGE = false; 

const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/500x500?text=No+Img';
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`;
};

// --- STATE ---
const product = ref(null);
const reviews = ref([]);
const quantity = ref(1);
const loading = ref(true);
const isFavorite = ref(false);

// [FIX] Khai báo biến bundleDeals để tránh lỗi template
const bundleDeals = ref([]); 

const selectedImage = ref('');
const allProducts = ref([]);
const relatedProducts = ref([]);
const tradeInSearchTerm = ref('');
const tradeInResultsVisible = ref(false);

// --- DATA MẪU ---
const paymentOffers = ref([
  { id: 1, partner: "HSBC", logo_url: "https://upload.wikimedia.org/wikipedia/commons/5/5a/HSBC_logo_%282018%29.svg", description: "Giảm <b>2 triệu</b> khi thanh toán bằng thẻ tín dụng HSBC." },
  { id: 2, partner: "Home Credit", logo_url: "https://upload.wikimedia.org/wikipedia/commons/8/86/Home_Credit_logo.svg", description: "Ưu đãi <b>0% lãi suất</b> khi trả góp qua Home Credit." },
  { id: 3, partner: "MOMO", logo_url: "https://upload.wikimedia.org/wikipedia/commons/0/0c/MoMo_Logo.png", description: "Giảm <b>200K</b> khi thanh toán qua ví MOMO." },
  { id: 4, partner: "TPBank", logo_url: "https://upload.wikimedia.org/wikipedia/commons/4/4d/TPBank_logo.svg", description: "Nhận <b>50K hoàn tiền</b> khi thanh toán bằng thẻ TPBank EVO." }
]);

// --- COMPUTED ---
const activeVariant = computed(() => {
  if (!product.value || !product.value.variants || !product.value.variants.length) return null;
  return product.value.variants[selectedVariantIndex.value];
});

// Logic lọc gợi ý tìm kiếm Trade-in
const tradeInSearchResults = computed(() => {
  if (!tradeInSearchTerm.value || tradeInSearchTerm.value.length < 2) return [];
  const term = tradeInSearchTerm.value.toLowerCase();
  return allProducts.value
    .filter(p => (p.name || '').toLowerCase().includes(term))
    .slice(0, 6);
});

// --- HELPER FUNCTIONS ---
const formatCurrency = (num) => {
  if (num === null || num === undefined || isNaN(num)) return "0 ₫";
  return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(num);
};

// Hàm tính giá hiển thị (Lấy giá min của variants)
const getProductDisplayPrice = (productItem) => {
  if (productItem.variants && productItem.variants.length > 0) {
    return Math.min(...productItem.variants.map(v => Number(v.price)));
  }
  return Number(productItem.price || 0);
};

// [FIX] Tạo alias để Template gọi getProductPrice không bị lỗi
const getProductPrice = getProductDisplayPrice;

const validateQty = () => {
  if (!activeVariant.value) return;
  const max = activeVariant.value.stock ?? 1;
  if (quantity.value > max) quantity.value = max;
  if (quantity.value < 1) quantity.value = 1;
  quantity.value = Number(quantity.value);
};

const decreaseQty = () => { if (quantity.value > 1) quantity.value--; };
const increaseQty = () => { 
    if (activeVariant.value && quantity.value < activeVariant.value.stock) quantity.value++; 
};

// --- ACTIONS ---
const viewAllOffers = () => alert("Tính năng đang cập nhật");

const selectVariant = (index) => {
  selectedVariantIndex.value = index;
  validateQty();
};

const selectImage = (imageUrl) => { selectedImage.value = imageUrl; };

// Chuyển hướng khi click vào sản phẩm liên quan
const navigateToProduct = (productId) => {
  router.push(`/products/${productId}`);
  window.scrollTo(0, 0); // Cuộn lên đầu trang
};

// [FIX] Hàm xử lý khi chọn sản phẩm gợi ý Trade-in
const selectTradeInProduct = (productId) => {
    router.push(`/products/${productId}`);
    tradeInResultsVisible.value = false;
    tradeInSearchTerm.value = '';
};

// --- API CALLS ---
const fetchAllProducts = async () => {
  try {
 const res = await apiService.get(`/products`);
    allProducts.value = res.data.data || res.data || [];
  } catch (err) {
    console.error("Lỗi tải danh sách:", err);
  }
};

const loadProductById = async (id) => {
  try {
    loading.value = true;
    const productRes = await apiService.get(`/product/${id}`);
    const data = productRes.data.data || productRes.data;

    if (!data) throw new Error("Không có dữ liệu sản phẩm");

    // Xử lý variants nếu rỗng
    if (!data.variants || !data.variants.length) {
      data.variants = [{ id: 'default', price: data.price || 0, original_price: 0, stock: data.stock || 0, name: 'Tiêu chuẩn' }];
    }

    // Chuẩn hóa dữ liệu variant
    data.variants.forEach((v, i) => {
      v.stock = Number(v.stock) || 0;
      v.price = Number(v.price) || 0;
      v.original_price = Number(v.original_price) || v.price;
      v.id = v.id || i;
    });

    // Xử lý hình ảnh
    const extraImages = (data.images || []).map(img => 
       (typeof img === 'string') ? img : (img.url || img.image_url || img.path)
    ).filter(Boolean);

    data.gallery_images = [data.image_url || data.thumbnail_url, ...extraImages].filter(Boolean);
    data.gallery_images = [...new Set(data.gallery_images)]; // Unique
    if (data.gallery_images.length === 0) data.gallery_images = ['https://placehold.co/500x500/009981/white?text=No+Image'];

    product.value = data;
    selectedVariantIndex.value = 0;
    selectedImage.value = data.gallery_images[0];
    quantity.value = 1;

    // Check wishlist
    if (typeof isInWishlist === 'function') {
        isFavorite.value = isInWishlist(product.value.id);
    }

    // Load reviews
    const reviewRes = await apiService.get(`/reviews?productId=${id}`);
    reviews.value = reviewRes.data.data || reviewRes.data || [];

  } catch (error) {
    console.error("Lỗi:", error);
    notify('error', 'Không thể tải thông tin sản phẩm', 'Lỗi kết nối');
  } finally {
    loading.value = false;
  }
};

const onAddToCart = (productItem) => {
  if (!activeVariant.value) return alert("Vui lòng chọn phiên bản sản phẩm.");
  
  store.dispatch('addToCart', { 
    product: productItem, 
    variant: availableVariant.value, 
    quantity: quantity.value 
  });
  alert(`Đã thêm ${quantity.value} x ${productItem.name} vào giỏ hàng!`);
};

// --- ACTION YÊU THÍCH (ĐÃ CẬP NHẬT THÔNG BÁO) ---
const toggleFavorite = (productItem) => {
    if (!productItem || typeof toggleWishlist !== 'function') return;
    const added = toggleWishlist(productItem);
    isFavorite.value = added;
    alert(added ? `Đã thêm ${productItem.name} vào Wishlist! ❤️` : `Đã xóa ${productItem.name} khỏi Wishlist!`);
};

// --- WATCHERS & HOOKS ---
onMounted(() => {
  const id = route.params.id;
  if (id) loadProductById(id);
  fetchAllProducts();
});

watch(() => route.params.id, (newId) => {
  if (newId) loadProductById(newId);
});

// [FIX] Cải thiện logic lấy sản phẩm liên quan
watchEffect(() => {
  if (product.value && allProducts.value.length > 0) {
    const currentId = product.value.id;
    // Lấy ID danh mục an toàn (xử lý cả trường hợp object hoặc id phẳng)
    const catId = product.value.category?.id || product.value.category_id;

    if (catId) {
      relatedProducts.value = allProducts.value
        .filter(p => {
            const pCatId = p.category?.id || p.category_id;
            return String(pCatId) === String(catId) && p.id !== currentId;
        })
        .slice(0, 5);
    }
  }
});
</script>

<template>
  <div class="container py-5 product-detail-page">
    <div v-if="!loading && product" class="row g-4 mb-5">

      <!-- Cột Trái: Hình ảnh -->
      <div class="col-lg-5">
        <div class="main-image-wrapper mb-3">
    <img :src="getImageUrl(selectedImage)" :alt="product.name" class="img-fluid rounded main-product-image" 
        @error="$event.target.src='https://placehold.co/500x500?text=No+Image'"/>
</div>

        <div class="thumbnail-gallery" v-if="product.gallery_images && product.gallery_images.length > 1">
          <img v-for="(image, index) in product.gallery_images" :key="index" 
             :src="getImageUrl(image)"
             class="thumbnail-item" :class="{ active: selectedImage === image }"
             @click="selectImage(image)" />
        </div>
      </div>

      <!-- Cột Phải: Thông tin & Options -->
      <div class="col-lg-7">
        <div class="product-info-box h-100">

          <h2 class="fw-bold mb-3 product-title">{{ product.name }}</h2>

          <div class="d-flex align-items-center mb-3 text-muted small">
            <div class="me-3">
              <i class="bi bi-star-fill text-warning"></i> {{ product.average_rating || 5 }} / 5
            </div>
            <div>(Đã bán: {{ product.sold_count || 0 }})</div>
          </div>

          <!-- HIỂN THỊ GIÁ -->
          <div class="price-section mb-4">
            <template v-if="availableVariant">
                <span class="fs-2 fw-bold text-danger me-2">
                  {{ formatCurrency(availableVariant.price) }}
                </span>
                <span v-if="availableVariant.original_price > availableVariant.price"
                  class="text-muted text-decoration-line-through fs-5">
                  {{ formatCurrency(availableVariant.original_price) }}
                </span>
            </template>
            <template v-else>
                <div v-if="product.min_price && product.max_price && product.min_price !== product.max_price">
                     <span class="fs-2 fw-bold text-danger me-2">
                      {{ formatCurrency(product.min_price) }} - {{ formatCurrency(product.max_price) }}
                    </span>
                </div>
                <div v-else>
                     <span class="fs-2 fw-bold text-danger me-2">
                      {{ formatCurrency(product.min_price || product.price) }}
                    </span>
                </div>
            </template>
          </div>

          <!-- PHẦN CHỌN ATTRIBUTES -->
          <div class="attributes-section mb-4">
             <div v-if="Object.keys(groupedAttributes).length === 0" class="text-muted fst-italic">
                <!-- Không hiện gì nếu không có attribute -->
             </div>

             <div v-for="(values, attrName) in groupedAttributes" :key="attrName" class="attribute-group mb-4">
                <label class="fw-bold mb-2 d-block text-dark">{{ attrName }}: 
                    <span class="fw-normal text-primary ms-1" v-if="selectedOptions[attrName]">
                        {{ values.find(v => v.id === selectedOptions[attrName])?.value }}
                    </span>
                </label>
                <div class="d-flex flex-wrap gap-2">
                    <button 
                        v-for="val in values" 
                        :key="val.id"
                        class="btn chip-btn"
                        :class="{ 
                            'active': selectedOptions[attrName] === val.id,
                            'disabled': isOptionDisabled(attrName, val.id)
                        }"
                        @click="selectAttribute(attrName, val.id)"
                        :disabled="isOptionDisabled(attrName, val.id)"
                    >
                        {{ val.value }}
                    </button>
                </div>
             </div>
          </div>

          <!-- SỐ LƯỢNG (Chỉ hiện khi đã chọn) -->
          <div class="d-flex align-items-center mb-4" v-if="availableVariant">
            <span class="fw-semibold me-3 fs-6">Số lượng:</span>
            <div class="input-group qty-group" style="width: 140px;">
                <button class="btn btn-outline-secondary" @click="decreaseQty"><i class="bi bi-dash"></i></button>
                <input type="number" v-model.number="quantity" class="form-control text-center border-secondary" @change="validateQty" />
                <button class="btn btn-outline-secondary" @click="increaseQty"><i class="bi bi-plus"></i></button>
            </div>
            <span class="ms-3 small fw-semibold" :class="availableVariant.stock > 0 ? 'text-success' : 'text-danger'">
              ({{ availableVariant.stock > 0 ? `Sẵn hàng: ${availableVariant.stock}` : 'Hết hàng' }})
            </span>
          </div>

          <!-- KHU VỰC NÚT HÀNH ĐỘNG (DÀN NGANG) -->
          <div class="action-buttons mt-4 gap-3">
            <button class="btn btn-outline-danger icon-btn" @click="toggleFavorite(product)">
              <i :class="['bi', isFavorite ? 'bi-heart-fill' : 'bi-heart']"></i> 
            </button>

            <button class="btn btn-primary-green mt-4 btn-lg flex-grow-1" 
                @click="onAddToCart(product)" 
                :disabled="!availableVariant || availableVariant.stock <= 0">
              <i class="bi bi-cart-plus me-2"></i> 
              {{ !availableVariant ? 'Vui lòng chọn phân loại' : (availableVariant.stock > 0 ? 'Thêm vào giỏ hàng' : 'Tạm hết hàng') }}
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- MÔ TẢ FULL WIDTH -->
    <div v-if="!loading && product" class="row mt-4">
        <div class="col-12">
            <section class="product-description-full">
                <h4 class="section-title">📄 Mô tả sản phẩm</h4>
                <div class="description-content" v-html="product.description || 'Đang cập nhật...'"></div>
            </section>
        </div>
    </div>

    <!-- Trade-in & Reviews -->
    <div v-if="!loading && product" class="row mt-5">
      <div class="col-12">
        
       <section class="trade-in-section mb-5">
    <h3 class="fw-bold">Iphone - Giảm giá tới 50% </h3>
    <p class="subtitle">(Giá độc quyền)</p>

    <div class="trade-in-features">
        <div class="feature-item">
            <div class="icon-wrapper"><i class="bi bi-cash-coin"></i></div>
            Giá thu tốt nhất<br>thị trường
        </div>
        <div class="feature-item">
            <div class="icon-wrapper"><i class="bi bi-pencil-square"></i></div>
            Định giá nhanh chóng<br>Thủ tục đơn giản
        </div>
        <div class="feature-item">
            <div class="icon-wrapper"><i class="bi bi-gift"></i></div>
            Trợ giá thêm đến 1 triệu<br>cho thành viên
        </div>
    </div>

    <div class="trade-in-search-wrapper">
        <div class="trade-in-searchbar">
            <button class="upload-btn"><i class="bi bi-arrow-up"></i></button>
            <input type="text" class="search-input" 
                placeholder="Tìm sản phẩm bạn muốn lên đời..." 
                v-model="tradeInSearchTerm"
                @focus="tradeInResultsVisible = true" 
                @blur="setTimeout(() => tradeInResultsVisible = false, 200)" 
            />
        </div>

        <div class="trade-in-results" v-if="tradeInResultsVisible && tradeInSearchResults.length > 0">
            <ul>
                <li v-for="item in tradeInSearchResults" :key="item.id" @click="selectTradeInProduct(item.id)">
                    <img :src="getImageUrl(item.thumbnail_url || item.image_url)" alt="img" class="suggest-img">
                    <div class="suggest-info">
                        <span class="suggest-name">{{ item.name }}</span>
                        <span class="suggest-price">{{ formatCurrency(getProductPrice(item)) }}</span>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</section>

<section class="related-products-section mb-5" v-if="relatedProducts.length > 0">
  <h4 class="section-title">Sản phẩm liên quan</h4>
  <div class="horizontal-scroll-container">
    <div v-for="relatedProduct in relatedProducts" :key="relatedProduct.id" class="product-card-simple"
      @click="navigateToProduct(relatedProduct.id)">
      

      <img :src="getImageUrl(relatedProduct.thumbnail_url || relatedProduct.image_url)" 
           :alt="relatedProduct.name" class="card-img" 
           @error="$event.target.src='https://placehold.co/150x150?text=Product'"/>
      
      <h5 class="card-name" :title="relatedProduct.name">{{ relatedProduct.name }}</h5>
      
      <p class="card-price">{{ formatCurrency(getProductDisplayPrice(relatedProduct)) }}</p>
      
    </div>
  </div>
</section>

        <!-- Reviews -->
        <section class="product-reviews">
          <h4 class="section-title">💬 Đánh giá ({{ reviews.length }})</h4>
          <p v-if="!reviews.length" class="text-muted">Chưa có đánh giá nào.</p>
          <div v-else>
             <div v-for="r in reviews" :key="r.id" class="review-item">
                 <strong>{{ r.user_name || 'Người dùng' }}</strong>: {{ r.content }}
             </div>
          </div>
        </section>
      </div>
    </div>
    <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
  </div>
</template>

<style scoped>
:root {
  --primary-color: rgb(0, 153, 129);
  --primary-hover: rgb(0, 117, 99);
  --primary-light: rgba(0, 153, 129, 0.1);
  --trade-in-red: #d70018;
  --text-dark: #222;
  --text-gray: #666;
  --border-color: #eee;
}

/* --- LAYOUT CHUNG --- */
.product-detail-page {
  background-color: #f9f9f9;
  border-radius: 8px;
  padding: 16px;
}

.section-title {
  border-bottom: 2px solid var(--primary-color);
  padding-bottom: 10px;
  margin-bottom: 20px;
  font-weight: 600;
  color: var(--primary-color);
}

/* --- CỘT TRÁI: ẢNH SẢN PHẨM --- */
.main-image-wrapper {
  border: 1px solid var(--border-color);
  border-radius: 8px;
  overflow: hidden;
}

.main-product-image {
  width: 100%;
  aspect-ratio: 1 / 1;
  object-fit: contain;
  transition: transform 0.3s ease;
}

.main-product-image:hover {
  transform: scale(1.05);
}

.thumbnail-gallery {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.thumbnail-item {
  width: 70px;
  height: 70px;
  border: 2px solid #ddd;
  border-radius: 8px;
  object-fit: cover;
  cursor: pointer;
  opacity: 0.7;
  transition: all 0.2s ease;
}

.thumbnail-item:hover {
  opacity: 1;
  border-color: #aaa;
}

.thumbnail-item.active {
  opacity: 1;
  border-color: var(--primary-color);
  box-shadow: 0 0 5px var(--primary-light);
}

.product-description {
  background-color: #fff;
  padding: 25px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

/* --- CỘT PHẢI: THÔNG TIN SẢN PHẨM --- */
.product-info-box {
  background-color: #fff;
  padding: 25px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.product-title {
  color: var(--text-dark);
}

.price-section {
  background-color: #fdfdfd;
  border-bottom: 1px solid #f0f0f0;
  padding-bottom: 15px;
}

/* Variant Buttons */
.variant-options {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.variant-btn {
  border: 2px solid #ddd;
  background-color: #fff;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  padding: 5px 15px;
  text-align: left;
  font-weight: 500;
  transition: all 0.2s ease;
}

.variant-btn:hover {
  border-color: #aaa;
}

.variant-btn.active {
  border-color: var(--primary-color);
  background-color: var(--primary-light);
  box-shadow: 0 0 0 2px var(--primary-color);
}

.variant-price {
  font-size: 0.85em;
  font-weight: bold;
  color: var(--primary-color);
}

/* Quantity & Action Buttons */
.btn-qty {
  width: 40px;
  height: 40px;
  padding: 0;
  line-height: 40px;
}

.btn-primary-green {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
  color: white;
  font-weight: 600;
  padding: 10px 20px;
  transition: all 0.3s ease;
}

.btn-primary-green:hover {
  border-radius: 8px;
  box-shadow: 0 4px 10px rgba(0, 153, 129, 0.4);
  transform: translateY(-2px);
}

.btn-primary-green:disabled {
  background-color: #ccc;
  border-color: #ccc;
  cursor: not-allowed;
  box-shadow: none;
  transform: none;
}

/* --- ƯU ĐÃI THANH TOÁN --- */
.payment-offers-section {
  background-color: #fff;
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 20px 25px;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
}

.offers-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.offer-item {
  background-color: #f9fdfc;
  border: 1px solid #e0f2ef;
  border-radius: 10px;
  padding: 10px 15px;
  transition: all 0.2s ease;
}

.offer-item:hover {
  background-color: #f3fbf9;
  border-color: var(--primary-color);
  transform: translateX(3px);
}

.offer-logo {
  width: 38px;
  height: 38px;
  object-fit: contain;
  border-radius: 6px;
  background-color: white;
  padding: 4px;
  border: 1px solid #eee;
}

.offer-text {
  flex: 1;
  font-size: 0.9rem;
  color: #333;
  line-height: 1.5;
}

.btn-view-all {
  border: none;
  background: none;
  font-weight: 600;
  font-size: 0.9rem;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  cursor: pointer;
  color: var(--primary-color);
}

.btn-view-all:hover {
  text-decoration: underline;
}

/* --- KHUYẾN MÃI & BUNDLE --- */
.promotion-section-box {
  background-color: #f3f9ff;
  border: 1px solid #d0e6ff;
  border-radius: 10px;
  padding: 20px 25px;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
}

.promotion-section-box h2 {
  font-size: 1.25rem;
  font-weight: 700;
  color: #0056b3;
  margin-bottom: 15px;
}

.promotion-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.promo-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.promo-badge-num {
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  background-color: #007bff;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: bold;
  margin-top: 3px;
}

.promo-text {
  flex: 1;
  font-size: 0.9rem;
  color: #333;
  line-height: 1.5;
}

.promo-link {
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  color: var(--primary-color);
  margin-left: 5px;
}

.promo-link:hover {
  text-decoration: underline;
}

.bundle-deal-section {
  background: #fff8f8;
  border: 1px solid #ffe0e0;
  border-radius: 10px;
  padding: 20px;
  margin-top: 20px;
}

.bundle-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.bundle-header h2 {
  font-size: 1.25rem;
  color: var(--trade-in-red);
  font-weight: 700;
}

.bundle-products {
  display: flex;
  gap: 15px;
  overflow-x: auto;
  padding-bottom: 15px;
}

.bundle-item {
  flex: 0 0 160px;
  background: #fff;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 10px;
  text-align: center;
  transition: all 0.2s ease;
}

.bundle-item:hover {
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
  transform: translateY(-3px);
}

.bundle-item img {
  width: 100%;
  height: 120px;
  object-fit: contain;
  border-radius: 6px;
  margin-bottom: 8px;
}

.bundle-item h3 {
  font-size: 0.9rem;
  font-weight: 600;
  height: 2.5em;
  overflow: hidden;
  margin-bottom: 5px;
}

.bundle-item .price {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 8px;
}

.bundle-item .new-price {
  font-size: 1rem;
  font-weight: 700;
  color: var(--trade-in-red);
}

.bundle-item .old-price {
  font-size: 0.8rem;
  color: #888;
  text-decoration: line-through;
}

.btn-buy-now {
  background: var(--primary-color);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 6px 12px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-buy-now:hover {
  background: var(--primary-hover);
}

/* --- TRADE-IN & SEARCH --- */
.trade-in-section {
  background-color: #222;
  color: #fff;
  border-radius: 12px;
  padding: 25px 30px;
  text-align: center;
}

.trade-in-section h3 {
  font-size: 1.75rem;
  font-weight: 700;
}

.trade-in-section .subtitle {
  color: #ccc;
  font-size: 0.95rem;
  margin-bottom: 25px;
}

.trade-in-features {
  display: flex;
  justify-content: space-around;
  align-items: flex-start;
  gap: 15px;
  margin-bottom: 25px;
}

.feature-item {
  flex: 1;
  font-size: 0.9rem;
  line-height: 1.4;
}

.feature-item .icon-wrapper {
  background-color: var(--trade-in-red);
  width: 50px;
  height: 50px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 12px;
  font-size: 1.6rem;
}

/* Search Wrapper & Dropdown */
.trade-in-search-wrapper {
  position: relative;
  max-width: 600px;
  margin: 0 auto;
  z-index: 100;
}

.trade-in-searchbar {
  background-color: #555;
  border-radius: 12px;
  padding: 8px;
  display: flex;
  align-items: center;
}

.trade-in-searchbar .upload-btn {
  background-color: var(--trade-in-red);
  border: none;
  border-radius: 8px;
  color: white;
  width: 45px;
  height: 45px;
  font-size: 1.3rem;
  margin-right: 10px;
  flex-shrink: 0;
}

.trade-in-searchbar .search-input {
  flex: 1;
  background-color: #fff;
  color: #222;
  border-radius: 8px;
  padding: 12px 15px;
  border: none;
  width: 100%;
  font-size: 0.9rem;
  outline: none;
}

.trade-in-results {
  position: absolute;
  top: 105%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
  z-index: 101;
  max-height: 300px;
  overflow-y: auto;
}

.trade-in-results ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.trade-in-results li {
  display: flex;
  align-items: center;
  padding: 10px 15px;
  cursor: pointer;
  border-bottom: 1px solid #f0f0f0;
  transition: background 0.2s;
}

.trade-in-results li:hover {
  background-color: #f8f9fa;
}

.suggest-img {
  width: 45px;
  height: 45px;
  object-fit: cover;
  border-radius: 4px;
  margin-right: 12px;
  border: 1px solid #eee;
}

.suggest-info {
  display: flex;
  flex-direction: column;
  text-align: left;
}

.suggest-name {
  font-size: 14px;
  font-weight: 600;
  color: #333;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.suggest-price {
  font-size: 13px;
  color: var(--trade-in-red);
  font-weight: bold;
}

/* --- SẢN PHẨM LIÊN QUAN --- */
.related-products-section {
  background-color: #fff;
  padding: 25px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

.horizontal-scroll-container {
  display: flex;
  overflow-x: auto;
  gap: 16px;
  padding-bottom: 10px;
}

.product-card-simple {
  flex: 0 0 180px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 10px;
  text-align: center;
  cursor: pointer;
  transition: box-shadow 0.2s;
}

.product-card-simple:hover {
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

.product-card-simple .card-img {
  width: 100%;
  height: 150px;
  object-fit: contain;
  margin-bottom: 10px;
}

.product-card-simple .card-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: #333;
  height: 2.5em;
  overflow: hidden;
}

.product-card-simple .card-price {
  font-size: 1rem;
  font-weight: 700;
  color: var(--primary-color);
}

/* --- REVIEWS --- */
.product-reviews {
  background-color: #fff;
  padding: 25px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
}

.review-item {
  border-bottom: 1px solid #f0f0f0;
  padding: 15px 0;
}

.review-item:last-child {
  border-bottom: none;
}
</style>