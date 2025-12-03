<script setup>
import { ref, watch, onMounted, computed, watchEffect, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex"; 
import apiService from '../../apiService.js';
import { isInWishlist, toggleWishlist } from "../../store/wishlistStore.js";
import Swal from 'sweetalert2';

// --- CONFIG & UTILS ---
const route = useRoute();
const router = useRouter();
const store = useStore();

const SERVER_URL = 'http://127.0.0.1:8000';    
const USE_STORAGE = false; 

// Lấy thông tin User hiện tại (từ Vuex hoặc LocalStorage) để check quyền xóa
const currentUser = computed(() => {
    // Ưu tiên lấy từ store auth module, fallback về localStorage nếu store chưa sync kịp
    return store.state.auth?.user || store.state.user || JSON.parse(localStorage.getItem('user') || 'null');
});

const formatDate = (dateString) => {
    if (!dateString) return new Date().toLocaleDateString('vi-VN');
    const date = new Date(dateString);
    // Format: 14:30 20/10/2023
    return `${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')} ${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
};

// Hàm tạo màu ngẫu nhiên cho avatar placeholder dựa trên tên
const getAvatarColor = (name) => {
    if (!name) return '#6c757d'; 
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const c = (hash & 0x00FFFFFF).toString(16).toUpperCase();
    return '#' + '00000'.substring(0, 6 - c.length) + c;
};

// Lấy chữ cái đầu của tên để làm Avatar chữ
const getInitials = (name) => {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

// --- FIX CORE: XỬ LÝ HIỂN THỊ USER INFO ---

/**
 * Lấy tên hiển thị ưu tiên từ bảng Users kết nối qua user_id
 * Logic: User Object (fullName) -> User Object (name) -> user_name (fallback cũ) -> "Khách hàng"
 */
const getUserDisplayName = (userObj, fallbackName) => {
    // 1. Nếu có object user từ quan hệ with('user')
    if (userObj) {
        if (userObj.fullName) return userObj.fullName; // Chuẩn schema của bạn
        if (userObj.full_name) return userObj.full_name;
        if (userObj.name) return userObj.name;
    }
    
    // 2. Nếu không có object user, dùng tên lưu tạm trong comment (nếu có)
    if (fallbackName) return fallbackName;

    // 3. Fallback cuối cùng
    return "Khách hàng";
};

/**
 * Lấy URL Avatar
 * Logic: Nếu là link ngoài (Google/FB) -> dùng luôn. Nếu là link local -> nối thêm SERVER_URL
 */
const getUserAvatar = (userObj) => {
    if (!userObj || !userObj.avatar_url) return null;
    
    const url = userObj.avatar_url;
    
    // Trường hợp 1: Link tuyệt đối (https://...)
    if (url.startsWith('http')) return url;
    
    // Trường hợp 2: Link tương đối (storage/uploads/...)
    const cleanPath = url.startsWith('/') ? url.substring(1) : url;
    
    // Kiểm tra xem path đã có chữ 'storage' chưa để tránh duplicate
    if (cleanPath.startsWith('storage/')) {
        return `${SERVER_URL}/${cleanPath}`;
    }
    return `${SERVER_URL}/storage/${cleanPath}`;
};

// --- END FIX ---

// --- NOTIFICATION SYSTEM ---
const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.addEventListener('mouseenter', Swal.stopTimer)
    toast.addEventListener('mouseleave', Swal.resumeTimer)
  }
});

const notify = (type, message, title = '') => {
    if (type === 'success') {
        Toast.fire({ icon: 'success', title: message });
    } else if (type === 'error' || type === 'warning') {
        Swal.fire({
            icon: type,
            title: title || (type === 'error' ? 'Lỗi!' : 'Chú ý!'),
            text: message,
            confirmButtonText: 'Đã hiểu',
            confirmButtonColor: 'var(--primary-color)',
        });
    } else {
        Toast.fire({ icon: 'info', title: message });
    }
};

const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/500x500?text=No+Img';
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  // Cấu hình lại logic lấy ảnh sản phẩm
  return `${SERVER_URL}/${cleanPath}`; 
};

// --- STATE ---
const product = ref(null);
const reviews = ref([]);    
const comments = ref([]);   
const rawCommentsList = ref([]); 
const quantity = ref(1);
const loading = ref(true);
const isFavorite = ref(false);
const activeTab = ref('desc'); // 'desc' | 'review' | 'comment'

// --- PAGINATION STATE ---
const ITEMS_PER_PAGE = 5; 

// Review Pagination
const reviewPage = ref(1);
const totalReviewPages = computed(() => Math.ceil(reviews.value.length / ITEMS_PER_PAGE));
const paginatedReviews = computed(() => {
    const start = (reviewPage.value - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;
    return reviews.value.slice(start, end);
});
const changeReviewPage = (page) => {
    if (page >= 1 && page <= totalReviewPages.value) reviewPage.value = page;
};

// Comment Pagination
const commentPage = ref(1);
const totalCommentPages = computed(() => Math.ceil(comments.value.length / ITEMS_PER_PAGE));
const paginatedComments = computed(() => {
    const start = (commentPage.value - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;
    return comments.value.slice(start, end);
});
const changeCommentPage = (page) => {
    if (page >= 1 && page <= totalCommentPages.value) commentPage.value = page;
};

// --- COMMENT & REPLY STATE ---
const newCommentContent = ref(''); 
const isSubmittingComment = ref(false);

const activeReplyId = ref(null); 
const replyContent = ref('');
const isSubmittingReply = ref(false);

// Variants State
const groupedAttributes = ref({}); 
const selectedOptions = ref({});   
const availableVariant = ref(null); 

const selectedImage = ref('');
const allProducts = ref([]);
const relatedProducts = ref([]);

// --- CORE LOGIC: VARIANTS ---
const isOptionDisabled = (attributeName, valueId) => {
  if (!product.value || !product.value.variants) return true;
  const currentSelectionCheck = { ...selectedOptions.value, [attributeName]: valueId };
  const exists = product.value.variants.some(variant => {
    if (!variant.attributesMap) return false;
    return Object.keys(currentSelectionCheck).every(key => {
        if (!currentSelectionCheck[key]) return true;
        return String(variant.attributesMap[key]) === String(currentSelectionCheck[key]);
    });
  });
  return !exists; 
};

const selectAttribute = (attributeName, valueId) => {
    if (selectedOptions.value[attributeName] === valueId) {
        const newSelection = { ...selectedOptions.value };
        delete newSelection[attributeName];
        selectedOptions.value = newSelection;
        availableVariant.value = null; 
        return;
    }
    let nextSelection = { ...selectedOptions.value, [attributeName]: valueId };
    Object.keys(nextSelection).forEach(key => {
        if (key === attributeName) return; 
        const isCompatible = product.value.variants.some(v => 
            v.attributesMap && 
            String(v.attributesMap[attributeName]) === String(valueId) &&
            String(v.attributesMap[key]) === String(nextSelection[key])
        );
        if (!isCompatible) delete nextSelection[key];
    });
    selectedOptions.value = nextSelection;
    quantity.value = 1; 
    findMatchingVariant();
};

const findMatchingVariant = () => {
    if(!product.value || !product.value.variants) return;
    const totalKeys = Object.keys(groupedAttributes.value).length;
    const selectedKeys = Object.keys(selectedOptions.value).length;
    if (selectedKeys < totalKeys) {
        availableVariant.value = null;
        return;
    }
    const match = product.value.variants.find(variant => {
        if (!variant.attributesMap) return false;
        return Object.keys(groupedAttributes.value).every(attrName => {
            return String(variant.attributesMap[attrName]) === String(selectedOptions.value[attrName]);
        });
    });
    availableVariant.value = match || null;
};

const processProductData = (data) => {
    if (!data.variants || data.variants.length === 0) {
        const defaultVariant = { 
            id: 'default', price: data.price || 0, stock: data.stock || 0, 
            original_price: data.original_price || 0, attributesMap: {} 
        };
        data.variants = [defaultVariant];
        groupedAttributes.value = {};
        selectedOptions.value = {};
        availableVariant.value = defaultVariant;
        return;
    }
    let groups = {};
    data.variants.forEach(variant => {
        variant.attributesMap = {}; 
        const attrs = variant.attribute_values || variant.attributeValues || [];
        if(attrs.length > 0) {
            attrs.forEach(av => {
                const attrName = av.attribute ? av.attribute.name : (av.attribute_name || 'Thuộc tính');
                const valId = av.id;
                const valName = av.value;
                if (!groups[attrName]) groups[attrName] = [];
                if (!groups[attrName].find(x => x.id === valId)) {
                    groups[attrName].push({ id: valId, value: valName });
                }
                variant.attributesMap[attrName] = valId;
            });
        }
    });
    groupedAttributes.value = groups;
    selectedOptions.value = {};
    availableVariant.value = null;
};

const fetchAllProducts = async () => {
  try {
 const res = await apiService.get(`/products`);
    allProducts.value = res.data.data || res.data || [];
  } catch (err) { console.error("Lỗi tải danh sách:", err); }
};

const loadProductById = async (id) => {
  try {
    loading.value = true;
    const productRes = await apiService.get(`/product/${id}`);
    const data = productRes.data.data || productRes.data;
    if (!data) throw new Error("No data");

    // Chuẩn hóa dữ liệu variant
    data.variants.forEach((v, i) => {
      v.stock = Number(v.stock) || 0;
      v.price = Number(v.price) || 0;
      v.original_price = Number(v.original_price) || v.price;
      v.id = v.id || i;
    });

    // Xử lý hình ảnh
    const extraImages = (data.images || []).map(img => 
       (typeof img === 'string') ? img : (img.url || img.image_url || img.path)
    ).filter(Boolean);

    data.gallery_images = [data.image_url || data.thumbnail_url, ...extraImages].filter(Boolean);
    data.gallery_images = [...new Set(data.gallery_images)]; 
    if (data.gallery_images.length === 0) data.gallery_images = ['https://placehold.co/500x500?text=No+Img'];

    product.value = data;
    selectedImage.value = product.value.gallery_images[0];
    processProductData(data);

    if (typeof isInWishlist === 'function') isFavorite.value = isInWishlist(product.value.id);
    
    reviewPage.value = 1;
    commentPage.value = 1;

    loadReviews(id);
    loadComments(id);

  } catch (error) {
    console.error("Lỗi:", error);
    notify('error', 'Không thể tải thông tin sản phẩm', 'Lỗi kết nối');
  } finally {
    loading.value = false;
  }
};

const loadReviews = async (productId) => {
    try {
        const reviewRes = await apiService.get(`/reviews?productId=${productId}`);
        reviews.value = Array.isArray(reviewRes.data) ? reviewRes.data : (reviewRes.data?.data || []);
    } catch (e) {
        reviews.value = [];
    }
};

// --- LOGIC XỬ LÝ COMMENT QUAN TRỌNG ---
const buildCommentTree = (flatList) => {
    const map = {};
    const roots = [];
    // Deep copy để tránh biến đổi mảng gốc
    const list = JSON.parse(JSON.stringify(flatList)); 

    list.forEach((comment, index) => {
        map[comment.id] = index;
        comment.replies = []; 
    });

    list.forEach(comment => {
        if (comment.parent_id !== null && map[comment.parent_id] !== undefined) {
            list[map[comment.parent_id]].replies.push(comment);
        } else {
            roots.push(comment);
        }
    });

    // Sort replies: Cũ nhất lên đầu (đọc theo dòng thời gian)
    roots.forEach(root => {
        if (root.replies.length > 0) {
            root.replies.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        }
    });
    
    // Sort roots: Mới nhất lên đầu
    return roots.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
};

const loadComments = async (productId) => {
    try {
        // Gọi API lấy comment. Yêu cầu Backend phải có .with('user')
        const commentRes = await apiService.get(`/comments?product_id=${productId}`);
        
        let rawData = [];
        if (Array.isArray(commentRes.data)) {
            rawData = commentRes.data;
        } else if (commentRes.data?.data && Array.isArray(commentRes.data.data)) {
            rawData = commentRes.data.data;
        }

        // Lọc client-side cho chắc chắn
        if (productId) {
            rawData = rawData.filter(c => String(c.product_id) === String(productId));
        }

        // --- DEBUG: Kiểm tra xem có user info không ---
        if (rawData.length > 0) {
            const sample = rawData[0];
            if (!sample.user) {
                console.warn("[Frontend Warning] Dữ liệu comment thiếu thông tin 'user'. Hãy kiểm tra Controller Backend đã thêm ->with('user') chưa.");
            } else {
                console.log("[Frontend Success] Đã nhận được thông tin user:", sample.user);
            }
        }

        rawCommentsList.value = rawData;
        comments.value = buildCommentTree(rawData);

    } catch (e) {
        console.error("Lỗi tải bình luận:", e);
        comments.value = [];
    }
};

const selectImage = (imageUrl) => selectedImage.value = imageUrl;

const navigateToProduct = (productId) => {
  router.push(`/products/${productId}`);
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const formatCurrency = (num) => {
  if (num === null || num === undefined || isNaN(num)) return "0 ₫";
  return new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(num);
};

const decreaseQty = () => { if (quantity.value > 1) quantity.value--; };
const increaseQty = () => {
  if (!availableVariant.value) return;
  const max = availableVariant.value.stock ?? 1;
  if (quantity.value < max) quantity.value++;
};
const validateQty = () => {
  if (!availableVariant.value) return;
  const max = availableVariant.value.stock ?? 1;
  if (quantity.value > max) quantity.value = max;
  if (quantity.value < 1) quantity.value = 1;
};

const onAddToCart = (productItem) => {
  if (!availableVariant.value) {
    notify('warning', 'Vui lòng chọn đầy đủ phân loại (Màu sắc, kích thước...)', 'Chưa chọn phân loại');
    return;
  }
  if (availableVariant.value.stock <= 0) {
    notify('error', 'Sản phẩm này tạm hết hàng.', 'Hết hàng');
    return;
  }
  store.dispatch('addToCart', { 
    product: productItem, 
    variant: availableVariant.value, 
    quantity: quantity.value 
  });
  notify('success', `Đã thêm ${quantity.value} sản phẩm vào giỏ!`);
};

const toggleFavorite = (productItem) => {
    if (!productItem || typeof toggleWishlist !== 'function') return;
    const added = toggleWishlist(productItem);
    isFavorite.value = added;
    if (added) notify('success', 'Đã thêm vào danh sách yêu thích!');
    else notify('info', 'Đã xóa khỏi danh sách yêu thích.');
};

// --- COMMENT ACTIONS ---
const openReplyForm = async (comment) => {
    if (activeReplyId.value === comment.id) {
        closeReplyForm();
        return;
    }
    
    activeReplyId.value = comment.id;
    const replyToName = getUserDisplayName(comment.user, comment.user_name);
    replyContent.value = `@${replyToName} `;
    
    await nextTick();
    const textarea = document.getElementById(`reply-input-${comment.id}`);
    if (textarea) textarea.focus();
};

const closeReplyForm = () => {
    activeReplyId.value = null;
    replyContent.value = '';
};

const submitNewComment = async () => {
    if (!newCommentContent.value.trim()) {
        notify('warning', 'Vui lòng nhập nội dung!');
        return;
    }
    await postComment(newCommentContent.value, null, 'new');
};

const submitReply = async (parentCommentId) => {
    if (!replyContent.value.trim()) {
        notify('warning', 'Vui lòng nhập nội dung trả lời!');
        return;
    }
    await postComment(replyContent.value, parentCommentId, 'reply');
};

const deleteComment = async (commentId) => {
    const result = await Swal.fire({
        title: 'Bạn chắc chắn?',
        text: "Bạn có muốn xóa bình luận này không?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Xóa ngay',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`/comments/${commentId}`);
            notify('success', 'Đã xóa bình luận thành công');
            await loadComments(product.value.id);
        } catch (error) {
            console.error("Lỗi xóa bình luận:", error);
            notify('error', 'Không thể xóa bình luận này.', 'Lỗi');
        }
    }
};

const postComment = async (content, parentId, type) => {
    const loadingState = type === 'new' ? isSubmittingComment : isSubmittingReply;
    loadingState.value = true;

    try {
        const payload = {
            product_id: product.value.id,
            content: content,
            parent_id: parentId
        };

        await apiService.post('/comments', payload);

        if (type === 'new') {
            newCommentContent.value = '';
        } else {
            closeReplyForm();
        }

        await loadComments(product.value.id);
        notify('success', type === 'new' ? 'Gửi câu hỏi thành công!' : 'Đã gửi câu trả lời!');
        
    } catch (error) {
        console.error("Lỗi gửi bình luận:", error);
        if (error.response?.status === 401) {
             notify('warning', 'Vui lòng đăng nhập để bình luận.');
        } else {
             notify('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
        }
    } finally {
        loadingState.value = false;
    }
};

// --- WATCHERS & HOOKS ---
onMounted(() => {
  const id = route.params.id;
  if (id) loadProductById(id);
  fetchAllProducts();
});

watch(() => route.params.id, (newId) => {
  if (newId) loadProductById(newId);
});

// [FIX] Cải thiện logic lấy sản phẩm liên quan
watchEffect(() => {
  if (product.value && allProducts.value.length > 0) {
    let related = [];
    if (product.value.category_id) {
        related = allProducts.value.filter(p => p.category_id === product.value.category_id && p.id !== product.value.id);
    }
    if (related.length < 5) {
        const others = allProducts.value.filter(p => p.id !== product.value.id && !related.includes(p));
        related = [...related, ...others];
    }
    relatedProducts.value = related.slice(0, 5);
  }
});
</script>

<template>
  <div class="container py-5 product-detail-page">
    <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted">Đang tải sản phẩm...</p>
    </div>

    <div v-if="!loading && product" class="row g-5 mb-5 fade-in-up">
      <!-- Cột Trái: Gallery -->
      <div class="col-lg-5">
        <div class="main-image-wrapper mb-3 shadow-sm">
          <img :src="getImageUrl(selectedImage)" :alt="product.name" class="main-product-image" 
           @error="$event.target.src='https://placehold.co/500x500?text=No+Image'"/>
        </div>
        <div class="thumbnail-gallery" v-if="product.gallery_images && product.gallery_images.length > 1">
          <div v-for="(image, index) in product.gallery_images" :key="index" 
               class="thumbnail-wrapper"
               :class="{ active: selectedImage === image }"
               @click="selectImage(image)">
             <img :src="getImageUrl(image)" class="thumbnail-img" />
          </div>
        </div>
      </div>

      <!-- Cột Phải: Info -->
      <div class="col-lg-7">
        <div class="product-info-box h-100">
          <h2 class="fw-bold mb-2 product-title text-dark">{{ product.name }}</h2>

          <div class="d-flex align-items-center mb-4 text-muted small">
            <div class="me-3 d-flex align-items-center">
              <span class="text-warning me-1">{{ product.average_rating || 5 }}</span>
              <i class="bi bi-star-fill text-warning me-1"></i>
            </div>
            <div class="border-start ps-3">Đã bán: <strong>{{ product.sold_count || 0 }}</strong></div>
            <div class="border-start ps-3 ms-3">Đánh giá: <strong>{{ reviews.length }}</strong></div>
          </div>

          <!-- PRICE DISPLAY -->
          <div class="price-section mb-4 p-3 rounded bg-light">
            <template v-if="availableVariant">
                <span class="fs-2 fw-bold text-danger me-2">
                  {{ formatCurrency(availableVariant.price) }}
                </span>
                <span v-if="availableVariant.original_price > availableVariant.price"
                  class="text-muted text-decoration-line-through fs-6">
                  {{ formatCurrency(availableVariant.original_price) }}
                </span>
                <span v-if="availableVariant.original_price > availableVariant.price" class="badge bg-danger ms-2">
                    -{{ Math.round((1 - availableVariant.price/availableVariant.original_price)*100) }}%
                </span>
            </template>
            <template v-else>
                <div v-if="product.min_price && product.max_price && product.min_price !== product.max_price">
                      <span class="fs-2 fw-bold text-danger me-2">
                        {{ formatCurrency(product.min_price) }} - {{ formatCurrency(product.max_price) }}
                      </span>
                </div>
                <div v-else>
                      <span class="fs-2 fw-bold text-danger me-2">
                        {{ formatCurrency(product.min_price || product.price) }}
                      </span>
                </div>
            </template>
          </div>

          <!-- ATTRIBUTES -->
          <div class="attributes-section mb-4">
             <div v-for="(values, attrName) in groupedAttributes" :key="attrName" class="attribute-group mb-4">
                <label class="fw-bold mb-2 d-block text-secondary small text-uppercase">{{ attrName }}: 
                    <span class="fw-bold text-dark ms-1" v-if="selectedOptions[attrName]">
                        {{ values.find(v => v.id === selectedOptions[attrName])?.value }}
                    </span>
                </label>
                <div class="d-flex flex-wrap gap-2">
                    <button 
                        v-for="val in values" :key="val.id"
                        class="btn chip-btn"
                        :class="{ 
                            'active': selectedOptions[attrName] === val.id,
                            'disabled': isOptionDisabled(attrName, val.id)
                        }"
                        @click="selectAttribute(attrName, val.id)"
                        :disabled="isOptionDisabled(attrName, val.id)"
                    >
                        {{ val.value }}
                    </button>
                </div>
             </div>
          </div>

          <!-- QUANTITY & ACTIONS -->
          <div class="action-wrapper border-top pt-4">
              <div class="d-flex align-items-center mb-4" v-if="availableVariant">
                <span class="fw-semibold me-3">Số lượng:</span>
                <div class="input-group qty-group shadow-sm" style="width: 140px;">
                    <button class="btn btn-white border-end-0" @click="decreaseQty"><i class="bi bi-dash"></i></button>
                    <input type="number" v-model.number="quantity" class="form-control text-center border-start-0 border-end-0 fw-bold" @change="validateQty" />
                    <button class="btn btn-white border-start-0" @click="increaseQty"><i class="bi bi-plus"></i></button>
                </div>
                <span class="ms-3 small" :class="availableVariant.stock > 0 ? 'text-success' : 'text-danger'">
                  {{ availableVariant.stock > 0 ? `Còn ${availableVariant.stock} sản phẩm` : 'Tạm hết hàng' }}
                </span>
              </div>

              <div class="d-flex gap-3">
                <button class="btn btn-lg flex-grow-1 btn-add-cart shadow-sm" 
                    @click="onAddToCart(product)" 
                    :disabled="!availableVariant || availableVariant.stock <= 0">
                  <i class="bi bi-bag-plus me-2"></i> 
                  {{ !availableVariant ? 'Chọn phân loại hàng' : (availableVariant.stock > 0 ? 'Thêm vào giỏ hàng' : 'Hết hàng') }}
                </button>
                
                <button class="btn btn-outline-danger icon-btn" @click="toggleFavorite(product)" title="Yêu thích">
                  <i :class="['bi', isFavorite ? 'bi-heart-fill' : 'bi-heart']"></i> 
                </button>
              </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 3 TABS -->
    <div v-if="!loading && product" class="row mt-4">
        <div class="col-12">
            <div class="bg-white rounded shadow-sm p-4">
                <ul class="nav nav-pills mb-4 border-bottom pb-3 gap-3" id="pills-tab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link fw-bold px-4" :class="{ active: activeTab === 'desc' }" 
                            @click="activeTab = 'desc'">
                            Chi tiết sản phẩm
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold px-4" :class="{ active: activeTab === 'review' }" 
                            @click="activeTab = 'review'">
                            Đánh giá ({{ reviews.length }})
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold px-4" :class="{ active: activeTab === 'comment' }" 
                            @click="activeTab = 'comment'">
                            Hỏi đáp ({{ rawCommentsList.length }})
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab Mô tả -->
                    <div v-show="activeTab === 'desc'" class="fade-in">
                        <div class="description-content" v-html="product.description || '<p class=text-muted>Đang cập nhật mô tả...</p>'"></div>
                    </div>

                    <!-- Tab Đánh giá (Review) -->
                    <div v-show="activeTab === 'review'" class="fade-in">
                        <div v-if="reviews.length === 0" class="text-center py-5">
                            <i class="bi bi-star fs-1 text-muted opacity-50"></i>
                            <p class="mt-3 text-muted">Chưa có đánh giá nào từ người mua.</p>
                        </div>
                        <div v-else>
                            <div class="review-list mt-3">
                                <div v-for="r in paginatedReviews" :key="r.id" class="review-card p-3 mb-3 border rounded">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center">
                                            <!-- Reviewer Avatar -->
                                            <div class="me-3">
                                                 <img v-if="getUserAvatar(r.user)" 
                                                      :src="getUserAvatar(r.user)" 
                                                      class="rounded-circle"
                                                      style="width: 45px; height: 45px; object-fit: cover;"
                                                      @error="$event.target.style.display='none'"
                                                 />
                                                 <div v-else class="avatar-circle text-white" 
                                                      :style="{ backgroundColor: getAvatarColor(getUserDisplayName(r.user, r.user_name)) }">
                                                     {{ getInitials(getUserDisplayName(r.user, r.user_name)) }}
                                                 </div>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold">{{ getUserDisplayName(r.user, r.user_name) }}</h6>
                                                <div class="text-warning small mt-1">
                                                    <i v-for="n in 5" :key="n" :class="n <= (r.rating || 5) ? 'bi-star-fill' : 'bi-star'" class="me-1"></i>
                                                    <span class="text-success ms-2 small fw-semibold"><i class="bi bi-check-circle-fill"></i> Đã mua hàng</span>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="text-muted fst-italic">{{ formatDate(r.created_at || r.createdAt) }}</small>
                                    </div>
                                    <div class="review-content ps-2 ms-5 border-start border-3 ps-3">
                                        <p class="mb-0 text-dark">{{ r.content || r.comment }}</p>
                                    </div>
                                </div>
                            </div>
                            <!-- Pagination Reviews -->
                            <nav v-if="totalReviewPages > 1" class="mt-4">
                              <ul class="pagination justify-content-center">
                                <li class="page-item" :class="{ disabled: reviewPage === 1 }">
                                  <button class="page-link" @click="changeReviewPage(reviewPage - 1)"><i class="bi bi-chevron-left"></i></button>
                                </li>
                                <li v-for="page in totalReviewPages" :key="page" class="page-item" :class="{ active: reviewPage === page }">
                                  <button class="page-link" @click="changeReviewPage(page)">{{ page }}</button>
                                </li>
                                <li class="page-item" :class="{ disabled: reviewPage === totalReviewPages }">
                                  <button class="page-link" @click="changeReviewPage(reviewPage + 1)"><i class="bi bi-chevron-right"></i></button>
                                </li>
                              </ul>
                            </nav>
                        </div>
                    </div>

                    <!-- Tab Bình luận (Comments) -->
                    <div v-show="activeTab === 'comment'" class="fade-in">
                        <!-- Form Viết Bình Luận Chính -->
                        <div class="card mb-4 border-0 shadow-sm bg-light">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">Hỏi đáp & Bình luận</h5>
                                <div class="mb-3">
                                    <textarea v-model="newCommentContent" class="form-control" rows="3" 
                                              placeholder="Bạn có thắc mắc gì về sản phẩm? Hãy đặt câu hỏi..."></textarea>
                                </div>
                                <div class="text-end">
                                    <button class="btn btn-primary btn-add-cart px-4" 
                                            @click="submitNewComment" 
                                            :disabled="isSubmittingComment">
                                            <span v-if="isSubmittingComment" class="spinner-border spinner-border-sm me-2"></span>
                                            {{ isSubmittingComment ? 'Đang gửi...' : 'Gửi câu hỏi' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Danh sách Bình Luận -->
                        <div v-if="comments.length === 0" class="text-center py-5">
                            <i class="bi bi-chat-dots fs-1 text-muted opacity-50"></i>
                            <p class="mt-3 text-muted">Chưa có bình luận nào. Hãy là người đầu tiên đặt câu hỏi!</p>
                        </div>

                        <div v-else>
                            <div class="comment-list mt-4">
                                <!-- VÒNG LẶP CHA -->
                                <div v-for="c in paginatedComments" :key="c.id" class="comment-wrapper mb-4">
                                    <!-- Comment Gốc -->
                                    <div class="comment-card p-3 border rounded bg-white">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <!-- Avatar tự tạo hoặc Ảnh -->
                                                <div class="me-3">
                                                     <img v-if="getUserAvatar(c.user)" 
                                                          :src="getUserAvatar(c.user)" 
                                                          class="rounded-circle"
                                                          style="width: 45px; height: 45px; object-fit: cover;"
                                                          @error="$event.target.style.display='none'"
                                                     />
                                                     <div v-else class="avatar-circle text-white" 
                                                          :style="{ backgroundColor: getAvatarColor(getUserDisplayName(c.user, c.user_name)) }">
                                                         {{ getInitials(getUserDisplayName(c.user, c.user_name)) }}
                                                     </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">
                                                        {{ getUserDisplayName(c.user, c.user_name) }}
                                                        <span v-if="c.user?.role === 'admin'" class="badge bg-danger ms-1" style="font-size:0.6rem">QTV</span>
                                                    </h6>
                                                    <span class="badge bg-light text-dark border mt-1">Khách hàng</span>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <small class="text-muted fst-italic">{{ formatDate(c.created_at) }}</small>
                                                <!-- Nút Xóa (Fix: Ép kiểu String để so sánh an toàn) -->
                                                <button v-if="currentUser && (String(currentUser.id) === String(c.user_id) || currentUser.role === 'admin')" 
                                                        class="btn btn-link text-danger p-0 ms-2 small text-decoration-none"
                                                        @click="deleteComment(c.id)">
                                                    <i class="bi bi-trash"></i> Xóa
                                                </button>
                                            </div>
                                        </div>
                                        <div class="comment-content ps-2 ms-5">
                                            <p class="mb-2 text-dark">{{ c.content }}</p>
                                            
                                            <!-- Nút Trả lời -->
                                            <button class="btn btn-link p-0 text-decoration-none small fw-bold text-primary" @click="openReplyForm(c)">
                                                <i class="bi bi-reply-fill"></i> Trả lời
                                            </button>

                                            <!-- FORM REPLY INLINE -->
                                            <div v-if="activeReplyId === c.id" class="reply-form mt-3 p-3 bg-light rounded fade-in">
                                                <textarea :id="`reply-input-${c.id}`" v-model="replyContent" class="form-control mb-2" rows="2"></textarea>
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <button class="btn btn-sm btn-secondary" @click="closeReplyForm">Hủy</button>
                                                    <button class="btn btn-sm btn-primary" @click="submitReply(c.id)" :disabled="isSubmittingReply">
                                                        {{ isSubmittingReply ? 'Đang gửi...' : 'Gửi' }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- VÒNG LẶP CON (REPLIES) -->
                                    <div v-if="c.replies && c.replies.length > 0" class="replies-list ms-5 mt-2 ps-3 border-start border-3 border-light">
                                        <div v-for="reply in c.replies" :key="reply.id" class="comment-card p-3 mb-2 border rounded bg-light">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="d-flex align-items-center">
                                                    <!-- Avatar Reply -->
                                                    <div class="me-2">
                                                         <img v-if="getUserAvatar(reply.user)" 
                                                              :src="getUserAvatar(reply.user)" 
                                                              class="rounded-circle"
                                                              style="width: 35px; height: 35px; object-fit: cover;"
                                                              @error="$event.target.style.display='none'"
                                                         />
                                                         <div v-else class="avatar-circle small text-white" 
                                                              style="width: 35px; height: 35px; font-size: 0.9rem;"
                                                              :style="{ backgroundColor: getAvatarColor(getUserDisplayName(reply.user, reply.user_name)) }">
                                                             {{ getInitials(getUserDisplayName(reply.user, reply.user_name)) }}
                                                         </div>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0 fw-bold" style="font-size: 0.95rem;">
                                                            {{ getUserDisplayName(reply.user, reply.user_name) }}
                                                            <span v-if="reply.user?.role === 'admin'" class="badge bg-danger ms-1" style="font-size:0.6rem">QTV</span>
                                                        </h6>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <small class="text-muted fst-italic" style="font-size: 0.8rem;">{{ formatDate(reply.created_at) }}</small>
                                                    <!-- Nút Xóa (Reply) Fix String -->
                                                    <button v-if="currentUser && (String(currentUser.id) === String(reply.user_id) || currentUser.role === 'admin')" 
                                                            class="btn btn-link text-danger p-0 ms-2 small text-decoration-none"
                                                            style="font-size: 0.8rem;"
                                                            @click="deleteComment(reply.id)">
                                                        <i class="bi bi-trash"></i> Xóa
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="comment-content ps-2 ms-5">
                                                <p class="mb-1 text-dark">{{ reply.content }}</p>
                                                
                                                <button class="btn btn-link p-0 text-decoration-none small fw-bold text-primary" @click="openReplyForm(reply)">
                                                    <i class="bi bi-reply-fill"></i> Trả lời
                                                </button>

                                                <!-- FORM REPLY INLINE (Con) -->
                                                <div v-if="activeReplyId === reply.id" class="reply-form mt-2 p-2 bg-white border rounded fade-in">
                                                    <textarea :id="`reply-input-${reply.id}`" v-model="replyContent" class="form-control mb-2" rows="2"></textarea>
                                                    <div class="d-flex gap-2 justify-content-end">
                                                        <button class="btn btn-sm btn-secondary" @click="closeReplyForm">Hủy</button>
                                                        <button class="btn btn-sm btn-primary" @click="submitReply(reply.id)" :disabled="isSubmittingReply">
                                                            {{ isSubmittingReply ? 'Đang gửi...' : 'Gửi' }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> 
                            </div>

                            <!-- Pagination Comments -->
                            <nav v-if="totalCommentPages > 1" class="mt-4">
                              <ul class="pagination justify-content-center">
                                <li class="page-item" :class="{ disabled: commentPage === 1 }">
                                  <button class="page-link" @click="changeCommentPage(commentPage - 1)"><i class="bi bi-chevron-left"></i></button>
                                </li>
                                <li v-for="page in totalCommentPages" :key="page" class="page-item" :class="{ active: commentPage === page }">
                                  <button class="page-link" @click="changeCommentPage(page)">{{ page }}</button>
                                </li>
                                <li class="page-item" :class="{ disabled: commentPage === totalCommentPages }">
                                  <button class="page-link" @click="changeCommentPage(commentPage + 1)"><i class="bi bi-chevron-right"></i></button>
                                </li>
                              </ul>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- RELATED PRODUCTS -->
    <div v-if="!loading && relatedProducts.length > 0" class="row mt-5">
      <div class="col-12">
         <h4 class="fw-bold mb-4 text-uppercase border-start border-4 border-success ps-3">Sản phẩm liên quan</h4>
         <div class="horizontal-scroll-container pb-4">
            <div v-for="rp in relatedProducts" :key="rp.id" class="product-card-simple shadow-sm" @click="navigateToProduct(rp.id)">
              <div class="card-img-wrapper">
                  <img :src="getImageUrl(rp.image_url)" class="card-img"/>
              </div>
              <div class="p-2">
                  <h6 class="card-name text-truncate">{{ rp.name }}</h6>
                  <p class="card-price text-danger fw-bold mb-0">{{ formatCurrency(rp.price) }}</p>
              </div>
            </div>
         </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.product-detail-page {
    --primary-color: #009981;
    --primary-hover: #007a67;
    background-color: #f4f6f8;
    min-height: 100vh;
}
.fade-in-up { animation: fadeInUp 0.5s ease-out; }
.fade-in { animation: fadeIn 0.3s ease-in; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* IMAGES */
.main-image-wrapper { 
    background: #fff; border-radius: 12px; overflow: hidden; padding: 20px; 
    height: 500px; width: 100%; display: flex; align-items: center; justify-content: center; position: relative; border: 1px solid rgba(0,0,0,0.05);
}
.main-product-image { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; border-radius: 8px; }
.main-product-image:hover { transform: scale(1.05); }
@media (max-width: 768px) { .main-image-wrapper { height: 350px; } }

.thumbnail-gallery { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
.thumbnail-wrapper { width: 70px; height: 70px; border-radius: 8px; overflow: hidden; border: 2px solid transparent; cursor: pointer; transition: all 0.2s; background: #fff; padding: 2px; }
.thumbnail-wrapper.active { border-color: var(--primary-color); transform: translateY(-2px); }
.thumbnail-img { width: 100%; height: 100%; object-fit: contain; }

/* INFO & CHIPS */
.product-info-box { padding: 0 10px; }
.chip-btn { border: 1px solid #dee2e6; background: #fff; color: #495057; min-width: 60px; transition: all 0.2s; border-radius: 6px; font-size: 0.9rem; }
.chip-btn:hover:not(.disabled) { border-color: var(--primary-color); color: var(--primary-color); background: rgba(0, 153, 129, 0.05); }
.chip-btn.active { border-color: var(--primary-color); background-color: var(--primary-color); color: #fff; box-shadow: 0 4px 6px rgba(0, 153, 129, 0.2); }
.chip-btn.disabled { opacity: 0.5; background: #f8f9fa; color: #adb5bd; text-decoration: line-through; cursor: not-allowed; border-style: dashed; }

.qty-group button { background: #fff; color: #333; border-color: #ced4da; }
.qty-group button:hover { background: #f8f9fa; }
.qty-group input { background: #fff; border-color: #ced4da; color: #333; }

.btn-add-cart { background-color: var(--primary-color); color: white; border: none; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.3s; }
.btn-add-cart:hover:not(:disabled) { background-color: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0, 153, 129, 0.2); }
.btn-add-cart:disabled { background-color: #a0c4be; cursor: not-allowed; }

.icon-btn { width: 50px; height: 50px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.icon-btn:hover { background-color: #ffebee; border-color: #ef5350; }

/* TABS & CARDS */
.nav-pills .nav-link { color: #555; border-radius: 30px; border: 1px solid #e9ecef; transition: all 0.2s; }
.nav-pills .nav-link:hover { background: #f8f9fa; }
.nav-pills .nav-link.active { background-color: var(--primary-color); color: #fff; border-color: var(--primary-color); box-shadow: 0 4px 8px rgba(0, 153, 129, 0.25); }

.review-card { background: #fff; border-color: #f1f1f1 !important; transition: all 0.2s; }
.review-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.comment-card { transition: all 0.2s; }
.comment-card:hover { box-shadow: 0 4px 10px rgba(0,0,0,0.03); }

/* Updated Avatar Style */
.avatar-circle {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.1rem;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    text-transform: uppercase;
}
.avatar-circle.small { width: 35px; height: 35px; font-size: 0.9rem; }

.horizontal-scroll-container { display: flex; overflow-x: auto; gap: 15px; padding: 5px; scrollbar-width: thin; }
.product-card-simple { flex: 0 0 180px; background: #fff; border-radius: 10px; overflow: hidden; cursor: pointer; transition: transform 0.2s; border: 1px solid rgba(0,0,0,0.05); }
.product-card-simple:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important; }
.card-img-wrapper { height: 140px; padding: 10px; display: flex; align-items: center; justify-content: center; }
.card-img { max-width: 100%; max-height: 100%; object-fit: contain; }

/* PAGINATION STYLES */
.page-link {
    color: var(--primary-color);
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin: 0 3px;
    transition: all 0.2s;
}
.page-link:hover {
    background-color: #e9ecef;
    color: var(--primary-hover);
}
.page-item.active .page-link {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}
.page-item.disabled .page-link {
    color: #6c757d;
    pointer-events: none;
    background-color: #fff;
    border-color: #dee2e6;
}
</style>