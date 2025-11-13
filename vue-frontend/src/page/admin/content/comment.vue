<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---

const allComments = ref([]); // Danh sách TẤT CẢ bình luận
const isLoading = ref(true);
const searchQuery = ref('');

// State cho Modal Xem
const viewModalRef = ref(null);
const viewModalInstance = ref(null);
const viewingComment = ref({});

// State cho phân trang (cục bộ)
const localPagination = reactive({
  currentPage: 1,
  itemsPerPage: 10,
});

// --- COMPUTED ---

// Lọc bình luận dựa trên tìm kiếm
const filteredComments = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return allComments.value;
  }
  return allComments.value.filter(comment =>
    (comment.user?.username && comment.user.username.toLowerCase().includes(query)) ||
    (comment.product?.name && comment.product.name.toLowerCase().includes(query)) ||
    (comment.content && comment.content.toLowerCase().includes(query))
  );
});

// Tính tổng số trang
const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredComments.value.length / localPagination.itemsPerPage));
});

// Lấy danh sách bình luận đã phân trang
const paginatedComments = computed(() => {
  // Reset về trang 1 nếu trang hiện tại không hợp lệ
  if (localPagination.currentPage > totalPages.value) {
    localPagination.currentPage = 1;
  }
  const start = (localPagination.currentPage - 1) * localPagination.itemsPerPage;
  const end = start + localPagination.itemsPerPage;
  return filteredComments.value.slice(start, end);
});

// --- WATCHERS ---
watch(searchQuery, () => {
  localPagination.currentPage = 1; // Reset về trang 1 khi tìm kiếm
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchComments(); // Tải tất cả bình luận
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- CÁC HÀM TẢI DỮ LIỆU ---

/**
 * Tải TẤT CẢ bình luận
 */
async function fetchComments() {
  isLoading.value = true;
  try {
    const response = await apiService.get(
      `/comments?_sort=id&_order=desc&_expand=product&_expand=user`
    );
    allComments.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải bình luận:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách bình luận.', 'error');
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

// Chuyển trang
function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    localPagination.currentPage = page;
  }
}

// --- CÁC HÀM CRUD ---

/**
 * Mở modal xem chi tiết
 * @param {object} comment - Bình luận để xem
 */
function openViewModal(comment) {
  viewingComment.value = comment;
  viewModalInstance.value.show();
}

/**
 * Cập nhật trạng thái (Duyệt / Từ chối)
 * @param {object} comment - Bình luận cần cập nhật
 * @param {string} newStatus - Trạng thái mới ('approved' hoặc 'rejected')
 */
async function handleUpdateStatus(comment, newStatus) {
  try {
    const response = await apiService.patch(`/comments/${comment.id}`, {
      status: newStatus
    });
    // Cập nhật lại dữ liệu trên giao diện mà không cần tải lại
    comment.status = response.data.status;

    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: `Đã ${newStatus === 'approved' ? 'duyệt' : 'từ chối'} bình luận!`,
      showConfirmButton: false,
      timer: 2000
    });

  } catch (error) {
    console.error("Lỗi khi cập nhật trạng thái:", error);
    Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
  }
}


/**
 * Xử lý xóa bình luận
 * @param {object} comment - Bình luận cần xóa
 */
