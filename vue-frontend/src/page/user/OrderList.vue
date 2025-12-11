<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useStore } from 'vuex'; 
import apiService from '../../apiService.js';
import Swal from 'sweetalert2';

const router = useRouter();
const store = useStore();

// --- CẤU HÌNH ĐƯỜNG DẪN ẢNH ---
const SERVER_URL = 'http://127.0.0.1:8000';

const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/70x70?text=No+Img';
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  return `${SERVER_URL}/${cleanPath}`;
};

// --- STATE ---
const orders = ref([]);
const isLoading = ref(false);

// Popup & Review logic
const showPopup = ref(false);
const selectedOrder = ref(null);
const isReviewing = ref(false);

// [CẬP NHẬT] reviewData lưu trạng thái đánh giá cho TỪNG sản phẩm
// Cấu trúc: { [itemId]: { rating: 0, content: '', product_id: 123 } }
const reviewData = ref({}); 

// --- TÍNH NĂNG: Tìm kiếm và Phân trang ---
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 5;

// --- [MỚI] HÀM XỬ LÝ TIẾNG VIỆT ---
const removeVietnameseTones = (str) => {
  if (!str) return "";
  str = str.toLowerCase();
  str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, "a");
  str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, "e");
  str = str.replace(/ì|í|ị|ỉ|ĩ/g, "i");
  str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, "o");
  str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, "u");
  str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g, "y");
  str = str.replace(/đ/g, "d");
  str = str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
  return str;
};

// --- HELPER: LẤY NHÃN BIẾN THỂ ---
const getVariantLabel = (item) => {
  if (item.variant_name && item.variant_name !== 'Mặc định') return item.variant_name;
  
  if (Array.isArray(item.attributes) && item.attributes.length > 0) {
      if (typeof item.attributes[0] === 'object') {
          return item.attributes.map(a => {
              const attrName = a.name || a.attribute?.name || ''; 
              const attrValue = a.value || '';
              return attrName ? `${attrName}: ${attrValue}` : attrValue;
          }).join(' - ').replace(/^: | :$/g, '');
      }
      return item.attributes.join(' - ');
  }

  if (item.attributes && typeof item.attributes === 'object') {
     return Object.values(item.attributes).join(' - ');
  }
  return null;
};

// --- Helper Map Trạng thái ---
const mapStatusBackendToFrontend = (status) => {
  const map = {
    'pending': 'Đã đặt hàng',
    'confirmed': 'Chờ chuyển phát',
    'processing': 'Chờ chuyển phát',
    'shipping': 'Đang giao hàng',
    'shipped': 'Đang giao hàng',
    'delivered': 'Đã giao thành công',
    'completed': 'Hoàn thành, có thể đánh giá',
    'cancelled': 'Đã hủy',
    'returned': 'Đã trả hàng',
    'returning': 'Đang trả hàng'
  };
  return map[status] || 'Đã đặt hàng';
};

