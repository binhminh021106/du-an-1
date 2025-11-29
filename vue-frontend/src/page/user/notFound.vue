<script setup>
import { useRouter } from "vue-router";
const router = useRouter();
import '@fortawesome/fontawesome-free/css/all.min.css'
// const goHome = () => {
//   router.push('/');
// };
</script>

<template>
  <div class="notfound-page">
    <div class="content-404">
      <h1>404</h1>
      <p>Ô mai chuối! Trang bạn đang tìm kiếm không tồn tại.</p>
      <router-link to="/" class="home-btn">
        <i class="fa-solid fa-arrow-left"></i> Quay về trang chủ
      </router-link>
    </div>
  </div>
</template>

<style scoped>

.notfound-page {
  font-family: Arial, sans-serif;
}

.site-header {
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 15px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.relative-container {
  position: relative;
  gap: 20px;
}

/* Top bar */
.top-bar {
  background-color: #009981;
  color: #fff;
  font-size: 13px;
  padding: 8px 0;
}

.top-bar a {
  color: #fff;
  text-decoration: none;
}

.top-bar a:hover {
  text-decoration: underline;
  opacity: 0.9;
}

/* Main header */
.main-header {
  background-color: #fff;
  padding: 15px 0;
  border-bottom: 1px solid #eee;
}

.logo img {
  height: 40px;
}

.search-bar {
  flex-grow: 1;
  position: relative;
}

.search-bar input {
  width: 100%;
  padding: 10px 15px;
  border: 2px solid #009981;
  border-radius: 8px;
  font-size: 14px;
  background-color: #f8f9fa;
  outline: none;
}

.search-btn {
  position: absolute;
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: #009981;
  color: #fff;
  padding: 6px 15px;
  border-radius: 6px;
  cursor: pointer;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 15px;
}

.action-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-radius: 8px;
  background-color: #f8f9fa;
  color: #333;
  font-size: 14px;
}

.login-btn {
  background-color: #009981;
  color: #fff !important;
  font-weight: bold;
}

/* Nội dung 404 */
.content-404 {
  text-align: center;
  padding: 100px 20px;
  background-color: #f8f9fa;
}

.content-404 h1 {
  font-size: 120px;
  font-weight: bold;
  color: #009981;
  margin: 0;
}

.content-404 p {
  font-size: 20px;
  margin: 15px 0 30px;
  color: #333;
}

.home-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background-color: #009981;
  color: #fff;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: bold;
  transition: all 0.2s;
}

.home-btn:hover {
  background-color: #00483d;
  text-decoration: none;
}
</style>
