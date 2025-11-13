<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---

const allReviews = ref([]); // Danh sách TẤT CẢ đánh giá
const isLoading = ref(true);
const searchQuery = ref('');

// State cho Modal Xem
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingReview = ref({});

// State cho phân trang (cục bộ)
const localPagination = reactive({
  currentPage: 1,
  itemsPerPage: 10, // Hiển thị 10 đánh giá mỗi trang
});

// --- COMPUTED ---

// Lọc đánh giá dựa trên tìm kiếm
const filteredReviews = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return allReviews.value;
  }
  return allReviews.value.filter(review =>
    (review.user?.username && review.user.username.toLowerCase().includes(query)) ||
    (review.product?.name && review.product.name.toLowerCase().includes(query)) ||
    (review.content && review.content.toLowerCase().includes(query))
  );
});

// Tính tổng số trang
const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredReviews.value.length / localPagination.itemsPerPage));
});

// Lấy danh sách đánh giá đã phân trang
const paginatedReviews = computed(() => {
  // Reset về trang 1 nếu trang hiện tại không hợp lệ
  if (localPagination.currentPage > totalPages.value) {
    localPagination.currentPage = 1;
  }
  const start = (localPagination.currentPage - 1) * localPagination.itemsPerPage;
  const end = start + localPagination.itemsPerPage;
  return filteredReviews.value.slice(start, end);
});

// --- WATCHERS ---
watch(searchQuery, () => {
  localPagination.currentPage = 1; // Reset về trang 1 khi tìm kiếm
});


// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchReviews(); // Tải tất cả đánh giá
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- CÁC HÀM TẢI DỮ LIỆU ---

/**
 * Tải TẤT CẢ đánh giá
 */
async function fetchReviews() {
  isLoading.value = true;
  try {
    // Tải tất cả, sắp xếp theo ID, và lấy kèm product, user
    const response = await apiService.get(
      `/reviews?_sort=id&_order=desc&_expand=product&_expand=user`
    );

    allReviews.value = response.data;

  } catch (error) {
    console.error("Lỗi khi tải đánh giá:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách đánh giá.', 'error');
  } finally {
    isLoading.value = false;
  }
}

// --- CÁC HÀM HELPER ---

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleString('vi-VN');
}

// Hàm helper để lấy class CSS và TEXT cho badge trạng thái
function getStatusBadge(status) {
  switch (status) {
    case 'pending':
      return { class: 'text-bg-warning', text: 'Chờ duyệt' };
    case 'approved':
      return { class: 'text-bg-success', text: 'Đã duyệt' };
    case 'rejected':
      return { class: 'text-bg-danger', text: 'Đã từ chối' };
    default:
      return { class: 'text-bg-secondary', text: status };
  }
}

/**
 * Hiển thị số sao
 * @param {number} rating - Số sao (1-5)
 */
function renderStars(rating) {
  let stars = '';
  for (let i = 0; i < 5; i++) {
    stars += (i < rating) ? '★' : '☆';
  }
  return stars;
}

// Chuyển trang
function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    localPagination.currentPage = page;
  }
}

// --- CÁC HÀM CRUD ---

/**
 * Mở modal xem chi tiết
 * @param {object} review - Đánh giá để xem
 */
function openViewModal(review) {
  viewingReview.value = review;
  viewModalInstance.value.show();
}

/**
 * Cập nhật trạng thái (Duyệt / Từ chối)
 * @param {object} review - Đánh giá cần cập nhật
 * @param {string} newStatus - Trạng thái mới ('approved' hoặc 'rejected')
 */
async function handleUpdateStatus(review, newStatus) {
  try {
    const response = await apiService.patch(`/reviews/${review.id}`, {
      status: newStatus
    });

    // Cập nhật lại dữ liệu trên giao diện
    review.status = response.data.status;

    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: `Đã ${newStatus === 'approved' ? 'duyệt' : 'từ chối'} đánh giá!`,
      showConfirmButton: false,
      timer: 2000
    });

  } catch (error) {
    console.error("Lỗi khi cập nhật trạng thái:", error);
    Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
  }
}


