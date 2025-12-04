<script setup>
import { onMounted, computed, watch } from "vue";
import { useStore } from "vuex";
import { useRouter } from "vue-router";
import Swal from 'sweetalert2'; // Import SweetAlert2

const store = useStore();
const router = useRouter();


const parsePrice = (value) => {
    if (typeof value === 'number') return value;
    if (typeof value === 'string') {
        return Number(value.replace(/[^0-9]/g, ''));
    }
    return 0;
};


const getVariantLabel = (item) => {
    if (item.variantName && item.variantName !== 'Mặc định') return item.variantName;
    if (item.variant_name && item.variant_name !== 'Mặc định') return item.variant_name;
    if (item.sku) return `SKU: ${item.sku}`;
    if (Array.isArray(item.attributes) && item.attributes.length > 0) {
        return item.attributes.map(a => `${a.name || ''}: ${a.value || ''}`).join(' - ');
    }
    return null;
};

// --- CẤU HÌNH API ---
const SERVER_URL = 'http://127.0.0.1:8000';
const USE_STORAGE = false;

const getImageUrl = (path) => {
    if (!path) return 'https://placehold.co/100x100?text=No+Img';
    if (path.startsWith('http')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`;
};

const formatPrice = (v) => {
    const price = parsePrice(v);
    if (isNaN(price)) return "0 ₫";
    return price.toLocaleString("vi-VN") + " ₫";
}

onMounted(() => {
    store.dispatch('enrichCartData');
});

// Debug: Log dữ liệu cart khi thay đổi
watch(cart, (newVal) => {
    if (newVal && newVal.length > 0) {
        console.log("🛒 Dữ liệu Giỏ hàng hiện tại:", newVal);
    }
});

// --- CẤU HÌNH SWEETALERT2 & TOAST ---
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

// --- ACTIONS ---

// [NÂNG CẤP] Xóa sản phẩm với SweetAlert2
const removeItem = (cartId) => {
    Swal.fire({
        title: 'Bạn chắc chắn chứ?',
        text: "Sản phẩm sẽ bị xóa khỏi giỏ hàng!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444', // Màu đỏ cho nút xóa
        cancelButtonColor: '#6b7280', // Màu xám cho nút hủy
        confirmButtonText: 'Vâng, xóa đi!',
        cancelButtonText: 'Hủy bỏ'
    }).then((result) => {
        if (result.isConfirmed) {
            store.dispatch('removeItem', cartId);

            // Hiển thị Toast thông báo thành công
            Toast.fire({
                icon: 'success',
                title: 'Đã xóa sản phẩm thành công'
            });
        }
    });
}

// [NÂNG CẤP] Update số lượng với Toast cảnh báo
const updateQty = (cartId, currentQty, change) => {
    let newQty = parseInt(currentQty) + change;
    const item = cart.value.find(i => i.cartId === cartId);
    const maxStock = item ? (item.stock || 999) : 999;

    if (newQty < 1) return;

    if (newQty > maxStock) {
        // Thay alert bằng Toast warning
        Toast.fire({
            icon: 'warning',
            title: `Kho chỉ còn ${maxStock} sản phẩm!`
        });
        return;
    }

    store.dispatch('updateItemQty', { cartId, qty: newQty });
}

// [NÂNG CẤP] Checkout check
const proceedToCheckout = () => {
    if (cart.value.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Giỏ hàng trống',
            text: 'Hãy chọn thêm sản phẩm trước khi thanh toán nhé!',
            confirmButtonColor: '#10b981'
        });
        return;
    }

    const itemsToCheckout = cart.value.map(item => item.cartId);
    localStorage.setItem('checkout_items', JSON.stringify(itemsToCheckout));
    router.push('/checkout');
}
</script>
<template>
    <div class="cart-page">
        <div class="container">

            <!-- HEADER -->
            <div class="page-header">
                <h1><i class="fa-solid fa-bag-shopping"></i> Giỏ hàng của bạn</h1>
                <span class="item-count">{{ cart.length }} sản phẩm</span>
            </div>

            <div v-if="cart.length === 0" class="empty-state">
                <img src="https://cdni.iconscout.com/illustration/premium/thumb/empty-cart-2130356-1800917.png"
                    alt="Empty Cart">
                <h3>Giỏ hàng đang trống</h3>
                <p>Hãy dạo một vòng và chọn những món đồ ưng ý nhé!</p>
                <router-link to="/shop" class="btn-primary">Tiếp tục mua sắm</router-link>
            </div>

            <div v-else class="cart-layout">

                <!-- CỘT TRÁI: DANH SÁCH ITEM -->
                <div class="cart-items-section">
                    <div class="item-list">
                        <div v-for="item in cart" :key="item.cartId" class="cart-item-card">
                            <!-- Image -->
                            <div class="item-image">
                                <router-link :to="`/products/${item.product_id || item.id}`">
                                    <img :src="getImageUrl(item.thumbnail_url || item.image_url)" alt="Product">
                                </router-link>
                            </div>

                            <!-- Content -->
                            <div class="item-content">
                                <div class="item-info">
                                    <span class="category-tag">{{ item.categoriesName || "Sản phẩm" }}</span>
                                    <router-link :to="`/products/${item.product_id || item.id}`" class="item-name">
                                        {{ item.name }}
                                    </router-link>

                                    <div class="item-variants" v-if="getVariantLabel(item)">
                                        <span class="variant-badge">
                                            <i class="fa-solid fa-layer-group"></i> {{ getVariantLabel(item) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="item-actions-mobile">
                                    <!-- Giá -->
                                    <div class="item-price">
                                        {{ formatPrice(item.price) }}
                                    </div>

                                    <!-- Bộ tăng giảm số lượng -->
                                    <div class="qty-control">
                                        <button @click="updateQty(item.cartId, item.qty, -1)"
                                            :disabled="item.qty <= 1">-</button>
                                        <input type="number" :value="item.qty" readonly>
                                        <button @click="updateQty(item.cartId, item.qty, 1)"
                                            :disabled="item.qty >= (item.stock || 999)">+</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Button (Desktop) -->
                            <button class="btn-remove-item" @click="removeItem(item.cartId)" title="Xóa">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CỘT PHẢI: TỔNG KẾT (Sticky) -->
                <div class="cart-summary-section">
                    <div class="summary-card">
                        <h3>Thanh toán</h3>

                        <div class="summary-row">
                            <span>Số lượng:</span>
                            <span>{{ cart.length }} món</span>
                        </div>

                        <div class="divider"></div>

                        <div class="summary-row total">
                            <span>Tổng cộng:</span>
                            <span class="price-highlight">{{ formatPrice(total) }}</span>
                        </div>

                        <div class="summary-note">
                            * Đã bao gồm thuế VAT (nếu có)
                        </div>

                        <button @click="proceedToCheckout" class="btn-checkout" :disabled="cart.length === 0">
                            Mua Hàng ngay
                        </button>

                        <router-link to="/shop" class="link-continue">
                            <i class="fa-solid fa-arrow-left"></i> Tiếp tục chọn đồ
                        </router-link>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>

@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

.cart-page {
    font-family: 'Inter', sans-serif;
    background-color: #f3f4f6;
    min-height: 100vh;
    padding: 40px 15px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Header */
.page-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

.page-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.item-count {
    background: #e5e7eb;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 14px;
    color: #4b5563;
    font-weight: 600;
}

/* Layout Grid */
.cart-layout {
    display: grid;
    grid-template-columns: 1fr 350px;
    /* Cột trái to, cột phải cố định 350px */
    gap: 25px;
    align-items: start;
}

/* Item Card - THE IMPORTANT PART */
.cart-item-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: flex-start;
    /* Căn hàng trên cùng */
    gap: 20px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.2s;
    border: 1px solid transparent;
    position: relative;
}

.cart-item-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.item-image img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #fff;
}

.item-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 100px;
}

.item-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.category-tag {
    font-size: 11px;
    text-transform: uppercase;
    color: #6b7280;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.item-name {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    text-decoration: none;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-right: 30px;
    /* Tránh đè lên nút xóa */
}

.item-name:hover {
    color: #10b981;
}

.item-variants {
    margin-top: 5px;
}

.variant-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f3f4f6;
    color: #4b5563;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 13px;
    border: 1px solid #e5e7eb;
}

.item-actions-mobile {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
}

.item-price {
    font-size: 16px;
    font-weight: 700;
    color: #059669;
}

/* Quantity Control - Custom Button */
.qty-control {
    display: flex;
    align-items: center;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
}

.qty-control button {
    width: 30px;
    height: 30px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 16px;
    color: #374151;
    transition: background 0.2s;
}

.qty-control button:hover:not(:disabled) {
    background: #f3f4f6;
}

.qty-control button:disabled {
    color: #d1d5db;
    cursor: not-allowed;
}

.qty-control input {
    width: 40px;
    text-align: center;
    border: none;
    font-weight: 600;
    font-size: 14px;
    color: #111827;
    outline: none;
    /* Hide spin button */
    -moz-appearance: textfield;
}

.qty-control input::-webkit-outer-spin-button,
.qty-control input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.btn-remove-item {
    position: absolute;
    top: 15px;
    right: 15px;
    background: transparent;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    font-size: 18px;
    transition: color 0.2s;
}

.btn-remove-item:hover {
    color: #ef4444;
}

/* Summary Column */
.cart-summary-section {
    position: sticky;
    top: 20px;
    /* Trượt theo màn hình */
}

.summary-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.summary-card h3 {
    margin-top: 0;
    color: #111827;
    font-size: 18px;
    margin-bottom: 20px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    color: #4b5563;
    font-size: 15px;
}

.summary-row.total {
    color: #111827;
    font-weight: 700;
    font-size: 18px;
    margin-top: 15px;
}

.price-highlight {
    color: #059669;
}

.divider {
    border-top: 1px dashed #d1d5db;
    margin: 15px 0;
}

.summary-note {
    font-size: 12px;
    color: #9ca3af;
    margin-bottom: 10px;
    font-style: italic;
    text-align: right;
}

.btn-checkout {
    width: 100%;
    background: #10b981;
    color: white;
    border: none;
    padding: 15px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-checkout:hover:not(:disabled) {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-checkout:disabled {
    background: #d1d5db;
    cursor: not-allowed;
}

.link-continue {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: #6b7280;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.link-continue:hover {
    color: #10b981;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
}

.empty-state img {
    width: 200px;
    margin-bottom: 20px;
    opacity: 0.8;
}

.empty-state h3 {
    font-size: 20px;
    color: #111827;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6b7280;
    margin-bottom: 25px;
}

.btn-primary {
    background: #10b981;
    color: white;
    padding: 10px 25px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
}

/* Responsive */
@media (max-width: 992px) {
    .cart-layout {
        grid-template-columns: 1fr;
    }

    .cart-summary-section {
        position: static;
    }
}

@media (max-width: 576px) {
    .cart-item-card {
        flex-direction: column;
        gap: 15px;
        position: relative;
    }

    .item-image {
        width: 100%;
    }

    .item-image img {
        width: 100%;
        height: 200px;
    }

    .btn-remove-item {
        top: 15px;
        right: 15px;
        background: white;
        padding: 5px;
        border-radius: 50%;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .item-actions-mobile {
        flex-direction: row;
        justify-content: space-between;
        width: 100%;
        margin-top: 10px;
    }
}
</style>