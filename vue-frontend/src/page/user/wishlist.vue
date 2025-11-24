<script setup>
import { ref } from 'vue';
// Import store wishlist
import { wishlist, removeItemFromWishlist } from "./wishlistStore.js"; 
// Import store giỏ hàng để thêm từ wishlist
import { addToCart } from "./cartStore.js"; 

// --- HELPER FUNCTION ---
const formatCurrency = (value) =>
  value.toLocaleString("vi-VN") + "\u00A0₫"; 

// --- ACTIONS ---

const removeItem = (itemId) => {
    removeItemFromWishlist(itemId);
    alert('Đã xóa sản phẩm khỏi danh sách yêu thích.');
};

const moveItemToCart = (item) => {
    // Luôn chọn variant đầu tiên và thêm 1 sản phẩm vào giỏ
    const variant = item.variants && item.variants.length > 0 
        ? item.variants[0]
        : { id: 'default', name: 'Mặc định', price: item.price, stock: item.stock || 999 };

    addToCart(item, variant, 1);
    
    // Xóa sản phẩm khỏi wishlist sau khi thêm vào giỏ
    removeItem(item.id); 

    alert(`Đã thêm ${item.name} vào Giỏ hàng!`);
};
</script>

<template>
    <div class="wishlist-page-wrapper">
        <div class="wishlist-container">
            <h2>Danh sách Yêu thích ({{ wishlist.length }} sản phẩm)</h2>

            <div v-if="wishlist.length" class="wishlist-items">
                <div v-for="item in wishlist" :key="item.id" class="wishlist-item">
                    <img :src="item.image_url" :alt="item.name" class="item-image">
                    
                    <div class="item-info">
                        <h3 class="item-name">{{ item.name }}</h3>
                        <p class="item-price">{{ formatCurrency(item.price) }}</p>
                    </div>

                    <div class="item-actions">
                        <button class="btn btn-primary" @click="moveItemToCart(item)">
                            Thêm vào giỏ
                        </button>
                        <button class="btn btn-secondary" @click="removeItem(item.id)">
                            Xóa
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="empty-wishlist">
                <p>❤️ Danh sách yêu thích của bạn đang trống.</p>
            </div>


            <p class="back-link">
                <router-link to="/">Tiếp tục mua sắm</router-link>
            </p>
        </div>
    </div>
</template>

<style scoped>
/* Import font và biến màu từ trang login */
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

:root {
    --primary-color: #009981;
    --text-color: #333;
    --border-color: #ddd;
    --bg-light: #f9f9f9;
    --danger-color: #e74c3c;
}

/* Wrapper ngoài cùng, tương tự .login-page-wrapper */
.wishlist-page-wrapper {
    font-family: 'Roboto', sans-serif;
    background-color: var(--bg-light);
    display: flex;
    justify-content: center;
    align-items: flex-start; /* Dùng flex-start để không bị căn giữa dọc khi list dài */
    min-height: 100vh;
    width: 100%;
    margin: 0;
    color: var(--text-color);
    padding: 40px 20px; /* Thêm padding cho trang */
    box-sizing: border-box;
}

/* Container chính, tương tự .login-container */
.wishlist-container {
    max-width: 1000px;
    width: 100%;
    background-color: #fff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    border-radius: 12px;
    overflow: hidden;
    padding: 40px;
}

.wishlist-container h2 {
    font-size: 1.75rem;
    font-weight: 700;
    text-align: left;
    margin-top: 0;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--primary-color);
}

.wishlist-items {
    display: flex;
    flex-direction: column;
    gap: 20px; /* Khoảng cách giữa các sản phẩm */
}

/* Kiểu cho mỗi sản phẩm trong danh sách */
.wishlist-item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    transition: box-shadow 0.2s;
}
.wishlist-item:hover {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
}

.item-image {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 6px;
    flex-shrink: 0;
}

.item-info {
    flex-grow: 1; /* Quan trọng: đẩy phần actions sang bên phải */
}

.item-name {
    font-size: 1.1rem;
    font-weight: 500;
    margin: 0 0 5px 0;
    color: var(--text-color);
}

.item-price {
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary-color);
    margin: 0;
}

.item-actions {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}

/* --- Kiểu nút bấm được tái sử dụng --- */
.btn {
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

/* Nút chính, tương tự .btn-login */
.btn-primary {
    background-color: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: #007a68;
}

/* Nút phụ, tương tự .social-btn */
.btn-secondary {
    background-color: #fff;
    color: var(--danger-color); /* Dùng màu đỏ cho hành động Xóa */
    border: 1px solid var(--danger-color);
}

.btn-secondary:hover {
    background-color: var(--danger-color);
    color: #fff;
}

/* Link quay lại, tương tự .register-link */
.back-link {
    text-align: center;
    margin-top: 30px;
    font-size: 0.9rem;
}

.back-link a {
    color: var(--primary-color);
    font-weight: 700;
    text-decoration: none;
}

.back-link a:hover {
    text-decoration: underline;
}

.empty-wishlist {
    text-align: center;
    padding: 30px;
    color: #777;
    font-size: 1.1em;
}

/* --- Responsive cho điện thoại --- */
@media (max-width: 768px) {
    .wishlist-container {
        padding: 20px;
    }

    .wishlist-item {
        flex-direction: column; /* Xếp dọc các item */
        align-items: flex-start;
        gap: 15px;
    }

    .item-image {
        width: 100%;
        height: 180px; /* Cho ảnh rộng ra */
    }

    .item-info {
        width: 100%;
    }

    .item-actions {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr; /* 2 nút trên 1 hàng */
    }
}
</style>