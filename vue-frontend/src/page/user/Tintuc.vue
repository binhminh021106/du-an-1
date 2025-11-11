<template>
  <section class="blog-page">
    <header class="blog-hero">
      <div class="blog-hero-inner">
        <p class="blog-pre-title">THÔNG TIN & KIẾN THỨC CÔNG NGHỆ</p>
        <h1>Blog Tin tức</h1>
        <p class="blog-subtitle">
          Cập nhật những tin tức, đánh giá sản phẩm, và mẹo vặt công nghệ mới nhất từ đội ngũ chuyên gia của chúng tôi.
        </p>
      </div>
    </header>

    <main class="blog-container">
      <div class="blog-grid">
        
        <section class="posts-content">
          
          <article class="featured-post">
            <div class="featured-image">
              
            </div>
            <div class="featured-body">
              <span class="category-tag">ĐÁNH GIÁ SẢN PHẨM</span>
              <h2>Đánh giá chi tiết Laptop Flagship 2025: Sức mạnh và Thiết kế vượt trội</h2>
              <p class="excerpt">
                Chúng tôi đã có cơ hội trải nghiệm sớm mẫu laptop cao cấp nhất năm 2025. Từ hiệu năng xử lý, chất lượng màn hình đến thời lượng pin, đây là tất cả những gì bạn cần biết trước khi quyết định nâng cấp...
              </p>
              <div class="post-meta">
                <span><span class="meta-icon">📅</span> 09/11/2025</span>
                <span><span class="meta-icon">✍️</span> Tác giả: Minh Tuấn</span>
              </div>
              <a href="#featured-post-link" class="read-more-btn">Đọc tiếp <span class="arrow">→</span></a>
            </div>
          </article>

          <h2 class="section-title">Tin tức mới nhất</h2>
          <div class="latest-posts-grid">

            <article class="post-card">
              <div class="post-image-container">
                
              </div>
              <div class="post-card-body">
                <span class="category-tag">TIN TỨC</span>
                <h3>Smartphone màn hình gập mới: Xu hướng thiết kế nửa cuối năm 2025</h3>
                <p class="excerpt-small">Phân tích những cải tiến đáng chú ý về độ bền và công nghệ bản lề...</p>
                <a href="#post-1-link" class="read-more-link">Xem chi tiết</a>
              </div>
            </article>

            <article class="post-card">
              <div class="post-image-container">
                
              </div>
              <div class="post-card-body">
                <span class="category-tag">PHỤ KIỆN</span>
                <h3>Top 5 tai nghe True Wireless đáng mua nhất tầm giá 3 triệu đồng</h3>
                <p class="excerpt-small">Đánh giá chất âm, chống ồn và thời lượng pin của các mẫu tai nghe hot...</p>
                <a href="#post-2-link" class="read-more-link">Xem chi tiết</a>
              </div>
            </article>

            <article class="post-card">
              <div class="post-image-container">
                
              </div>
              <div class="post-card-body">
                <span class="category-tag">MẸO VẶT</span>
                <h3>Cách tối ưu hóa pin cho Smartwatch của bạn hoạt động cả tuần</h3>
                <p class="excerpt-small">Các cài đặt ẩn giúp đồng hồ thông minh của bạn kéo dài thời gian sử dụng...</p>
                <a href="#post-3-link" class="read-more-link">Xem chi tiết</a>
              </div>
            </article>

          </div>
          
          </section>
        
        <aside class="blog-sidebar">
          
          <div class="sidebar-widget search-widget">
            <h4><span class="sidebar-icon">🔍</span> Tìm kiếm</h4>
            <input type="text" placeholder="Nhập từ khóa..." class="search-input">
            <button class="search-btn">Tìm</button>
          </div>
          
          <div class="sidebar-widget category-widget">
            <h4><span class="sidebar-icon">🏷️</span> Danh mục</h4>
            <ul>
              <li><a href="#cat-review">Đánh giá sản phẩm (14)</a></li>
              <li><a href="#cat-news">Tin tức mới (22)</a></li>
              <li><a href="#cat-tips">Mẹo & Thủ thuật (10)</a></li>
              <li><a href="#cat-gaming">Gaming Gear (5)</a></li>
              <li><a href="#cat-accessory">Phụ kiện (8)</a></li>
            </ul>
          </div>

          <div class="sidebar-widget popular-widget">
            <h4><span class="sidebar-icon">🔥</span> Bài viết phổ biến</h4>
            <div class="popular-post-item">
                <p>1. 6 điểm khác biệt giữa OLED và Mini LED</p>
                <span class="post-meta-small">5,200 lượt xem</span>
            </div>
             <div class="popular-post-item">
                <p>2. Hướng dẫn backup dữ liệu điện thoại an toàn</p>
                <span class="post-meta-small">4,150 lượt xem</span>
            </div>
          </div>
          
        </aside>
      </div>
    </main>

  </section>
</template>

<style scoped>
:root {
  --primary: #009981;
  --accent: #00483D;
  --text-dark: #263238;
  --text-subtle: #546E7A;
  --bg-light: #F4F6F8;
  --card-bg: #FFFFFF;
}

