<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from 'vuex'; // <--- BỔ SUNG
import apiService from '../../apiService';
import Swal from 'sweetalert2';

const route = useRoute();
const router = useRouter();
const store = useStore(); // <--- KHỞI TẠO STORE

const loading = ref(true);
const status = ref('');
const message = ref('Đang xử lý kết quả thanh toán...');
const orderInfo = ref(null);

onMounted(async () => {
    // 1. Lấy tham số từ URL
    const params = route.query;

    if (!params.vnp_ResponseCode) {
        status.value = 'error';
        message.value = 'Không tìm thấy thông tin giao dịch hợp lệ.';
        loading.value = false;
        return;
    }

    try {
        // 2. Gọi Backend check checksum
        const res = await apiService.get('/payment/vnpay-return', { params });

        if (res.data.status === 'success') {
            status.value = 'success';
            message.value = 'Giao dịch thành công!';
            orderInfo.value = res.data.data;

            // --- 3. LOGIC XÓA GIỎ HÀNG ĐÃ SỬA ---
            // Lấy danh sách ID các món hàng đã mang đi thanh toán từ LocalStorage
            const storedIds = localStorage.getItem('checkout_items');

            if (storedIds) {
                try {
                    const idsToDelete = JSON.parse(storedIds);
                    if (Array.isArray(idsToDelete)) {
                        // Lặp qua từng ID và xóa khỏi Vuex Store
                        idsToDelete.forEach(cartId => {
                            store.dispatch('removeItem', cartId);
                        });
                    }
                } catch (e) {
                    console.error("Lỗi parse JSON checkout_items", e);
                }
                // Xóa key lưu tạm sau khi xử lý xong
                localStorage.removeItem('checkout_items');
            }
            // ------------------------------------

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

        } else {
            status.value = 'error';
            message.value = res.data.message || 'Giao dịch thất bại.';
        }
    } catch (err) {
        console.error(err);
        status.value = 'error';
        message.value = err.response?.data?.message || 'Có lỗi xảy ra khi xác thực giao dịch.';
    } finally {
        loading.value = false;
    }
});

const goHome = () => router.push('/');
const viewOrders = () => router.push('/OrderList');
const retryPayment = () => router.push('/cart'); 
</script>

