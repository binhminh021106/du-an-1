import { createStore } from 'vuex';
import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api';

// Helper lưu Local Storage (Chỉ dùng khi chưa đăng nhập)
const saveToLocalStorage = (cart) => {
    localStorage.setItem('my_cart', JSON.stringify(cart));
};

// Map danh mục
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

// Helper lấy tên danh mục an toàn
const extractCategoryName = (data) => {
    if (data.category_id && CATEGORY_MAP[data.category_id]) return CATEGORY_MAP[data.category_id];
    let catObj = data.category || data.categories;
    if (Array.isArray(catObj)) catObj = catObj.length > 0 ? catObj[0] : null;
    if (catObj && typeof catObj === 'object' && catObj.name) return catObj.name;
    if (data.category_name) return data.category_name;
    return 'Khác';
};

// Helper tạo headers auth
const getAuthHeaders = () => {
    const token = localStorage.getItem('authToken') || localStorage.getItem('auth_token');
    return token ? { Authorization: `Bearer ${token}` } : {};
};

export default createStore({
    state: {
        cart: [], // Khởi tạo rỗng, sẽ load từ Local hoặc API
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
        // [MỚI] Mutation để cập nhật trạng thái đăng nhập
        SET_LOGIN_STATUS(state, status) {
            state.isLoggedIn = status;
        },
        SET_CART(state, cart) {
            state.cart = cart;
            // Nếu chưa đăng nhập thì mới cần sync ngược lại local storage để giữ trạng thái
            if (!state.isLoggedIn) {
                saveToLocalStorage(cart);
            }
        },
        ADD_TO_CART_MUTATION(state, { product, quantity, variant }) {
            const qty = Number(quantity) > 0 ? Number(quantity) : 1;
            const variantId = variant ? variant.id : 'default';
            const compareId = `${product.id}-${variantId}`;
            
            const existingItem = state.cart.find(item => item.compareId === compareId);
            const currentStock = variant ? Number(variant.stock) : Number(product.stock);
            const catName = extractCategoryName(product);

            if (existingItem) {
                let newQty = existingItem.qty + qty;
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
                    qty: qty > currentStock ? currentStock : qty,
                    cartId: Date.now() + Math.random().toString(36).substr(2, 9)
                });
            }
            if (!state.isLoggedIn) saveToLocalStorage(state.cart);
        },
        REMOVE_ITEM_MUTATION(state, cartId) {
            state.cart = state.cart.filter(item => item.cartId !== cartId);
            if (!state.isLoggedIn) saveToLocalStorage(state.cart);
        },
        UPDATE_QTY_MUTATION(state, { cartId, qty }) {
            const item = state.cart.find(i => i.cartId === cartId);
            if (item) {
                let newQty = Number(qty);
                if (newQty > item.stock) newQty = item.stock;
                if (newQty < 1) newQty = 1;
                item.qty = newQty;
                if (!state.isLoggedIn) saveToLocalStorage(state.cart);
            }
        },
        CLEAR_CART_MUTATION(state) {
            state.cart = [];
            // Logic quan trọng: Nếu không đăng nhập (vừa logout xong) -> Xóa luôn cả LocalStorage
            if (!state.isLoggedIn) saveToLocalStorage([]); 
        },
        UPDATE_ITEM_INFO(state, { cartId, data }) {
            const item = state.cart.find(i => i.cartId === cartId);
            if (!item || !data) return;

            item.image_url = data.thumbnail_url || data.image_url || item.image_url;
            item.name = data.name || item.name;
            const newCatName = extractCategoryName(data);
            if (newCatName !== 'Khác') item.categoriesName = newCatName;

            if (item.variantName !== 'Mặc định' && data.variants && data.variants.length > 0) {
                let foundVariant = null;
                if (item.variantId) foundVariant = data.variants.find(v => v.id == item.variantId);
                if (!foundVariant) foundVariant = data.variants.find(v => v.name === item.variantName);

                if (foundVariant) {
                    item.price = Number(foundVariant.price);
                    item.stock = Number(foundVariant.stock);
                }
            } else {
                item.price = Number(data.price);
                item.stock = Number(data.stock);
            }
            if (item.qty > item.stock) item.qty = item.stock;
        }
    },
    actions: {
        // --- 1. ACTION THÊM GIỎ HÀNG ---
        async addToCart({ commit, state, dispatch }, payload) {
            const { product, quantity, variant } = payload;
            
            // CASE 1: Đã đăng nhập -> Gọi API lưu Database
            if (state.isLoggedIn) {
                try {
                    // [MATCHED] Route::post('/cart/add', ...)
                    const response = await axios.post(`${API_URL}/cart/add`, {
                        product_id: product.id,
                        quantity: quantity,
                        variant_id: variant ? variant.id : null
                    }, { headers: getAuthHeaders() });

                    if (response.status === 200 || response.status === 201) {
                        await dispatch('fetchCart'); 
                    }
                    return true;
                } catch (error) {
                    console.error("Lỗi thêm vào DB:", error);
                    throw error; 
                }
            } 
            // CASE 2: Chưa đăng nhập -> Lưu LocalStorage
            else {
                return new Promise((resolve) => {
                    commit('ADD_TO_CART_MUTATION', payload);
                    resolve(true);
                });
            }
        },

        // --- 2. ACTION LẤY GIỎ HÀNG TỪ DB ---
        async fetchCart({ commit, state }) {
            if (!state.isLoggedIn) return;
            try {
                // [FIXED] Route::get('/carts', ...) -> Backend dùng số nhiều '/carts'
                const response = await axios.get(`${API_URL}/carts`, { headers: getAuthHeaders() });
                const cartData = response.data.data || response.data; 
                
                if (Array.isArray(cartData)) {
                    const normalizedCart = cartData.map(item => ({
                        ...item,
                        cartId: item.id || `db-${item.product_id}`, 
                        compareId: `${item.product_id}-${item.variant_id || 'default'}`
                    }));
                    commit('SET_CART', normalizedCart);
                }
            } catch (error) {
                console.error("Lỗi load cart từ DB:", error);
            }
        },

        // --- 3. INIT CART KHI LOAD TRANG ---
        async initializeCart({ commit, state, dispatch }) {
            if (state.isLoggedIn) {
                await dispatch('fetchCart');
            } else {
                const savedCart = localStorage.getItem('my_cart');
                if (savedCart) {
                    try {
                        commit('SET_CART', JSON.parse(savedCart));
                    } catch (e) {
                        commit('SET_CART', []);
                    }
                }
            }
        },

        // --- 4. CÁC ACTION KHÁC (UPDATE/REMOVE) ---
        async removeItem({ commit, state, dispatch }, cartId) { 
            if (state.isLoggedIn) {
                try {
                    // [FIXED] Route::delete('/cart/{id}', ...) -> Backend không có chữ 'remove'
                    await axios.delete(`${API_URL}/cart/${cartId}`, { headers: getAuthHeaders() });
                    await dispatch('fetchCart');
                } catch (e) {
                    console.error("Lỗi xóa item DB:", e);
                }
            } else {
                commit('REMOVE_ITEM_MUTATION', cartId); 
            }
        },
        
        async updateItemQty({ commit, state, dispatch }, { cartId, qty }) { 
            if (state.isLoggedIn) {
                try {
                    // [FIXED] Route::put('/cart/{id}', ...) -> Backend không có chữ 'update'
                    await axios.put(`${API_URL}/cart/${cartId}`, { quantity: qty }, { headers: getAuthHeaders() });
                    await dispatch('fetchCart');
                } catch (e) {
                    console.error("Lỗi update qty DB:", e);
                }
            } else {
                commit('UPDATE_QTY_MUTATION', { cartId, qty }); 
            }
        },
        
        async clearCart({ commit, state }) { 
            commit('CLEAR_CART_MUTATION'); 
        },

        async enrichCartData({ state, commit }) {
            if (!state.cart.length) return;
             const uniqueProductIds = [...new Set(state.cart.map(item => item.id || item.product_id))];
        }
    }
});