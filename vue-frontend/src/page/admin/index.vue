<script setup>
import { ref, shallowRef, markRaw, onMounted, nextTick, watch, computed } from 'vue';
import apiService from '../../apiService.js';
// import Swal from 'sweetalert2'; 

// ==========================================
// CONFIG & STATE
// ==========================================
const isLoading = ref(true);
const activeDashboardTab = ref('overview'); // 'overview' | 'revenue' | 'inventory'

// Stats
const stats = ref({
    totalRevenue: 0,
    totalOrders: 0,
    totalProducts: 0,
    totalCustomers: 0,
    avgOrderValue: 0,
    bestMonth: '-',
});

// Data Lists
const allOrders = ref([]);
const allProducts = ref([]);
const categories = ref([]); 
const brands = ref([]);     
const latestOrders = ref([]);
const latestReviews = ref([]);
const latestComments = ref([]);
const newMembers = ref([]);
const orderStatusCounts = ref({});

// Inventory State
const inventoryThreshold = ref(10);
const inventoryViewMode = ref('variant'); // 'variant' | 'product'
const isUpdatingStock = ref(null);
const stockAdditions = ref({}); // [NEW] Lưu trữ số lượng muốn cộng thêm cho từng variant
const currentPage = ref(1);
const itemsPerPage = ref(10);

const filters = ref({
    searchName: '',
    category: '',
    brand: '',
    priceRange: '',
    onlyLowStock: false
});

// Modal Detail State
const showDetailModal = ref(false);
const selectedItemDetail = ref(null);

// Revenue Data
const monthlyRevenue = ref(new Array(12).fill(0));
const yearlyRevenue = ref({});

// Chart Refs
const chartInstance = shallowRef(null);
const chartCanvas = ref(null);
const chartConfigKeys = ref([]);
const hoveredLegendKey = ref(null);

const revenueBarInstance = shallowRef(null);
const revenueBarCanvas = ref(null);
const revenueLineInstance = shallowRef(null);
const revenueLineCanvas = ref(null);

// Helper Constants
// Base URL của API (thường có /api ở cuối)
const BACKEND_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';

// [FIX 404 IMAGE] Tạo Base URL riêng cho ảnh bằng cách loại bỏ '/api' ở cuối BACKEND_URL
// Ví dụ: 'http://localhost:8000/api' -> 'http://localhost:8000'
const IMAGE_BASE_URL = BACKEND_URL.replace(/\/api\/?$/, ''); 

const STATUS_CONFIG = {
    'pending':   { label: 'Chờ xử lý', color: '#ffc107', icon: 'bi-hourglass-split' },
    'approved':  { label: 'Đã duyệt',  color: '#0dcaf0', icon: 'bi-check2-circle' },
    'shipping':  { label: 'Đang giao', color: '#009981', icon: 'bi-truck' },
    'completed': { label: 'Hoàn thành',color: '#198754', icon: 'bi-check-circle-fill' },
    'cancelled': { label: 'Đã hủy',    color: '#dc3545', icon: 'bi-x-circle' },
    'returning': { label: 'Đang hoàn', color: '#6c757d', icon: 'bi-arrow-counterclockwise' },
    'returned':  { label: 'Đã hoàn',   color: '#212529', icon: 'bi-box-seam' }
};

const GENERAL_STATUS_CONFIG = {
    'active':   { label: 'Hoạt động', color: 'bg-success' },
    'inactive': { label: 'Tạm khóa',  color: 'bg-secondary' },
    'banned':   { label: 'Bị cấm',    color: 'bg-danger' },
    'pending':  { label: 'Chờ duyệt', color: 'bg-warning text-dark' },
    'approved': { label: 'Đã duyệt',  color: 'bg-success' },
    'hidden':   { label: 'Đã ẩn',     color: 'bg-dark' },
    'rejected': { label: 'Từ chối',   color: 'bg-danger' }
};

// ==========================================
// DATA FETCHING
// ==========================================

const getApiData = (response) => {
    if (!response || !response.data) return [];
    if (Array.isArray(response.data.data)) return response.data.data;
    if (response.data.data && Array.isArray(response.data.data)) return response.data.data;
    if (Array.isArray(response.data)) return response.data;
    return [];
};

