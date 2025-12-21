<script setup>
import { ref, nextTick, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
// [UPDATED] Import logic wishlist từ store
import { isInWishlist, toggleWishlist } from '../../store/wishlistStore.js';

// --- PROPS ---
const props = defineProps({
    products: {
        type: Array,
        default: () => []
    }
});

// --- CẤU HÌNH ---
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';
const CHATBOT_API_URL = `${API_BASE_URL}/chatbot/search`;
const SERVER_ROOT_URL = API_BASE_URL.replace('/api', '');

const STORAGE_KEY = 'thinkhub_chat_history';
const SESSION_KEY = 'thinkhub_chat_session_id';

// --- STATE ---
const router = useRouter();
const isChatOpen = ref(false);
const isChatExpanded = ref(false);
const chatInput = ref("");
const isChatLoading = ref(false);
const chatBodyRef = ref(null);
const inputRef = ref(null);
const sessionId = ref("");

// [NEW] Biến lưu ngữ cảnh tìm kiếm gần nhất
const lastSearchContext = ref({
    keyword: '',
    detectedBrands: [],
    detectedCategories: []
});

// --- WISHLIST LOGIC (UPDATED) ---
// Sử dụng trực tiếp isInWishlist từ store trong template

const handleToggleWishlist = (p) => {
    // Sử dụng toggleWishlist từ store (trả về true nếu added, false nếu removed)
    const added = toggleWishlist(p);
    
    // Cấu hình Toast giống ProductDetail
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

    if (added) {
        Toast.fire({ icon: 'success', title: 'Đã thêm vào yêu thích' });
    } else {
        Toast.fire({ icon: 'info', title: 'Đã xóa khỏi yêu thích' });
    }
};

const defaultMessage = {
    role: 'ai',
    text: 'Xin chào! Mình là ThinkBot 🤖. Bạn đang tìm điện thoại, macbook hay tai nghe? Nhắn cho mình biết nhé!',
    type: 'chat'
};

const chatMessages = ref([defaultMessage]);

// Gợi ý nhanh
const quickSuggestions = ref([
    "iPhone 15 Promax",
    "Macbook Air M2",
    "Âm thanh",
    "Đồng hồ ",
    "Samsung "
]);

// --- TỪ ĐIỂN NHẬN DIỆN (Knowledge Base) ---
const knownBrands = [
    'samsung', 'apple', 'iphone', 'ipad', 'macbook', 'xiaomi', 'oppo', 'vivo', 'realme', 
    'dell', 'asus', 'hp', 'lenovo', 'acer', 'msi', 'sony', 'jbl', 'marshall', 'lg', 
    'garmin', 'huawei', 'logitech', 'corsair', 'razer'
];

const knownCategories = [
    'điện thoại', 'mobile', 'smartphone', 'di động', 'dế',
    'laptop', 'macbook', 'máy tính xách tay', 'notebook', 'pc', 'máy tính',
    'thiết bị âm thanh', 'âm thanh', 'tai nghe', 'headphone', 'earphone', 'loa', 'speaker', 
    'mic', 'micro', 'máy nghe nhạc', 'dàn âm thanh', 'soundbar', 'amply',
    'đồng hồ', 'đồng hồ thông minh', 'smartwatch', 'watch',
    'máy tính bảng', 'tablet', 'ipad', 'tab'
];

// --- HELPER FUNCTIONS ---
const getImageUrl = (path) => {
    if (!path) return 'https://placehold.co/100x100?text=No+Img';
    if (path.startsWith('http') || path.startsWith('data:')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return `${SERVER_ROOT_URL}/${cleanPath}`;
};

const formatCurrency = (val) => {
    if (val === undefined || val === null || isNaN(val)) return '0 ₫';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const getDisplayPrice = (p) => {
    if (typeof p.price === 'string' && p.price.includes('đ')) return p.price;
    const min = Number(p.min_price || p.price || 0);
    const max = Number(p.max_price || p.price || 0);
    if (max > min) return `${formatCurrency(min)} - ${formatCurrency(max)}`;
    return formatCurrency(min);
};

const calculateDiscount = (price, original) => {
    const p = Number(price || 0);
    const o = Number(original || 0);
    if (!o || o <= p) return 0;
    return Math.round(((o - p) / o) * 100);
};

const isNewProduct = (createdAt) => {
    if (!createdAt) return false;
    const date = new Date(createdAt);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays <= 30; 
};

const getProductPriceVal = (p) => {
    if (typeof p.price === 'string') {
        const cleanStr = p.price.replace(/\./g, '').replace(/[^\d]/g, '');
        return parseFloat(cleanStr) || 0;
    }
    return Number(p.min_price || p.price || 0);
};

const formatMessage = (text) => {
    if (!text) return '';
    return text.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>').replace(/\n/g, '<br>');
};

const goToProduct = (p) => {
    if (p.id) router.push(`/products/${p.id}`);
    else if (p.url) window.location.href = p.url;
};

// --- LOGIC BOT CẢI TIẾN ---

const parseMoney = (text) => {
    if (!text) return 0;
    const cleanText = text.toLowerCase().trim();
    let numStr = cleanText.replace(/,/g, '.').replace(/[^\d.]/g, '');
    let num = parseFloat(numStr);
    if (isNaN(num)) return 0;

    if (cleanText.includes('tr') || cleanText.includes('tỷ') || cleanText.includes('củ')) {
        num *= 1000000;
    } else if (cleanText.includes('k') || cleanText.includes('nghìn') || cleanText.includes('ngàn')) {
        num *= 1000;
    }
    return num;
};

const removeVietnameseTones = (str) => {
    str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g,"a"); 
    str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g,"e"); 
    str = str.replace(/ì|í|ị|ỉ|ĩ/g,"i"); 
    str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g,"o"); 
    str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g,"u"); 
    str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g,"y"); 
    str = str.replace(/đ/g,"d");
    return str;
}