// --- FETCH ORDERS ---
const fetchOrders = async () => {
  isLoading.value = true;
  try {
    const response = await apiService.get('/orders');
    const rawData = response.data.data || response.data;

    orders.value = rawData.map(order => {
      const statusVN = mapStatusBackendToFrontend(order.status);
      
      // Logic xác định trạng thái hoàn thành để cho phép đánh giá
      const canReviewState = order.status === 'delivered' || order.status === 'completed';

      return {
        id: String(order.id),
        date: order.created_at,
        status: statusVN,
        statusRaw: order.status,
        
        canCancel: order.status === 'pending',
        canRepurchase: true, // Luôn cho phép mua lại
        canReturn: order.status === 'delivered',
        canReview: canReviewState,
        isReviewed: false, // Backend cần trả về cờ này nếu muốn ẩn nút đánh giá sau khi đã đánh giá

        items: order.items ? order.items.map(item => {
          const variant = item.variant || {};
          const product = variant.product || {};
          let displayName = product.name || item.product_name || 'Sản phẩm';
          const rawAttributes = variant.attribute_values || variant.attributes;
          let simpleVariantLabel = null;
          if (variant.name && variant.name !== 'Mặc định' && variant.name !== displayName) {
             simpleVariantLabel = variant.name;
          }
          const rawImagePath = variant.image || product.thumbnail_url;

          // Lấy Product ID chuẩn để gửi review
          const realProductId = product.id || item.product_id;

          return {
            id: item.id, // ID của order_item
            product_id: realProductId, // ID sản phẩm thực tế
            name: displayName,
            variant_name: simpleVariantLabel, 
            attributes: rawAttributes, 
            image: getImageUrl(rawImagePath),
            qty: item.quantity,
            price: item.price,
            quantity: item.quantity
          };
        }) : [],

        customer: {
          name: order.customer_name,
          phone: order.customer_phone,
          address: order.shipping_address
        },

        payment: {
          subtotal: order.subtotal_amount,
          shippingFee: order.shipping_fee,
          total: order.total_amount,
          method: order.payment_method
        },
        total: order.total_amount 
      };
    });

    orders.value.sort((a, b) => new Date(b.date) - new Date(a.date));

  } catch (error) {
    console.error("Lỗi tải đơn hàng:", error);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchOrders();
});

// --- LOGIC UI (Search & Pagination) ---
const sortedOrders = computed(() => orders.value);

const filteredOrders = computed(() => {
  const query = removeVietnameseTones(searchQuery.value.trim()); // Chuẩn hóa từ khóa tìm kiếm
  
  if (!query) return sortedOrders.value;

  return sortedOrders.value.filter(order => {
    const orderIdMatch = String(order.id).toLowerCase().includes(query);
    const itemMatch = order.items.some(item => 
      removeVietnameseTones(item.name).includes(query)
    );
    return orderIdMatch || itemMatch;
  });
});

const totalPages = computed(() => Math.ceil(filteredOrders.value.length / itemsPerPage));
const paginatedOrders = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  const end = start + itemsPerPage;
  return filteredOrders.value.slice(start, end);
});

const setPage = (page) => { if (page >= 1 && page <= totalPages.value) currentPage.value = page; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };
const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };

watch(searchQuery, () => { currentPage.value = 1; });

// --- LOGIC CHI TIẾT & HÀNH ĐỘNG ---
const isCancellable = computed(() => selectedOrder.value?.canCancel); 
const isRepurchasable = computed(() => selectedOrder.value?.canRepurchase);
const isReturnable = computed(() => selectedOrder.value?.canReturn);
const isReviewAvailable = computed(() => selectedOrder.value?.canReview && !selectedOrder.value?.isReviewed);

const orderSteps = [
  { key: 'placed', label: 'Đã đặt hàng', statusMatch: ['Đã đặt hàng', 'pending'] },
  { key: 'shipping_prep', label: 'Chờ chuyển phát', statusMatch: ['Chờ chuyển phát', 'processing', 'confirmed'] },
  { key: 'in_transit', label: 'Đang giao hàng', statusMatch: ['Đang giao hàng', 'shipping', 'shipped'] },
  { key: 'delivered', label: 'Đã giao đơn hàng', statusMatch: ['Đã giao thành công', 'Hoàn thành, có thể đánh giá', 'delivered', 'completed'] },
];

const getActiveStepIndex = computed(() => {
  if (!selectedOrder.value) return -1;
  if (selectedOrder.value.status === 'Đã hủy' || selectedOrder.value.statusRaw === 'cancelled') return -2;
  let activeIndex = -1;
  const currentStatus = selectedOrder.value.status;
  for (let i = orderSteps.length - 1; i >= 0; i--) {
    if (orderSteps[i].label === currentStatus || orderSteps[i].statusMatch.includes(currentStatus) || orderSteps[i].statusMatch.includes(selectedOrder.value.statusRaw)) {
        activeIndex = i;
        break;
    }
  }
  if (currentStatus === 'Đang giao hàng') return 2;
  if (currentStatus === 'Đã đặt hàng') return 0;
  return activeIndex;
});

