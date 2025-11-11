<!-- <script setup>
import { onMounted, onUnmounted } from 'vue';
import AdminHeader from '../components/admin/AdminHeader.vue';
import AdminFooter from '../components/admin/AdminFooter.vue';
import AdminSidebar from '../components/admin/AdminSidebar.vue'; 

onMounted(() => {
  document.body.classList.add('layout-fixed', 'sidebar-expand-lg', 'bg-body-tertiary');
});

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

    <AdminFooter />
  </div>
</template>

<style scoped>
/* Có thể thêm style riêng cho layout nếu cần */
</style> -->
<script setup>
import { onMounted, onUnmounted } from 'vue';
import AdminHeader from '../components/admin/AdminHeader.vue';
import AdminFooter from '../components/admin/AdminFooter.vue';
import AdminSidebar from '../components/admin/AdminSidebar.vue';

let adminLteScript = null; // Biến để theo dõi thẻ script

onMounted(() => {
  // 1. Thêm các class cần thiết vào body
  document.body.classList.add('layout-fixed', 'sidebar-expand-lg', 'bg-body-tertiary');

  // 2. Tải động script AdminLTE
  // Đây là đường dẫn tới tệp bạn vừa di chuyển vào thư mục public
  const scriptSrc = '/js/adminlte.min.js'; // <-- Điều chỉnh nếu bạn đặt ở vị trí khác trong /public

  // Kiểm tra xem script đã được tải trước đó chưa
  let existingScript = document.querySelector(`script[src="${scriptSrc}"]`);

  if (!existingScript) {
    adminLteScript = document.createElement('script');
    adminLteScript.src = scriptSrc;
    adminLteScript.defer = true; // Đảm bảo nó chạy sau khi DOM parse
    adminLteScript.onload = () => {
      console.log('AdminLTE script đã được tải động và thực thi.');
      // Khi script tải xong, nó sẽ tự động tìm các thuộc tính
      // data-lte-toggle và gắn sự kiện.
    };
    document.body.appendChild(adminLteScript);
  }
});

onUnmounted(() => {
  // 3. Xóa các class khi rời khỏi layout
  document.body.classList.remove('layout-fixed', 'sidebar-expand-lg', 'bg-body-tertiary');

  if (adminLteScript && adminLteScript.parentNode) {
    // adminLteScript.parentNode.removeChild(adminLteScript);
  }
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

    <AdminFooter />
  </div>
</template>

<style scoped>
/* Có thể thêm style riêng cho layout nếu cần */
</style>