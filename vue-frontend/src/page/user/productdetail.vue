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

// State để theo dõi các ảnh bị lỗi load
const imageLoadErrors = ref({});

// Lấy thông tin User hiện tại
const currentUser = computed(() => {
    return store.state.auth?.user || store.state.user || JSON.parse(localStorage.getItem('user') || 'null');
});

const formatDate = (dateString) => {
    if (!dateString) return new Date().toLocaleDateString('vi-VN');
    const date = new Date(dateString);
    return `${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')} ${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
};

const getAvatarColor = (name) => {
    if (!name) return '#6c757d';
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const c = (hash & 0x00FFFFFF).toString(16).toUpperCase();
    return '#' + '00000'.substring(0, 6 - c.length) + c;
};

const getInitials = (name) => {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0][0].toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

const handleImageError = (id) => {
    if (id) {
        imageLoadErrors.value[id] = true;
    }
};

const getUserDisplayName = (userObj, fallbackName) => {
    if (userObj) {
        if (userObj.fullName) return userObj.fullName;
        if (userObj.full_name) return userObj.full_name;
        if (userObj.name) return userObj.name;
    }
    if (fallbackName) return fallbackName;
    return "Khách hàng";
};

const getUserAvatar = (userObj) => {
    if (!userObj || !userObj.avatar_url) return null;
    const url = userObj.avatar_url;
    if (url.startsWith('http')) return url;
    const cleanPath = url.startsWith('/') ? url.substring(1) : url;
    if (cleanPath.startsWith('storage/') || cleanPath.startsWith('uploads/')) {
        return `${SERVER_URL}/${cleanPath}`;
    }
    return `${SERVER_URL}/storage/${cleanPath}`;
};

// --- NOTIFICATION SYSTEM ---
const Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  background: '#fff',
  color: '#333',
  iconColor: '#009981', 
  customClass: {
    popup: 'elegant-toast', 
    title: 'elegant-toast-title',
    timerProgressBar: 'elegant-toast-progress'
  },
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
    if (path.startsWith('http') || path.startsWith('blob:')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
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

// [UPDATED] Active Tab for Reviews/Comments Section
const activeFeedbackTab = ref('review'); // 'review' or 'comment'

// Active Tab for Recommendations
const activeProductTab = ref('related'); // 'related', 'newest', 'toprated'

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
const newestProducts = ref([]); 
const topRatedProducts = ref([]); 

// Ref cho container cuộn ngang
const relatedContainer = ref(null);
const newestContainer = ref(null);
const topRatedContainer = ref(null);

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
    if (!product.value || !product.value.variants) return;
    const totalKeys = Object.keys(groupedAttributes.value).length;
    const selectedKeys = Object.keys(selectedOptions.value).length;

    // Nếu chưa chọn đủ thuộc tính
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

    if (match && match.image) {
        selectedImage.value = match.image;
    }
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
        if (attrs.length > 0) {
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

        if (!data.variants) data.variants = [];

        // Chuẩn hóa dữ liệu variant
        data.variants.forEach((v, i) => {
            v.stock = Number(v.stock) || 0;
            v.price = Number(v.price) || 0;
            v.original_price = Number(v.original_price) || v.price;
            v.id = v.id || i;
        });

        // Lấy thêm ảnh từ biến thể để hiển thị trong gallery
        const variantImages = data.variants.map(v => v.image).filter(img => img && typeof img === 'string');

        // Xử lý hình ảnh
        const extraImages = (data.images || []).map(img =>
            (typeof img === 'string') ? img : (img.url || img.image_url || img.path)
        ).filter(Boolean);

        // Gộp ảnh thumbnail, ảnh phụ và ảnh biến thể
        data.gallery_images = [
            data.image_url || data.thumbnail_url,
            ...extraImages,
            ...variantImages
        ].filter(Boolean);

        data.gallery_images = [...new Set(data.gallery_images)]; // Loại bỏ ảnh trùng lặp
        if (data.gallery_images.length === 0) data.gallery_images = ['https://placehold.co/500x500?text=No+Img'];

        product.value = data;
        selectedImage.value = product.value.gallery_images[0];
        processProductData(data);

        if (typeof isInWishlist === 'function') isFavorite.value = isInWishlist(product.value.id);

        reviewPage.value = 1;
        commentPage.value = 1;
        imageLoadErrors.value = {};

        loadReviews(id);
        loadComments(id);

    } catch (error) {
        console.error("Lỗi:", error);
        notify('error', 'Không thể tải thông tin sản phẩm', 'Lỗi kết nối');
    } finally {
        // [FIX] Thêm delay 2 giây để hiển thị Skeleton như yêu cầu
        setTimeout(() => { loading.value = false }, 2000);
    }
};

// [UPDATED] Hàm loadReviews với Client-side filtering
const loadReviews = async (productId) => {
    try {
        // Gọi API, thử dùng params product_id (chuẩn) hoặc productId
        const reviewRes = await apiService.get(`/reviews?product_id=${productId}`);
        const allReviews = Array.isArray(reviewRes.data) ? reviewRes.data : (reviewRes.data?.data || []);
        
        // LỌC REVIEW THEO PRODUCT ID TẠI ĐÂY
        reviews.value = allReviews.filter(r => 
            r.status === 'approved' && 
            String(r.product_id) === String(productId)
        );
    } catch (e) {
        reviews.value = [];
    }
};

const buildCommentTree = (flatList) => {
    const map = {};
    const roots = [];
    const list = JSON.parse(JSON.stringify(flatList));

    list.forEach((comment, index) => {
        map[comment.id] = index;
        comment.replies = [];
    });

    list.forEach(comment => {
        if (comment.parent_id !== null && map[comment.parent_id] !== undefined) {
            if (list[map[comment.parent_id]]) {
                list[map[comment.parent_id]].replies.push(comment);
            }
        } else {
            roots.push(comment);
        }
    });

    roots.forEach(root => {
        if (root.replies.length > 0) {
            root.replies.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        }
    });

    return roots.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
};

const loadComments = async (productId) => {
    try {
        const commentRes = await apiService.get(`/comments?product_id=${productId}`);
        let rawData = [];
        if (Array.isArray(commentRes.data)) {
            rawData = commentRes.data;
        } else if (commentRes.data?.data && Array.isArray(commentRes.data.data)) {
            rawData = commentRes.data.data;
        }

        if (productId) {
            rawData = rawData.filter(c => String(c.product_id) === String(productId));
        }
        rawData = rawData.filter(c => c.status === 'approved');

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
        notify('success', type === 'new' ? 'Gửi câu hỏi thành công! (Chờ duyệt)' : 'Đã gửi câu trả lời! (Chờ duyệt)');

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

// --- HELPER FOR RELATED PRODUCTS ---
const calculateDiscount = (price, original) => {
    if (!original || original <= price) return 0;
    return Math.round(((original - price) / original) * 100);
};

const isNewProduct = (createdAt) => {
    if (!createdAt) return false;
    const date = new Date(createdAt);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays <= 30; // New nếu tạo trong 30 ngày
};

// [UPDATED] Hàm cuộn slide (Generic cho cả Related, Newest và TopRated)
const scrollList = (type, direction) => {
    let container;
    if (type === 'related') container = relatedContainer.value;
    else if (type === 'newest') container = newestContainer.value;
    else if (type === 'toprated') container = topRatedContainer.value;

    if (!container) return;

    const scrollAmount = 300; // Khoảng cách mỗi lần cuộn
    if (direction === 'left') {
        container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
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

watchEffect(() => {
    if (product.value && allProducts.value.length > 0) {
        // 1. Logic cho Related Products
        let related = [];
        if (product.value.category_id) {
            related = allProducts.value.filter(p => p.category_id === product.value.category_id && p.id !== product.value.id);
        }
        if (related.length < 8) {
            const others = allProducts.value.filter(p => p.id !== product.value.id && !related.includes(p));
            related = [...related, ...others];
        }
        relatedProducts.value = related;

        // 2. Logic cho Newest Products (Sản phẩm mới nhất)
        const sortedByDate = [...allProducts.value].sort((a, b) => {
            return new Date(b.created_at) - new Date(a.created_at);
        });
        newestProducts.value = sortedByDate.filter(p => p.id !== product.value.id).slice(0, 10);

        // 3. Logic cho "Có thể bạn thích" (Top rated per category - Round Robin fill)
        const groups = {};
        allProducts.value.forEach(p => {
            if (p.id === product.value.id) return;
            const catId = p.category_id || 'unknown';
            if (!groups[catId]) groups[catId] = [];
            groups[catId].push(p);
        });

        // Sắp xếp từng nhóm theo rating giảm dần
        Object.keys(groups).forEach(catId => {
            groups[catId].sort((a, b) => {
                const ratingDiff = (b.average_rating || 0) - (a.average_rating || 0);
                if (ratingDiff !== 0) return ratingDiff;
                return (b.sold_count || 0) - (a.sold_count || 0);
            });
        });

        const topRated = [];
        let index = 0;
        let addedSomething = true;

        // Vòng lặp lấy sản phẩm (Round Robin)
        while (topRated.length < 8 && addedSomething) {
            addedSomething = false;
            const catIds = Object.keys(groups);
            
            for (const catId of catIds) {
                if (topRated.length >= 8) break;
                
                const productsInCat = groups[catId];
                if (index < productsInCat.length) {
                    topRated.push(productsInCat[index]);
                    addedSomething = true;
                }
            }
            index++;
        }
        
        topRatedProducts.value = topRated;
    }
});
</script>

<template>
    <div class="container py-5 product-detail-page">
        <!-- [NEW] SKELETON LOADING STRUCTURE -->
        <div v-if="loading" class="skeleton-container fade-in">
            <!-- 1. Product Info Skeleton -->
            <div class="row g-5 mb-5">
                <!-- Left: Image -->
                <div class="col-lg-5">
                    <div class="skeleton-box skeleton-main-img shimmer mb-3">
                        <span class="skeleton-placeholder-text-large">ThinkHub</span>
                    </div>
                    <div class="d-flex gap-2">
                        <div v-for="n in 4" :key="n" class="skeleton-box skeleton-thumb shimmer"></div>
                    </div>
                </div>
                <!-- Right: Info -->
                <div class="col-lg-7">
                    <div class="skeleton-box skeleton-title shimmer mb-3"></div>
                    <div class="d-flex mb-4">
                        <div class="skeleton-box skeleton-text w-25 shimmer me-3"></div>
                        <div class="skeleton-box skeleton-text w-25 shimmer"></div>
                    </div>
                    
                    <div class="skeleton-box skeleton-price-area shimmer mb-4"></div>
                    
                    <!-- Attributes -->
                    <div class="mb-4">
                        <div class="skeleton-box skeleton-text w-25 shimmer mb-2"></div>
                        <div class="d-flex gap-2 mb-3">
                            <div v-for="n in 3" :key="n" class="skeleton-box skeleton-chip shimmer"></div>
                        </div>
                        <div class="skeleton-box skeleton-text w-25 shimmer mb-2"></div>
                        <div class="d-flex gap-2">
                            <div v-for="n in 2" :key="n" class="skeleton-box skeleton-chip shimmer"></div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-3 mt-5">
                        <div class="skeleton-box skeleton-action-btn flex-grow-1 shimmer"></div>
                        <div class="skeleton-box skeleton-icon-btn shimmer"></div>
                    </div>
                </div>
            </div>

            <!-- 2. Description Skeleton -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="bg-white rounded p-4 border border-light">
                        <div class="skeleton-box skeleton-title w-25 shimmer mb-4"></div>
                        <div v-for="n in 5" :key="n" class="skeleton-box skeleton-text w-100 shimmer mb-2"></div>
                        <div class="skeleton-box skeleton-text w-75 shimmer"></div>
                    </div>
                </div>
            </div>

            <!-- 3. Recommendations Skeleton -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="bg-white rounded p-4 border border-light">
                        <div class="d-flex justify-content-between mb-4">
                            <div class="skeleton-box skeleton-text w-25 shimmer"></div>
                            <div class="d-flex gap-2">
                                <div class="skeleton-box skeleton-chip shimmer" style="width: 100px;"></div>
                                <div class="skeleton-box skeleton-chip shimmer" style="width: 100px;"></div>
                            </div>
                        </div>
                        <div class="d-flex gap-3 overflow-hidden">
                            <div v-for="n in 5" :key="n" class="skeleton-box skeleton-card shimmer"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. PRODUCT INFO SECTION -->
        <div v-if="!loading && product" class="row g-5 mb-5 fade-in-up">
            <!-- Cột Trái: Gallery -->
            <div class="col-lg-5">
                <div class="main-image-wrapper mb-3 shadow-sm">
                    <img :src="getImageUrl(selectedImage)" :alt="product.name" class="main-product-image"
                        @error="$event.target.src = 'https://placehold.co/500x500?text=No+Image'" />
                </div>
                <div class="thumbnail-gallery" v-if="product.gallery_images && product.gallery_images.length > 1">
                    <div v-for="(image, index) in product.gallery_images" :key="index" class="thumbnail-wrapper"
                        :class="{ active: selectedImage === image }" @click="selectImage(image)">
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
                            <span v-if="availableVariant.original_price > availableVariant.price"
                                class="badge bg-danger ms-2">
                                -{{ Math.round((1 - availableVariant.price / availableVariant.original_price) * 100) }}%
                            </span>
                        </template>
                        <template v-else>
                            <div
                                v-if="product.min_price && product.max_price && product.min_price !== product.max_price">
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
                        <div v-for="(values, attrName) in groupedAttributes" :key="attrName"
                            class="attribute-group mb-4">
                            <label class="fw-bold mb-2 d-block text-secondary small text-uppercase">{{ attrName }}:
                                <span class="fw-bold text-dark ms-1" v-if="selectedOptions[attrName]">
                                    {{values.find(v => v.id === selectedOptions[attrName])?.value}}
                                </span>
                            </label>
                            <div class="d-flex flex-wrap gap-2">
                                <button v-for="val in values" :key="val.id" class="btn chip-btn" :class="{
                                    'active': selectedOptions[attrName] === val.id,
                                    'disabled': isOptionDisabled(attrName, val.id)
                                }" @click="selectAttribute(attrName, val.id)" :disabled="isOptionDisabled(attrName, val.id)">
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
                                <button class="btn btn-white border-end-0" @click="decreaseQty"><i
                                        class="bi bi-dash"></i></button>
                                <input type="number" v-model.number="quantity"
                                    class="form-control text-center border-start-0 border-end-0 fw-bold"
                                    @change="validateQty" />
                                <button class="btn btn-white border-start-0" @click="increaseQty"><i
                                        class="bi bi-plus"></i></button>
                            </div>
                            <span class="ms-3 small"
                                :class="availableVariant.stock > 0 ? 'text-success' : 'text-danger'">
                                {{ availableVariant.stock > 0 ? `Còn ${availableVariant.stock} sản phẩm` : 'Tạm hết hàng' }}
                            </span>
                        </div>

                        <div class="d-flex gap-3">
                            <button class="btn btn-lg flex-grow-1 btn-add-cart shadow-sm" @click="onAddToCart(product)"
                                :disabled="!availableVariant || availableVariant.stock <= 0">
                                <i class="bi bi-bag-plus me-2"></i>
                                {{ !availableVariant ? 'Chọn phân loại hàng' : (availableVariant.stock > 0 ? 'Thêm vào giỏ hàng' : 'Hết hàng') }}
                            </button>

                            <button class="btn btn-outline-danger icon-btn" @click="toggleFavorite(product)"
                                title="Yêu thích">
                                <i :class="['bi', isFavorite ? 'bi-heart-fill' : 'bi-heart']"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. DESCRIPTION SECTION (MOVED OUT OF TABS) -->
        <div v-if="!loading && product" class="row mt-4">
            <div class="col-12">
                <div class="bg-white rounded shadow-sm p-4">
                    <h4 class="fw-bold mb-3 pb-2 border-bottom text-uppercase">Chi tiết sản phẩm</h4>
                    <div class="description-content mt-3"
                        v-html="product.description || '<p class=text-muted>Đang cập nhật mô tả...</p>'">
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. RECOMMENDATION SECTION (TABS) [MOVED UP] -->
        <div v-if="!loading" class="row mt-5">
            <div class="col-12">
                <div class="recommendation-box bg-white p-4 rounded shadow-sm">
                    <!-- HEADER + TABS -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 border-bottom pb-3">
                        <div class="mb-3 mb-md-0">
                            <h4 class="fw-bold m-0 text-uppercase text-dark mb-1">Có thể bạn sẽ thích</h4>
                            <small class="text-muted">Khám phá thêm các sản phẩm chất lượng khác</small>
                        </div>
                        
                        <div class="d-flex align-items-center gap-3">
                            <ul class="nav nav-pills custom-pills" id="pills-tab-recom" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link rounded-pill px-3 py-2 fw-bold" 
                                        :class="{ active: activeProductTab === 'related' }"
                                        @click="activeProductTab = 'related'">
                                        Sản phẩm liên quan
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link rounded-pill px-3 py-2 fw-bold"
                                        :class="{ active: activeProductTab === 'newest' }"
                                        @click="activeProductTab = 'newest'">
                                        Sản phẩm mới
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link rounded-pill px-3 py-2 fw-bold"
                                        :class="{ active: activeProductTab === 'toprated' }"
                                        @click="activeProductTab = 'toprated'">
                                        Đánh giá cao
                                    </button>
                                </li>
                            </ul>
                            
                            <!-- NAV BUTTONS (Chung cho cả 3 tabs) -->
                            <div class="d-flex gap-2 ms-2 border-start ps-3">
                                <button class="btn btn-outline-secondary rounded-circle btn-nav btn-sm"
                                    @click="scrollList(activeProductTab, 'left')"><i class="bi bi-chevron-left"></i></button>
                                <button class="btn btn-outline-secondary rounded-circle btn-nav btn-sm"
                                    @click="scrollList(activeProductTab, 'right')"><i class="bi bi-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- CONTENT AREA -->
                    <div class="tab-content-area position-relative" style="min-height: 380px;">
                        
                        <!-- TAB 1: RELATED -->
                        <div v-show="activeProductTab === 'related'" class="fade-in">
                            <div v-if="relatedProducts.length > 0" class="related-scroll-wrapper" ref="relatedContainer">
                                <div class="d-flex gap-3 pb-3" style="width: max-content;">
                                    <div class="product-card-pro border shadow-sm position-relative" v-for="rp in relatedProducts"
                                        :key="rp.id" style="width: 240px;">
                                        <!-- Copy logic Card -->
                                        <div class="badges-overlay position-absolute top-0 start-0 p-2 z-index-2 d-flex flex-column gap-1">
                                            <span v-if="calculateDiscount(rp.price, rp.original_price) > 0" class="badge bg-danger rounded-pill shadow-sm">-{{ calculateDiscount(rp.price, rp.original_price) }}%</span>
                                            <span v-if="isNewProduct(rp.created_at)" class="badge bg-primary rounded-pill shadow-sm">NEW</span>
                                            <span v-if="rp.sold_count > 100" class="badge bg-warning text-dark rounded-pill shadow-sm">HOT</span>
                                        </div>
                                        <button class="btn btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-2 wish-btn-visible"
                                            @click.stop="toggleFavorite(rp)" :title="isInWishlist(rp.id) ? 'Bỏ thích' : 'Yêu thích'">
                                            <i :class="['bi', isInWishlist(rp.id) ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary']"></i>
                                        </button>
                                        <div class="card-img-top-wrapper overflow-hidden position-relative" @click="navigateToProduct(rp.id)">
                                            <img :src="getImageUrl(rp.image_url || rp.thumbnail_url)" class="card-img-top product-img" :alt="rp.name">
                                        </div>
                                        <div class="card-body p-3 d-flex flex-column" @click="navigateToProduct(rp.id)">
                                            <small class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">{{ rp.brand?.name || 'THƯƠNG HIỆU' }}</small>
                                            <h6 class="card-title fw-bold text-dark text-truncate-2 mb-2" style="height: 40px;">{{ rp.name }}</h6>
                                            <div class="d-flex align-items-center mb-2 small text-muted">
                                                <div class="d-flex text-warning me-2"><i class="bi bi-star-fill" style="font-size: 0.8rem;"></i><span class="ms-1 text-dark fw-bold">{{ rp.average_rating || 5 }}</span></div>
                                                <span class="border-start ps-2">Đã bán {{ rp.sold_count || 0 }}</span>
                                            </div>
                                            <div class="mt-auto">
                                                <div class="d-flex align-items-baseline flex-wrap gap-2">
                                                    <template v-if="rp.min_price && rp.max_price && rp.min_price !== rp.max_price">
                                                        <span class="text-theme fw-bold fs-6">{{ formatCurrency(rp.min_price) }} - {{ formatCurrency(rp.max_price) }}</span>
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-theme fw-bold fs-5">{{ formatCurrency(rp.price || rp.min_price) }}</span>
                                                        <span v-if="rp.original_price > (rp.price || rp.min_price)" class="text-muted text-decoration-line-through small">{{ formatCurrency(rp.original_price) }}</span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-5 text-muted">Không có sản phẩm liên quan nào.</div>
                        </div>

                        <!-- TAB 2: NEWEST -->
                        <div v-show="activeProductTab === 'newest'" class="fade-in">
                            <div v-if="newestProducts.length > 0" class="related-scroll-wrapper" ref="newestContainer">
                                <div class="d-flex gap-3 pb-3" style="width: max-content;">
                                    <div class="product-card-pro border shadow-sm position-relative" v-for="np in newestProducts"
                                        :key="np.id" style="width: 240px;">
                                        <!-- Copy logic Card -->
                                        <div class="badges-overlay position-absolute top-0 start-0 p-2 z-index-2 d-flex flex-column gap-1">
                                            <span v-if="calculateDiscount(np.price, np.original_price) > 0" class="badge bg-danger rounded-pill shadow-sm">-{{ calculateDiscount(np.price, np.original_price) }}%</span>
                                            <span class="badge bg-primary rounded-pill shadow-sm">NEW</span>
                                            <span v-if="np.sold_count > 100" class="badge bg-warning text-dark rounded-pill shadow-sm">HOT</span>
                                        </div>
                                        <button class="btn btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-2 wish-btn-visible"
                                            @click.stop="toggleFavorite(np)" :title="isInWishlist(np.id) ? 'Bỏ thích' : 'Yêu thích'">
                                            <i :class="['bi', isInWishlist(np.id) ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary']"></i>
                                        </button>
                                        <div class="card-img-top-wrapper overflow-hidden position-relative" @click="navigateToProduct(np.id)">
                                            <img :src="getImageUrl(np.image_url || np.thumbnail_url)" class="card-img-top product-img" :alt="np.name">
                                        </div>
                                        <div class="card-body p-3 d-flex flex-column" @click="navigateToProduct(np.id)">
                                            <small class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">{{ np.brand?.name || 'THƯƠNG HIỆU' }}</small>
                                            <h6 class="card-title fw-bold text-dark text-truncate-2 mb-2" style="height: 40px;">{{ np.name }}</h6>
                                            <div class="d-flex align-items-center mb-2 small text-muted">
                                                <div class="d-flex text-warning me-2"><i class="bi bi-star-fill" style="font-size: 0.8rem;"></i><span class="ms-1 text-dark fw-bold">{{ np.average_rating || 5 }}</span></div>
                                                <span class="border-start ps-2">Đã bán {{ np.sold_count || 0 }}</span>
                                            </div>
                                            <div class="mt-auto">
                                                <div class="d-flex align-items-baseline flex-wrap gap-2">
                                                    <template v-if="np.min_price && np.max_price && np.min_price !== np.max_price">
                                                        <span class="text-theme fw-bold fs-6">{{ formatCurrency(np.min_price) }} - {{ formatCurrency(np.max_price) }}</span>
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-theme fw-bold fs-5">{{ formatCurrency(np.price || np.min_price) }}</span>
                                                        <span v-if="np.original_price > (np.price || np.min_price)" class="text-muted text-decoration-line-through small">{{ formatCurrency(np.original_price) }}</span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-5 text-muted">Đang cập nhật sản phẩm mới.</div>
                        </div>

                        <!-- TAB 3: TOP RATED -->
                        <div v-show="activeProductTab === 'toprated'" class="fade-in">
                            <div v-if="topRatedProducts.length > 0" class="related-scroll-wrapper" ref="topRatedContainer">
                                <div class="d-flex gap-3 pb-3" style="width: max-content;">
                                    <div class="product-card-pro border shadow-sm position-relative" v-for="tp in topRatedProducts"
                                        :key="tp.id" style="width: 240px;">
                                        <!-- Copy logic Card -->
                                        <div class="badges-overlay position-absolute top-0 start-0 p-2 z-index-2 d-flex flex-column gap-1">
                                            <span v-if="calculateDiscount(tp.price, tp.original_price) > 0" class="badge bg-danger rounded-pill shadow-sm">-{{ calculateDiscount(tp.price, tp.original_price) }}%</span>
                                            <span class="badge bg-info text-dark rounded-pill shadow-sm">TOP</span>
                                            <span v-if="tp.sold_count > 100" class="badge bg-warning text-dark rounded-pill shadow-sm">HOT</span>
                                        </div>
                                        <button class="btn btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-2 wish-btn-visible"
                                            @click.stop="toggleFavorite(tp)" :title="isInWishlist(tp.id) ? 'Bỏ thích' : 'Yêu thích'">
                                            <i :class="['bi', isInWishlist(tp.id) ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary']"></i>
                                        </button>
                                        <div class="card-img-top-wrapper overflow-hidden position-relative" @click="navigateToProduct(tp.id)">
                                            <img :src="getImageUrl(tp.image_url || tp.thumbnail_url)" class="card-img-top product-img" :alt="tp.name">
                                        </div>
                                        <div class="card-body p-3 d-flex flex-column" @click="navigateToProduct(tp.id)">
                                            <small class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">{{ tp.brand?.name || 'THƯƠNG HIỆU' }}</small>
                                            <h6 class="card-title fw-bold text-dark text-truncate-2 mb-2" style="height: 40px;">{{ tp.name }}</h6>
                                            <div class="d-flex align-items-center mb-2 small text-muted">
                                                <div class="d-flex text-warning me-2"><i class="bi bi-star-fill" style="font-size: 0.8rem;"></i><span class="ms-1 text-dark fw-bold">{{ tp.average_rating || 5 }}</span></div>
                                                <span class="border-start ps-2">Đã bán {{ tp.sold_count || 0 }}</span>
                                            </div>
                                            <div class="mt-auto">
                                                <div class="d-flex align-items-baseline flex-wrap gap-2">
                                                    <template v-if="tp.min_price && tp.max_price && tp.min_price !== tp.max_price">
                                                        <span class="text-theme fw-bold fs-6">{{ formatCurrency(tp.min_price) }} - {{ formatCurrency(tp.max_price) }}</span>
                                                    </template>
                                                    <template v-else>
                                                        <span class="text-theme fw-bold fs-5">{{ formatCurrency(tp.price || tp.min_price) }}</span>
                                                        <span v-if="tp.original_price > (tp.price || tp.min_price)" class="text-muted text-decoration-line-through small">{{ formatCurrency(tp.original_price) }}</span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-5 text-muted">Chưa có sản phẩm nổi bật.</div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- 4. REVIEWS & COMMENTS TABS (MOVED DOWN) -->
        <div v-if="!loading && product" class="row mt-5">
            <div class="col-12">
                <div class="bg-white rounded shadow-sm p-4">
                    <ul class="nav nav-pills mb-4 border-bottom pb-3 gap-3" id="pills-tab-feedback" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link fw-bold px-4" :class="{ active: activeFeedbackTab === 'review' }"
                                @click="activeFeedbackTab = 'review'">
                                Đánh giá ({{ reviews.length }})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link fw-bold px-4" :class="{ active: activeFeedbackTab === 'comment' }"
                                @click="activeFeedbackTab = 'comment'">
                                Hỏi đáp ({{ rawCommentsList.length }})
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Tab Đánh giá (Review) -->
                        <div v-show="activeFeedbackTab === 'review'" class="fade-in">
                            <div v-if="reviews.length === 0" class="text-center py-5">
                                <i class="bi bi-star fs-1 text-muted opacity-50"></i>
                                <p class="mt-3 text-muted">Chưa có đánh giá nào từ người mua.</p>
                            </div>
                            <div v-else>
                                <div class="review-list mt-3">
                                    <div v-for="r in paginatedReviews" :key="r.id"
                                        class="review-card p-3 mb-3 border rounded">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <!-- Reviewer Avatar -->
                                                <div class="me-3">
                                                    <img v-if="getUserAvatar(r.user) && !imageLoadErrors[r.id]"
                                                        :src="getUserAvatar(r.user)" class="rounded-circle"
                                                        style="width: 45px; height: 45px; object-fit: cover;"
                                                        @error="handleImageError(r.id)" />
                                                    <div v-else class="avatar-circle text-white"
                                                        :style="{ backgroundColor: getAvatarColor(getUserDisplayName(r.user, r.user_name)) }">
                                                        {{ getInitials(getUserDisplayName(r.user, r.user_name)) }}
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold">{{ getUserDisplayName(r.user, r.user_name)
                                                        }}</h6>
                                                    <div class="text-warning small mt-1">
                                                        <i v-for="n in 5" :key="n"
                                                            :class="n <= (r.rating || 5) ? 'bi-star-fill' : 'bi-star'"
                                                            class="me-1"></i>
                                                        <span class="text-success ms-2 small fw-semibold"><i
                                                                class="bi bi-check-circle-fill"></i> Đã mua hàng</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <small class="text-muted fst-italic">{{ formatDate(r.created_at ||
                                                r.createdAt)
                                                }}</small>
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
                                            <button class="page-link" @click="changeReviewPage(reviewPage - 1)"><i
                                                    class="bi bi-chevron-left"></i></button>
                                        </li>
                                        <li v-for="page in totalReviewPages" :key="page" class="page-item"
                                            :class="{ active: reviewPage === page }">
                                            <button class="page-link" @click="changeReviewPage(page)">{{ page
                                                }}</button>
                                        </li>
                                        <li class="page-item" :class="{ disabled: reviewPage === totalReviewPages }">
                                            <button class="page-link" @click="changeReviewPage(reviewPage + 1)"><i
                                                    class="bi bi-chevron-right"></i></button>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>

                        <!-- Tab Bình luận (Comments) -->
                        <div v-show="activeFeedbackTab === 'comment'" class="fade-in">
                            <!-- Form Viết Bình Luận Chính -->
                            <div class="card mb-4 border-0 shadow-sm bg-light">
                                <div class="card-body p-4">
                                    <h5 class="fw-bold mb-3">Hỏi đáp & Bình luận</h5>
                                    <div class="mb-3">
                                        <textarea v-model="newCommentContent" class="form-control" rows="3"
                                            placeholder="Bạn có thắc mắc gì về sản phẩm? Hãy đặt câu hỏi..."></textarea>
                                    </div>
                                    <div class="text-end">
                                        <!-- Button Gửi Câu Hỏi - Dùng btn-theme -->
                                        <button class="btn btn-theme px-4" @click="submitNewComment"
                                            :disabled="isSubmittingComment">
                                            <span v-if="isSubmittingComment"
                                                class="spinner-border spinner-border-sm me-2"></span>
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
                                                        <!-- Fix Logic Avatar -->
                                                        <img v-if="getUserAvatar(c.user) && !imageLoadErrors[c.id]"
                                                            :src="getUserAvatar(c.user)" class="rounded-circle"
                                                            style="width: 45px; height: 45px; object-fit: cover;"
                                                            @error="handleImageError(c.id)" />
                                                        <div v-else class="avatar-circle text-white"
                                                            :style="{ backgroundColor: getAvatarColor(getUserDisplayName(c.user, c.user_name)) }">
                                                            {{ getInitials(getUserDisplayName(c.user, c.user_name)) }}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0 fw-bold">
                                                            {{ getUserDisplayName(c.user, c.user_name) }}
                                                            <span v-if="c.user?.role === 'admin'"
                                                                class="badge bg-danger ms-1"
                                                                style="font-size:0.6rem">QTV</span>
                                                            <span class="badge bg-light text-dark border mt-1">Khách
                                                                hàng</span>
                                                        </h6>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <small class="text-muted fst-italic">{{ formatDate(c.created_at)
                                                        }}</small>
                                                    <!-- Nút Xóa: Hiển thị nếu là chủ comment hoặc Admin -->
                                                    <button
                                                        v-if="currentUser && (String(currentUser.id) === String(c.user_id) || currentUser.role === 'admin')"
                                                        class="btn btn-link text-danger p-0 ms-2 small text-decoration-none btn-delete-comment"
                                                        @click="deleteComment(c.id)">
                                                        <i class="bi bi-trash"></i> Xóa
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="comment-content ps-2 ms-5">
                                                <p class="mb-2 text-dark">{{ c.content }}</p>

                                                <!-- Nút Trả lời - Dùng text-theme -->
                                                <button
                                                    class="btn btn-link p-0 text-decoration-none small fw-bold text-theme"
                                                    @click="openReplyForm(c)">
                                                    <i class="bi bi-reply-fill"></i> Trả lời
                                                </button>

                                                <!-- FORM REPLY INLINE -->
                                                <div v-if="activeReplyId === c.id"
                                                    class="reply-form mt-3 p-3 bg-light rounded fade-in">
                                                    <textarea :id="`reply-input-${c.id}`" v-model="replyContent"
                                                        class="form-control mb-2" rows="2"></textarea>
                                                    <div class="d-flex gap-2 justify-content-end">
                                                        <button class="btn btn-sm btn-secondary"
                                                            @click="closeReplyForm">Hủy</button>
                                                        <!-- Nút Gửi Reply - Dùng btn-theme -->
                                                        <button class="btn btn-sm btn-theme" @click="submitReply(c.id)"
                                                            :disabled="isSubmittingReply">
                                                            {{ isSubmittingReply ? 'Đang gửi...' : 'Gửi' }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- VÒNG LẶP CON (REPLIES) -->
                                        <div v-if="c.replies && c.replies.length > 0"
                                            class="replies-list ms-5 mt-2 ps-3 border-start border-3 border-light">
                                            <div v-for="reply in c.replies" :key="reply.id"
                                                class="comment-card p-3 mb-2 border rounded bg-light">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="d-flex align-items-center">
                                                        <!-- Avatar Reply -->
                                                        <div class="me-2">
                                                            <img v-if="getUserAvatar(reply.user) && !imageLoadErrors[reply.id]"
                                                                :src="getUserAvatar(reply.user)" class="rounded-circle"
                                                                style="width: 35px; height: 35px; object-fit: cover;"
                                                                @error="handleImageError(reply.id)" />
                                                            <div v-else class="avatar-circle small text-white"
                                                                style="width: 35px; height: 35px; font-size: 0.9rem;"
                                                                :style="{ backgroundColor: getAvatarColor(getUserDisplayName(reply.user, reply.user_name)) }">
                                                                {{ getInitials(getUserDisplayName(reply.user,
                                                                reply.user_name))
                                                                }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 fw-bold" style="font-size: 0.95rem;">
                                                                {{ getUserDisplayName(reply.user, reply.user_name) }}
                                                                <span v-if="reply.user?.role === 'admin'"
                                                                    class="badge bg-danger ms-1"
                                                                    style="font-size:0.6rem">QTV</span>
                                                            </h6>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <small class="text-muted fst-italic"
                                                            style="font-size: 0.8rem;">{{
                                                            formatDate(reply.created_at) }}</small>
                                                        <!-- Nút Xóa (Reply) -->
                                                        <button
                                                            v-if="currentUser && (String(currentUser.id) === String(reply.user_id) || currentUser.role === 'admin')"
                                                            class="btn btn-link text-danger p-0 ms-2 small text-decoration-none btn-delete-comment"
                                                            style="font-size: 0.8rem;" @click="deleteComment(reply.id)">
                                                            <i class="bi bi-trash"></i> Xóa
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="comment-content ps-2 ms-5">
                                                    <p class="mb-1 text-dark">{{ reply.content }}</p>

                                                    <!-- Nút Trả lời Reply - Dùng text-theme -->
                                                    <button
                                                        class="btn btn-link p-0 text-decoration-none small fw-bold text-theme"
                                                        @click="openReplyForm(reply)">
                                                        <i class="bi bi-reply-fill"></i> Trả lời
                                                    </button>

                                                    <!-- FORM REPLY INLINE (Con) -->
                                                    <div v-if="activeReplyId === reply.id"
                                                        class="reply-form mt-2 p-2 bg-white border rounded fade-in">
                                                        <textarea :id="`reply-input-${reply.id}`" v-model="replyContent"
                                                            class="form-control mb-2" rows="2"></textarea>
                                                        <div class="d-flex gap-2 justify-content-end">
                                                            <button class="btn btn-sm btn-secondary"
                                                                @click="closeReplyForm">Hủy</button>
                                                            <!-- Nút Gửi Reply - Dùng btn-theme -->
                                                            <button class="btn btn-sm btn-theme"
                                                                @click="submitReply(reply.id)"
                                                                :disabled="isSubmittingReply">
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
                                            <button class="page-link" @click="changeCommentPage(commentPage - 1)"><i
                                                    class="bi bi-chevron-left"></i></button>
                                        </li>
                                        <li v-for="page in totalCommentPages" :key="page" class="page-item"
                                            :class="{ active: commentPage === page }">
                                            <button class="page-link" @click="changeCommentPage(page)">{{ page
                                                }}</button>
                                        </li>
                                        <li class="page-item" :class="{ disabled: commentPage === totalCommentPages }">
                                            <button class="page-link" @click="changeCommentPage(commentPage + 1)"><i
                                                    class="bi bi-chevron-right"></i></button>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
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

.fade-in-up {
    animation: fadeInUp 0.5s ease-out;
}

.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

/* THEME UTILS */
.text-theme {
    color: var(--primary-color) !important;
}

.text-theme:hover {
    color: var(--primary-hover) !important;
    text-decoration: underline !important;
}

.btn-theme {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
    font-weight: 500;
}

.btn-theme:hover:not(:disabled) {
    background-color: var(--primary-hover);
    border-color: var(--primary-hover);
    color: white;
}

.btn-theme:disabled {
    background-color: #a0c4be;
    border-color: #a0c4be;
    color: #fff;
}

.btn-delete-comment:hover {
    text-decoration: underline !important;
    font-weight: bold;
}

/* IMAGES */
.main-image-wrapper {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    padding: 20px;
    height: 500px;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.main-product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
    border-radius: 8px;
}

.main-product-image:hover {
    transform: scale(1.05);
}

@media (max-width: 768px) {
    .main-image-wrapper {
        height: 350px;
    }
}

.thumbnail-gallery {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    flex-wrap: nowrap;
    margin-top: 10px;
    padding-bottom: 5px;
    scrollbar-width: thin;
}

.thumbnail-gallery::-webkit-scrollbar {
    height: 4px;
}

.thumbnail-gallery::-webkit-scrollbar-track {
    background: transparent;
}

.thumbnail-gallery::-webkit-scrollbar-thumb {
    background-color: #ccc;
    border-radius: 4px;
}

.thumbnail-wrapper {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    background: #fff;
    padding: 2px;
    flex-shrink: 0;
}

.thumbnail-wrapper.active {
    border-color: var(--primary-color);
    transform: translateY(-2px);
}

.thumbnail-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* INFO & CHIPS */
.product-info-box {
    padding: 0 10px;
}

.chip-btn {
    border: 1px solid #e0e0e0;
    background: #fff;
    color: #333;
    padding: 8px 16px;
    min-width: unset;
    transition: all 0.3s ease;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
}

.chip-btn:hover:not(.disabled) {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: #f0fdfa;
}

.chip-btn.active {
    border-color: var(--primary-color);
    background-color: var(--primary-color);
    color: #fff;
    box-shadow: 0 4px 10px rgba(0, 153, 129, 0.3);
}

.chip-btn.disabled {
    opacity: 0.5;
    background: #f8f9fa;
    color: #adb5bd;
    text-decoration: line-through;
    cursor: not-allowed;
    border-style: dashed;
}

.qty-group button {
    background: #fff;
    color: #333;
    border-color: #ced4da;
}

.qty-group button:hover {
    background: #f8f9fa;
}

.qty-group input {
    background: #fff;
    border-color: #ced4da;
    color: #333;
}

.btn-add-cart {
    background-color: var(--primary-color);
    color: white;
    border: none;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s;
}

.btn-add-cart:hover:not(:disabled) {
    background-color: var(--primary-hover);
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 153, 129, 0.2);
}

.btn-add-cart:disabled {
    background-color: #a0c4be;
    cursor: not-allowed;
}

.icon-btn {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
}

.icon-btn:hover {
    background-color: #ffebee;
    border-color: #ef5350;
}

/* TABS & CARDS */
.nav-pills .nav-link {
    color: #555;
    border-radius: 30px;
    border: 1px solid #e9ecef;
    transition: all 0.2s;
}

.nav-pills .nav-link:hover {
    background: #f8f9fa;
}

.nav-pills .nav-link.active {
    background-color: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
    box-shadow: 0 4px 8px rgba(0, 153, 129, 0.25);
}

.review-card {
    background: #fff;
    border-color: #f1f1f1 !important;
    transition: all 0.2s;
}

.review-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.comment-card {
    transition: all 0.2s;
}

.comment-card:hover {
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
}

/* AVATAR */
.avatar-circle {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.1rem;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    text-transform: uppercase;
}

.avatar-circle.small {
    width: 35px;
    height: 35px;
    font-size: 0.9rem;
}

/* CUSTOM TABS FOR RECOMMENDATIONS */
.custom-pills .nav-link {
    color: #555;
    border: none;
    background: transparent;
    border-bottom: 3px solid transparent;
    border-radius: 0 !important; /* Overriding rounded-pill if want square */
    padding-bottom: 10px;
    margin-bottom: -3px; /* Align border with container border */
}

.custom-pills .nav-link:hover {
    color: var(--primary-color);
    background: transparent;
}

.custom-pills .nav-link.active {
    color: var(--primary-color);
    background: transparent;
    border-bottom-color: var(--primary-color);
    box-shadow: none;
}

/* RELATED PRODUCTS (SCROLL STYLE) */
.related-scroll-wrapper {
    overflow-x: auto;
    scrollbar-width: none;
    /* Hide scrollbar Firefox */
    -ms-overflow-style: none;
    /* Hide scrollbar IE/Edge */
}

.related-scroll-wrapper::-webkit-scrollbar {
    display: none;
    /* Hide scrollbar Chrome */
}

.btn-nav {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-color: #ddd;
    color: #555;
    transition: all 0.2s;
}

.btn-nav:hover {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.product-card-pro {
    transition: all 0.3s ease;
    cursor: pointer;
    background: #fff;
    overflow: hidden;
    border-radius: 12px;
}

.product-card-pro:hover {
    transform: translateY(-5px);
    /* Move card up slightly */
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
}

.card-img-top-wrapper {
    height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    padding: 10px;
    overflow: hidden;
    /* Ensure image zoom stays inside */
}

.product-img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.5s ease;
}

.product-card-pro:hover .product-img {
    transform: scale(1.1);
    /* Zoom image inside wrapper */
}

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.wish-btn-visible {
    z-index: 5;
    transition: all 0.2s;
    background: rgba(255, 255, 255, 0.9);
}

.wish-btn-visible:hover {
    background-color: #fee2e2;
    transform: scale(1.1);
}

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

/* ------------------------------------------- */
/* [NEW] SKELETON LOADING STYLES               */
/* ------------------------------------------- */

.skeleton-container {
    padding-bottom: 50px;
}

/* Hiệu ứng Shimmer (Ánh sáng chạy qua) */
.shimmer {
  background: #f6f7f8;
  background-image: linear-gradient(
    to right,
    #f6f7f8 0%,
    #edeef1 20%,
    #f6f7f8 40%,
    #f6f7f8 100%
  );
  background-repeat: no-repeat;
  background-size: 800px 100%; 
  animation: placeholderShimmer 1.5s linear infinite forwards;
}

@keyframes placeholderShimmer {
  0% {
    background-position: -468px 0;
  }
  100% {
    background-position: 468px 0;
  }
}

.skeleton-box {
    background-color: #eee;
    border-radius: 8px;
}

/* 1. Main Image */
.skeleton-main-img {
    width: 100%;
    height: 500px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* 2. Thumbnails */
.skeleton-thumb {
    width: 70px;
    height: 70px;
}

/* 3. Info Right */
.skeleton-title {
    height: 32px;
    width: 70%;
}

.skeleton-text {
    height: 16px;
    border-radius: 4px;
}

.skeleton-price-area {
    height: 60px;
    width: 100%;
}

.skeleton-chip {
    width: 80px;
    height: 35px;
    border-radius: 8px;
}

.skeleton-action-btn {
    height: 50px;
    border-radius: 8px;
}

.skeleton-icon-btn {
    width: 50px;
    height: 50px;
    border-radius: 8px;
}

/* 4. Placeholder Text */
.skeleton-placeholder-text-large {
  font-size: 3rem;
  font-weight: 900;
  color: #e5e7eb;
  text-transform: uppercase;
  letter-spacing: 2px;
  opacity: 0.8;
}

/* 5. Recommendation Card Skeleton */
.skeleton-card {
    min-width: 240px;
    height: 350px;
    border-radius: 12px;
}

@media (max-width: 768px) {
    .skeleton-main-img {
        height: 350px;
    }
}
</style>