const formatCurrency = (amount) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
const formatDate = (isoDate) => {
  if (!isoDate) return '';
  const date = new Date(isoDate);
  return date.toLocaleDateString('vi-VN');
};

const getStatusClass = (status) => {
  if (!status) return '';
  const s = status.toLowerCase();
  if (s.includes('hủy')) return 'status-da-huy';
  if (s.includes('đặt hàng')) return 'status-da-dat-hang';
  if (s.includes('giao hàng')) return 'status-dang-giao-hang';
  if (s.includes('thành công') || s.includes('hoàn thành')) return 'status-da-giao-thanh-cong';
  return 'status-default';
};

// --- XỬ LÝ MỞ POPUP & REVIEW ---
const openDetailPopup = (order, startReview = false) => {
  selectedOrder.value = order;
  isReviewing.value = startReview;
  
  // [CẬP NHẬT] Khởi tạo object reviewData cho từng sản phẩm
  reviewData.value = {};
  if (order && order.items) {
      order.items.forEach(item => {
          reviewData.value[item.id] = {
              product_id: item.product_id,
              rating: 0,
              content: ''
          };
      });
  }
  
  showPopup.value = true;
};

const closeDetailPopup = () => {
  showPopup.value = false;
  selectedOrder.value = null;
  isReviewing.value = false;
};

// --- API Actions ---
const handleRepurchaseList = async (order) => {
  try {
    Swal.fire({ title: 'Đang xử lý...', didOpen: () => Swal.showLoading() });
    await apiService.post(`/orders/${order.id}/repurchase`);
    await store.dispatch('fetchCart'); 
    Swal.fire('Thành công!', 'Sản phẩm đã được thêm vào giỏ!', 'success').then((r) => {
      if (r.isConfirmed) router.push('/cart');
    });
  } catch (error) {
    console.error("Lỗi mua lại:", error);
    Swal.fire('Lỗi', 'Không thể mua lại lúc này.', 'error');
  }
};

const handleCancelList = async (order) => {
  const result = await Swal.fire({
    title: 'Xác nhận hủy?',
    text: `Bạn có chắc muốn hủy đơn hàng #${order.id} không?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Đồng ý hủy',
    cancelButtonText: 'Không'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`/order/${order.id}`);
      Swal.fire('Thành công!', `Đơn hàng #${order.id} đã được hủy.`, 'success');
      await fetchOrders(); 
      if (selectedOrder.value && String(selectedOrder.value.id) === String(order.id)) {
        closeDetailPopup();
      }
    } catch (error) {
      console.error("Lỗi hủy đơn:", error);
      Swal.fire('Lỗi', 'Không thể hủy đơn hàng này.', 'error');
    }
  }
};

const handleReturnList = (order) => {
  Swal.fire('Yêu cầu hoàn hàng', `Đã gửi yêu cầu hoàn hàng cho đơn hàng #${order.id}.`, 'info');
};

const handleStartReviewFromList = (order) => {
  openDetailPopup(order, true);
};

// Wrapper functions cho Popup
const handleCancel = () => { handleCancelList(selectedOrder.value); };
const handleRepurchase = () => { handleRepurchaseList(selectedOrder.value); }; 
const handleStartReview = () => { 
    isReviewing.value = true;
    // Khởi tạo lại reviewData nếu chuyển từ xem chi tiết sang đánh giá
    if (selectedOrder.value && Object.keys(reviewData.value).length === 0) {
        selectedOrder.value.items.forEach(item => {
            reviewData.value[item.id] = {
                product_id: item.product_id,
                rating: 0,
                content: ''
            };
        });
    }
};
const handleReturn = () => { handleReturnList(selectedOrder.value); };

