<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
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

// reviewData lưu trạng thái đánh giá cho TỪNG sản phẩm
const reviewData = ref({});

// --- TÍNH NĂNG: Tìm kiếm, Tabs và Phân trang ---
const searchQuery = ref('');
const currentTab = ref('all');
const currentPage = ref(1);
const itemsPerPage = 5;

const tabs = [
  { label: 'Tất cả', value: 'all' },
  { label: 'Chờ xác nhận', value: 'pending' },
  { label: 'Chờ lấy hàng', value: 'processing' },
  { label: 'Đang giao', value: 'shipping' },
  { label: 'Hoàn thành', value: 'completed' },
  { label: 'Đã hủy/Trả', value: 'cancelled' }
];

// --- HÀM XỬ LÝ TIẾNG VIỆT ---
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
  if (!status) return 'Đã đặt hàng';
  const s = status.toLowerCase().trim();
  const map = {
    'pending': 'Đã đặt hàng',
    'approved': 'Đã duyệt',
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
  return map[s] || map[status] || 'Đã đặt hàng';
};

// --- Helper Class CSS cho Badge ---
const getStatusClass = (statusRaw) => {
  if (!statusRaw) return 'status-default';
  const s = statusRaw.toLowerCase().trim();
  
  if (['cancelled'].includes(s)) return 'status-da-huy';
  if (['returning', 'return'].includes(s)) return 'status-dang-tra-hang';
  if (['returned', 'refunded'].includes(s)) return 'status-da-tra-hang';
  
  if (['pending'].includes(s)) return 'status-da-dat-hang';
  if (['shipping', 'shipped', 'processing', 'confirmed', 'approved'].includes(s)) return 'status-dang-giao-hang';
  if (['delivered', 'completed'].includes(s)) return 'status-da-giao-thanh-cong';
  return 'status-default';
};

// [NEW IMPROVED] Logic xác định loại trạng thái ngoại lệ (Exception Type)
// Hàm này là "trái tim" để đảm bảo hiển thị đúng
const getExceptionType = (statusRaw) => {
  const s = statusRaw ? statusRaw.toLowerCase().trim() : '';
  if (['returning', 'return', 'processing_return'].includes(s)) return 'returning';
  if (['returned', 'refunded', 'return_completed'].includes(s)) return 'returned';
  // Mặc định là cancelled nếu rơi vào nhóm ẩn progress bar nhưng không phải hoàn trả
  return 'cancelled';
};

const getExceptionMessage = (statusRaw) => {
    const type = getExceptionType(statusRaw);
    if (type === 'returning') return 'Đơn hàng đang được xử lý hoàn trả';
    if (type === 'returned') return 'Đơn hàng đã hoàn trả thành công';
    return 'Đơn hàng đã bị hủy';
};

const getExceptionIcon = (statusRaw) => {
    const type = getExceptionType(statusRaw);
    if (type === 'returning') return 'fas fa-shipping-fast fa-flip-horizontal'; // Icon xe tải quay đầu
    if (type === 'returned') return 'fas fa-clipboard-check'; // Icon check list
    return 'fas fa-times-circle'; // Icon X
};

const getExceptionStyleClass = (statusRaw) => {
    const type = getExceptionType(statusRaw);
    if (type === 'returning') return 'msg-returning';
    if (type === 'returned') return 'msg-returned';
    return ''; // Mặc định style của cancelled
};


