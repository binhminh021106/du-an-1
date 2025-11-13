<script setup>
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';

// Giả định useRouter và useRoute cho code chạy độc lập
const useRouterMock = () => ({
  push: (path) => console.log('Navigating to:', path)
});
const router = useRouterMock();

// Biến điều khiển popup
const showPopup = ref(false);
const selectedOrder = ref(null);
const isReviewing = ref(false);
const reviewText = ref('');
const reviewRating = ref(0);

// --- TÍNH NĂNG MỚI: Biến cho Tìm kiếm và Phân trang ---
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 5;

// 🧾 Dữ liệu giả định cho danh sách đơn hàng (10 ĐƠN HÀNG)
const orders = ref([
  {
    id: 'DH1001',
    date: '2025-11-10',
    total: 550000,
    status: 'Đang giao hàng',
    customer: { name: 'Nguyễn Văn An', phone: '0901 234 567', address: 'Số 123, đường A, Phường B, Quận C, TP. HCM' },
    payment: { subtotal: 550000, shippingFee: 0, total: 550000, method: 'Chuyển khoản' },
    items: [
      { id: 1, name: 'Chuột Logitech G102', price: 350000, qty: 1, image: 'https://via.placeholder.com/100x100/3498DB/FFFFFF?text=Mouse' },
      { id: 2, name: 'Lót chuột Razer', price: 200000, qty: 1, image: 'https://via.placeholder.com/100x100/EEEEEE/333333?text=Pad' },
    ],
    canCancel: true, canRepurchase: true, canReview: false, canReturn: true, isReviewed: false,
  },
  {
    id: 'DH1002',
    date: '2025-11-05',
    total: 280000,
    status: 'Đã giao thành công',
    customer: { name: 'Trần Thị B', phone: '0902 876 543', address: 'Đường Nguyễn Huệ, Quận 1, TP. HCM' },
    payment: { subtotal: 280000, shippingFee: 0, total: 280000, method: 'Thanh toán khi nhận hàng (COD)' },
    items: [
      { id: 3, name: 'Bàn phím cơ Akko 3087', price: 280000, qty: 1, image: 'https://via.placeholder.com/100x100/2ECC71/FFFFFF?text=Keyboard' },
    ],
    canCancel: false, canRepurchase: true, canReview: true, canReturn: true, isReviewed: false,
  },
  {
    id: 'DH1003',
    date: '2025-10-28',
    total: 1200000,
    status: 'Đã hủy',
    customer: { name: 'Lê Văn C', phone: '0903 123 987', address: 'Quận Bình Thạnh, TP. HCM' },
    payment: { subtotal: 1200000, shippingFee: 0, total: 1200000, method: 'Thẻ tín dụng' },
    items: [
      { id: 4, name: 'Tai nghe Razer Kraken', price: 1200000, qty: 1, image: 'https://via.placeholder.com/100x100/E74C3C/FFFFFF?text=Headset' },
    ],
    canCancel: false, canRepurchase: true, canReview: false, canReturn: false, isReviewed: false,
  },
  {
    id: 'DH1004',
    date: '2025-10-15',
    total: 350000,
    status: 'Đã đặt hàng',
    customer: { name: 'Phạm Thu D', phone: '0904 456 123', address: 'Quận Tân Bình, TP. HCM' },
    payment: { subtotal: 350000, shippingFee: 0, total: 350000, method: 'Momo' },
    items: [
      { id: 5, name: 'Tấm lót bàn RGB', price: 350000, qty: 1, image: 'https://via.placeholder.com/100x100/95A5A6/FFFFFF?text=RGB' },
    ],
    canCancel: false, canRepurchase: true, canReview: true, canReturn: true, isReviewed: false,
  },
  {
    id: 'DH1005',
    date: '2025-11-12', 
    total: 350000,
    status: 'Đã giao thành công',
    customer: { name: 'Phạm Thu D', phone: '0904 456 123', address: 'Quận Tân Bình, TP. HCM' },
    payment: { subtotal: 350000, shippingFee: 0, total: 350000, method: 'Momo' },
    items: [
      { id: 5, name: 'Tấm lót bàn RGB', price: 350000, qty: 1, image: 'https://via.placeholder.com/100x100/95A5A6/FFFFFF?text=RGB' },
    ],
    canCancel: false, canRepurchase: true, canReview: true, canReturn: true, isReviewed: false,
  },
  {
    id: 'DH1006',
    date: '2025-11-11',
    total: 550000,
    status: 'Đã hủy',
    customer: { name: 'Nguyễn Văn An', phone: '0901 234 567', address: 'Số 123, đường A, Phường B, Quận C, TP. HCM' },
    payment: { subtotal: 550000, shippingFee: 0, total: 550000, method: 'Chuyển khoản' },
    items: [
      { id: 1, name: 'Chuột Logitech G102', price: 350000, qty: 1, image: 'https://via.placeholder.com/100x100/3498DB/FFFFFF?text=Mouse' },
    ],
    canCancel: false, canRepurchase: true, canReview: false, canReturn: false, isReviewed: false,
  },
  {
    id: 'DH1007',
    date: '2025-11-01',
    total: 280000,
    status: 'Đang giao hàng',
    customer: { name: 'Trần Thị B', phone: '0902 876 543', address: 'Đường Nguyễn Huệ, Quận 1, TP. HCM' },
    payment: { subtotal: 280000, shippingFee: 0, total: 280000, method: 'Thanh toán khi nhận hàng (COD)' },
    items: [
      { id: 3, name: 'Bàn phím cơ Akko 3087', price: 280000, qty: 1, image: 'https://via.placeholder.com/100x100/2ECC71/FFFFFF?text=Keyboard' },
    ],
    canCancel: true, canRepurchase: true, canReview: false, canReturn: true, isReviewed: false,
  },
  {
    id: 'DH1008',
    date: '2025-11-15', // Mới nhất
    total: 1550000,
    status: 'Đã đặt hàng',
    customer: { name: 'Hoàng Văn E', phone: '0905 555 123', address: 'Quận 10, TP. HCM' },
    payment: { subtotal: 1550000, shippingFee: 0, total: 1550000, method: 'Thẻ tín dụng' },
    items: [
      { id: 1, name: 'Chuột Logitech G102', price: 350000, qty: 1, image: 'https://via.placeholder.com/100x100/3498DB/FFFFFF?text=Mouse' },
      { id: 4, name: 'Tai nghe Razer Kraken', price: 1200000, qty: 1, image: 'https://via.placeholder.com/100x100/E74C3C/FFFFFF?text=Headset' },
    ],
    canCancel: false, canRepurchase: true, canReview: false, canReturn: false, isReviewed: false,
  },
  {
    id: 'DH1009',
    date: '2025-11-14',
    total: 200000,
    status: 'Đã giao thành công',
    customer: { name: 'Nguyễn Văn An', phone: '0901 234 567', address: 'Số 123, đường A, Phường B, Quận C, TP. HCM' },
    payment: { subtotal: 200000, shippingFee: 0, total: 200000, method: 'Momo' },
    items: [
      { id: 2, name: 'Lót chuột Razer', price: 200000, qty: 1, image: 'https://via.placeholder.com/100x100/EEEEEE/333333?text=Pad' },
    ],
    canCancel: false, canRepurchase: true, canReview: true, canReturn: true, isReviewed: false,
  },
  {
    id: 'DH1010',
    date: '2025-11-13',
    total: 280000,
    status: 'Đang giao hàng',
    customer: { name: 'Lê Văn C', phone: '0903 123 987', address: 'Quận Bình Thạnh, TP. HCM' },
    payment: { subtotal: 280000, shippingFee: 0, total: 280000, method: 'Thanh toán khi nhận hàng (COD)' },
    items: [
      { id: 3, name: 'Bàn phím cơ Akko 3087', price: 280000, qty: 1, image: 'https://via.placeholder.com/100x100/2ECC71/FFFFFF?text=Keyboard' },
    ],
    canCancel: true, canRepurchase: true, canReview: false, canReturn: true, isReviewed: false,
  },
]);

