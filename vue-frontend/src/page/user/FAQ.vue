<script setup>
import { ref, onMounted } from 'vue';

// --- STATE ---
// Lưu ID câu hỏi đang mở. Mặc định mở câu đầu tiên của nhóm Giao dịch
const activeQuestion = ref('gd1'); 

// --- METHODS ---
const toggleAccordion = (questionId) => {
  // Nếu click vào câu đang mở -> Đóng lại
  if (activeQuestion.value === questionId) {
    activeQuestion.value = null; 
  } else {
    // Nếu click câu khác -> Mở câu đó (và tự động đóng câu cũ nhờ cơ chế reactivity)
    activeQuestion.value = questionId;
  }
};

// Đảm bảo cuộn mượt khi click menu
onMounted(() => {
  document.documentElement.style.scrollBehavior = 'smooth';
});
</script>

<template>
  <section class="faq-page">
    
    <!-- HERO HEADER (Đồng bộ style với Blog) -->
    <header class="faq-hero">
      <div class="faq-hero-inner">
        <p class="faq-pre-title">HỖ TRỢ KHÁCH HÀNG</p>
        <h1>Câu hỏi thường gặp</h1>
        <p class="faq-subtitle">
          Giải đáp nhanh các thắc mắc về vận chuyển, thanh toán và bảo hành.
        </p>
      </div>
    </header>

    <!-- [EDIT] Thêm class 'container' để đồng bộ width với Header/Footer -->
    <main class="faq-container container">
      <div class="faq-layout">
        
        <!-- SIDEBAR NAV (Bên trái) -->
        <aside class="faq-sidebar">
          <div class="sidebar-widget">
            <h3><i class="bi bi-list-stars me-2"></i> Danh mục hỗ trợ</h3>
            <ul class="nav-links">
              <li>
                  <a href="#giao-dich">
                    <i class="bi bi-credit-card-2-front me-2"></i> Giao dịch & Thanh toán
                  </a>
              </li>
              <li>
                  <a href="#van-chuyen">
                    <i class="bi bi-truck me-2"></i> Vận chuyển & Nhận hàng
                  </a>
              </li>
              <li>
                  <a href="#doi-tra">
                    <i class="bi bi-shield-check me-2"></i> Đổi trả & Bảo hành
                  </a>
              </li>
              <li>
                  <a href="#san-pham">
                    <i class="bi bi-box-seam me-2"></i> Thông tin sản phẩm
                  </a>
              </li>
            </ul>
          </div>

          <!-- Banner phụ hoặc Contact nhanh -->
          <div class="sidebar-widget contact-widget mt-4 text-center">
              <i class="bi bi-headset display-4 mb-3"></i>
              <h5>Cần hỗ trợ trực tiếp?</h5>
              <p class="small text-muted mb-3">Hotline hỗ trợ 24/7</p>
              <a href="tel:19001234" class="btn btn-primary w-100 fw-bold">1900 1234</a>
          </div>
        </aside>
        
        <!-- MAIN CONTENT (Bên phải) -->
        <section class="faq-content">
          
          <!-- GROUP 1: GIAO DỊCH -->
          <article id="giao-dich" class="faq-group">
            <h3 class="group-title">
                <i class="bi bi-credit-card-2-front me-2"></i> Giao dịch & Thanh toán
            </h3>
            <div class="accordion-list">
              
              <!-- Item 1 -->
              <div class="accordion-item" :class="{ 'is-active': activeQuestion === 'gd1' }">
                <button class="accordion-header" @click="toggleAccordion('gd1')">
                  <span class="fw-bold">Cửa hàng chấp nhận những hình thức thanh toán nào?</span>
                  <span class="icon-toggle">
                      <i class="bi" :class="activeQuestion === 'gd1' ? 'bi-dash-circle-fill' : 'bi-plus-circle'"></i>
                  </span>
                </button>
                <div class="accordion-collapse" :style="{ maxHeight: activeQuestion === 'gd1' ? '500px' : '0' }">
                    <div class="accordion-body">
                        <p>Chúng tôi chấp nhận đa dạng các hình thức thanh toán để thuận tiện nhất cho quý khách:</p>
                        <ul class="mb-0 ps-3">
                            <li><strong>COD:</strong> Thanh toán tiền mặt khi nhận hàng.</li>
                            <li><strong>Chuyển khoản:</strong> Qua Internet Banking tới tài khoản công ty.</li>
                            <li><strong>Ví điện tử:</strong> Momo, ZaloPay, VNPay (Quét mã QR).</li>
                            <li><strong>Thẻ quốc tế:</strong> Visa, Mastercard (Cổng thanh toán bảo mật).</li>
                        </ul>
                    </div>
                </div>
              </div>

              <!-- Item 2 -->
              <div class="accordion-item" :class="{ 'is-active': activeQuestion === 'gd2' }">
                <button class="accordion-header" @click="toggleAccordion('gd2')">
                  <span class="fw-bold">Làm thế nào để xuất hóa đơn VAT?</span>
                  <span class="icon-toggle">
                      <i class="bi" :class="activeQuestion === 'gd2' ? 'bi-dash-circle-fill' : 'bi-plus-circle'"></i>
                  </span>
                </button>
                <div class="accordion-collapse" :style="{ maxHeight: activeQuestion === 'gd2' ? '500px' : '0' }">
                    <div class="accordion-body">
                        <p>Quý khách vui lòng điền thông tin xuất hóa đơn (Tên công ty, MST, Địa chỉ) tại bước <strong>Thanh toán</strong> trong ô "Ghi chú" hoặc chọn tùy chọn "Xuất hóa đơn công ty". Hóa đơn điện tử sẽ được gửi qua email của quý khách sau khi đơn hàng hoàn tất.</p>
                    </div>
                </div>
              </div>

              <!-- Item 3 -->
              <div class="accordion-item" :class="{ 'is-active': activeQuestion === 'gd3' }">
                <button class="accordion-header" @click="toggleAccordion('gd3')">
                  <span class="fw-bold">Tôi có thể thanh toán trả góp không?</span>
                  <span class="icon-toggle">
                      <i class="bi" :class="activeQuestion === 'gd3' ? 'bi-dash-circle-fill' : 'bi-plus-circle'"></i>
                  </span>
                </button>
                <div class="accordion-collapse" :style="{ maxHeight: activeQuestion === 'gd3' ? '500px' : '0' }">
                    <div class="accordion-body">
                        <p>Có. Chúng tôi hỗ trợ trả góp 0% lãi suất qua thẻ tín dụng của hơn 25 ngân hàng liên kết. Kỳ hạn linh hoạt 3, 6, 9, 12 tháng. Áp dụng cho đơn hàng từ 3.000.000đ trở lên.</p>
                    </div>
                </div>
              </div>

            </div>
          </article>

          <!-- GROUP 2: VẬN CHUYỂN -->
          <article id="van-chuyen" class="faq-group">
            <h3 class="group-title">
                <i class="bi bi-truck text-primary me-2"></i> Vận chuyển & Nhận hàng
            </h3>
            <div class="accordion-list">
              <div class="accordion-item" :class="{ 'is-active': activeQuestion === 'vc1' }">
                <button class="accordion-header" @click="toggleAccordion('vc1')">
                  <span class="fw-bold">Thời gian giao hàng tiêu chuẩn là bao lâu?</span>
                  <span class="icon-toggle">
                      <i class="bi" :class="activeQuestion === 'vc1' ? 'bi-dash-circle-fill' : 'bi-plus-circle'"></i>
                  </span>
                </button>
                <div class="accordion-collapse" :style="{ maxHeight: activeQuestion === 'vc1' ? '500px' : '0' }">
                    <div class="accordion-body">
                        <p>Thời gian giao hàng dự kiến:</p>
                        <ul class="mb-0 ps-3">
                            <li><strong>Phường Buôn Ma Thuột:</strong> 1 - 2 ngày làm việc.</li>
                            <li><strong>Các tỉnh thành khác:</strong> 3 - 5 ngày làm việc.</li>
                        </ul>
                    </div>
                </div>
              </div>
              
               <div class="accordion-item" :class="{ 'is-active': activeQuestion === 'vc2' }">
                <button class="accordion-header" @click="toggleAccordion('vc2')">
                  <span class="fw-bold">Tôi có được kiểm tra hàng trước khi nhận?</span>
                  <span class="icon-toggle">
                      <i class="bi" :class="activeQuestion === 'vc2' ? 'bi-dash-circle-fill' : 'bi-plus-circle'"></i>
                  </span>
                </button>
                <div class="accordion-collapse" :style="{ maxHeight: activeQuestion === 'vc2' ? '500px' : '0' }">
                    <div class="accordion-body">
                        <p>Chắc chắn rồi! Chúng tôi khuyến khích quý khách đồng kiểm ngoại quan (kiểm tra tình trạng hộp, tem niêm phong) cùng shipper. Nếu hộp bị móp méo nặng hoặc có dấu hiệu bị bóc, quý khách vui lòng từ chối nhận hàng và liên hệ hotline.</p>
                    </div>
                </div>
              </div>
            </div>
          </article>
          
          <!-- GROUP 3: BẢO HÀNH -->
          <article id="doi-tra" class="faq-group">
            <h3 class="group-title">
                <i class="bi bi-shield-check text-primary me-2"></i> Đổi trả & Bảo hành
            </h3>
            <div class="accordion-list">
              <div class="accordion-item" :class="{ 'is-active': activeQuestion === 'dt1' }">
                <button class="accordion-header" @click="toggleAccordion('dt1')">
                  <span class="fw-bold">Chính sách đổi trả sản phẩm như thế nào?</span>
                  <span class="icon-toggle">
                      <i class="bi" :class="activeQuestion === 'dt1' ? 'bi-dash-circle-fill' : 'bi-plus-circle'"></i>
                  </span>
                </button>
                <div class="accordion-collapse" :style="{ maxHeight: activeQuestion === 'dt1' ? '500px' : '0' }">
                    <div class="accordion-body">
                        <p>Chúng tôi áp dụng chính sách <strong>1 đổi 1 trong 30 ngày đầu</strong> nếu sản phẩm có lỗi phần cứng từ nhà sản xuất. Sau 30 ngày, sản phẩm sẽ được bảo hành theo quy định của hãng.</p>
                    </div>
                </div>
              </div>
            </div>
          </article>

        </section>
      </div>
    </main>

    <footer class="faq-footer">
      <div class="faq-footer-inner">
        <p>Bạn vẫn còn thắc mắc? <a href="#contact">Liên hệ ngay</a> với đội ngũ tư vấn viên của chúng tôi.</p>
      </div>
    </footer>
  </section>
