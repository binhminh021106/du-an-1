<script setup>
import { ref, onMounted, reactive } from 'vue';
import apiService from '../../apiService.js';

// --- STATE ---
const currentUser = ref({});
const userPermissions = ref([]);

// State quản lý đóng/mở menu
const menuState = reactive({
    users: false,
    products: false,
    sales: false,
    content: false,
    interaction: false
});

// Hàm toggle menu
const toggleMenu = (menu) => {
    menuState[menu] = !menuState[menu];
};

// --- CHECK QUYỀN ---
const can = (permissionSlug) => {
    if (currentUser.value.role_id === 11) return true;
    return userPermissions.value.includes(permissionSlug);
};

const canAny = (permissionsArray) => {
    if (currentUser.value.role_id === 11) return true;
    return permissionsArray.some(slug => userPermissions.value.includes(slug));
};

// --- FETCH DATA ---
const fetchUserData = async () => {
    const storedAdmin = localStorage.getItem('adminData');
    if (storedAdmin) {
        try {
            currentUser.value = JSON.parse(storedAdmin);
            if (currentUser.value.role_id === 11) return;

            if (currentUser.value.role_id) {
                const response = await apiService.get(`admin/roles/${currentUser.value.role_id}`);
                if (response.data && response.data.permissions) {
                    userPermissions.value = response.data.permissions.map(p => p.slug);
                }
            }
        } catch (e) {
            console.error("Lỗi tải thông tin quyền hạn:", e);
        }
    }
};

onMounted(() => {
    fetchUserData();
});
</script>

<template>
  <!-- Áp dụng màu nền custom -->
  <aside class="app-sidebar shadow custom-sidebar" data-bs-theme="dark">
    <div class="sidebar-brand">
      <router-link to="/admin" class="brand-link">
        <img src="../img/logo.png" alt="Logo" class="brand-image opacity-75 shadow">
        <span class="brand-text fw-bold text-white">ThinkHub</span>
      </router-link>
    </div>
    
    <div class="sidebar-wrapper">
      <nav class="mt-3">
        <ul class="nav sidebar-menu flex-column" role="menu" data-accordion="false">
          
          <!-- DASHBOARD -->
          <li class="nav-item mb-2" v-if="can('dashboard.access')">
            <router-link to="/admin" class="nav-link" active-class="active">
              <i class="nav-icon bi bi-speedometer2"></i>
              <p>Dashboard</p>
            </router-link>
          </li>

          <!-- GROUP: NGƯỜI DÙNG -->
          <li class="nav-item" :class="{ 'menu-open': menuState.users }" v-if="canAny(['admins.access', 'users.access'])">
            <a href="#" class="nav-link group-header" @click.prevent="toggleMenu('users')" :class="{ 'active-group': menuState.users }">
              <i class="nav-icon bi bi-people-fill"></i>
              <p>
                Người Dùng
                <i class="right bi bi-chevron-left transition-icon" :class="{ 'rotate-down': menuState.users }"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item" v-if="can('admins.access')">
                <router-link to="/admin/adminAccount" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Tài khoản nội bộ</p>
                </router-link>
              </li>
              <li class="nav-item" v-if="can('users.access')">
                <router-link to="/admin/userAccount" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Khách hàng</p>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- GROUP: SẢN PHẨM -->
          <li class="nav-item" :class="{ 'menu-open': menuState.products }" v-if="canAny(['categories.access', 'products.access'])">
            <a href="#" class="nav-link group-header" @click.prevent="toggleMenu('products')" :class="{ 'active-group': menuState.products }">
              <i class="nav-icon bi bi-box-seam-fill"></i>
              <p>
                Sản Phẩm
                <i class="right bi bi-chevron-left transition-icon" :class="{ 'rotate-down': menuState.products }"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item" v-if="can('categories.access')">
                <router-link to="/admin/categories" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Danh mục</p>
                </router-link>
              </li>
              <li class="nav-item" v-if="can('products.access')">
                <router-link to="/admin/products" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Tất cả sản phẩm</p>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- GROUP: BÁN HÀNG -->
          <li class="nav-item" :class="{ 'menu-open': menuState.sales }" v-if="canAny(['orders.access', 'coupons.access'])">
            <a href="#" class="nav-link group-header" @click.prevent="toggleMenu('sales')" :class="{ 'active-group': menuState.sales }">
              <i class="nav-icon bi bi-cart-check-fill"></i>
              <p>
                Bán Hàng
                <i class="right bi bi-chevron-left transition-icon" :class="{ 'rotate-down': menuState.sales }"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item" v-if="can('orders.access')">
                <router-link to="/admin/orders" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Đơn hàng</p>
                </router-link>
              </li>
              <li class="nav-item" v-if="can('coupons.access')">
                <router-link :to="{ name: 'admin-coupons' }" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Mã giảm giá</p>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- GROUP: NỘI DUNG -->
          <li class="nav-item" :class="{ 'menu-open': menuState.content }" v-if="canAny(['news.access', 'slides.access'])">
            <a href="#" class="nav-link group-header" @click.prevent="toggleMenu('content')" :class="{ 'active-group': menuState.content }">
              <i class="nav-icon bi bi-collection-play-fill"></i>
              <p>
                Nội Dung
                <i class="right bi bi-chevron-left transition-icon" :class="{ 'rotate-down': menuState.content }"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item" v-if="can('news.access')">
                <router-link to="/admin/news" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Tin tức & Blog</p>
                </router-link>
              </li>
              <li class="nav-item" v-if="can('slides.access')">
                <router-link :to="{ name: 'admin-slides' }" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Slide & Banner</p>
                </router-link>
              </li>
            </ul>
          </li>

          <!-- GROUP: TƯƠNG TÁC -->
          <li class="nav-item" :class="{ 'menu-open': menuState.interaction }" v-if="canAny(['comments.access', 'reviews.access'])">
            <a href="#" class="nav-link group-header" @click.prevent="toggleMenu('interaction')" :class="{ 'active-group': menuState.interaction }">
              <i class="nav-icon bi bi-chat-left-text-fill"></i>
              <p>
                Tương Tác
                <i class="right bi bi-chevron-left transition-icon" :class="{ 'rotate-down': menuState.interaction }"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item" v-if="can('comments.access')"> 
                <router-link :to="{ name: 'admin-comments' }" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Bình luận</p>
                </router-link>
              </li>
              <li class="nav-item" v-if="can('reviews.access')">
                <router-link :to="{ name: 'admin-reviews' }" class="nav-link" active-class="active">
                  <i class="bi bi-circle nav-icon small-dot"></i>
                  <p>Đánh giá</p>
                </router-link>
              </li>
            </ul>
          </li>
          
        </ul>
      </nav>
    </div>
  </aside>
