<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// CONFIGURATION
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';

// --- CẤU HÌNH ENDPOINT ---
const RESOURCE_ENDPOINT = '/admin/orders';
const RESOURCE_ITEMS_ENDPOINT = '/admin/order_items';

// AUTHENTICATION & PERMISSIONS
const currentUser = ref({});

// Hàm kiểm tra quyền hạn (Pure Function)
function hasRole(allowedRoles) {
    if (!currentUser.value || !currentUser.value.role_id) {
        return false;
    }

    const userRoleId = Number(currentUser.value.role_id);
    let userRoleName = '';

    if (userRoleId === 1) userRoleName = 'admin';
    else if (userRoleId === 12) userRoleName = 'staff';
    else if (userRoleId === 13) userRoleName = 'blogger';

    if (!userRoleName) return false;
    if (userRoleName === 'admin') return true;

    return allowedRoles.includes(userRoleName);
}

// --- HÀM CHECK AUTH ---
async function checkAuthState() {
    const token = localStorage.getItem('adminToken');
    if (!token) return false;

    // Set token cho request tiếp theo
    apiService.defaults.headers.common['Authorization'] = `Bearer ${token}`;

    const storedAdmin = localStorage.getItem('adminData');
    let userData = null;

    try {
        if (storedAdmin) userData = JSON.parse(storedAdmin);
    } catch (e) { console.error("Parse adminData error", e); }

    if (userData) {
        currentUser.value = { ...userData, role_id: Number(userData.role_id) };
        return true;
    }

    try {
        const response = await apiService.get('/user');
        let data = response.data;
        // Xử lý response bọc trong data.data nếu có
        if (data.data && !data.id) data = data.data;

        const roleId = Number(data.role_id);
        // Chỉ chấp nhận admin/staff
        if (roleId !== 1 && roleId !== 12) {
            console.warn("Token hợp lệ nhưng không phải quyền Admin/Staff");
            return false;
        }

        currentUser.value = { ...data, role_id: roleId };
        localStorage.setItem('adminData', JSON.stringify(currentUser.value));
        return true;
    } catch (error) {
        console.error("Auth API Error:", error);
        return false;
    }
}

function requireLogin() {
    if (!currentUser.value || !currentUser.value.id) {
        Swal.fire({
            icon: 'error',
            title: 'Truy cập bị từ chối',
            text: 'Phiên làm việc hết hạn hoặc bạn không có quyền Admin.',
            confirmButtonText: 'Đăng nhập lại'
        });
        return false;
    }
    if (!hasRole(['admin', 'staff'])) {
        Swal.fire({
            icon: 'warning',
            title: 'Quyền hạn',
            text: 'Tài khoản này không có quyền quản lý đơn hàng.'
        });
        return false;
    }
    return true;
}

// STATE MANAGEMENT
const allOrders = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const activeTab = ref('pending');
const sortCriteria = ref('id-desc');

// Modal State
const detailModalRef = ref(null);
const detailModalInstance = ref(null);
const selectedOrder = ref({});
const selectedOrderItems = ref([]);
const isLoadingDetails = ref(false);

// --- INVOICE STATE (HTML2PDF) ---
const invoiceModalRef = ref(null);
const invoiceModalInstance = ref(null);
const isInvoiceLoading = ref(false);
const invoiceHtmlContent = ref(''); // Biến chứa HTML của hóa đơn

// Pagination State
const pagination = reactive({
    pending: { currentPage: 1, itemsPerPage: 10 },
    approved: { currentPage: 1, itemsPerPage: 10 },
    shipping: { currentPage: 1, itemsPerPage: 10 },
    completed: { currentPage: 1, itemsPerPage: 10 },
    cancelled: { currentPage: 1, itemsPerPage: 10 },
    returns: { currentPage: 1, itemsPerPage: 10 },
});

// COMPUTED & LOGIC
const processedOrders = computed(() => {
    let result = Array.isArray(allOrders.value) ? [...allOrders.value] : [];

    const query = searchQuery.value.toLowerCase().trim();
    if (query) {
        result = result.filter(order =>
            order.id.toString().includes(query) ||
            (order.customerName && order.customerName.toLowerCase().includes(query)) ||
            (order.customerPhone && order.customerPhone.includes(query))
        );
    }

    const [key, order] = sortCriteria.value.split('-');
    result.sort((a, b) => {
        let valA, valB;
        if (key === 'id') { valA = a.id; valB = b.id; }
        else if (key === 'total') { valA = parseFloat(a.totalAmount); valB = parseFloat(b.totalAmount); }
        else if (key === 'date') { valA = new Date(a.createdAt); valB = new Date(b.createdAt); }

        return order === 'asc' ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1);
    });

    return result;
});

