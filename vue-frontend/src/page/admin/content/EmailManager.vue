<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';
import apiService from '../../../apiService'; 

// --- STATE MANAGEMENT ---
const emails = ref([]); 
const isLoading = ref(false); 
const searchQuery = ref('');
const activeTab = ref('inbox'); 
const sortOption = ref('newest');

// Modal State
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingEmail = ref({});
const replyContent = ref('');

// Pagination State
const currentPage = ref(1);
const itemsPerPage = 10;

// [CẤU HÌNH] URL Backend để nối chuỗi hiển thị ảnh
// Nếu bạn deploy lên host thật thì sửa dòng này thành domain thật
const BACKEND_URL = 'http://127.0.0.1:8000'; 

// --- API ACTIONS ---

// 1. Lấy danh sách Email từ Server
const fetchEmails = async () => {
    isLoading.value = true;
    try {
        const response = await apiService.get('/admin/support-emails');
        
        if (response.data.success) {
            // Map dữ liệu từ Backend (snake_case) sang Frontend (camelCase/Nested Object)
            emails.value = response.data.data.map(email => ({
                id: email.id,
                sender: { 
                    name: email.sender_name, 
                    email: email.sender_email, 
                    avatar: email.sender_avatar 
                },
                subject: email.subject,
                preview: email.preview,
                content: email.content,
                created_at: email.created_at,
                status: email.status,
                isRead: Boolean(email.is_read),
                hasAttachment: Boolean(email.has_attachment),
                // [QUAN TRỌNG] Map thêm đường dẫn ảnh
                attachmentPath: email.attachment_path 
            }));
        }
    } catch (error) {
        console.error("Lỗi tải email:", error);
    } finally {
        isLoading.value = false;
    }
};

// 2. Gửi phản hồi (Reply)
const handleReply = async () => {
    if (!replyContent.value.trim()) {
        Swal.fire('Lỗi', 'Vui lòng nhập nội dung trả lời', 'warning');
        return;
    }

    Swal.fire({
        title: 'Đang gửi...',
        text: 'Vui lòng chờ trong giây lát',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        await apiService.post(`/admin/support-emails/${viewingEmail.value.id}/reply`, {
            content: replyContent.value
        });

        viewModalInstance.value?.hide();
        Swal.fire({
            icon: 'success',
            title: 'Đã gửi',
            text: 'Phản hồi đã được gửi thành công!',
            timer: 1500,
            showConfirmButton: false
        });
        
        replyContent.value = '';
        await fetchEmails();

    } catch (error) {
        console.error(error);
        Swal.fire('Thất bại', 'Có lỗi xảy ra khi gửi mail.', 'error');
    }
};

// 3. Xóa Email
const handleDelete = async (email) => {
    const result = await Swal.fire({
        title: 'Bạn chắc chắn chứ?',
        text: "Hành động này sẽ xóa email này.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        try {
            await apiService.delete(`/admin/support-emails/${email.id}`);
            Swal.fire('Đã xóa!', 'Email đã được xóa.', 'success');
            await fetchEmails();
            if (viewModalInstance.value && viewingEmail.value.id === email.id) {
                viewModalInstance.value.hide();
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Lỗi', 'Không thể xóa email này.', 'error');
        }
    }
};

const handlePermanentDelete = async (email) => {
    handleDelete(email); 
};

// --- COMPUTED ---
const filteredEmails = computed(() => {
    let result = emails.value.filter(e => e.status === activeTab.value);
    const query = searchQuery.value.toLowerCase().trim();
    if (query) {
        result = result.filter(e => 
            e.sender.name.toLowerCase().includes(query) ||
            e.sender.email.toLowerCase().includes(query) ||
            e.subject.toLowerCase().includes(query)
        );
    }
    result.sort((a, b) => {
        const dateA = new Date(a.created_at);
        const dateB = new Date(b.created_at);
        return sortOption.value === 'newest' ? dateB - dateA : dateA - dateB;
    });
    return result;
});

const statusCounts = computed(() => ({
    inbox: emails.value.filter(e => e.status === 'inbox' && !e.isRead).length,
    sent: emails.value.filter(e => e.status === 'sent').length,
    trash: emails.value.filter(e => e.status === 'trash').length
}));

const paginatedEmails = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredEmails.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil(filteredEmails.value.length / itemsPerPage));

// --- HELPERS ---
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const today = new Date();
    if (date.toDateString() === today.toDateString()) {
        return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
    }
    return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
};

const getAvatar = (name) => {
    const char = name ? name.charAt(0).toUpperCase() : 'U';
    return `https://placehold.co/50x50/009981/ffffff?text=${char}`;
};

// [MỚI] Hàm lấy full URL ảnh
const getAttachmentUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    // Nối domain backend vào đường dẫn relative
    return `${BACKEND_URL}${path}`;
};