// --- TÍNH NĂNG MỚI: Sắp xếp, Lọc, và Phân trang ---

// 1. Sắp xếp đơn hàng (mới nhất lên đầu)
const sortedOrders = computed(() => {
  return [...orders.value].sort((a, b) => b.date.localeCompare(a.date));
});

// 2. Lọc đơn hàng theo tìm kiếm
const filteredOrders = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return sortedOrders.value;
  }

  return sortedOrders.value.filter(order => {
    // Tìm theo mã đơn hàng
    const orderIdMatch = order.id.toLowerCase().includes(query);
    
    // Tìm theo tên sản phẩm
    const itemMatch = order.items.some(item => 
      item.name.toLowerCase().includes(query)
    );
    
    return orderIdMatch || itemMatch;
  });
});

// 3. Tính tổng số trang
const totalPages = computed(() => {
  return Math.ceil(filteredOrders.value.length / itemsPerPage);
});

// 4. Lấy đơn hàng cho trang hiện tại
const paginatedOrders = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return filteredOrders.value.slice(start, end);
});

// --- TÍNH NĂNG MỚI: Hàm điều khiển phân trang ---
const setPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
};

const prevPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--;
  }
};

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++;
  }
};

// --- TÍNH NĂNG MỚI: Watcher để reset về trang 1 khi tìm kiếm ---
watch(searchQuery, () => {
  currentPage.value = 1;
});