const fetchData = async () => {
    isLoading.value = true;
    try {
        const [ordersRes, usersRes, productsRes, reviewsRes, commentsRes, catsRes, brandsRes] = await Promise.all([
            apiService.get('/admin/orders'),
            apiService.get('/admin/users'),
            apiService.get('/admin/inventory'),
            apiService.get('/admin/reviews'),
            apiService.get('/admin/comments'),
            apiService.get('/admin/categories'),
            apiService.get('/admin/brands')
        ]);

        allOrders.value = getApiData(ordersRes);
        const users = getApiData(usersRes);
        const productsRaw = getApiData(productsRes);
        
        allProducts.value = productsRaw;
        categories.value = getApiData(catsRes);
        brands.value = getApiData(brandsRes);

        const reviews = getApiData(reviewsRes);
        const comments = getApiData(commentsRes);

        // --- Stats ---
        stats.value.totalOrders = allOrders.value.length;
        stats.value.totalCustomers = users.filter(u => u.role_id !== 1 && u.role_id !== 12).length; 
        stats.value.totalProducts = productsRaw.length;
        
        const completedOrders = allOrders.value.filter(o => o.status === 'completed');
        stats.value.totalRevenue = completedOrders.reduce((sum, o) => sum + Number(o.totalAmount || o.total_amount || 0), 0);
        
        if (completedOrders.length > 0) {
            stats.value.avgOrderValue = stats.value.totalRevenue / completedOrders.length;
        }

        // --- Latest Lists ---
        latestOrders.value = [...allOrders.value]
            .sort((a, b) => new Date(b.createdAt || b.created_at) - new Date(a.createdAt || a.created_at))
            .slice(0, 5);
        
        newMembers.value = users
            .filter(u => u.role_id !== 1 && u.role_id !== 12)
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
            .slice(0, 5);

        latestReviews.value = reviews
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
            .slice(0, 5);
            
        latestComments.value = comments
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
            .slice(0, 5);

        // --- Chart Data ---
        const counts = {};
        Object.keys(STATUS_CONFIG).forEach(k => counts[k] = 0);
        allOrders.value.forEach(o => {
            const status = o.status || 'other';
            if (counts[status] !== undefined) counts[status]++;
        });
        orderStatusCounts.value = counts;

        processRevenueData(completedOrders);

        await loadChartJs();
        if (activeDashboardTab.value === 'overview') {
            renderStatusChart();
        }
        
    } catch (error) {
        console.error("Dashboard Error:", error);
    } finally {
        isLoading.value = false;
    }
};

const processRevenueData = (orders) => {
    monthlyRevenue.value = new Array(12).fill(0);
    yearlyRevenue.value = {};
    const currentYear = new Date().getFullYear();

    orders.forEach(order => {
        const dateStr = order.createdAt || order.created_at;
        if (!dateStr) return;

        const date = new Date(dateStr);
        const month = date.getMonth(); 
        const year = date.getFullYear();
        const amount = Number(order.totalAmount || order.total_amount || 0);

        if (!yearlyRevenue.value[year]) yearlyRevenue.value[year] = 0;
        yearlyRevenue.value[year] += amount;

        if (year === currentYear) {
            monthlyRevenue.value[month] += amount;
        }
    });

    const maxVal = Math.max(...monthlyRevenue.value);
    const maxIdx = monthlyRevenue.value.indexOf(maxVal);
    stats.value.bestMonth = maxVal > 0 ? `Tháng ${maxIdx + 1}` : '-';
};

// ==========================================
// INVENTORY LOGIC (UPDATED)
// ==========================================

const checkPrice = (price) => {
    const r = filters.value.priceRange;
    if (!r) return true;
    if (r === 'under5') return price < 5000000;
    if (r === '5to10') return price >= 5000000 && price <= 10000000;
    if (r === '10to20') return price >= 10000000 && price <= 20000000;
    if (r === 'over20') return price > 20000000;
    return true;
};

// [NEW] Computed: Đếm tổng số lượng Variants đang ở mức cảnh báo (Toàn cục)
const lowStockCount = computed(() => {
    const threshold = Number(inventoryThreshold.value) || 0;
    let count = 0;
    
    // Duyệt qua tất cả sản phẩm đang active
    allProducts.value.forEach(p => {
        if (p.status === 'active' && p.variants) {
            // Đếm từng variant có stock <= ngưỡng
            p.variants.forEach(v => {
                if (Number(v.stock) <= threshold) {
                    count++;
                }
            });
        }
    });
    
    return count;
});

// 1. Tính toán danh sách đầy đủ sau khi lọc
const filteredInventory = computed(() => {
    const threshold = Number(inventoryThreshold.value) || 0;
    let products = allProducts.value.filter(p => p.status === 'active');

    if (filters.value.searchName) {
        const lowerName = filters.value.searchName.toLowerCase();
        products = products.filter(p => p.name.toLowerCase().includes(lowerName));
    }
    if (filters.value.category) products = products.filter(p => p.category_id == filters.value.category);
    if (filters.value.brand) products = products.filter(p => p.brand_id == filters.value.brand);

    let result = [];

    if (inventoryViewMode.value === 'variant') {
        products.forEach(p => {
            const variants = p.variants || [];
            variants.forEach(v => {
                if (!checkPrice(v.price)) return;

                const item = {
                    type: 'variant',
                    id: v.id,
                    productId: p.id,
                    productName: p.name,
                    image: v.image || p.thumbnail_url, 
                    variantName: v.attributes_text || 'Mặc định', 
                    sku: `VAR-${v.id}`, 
                    price: v.price,
                    stock: v.stock,
                    isLowStock: v.stock <= threshold,
                    isOutOfStock: v.stock === 0,
                    originalVariant: v 
                };

                if (filters.value.onlyLowStock && !item.isLowStock) return;
                result.push(item);
            });
        });
    } else {
        products.forEach(p => {
            const variants = p.variants || [];
            const repPrice = variants.length > 0 ? variants[0].price : 0;
            if (!checkPrice(repPrice)) return;

            const totalStock = variants.reduce((sum, v) => sum + Number(v.stock), 0);
            
            const item = {
                type: 'product',
                id: p.id,
                productName: p.name,
                image: p.thumbnail_url,
                sku: `PRD-${p.id}`,
                price: repPrice,
                stock: totalStock,
                variantCount: variants.length,
                isLowStock: totalStock <= threshold,
                isOutOfStock: totalStock === 0,
            };

            if (filters.value.onlyLowStock && !item.isLowStock) return;
            result.push(item);
        });
    }
    return result.sort((a, b) => a.stock - b.stock);
});

