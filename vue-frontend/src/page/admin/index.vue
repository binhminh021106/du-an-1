<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';
import apiService from '../../apiService.js';
// import Swal from 'sweetalert2'; // Uncomment nếu cần dùng

// CONFIG & STATE
const isLoading = ref(true);
const activeDashboardTab = ref('overview'); // 'overview' | 'revenue'

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
const latestOrders = ref([]);
const latestReviews = ref([]);
const latestComments = ref([]);
const newMembers = ref([]);
const orderStatusCounts = ref({});

// Revenue Data
const monthlyRevenue = ref(new Array(12).fill(0));
const yearlyRevenue = ref({});

// Chart Refs (Pie Chart)
const chartInstance = ref(null);
const chartCanvas = ref(null);
const chartConfigKeys = ref([]);
const hoveredLegendKey = ref(null);

// Chart Refs (Revenue)
const revenueBarInstance = ref(null);
const revenueBarCanvas = ref(null);
const revenueLineInstance = ref(null);
const revenueLineCanvas = ref(null);

// Helper Constants
const BACKEND_URL = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');

const STATUS_CONFIG = {
    'pending': { label: 'Chờ xử lý', color: '#ffc107', icon: 'bi-hourglass-split' },
    'approved': { label: 'Đã duyệt', color: '#0dcaf0', icon: 'bi-check2-circle' },
    'shipping': { label: 'Đang giao', color: '#009981', icon: 'bi-truck' },
    'completed': { label: 'Hoàn thành', color: '#198754', icon: 'bi-check-circle-fill' },
    'cancelled': { label: 'Đã hủy', color: '#dc3545', icon: 'bi-x-circle' },
    'returning': { label: 'Đang hoàn', color: '#6c757d', icon: 'bi-arrow-counterclockwise' },
    'returned': { label: 'Đã hoàn', color: '#212529', icon: 'bi-box-seam' }
};

// DATA FETCHING
const fetchData = async () => {
    isLoading.value = true;
    try {
        const [ordersRes, usersRes, productsRes, reviewsRes, commentsRes] = await Promise.all([
            apiService.get('/admin/orders'),
            apiService.get('/admin/users'),
            apiService.get('/admin/products'),
            apiService.get('/admin/reviews'),
            apiService.get('/admin/comments')
        ]);

        allOrders.value = ordersRes.data || [];
        const users = usersRes.data || [];
        const products = productsRes.data || [];
        const reviews = reviewsRes.data || [];
        const comments = commentsRes.data || [];

        // 1. Calc Basic Stats
        stats.value.totalOrders = allOrders.value.length;
        stats.value.totalCustomers = users.filter(u => u.role_id !== 11 && u.role_id !== 12).length;
        stats.value.totalProducts = products.length;

        const completedOrders = allOrders.value.filter(o => o.status === 'completed');
        
        // --- FIX BUG NAN ---
        // Sử dụng total_amount (DB) hoặc totalAmount (nếu API trả về camelCase)
        stats.value.totalRevenue = completedOrders.reduce((sum, o) => {
            const amount = Number(o.total_amount || o.totalAmount || 0);
            return sum + amount;
        }, 0);

        if (completedOrders.length > 0) {
            stats.value.avgOrderValue = stats.value.totalRevenue / completedOrders.length;
        }

        // 2. Latest Lists
        // FIX: Sort date an toàn cho cả created_at và createdAt
        latestOrders.value = [...allOrders.value]
            .sort((a, b) => new Date(b.created_at || b.createdAt) - new Date(a.created_at || a.createdAt))
            .slice(0, 5);

        newMembers.value = users
            .filter(u => u.role_id !== 11 && u.role_id !== 12)
            .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
            .slice(0, 5);

        latestReviews.value = reviews.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).slice(0, 5);
        latestComments.value = comments.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).slice(0, 5);

        // 3. Pie Chart Data
        const counts = {};
        Object.keys(STATUS_CONFIG).forEach(k => counts[k] = 0);
        allOrders.value.forEach(o => {
            const status = o.status || 'other';
            if (counts[status] !== undefined) counts[status]++;
        });
        orderStatusCounts.value = counts;

        // 4. Process Revenue Data
        processRevenueData(completedOrders);

        // Initial Load Library
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
        // --- FIX BUG DATE & AMOUNT ---
        const dateString = order.created_at || order.createdAt;
        if (!dateString) return;

        const date = new Date(dateString);
        const month = date.getMonth();
        const year = date.getFullYear();
        const amount = Number(order.total_amount || order.totalAmount || 0);

        if (!yearlyRevenue.value[year]) yearlyRevenue.value[year] = 0;
        yearlyRevenue.value[year] += amount;

        if (year === currentYear) {
            monthlyRevenue.value[month] += amount;
        }
    });

    const maxVal = Math.max(...monthlyRevenue.value);
    const maxIdx = monthlyRevenue.value.indexOf(maxVal);
    stats.value.bestMonth = maxVal > 0 ? `Tháng ${maxIdx + 1}` : 'Chưa có';
};

