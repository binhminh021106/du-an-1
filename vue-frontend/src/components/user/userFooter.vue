<template>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
    crossorigin="anonymous" referrerpolicy="no-referrer">

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <link
    href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Montserrat:wght@500;600;700&display=swap"
    rel="stylesheet" />

  <footer class="site-footer">
    <!-- Phần chính Footer -->
    <div class="footer-main">
      <div class="container">
        <div class="footer-row">

          <!-- Cột 1: Về Chúng tôi -->
          <div class="footer-col intro-col">
            <h3 class="footer-title">Về ThinkHub</h3>
            <p class="footer-desc">
              ThinkHub là hệ thống bán lẻ các sản phẩm công nghệ uy tín, chất lượng hàng đầu Việt Nam. Chúng tôi cam kết
              mang đến trải nghiệm mua sắm tốt nhất.
            </p>
            <ul class="footer-contact">
              <li>
                <div class="icon-box"><i class="fa-solid fa-location-dot"></i></div>
                <span>123 Đường ABC, Quận XYZ, TP.HCM</span>
              </li>
              <li>
                <div class="icon-box"><i class="fa-solid fa-phone"></i></div>
                <span>1800.2097 (Miễn phí)</span>
              </li>
              <li>
                <div class="icon-box"><i class="fa-solid fa-envelope"></i></div>
                <span>cskh@thinkhub.vn</span>
              </li>
            </ul>
          </div>

          <!-- Cột 2: Hỗ trợ -->
          <div class="footer-col">
            <h3 class="footer-title">Hỗ trợ khách hàng</h3>
            <ul class="footer-links">
              <li><router-link :to="{ name: 'policy', hash: '#guide-v2' }">Hỗ trợ mua hàng</router-link></li>
              <li><router-link :to="{ name: 'policy', hash: '#warranty-v2' }">Chính sách bảo hành</router-link></li>
              <li><router-link :to="{ name: 'policy', hash: '#returns-v2' }">Chính sách đổi trả</router-link></li>
              <li><router-link :to="{ name: 'policy', hash: '#guide-v2' }">Phương thức thanh toán</router-link></li>
              <li><router-link :to="{ name: 'FAQ' }">Câu hỏi thường gặp (FAQ)</router-link></li>
            </ul>
          </div>

          <!-- Cột 3: Chính sách -->
          <div class="footer-col">
            <h3 class="footer-title">Chính sách</h3>
            <ul class="footer-links">
              <li><router-link :to="{ name: 'policy', hash: '#privacy-v2' }">Chính sách bảo mật</router-link></li>
              <li><router-link :to="{ name: 'policy', hash: '#terms-v2' }">Điều khoản sử dụng</router-link></li>
              <li><router-link :to="{ name: 'policy', hash: '#shipping-v2' }">Chính sách vận chuyển</router-link></li>
              <li><router-link :to="{ name: 'policy', hash: '#returns-v2' }">Chính sách kiểm hàng</router-link></li>
            </ul>
          </div>

          <!-- Cột 4: Kết nối & Newsletter -->
          <div class="footer-col social-col">
            <h3 class="footer-title">Kết nối với chúng tôi</h3>
            <div class="social-links">
              <!-- Style nút vuông bo góc giống Header Action Items -->
              <a href="#" class="social-item"><i class="fa-brands fa-facebook-f"></i></a>
              <a href="#" class="social-item"><i class="fa-brands fa-youtube"></i></a>
              <a href="#" class="social-item"><i class="fa-brands fa-tiktok"></i></a>
              <a href="#" class="social-item z-icon">Zalo</a>
            </div>

            <h3 class="footer-title mt-4">Đăng ký nhận tin</h3>
            <!-- Form style giống Search Bar Header -->
            <form class="newsletter-form" @submit.prevent="handleSubscribe">
              <input type="email" placeholder="Nhập email của bạn..." required>
              <button type="submit" class="newsletter-btn">
                <i class="fa-solid fa-paper-plane"></i>
              </button>
            </form>
          </div>

        </div>
      </div>
    </div>

    <!-- Phần đáy Footer -->
    <div class="footer-bottom">
      <div class="container">
        <div class="copyright">
          © 2024 <strong>Công ty Cổ phần ThinkHub</strong>. Tất cả các quyền được bảo lưu.
        </div>
        <div class="payment-icons">
          <i class="fa-brands fa-cc-visa"></i>
          <i class="fa-brands fa-cc-mastercard"></i>
          <i class="fa-brands fa-cc-paypal"></i>
          <i class="fa-brands fa-cc-apple-pay"></i>
        </div>
      </div>
    </div>

    <!-- Truyền biến products vào Chatbot -->
   

  </footer>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import apiService from '../../apiService.js'; // Import apiService để gọi API

// Khai báo biến products
const products = ref([]);

// Gọi API lấy danh sách sản phẩm khi Footer được mounted
onMounted(async () => {
  try {
    // Chỉ lấy sản phẩm để phục vụ Chatbot, không cần lấy các data khác của trang Home
    const prodRes = await apiService.get('/products?include=variants');
    products.value = prodRes?.data?.data || prodRes?.data || [];
  } catch (error) {
    console.error("Lỗi lấy sản phẩm cho chatbot tại Footer:", error);
    // Nếu lỗi thì products vẫn là mảng rỗng [], Chatbot sẽ không crash nhưng có thể không trả lời được về sản phẩm
  }
});

