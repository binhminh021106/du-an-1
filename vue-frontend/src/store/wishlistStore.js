import { ref } from 'vue';
import apiService from '../apiService'; // Đảm bảo import axios instance

// Khởi tạo Wishlist từ localStorage
const initializeWishlist = async () => {
    // 1. Nếu có token (đã đăng nhập) -> Gọi API lấy wishlist thật
    const token = localStorage.getItem('auth_token') || localStorage.getItem('authToken');
    if (token) {
        try {
            const response = await apiService.get('/wishlist');
            
            // [FIX CORE] Map dữ liệu cẩn thận để đảm bảo cấu trúc đồng nhất với Frontend
            // Thay vì chỉ return item.product, ta map lại để đảm bảo có image_url và id chuẩn
            return response.data.data.map(wishlistItem => {
                // wishlistItem là wrapper { id: 29, product_id: 123, product: {...} }
                const product = wishlistItem.product || {};
                
                return {
                    // QUAN TRỌNG: ID của item trong store PHẢI là Product ID để hàm isInWishlist hoạt động đúng
                    id: product.id || wishlistItem.product_id, 
                    
                    product_id: product.id || wishlistItem.product_id,
                    name: product.name,
                    price: Number(product.price || 0),
                    
                    // [FIX] Xử lý nhiều trường hợp tên field ảnh từ Backend (image, thumbnail, thumbnail_url...)
                    // Để đảm bảo UI có ảnh hiển thị ngay lập tức
                    image_url: product.image_url || product.thumbnail_url || product.thumbnail || product.image,
                    
                    stock: product.stock,
                    
                    // Giữ lại object product gốc lồng bên trong để Wishlist.vue nhận diện được logic "if (item.product)"
                    product: product
                };
            });
        } catch (error) {
            console.error("Lỗi tải wishlist từ server:", error);
            // Nếu lỗi API, thử fallback về localStorage để không trắng trang
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
    // Vì ta đã chuẩn hóa item.id = product.id ở trên nên phép so sánh này luôn đúng
    return wishlist.value.some(item => String(item.id) === String(productId));
};

// --- HÀM TOGGLE QUAN TRỌNG: GỌI API ---
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
            product_id: product.id, // Thêm trường này cho chắc chắn
            name: product.name,
            // Đảm bảo lấy được ảnh dù ở property nào
            image_url: product.image_url || product.thumbnail_url || product.thumbnail || product.image,
            price: product.price || 0,
            product: product // Lưu lại chính nó vào field product để đồng bộ cấu trúc
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