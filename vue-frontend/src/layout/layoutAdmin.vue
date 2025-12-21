<script setup>
import { onMounted, onUnmounted } from 'vue';
import AdminHeader from '../components/admin/AdminHeader.vue';
import AdminFooter from '../components/admin/AdminFooter.vue';
import AdminSidebar from '../components/admin/AdminSidebar.vue';

let adminLteScript = null; 

onMounted(() => {
  document.body.classList.add('layout-fixed', 'sidebar-expand-lg', 'bg-body-tertiary');


  const scriptSrc = '/js/adminlte.min.js';

  let existingScript = document.querySelector(`script[src="${scriptSrc}"]`);

  if (!existingScript) {
    adminLteScript = document.createElement('script');
    adminLteScript.src = scriptSrc;
    adminLteScript.defer = true; // Đảm bảo nó chạy sau khi DOM parse
    adminLteScript.onload = () => {
      console.log('AdminLTE script đã được tải động và thực thi.');

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
</style>