// 2. Tính toán phân trang
const totalPages = computed(() => Math.ceil(filteredInventory.value.length / itemsPerPage.value));

const paginatedInventory = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredInventory.value.slice(start, end);
});

// Watch filters để reset về trang 1
watch(filters, () => { currentPage.value = 1; }, { deep: true });
watch(inventoryViewMode, () => { currentPage.value = 1; });

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// [UPDATED] Hàm cộng thêm số lượng thay vì set cứng
const addStock = async (item) => {
    if (item.type !== 'variant') return;
    
    // Lấy số lượng nhập thêm từ biến tạm stockAdditions
    const amountToAdd = parseInt(stockAdditions.value[item.id]);
    
    // Validate: Phải là số dương
    if (!amountToAdd || isNaN(amountToAdd) || amountToAdd <= 0) return;

    // Tính tổng mới = Tồn kho hiện tại + Số lượng thêm
    const currentStock = parseInt(item.originalVariant.stock);
    const newTotalStock = currentStock + amountToAdd;

    isUpdatingStock.value = item.id;
    try {
        // Gọi API cập nhật với số lượng MỚI TỔNG CỘNG
        await apiService.put(`/admin/inventory/variants/${item.id}/stock`, { stock: newTotalStock });
        
        // Cập nhật giao diện ngay lập tức
        item.originalVariant.stock = newTotalStock; 
        
        // Reset ô nhập liệu
        stockAdditions.value[item.id] = '';
        
        console.log(`Updated variant ${item.id}: ${currentStock} + ${amountToAdd} = ${newTotalStock}`);
    } catch (e) {
        console.error(e);
        // Có thể thêm Toast báo lỗi ở đây nếu cần
    } finally {
        isUpdatingStock.value = null;
    }
};

const clearFilters = () => {
    filters.value = { searchName: '', category: '', brand: '', priceRange: '', onlyLowStock: false };
    inventoryThreshold.value = 10;
    currentPage.value = 1;
};

// Modal Handling
const openDetail = (item) => {
    selectedItemDetail.value = item;
    showDetailModal.value = true;
};

const closeDetail = () => {
    showDetailModal.value = false;
    selectedItemDetail.value = null;
};

