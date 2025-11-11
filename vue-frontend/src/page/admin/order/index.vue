<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// Global state
const API_URL = import.meta.env.VITE_API_BASE_URL;

// Loading states
const isMainLoading = ref(true);
const isReturnedLoading = ref(true);
const isDetailModalLoading = ref(false);
const isSavingStatus = ref(false);

// Modal states
const detailModalRef = ref(null);
const detailModalInstance = ref(null);
const statusModalRef = ref(null);
const statusModalInstance = ref(null);

// Temporary data states
const selectedOrder = ref(null);
const selectedOrderStatus = ref('');
const selectedOrderItems = ref([]);

// Main orders table state
const mainOrders = ref([]);
const mainPagination = reactive({
  currentPage: 1,
  itemsPerPage: 10,
  totalItems: 0,
  totalPages: 1
});

// Returned orders table state
const returnedOrders = ref([]);
const returnedPagination = reactive({
  currentPage: 1,
  itemsPerPage: 10,
  totalItems: 0,
  totalPages: 1
});

// --- STATUS LOGIC (ENGLISH) ---
const STATUS_HIERARCHY = {
  'pending': 1,
  'approved': 2,
  'shipping': 3,
  'completed': 4,
  'cancelled': -1,
  'returning': 10,
  'returned': 11
};

const ALL_STATUS_OPTIONS = [
  { value: 'pending', text: 'Chờ xử lý (Pending)' },
  { value: 'approved', text: 'Đã duyệt (Approved)' },
  { value: 'shipping', text: 'Đang giao (Shipping)' },
  { value: 'completed', text: 'Đã hoàn thành (Completed)' },
  { value: 'cancelled', text: 'Đã hủy (Cancelled)' },
  { value: 'returning', text: 'Đang hoàn hàng (Returning)' },
  { value: 'returned', text: 'Hoàn hàng thành công (Returned)' }
];

// Computed available statuses based on current status
const availableStatuses = computed(() => {
  if (!selectedOrder.value) return [];

  const currentStatus = selectedOrder.value.status;
  const currentLevel = STATUS_HIERARCHY[currentStatus];

  // If currently in return flow, can only stay in return flow
  if (currentLevel >= 10) {
    return ALL_STATUS_OPTIONS.filter(opt => ['returning', 'returned'].includes(opt.value));
  }

  // If cancelled, can only stay cancelled (usually)
  if (currentLevel === -1) {
    return ALL_STATUS_OPTIONS.filter(opt => opt.value === 'cancelled');
  }

  // If shipping or completed, can move to returning
  if (currentLevel === 3 || currentLevel === 4) {
     // Shipping can go to completed, returning, or stay shipping
     // Completed can go to returning or stay completed
      const allowed = ['returning'];
      if (currentLevel === 3) allowed.push('completed', 'shipping');
      if (currentLevel === 4) allowed.push('completed');
      
      return ALL_STATUS_OPTIONS.filter(opt => allowed.includes(opt.value));
  }

  // Main flow (pending, approved) -> can move forward OR cancel
  return ALL_STATUS_OPTIONS.filter(opt => {
    const optLevel = STATUS_HIERARCHY[opt.value];
    if (optLevel === -1) return true; // Allow jump to Cancelled
    return optLevel >= currentLevel && optLevel < 10; // Forward only, no jumping to return yet (except from shipping/completed handled above)
  });
});

// Lifecycle hooks
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

