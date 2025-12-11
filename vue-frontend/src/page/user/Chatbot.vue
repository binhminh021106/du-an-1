<script setup>
import { ref, nextTick, onMounted, watch } from 'vue';

// --- PROPS ---
const props = defineProps({
    products: {
        type: Array,
        default: () => []
    }
});

// --- CẤU HÌNH ---
const SERVER_URL = 'http://127.0.0.1:8000'; // URL Backend Laravel
const CHATBOT_API_URL = 'http://localhost:3000/api/chat-search'; // URL Node.js AI
const STORAGE_KEY = 'thinkhub_chat_history';

// --- STATE ---
const isChatOpen = ref(false);
const isChatExpanded = ref(false);
const chatInput = ref("");
const isChatLoading = ref(false);
const chatBodyRef = ref(null);
const inputRef = ref(null);

const defaultMessage = {
    role: 'ai',
    text: 'Xin chào! Mình là ThinkBot 🤖. Bạn cần tìm điện thoại, laptop hay muốn tâm sự mỏng với mình?',
    type: 'chat'
};

const chatMessages = ref([defaultMessage]);

const quickSuggestions = ref([
    "Laptop gaming dưới 20 triệu",
    "iPhone 15 Pro Max",
    "Hôm nay ngày bao nhiêu?",
    "Shop có bán trả góp không?"
]);

// --- HELPER FUNCTIONS ---
const getImageUrl = (path) => {
    if (!path) return 'https://placehold.co/100x100?text=No+Img';
    if (path.startsWith('http') || path.startsWith('data:')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return `${SERVER_URL}/${cleanPath}`;
};

const formatCurrency = (val) => {
    if (!val) return '0 ₫';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const getProductPrice = (p) => {
    if (p.variants && p.variants.length > 0) {
        const prices = p.variants.map(v => Number(v.price));
        return Math.min(...prices);
    }
    return Number(p.price) || 0;
};

const formatMessage = (text) => {
    if (!text) return '';
    return text.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>').replace(/\n/g, '<br>');
};

const clearHistory = () => {
    if (confirm('Bạn có chắc muốn xóa lịch sử trò chuyện?')) {
        chatMessages.value = [defaultMessage];
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) { }
    }
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

// --- LOGIC GỬI TIN NHẮN ---
const handleSend = async (text) => {
    const msg = text || chatInput.value;
    if (!msg || !msg.trim()) return;

    chatMessages.value.push({ role: 'user', text: msg });
    chatInput.value = "";
    isChatLoading.value = true;
    scrollToBottom();

    try {
        const categories = [...new Set(props.products.map(p => p.category?.name).filter(Boolean))].slice(0, 15);
        const brands = [...new Set(props.products.map(p => p.brand?.name).filter(Boolean))].slice(0, 15);

        // [CẬP NHẬT] Lấy 10 tin nhắn gần nhất để gửi làm lịch sử (Context)
        const historyContext = chatMessages.value.slice(-10).map(m => ({
            role: m.role,
            text: m.text
        }));

        const res = await fetch(CHATBOT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                query: msg,
                categories,
                brands,
                history: historyContext // Gửi lịch sử lên server
            })
        });

        if (!res.ok) throw new Error("API Error");

        const data = await res.json();
        const aiData = data.ai_data || data.data || {};
        const filters = aiData.filters || {};

        if (aiData.intent === 'chat') {
            chatMessages.value.push({
                role: 'ai',
                text: aiData.reply || "Mình đang lắng nghe bạn đây!",
                type: 'chat'
            });
        } else {
            // Logic SEARCH (Đã tối ưu hóa)
            let results = props.products.filter(p => {
                let match = true;
                const pName = (p.name || '').toLowerCase();
                const pBrand = (p.brand?.name || '').toLowerCase();
                const pCat = (p.category?.name || '').toLowerCase();
                const price = getProductPrice(p);

                // 1. Lọc Keyword & Category (Logic OR thông minh)
                // Nếu AI trả về category_name (VD: "Laptop"), ta tìm cả trong Category lẫn Name
                if (filters.category_name) {
                    const c = filters.category_name.toLowerCase();
                    match = match && (pCat.includes(c) || pName.includes(c));
                }

                // Nếu có keyword riêng (VD: "Gaming")
                if (filters.keyword) {
                    const k = filters.keyword.toLowerCase();
                    match = match && (pName.includes(k) || pCat.includes(k));
                }

                // 2. Lọc Brand
                if (filters.brand_name) {
                    const b = filters.brand_name.toLowerCase();
                    match = match && (pBrand.includes(b) || pName.includes(b));
                }

                // 3. Lọc Giá
                if (filters.min_price !== null && filters.min_price !== undefined) {
                    match = match && price >= filters.min_price;
                }
                if (filters.max_price !== null && filters.max_price !== undefined) {
                    match = match && price <= filters.max_price;
                }

                return match;
            });

            // Sắp xếp
            if (filters.sort === 'price_asc') results.sort((a, b) => getProductPrice(a) - getProductPrice(b));
            if (filters.sort === 'price_desc') results.sort((a, b) => getProductPrice(b) - getProductPrice(a));
            if (filters.sort === 'newest') results.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

            const finalProducts = results.slice(0, 5);

            chatMessages.value.push({
                role: 'ai',
                text: aiData.reply || (finalProducts.length > 0 ? `Mình tìm thấy ${finalProducts.length} sản phẩm này:` : "Tiếc quá, không tìm thấy sản phẩm nào khớp bộ lọc."),
                products: finalProducts,
                type: 'search'
            });
        }

    } catch (err) {
        console.error("Chat Error:", err);
        chatMessages.value.push({ role: 'ai', text: "Hệ thống đang bảo trì một chút, bạn chờ xíu nhé!", type: 'chat' });
    } finally {
        isChatLoading.value = false;
        scrollToBottom();
    }
};