// --- [CẬP NHẬT] GỬI ĐÁNH GIÁ (MULTIPLE) ---
const handleSubmitReviews = async () => {
  // Lọc ra các sản phẩm đã được đánh giá (rating > 0)
  const reviewsToSend = Object.values(reviewData.value).filter(r => r.rating > 0);

  if (reviewsToSend.length === 0) {
    Swal.fire('Chưa có đánh giá', 'Vui lòng chọn số sao cho ít nhất một sản phẩm!', 'warning');
    return;
  }

  try {
    Swal.fire({ title: 'Đang gửi đánh giá...', didOpen: () => Swal.showLoading() });

    // Gửi song song các request đánh giá cho từng sản phẩm
    const promises = reviewsToSend.map(review => 
        apiService.post('/reviews', {
            product_id: review.product_id,
            rating: review.rating,
            content: review.content
        })
    );

    await Promise.all(promises);

    Swal.fire('Cảm ơn!', 'Đánh giá của bạn đã được gửi thành công!', 'success');
    isReviewing.value = false;
    // Tùy chọn: Refresh lại đơn hàng để cập nhật trạng thái nếu backend có trả về isReviewed
    // fetchOrders(); 
    
  } catch (error) {
    console.error("Lỗi gửi review:", error);
    const msg = error.response?.data?.message || 'Có lỗi xảy ra khi gửi đánh giá.';
    Swal.fire('Thất bại', msg, 'error');
  }
};
</script>

