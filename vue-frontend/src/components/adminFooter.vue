<template>
  <footer class="site-footer">
    <div class="footer-main">
      <div class="container">
        <div class="footer-row">
          
          <div class="footer-col">
            <h3 class="footer-title">Về ThinkHub</h3>
            <p class="footer-desc">
              ThinkHub là hệ thống bán lẻ các sản phẩm công nghệ uy tín, chất lượng hàng đầu Việt Nam. Chúng tôi cam kết mang đến trải nghiệm mua sắm tốt nhất cho khách hàng.
            </p>
            <ul class="footer-contact">
              <li><i class="fa-solid fa-location-dot"></i> 123 Đường ABC, Quận XYZ, TP.HCM</li>
              <li><i class="fa-solid fa-phone"></i> 1800.2097 (Miễn phí)</li>
              <li><i class="fa-solid fa-envelope"></i> cskh@thinkhub.vn</li>
            </ul>
          </div>

          <div class="footer-col">
            <h3 class="footer-title">Hỗ trợ khách hàng</h3>
            <ul class="footer-links">
              <li><router-link to="/page/huong-dan-mua-hang">Hướng dẫn mua hàng</router-link></li>
              <li><router-link to="/page/chinh-sach-bao-hanh">Chính sách bảo hành</router-link></li>
              <li><router-link to="/page/chinh-sach-doi-tra">Chính sách đổi trả</router-link></li>
              <li><router-link to="/page/phuong-thuc-thanh-toan">Phương thức thanh toán</router-link></li>
              <li><router-link to="/page/cau-hoi-thuong-gap">Câu hỏi thường gặp (FAQ)</router-link></li>
            </ul>
          </div>

          <div class="footer-col">
            <h3 class="footer-title">Chính sách</h3>
            <ul class="footer-links">
              <li><router-link to="/page/chinh-sach-bao-mat">Chính sách bảo mật</router-link></li>
              <li><router-link to="/page/dieu-khoan-su-dung">Điều khoản sử dụng</router-link></li>
              <li><router-link to="/page/chinh-sach-van-chuyen">Chính sách vận chuyển</router-link></li>
              <li><router-link to="/page/chinh-sach-kiem-hang">Chính sách kiểm hàng</router-link></li>
            </ul>
          </div>

          <div class="footer-col">
            <h3 class="footer-title">Kết nối với chúng tôi</h3>
            <div class="social-links">
              <a href="#" class="social-item facebook"><i class="fa-brands fa-facebook-f"></i></a>
              <a href="#" class="social-item youtube"><i class="fa-brands fa-youtube"></i></a>
              <a href="#" class="social-item tiktok"><i class="fa-brands fa-tiktok"></i></a>
              <a href="#" class="social-item zalo">Zalo</a>
            </div>
            
            <h3 class="footer-title" style="margin-top: 20px;">Đăng ký nhận tin</h3>
            <form class="newsletter-form" @submit.prevent="handleSubscribe">
              <input type="email" placeholder="Nhập email của bạn..." required>
              <button type="submit"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
          </div>

        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="container">
        <p>© 2024 Công ty Cổ phần ThinkHub. Tất cả các quyền được bảo lưu.</p>
        <div class="payment-icons">
            <i class="fa-brands fa-cc-visa"></i>
            <i class="fa-brands fa-cc-mastercard"></i>
            <i class="fa-brands fa-cc-paypal"></i>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
const handleSubscribe = () => {
  alert("Cảm ơn bạn đã đăng ký nhận tin!");
  // Thêm logic gọi API đăng ký email ở đây
};
</script>

<style>
:root {
  --footer-bg: #f8f9fa;         /* Nền footer chính (xám rất nhạt) */
  --footer-title-color: #333;   /* Màu tiêu đề cột */
  --footer-text-color: #555;    /* Màu chữ nội dung */
  --primary-color: #009981;     /* Màu chính thương hiệu */
  --primary-dark: #00483D;      /* Màu nhấn đậm */
}
</style>

<style scoped>
/* Import Font Awesome nếu cần */
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

.site-footer {
  background-color: var(--footer-bg);
  color: var(--footer-text-color);
  font-family: Arial, sans-serif;
  font-size: 14px;
  border-top: 4px solid var(--primary-color); /* Đường viền màu thương hiệu tạo điểm nhấn */
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 15px;
}

/* --- Phần chính Footer --- */
.footer-main {
  padding: 40px 0;
}

.footer-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 30px;
}

.footer-col {
  flex: 1 1 200px; /* Co giãn linh hoạt, tối thiểu 200px mỗi cột */
  min-width: 200px;
}

.footer-title {
  font-size: 16px;
  font-weight: bold;
  color: var(--footer-title-color);
  margin-bottom: 20px;
  position: relative;
}

/* Đường gạch chân ngắn dưới tiêu đề */
/* .footer-title::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -8px;
    width: 30px;
    height: 2px;
    background-color: var(--primary-color);
} */

.footer-desc {
  line-height: 1.6;
  margin-bottom: 20px;
}

.footer-contact li {
  margin-bottom: 12px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  line-height: 1.5;
}
.footer-contact i {
  color: var(--primary-color);
  margin-top: 3px;
}

/* Link liên kết */
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
  color: var(--footer-text-color);
  transition: all 0.2s;
  display: inline-block;
}
.footer-links a:hover {
  color: var(--primary-color);
  transform: translateX(5px); /* Hiệu ứng di chuyển nhẹ khi hover */
}

/* Mạng xã hội */
.social-links {
  display: flex;
  gap: 10px;
}
.social-item {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #fff;
  border: 1px solid #eee;
  border-radius: 50%; /* Hình tròn */
  color: #555;
  text-decoration: none;
  transition: all 0.3s;
}
.social-item:hover {
  background-color: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}
/* Màu riêng cho từng mạng xã hội nếu thích */
/* .social-item.facebook:hover { background-color: #3b5998; border-color: #3b5998; }
.social-item.youtube:hover { background-color: #ff0000; border-color: #ff0000; } */

/* Form đăng ký nhận tin */
.newsletter-form {
  display: flex;
  margin-top: 15px;
}
.newsletter-form input {
  flex: 1;
  padding: 10px;
  border: 1px solid #ddd;
  border-right: none;
  border-radius: 4px 0 0 4px;
  outline: none;
  font-size: 14px;
}
.newsletter-form input:focus {
    border-color: var(--primary-color);
}
.newsletter-form button {
  padding: 0 15px;
  background-color: var(--primary-color);
  color: white;
  border: none;
  border-radius: 0 4px 4px 0;
  cursor: pointer;
  transition: background-color 0.2s;
}
.newsletter-form button:hover {
  background-color: var(--primary-dark);
}

/* --- Phần đáy Footer --- */
.footer-bottom {
  background-color: #eee; /* Nền tối hơn một chút để phân biệt */
  padding: 15px 0;
  font-size: 13px;
  color: #666;
}
.footer-bottom .container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.footer-bottom p {
    margin: 0;
}
.payment-icons {
    display: flex;
    gap: 10px;
    font-size: 24px;
    color: #888;
}

/* Responsive cho mobile */
@media (max-width: 768px) {
  .footer-row {
    flex-direction: column;
    gap: 30px;
  }
  .footer-col {
      width: 100%;
  }
  .footer-bottom .container {
      flex-direction: column;
      gap: 10px;
      text-align: center;
  }
}
</style>