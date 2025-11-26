<script setup>
import { ref, computed, onMounted } from 'vue';
// Import apiService mà chúng ta đã cấu hình (hoặc import axios trực tiếp nếu muốn)
import apiService from '../../apiService.js';

// --- CONFIG ---
const BACKEND_URL = 'http://127.0.0.1:8000'; // Đổi theo port thực tế của bạn

// --- STATE ---
const posts = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');

// --- COMPUTED ---
// 1. Bài viết nổi bật (Lấy bài đầu tiên trong danh sách)
const featuredPost = computed(() => {
  return posts.value.length > 0 ? posts.value[0] : null;
});

// 2. Danh sách tin tức mới nhất (Lấy từ bài thứ 2 trở đi)
const latestPosts = computed(() => {
  return posts.value.slice(1);
});

// --- METHODS ---
const getFullImage = (path) => {
    if (!path) return 'https://placehold.co/600x400?text=No+Image';
    if (path.startsWith('http')) return path;
    return `${BACKEND_URL}${path}`;
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('vi-VN', { 
        day: '2-digit', month: '2-digit', year: 'numeric' 
    }).format(date);
};

const fetchPosts = async () => {
    isLoading.value = true;
    try {
        // --- SỬA Ở ĐÂY ---
        // Cũ (Sai với ý bạn): const response = await apiService.get('/client/news');
        
        // Mới (Đúng ý bạn): Gọi thẳng vào /news
        const response = await apiService.get('/news'); 
        
        // --- XỬ LÝ DỮ LIỆU ---
        // Tùy vào cách NewController trả về (có bọc 'data' hay không)
        // Nếu controller trả về: return response()->json($news); -> Thì lấy response.data
        // Nếu controller trả về: return response()->json(['data' => $news]); -> Thì lấy response.data.data
        
        const allPosts = response.data; // Hoặc response.data.data (hãy console.log để xem)
        
        posts.value = Array.isArray(allPosts) ? allPosts : [];
            
    } catch (error) {
        console.error("Lỗi tải bài viết:", error);
    } finally {
        isLoading.value = false;
    }
};

const handleSearch = () => {
    // Logic tìm kiếm (có thể gọi API search hoặc filter local)
    console.log("Tìm kiếm:", searchQuery.value);
    // Demo filter local đơn giản:
    // posts.value = posts.value.filter(p => p.title.includes(searchQuery.value));
};

// --- LIFECYCLE ---
onMounted(() => {
    fetchPosts();
});
</script>

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
        
      <div v-if="isLoading" class="text-center py-5">
         <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
         </div>
         <p class="mt-2 text-muted">Đang tải tin tức...</p>
      </div>

      <div v-else class="blog-grid">
        
        <section class="posts-content">
          
          <article v-if="featuredPost" class="featured-post">
            <div class="featured-image" 
                 :style="{ backgroundImage: `url(${getFullImage(featuredPost.image_url)})` }">
            </div>
            <div class="featured-body">
              <span class="category-tag">MỚI NHẤT</span>
              <h2>
                  <router-link :to="`/blog/${featuredPost.slug}`" class="text-decoration-none text-reset">
                      {{ featuredPost.title }}
                  </router-link>
              </h2>
              <p class="excerpt">
                {{ featuredPost.excerpt || 'Đang cập nhật mô tả...' }}
              </p>
              <div class="post-meta">
                <span><span class="meta-icon">📅</span> {{ formatDate(featuredPost.created_at) }}</span>
                <span><span class="meta-icon">✍️</span> {{ featuredPost.author?.name || 'Admin' }}</span>
              </div>
              <router-link :to="`/blog/${featuredPost.slug}`" class="read-more-btn">
                  Đọc tiếp <span class="arrow">→</span>
              </router-link>
            </div>
          </article>

          <h2 class="section-title">Tin tức mới nhất</h2>
          
          <div v-if="latestPosts.length > 0" class="latest-posts-grid">
            
            <article v-for="post in latestPosts" :key="post.id" class="post-card">
              <div class="post-image-container" 
                   :style="{ backgroundImage: `url(${getFullImage(post.image_url)})` }">
              </div>
              <div class="post-card-body">
                <span class="category-tag">TIN TỨC</span>
                <h3>
                    <router-link :to="`/blog/${post.slug}`" class="text-decoration-none text-reset">
                        {{ post.title }}
                    </router-link>
                </h3>
                <p class="excerpt-small">
                    {{ post.excerpt ? post.excerpt.substring(0, 100) + '...' : '' }}
                </p>
                <router-link :to="`/blog/${post.slug}`" class="read-more-link">
                    Xem chi tiết
                </router-link>
              </div>
            </article>

          </div>
          <div v-else class="text-center py-4 text-muted">
              Hiện chưa có thêm tin tức nào.
          </div>
          
        </section>
        
        <aside class="blog-sidebar">
          
          <div class="sidebar-widget search-widget">
            <h4><span class="sidebar-icon">🔍</span> Tìm kiếm</h4>
            <input type="text" 
                   v-model="searchQuery" 
                   @keyup.enter="handleSearch"
                   placeholder="Nhập từ khóa..." 
                   class="search-input">
            <button class="search-btn" @click="handleSearch">Tìm</button>
          </div>
          
          <div class="sidebar-widget category-widget">
            <h4><span class="sidebar-icon">🏷️</span> Danh mục</h4>
            <ul>
              <li><a href="#">Đánh giá sản phẩm</a></li>
              <li><a href="#">Tin tức công nghệ</a></li>
              <li><a href="#">Mẹo & Thủ thuật</a></li>
              <li><a href="#">Khuyến mãi</a></li>
            </ul>
          </div>

          <div class="sidebar-widget popular-widget">
            <h4><span class="sidebar-icon">🔥</span> Bài viết phổ biến</h4>
             <div class="popular-post-item">
                <p>Top laptop gaming đáng mua 2025</p>
                <span class="post-meta-small">5,200 lượt xem</span>
            </div>
             <div class="popular-post-item">
                <p>Hướng dẫn vệ sinh bàn phím cơ</p>
                <span class="post-meta-small">4,150 lượt xem</span>
            </div>
          </div>
          
        </aside>
      </div>
    </main>
  </section>
</template>

<style scoped>
/* Giữ nguyên CSS của bạn, chỉ thêm 1 chút cho text-reset của router-link */
.text-decoration-none { text-decoration: none; }
.text-reset { color: inherit; }
.text-reset:hover { color: var(--primary); }

/* --- CSS CŨ CỦA BẠN --- */
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