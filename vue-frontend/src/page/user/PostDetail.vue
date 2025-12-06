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
// Hàm chuyển đổi Tiếng Việt có dấu thành không dấu - ngăn cách bởi dấu gạch ngang
function toSlug(str) {
    if (!str) return '';
    str = str.toLowerCase();
    str = str.replace(/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/g, 'a');
    str = str.replace(/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/g, 'e');
    str = str.replace(/(ì|í|ị|ỉ|ĩ)/g, 'i');
    str = str.replace(/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/g, 'o');
    str = str.replace(/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/g, 'u');
    str = str.replace(/(ỳ|ý|ỵ|ỷ|ỹ)/g, 'y');
    str = str.replace(/(đ)/g, 'd');

    // Xóa ký tự đặc biệt
    str = str.replace(/([^0-9a-z-\s])/g, '');

    // Xóa khoảng trắng thay bằng dấu gạch ngang
    str = str.replace(/(\s+)/g, '-');

    // Xóa phần dư - ở đầu
    str = str.replace(/^-+/g, '');

    // Xóa phần dư - ở cuối
    str = str.replace(/-+$/g, '');

    return str;
}

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
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
    });
};

// [SEO UPGRADE] Hàm cập nhật thẻ Meta động trên HEAD
const updateSeoTags = (postData) => {
    // 1. Update Title (Ưu tiên Meta Title, nếu không có thì dùng Title bài viết)
    const title = postData.meta_title || postData.title;
    document.title = title;

    // 2. Helper function để update hoặc tạo thẻ meta
    const setMetaTag = (name, content) => {
        let element = document.querySelector(`meta[name="${name}"]`);
        if (!element) {
            element = document.createElement('meta');
            element.setAttribute('name', name);
            document.head.appendChild(element);
        }
        element.setAttribute('content', content);
    };

    const setOpenGraphTag = (property, content) => {
        let element = document.querySelector(`meta[property="${property}"]`);
        if (!element) {
            element = document.createElement('meta');
            element.setAttribute('property', property);
            document.head.appendChild(element);
        }
        element.setAttribute('content', content);
    };

    // 3. Update Description
    const desc = postData.meta_description || postData.excerpt || '';
    setMetaTag('description', desc);

    // 4. Update Keywords
    if (postData.meta_keywords) {
        setMetaTag('keywords', postData.meta_keywords);
    }

    // 5. Update Open Graph (Cho Facebook/Zalo share)
    setOpenGraphTag('og:title', title);
    setOpenGraphTag('og:description', desc);
    setOpenGraphTag('og:image', getFullImage(postData.image_url));
    setOpenGraphTag('og:url', window.location.href);
    setOpenGraphTag('og:type', 'article');
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

    // [FIX BUG] Nếu không có ID, tắt loading ngay để hiển thị thông báo lỗi thay vì xoay mãi
    if (!id) {
        isLoading.value = false;
        post.value = null;
        return;
    }

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
            content: data.content || '',
            // [SEO UPGRADE] Map thêm các trường SEO từ API
            meta_title: data.meta_title,
            meta_description: data.meta_description,
            meta_keywords: data.meta_keywords,
            image_url: data.image_url // Để dùng cho OG Image
        };

        // [SEO UPGRADE] Gọi hàm update SEO Tags
        updateSeoTags(post.value);

        // Sau khi có bài viết chính, gọi hàm lấy bài liên quan
        await fetchRelatedPosts(data.id);

    } catch (error) {
        console.error("Lỗi tải chi tiết bài viết:", error);
        post.value = null;
    } finally {
        // [QUAN TRỌNG] Luôn tắt loading khi kết thúc (dù thành công hay thất bại)
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
    // [FIX] Gọi hàm fetchPostDetail ngay cả khi newId rỗng để logic bên trong xử lý tắt loading
    fetchPostDetail();
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
            <router-link to="/blog" class="btn btn-primary">Quay lại trang tin tức</router-link>
        </div>

        <div v-else>
            <div class="bg-light py-2 border-bottom">
                <div class="container">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 text-small">
                            <li class="breadcrumb-item"><router-link to="/"
                                    class="text-reset text-decoration-none">Trang chủ</router-link></li>
                            <li class="breadcrumb-item"><router-link to="/blog"
                                    class="text-reset text-decoration-none">Tin tức</router-link></li>
                            <li class="breadcrumb-item active text-truncate" style="max-width: 300px;"
                                aria-current="page">{{ post.title }}</li>
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
                                        <img :src="post.author.avatar" alt="Author" class="rounded-circle me-2"
                                            width="32" height="32">
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

                            <div v-if="post.sapo"
                                class="article-sapo p-4 bg-light rounded-3 mb-4 fst-italic border-start border-4 border-primary">
                                {{ post.sapo }}
                            </div>

                            <figure class="figure w-100 mb-4">
                                <img :src="post.thumbnail" class="figure-img img-fluid rounded w-100" alt="Thumbnail"
                                    style="max-height: 500px; object-fit: cover;">
                            </figure>

                            <div class="article-body" v-html="post.content"></div>

                            <div class="author-box d-flex align-items-center bg-light p-4 rounded mt-4">
                                <img :src="post.author.avatar" class="rounded-circle me-3 shadow-sm" width="80"
                                    height="80">
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
                                    <p v-if="relatedPosts.length === 0" class="text-muted fst-italic">Không có bài viết
                                        nào khác.</p>

                                    <router-link :to="{
                                        name: 'PostDetailt',
                                        params: {
                                            id: item.id,
                                            slug: item.slug ? item.slug : toSlug(item.title)
                                        }
                                    }" v-for="item in relatedPosts" :key="item.id" class="related-item d-flex mb-3 text-decoration-none">
                                        <div class="flex-shrink-0">
                                            <img :src="item.image" class="rounded border" width="80" height="60"
                                                style="object-fit: cover;">
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
:root {
    --primary: #009981;
    --accent: #00483D;
}

.post-detail-page {
    --primary: #009981;
    --accent: #00483D;
    font-family: 'Inter', sans-serif;
}

.text-primary {
    color: var(--primary) !important;
}

.bg-primary {
    background-color: var(--primary) !important;
}

.btn-primary {
    background-color: var(--primary);
    border-color: var(--primary);
}

.article-title {
    font-weight: 800;
    font-size: 2.2rem;
    color: var(--accent);
}

.article-body :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 20px auto;
    display: block;
}

.widget-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--accent);
    border-bottom: 2px solid var(--primary);
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.related-title {
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color 0.2s;
}

.related-item:hover .related-title {
    color: var(--primary) !important;
}
</style>