// CHARTS
const loadChartJs = () => {
    return new Promise((resolve) => {
        if (window.Chart) return resolve();
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = resolve;
        document.head.appendChild(script);
    });
};

// --- STATUS PIE CHART ---
const renderStatusChart = () => {
    if (!chartCanvas.value) return;
    if (chartInstance.value) chartInstance.value.destroy();

    chartConfigKeys.value = Object.keys(STATUS_CONFIG);
    const labels = chartConfigKeys.value.map(key => STATUS_CONFIG[key].label);
    const data = chartConfigKeys.value.map(key => orderStatusCounts.value[key]);
    const colors = chartConfigKeys.value.map(key => STATUS_CONFIG[key].color);

    const ctx = chartCanvas.value.getContext('2d');
    chartInstance.value = new window.Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 0,
                hoverOffset: 30,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: 20 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(0,0,0,0.9)',
                    padding: 12,
                    callbacks: {
                        label: function (context) {
                            const value = context.raw;
                            const total = context.chart._metasets[context.datasetIndex].total;
                            const percentage = total > 0 ? Math.round((value / total) * 100) + '%' : '0%';
                            return ` ${context.label}: ${value} (${percentage})`;
                        }
                    }
                }
            },
            interaction: { mode: 'nearest', intersect: true },
            hover: { mode: 'nearest', intersect: true }
        }
    });
};

// --- REVENUE CHARTS ---
const renderRevenueCharts = () => {
    if (!revenueBarCanvas.value || !revenueLineCanvas.value) return;
    if (revenueBarInstance.value) revenueBarInstance.value.destroy();
    if (revenueLineInstance.value) revenueLineInstance.value.destroy();

    const ctxBar = revenueBarCanvas.value.getContext('2d');
    revenueBarInstance.value = new window.Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
            datasets: [{ label: 'Doanh thu', data: monthlyRevenue.value, backgroundColor: '#009981', borderRadius: 4 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { ticks: { callback: (value) => formatCurrency(value) } }
            }
        }
    });

    const years = Object.keys(yearlyRevenue.value).sort();
    const yearlyData = years.map(y => yearlyRevenue.value[y]);
    const ctxLine = revenueLineCanvas.value.getContext('2d');
    revenueLineInstance.value = new window.Chart(ctxLine, {
        type: 'line',
        data: {
            labels: years,
            datasets: [{ label: 'Tổng doanh thu', data: yearlyData, borderColor: '#0dcaf0', backgroundColor: 'rgba(13, 202, 240, 0.2)', fill: true, tension: 0.3 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { ticks: { callback: (value) => formatCurrency(value) } }
            }
        }
    });
};

// --- INTERACTIONS ---
const onLegendHover = (key) => {
    if (!chartInstance.value) return;
    hoveredLegendKey.value = key;
    const index = chartConfigKeys.value.indexOf(key);
    if (index !== -1) {
        chartInstance.value.setActiveElements([{ datasetIndex: 0, index: index }]);
        chartInstance.value.update();
    }
};

const onLegendLeave = () => {
    if (!chartInstance.value) return;
    hoveredLegendKey.value = null;
    chartInstance.value.setActiveElements([]);
    chartInstance.value.update();
};

watch(activeDashboardTab, async (newTab) => {
    await nextTick();
    if (newTab === 'overview') renderStatusChart();
    else if (newTab === 'revenue') renderRevenueCharts();
});

