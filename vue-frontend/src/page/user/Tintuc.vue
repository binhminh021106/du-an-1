<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import apiService from '../../apiService.js'; 

// --- CONFIG ---
const BACKEND_URL = 'http://127.0.0.1:8000'; 
const ITEMS_PER_PAGE = 4; // Số bài viết mỗi trang
const SITE_NAME = 'ThinkHub Blog'; // Tên website của bạn

// --- STATE ---
const posts = ref([]);
const isLoading = ref(true);
const searchQuery = ref(''); // State tìm kiếm theo tên bài viết
const authorQuery = ref(''); // State tìm kiếm theo Tác giả
const currentPage = ref(1);

// --- HELPER METHODS ---

// [MỚI] Hàm tạo Slug cho tiêu đề bài viết
const toSlug = (str) => {
    if (!str) return '';
    str = str.toLowerCase();
    str = str.replace(/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/g, 'a');
    str = str.replace(/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/g, 'e');
    str = str.replace(/(ì|í|ị|ỉ|ĩ)/g, 'i');
    str = str.replace(/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/g, 'o');
    str = str.replace(/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/g, 'u');
    str = str.replace(/(ỳ|ý|ỵ|ỷ|ỹ)/g, 'y');
    str = str.replace(/(đ)/g, 'd');
    str = str.replace(/([^0-9a-z-\s])/g, '');
    str = str.replace(/(\s+)/g, '-');
    str = str.replace(/^-+/g, '');
    str = str.replace(/-+$/g, '');
    return str;
};

// --- SEO HELPER FOR LISTING PAGE ---
const updateListingSeo = () => {
    // 1. Basic Meta
    document.title = `Tin tức Công Nghệ & Thủ Thuật - ${SITE_NAME}`;
    const desc = "Cập nhật xu hướng công nghệ mới nhất, đánh giá sản phẩm, thủ thuật và mẹo hay từ đội ngũ chuyên gia.";
    const url = window.location.href;
    const image = 'https://placehold.co/1200x630?text=News+Page'; // Ảnh đại diện mặc định cho trang tin

    // Helper set meta tag
    const setMetaName = (name, content) => {
        let element = document.querySelector(`meta[name="${name}"]`);
        if (!element) {
            element = document.createElement('meta');
            element.setAttribute('name', name);
            document.head.appendChild(element);
        }
        element.setAttribute('content', content);
    };

    const setMetaProperty = (property, content) => {
        let element = document.querySelector(`meta[property="${property}"]`);
        if (!element) {
            element = document.createElement('meta');
            element.setAttribute('property', property);
            document.head.appendChild(element);
        }
        element.setAttribute('content', content);
    };

    // 2. Canonical (Tránh trùng lặp nội dung)
    let canonical = document.querySelector('link[rel="canonical"]');
    if (!canonical) {
        canonical = document.createElement('link');
        canonical.setAttribute('rel', 'canonical');
        document.head.appendChild(canonical);
    }
    canonical.setAttribute('href', url.split('?')[0]); 

    // 3. Open Graph & Twitter Card (Fix lỗi thiếu Twitter Card)
    setMetaName('description', desc);
    setMetaProperty('og:title', document.title);
    setMetaProperty('og:description', desc);
    setMetaProperty('og:image', image);
    setMetaProperty('og:url', url);
    setMetaProperty('og:type', 'website');

    // [FIX] Thêm Twitter Card
    setMetaName('twitter:card', 'summary_large_image');
    setMetaName('twitter:title', document.title);
    setMetaName('twitter:description', desc);
    setMetaName('twitter:image', image);

    // 4. Schema.org (CollectionPage)
    let schemaScript = document.querySelector('#news-listing-schema');
    if (!schemaScript) {
        schemaScript = document.createElement('script');
        schemaScript.id = 'news-listing-schema';
        schemaScript.type = 'application/ld+json';
        document.head.appendChild(schemaScript);
    }
    
    const schemaData = {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        "headline": "Tin tức Công Nghệ",
        "description": desc,
        "url": url,
        "publisher": {
            "@type": "Organization",
            "name": SITE_NAME,
            "logo": {
                "@type": "ImageObject",
                "url": `${window.location.origin}/logo.png`
            }
        }
    };
    schemaScript.textContent = JSON.stringify(schemaData);
};