// --- Logic Tính Toán cho Popup (Dựa trên selectedOrder) ---
// (Giữ nguyên)
const isCancellable = computed(() => selectedOrder.value?.canCancel && selectedOrder.value?.status === 'Đang giao hàng');
const isRepurchasable = computed(() => selectedOrder.value?.canRepurchase);
const isReturnable = computed(() => selectedOrder.value?.canReturn && (selectedOrder.value?.status === 'Đã giao thành công' || selectedOrder.value?.status === 'Hoàn thành, có thể đánh giá'));
const isReviewAvailable = computed(() => selectedOrder.value?.canReview && !selectedOrder.value?.isReviewed && (selectedOrder.value?.status === 'Đã giao thành công' || selectedOrder.value?.status === 'Hoàn thành, có thể đánh giá'));

// --- Logic cho Thanh Tiến Trình ---
// (Giữ nguyên)
const orderSteps = [
  { key: 'placed', label: 'Đã đặt hàng', statusMatch: ['Đã đặt hàng', 'Chờ chuyển phát', 'Đang giao hàng', 'Đã giao thành công', 'Hoàn thành, có thể đánh giá'] },
  { key: 'shipping_prep', label: 'Chờ chuyển phát', statusMatch: ['Chờ chuyển phát', 'Đang giao hàng', 'Đã giao thành công', 'Hoàn thành, có thể đánh giá'] },
  { key: 'in_transit', label: 'Đang trung chuyển', statusMatch: ['Đang giao hàng', 'Đã giao thành công', 'Hoàn thành, có thể đánh giá'] },
  { key: 'delivered', label: 'Đã giao đơn hàng', statusMatch: ['Đã giao thành công', 'Hoàn thành, có thể đánh giá'] },
];

const getActiveStepIndex = computed(() => {
  if (!selectedOrder.value) return -1;
  if (selectedOrder.value.status === 'Đã hủy') {
    return -2;
  }
  let activeIndex = -1;
  const currentStatus = selectedOrder.value.status;
  for (let i = orderSteps.length - 1; i >= 0; i--) {
    if (orderSteps[i].statusMatch.includes(currentStatus)) {
      activeIndex = i;
      break;
    }
  }
  return activeIndex;
});


// --- Hàm Định Dạng và Class ---
// (Giữ nguyên)
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
};

const getStatusClass = (status) => {
  return 'status-' + status.toLowerCase().replace(/ /g, '-').replace(/,/g, '');
};

// ** THÊM HÀM FORMAT NGÀY **
const formatDate = (isoDate) => {
  if (!isoDate) return '';
  const parts = isoDate.split('-'); // Tách [YYYY, MM, DD]
  if (parts.length === 3) {
    return `${parts[2]}-${parts[1]}-${parts[0]}`; // Trả về DD-MM-YYYY
  }
  return isoDate; // Trả về như cũ nếu không đúng định dạng
};


// --- Logic Popup ---
// (Giữ nguyên)
const openDetailPopup = (order, startReview = false) => {
  selectedOrder.value = order;
  isReviewing.value = startReview;
  reviewText.value = '';
  reviewRating.value = 0;
  showPopup.value = true;
};

const closeDetailPopup = () => {
  showPopup.value = false;
  selectedOrder.value = null;
  isReviewing.value = false;
};

// --- Các hàm xử lý hành động (cho POPUP) ---
// (Giữ nguyên)
const handleCancel = () => {
  handleCancelList(selectedOrder.value);
};
const handleRepurchase = () => {
  handleRepurchaseList(selectedOrder.value);
};
const handleStartReview = () => {
  isReviewing.value = true;
};
const handleSubmitReview = () => {
  if (reviewRating.value === 0) {
    alert('Vui lòng chọn số sao để đánh giá!');
    return;
  }
  alert(`Cảm ơn bạn đã đánh giá ${reviewRating.value} sao cho đơn hàng #${selectedOrder.value.id}!`);
  const orderToUpdate = orders.value.find(o => o.id === selectedOrder.value.id);
  if (orderToUpdate) {
    orderToUpdate.isReviewed = true;
    selectedOrder.value.isReviewed = true;
  }
  isReviewing.value = false;
};
const handleReturn = () => {
  handleReturnList(selectedOrder.value);
};