/**
 * Xử lý xóa đánh giá
 * @param {object} review - Đánh giá cần xóa
 */
async function handleDelete(review) {
  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn sẽ xóa vĩnh viễn đánh giá này!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Đồng ý xóa!',
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`/reviews/${review.id}`);
      Swal.fire(
        'Đã xóa!',
        'Đánh giá đã được xóa.',
        'success'
      );

      // Tải lại dữ liệu
      fetchReviews();

    } catch (error) {
      console.error("Lỗi khi xóa đánh giá:", error);
      Swal.fire('Lỗi', 'Không thể xóa đánh giá này.', 'error');
    }
  }
}
</script>

<template>
  <!-- 1. Header của trang -->
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Đánh giá</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">
              Đánh giá
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <!-- 2. Nội dung chính của trang -->
  <div class="app-content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <!-- Card chứa bảng -->
          <div class="card">
            <div class="card-header">
              <!-- Thêm Thanh tìm kiếm -->
              <div class="row align-items-center">
                <div class="col-md-6 col-12 mb-2 mb-md-0">
                  <h3 class="card-title mb-0">Danh sách Đánh giá</h3>
                </div>
                <div class="col-md-6 col-12">
                  <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                      <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 ps-0"
                      placeholder="Tìm theo người gửi, sản phẩm, nội dung..." v-model="searchQuery">
                  </div>
                </div>
              </div>
            </div>

            <div class="card-body p-0">
              <!-- Hiển thị loading -->
              <div v-if="isLoading && allReviews.length === 0" class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>

              <!-- Hiển thị bảng dữ liệu -->
              <div v-else class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Người gửi</th>
                      <th>Sản phẩm</th>
                      <th style="width: 120px">Xếp hạng</th>
                      <th>Nội dung</th>
                      <th style="width: 120px">Trạng thái</th>
                      <th style="width: 160px">Ngày gửi</th>
                      <th style="width: 220px">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="paginatedReviews.length === 0">
                      <td colspan="8" class="text-center">
                        {{ searchQuery ? 'Không tìm thấy đánh giá nào.' : 'Không có đánh giá nào.' }}
                      </td>
                    </tr>
                    <tr v-for="review in paginatedReviews" :key="review.id">
                      <td>{{ review.id }}</td>
                      <td>{{ review.user?.username || '(Không rõ)' }}</td>
                      <td>{{ review.product?.name || '(Không rõ)' }}</td>
                      <td>
                        <span class="text-warning" style="font-size: 1.1rem; letter-spacing: 1px;">
                          {{ renderStars(review.rating) }}
                        </span>
                      </td>
                      <td>
                        <span :title="review.content">
                          {{ review.content.length > 50 ? review.content.substring(0, 50) + '...' : review.content
                          }}
                        </span>
                      </td>
                      <td>
                        <!-- Cập nhật badge để hiển thị text Tiếng Việt -->
                        <span class="badge" :class="getStatusBadge(review.status).class">
                          {{ getStatusBadge(review.status).text }}
                        </span>
                      </td>
                      <td>{{ formatDate(review.createdAt) }}</td>
                      <td>
                        <!-- Thêm nút Xem -->
                        <button class="btn btn-info btn-sm me-1" @click="openViewModal(review)" title="Xem chi tiết">
                          <i class="bi bi-eye"></i>
                        </button>

                        <button v-if="review.status !== 'approved'" class="btn btn-success btn-sm me-1"
                          @click="handleUpdateStatus(review, 'approved')">
                          <i class="bi bi-check-lg"></i> Duyệt
                        </button>
                        <button v-if="review.status !== 'rejected'" class="btn btn-secondary btn-sm me-1"
                          @click="handleUpdateStatus(review, 'rejected')">
                          <i class="bi bi-x-lg"></i> Từ chối
                        </button>
                        <button class="btn btn-danger btn-sm" @click="handleDelete(review)">
                          <i class="bi bi-trash"></i> Xóa
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <!-- /.card-body -->

            <!-- 3. PHÂN TRANG (PAGINATION) -->
            <div class="card-footer clearfix" v-if="!isLoading && totalPages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <!-- Nút Về trang trước -->
                <li class="page-item" :class="{ disabled: localPagination.currentPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="goToPage(localPagination.currentPage - 1)">&laquo;</a>
                </li>

                <!-- Các trang -->
                <li v-for="page in totalPages" :key="page" class="page-item"
                  :class="{ active: localPagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="goToPage(page)">{{ page }}</a>
                </li>

                <!-- Nút Tới trang sau -->
                <li class="page-item" :class="{ disabled: localPagination.currentPage === totalPages }">
                  <a class="page-link" href="#" @click.prevent="goToPage(localPagination.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
            <!-- /.card-footer -->

          </div>
          <!-- /.card -->
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Xem Chi Tiết (ĐÃ CẬP NHẬT) -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <!-- Status Badge -->
          <div class="position-absolute top-0 start-0 m-3">
            <span class="badge" :class="getStatusBadge(viewingReview.status).class">
              {{ getStatusBadge(viewingReview.status).text }}
            </span>
          </div>

          <!-- Thông tin SẢN PHẨM (MỚI) -->
          <div class="text-center mb-4 mt-3">
            <img
              :src="viewingReview.product?.images?.[0]?.url || 'https://placehold.co/120x120/EBF4FF/1D62F0?text=N/A'"
              class="img-thumbnail shadow-sm" alt="Product Image"
              style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px;">
            <h5 class="mt-3 mb-1">{{ viewingReview.product?.name || 'Sản phẩm không rõ' }}</h5>
            <p class="text-muted mb-0">ID Sản phẩm: {{ viewingReview.product?.id || 'N/A' }}</p>
          </div>

          <!-- Chi tiết đánh giá -->
          <div class="list-group list-group-flush">
            <div class="list-group-item px-0 text-center">
              <h6 class="mb-2"><i class="bi bi-star me-2 text-warning"></i>Xếp hạng</h6>
              <span class="text-warning" style="font-size: 1.5rem; letter-spacing: 2px;">
                {{ renderStars(viewingReview.rating) }}
              </span>
            </div>
             <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-chat-dots me-3 text-muted"></i>Nội dung đánh giá</h6>
               <p class="mb-1 text-muted small" style="white-space: pre-wrap;">{{ viewingReview.content || 'Không có nội dung.' }}</p>
            </div>
            <!-- Thông tin người gửi (Đã di chuyển xuống) -->
             <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-person me-3 text-primary"></i>Người gửi</h6>
                <div class="d-flex align-items-center">
                   <img
                    :src="viewingReview.user?.avatar || `https://placehold.co/40x40/EBF4FF/1D62F0?text=${viewingReview.user?.username ? viewingReview.user.username.charAt(0).toUpperCase() : 'U'}`"
                    class="rounded-circle" alt="Avatar"
                    style="width: 40px; height: 40px; object-fit: cover; margin-right: 10px;">
                    <span class="mb-1 text-muted small">{{ viewingReview.user?.username || 'Người dùng ẩn danh' }} (ID: {{ viewingReview.user?.id || 'N/A' }})</span>
                </div>
            </div>
            <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-calendar-event me-3 text-muted"></i>Ngày gửi</h6>
               <p class="mb-1 text-muted small">{{ formatDate(viewingReview.createdAt) }}</p>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.table td .btn {
  margin-top: 2px;
  margin-bottom: 2px;
  font-size: 0.8rem;
}

.card-body.p-0 .table {
  margin-bottom: 0;
}

.text-warning {
  color: #ffc107 !important;
}
</style>