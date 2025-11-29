import { createStore } from 'vuex';
import axios from 'axios';

// Cấu hình API URL
const API_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api';

const saveToLocalStorage = (cart) => {
    localStorage.setItem('my_cart', JSON.stringify(cart));
};

// --- TỪ ĐIỂN DANH MỤC (Dựa trên DB của bạn) ---
// Giúp hiển thị tên ngay cả khi API chỉ trả về ID
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

// Hàm helper thông minh để lấy tên danh mục
const extractCategoryName = (data) => {
    // 1. Ưu tiên: Check nếu có category_id thì tra từ điển ngay
    if (data.category_id && CATEGORY_MAP[data.category_id]) {
        return CATEGORY_MAP[data.category_id];
    }

    // 2. Check object category (nếu sau này bạn sửa API trả về object)
    let catObj = data.category || data.categories;
    if (Array.isArray(catObj)) catObj = catObj.length > 0 ? catObj[0] : null;
    
    if (catObj && typeof catObj === 'object' && catObj.name) {
        return catObj.name;
    }

    // 3. Các trường hợp khác
    if (data.category_name) return data.category_name;
    if (data.categories_name) return data.categories_name;

    return 'Khác';
};

export default createStore({
    state: {
        cart: JSON.parse(localStorage.getItem('my_cart')) || []
    },
    getters: {
        cartItems: (state) => state.cart,
        cartCount: (state) => state.cart.reduce((count, item) => count + Number(item.qty), 0),
        cartTotal: (state) => {
            return state.cart.reduce((total, item) => {
                const p = Number(item.price) || 0;
                const q = Number(item.qty) || 0;
                return total + (p * q);
            }, 0);
        }
    },
    mutations: {
        ADD_TO_CART(state, { product, quantity, variant }) {
            const variantId = variant ? variant.id : 'default';
            const compareId = `${product.id}-${variantId}`;
            
            const existingItem = state.cart.find(item => item.compareId === compareId);
            const currentStock = variant ? Number(variant.stock) : Number(product.stock);

            // Tự động lấy tên danh mục
            const catName = extractCategoryName(product);

            if (existingItem) {
                let newQty = existingItem.qty + quantity;
                if (newQty > currentStock) {
                    newQty = currentStock;
                    alert(`Chỉ còn ${currentStock} sản phẩm trong kho!`);
                }
                existingItem.qty = newQty;
                // Update danh mục nếu chưa có
                if (existingItem.categoriesName === 'Khác' && catName !== 'Khác') {
                    existingItem.categoriesName = catName;
                }
            } else {
                state.cart.push({
                    compareId: compareId,
                    id: product.id,
                    name: product.name,
                    variantId: variant ? variant.id : null,
                    variantName: variant ? variant.name : 'Mặc định',
                    price: variant ? Number(variant.price) : Number(product.price),
                    image_url: product.image_url || product.thumbnail_url,
                    
                    categoriesName: catName, // <-- Đã fix
                    
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

        UPDATE_ITEM_INFO(state, { cartId, data }) {
            const item = state.cart.find(i => i.cartId === cartId);
            if (!item || !data) return;

            item.image_url = data.thumbnail_url || data.image_url || item.image_url;
            item.name = data.name || item.name;
            
            // Cập nhật lại danh mục
            const newCatName = extractCategoryName(data);
            if (newCatName !== 'Khác') {
                item.categoriesName = newCatName;
            }

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
        addToCart({ commit }, payload) { commit('ADD_TO_CART', payload); },
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
                } catch (e) {
                    console.warn(`Lỗi update SP ${id}`);
                }
            });
            setTimeout(() => saveToLocalStorage(state.cart), 1000);
        }
    }
});