<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CẤU HÌNH ---
const BACKEND_URL = 'http://127.0.0.1:8000'; // URL của Laravel Server

// --- STATE QUẢN LÝ ---
const allReviews = ref([]); // Dữ liệu gốc
const isLoading = ref(true);
const searchQuery = ref('');
const sortOption = ref('newest'); // Mặc định: Mới nhất
const activeTab = ref('pending'); // Tab đang active: 'pending', 'approved', 'rejected'

// State cho Modal Xem
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingReview = ref({});

// State cho phân trang (3 danh sách riêng biệt)
const pagination = reactive({
  pending: { currentPage: 1, itemsPerPage: 8 },
  approved: { currentPage: 1, itemsPerPage: 8 },
  rejected: { currentPage: 1, itemsPerPage: 8 }
});

// --- COMPUTED: LỌC & SẮP XẾP & CHIA DANH SÁCH ---

// 1. Xử lý Tìm kiếm & Sắp xếp trên toàn bộ dữ liệu trước khi chia Tab
const processedReviews = computed(() => {
  let result = [...allReviews.value];

  // A. Lọc theo tìm kiếm
  const query = searchQuery.value.toLowerCase().trim();
  if (query) {
    result = result.filter(review =>
      (review.user?.username && review.user.username.toLowerCase().includes(query)) ||
      (review.product?.name && review.product.name.toLowerCase().includes(query)) ||
      (review.content && review.content.toLowerCase().includes(query))
    );
  }

  // B. Sắp xếp
  switch (sortOption.value) {
    case 'rating_desc': // Sao cao -> thấp
      result.sort((a, b) => b.rating - a.rating);
      break;
    case 'rating_asc': // Sao thấp -> cao
      result.sort((a, b) => a.rating - b.rating);
      break;
    case 'oldest': // Cũ nhất trước
      result.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
      break;
    case 'newest': // Mới nhất trước (Mặc định)
    default:
      result.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
      break;
  }

  return result;
});

// 2. Chia danh sách theo trạng thái (Dựa trên kết quả đã xử lý)
const pendingList = computed(() => processedReviews.value.filter(r => r.status === 'pending'));
const approvedList = computed(() => processedReviews.value.filter(r => r.status === 'approved'));
const rejectedList = computed(() => processedReviews.value.filter(r => r.status === 'rejected'));

// 3. Helper tính toán phân trang
function getPaginatedData(list, type) {
  const pageInfo = pagination[type];
  const totalPages = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));
  
  // Reset về trang 1 nếu trang hiện tại vượt quá tổng số trang (do filter/delete)
  if (pageInfo.currentPage > totalPages) pageInfo.currentPage = 1;

  const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
  const end = start + pageInfo.itemsPerPage;
  
  return {
    data: list.slice(start, end),
    totalPages: totalPages,
    totalItems: list.length
  };
}

// 4. Dữ liệu hiển thị cho từng tab (Đã phân trang)
const pagedPending = computed(() => getPaginatedData(pendingList.value, 'pending'));
const pagedApproved = computed(() => getPaginatedData(approvedList.value, 'approved'));
const pagedRejected = computed(() => getPaginatedData(rejectedList.value, 'rejected'));

// --- WATCHERS ---
watch([searchQuery, sortOption], () => {
  // Reset trang về 1 khi tìm kiếm HOẶC thay đổi sắp xếp
  pagination.pending.currentPage = 1;
  pagination.approved.currentPage = 1;
  pagination.rejected.currentPage = 1;
});

