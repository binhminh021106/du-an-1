<script setup>
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex'; // 1. Import Vuex
// Import store wishlist (giữ nguyên logic quản lý wishlist)
import { wishlist, removeItemFromWishlist } from "../../store/wishlistStore.js"; 
import apiService from '../../apiService.js';
import Swal from 'sweetalert2'; // Import SweetAlert2

const store = useStore(); // 2. Khởi tạo store

// [NEW] Loading State for Skeleton
const isLoading = ref(true);

// --- CẤU HÌNH TOAST (Theo yêu cầu) ---
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

// --- CẤU HÌNH SERVER ẢNH ---
const SERVER_URL = 'http://127.0.0.1:8000'; 
const USE_STORAGE = false; 

// 1. Hàm xử lý hiển thị ảnh
const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/70x70?text=No+Img';
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`;
};

const formatPrice = (v) => {
    if(!v) return "0 ₫";
    return Number(v).toLocaleString("vi-VN") + "\u00A0₫";
}

// --- HELPER MỚI: Tính giá và kho an toàn ---
const getProductPrice = (product) => {
    // Nếu có giá gốc thì dùng, nếu không thì lấy giá thấp nhất trong variants
    if (product.price && Number(product.price) > 0) return Number(product.price);
    if (product.variants && product.variants.length > 0) {
        return Math.min(...product.variants.map(v => Number(v.price)));
    }
    return 0;
};

const getProductStock = (product) => {
    // Ưu tiên tính tổng tồn kho của các biến thể
    if (product.variants && product.variants.length > 0) {
        return product.variants.reduce((sum, v) => sum + (Number(v.stock) || 0), 0);
    }
    // Fallback về stock gốc
    return Number(product.stock) || 0;
};

// 2. Hàm làm mới dữ liệu từ API
const enrichWishlistData = async () => {
  if (!wishlist.value || !wishlist.value.length) {
      isLoading.value = false;
      return;
  }

  const promises = wishlist.value.map(async (item) => {
    try {
      const response = await apiService.get(`/product/${item.id}`);
      const productData = response.data.data || response.data; 

      if (productData) {
        // Cập nhật thông tin mới nhất
        item.image_url = productData.image_url || productData.thumbnail_url || item.image_url;
        item.name = productData.name;
        
        // FIX QUAN TRỌNG: Tính toán lại giá và kho thay vì gán trực tiếp
        item.price = getProductPrice(productData);
        item.stock = getProductStock(productData);
        
        item.variants = productData.variants || []; 
      }
    } catch (e) {
      console.warn(`Lỗi cập nhật SP ${item.id} trong wishlist`, e);
    }
  });

  await Promise.all(promises);
  // [FIX] Delay to show skeleton
  setTimeout(() => { isLoading.value = false }, 1500);
};

// --- LIFECYCLE ---
onMounted(() => {
    // Nếu wishlist rỗng thì tắt loading luôn
    if (wishlist.value.length === 0) {
        isLoading.value = false;
    } else {
        enrichWishlistData();
    }
});

// --- ACTIONS ---
// SỬA: Nhận vào nguyên object item thay vì chỉ itemId để lấy name
const handleRemove = async (item) => {
    const result = await Swal.fire({
        title: 'Xác nhận xóa',
        html: `Bạn có chắc muốn xóa <strong>"${item.name}"</strong> khỏi danh sách yêu thích?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#009981',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
        reverseButtons: true
    });

    if (result.isConfirmed) {
        removeItemFromWishlist(item.id);
        Toast.fire({
            icon: 'success',
            title: 'Đã xóa khỏi yêu thích'
        });
    }
};

const moveItemToCart = (item) => {
    // Tự động chọn variant đầu tiên hoặc tạo variant mặc định
    const variant = item.variants && item.variants.length > 0 
        ? item.variants[0]
        : { id: 'default', name: 'Mặc định', price: item.price, stock: item.stock || 999 };

    // 3. Sử dụng Vuex Action để thêm vào giỏ
    store.dispatch('addToCart', {
        product: item,
        variant: variant,
        quantity: 1
    });

    // Xóa khỏi wishlist sau khi thêm vào giỏ
    removeItemFromWishlist(item.id); 
    
    // Hiển thị toast thông báo thành công
    Toast.fire({
        icon: 'success',
        title: `Đã chuyển "${item.name}" sang Giỏ hàng!`
    });
};
</script>

