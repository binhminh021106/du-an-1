<script setup>
import { onMounted, onUnmounted } from 'vue';
import AdminHeader from '../components/admin/adminHeader.vue';
import AdminFooter from '../components/admin/adminFooter.vue';
import AdminSidebar from '../components/admin/AdminSidebar.vue'; // Import Sidebar mới tạo

// Thêm các class cần thiết cho body khi vào trang admin
onMounted(() => {
  document.body.classList.add('layout-fixed', 'sidebar-expand-lg', 'bg-body-tertiary');
});

// Xóa các class khi rời khỏi trang admin để không ảnh hưởng trang user
onUnmounted(() => {
  document.body.classList.remove('layout-fixed', 'sidebar-expand-lg', 'bg-body-tertiary');
});
</script>

<template>
  <div class="app-wrapper">
    <AdminHeader />
    <AdminSidebar />

    <main class="app-main">
      <div class="app-content">
        <div class="container-fluid">
          <router-view></router-view>
        </div>
      </div>
    </main>
<!-- test git -->
    <AdminFooter />
  </div>
</template>

<style scoped>
/* Có thể thêm style riêng cho layout nếu cần */
</style>