// --- HELPER FUNCTIONS ---

// Hàm Debounce: Chờ một khoảng thời gian (ms) trước khi gọi hàm
let debounceTimer = null;
const debounce = (func, delay) => {
    return function(...args) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            func.apply(this, args);
        }, delay);
    };
};

const getFullImage = (path) => {
    if (!path) return 'https://placehold.co/800x450?text=No+Image';
    if (path.startsWith('http')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return `${BACKEND_URL}/${cleanPath}`;
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('vi-VN', { 
        day: '2-digit', month: '2-digit', year: 'numeric' 
    });
};

// --- COMPUTED ---

// Kiểm tra xem có đang tìm kiếm hay không (tên bài hoặc tác giả)
const isSearching = computed(() => {
    return !!searchQuery.value || !!authorQuery.value;
});

// 1. Bài viết nổi bật (Lấy bài đầu tiên)
const featuredPost = computed(() => {
    return posts.value.length > 0 ? posts.value[0] : null;
});

// 2. Danh sách tin tức (Lấy từ bài thứ 2 trở đi)
const allLatestPosts = computed(() => {
    return posts.value.length > 0 ? posts.value.slice(1) : [];
});

// 3. Tổng số trang
const totalPages = computed(() => {
    return Math.ceil(allLatestPosts.value.length / ITEMS_PER_PAGE);
});

// 4. Danh sách tin cũ ĐÃ PHÂN TRANG (Chỉ hiển thị bài của trang hiện tại)
const paginatedPosts = computed(() => {
    const start = (currentPage.value - 1) * ITEMS_PER_PAGE;
    const end = start + ITEMS_PER_PAGE;
    return allLatestPosts.value.slice(start, end);
});


// --- METHODS ---

const fetchPosts = async (query = '', author = '') => {
    isLoading.value = true;
    try {
        let url = '/news';
        const params = new URLSearchParams();

        if (query) {
            params.append('q', query);
        }
        if (author) {
            params.append('author', author);
        }

        if (params.toString()) {
            url += `?${params.toString()}`;
        }

        const response = await apiService.get(url);
        const data = response.data.data ? response.data.data : response.data;
        
        posts.value = Array.isArray(data) ? data : [];
        currentPage.value = 1;

    } catch (error) {
        console.error("Lỗi tải bài viết:", error);
        posts.value = [];
    } finally {
        isLoading.value = false;
        
        // FIX CUỘN TRANG (Cuộn lên đầu trang sau khi tìm kiếm/load data)
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

const triggerSearch = () => {
    const finalQuery = searchQuery.value || ''; 
    const finalAuthor = authorQuery.value || ''; 
    fetchPosts(finalQuery, finalAuthor);
};

const searchByAuthor = (authorName) => {
    searchQuery.value = ''; // Xóa tìm kiếm theo tên bài viết
    authorQuery.value = authorName; // Đặt tên tác giả
    triggerSearch(); // Bỏ qua watcher, gọi trực tiếp để phản hồi ngay lập tức
};

const handleSearch = () => {
    authorQuery.value = ''; // Nếu click nút search, ưu tiên tìm kiếm theo tên bài viết
    triggerSearch();
};


const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
        const listSection = document.getElementById('latest-news-section');
        if (listSection) {
            listSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
};

// --- LIFECYCLE & WATCHER ---
onMounted(() => {
    // Gọi triggerSearch để tải bài viết khi trang được mount (lần đầu)
    triggerSearch();
    updateListingSeo(); // Cập nhật SEO cho trang danh sách
});

// Watcher 1: Theo dõi thay đổi của searchQuery (Tên bài viết) và dùng Debounce
watch(searchQuery, debounce((newQuery) => {
    if (newQuery !== null) {
        authorQuery.value = ''; // Hủy lọc theo tác giả nếu người dùng gõ tìm kiếm mới
        triggerSearch();
    }
}, 400)); 

// Watcher cho AuthorQuery: Khi bấm nút X reset filter
watch(authorQuery, (newAuthor) => {
    if (newAuthor === '') {
        // Nếu authorQuery bị reset (Xóa bộ lọc), gọi lại tìm kiếm/load mặc định
        triggerSearch();
    } 
});
</script>

<template>
  <section class="blog-page">
    
    <header class="page-hero">
      <div class="hero-inner">
        <p class="hero-pre-title">THÔNG TIN & KIẾN THỨC</p>
        <h1 v-if="isSearching">Kết quả tìm kiếm</h1>
        <h1 v-else>Blog Công Nghệ</h1>
        <p class="hero-subtitle">
          Cập nhật xu hướng công nghệ mới nhất, đánh giá sản phẩm và mẹo hay từ đội ngũ chuyên gia.
        </p>
      </div>
    </header>

    <!-- [EDIT] Thêm class 'container' để đồng bộ width với Header/Footer -->
    <main class="page-container container">
        
      <div v-if="isLoading && posts.length === 0" class="text-center py-5 loading-box">
           <div class="spinner-border text-primary" role="status">
             <span class="visually-hidden">Loading...</span>
           </div>
      </div>

      <div v-else class="page-layout">
        
        <div v-if="isLoading && posts.length > 0" class="content-overlay">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-primary mt-2">Đang tải kết quả...</p>
        </div>


        <section class="content-column" :class="{ 'content-loading': isLoading && posts.length > 0 }">
          
          <div v-if="posts.length === 0" class="empty-state card-style">
              <i class="bi bi-newspaper display-4 text-muted mb-3"></i>
              <h3 v-if="searchQuery">Không tìm thấy kết quả cho "{{ searchQuery }}"</h3>
              <h3 v-else-if="authorQuery">Không tìm thấy bài viết của "{{ authorQuery }}"</h3>
              <h3 v-else>Chưa có tin tức nào</h3>
              <p class="text-muted">Vui lòng thử lại với từ khóa khác hoặc quay lại sau.</p>
          </div>

          <template v-else>
              <h3 class="featured-heading">
                <i class="bi bi-bullseye me-2 icon-color"></i>
                {{ isSearching ? 'Bài viết liên quan nhất' : 'Tin tức nổi bật' }}
              </h3>

              <article v-if="featuredPost" class="featured-post card-style">
                <div class="featured-image-wrap">
                    <router-link :to="{ name: 'PostDetailt', params: { id: featuredPost.id, slug: featuredPost.slug || toSlug(featuredPost.title) } }" class="full-link">
                        <div class="featured-image" 
                             :style="{ backgroundImage: `url(${getFullImage(featuredPost.image_url)})` }">
                        </div>
                    </router-link>
                </div>
                <div class="featured-body">
                  <div class="d-flex align-items-center mb-3">
                      <span class="badge-custom me-2" v-if="isSearching">KẾT QUẢ ĐẦU TIÊN</span>
                      <span class="badge-custom me-2" v-else>MỚI NHẤT</span>
                      <span class="date-meta"><i class="bi bi-calendar3 icon-color"></i> {{ formatDate(featuredPost.created_at) }}</span>
                  </div>
                  
                  <h2 class="featured-title">
                      <router-link :to="{ name: 'PostDetailt', params: { id: featuredPost.id, slug: featuredPost.slug || toSlug(featuredPost.title) } }" class="text-reset">
                          {{ featuredPost.title }}
                      </router-link>
                  </h2>
                  <p class="excerpt">
                      {{ featuredPost.excerpt || featuredPost.content?.substring(0, 180) + '...' }}
                  </p>
                  
                  <div class="post-footer">
                      <span class="author">
                        <i class="bi bi-person-fill icon-color"></i> 
                        <a href="#" @click.prevent="searchByAuthor(featuredPost.author_name || 'Admin')" class="author-link">
                            {{ featuredPost.author_name || 'Admin' }}
                        </a>
                    </span>
                    <router-link :to="{ name: 'PostDetailt', params: { id: featuredPost.id, slug: featuredPost.slug || toSlug(featuredPost.title) } }" class="read-more-btn">
                        Đọc tiếp <i class="bi bi-arrow-right ms-1"></i>
                    </router-link>
                  </div>
                </div>
              </article>

              <div v-if="allLatestPosts.length > 0" class="latest-section" id="latest-news-section">
                  <h3 class="section-heading">
                      <i class="bi bi-grid-fill me-2"></i>
                      {{ isSearching ? 'Các kết quả khác' : 'Tin cũ hơn' }}
                  </h3>
                  
                  <div class="latest-posts-grid">
                    <article v-for="post in paginatedPosts" :key="post.id" class="post-card card-style">
                      <div class="card-img-top">
                          <router-link :to="{ name: 'PostDetailt', params: { id: post.id, slug: post.slug || toSlug(post.title) } }" class="full-link">
                              <div class="img-bg" :style="{ backgroundImage: `url(${getFullImage(post.image_url)})` }"></div>
                          </router-link>
                      </div>
                      <div class="card-body">
                        <div class="card-meta">
                            <span class="date"><i class="bi bi-calendar-event icon-color"></i> {{ formatDate(post.created_at) }}</span>
                        </div>
                        <h4 class="card-title">
                            <router-link :to="{ name: 'PostDetailt', params: { id: post.id, slug: post.slug || toSlug(post.title) } }" class="text-reset">
                                {{ post.title }}
                            </router-link>
                        </h4>
                        <p class="card-excerpt">
                            {{ post.excerpt ? post.excerpt.substring(0, 90) + '...' : '' }}
                        </p>
                        <div class="card-footer-custom">
                            <router-link :to="{ name: 'PostDetailt', params: { id: post.id, slug: post.slug || toSlug(post.title) } }" class="card-link">
                                Xem chi tiết <i class="bi bi-chevron-right small-icon"></i>
                            </router-link>
                        </div>
                      </div>
                    </article>
                  </div>

                  <div v-if="totalPages > 1" class="pagination-wrapper">
                      <button 
                        class="page-btn prev" 
                        :disabled="currentPage === 1"
                        @click="changePage(currentPage - 1)"
                      >
                        <i class="bi bi-chevron-left"></i>
                      </button>

                      <div class="page-numbers">
                        <button 
                            v-for="page in totalPages" 
                            :key="page"
                            class="page-btn" 
                            :class="{ active: currentPage === page }"
                            @click="changePage(page)"
                        >
                            {{ page }}
                        </button>
                      </div>

                      <button 
                        class="page-btn next" 
                        :disabled="currentPage === totalPages"
                        @click="changePage(currentPage + 1)"
                      >
                        <i class="bi bi-chevron-right"></i>
                      </button>
                  </div>
              </div>
          </template>

        </section>
        
        <aside class="sidebar-column">
          
          <div class="sidebar-widget search-widget">
            <h4><i class="bi bi-search me-2"></i> Tìm kiếm</h4>
            <div class="search-box">
                <input type="text" v-model="searchQuery" placeholder="Nhập từ khóa...">
                <button @click="handleSearch"><i class="bi bi-search"></i></button>
            </div>
            <div v-if="authorQuery" class="alert alert-info mt-3 py-2 px-3 d-flex justify-content-between align-items-center small">
                <span>Đang lọc theo: <strong>{{ authorQuery }}</strong></span>
                <button @click="authorQuery = ''" class="btn-close ms-2" aria-label="Close"></button>
            </div>
          </div>
          
          <div class="sidebar-widget category-widget">
            <h4><i class="bi bi-tags-fill me-2"></i> Danh mục</h4>
            <ul>
              <li><a href="#"><i class="bi bi-caret-right-fill me-1 bullet-icon"></i> Đánh giá sản phẩm</a></li>
              <li><a href="#"><i class="bi bi-caret-right-fill me-1 bullet-icon"></i> Tin tức công nghệ</a></li>
              <li><a href="#"><i class="bi bi-caret-right-fill me-1 bullet-icon"></i> Mẹo & Thủ thuật</a></li>
              <li><a href="#"><i class="bi bi-caret-right-fill me-1 bullet-icon"></i> Khuyến mãi</a></li>
            </ul>
          </div>

          <div class="sidebar-widget popular-widget">
            <h4><i class="bi bi-star-fill me-2"></i> Phổ biến</h4>
              <div class="popular-post-item">
                <div class="pop-number">1</div>
                <div>
                    <p>Top laptop gaming đáng mua 2025</p>
                    <span class="post-meta-small"><i class="bi bi-eye"></i> 5,200 view</span>
                </div>
            </div>
              <div class="popular-post-item">
                <div class="pop-number">2</div>
                <div>
                    <p>Hướng dẫn vệ sinh bàn phím cơ</p>
                    <span class="post-meta-small"><i class="bi bi-eye"></i> 4,150 view</span>
                </div>
            </div>
              <div class="popular-post-item">
                <div class="pop-number">3</div>
                <div>
                    <p>So sánh iPhone 15 và 16</p>
                    <span class="post-meta-small"><i class="bi bi-eye"></i> 3,800 view</span>
                </div>
            </div>
          </div>

           <div class="sidebar-widget support-box mt-4">
              <div class="d-flex align-items-center mb-3">
                  <i class="bi bi-envelope-paper-fill text-primary fs-3 me-3"></i>
                  <div>
                      <h6 class="mb-0 fw-bold">Đăng ký nhận tin</h6>
                      <small class="text-muted">Nhận bài viết mới qua email</small>
                  </div>
              </div>
              <button class="btn btn-outline-primary w-100 btn-sm fw-bold">Đăng ký ngay</button>
           </div>
          
        </aside>

      </div>
    </main>
  </section>
</template>

<style scoped>
/* --- VARIABLES --- */
:root {
  --primary: #009981;
  --primary-dark: #007a67;
  --accent: #00483D;
  --text-dark: #2c3e50;
  --text-gray: #636e72;
  --bg-light: #F8F9FA;
  --white: #FFFFFF;
}

/* --- UTILS --- */
.full-link {
    display: block;      
    width: 100%;
    height: 100%;        
    text-decoration: none;
}
.author-link {
    /* Style cho link tác giả trong featured post */
    color: var(--text-dark); 
    text-decoration: underline;
    font-weight: 600;
    cursor: pointer;
    transition: color 0.2s;
}
.author-link:hover {
    color: var(--primary);
}
.alert-info {
    background-color: #e0f7fa; 
    color: #004d40; 
    border: 1px solid #b2ebf2;
    border-radius: 8px;
}
.btn-close {
    background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23004d40'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
    opacity: 0.8;
    padding: 0.2em;
    font-size: 0.75rem;
}
.btn-close:hover {
    opacity: 1;
}


/* --- BASE --- */
.blog-page {
  font-family: 'Inter', system-ui, sans-serif;
  background-color: var(--bg-light);
  min-height: 100vh;
  color: var(--text-dark);
  display: flex;
  flex-direction: column;
}

.text-reset { text-decoration: none; color: inherit; transition: color 0.2s; }
.text-reset:hover { color: var(--primary); }
.icon-color { color: var(--primary); margin-right: 5px; }

/* --- HERO --- */
.page-hero {
  background: linear-gradient(135deg, #e0f2f1 0%, #ffffff 100%);
  padding: 60px 20px;
  text-align: center;
  border-bottom: 1px solid #e0e0e0;
}
.hero-inner { max-width: 800px; margin: 0 auto; }
.hero-pre-title {
  color: var(--primary);
  font-weight: 700;
  letter-spacing: 2px;
  font-size: 0.85rem;
  margin-bottom: 10px;
}
.page-hero h1 {
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

/* --- LAYOUT CONTAINER --- */
.page-container {
  /* [EDIT] Xóa max-width cứng để dùng class .container của hệ thống */
  /* max-width: 1320px; */
  /* padding: 0 20px; */
  
  margin: 50px auto;
  flex-grow: 1;
}

/* --- GRID LAYOUT --- */
.page-layout {
    display: grid;
    grid-template-columns: 1fr 320px; 
    gap: 48px;
    align-items: start;
    position: relative; /* Thêm position relative cho overlay */
}

/* --- SHARED CARD STYLE --- */
.card-style {
    background: var(--white);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    border: 1px solid rgba(0,0,0,0.03);
    overflow: hidden;
}
.empty-state {
    padding: 60px 0;
    text-align: center;
    grid-column: 1 / -1; /* Chiếm hết chiều ngang nếu không có bài nào */
}

/* --- FEATURED POST --- */
.featured-heading {
    /* NEW STYLE: Đặt riêng tiêu đề cho Featured Post */
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 20px;
    color: var(--accent);
    display: flex;
    align-items: center;
    padding-bottom: 10px;
    border-bottom: 2px solid #eee;
}

.featured-post {
    display: flex;
    flex-direction: row;
    min-height: 400px;
    margin-bottom: 50px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.featured-post:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}
.featured-image-wrap {
    width: 60%;
    position: relative;
    overflow: hidden;
    display: flex; 
}
.featured-image {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    transition: transform 0.5s ease;
    min-height: 100%; 
}
.featured-post:hover .featured-image {
    transform: scale(1.05);
}
.featured-body {
    width: 40%;
    padding: 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.badge-custom {
    background-color: var(--primary);
    color: white;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
}
.date-meta { font-size: 0.85rem; color: #999; }

.featured-title {
    font-size: 1.8rem;
    font-weight: 700;
    line-height: 1.3;
    margin-bottom: 15px;
}
.excerpt {
    color: var(--text-gray);
    margin-bottom: 25px;
    line-height: 1.6;
    font-size: 1rem;
}
.post-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
}
.read-more-btn {
    color: var(--primary);
    font-weight: 700;
    text-decoration: none;
    font-size: 1rem;
    display: flex;
    align-items: center;
    transition: padding-left 0.2s;
}
.read-more-btn:hover {
    color: var(--accent);
    padding-left: 5px;
}

/* --- LIST POSTS --- */
.section-heading {
    /* Đã tách ra, giờ chỉ dùng cho List Grid */
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 25px;
    color: var(--accent);
    display: flex;
    align-items: center;
}

.latest-posts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 30px;
}

.post-card {
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: transform 0.3s, box-shadow 0.3s;
}
.post-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.08);
}
.card-img-top {
    width: 100%;
    aspect-ratio: 16/9; 
    overflow: hidden;
    position: relative;
}
.img-bg {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    transition: transform 0.5s;
}
.post-card:hover .img-bg {
    transform: scale(1.1);
}
.card-body {
    padding: 25px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}
.card-meta {
    font-size: 0.8rem;
    color: #aaa;
    margin-bottom: 12px;
}
.card-title {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 12px;
    line-height: 1.4;
    
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.card-excerpt {
    font-size: 0.95rem;
    color: var(--text-gray);
    margin-bottom: 20px;
    flex-grow: 1;
    line-height: 1.5;
}
.card-footer-custom {
    border-top: 1px solid #eee;
    padding-top: 15px;
}
.card-link {
    font-size: 0.9rem;
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 5px;
}
.card-link:hover { color: var(--accent); }

/* --- SIDEBAR --- */
.sidebar-column {
    display: flex;
    flex-direction: column;
    gap: 30px;
    position: sticky;
    top: 20px;
}
.sidebar-widget {
    background: var(--white);
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.03);
    border: 1px solid rgba(0,0,0,0.03);
}
.sidebar-widget h4 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 20px;
    color: var(--accent);
    border-bottom: 1px dashed #eee;
    padding-bottom: 15px;
    display: flex;
    align-items: center;
}

/* Search Box */
.search-box { display: flex; gap: 8px; }
.search-box input {
    flex-grow: 1;
    padding: 10px 15px;
    border: 1px solid #eee;
    border-radius: 8px;
    outline: none;
    background: #fdfdfd;
    transition: border 0.2s;
}
.search-box input:focus { border-color: var(--primary); background: #fff; }
.search-box button {
    background: var(--primary);
    color: white;
    border: none;
    width: 45px;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.2s;
}
.search-box button:hover { background-color: var(--accent); }

/* Category */
.category-widget ul { list-style: none; padding: 0; margin: 0; }
.category-widget li { margin-bottom: 8px; }
.category-widget a {
    text-decoration: none;
    color: var(--text-dark);
    font-weight: 500;
    padding: 8px 10px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    transition: all 0.2s;
}
.category-widget a:hover {
    background-color: rgba(0, 153, 129, 0.08);
    color: var(--primary);
    padding-left: 15px;
}
.bullet-icon { font-size: 0.7rem; color: #ccc; transition: color 0.2s; }
.category-widget a:hover .bullet-icon { color: var(--primary); }

/* Popular Posts */
.popular-post-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f5f5f5;
    cursor: pointer;
}
.popular-post-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.pop-number {
    font-size: 1.2rem;
    font-weight: 900;
    color: #eee;
    line-height: 1;
    min-width: 20px;
}
.popular-post-item:hover .pop-number { color: var(--primary); }
.popular-post-item p {
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 5px;
    transition: color 0.2s;
    line-height: 1.4;
}
.popular-post-item:hover p { color: var(--primary); }
.post-meta-small { font-size: 0.8rem; color: #999; }

/* Empty State */
.empty-state { text-align: center; padding: 60px 0; }

/* Footer */
.page-footer {
    border-top: 1px solid #eee;
    padding: 30px 0;
    margin-top: auto;
    background: var(--white);
}

/* --- PAGINATION CSS --- */
.pagination-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 40px;
    gap: 15px;
}

.page-numbers {
    display: flex;
    gap: 8px;
}

.page-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #eee;
    background: var(--white);
    border-radius: 8px;
    color: var(--text-dark);
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.page-btn:hover:not(:disabled) {
    border-color: var(--primary);
    color: var(--primary);
}

.page-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-btn:disabled {
    background: #f8f9fa;
    color: #ccc;
    cursor: not-allowed;
    border-color: #eee;
}

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
    .page-layout {
        grid-template-columns: 1fr;
        gap: 40px;
    }
    .featured-post {
        flex-direction: column;
        min-height: auto;
    }
    .featured-image-wrap {
        width: 100%;
        height: 250px;
    }
    .featured-body {
        width: 100%;
        padding: 25px;
    }
    .featured-title { font-size: 1.5rem; }
    .sidebar-column { order: 1; } 
}
/* Thêm CSS cho Overlay */
.content-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 10;
    border-radius: 12px;
    margin: 50px 0; /* Cho phép header và sidebar không bị mờ */
}

/* Điều chỉnh lại vị trí của Overlay trong page-layout */
.page-layout {
    position: relative;
    /* ... các thuộc tính grid khác ... */
}
</style>