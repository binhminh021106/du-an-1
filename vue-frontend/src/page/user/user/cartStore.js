// src/page/user/cartStore.js
import { ref, computed } from 'vue';

// Khởi tạo giỏ hàng từ localStorage
const initializeCart = () => {
    try {
        const storedCart = localStorage.getItem('my_app_cart');
        return storedCart ? JSON.parse(storedCart) : [];
    } catch (error) {
        console.error("Lỗi khi tải giỏ hàng từ localStorage:", error);
        return [];
    }
};

const cart = ref(initializeCart());

// Lưu giỏ hàng vào localStorage
const saveCart = () => {
    localStorage.setItem('my_app_cart', JSON.stringify(cart.value));
};

// Tính tổng tiền
const total = computed(() =>
    cart.value.reduce((sum, item) => sum + (item.qty * item.price), 0)
);

// Tổng số loại sản phẩm
const itemCount = computed(() => cart.value.length);

// Tổng số lượng items
const totalQuantity = computed(() =>
    cart.value.reduce((sum, item) => sum + item.qty, 0)
);

// Hàm thêm sản phẩm
const addToCart = (product, variant, qty) => {
    const validQty = Math.max(1, qty);
    
    // Fallback nếu variant bị null (tránh lỗi crash app)
    const safeVariant = variant || { id: 'default', name: 'Mặc định', price: product.price || 0, stock: 100 };
    
    const variantId = safeVariant.id !== undefined ? safeVariant.id : 'default';
    const cartItemId = `${product.id}-${variantId}`;
    
    const existingItem = cart.value.find(item => item.cartId === cartItemId);
    const price = Number(safeVariant.price);
    const stock = safeVariant.stock || Infinity;

    if (existingItem) {
        const newQty = existingItem.qty + validQty;
        existingItem.qty = Math.min(newQty, stock);
        existingItem.price = price; // Cập nhật giá mới nhất nếu có thay đổi
        existingItem.stock = stock;
    } else {
        const newItem = {
            cartId: cartItemId,
            id: product.id,
            variantId: variantId,
            name: product.name,
            variantName: safeVariant.name || 'Mặc định',
            thumbnail_url: product.thumbnail_url || product.image_url, // Fallback ảnh
            category: product.category,
            price: price,
            stock: stock,
            qty: validQty,
        };
        cart.value.push(newItem);
    }
    
    saveCart();
};

// Hàm xóa sản phẩm
const removeItem = (cartId) => {
    const index = cart.value.findIndex(item => item.cartId === cartId);
    if (index !== -1) {
        cart.value.splice(index, 1);
        saveCart();
    }
};

// Hàm cập nhật số lượng
const updateItemQty = (cartId, newQty) => {
    const item = cart.value.find(i => i.cartId === cartId);
    if (item) {
        const validatedQty = Math.max(1, Math.min(newQty, item.stock || Infinity));
        item.qty = validatedQty;
        saveCart();
    }
}

// Hàm xóa toàn bộ giỏ hàng
const clearCart = () => {
    cart.value = [];
    saveCart();
};

export { 
    cart, 
    total, 
    itemCount, 
    totalQuantity, 
    addToCart, 
    removeItem, 
    updateItemQty, 
    saveCart, 
    clearCart 
};