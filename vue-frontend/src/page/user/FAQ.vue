<template>
  <section class="faq-page">
    <header class="faq-hero">
      <div class="faq-hero-inner">
        <p class="faq-pre-title">TƯ VẤN & GIẢI ĐÁP</p>
        <h1>Câu hỏi thường gặp (FAQ)</h1>
        <p class="faq-subtitle">
          Tìm câu trả lời nhanh chóng cho các thắc mắc về sản phẩm, giao dịch, vận chuyển, đổi trả và bảo hành tại cửa hàng chúng tôi.
        </p>
      </div>
    </header>

    <main class="faq-container">
      <div class="faq-grid">
        <aside class="faq-sidebar">
          <h3><span class="sidebar-icon">💡</span> Danh mục chính</h3>
          <ul>
            <li><a href="#giao-dich">Giao dịch & Thanh toán</a></li>
            <li><a href="#van-chuyen">Vận chuyển & Nhận hàng</a></li>
            <li><a href="#doi-tra">Đổi trả & Bảo hành</a></li>
            <li><a href="#san-pham">Thông tin sản phẩm</a></li>
          </ul>
        </aside>
        
        <section class="faq-content">
          <h2 class="content-heading">Tất cả câu hỏi & giải đáp</h2>

          <article id="giao-dich" class="faq-category">
            <h3 class="category-title"># Giao dịch & Thanh toán</h3>
            <div class="accordion-group">
              
              <div class="accordion-item">
                <button class="accordion-header">
                  Cửa hàng chấp nhận những hình thức thanh toán nào?
                  <span class="icon">+</span>
                </button>
                <div class="accordion-body">
                  <p>Chúng tôi chấp nhận Thanh toán khi nhận hàng (COD), Chuyển khoản ngân hàng, và Thanh toán qua các ví điện tử phổ biến (Momo, ZaloPay, VNPay).</p>
                </div>
              </div>

              <div class="accordion-item">
                <button class="accordion-header active">
                  Làm thế nào để xuất hóa đơn VAT?
                  <span class="icon">—</span>
                </button>
                <div class="accordion-body is-open">
                  <p>Quý khách vui lòng cung cấp thông tin công ty (Tên, Mã số thuế, Địa chỉ) tại bước thanh toán. Hóa đơn VAT sẽ được gửi kèm sản phẩm hoặc qua email.</p>
                </div>
              </div>
              
              <div class="accordion-item">
                <button class="accordion-header">
                  Tôi có thể thanh toán trả góp không?
                  <span class="icon">+</span>
                </button>
                <div class="accordion-body">
                  <p>Có. Chúng tôi hỗ trợ trả góp 0% qua thẻ tín dụng của hơn 20 ngân hàng lớn. Vui lòng chọn hình thức trả góp khi thanh toán.</p>
                </div>
              </div>

            </div>
          </article>

          <article id="van-chuyen" class="faq-category">
            <h3 class="category-title"># Vận chuyển & Nhận hàng</h3>
            <div class="accordion-group">
              
              <div class="accordion-item">
                <button class="accordion-header">
                  Thời gian giao hàng tiêu chuẩn là bao lâu?
                  <span class="icon">+</span>
                </button>
                <div class="accordion-body">
                  <p>Thời gian xử lý đơn hàng là <strong>1-2 ngày làm việc</strong>. Thời gian vận chuyển thường là <strong>1-5 ngày</strong> tùy thuộc vào vị trí địa lý của quý khách.</p>
                </div>
              </div>
              
              <div class="accordion-item">
                <button class="accordion-header">
                  Tôi có thể kiểm tra hàng trước khi thanh toán (COD) không?
                  <span class="icon">+</span>
                </button>
                <div class="accordion-body">
                  <p>Có. Với đơn hàng COD, quý khách được quyền kiểm tra ngoại quan kiện hàng trước khi thanh toán. Nếu có bất thường (rách, móp), vui lòng từ chối nhận.</p>
                </div>
              </div>
              
            </div>
          </article>
          
          <article id="doi-tra" class="faq-category">
            <h3 class="category-title"># Đổi trả & Bảo hành</h3>
            <div class="accordion-group">
              
              <div class="accordion-item">
                <button class="accordion-header">
                  Chính sách đổi trả sản phẩm trong bao lâu?
                  <span class="icon">+</span>
                </button>
                <div class="accordion-body">
                  <p>Chính sách đổi trả áp dụng trong vòng <strong>7 ngày</strong> kể từ ngày nhận hàng, chỉ áp dụng cho trường hợp lỗi kỹ thuật do nhà sản xuất hoặc giao sai sản phẩm.</p>
                </div>
              </div>
              
              <div class="accordion-item">
                <button class="accordion-header">
                  Sản phẩm được bảo hành theo tiêu chuẩn nào?
                  <span class="icon">+</span>
                </button>
                <div class="accordion-body">
                  <p>Tất cả sản phẩm chính hãng đều được bảo hành theo đúng <strong>chính sách của Nhà sản xuất</strong> tại Việt Nam. Vui lòng xem mô tả sản phẩm để biết chi tiết thời gian bảo hành cụ thể.</p>
                </div>
              </div>
              
            </div>
          </article>
        </section>
      </div>
    </main>

    <footer class="faq-footer">
      <div class="faq-footer-inner">
        <p>Không tìm thấy câu trả lời? Vui lòng <a href="#contact">Liên hệ ngay</a> với đội ngũ hỗ trợ của chúng tôi.</p>
      </div>
    </footer>
  </section>
