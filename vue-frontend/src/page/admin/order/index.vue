<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---
const API_URL = import.meta.env.VITE_API_BASE_URL;

// (SỬA) Tách riêng các state loading
const isMainLoading = ref(true); 
const isReturnedLoading = ref(true); 
const isDetailModalLoading = ref(false); 
const isSavingStatus = ref(false); 

// State cho 2 Modal
const detailModalRef = ref(null);
const detailModalInstance = ref(null);
const statusModalRef = ref(null);
const statusModalInstance = ref(null);

// State lưu dữ liệu tạm thời
const selectedOrder = ref(null);
const selectedOrderStatus = ref('');
const selectedOrderItems = ref([]);

// --- STATE BẢNG ĐƠN HÀNG CHÍNH ---
const mainOrders = ref([]);
const mainPagination = reactive({
  currentPage: 1,
  itemsPerPage: 10,
  totalItems: 0,
  totalPages: 1
});

// --- STATE BẢNG HÀNG HOÀN ---
const returnedOrders = ref([]);
const returnedPagination = reactive({
  currentPage: 1,
  itemsPerPage: 10,
  totalItems: 0,
  totalPages: 1
});

// --- (MỚI) LOGIC TRẠNG THÁI TUẦN TỰ ---
const STATUS_HIERARCHY = {
  'Chờ xử lý': 1,
  'Đã duyệt': 2,
  'Đang giao': 3,
  'Đã hoàn thành': 4,  // Trạng thái cuối của luồng chính
  'Đã hủy': -1,         // Trạng thái cuối (riêng biệt)
  'Hàng hoàn': 10,       // Trạng thái đầu của luồng hoàn
  'Hoàn hàng thành công': 11 // Trạng thái cuối của luồng hoàn
};

const ALL_STATUS_OPTIONS = [
  { value: 'Chờ xử lý', text: 'Chờ xử lý' },
  { value: 'Đã duyệt', text: 'Đã duyệt (Chuẩn bị hàng)' },
  { value: 'Đang giao', text: 'Đang giao (Đang vận chuyển)' },
  { value: 'Đã hoàn thành', text: 'Đã hoàn thành (Đã giao hàng)' },
  { value: 'Đã hủy', text: 'Đã hủy' },
  { value: 'Hàng hoàn', text: 'Hàng hoàn (Trả về)' },
  { value: 'Hoàn hàng thành công', text: 'Hoàn hàng thành công' }
];

/**
 * (MỚI) Computed property để lọc các trạng thái CÓ THỂ chọn
 */
const availableStatuses = computed(() => {
  if (!selectedOrder.value) {
    return [];
  }

  const currentStatus = selectedOrder.value.status;
  const currentLevel = STATUS_HIERARCHY[currentStatus];

  if (currentLevel === 10) { // 'Hàng hoàn'
    return ALL_STATUS_OPTIONS.filter(opt =>
      opt.value === 'Hàng hoàn' || opt.value === 'Hoàn hàng thành công'
    );
  }
  if (currentLevel === 11) { // 'Hoàn hàng thành công'
    return ALL_STATUS_OPTIONS.filter(opt => opt.value === 'Hoàn hàng thành công');
  }
  if (currentLevel === -1) { // 'Đã hủy'
    return ALL_STATUS_OPTIONS.filter(opt => opt.value === 'Đã hủy');
  }
  if (currentLevel === 4) { // 'Đã hoàn thành'
    return ALL_STATUS_OPTIONS.filter(opt =>
      opt.value === 'Đã hoàn thành' || opt.value === 'Hàng hoàn'
    );
  }
  // Luồng chính (Chờ, Duyệt, Giao)
  return ALL_STATUS_OPTIONS.filter(opt => {
    const optLevel = STATUS_HIERARCHY[opt.value];
    if (optLevel === -1 || optLevel === 10) return true; // Cho phép nhảy sang Hủy/Hoàn
    return optLevel >= currentLevel && optLevel < 10; // Chỉ đi tới
  });
});
// --- KẾT THÚC LOGIC TRẠNG THÁI ---


// --- VÒNG ĐỜI (LIFECYCLE) ---
onMounted(() => {
  fetchMainOrders(1);
  fetchReturnedOrders(1);

  if (detailModalRef.value) {
    detailModalInstance.value = new Modal(detailModalRef.value);
  }
  if (statusModalRef.value) {
    statusModalInstance.value = new Modal(statusModalRef.value);
  }
});

// --- CÁC HÀM TẢI DỮ LIỆU ---

/**
 * NOTE:
 * Để đảm bảo 2 bảng hoàn toàn tách biệt và pagination đúng với dữ liệu đã lọc,
 * ta fetch TOÀN BỘ đơn từ json-server (nhỏ) rồi lọc và slice theo trang.
 * Nếu dataset lớn, có thể thay bằng API backend hỗ trợ filter server-side.
 */