</template>
<style>
/* --- VARIABLES --- */
:root {
  --primary: #009981;
  --primary-dark: #007a67;
  --accent: #00483D;
  --text-dark: #2c3e50;
  --text-subtle: #636e72;
  --bg-light: #F8F9FA;
  --white: #FFFFFF;
}
</style>
<style scoped>
.sidebar-widget i{
  color: var(--primary);
}

/* --- BASE --- */
.faq-page {
  font-family: 'Inter', system-ui, sans-serif;
  color: var(--text-dark);
  background-color: var(--bg-light);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* --- HERO --- */
.faq-hero {
  background: linear-gradient(135deg, #e0f2f1 0%, #ffffff 100%);
  border-bottom: 1px solid #e0e0e0;
  padding: 60px 20px;
  text-align: center;
}
.faq-hero-inner {
  max-width: 800px;
  margin: 0 auto;
}
.faq-pre-title {
  color: var(--primary);
  font-weight: 700;
  letter-spacing: 2px;
  font-size: 0.85rem;
  margin-bottom: 10px;
}
.faq-hero h1 {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--accent);
  margin-bottom: 15px;
}
.faq-subtitle {
  color: var(--text-subtle);
  font-size: 1.1rem;
  line-height: 1.6;
}

/* --- CONTAINER & LAYOUT --- */
.faq-container {
  /* [EDIT] Xóa max-width cứng để dùng class .container của hệ thống */
  /* max-width: 1320px; */
  /* padding: 0 20px; */
  
  margin: 50px auto;
  flex-grow: 1;
}

.faq-layout {
  display: grid;
  grid-template-columns: 320px 1fr; /* Sidebar 320px để cân đối */
  gap: 48px; /* Gap rộng rãi 48px */
  align-items: start;
}

/* --- SIDEBAR --- */
.faq-sidebar {
  position: sticky;
  top: 20px;
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.sidebar-widget {
  background: var(--white);
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.03); /* Shadow nhẹ, tinh tế */
  border: 1px solid rgba(0,0,0,0.03);
}

.sidebar-widget h3 {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--accent);
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px dashed #eee;
  display: flex;
  align-items: center;
}

