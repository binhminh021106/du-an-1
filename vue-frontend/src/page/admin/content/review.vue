<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

// --- STATE QUẢN LÝ ---
const API_URL = import.meta.env.VITE_API_BASE_URL;

const reviews = ref([]);     // Danh sách đánh giá trên trang hiện tại
const isLoading = ref(true);

// State cho phân trang
const pagination = reactive({
  currentPage: 1,
  itemsPerPage: 10, // Hiển thị 10 đánh giá mỗi trang
  totalItems: 0,
  totalPages: 1
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchReviews(1); // Tải trang đầu tiên
});

// --- CÁC HÀM TẢI DỮ LIỆU ---

/**
 * Tải danh sách đánh giá (Có phân trang)
 * @param {number} page - Trang cần tải
 */
async function fetchReviews(page = 1) {
  isLoading.value = true;
  if (page < 1 || (page > pagination.totalPages && pagination.totalItems > 0)) {
    isLoading.value = false;
    return;
  }
  
  pagination.currentPage = page;

  try {
    // _page: Trang hiện tại
    // _limit: Số lượng/trang
    // _sort=id&_order=desc: Sắp xếp theo ID, mới nhất lên đầu
    // _expand=product&_expand=user: Lấy kèm thông tin sản phẩm và người dùng
    const response = await axios.get(
      `${API_URL}/reviews?_page=${pagination.currentPage}&_limit=${pagination.itemsPerPage}&_sort=id&_order=desc&_expand=product&_expand=user`
    );
    
    reviews.value = response.data;
    
    // json-server trả về tổng số mục trong header 'x-total-count'
    pagination.totalItems = parseInt(response.headers['x-total-count'] || 0);
    pagination.totalPages = Math.ceil(pagination.totalItems / pagination.itemsPerPage);
    
  } catch (error) {
    console.error("Lỗi khi tải đánh giá:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách đánh giá. Bạn đã chạy "json-server" và thêm bảng "reviews" vào db.json chưa?', 'error');
  } finally {
    isLoading.value = false;
  }
}

// --- CÁC HÀM HELPER ---

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleString('vi-VN');
}

// Hàm helper để lấy class CSS cho badge trạng thái
function getStatusBadge(status) {
  switch (status) {
    case 'pending':
      return 'text-bg-warning'; // Chờ duyệt
    case 'approved':
      return 'text-bg-success'; // Đã duyệt
    case 'rejected':
      return 'text-bg-danger';  // Đã từ chối
    default:
      return 'text-bg-secondary';
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

// --- CÁC HÀM CRUD ---

/**
 * Cập nhật trạng thái (Duyệt / Từ chối)
 * @param {object} review - Đánh giá cần cập nhật
 * @param {string} newStatus - Trạng thái mới ('approved' hoặc 'rejected')
 */
async function handleUpdateStatus(review, newStatus) {
  try {
    // Gửi PATCH request chỉ để cập nhật 1 trường 'status'
    const response = await axios.patch(`${API_URL}/reviews/${review.id}`, {
      status: newStatus
    });
    
    // Cập nhật lại dữ liệu trên giao diện mà không cần tải lại
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
      await axios.delete(`${API_URL}/reviews/${review.id}`);
      Swal.fire(
        'Đã xóa!',
        'Đánh giá đã được xóa.',
        'success'
      );
      
      // Tải lại dữ liệu
      if (reviews.value.length === 1 && pagination.currentPage > 1) {
        fetchReviews(pagination.currentPage - 1);
      } else {
        fetchReviews(pagination.currentPage);
      }
      
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
              <h3 class="card-title">Danh sách Đánh giá</h3>
            </div>
            
            <div class="card-body p-0">
              <!-- Hiển thị loading -->
              <div v-if="isLoading && reviews.length === 0" class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>

              <!-- Hiển thị bảng dữ liệu -->
              <table v-else class="table table-hover table-striped">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Người gửi</th>
                    <th>Sản phẩm</th>
                    <th style="width: 120px">Xếp hạng</th>
                    <th>Nội dung</th>
                    <th style="width: 120px">Trạng thái</th>
                    <th style="width: 160px">Ngày gửi</th>
                    <th style="width: 200px">Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="reviews.length === 0">
                    <td colspan="8" class="text-center">Không có đánh giá nào</td>
                  </tr>
                  <tr v-for="review in reviews" :key="review.id">
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
                        {{ review.content.length > 50 ? review.content.substring(0, 50) + '...' : review.content }}
                      </span>
                    </td>
                    <td>
                      <span class="badge" :class="getStatusBadge(review.status)">
                        {{ review.status }}
                      </span>
                    </td>
                    <td>{{ formatDate(review.createdAt) }}</td>
                    <td>
                      <button v-if="review.status !== 'approved'" 
                              class="btn btn-success btn-sm me-2" 
                              @click="handleUpdateStatus(review, 'approved')">
                        <i class="bi bi-check-lg"></i> Duyệt
                      </button>
                      <button v-if="review.status !== 'rejected'" 
                              class="btn btn-secondary btn-sm me-2" 
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
            <!-- /.card-body -->
            
            <!-- 3. PHÂN TRANG (PAGINATION) -->
            <div class="card-footer clearfix" v-if="!isLoading && pagination.totalPages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <!-- Nút Về trang trước -->
                <li class="page-item" :class="{ disabled: pagination.currentPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="fetchReviews(pagination.currentPage - 1)">&laquo;</a>
                </li>
                
                <!-- Các trang -->
                <li v-for="page in pagination.totalPages" :key="page" 
                    class="page-item" 
                    :class="{ active: pagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="fetchReviews(page)">{{ page }}</a>
                </li>

                <!-- Nút Tới trang sau -->
                <li class="page-item" :class="{ disabled: pagination.currentPage === pagination.totalPages }">
                  <a class="page-link" href="#" @click.prevent="fetchReviews(pagination.currentPage + 1)">&raquo;</a>
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