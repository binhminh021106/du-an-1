<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

// --- STATE QUẢN LÝ ---
const API_URL = import.meta.env.VITE_API_BASE_URL;

const comments = ref([]);     // Danh sách bình luận trên trang hiện tại
const isLoading = ref(true);

// State cho phân trang
const pagination = reactive({
  currentPage: 1,
  itemsPerPage: 10, // Hiển thị 10 bình luận mỗi trang
  totalItems: 0,
  totalPages: 1
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchComments(1); // Tải trang đầu tiên
});

// --- CÁC HÀM TẢI DỮ LIỆU ---

/**
 * Tải danh sách bình luận (Có phân trang)
 * @param {number} page - Trang cần tải
 */
async function fetchComments(page = 1) {
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
      `${API_URL}/comments?_page=${pagination.currentPage}&_limit=${pagination.itemsPerPage}&_sort=id&_order=desc&_expand=product&_expand=user`
    );
    
    comments.value = response.data;
    
    // json-server trả về tổng số mục trong header 'x-total-count'
    pagination.totalItems = parseInt(response.headers['x-total-count'] || 0);
    pagination.totalPages = Math.ceil(pagination.totalItems / pagination.itemsPerPage);
    
  } catch (error) {
    console.error("Lỗi khi tải bình luận:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách bình luận. Bạn đã chạy "json-server" chưa?', 'error');
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

// --- CÁC HÀM CRUD ---

/**
 * Cập nhật trạng thái (Duyệt / Từ chối)
 * @param {object} comment - Bình luận cần cập nhật
 * @param {string} newStatus - Trạng thái mới ('approved' hoặc 'rejected')
 */
async function handleUpdateStatus(comment, newStatus) {
  try {
    // Gửi PATCH request chỉ để cập nhật 1 trường 'status'
    const response = await axios.patch(`${API_URL}/comments/${comment.id}`, {
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
      await axios.delete(`${API_URL}/comments/${comment.id}`);
      Swal.fire(
        'Đã xóa!',
        'Bình luận đã được xóa.',
        'success'
      );
      
      // Tải lại dữ liệu (có thể cần quay về trang 1 nếu trang hiện tại hết dữ liệu)
      if (comments.value.length === 1 && pagination.currentPage > 1) {
        fetchComments(pagination.currentPage - 1);
      } else {
        fetchComments(pagination.currentPage);
      }
      
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
              <h3 class="card-title">Danh sách Bình luận</h3>
              <!-- Không có nút Thêm mới -->
            </div>
            
            <div class="card-body p-0">
              <!-- Hiển thị loading -->
              <div v-if="isLoading && comments.length === 0" class="text-center p-5">
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
                    <th>Nội dung</th>
                    <th style="width: 120px">Trạng thái</th>
                    <th style="width: 160px">Ngày gửi</th>
                    <th style="width: 200px">Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="comments.length === 0">
                    <td colspan="7" class="text-center">Không có bình luận nào</td>
                  </tr>
                  <tr v-for="comment in comments" :key="comment.id">
                    <td>{{ comment.id }}</td>
                    <td>{{ comment.user?.username || '(Không rõ)' }}</td>
                    <td>{{ comment.product?.name || '(Không rõ)' }}</td>
                    <td>
                      <span :title="comment.content">
                        {{ comment.content.length > 60 ? comment.content.substring(0, 60) + '...' : comment.content }}
                      </span>
                    </td>
                    <td>
                      <span class="badge" :class="getStatusBadge(comment.status)">
                        {{ comment.status }}
                      </span>
                    </td>
                    <td>{{ formatDate(comment.createdAt) }}</td>
                    <td>
                      <button v-if="comment.status !== 'approved'" 
                              class="btn btn-success btn-sm me-2" 
                              @click="handleUpdateStatus(comment, 'approved')">
                        <i class="bi bi-check-lg"></i> Duyệt
                      </button>
                      <button v-if="comment.status !== 'rejected'" 
                              class="btn btn-secondary btn-sm me-2" 
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
            <!-- /.card-body -->
            
            <!-- 3. PHÂN TRANG (PAGINATION) -->
            <div class="card-footer clearfix" v-if="!isLoading && pagination.totalPages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <!-- Nút Về trang trước -->
                <li class="page-item" :class="{ disabled: pagination.currentPage === 1 }">
                  <a class="page-link" href="#" @click.prevent="fetchComments(pagination.currentPage - 1)">&laquo;</a>
                </li>
                
                <!-- Các trang -->
                <li v-for="page in pagination.totalPages" :key="page" 
                    class="page-item" 
                    :class="{ active: pagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="fetchComments(page)">{{ page }}</a>
                </li>

                <!-- Nút Tới trang sau -->
                <li class="page-item" :class="{ disabled: pagination.currentPage === pagination.totalPages }">
                  <a class="page-link" href="#" @click.prevent="fetchComments(pagination.currentPage + 1)">&raquo;</a>
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
  font-size: 0.8rem; /* Thu nhỏ nút cho vừa vặn */
}
.card-body.p-0 .table {
  margin-bottom: 0;
}
</style>