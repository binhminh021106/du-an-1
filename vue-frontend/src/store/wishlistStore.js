// src/store/wishlistStore.js
import { ref } from 'vue';
import apiService from '../apiService'; // Đảm bảo import axios instance

// Khởi tạo Wishlist từ localStorage
const initializeWishlist = async () => {
    // 1. Nếu có token (đã đăng nhập) -> Gọi API lấy wishlist thật
    const token = localStorage.getItem('auth_token') || localStorage.getItem('authToken');
    if (token) {
        try {
            const response = await apiService.get('/wishlist');
            // Map dữ liệu từ API về format của store
            return response.data.data.map(item => item.product);
        } catch (error) {
            console.error("Lỗi tải wishlist từ server:", error);
        }
    }

    // 2. Fallback: Dùng LocalStorage
    try {
        const storedWishlist = localStorage.getItem('my_app_wishlist');
        return storedWishlist ? JSON.parse(storedWishlist) : [];
    } catch (error) {
        return [];
    }
};

const wishlist = ref([]);

// Hàm init async
const init = async () => {
    wishlist.value = await initializeWishlist();
};
init(); // Chạy ngay khi file được import

// Lưu Wishlist vào localStorage (Chỉ dùng khi chưa login hoặc để backup)
const saveWishlistLocal = () => {
    localStorage.setItem('my_app_wishlist', JSON.stringify(wishlist.value));
};

// Kiểm tra xem sản phẩm có trong Wishlist không
const isInWishlist = (productId) => {
    return wishlist.value.some(item => String(item.id) === String(productId));
};

// --- HÀM TOGGLE QUAN TRỌNG: GỌI API ---
// [FIX] Bỏ 'async' để hàm trả về boolean ngay lập tức, không trả về Promise
const toggleWishlist = (product) => {
    const productId = product.id;
    const index = wishlist.value.findIndex(item => String(item.id) === String(productId));
    const token = localStorage.getItem('auth_token') || localStorage.getItem('authToken');

    let isAdded = false;

    // 1. Cập nhật UI ngay lập tức (Optimistic UI Update)
    if (index !== -1) {
        wishlist.value.splice(index, 1); // Xóa khỏi danh sách
        isAdded = false;
    } else {
        const newItem = {
            id: product.id,
            name: product.name,
            image_url: product.image_url || product.thumbnail_url,
            price: product.price || 0, 
        };
        wishlist.value.push(newItem); // Thêm vào danh sách
        isAdded = true;
    }

    // 2. Gọi API đồng bộ ngầm (Fire & Forget)
    if (token) {
        // Không dùng await để tránh chặn UI
        apiService.post('/wishlist/toggle', { product_id: productId })
            .then(() => console.log("✅ Đã đồng bộ Wishlist lên Server"))
            .catch(error => console.error("❌ Lỗi đồng bộ Wishlist:", error));
    } else {
        // Nếu chưa đăng nhập -> Lưu Local
        saveWishlistLocal();
    }

    return isAdded; // Trả về true/false chuẩn xác
};

// Hàm xóa (dùng cho trang quản lý)
const removeItemFromWishlist = (productId) => {
    const index = wishlist.value.findIndex(item => String(item.id) === String(productId));
    if (index !== -1) {
        wishlist.value.splice(index, 1);
        
        const token = localStorage.getItem('auth_token') || localStorage.getItem('authToken');
        if (token) {
             apiService.post('/wishlist/toggle', { product_id: productId }).catch(() => {});
        } else {
            saveWishlistLocal();
        }
    }
};

export { wishlist, isInWishlist, toggleWishlist, removeItemFromWishlist };