const pendingList = computed(() => processedOrders.value.filter(o => o.status === 'pending'));
const approvedList = computed(() => processedOrders.value.filter(o => o.status === 'approved'));
const shippingList = computed(() => processedOrders.value.filter(o => o.status === 'shipping'));
const completedList = computed(() => processedOrders.value.filter(o => o.status === 'completed'));
const cancelledList = computed(() => processedOrders.value.filter(o => o.status === 'cancelled'));
const returnsList = computed(() => processedOrders.value.filter(o => ['returning', 'returned'].includes(o.status)));

const statusCounts = computed(() => ({
    pending: pendingList.value.length,
    approved: approvedList.value.length,
    shipping: shippingList.value.length,
    completed: completedList.value.length,
    cancelled: cancelledList.value.length,
    returns: returnsList.value.length
}));

function getPaginatedData(list, type) {
    const pageInfo = pagination[type];
    const totalPages = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));
    if (pageInfo.currentPage > totalPages) pageInfo.currentPage = 1;

    const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
    return {
        data: list.slice(start, start + pageInfo.itemsPerPage),
        totalPages
    };
}

const pagedPending = computed(() => getPaginatedData(pendingList.value, 'pending'));
const pagedApproved = computed(() => getPaginatedData(approvedList.value, 'approved'));
const pagedShipping = computed(() => getPaginatedData(shippingList.value, 'shipping'));
const pagedCompleted = computed(() => getPaginatedData(completedList.value, 'completed'));
const pagedCancelled = computed(() => getPaginatedData(cancelledList.value, 'cancelled'));
const pagedReturns = computed(() => getPaginatedData(returnsList.value, 'returns'));

watch([searchQuery, sortCriteria], () => {
    Object.keys(pagination).forEach(key => pagination[key].currentPage = 1);
});

// HELPER FUNCTIONS
const changePage = (type, page) => { pagination[type].currentPage = page; };
const setActiveTab = (tabName) => activeTab.value = tabName;

const formatCurrency = (value) => {
    if (!value && value !== 0) return '0 ₫';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('vi-VN', {
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
    });
};

async function openDetailModal(order) {
    selectedOrder.value = order;
    selectedOrderItems.value = [];
    isLoadingDetails.value = true;

    if (!detailModalInstance.value && detailModalRef.value) {
        detailModalInstance.value = new Modal(detailModalRef.value);
    }

    if (detailModalInstance.value) {
        detailModalInstance.value.show();
    }

    try {
        const response = await apiService.get(`${RESOURCE_ITEMS_ENDPOINT}?orderId=${order.id}&_expand=product`);

        const itemsData = response.data.data || response.data;

        if (Array.isArray(itemsData)) {
            selectedOrderItems.value = itemsData.map(item => ({
                id: item.id,
                variantId: item.variant_id,
                quantity: item.quantity,
                price: item.price,
                product: item.variant?.product || item.product,
                color: item.variant?.attribute_values?.find(val => val.attribute?.name?.toLowerCase().includes('màu'))?.value,
                size: item.variant?.attribute_values?.find(val => val.attribute?.name?.toLowerCase().includes('kích thước'))?.value,
            }));
        } else {
            console.error("Order items data structure error: Expected array.", response.data);
            selectedOrderItems.value = [];
        }

    } catch (error) {
        console.error("Error details:", error);
        Swal.fire('Lỗi', `Không thể tải chi tiết. Vui lòng kiểm tra API Backend (${RESOURCE_ITEMS_ENDPOINT}).`, 'error');
    } finally {
        isLoadingDetails.value = false;
    }
}

