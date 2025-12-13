<script setup>
import { onMounted, watch, nextTick } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

// Hàm xử lý cuộn: Nhận tham số targetHash (tùy chọn) để xử lý khi click trực tiếp
const scrollToSection = (targetHash) => {
  // Nếu targetHash là chuỗi (khi click menu) thì dùng nó, nếu không thì lấy từ URL hiện tại (khi load trang)
  const hash = (typeof targetHash === 'string') ? targetHash : route.hash;

  if (hash) {
    // Nếu là hành động click, cập nhật URL thủ công để người dùng có thể copy link chia sẻ
    if (typeof targetHash === 'string') {
      history.pushState(null, null, hash);
    }

    // Xóa dấu # để lấy ID
    const id = hash.slice(1); 
    const element = document.getElementById(id);
    
    if (element) {
      // Tính toán vị trí để cuộn (trừ đi chiều cao header = 100px)
      const headerOffset = 100; 
      const elementPosition = element.getBoundingClientRect().top;
      const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

      window.scrollTo({
        top: offsetPosition,
        behavior: "smooth"
      });
    }
  }
};

onMounted(() => {
  document.documentElement.style.scrollBehavior = 'smooth';
  // Gọi hàm cuộn khi trang vừa load xong (nếu URL đã có sẵn hash từ trang khác chuyển sang)
  setTimeout(() => {
    scrollToSection();
  }, 300);
});

// Lắng nghe sự thay đổi của Hash (dành cho router-link từ footer hoặc nơi khác)
watch(() => route.hash, () => {
  nextTick(() => {
    scrollToSection();
  });
});
</script>