const analyzeIntent = (message) => {
    const lowerMsg = message.toLowerCase();
    
    // 1. Chào hỏi
    const greetings = ['hi', 'hello', 'chào', 'hé lô', 'xin chào', 'bot ơi'];
    if (greetings.some(g => lowerMsg.includes(g) && lowerMsg.length < 20)) {
        return { type: 'greeting', response: 'Dạ em chào anh/chị ạ! 👋' };
    }

    // 2. Phân tích giá
    let min = 0;
    let max = Infinity;
    let isPriceFilter = false;
    
    // Regex bắt các cụm từ chỉ giá
    const priceRegex = /(?:giá\s*)?(dưới|trên|hơn|tầm|khoảng|từ)\s*(\d+(?:[.,]\d+)?)\s*(triệu|tr|củ|tỷ|k|nghìn|ngàn|đ|vnd)?/i;
    const match = lowerMsg.match(priceRegex);

    let textToAnalyze = lowerMsg;

    if (match) {
        isPriceFilter = true;
        const operator = match[1];
        const numberPart = match[2];
        const unitPart = match[3] || '';
        const priceVal = parseMoney(numberPart + unitPart);
        
        // Xóa phần giá khỏi text để tìm từ khóa sản phẩm
        textToAnalyze = lowerMsg.replace(match[0], '').trim();

        if (['trên', 'từ', 'hơn'].includes(operator)) {
            min = priceVal;
        } else if (['dưới', 'bé hơn', 'nhỏ hơn', 'thấp hơn'].includes(operator)) {
            max = priceVal;
        } else if (['tầm', 'khoảng'].includes(operator)) {
            min = priceVal * 0.9;
            max = priceVal * 1.1;
        }
    }

    // 3. Tách thực thể (Brand/Category)
    let detectedBrands = [];
    let detectedCategories = [];
    const normalizedText = removeVietnameseTones(textToAnalyze);
    const textNoSpaces = normalizedText.replace(/\s+/g, '');

    // Quét Brands
    knownBrands.forEach(brand => {
        if (textToAnalyze.includes(brand) || normalizedText.includes(brand)) {
            detectedBrands.push(brand);
        }
        else if (brand.length > 2 && textNoSpaces.includes(brand)) {
             detectedBrands.push(brand);
        }
    });

    // Quét Categories
    knownCategories.forEach(cat => {
        if (textToAnalyze.includes(cat) || normalizedText.includes(removeVietnameseTones(cat))) {
            if (['mobile', 'smartphone', 'di động', 'dế'].includes(cat)) detectedCategories.push('điện thoại');
            else if (['pc', 'máy tính', 'notebook'].includes(cat)) detectedCategories.push('laptop');
            else if (['headphone', 'earphone', 'loa', 'speaker', 'mic', 'micro', 'máy nghe nhạc', 'dàn âm thanh', 'soundbar', 'amply'].includes(cat)) {
                detectedCategories.push(cat); 
            }
            else if (['watch', 'smartwatch'].includes(cat)) detectedCategories.push('đồng hồ');
            else if (['tablet', 'ipad', 'tab'].includes(cat)) detectedCategories.push('máy tính bảng');
            else detectedCategories.push(cat);
        }
    });

    // 4. Xác định xem người dùng có nhập "Chủ ngữ" (Sản phẩm) không?
    let finalKeyword = "";
    let isExplicitSubject = false; // Cờ đánh dấu có chủ ngữ rõ ràng

    if (detectedBrands.length > 0) {
        finalKeyword = detectedBrands[0]; 
        isExplicitSubject = true;
        if (detectedCategories.length > 0) {
            if (!detectedBrands[0].includes(detectedCategories[0]) && !detectedCategories[0].includes(detectedBrands[0])) {
                finalKeyword = `${detectedCategories[0]} ${detectedBrands[0]}`;
            }
        }
    } else if (detectedCategories.length > 0) {
        finalKeyword = detectedCategories[0];
        isExplicitSubject = true;
    } else {
        // Clean stop words
        const stopWords = ['mua', 'cần tìm', 'tìm', 'kiếm', 'bán', 'cho mình', 'cho tớ', 'em muốn', 'tôi muốn', 'cái', 'chiếc', 'loại', 'sản phẩm', 'shop', 'ơi', 'hỏi', 'giá', 'nào', 'tốt', 'nhất', 'rẻ', 'tư vấn'];
        const stopWordsRegex = new RegExp(`\\b(${stopWords.join('|')})\\b`, 'gi');
        finalKeyword = textToAnalyze.replace(stopWordsRegex, '').replace(/\s+/g, ' ').trim();
        
        if (!finalKeyword) finalKeyword = 'all';
        else isExplicitSubject = true; // Có từ khóa khác rỗng (vd: tên model cụ thể ko nằm trong từ điển)
    }

    // 5. Trả về kết quả phân tích
    return {
        type: isPriceFilter ? 'price_filter' : 'search',
        keyword: finalKeyword,
        detectedBrands,
        detectedCategories,
        min,
        max,
        rawText: textToAnalyze,
        isExplicitSubject // [NEW] Quan trọng để quyết định dùng context cũ hay không
    };
};