// --- ACTIONS ---
const setActiveTab = (tab) => {
    activeTab.value = tab;
    currentPage.value = 1;
};

const openViewModal = (email) => {
    viewingEmail.value = email;
    if (email.status === 'inbox' && !email.isRead) {
        email.isRead = true;
    }
    replyContent.value = '';
    viewModalInstance.value?.show();
};

onMounted(() => {
    fetchEmails(); 
    nextTick(() => {
        if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value);
    });
});
</script>

<template>
    <!-- ... Giữ nguyên phần Header và Tabs ... -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0 text-brand">Hộp thư hỗ trợ</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
                        <li class="breadcrumb-item active">Hộp thư</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card mb-4 shadow-sm border-0">
                <!-- TABS -->
                <div class="card-header border-bottom-0 pb-0 bg-white">
                    <ul class="nav nav-tabs card-header-tabs custom-tabs">
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'inbox' }" href="#" @click.prevent="setActiveTab('inbox')">
                                <i class="bi bi-inbox me-2"></i> Hộp thư đến
                                <span class="badge rounded-pill bg-danger ms-2" v-if="statusCounts.inbox > 0">{{ statusCounts.inbox }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'sent' }" href="#" @click.prevent="setActiveTab('sent')">
                                <i class="bi bi-send me-2"></i> Đã gửi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'trash' }" href="#" @click.prevent="setActiveTab('trash')">
                                <i class="bi bi-trash me-2"></i> Thùng rác
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- FILTER BAR -->
                <div class="card-body bg-light border-bottom py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-4 col-12">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm tiêu đề, người gửi..." v-model="searchQuery">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <select class="form-select" v-model="sortOption">
                                <option value="newest">⏱️ Mới nhất</option>
                                <option value="oldest">⏳ Cũ nhất</option>
                            </select>
                        </div>
                        <div class="col-md-5 col-6 text-end">
                        </div>
                    </div>
                </div>

                <!-- EMAIL LIST (TABLE) -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <div v-if="isLoading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted small">Đang tải dữ liệu...</p>
                        </div>

                        <table v-else class="table table-hover align-middle mb-0 custom-table">
                            <thead class="bg-light text-secondary">
                                <tr>
                                    <th style="width: 50px" class="ps-3"><input type="checkbox" class="form-check-input"></th>
                                    <th style="width: 250px">Người gửi</th>
                                    <th>Chủ đề & Nội dung</th>
                                    <th style="width: 120px" class="text-end">Ngày</th>
                                    <th style="width: 100px" class="text-end pe-3">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="filteredEmails.length === 0">
                                    <td colspan="5" class="text-center py-5 text-muted fst-italic">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        Không có email nào.
                                    </td>
                                </tr>
                                <tr v-for="email in paginatedEmails" :key="email.id" 
                                    :class="{ 'bg-unread': !email.isRead && email.status === 'inbox' }"
                                    class="email-row cursor-pointer"
                                    @click="openViewModal(email)">
                                    
                                    <td class="ps-3" @click.stop>
                                        <input type="checkbox" class="form-check-input">
                                    </td>
                                    
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img :src="email.sender.avatar || getAvatar(email.sender.name)" class="rounded-circle me-2" width="32" height="32">
                                            <div class="text-truncate" style="max-width: 180px;">
                                                <span class="d-block text-dark" :class="{ 'fw-bold': !email.isRead && email.status === 'inbox' }">{{ email.sender.name }}</span>
                                                <small class="text-muted">{{ email.sender.email }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-light text-secondary border me-2" v-if="email.hasAttachment"><i class="bi bi-paperclip"></i></span>
                                            <div class="text-truncate" style="max-width: 500px;">
                                                <span :class="{ 'fw-bold text-dark': !email.isRead && email.status === 'inbox', 'text-dark': email.isRead }">{{ email.subject }}</span>
                                                <span class="text-muted ms-1">- {{ email.preview }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="text-end text-muted small fw-bold">
                                        {{ formatDate(email.created_at) }}
                                    </td>
                                    
                                    <td class="text-end pe-3" @click.stop>
                                        <button v-if="activeTab !== 'trash'" class="btn btn-sm btn-light text-danger border hover-shadow" @click="handleDelete(email)" title="Xóa">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <button v-else class="btn btn-sm btn-light text-danger border hover-shadow" @click="handlePermanentDelete(email)" title="Xóa vĩnh viễn">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION -->
                    <div class="card-footer bg-white border-top-0 py-3" v-if="totalPages > 1">
                        <ul class="pagination pagination-sm m-0 justify-content-end">
                            <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                <button class="page-link border-0" @click="currentPage--">&laquo;</button>
                            </li>
                            <li v-for="p in totalPages" :key="p" class="page-item" :class="{ active: currentPage === p }">
                                <button class="page-link border-0 rounded-circle mx-1" @click="currentPage = p">{{ p }}</button>
                            </li>
                            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                                <button class="page-link border-0" @click="currentPage++">&raquo;</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- VIEW & REPLY MODAL -->
    <div class="modal fade" id="viewEmailModal" ref="viewModalRef" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <!-- Modal Header -->
                <div class="modal-header bg-light py-3">
                    <div class="d-flex align-items-center w-100">
                        <h5 class="modal-title fw-bold text-dark mb-0 me-auto text-truncate pe-3">
                            {{ viewingEmail.subject }}
                        </h5>
                        <div class="d-flex gap-2">
                             <button class="btn btn-light border text-muted" title="In"><i class="bi bi-printer"></i></button>
                             <button class="btn btn-light border text-danger" title="Xóa" @click="handleDelete(viewingEmail)"><i class="bi bi-trash"></i></button>
                             <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-0">
                    <!-- Sender Info -->
                    <div class="p-4 border-bottom bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center">
                                <img :src="viewingEmail.sender?.avatar || getAvatar(viewingEmail.sender?.name)" class="rounded-circle me-3 border shadow-sm" width="48" height="48">
                                <div>
                                    <div class="fw-bold text-dark fs-6">{{ viewingEmail.sender?.name }}</div>
                                    <div class="text-muted small">&lt;{{ viewingEmail.sender?.email }}&gt;</div>
                                </div>
                            </div>
                            <div class="text-end text-muted small">
                                <div>{{ new Date(viewingEmail.created_at).toLocaleString('vi-VN') }}</div>
                                <div>tới <span class="text-dark">Tôi</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Email Content -->
                    <div class="p-4 bg-white" style="min-height: 200px;">
                        <div class="email-body text-dark" style="white-space: pre-line;">{{ viewingEmail.content }}</div>
                    </div>

                    <!-- [MỚI] HIỂN THỊ ẢNH ĐÍNH KÈM -->
                    <div class="px-4 pb-4 bg-white" v-if="viewingEmail.hasAttachment && viewingEmail.attachmentPath">
                        <div class="border rounded p-3 bg-light">
                            <h6 class="fw-bold mb-2 small text-muted"><i class="bi bi-paperclip"></i> Ảnh đính kèm:</h6>
                            
                            <!-- Hiện ảnh Preview -->
                            <div class="mb-3">
                                <img 
                                    :src="getAttachmentUrl(viewingEmail.attachmentPath)" 
                                    class="img-fluid rounded border shadow-sm" 
                                    style="max-height: 300px; object-fit: contain;" 
                                    alt="Ảnh đính kèm"
                                >
                            </div>

                            <!-- Nút Tải Về -->
                            <!-- Dùng thẻ a với target blank để mở ảnh ra tab mới, user có thể save as từ đó -->
                            <a 
                                :href="getAttachmentUrl(viewingEmail.attachmentPath)" 
                                target="_blank"
                                class="btn btn-outline-primary btn-sm"
                            >
                                <i class="bi bi-download me-1"></i> Xem & Tải ảnh gốc
                            </a>
                        </div>
                    </div>

                    <!-- Reply Section -->
                    <div class="p-4 bg-light border-top">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted"><i class="bi bi-reply-fill"></i> Trả lời nhanh:</label>
                            <textarea class="form-control" rows="3" placeholder="Nhập nội dung phản hồi tại đây..." v-model="replyContent"></textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                             <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                             <button type="button" class="btn btn-primary" @click="handleReply"><i class="bi bi-send me-1"></i> Gửi phản hồi</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* REUSED STYLES */
.text-brand { color: #009981 !important; }
.text-primary { color: #009981 !important; }
.bg-primary { background-color: #009981 !important; }
.btn-primary { background-color: #009981 !important; border-color: #009981 !important; color: white !important; }
.btn-primary:hover { background-color: #007a67 !important; border-color: #007a67 !important; }
.btn-outline-primary { color: #009981; border-color: #009981; }
.btn-outline-primary:hover { background-color: #009981; color: white; }

/* Tabs */
.custom-tabs .nav-link { color: #6c757d; border: none; font-weight: 500; padding: 12px 20px; border-bottom: 3px solid transparent; cursor: pointer; }
.custom-tabs .nav-link:hover { color: #009981; }
.custom-tabs .nav-link.active { color: #009981; background: transparent; border-bottom: 3px solid #009981; }

/* Table */
.custom-table th { font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
.email-row { transition: all 0.2s; }
.email-row:hover { background-color: #f8f9fa; cursor: pointer; }

/* Pagination */
.page-item.active .page-link { background-color: #009981 !important; border-color: #009981 !important; color: white !important; }
.page-link { color: #666; cursor: pointer; }

/* SPECIFIC EMAIL STYLES */
.bg-unread { background-color: rgba(0, 153, 129, 0.05); } 
.cursor-pointer { cursor: pointer; }
.hover-shadow:hover { box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
</style>