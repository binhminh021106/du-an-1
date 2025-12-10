<script setup>
import { ref, nextTick } from 'vue';

// Nhận dữ liệu sản phẩm từ trang cha để tìm kiếm
const props = defineProps({
  products: {
    type: Array,
    default: () => []
  }
});

// --- CẤU HÌNH RIÊNG CHO CHATBOT ---
const SERVER_URL = 'http://127.0.0.1:8000';
const CHATBOT_API_URL = 'http://localhost:3000/api/chat-search'; 
const USE_STORAGE = false;

// --- STATE ---
const isChatOpen = ref(false);
const chatInput = ref("");
const isChatLoading = ref(false);
const chatMessages = ref([
    { role: 'ai', text: 'Xin chào! Bạn cần tìm sản phẩm gì?', products: [] }
]);
const chatBodyRef = ref(null);

// --- HELPER FUNCTIONS (Dùng nội bộ cho Chatbot) ---
const getImageUrl = (path) => {
    if (!path) return 'https://placehold.co/400x300?text=No+Image';
    if (path.startsWith('http') || path.startsWith('data:') || path.startsWith('blob:')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`;
};

const formatCurrency = (value) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);

const getProductPrice = (product) => {
    if (!product) return 0;
    if (product.variants && Array.isArray(product.variants) && product.variants.length > 0) {
        const prices = product.variants.map(v => Number(v.price)).filter(p => !isNaN(p) && p > 0);
        if (prices.length > 0) return Math.min(...prices);
    }
    return Number(product.price) || 0;
};

// --- LOGIC CHAT ---
const toggleChat = () => { 
    isChatOpen.value = !isChatOpen.value; 
    if (isChatOpen.value) scrollToBottom(); 
};

const scrollToBottom = () => { 
    nextTick(() => { 
        if (chatBodyRef.value) chatBodyRef.value.scrollTop = chatBodyRef.value.scrollHeight; 
    }); 
};

const sendChatMessage = async () => {
    if (!chatInput.value.trim()) return;
    
    const userText = chatInput.value;
    chatMessages.value.push({ role: 'user', text: userText });
    chatInput.value = "";
    isChatLoading.value = true;
    scrollToBottom();

    try {
        const response = await fetch(CHATBOT_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: userText })
        });
        
        if (!response.ok) throw new Error("AI Error");
        const data = await response.json();
        const filters = data.ai_data || {};

        // Logic lọc sản phẩm dựa trên filter từ AI trả về
        let realProducts = props.products; // Sử dụng props.products
        
        if (filters.keyword) {
            const k = filters.keyword.toLowerCase();
            realProducts = realProducts.filter(p => (p.name || '').toLowerCase().includes(k));
        }
        if (filters.min_price) realProducts = realProducts.filter(p => getProductPrice(p) >= filters.min_price);
        if (filters.max_price) realProducts = realProducts.filter(p => getProductPrice(p) <= filters.max_price);

        chatMessages.value.push({
            role: 'ai',
            text: realProducts.length > 0 ? `Tìm thấy ${realProducts.length} sản phẩm phù hợp:` : "Tiếc quá, mình không tìm thấy sản phẩm nào như vậy.",
            products: realProducts
        });
    } catch (error) {
        chatMessages.value.push({ role: 'ai', text: "Đang gặp sự cố kết nối AI. Vui lòng thử lại sau.", products: [] });
    } finally {
        isChatLoading.value = false;
        scrollToBottom();
    }
};
</script>

<template>
    <div class="chatbot-wrapper">
        <button class="chat-toggle-btn" @click="toggleChat">
            <i class="fas" :class="isChatOpen ? 'fa-times' : 'fa-comment-dots'"></i>
        </button>
        
        <transition name="slide-up">
            <div class="chat-window" v-if="isChatOpen">
                <div class="chat-header">
                    <span><i class="fas fa-robot me-2"></i> Trợ lý AI</span>
                    <button @click="toggleChat"><i class="fas fa-minus"></i></button>
                </div>
                
                <div class="chat-body" ref="chatBodyRef">
                    <div v-for="(msg, index) in chatMessages" :key="index" class="chat-message" :class="msg.role">
                        <div class="chat-avatar" v-if="msg.role === 'ai'"><i class="fas fa-robot"></i></div>
                        <div class="msg-content-wrapper">
                            <div class="msg-content">
                                <p>{{ msg.text }}</p>
                            </div>
                            <!-- Hiển thị sản phẩm gợi ý -->
                            <div v-if="msg.products && msg.products.length > 0" class="chat-product-list">
                                <div v-for="p in msg.products" :key="p.id" class="chat-product-item"
                                    @click="$router.push({ name: 'ProductDetail', params: { id: p.id } })">
                                    <img :src="getImageUrl(p.thumbnail_url || p.image)" alt="img">
                                    <div class="cp-info">
                                        <div class="cp-name">{{ p.name }}</div>
                                        <div class="cp-price">{{ formatCurrency(getProductPrice(p)) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="isChatLoading" class="chat-message ai">
                        <div class="chat-avatar"><i class="fas fa-robot"></i></div>
                        <div class="msg-content typing"><span>.</span><span>.</span><span>.</span></div>
                    </div>
                </div>
                
                <div class="chat-footer">
                    <input type="text" v-model="chatInput"
                        placeholder="Hỏi AI (VD: Tìm laptop dưới 20tr)..." 
                        @keyup.enter="sendChatMessage">
                    <button @click="sendChatMessage" :disabled="isChatLoading">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.chatbot-wrapper {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9999;
    font-family: 'Montserrat', sans-serif;
}

.chat-toggle-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #009981, #006b5a);
    color: white;
    border: none;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0, 153, 129, 0.4);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-toggle-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0, 153, 129, 0.6);
}

.chat-window {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 360px;
    height: 520px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

/* Animation Slide Up */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px) scale(0.95);
}

.chat-header {
    background: linear-gradient(135deg, #009981, #007563);
    color: white;
    padding: 15px 20px;
    font-weight: 600;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.chat-header button {
    background: transparent;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 16px;
    opacity: 0.8;
    transition: 0.2s;
}

.chat-header button:hover {
    opacity: 1;
}

.chat-body {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    background: #f4f6f8;
    display: flex;
    flex-direction: column;
    gap: 15px;
    scroll-behavior: smooth;
}

.chat-message {
    display: flex;
    gap: 10px;
    max-width: 100%;
}

.chat-message.user {
    justify-content: flex-end;
}

.chat-avatar {
    width: 32px;
    height: 32px;
    background: #e0e0e0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    font-size: 14px;
    flex-shrink: 0;
}

.chat-message.ai .chat-avatar {
    background: #009981;
    color: white;
}

.msg-content-wrapper {
    max-width: 80%;
    display: flex;
    flex-direction: column;
}

.msg-content {
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.5;
    position: relative;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.chat-message.user .msg-content {
    background: #009981;
    color: white;
    border-bottom-right-radius: 2px;
}

.chat-message.ai .msg-content {
    background: white;
    color: #333;
    border-bottom-left-radius: 2px;
    border: 1px solid #eee;
}

.chat-product-list {
    margin-top: 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.chat-product-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: white;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.2s;
    border: 1px solid #eee;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
}

.chat-product-item:hover {
    border-color: #009981;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 153, 129, 0.15);
}

.chat-product-item img {
    width: 45px;
    height: 45px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #f0f0f0;
}

.cp-info {
    flex: 1;
    overflow: hidden;
}

.cp-name {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #333;
}

.cp-price {
    font-size: 12px;
    color: #d0021b;
    font-weight: 700;
}

.chat-footer {
    padding: 12px;
    border-top: 1px solid #eee;
    background: white;
    display: flex;
    gap: 8px;
    align-items: center;
}

.chat-footer input {
    flex: 1;
    border: 1px solid #e0e0e0;
    padding: 10px 15px;
    border-radius: 25px;
    outline: none;
    font-size: 13px;
    transition: 0.2s;
    background: #f9f9f9;
}

.chat-footer input:focus {
    border-color: #009981;
    background: white;
}

.chat-footer button {
    background: #009981;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
    flex-shrink: 0;
}

.chat-footer button:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.chat-footer button:hover:not(:disabled) {
    background: #007563;
    transform: scale(1.05);
}

.typing span {
    animation: blink 1.4s infinite both;
    margin: 0 2px;
    font-weight: bold;
    font-size: 16px;
    color: #888;
}

.typing span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes blink {
    0% { opacity: 0.2; }
    20% { opacity: 1; }
    100% { opacity: 0.2; }
}

@media (max-width: 768px) {
    .chatbot-wrapper {
        bottom: 20px;
        right: 20px;
    }

    .chat-window {
        position: fixed;
        bottom: 0;
        right: 0;
        width: 100%;
        height: 80vh;
        border-radius: 20px 20px 0 0;
    }
}
</style>