.blog-page {
  font-family: 'Inter', 'Roboto', system-ui, sans-serif;
  color: var(--text-dark);
  background-color: var(--bg-light);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.blog-hero {
  background: linear-gradient(135deg, rgba(0, 153, 129, 0.1), rgba(0, 72, 61, 0.05));
  border-bottom: 4px solid var(--primary);
  padding: 80px 16px 50px 16px;
  text-align: center;
}
.blog-hero-inner {
  max-width: 900px;
  margin: 0 auto;
}
.blog-pre-title {
  color: var(--primary);
  font-size: 0.9rem;
  letter-spacing: 2px;
  text-transform: uppercase;
  font-weight: 700;
  margin-bottom: 10px;
}
.blog-hero h1 {
  color: var(--accent);
  font-size: 3rem;
  font-weight: 800;
  margin: 0 0 15px 0;
}
.blog-subtitle {
  color: var(--text-subtle);
  font-size: 1.1rem;
  max-width: 700px;
  margin: 0 auto;
  line-height: 1.6;
}

.blog-container {
  max-width: 1280px;
  margin: 50px auto;
  padding: 0 30px;
  flex-grow: 1;
}
.blog-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 40px;
}
.section-title {
    color: var(--accent);
    font-size: 1.8rem;
    font-weight: 700;
    margin-top: 40px;
    margin-bottom: 30px;
    border-bottom: 3px solid var(--primary);
    padding-bottom: 5px;
    display: inline-block;
}

.featured-post {
    display: flex;
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    margin-bottom: 40px;
    transition: transform 0.3s ease;
}
.featured-post:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.featured-image {
    width: 55%;
    min-height: 400px;
    background-color: #E0E0E0;
    background-size: cover;
    background-position: center;
}
.featured-body {
    width: 45%;
    padding: 30px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.featured-body h2 {
    color: var(--accent);
    font-size: 2rem;
    font-weight: 800;
    margin: 10px 0 15px 0;
    line-height: 1.3;
}
.excerpt {
    color: var(--text-subtle);
    line-height: 1.7;
    margin-bottom: 20px;
}
.post-meta {
    font-size: 0.9rem;
    color: var(--text-subtle);
    margin-top: 10px;
    margin-bottom: 15px;
}
.post-meta span {
    margin-right: 15px;
}
.meta-icon {
    margin-right: 5px;
    color: var(--primary);
}
.read-more-btn {
    display: inline-block;
    color: var(--primary);
    text-decoration: none;
    font-weight: 700;
    font-size: 1rem;
    transition: color 0.2s;
    border-bottom: 2px solid var(--primary);
    padding-bottom: 2px;
    align-self: flex-start;
}
.read-more-btn:hover {
    color: var(--accent);
    border-bottom-color: var(--accent);
}
.arrow {
    margin-left: 5px;
    font-weight: 900;
}

.latest-posts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}
.post-card {
    background: var(--card-bg);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.06);
    transition: box-shadow 0.3s ease, transform 0.3s ease;
}
.post-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.post-image-container {
    height: 200px;
    background-color: #ECEFF1;
    background-size: cover;
    background-position: center;
    border-bottom: 3px solid var(--primary);
}
.post-card-body {
    padding: 20px;
}
.post-card-body h3 {
    color: var(--accent);
    font-size: 1.25rem;
    font-weight: 700;
    margin: 8px 0 10px 0;
    line-height: 1.4;
}
.excerpt-small {
    color: var(--text-subtle);
    font-size: 0.95rem;
    margin-bottom: 15px;
}
.read-more-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    border-bottom: 1px solid var(--primary);
    transition: color 0.2s;
}
.category-tag {
    display: inline-block;
    background-color: var(--primary);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 4px;
    letter-spacing: 0.5px;
}

.blog-sidebar {
    height: fit-content;
    display: flex;
    flex-direction: column;
    gap: 30px;
}
.sidebar-widget {
    background: var(--card-bg);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.sidebar-widget h4 {
    color: var(--accent);
    font-size: 1.3rem;
    font-weight: 700;
    margin-top: 0;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--bg-light);
    padding-bottom: 10px;
}
.sidebar-icon {
    font-size: 1.1em;
    margin-right: 8px;
    color: var(--primary);
}

.search-input {
    width: 100%;
    padding: 12px;
    border: 1px solid #CFD8DC;
    border-radius: 6px;
    margin-bottom: 10px;
    font-size: 1rem;
    box-sizing: border-box;
}
.search-btn {
    width: 100%;
    padding: 12px;
    background-color: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 0.2s;
}
.search-btn:hover {
    background-color: var(--accent);
}

.category-widget ul {
    list-style: none;
    padding: 0;
}
.category-widget li {
    margin-bottom: 8px;
    border-bottom: 1px dashed #ECEFF1;
    padding-bottom: 8px;
}
.category-widget a {
    color: var(--text-dark);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}
.category-widget a:hover {
    color: var(--primary);
    padding-left: 5px;
}

.popular-post-item {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px dotted #CFD8DC;
}
.popular-post-item p {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
}
.post-meta-small {
    font-size: 0.85rem;
    color: var(--text-subtle);
}

.blog-footer {
    margin-top: 50px;
    padding: 20px 16px;
    border-top: 1px solid #E0E0E0;
    background-color: var(--card-bg);
    text-align: center;
}
.blog-footer-inner p {
    color: var(--text-subtle);
    font-size: 0.95rem;
}

@media (max-width: 1100px) {
    .blog-grid {
        grid-template-columns: 1fr;
    }
    .blog-sidebar {
        order: -1;
    }
}
@media (max-width: 900px) {
    .featured-post {
        flex-direction: column;
    }
    .featured-image, .featured-body {
        width: 100%;
        min-height: 250px;
    }
    .featured-body {
        padding: 25px;
    }
    .featured-body h2 {
        font-size: 1.6rem;
    }
    .latest-posts-grid {
        grid-template-columns: 1fr;
    }
}
</style>