/**
 * Tải danh sách đơn hàng CHÍNH
 */
async function fetchMainOrders(page = 1) {
  isMainLoading.value = true; 
  if (page < 1) {
    isMainLoading.value = false;
    return;
  }

  try {
    // Lấy toàn bộ đơn (sắp xếp desc)
    const response = await axios.get(`${API_URL}/orders?_sort=id&_order=desc`);
    const allOrders = response.data || [];

    // Lọc: chỉ lấy đơn KHÔNG PHẢI là 'Hàng hoàn' và KHÔNG PHẢI là 'Hoàn hàng thành công'
    const filtered = allOrders.filter(order => !['Hàng hoàn', 'Hoàn hàng thành công'].includes(order.status));

    // Cập nhật pagination dựa trên filtered
    mainPagination.totalItems = filtered.length;
    mainPagination.totalPages = Math.max(1, Math.ceil(mainPagination.totalItems / mainPagination.itemsPerPage));

    // Điều chỉnh page nếu quá lớn
    if (page > mainPagination.totalPages) page = mainPagination.totalPages;
    mainPagination.currentPage = page;

    // Lấy page slice
    const start = (mainPagination.currentPage - 1) * mainPagination.itemsPerPage;
    const end = start + mainPagination.itemsPerPage;
    mainOrders.value = filtered.slice(start, end);

  } catch (error) {
    console.error("Lỗi khi tải đơn hàng chính:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách đơn hàng. Bạn đã chạy "json-server" chưa?', 'error');
  } finally {
    isMainLoading.value = false; 
  }
}

/**
 * Tải danh sách đơn hàng HOÀN
 */
async function fetchReturnedOrders(page = 1) {
  isReturnedLoading.value = true;
  if (page < 1 || (page > returnedPagination.totalPages && returnedPagination.totalItems > 0)) {
    isReturnedLoading.value = false;
    return;
  }
  returnedPagination.currentPage = page;

  try {
    const response = await axios.get(
      `${API_URL}/orders?_page=${returnedPagination.currentPage}&_limit=${returnedPagination.itemsPerPage}&_sort=id&_order=desc`
    );

    // ✅ Lọc chuẩn: chỉ lấy đơn có trạng thái là “Hàng hoàn” hoặc “Hoàn hàng thành công”
    returnedOrders.value = response.data.filter(order =>
      order.status.toLowerCase().includes('hoàn')
    );

    returnedPagination.totalItems = parseInt(response.headers['x-total-count'] || 0);
    returnedPagination.totalPages = Math.ceil(returnedPagination.totalItems / returnedPagination.itemsPerPage);
  } catch (error) {
    console.error("Lỗi khi tải đơn hàng hoàn:", error);
  } finally {
    isReturnedLoading.value = false;
  }
}


// --- CÁC HÀM HELPER ---

function formatCurrency(value) {
  if (!value && value !== 0) return '0 đ';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleString('vi-VN');
}

function getStatusBadge(status) {
  switch (status) {
    case 'Chờ xử lý': return 'text-bg-warning';
    case 'Đã duyệt': return 'text-bg-info';
    case 'Đang giao': return 'text-bg-primary';
    case 'Đã hoàn thành': return 'text-bg-success';
    case 'Đã hủy': return 'text-bg-danger';
    case 'Hàng hoàn': return 'text-bg-secondary';
    case 'Hoàn hàng thành công': return 'text-bg-dark'; 
    default: return 'text-bg-dark';
  }
}

// --- CÁC HÀM CRUD MODAL ---

async function openDetailModal(order) {
  selectedOrder.value = order;
  isDetailModalLoading.value = true; 
  detailModalInstance.value.show();

  try {
    const response = await axios.get(
      `${API_URL}/order_items?orderId=${order.id}&_expand=product`
    );
    selectedOrderItems.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải chi tiết đơn hàng:", error);
    detailModalInstance.value.hide();
    Swal.fire('Lỗi', 'Không thể tải chi tiết đơn hàng.', 'error');
  } finally {
    isDetailModalLoading.value = false; 
  }
}

function openStatusModal(order) {
  selectedOrder.value = order;
  selectedOrderStatus.value = order.status;
  statusModalInstance.value.show();
}

async function handleUpdateStatus() {
  if (!selectedOrder.value) return;

  isSavingStatus.value = true; 

  try {
    await axios.patch(`${API_URL}/orders/${selectedOrder.value.id}`, {
      status: selectedOrderStatus.value
    });

    statusModalInstance.value.hide();
    Swal.fire('Thành công', 'Đã cập nhật trạng thái đơn hàng.', 'success');

    // Reload lại 2 bảng (vẫn theo phân trang hiện tại)
    fetchMainOrders(mainPagination.currentPage);
    fetchReturnedOrders(returnedPagination.currentPage);

  } catch (error) {
    console.error("Lỗi khi cập nhật trạng thái:", error);
    Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
  } finally {
    isSavingStatus.value = false; 
  }
}

async function handleDelete(order) {
  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?',
    text: `Bạn sẽ xóa vĩnh viễn đơn hàng #${order.id}!`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Đồng ý xóa!',
    cancelButtonText: 'Hủy bỏ'
  });

  if (result.isConfirmed) {
    try {
      const itemsToDelete = (await axios.get(`${API_URL}/order_items?orderId=${order.id}`)).data;
      await Promise.all(
        itemsToDelete.map(item => axios.delete(`${API_URL}/order_items/${item.id}`))
      );

      await axios.delete(`${API_URL}/orders/${order.id}`);
      Swal.fire('Đã xóa!', 'Đơn hàng đã được xóa.', 'success');

      if (STATUS_HIERARCHY[order.status] >= 10) { 
        if (returnedOrders.value.length === 1 && returnedPagination.currentPage > 1) {
          fetchReturnedOrders(returnedPagination.currentPage - 1);
        } else {
          fetchReturnedOrders(returnedPagination.currentPage);
        }
        fetchMainOrders(mainPagination.currentPage);
      } else { 
        if (mainOrders.value.length === 1 && mainPagination.currentPage > 1) {
          fetchMainOrders(mainPagination.currentPage - 1);
        } else {
          fetchMainOrders(mainPagination.currentPage);
        }
        fetchReturnedOrders(returnedPagination.currentPage);
      }

    } catch (error) {
      console.error("Lỗi khi xóa đơn hàng:", error);
      Swal.fire('Lỗi', 'Không thể xóa đơn hàng này.', 'error');
    }
  }
}
</script>