<template>
  <div class="order-list-container">
    <h2 class="title">📋 Danh Sách Đơn Hàng</h2>

    <div v-if="isLoading" class="loading-state">
      <i class="fas fa-spinner fa-spin"></i> Đang tải dữ liệu...
    </div>

    <div v-else>
      <div v-if="orders.length > 0">
        
        <div class="search-container">
          <i class="fas fa-search search-icon"></i>
          <input 
            type="text" 
            :value="searchQuery"
            @input="searchQuery = $event.target.value"
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
                    <div class="col-name-wrapper col-name">
                        <div class="product-name-text">{{ product.name }}</div>
                        <div class="item-variants" v-if="getVariantLabel(product)">
                            <span class="variant-badge">
                                <i class="fa-solid fa-layer-group"></i> {{ getVariantLabel(product) }}
                            </span>
                        </div>
                    </div>
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
                  v-if="order.canCancel && (order.status === 'Đã đặt hàng' || order.status === 'Đang giao hàng')"
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
    </div>

    <!-- POPUP -->
    <div v-if="showPopup && selectedOrder" class="popup-overlay" @click.self="closeDetailPopup">
      <div class="popup-content">
        <button class="close-btn" @click="closeDetailPopup">×</button>
        <h2 class="popup-title">🛒 Chi Tiết Đơn Hàng #{{ selectedOrder.id }}</h2>

        <!-- Progress Bar -->
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
                :style="{ width: getActiveStepIndex < 0 ? '0%' : (getActiveStepIndex / (orderSteps.length - 1)) * 100 + '%' }">
              </div>
            </div>
          </div>
        </div>

        <!-- Thông tin chung -->
        <div class="detail-grid">
            <div class="detail-card info-section">
                <h3><i class="fas fa-info-circle section-icon"></i> Thông tin Đơn hàng</h3>
                <div class="info-row"><span>Mã đơn hàng:</span> <strong>#{{ selectedOrder.id }}</strong></div>
                <div class="info-row"><span>Ngày đặt:</span> <span>{{ formatDate(selectedOrder.date) }}</span></div>
                <div class="info-row"><span>Trạng thái:</span> <strong class="status-text">{{ selectedOrder.status }}</strong></div>
            </div>

            <div class="detail-card customer-section">
                <h3><i class="fas fa-user section-icon"></i> Thông tin Khách hàng</h3>
                <p><strong>{{ selectedOrder.customer.name }}</strong></p>
                <p><i class="fas fa-phone-alt"></i> {{ selectedOrder.customer.phone }}</p>
                <p><i class="fas fa-map-marker-alt"></i> {{ selectedOrder.customer.address }}</p>
            </div>
        </div>

        <!-- [CHỈ HIỆN KHI KHÔNG REVIEW] Danh sách sản phẩm -->
        <div v-if="!isReviewing" class="detail-card product-section">
          <h3><i class="fas fa-box-open section-icon"></i> Sản phẩm đã đặt</h3>
          <div class="product-list-popup">
            <div v-for="item in selectedOrder.items" :key="item.id" class="product-item">
              <img :src="item.image" :alt="item.name" class="product-image" @error="$event.target.src = 'https://placehold.co/70x70?text=No+Img'">
              <div class="product-info">
                <span class="product-name">{{ item.name }}</span>
                <div class="item-variants" v-if="getVariantLabel(item)">
                    <span class="variant-badge">
                        <i class="fa-solid fa-layer-group"></i> {{ getVariantLabel(item) }}
                    </span>
                </div>
                <span class="product-qty">Số lượng: x{{ item.qty || item.quantity }}</span>
              </div>
              <span class="product-price">{{ formatCurrency(item.price * (item.qty || item.quantity)) }}</span>
            </div>
          </div>
        </div>

        <!-- [CHỈ HIỆN KHI KHÔNG REVIEW] Thanh toán & Action -->
        <div v-if="!isReviewing">
            <div class="detail-card payment-section">
              <h3><i class="fas fa-credit-card section-icon"></i> Chi tiết Thanh toán</h3>
              <div class="summary-row"><span>Tạm tính:</span> <span>{{ formatCurrency(selectedOrder.payment.subtotal) }}</span></div>
              <div class="summary-row"><span>Phí giao hàng:</span> <span>{{ formatCurrency(selectedOrder.payment.shippingFee) }}</span></div>
              <div class="summary-row total"><strong>Tổng cộng:</strong> <strong class="total-amount">{{ formatCurrency(selectedOrder.payment.total) }}</strong></div>
              <div class="summary-row payment-method"><span>Hình thức thanh toán:</span> <span><i class="fas fa-money-bill-wave"></i> {{ selectedOrder.payment.method }}</span></div>
            </div>

            <div class="detail-card action-section">
              <h3><i class="fas fa-cogs section-icon"></i> Hành Động</h3>
              <div class="action-buttons">
                <button v-if="isCancellable" @click="handleCancel" class="action-btn danger-btn"><i class="fas fa-times-circle"></i> Hủy Đơn Hàng</button>
                <button v-if="isRepurchasable" @click="handleRepurchase" class="action-btn primary-btn"><i class="fas fa-redo-alt"></i> Mua Lại Đơn Này</button>
                <button v-if="isReviewAvailable" @click="handleStartReview" class="action-btn success-btn"><i class="fas fa-star"></i> Đánh Giá</button>
                <button v-else-if="selectedOrder.isReviewed" class="action-btn disabled-btn" disabled><i class="fas fa-check-circle"></i> Đã Đánh Giá</button>
                <button v-if="isReturnable" @click="handleReturn" class="action-btn secondary-btn"><i class="fas fa-undo-alt"></i> Yêu Cầu Hoàn Hàng</button>
              </div>
            </div>
        </div>

        <!-- [SECTION MỚI] FORM ĐÁNH GIÁ TỪNG SẢN PHẨM -->
        <div v-if="isReviewing" class="review-container">
          <h3 class="review-header"><i class="fas fa-star section-icon"></i> Đánh Giá Sản Phẩm</h3>
          <p class="review-hint">Vui lòng đánh giá các sản phẩm bạn đã mua.</p>
          
          <div class="review-scroll-list">
              <div v-for="item in selectedOrder.items" :key="item.id" class="review-item-card">
                  <!-- Thông tin sản phẩm -->
                  <div class="review-product-info">
                      <img :src="item.image" class="review-thumb" @error="$event.target.src = 'https://placehold.co/50x50?text=No+Img'">
                      <div>
                          <div class="review-prod-name">{{ item.name }}</div>
                          <div class="review-prod-variant" v-if="getVariantLabel(item)">
                              {{ getVariantLabel(item) }}
                          </div>
                      </div>
                  </div>
                  
                  <!-- Form nhập liệu -->
                  <div class="review-input-area">
                      <div class="rating-stars">
                          <span v-for="star in 5" :key="star" 
                                @click="reviewData[item.id].rating = star"
                                :class="{ 'star-icon': true, 'active': star <= reviewData[item.id].rating }">
                            ★
                          </span>
                          <span class="rating-label" v-if="reviewData[item.id].rating > 0">
                              {{ reviewData[item.id].rating }} sao
                          </span>
                      </div>
                      <textarea 
                          v-model="reviewData[item.id].content" 
                          placeholder="Chất lượng sản phẩm thế nào? Hãy chia sẻ với mọi người nhé..."
                      ></textarea>
                  </div>
              </div>
          </div>

          <div class="review-actions-footer">
              <button @click="isReviewing = false" class="action-btn secondary-btn back-btn">
                  Quay lại
              </button>
              <button @click="handleSubmitReviews" class="action-btn primary-btn submit-review-btn">
                  <i class="fas fa-paper-plane"></i> Gửi Đánh Giá
              </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