</template>

<style scoped>
/* --- 1. Custom Background Color --- */
/* Đổi màu nền sidebar sang màu tối hơn chút để nổi bật text */
.custom-sidebar {
    background-color: #212529 !important; /* Dark Gray chuẩn AdminLTE */
}

.sidebar-brand {
    background-color: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

/* --- 2. Typography & Spacing --- */
.nav-link p {
    font-size: 15px;
    font-weight: 500;
    margin: 0;
    margin-left: 8px;
}

/* --- 3. Active States (The "Xịn Sò" Part) --- */
/* Màu Active chuẩn #009981 */
.nav-pills .nav-link.active, 
.nav-link.active {
    background-color: #009981 !important; /* Màu xanh bạn chọn */
    color: #fff !important;
    box-shadow: 0 4px 6px rgba(0,0,0,0.2);
    border-radius: 6px;
    font-weight: 600;
}

/* Màu cho group header khi đang mở */
.nav-link.active-group {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: #fff !important;
}

/* Hover Effect */
.nav-link:hover {
    background-color: rgba(255, 255, 255, 0.08);
    color: #fff;
}

/* --- 4. Dropdown Animation & Icons --- */
.transition-icon {
    transition: transform 0.3s ease;
    font-size: 0.8rem;
}

.rotate-down {
    transform: rotate(-90deg);
}

.nav-treeview {
    display: none; /* Mặc định ẩn */
    background-color: rgba(0, 0, 0, 0.2); /* Màu nền tối hơn cho menu con */
    margin-left: 0;
    padding-left: 0;
}

/* Khi có class menu-open thì hiện menu con */
.menu-open > .nav-treeview {
    display: block;
}

.small-dot {
    font-size: 0.5rem; /* Icon chấm tròn nhỏ cho menu con */
    opacity: 0.7;
    margin-right: 5px;
}
</style>