// Hàm tìm kiếm API
const performSearch = async (query) => {
    if (!query) return { results: [], messages: [] };
    const url = new URL(CHATBOT_API_URL);
    url.searchParams.append('q', query);
    if (sessionId.value) url.searchParams.append('session_id', sessionId.value);

    try {
        const response = await fetch(url.toString(), {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) return { results: [], messages: [] };
        return await response.json();
    } catch (e) {
        console.error("API Error", e);
        return { results: [], messages: [] };
    }
};

const handleSend = async (text) => {
    const msg = text || chatInput.value;
    if (!msg || !msg.trim()) return;

    chatMessages.value.push({ role: 'user', text: msg });
    chatInput.value = "";
    isChatLoading.value = true;
    scrollToBottom();

    try {
        const intent = analyzeIntent(msg);
        let finalProducts = [];
        let botResponseText = "";
        
        // [QUAN TRỌNG] Xử lý ngữ cảnh (Context)
        // Nếu câu hỏi chỉ có giá (không có chủ ngữ rõ ràng) VÀ ta có ngữ cảnh cũ -> Dùng ngữ cảnh cũ
        let currentSearchKeyword = intent.keyword;
        let isUsingContext = false;

        if (intent.keyword === 'all' && !intent.isExplicitSubject && lastSearchContext.value.keyword && lastSearchContext.value.keyword !== 'all') {
            currentSearchKeyword = lastSearchContext.value.keyword;
            // Kế thừa cả brand/category cũ để hiển thị lời thoại cho mượt
            if (intent.detectedBrands.length === 0) intent.detectedBrands = lastSearchContext.value.detectedBrands;
            if (intent.detectedCategories.length === 0) intent.detectedCategories = lastSearchContext.value.detectedCategories;
            isUsingContext = true;
        }

        if (intent.type === 'greeting') {
            botResponseText = intent.response;
            const data = await performSearch('iphone');
            finalProducts = data.results || [];
        } else {
            // --- CHIẾN THUẬT TÌM KIẾM ĐA TẦNG ---
            
            // Bước 1: Tìm theo keyword đã xử lý (có thể là keyword mới hoặc lấy từ context)
            let attempt1_Query = "";
            if (intent.detectedCategories.length > 0 && intent.detectedBrands.length > 0) {
                // Nếu người dùng nhập mới thì ưu tiên ghép lại, nếu dùng context thì lấy keyword gốc
                attempt1_Query = isUsingContext ? currentSearchKeyword : `${intent.detectedCategories[0]} ${intent.detectedBrands[0]}`;
            } else {
                attempt1_Query = currentSearchKeyword === 'all' ? intent.rawText : currentSearchKeyword;
            }

            let searchData = await performSearch(attempt1_Query);
            
            if (searchData.results && searchData.results.length > 0) {
                finalProducts = searchData.results;
            } else {
                // FALLBACKS (Chỉ chạy nếu không dùng context hoặc context cũng fail)
                if (intent.detectedBrands.length > 0) {
                    const brandQuery = intent.detectedBrands[0];
                    searchData = await performSearch(brandQuery);
                    if (searchData.results?.length > 0) {
                        finalProducts = searchData.results;
                        currentSearchKeyword = brandQuery; // Update keyword thực tế tìm được
                    }
                }

                if (finalProducts.length === 0 && intent.detectedCategories.length > 0) {
                    const catQuery = intent.detectedCategories[0];
                    searchData = await performSearch(catQuery);
                    if (searchData.results?.length > 0) {
                        finalProducts = searchData.results;
                        currentSearchKeyword = catQuery;
                    }
                }
            }

            // [CONTEXT UPDATE] Nếu tìm thấy sản phẩm và có từ khóa rõ ràng, lưu lại ngữ cảnh
            if (finalProducts.length > 0) {
                // Nếu đang dùng context cũ thì không cần update, trừ khi người dùng đổi chủ đề
                if (intent.isExplicitSubject) {
                    lastSearchContext.value = {
                        keyword: currentSearchKeyword,
                        detectedBrands: intent.detectedBrands,
                        detectedCategories: intent.detectedCategories
                    };
                }
            }

            // Xây dựng câu trả lời
            if (finalProducts.length > 0) {
                const brandStr = intent.detectedBrands.length ? intent.detectedBrands.join(', ') : '';
                const catStr = intent.detectedCategories.length ? intent.detectedCategories.join(', ') : '';
                const subject = (catStr || brandStr) ? `${catStr} ${brandStr}` : `"${currentSearchKeyword}"`;
                
                botResponseText = isUsingContext 
                    ? `Dạ, vẫn là <b>${subject}</b>` 
                    : `Dạ, em tìm thấy các mẫu <b>${subject}</b>`;
            } else {
                botResponseText = `Hmm, em chưa tìm thấy sản phẩm nào khớp với yêu cầu. Anh/chị thử tìm tên khác xem sao ạ?`;
                const randomData = await performSearch('all'); 
                finalProducts = (randomData.results || []).slice(0, 5);
            }
        }

        // Lọc giá ở Client-side (Bước cuối cùng)
        if (intent.type === 'price_filter') {
            const formatP = (n) => n >= 1000000 ? (n/1000000) + ' triệu' : (n/1000) + 'k';
            let priceMsg = intent.max === Infinity ? `trên ${formatP(intent.min)}` : `dưới ${formatP(intent.max)}`;
            if (intent.min > 0 && intent.max < Infinity) priceMsg = `tầm ${formatP((intent.min+intent.max)/2)}`;

            const beforeFilterCount = finalProducts.length;
            finalProducts = finalProducts.filter(p => {
                const price = getProductPriceVal(p);
                return price >= intent.min && price <= intent.max;
            });

            if (finalProducts.length > 0) {
                botResponseText += ` có giá <b>${priceMsg}</b> đây ạ:`;
            } else if (beforeFilterCount > 0) {
                botResponseText = `Dạ dòng này bên em có, nhưng hiện chưa có mẫu nào giá <b>${priceMsg}</b> ạ. Anh/chị tham khảo các mức giá khác nhé:`;
                // Restore list để user xem
                const restoreData = await performSearch(currentSearchKeyword);
                finalProducts = restoreData.results.slice(0, 5);
            }
        } else if (finalProducts.length > 0 && intent.type !== 'greeting') {
             botResponseText += ` đây ạ:`;
        }

        chatMessages.value.push({
            role: 'ai',
            text: botResponseText,
            products: finalProducts,
            type: finalProducts.length > 0 ? 'search' : 'chat'
        });

    } catch (err) {
        console.error("Chat Error:", err);
        chatMessages.value.push({ role: 'ai', text: "Hệ thống đang bận xíu, bạn thử lại sau nhé!", type: 'chat' });
    } finally {
        isChatLoading.value = false;
        scrollToBottom();
    }
};

const clearHistory = () => {
    chatMessages.value = [defaultMessage];
    sessionId.value = "";
    lastSearchContext.value = { keyword: '', detectedBrands: [], detectedCategories: [] }; // Reset context
    try { 
        localStorage.removeItem(STORAGE_KEY); 
        localStorage.removeItem(SESSION_KEY);
    } catch (e) { }

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
    Toast.fire({ icon: 'success', title: 'Đã xóa lịch sử trò chuyện' });
};

const toggleChat = () => isChatOpen.value = !isChatOpen.value;
const toggleExpand = () => isChatExpanded.value = !isChatExpanded.value;

const scrollToBottom = () => {
    nextTick(() => {
        if (chatBodyRef.value) {
            chatBodyRef.value.scrollTop = chatBodyRef.value.scrollHeight;
        }
    });
};

onMounted(() => {
    try {
        const savedChat = localStorage.getItem(STORAGE_KEY);
        if (savedChat) chatMessages.value = JSON.parse(savedChat);
        const savedSession = localStorage.getItem(SESSION_KEY);
        if (savedSession) sessionId.value = savedSession;
    } catch (e) { }
});

watch(chatMessages, (newVal) => {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(newVal)); } catch (e) { }
}, { deep: true });

