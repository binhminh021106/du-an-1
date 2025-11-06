<template>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid">
      
      <button 
        class="btn btn-outline-light me-2 d-lg-none" 
        type="button"
        aria-label="Toggle sidebar"
      >
        <i class="bi bi-list"></i>
      </button>

      <a class="navbar-brand fw-bold" href="#">Admin Panel</a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="adminNavbar">
        
        <form class="d-flex ms-auto my-2 my-lg-0" role="search">
          <input class="form-control me-2" type="search" placeholder="Tìm kiếm..." aria-label="Search">
          <button class="btn btn-outline-light" type="submit">
            <i class="bi bi-search"></i>
          </button>
        </form>

        <ul class="navbar-nav ms-lg-3">
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle fs-5 me-1"></i>
              <span>Xin chào, Admin</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
              <li><a class="dropdown-item" href="#">
                <i class="bi bi-person-fill me-2"></i>Hồ sơ
              </a></li>
              <li><a class="dropdown-item" href="#">
                <i class="bi bi-gear-fill me-2"></i>Cài đặt
              </a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="#">
                <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
              </a></li>
            </ul>
          </li>
        </ul>

      </div>
    </div>
  </nav>
</template>

<script setup>
// Hiện tại không cần script gì, nhưng bạn có thể
// thêm logic @click cho nút đăng xuất ở đây sau
</script>

<style scoped>
/* Tùy chỉnh nhỏ nếu cần */
.navbar {
  z-index: 1040; /* Đảm bảo header luôn ở trên */
}
.dropdown-menu i {
  width: 1.25rem; /* Căn chỉnh icon trong dropdown */
}
</style>