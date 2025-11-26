// src/pages/user/cartStore.js
import { ref, computed } from 'vue';

// Khởi tạo giỏ hàng từ localStorage hoặc là mảng rỗng
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

// Lưu giỏ hàng vào localStorage mỗi khi nó thay đổi
const saveCart = () => {
    localStorage.setItem('my_app_cart', JSON.stringify(cart.value));
};

// Tính tổng tiền
const total = computed(() =>
    cart.value.reduce((sum, item) => sum + item.qty * item.price, 0)
);

// ➕ BỔ SUNG: Tổng số loại sản phẩm (Số hàng)
const itemCount = computed(() => cart.value.length);

// ➕ BỔ SUNG: Tổng số lượng món hàng
const totalQuantity = computed(() =>
    cart.value.reduce((sum, item) => sum + item.qty, 0)
);

// Hàm thêm sản phẩm (CHỨC NĂNG CHÍNH)
const addToCart = (product, variant, qty) => {
    const validQty = Math.max(1, qty);
    const variantId = variant.id !== undefined ? variant.id : 'default';
    const cartItemId = `${product.id}-${variantId}`;
    const existingItem = cart.value.find(item => item.cartId === cartItemId);
    const price = variant.price;
    const stock = variant.stock || Infinity;

    if (existingItem) {
        const newQty = existingItem.qty + validQty;
        existingItem.qty = Math.min(newQty, stock);
        existingItem.price = price;
        existingItem.stock = stock;
    } else {
        const newItem = {
            cartId: cartItemId,
            id: product.id,
            variantId: variantId,
            name: product.name,
            variantName: variant.name || 'Mặc định',
            image_url: product.image_url,
            category: product.category,
            price: price,
            stock: stock,
            qty: validQty,
        };
        cart.value.push(newItem);
    }
    
    saveCart();
};

// Hàm xóa sản phẩm (dùng trong CartPage)
const removeItem = (cartId) => {
    const index = cart.value.findIndex(item => item.cartId === cartId);
    if (index !== -1) {
        cart.value.splice(index, 1);
        saveCart();
    }
};

// Hàm cập nhật số lượng (dùng trong CartPage)
const updateItemQty = (cartId, newQty) => {
    const item = cart.value.find(i => i.cartId === cartId);
    if (item) {
        const validatedQty = Math.max(1, Math.min(newQty, item.stock || Infinity));
        item.qty = validatedQty;
        saveCart();
    }
}

// ➕ BỔ SUNG: Hàm xóa toàn bộ giỏ hàng
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
    clearCart // Xuất hàm mới
};