<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from 'vuex';
import apiService from '../../apiService'; 
// Import store wishlist để dùng tính năng yêu thích giống Product Detail
import { isInWishlist, toggleWishlist } from "../../store/wishlistStore.js"; 
import Swal from 'sweetalert2';

const route = useRoute();
const router = useRouter();
const store = useStore();

const BACKEND_URL = 'http://127.0.0.1:8000';

const products = ref([]);
const relatedProducts = ref([]);
const isLoading = ref(false);
const searchQuery = ref(route.query.q || '');

// --- HELPER FUNCTIONS (Giống Product Detail) ---

// 1. Xử lý ảnh
const getImageUrl = (path) => {
    if (!path) return 'https://placehold.co/300x300?text=No+Img';
    if (path.startsWith('http') || path.startsWith('https') || path.startsWith('blob:')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return `${BACKEND_URL}/${cleanPath}`;
};

// 2. Format tiền
const formatCurrency = (num) => {
    if (num === null || num === undefined || isNaN(num)) return "0 ₫";
    return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(num);
};

// 3. Tính giảm giá
const calculateDiscount = (price, original) => {
    if (!original || original <= price) return 0;
    return Math.round(((original - price) / original) * 100);
};

// 4. Check sản phẩm mới (trong 30 ngày)
const isNewProduct = (createdAt) => {
    if (!createdAt) return false;
    const date = new Date(createdAt);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays <= 30;
};

// 5. Xử lý Toggle Favorite
const toggleFavorite = (productItem) => {
    const added = toggleWishlist(productItem);
    if (added) {
        Swal.fire({
            toast: true, position: 'top-end', icon: 'success', 
            title: 'Đã thêm vào yêu thích', showConfirmButton: false, timer: 1500
        });
    } else {
        Swal.fire({
            toast: true, position: 'top-end', icon: 'info', 
            title: 'Đã xóa khỏi yêu thích', showConfirmButton: false, timer: 1500
        });
    }
};

const navigateToProduct = (productId) => {
    router.push(`/products/${productId}`);
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// --- CORE LOGIC ---

const fetchData = async () => {
    isLoading.value = true;
    try {
        const query = searchQuery.value.trim();
        
        // [QUAN TRỌNG] Gọi API với tham số keyword để Scout (Backend) xử lý tìm kiếm
        // Không fetch toàn bộ rồi filter ở client nữa -> Tối ưu hiệu năng
        const response = await apiService.get('/products', {
            params: {
                keyword: query
            }
        });

        const data = response.data.data || response.data;
        products.value = Array.isArray(data) ? data : [];

        // Lấy sản phẩm liên quan (Gợi ý ngẫu nhiên nếu kết quả ít)
        // Gọi API riêng để lấy list ngẫu nhiên hoặc mới nhất làm gợi ý
        if (products.value.length < 4) {
            const relatedRes = await apiService.get('/products', { params: { per_page: 8 } });
            const all = relatedRes.data.data || relatedRes.data;
            // Lọc bỏ những sp đã có trong kết quả tìm kiếm
            relatedProducts.value = all.filter(p => !products.value.find(existing => existing.id === p.id))
                                       .sort(() => 0.5 - Math.random()) // Shuffle
                                       .slice(0, 4);
        } else {
            relatedProducts.value = [];
        }

    } catch (error) {
        console.error("Lỗi tìm kiếm:", error);
        products.value = [];
    } finally {
        isLoading.value = false;
    }
};

watch(() => route.query.q, (newQ) => {
    searchQuery.value = newQ || '';
    fetchData();
});

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div class="search-page container py-5">
        <!-- BREADCRUMB -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><router-link to="/" class="text-decoration-none text-muted">Trang chủ</router-link></li>
                <li class="breadcrumb-item active" aria-current="page">Tìm kiếm</li>
            </ol>
        </nav>

        <div class="search-header mb-4 pb-3 border-bottom">
            <h2 class="fw-bold">Kết quả tìm kiếm cho: "<span class="text-theme">{{ searchQuery }}</span>"</h2>
            <p class="text-muted mb-0">Tìm thấy <strong>{{ products.length }}</strong> sản phẩm phù hợp</p>
        </div>

        <!-- LOADING -->
        <div v-if="isLoading" class="text-center py-5">
            <div class="spinner-border text-theme" role="status"></div>
            <p class="mt-2 text-muted">Đang tìm kiếm...</p>
        </div>

        <!-- KẾT QUẢ TÌM KIẾM -->
        <div v-else>
            
            <div v-if="products.length > 0" class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <div class="col" v-for="p in products" :key="p.id">
                    <!-- PRODUCT CARD (STYLE ĐỒNG BỘ VỚI DETAIL) -->
                    <div class="product-card-pro border shadow-sm position-relative h-100">
                        
                        <!-- Badges -->
                        <div class="badges-overlay position-absolute top-0 start-0 p-2 z-index-2 d-flex flex-column gap-1">
                            <span v-if="calculateDiscount(p.price, p.original_price) > 0" class="badge bg-danger rounded-pill shadow-sm">
                                -{{ calculateDiscount(p.price, p.original_price) }}%
                            </span>
                            <span v-if="isNewProduct(p.created_at)" class="badge bg-primary rounded-pill shadow-sm">NEW</span>
                            <span v-if="p.sold_count > 100" class="badge bg-warning text-dark rounded-pill shadow-sm">HOT</span>
                        </div>

                        <!-- Wishlist Button -->
                        <button class="btn btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-2 wish-btn-visible"
                            @click.stop="toggleFavorite(p)" 
                            :title="isInWishlist(p.id) ? 'Bỏ thích' : 'Yêu thích'">
                            <i :class="['bi', isInWishlist(p.id) ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary']"></i>
                        </button>

                        <!-- Image -->
                        <div class="card-img-top-wrapper overflow-hidden position-relative" @click="navigateToProduct(p.id)">
                            <img :src="getImageUrl(p.image_url || p.thumbnail_url)" class="card-img-top product-img" :alt="p.name"
                                @error="$event.target.src = 'https://placehold.co/300x300?text=No+Img'">
                        </div>

                        <!-- Body -->
                        <div class="card-body p-3 d-flex flex-column" @click="navigateToProduct(p.id)">
                            <small class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                {{ p.brand?.name || 'THƯƠNG HIỆU' }}
                            </small>
                            <h6 class="card-title fw-bold text-dark text-truncate-2 mb-2" style="height: 40px;">
                                {{ p.name }}
                            </h6>
                            
                            <!-- Rating & Sold -->
                            <div class="d-flex align-items-center mb-2 small text-muted">
                                <div class="d-flex text-warning me-2">
                                    <i class="bi bi-star-fill" style="font-size: 0.8rem;"></i>
                                    <span class="ms-1 text-dark fw-bold">{{ p.average_rating || 5 }}</span>
                                </div>
                                <span class="border-start ps-2">Đã bán {{ p.sold_count || 0 }}</span>
                            </div>

                            <!-- Price -->
                            <div class="mt-auto">
                                <div class="d-flex align-items-baseline flex-wrap gap-2">
                                    <template v-if="p.min_price && p.max_price && p.min_price !== p.max_price">
                                        <span class="text-theme fw-bold fs-6">{{ formatCurrency(p.min_price) }} - {{ formatCurrency(p.max_price) }}</span>
                                    </template>
                                    <template v-else>
                                        <span class="text-theme fw-bold fs-5">{{ formatCurrency(p.price || p.min_price) }}</span>
                                        <span v-if="p.original_price > (p.price || p.min_price)" class="text-muted text-decoration-line-through small">
                                            {{ formatCurrency(p.original_price) }}
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EMPTY STATE -->
            <div v-else class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-search" style="font-size: 4rem; color: #e9ecef;"></i>
                </div>
                <h3>Rất tiếc, không tìm thấy sản phẩm nào!</h3>
                <p class="text-muted">Hãy thử tìm kiếm với từ khóa chung chung hơn hoặc kiểm tra lỗi chính tả.</p>
                <router-link to="/" class="btn btn-theme text-white mt-3 px-4">Về trang chủ</router-link>
            </div>
        </div>

        <div class="divider-section my-5 border-top" v-if="relatedProducts.length > 0"></div>

        <!-- SẢN PHẨM LIÊN QUAN (FALLBACK KHI ÍT KẾT QUẢ) -->
        <div class="related-section" v-if="relatedProducts.length > 0">
            <h4 class="fw-bold mb-4">Có thể bạn quan tâm</h4>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-4">
                <div class="col" v-for="rp in relatedProducts" :key="rp.id">
                    <!-- Reuse Card Structure -->
                    <div class="product-card-pro border shadow-sm position-relative h-100">
                        <div class="badges-overlay position-absolute top-0 start-0 p-2 z-index-2 d-flex flex-column gap-1">
                            <span v-if="calculateDiscount(rp.price, rp.original_price) > 0" class="badge bg-danger rounded-pill shadow-sm">-{{ calculateDiscount(rp.price, rp.original_price) }}%</span>
                        </div>
                        <button class="btn btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-2 wish-btn-visible"
                            @click.stop="toggleFavorite(rp)">
                            <i :class="['bi', isInWishlist(rp.id) ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary']"></i>
                        </button>
                        <div class="card-img-top-wrapper overflow-hidden position-relative" @click="navigateToProduct(rp.id)">
                            <img :src="getImageUrl(rp.image_url || rp.thumbnail_url)" class="card-img-top product-img" :alt="rp.name">
                        </div>
                        <div class="card-body p-3 d-flex flex-column" @click="navigateToProduct(rp.id)">
                            <small class="text-muted text-uppercase mb-1" style="font-size: 0.75rem;">{{ rp.brand?.name || 'THƯƠNG HIỆU' }}</small>
                            <h6 class="card-title fw-bold text-dark text-truncate-2 mb-2" style="height: 40px;">{{ rp.name }}</h6>
                            <div class="mt-auto">
                                <span class="text-theme fw-bold fs-5">{{ formatCurrency(rp.price || rp.min_price) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
.search-page {
    font-family: 'Inter', sans-serif;
    color: #333;
    min-height: 80vh;
}

.text-theme {
    color: #009981 !important;
}

.btn-theme {
    background-color: #009981;
    border-color: #009981;
}
.btn-theme:hover {
    background-color: #007a67;
}

/* --- PRODUCT CARD STYLES (COPY FROM PRODUCT DETAIL) --- */
.product-card-pro {
    transition: all 0.3s ease;
    cursor: pointer;
    background: #fff;
    overflow: hidden;
    border-radius: 12px;
}

.product-card-pro:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
    border-color: #b2f5ea !important;
}

.card-img-top-wrapper {
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    padding: 10px;
    overflow: hidden;
}

.product-img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.5s ease;
}

.product-card-pro:hover .product-img {
    transform: scale(1.1);
}

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wish-btn-visible {
    z-index: 5;
    transition: all 0.2s;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.wish-btn-visible:hover {
    background-color: #fee2e2;
    transform: scale(1.1);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .card-img-top-wrapper {
        height: 180px;
    }
    .product-card-pro h6 {
        font-size: 0.9rem;
    }
}
</style>