async function handleDelete(comment) {
  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn sẽ xóa vĩnh viễn bình luận này!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Đồng ý xóa!',
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    try {
      await apiService.delete(`/comments/${comment.id}`);
      Swal.fire(
        'Đã xóa!',
        'Bình luận đã được xóa.',
        'success'
      );
      
      // Tải lại toàn bộ dữ liệu
      fetchComments();

    } catch (error) {
      console.error("Lỗi khi xóa bình luận:", error);
      Swal.fire('Lỗi', 'Không thể xóa bình luận này.', 'error');
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
          <h3 class="mb-0">Quản lý Bình luận</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">
              Bình luận
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
              <div class="row align-items-center">
                <div class="col-md-6 col-12 mb-2 mb-md-0">
                  <h3 class="card-title mb-0">Danh sách Bình luận</h3>
                </div>
                <div class="col-md-6 col-12">
                  <!-- THANH TÌM KIẾM -->
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
              <div v-if="isLoading && allComments.length === 0" class="text-center p-5">
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
                      <th>Nội dung</th>
                      <th style="width: 120px">Trạng thái</th>
                      <th style="width: 160px">Ngày gửi</th>
                      <th style="width: 220px">Hành động</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="paginatedComments.length === 0">
                      <td colspan="7" class="text-center">
                        {{ searchQuery ? 'Không tìm thấy bình luận nào.' : 'Không có bình luận nào.' }}
                      </td>
                    </tr>
                    <tr v-for="comment in paginatedComments" :key="comment.id">
                      <td>{{ comment.id }}</td>
                      <td>{{ comment.user?.username || '(Không rõ)' }}</td>
                      <td>{{ comment.product?.name || '(Không rõ)' }}</td>
                      <td>
                        <span :title="comment.content">
                          {{ comment.content.length > 50 ? comment.content.substring(0, 50) + '...' : comment.content
                          }}
                        </span>
                      </td>
                      <td>
                        <span class="badge" :class="getStatusBadge(comment.status).class">
                          {{ getStatusBadge(comment.status).text }} <!-- Hiển thị text tiếng Việt -->
                        </span>
                      </td>
                      <td>{{ formatDate(comment.createdAt) }}</td>
                      <td>
                        <!-- Nút XEM MỚI -->
                         <button class="btn btn-info btn-sm me-1" @click="openViewModal(comment)" title="Xem chi tiết">
                            <i class="bi bi-eye"></i>
                          </button>
                          
                        <button v-if="comment.status !== 'approved'" class="btn btn-success btn-sm me-1"
                          @click="handleUpdateStatus(comment, 'approved')">
                          <i class="bi bi-check-lg"></i> Duyệt
                        </button>
                        <button v-if="comment.status !== 'rejected'" class="btn btn-secondary btn-sm me-1"
                          @click="handleUpdateStatus(comment, 'rejected')">
                          <i class="bi bi-x-lg"></i> Từ chối
                        </button>
                        <button class="btn btn-danger btn-sm" @click="handleDelete(comment)">
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

  <!-- Modal Xem Chi Tiết (MỚI) -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <!-- Status Badge -->
          <div class="position-absolute top-0 start-0 m-3">
            <span class="badge" :class="getStatusBadge(viewingComment.status).class">
              {{ getStatusBadge(viewingComment.status).text }}
            </span>
          </div>

          <!-- Thông tin người gửi -->
          <div class="text-center mb-4 mt-3">
             <img
              :src="viewingComment.user?.avatar || `https://placehold.co/120x120/EBF4FF/1D62F0?text=${viewingComment.user?.username ? viewingComment.user.username.charAt(0).toUpperCase() : 'U'}`"
              class="rounded-circle img-thumbnail shadow-sm" alt="Avatar"
              style="width: 120px; height: 120px; object-fit: cover;">
            <h5 class="mt-3 mb-1">{{ viewingComment.user?.username || 'Người dùng ẩn danh' }}</h5>
            <p class="text-muted mb-0">ID: {{ viewingComment.user?.id || 'N/A' }}</p>
          </div>

          <!-- Chi tiết bình luận -->
          <div class="list-group list-group-flush">
             <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-box-seam me-3 text-primary"></i>Sản phẩm</h6>
               <p class="mb-1 text-muted small">{{ viewingComment.product?.name || '(Không rõ)' }}</p>
            </div>
             <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-chat-dots me-3 text-muted"></i>Nội dung bình luận</h6>
               <p class="mb-1 text-muted small" style="white-space: pre-wrap;">{{ viewingComment.content || 'Không có nội dung.' }}</p>
            </div>
            <div class="list-group-item px-0">
               <h6 class="mb-2"><i class="bi bi-calendar-event me-3 text-muted"></i>Ngày gửi</h6>
               <p class="mb-1 text-muted small">{{ formatDate(viewingComment.createdAt) }}</p>
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
  /* Thu nhỏ nút cho vừa vặn */
}

.card-body.p-0 .table {
  margin-bottom: 0;
}
</style>