// Data fetching functions
async function fetchMainOrders(page = 1) {
  isMainLoading.value = true;
  if (page < 1) {
    isMainLoading.value = false;
    return;
  }

  try {
    // Fetch all orders sorted desc
    const response = await axios.get(`${API_URL}/orders?_sort=id&_order=desc`);
    const allOrders = response.data || [];

    // Filter: EXCLUDE 'returning' and 'returned'
    const filtered = allOrders.filter(order => !['returning', 'returned'].includes(order.status));

    // Update pagination
    mainPagination.totalItems = filtered.length;
    mainPagination.totalPages = Math.max(1, Math.ceil(mainPagination.totalItems / mainPagination.itemsPerPage));

    if (page > mainPagination.totalPages) page = mainPagination.totalPages;
    mainPagination.currentPage = page;

    // Slice for current page
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

async function fetchReturnedOrders(page = 1) {
  isReturnedLoading.value = true;
  if (page < 1) {
    isReturnedLoading.value = false;
    return;
  }

  try {
    // Fetch all orders sorted desc
    const response = await axios.get(`${API_URL}/orders?_sort=id&_order=desc`);
    const allOrders = response.data || [];

    // Filter: ONLY 'returning' and 'returned'
    const filtered = allOrders.filter(order => ['returning', 'returned'].includes(order.status));

    // Update pagination
    returnedPagination.totalItems = filtered.length;
    returnedPagination.totalPages = Math.max(1, Math.ceil(returnedPagination.totalItems / returnedPagination.itemsPerPage));

    if (page > returnedPagination.totalPages && returnedPagination.totalPages > 0) {
        page = returnedPagination.totalPages;
    }
    returnedPagination.currentPage = page;

    // Slice for current page
    const start = (returnedPagination.currentPage - 1) * returnedPagination.itemsPerPage;
    const end = start + returnedPagination.itemsPerPage;
    returnedOrders.value = filtered.slice(start, end);

  } catch (error) {
    console.error("Lỗi khi tải đơn hàng hoàn:", error);
  } finally {
    isReturnedLoading.value = false;
  }
}

// Helper functions
function formatCurrency(value) {
  if (!value && value !== 0) return '0 đ';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleString('vi-VN');
}

// Translated status badges
function getStatusBadge(status) {
  switch (status) {
    case 'pending': return { class: 'text-bg-warning', text: 'Chờ xử lý' };
    case 'approved': return { class: 'text-bg-info', text: 'Đã duyệt' };
    case 'shipping': return { class: 'text-bg-primary', text: 'Đang giao' };
    case 'completed': return { class: 'text-bg-success', text: 'Hoàn thành' };
    case 'cancelled': return { class: 'text-bg-danger', text: 'Đã hủy' };
    case 'returning': return { class: 'text-bg-secondary', text: 'Đang hoàn' };
    case 'returned': return { class: 'text-bg-dark', text: 'Đã hoàn' };
    default: return { class: 'text-bg-light', text: status };
  }
}

// Modal & CRUD operations
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

    // Reload both tables to reflect changes (e.g., moving from main to returned list)
    fetchMainOrders(mainPagination.currentPage);
    fetchReturnedOrders(returnedPagination.currentPage);

  } catch (error) {
    console.error("Lỗi khi cập nhật trạng thái:", error);
    Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
  } finally {
    isSavingStatus.value = false;
  }
}