// --- CÁC HÀM XỬ LÝ HÀNH ĐỘNG MỚI (CHO DANH SÁCH) ---
// (Giữ nguyên)
const handleCancelList = (order) => {
  if (confirm(`Bạn có chắc muốn hủy đơn hàng #${order.id} không?`)) {
    alert(`Đã gửi yêu cầu hủy đơn hàng #${order.id}`);
    const orderToUpdate = orders.value.find(o => o.id === order.id);
    if (orderToUpdate) {
      orderToUpdate.status = 'Đã hủy';
      orderToUpdate.canCancel = false;
    }
    if (selectedOrder.value && selectedOrder.value.id === order.id) {
      selectedOrder.value.status = 'Đã hủy';
      selectedOrder.value.canCancel = false;
    }
  }
};
const handleRepurchaseList = (order) => {
  alert(`Đã thêm các sản phẩm của đơn hàng #${order.id} vào giỏ hàng!`);
  // router.push('/cart');
};
const handleReturnList = (order) => {
  if (confirm(`Bạn có chắc muốn yêu cầu hoàn hàng cho đơn hàng #${order.id} không?`)) {
    alert(`Đã gửi yêu cầu hoàn hàng cho đơn hàng #${order.id}.`);
    const orderToUpdate = orders.value.find(o => o.id === order.id);
    if (orderToUpdate) {
      orderToUpdate.canReturn = false;
    }
    if (selectedOrder.value && selectedOrder.value.id === order.id) {
      selectedOrder.value.canReturn = false;
    }
  }
};
const handleStartReviewFromList = (order) => {
  openDetailPopup(order, true);
};

</script>