<template>
  <section class="policy-page">
    
    <!-- HERO HEADER -->
    <header class="policy-hero">
      <div class="hero-inner">
        <p class="hero-pre-title">QUY TẮC & QUYỀN LỢI</p>
        <h1>Chính sách cửa hàng</h1>
        <p class="hero-subtitle">
          Tổng hợp chi tiết về quy định Vận chuyển, Bảo hành, Đổi trả và Bảo mật thông tin nhằm đảm bảo quyền lợi tối đa cho khách hàng.
        </p>
      </div>
    </header>

    <!-- [EDIT] Thêm class 'container' để đồng bộ width với Header -->
    <main class="policy-container container">
      <div class="policy-layout">
        
        <!-- SIDEBAR TOC -->
        <aside class="policy-sidebar">
          <div class="sidebar-widget">
            <h3><i class="bi bi-list-columns-reverse me-2"></i> Mục lục chính sách</h3>
            <ul class="toc-links">
              <!-- SỬA ĐỔI: Truyền trực tiếp ID vào hàm scrollToSection -->
              <li><a href="#shipping-v2" @click.prevent="scrollToSection('#shipping-v2')"><i class="bi bi-truck me-2"></i> Vận chuyển & Giao hàng</a></li>
              <li><a href="#guide-v2" @click.prevent="scrollToSection('#guide-v2')"><i class="bi bi-cart-check me-2"></i> Hướng dẫn mua hàng</a></li>
              <li><a href="#returns-v2" @click.prevent="scrollToSection('#returns-v2')"><i class="bi bi-arrow-repeat me-2"></i> Đổi trả & Hoàn tiền</a></li>
              <li><a href="#warranty-v2" @click.prevent="scrollToSection('#warranty-v2')"><i class="bi bi-shield-check me-2"></i> Chính sách bảo hành</a></li>
              <li><a href="#privacy-v2" @click.prevent="scrollToSection('#privacy-v2')"><i class="bi bi-lock me-2"></i> Bảo mật thông tin</a></li>
              <li><a href="#terms-v2" @click.prevent="scrollToSection('#terms-v2')"><i class="bi bi-file-earmark-text me-2"></i> Điều khoản sử dụng</a></li>
              <li><a href="#contact-v2" @click.prevent="scrollToSection('#contact-v2')"><i class="bi bi-headset me-2"></i> Liên hệ hỗ trợ</a></li>
            </ul>
          </div>

          <!-- Box hỗ trợ nhanh -->
          <div class="sidebar-widget support-box mt-4">
             <div class="d-flex align-items-center mb-3">
                 <i class="bi bi-question-circle-fill fs-3 me-3"></i>
                 <div>
                     <h6 class="mb-0 fw-bold">Cần giải đáp gấp?</h6>
                     <small class="text-muted">Chat ngay với tư vấn viên</small>
                 </div>
             </div>
             <button class="btn btn-outline-primary w-100 btn-sm fw-bold">Chat Zalo/Messenger</button>
          </div>
        </aside>

        <!-- MAIN CONTENT -->
        <section class="policy-content">
          
          <!-- 1. VẬN CHUYỂN -->
          <article id="shipping-v2" class="policy-card">
            <h2 class="policy-title">
                <i class="bi bi-truck me-2"></i> Vận chuyển & Giao hàng
            </h2>
            <div class="policy-body">
                <p>
                  Chúng tôi hợp tác với các đơn vị vận chuyển uy tín (Giao Hàng Nhanh, Viettel Post) để cung cấp dịch vụ giao hàng tiêu chuẩn trên <strong>toàn quốc</strong>.
                </p>
                <ul class="info-list">
                    <li><strong>Thời gian xử lý:</strong> 1 - 2 ngày làm việc.</li>
                    <li><strong>Thời gian vận chuyển:</strong> 1 - 5 ngày tùy khu vực.</li>
                    <li><strong>Phí vận chuyển:</strong> Tính tự động tại bước thanh toán dựa trên cân nặng và địa lý.</li>
                </ul>
                <div class="highlight-box">
                  <p class="mb-2"><strong><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Lưu ý quan trọng khi nhận hàng:</strong></p>
                  <ul class="mb-0 ps-3">
                    <li>Kiểm tra kỹ tình trạng ngoại quan (hộp, tem niêm phong) trước khi ký nhận.</li>
                    <li>Nếu thấy dấu hiệu móp méo, rách hoặc bị bóc, vui lòng <strong>từ chối nhận hàng</strong> và liên hệ Hotline ngay lập tức.</li>
                    <li>Khuyến khích quay video clip quá trình mở hộp để làm bằng chứng khiếu nại nếu có sai sót bên trong.</li>
                  </ul>
                </div>
            </div>
          </article>

          <!-- 2. HƯỚNG DẪN MUA HÀNG -->
          <article id="guide-v2" class="policy-card">
            <h2 class="policy-title">
                <i class="bi bi-cart-check me-2"></i> Hướng dẫn Mua hàng
            </h2>
            <div class="policy-body">
                <div class="steps-container">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <strong>Chọn sản phẩm</strong>
                            <p>Xem chi tiết và chọn phiên bản phù hợp.</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <strong>Đặt hàng</strong>
                            <p>Thêm vào giỏ & điền thông tin nhận hàng.</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <strong>Thanh toán</strong>
                            <p>Chọn COD hoặc Chuyển khoản/Thẻ.</p>
                        </div>
                    </div>
                      <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <strong>Xác nhận</strong>
                            <p>Nhận email và cuộc gọi xác nhận từ CSKH.</p>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-muted small fst-italic">
                    * Nếu gặp khó khăn khi đặt hàng online, quý khách có thể gọi Hotline <strong>1900-0000</strong> để nhân viên hỗ trợ lên đơn trực tiếp.
                </p>
            </div>
          </article>

          <!-- 3. ĐỔI TRẢ -->
          <article id="returns-v2" class="policy-card">
             <h2 class="policy-title">
                <i class="bi bi-arrow-repeat me-2"></i> Đổi trả & Hoàn tiền
            </h2>
             <div class="policy-body">
                <p>
                  Chính sách đổi trả áp dụng trong vòng <strong>7 ngày</strong> (tính từ ngày nhận hàng) cho các trường hợp:
                </p>
                <ul class="check-list">
                    <li>Sản phẩm gặp lỗi kỹ thuật do nhà sản xuất (nguồn, màn hình, mainboard...).</li>
                    <li>Giao sai sản phẩm, sai màu sắc hoặc thiếu phụ kiện so với đơn hàng.</li>
                </ul>
                <p>
                  <strong>Điều kiện đổi trả:</strong> Sản phẩm phải còn nguyên vẹn tem bảo hành, hộp, đầy đủ phụ kiện và quà tặng kèm (nếu có). Không có dấu hiệu trầy xước, cấn móp do tác động vật lý của người dùng.
                </p>
            </div>
          </article>

          <!-- 4. BẢO HÀNH -->
          <article id="warranty-v2" class="policy-card">
             <h2 class="policy-title">
                <i class="bi bi-shield-check me-2"></i> Bảo hành sản phẩm
            </h2>
             <div class="policy-body">
                <p>
                  Tất cả sản phẩm chính hãng (Apple, Samsung, Xiaomi, Laptop...) đều được hưởng chế độ bảo hành theo quy định của <strong>Nhà sản xuất/Nhà phân phối</strong> tại Việt Nam.
                </p>
                  <div class="row mt-3">
                      <div class="col-md-6 mb-3">
                          <div class="p-3 border rounded bg-light h-100">
                              <h6 class="fw-bold text-dark"><i class="bi bi-phone me-1"></i> Thiết bị chính</h6>
                              <p class="mb-0 small text-secondary">Bảo hành 12 - 24 tháng tùy hãng.</p>
                          </div>
                      </div>
                      <div class="col-md-6 mb-3">
                          <div class="p-3 border rounded bg-light h-100">
                              <h6 class="fw-bold text-dark"><i class="bi bi-headphones me-1"></i> Phụ kiện kèm theo</h6>
                              <p class="mb-0 small text-secondary">Bảo hành 3 - 6 tháng (Cáp, sạc, pin...).</p>
                          </div>
                      </div>
                  </div>
            </div>
          </article>

          <!-- 5. BẢO MẬT -->
          <article id="privacy-v2" class="policy-card">
             <h2 class="policy-title">
                <i class="bi bi-lock me-2"></i> Quyền riêng tư & Bảo mật
            </h2>
            <div class="policy-body">
                <p>
                  Chúng tôi cam kết bảo mật tuyệt đối thông tin cá nhân của khách hàng. Thông tin chỉ được sử dụng cho mục đích xử lý đơn hàng và chăm sóc khách hàng.
                </p>
                <p>
                  Chúng tôi <strong>TUYỆT ĐỐI KHÔNG</strong> chia sẻ, bán hoặc tiết lộ thông tin của quý khách cho bên thứ ba vì mục đích thương mại. Mọi giao dịch thanh toán trực tuyến đều được mã hóa qua cổng thanh toán an toàn.
                </p>
            </div>
          </article>

          <!-- 6. ĐIỀU KHOẢN -->
           <article id="terms-v2" class="policy-card">
             <h2 class="policy-title">
                <i class="bi bi-file-earmark-text ary me-2"></i> Điều khoản sử dụng
            </h2>
            <div class="policy-body">
                <p>
                  Khi truy cập website và đặt hàng, quý khách mặc nhiên đồng ý với các điều khoản hoạt động của chúng tôi. Chúng tôi bảo lưu quyền thay đổi chính sách bất cứ lúc nào để phù hợp với quy định pháp luật và hoạt động kinh doanh, thông báo sẽ được cập nhật tại trang này.
                </p>
            </div>
          </article>

          <!-- 7. LIÊN HỆ -->
          <article id="contact-v2" class="policy-card contact-section">
             <h2 class="policy-title text-white border-0 mb-4">
                <i class="bi bi-headset me-2"></i> Thông tin Liên hệ
            </h2>
            <div class="contact-grid">
                <div class="contact-item">
                    <i class="bi bi-telephone-fill fs-2 mb-2 opacity-75"></i>
                    <h6>Hotline</h6>
                    <p class="fw-bold fs-5">1900-0000</p>
                </div>
                 <div class="contact-item">
                    <i class="bi bi-envelope-fill fs-2 mb-2 opacity-75"></i>
                    <h6>Email</h6>
                    <p class="fw-bold">support@shop.com</p>
                </div>
                 <div class="contact-item">
                    <i class="bi bi-geo-alt-fill fs-2 mb-2 opacity-75"></i>
                    <h6>Văn phòng</h6>
                    <p class="fw-bold">Q.1, TP.HCM</p>
                </div>
            </div>
          </article>

        </section>
      </div>
    </main>
    
  </section>