<template>
    <div class="payment-result-page container">
        <h2 class="page-title"><i class="fa-solid fa-receipt"></i> Kết quả thanh toán</h2>

        <div class="result-content">
            <div class="card result-card">

                <!-- TRẠNG THÁI LOADING -->
                <div v-if="loading" class="state-box loading">
                    <div class="spinner">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                    </div>
                    <h3>Đang xử lý...</h3>
                    <p>Vui lòng đợi trong giây lát, chúng tôi đang xác thực giao dịch của bạn với VNPay.</p>
                </div>

                <!-- TRẠNG THÁI THÀNH CÔNG -->
                <div v-else-if="status === 'success'" class="state-box success">
                    <div class="icon-wrapper success-icon">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h2 class="status-title">Thanh toán thành công!</h2>
                    <p class="status-desc">Cảm ơn bạn đã mua sắm. Đơn hàng của bạn đã được thanh toán và đang chờ xử lý.
                    </p>

                    <div class="order-summary-box" v-if="orderInfo">
                        <div class="summary-row">
                            <span>Mã giao dịch:</span>
                            <strong>{{ route.query.vnp_TransactionNo || 'N/A' }}</strong>
                        </div>
                        <div class="summary-row">
                            <span>Mã đơn hàng:</span>
                            <strong>#{{ route.query.vnp_TxnRef || 'N/A' }}</strong>
                        </div>
                        <div class="summary-row total">
                            <span>Số tiền:</span>
                            <span class="amount">
                                {{ Number(route.query.vnp_Amount / 100).toLocaleString() }} đ
                            </span>
                        </div>
                        <div class="summary-row">
                            <span>Ngân hàng:</span>
                            <span>{{ route.query.vnp_BankCode }}</span>
                        </div>
                        <div class="summary-row">
                            <span>Thời gian:</span>
                            <span>{{ route.query.vnp_PayDate }}</span>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button @click="viewOrders" class="btn-primary-custom">
                            <i class="fa-solid fa-box"></i> Xem đơn hàng
                        </button>
                        <button @click="goHome" class="btn-outline-custom">
                            <i class="fa-solid fa-house"></i> Về trang chủ
                        </button>
                    </div>
                </div>

                <!-- TRẠNG THÁI THẤT BẠI -->
                <div v-else class="state-box error">
                    <div class="icon-wrapper error-icon">
                        <i class="fa-solid fa-xmark"></i>
                    </div>
                    <h2 class="status-title">Thanh toán thất bại</h2>
                    <p class="status-desc">{{ message }}</p>
                    <p class="sub-desc">Giao dịch đã bị hủy hoặc xảy ra lỗi trong quá trình thanh toán.</p>

                    <div class="action-buttons">
                        <button @click="retryPayment" class="btn-primary-custom error-btn">
                            <i class="fa-solid fa-rotate-right"></i> Thử lại / Về giỏ hàng
                        </button>
                        <button @click="goHome" class="btn-outline-custom">
                            Về trang chủ
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.payment-result-page {
    padding: 40px 20px;
    background: #f8f9fa;
    min-height: 80vh;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.page-title {
    color: #111;
    font-weight: 700;
    font-size: 28px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-title i {
    color: #009981;
}

.result-content {
    width: 100%;
    max-width: 600px;
}

/* Card Style giống Checkout */
.card {
    background: #fff;
    padding: 40px;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    border: 1px solid #eee;
    text-align: center;
}

/* State Boxes */
.state-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Icons */
.icon-wrapper {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    margin-bottom: 20px;
}

.success-icon {
    background-color: #e6fffa;
    color: #009981;
    box-shadow: 0 0 0 10px #f0fdfa;
}

.error-icon {
    background-color: #ffe6e6;
    color: #e74c3c;
    box-shadow: 0 0 0 10px #fff5f5;
}

.spinner {
    font-size: 50px;
    color: #009981;
    margin-bottom: 20px;
}

/* Typography */
.status-title {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 10px;
    color: #333;
}

.status-desc {
    color: #666;
    font-size: 15px;
    margin-bottom: 25px;
    line-height: 1.5;
}

.sub-desc {
    color: #888;
    font-size: 14px;
    margin-top: -15px;
    margin-bottom: 25px;
}

/* Order Summary Box */
.order-summary-box {
    background: #f8f9fa;
    border: 1px dashed #ddd;
    border-radius: 10px;
    padding: 20px;
    width: 100%;
    margin-bottom: 30px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 14px;
    color: #555;
}

.summary-row.total {
    border-top: 1px solid #eee;
    padding-top: 10px;
    margin-top: 10px;
    margin-bottom: 0;
    align-items: center;
}

.summary-row.total span {
    font-size: 16px;
    font-weight: 600;
}

.summary-row.total .amount {
    color: #009981;
    font-size: 20px;
    font-weight: 700;
}

/* Buttons */
.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    width: 100%;
}

.btn-primary-custom {
    padding: 12px 25px;
    background: #009981;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    justify-content: center;
}

.btn-primary-custom:hover {
    background: #007a67;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0, 153, 129, 0.2);
}

.btn-primary-custom.error-btn {
    background: #e74c3c;
}

.btn-primary-custom.error-btn:hover {
    background: #c0392b;
    box-shadow: 0 4px 10px rgba(231, 76, 60, 0.2);
}

.btn-outline-custom {
    padding: 12px 25px;
    background: transparent;
    border: 1px solid #ddd;
    color: #555;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    flex: 1;
}

.btn-outline-custom:hover {
    border-color: #009981;
    color: #009981;
    background: #f0fdfa;
}
</style>