<template>
  <div class="order-list-container">
    <h2 class="title">📋 Danh Sách Đơn Hàng</h2>

    <div v-if="orders.length > 0">
      
      <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input 
          type="text" 
          v-model="searchQuery" 
          placeholder="Tìm theo mã đơn hàng hoặc tên sản phẩm..."
          class="search-bar"
        >
      </div>

      <div v-if="filteredOrders.length > 0">
        <div class="order-cards">
          <div v-for="order in paginatedOrders" :key="order.id" class="order-card">
            
            <div @click.stop="openDetailPopup(order)">
              <div class="card-header">
                <strong>Đơn hàng #{{ order.id }}</strong>
              </div>
              
              <p class="card-status-line">
                Trạng thái: 
                <span :class="['status-badge', getStatusClass(order.status)]">
                  {{ order.status }}
                </span>
              </p>
              
              <p>Ngày đặt: {{ formatDate(order.date) }}</p>

              <div class="product-table">
                <div class="product-header">
                  <span class="col-name">Sản phẩm</span>
                  <span class="col-qty">SL</span>
                  <span class="col-price">Giá</span>
                </div>
                <div v-for="(product, index) in order.items" :key="index" class="product-row">
                  <span class="col-name">{{ product.name }}</span>
                  <span class="col-qty">x{{ product.qty || product.quantity }}</span>
                  <span class="col-price">{{ formatCurrency(product.price) }}</span>
                </div>
              </div>
              <p class="total-amount">Tổng cộng: <strong>{{ formatCurrency(order.total) }}</strong></p>
            </div>

            <div class="card-action-buttons">
              <button class="detail-btn" @click.stop="openDetailPopup(order)">
                <i class="fas fa-eye"></i> Xem Chi Tiết
              </button>
              <button
                v-if="order.canCancel && order.status === 'Đang giao hàng'"
                class="action-btn-list danger-btn-list"
                @click.stop="handleCancelList(order)">
                <i class="fas fa-times-circle"></i> Hủy Đơn
              </button>
              <button
                v-if="order.canRepurchase"
                class="action-btn-list primary-btn-list"
                @click.stop="handleRepurchaseList(order)">
                <i class="fas fa-redo-alt"></i> Mua Lại
              </button>
              <button
                v-if="order.canReturn && (order.status === 'Đã giao thành công' || order.status === 'Hoàn thành, có thể đánh giá')"
                class="action-btn-list secondary-btn-list"
                @click.stop="handleReturnList(order)">
                <i class="fas fa-undo-alt"></i> Hoàn Hàng
              </button>
              <button
                v-if="order.canReview && !order.isReviewed && (order.status === 'Đã giao thành công' || order.status === 'Hoàn thành, có thể đánh giá')"
                class="action-btn-list success-btn-list"
                @click.stop="handleStartReviewFromList(order)">
                <i class="fas fa-star"></i> Đánh Giá
              </button>
            </div>
            
          </div>
        </div>

        <div class="pagination-container" v-if="totalPages > 1">
          <button @click="prevPage" :disabled="currentPage === 1" class="page-btn">
            &laquo; Trước
          </button>
          
          <button 
            v-for="page in totalPages" 
            :key="page"
            @click="setPage(page)"
            :class="['page-btn', { 'active': currentPage === page }]"
          >
            {{ page }}
          </button>

          <button @click="nextPage" :disabled="currentPage === totalPages" class="page-btn">
            Sau &raquo;
          </button>
        </div>

      </div>
      
      <p v-else class="no-results">
        Không tìm thấy đơn hàng nào khớp với tìm kiếm của bạn.
      </p>

    </div>

    <p v-else class="no-orders">Bạn chưa có đơn hàng nào.</p>

    
    <div v-if="showPopup && selectedOrder" class="popup-overlay" @click.self="closeDetailPopup">
      <div class="popup-content">
        <button class="close-btn" @click="closeDetailPopup">×</button>
        <h2 class="popup-title">🛒 Chi Tiết Đơn Hàng #{{ selectedOrder.id }}</h2>

        <div class="status-progress-bar-container">
          <div v-if="getActiveStepIndex === -2" class="cancelled-status-message">
            ❌ Đơn hàng đã bị hủy
          </div>
          <div v-else class="status-progress-bar">
            <div v-for="(step, index) in orderSteps" :key="step.key" class="step" :class="{
              'active': index <= getActiveStepIndex,
              'current': index === getActiveStepIndex
            }">
              <div class="icon-container">
                <i v-if="step.key === 'placed'" class="fas fa-box-open"></i>
                <i v-else-if="step.key === 'shipping_prep'" class="fas fa-truck-loading"></i>
                <i v-else-if="step.key === 'in_transit'" class="fas fa-shipping-fast"></i>
                <i v-else-if="step.key === 'delivered'" class="fas fa-check-circle"></i>
              </div>
              <div class="step-label">{{ step.label }}</div>
            </div>
            <div class="progress-line">
              <div class="progress-fill"
                :style="{ width: getActiveStepIndex === -1 ? '0%' : (getActiveStepIndex / (orderSteps.length - 1)) * 100 + '%' }">
              </div>
            </div>
          </div>
        </div>
        <div class="detail-card info-section">
          <h3><i class="fas fa-info-circle section-icon"></i> Thông tin Đơn hàng</h3>
          <div class="info-row">
            <span>Mã đơn hàng:</span>
            <strong>#{{ selectedOrder.id }}</strong>
          </div>
          
          <div class="info-row">
            <span>Ngày đặt:</span>
            <span>{{ formatDate(selectedOrder.date) }}</span>
          </div>

          <div class="info-row">
            <span>Trạng thái:</span>
            <strong class="status-text">{{ selectedOrder.status }}</strong>
          </div>
        </div>

        <div class="detail-card customer-section">
          <h3><i class="fas fa-user section-icon"></i> Thông tin Khách hàng</h3>
          <p><strong>{{ selectedOrder.customer.name }}</strong></p>
          <p><i class="fas fa-phone-alt"></i> {{ selectedOrder.customer.phone }}</p>
          <p><i class="fas fa-map-marker-alt"></i> {{ selectedOrder.customer.address }}</p>
        </div>

        <div class="detail-card product-section">
          <h3><i class="fas fa-box-open section-icon"></i> Sản phẩm đã đặt</h3>
          <div class="product-list-popup">
            <div v-for="item in selectedOrder.items" :key="item.id" class="product-item">
              <img :src="item.image" :alt="item.name" class="product-image">
              <div class="product-info">
                <span class="product-name">{{ item.name }}</span>
                <span class="product-qty">Số lượng: x{{ item.qty }}</span>
              </div>
              <span class="product-price">{{ formatCurrency(item.price * item.qty) }}</span>
            </div>
          </div>
        </div>

        <div class="detail-card payment-section">
          <h3><i class="fas fa-credit-card section-icon"></i> Chi tiết Thanh toán</h3>
          <div class="summary-row">
            <span>Tạm tính:</span>
            <span>{{ formatCurrency(selectedOrder.payment.subtotal) }}</span>
          </div>
          <div class="summary-row">
            <span>Phí giao hàng:</span>
            <span>{{ formatCurrency(selectedOrder.payment.shippingFee) }}</span>
          </div>
          <div class="summary-row total">
            <strong>Tổng cộng:</strong>
            <strong class="total-amount">{{ formatCurrency(selectedOrder.payment.total) }}</strong>
          </div>
          <div class="summary-row payment-method">
            <span>Hình thức thanh toán:</span>
            <span><i class="fas fa-money-bill-wave"></i> {{ selectedOrder.payment.method }}</span>
          </div>
        </div>

        <div class="detail-card action-section">
          <h3><i class="fas fa-cogs section-icon"></i> Hành Động</h3>
          <div class="action-buttons">
            <button v-if="isCancellable" @click="handleCancel" class="action-btn danger-btn">
              <i class="fas fa-times-circle"></i> Hủy Đơn Hàng
            </button>
            <button v-if="isRepurchasable" @click="handleRepurchase" class="action-btn primary-btn">
              <i class="fas fa-redo-alt"></i> Mua Lại Đơn Này
            </button>
            <button v-if="isReviewAvailable" @click="handleStartReview" class="action-btn success-btn">
              <i class="fas fa-star"></i> Đánh Giá
            </button>
            <button v-else-if="selectedOrder.isReviewed" class="action-btn disabled-btn" disabled>
              <i class="fas fa-check-circle"></i> Đã Đánh Giá
            </button>
            <button v-if="isReturnable" @click="handleReturn" class="action-btn secondary-btn">
              <i class="fas fa-undo-alt"></i> Yêu Cầu Hoàn Hàng
            </button>
          </div>
        </div>

        <div v-if="isReviewing" class="detail-card review-form-section">
          <h3><i class="fas fa-comment-dots section-icon"></i> Gửi Đánh Giá Của Bạn</h3>
          <div class="rating-stars">
            <span v-for="star in 5" :key="star" @click="reviewRating = star"
              :class="{ 'star-icon': true, 'active': star <= reviewRating }">
              ★
            </span>
          </div>
          <textarea v-model="reviewText" placeholder="Viết nhận xét của bạn..."></textarea>
          <button @click="handleSubmitReview" class="action-btn primary-btn submit-review-btn">
            <i class="fas fa-paper-plane"></i> Gửi Đánh Giá
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
/* Định nghĩa màu sắc (ĐÃ BỔ SUNG WARNING-COLOR) */
:root {
  --primary-color: #009981;
  --danger-color: #E74C3C;
  --secondary-color: #3498DB;
  --success-color: #28A745;
  --warning-color: #F39C12; /* <-- DÒNG NÀY ĐÃ ĐƯỢC THÊM */
  --text-color: #333;
  --light-gray: #f8f8f8;
  --border-color: #e0e0e0;
}