// --- VÒNG ĐỜI ---
onMounted(() => {
  fetchReviews();
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- API FUNCTIONS ---

async function fetchReviews() {
  // Silent Refresh: Chỉ hiện spinner to nếu chưa có dữ liệu
  if (allReviews.value.length === 0) {
    isLoading.value = true;
  }
  
  try {
    const response = await apiService.get(`admin/reviews`);
    allReviews.value = response.data;
  } catch (error) {
    console.error("Lỗi tải đánh giá:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách.', 'error');
  } finally {
    isLoading.value = false;
  }
}

// --- ACTION HANDLERS ---

// Cập nhật trạng thái (Optimistic Update)
async function handleUpdateStatus(review, newStatus) {
  const oldStatus = review.status;
  
  // 1. Cập nhật ngay lập tức trên UI
  review.status = newStatus; // Vue sẽ tự động chuyển item sang tab danh sách mới

  // 2. Hiển thị Toast
  const statusText = newStatus === 'approved' ? 'Đã duyệt' : 'Đã từ chối';
  const icon = newStatus === 'approved' ? 'success' : 'info';
  
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
  });
  Toast.fire({ icon: icon, title: statusText });

  // 3. Gọi API
  try {
    await apiService.patch(`admin/reviews/${review.id}`, { status: newStatus });
  } catch (error) {
    // Rollback nếu lỗi
    review.status = oldStatus;
    Swal.fire('Lỗi', 'Cập nhật thất bại, đã hoàn tác.', 'error');
  }
}

// Xóa đánh giá
async function handleDelete(review) {
  const result = await Swal.fire({
    title: 'Xóa đánh giá?',
    text: "Hành động này không thể hoàn tác!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Xóa',
    cancelButtonText: 'Hủy'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`admin/reviews/${review.id}`);
      
      // Xóa khỏi mảng local
      allReviews.value = allReviews.value.filter(r => r.id !== review.id);
      
      Swal.fire('Đã xóa!', 'Đánh giá đã được xóa.', 'success');
    } catch (error) {
      Swal.fire('Lỗi', 'Không thể xóa.', 'error');
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

function renderStars(rating) {
  return '★'.repeat(Math.floor(rating)) + '☆'.repeat(5 - Math.floor(rating));
}

function openViewModal(review) {
  viewingReview.value = review;
  viewModalInstance.value.show();
}
</script>

<template>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6"><h3 class="mb-0">Quản lý Đánh giá</h3></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active">Đánh giá</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card shadow-sm">
        <div class="card-header border-bottom-0 pb-0">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <h3 class="card-title text-nowrap">Danh sách Đánh giá</h3>
            
            <!-- TOOLBAR: SEARCH & SORT -->
            <div class="d-flex gap-2 flex-grow-1 justify-content-end" style="max-width: 600px;">
               <!-- Sắp xếp -->
               <select class="form-select w-auto" v-model="sortOption">
                  <option value="newest">Mới nhất</option>
                  <option value="oldest">Cũ nhất</option>
                  <option value="rating_desc">Sao giảm dần (5 &rarr; 1)</option>
                  <option value="rating_asc">Sao tăng dần (1 &rarr; 5)</option>
               </select>

               <!-- Tìm kiếm -->
               <div class="input-group" style="min-width: 200px;">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm người dùng, sản phẩm..." v-model="searchQuery">
              </div>
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
          <div v-if="isLoading && allReviews.length === 0" class="text-center p-5">
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
                      <th style="width: 120px">Sao</th>
                      <th>Nội dung</th>
                      <th style="width: 150px">Ngày gửi</th>
                      <th style="width: 180px" class="text-end">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedPending.data.length === 0">
                      <td colspan="7" class="text-center py-4 text-muted">Không có đánh giá chờ duyệt nào phù hợp.</td>
                    </tr>
                    <tr v-for="review in pagedPending.data" :key="review.id">
                      <td>{{ review.id }}</td>
                      <td><span class="fw-bold text-primary">{{ review.product?.name }}</span></td>
                      <td>{{ review.user?.username || 'Ẩn danh' }}</td>
                      <td class="text-warning">{{ renderStars(review.rating) }}</td>
                      <td><span class="d-inline-block text-truncate" style="max-width: 200px;" :title="review.content">{{ review.content }}</span></td>
                      <td class="small text-muted">{{ formatDate(review.created_at) }}</td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-outline-info me-1" @click="openViewModal(review)"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm btn-success me-1" @click="handleUpdateStatus(review, 'approved')" title="Duyệt"><i class="bi bi-check-lg"></i></button>
                        <button class="btn btn-sm btn-outline-secondary" @click="handleUpdateStatus(review, 'rejected')" title="Từ chối"><i class="bi bi-x-lg"></i></button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination Pending -->
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
                      <th style="width: 120px">Sao</th>
                      <th>Nội dung</th>
                      <th style="width: 150px">Ngày gửi</th>
                      <th style="width: 150px" class="text-end">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedApproved.data.length === 0">
                      <td colspan="7" class="text-center py-4 text-muted">Chưa có đánh giá nào được duyệt.</td>
                    </tr>
                    <tr v-for="review in pagedApproved.data" :key="review.id">
                      <td>{{ review.id }}</td>
                      <td><span class="fw-bold text-primary">{{ review.product?.name }}</span></td>
                      <td>{{ review.user?.username || 'Ẩn danh' }}</td>
                      <td class="text-warning">{{ renderStars(review.rating) }}</td>
                      <td><span class="d-inline-block text-truncate" style="max-width: 200px;" :title="review.content">{{ review.content }}</span></td>
                      <td class="small text-muted">{{ formatDate(review.created_at) }}</td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-outline-info me-1" @click="openViewModal(review)"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm btn-outline-secondary me-1" @click="handleUpdateStatus(review, 'rejected')" title="Gỡ bỏ"><i class="bi bi-x-lg"></i></button>
                        <button class="btn btn-sm btn-outline-danger" @click="handleDelete(review)"><i class="bi bi-trash"></i></button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination Approved -->
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
                      <th style="width: 120px">Sao</th>
                      <th>Nội dung</th>
                      <th style="width: 150px">Ngày gửi</th>
                      <th style="width: 150px" class="text-end">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="pagedRejected.data.length === 0">
                      <td colspan="7" class="text-center py-4 text-muted">Chưa có đánh giá nào bị từ chối.</td>
                    </tr>
                    <tr v-for="review in pagedRejected.data" :key="review.id">
                      <td>{{ review.id }}</td>
                      <td><span class="fw-bold text-primary">{{ review.product?.name }}</span></td>
                      <td>{{ review.user?.username || 'Ẩn danh' }}</td>
                      <td class="text-warning">{{ renderStars(review.rating) }}</td>
                      <td><span class="d-inline-block text-truncate" style="max-width: 200px;" :title="review.content">{{ review.content }}</span></td>
                      <td class="small text-muted">{{ formatDate(review.created_at) }}</td>
                      <td class="text-end">
                        <button class="btn btn-sm btn-outline-info me-1" @click="openViewModal(review)"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm btn-outline-success me-1" @click="handleUpdateStatus(review, 'approved')" title="Duyệt lại"><i class="bi bi-check-lg"></i></button>
                        <button class="btn btn-sm btn-outline-danger" @click="handleDelete(review)"><i class="bi bi-trash"></i></button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <!-- Pagination Rejected -->
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
          <h5 class="modal-title">Chi tiết đánh giá #{{ viewingReview.id }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-4">
            <!-- CỘT TRÁI: THÔNG TIN SẢN PHẨM -->
            <div class="col-md-5 text-center border-end">
              <div class="mb-3">
                <img 
                  :src="getProductImageUrl(viewingReview.product?.thumbnail_url)" 
                  class="img-fluid rounded shadow-sm" 
                  style="max-height: 250px; object-fit: contain;" 
                  alt="Product Image"
                >
              </div>
              <h5 class="text-primary fw-bold">{{ viewingReview.product?.name || 'Sản phẩm không rõ' }}</h5>
              <p class="text-muted small">ID Sản phẩm: {{ viewingReview.product?.id }}</p>
              <div class="mt-3">
                <span v-if="viewingReview.status === 'pending'" class="badge bg-warning text-dark fs-6">Đang chờ duyệt</span>
                <span v-if="viewingReview.status === 'approved'" class="badge bg-success fs-6">Đã duyệt</span>
                <span v-if="viewingReview.status === 'rejected'" class="badge bg-danger fs-6">Đã từ chối</span>
              </div>
            </div>

            <!-- CỘT PHẢI: THÔNG TIN ĐÁNH GIÁ -->
            <div class="col-md-7">
              <h6 class="border-bottom pb-2 mb-3 text-secondary text-uppercase small fw-bold">Thông tin người gửi</h6>
              <div class="d-flex align-items-center mb-4">
                <img 
                  :src="viewingReview.user?.avatar || `https://placehold.co/50x50/EBF4FF/1D62F0?text=${viewingReview.user?.username?.charAt(0).toUpperCase() || 'U'}`" 
                  class="rounded-circle me-3" width="50" height="50"
                >
                <div>
                  <div class="fw-bold fs-5">{{ viewingReview.user?.username || 'Người dùng ẩn danh' }}</div>
                  <div class="text-muted small">ID User: {{ viewingReview.user?.id }}</div>
                </div>
              </div>

              <h6 class="border-bottom pb-2 mb-3 text-secondary text-uppercase small fw-bold">Nội dung đánh giá</h6>
              <div class="mb-2">
                <span class="text-warning fs-4 me-2">{{ renderStars(viewingReview.rating) }}</span>
                <span class="text-muted small">({{ viewingReview.rating }}/5 sao)</span>
              </div>
              <div class="bg-light p-3 rounded mb-3" style="min-height: 100px;">
                <i class="bi bi-quote fs-4 text-secondary opacity-50"></i>
                <p class="mb-0 fst-italic text-dark">{{ viewingReview.content }}</p>
              </div>
              <div class="text-end text-muted small">
                <i class="bi bi-clock me-1"></i> Gửi lúc: {{ formatDate(viewingReview.created_at) }}
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