// HELPERS
// FIX: Hàm formatCurrency an toàn hơn, tránh NaN
const formatCurrency = (v) => {
    if (v === undefined || v === null || isNaN(v)) return '0 ₫';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }) : 'N/A';
const getStatusLabel = (s) => STATUS_CONFIG[s]?.label || s;
const getStatusClass = (s) => {
    const map = { 'pending': 'bg-warning text-dark', 'approved': 'bg-info text-dark', 'shipping': 'bg-primary', 'completed': 'bg-success', 'cancelled': 'bg-danger', 'returning': 'bg-secondary', 'returned': 'bg-dark' };
    return map[s] || 'bg-light text-dark';
};
const renderStars = (r) => '★'.repeat(Math.floor(r)) + '☆'.repeat(5 - Math.floor(r));

const getAvatar = (url, name) => {
    if (url && url.startsWith('http')) return url;
    if (url) {
        const path = url.startsWith('/') ? url : `/${url}`;
        return `${BACKEND_URL}${path}`;
    }
    const char = name ? name.charAt(0).toUpperCase() : 'U';
    return `https://placehold.co/40x40/009981/ffffff?text=${char}`;
};

onMounted(() => { fetchData(); });
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0 text-brand fw-bold">Dashboard</h3>
                </div>
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
                            <div class="icon-wrapper bg-primary-subtle text-primary rounded-circle me-3"><i
                                    class="bi bi-currency-dollar fs-4"></i></div>
                            <div>
                                <p class="text-muted mb-0 small text-uppercase fw-bold">Doanh thu</p>
                                <h5 class="mb-0 fw-bold text-dark">{{ formatCurrency(stats.totalRevenue) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100 stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon-wrapper bg-info-subtle text-info rounded-circle me-3"><i
                                    class="bi bi-bag-check fs-4"></i></div>
                            <div>
                                <p class="text-muted mb-0 small text-uppercase fw-bold">Đơn hàng</p>
                                <h5 class="mb-0 fw-bold text-dark">{{ stats.totalOrders }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100 stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon-wrapper bg-warning-subtle text-warning rounded-circle me-3"><i
                                    class="bi bi-people fs-4"></i></div>
                            <div>
                                <p class="text-muted mb-0 small text-uppercase fw-bold">Khách hàng</p>
                                <h5 class="mb-0 fw-bold text-dark">{{ stats.totalCustomers }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card shadow-sm border-0 h-100 stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon-wrapper bg-success-subtle text-success rounded-circle me-3"><i
                                    class="bi bi-box-seam fs-4"></i></div>
                            <div>
                                <p class="text-muted mb-0 small text-uppercase fw-bold">Sản phẩm</p>
                                <h5 class="mb-0 fw-bold text-dark">{{ stats.totalProducts }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. TABS NAVIGATION -->
            <ul class="nav nav-pills mb-4 bg-white p-2 rounded shadow-sm d-inline-flex">
                <li class="nav-item"><a class="nav-link cursor-pointer fw-bold px-4"
                        :class="{ active: activeDashboardTab === 'overview' }"
                        @click="activeDashboardTab = 'overview'"><i class="bi bi-grid-1x2-fill me-2"></i>Tổng quan</a>
                </li>
                <li class="nav-item"><a class="nav-link cursor-pointer fw-bold px-4"
                        :class="{ active: activeDashboardTab === 'revenue' }" @click="activeDashboardTab = 'revenue'"><i
                            class="bi bi-graph-up-arrow me-2"></i>Doanh thu</a></li>
            </ul>

            <!-- TAB 1: OVERVIEW -->
            <div v-if="activeDashboardTab === 'overview'" class="row g-4 fade-in">
                <!-- LEFT COLUMN: Orders, Reviews, Comments -->
                <div class="col-lg-8">
                    <!-- Orders -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div
                            class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h5 class="card-title fw-bold text-brand mb-0">Đơn Hàng Mới Nhất</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-secondary small text-uppercase">
                                    <tr>
                                        <th class="ps-3">Mã ĐH</th>
                                        <th>Khách hàng</th>
                                        <th>Trạng thái</th>
                                        <th class="text-end pe-3">Tổng tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="latestOrders.length === 0">
                                        <td colspan="4" class="text-center py-4 text-muted">Chưa có đơn hàng nào.</td>
                                    </tr>
                                    <tr v-for="order in latestOrders" :key="order.id">
                                        <td class="ps-3 fw-bold text-primary">#{{ order.id }}</td>
                                        <td>
                                            <div class="fw-medium text-dark">{{ order.customerName }}</div>
                                            <!-- Fix: dùng created_at hoặc createdAt -->
                                            <div class="small text-muted">{{ formatDate(order.created_at || order.createdAt) }}</div>
                                        </td>
                                        <td><span class="badge  " :class="getStatusClass(order.status)">{{
                                                getStatusLabel(order.status) }}</span></td>
                                        <!-- Fix: dùng total_amount hoặc totalAmount -->
                                        <td class="text-end pe-3 fw-bold text-danger">{{
                                            formatCurrency(order.total_amount || order.totalAmount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer bg-white border-top-0 text-center py-3">
                            <router-link to="/admin/orders"
                                class="btn btn-sm btn-light text-brand fw-bold border px-4 shadow-sm hover-brand">Xem
                                thêm</router-link>
                        </div>
                    </div>

                    <!-- Reviews & Comments Row -->
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white border-bottom py-3">
                                    <h5 class="card-title fw-bold text-brand mb-0">Đánh Giá Mới</h5>
                                </div>
                                <div class="list-group list-group-flush flex-grow-1">
                                    <div v-if="latestReviews.length === 0" class="p-3 text-center text-muted">Trống
                                    </div>
                                    <div v-for="review in latestReviews" :key="review.id"
                                        class="list-group-item px-3 py-2 border-bottom-0">
                                        <div class="d-flex justify-content-between mb-1"><small
                                                class="fw-bold text-truncate" style="max-width: 120px;">{{
                                                review.product?.name }}</small><span class="text-warning small"
                                                style="letter-spacing: -1px;">{{ renderStars(review.rating) }}</span>
                                        </div>
                                        <p class="mb-0 small text-muted text-truncate fst-italic">"{{ review.content }}"
                                        </p>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0 text-center py-3">
                                    <router-link to="/admin/reviews"
                                        class="btn btn-sm btn-light text-brand fw-bold border px-4 shadow-sm hover-brand">Xem
                                        thêm</router-link>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white border-bottom py-3">
                                    <h5 class="card-title fw-bold text-brand mb-0">Bình Luận Mới</h5>
                                </div>
                                <div class="list-group list-group-flush flex-grow-1">
                                    <div v-if="latestComments.length === 0" class="p-3 text-center text-muted">Trống
                                    </div>
                                    <div v-for="cmt in latestComments" :key="cmt.id"
                                        class="list-group-item px-3 py-2 border-bottom-0">
                                        <div class="d-flex align-items-center mb-1"><img
                                                :src="getAvatar(cmt.user?.avatar, cmt.user?.username)"
                                                class="rounded-circle me-2" width="20"><span
                                                class="fw-bold small text-dark">{{ cmt.user?.username || 'Ẩn danh'
                                                }}</span></div>
                                        <p class="mb-0 small text-muted text-truncate">"{{ cmt.content }}"</p>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0 text-center py-3">
                                    <router-link to="/admin/comments"
                                        class="btn btn-sm btn-light text-brand fw-bold border px-4 shadow-sm hover-brand">Xem
                                        thêm</router-link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Chart & Members -->
                <div class="col-lg-4">
                    <!-- CHART -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="card-title fw-bold text-brand mb-0">Tình Trạng Đơn Hàng</h5>
                        </div>
                        <div class="card-body">
                            <div style="height: 220px;" class="mb-3 position-relative"><canvas
                                    ref="chartCanvas"></canvas></div>
                            <!-- Legend -->
                            <div class="status-list small">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                    <span class="fw-bold text-secondary">Tổng</span><span class="fw-bold text-dark">{{
                                        stats.totalOrders }} đơn</span>
                                </div>
                                <div v-for="(config, key) in STATUS_CONFIG" :key="key"
                                    class="d-flex align-items-center justify-content-between py-1 px-2 rounded cursor-pointer transition-opacity"
                                    :class="{ 'opacity-25': hoveredLegendKey && hoveredLegendKey !== key }"
                                    @mouseenter="onLegendHover(key)" @mouseleave="onLegendLeave">
                                    <div class="d-flex align-items-center">
                                        <div class="dot rounded-circle me-2" :style="{ backgroundColor: config.color }">
                                        </div>
                                        <span class="text-dark">{{ config.label }}</span>
                                    </div>
                                    <span class="fw-bold badge bg-light text-dark border">{{ orderStatusCounts[key] || 0
                                        }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MEMBERS -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="card-title fw-bold text-brand mb-0">Khách Hàng Mới</h5>
                        </div>
                        <div class="list-group list-group-flush">
                            <div v-for="user in newMembers" :key="user.id"
                                class="list-group-item px-3 py-3 d-flex align-items-center">
                                <img :src="getAvatar(user.avatar_url, user.fullName)"
                                    @error="$event.target.src = getAvatar(null, user.fullName)"
                                    class="rounded-circle me-3 border shadow-sm" width="36" height="36"
                                    style="object-fit: cover;">
                                <div class="flex-grow-1 min-width-0">
                                    <div class="fw-bold text-dark text-truncate small">{{ user.fullName || 'Chưa cập nhật tên' }}</div>
                                    <div class="small text-muted text-truncate" style="font-size: 0.75rem;">{{
                                        user.email }}</div>
                                </div>
                                <div class="small text-muted">{{ formatDate(user.created_at) }}</div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 text-center py-3">
                            <router-link to="/admin/userAccount"
                                class="btn btn-sm btn-light text-brand fw-bold border px-4 shadow-sm hover-brand">Xem
                                thêm</router-link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: REVENUE -->
            <div v-if="activeDashboardTab === 'revenue'" class="row g-4 fade-in">
                <div class="col-12">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card bg-light border-0 p-3 h-100">
                                <h6 class="text-muted text-uppercase small fw-bold">Doanh thu năm nay</h6>
                                <h3 class="text-brand fw-bold mb-0">{{ formatCurrency(stats.totalRevenue) }}</h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light border-0 p-3 h-100">
                                <h6 class="text-muted text-uppercase small fw-bold">TB Đơn hàng</h6>
                                <h4 class="text-dark fw-bold mb-0">{{ formatCurrency(stats.avgOrderValue) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light border-0 p-3 h-100">
                                <h6 class="text-muted text-uppercase small fw-bold">Tháng cao điểm</h6>
                                <h4 class="text-success fw-bold mb-0">{{ stats.bestMonth }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="card-title fw-bold text-brand mb-0">Doanh Thu Theo Tháng</h5>
                        </div>
                        <div class="card-body">
                            <div style="height: 350px;"><canvas ref="revenueBarCanvas"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-bottom py-3">
                            <h5 class="card-title fw-bold text-brand mb-0">Tăng Trưởng</h5>
                        </div>
                        <div class="card-body">
                            <div style="height: 350px;"><canvas ref="revenueLineCanvas"></canvas></div>
                        </div>
                    </div>
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

.hover-brand:hover {
    background-color: #009981 !important;
    color: white !important;
    border-color: #009981 !important;
}

/* NAV PILLS */
.nav-pills .nav-link {
    color: #6c757d;
    transition: all 0.2s;
}

.nav-pills .nav-link:hover {
    color: #009981;
    background-color: rgba(0, 153, 129, 0.1);
}

.nav-pills .nav-link.active {
    background-color: #009981 !important;
    color: white !important;
}

/* CARDS */
.stats-card {
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-3px);
}

.icon-wrapper {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* LEGEND & TRANSITIONS */
.transition-opacity {
    transition: opacity 0.3s ease;
}

.opacity-25 {
    opacity: 0.3;
}

.dot {
    width: 10px;
    height: 10px;
    display: inline-block;
}

.cursor-pointer {
    cursor: pointer;
}

.fade-in {
    animation: fadeIn 0.4s ease-in-out;
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

/* TABLE */
:deep(.table) td {
    vertical-align: middle;
}

.min-width-0 {
    min-width: 0;
}
</style>