<template>
  <div class="wishlist-page">
    <div class="container">
      <div class="wishlist-card">
        <h2>❤️ Danh sách Yêu thích <span v-if="!isLoading">({{ wishlist.length }})</span></h2>

        <!-- [NEW] SKELETON LOADING -->
        <div v-if="isLoading">
            <table class="wishlist-table">
                <thead>
                    <tr>
                        <th width="50%">Sản phẩm</th>
                        <th width="20%">Giá</th>
                        <th width="30%" class="text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="n in 3" :key="n" class="skeleton-row-container">
                        <td>
                            <div class="product-info">
                                <div class="skeleton-box img-box shimmer" style="width: 70px; height: 70px;">
                                     <span class="skeleton-placeholder-text-tiny">ThinkHub</span>
                                </div>
                                <div class="product-details w-100">
                                    <div class="skeleton-box text-box w-75 mb-2 shimmer"></div>
                                    <div class="skeleton-box text-box w-25 shimmer"></div>
                                </div>
                            </div>
                        </td>
                        <td><div class="skeleton-box text-box w-50 shimmer"></div></td>
                        <td class="actions-cell">
                            <div class="action-buttons">
                                <div class="skeleton-box btn-box shimmer" style="width: 120px;"></div>
                                <div class="skeleton-box btn-icon-box shimmer"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else-if="wishlist.length" class="fade-in">
            <table class="wishlist-table">
            <thead>
                <tr>
                <th width="50%">Sản phẩm</th>
                <th width="20%">Giá</th>
                <th width="30%" class="text-right">Hành động</th>
                </tr>
            </thead>

            <tbody>
                <tr v-for="item in wishlist" :key="item.id">
                
                <!-- CỘT SẢN PHẨM -->
                <td>
                    <div class="product-info">
                    <router-link :to="`/products/${item.id}`">
                        <img 
                        :src="getImageUrl(item.image_url || item.thumbnail_url)" 
                        alt="Product"
                        @error="$event.target.src='https://placehold.co/70x70?text=No+Img'" 
                        />
                    </router-link>
                    <div class="product-details">
                        <router-link :to="`/products/${item.id}`" class="product-name">
                        {{ item.name }}
                        </router-link>
                        <span class="stock-status" v-if="item.stock > 0">Còn hàng</span>
                        <span class="stock-status out" v-else>Hết hàng</span>
                    </div>
                    </div>
                </td>
                
                <td class="price">{{ formatPrice(item.price) }}</td>
                
                <td class="actions-cell">
                    <div class="action-buttons">
                        <button class="add-cart-btn" @click="moveItemToCart(item)">
                            <i class="fas fa-cart-plus"></i> Thêm vào giỏ
                        </button>
                        <!-- SỬA: Truyền nguyên object item vào hàm handleRemove -->
                        <button class="remove-btn" @click="handleRemove(item)" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
                </tr>
            </tbody>
            </table>
            
             <div class="wishlist-footer">
                <router-link to="/" class="continue-shopping">Tiếp tục mua sắm</router-link>
             </div>
        </div>

        <div v-else class="empty-wishlist fade-in">
            <i class="fas fa-heart-broken"></i>
            <p>Danh sách yêu thích của bạn đang trống.</p>
            <router-link to="/Shop" class="continue-shopping">Khám phá sản phẩm ngay</router-link>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

/* --- LAYOUT CHUNG --- */
.wishlist-page {
  padding: 50px 15px;
  background-color: #f8f9fa;
  min-height: 100vh;
}

.wishlist-card {
  background: #fff;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
  max-width: 1100px;
  margin: auto;
}

h2 {
  color: #2c3e50;
  margin-bottom: 25px;
  font-weight: 700;
  border-bottom: 2px solid #eee;
  padding-bottom: 15px;
}

/* --- TABLE STYLES --- */
.wishlist-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 15px;
}

.wishlist-table th {
  background: transparent;
  color: #7f8c8d;
  padding: 10px;
  text-align: left;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 12px;
  letter-spacing: 0.5px;
}
.wishlist-table th.text-right {
    text-align: right;
    padding-right: 20px;
}

.wishlist-table td {
  padding: 15px 10px;
  background: #fff;
  border-top: 1px solid #f1f2f6;
  border-bottom: 1px solid #f1f2f6;
  vertical-align: middle;
}

