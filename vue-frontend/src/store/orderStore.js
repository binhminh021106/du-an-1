import { ref } from "vue";

// Biến lưu trữ danh sách đơn hàng
export const orders = ref([]); 

// Khóa Local Storage
const ORDER_STORAGE_KEY = 'orderHistory';

/**
 * 📚 Tải danh sách đơn hàng từ Local Storage.
 * Sắp xếp đơn hàng mới nhất lên đầu (Date lớn nhất).
 */
const loadOrders = () => {
    try {
        const storedOrders = localStorage.getItem(ORDER_STORAGE_KEY);
        if (storedOrders) {
            const parsedOrders = JSON.parse(storedOrders);
            // Sắp xếp theo ngày (mới nhất lên đầu)
            orders.value = parsedOrders.sort((a, b) => new Date(b.date) - new Date(a.date));
        } else {
            // Không tạo dữ liệu ảo nữa, để danh sách trống
            orders.value = [];
        }
    } catch (e) {
        console.error("Error loading orders from localStorage:", e);
        orders.value = [];
    }
};

/**
 * 💾 Lưu danh sách đơn hàng vào Local Storage.
 */
export const saveOrders = () => {
    localStorage.setItem(ORDER_STORAGE_KEY, JSON.stringify(orders.value));
};

/**
 * ➕ Thêm đơn hàng mới vào danh sách.
 */
export const addOrder = (newOrderData) => {
    // Tạo ID đơn hàng giả lập (DH + 6 chữ số cuối của timestamp)
    const orderId = 'DH' + Date.now().toString().slice(-6); 
    const orderDate = new Date().toISOString().split('T')[0]; // Dạng YYYY-MM-DD
    
    // Tạo đối tượng đơn hàng đầy đủ
    const finalOrder = {
        ...newOrderData,
        id: orderId,
        date: orderDate,
        status: 'Đã đặt hàng', // Trạng thái ban đầu
        canCancel: true,
        canRepurchase: true,
        canReview: false,
        canReturn: false, 
        isReviewed: false,
    };

    orders.value.unshift(finalOrder); // Thêm vào đầu để hiển thị mới nhất
    saveOrders();
    return finalOrder;
};

/**
 * Cập nhật trạng thái và cờ hành động của đơn hàng.
 */
export const updateOrderStatus = (orderId, newStatus) => {
    const orderIndex = orders.value.findIndex(o => o.id === orderId);
    if (orderIndex > -1) {
        const order = orders.value[orderIndex];
        order.status = newStatus;
        
        if (newStatus === 'Đã hủy') {
            order.canCancel = false;
        } else if (newStatus === 'Đã giao thành công') {
            order.canCancel = false;
            order.canReview = true;
            order.canReturn = true;
        }
        
        saveOrders();
    }
};

// Khởi tạo: Tải dữ liệu khi store được import lần đầu
loadOrders();