// ACTIONS (API)
async function fetchOrders() {
    if (allOrders.value.length === 0) isLoading.value = true;
    try {
        const response = await apiService.get(RESOURCE_ENDPOINT);
        const apiData = response.data;

        let ordersArray = [];

        if (Array.isArray(apiData)) {
            ordersArray = apiData;
        } else if (apiData && Array.isArray(apiData.data)) {
            ordersArray = apiData.data;
        } else {
            console.error("Orders API: Returned non-array data:", apiData);
        }

        const mappedOrders = ordersArray.map(order => ({
            id: order.id,
            customerName: order.customerName || order.customer_name,
            customerPhone: order.customerPhone || order.customer_phone,
            customerAddress: order.customerAddress || order.shipping_address,
            status: order.status,
            totalAmount: order.totalAmount || order.total_amount,
            createdAt: order.createdAt || order.created_at,
            note: order.note || '',
        }));

        allOrders.value = mappedOrders;

    } catch (error) {
        console.error("Error fetching orders:", error);
        allOrders.value = [];

        if (error.code === 'ERR_NETWORK') {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi Kết Nối (CORS)',
                text: 'Không thể kết nối đến Server Backend. Vui lòng kiểm tra Server.'
            });
        } else if (error.response && error.response.status === 500) {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi Server (500)',
                text: 'Backend gặp lỗi nội bộ. Vui lòng kiểm tra log Laravel.'
            });
        } else {
            Swal.fire('Lỗi', `Không thể tải danh sách đơn hàng. Kiểm tra API (${RESOURCE_ENDPOINT}).`, 'error');
        }
    } finally {
        isLoading.value = false;
    }
}

