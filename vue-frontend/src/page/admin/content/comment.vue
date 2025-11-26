<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CONFIG ---
const BACKEND_URL = 'http://127.0.0.1:8000'; // URL of Laravel Server

// --- STATE MANAGEMENT ---
const allComments = ref([]); // Raw data
const isLoading = ref(true);
const searchQuery = ref('');
const activeTab = ref('pending'); // Default tab

// Modal State
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingComment = ref({});

// Pagination State (3 separate lists)
const pagination = reactive({
  pending: { currentPage: 1, itemsPerPage: 8 },
  approved: { currentPage: 1, itemsPerPage: 8 },
  rejected: { currentPage: 1, itemsPerPage: 8 }
});

// --- COMPUTED: FILTER & SPLIT LISTS ---

// 1. Search Filter (Applied to all data first)
const searchResults = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return allComments.value;

  return allComments.value.filter(comment =>
    (comment.user?.username && comment.user.username.toLowerCase().includes(query)) ||
    (comment.product?.name && comment.product.name.toLowerCase().includes(query)) ||
    (comment.content && comment.content.toLowerCase().includes(query))
  );
});

// 2. Split into lists by Status
const pendingList = computed(() => searchResults.value.filter(c => c.status === 'pending'));
const approvedList = computed(() => searchResults.value.filter(c => c.status === 'approved'));
const rejectedList = computed(() => searchResults.value.filter(c => c.status === 'rejected'));

// 3. Pagination Helper
function getPaginatedData(list, type) {
  const pageInfo = pagination[type];
  const totalPages = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));
  
  // Reset to page 1 if current page is out of bounds
  if (pageInfo.currentPage > totalPages) pageInfo.currentPage = 1;

  const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
  const end = start + pageInfo.itemsPerPage;
  
  return {
    data: list.slice(start, end),
    totalPages: totalPages,
    totalItems: list.length
  };
}

// 4. Paged Data for each Tab
const pagedPending = computed(() => getPaginatedData(pendingList.value, 'pending'));
const pagedApproved = computed(() => getPaginatedData(approvedList.value, 'approved'));
const pagedRejected = computed(() => getPaginatedData(rejectedList.value, 'rejected'));

// --- WATCHERS ---
watch(searchQuery, () => {
  // Reset all pages when searching
  pagination.pending.currentPage = 1;
  pagination.approved.currentPage = 1;
  pagination.rejected.currentPage = 1;
});

// --- LIFECYCLE ---
onMounted(() => {
  fetchComments();
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- API FUNCTIONS ---

async function fetchComments() {
  // Silent Refresh: Only show big spinner if no data exists
  if (allComments.value.length === 0) {
    isLoading.value = true;
  }
  
  try {
    const response = await apiService.get(`admin/comments`);
    // Sort newest first
    allComments.value = response.data.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
  } catch (error) {
    console.error("Error loading comments:", error);
    Swal.fire('Error', 'Could not load comments list.', 'error');
  } finally {
    isLoading.value = false;
  }
}

// --- ACTION HANDLERS ---

// Update Status (Optimistic Update)
async function handleUpdateStatus(comment, newStatus) {
  const oldStatus = comment.status;
  
  // 1. Update UI immediately
  comment.status = newStatus; 

  // 2. Show Toast
  const statusText = newStatus === 'approved' ? 'Approved' : 'Rejected';
  const icon = newStatus === 'approved' ? 'success' : 'info';
  
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
  });
  Toast.fire({ icon: icon, title: statusText });

  // 3. Call API
  try {
    // Note: Using PUT or PATCH depending on your API
    await apiService.put(`admin/comments/${comment.id}`, { status: newStatus });
  } catch (error) {
    // Rollback on error
    comment.status = oldStatus;
    Swal.fire('Error', 'Update failed, reverted changes.', 'error');
  }
}

