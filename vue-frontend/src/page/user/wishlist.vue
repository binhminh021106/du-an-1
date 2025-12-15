<script setup>
import { ref, onMounted, watch } from 'vue'; 
import { useStore } from 'vuex';
import { wishlist, removeItemFromWishlist } from "../../store/wishlistStore.js"; 
import apiService from '../../apiService.js';
import Swal from 'sweetalert2';

const store = useStore();
const isLoading = ref(true);
// [NEW] Biến cục bộ để hiển thị, tách biệt hoàn toàn với Store
const displayItems = ref([]);

// --- CONFIG & UTILS ---
const SERVER_URL = 'http://127.0.0.1:8000'; 

const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  background: '#fff',
  color: '#333',
  iconColor: '#009981', 
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
    if (!path) return 'https://placehold.co/300x300?text=No+Img';
    if (path.startsWith('http') || path.startsWith('blob:')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return `${SERVER_URL}/${cleanPath}`;
};

const formatCurrency = (v) => {
    if(!v) return "0 ₫";
    return Number(v).toLocaleString("vi-VN") + "\u00A0₫";
}

const calculateDiscount = (price, original) => {
    if (!original || original <= price) return 0;
    return Math.round(((original - price) / original) * 100);
};

// --- DATA ENRICHMENT ---
const getProductPrice = (product) => {
    if (product.price && Number(product.price) > 0) return Number(product.price);
    if (product.variants && product.variants.length > 0) {
        return Math.min(...product.variants.map(v => Number(v.price)));
    }
    return 0;
};

const getProductStock = (product) => {
    if (product.variants && product.variants.length > 0) {
        return product.variants.reduce((sum, v) => sum + (Number(v.stock) || 0), 0);
    }
    return Number(product.stock) || 0;
};

// [NEW] Hàm kiểm tra trực tiếp từ Server để quyết định có hiện Empty State không
const verifyEmptyState = async () => {
    try {
        // Gọi API kiểm tra danh sách thật sự trên server
        const response = await apiService.get('/wishlist');
        const serverData = response.data.data || [];
        
        if (serverData.length === 0) {
            // TRƯỜNG HỢP 1: Server xác nhận trống thật -> Hiện thông báo trống
            displayItems.value = [];
            isLoading.value = false;
        } else {
            // TRƯỜNG HỢP 2: Server có dữ liệu -> Vẫn giữ loading
            // Chờ Store hoặc Watcher cập nhật lại dữ liệu để hiển thị
            // Không set isLoading = false ở đây để tránh hiện thông báo trống sai
            
            // [Fallback] Nếu Store bị lag quá lâu, ta có thể fill tạm dữ liệu từ check này
            if (displayItems.value.length === 0) {
                 displayItems.value = serverData.map(item => ({
                    id: item.product?.id || item.product_id,
                    product_id: item.product?.id || item.product_id,
                    name: item.product?.name,
                    image_url: item.product?.image_url || item.product?.thumbnail_url || item.product?.image,
                    price: Number(item.product?.price || 0),
                    stock: item.product?.stock,
                    product: item.product
                 }));
                 isLoading.value = false;
            }
        }
    } catch (e) {
        console.warn("Lỗi kiểm tra Empty State:", e);
        // Nếu lỗi mạng, tắt loading để hiện Empty (hoặc Error state nếu có)
        isLoading.value = false;
    }
};

const syncAndEnrichData = async () => {
  // 1. Nếu Store có dữ liệu -> Xử lý hiển thị ngay
  if (wishlist.value && wishlist.value.length > 0) {
      const localList = wishlist.value.map(item => ({ ...item }));
      displayItems.value = localList; 

      // Enrichment logic (Lấy ảnh, giá chi tiết)
      const promises = localList.map(async (item) => {
        if (item.product) {
            item.name = item.name || item.product.name;
            const pImg = item.product.image_url || item.product.thumbnail_url || item.product.thumbnail || item.product.image;
            item.image_url = item.image_url || pImg; 
            item.price = item.price || item.product.price;
        }
        try {
          const realProductId = item.product_id || (item.product ? item.product.id : item.id);
          const response = await apiService.get(`/product/${realProductId}`);
          const productData = response.data.data || response.data; 
          if (productData) {
            item.image_url = productData.image_url || productData.thumbnail_url || item.image_url;
            item.name = productData.name;
            item.price = getProductPrice(productData);
            item.stock = getProductStock(productData);
            item.variants = productData.variants || [];
            item.brand = productData.brand; 
            item.average_rating = productData.average_rating || 5;
            item.sold_count = productData.sold_count || 0;
            item.original_price = productData.original_price || item.price;
            item.created_at = productData.created_at;
          }
        } catch (e) {}
        return item;
      });

      await Promise.all(promises);
      displayItems.value = [...localList];
      isLoading.value = false; // Tắt loading khi đã có dữ liệu
      return;
  }

  // 2. Nếu Store rỗng -> Kiểm tra kỹ trước khi báo Empty
  const token = localStorage.getItem('auth_token') || localStorage.getItem('authToken');
  
  if (token) {
      // Đã đăng nhập: Gọi hàm check server, KHÔNG set isLoading = false ngay
      await verifyEmptyState();
  } else {
      // Chưa đăng nhập: Chắc chắn rỗng (theo LocalStorage)
      displayItems.value = [];
      isLoading.value = false;
  }
};