/* --- STYLES CHO DANH SÁCH --- */
.order-list-container {
  padding: 20px;
  max-width: 900px;
  margin: 0 auto;
  background-color: var(--light-gray);
  min-height: 100vh;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.title {
  color: var(--primary-color);
  border-bottom: 3px solid var(--primary-color);
  padding-bottom: 10px;
  margin-bottom: 25px;
}

.order-cards {
  display: grid;
  gap: 20px;
}

.order-card {
  background-color: #FFFFFF;
  border-radius: 8px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
  padding: 15px 20px;
  transition: transform 0.2s, box-shadow 0.2s;
  border-left: 5px solid var(--primary-color);
}

.order-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
}

.order-card .card-header,
.order-card .product-table {
  cursor: pointer;
}

.card-header {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}

.card-status-line {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  margin-top: 5px; 
  font-weight: 500;
  color: #555;
}


.status-badge {
  padding: 5px 10px;
  border-radius: 5px;
  font-size: 0.85em;
  font-weight: bold;
  color:#009981;
}

.status-dang-giao-hang {
  background-color: var(--secondary-color);
}
.status-da-giao-thanh-cong,
.status-hoan-thanh-co-the-danh-gia {
  background-color: var(--success-color);
}
.status-da-huy {
  background-color: #95A5A6;
}
/* ** FIX LỖI: Đây là CSS bị thiếu cho trạng thái "Đã đặt hàng" ** */
.status-da-dat-hang {
  background-color: var(--warning-color); /* <-- DÒNG NÀY ĐÃ ĐƯỢC THÊM */
}

.product-table {
  margin: 10px 0;
  font-size: 0.95em;
  width: 100%;
}

.product-header {
  display: grid;
  grid-template-columns: 1fr 60px 100px;
  font-weight: bold;
  color: #555;
  border-bottom: 2px solid #ddd;
  padding-bottom: 5px;
  margin-bottom: 5px;
}

.product-row {
  display: grid;
  grid-template-columns: 1fr 60px 100px;
  padding: 5px 0;
  border-bottom: 1px dashed #ddd;
  align-items: center;
}