// Delete Comment
async function handleDelete(comment) {
  const result = await Swal.fire({
    title: 'Delete comment?',
    text: "This action cannot be undone!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Delete',
    cancelButtonText: 'Cancel'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`admin/comments/${comment.id}`);
      
      // Remove from local array
      allComments.value = allComments.value.filter(c => c.id !== comment.id);
      
      Swal.fire('Deleted!', 'Comment has been deleted.', 'success');
    } catch (error) {
      Swal.fire('Error', 'Could not delete comment.', 'error');
    }
  }
}

// --- HELPER FUNCTIONS ---

function changePage(type, page) {
  pagination[type].currentPage = page;
}

function setActiveTab(tabName) {
  activeTab.value = tabName;
}

function getProductImageUrl(path) {
  if (!path) return 'https://placehold.co/120x120/EBF4FF/1D62F0?text=No+Img';
  if (path.startsWith('http')) return path;
  return `${BACKEND_URL}${path.startsWith('/') ? '' : '/'}${path}`;
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleString('vi-VN');
}

function openViewModal(comment) {
  viewingComment.value = comment;
  viewModalInstance.value.show();
}
</script>

<template>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6"><h3 class="mb-0">Quản lý Bình luận</h3></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active">Bình luận</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card shadow-sm">
        <div class="card-header border-bottom-0 pb-0">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="card-title">Danh sách Bình luận</h3>
            <div class="input-group" style="width: 300px;">
              <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control border-start-0" placeholder="Tìm kiếm..." v-model="searchQuery">
            </div>
          </div>

          <!-- TABS NAVIGATION -->
          <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'pending' }" href="#" @click.prevent="setActiveTab('pending')">
                <i class="bi bi-hourglass-split me-1 text-warning"></i> Chờ duyệt
                <span class="badge rounded-pill bg-warning text-dark ms-1">{{ pendingList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'approved' }" href="#" @click.prevent="setActiveTab('approved')">
                <i class="bi bi-check-circle me-1 text-success"></i> Đã duyệt
                <span class="badge rounded-pill bg-success ms-1">{{ approvedList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'rejected' }" href="#" @click.prevent="setActiveTab('rejected')">
                <i class="bi bi-x-circle me-1 text-danger"></i> Đã từ chối
                <span class="badge rounded-pill bg-danger ms-1">{{ rejectedList.length }}</span>
              </a>
            </li>
          </ul>
        </div>

        <div class="card-body p-0">
          <!-- Loading Spinner -->
          <div v-if="isLoading && allComments.length === 0" class="text-center p-5">
            <div class="spinner-border text-primary" role="status"></div>
          </div>

          <!-- Content Area -->
          <div v-else class="tab-content p-0">
            
            <!-- TAB: PENDING -->
            <div class="tab-pane fade show active" v-if="activeTab === 'pending'">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 50px">ID</th>
                      <th>Sản phẩm</th>
                      <th>Người gửi</th>
                      <th>Nội dung</th>
                      <th style="width: 150px">Ngày gửi</th>
                      <th style="width: 180px" class="text-end">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedPending.data.length === 0">
                      <td colspan="6" class="text-center py-4 text-muted">Không có bình luận chờ duyệt.</td>
                    </tr>
                    <tr v-for="comment in pagedPending.data" :key="comment.id">
                      <td>{{ comment.id }}</td>
                      <td><span class="fw-bold text-primary">{{ comment.product?.name || 'N/A' }}</span></td>
                      <td>{{ comment.user?.username || 'Ẩn danh' }}</td>
                      <td><span class="d-inline-block text-truncate" style="max-width: 300px;" :title="comment.content">{{ comment.content }}</span></td>
                      <td class="small text-muted">{{ formatDate(comment.created_at || comment.createdAt) }}</td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-outline-info me-1" @click="openViewModal(comment)"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm btn-success me-1" @click="handleUpdateStatus(comment, 'approved')" title="Duyệt"><i class="bi bi-check-lg"></i></button>
                        <button class="btn btn-sm btn-outline-secondary" @click="handleUpdateStatus(comment, 'rejected')" title="Từ chối"><i class="bi bi-x-lg"></i></button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination -->
              <div v-if="pagedPending.totalPages > 1" class="d-flex justify-content-end p-3">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: pagination.pending.currentPage === 1 }"><a class="page-link" href="#" @click.prevent="changePage('pending', pagination.pending.currentPage - 1)">&laquo;</a></li>
                  <li v-for="p in pagedPending.totalPages" :key="p" class="page-item" :class="{ active: pagination.pending.currentPage === p }"><a class="page-link" href="#" @click.prevent="changePage('pending', p)">{{ p }}</a></li>
                  <li class="page-item" :class="{ disabled: pagination.pending.currentPage === pagedPending.totalPages }"><a class="page-link" href="#" @click.prevent="changePage('pending', pagination.pending.currentPage + 1)">&raquo;</a></li>
                </ul>
              </div>
            </div>

            <!-- TAB: APPROVED -->
            <div class="tab-pane fade show active" v-if="activeTab === 'approved'">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 50px">ID</th>
                      <th>Sản phẩm</th>
                      <th>Người gửi</th>
                      <th>Nội dung</th>
                      <th style="width: 150px">Ngày gửi</th>
                      <th style="width: 150px" class="text-end">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedApproved.data.length === 0">
                      <td colspan="6" class="text-center py-4 text-muted">Chưa có bình luận nào được duyệt.</td>
                    </tr>
                    <tr v-for="comment in pagedApproved.data" :key="comment.id">
                      <td>{{ comment.id }}</td>
                      <td><span class="fw-bold text-primary">{{ comment.product?.name || 'N/A' }}</span></td>
                      <td>{{ comment.user?.username || 'Ẩn danh' }}</td>
                      <td><span class="d-inline-block text-truncate" style="max-width: 300px;" :title="comment.content">{{ comment.content }}</span></td>
                      <td class="small text-muted">{{ formatDate(comment.created_at || comment.createdAt) }}</td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-outline-info me-1" @click="openViewModal(comment)"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm btn-outline-secondary me-1" @click="handleUpdateStatus(comment, 'rejected')" title="Gỡ bỏ"><i class="bi bi-x-lg"></i></button>
                        <button class="btn btn-sm btn-outline-danger" @click="handleDelete(comment)"><i class="bi bi-trash"></i></button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination -->
              <div v-if="pagedApproved.totalPages > 1" class="d-flex justify-content-end p-3">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: pagination.approved.currentPage === 1 }"><a class="page-link" href="#" @click.prevent="changePage('approved', pagination.approved.currentPage - 1)">&laquo;</a></li>
                  <li v-for="p in pagedApproved.totalPages" :key="p" class="page-item" :class="{ active: pagination.approved.currentPage === p }"><a class="page-link" href="#" @click.prevent="changePage('approved', p)">{{ p }}</a></li>
                  <li class="page-item" :class="{ disabled: pagination.approved.currentPage === pagedApproved.totalPages }"><a class="page-link" href="#" @click.prevent="changePage('approved', pagination.approved.currentPage + 1)">&raquo;</a></li>
                </ul>
              </div>
            </div>

            <!-- TAB: REJECTED -->
            <div class="tab-pane fade show active" v-if="activeTab === 'rejected'">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 50px">ID</th>
                      <th>Sản phẩm</th>
                      <th>Người gửi</th>
                      <th>Nội dung</th>
                      <th style="width: 150px">Ngày gửi</th>
                      <th style="width: 150px" class="text-end">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedRejected.data.length === 0">
                      <td colspan="6" class="text-center py-4 text-muted">Chưa có bình luận nào bị từ chối.</td>
                    </tr>
                    <tr v-for="comment in pagedRejected.data" :key="comment.id">
                      <td>{{ comment.id }}</td>
                      <td><span class="fw-bold text-primary">{{ comment.product?.name || 'N/A' }}</span></td>
                      <td>{{ comment.user?.username || 'Ẩn danh' }}</td>
                      <td><span class="d-inline-block text-truncate" style="max-width: 300px;" :title="comment.content">{{ comment.content }}</span></td>
                      <td class="small text-muted">{{ formatDate(comment.created_at || comment.createdAt) }}</td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-outline-info me-1" @click="openViewModal(comment)"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm btn-outline-success me-1" @click="handleUpdateStatus(comment, 'approved')" title="Duyệt lại"><i class="bi bi-check-lg"></i></button>
                        <button class="btn btn-sm btn-outline-danger" @click="handleDelete(comment)"><i class="bi bi-trash"></i></button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination -->
              <div v-if="pagedRejected.totalPages > 1" class="d-flex justify-content-end p-3">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item" :class="{ disabled: pagination.rejected.currentPage === 1 }"><a class="page-link" href="#" @click.prevent="changePage('rejected', pagination.rejected.currentPage - 1)">&laquo;</a></li>
                  <li v-for="p in pagedRejected.totalPages" :key="p" class="page-item" :class="{ active: pagination.rejected.currentPage === p }"><a class="page-link" href="#" @click.prevent="changePage('rejected', p)">{{ p }}</a></li>
                  <li class="page-item" :class="{ disabled: pagination.rejected.currentPage === pagedRejected.totalPages }"><a class="page-link" href="#" @click.prevent="changePage('rejected', pagination.rejected.currentPage + 1)">&raquo;</a></li>
                </ul>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL XEM CHI TIẾT (2 CỘT) -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Chi tiết bình luận #{{ viewingComment.id }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-4">
            <!-- CỘT TRÁI: THÔNG TIN SẢN PHẨM -->
            <div class="col-md-5 text-center border-end">
              <div class="mb-3">
                <img 
                  :src="getProductImageUrl(viewingComment.product?.thumbnail_url)" 
                  class="img-fluid rounded shadow-sm" 
                  style="max-height: 250px; object-fit: contain;" 
                  alt="Product Image"
                >
              </div>
              <h5 class="text-primary fw-bold">{{ viewingComment.product?.name || 'Sản phẩm không rõ' }}</h5>
              <p class="text-muted small">ID Sản phẩm: {{ viewingComment.product?.id }}</p>
              <div class="mt-3">
                <span v-if="viewingComment.status === 'pending'" class="badge bg-warning text-dark fs-6">Đang chờ duyệt</span>
                <span v-if="viewingComment.status === 'approved'" class="badge bg-success fs-6">Đã duyệt</span>
                <span v-if="viewingComment.status === 'rejected'" class="badge bg-danger fs-6">Đã từ chối</span>
              </div>
            </div>

            <!-- CỘT PHẢI: THÔNG TIN BÌNH LUẬN -->
            <div class="col-md-7">
              <h6 class="border-bottom pb-2 mb-3 text-secondary text-uppercase small fw-bold">Thông tin người gửi</h6>
              <div class="d-flex align-items-center mb-4">
                <img 
                  :src="viewingComment.user?.avatar || `https://placehold.co/50x50/EBF4FF/1D62F0?text=${viewingComment.user?.username?.charAt(0).toUpperCase() || 'U'}`" 
                  class="rounded-circle me-3" width="50" height="50"
                >
                <div>
                  <div class="fw-bold fs-5">{{ viewingComment.user?.username || 'Người dùng ẩn danh' }}</div>
                  <div class="text-muted small">ID User: {{ viewingComment.user?.id }}</div>
                </div>
              </div>

              <h6 class="border-bottom pb-2 mb-3 text-secondary text-uppercase small fw-bold">Nội dung bình luận</h6>
              <div class="bg-light p-3 rounded mb-3" style="min-height: 100px;">
                <i class="bi bi-quote fs-4 text-secondary opacity-50"></i>
                <p class="mb-0 fst-italic text-dark">{{ viewingComment.content }}</p>
              </div>
              <div class="text-end text-muted small">
                <i class="bi bi-clock me-1"></i> Gửi lúc: {{ formatDate(viewingComment.created_at || viewingComment.createdAt) }}
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.nav-tabs .nav-link {
  cursor: pointer;
  color: #6c757d;
  font-weight: 500;
}
.nav-tabs .nav-link.active {
  color: #0d6efd;
  border-bottom-color: #fff;
}
.pagination .page-link {
  cursor: pointer;
}
</style>