// Quick status update without modal
async function quickUpdateStatus(order, newStatus) {
  const statusTextMap = {
    'approved': 'Duyệt đơn hàng',
    'shipping': 'Bắt đầu giao hàng',
    'completed': 'Hoàn thành đơn hàng',
    'cancelled': 'Hủy đơn hàng',
    'returning': 'Chuyển sang hoàn hàng',
    'returned': 'Xác nhận đã hoàn hàng'
  };
  
  const actionText = statusTextMap[newStatus] || 'Cập nhật trạng thái';
  const confirmButtonColor = newStatus === 'cancelled' ? '#d33' : '#3085d6';

  const result = await Swal.fire({
    title: 'Xác nhận',
    text: `Bạn có chắc chắn muốn "${actionText}" cho đơn hàng #${order.id}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: confirmButtonColor,
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Đồng ý',
    cancelButtonText: 'Hủy'
  });

  if (result.isConfirmed) {
    try {
      await axios.patch(`${API_URL}/orders/${order.id}`, {
        status: newStatus
      });
      Swal.fire('Thành công', `Đã ${actionText} thành công.`, 'success');
      fetchMainOrders(mainPagination.currentPage);
      fetchReturnedOrders(returnedPagination.currentPage);
    } catch (error) {
      console.error("Lỗi khi cập nhật trạng thái:", error);
      Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
    }
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

      // Reload tables
      fetchMainOrders(mainPagination.currentPage);
      fetchReturnedOrders(returnedPagination.currentPage);

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

              <table v-else class="table table-hover table-striped align-middle">
                <thead>
                  <tr>
                    <th style="width: 80px">Mã ĐH</th>
                    <th>Khách hàng</th>
                    <th>Điện thoại</th>
                    <th>Tổng tiền</th>
                    <th style="width: 150px">Trạng thái</th>
                    <th style="width: 160px">Ngày đặt</th>
                    <th style="width: 350px">Hành động</th>
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
                      <span class="badge" :class="getStatusBadge(order.status).class">
                        {{ getStatusBadge(order.status).text }}
                      </span>
                    </td>
                    <td>{{ formatDate(order.createdAt) }}</td>
                    <td>
                      <button class="btn btn-info btn-sm me-1" @click="openDetailModal(order)" title="Xem chi tiết">
                        <i class="bi bi-eye"></i>
                      </button>

                      <!-- Quick Action Buttons based on status -->
                      <template v-if="order.status === 'pending'">
                        <button class="btn btn-success btn-sm me-1" @click="quickUpdateStatus(order, 'approved')" title="Duyệt đơn hàng">
                          <i class="bi bi-check-lg"></i> Duyệt
                        </button>
                        <button class="btn btn-danger btn-sm me-1" @click="quickUpdateStatus(order, 'cancelled')" title="Hủy đơn hàng">
                          <i class="bi bi-x-lg"></i> Hủy
                        </button>
                      </template>

                      <template v-if="order.status === 'approved'">
                        <button class="btn btn-primary btn-sm me-1" @click="quickUpdateStatus(order, 'shipping')" title="Bắt đầu giao hàng">
                          <i class="bi bi-truck"></i> Giao hàng
                        </button>
                      </template>

                      <template v-if="order.status === 'shipping'">
                        <button class="btn btn-success btn-sm me-1" @click="quickUpdateStatus(order, 'completed')" title="Hoàn thành đơn hàng">
                          <i class="bi bi-check2-circle"></i> Hoàn thành
                        </button>
                        <button class="btn btn-secondary btn-sm me-1" @click="quickUpdateStatus(order, 'returning')" title="Chuyển sang hoàn hàng">
                          <i class="bi bi-arrow-counterclockwise"></i> Hoàn hàng
                        </button>
                      </template>
                      
                      <template v-if="order.status === 'completed'">
                         <button class="btn btn-secondary btn-sm me-1" @click="quickUpdateStatus(order, 'returning')" title="Chuyển sang hoàn hàng">
                          <i class="bi bi-arrow-counterclockwise"></i> Hoàn hàng
                        </button>
                      </template>
                      
                      <!-- Fallback for other statuses or manual edit -->
                       <button v-if="!['pending', 'approved', 'shipping', 'completed', 'cancelled'].includes(order.status)"
                        class="btn btn-warning btn-sm me-1" @click="openStatusModal(order)" title="Cập nhật trạng thái thủ công">
                        <i class="bi bi-pencil-square"></i>
                      </button>

                      <button class="btn btn-outline-danger btn-sm" @click="handleDelete(order)" title="Xóa đơn hàng">
                        <i class="bi bi-trash"></i>
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
              <table v-else class="table table-hover table-striped align-middle">
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
                      <span class="badge" :class="getStatusBadge(order.status).class">
                        {{ getStatusBadge(order.status).text }}
                      </span>
                    </td>
                    <td>{{ formatDate(order.createdAt) }}</td>
                    <td>
                      <button class="btn btn-info btn-sm me-1" @click="openDetailModal(order)" title="Xem chi tiết">
                        <i class="bi bi-eye"></i>
                      </button>

                      <template v-if="order.status === 'returning'">
                        <button class="btn btn-dark btn-sm me-1" @click="quickUpdateStatus(order, 'returned')" title="Xác nhận đã hoàn hàng">
                          <i class="bi bi-box-seam"></i> Đã nhận hoàn
                        </button>
                      </template>

                      <button v-if="order.status !== 'returned' && order.status !== 'returning'" class="btn btn-warning btn-sm me-1"
                        @click="openStatusModal(order)" title="Cập nhật trạng thái thủ công">
                        <i class="bi bi-pencil-square"></i>
                      </button>

                      <button class="btn btn-outline-danger btn-sm" @click="handleDelete(order)" title="Xóa đơn hàng">
                        <i class="bi bi-trash"></i>
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
              <option v-for="status in availableStatuses" :key="status.value" :value="status.value">
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