</template>

<style scoped>
/* --- VARIABLES (Đồng bộ) --- */
:root {
  --primary: #009981;
  --primary-dark: #007a67;
  --accent: #00483D;
  --text-dark: #2c3e50;
  --text-gray: #636e72;
  --bg-light: #F8F9FA;
  --white: #FFFFFF;
}

/* --- BASE --- */
.policy-page {
  font-family: 'Inter', system-ui, sans-serif;
  color: var(--text-dark);
  background-color: var(--bg-light);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* --- HERO HEADER --- */
.policy-hero {
  background: linear-gradient(135deg, #e0f2f1 0%, #ffffff 100%);
  border-bottom: 1px solid #e0e0e0;
  padding: 60px 20px;
  text-align: center;
}
.hero-inner {
  max-width: 800px;
  margin: 0 auto;
}
.hero-pre-title {
  color: var(--primary);
  font-weight: 700;
  letter-spacing: 2px;
  font-size: 0.85rem;
  margin-bottom: 10px;
}
.policy-hero h1 {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--accent);
  margin-bottom: 15px;
}
.hero-subtitle {
  color: var(--text-gray);
  font-size: 1.1rem;
  line-height: 1.6;
}

/* --- LAYOUT --- */
.policy-container {
  /* [EDIT] Xóa max-width cứng và padding để dùng chung chuẩn với class .container */
  /* max-width: 1320px; */
  /* padding: 0 20px; */
  
  margin: 50px auto;
  flex-grow: 1;
}

.policy-layout {
  display: grid;
  grid-template-columns: 320px 1fr; /* Sidebar 320px */
  gap: 48px;
  align-items: start;
}

/* --- SIDEBAR --- */
.policy-sidebar {
  position: sticky;
  top: 80px; /* Cập nhật để tránh bị dính sát mép trên */
}

.sidebar-widget {
  background: var(--white);
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.03);
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

.toc-links {
  list-style: none;
  padding: 0;
  margin: 0;
}
.toc-links li {
  margin-bottom: 5px;
}
.toc-links a {
  display: flex;
  align-items: center;
  padding: 10px 15px;
  color: var(--text-dark);
  text-decoration: none;
  font-weight: 500;
  border-radius: 8px;
  transition: all 0.2s;
}
.toc-links a:hover {
  background-color: rgba(0, 153, 129, 0.08);
  color: var(--primary);
  transform: translateX(5px);
}
.toc-links i {
  color: #999;
  transition: color 0.2s;
}
.toc-links a:hover i {
  color: var(--primary);
}

/* --- CONTENT --- */
.policy-content {
    display: flex;
    flex-direction: column;
    gap: 40px;
}

.policy-card {
  background: var(--white);
  padding: 35px;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.04);
  scroll-margin-top: 100px; /* Quan trọng: Giúp khi scroll không bị che bởi header */
}