</template>

<style scoped>
:root {
  --primary: #009981; /* rgb(0, 153, 129) */
  --accent: #00483D; /* rgb(0, 72, 61) */
  --text-dark: #263238;
  --text-subtle: #546E7A;
  --bg-light: #F8F9FA;
  --card-bg: #FFFFFF;
}

.faq-page {
  font-family: 'Inter', 'Roboto', system-ui, sans-serif;
  color: var(--text-dark);
  background-color: var(--bg-light);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.faq-hero {
  background: linear-gradient(135deg, rgba(0, 153, 129, 0.1), rgba(0, 72, 61, 0.05));
  border-bottom: 4px solid var(--primary);
  padding: 80px 16px 50px 16px;
  text-align: center;
}
.faq-hero-inner {
  max-width: 900px;
  margin: 0 auto;
}
.faq-pre-title {
  color: var(--primary);
  font-size: 0.9rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  font-weight: 700;
  margin-bottom: 10px;
}
.faq-hero h1 {
  color: var(--accent);
  font-size: 2.8rem;
  font-weight: 800;
  margin: 0 0 15px 0;
}
.faq-subtitle {
  color: var(--text-subtle);
  font-size: 1.1rem;
  max-width: 700px;
  margin: 0 auto;
  line-height: 1.6;
}

.faq-container {
  max-width: 1200px;
  margin: 40px auto;
  padding: 0 24px;
  flex-grow: 1;
}

.faq-grid {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 40px;
}

.faq-sidebar {
    background: var(--card-bg);
    border: 1px solid #E0E0E0;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    height: fit-content;
    position: sticky;
    top: 20px;
}
.faq-sidebar h3 {
    color: var(--accent);
    font-size: 1.15rem;
    font-weight: 700;
    border-bottom: 2px solid var(--bg-light);
    padding-bottom: 10px;
    margin-top: 0;
    margin-bottom: 15px;
}
.sidebar-icon {
    font-size: 1.2em;
    margin-right: 8px;
    color: var(--primary);
}
.faq-sidebar ul {
    list-style: none;
    padding: 0;
}
.faq-sidebar li {
    margin: 10px 0;
}
.faq-sidebar a {
    color: var(--text-dark);
    text-decoration: none;
    font-weight: 500;
    display: block;
    padding: 8px 10px;
    border-radius: 6px;
    transition: all 0.2s;
}
.faq-sidebar a:hover {
    background-color: rgba(0, 153, 129, 0.1);
    color: var(--primary);
    padding-left: 15px;
}

.content-heading {
    color: var(--accent);
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 30px;
    border-bottom: 2px solid var(--primary);
    display: inline-block;
    padding-bottom: 5px;
}
.faq-category {
    margin-bottom: 40px;
}
.category-title {
    color: var(--primary);
    font-size: 1.4rem;
    font-weight: 700;
    margin-bottom: 20px;
    padding-left: 10px;
    border-left: 4px solid var(--primary);
}

.accordion-group {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
.accordion-item {
    border-top: 1px solid #ECEFF1;
}
.accordion-item:first-child {
    border-top: none;
}
.accordion-header {
    width: 100%;
    padding: 20px 25px;
    text-align: left;
    background-color: var(--card-bg);
    color: var(--text-dark);
    font-size: 1.1rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.accordion-header.active {
    background-color: #F0F4F6;
    color: var(--primary);
}
.accordion-header:hover {
    background-color: #F0F4F6;
}

.accordion-header .icon {
    font-size: 1.5rem;
    color: var(--primary);
    font-weight: 900;
    transition: transform 0.3s;
}
.accordion-header.active .icon {
    transform: rotate(45deg);
}

.accordion-body {
    background-color: #F8FCF9;
    padding: 0 25px;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease-in-out, padding 0.4s ease-in-out;
}
.accordion-body.is-open {
    max-height: 500px; 
    padding: 15px 25px 30px 25px;
    border-top: 1px dashed #B2DFDB;
}
.accordion-body p {
    margin: 0;
    line-height: 1.7;
    color: var(--text-subtle);
}

.faq-footer {
    margin-top: auto;
    padding: 25px 16px;
    border-top: 1px solid #E0E0E0;
    background-color: var(--card-bg);
    text-align: center;
}
.faq-footer-inner p {
    font-size: 1rem;
    color: var(--text-dark);
}
.faq-footer-inner a {
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
    border-bottom: 2px solid var(--primary);
    padding-bottom: 2px;
}

@media (max-width: 1000px) {
    .faq-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    .faq-sidebar {
        order: -1;
        position: relative;
        top: auto;
    }
}
@media (max-width: 600px) {
    .faq-hero h1 {
        font-size: 2.2rem;
    }
    .accordion-header {
        font-size: 1rem;
        padding: 15px 20px;
    }
    .faq-container {
        padding: 0 15px;
    }
}
</style>