.col-name { text-align: left; }
.col-qty { text-align: center; }
.col-price { text-align: right; }

.total-amount {
  margin-top: 10px;
  font-size: 1.1em;
  color: var(--primary-color);
}

.detail-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background-color: var(--primary-color);
  color: white;
  padding: 8px 15px;
  border-radius: 5px;
  transition: background-color 0.3s;
  border: none;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.9em;
}
.detail-btn:hover {
  background-color: #007A65;
}

.card-action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 15px;
  border-top: 1px solid var(--border-color);
  padding-top: 15px;
}

.card-action-buttons .detail-btn {
  margin-top: 0;
  flex-grow: 1;
  flex-basis: 120px;
}

.action-btn-list {
  padding: 8px 15px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.9em;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex-grow: 1;
  flex-basis: 120px;
}

.action-btn-list i {
  font-size: 1em;
}

.primary-btn-list {
  background-color: var(--primary-color);
  color: white;
}
.primary-btn-list:hover {
  background-color: #007A65;
}
.danger-btn-list {
  background-color: red;
  color: white;
}
.danger-btn-list:hover {
  background-color: #C0392B;
}
.secondary-btn-list {
  background-color: rgb(220, 53, 69);
  color: white;
}
.secondary-btn-list:hover {
  background-color: rgb(192, 44, 59);
}
.success-btn-list {
  background-color: green;
  color: white;
}
.success-btn-list:hover {
  background-color: #218838;
}

.no-orders {
  text-align: center;
  color: #666;
  font-style: italic;
  font-size: 1.1em;
  padding: 20px;
}


/* --- ** STYLES MỚI CHO TÌM KIẾM, PHÂN TRANG, NO-RESULTS ** --- */

.search-container {
  margin-bottom: 25px;
  background-color: #fff;
  padding: 15px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  position: relative;
}

.search-icon {
  position: absolute;
  top: 50%;
  left: 30px; /* 15px padding của container + 15px */
  transform: translateY(-50%);
  color: #999;
  font-size: 1em;
  z-index: 10;
}

.search-bar {
  width: 100%;
  padding: 12px 15px;
  font-size: 1em;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  box-sizing: border-box; 
  transition: border-color 0.2s, box-shadow 0.2s;
  padding-left: 45px; 
}

.search-bar:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(0, 153, 129, 0.1);
}

.pagination-container {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 25px;
  flex-wrap: wrap;
  gap: 8px;
}

.page-btn {
  background-color: #fff;
  border: 1px solid var(--border-color);
  color: var(--primary-color);
  padding: 8px 14px;
  border-radius: 5px;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.2s, color 0.2s;
}

.page-btn:hover:not(:disabled) {
  background-color: var(--light-gray);
}

.page-btn.active {
  background-color: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}

.page-btn:disabled {
  background-color: #f5f5f5;
  color: #ccc;
  cursor: not-allowed;
}

.no-results {
  text-align: center;
  color: #888;
  font-style: italic;
  font-size: 1.1em;
  padding: 20px;
  margin-top: 15px;
  background-color: #fff;
  border-radius: 8px;
  border: 1px dashed var(--border-color);
}


/* --- STYLES CHO POPUP (Giữ nguyên toàn bộ) --- */
.popup-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.popup-content {
  background: #FFFFFF;
  border-radius: 12px;
  padding: 25px 30px;
  width: 95%;
  max-width: 650px;
  position: relative;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
  max-height: 90vh;
  overflow-y: auto;
}

.close-btn {
  position: absolute;
  top: 15px;
  right: 20px;
  background: none;
  border: none;
  font-size: 2rem;
  color: #888;
  cursor: pointer;
  transition: color 0.2s;
}
.close-btn:hover {
  color: #000;
}

.popup-title {
  text-align: center;
  font-size: 1.6em;
  font-weight: bold;
  color: var(--primary-color);
  margin-bottom: 20px;
}

