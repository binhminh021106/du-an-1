<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
// Import apiService (Đảm bảo đường dẫn đúng với project của bạn)
import apiService from '../../apiService.js'; 

const route = useRoute();

// --- CONFIG ---
const BACKEND_URL = 'http://127.0.0.1:8000'; 

// --- STATE ---
const isLoading = ref(true);
const post = ref(null);
const relatedPosts = ref([]); 

// --- HELPER METHODS ---

// Hàm xử lý đường dẫn ảnh (dùng chung cho cả bài chính và sidebar)
const getFullImage = (path) => {
    if (!path) return 'https://placehold.co/1200x600?text=No+Image';
    if (path.startsWith('http')) return path;
    const cleanPath = path.startsWith('/') ? path.substring(1) : path;
    return `${BACKEND_URL}/${cleanPath}`;
};

// Hàm format ngày tháng tiếng Việt
const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('vi-VN', { 
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute:'2-digit'
    });
};

// --- DATA FETCHING ---

// 1. Lấy danh sách bài liên quan (Logic: Lấy bài mới - Trừ bài hiện tại)
const fetchRelatedPosts = async (currentId) => {
    try {
        // Gọi API lấy danh sách bài viết chung
        const response = await apiService.get('/news'); 
        
        // Kiểm tra cấu trúc dữ liệu trả về (mảng hoặc object phân trang)
        const listPosts = Array.isArray(response.data) ? response.data : (response.data.data || []);

        // Lọc: Bỏ bài đang xem (id != currentId) -> Lấy 5 bài đầu
        relatedPosts.value = listPosts
            .filter(item => item.id != currentId) 
            .slice(0, 5) 
            .map(item => ({
                id: item.id,
                title: item.title,
                // Xử lý ảnh thumbnail cho sidebar
                image: getFullImage(item.image_url), 
                created_at: item.created_at
            }));

    } catch (error) {
        console.error("Lỗi tải bài liên quan:", error);
        relatedPosts.value = [];
    }
};