// Sử dụng SweetAlert2 cho đồng bộ với Header thay vì alert() thường
const handleSubscribe = () => {
  Swal.fire({
    title: 'Đăng ký thành công!',
    text: 'Cảm ơn bạn đã quan tâm đến bản tin của ThinkHub.',
    icon: 'success',
    confirmButtonColor: '#009981',
    customClass: {
      popup: 'elegant-popup', // Dùng lại class style từ Header nếu có global css
      confirmButton: 'elegant-confirm-btn'
    }
  });
};
</script>

<style>
:root {
  --footer-bg: #f8f9fa;
  /* Nền footer chính (xám rất nhạt) */
  --footer-title-color: #333;
  /* Màu tiêu đề cột */
  --footer-text-color: #555;
  /* Màu chữ nội dung */
  --primary-color: #009981;
  /* Màu chính thương hiệu */
  --primary-dark: #00483D;
  /* Màu nhấn đậm */
}
</style>
<style scoped>
/* --- VARIABLES (Đồng bộ với Header) --- */
.site-footer {
  --primary-color: #009981;
  --primary-light: #DBF9EB;
  --primary-dark: #00483D;
  --text-dark: #333333;
  --text-gray: #666666;
  --bg-gray: #f8f9fa;
  --border-color: #eeeeee;

  background-color: #fff;
  /* Nền trắng sạch sẽ */
  border-top: 4px solid var(--primary-color);
  font-family: Arial, sans-serif;
  font-size: 14px;
  color: var(--text-gray);
}

/* ĐÃ XÓA: class .container ở đây để Footer ăn theo style global giống Header */

/* --- LAYOUT --- */
.footer-main {
  padding: 50px 0;
}

.footer-row {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
  /* Chia cột tỷ lệ hợp lý hơn */
  gap: 30px;
}

.footer-title {
  font-size: 15px;
  /* Giống Header User Name */
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 20px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* --- CONTACT INFO --- */
.footer-desc {
  line-height: 1.6;
  margin-bottom: 20px;
  font-size: 13.5px;
}

.footer-contact li {
  margin-bottom: 12px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  line-height: 1.5;
}

/* Icon nhỏ xinh đồng bộ màu Primary */
.icon-box {
  min-width: 20px;
  color: var(--primary-color);
  text-align: center;
}

/* --- LINKS --- */
.footer-links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-links li {
  margin-bottom: 10px;
}

.footer-links a {
  text-decoration: none;
  color: var(--text-gray);
  transition: all 0.2s;
  display: inline-block;
  position: relative;
}

.footer-links a:hover {
  color: var(--primary-color);
  transform: translateX(5px);
}

/* --- SOCIAL BUTTONS (Style giống Header Action Items) --- */
.social-links {
  display: flex;
  gap: 10px;
}

.social-item {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--bg-gray);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  /* Bo góc 8px chuẩn Header */
  color: var(--text-dark);
  text-decoration: none;
  transition: all 0.2s;
  font-size: 16px;
}

.social-item:hover {
  background-color: var(--primary-light);
  color: var(--primary-dark);
  border-color: var(--primary-light);
  transform: translateY(-2px);
}

.z-icon {
  font-weight: bold;
  font-size: 12px;
}

/* --- NEWSLETTER FORM (Style giống Header Search Bar) --- */
.newsletter-form {
  position: relative;
  margin-top: 15px;
  width: 100%;
}

.newsletter-form input {
  width: 100%;
  padding: 10px 15px;
  padding-right: 45px;
  /* Chừa chỗ cho nút */
  border: 2px solid var(--border-color);
  /* Viền thường */
  border-radius: 8px;
  /* Bo góc 8px */
  outline: none;
  font-size: 14px;
  background-color: var(--bg-gray);
  transition: all 0.2s;
}

.newsletter-form input:focus {
  background-color: #fff;
  border-color: var(--primary-color);
  /* Focus xanh Teal giống header */
  box-shadow: 0 0 0 3px rgba(0, 153, 129, 0.1);
}

.newsletter-btn {
  position: absolute;
  right: 5px;
  top: 50%;
  transform: translateY(-50%);
  width: 32px;
  height: 32px;
  border: none;
  background: var(--primary-color);
  color: white;
  border-radius: 6px;
  cursor: pointer;
  transition: 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
}

.newsletter-btn:hover {
  background-color: var(--primary-dark);
}

/* --- FOOTER BOTTOM --- */
.footer-bottom {
  background-color: var(--bg-gray);
  padding: 15px 0;
  border-top: 1px solid var(--border-color);
  font-size: 13px;
}

.footer-bottom .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.payment-icons {
  display: flex;
  gap: 15px;
  font-size: 24px;
  color: #9ca3af;
  /* Màu nhạt hơn cho icon payment */
}

.mt-4 {
  margin-top: 1.5rem;
}

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
  .footer-row {
    grid-template-columns: 1fr 1fr;
    /* 2 cột trên Tablet */
    gap: 40px;
  }
}

@media (max-width: 576px) {
  .footer-row {
    grid-template-columns: 1fr;
    /* 1 cột trên Mobile */
    gap: 30px;
  }

  .footer-bottom .container {
    flex-direction: column;
    gap: 15px;
    text-align: center;
  }
}
</style>