// --- FETCH ORDERS ---
const fetchOrders = async () => {
  isLoading.value = true;
  try {
    const response = await apiService.get('/orders');
    const rawData = response.data.data || response.data;

    orders.value = rawData.map(order => {
      const statusVN = mapStatusBackendToFrontend(order.status);
      const canReviewState = order.status === 'delivered' || order.status === 'completed';

      // Map Items và thông tin Review (nếu có)
      const mappedItems = order.items ? order.items.map(item => {
        const variant = item.variant || {};
        const product = variant.product || {};
        let displayName = product.name || item.product_name || 'Sản phẩm';
        const rawAttributes = variant.attribute_values || variant.attributes;
        let simpleVariantLabel = null;
        if (variant.name && variant.name !== 'Mặc định' && variant.name !== displayName) {
          simpleVariantLabel = variant.name;
        }
        const rawImagePath = variant.image || product.thumbnail_url;
        const realProductId = product.id || item.product_id;

        // [QUAN TRỌNG] Lấy thông tin review từ item
        const existingReview = item.review || null;

        return {
          id: item.id,
          product_id: realProductId,
          name: displayName,
          variant_name: simpleVariantLabel,
          attributes: rawAttributes,
          image: getImageUrl(rawImagePath),
          qty: item.quantity,
          price: item.price,
          quantity: item.quantity,
          review: existingReview // Lưu lại review cũ
        };
      }) : [];

      const hasAnyReview = mappedItems.some(i => i.review);

      return {
        id: String(order.id),
        date: order.created_at,
        status: statusVN,
        statusRaw: order.status,

        canCancel: order.status === 'pending',
        canRepurchase: true,
        canReturn: ['delivered', 'completed'].includes(order.status),
        canReview: canReviewState,
        hasAnyReview: hasAnyReview, 

        items: mappedItems,

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

// [FIX] Xóa thanh cuộn body khi popup mở
watch(showPopup, (newVal) => {
  if (newVal) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

onUnmounted(() => {
  document.body.style.overflow = '';
});

// --- LOGIC UI ---
const sortedOrders = computed(() => orders.value);

const filteredOrders = computed(() => {
  let result = sortedOrders.value;
  if (currentTab.value !== 'all') {
    result = result.filter(order => {
      const s = order.statusRaw ? order.statusRaw.toLowerCase().trim() : '';
      if (currentTab.value === 'pending') return s === 'pending';
      if (currentTab.value === 'processing') return ['confirmed', 'processing', 'approved'].includes(s);
      if (currentTab.value === 'shipping') return ['shipping', 'shipped'].includes(s);
      if (currentTab.value === 'completed') return ['delivered', 'completed'].includes(s);
      if (currentTab.value === 'cancelled') return ['cancelled', 'returned', 'returning'].includes(s);
      return true;
    });
  }
  const query = removeVietnameseTones(searchQuery.value.trim());
  if (query) {
    result = result.filter(order => {
      const orderIdMatch = String(order.id).toLowerCase().includes(query);
      const itemMatch = order.items.some(item =>
        removeVietnameseTones(item.name).includes(query)
      );
      return orderIdMatch || itemMatch;
    });
  }
  return result;
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

watch([searchQuery, currentTab], () => { currentPage.value = 1; });

// --- LOGIC CHI TIẾT & HÀNH ĐỘNG ---
const isCancellable = computed(() => selectedOrder.value?.canCancel);
const isRepurchasable = computed(() => selectedOrder.value?.canRepurchase);
const isReturnable = computed(() => selectedOrder.value?.canReturn);
// Vẫn cho phép review nếu trạng thái ok, không chặn bởi biến isReviewed cũ nữa
const isReviewAvailable = computed(() => selectedOrder.value?.canReview);

const orderSteps = [
  { key: 'placed', label: 'Đã đặt hàng', statusMatch: ['Đã đặt hàng', 'pending'] },
  { key: 'shipping_prep', label: 'Chờ chuyển phát', statusMatch: ['Chờ chuyển phát', 'processing', 'confirmed', 'approved'] },
  { key: 'in_transit', label: 'Đang giao hàng', statusMatch: ['Đang giao hàng', 'shipping', 'shipped'] },
  { key: 'delivered', label: 'Đã giao đơn hàng', statusMatch: ['Đã giao thành công', 'Hoàn thành, có thể đánh giá', 'delivered', 'completed'] },
];

const getActiveStepIndex = computed(() => {
  if (!selectedOrder.value) return -1;
  const raw = selectedOrder.value.statusRaw ? selectedOrder.value.statusRaw.toLowerCase().trim() : '';
  
  // Xác định xem có phải là nhóm trạng thái đặc biệt (Hủy/Trả) hay không
  // Dùng includes để bắt cả các biến thể (vd: returned_pending)
  if (['cancelled', 'returned', 'returning', 'refunded', 'return'].some(k => raw.includes(k))) {
    return -2;
  }

  let activeIndex = -1;
  for (let i = orderSteps.length - 1; i >= 0; i--) {
    if (orderSteps[i].statusMatch.map(s => s.toLowerCase()).includes(raw)) {
      activeIndex = i;
      break;
    }
  }
  return activeIndex;
});

const formatCurrency = (amount) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
const formatDate = (isoDate) => {
  if (!isoDate) return '';
  const date = new Date(isoDate);
  return date.toLocaleDateString('vi-VN');
};

const openDetailPopup = (order, startReview = false) => {
  selectedOrder.value = order;
  isReviewing.value = startReview;
  reviewData.value = {};

  if (order && order.items) {
    order.items.forEach(item => {
      if (item.review) {
        reviewData.value[item.id] = { 
          product_id: item.product_id, 
          rating: item.review.rating || 5, 
          content: item.review.content || '' 
        };
      } else {
        reviewData.value[item.id] = { product_id: item.product_id, rating: 0, content: '' };
      }
    });
  }
  showPopup.value = true;
};

const closeDetailPopup = () => {
  showPopup.value = false;
  selectedOrder.value = null;
  isReviewing.value = false;
};

// --- API ACTIONS ---
const handleRepurchaseList = async (order) => {
  try {
    Swal.fire({ title: 'Đang xử lý...', didOpen: () => Swal.showLoading() });
    await apiService.post(`/orders/${order.id}/repurchase`);
    await store.dispatch('fetchCart');
    Swal.fire('Thành công!', 'Sản phẩm đã được thêm vào giỏ!', 'success').then((r) => {
      if (r.isConfirmed) router.push('/cart');
    });
  } catch (error) {
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
      Swal.fire('Lỗi', 'Không thể hủy đơn hàng này.', 'error');
    }
  }
};

const handleReturnList = async (order) => {
  const result = await Swal.fire({
    title: 'Yêu cầu hoàn hàng?',
    text: `Bạn có chắc muốn yêu cầu hoàn hàng cho đơn #${order.id}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Gửi yêu cầu',
    cancelButtonText: 'Hủy'
  });

  if (result.isConfirmed) {
    try {
      Swal.fire({ title: 'Đang xử lý...', didOpen: () => Swal.showLoading() });
      await apiService.post(`/orders/${order.id}/return`);
      
      Swal.fire('Thành công!', 'Yêu cầu hoàn hàng đã được gửi. Vui lòng chờ Admin xử lý.', 'success');
      await fetchOrders(); 
      
      if (selectedOrder.value && String(selectedOrder.value.id) === String(order.id)) {
        closeDetailPopup();
      }
    } catch (error) {
       Swal.fire('Lỗi', error.response?.data?.message || 'Không thể gửi yêu cầu hoàn hàng.', 'error');
    }
  }
};

const handleStartReviewFromList = (order) => openDetailPopup(order, true);
const handleCancel = () => handleCancelList(selectedOrder.value);
const handleRepurchase = () => handleRepurchaseList(selectedOrder.value);
const handleStartReview = () => isReviewing.value = true;
const handleReturn = () => handleReturnList(selectedOrder.value);

const handleSubmitReviews = async () => {
  const reviewsToSend = Object.values(reviewData.value).filter(r => r.rating > 0);
  if (reviewsToSend.length === 0) {
    Swal.fire('Chưa có đánh giá', 'Vui lòng chọn số sao cho ít nhất một sản phẩm!', 'warning');
    return;
  }
  try {
    Swal.fire({ title: 'Đang gửi đánh giá...', didOpen: () => Swal.showLoading() });
    const promises = reviewsToSend.map(review =>
      apiService.post('/reviews', { 
        product_id: review.product_id, 
        rating: review.rating, 
        content: review.content,
        order_id: selectedOrder.value.id 
      })
    );
    await Promise.all(promises);
    Swal.fire('Cảm ơn!', 'Đánh giá của bạn đã được gửi thành công!', 'success');
    isReviewing.value = false;
    await fetchOrders();
  } catch (error) {
    Swal.fire('Thất bại', error.response?.data?.message || 'Có lỗi xảy ra.', 'error');
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

        <div class="tabs-container">
          <button v-for="tab in tabs" :key="tab.value" @click="currentTab = tab.value"
            :class="['tab-btn', { active: currentTab === tab.value }]">
            {{ tab.label }}
          </button>
        </div>

        <div class="search-container">
          <i class="fas fa-search search-icon"></i>
          <input type="text" :value="searchQuery" @input="searchQuery = $event.target.value"
            placeholder="Tìm theo mã đơn hàng hoặc tên sản phẩm..." class="search-bar">
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
                  <span :class="['status-badge', getStatusClass(order.statusRaw)]">
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
                  <div v-for="(product, index) in order.items.slice(0, 3)" :key="index" class="product-row">
                    <div class="col-name-wrapper col-name">
                      <div class="product-name-text">{{ product.name }}</div>
                      <div class="item-variants" v-if="getVariantLabel(product)">
                        <span class="variant-badge"><i class="fa-solid fa-layer-group"></i> {{ getVariantLabel(product)
                          }}</span>
                      </div>
                    </div>
                    <span class="col-qty">x{{ product.qty || product.quantity }}</span>
                    <span class="col-price">{{ formatCurrency(product.price) }}</span>
                  </div>
                  <div v-if="order.items.length > 3" class="more-items-hint">
                    ... và {{ order.items.length - 3 }} sản phẩm khác
                  </div>
                </div>
                <p class="total-amount">Tổng cộng: <strong>{{ formatCurrency(order.total) }}</strong></p>
              </div>

              <div class="card-action-buttons">
                <button class="detail-btn" @click.stop="openDetailPopup(order)"><i class="fas fa-eye"></i> Xem Chi
                  Tiết</button>
                <button v-if="order.canCancel" class="action-btn-list danger-btn-list"
                  @click.stop="handleCancelList(order)"><i class="fas fa-times-circle"></i> Hủy Đơn</button>
                <button v-if="order.canRepurchase" class="action-btn-list primary-btn-list"
                  @click.stop="handleRepurchaseList(order)"><i class="fas fa-redo-alt"></i> Mua Lại</button>
                <button v-if="order.canReturn" class="action-btn-list secondary-btn-list"
                  @click.stop="handleReturnList(order)"><i class="fas fa-undo-alt"></i> Hoàn Hàng</button>
                
                <button v-if="order.canReview" 
                  :class="['action-btn-list', order.hasAnyReview ? 'warning-btn-list' : 'success-btn-list']"
                  @click.stop="handleStartReviewFromList(order)">
                  <i :class="order.hasAnyReview ? 'fas fa-edit' : 'fas fa-star'"></i> 
                  {{ order.hasAnyReview ? 'Sửa Đánh Giá' : 'Đánh Giá' }}
                </button>
              </div>
            </div>
          </div>

          <div class="pagination-container" v-if="totalPages > 1">
            <button @click="prevPage" :disabled="currentPage === 1" class="page-btn">&laquo; Trước</button>
            <button v-for="page in totalPages" :key="page" @click="setPage(page)"
              :class="['page-btn', { 'active': currentPage === page }]">{{ page }}</button>
            <button @click="nextPage" :disabled="currentPage === totalPages" class="page-btn">Sau &raquo;</button>
          </div>
        </div>
        <p v-else class="no-results">Không tìm thấy đơn hàng nào ở mục này.</p>
      </div>
      <p v-else class="no-orders">Bạn chưa có đơn hàng nào.</p>
    </div>

    <!-- POPUP -->
    <div v-if="showPopup && selectedOrder" class="popup-overlay" @click.self="closeDetailPopup">
      <div class="popup-content">
        <button class="close-btn" @click="closeDetailPopup">×</button>
        <h2 class="popup-title">🛒 Chi Tiết Đơn Hàng #{{ selectedOrder.id }}</h2>

        <div class="status-progress-bar-container">
          <!-- [MODIFIED] Logic hiển thị thông báo trạng thái đặc biệt -->
          <div v-if="getActiveStepIndex === -2" 
               :class="['cancelled-status-message', getExceptionStyleClass(selectedOrder.statusRaw)]">
             <i :class="getExceptionIcon(selectedOrder.statusRaw)"></i> {{ getExceptionMessage(selectedOrder.statusRaw) }}
          </div>
          
          <div v-else class="status-progress-bar">
            <div v-for="(step, index) in orderSteps" :key="step.key" class="step"
              :class="{ 'active': index <= getActiveStepIndex, 'current': index === getActiveStepIndex }">
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

        <div class="popup-body-scroll">
          <div class="detail-grid">
            <div class="detail-card info-section">
              <h3><i class="fas fa-info-circle section-icon"></i> Thông tin Đơn hàng</h3>
              <div class="info-row"><span>Mã đơn hàng:</span> <strong>#{{ selectedOrder.id }}</strong></div>
              <div class="info-row"><span>Ngày đặt:</span> <span>{{ formatDate(selectedOrder.date) }}</span></div>
              <div class="info-row"><span>Trạng thái:</span> <strong
                  :class="['status-badge', getStatusClass(selectedOrder.statusRaw)]">{{ selectedOrder.status }}</strong>
              </div>
            </div>
            <div class="detail-card customer-section">
              <h3><i class="fas fa-user section-icon"></i> Thông tin Khách hàng</h3>
              <p><strong>{{ selectedOrder.customer.name }}</strong></p>
              <p><i class="fas fa-phone-alt"></i> {{ selectedOrder.customer.phone }}</p>
              <p><i class="fas fa-map-marker-alt"></i> {{ selectedOrder.customer.address }}</p>
            </div>
          </div>

          <div v-if="!isReviewing" class="detail-card product-section">
            <h3><i class="fas fa-box-open section-icon"></i> Sản phẩm đã đặt</h3>
            <div class="product-list-popup">
              <div v-for="item in selectedOrder.items" :key="item.id" class="product-item">
                <img :src="item.image" @error="$event.target.src = 'https://placehold.co/70x70?text=No+Img'"
                  class="product-image">
                <div class="product-info">
                  <span class="product-name">{{ item.name }}</span>
                  <div class="item-variants" v-if="getVariantLabel(item)">
                    <span class="variant-badge"><i class="fa-solid fa-layer-group"></i> {{ getVariantLabel(item)
                      }}</span>
                  </div>
                  <span class="product-qty">Số lượng: x{{ item.qty || item.quantity }}</span>
                  <div v-if="item.review" class="reviewed-badge">
                    <i class="fas fa-check-circle"></i> Đã đánh giá: {{ item.review.rating }} sao
                  </div>
                </div>
                <span class="product-price">{{ formatCurrency(item.price * (item.qty || item.quantity)) }}</span>
              </div>
            </div>
          </div>

          <div v-if="!isReviewing">
            <div class="detail-card payment-section">
              <h3><i class="fas fa-credit-card section-icon"></i> Chi tiết Thanh toán</h3>
              <div class="summary-row"><span>Tạm tính:</span> <span>{{ formatCurrency(selectedOrder.payment.subtotal)
                  }}</span></div>
              <div class="summary-row"><span>Phí giao hàng:</span> <span>{{
                formatCurrency(selectedOrder.payment.shippingFee) }}</span></div>
              <div class="summary-row total"><strong>Tổng cộng:</strong> <strong class="total-amount">{{
                formatCurrency(selectedOrder.payment.total) }}</strong></div>
              <div class="summary-row payment-method"><span>Hình thức thanh toán:</span> <span><i
                    class="fas fa-money-bill-wave"></i> {{ selectedOrder.payment.method }}</span></div>
            </div>
            <div class="detail-card action-section">
              <h3><i class="fas fa-cogs section-icon"></i> Hành Động</h3>
              <div class="action-buttons">
                <button v-if="isCancellable" @click="handleCancel" class="action-btn danger-btn"><i
                    class="fas fa-times-circle"></i> Hủy Đơn Hàng</button>
                <button v-if="isRepurchasable" @click="handleRepurchase" class="action-btn primary-btn"><i
                    class="fas fa-redo-alt"></i> Mua Lại Đơn Này</button>
                
                <button v-if="isReviewAvailable" @click="handleStartReview" 
                  :class="['action-btn', selectedOrder.hasAnyReview ? 'warning-btn' : 'success-btn']">
                  <i :class="selectedOrder.hasAnyReview ? 'fas fa-edit' : 'fas fa-star'"></i> 
                  {{ selectedOrder.hasAnyReview ? 'Xem/Sửa Đánh Giá' : 'Đánh Giá' }}
                </button>
                
                <button v-if="isReturnable" @click="handleReturn" class="action-btn secondary-btn"><i
                    class="fas fa-undo-alt"></i> Yêu Cầu Hoàn Hàng</button>
              </div>
            </div>
          </div>

          <div v-if="isReviewing" class="review-container">
            <h3 class="review-header"><i class="fas fa-star section-icon"></i> {{ selectedOrder.hasAnyReview ? 'Cập Nhật Đánh Giá' : 'Đánh Giá Sản Phẩm' }}</h3>
            <p class="review-hint">
              {{ selectedOrder.hasAnyReview 
                 ? 'Bạn có thể chỉnh sửa lại đánh giá của mình. Đánh giá sẽ được gửi lại để duyệt.' 
                 : 'Vui lòng đánh giá các sản phẩm bạn đã mua.' 
              }}
            </p>
            <div class="review-scroll-list">
              <div v-for="item in selectedOrder.items" :key="item.id" class="review-item-card">
                <div class="review-product-info">
                  <img :src="item.image" @error="$event.target.src = 'https://placehold.co/50x50?text=No+Img'"
                    class="review-thumb">
                  <div>
                    <div class="review-prod-name">{{ item.name }}</div>
                    <div class="review-prod-variant" v-if="getVariantLabel(item)">{{ getVariantLabel(item) }}</div>
                  </div>
                </div>
                <div class="review-input-area">
                  <div class="rating-stars">
                    <span v-for="star in 5" :key="star" @click="reviewData[item.id].rating = star"
                      :class="{ 'star-icon': true, 'active': star <= reviewData[item.id].rating }">★</span>
                    <span class="rating-label" v-if="reviewData[item.id].rating > 0">{{ reviewData[item.id].rating }}
                      sao</span>
                  </div>
                  <textarea v-model="reviewData[item.id].content"
                    placeholder="Chất lượng sản phẩm thế nào? Hãy chia sẻ với mọi người nhé..."></textarea>
                </div>
              </div>
            </div>
            <div class="review-actions-footer">
              <button @click="isReviewing = false" class="action-btn secondary-btn back-btn"><i class="fas fa-eye"></i>  Xem Chi Tiết</button>
              <button @click="handleSubmitReviews" class="action-btn primary-btn submit-review-btn"><i
                  class="fas fa-paper-plane"></i> {{ selectedOrder.hasAnyReview ? 'Cập Nhật' : 'Gửi Đánh Giá' }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* STYLES GỐC CỦA BẠN (Đã fix cứng màu để hiển thị tốt) */
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
  color: #fff;
}

/* [FIXED] Set cứng màu Hex để đảm bảo hiển thị */
.status-dang-giao-hang {
  background-color: #3498DB;
}

/* Blue */
.status-da-giao-thanh-cong {
  background-color: #28A745;
}

/* Green */
.status-da-huy {
  background-color: #DC3545;
}

/* Red */
.status-da-dat-hang {
  background-color: #F39C12;
}

/* [NEW] Style cho trạng thái trả hàng */
.status-dang-tra-hang {
  background-color: #8e44ad; /* Tím cho trạng thái đang xử lý trả */
}

.status-da-tra-hang {
  background-color: #34495e; /* Màu tối cho đã hoàn tất trả */
}


/* Orange */
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

.col-name-wrapper {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.col-name {
  text-align: left;
}

.col-qty {
  text-align: center;
}

.col-price {
  text-align: right;
}

.more-items-hint {
  font-size: 0.85em;
  color: #888;
  font-style: italic;
  padding-top: 5px;
}

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

.primary-btn-list {
  background-color: var(--primary-color);
  color: white;
}

.danger-btn-list {
  background-color: red;
  color: white;
}

.secondary-btn-list {
  background-color: rgb(220, 53, 69);
  color: white;
}

.success-btn-list {
  background-color: green;
  color: white;
}

.warning-btn-list {
  background-color: #F39C12;
  color: white;
}

.no-orders {
  text-align: center;
  color: #666;
  font-style: italic;
  font-size: 1.1em;
  padding: 20px;
}

.tabs-container {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  overflow-x: auto;
  padding-bottom: 5px;
  border-bottom: 2px solid #eee;
}

.tab-btn {
  padding: 10px 15px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 1em;
  font-weight: 500;
  color: #666;
  border-bottom: 3px solid transparent;
  transition: all 0.3s;
  white-space: nowrap;
}

.tab-btn:hover {
  color: var(--primary-color);
  background-color: rgba(0, 153, 129, 0.05);
}

.tab-btn.active {
  color: var(--primary-color);
  border-bottom-color: var(--primary-color);
  font-weight: bold;
}

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
  left: 30px;
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
  transition: background-color 0.2s;
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
  padding: 0;
  width: 95%;
  max-width: 900px;
  height: 90vh;
  max-height: 90vh;
  position: relative;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
}

.popup-title {
  text-align: center;
  font-size: 1.6em;
  font-weight: bold;
  color: var(--primary-color);
  padding: 20px 0 10px 0;
  margin: 0;
  border-bottom: 1px solid #eee;
}

.popup-body-scroll {
  flex: 1;
  overflow-y: auto;
  padding: 20px 30px;
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
  z-index: 10;
}

.close-btn:hover {
  color: #000;
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

.customer-section p {
  margin: 8px 0;
  color: #555;
  line-height: 1.6;
  display: flex;
  align-items: center;
  gap: 10px;
}

.product-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border-color);
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

.primary-btn {
  background-color: var(--primary-color);
  color: white;
}

.danger-btn {
  background-color: red;
  color: white;
}

.success-btn {
  background-color: green;
  color: white;
}

.secondary-btn {
  background-color: rgb(220, 53, 69);
  color: white;
}

.warning-btn {
  background-color: #F39C12;
  color: white;
}

.disabled-btn {
  background-color: #e0e0e0;
  color: #999;
  cursor: not-allowed;
}

/* === UPDATED STYLES FOR REVIEW FORM === */

/* Style cho Textarea */
.review-input-area textarea {
  width: 100%;
  min-height: 100px;
  padding: 12px;
  margin-bottom: 10px; /* Thêm khoảng cách dưới */
  border: 1px solid #ccebe6; /* Viền xanh nhạt */
  border-radius: 8px; /* Bo tròn */
  box-sizing: border-box;
  font-family: inherit;
  font-size: 0.95em;
  background-color: #fff;
  transition: all 0.3s ease;
  resize: vertical; /* Chỉ cho phép kéo dãn chiều dọc */
}

.review-input-area textarea:focus {
  outline: none;
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px rgba(0, 153, 129, 0.1); /* Hiệu ứng focus */
}

/* Style cho hàng Sao */
.rating-stars {
  font-size: 1.8em;
  cursor: pointer;
  color: #e0e0e0; /* Màu sao chưa chọn */
  display: flex;
  align-items: center; /* Căn giữa theo chiều dọc */
  gap: 5px;
  margin-bottom: 12px;
}

.star-icon.active {
  color: #FFD700; /* Màu vàng đậm hơn */
}

.rating-label {
  font-size: 0.85em; /* Tăng kích thước chữ lên một chút */
  color: var(--primary-color); /* Đồng bộ màu text với theme */
  margin-left: 10px;
  font-weight: 600;
  padding-top: 4px; /* Tinh chỉnh nhỏ để thẳng hàng với sao */
}

.submit-review-btn {
  /* Xóa margin-top cũ, dùng gap của cha */
  margin-top: 0; 
}

.status-progress-bar-container {
  padding: 0px 10px;
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

/* [MODIFIED] Style chung cho thông báo trạng thái ngoại lệ */
.cancelled-status-message {
  text-align: center;
  padding: 15px;
  background-color: #FADBD8;
  color: var(--danger-color);
  border: 1px solid var(--danger-color);
  border-radius: 6px;
  font-weight: bold;
  font-size: 1.1em;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

/* [NEW] Style riêng cho đang hoàn (màu cam/vàng hoặc xanh nhạt) */
.msg-returning {
  background-color: #fcf3cf !important; /* Dùng !important để override class gốc */
  color: #d35400 !important;
  border-color: #f39c12 !important;
}

/* [NEW] Style riêng cho đã hoàn (màu xanh lá nhạt hoặc xám) */
.msg-returned {
  background-color: #d6eaf8 !important; /* Dùng !important để override class gốc */
  color: #2c3e50 !important;
  border-color: #34495e !important;
}


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

.reviewed-badge {
  color: var(--success-color);
  font-size: 0.85em;
  margin-top: 4px;
  font-weight: 600;
}

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
  padding-right: 5px;
}

/* Updated Review Item Card */
.review-item-card {
  background: rgba(0, 153, 129, 0.04); /* Xanh nhạt theo tông màu */
  border: 1px solid rgba(0, 153, 129, 0.2); /* Viền xanh rất nhạt */
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 15px;
}

.review-product-info {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-bottom: 12px;
  border-bottom: 1px solid rgba(0,0,0,0.05); /* Viền mờ hơn */
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
  color: var(--primary-color); /* Đồng bộ màu tiêu đề sản phẩm */
}

.review-prod-variant {
  font-size: 0.8em;
  color: #666;
  background: #fff; /* Nền trắng cho nổi bật trên nền xanh nhạt */
  padding: 2px 6px;
  border-radius: 3px;
  width: fit-content;
  margin-top: 2px;
  border: 1px solid #eee;
}

.review-input-area {
  display: flex;
  flex-direction: column;
  gap: 5px; /* Giảm gap vì đã có margin trong elements */
}
 
/* Footer Buttons */
.review-actions-footer {
  display: flex;
  gap: 15px; /* Tăng khoảng cách giữa 2 nút */
  margin-top: 15px;
  position: sticky;
  bottom: 0;
  background: #fff;
  padding-top: 15px;
  border-top: 1px solid #eee;
}

.review-actions-footer .action-btn {
  flex: 1; /* Chia đều chiều rộng cho 2 nút */
  height: 45px; /* Chiều cao cố định bằng nhau */
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 15px;
  margin: 0; /* Xóa margin thừa */
}

.back-btn {
  /* Kế thừa style chung, không cần ghi đè width nữa */
  background-color: #6c757d; /* Màu xám chuẩn cho nút Back/Secondary */
  color: white;
}

.back-btn:hover {
  background-color: #5a6268;
}
</style>