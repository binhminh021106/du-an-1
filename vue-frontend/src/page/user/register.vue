<script setup>

</script>

<template>
  <div class="login-page-wrapper">
    <div class="login-container">
      
      <div class="promo-section">
        <h2>Chào mừng bạn mới!</h2>
        <p>Tạo tài khoản để quản lý đơn hàng và nhận ưu đãi độc quyền.</p>

        <div class="benefits-box">
          <ul class="benefits-list">
            <li>Quản lý và theo dõi đơn hàng tiện lợi.</li>
            <li>Nhận thông báo khuyến mãi sớm nhất.</li>
            <li>Lưu danh sách sản phẩm yêu thích.</li>
            <li>Tích điểm đổi quà và thăng hạng thành viên.</li>
            <li>Bảo hành điện tử nhanh chóng, tiện lợi.</li>
            <li>Đặc quyền T-Student/T-Teacher ưu đãi thêm đến 10%</li>
          </ul>
        </div>

        <a href="#" class="details-link">Xem chi tiết chính sách ưu đãi Tmember ></a>
        
      </div>

      <div class="login-section">
        <h2>Đăng ký</h2>

        <form action="#" method="POST" class="login-form" @submit.prevent>
          <div class="form-group">
            <label for="fullname">Họ và tên</label>
            <input type="text" id="fullname" name="fullname" placeholder="Nhập họ và tên của bạn" required>
          </div>

          <div class="form-group">
            <label for="fullname">Email</label>
            <input type="text" id="Email" name="Email" placeholder="Nhập email của bạn" required>
          </div>

          <div class="form-group">
            <label for="phone">Số điện thoại</label>
            <input type="text" id="phone" name="phone" placeholder="Nhập số điện thoại của bạn" required>
          </div>

          <div class="form-group">
            <label for="password">Mật khẩu</label>
            <div class="password-wrapper">
              <input type="password" id="password" name="password" placeholder="Nhập mật khẩu của bạn" required>
              <span class="toggle-password"><i class="fa-solid fa-eye"></i></span>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm-password">Xác nhận mật khẩu</label>
            <div class="password-wrapper">
              <input type="password" id="confirm-password" name="confirm-password" placeholder="Nhập lại mật khẩu của bạn" required>
              <span class="toggle-password"><i class="fa-solid fa-eye"></i></span>
            </div>
          </div>

          <button type="submit" class="btn-login">Đăng ký</button>
        </form>

        <div class="separator">Hoặc đăng ký bằng</div>

        <div class="social-login">
          <button class="social-btn">
            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google">
            Google
          </button>
          <button class="social-btn">
              <img src="../../assets/facebook-svgrepo-com.svg" alt="Facebook">
            Facebook
          </button>
        </div>

        <p class="register-link">
          Bạn đã có tài khoản? <router-link :to="{name: 'login'}">Đăng nhập ngay</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap');

:root {
    --primary-color: #009981;
    --text-color: #333;
    --border-color: #ddd;
    --bg-light: #f9f9f9;
}

.login-page-wrapper {
    font-family: 'Roboto', sans-serif;
    background-color: var(--bg-light);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    width: 100%;
    margin: 0;
    color: var(--text-color);
}

.login-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    max-width: 1000px;
    width: 100%;
    background-color: #fff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    border-radius: 12px;
    overflow: hidden;
    margin: 20px;
}

.promo-section {
    padding: 40px;
}

.promo-section h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-top: 0;
}

.promo-section p {
    font-size: 1rem;
    margin-bottom: 25px;
}

.benefits-box {
    border: 2px dashed var(--primary-color);
    border-radius: 10px;
    padding: 20px;
}

.benefits-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.benefits-list li {
    position: relative;
    padding-left: 30px; 
    margin-bottom: 15px;
    font-size: 0.9rem;
    font-weight: 500;
}

.benefits-list li::before {
    content: '🎁';
    position: absolute;
    left: 0;
    top: 0;
    font-size: 1.2rem;
}

.details-link {
    display: inline-block;
    margin-top: 20px;
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 700;
    font-size: 0.9rem;
}

.details-link:hover {
    text-decoration: underline;
}

.login-section {
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border-left: 1px solid var(--border-color);
}

.login-section h2 {
    font-size: 1.75rem;
    font-weight: 700;
    text-align: left;
    margin-bottom: 25px;
}

.login-form .form-group {
    margin-bottom: 20px;
}

.login-form label {
    display: none;
}

.login-form input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid black;
    border-radius: 8px;
    font-size: 1rem;
    box-sizing: border-box; 
}

.password-wrapper {
    position: relative;
}

.password-wrapper .toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #888;
}

.btn-login {
    width: 100%;
    padding: 12px;
    background-color: var(--primary-color);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.btn-login:hover {
    opacity: 0.9;
}

.forgot-password {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: var(--primary-color);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
}

.forgot-password:hover {
    text-decoration: underline;
}

.separator {
    text-align: center;
    margin: 25px 0;
    color: #aaa;
    font-size: 0.85rem;
    position: relative;
}

.separator::before,
.separator::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 40%;
    height: 1px;
    background-color: var(--border-color);
}

.separator::before {
    left: 0;
}

.separator::after {
    right: 0;
}

.social-login {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.social-btn {
    padding: 10px;
    border: 1px solid #009981;
    border-radius: 8px;
    background-color: #fff;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.social-btn img {
    width: 20px;
    height: 20px;
}

.social-btn:hover {
    background-color: #009981;
    color: white;
}

.register-link {
    text-align: center;
    margin-top: 30px;
    font-size: 0.9rem;
}

.register-link a {
    color: var(--primary-color);
    font-weight: 700;
    text-decoration: none;
}

.register-link a:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .login-container {
        grid-template-columns: 1fr;
    }
    .promo-section {
        display: none; 
    }
    .login-section {
        border-left: none;
    }
}
</style>