// 2. Lấy chi tiết bài viết hiện tại
const fetchPostDetail = async () => {
    const id = route.params.id; 
    
    if (!id) return;

    isLoading.value = true;
    try {
        const response = await apiService.get(`/news/${id}`);
        const data = response.data;

        // Map dữ liệu API vào state
        post.value = {
            id: data.id,
            title: data.title,
            slug: data.slug,
            author: {
                name: data.author_name || 'Admin', 
                avatar: "https://placehold.co/100x100?text=" + (data.author_name ? data.author_name.charAt(0).toUpperCase() : 'A'),
                role: "Tác giả"
            },
            created_at: data.created_at,
            category: data.category_name || "Tin tức", 
            view_count: data.view_count || 0,
            thumbnail: getFullImage(data.image_url), 
            sapo: data.excerpt || '', 
            content: data.content || '' 
        };

        // Sau khi có bài viết chính, gọi hàm lấy bài liên quan
        await fetchRelatedPosts(data.id);

    } catch (error) {
        console.error("Lỗi tải chi tiết bài viết:", error);
        post.value = null;
    } finally {
        isLoading.value = false;
        // Cuộn lên đầu trang khi load xong
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

// --- LIFECYCLE ---
onMounted(() => {
    fetchPostDetail();
});

// Theo dõi thay đổi ID trên URL (khi click vào sidebar)
watch(() => route.params.id, (newId) => {
    if(newId) fetchPostDetail();
});
</script>

<template>
  <div class="post-detail-page">
    
    <div v-if="isLoading" class="d-flex justify-content-center align-items-center" style="min-height: 400px;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <div v-else-if="!post" class="container py-5 text-center">
        <h2>Không tìm thấy bài viết</h2>
        <p>Bài viết bạn đang tìm kiếm không tồn tại hoặc ID không hợp lệ.</p>
        <a href="/blog" class="btn btn-primary">Quay lại trang tin tức</a>
    </div>

    <div v-else>
        <div class="bg-light py-2 border-bottom">
            <div class="container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 text-small">
                        <li class="breadcrumb-item"><a href="/" class="text-reset text-decoration-none">Trang chủ</a></li>
                        <li class="breadcrumb-item"><a href="/blog" class="text-reset text-decoration-none">Tin tức</a></li>
                        <li class="breadcrumb-item active text-truncate" style="max-width: 300px;" aria-current="page">{{ post.title }}</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="container my-5">
        <div class="row">
            
            <div class="col-lg-8 pe-lg-5">
                <article class="article-container">
                    
                    <header class="article-header mb-4">
                        <span class="badge bg-primary mb-2">{{ post.category }}</span>
                        <h1 class="article-title">{{ post.title }}</h1>
                        
                        <div class="article-meta d-flex align-items-center mt-3 text-muted">
                            <div class="d-flex align-items-center me-4">
                                <img :src="post.author.avatar" alt="Author" class="rounded-circle me-2" width="32" height="32">
                                <span>Bởi <strong class="text-dark">{{ post.author.name }}</strong></span>
                            </div>
                            <div class="me-4">
                                <i class="bi bi-clock me-1"></i> {{ formatDate(post.created_at) }}
                            </div>
                            <div v-if="post.view_count > 0">
                                <i class="bi bi-eye me-1"></i> {{ post.view_count }} lượt xem
                            </div>
                        </div>
                    </header>

                    <div v-if="post.sapo" class="article-sapo p-4 bg-light rounded-3 mb-4 fst-italic border-start border-4 border-primary">
                        {{ post.sapo }}
                    </div>

                    <figure class="figure w-100 mb-4">
                        <img :src="post.thumbnail" class="figure-img img-fluid rounded w-100" alt="Thumbnail" style="max-height: 500px; object-fit: cover;">
                    </figure>

                    <div class="article-body" v-html="post.content"></div>

                    <div class="author-box d-flex align-items-center bg-light p-4 rounded mt-4">
                        <img :src="post.author.avatar" class="rounded-circle me-3 shadow-sm" width="80" height="80">
                        <div>
                            <h5 class="fw-bold mb-1">{{ post.author.name }}</h5>
                            <p class="mb-0 text-secondary">"Chia sẻ kiến thức công nghệ."</p>
                        </div>
                    </div>

                </article>
            </div>

            <div class="col-lg-4 mt-5 mt-lg-0">
                <aside class="sidebar-sticky">
                    <div class="sidebar-widget mb-4">
                        <h4 class="widget-title">Bài viết mới nhất</h4>
                        
                        <div class="related-posts">
                            <p v-if="relatedPosts.length === 0" class="text-muted fst-italic">Không có bài viết nào khác.</p>

                            <router-link 
                                :to="`/PostDetail/${item.id}`" 
                                v-for="item in relatedPosts" 
                                :key="item.id" 
                                class="related-item d-flex mb-3 text-decoration-none"
                            >
                                <div class="flex-shrink-0">
                                    <img :src="item.image" class="rounded border" width="80" height="60" style="object-fit: cover;">
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1 text-dark related-title">{{ item.title }}</h6>
                                    <small class="text-muted" style="font-size: 0.8rem">
                                        {{ formatDate(item.created_at).split(' ')[0] }}
                                    </small>
                                </div>
                            </router-link>
                        </div>
                    </div>
                </aside>
            </div>

        </div>
        </div>
    </div>
  </div>
</template>

<style scoped>
:root { --primary: #009981; --accent: #00483D; }
.post-detail-page { --primary: #009981; --accent: #00483D; font-family: 'Inter', sans-serif; }
.text-primary { color: var(--primary) !important; }
.bg-primary { background-color: var(--primary) !important; }
.btn-primary { background-color: var(--primary); border-color: var(--primary); }
.article-title { font-weight: 800; font-size: 2.2rem; color: var(--accent); }
.article-body :deep(img) { max-width: 100%; height: auto; border-radius: 8px; margin: 20px auto; display: block; }
.widget-title { font-size: 1.25rem; font-weight: 700; color: var(--accent); border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 20px; }
.related-title { font-size: 0.95rem; font-weight: 600; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; transition: color 0.2s; }
.related-item:hover .related-title { color: var(--primary) !important; }
</style>