/* STYLES GIỮ NGUYÊN (Thêm 1 class loading) */
:root {
  --primary-color: #009981;
  --danger-color: #E74C3C;
  --secondary-color: #3498DB;
  --success-color: #28A745;
  --warning-color: #F39C12;
  --text-color: #333;
  --light-gray: #f8f8f8;
  --border-color: #e0e0e0;
}

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

.loading-state {
  text-align: center;
  font-size: 1.2em;
  color: #666;
  padding: 40px;
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
  color:#fff;
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
.status-da-dat-hang {
  background-color: var(--warning-color); 
}
.status-default {
  background-color: #777;
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

/* [UPDATED] CSS cho cột tên sản phẩm trong danh sách */
.col-name-wrapper {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}
.product-name-text {
    /* Styles cho tên sản phẩm */
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

/* [UPDATED] STYLES CHO BADGE BIẾN THỂ */
.variant-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f3f4f6;
    color: #4b5563;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.85em;
    border: 1px solid #e5e7eb;
    margin-top: 4px;
    font-weight: 500;
}
.variant-badge i {
    font-size: 0.9em;
    color: var(--primary-color);
}
.item-variants {
    margin-top: 2px;
}

/* [NEW] STYLE CHO DROPDOWN CHỌN SẢN PHẨM */
.review-product-select {
    margin-bottom: 15px;
}
.form-select {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1em;
    background-color: #fff;
}

/* --- [CSS MỚI] CHO PHẦN ĐÁNH GIÁ TÁCH RIÊNG --- */
.review-container {
    margin-top: 15px;
    border-top: 2px dashed #ddd;
    padding-top: 15px;
}
.review-header {
    text-align: center;
    color: var(--primary-color);
    margin-bottom: 5px;
}
.review-hint {
    text-align: center;
    color: #777;
    margin-bottom: 15px;
    font-size: 0.9em;
}
.review-scroll-list {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 5px;
}
.review-item-card {
    background: #f9f9f9;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}
.review-product-info {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 12px;
    border-bottom: 1px solid #e5e5e5;
    padding-bottom: 8px;
}
.review-thumb {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    object-fit: cover;
    border: 1px solid #ddd;
}
.review-prod-name {
    font-weight: 600;
    font-size: 0.95em;
    color: #333;
}
.review-prod-variant {
    font-size: 0.8em;
    color: #666;
    background: #e0e0e0;
    padding: 2px 6px;
    border-radius: 3px;
    width: fit-content;
    margin-top: 2px;
}
.review-input-area {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.rating-stars {
    font-size: 1.8em;
    color: #ddd;
    cursor: pointer;
    display: flex;
    gap: 5px;
    align-items: center;
}
.star-icon { transition: color 0.2s; }
.star-icon.active { color: #FFD700; }
.rating-label {
    font-size: 0.6em;
    color: #666;
    margin-left: 10px;
    font-weight: normal;
}
.review-input-area textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    min-height: 80px;
    font-family: inherit;
    resize: vertical;
}
.review-actions-footer {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    position: sticky;
    bottom: 0;
    background: #fff;
    padding-top: 10px;
    border-top: 1px solid #eee;
}
.back-btn {
    flex: 0 0 auto;
    width: auto;
    padding: 0 20px;
}
</style>