.nav-links {
  list-style: none;
  padding: 0;
  margin: 0;
}
.nav-links li {
  margin-bottom: 8px;
}
.nav-links a {
  display: flex;
  align-items: center;
  padding: 12px 15px;
  color: var(--text-dark);
  text-decoration: none;
  font-weight: 500;
  border-radius: 8px;
  transition: all 0.2s ease;
}
.nav-links a:hover {
  background-color: rgba(0, 153, 129, 0.08);
  color: var(--primary);
  transform: translateX(5px);
}
.nav-links i {
  font-size: 1.1rem;
  color: #999;
  transition: color 0.2s;
}
.nav-links a:hover i {
  color: var(--primary);
}

/* --- MAIN CONTENT --- */
.faq-content {
  min-height: 500px;
}

.faq-group {
  margin-bottom: 50px;
  scroll-margin-top: 100px; /* Để khi scroll tới ID không bị che bởi header */
}

.group-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--accent);
  margin-bottom: 25px;
  display: flex;
  align-items: center;
  padding-bottom: 10px;
  border-bottom: 2px solid #f0f0f0;
}

/* --- ACCORDION STYLES --- */
.accordion-list {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.accordion-item {
  background: var(--white);
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  border: 1px solid transparent;
  transition: all 0.3s ease;
}

.accordion-item.is-active {
  box-shadow: 0 8px 20px rgba(0, 153, 129, 0.1);
  border-color: rgba(0, 153, 129, 0.2);
}

.accordion-header {
  width: 100%;
  padding: 20px 25px;
  background: none;
  border: none;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  text-align: left;
  color: var(--text-dark);
  font-size: 1.05rem;
  transition: color 0.3s;
}

.accordion-item.is-active .accordion-header {
  color: var(--primary);
  background-color: #fafffe;
}

.icon-toggle i {
  font-size: 1.3rem;
  color: #ccc;
  transition: all 0.3s;
}
.accordion-item.is-active .icon-toggle i {
  color: var(--primary);
  transform: rotate(180deg);
}

.accordion-collapse {
  overflow: hidden;
  transition: max-height 0.4s cubic-bezier(0, 1, 0, 1);
}

.accordion-body {
  padding: 0 25px 25px 25px;
  color: var(--text-subtle);
  line-height: 1.7;
  background-color: #fafffe; /* Nền nhạt khi mở */
}
.accordion-body strong {
    color: var(--accent);
}

/* --- FOOTER --- */
.faq-footer {
  margin-top: auto;
  padding: 40px 20px;
  background-color: var(--white);
  border-top: 1px solid #eee;
  text-align: center;
}
.faq-footer p {
  font-size: 1.1rem;
  color: var(--text-subtle);
}
.faq-footer a {
  color: var(--primary);
  font-weight: 700;
  text-decoration: none;
  border-bottom: 2px solid var(--primary);
  padding-bottom: 2px;
  transition: color 0.2s;
}
.faq-footer a:hover {
  color: var(--accent);
  border-color: var(--accent);
}

/* --- UTILS --- */
.btn-primary {
    background-color: var(--primary);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-block;
    transition: background 0.2s;
}
.btn-primary:hover {
    background-color: var(--accent);
    color: white;
}

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
  .faq-layout {
    grid-template-columns: 1fr;
    gap: 30px;
  }
  .faq-sidebar {
    position: relative;
    top: 0;
    order: -1; /* Đưa sidebar lên đầu trên mobile nếu muốn, hoặc bỏ dòng này để nó nằm dưới */
    z-index: 1;
  }
}
</style>