.policy-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--accent);
  margin-top: 0;
  margin-bottom: 25px;
  padding-bottom: 15px;
  border-bottom: 2px solid #f0f0f0;
  display: flex;
  align-items: center;
}

.policy-body {
    color: var(--text-dark);
    line-height: 1.7;
}
.policy-body p {
    margin-bottom: 15px;
}

/* --- UTILS & ELEMENTS --- */
.info-list, .check-list {
    margin-bottom: 20px;
    padding-left: 20px;
}
.info-list li {
    margin-bottom: 8px;
    list-style-type: disc;
}
.check-list li {
    margin-bottom: 8px;
    list-style-type: none;
    position: relative;
    padding-left: 5px;
}
.check-list li::before {
    content: "✔";
    color: var(--primary);
    font-weight: bold;
    margin-right: 8px;
}

.highlight-box {
    background-color: #e0f2f1; /* Xanh rất nhạt */
    border-left: 4px solid var(--primary);
    padding: 20px;
    border-radius: 6px;
    margin-top: 20px;
}

/* Steps Guide */
.steps-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.step-item {
    text-align: center;
    position: relative;
}
.step-number {
    width: 40px;
    height: 40px;
    background-color: var(--primary);
    color: white;
    font-weight: 700;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px auto;
    font-size: 1.1rem;
}
.step-content strong {
    display: block;
    color: var(--accent);
    margin-bottom: 5px;
}
.step-content p {
    font-size: 0.9rem;
    color: var(--text-gray);
    line-height: 1.4;
    margin-bottom: 0;
}

/* Contact Card Special */
.contact-section {
    background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
    color: white;
}
.contact-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    text-align: center;
}
.contact-item h6 {
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 1px;
    margin-bottom: 5px;
    opacity: 0.9;
}

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
  .policy-layout {
    grid-template-columns: 1fr;
  }
  .policy-sidebar {
      position: relative;
      top: 0;
      margin-bottom: 30px;
  }
  .contact-grid {
      grid-template-columns: 1fr;
      gap: 30px;
  }
}
</style>