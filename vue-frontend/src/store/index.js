import { createStore } from 'vuex';
import axios from 'axios';

// Cấu hình API URL
const API_URL = 'http://127.0.0.1:8000/api';

// Helper lưu Local Storage
const saveToLocalStorage = (cart) => {
    localStorage.setItem('my_cart', JSON.stringify(cart));
};

// Helper lấy Token (Xử lý cả 2 trường hợp tên key để an toàn)
const getToken = () => {
    return localStorage.getItem('authToken') || localStorage.getItem('auth_token');
};

// --- TỪ ĐIỂN DANH MỤC ---
const CATEGORY_MAP = {
    1: 'Laptop Gaming ASUS ROG',
    42: 'Điện thoại di động',
    43: 'Laptop & Macbook',
    44: 'Thiết bị âm thanh',
    45: 'Máy tính bảng',
    46: 'Phụ kiện công nghệ',
    47: 'Đồng hồ thông minh',
    48: 'PC & Màn hình',
    50: 'Đồ thể thao'
};

const extractCategoryName = (data) => {
    if (data.category_id && CATEGORY_MAP[data.category_id]) return CATEGORY_MAP[data.category_id];
    let catObj = data.category || data.categories;
    if (Array.isArray(catObj)) catObj = catObj.length > 0 ? catObj[0] : null;
    if (catObj && typeof catObj === 'object' && catObj.name) return catObj.name;
    return 'Khác';
};

export default createStore({
    state: {
        cart: JSON.parse(localStorage.getItem('my_cart')) || [],
        // [FIX] Kiểm tra cả 2 loại key token
        isLoggedIn: !!(localStorage.getItem('authToken') || localStorage.getItem('auth_token')), 
    },
    getters: {
        cartItems: (state) => state.cart,
        cartCount: (state) => state.cart.length, 
        cartTotal: (state) => {
            return state.cart.reduce((total, item) => {
                const p = Number(item.price) || 0;
                const q = Number(item.qty) || 0;
                return total + (p * q);
            }, 0);
        }
    },
    mutations: {
        SET_CART(state, items) {
            state.cart = items;
            saveToLocalStorage(state.cart);
        },
        
        ADD_TO_CART_LOCAL(state, { product, quantity, variant }) {
            const variantId = variant ? variant.id : 'default';
            const compareId = `${product.id}-${variantId}`;
            
            const existingItem = state.cart.find(item => item.compareId === compareId);
            const currentStock = variant ? Number(variant.stock) : Number(product.stock);
            const catName = extractCategoryName(product);

            if (existingItem) {
                let newQty = existingItem.qty + quantity;
                if (newQty > currentStock) newQty = currentStock;
                existingItem.qty = newQty;
            } else {
                state.cart.push({
                    compareId: compareId,
                    id: product.id,
                    name: product.name,
                    variantId: variantId,
                    variantName: variant ? (variant.attributes ? Object.values(variant.attributes).join(' - ') : 'Mặc định') : 'Mặc định',
                    price: variant ? Number(variant.price) : Number(product.price),
                    image_url: product.thumbnail_url || product.image_url,
                    categoriesName: catName,
                    stock: currentStock,
                    qty: quantity > currentStock ? currentStock : quantity,
                    cartId: Date.now() + Math.random().toString(36).substr(2, 9)
                });
            }
            saveToLocalStorage(state.cart);
        },

        REMOVE_ITEM(state, cartId) {
            state.cart = state.cart.filter(item => item.cartId !== cartId);
            saveToLocalStorage(state.cart);
        },

        UPDATE_QTY(state, { cartId, qty }) {
            const item = state.cart.find(i => i.cartId === cartId);
            if (item) {
                let newQty = Number(qty);
                if (newQty > item.stock) newQty = item.stock;
                if (newQty < 1) newQty = 1;
                item.qty = newQty;
                saveToLocalStorage(state.cart);
            }
        },

        CLEAR_CART(state) {
            state.cart = [];
            saveToLocalStorage(state.cart);
        },

        SET_LOGIN_STATUS(state, status) {
            state.isLoggedIn = status;
        },
        
        UPDATE_ITEM_INFO(state, { cartId, data }) {
            const item = state.cart.find(i => i.cartId === cartId);
            if (!item || !data) return;
            item.image_url = data.thumbnail_url || data.image_url || item.image_url;
            item.name = data.name || item.name;
        }
    },
    actions: {
        // --- [QUAN TRỌNG] ACTION KHỞI TẠO ---
        async initializeCart({ commit, state, dispatch }) {
            // [FIX] Lấy token bằng hàm helper
            const token = getToken();
            const hasToken = !!token;
            
            // Cập nhật lại state cho chính xác
            commit('SET_LOGIN_STATUS', hasToken);
            
            console.log("Init Cart - LoggedIn:", hasToken); // Debug log

            if (hasToken) {
                // Nếu đã login, gọi API sync/update thông tin
                dispatch('enrichCartData'); 
            }
        },

        // --- ACTION THÊM GIỎ HÀNG ---
        async addToCart({ commit, state, dispatch }, payload) {
            const { product, variant, quantity } = payload;

            // [FIX] Kiểm tra lại token lần nữa ngay tại thời điểm bấm nút
            // Để tránh trường hợp state chưa kịp cập nhật hoặc token bị xóa
            const token = getToken();
            const isActuallyLoggedIn = !!token;

            console.log("Add to Cart - Check Auth:", isActuallyLoggedIn);

            if (isActuallyLoggedIn) {
                try {
                    const headers = { Authorization: `Bearer ${token}` };

                    const response = await axios.post(`${API_URL}/cart/add`, {
                        product_id: product.id,
                        variant_id: variant ? variant.id : 'default',
                        quantity: quantity
                    }, { headers });
                    
                    console.log("✅ Đã lưu DB:", response.data);
                    
                    commit('ADD_TO_CART_LOCAL', payload);
                    return true;
                } catch (error) {
                    console.error("❌ Lỗi lưu DB:", error);
                    // Nếu lỗi 401 (Token hết hạn), tự động logout
                    if (error.response && error.response.status === 401) {
                        commit('SET_LOGIN_STATUS', false);
                        localStorage.removeItem('authToken'); // Xóa token cũ
                        localStorage.removeItem('auth_token');
                    }
                    
                    // Fallback: Vẫn lưu local để khách không mất đơn
                    commit('ADD_TO_CART_LOCAL', payload);
                    return false;
                }
            } else {
                commit('ADD_TO_CART_LOCAL', payload);
                console.log("⚠️ Lưu Local (Chưa đăng nhập)");
                return true;
            }
        },

        removeItem({ commit }, cartId) { commit('REMOVE_ITEM', cartId); },
        updateItemQty({ commit }, payload) { commit('UPDATE_QTY', payload); },
        clearCart({ commit }) { commit('CLEAR_CART'); },

        async enrichCartData({ state, commit }) {
            if (!state.cart.length) return;
            const uniqueProductIds = [...new Set(state.cart.map(item => item.id))];

            uniqueProductIds.forEach(async (id) => {
                try {
                    const response = await axios.get(`${API_URL}/product/${id}`);
                    const productData = response.data.data || response.data;
                    if (productData) {
                        state.cart.forEach(cartItem => {
                            if (cartItem.id === id) {
                                commit('UPDATE_ITEM_INFO', { 
                                    cartId: cartItem.cartId, 
                                    data: productData 
                                });
                            }
                        });
                    }
                } catch (e) {}
            });
            setTimeout(() => saveToLocalStorage(state.cart), 1000);
        }
    }
});