// [WATCHER] Theo dõi Store wishlist
watch(wishlist, (newVal) => {
    // Luôn chạy sync khi wishlist thay đổi
    syncAndEnrichData();
}, { immediate: true, deep: true }); 

onMounted(() => {
    // Fallback an toàn: nếu sau 5s vẫn loading (do mạng lag hoặc lỗi), tắt loading
    setTimeout(() => {
        if (isLoading.value) isLoading.value = false;
    }, 5000);
});

// --- ACTIONS ---
const handleRemove = async (item) => {
    const result = await Swal.fire({
        title: 'Bỏ thích?',
        text: `Bạn muốn xóa "${item.name}" khỏi danh sách yêu thích?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Bỏ thích',
        cancelButtonText: 'Hủy',
        iconColor: '#d33',
    });

    if (result.isConfirmed) {
        removeItemFromWishlist(item.id);
        displayItems.value = displayItems.value.filter(i => i.id !== item.id);
        
        // Nếu xóa hết, set lại empty state
        if (displayItems.value.length === 0) {
            // Có thể gọi verifyEmptyState() hoặc set luôn
            isLoading.value = false;
        }
        
        Toast.fire({ icon: 'success', title: 'Đã xóa sản phẩm' });
    }
};

// [DELETED] moveItemToCart function removed as requested
</script>

<template>
  <div class="wishlist-page">
    <div class="container">
      <div class="wishlist-header mb-4">
        <h2 class="fw-bold text-uppercase text-dark border-start border-4 border-success ps-3">
            Danh sách yêu thích <span v-if="!isLoading" class="text-muted fs-5 fw-normal">({{ displayItems.length }} sản phẩm)</span>
        </h2>
      </div>

      <!-- [UPDATED] SKELETON LOADING GRID CHUYÊN NGHIỆP -->
      <div v-if="isLoading && displayItems.length === 0" class="row g-3">
        <div v-for="n in 4" :key="n" class="col-6 col-md-4 col-lg-3">
             <div class="product-card-pro border shadow-sm position-relative h-100 bg-white" style="pointer-events: none;">
                <!-- Giả lập Icon Heart -->
                <div class="position-absolute top-0 end-0 m-2 p-1 bg-white rounded-circle shadow-sm z-index-2" style="width: 35px; height: 35px;">
                    <div class="shimmer w-100 h-100 rounded-circle"></div>
                </div>
                <!-- [EDIT] Giả lập Ảnh với chữ THINKHUB -->
                <div class="card-img-top-wrapper position-relative shimmer d-flex align-items-center justify-content-center" style="height: 220px;">
                    <span class="fw-bold text-muted opacity-25 fs-3 user-select-none">THINKHUB</span>
                </div>
                <!-- Giả lập Nội dung -->
                <div class="card-body p-3 d-flex flex-column">
                    <div class="shimmer mb-2 rounded" style="height: 12px; width: 40%;"></div> <!-- Brand -->
                    <div class="shimmer mb-2 rounded" style="height: 18px; width: 90%;"></div> <!-- Name line 1 -->
                    <div class="shimmer mb-3 rounded" style="height: 18px; width: 60%;"></div> <!-- Name line 2 -->
                    
                    <div class="d-flex mb-3">
                        <div class="shimmer me-2 rounded" style="height: 14px; width: 20px;"></div> <!-- Star -->
                        <div class="shimmer rounded" style="height: 14px; width: 60px;"></div> <!-- Sold -->
                    </div>
                    
                    <!-- [DELETED] Skeleton Button removed -->
                    <div class="mt-auto">
                        <div class="shimmer mb-3 rounded" style="height: 24px; width: 50%;"></div> <!-- Price -->
                    </div>
                </div>
             </div>
        </div>
      </div>

      <!-- PRODUCT GRID -->
      <div v-else-if="displayItems.length" class="row g-3 fade-in">
        <div v-for="item in displayItems" :key="item.id" class="col-6 col-md-4 col-lg-3">
            
            <!-- PRODUCT CARD PRO -->
            <div class="product-card-pro border shadow-sm position-relative h-100 bg-white">
                <!-- Badges -->
                <div class="badges-overlay position-absolute top-0 start-0 p-2 z-index-2 d-flex flex-column gap-1">
                    <span v-if="calculateDiscount(item.price, item.original_price) > 0" class="badge bg-danger rounded-pill shadow-sm">
                        -{{ calculateDiscount(item.price, item.original_price) }}%
                    </span>
                    <span v-if="item.stock <= 0" class="badge bg-secondary rounded-pill shadow-sm">HẾT HÀNG</span>
                </div>

                <!-- [UPDATED] Remove Button -> Heart Icon -->
                <button class="btn btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-2 remove-btn-absolute"
                    @click.stop="handleRemove(item)" title="Bỏ yêu thích">
                    <i class="fas fa-heart text-danger"></i>
                </button>

                <!-- Image Wrapper -->
                <div class="card-img-top-wrapper overflow-hidden position-relative">
                    <router-link :to="`/products/${item.product_id || item.id}`">
                        <img :src="getImageUrl(item.image_url)" class="card-img-top product-img" :alt="item.name"
                             @error="$event.target.src = 'https://placehold.co/300x300?text=No+Img'">
                    </router-link>
                </div>

                <!-- Card Body -->
                <div class="card-body p-3 d-flex flex-column">
                    <small class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                        {{ item.brand?.name || 'THƯƠNG HIỆU' }}
                    </small>
                    <h6 class="card-title fw-bold text-dark text-truncate-2 mb-2" style="min-height: 40px;">
                        <router-link :to="`/products/${item.product_id || item.id}`" class="text-decoration-none text-dark hover-theme">
                            {{ item.name }}
                        </router-link>
                    </h6>
                    <div class="d-flex align-items-center mb-2 small text-muted">
                        <div class="d-flex text-warning me-2">
                            <i class="fas fa-star" style="font-size: 0.8rem;"></i>
                            <span class="ms-1 text-dark fw-bold">{{ item.average_rating || 5 }}</span>
                        </div>
                        <span class="border-start ps-2">Đã bán {{ item.sold_count || 0 }}</span>
                    </div>
                    
                    <!-- Price (đẩy xuống đáy nhờ mt-auto) -->
                    <div class="mt-auto mb-3">
                        <div class="d-flex align-items-baseline flex-wrap gap-2">
                            <span class="text-theme fw-bold fs-5">{{ formatCurrency(item.price) }}</span>
                            <span v-if="item.original_price > item.price" class="text-muted text-decoration-line-through small">
                                {{ formatCurrency(item.original_price) }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- [DELETED] Add to Cart button removed -->
                </div>
            </div>

        </div>
      </div>

      <!-- EMPTY STATE -->
      <div v-else class="empty-wishlist text-center py-5 fade-in bg-white rounded shadow-sm mt-3">
        <i class="fas fa-heart-broken mb-3" style="font-size: 4rem; color: #e9ecef;"></i>
        <h5 class="text-muted">Danh sách yêu thích của bạn đang trống.</h5>
        <p class="text-secondary small">Hãy tìm kiếm sản phẩm yêu thích và thêm vào đây nhé!</p>
        <router-link to="/Shop" class="btn btn-theme px-4 py-2 mt-3 rounded-pill">
            Khám phá sản phẩm ngay
        </router-link>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

.wishlist-page {
  padding: 40px 0;
  background-color: #f4f6f8;
  min-height: 100vh;
}

/* --- THEME COLORS --- */
.text-theme { color: #009981 !important; }
.btn-theme {
    background-color: #009981;
    border-color: #009981;
    color: white;
    transition: all 0.3s ease;
}
.btn-theme:hover:not(:disabled) {
    background-color: #007f6b;
    border-color: #007f6b;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 153, 129, 0.3);
}
.btn-theme:disabled {
    background-color: #a0c4be;
    border-color: #a0c4be;
}
.hover-theme:hover { color: #009981 !important; }

/* --- PRODUCT CARD PRO STYLES (MATCHING REFERENCE) --- */
.product-card-pro {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.product-card-pro:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
}

.card-img-top-wrapper {
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    padding: 0; /* [FIX] Xóa padding để ảnh full width */
    position: relative;
    overflow: hidden; /* [FIX] Đảm bảo ảnh zoom không bị tràn ra ngoài */
}

.product-img {
    width: 100%; /* [FIX] Full width */
    height: 100%;
    object-fit: contain; /* Giữ contain để thấy hết sản phẩm, hoặc dùng cover nếu muốn lấp đầy tuyệt đối */
    transition: transform 0.5s ease;
}

.product-card-pro:hover .product-img {
    transform: scale(1.1); /* Zoom effect */
}

/* Remove Button Absolute */
.remove-btn-absolute {
    z-index: 5;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid #eee;
}
.remove-btn-absolute:hover {
    background-color: #fee2e2;
    transform: scale(1.1);
    color: #dc3545 !important;
}

/* Text Truncate */
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
}

/* --- SKELETON LOADING --- */
.skeleton-card {
    height: 380px;
    background-color: #e0e0e0;
    border-radius: 12px;
}

.shimmer {
    background: #f6f7f8;
    background-image: linear-gradient(to right, #f6f7f8 0%, #edeef1 20%, #f6f7f8 40%, #f6f7f8 100%);
    background-repeat: no-repeat;
    background-size: 800px 100%; 
    animation: placeholderShimmer 1.5s linear infinite forwards;
}

@keyframes placeholderShimmer {
    0% { background-position: -468px 0; }
    100% { background-position: 468px 0; }
}

.fade-in {
    animation: fadeIn 0.4s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-img-top-wrapper {
        height: 180px;
    }
}
</style>