const sendChatMessage = () => handleSend();

// --- LIFECYCLE & WATCHERS ---
onMounted(() => {
    try {
        const savedChat = localStorage.getItem(STORAGE_KEY);
        if (savedChat) chatMessages.value = JSON.parse(savedChat);
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
                        <button @click="toggleChat" title="Thu nhỏ"><i class="fas fa-minus"></i></button>
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
                                <div v-for="p in msg.products" :key="p.id" class="product-card"
                                    @click="$router.push(`/products/${p.id}`)">
                                    <div class="pc-img">
                                        <img :src="getImageUrl(p.thumbnail_url || p.image)" alt="Product">
                                    </div>
                                    <div class="pc-info">
                                        <div class="pc-name" :title="p.name">{{ p.name }}</div>
                                        <div class="pc-price">{{ formatCurrency(getProductPrice(p)) }}</div>
                                        <button class="pc-btn">Xem ngay</button>
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
                        placeholder="Nhập tin nhắn..." />
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
    height: 80vh;
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

/* Product Slider */
.product-slider {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    margin-top: 8px;
    padding-bottom: 5px;
    scrollbar-width: none;
    max-width: 300px;
}

.expanded .product-slider {
    max-width: 700px;
}

.product-card {
    min-width: 140px;
    width: 140px;
    background: white;
    border-radius: 10px;
    padding: 8px;
    border: 1px solid #e5e7eb;
    cursor: pointer;
    transition: transform 0.2s;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-3px);
    border-color: #10b981;
}

.pc-img img {
    width: 100%;
    height: 100px;
    object-fit: cover;
    border-radius: 6px;
}

.pc-info {
    margin-top: 6px;
}

.pc-name {
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
}

.pc-price {
    color: #dc2626;
    font-weight: bold;
    font-size: 12px;
}

.pc-btn {
    width: 100%;
    background: #e5e7eb;
    border: none;
    font-size: 11px;
    padding: 4px;
    margin-top: 5px;
    border-radius: 4px;
    cursor: pointer;
}

.product-card:hover .pc-btn {
    background: #10b981;
    color: white;
}

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