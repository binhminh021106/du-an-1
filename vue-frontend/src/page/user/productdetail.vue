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

// --- CẤU HÌNH THÔNG BÁO (SWEETALERT & TOAST) ---
const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer)
    toast.addEventListener('mouseleave', Swal.resumeTimer)
  }
});

// Hàm thông báo thông minh
const notify = (type, message, title = '') => {
    if (type === 'success') {
        // Thành công -> Dùng Toast nhẹ nhàng
        Toast.fire({
            icon: 'success',
            title: message
        });
    } else if (type === 'error' || type === 'warning') {
        // Lỗi hoặc Cảnh báo -> Dùng Modal để gây chú ý
        Swal.fire({
            icon: type,
            title: title || (type === 'error' ? 'Lỗi!' : 'Chú ý!'),
            text: message,
            confirmButtonText: 'Đã hiểu',
            confirmButtonColor: 'var(--primary-color)', // Đồng bộ màu nút
        });
    } else {
        // Info -> Dùng Toast
        Toast.fire({
            icon: 'info',
            title: message
        });
    }
};

// --- CẤU HÌNH SERVER ---
const SERVER_URL = 'http://127.0.0.1:8000';    
const USE_STORAGE = false; 

// Hàm xử lý link ảnh
const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/500x500?text=No+Img';
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`;
};

// --- STATE QUẢN LÝ SẢN PHẨM & BIẾN THỂ ---
const product = ref(null);
const reviews = ref([]);
const quantity = ref(1);
const loading = ref(true);
const isFavorite = ref(false);

// State cho logic chọn biến thể
const groupedAttributes = ref({}); 
const selectedOptions = ref({});   
const availableVariant = ref(null); 

const selectedImage = ref('');
const allProducts = ref([]);
const relatedProducts = ref([]);
const tradeInSearchTerm = ref('');
const tradeInResultsVisible = ref(false);

// --- LOGIC XỬ LÝ BIẾN THỂ (CORE - STEP BY STEP) ---

// 1. Kiểm tra xem nút có nên bị disable không
const isOptionDisabled = (attributeName, valueId) => {
  if (!product.value || !product.value.variants) return true;
  
  const currentSelectionCheck = { ...selectedOptions.value, [attributeName]: valueId };
  
  const exists = product.value.variants.some(variant => {
    if (!variant.attributesMap) return false;
    
    return Object.keys(currentSelectionCheck).every(key => {
        if (!currentSelectionCheck[key]) return true;
        if (!variant.attributesMap[key]) return false;
        return String(variant.attributesMap[key]) === String(currentSelectionCheck[key]);
    });
  });

  return !exists; 
};

// 2. Xử lý khi click chọn Attribute
const selectAttribute = (attributeName, valueId) => {
    if (selectedOptions.value[attributeName] === valueId) {
        const newSelection = { ...selectedOptions.value };
        delete newSelection[attributeName];
        selectedOptions.value = newSelection;
        availableVariant.value = null; 
        return;
    }

    let nextSelection = { ...selectedOptions.value, [attributeName]: valueId };
    
    Object.keys(nextSelection).forEach(key => {
        if (key === attributeName) return; 

        const isCompatible = product.value.variants.some(v => 
            v.attributesMap && 
            String(v.attributesMap[attributeName]) === String(valueId) &&
            String(v.attributesMap[key]) === String(nextSelection[key])
        );

        if (!isCompatible) {
            delete nextSelection[key];
        }
    });

    selectedOptions.value = nextSelection;
    quantity.value = 1; 
    findMatchingVariant();
};

// 3. Tìm variant khớp hoàn toàn
const findMatchingVariant = () => {
    if(!product.value || !product.value.variants) return;

    const totalKeys = Object.keys(groupedAttributes.value).length;
    const selectedKeys = Object.keys(selectedOptions.value).length;

    if (selectedKeys < totalKeys) {
        availableVariant.value = null;
        return;
    }

    const match = product.value.variants.find(variant => {
        if (!variant.attributesMap) return false;
        return Object.keys(groupedAttributes.value).every(attrName => {
            return String(variant.attributesMap[attrName]) === String(selectedOptions.value[attrName]);
        });
    });

    availableVariant.value = match || null;
};

// 4. Xử lý dữ liệu thô từ API
const processProductData = (data) => {
    if (!data.variants || data.variants.length === 0) {
        const defaultVariant = { 
            id: 'default', 
            price: data.price || 0, 
            stock: data.stock || 0, 
            original_price: data.original_price || 0,
            attributesMap: {} 
        };
        data.variants = [defaultVariant];
        groupedAttributes.value = {};
        selectedOptions.value = {};
        availableVariant.value = defaultVariant;
        return;
    }

    let groups = {};
    data.variants.forEach(variant => {
        variant.attributesMap = {}; 
        const attrs = variant.attribute_values || variant.attributeValues || [];

        if(attrs.length > 0) {
            attrs.forEach(av => {
                const attrName = av.attribute ? av.attribute.name : (av.attribute_name || 'Thuộc tính');
                const valId = av.id;
                const valName = av.value;

                if (!groups[attrName]) groups[attrName] = [];
                if (!groups[attrName].find(x => x.id === valId)) {
                    groups[attrName].push({ id: valId, value: valName });
                }
                variant.attributesMap[attrName] = valId;
            });
        }
    });

    groupedAttributes.value = groups;
    
    selectedOptions.value = {};
    availableVariant.value = null;
};

const tradeInSearchResults = computed(() => {
  if (tradeInSearchTerm.value.length < 2) return [];
  const term = tradeInSearchTerm.value.toLowerCase();
  return allProducts.value.filter(p => (p.name || '').toLowerCase().includes(term)).slice(0, 5);
});

const viewAllOffers = () => {
  notify('info', 'Tính năng đang phát triển!');
};

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

    if (!data) throw new Error("No data");

    const extraImages = (data.images || []).map(img => {
       if (typeof img === 'string') return img;
       return img.url || img.image_url || img.path;
    }).filter(Boolean);

    data.gallery_images = [data.image_url || data.thumbnail_url, ...extraImages].filter(Boolean);
    data.gallery_images = [...new Set(data.gallery_images)];
    if (data.gallery_images.length === 0) data.gallery_images = ['https://placehold.co/500x500?text=No+Img'];

    product.value = data;
    selectedImage.value = product.value.gallery_images[0];
    
    processProductData(data);

    if (typeof isInWishlist === 'function') {
        isFavorite.value = isInWishlist(product.value.id);
    }
    
    try {
        const reviewRes = await apiService.get(`/reviews?productId=${id}`);
        reviews.value = reviewRes.data.data || reviewRes.data || [];
    } catch (e) {}

  } catch (error) {
    console.error("Lỗi:", error);
    notify('error', 'Không thể tải thông tin sản phẩm', 'Lỗi kết nối');
  } finally {
    loading.value = false;
  }
};

const selectImage = (imageUrl) => selectedImage.value = imageUrl;

const navigateToProduct = (productId) => {
  router.push(`/products/${productId}`);
  tradeInResultsVisible.value = false;
};

const formatCurrency = (num) => {
  if (num === null || num === undefined || isNaN(num)) return "0 ₫";
  return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(num);
};

const decreaseQty = () => { if (quantity.value > 1) quantity.value--; };
const increaseQty = () => {
  if (!availableVariant.value) return;
  const max = availableVariant.value.stock ?? 1;
  if (quantity.value < max) quantity.value++;
};
const validateQty = () => {
  if (!availableVariant.value) return;
  const max = availableVariant.value.stock ?? 1;
  if (quantity.value > max) quantity.value = max;
  if (quantity.value < 1) quantity.value = 1;
};

// --- ACTION THÊM GIỎ HÀNG (ĐÃ CẬP NHẬT THÔNG BÁO) ---
const onAddToCart = (productItem) => {
  // Kiểm tra quan trọng -> Dùng SweetAlert Modal (Warning)
  if (!availableVariant.value) {
    notify('warning', 'Vui lòng chọn đầy đủ phân loại (Màu sắc, kích thước...) trước khi thêm.', 'Chưa chọn phân loại');
    return;
  }
  
  if (availableVariant.value.stock <= 0) {
    notify('error', 'Sản phẩm này tạm hết hàng, vui lòng quay lại sau.', 'Hết hàng');
    return;
  }
    
  store.dispatch('addToCart', { 
    product: productItem, 
    variant: availableVariant.value, 
    quantity: quantity.value 
  });
    
  // Thành công -> Dùng Toast (Success)
  notify('success', `Đã thêm ${quantity.value} sản phẩm vào giỏ!`);
};

// --- ACTION YÊU THÍCH (ĐÃ CẬP NHẬT THÔNG BÁO) ---
const toggleFavorite = (productItem) => {
    if (!productItem || typeof toggleWishlist !== 'function') return;
    const added = toggleWishlist(productItem);
    isFavorite.value = added;
    
    // Thành công nhẹ -> Dùng Toast
    if (added) {
        notify('success', 'Đã thêm vào danh sách yêu thích!');
    } else {
        notify('info', 'Đã xóa khỏi danh sách yêu thích.');
    }
};

onMounted(() => {
  const id = route.params.id;
  if (id) loadProductById(id);
  fetchAllProducts();
});

watch(() => route.params.id, (newId) => {
  if (newId) loadProductById(newId);
});

watchEffect(() => {
  if (product.value && allProducts.value.length > 0) {
    relatedProducts.value = allProducts.value.slice(0, 5);
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
        <section class="related-products-section mb-5" v-if="relatedProducts.length > 0">
          <h4 class="section-title">Sản phẩm liên quan</h4>
          <div class="horizontal-scroll-container">
            <div v-for="rp in relatedProducts" :key="rp.id" class="product-card-simple" @click="navigateToProduct(rp.id)">
              <img :src="getImageUrl(rp.image_url)" class="card-img"/>
              <h5 class="card-name">{{ rp.name }}</h5>
              <p class="card-price">{{ formatCurrency(rp.price) }}</p>
            </div>
          </div>
        </section>
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
/* FIX: Chuyển biến vào class cha của component thay vì :root để tránh lỗi scope */
.product-detail-page { 
    background-color: #f8f9fa; 
    border-radius: 8px; 
    padding: 16px; 
    --primary-color: rgb(0, 153, 129);
    --primary-hover: rgb(0, 117, 99);
    --primary-light: rgba(0, 153, 129, 0.1);
    --trade-in-red: #d70018;
}

/* CHIPS ATTRIBUTES */
.chip-btn { border: 1px solid #e0e0e0; background: #fff; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 500; transition: all 0.2s ease; min-width: 70px; text-align: center; color: #333; position: relative; }
.chip-btn:hover:not(.disabled) { border-color: #aaa; transform: translateY(-1px); }
.chip-btn.active { border-color: var(--primary-color); background-color: #fff; color: var(--primary-color); font-weight: 600; box-shadow: 0 0 0 1px var(--primary-color) inset; position: relative; }
.chip-btn.active::after { content: ""; position: absolute; top: -1px; right: -1px; width: 12px; height: 12px; background: var(--primary-color); clip-path: polygon(100% 0, 0 0, 100% 100%); border-top-right-radius: 7px; }

/* NÚT DISABLED KHÔNG XÁM */
.chip-btn.disabled { 
    opacity: 0.3; background-color: #fff; color: #333; cursor: not-allowed; border: 1px dashed #ccc; text-decoration: none; 
}
.chip-btn.disabled::before {
    content: ""; position: absolute; top: 50%; left: 10%; right: 10%; border-top: 1px solid #999; transform: rotate(-15deg); opacity: 0.6;
}

.qty-group button { border-color: #ced4da; color: #555; }
.qty-group button:hover { background-color: #f8f9fa; color: #000; }
.qty-group input { border-color: #ced4da; background: #fff; }

.product-info-box { background-color: #fff; padding: 25px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
.main-image-wrapper { border: 1px solid #f0f0f0; border-radius: 12px; overflow: hidden; background: #fff; }
.main-product-image { width: 100%; aspect-ratio: 1/1; object-fit: contain; }
.thumbnail-gallery { display: flex; gap: 10px; flex-wrap: wrap; }
.thumbnail-item { width: 70px; height: 70px; border: 2px solid transparent; border-radius: 8px; object-fit: cover; cursor: pointer; opacity: 0.8; background: #fff; }
.thumbnail-item.active { opacity: 1; border-color: var(--primary-color); }

/* STYLE BUTTON CHÍNH */
.btn-primary-green { background-color: var(--primary-color); border-color: var(--primary-color); color: white; font-weight: 600; padding: 12px 24px; border-radius: 8px; transition: all 0.3s; }
.btn-primary-green:hover { background-color: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 153, 129, 0.3); }

/* DISABLED MÀU XANH MỜ */
.btn-primary-green:disabled { 
    background-color: rgba(0, 153, 129, 0.5); /* Màu xanh thương hiệu mờ 50% */
    border-color: rgba(0, 153, 129, 0.1); 
    color: #fff; cursor: not-allowed; box-shadow: none; transform: none; 
}

/* NÚT YÊU THÍCH VUÔNG NHỎ */
.icon-btn { width: 48px; min-width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 8px; padding: 0; }
.section-title { border-bottom: 2px solid var(--primary-color); padding-bottom: 10px; margin-bottom: 20px; font-weight: 700; color: #333; font-size: 1.1rem; }

.product-description-full { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #f0f0f0; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
.description-content { line-height: 1.6; color: #444; }
.description-content img { max-width: 100%; height: auto; display: block; margin: 10px auto; border-radius: 8px; }

.horizontal-scroll-container { display: flex; overflow-x: auto; gap: 15px; padding-bottom: 10px; }
.product-card-simple { flex: 0 0 160px; border: 1px solid #f0f0f0; padding: 10px; border-radius: 10px; cursor: pointer; text-align: center; transition: all 0.2s; background: #fff; }
.product-card-simple:hover { box-shadow: 0 5px 15px rgba(0,0,0,0.08); transform: translateY(-3px); }
.product-card-simple img { width: 100%; height: 120px; object-fit: contain; margin-bottom: 10px; }
.trade-in-section, .related-products-section, .product-reviews { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #f0f0f0; margin-bottom: 20px; }
.trade-in-section { background-color: #2c3e50; color: #fff; text-align: center; }
.trade-in-searchbar input { width: 100%; padding: 12px; border-radius: 8px; border: none; outline: none; }
</style>