watch(isChatOpen, (val) => {
    if (val) {
        scrollToBottom();
        nextTick(() => inputRef.value?.focus());
    }
});

watch(isChatExpanded, () => scrollToBottom());
</script>

<template>
    <div class="chatbot-wrapper">
        <button class="chat-toggle-btn" :class="{ 'hidden-btn': isChatOpen }" @click="toggleChat">
            <span v-if="!isChatOpen" class="badge">1</span>
            <i class="fas fa-comment-dots"></i>
        </button>

        <transition name="zoom-fade">
            <div class="chat-window" v-if="isChatOpen" :class="{ 'expanded': isChatExpanded }">
                <div class="chat-header">
                    <div class="bot-info">
                        <div class="bot-avatar-header">
                            <img src="https://cdn-icons-png.flaticon.com/512/4712/4712027.png" alt="Bot">
                        </div>
                        <div>
                            <div class="fw-bold">ThinkHub Assistant</div>
                            <div class="status-dot">● Đang trực tuyến</div>
                        </div>
                    </div>
                    <div class="window-actions">
                        <button @click="toggleExpand" :title="isChatExpanded ? 'Thu nhỏ' : 'Phóng to'">
                            <i class="fas" :class="isChatExpanded ? 'fa-compress' : 'fa-expand'"></i>
                        </button>
                        <button @click="clearHistory" title="Xóa lịch sử"><i class="fas fa-trash-alt"></i></button>
                        <button @click="toggleChat" title="Đóng"><i class="fas fa-minus"></i></button>
                    </div>
                </div>

                <div class="chat-body" ref="chatBodyRef">
                    <div v-for="(msg, i) in chatMessages" :key="i" class="message-row" :class="msg.role">
                        <div v-if="msg.role === 'ai'" class="avatar-col">
                            <img src="https://cdn-icons-png.flaticon.com/512/4712/4712027.png" alt="AI">
                        </div>

                        <div class="content-col">
                            <div class="bubble" v-html="formatMessage(msg.text)"></div>

                            <div v-if="msg.products && msg.products.length" class="product-slider">
                                <div v-for="p in msg.products" :key="p.id" class="product-card-pro border shadow-sm position-relative"
                                    @click="goToProduct(p)">
                                    
                                    <!-- WISHLIST BUTTON (NEW) -->
                                    <button class="btn-wishlist position-absolute top-0 end-0 m-2 rounded-circle border-0 shadow-sm d-flex align-items-center justify-content-center"
                                        style="width: 32px; height: 32px; z-index: 5; background: rgba(255,255,255,0.95); transition: all 0.2s;"
                                        :class="{ 'text-danger': isInWishlist(p.id), 'text-muted': !isInWishlist(p.id) }"
                                        @click.stop="handleToggleWishlist(p)"
                                        title="Thêm vào yêu thích">
                                        <i class="fas fa-heart" v-if="isInWishlist(p.id)"></i>
                                        <i class="far fa-heart" v-else></i>
                                    </button>

                                    <!-- BADGES (Overlay) -->
                                    <div class="badges-overlay position-absolute top-0 start-0 p-2 z-index-2 d-flex flex-column gap-1">
                                        <span v-if="p.original_price && calculateDiscount(getProductPriceVal(p), p.original_price) > 0" class="badge bg-danger rounded-pill shadow-sm">
                                            -{{ calculateDiscount(getProductPriceVal(p), p.original_price) }}%
                                        </span>
                                        <span v-if="isNewProduct(p.created_at)" class="badge bg-primary rounded-pill shadow-sm">NEW</span>
                                        <span v-if="p.sold_count > 100" class="badge bg-warning text-dark rounded-pill shadow-sm">HOT</span>
                                    </div>

                                    <!-- Image -->
                                    <div class="card-img-top-wrapper overflow-hidden position-relative">
                                        <img :src="getImageUrl(p.image_url || p.thumbnail_url || p.image)" class="card-img-top product-img" :alt="p.name" 
                                            @error="$event.target.src='https://placehold.co/100x100?text=Error'">
                                    </div>

                                    <!-- Body -->
                                    <div class="card-body p-3 d-flex flex-column">
                                        <small class="text-muted text-uppercase mb-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                            {{ p.brand || p.brand_name || 'THƯƠNG HIỆU' }}
                                        </small>
                                        
                                        <h6 class="card-title fw-bold text-dark text-truncate-2 mb-2" style="height: 36px; font-size: 0.9rem;" :title="p.name">
                                            {{ p.name }}
                                        </h6>

                                        <div class="d-flex align-items-center mb-2 small text-muted">
                                            <div class="d-flex text-warning me-2" v-if="p.average_rating || p.rating_avg">
                                                <i class="bi bi-star-fill" style="font-size: 0.8rem;"></i>
                                                <span class="ms-1 text-dark fw-bold">{{ Number(p.average_rating || p.rating_avg).toFixed(1) }}</span>
                                            </div>
                                            <span class="border-start ps-2" v-if="p.sold_count">Đã bán {{ p.sold_count }}</span>
                                        </div>

                                        <div class="mt-auto">
                                            <div class="d-flex align-items-baseline flex-wrap gap-2">
                                                <span class="text-theme fw-bold fs-6" style="color: #dc2626;">{{ getDisplayPrice(p) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="msg.type === 'search' && (!msg.products || msg.products.length === 0)"
                                class="no-result">
                                <i class="far fa-sad-tear"></i> Không tìm thấy sản phẩm nào.
                            </div>
                        </div>
                    </div>

                    <div v-if="isChatLoading" class="message-row ai">
                        <div class="avatar-col">
                            <img src="https://cdn-icons-png.flaticon.com/512/4712/4712027.png" alt="AI">
                        </div>
                        <div class="bubble loading">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>

                <div class="suggestions-bar" v-if="!isChatLoading">
                    <div class="sug-item" v-for="s in quickSuggestions" :key="s" @click="handleSend(s)">
                        {{ s }}
                    </div>
                </div>

                <div class="chat-input-area">
                    <input ref="inputRef" v-model="chatInput" @keyup.enter="handleSend()"
                        placeholder="Nhập tên sản phẩm, hãng..." />
                    <button @click="handleSend()" :disabled="!chatInput.trim()"><i
                            class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
/* Main Wrapper */
.chatbot-wrapper {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 10000;
    font-family: 'Segoe UI', sans-serif;
    pointer-events: none;
    width: 0;
    height: 0;
}

/* Toggle Button */
.chat-toggle-btn {
    pointer-events: auto;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border: none;
    font-size: 24px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    cursor: pointer;
    position: absolute;
    bottom: 0;
    right: 0;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    transform: scale(1);
    opacity: 1;
    z-index: 10001;
    animation: pulse-green 2s infinite;
}

.chat-toggle-btn:hover {
    transform: scale(1.1);
    animation: none;
}

.chat-toggle-btn.hidden-btn {
    transform: scale(0) rotate(90deg);
    opacity: 0;
    pointer-events: none;
    animation: none;
}

.badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #ef4444;
    color: white;
    font-size: 11px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}

/* Chat Window */
.chat-window {
    pointer-events: auto;
    position: absolute;
    bottom: 0;
    right: 0;
    width: 380px;
    height: 600px;
    max-height: 80vh;
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transform-origin: bottom right;
    z-index: 10002;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.zoom-fade-enter-active,
.zoom-fade-leave-active {
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.zoom-fade-enter-from,
.zoom-fade-leave-to {
    opacity: 0;
    transform: scale(0) translate(20px, 20px);
    border-radius: 50%;
}

.zoom-fade-enter-to,
.zoom-fade-leave-from {
    opacity: 1;
    transform: scale(1) translate(0, 0);
    border-radius: 16px;
}

.chat-window.expanded {
    width: 800px;
    height: 90vh; 
    max-height: 95vh; 
    max-width: 90vw;
}
/* Header */
.chat-header {
    background: linear-gradient(to right, #10b981, #059669);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: white;
}

.bot-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.bot-avatar-header img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: white;
    padding: 2px;
}

.status-dot {
    font-size: 11px;
    color: #d1fae5;
}

.window-actions button {
    background: transparent;
    border: none;
    color: white;
    opacity: 0.8;
    margin-left: 8px;
    cursor: pointer;
    transition: opacity 0.2s;
}

.window-actions button:hover {
    opacity: 1;
}

/* Body */
.chat-body {
    flex: 1;
    background: #f3f4f6;
    padding: 16px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.message-row {
    display: flex;
    gap: 8px;
    width: 100%;
}

.message-row.user {
    justify-content: flex-end;
}

.avatar-col img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
}

.bubble {
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.5;
    max-width: 280px;
    position: relative;
    word-wrap: break-word;
}

.message-row.user .bubble {
    background: #10b981;
    color: white;
    border-bottom-right-radius: 2px;
}

.message-row.ai .bubble {
    background: white;
    color: #1f2937;
    border-bottom-left-radius: 2px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

/* Product Slider Styles */
.product-slider {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    margin-top: 8px;
    padding-bottom: 8px;
    scrollbar-width: thin;
    max-width: 300px;
    padding-left: 2px; 
}

.expanded .product-slider {
    max-width: 700px;
}

/* ====== NEW CARD STYLES ====== */
.product-card-pro {
    min-width: 180px; 
    width: 180px;
    background: white;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0; 
}

.product-card-pro:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
    border-color: #10b981 !important;
}

.card-img-top-wrapper {
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    padding: 5px;
    overflow: hidden;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.product-img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.5s ease;
}

.product-card-pro:hover .product-img {
    transform: scale(1.1);
}

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.badges-overlay .badge {
    font-size: 9px;
    padding: 3px 6px;
}

/* Wishlist Button Hover Effect */
.btn-wishlist:hover {
    transform: scale(1.1);
    background: white !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

/* ========================================================== */

.no-result {
    font-size: 12px;
    color: #6b7280;
    margin-top: 5px;
    font-style: italic;
}

/* Suggestions & Input */
.suggestions-bar {
    padding: 10px;
    background: white;
    border-top: 1px solid #f3f4f6;
    display: flex;
    gap: 8px;
    overflow-x: auto;
    scrollbar-width: none;
}

.sug-item {
    background: #f3f4f6;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    white-space: nowrap;
    cursor: pointer;
    color: #4b5563;
    border: 1px solid transparent;
}

.sug-item:hover {
    border-color: #10b981;
    color: #10b981;
    background: #ecfdf5;
}

.chat-input-area {
    padding: 10px;
    background: white;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 8px;
}

.chat-input-area input {
    flex: 1;
    border: 1px solid #d1d5db;
    border-radius: 20px;
    padding: 8px 16px;
    outline: none;
    font-size: 14px;
}

.chat-input-area input:focus {
    border-color: #10b981;
}

.chat-input-area button {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #10b981;
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-input-area button:disabled {
    background: #d1d5db;
    cursor: not-allowed;
}

/* Loading Animation */
.loading span {
    display: inline-block;
    width: 6px;
    height: 6px;
    background: #9ca3af;
    border-radius: 50%;
    margin: 0 2px;
    animation: bounce 1.4s infinite;
}

.loading span:nth-child(2) {
    animation-delay: 0.2s;
}

.loading span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes bounce {

    0%,
    100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-5px);
    }
}

@keyframes pulse-green {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }

    70% {
        box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

@media (max-width: 480px) {
    .chatbot-wrapper {
        right: 10px;
        bottom: 10px;
        width: auto;
        height: auto;
    }

    .chat-toggle-btn {
        right: 10px;
        bottom: 10px;
    }

    .chat-window,
    .chat-window.expanded {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: 100%;
        max-height: 100%;
        border-radius: 0;
        transform-origin: bottom right;
    }

    .product-slider {
        max-width: 90vw;
    }
}
</style>