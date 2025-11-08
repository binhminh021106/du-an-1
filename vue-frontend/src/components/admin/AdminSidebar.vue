<template>
  <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
    <div class="sidebar-brand">
      <a href="#" class="brand-link">
        <img src="../img/logo.png" alt="AdminLTE Logo" class="brand-image opacity-75 shadow">
        <span class="brand-text fw-light">ThinkHub</span>
      </a>
    </div>
    <div class="sidebar-wrapper">
      <nav class="mt-2">
        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <router-link to="/admin" class="nav-link">
              <i class="nav-icon bi bi-speedometer"></i>
              <p>Dashboard</p>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link to="/admin/adminUsers" class="nav-link">
              <i class="nav-icon fas fa-user"></i>
              <p> Quản lý tài khoản</p>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link to="/admin/categories" class="nav-link">
              <i class="nav-icon bi fas fa-cubes"></i>
              <p>Quản lý danh mục</p>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link to="/admin/products" class="nav-link">
              <i class="nav-icon bi fas fa-box"></i>
              <p>Quản lý sản phẩm</p>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link to="/admin/orders" class="nav-link">
              <i class="nav-icon  fas fa-shopping-cart"></i>
              <p>Quản lý đơn hàng</p>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link :to="{ name: 'admin-comments' }" class="nav-link">
              <i class="nav-icon  fas fa-comments"></i>
              <p>Quản lý bình luận</p>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link :to="{ name: 'admin-reviews' }" class="nav-link">
              <i class="nav-icon  fas fa-star"></i>
              <p>Quản lý đánh giá</p>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link :to="{ name: 'admin-slides' }" class="nav-link">
              <i class="nav-icon  fas fa-sliders-h"></i>
              <p>Quản lý slide</p>
            </router-link>
          </li>
          <li class="nav-item">
            <router-link :to="{ name: 'admin-coupons' }" class="nav-link">
              <i class="nav-icon  fas fa-sliders-h"></i>
              <p>Quản lý coupon</p>
            </router-link>
          </li>
        </ul>
      </nav>
    </div>
  </aside>
</template>
<style scoped>
.nav-item p{
  font-size: 18px;
  font-weight: 700;
  margin: 8px 0;
}
</style>