<template>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Đơn hàng</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">
              Đơn hàng
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">

      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Danh sách Đơn hàng</h3>
            </div>

            <div class="card-body p-0">
              <div v-if="isMainLoading && mainOrders.length === 0" class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>

              <table v-else class="table table-hover table-striped">
                <thead>
                  <tr>
                    <th style="width: 80px">Mã ĐH</th>
                    <th>Khách hàng</th>
                    <th>Điện thoại</th>
                    <th>Tổng tiền</th>
                    <th style="width: 150px">Trạng thái</th>
                    <th style="width: 160px">Ngày đặt</th>
                    <th style="width: 250px">Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="mainOrders.length === 0 && !isMainLoading">
                    <td colspan="7" class="text-center">Không có đơn hàng nào</td>
                  </tr>
                  <tr v-for="order in mainOrders" :key="order.id">
                    <td>#{{ order.id }}</td>
                    <td>{{ order.customerName }}</td>
                    <td>{{ order.customerPhone }}</td>
                    <td>{{ formatCurrency(order.totalAmount) }}</td>
                    <td>
                      <span class="badge" :class="getStatusBadge(order.status)">
                        {{ order.status }}
                      </span>
                    </td>
                    <td>{{ formatDate(order.createdAt) }}</td>
                    <td>
                      <button class="btn btn-info btn-sm me-2" @click="openDetailModal(order)">
                        <i class="bi bi-eye"></i> Xem
                      </button>
                      
                      <button 
                        v-if="!['Đã hoàn thành', 'Đã hủy'].includes(order.status)"
                        class="btn btn-warning btn-sm me-2" 
                        @click="openStatusModal(order)"
                      >
                        <i class="bi bi-pencil-square"></i> Trạng thái
                      </button>
                      
                      <button class="btn btn-danger btn-sm" @click="handleDelete(order)">
                        <i class="bi bi-trash"></i> Xóa
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="card-footer clearfix" v-if="!isMainLoading && mainPagination.totalPages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <li class="page-item" :class="{ disabled: mainPagination.currentPage === 1 }">
                  <a class="page-link" href="#"
                    @click.prevent="fetchMainOrders(mainPagination.currentPage - 1)">&laquo;</a>
                </li>
                <li v-for="page in mainPagination.totalPages" :key="page" class="page-item"
                  :class="{ active: mainPagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="fetchMainOrders(page)">{{ page }}</a>
                </li>
                <li class="page-item" :class="{ disabled: mainPagination.currentPage === mainPagination.totalPages }">
                  <a class="page-link" href="#"
                    @click.prevent="fetchMainOrders(mainPagination.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-4">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Danh sách Hàng hoàn</h3>
            </div>

            <div class="card-body p-0">
              <div v-if="isReturnedLoading && returnedOrders.length === 0" class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading...</span>
                </div>
              </div>
              <table v-else class="table table-hover table-striped">
                <thead>
                  <tr>
                    <th style="width: 80px">Mã ĐH</th>
                    <th>Khách hàng</th>
                    <th>Điện thoại</th>
                    <th>Tổng tiền</th>
                    <th style="width: 170px">Trạng thái</th> 
                    <th style="width: 160px">Ngày đặt</th>
                    <th style="width: 250px">Hành động</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="returnedOrders.length === 0 && !isReturnedLoading">
                    <td colspan="7" class="text-center">Không có đơn hàng hoàn nào</td>
                  </tr>
                  <tr v-for="order in returnedOrders" :key="order.id">
                    <td>#{{ order.id }}</td>
                    <td>{{ order.customerName }}</td>
                    <td>{{ order.customerPhone }}</td>
                    <td>{{ formatCurrency(order.totalAmount) }}</td>
                    <td>
                      <span class="badge" :class="getStatusBadge(order.status)">
                        {{ order.status }}
                      </span>
                    </td>
                    <td>{{ formatDate(order.createdAt) }}</td>
                    <td>
                      <button class="btn btn-info btn-sm me-2" @click="openDetailModal(order)">
                        <i class="bi bi-eye"></i> Xem
                      </button>
                      
                      <button 
                        v-if="order.status !== 'Hoàn hàng thành công'"
                        class="btn btn-warning btn-sm me-2" 
                        @click="openStatusModal(order)"
                      >
                        <i class="bi bi-pencil-square"></i> Sửa TT
                      </button>
                      
                      <button class="btn btn-danger btn-sm" @click="handleDelete(order)">
                        <i class="bi bi-trash"></i> Xóa
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="card-footer clearfix" v-if="!isReturnedLoading && returnedPagination.totalPages > 1">
              <ul class="pagination pagination-sm m-0 float-end">
                <li class="page-item" :class="{ disabled: returnedPagination.currentPage === 1 }">
                  <a class="page-link" href="#"
                    @click.prevent="fetchReturnedOrders(returnedPagination.currentPage - 1)">&laquo;</a>
                </li>
                <li v-for="page in returnedPagination.totalPages" :key="page" class="page-item"
                  :class="{ active: returnedPagination.currentPage === page }">
                  <a class="page-link" href="#" @click.prevent="fetchReturnedOrders(page)">{{ page }}</a>
                </li>
                <li class="page-item"
                  :class="{ disabled: returnedPagination.currentPage === returnedPagination.totalPages }">
                  <a class="page-link" href="#"
                    @click.prevent="fetchReturnedOrders(returnedPagination.currentPage + 1)">&raquo;</a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="detailModal" ref="detailModalRef" tabindex="-1" aria-labelledby="detailModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="detailModalLabel">
            Chi tiết Đơn hàng #{{ selectedOrder?.id }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div v-if="!selectedOrder">
            <p>Đang tải chi tiết...</p>
          </div>
          <div v-else>
            <h6>Thông tin khách hàng:</h6>
            <ul class="list-unstyled">
              <li><strong>Tên:</strong> {{ selectedOrder.customerName }}</li>
              <li><strong>SĐT:</strong> {{ selectedOrder.customerPhone }}</li>
              <li><strong>Địa chỉ:</strong> {{ selectedOrder.customerAddress }}</li>
            </ul>
            <hr>

            <h6>Các sản phẩm trong đơn:</h6>
            <table class="table table-sm table-bordered">
              <thead>
                <tr>
                  <th>Sản phẩm</th>
                  <th>Đơn giá</th>
                  <th>Số lượng</th>
                  <th>Thành tiền</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="isDetailModalLoading">
                  <td colspan="4" class="text-center">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                  </td>
                </tr>
                <tr v-for="item in selectedOrderItems" :key="item.id">
                  <td>
                    {{ item.product?.name || `(SP ID: ${item.productId})` }}
                  </td>
                  <td>{{ formatCurrency(item.price) }}</td>
                  <td>x {{ item.quantity }}</td>
                  <td>{{ formatCurrency(item.price * item.quantity) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="fw-bold">
                  <td colspan="3" class="text-end">Tổng cộng:</td>
                  <td>{{ formatCurrency(selectedOrder.totalAmount) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="statusModal" ref="statusModalRef" tabindex="-1" aria-labelledby="statusModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="statusModalLabel">
            Cập nhật trạng thái ĐH #{{ selectedOrder?.id }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="statusSelect" class="form-label">Trạng thái đơn hàng</label>
            
            <select class="form-select" id="statusSelect" v-model="selectedOrderStatus">
              <option 
                v-for="status in availableStatuses" 
                :key="status.value" 
                :value="status.value"
              >
                {{ status.text }}
              </option>
            </select>

          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          
          <button type="button" class="btn btn-primary" @click="handleUpdateStatus" :disabled="isSavingStatus">
            <span v-if="isSavingStatus" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            Lưu thay đổi
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.table td .btn {
  margin-top: 2px;
  margin-bottom: 2px;
}

.card-body.p-0 .table {
  margin-bottom: 0;
}
</style>