.detail-card {
  background-color: #fcfcfc;
  border-radius: 8px;
  box-shadow: none;
  border: 1px solid var(--border-color);
  padding: 15px 20px;
  margin-bottom: 15px;
}
.detail-card h3 {
  border-left: 5px solid var(--primary-color);
  padding-left: 15px;
  font-size: 1.2em;
  margin-bottom: 15px;
}
.product-list-popup {
  display: flex;
  flex-direction: column;
  gap: 15px;
}
.info-section .info-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 1em;
  border-bottom: 1px dashed var(--border-color);
}
.info-section .status-text {
  color: var(--primary-color);
  font-weight: bold;
}
.customer-section p {
  margin: 8px 0;
  color: #555;
  line-height: 1.6;
  display: flex;
  align-items: center;
  gap: 10px;
}
.customer-section p strong {
  color: var(--text-color);
}
.product-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border-color);
}
.product-list-popup .product-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}
.product-image {
  width: 60px;
  height: 60px;
  border-radius: 6px;
  object-fit: cover;
  flex-shrink: 0;
}
.product-name {
  font-weight: bold;
  color: var(--text-color);
  margin-bottom: 3px;
  font-size: 1em;
}
.product-qty {
  font-size: 0.9em;
  color: #777;
}
.product-price {
  font-weight: bold;
  color: var(--primary-color);
  white-space: nowrap;
  font-size: 1em;
}
.payment-section .summary-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 1em;
  color: #555;
}
.payment-section .summary-row.total {
  font-size: 1.2em;
  font-weight: bold;
  color: var(--text-color);
  border-top: 2px solid var(--border-color);
  padding-top: 10px;
  margin-top: 5px;
}
.payment-section .total-amount {
  color: var(--primary-color);
}
.payment-method {
  font-size: 0.95em;
  color: #777;
  padding-top: 5px;
  border-top: 1px dashed var(--border-color);
  display: flex;
  align-items: center;
  gap: 8px;
}
.action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.action-btn {
  padding: 12px 20px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 1em;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  flex-grow: 1;
}
.action-btn i {
  font-size: 1.1em;
}
.primary-btn {
  background-color: var(--primary-color);
  color: white;
}
.primary-btn:hover {
  background-color: #007A65;
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}
.danger-btn {
  background-color: red;
  color: white;
}
.danger-btn:hover {
  background-color: #C0392B;
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}
.success-btn {
  background-color: green;
  color: white;
}
.success-btn:hover {
  background-color: #218838;
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}
.secondary-btn {
  background-color: rgb(220, 53, 69);
  color: white;
}
.secondary-btn:hover {
  background-color: rgb(192, 44, 59);
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}
.disabled-btn {
  background-color: #e0e0e0;
  color: #999;
  cursor: not-allowed;
  box-shadow: none;
  opacity: 0.8;
}
.disabled-btn:hover {
  transform: none;
  box-shadow: none;
}
.review-form-section textarea {
  width: 100%;
  min-height: 120px;
  padding: 12px;
  margin: 15px 0;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  box-sizing: border-box;
  font-family: inherit;
  font-size: 1em;
}
.rating-stars {
  font-size: 1.8em;
  cursor: pointer;
  color: #ccc;
  display: flex;
  gap: 5px;
  margin-bottom: 10px;
}
.star-icon {
  transition: color 0.1s ease-in-out;
}
.star-icon.active {
  color: gold;
}
.submit-review-btn {
  width: auto;
  margin-top: 10px;
}

.status-progress-bar-container {
  padding: 20px 10px;
  margin-bottom: 20px;
  background-color: #f5f5ff;
  border-radius: 8px;
  position: relative;
  border: 1px solid #ddd;
}
.status-progress-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  position: relative;
  padding-top: 20px;
}
.progress-line {
  position: absolute;
  top: 30px;
  left: 10%;
  right: 10%;
  height: 4px;
  background-color: #ccc;
  z-index: 1;
  border-radius: 2px;
}
.progress-fill {
  height: 100%;
  background-color: var(--primary-color);
  transition: width 0.5s ease-in-out;
  border-radius: 2px;
}
.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  width: 25%;
  position: relative;
  z-index: 2;
}
.icon-container {
  width: 40px;
  height: 40px;
  background-color: #fff;
  border: 3px solid #ccc;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  color: #ccc;
  font-size: 1.2em;
  margin-bottom: 10px;
  transition: all 0.3s ease;
}
.step.active .icon-container {
  border-color: var(--primary-color);
  color: var(--primary-color);
}
.step.current .icon-container {
  background-color: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
  transform: scale(1.1);
  box-shadow: 0 0 0 5px rgba(0, 153, 129, 0.2);
}
.step-label {
  font-size: 0.9em;
  font-weight: 500;
  color: #999;
  min-height: 40px;
  transition: color 0.3s ease;
}
.step.active .step-label {
  color: var(--text-color);
}
.step.current .step-label {
  font-weight: bold;
  color: var(--primary-color);
}
.cancelled-status-message {
  text-align: center;
  padding: 15px;
  background-color: #FADBD8;
  color: var(--danger-color);
  border: 1px solid var(--danger-color);
  border-radius: 6px;
  font-weight: bold;
  font-size: 1.1em;
}
</style>