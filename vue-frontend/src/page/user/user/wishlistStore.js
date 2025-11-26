// src/pages/user/wishlistStore.js
import { ref } from 'vue';

// Khởi tạo Wishlist từ localStorage
const initializeWishlist = () => {
    try {
        const storedWishlist = localStorage.getItem('my_app_wishlist');
        return storedWishlist ? JSON.parse(storedWishlist) : [];
    } catch (error) {
        console.error("Lỗi khi tải wishlist từ localStorage:", error);
        return [];
    }
};

const wishlist = ref(initializeWishlist());

// Lưu Wishlist vào localStorage
const saveWishlist = () => {
    localStorage.setItem('my_app_wishlist', JSON.stringify(wishlist.value));
};

// Kiểm tra xem sản phẩm có trong Wishlist không
const isInWishlist = (productId) => {
    return wishlist.value.some(item => String(item.id) === String(productId));
};

// Thêm/Xóa sản phẩm khỏi Wishlist
const toggleWishlist = (product) => {
    const productId = product.id;
    const index = wishlist.value.findIndex(item => String(item.id) === String(productId));

    if (index !== -1) {
        // Xóa khỏi Wishlist
        wishlist.value.splice(index, 1);
        saveWishlist();
        return false; // Đã xóa
    } else {
        // Thêm vào Wishlist (chỉ lưu các thông tin cần thiết)
        const newItem = {
            id: product.id,
            name: product.name,
            image_url: product.image_url,
            price: product.variants[0]?.price || 0, // Lấy giá của variant đầu tiên hoặc 0
            stock: product.variants[0]?.stock || 0,
            category: product.category,
            variants: product.variants // Giữ lại variants để dễ thêm vào giỏ hàng
        };
        wishlist.value.push(newItem);
        saveWishlist();
        return true; // Đã thêm
    }
};

// Hàm xóa sản phẩm khỏi Wishlist (dùng trong WishlistPage)
const removeItemFromWishlist = (productId) => {
    const index = wishlist.value.findIndex(item => String(item.id) === String(productId));
    if (index !== -1) {
        wishlist.value.splice(index, 1);
        saveWishlist();
    }
};

export { wishlist, isInWishlist, toggleWishlist, removeItemFromWishlist };