// ==========================================
// CHARTS & HELPERS
// ==========================================
const loadChartJs = () => { return new Promise((resolve, reject) => { if (window.Chart) return resolve(); const script = document.createElement('script'); script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'; script.onload = () => { resolve(); }; script.onerror = (e) => { reject(e); }; document.head.appendChild(script); }); };
const renderStatusChart = () => { if (!chartCanvas.value || !window.Chart) return; if (chartInstance.value) chartInstance.value.destroy(); chartConfigKeys.value = Object.keys(STATUS_CONFIG); const labels = chartConfigKeys.value.map(key => STATUS_CONFIG[key].label); const data = chartConfigKeys.value.map(key => orderStatusCounts.value[key]); const colors = chartConfigKeys.value.map(key => STATUS_CONFIG[key].color); const ctx = chartCanvas.value.getContext('2d'); chartInstance.value = markRaw(new window.Chart(ctx, { type: 'doughnut', data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 0, hoverOffset: 15, cutout: '65%' }] }, options: { responsive: true, maintainAspectRatio: false, layout: { padding: 10 }, plugins: { legend: { display: false }, tooltip: { enabled: true, callbacks: { label: function(context) { const value = context.raw; const total = context.chart._metasets[context.datasetIndex].total; const percentage = total > 0 ? Math.round((value / total) * 100) + '%' : '0%'; return ` ${context.label}: ${value} đơn (${percentage})`; } } } }, interaction: { mode: 'nearest', intersect: true }, } })); };
const renderRevenueCharts = () => { if (!revenueBarCanvas.value || !revenueLineCanvas.value || !window.Chart) return; if (revenueBarInstance.value) revenueBarInstance.value.destroy(); if (revenueLineInstance.value) revenueLineInstance.value.destroy(); const ctxBar = revenueBarCanvas.value.getContext('2d'); revenueBarInstance.value = markRaw(new window.Chart(ctxBar, { type: 'bar', data: { labels: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'], datasets: [{ label: 'Doanh thu', data: monthlyRevenue.value, backgroundColor: '#009981', borderRadius: 4, barPercentage: 0.6 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: (v) => formatCurrency(v, true) }, grid: { color: '#f0f0f0' }, border: { display: false } }, x: { grid: { display: false }, border: { display: false } } } } })); const years = Object.keys(yearlyRevenue.value).sort(); const yearlyData = years.map(y => yearlyRevenue.value[y]); const ctxLine = revenueLineCanvas.value.getContext('2d'); revenueLineInstance.value = markRaw(new window.Chart(ctxLine, { type: 'line', data: { labels: years.length > 0 ? years : [new Date().getFullYear()], datasets: [{ label: 'Tổng doanh thu', data: yearlyData.length > 0 ? yearlyData : [0], borderColor: '#0dcaf0', backgroundColor: 'rgba(13, 202, 240, 0.1)', fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: (v) => formatCurrency(v, true) }, grid: { color: '#f0f0f0' }, border: { display: false } }, x: { grid: { display: false }, border: { display: false } } } } })); };
const onLegendHover = (key) => { if (!chartInstance.value) return; hoveredLegendKey.value = key; const index = chartConfigKeys.value.indexOf(key); if (index !== -1) { try { chartInstance.value.setActiveElements([{ datasetIndex: 0, index: index }]); chartInstance.value.update(); } catch (e) {} } };
const onLegendLeave = () => { if (!chartInstance.value) return; hoveredLegendKey.value = null; try { chartInstance.value.setActiveElements([]); chartInstance.value.update(); } catch (e) {} };
watch(activeDashboardTab, async (newTab) => { await nextTick(); if (newTab === 'overview') setTimeout(renderStatusChart, 100); else if (newTab === 'revenue') setTimeout(renderRevenueCharts, 100); });

// [UPDATED] Hàm xử lý ảnh thông minh hơn - Loại bỏ /api ở cuối domain nếu có
const getAvatar = (url, name) => {
    if (!url) return `https://placehold.co/40x40/009981/ffffff?text=${name ? name.charAt(0).toUpperCase() : 'U'}`;
    
    // Nếu URL đã là tuyệt đối (có http) thì dùng luôn
    if (url.startsWith('http')) return url;
    
    // Nếu URL là tương đối (ví dụ: /uploads/...) -> Nối với IMAGE_BASE_URL (Đã lọc bỏ /api)
    // Đảm bảo không bị double slash hoặc thiếu slash
    const cleanUrl = url.startsWith('/') ? url : `/${url}`;
    
    // Loại bỏ dấu / cuối của BASE nếu có
    const cleanBase = IMAGE_BASE_URL.endsWith('/') ? IMAGE_BASE_URL.slice(0, -1) : IMAGE_BASE_URL;
    
    return `${cleanBase}${cleanUrl}`;
};

const formatCurrency = (v, compact = false) => { if (!v && v !== 0) return '0 ₫'; if (compact) return new Intl.NumberFormat('vi-VN', { maximumSignificantDigits: 3 }).format(v); return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v); };
const formatDate = (d) => { if (!d) return 'N/A'; return new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }); };
const getStatusLabel = (s) => STATUS_CONFIG[s]?.label || s;
const getStatusClass = (s) => { const map = { 'pending': 'bg-warning text-dark', 'approved': 'bg-info text-dark', 'shipping': 'bg-primary', 'completed': 'bg-success', 'cancelled': 'bg-danger', 'returning': 'bg-secondary', 'returned': 'bg-dark' }; return map[s] || 'bg-light text-dark'; };
const getGeneralStatusClass = (s) => GENERAL_STATUS_CONFIG[s]?.color || 'bg-light text-dark border';
const getGeneralStatusLabel = (s) => GENERAL_STATUS_CONFIG[s]?.label || s || 'Mặc định';
const renderStars = (r) => '★'.repeat(Math.floor(r || 5)) + '☆'.repeat(5 - Math.floor(r || 5));

onMounted(() => { fetchData(); });
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0 text-brand fw-bold">Dashboard</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item active">Tổng quan</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- 1. STATS CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100 stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon-wrapper bg-primary-subtle text-primary rounded-circle me-3"><i class="bi bi-currency-dollar fs-4"></i></div>
                            <div><p class="text-muted mb-0 small text-uppercase fw-bold">Doanh thu</p><h5 class="mb-0 fw-bold text-dark">{{ formatCurrency(stats.totalRevenue) }}</h5></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100 stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon-wrapper bg-info-subtle text-info rounded-circle me-3"><i class="bi bi-bag-check fs-4"></i></div>
                            <div><p class="text-muted mb-0 small text-uppercase fw-bold">Đơn hàng</p><h5 class="mb-0 fw-bold text-dark">{{ stats.totalOrders }}</h5></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100 stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon-wrapper bg-warning-subtle text-warning rounded-circle me-3"><i class="bi bi-people fs-4"></i></div>
                            <div><p class="text-muted mb-0 small text-uppercase fw-bold">Khách hàng</p><h5 class="mb-0 fw-bold text-dark">{{ stats.totalCustomers }}</h5></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100 stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon-wrapper bg-success-subtle text-success rounded-circle me-3"><i class="bi bi-box-seam fs-4"></i></div>
                            <div><p class="text-muted mb-0 small text-uppercase fw-bold">Sản phẩm</p><h5 class="mb-0 fw-bold text-dark">{{ stats.totalProducts }}</h5></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. TABS NAVIGATION (UPDATED WITH BADGE) -->
            <ul class="nav nav-pills mb-4 bg-white p-2 rounded shadow-sm d-inline-flex">
                <li class="nav-item"><a class="nav-link cursor-pointer fw-bold px-4 py-2" :class="{ active: activeDashboardTab === 'overview' }" @click="activeDashboardTab = 'overview'"><i class="bi bi-grid-1x2-fill me-2"></i>Tổng quan</a></li>
                <li class="nav-item"><a class="nav-link cursor-pointer fw-bold px-4 py-2" :class="{ active: activeDashboardTab === 'revenue' }" @click="activeDashboardTab = 'revenue'"><i class="bi bi-graph-up-arrow me-2"></i>Doanh thu</a></li>
                <li class="nav-item">
                    <a class="nav-link cursor-pointer fw-bold px-4 py-2 d-flex align-items-center" :class="{ active: activeDashboardTab === 'inventory' }" @click="activeDashboardTab = 'inventory'">
                        <i class="bi bi-box-seam me-2"></i>Quản lý kho
                        <!-- BADGE THÔNG BÁO TỒN KHO -->
                        <span v-if="lowStockCount > 0" class="badge rounded-pill bg-warning ms-2 shadow-sm animate__animated animate__pulse animate__infinite" style="font-size: 0.75rem;">
                            {{ lowStockCount > 99 ? '99+' : lowStockCount }}
                        </span>
                    </a>
                </li>
            </ul>

            <!-- TAB 1: OVERVIEW -->
            <div v-if="activeDashboardTab === 'overview'" class="fade-in">
                <!-- (Giữ nguyên nội dung tab Overview) -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                                <h5 class="card-title fw-bold text-brand mb-0">Đơn Hàng Mới Nhất</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light text-secondary small text-uppercase"><tr><th class="ps-3">Mã ĐH</th><th>Khách hàng</th><th>Trạng thái</th><th class="text-end pe-3">Tổng tiền</th></tr></thead>
                                    <tbody>
                                        <tr v-if="isLoading">
                                            <td colspan="4" class="text-center py-5">
                                                <div class="spinner-border text-brand" role="status"><span class="visually-hidden">Loading...</span></div>
                                            </td>
                                        </tr>
                                        <tr v-else-if="latestOrders.length === 0"><td colspan="4" class="text-center py-4 text-muted">Chưa có đơn hàng nào.</td></tr>
                                        <tr v-else v-for="order in latestOrders" :key="order.id">
                                            <td class="ps-3 fw-bold text-primary">#{{ order.id }}</td>
                                            <td><div class="fw-medium text-dark">{{ order.customerName || order.customer_name }}</div><div class="small text-muted">{{ formatDate(order.createdAt || order.created_at) }}</div></td>
                                            <td><span class="badge rounded-pill" :class="getStatusClass(order.status)">{{ getStatusLabel(order.status) }}</span></td>
                                            <td class="text-end pe-3 fw-bold text-danger">{{ formatCurrency(order.totalAmount || order.total_amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer bg-white border-top-0 text-center py-3">
                                <router-link to="/admin/orders" class="btn btn-sm btn-light text-brand fw-bold border px-4 shadow-sm hover-brand">Xem thêm</router-link>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                                <h5 class="card-title fw-bold text-brand mb-0">Tình Trạng Đơn Hàng</h5>
                                <span class="badge bg-light text-dark border">{{ stats.totalOrders }} đơn</span>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <div style="width: 55%; height: 200px; position: relative;"><canvas ref="chartCanvas"></canvas></div>
                                <div class="ms-3 flex-grow-1" style="max-height: 220px; overflow-y: auto;">
                                    <div v-for="(config, key) in STATUS_CONFIG" :key="key" class="d-flex align-items-center justify-content-between py-1 px-2 rounded cursor-pointer transition-opacity mb-1" :class="{ 'opacity-25': hoveredLegendKey && hoveredLegendKey !== key }" @mouseenter="onLegendHover(key)" @mouseleave="onLegendLeave" style="font-size: 0.85rem;"><div class="d-flex align-items-center"><div class="dot rounded-circle me-2" :style="{ backgroundColor: config.color }"></div><span class="text-dark text-truncate" style="max-width: 80px;">{{ config.label }}</span></div><span class="fw-bold text-secondary">{{ orderStatusCounts[key] || 0 }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-bottom py-3"><h5 class="card-title fw-bold text-brand mb-0">Đánh Giá Mới</h5></div>
                            <div class="list-group list-group-flush flex-grow-1 position-relative">
                                <div v-if="isLoading" class="position-absolute w-100 h-100 top-0 start-0 d-flex align-items-center justify-content-center bg-white" style="z-index: 5;"><div class="spinner-border text-brand spinner-border-sm" role="status"></div></div>
                                <div v-else-if="latestReviews.length === 0" class="p-3 text-center text-muted">Trống</div>
                                <div v-else v-for="review in latestReviews" :key="review.id" class="list-group-item px-3 py-2 border-bottom-0"><div class="d-flex justify-content-between align-items-center mb-1"><div class="text-truncate flex-grow-1" style="max-width: 55%;"><small class="fw-bold d-block text-truncate">{{ review.product?.name }}</small></div><div class="d-flex align-items-center"><span class="badge rounded-pill me-2 px-2" :class="getGeneralStatusClass(review.status || 'pending')" style="font-size: 0.65rem;">{{ getGeneralStatusLabel(review.status || 'pending') }}</span><span class="text-warning small" style="letter-spacing: -1px;">{{ renderStars(review.rating) }}</span></div></div><p class="mb-0 small text-muted text-truncate fst-italic">"{{ review.content }}"</p></div>
                            </div>
                            <div class="card-footer bg-white border-top-0 text-center py-3"><router-link to="/admin/reviews" class="btn btn-sm btn-light text-brand fw-bold border px-4 shadow-sm hover-brand">Xem thêm</router-link></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-bottom py-3"><h5 class="card-title fw-bold text-brand mb-0">Bình Luận Mới</h5></div>
                            <div class="list-group list-group-flush flex-grow-1 position-relative">
                                <div v-if="isLoading" class="position-absolute w-100 h-100 top-0 start-0 d-flex align-items-center justify-content-center bg-white" style="z-index: 5;"><div class="spinner-border text-brand spinner-border-sm" role="status"></div></div>
                                <div v-else-if="latestComments.length === 0" class="p-3 text-center text-muted">Trống</div>
                                <div v-else v-for="cmt in latestComments" :key="cmt.id" class="list-group-item px-3 py-2 border-bottom-0"><div class="d-flex align-items-center justify-content-between mb-1"><div class="d-flex align-items-center"><img :src="getAvatar(cmt.user?.avatar, cmt.user?.username)" class="rounded-circle me-2" width="20"><span class="fw-bold small text-dark">{{ cmt.user?.username || 'Ẩn danh' }}</span></div><span class="badge rounded-pill px-2" :class="getGeneralStatusClass(cmt.status || 'approved')" style="font-size: 0.65rem;">{{ getGeneralStatusLabel(cmt.status || 'approved') }}</span></div><p class="mb-0 small text-muted text-truncate">"{{ cmt.content }}"</p></div>
                            </div>
                            <div class="card-footer bg-white border-top-0 text-center py-3"><router-link to="/admin/comments" class="btn btn-sm btn-light text-brand fw-bold border px-4 shadow-sm hover-brand">Xem thêm</router-link></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-bottom py-3"><h5 class="card-title fw-bold text-brand mb-0">Khách Hàng Mới</h5></div>
                            <div class="list-group list-group-flush flex-grow-1 position-relative">
                                <div v-if="isLoading" class="position-absolute w-100 h-100 top-0 start-0 d-flex align-items-center justify-content-center bg-white" style="z-index: 5;"><div class="spinner-border text-brand spinner-border-sm" role="status"></div></div>
                                <div v-else-if="newMembers.length === 0" class="p-3 text-center text-muted">Trống</div>
                                <div v-else v-for="user in newMembers" :key="user.id" class="list-group-item px-3 py-3 d-flex align-items-center"><img :src="getAvatar(user.avatar_url, user.fullname)" class="rounded-circle me-3 border shadow-sm" width="36" height="36" style="object-fit: cover;"><div class="flex-grow-1 min-width-0"><div class="fw-bold text-dark text-truncate small">{{ user.fullname }}</div><div class="small text-muted text-truncate" style="font-size: 0.75rem;">{{ user.email }}</div></div><div class="text-end ms-2"><span class="badge rounded-pill mb-1 d-block ms-auto" :class="getGeneralStatusClass(user.status || 'active')" style="width: fit-content; font-size: 0.65rem;">{{ getGeneralStatusLabel(user.status || 'active') }}</span><div class="small text-muted" style="font-size: 0.7rem;">{{ formatDate(user.created_at) }}</div></div></div>
                            </div>
                            <div class="card-footer bg-white border-top-0 text-center py-3"><router-link to="/admin/userAccount" class="btn btn-sm btn-light text-brand fw-bold border px-4 shadow-sm hover-brand">Xem thêm</router-link></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: REVENUE -->
            <div v-if="activeDashboardTab === 'revenue'" class="row g-4 fade-in">
                <!-- (Giữ nguyên nội dung tab Revenue) -->
                <div class="col-12"><div class="row g-3"><div class="col-md-6"><div class="card bg-light border-0 p-3 h-100"><h6 class="text-muted text-uppercase small fw-bold">Doanh thu năm nay</h6><h3 class="text-brand fw-bold mb-0">{{ formatCurrency(stats.totalRevenue) }}</h3></div></div><div class="col-md-3"><div class="card bg-light border-0 p-3 h-100"><h6 class="text-muted text-uppercase small fw-bold">TB Đơn hàng</h6><h4 class="text-dark fw-bold mb-0">{{ formatCurrency(stats.avgOrderValue) }}</h4></div></div><div class="col-md-3"><div class="card bg-light border-0 p-3 h-100"><h6 class="text-muted text-uppercase small fw-bold">Tháng cao điểm</h6><h4 class="text-success fw-bold mb-0">{{ stats.bestMonth }}</h4></div></div></div></div>
                <div class="col-lg-8"><div class="card shadow-sm border-0 h-100"><div class="card-header bg-white border-bottom py-3"><h5 class="card-title fw-bold text-brand mb-0">Doanh Thu Theo Tháng</h5></div><div class="card-body"><div style="height: 350px;"><canvas ref="revenueBarCanvas"></canvas></div></div></div></div>
                <div class="col-lg-4"><div class="card shadow-sm border-0 h-100"><div class="card-header bg-white border-bottom py-3"><h5 class="card-title fw-bold text-brand mb-0">Tăng Trưởng</h5></div><div class="card-body"><div style="height: 350px;"><canvas ref="revenueLineCanvas"></canvas></div></div></div></div>
            </div>

            <!-- TAB 3: INVENTORY (NÂNG CẤP GIAO DIỆN & LOGIC) -->
            <div v-if="activeDashboardTab === 'inventory'" class="fade-in">
                <!-- Toolbar Lọc & Tìm kiếm -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body py-4">
                        <!-- Hàng 1: Bộ lọc chính -->
                        <div class="row g-3">
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label fw-bold small text-secondary">Tên sản phẩm</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                    <input type="text" v-model="filters.searchName" class="form-control border-start-0 ps-0 shadow-none fs-6" placeholder="Nhập tên sản phẩm...">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-3 col-6">
                                <label class="form-label fw-bold small text-secondary">Danh mục</label>
                                <select v-model="filters.category" class="form-select form-select-lg fs-6 shadow-none">
                                    <option value="">Tất cả</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-3 col-6">
                                <label class="form-label fw-bold small text-secondary">Thương hiệu</label>
                                <select v-model="filters.brand" class="form-select form-select-lg fs-6 shadow-none">
                                    <option value="">Tất cả</option>
                                    <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label fw-bold small text-secondary">Khoảng giá</label>
                                <select v-model="filters.priceRange" class="form-select form-select-lg fs-6 shadow-none">
                                    <option value="">Tất cả</option>
                                    <option value="under5">Dưới 5 triệu</option>
                                    <option value="5to10">5 - 10 triệu</option>
                                    <option value="10to20">10 - 20 triệu</option>
                                    <option value="over20">Trên 20 triệu</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6 d-flex align-items-end">
                                <button @click="clearFilters" class="btn btn-lg btn-outline-secondary w-100 fs-6"><i class="bi bi-arrow-counterclockwise me-2"></i>Đặt lại</button>
                            </div>
                        </div>

                        <hr class="text-secondary opacity-10 my-4">

                        <!-- Hàng 2: Cấu hình hiển thị -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <!-- Nút gạt Sắp hết (Cố định, không chạy lung tung) -->
                                <div class="form-check form-switch custom-switch" title="Chỉ hiện sản phẩm sắp hết hàng">
                                    <input class="form-check-input cursor-pointer" type="checkbox" id="lowStockOnly" v-model="filters.onlyLowStock" style="width: 3em; height: 1.5em;">
                                    <label class="form-check-label fw-bold ms-2 mt-1 cursor-pointer" for="lowStockOnly">Chỉ hiện sắp hết</label>
                                </div>
                                <div class="d-flex align-items-center gap-2 bg-light rounded px-3 py-2 border">
                                    <label class="fw-bold text-secondary small text-nowrap mb-0">Ngưỡng báo động:</label>
                                    <input type="number" v-model="inventoryThreshold" class="form-control form-control-sm text-center fw-bold text-danger border-0 bg-transparent p-0 fs-6" style="width: 50px;" min="0">
                                </div>
                            </div>
                            
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="invView" id="viewVariant" value="variant" v-model="inventoryViewMode">
                                <label class="btn btn-outline-brand px-4 py-2" :class="{active: inventoryViewMode === 'variant'}" for="viewVariant"><i class="bi bi-list-columns-reverse me-2"></i>Chi tiết biến thể</label>
                                
                                <input type="radio" class="btn-check" name="invView" id="viewProduct" value="product" v-model="inventoryViewMode">
                                <label class="btn btn-outline-brand px-4 py-2" :class="{active: inventoryViewMode === 'product'}" for="viewProduct"><i class="bi bi-box me-2"></i>Tổng hợp sản phẩm</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card shadow-sm border-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-secondary small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 80px;">Ảnh</th>
                                    <th class="py-3">Thông tin sản phẩm</th>
                                    <th v-if="inventoryViewMode === 'variant'" class="py-3">Mã SKU</th>
                                    <th class="text-end py-3">Giá bán</th>
                                    <th class="text-center py-3" style="width: 250px;">Tồn kho</th>
                                    <th class="text-center py-3">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="isLoading">
                                    <td colspan="6" class="text-center py-5"><div class="spinner-border text-brand" role="status"></div></td>
                                </tr>
                                <tr v-else-if="filteredInventory.length === 0">
                                    <td colspan="6" class="text-center py-5 text-muted">Không tìm thấy sản phẩm nào phù hợp.</td>
                                </tr>
                                <tr v-else v-for="item in paginatedInventory" :key="item.id + item.type" class="cursor-pointer" @click="openDetail(item)">
                                    <td class="ps-4 py-3">
                                        <div class="position-relative">
                                            <img :src="getAvatar(item.image, item.productName)" class="rounded border shadow-sm" width="50" height="50" style="object-fit: cover;">
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <div class="fw-bold text-dark fs-6 text-truncate" style="max-width: 300px;">{{ item.productName }}</div>
                                        <div v-if="item.type === 'variant'" class="small text-brand fst-italic mt-1">
                                            <i class="bi bi-caret-right-fill me-1"></i> {{ item.variantName }}
                                        </div>
                                        <div v-else class="small text-muted mt-1"><i class="bi bi-layers me-1"></i> {{ item.variantCount }} biến thể</div>
                                    </td>
                                    <td v-if="inventoryViewMode === 'variant'" class="py-3">
                                        <span class="badge bg-light text-secondary border font-monospace px-2 py-1">{{ item.sku }}</span>
                                    </td>
                                    <td class="text-end fw-medium py-3 fs-6">{{ formatCurrency(item.price) }}</td>
                                    
                                    <!-- Stock Input (Cập nhật kiểu cộng dồn) -->
                                    <td class="text-center py-3" @click.stop>
                                        <div v-if="item.type === 'variant'" class="d-flex align-items-center justify-content-center gap-2">
                                            <!-- Số hiện tại -->
                                            <div class="fw-bold fs-5" style="min-width: 40px;" :class="{'text-danger': item.isOutOfStock, 'text-warning': item.isLowStock}">{{ item.stock }}</div>
                                            
                                            <!-- Dấu cộng -->
                                            <i class="bi bi-plus-lg text-muted small"></i>
                                            
                                            <!-- Ô nhập thêm -->
                                            <input type="number" 
                                                   v-model="stockAdditions[item.id]" 
                                                   class="form-control text-center fw-bold input-stock fs-6" 
                                                   :class="{'border-danger': item.isOutOfStock, 'border-warning': item.isLowStock && !item.isOutOfStock}"
                                                   placeholder="0"
                                                   style="width: 70px;"
                                                   min="0">
                                            
                                            <!-- Nút Save -->
                                            <button @click="addStock(item)" 
                                                    class="btn btn-light text-success border shadow-sm hover-success" 
                                                    :disabled="isUpdatingStock === item.id || !stockAdditions[item.id]">
                                                <div v-if="isUpdatingStock === item.id" class="spinner-border spinner-border-sm" role="status"></div>
                                                <i v-else class="bi bi-check-lg fw-bold"></i>
                                            </button>
                                        </div>
                                        <div v-else class="fw-bold fs-5 mb-0" :class="{'text-danger': item.isOutOfStock, 'text-warning': item.isLowStock}">
                                            {{ item.stock }}
                                        </div>
                                    </td>

                                    <td class="text-center py-3">
                                        <span v-if="item.isOutOfStock" class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">Hết hàng</span>
                                        <span v-else-if="item.isLowStock" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill">Sắp hết</span>
                                        <span v-else class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Sẵn sàng</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Control -->
                    <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Hiển thị {{ paginatedInventory.length }} / {{ filteredInventory.length }} sản phẩm</span>
                        <nav aria-label="Inventory pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                    <button class="page-link border-0" @click="changePage(currentPage - 1)"><i class="bi bi-chevron-left"></i></button>
                                </li>
                                <li class="page-item disabled"><span class="page-link border-0 text-dark fw-bold">Trang {{ currentPage }} / {{ totalPages || 1 }}</span></li>
                                <li class="page-item" :class="{ disabled: currentPage === totalPages || totalPages === 0 }">
                                    <button class="page-link border-0" @click="changePage(currentPage + 1)"><i class="bi bi-chevron-right"></i></button>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CHI TIẾT SẢN PHẨM -->
    <div v-if="showDetailModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-brand">Chi tiết sản phẩm</h5>
                    <button type="button" class="btn-close" @click="closeDetail"></button>
                </div>
                <div class="modal-body text-center pt-4" v-if="selectedItemDetail">
                    <img :src="getAvatar(selectedItemDetail.image, selectedItemDetail.productName)" class="rounded shadow mb-3" style="max-height: 150px; object-fit: contain;">
                    <h5 class="fw-bold mb-1">{{ selectedItemDetail.productName }}</h5>
                    <p class="text-muted mb-3" v-if="selectedItemDetail.type === 'variant'">{{ selectedItemDetail.variantName }}</p>
                    <p class="text-muted mb-3" v-else>Tổng hợp {{ selectedItemDetail.variantCount }} biến thể</p>
                    
                    <div class="row g-2 mt-3">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded border">
                                <div class="small text-muted text-uppercase">Giá bán</div>
                                <div class="fw-bold text-danger">{{ formatCurrency(selectedItemDetail.price) }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded border">
                                <div class="small text-muted text-uppercase">Tồn kho</div>
                                <div class="fw-bold text-dark">{{ selectedItemDetail.stock }}</div>
                            </div>
                        </div>
                        <div class="col-12" v-if="selectedItemDetail.sku">
                            <div class="p-2 bg-light rounded border">
                                <div class="small text-muted text-uppercase">Mã SKU / ID</div>
                                <div class="fw-bold font-monospace">{{ selectedItemDetail.sku }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 justify-content-center pb-4">
                    <button type="button" class="btn btn-secondary px-4" @click="closeDetail">Đóng</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* COLORS */
.text-brand { color: #009981 !important; }
.hover-brand:hover { background-color: #009981 !important; color: white !important; border-color: #009981 !important; }
.btn-outline-brand { color: #009981; border-color: #009981; }
.btn-outline-brand:hover, .btn-outline-brand.active { background-color: #009981; color: white; }
.hover-success:hover { background-color: #198754 !important; color: white !important; border-color: #198754 !important; }

/* NAV PILLS */
.nav-pills .nav-link { color: #6c757d; transition: all 0.2s; }
.nav-pills .nav-link:hover { color: #009981; background-color: rgba(0, 153, 129, 0.1); }
.nav-pills .nav-link.active { background-color: #009981 !important; color: white !important; }

/* INPUTS & FILTERS */
.form-control:focus, .form-select:focus { border-color: #009981; box-shadow: 0 0 0 0.25rem rgba(0, 153, 129, 0.25); }
.input-group-text { background-color: #fff; border-right: none; }
.input-stock:focus { background-color: #f8f9fa; }
.form-check-input:checked { background-color: #009981; border-color: #009981; }

/* CUSTOM SWITCH SIZE */
.custom-switch .form-check-input { width: 3em; height: 1.5em; }

/* CARDS */
.stats-card { transition: transform 0.2s; }
.stats-card:hover { transform: translateY(-3px); }
.icon-wrapper { width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; }

/* LEGEND & TRANSITIONS */
.transition-opacity { transition: opacity 0.3s ease; }
.opacity-25 { opacity: 0.3; }
.dot { width: 10px; height: 10px; display: inline-block; }
.cursor-pointer { cursor: pointer; }
.fade-in { animation: fadeIn 0.4s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* TABLE */
:deep(.table) td { vertical-align: middle; }
.min-width-0 { min-width: 0; }
</style>