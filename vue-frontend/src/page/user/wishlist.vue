<script setup>
</script>

<template>
    <div class="wishlist-page-wrapper">
        <div class="wishlist-container">
            <h2>Danh sách Yêu thích</h2>

            <div class="wishlist-items">
                <div class="wishlist-item">
                    <img src="https://placehold.co/150x150/009981/white?text=Product" alt="Hình ảnh sản phẩm" class="item-image">
                    
                    <div class="item-info">
                        <h3 class="item-name">Tên sản phẩm mẫu A (Ví dụ: Laptop XYZ)</h3>
                        <p class="item-price">25.000.000đ</p>
                    </div>

                    <div class="item-actions">
                        <button class="btn btn-primary">Thêm vào giỏ</button>
                        <button class="btn btn-secondary">Xóa</button>
                    </div>
                </div>

                <div class="wishlist-item">
                    <img src="https://placehold.co/150x150/009981/white?text=Product" alt="Hình ảnh sản phẩm" class="item-image">
                    
                    <div class="item-info">
                        <h3 class="item-name">Tên sản phẩm mẫu B (Ví dụ: Chuột không dây)</h3>
                        <p class="item-price">1.200.000đ</p>
                    </div>

                    <div class="item-actions">
                        <button class="btn btn-primary">Thêm vào giỏ</button>
                        <button class="btn btn-secondary">Xóa</button>
                    </div>
                </div>

                <div class="wishlist-item">
                    <img src="https://placehold.co/150x150/009981/white?text=Product" alt="Hình ảnh sản phẩm" class="item-image">
                    
                    <div class="item-info">
                        <h3 class="item-name">Tên sản phẩm mẫu C (Ví dụ: Bàn phím cơ)</h3>
                        <p class="item-price">3.500.000đ</p>
                    </div>

                    <div class="item-actions">
                        <button class="btn btn-primary">Thêm vào giỏ</button>
                        <button class="btn btn-secondary">Xóa</button>
                    </div>
                </div>
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
    opacity: 0.9;
}

/* Nút phụ, tương tự .social-btn */
.btn-secondary {
    background-color: #fff;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.btn-secondary:hover {
    background-color: var(--primary-color);
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