async function handleUpdateStatus(order, newStatus) {
    if (!requireLogin()) return;

    const oldStatus = order.status;
    const statusMessages = {
        'approved': 'Đã duyệt đơn hàng',
        'shipping': 'Đã chuyển sang giao hàng',
        'completed': 'Đã hoàn thành đơn hàng',
        'cancelled': 'Đã hủy đơn hàng',
        'returning': 'Đã chuyển sang hoàn hàng',
        'returned': 'Xác nhận đã nhận hàng hoàn'
    };

    if (['cancelled', 'returning'].includes(newStatus)) {
        const confirmResult = await Swal.fire({
            title: 'Xác nhận?',
            text: `Bạn có chắc chắn muốn chuyển trạng thái thành "${newStatus === 'cancelled' ? 'Hủy' : 'Hoàn hàng'}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Đồng ý', cancelButtonText: 'Hủy'
        });
        if (!confirmResult.isConfirmed) return;
    }

    order.status = newStatus;

    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
    const icon = ['cancelled', 'returning'].includes(newStatus) ? 'warning' : 'success';
    Toast.fire({ icon: icon, title: statusMessages[newStatus] || 'Cập nhật thành công' });

    try {
        await apiService.patch(`${RESOURCE_ENDPOINT}/${order.id}`, { status: newStatus });
    } catch (error) {
        order.status = oldStatus;

        let errorMsg = 'Cập nhật thất bại.';
        if (error.response && error.response.status === 404) {
            errorMsg = `Lỗi 404: API không tồn tại (${RESOURCE_ENDPOINT}/${order.id}).`;
        } else if (error.code === 'ERR_NETWORK') {
            errorMsg = 'Lỗi kết nối mạng (CORS).';
        }

        console.error("Update Status Error:", error);
        Swal.fire('Lỗi', errorMsg, 'error');
    }
}

async function handleDelete(order) {
    if (!requireLogin()) return;

    const result = await Swal.fire({
        title: 'Xóa đơn hàng?',
        text: "Hành động này sẽ xóa vĩnh viễn đơn hàng và dữ liệu liên quan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa ngay', cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`${RESOURCE_ENDPOINT}/${order.id}`);
            allOrders.value = allOrders.value.filter(o => o.id !== order.id);
            Swal.fire('Đã xóa!', 'Đơn hàng đã được xóa.', 'success');
        } catch (error) {
            let errorMsg = 'Không thể xóa đơn hàng.';
            if (error.response && error.response.status === 404) {
                errorMsg = `Lỗi 404: API không tồn tại (${RESOURCE_ENDPOINT}/${order.id}).`;
            }
            console.error("Delete Error:", error);
            Swal.fire('Lỗi', errorMsg, 'error');
        }
    }
}

// --- HTML2PDF INVOICE LOGIC ---
const loadHtml2Pdf = () => {
    return new Promise((resolve, reject) => {
        if (window.html2pdf) {
            resolve(window.html2pdf);
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        script.onload = () => resolve(window.html2pdf);
        script.onerror = () => reject(new Error('Failed to load html2pdf'));
        document.head.appendChild(script);
    });
};

async function openInvoiceModal(order) {
    selectedOrder.value = order;
    isInvoiceLoading.value = true;
    invoiceHtmlContent.value = ''; // Reset content

    if (!invoiceModalInstance.value && invoiceModalRef.value) {
        invoiceModalInstance.value = new Modal(invoiceModalRef.value);
    }
    if (invoiceModalInstance.value) invoiceModalInstance.value.show();

    // Fetch items
    let items = [];
    try {
        const response = await apiService.get(`${RESOURCE_ITEMS_ENDPOINT}?orderId=${order.id}&_expand=product`);
        const itemsData = response.data.data || response.data;
        if (Array.isArray(itemsData)) {
            items = itemsData.map(item => ({
                id: item.id,
                quantity: item.quantity,
                price: item.price,
                productName: item.variant?.product?.name || item.product?.name || 'Sản phẩm ' + item.variant_id,
                color: item.variant?.attribute_values?.find(val => val.attribute?.name?.toLowerCase().includes('màu'))?.value || '',
                size: item.variant?.attribute_values?.find(val => val.attribute?.name?.toLowerCase().includes('kích thước'))?.value || '',
            }));
        }
    } catch (error) {
        console.error("Failed to load invoice items", error);
    }

    // Generate HTML Template
    invoiceHtmlContent.value = generateBeautifulInvoice(order, items);
    isInvoiceLoading.value = false;
}

function generateBeautifulInvoice(order, items) {
    const date = new Date(order.createdAt).toLocaleDateString('vi-VN');
    const itemsHtml = items.map((item, index) => {
        const meta = [item.color, item.size].filter(Boolean).join(' - ');
        return `
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 12px 8px; text-align: center;">${index + 1}</td>
                <td style="padding: 12px 8px;">
                    <div style="font-weight: 600; color: #333;">${item.productName}</div>
                    ${meta ? `<div style="font-size: 0.85em; color: #666;">${meta}</div>` : ''}
                </td>
                <td style="padding: 12px 8px; text-align: center;">${item.quantity}</td>
                <td style="padding: 12px 8px; text-align: right;">${formatCurrency(item.price)}</td>
                <td style="padding: 12px 8px; text-align: right; font-weight: bold;">${formatCurrency(item.price * item.quantity)}</td>
            </tr>
        `;
    }).join('');

    // Template HTML đẹp hơn, clean hơn cho PDF
    return `
        <div class="invoice-container" style="font-family: 'DejaVu Sans', sans-serif; max-width: 800px; margin: 0 auto; padding: 40px; background: #fff; color: #333; line-height: 1.5;">
            <!-- Header -->
            <div style="display: flex; justify-content: space-between; margin-bottom: 40px; border-bottom: 2px solid #009981; padding-bottom: 20px;">
                <div>
                    <h1 style="color: #009981; margin: 0; font-size: 24px; text-transform: uppercase;">CỬA HÀNG THỜI TRANG</h1>
                    <p style="margin: 5px 0; color: #666; font-size: 14px;">Địa chỉ: 123 Đường ABC, Quận XYZ, TP.HCM</p>
                    <p style="margin: 5px 0; color: #666; font-size: 14px;">Hotline: 1900 1234</p>
                </div>
                <div style="text-align: right;">
                    <h2 style="margin: 0; font-size: 28px; color: #333;">HÓA ĐƠN</h2>
                    <p style="margin: 5px 0; font-weight: bold;">#${order.id}</p>
                    <p style="margin: 5px 0; font-size: 14px; color: #666;">Ngày: ${date}</p>
                </div>
            </div>

            <!-- Info -->
            <div style="margin-bottom: 30px; display: flex;">
                <div style="flex: 1;">
                    <h4 style="margin: 0 0 10px 0; color: #009981; font-size: 16px; text-transform: uppercase;">Khách hàng</h4>
                    <p style="margin: 3px 0;"><strong>Tên:</strong> ${order.customerName}</p>
                    <p style="margin: 3px 0;"><strong>SĐT:</strong> ${order.customerPhone}</p>
                    <p style="margin: 3px 0;"><strong>Địa chỉ:</strong> ${order.customerAddress}</p>
                </div>
                ${order.note ? `
                <div style="flex: 1; padding-left: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #009981; font-size: 16px; text-transform: uppercase;">Ghi chú</h4>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; font-size: 14px;">${order.note}</div>
                </div>` : ''}
            </div>

            <!-- Table -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <thead>
                    <tr style="background-color: #f2f2f2; font-size: 14px;">
                        <th style="padding: 10px 8px; text-align: center; width: 50px; font-weight: bold; border-top: 2px solid #ddd;">STT</th>
                        <th style="padding: 10px 8px; text-align: left; font-weight: bold; border-top: 2px solid #ddd;">Tên sản phẩm</th>
                        <th style="padding: 10px 8px; text-align: center; width: 60px; font-weight: bold; border-top: 2px solid #ddd;">SL</th>
                        <th style="padding: 10px 8px; text-align: right; width: 120px; font-weight: bold; border-top: 2px solid #ddd;">Đơn giá</th>
                        <th style="padding: 10px 8px; text-align: right; width: 130px; font-weight: bold; border-top: 2px solid #ddd;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>

            <!-- Total -->
            <div style="display: flex; justify-content: flex-end; margin-bottom: 50px;">
                <table style="width: 40%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px; text-align: right; font-weight: bold;">Tổng tiền thanh toán:</td>
                        <td style="padding: 8px; text-align: right; font-size: 20px; font-weight: bold; color: #d63384;">${formatCurrency(order.totalAmount)}</td>
                    </tr>
                </table>
            </div>

            <!-- Footer -->
            <div style="display: flex; justify-content: space-between; text-align: center; margin-top: 40px;">
                <div style="width: 40%;">
                    <p style="font-weight: bold; margin-bottom: 60px;">Người mua hàng</p>
                    <p style="font-size: 14px; color: #999;">(Ký, ghi rõ họ tên)</p>
                </div>
                <div style="width: 40%;">
                    <p style="font-weight: bold; margin-bottom: 60px;">Người bán hàng</p>
                    <p style="font-size: 14px; color: #999;">(Ký, đóng dấu)</p>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 50px; border-top: 1px solid #eee; padding-top: 20px; color: #888; font-size: 13px;">
                <p>Cảm ơn quý khách đã mua sắm tại cửa hàng chúng tôi!</p>
            </div>
        </div>
    `;
}

async function exportToPDF() {
    try {
        const html2pdf = await loadHtml2Pdf();
        const element = document.getElementById('invoice-content');
        if (!element) {
            Swal.fire('Lỗi', 'Không tìm thấy nội dung hóa đơn.', 'error');
            return;
        }

        const opt = {
            margin: [10, 10, 10, 10], // top, left, bottom, right
            filename: `Hoa_don_${selectedOrder.value.id}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        // Bắt đầu quá trình lưu
        html2pdf().set(opt).from(element).save();

    } catch (error) {
        console.error(error);
        Swal.fire('Lỗi', 'Không thể tải thư viện xuất PDF.', 'error');
    }
}

function printInvoicePreview() {
    const content = document.getElementById('invoice-content').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>In Hóa Đơn #${selectedOrder.value.id}</title>
                <style>
                    body { font-family: sans-serif; -webkit-print-color-adjust: exact; }
                </style>
            </head>
            <body>${content}</body>
            <script>
                window.onload = function() { window.print(); window.close(); }
            <\/script>
        </html>
    `);
    printWindow.document.close();
}

onMounted(async () => {
    const isAuthenticated = await checkAuthState();
    if (!isAuthenticated) {
        isLoading.value = false;
        return;
    }

    if (!requireLogin()) { isLoading.value = false; return; }

    nextTick(() => {
        if (detailModalRef.value) detailModalInstance.value = new Modal(detailModalRef.value);
        if (invoiceModalRef.value) invoiceModalInstance.value = new Modal(invoiceModalRef.value);
    });
    fetchOrders();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-brand">Quản lý Đơn hàng</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active">Đơn hàng</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4 shadow-sm border-0">

                <!-- TABS -->
                <div class="card-header border-bottom-0 pb-0 bg-white">
                    <ul class="nav nav-tabs card-header-tabs custom-tabs">
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'pending' }"
                                href="#" @click.prevent="setActiveTab('pending')">
                                <i class="bi bi-hourglass-split me-1 text-warning"></i> Chờ xử lý
                                <span class="badge rounded-pill bg-warning text-dark ms-2"
                                    v-if="statusCounts.pending > 0">{{ statusCounts.pending }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'approved' }"
                                href="#" @click.prevent="setActiveTab('approved')">
                                <i class="bi bi-check2-circle me-1 text-info"></i> Đã duyệt
                                <span class="badge rounded-pill bg-info ms-2">{{ statusCounts.approved }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'shipping' }"
                                href="#" @click.prevent="setActiveTab('shipping')">
                                <i class="bi bi-truck me-1 text-primary"></i> Đang giao
                                <span class="badge rounded-pill bg-primary ms-2">{{ statusCounts.shipping }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'completed' }"
                                href="#" @click.prevent="setActiveTab('completed')">
                                <i class="bi bi-check-circle-fill me-1 text-success"></i> Hoàn thành
                                <span class="badge rounded-pill bg-success ms-2">{{ statusCounts.completed }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'returns' }"
                                href="#" @click.prevent="setActiveTab('returns')">
                                <i class="bi bi-arrow-counterclockwise me-1 text-secondary"></i> Hàng hoàn
                                <span class="badge rounded-pill bg-secondary ms-2">{{ statusCounts.returns }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'cancelled' }"
                                href="#" @click.prevent="setActiveTab('cancelled')">
                                <i class="bi bi-x-circle me-1 text-danger"></i> Đã hủy
                                <span class="badge rounded-pill bg-danger ms-2">{{ statusCounts.cancelled }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- FILTER BAR -->
                <div class="card-body bg-light border-bottom py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-4 col-12">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0"
                                    placeholder="Tìm mã đơn, tên khách, SĐT..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <select class="form-select" v-model="sortCriteria">
                                <option value="id-desc">Mã ĐH (Mới nhất)</option>
                                <option value="id-asc">Mã ĐH (Cũ nhất)</option>
                                <option value="total-desc">Tổng tiền (Cao - Thấp)</option>
                                <option value="total-asc">Tổng tiền (Thấp - Cao)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- TABLE CONTENT -->
                <div class="card-body p-0">
                    <div class="tab-content">
                        <template
                            v-for="tab in ['pending', 'approved', 'shipping', 'completed', 'returns', 'cancelled']"
                            :key="tab">
                            <div class="tab-pane fade show active" v-if="activeTab === tab">
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered align-middle mb-0 custom-table">
                                        <thead class="bg-light text-secondary">
                                            <tr>
                                                <th style="width: 80px" class="ps-3">Mã ĐH</th>
                                                <th>Khách hàng</th>
                                                <th>Liên hệ</th>
                                                <th>Tổng tiền</th>
                                                <th>Ngày đặt</th>
                                                <th>Trạng thái</th>
                                                <th class="text-end pe-3" style="min-width: 180px;">Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="isLoading">
                                                <td colspan="7" class="text-center py-5">
                                                    <div class="spinner-border text-primary"></div>
                                                </td>
                                            </tr>
                                            <tr
                                                v-else-if="(tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).data.length === 0">
                                                <td colspan="7" class="text-center py-5 text-muted fst-italic">Không có
                                                    đơn hàng nào trong mục này.</td>
                                            </tr>
                                            <tr v-else
                                                v-for="order in (tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).data"
                                                :key="order.id">
                                                <td class="ps-3 fw-bold text-brand">#{{ order.id }}</td>
                                                <td class="fw-bold text-dark">{{ order.customerName }}</td>
                                                <td>{{ order.customerPhone }}</td>
                                                <td class="fw-bold text-danger">{{ formatCurrency(order.totalAmount) }}
                                                </td>
                                                <td class="small text-muted">{{ formatDate(order.createdAt) }}</td>
                                                <td>
                                                    <span v-if="order.status === 'pending'"
                                                        class="badge bg-warning text-dark border border-warning">Chờ xử
                                                        lý</span>
                                                    <span v-else-if="order.status === 'approved'"
                                                        class="badge bg-info border border-info">Đã duyệt</span>
                                                    <span v-else-if="order.status === 'shipping'"
                                                        class="badge bg-primary border border-primary">Đang giao</span>
                                                    <span v-else-if="order.status === 'completed'"
                                                        class="badge bg-success border border-success">Hoàn thành</span>
                                                    <span v-else-if="order.status === 'cancelled'"
                                                        class="badge bg-danger border border-danger">Đã hủy</span>
                                                    <span v-else-if="order.status === 'returning'"
                                                        class="badge bg-secondary border border-secondary">Đang
                                                        hoàn</span>
                                                    <span v-else-if="order.status === 'returned'"
                                                        class="badge bg-dark border border-dark">Đã nhận hoàn</span>
                                                    <span v-else class="badge bg-light text-dark border">{{ order.status
                                                        }}</span>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <div class="d-flex justify-content-end gap-1">
                                                        <button class="btn btn-sm btn-light text-secondary border"
                                                            @click="openDetailModal(order)" title="Xem chi tiết"><i
                                                                class="bi bi-eye"></i></button>

                                                        <!-- Status Actions -->
                                                        <template v-if="order.status === 'pending'">
                                                            <button class="btn btn-sm btn-success text-white"
                                                                @click="handleUpdateStatus(order, 'approved')"
                                                                title="Duyệt"><i class="bi bi-check-lg"></i> Duyệt
                                                                đơn</button>
                                                            <button class="btn btn-sm btn-light text-danger border"
                                                                @click="handleUpdateStatus(order, 'cancelled')"
                                                                title="Hủy"><i class="bi bi-x-lg"> Hủy</i></button>
                                                        </template>

                                                        <template v-if="order.status === 'approved'">
                                                            <button class="btn btn-sm btn-primary text-white"
                                                                @click="handleUpdateStatus(order, 'shipping')"
                                                                title="Giao hàng"><i class="bi bi-truck"></i> Giao
                                                                hàng</button>
                                                            <button class="btn btn-sm btn-light text-danger border"
                                                                @click="handleUpdateStatus(order, 'cancelled')"
                                                                title="Hủy"><i class="bi bi-x-lg"></i> Hủy</button>
                                                        </template>

                                                        <template v-if="order.status === 'shipping'">
                                                            <button class="btn btn-sm btn-success text-white"
                                                                @click="handleUpdateStatus(order, 'completed')"
                                                                title="Hoàn thành"><i class="bi bi-check2-circle"></i>
                                                                Hoàn Thành</button>
                                                            <button class="btn btn-sm btn-light text-secondary border"
                                                                @click="handleUpdateStatus(order, 'returning')"
                                                                title="Hoàn hàng"><i
                                                                    class="bi bi-arrow-counterclockwise"></i> Hoàn
                                                                Hàng</button>
                                                        </template>

                                                        <template v-if="order.status === 'completed'">
                                                            <!-- NÚT XUẤT HÓA ĐƠN PDF -->
                                                            <button class="btn btn-sm btn-danger text-white"
                                                                @click="openInvoiceModal(order)" title="Xuất PDF"><i
                                                                    class="bi bi-file-earmark-pdf"></i> PDF</button>

                                                            <button class="btn btn-sm btn-light text-secondary border"
                                                                @click="handleUpdateStatus(order, 'returning')"
                                                                title="Hoàn hàng"><i
                                                                    class="bi bi-arrow-counterclockwise"></i> Hoàn
                                                                thành</button>
                                                        </template>

                                                        <template v-if="order.status === 'returning'">
                                                            <button class="btn btn-sm btn-dark text-white"
                                                                @click="handleUpdateStatus(order, 'returned')"
                                                                title="Xác nhận đã nhận"><i
                                                                    class="bi bi-box-seam"></i>Xác nhận đã nhận</button>
                                                        </template>

                                                        <button v-if="['cancelled', 'returned'].includes(order.status)"
                                                            class="btn btn-sm btn-light text-danger border"
                                                            @click="handleDelete(order)" title="Xóa"><i
                                                                class="bi bi-trash"></i> Xoá</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <div class="card-footer bg-white border-top-0 py-3"
                                    v-if="(tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).totalPages > 1">
                                    <ul class="pagination pagination-sm m-0 justify-content-end">
                                        <li class="page-item" :class="{ disabled: pagination[tab].currentPage === 1 }">
                                            <button class="page-link border-0"
                                                @click="changePage(tab, pagination[tab].currentPage - 1)">&laquo;</button>
                                        </li>
                                        <li v-for="p in (tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).totalPages"
                                            :key="p" class="page-item"
                                            :class="{ active: pagination[tab].currentPage === p }"><button
                                                class="page-link border-0 rounded-circle mx-1"
                                                @click="changePage(tab, p)">{{ p }}</button></li>
                                        <li class="page-item"
                                            :class="{ disabled: pagination[tab].currentPage === (tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).totalPages }">
                                            <button class="page-link border-0"
                                                @click="changePage(tab, pagination[tab].currentPage + 1)">&raquo;</button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CHI TIẾT -->
    <div class="modal fade" id="detailModal" ref="detailModalRef" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-brand fw-bold"><i class="bi bi-receipt me-2"></i> Chi tiết đơn hàng #{{
                        selectedOrder.id }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Info Section -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6 border-end">
                            <h6 class="text-uppercase text-secondary small fw-bold mb-3">Thông tin khách hàng</h6>
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-light rounded-circle p-2 me-3"><i
                                        class="bi bi-person fs-5 text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">{{ selectedOrder.customerName }}</div>
                                    <div class="text-muted small">{{ selectedOrder.customerPhone }}</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="bg-light rounded-circle p-2 me-3"><i
                                        class="bi bi-geo-alt fs-5 text-danger"></i>
                                </div>
                                <div class="text-muted small mt-1">{{ selectedOrder.customerAddress }}</div>
                            </div>
                        </div>
                        <div class="col-md-6 ps-md-4">
                            <h6 class="text-uppercase text-secondary small fw-bold mb-3">Thông tin đơn hàng</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Ngày đặt:</span>
                                <span class="fw-bold">{{ formatDate(selectedOrder.createdAt) }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Tổng tiền:</span>
                                <span class="fw-bold text-danger fs-5">{{ formatCurrency(selectedOrder.totalAmount)
                                    }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Trạng thái:</span>
                                <span class="badge bg-warning text-dark" v-if="selectedOrder.status === 'pending'">Chờ
                                    xử
                                    lý</span>
                                <span class="badge bg-success" v-else>{{ selectedOrder.status }}</span>
                            </div>
                            <div class="alert alert-light border mb-0 small py-2 mt-3" v-if="selectedOrder.note">
                                <i class="bi bi-sticky me-1"></i> <strong>Ghi chú:</strong> {{ selectedOrder.note }}
                            </div>
                        </div>
                    </div>

                    <hr class="bg-light">

                    <!-- Order Items -->
                    <h6 class="text-uppercase text-secondary small fw-bold mb-3">Sản phẩm ({{ selectedOrderItems.length
                        }})
                    </h6>
                    <div v-if="isLoadingDetails" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                    <div v-else class="table-responsive border rounded">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3 py-2">Sản phẩm</th>
                                    <th class="text-center py-2">SL</th>
                                    <th class="text-end py-2">Đơn giá</th>
                                    <th class="text-end pe-3 py-2">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in selectedOrderItems" :key="item.id">
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark">{{ item.product?.name || `Product ID:
                                            ${item.variantId}` }}</div>
                                        <div class="small text-muted" v-if="item.color || item.size">Phân loại: {{
                                            item.color }} / {{ item.size }}</div>
                                    </td>
                                    <td class="text-center">{{ item.quantity }}</td>
                                    <td class="text-end text-muted">{{ formatCurrency(item.price) }}</td>
                                    <td class="text-end pe-3 fw-bold">{{ formatCurrency(item.price * item.quantity) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Đóng</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL INVOICE PDF -->
    <div class="modal fade" id="invoiceModal" ref="invoiceModalRef" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title text-brand fw-bold"><i class="bi bi-printer me-2"></i> Xem trước Hóa đơn</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0 bg-light" style="min-height: 500px; max-height: 80vh; overflow-y: auto;">
                    <div v-if="isInvoiceLoading" class="d-flex justify-content-center align-items-center h-100 py-5">
                        <div class="spinner-border text-primary"></div>
                    </div>

                    <!-- Vùng hiển thị hóa đơn (Preview) -->
                    <div v-else class="d-flex justify-content-center py-4">
                        <div id="invoice-content" class="shadow-sm" style="width: 800px; background: white;"
                            v-html="invoiceHtmlContent"></div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Đóng</button>
                    <button type="button" class="btn btn-outline-dark px-4" @click="printInvoicePreview">
                        <i class="bi bi-printer"></i> In ngay
                    </button>
                    <button type="button" class="btn btn-danger px-4" @click="exportToPDF">
                        <i class="bi bi-file-earmark-pdf-fill"></i> Tải PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* COLORS */
.text-brand {
    color: #009981 !important;
}

.text-primary {
    color: #009981 !important;
}

.bg-primary {
    background-color: #009981 !important;
}

.btn-primary {
    background-color: #009981 !important;
    border-color: #009981 !important;
    color: white !important;
}

.btn-primary:hover {
    background-color: #007a67 !important;
    border-color: #007a67 !important;
}

.btn-outline-primary {
    color: #009981 !important;
    border-color: #009981 !important;
}

.btn-outline-primary:hover {
    background-color: #009981 !important;
    color: white !important;
}

/* SUCCESS */
.btn-success {
    background-color: #009981 !important;
    border-color: #009981 !important;
    color: white !important;
}

.btn-success:hover {
    background-color: #007a67 !important;
    border-color: #007a67 !important;
}

/* TABS */
.custom-tabs .nav-link {
    color: #6c757d;
    border: none;
    font-weight: 500;
    padding: 12px 20px;
    border-bottom: 3px solid transparent;
    cursor: pointer;
}

.custom-tabs .nav-link:hover {
    color: #009981;
}

.custom-tabs .nav-link.active {
    color: #009981;
    background: transparent;
    border-bottom: 3px solid #009981;
}

/* PAGINATION */
.page-item.active .page-link {
    background-color: #009981 !important;
    border-color: #009981 !important;
    color: white !important;
}

.page-link {
    color: #666;
    cursor: pointer;
}

/* TABLE */
:deep(.table-striped-columns) tbody tr td:nth-child(even) {
    background-color: rgba(0, 0, 0, 0.02);
}

.custom-table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

/* MODAL */
.modal-title {
    color: #00483D;
}
</style>