.wishlist-table td:first-child {
  border-left: 1px solid #f1f2f6;
  border-top-left-radius: 8px;
  border-bottom-left-radius: 8px;
}
.wishlist-table td:last-child {
  border-right: 1px solid #f1f2f6;
  border-top-right-radius: 8px;
  border-bottom-right-radius: 8px;
}

/* --- PRODUCT INFO --- */
.product-info {
  display: flex;
  align-items: center;
  gap: 15px;
}

.product-info img {
  width: 70px;
  height: 70px;
  border-radius: 8px;
  border: 1px solid #eee;
  object-fit: cover;
  transition: transform 0.2s;
}

.product-info img:hover {
  transform: scale(1.05);
}

.product-details {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.product-name {
  font-weight: 600;
  color: #2c3e50;
  text-decoration: none;
  font-size: 15px;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-name:hover {
  color: #009981;
}

.stock-status {
    font-size: 12px;
    color: #27ae60;
    background: #eafaf1;
    padding: 2px 8px;
    border-radius: 4px;
    width: fit-content;
}
.stock-status.out {
    color: #e74c3c;
    background: #fdedec;
}

.price {
  color: #009981;
  font-weight: 700;
  font-size: 16px;
}

/* --- ACTION BUTTONS --- */
.actions-cell {
    text-align: right;
}

.action-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    align-items: center;
}

.add-cart-btn {
    background: #009981;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.add-cart-btn:hover {
    background: #007f6b;
    transform: translateY(-2px);
}

.remove-btn {
  width: 35px;
  height: 35px;
  border-radius: 50%;
  border: 1px solid #eee;
  background: white;
  color: #bdc3c7;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-btn:hover {
  background: #fff0f0;
  color: #e74c3c;
  border-color: #ffcccc;
}

/* --- EMPTY STATE --- */
.empty-wishlist {
  text-align: center;
  padding: 80px 20px;
  color: #b2bec3;
}
.empty-wishlist i {
  font-size: 60px;
  margin-bottom: 20px;
  opacity: 0.5;
}

.continue-shopping {
  color: #009981;
  text-decoration: none;
  font-weight: 600;
  border: 1px solid #009981;
  padding: 10px 25px;
  border-radius: 6px;
  margin-top: 15px;
  display: inline-block;
  transition: all 0.2s;
}
.continue-shopping:hover {
  background: #009981;
  color: white;
}

.wishlist-footer {
    margin-top: 30px;
    border-top: 2px dashed #eee;
    padding-top: 20px;
}

/* Responsive Mobile */
@media (max-width: 768px) {
    .product-info {
        flex-direction: column;
        align-items: flex-start;
    }
    .wishlist-table th, .wishlist-table td {
        padding: 10px 5px;
    }
    .add-cart-btn span {
        display: none;
    }
    .add-cart-btn {
        padding: 8px 10px;
    }
}

/* ------------------------------------------- */
/* [NEW] SKELETON LOADING STYLES               */
/* ------------------------------------------- */

.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Hiệu ứng Shimmer (Ánh sáng chạy qua) */
.shimmer {
  background: #f6f7f8;
  background-image: linear-gradient(
    to right,
    #f6f7f8 0%,
    #edeef1 20%,
    #f6f7f8 40%,
    #f6f7f8 100%
  );
  background-repeat: no-repeat;
  background-size: 800px 100%; 
  animation: placeholderShimmer 1.5s linear infinite forwards;
}

@keyframes placeholderShimmer {
  0% { background-position: -468px 0; }
  100% { background-position: 468px 0; }
}

.skeleton-box {
    background-color: #eee;
    border-radius: 4px;
}

.skeleton-box.img-box {
    background-color: #ddd;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.skeleton-box.text-box {
    height: 14px;
    border-radius: 4px;
}

.skeleton-box.btn-box {
    height: 35px;
    border-radius: 6px;
}

.skeleton-box.btn-icon-box {
    width: 35px;
    height: 35px;
    border-radius: 50%;
}

.skeleton-placeholder-text-tiny {
    font-size: 0.6rem;
    font-weight: 800;
    color: #e5e7eb;
    text-transform: uppercase;
}

.skeleton-row-container td {
    vertical-align: top;
    padding-top: 20px;
}
</style>