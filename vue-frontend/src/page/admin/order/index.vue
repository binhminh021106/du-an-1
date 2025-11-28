<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService.js';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CONFIG ---
// Không cần URL cứng nếu apiService đã cấu hình base URL

// --- STATE MANAGEMENT ---
const allOrders = ref([]); // Chứa toàn bộ dữ liệu thô
const isLoading = ref(true);
const searchQuery = ref('');
const activeTab = ref('pending'); // Tab mặc định

// Modal State
const detailModalRef = ref(null);
const detailModalInstance = ref(null);
const selectedOrder = ref({});
const selectedOrderItems = ref([]);
const isLoadingDetails = ref(false);

// Pagination State (Quản lý trang riêng cho từng tab)
const pagination = reactive({
  pending: { currentPage: 1, itemsPerPage: 10 },
  approved: { currentPage: 1, itemsPerPage: 10 },
  shipping: { currentPage: 1, itemsPerPage: 10 },
  completed: { currentPage: 1, itemsPerPage: 10 },
  cancelled: { currentPage: 1, itemsPerPage: 10 },
  returns: { currentPage: 1, itemsPerPage: 10 }, // Gộp returning và returned
});

// --- COMPUTED: FILTER & SPLIT LISTS ---

// 1. Search Filter (Áp dụng tìm kiếm lên toàn bộ dữ liệu trước)
const searchResults = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) return allOrders.value;

  return allOrders.value.filter(order =>
    order.id.toString().includes(query) ||
    (order.customerName && order.customerName.toLowerCase().includes(query)) ||
    (order.customerPhone && order.customerPhone.includes(query))
  );
});

// 2. Split into lists by Status (Chia danh sách theo trạng thái)
const pendingList = computed(() => searchResults.value.filter(o => o.status === 'pending'));
const approvedList = computed(() => searchResults.value.filter(o => o.status === 'approved'));
const shippingList = computed(() => searchResults.value.filter(o => o.status === 'shipping'));
const completedList = computed(() => searchResults.value.filter(o => o.status === 'completed'));
const cancelledList = computed(() => searchResults.value.filter(o => o.status === 'cancelled'));
// Tab "Hàng hoàn" bao gồm cả đang hoàn và đã hoàn
const returnsList = computed(() => searchResults.value.filter(o => ['returning', 'returned'].includes(o.status)));

// 3. Pagination Helper (Hàm cắt trang)
function getPaginatedData(list, type) {
  const pageInfo = pagination[type];
  const totalPages = Math.max(1, Math.ceil(list.length / pageInfo.itemsPerPage));

  // Reset về trang 1 nếu trang hiện tại vượt quá tổng số trang (do search)
  if (pageInfo.currentPage > totalPages) pageInfo.currentPage = 1;

  const start = (pageInfo.currentPage - 1) * pageInfo.itemsPerPage;
  const end = start + pageInfo.itemsPerPage;

  return {
    data: list.slice(start, end),
    totalPages: totalPages,
    totalItems: list.length
  };
}

// 4. Paged Data for each Tab (Dữ liệu đã phân trang để hiển thị)
const pagedPending = computed(() => getPaginatedData(pendingList.value, 'pending'));
const pagedApproved = computed(() => getPaginatedData(approvedList.value, 'approved'));
const pagedShipping = computed(() => getPaginatedData(shippingList.value, 'shipping'));
const pagedCompleted = computed(() => getPaginatedData(completedList.value, 'completed'));
const pagedCancelled = computed(() => getPaginatedData(cancelledList.value, 'cancelled'));
const pagedReturns = computed(() => getPaginatedData(returnsList.value, 'returns'));

// --- WATCHERS ---
watch(searchQuery, () => {
  // Reset trang về 1 khi tìm kiếm
  Object.keys(pagination).forEach(key => pagination[key].currentPage = 1);
});

// --- LIFECYCLE ---
onMounted(() => {
  fetchOrders();
  if (detailModalRef.value) {
    detailModalInstance.value = new Modal(detailModalRef.value);
  }
});

// --- API FUNCTIONS ---

async function fetchOrders() {
  // Chỉ hiện loading lớn khi chưa có dữ liệu
  if (allOrders.value.length === 0) {
    isLoading.value = true;
  }

  try {
    // Lấy tất cả đơn hàng, sắp xếp mới nhất trước
    const response = await apiService.get('/orders?_sort=id&_order=desc');
    allOrders.value = response.data;
  } catch (error) {
    console.error("Error loading orders:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách đơn hàng.', 'error');
  } finally {
    isLoading.value = false;
  }
}

// --- ACTION HANDLERS ---

// Update Status (Optimistic Update - Cập nhật giao diện trước, gọi API sau)
async function handleUpdateStatus(order, newStatus) {
  const oldStatus = order.status;
  
  // Mapping text hiển thị
  const statusMessages = {
    'approved': 'Đã duyệt đơn hàng',
    'shipping': 'Đã chuyển sang giao hàng',
    'completed': 'Đã hoàn thành đơn hàng',
    'cancelled': 'Đã hủy đơn hàng',
    'returning': 'Đã chuyển sang hoàn hàng',
    'returned': 'Xác nhận đã nhận hàng hoàn'
  };

  // Xác nhận trước khi hủy hoặc hoàn
  if (['cancelled', 'returning'].includes(newStatus)) {
    const confirmResult = await Swal.fire({
      title: 'Xác nhận?',
      text: `Bạn có chắc chắn muốn chuyển sang trạng thái "${newStatus}"?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Đồng ý',
      cancelButtonText: 'Hủy'
    });
    if (!confirmResult.isConfirmed) return;
  }

  // 1. Update UI immediately
  order.status = newStatus;

  // 2. Show Toast Notification
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
  });
  
  const icon = ['cancelled', 'returning'].includes(newStatus) ? 'warning' : 'success';
  Toast.fire({ icon: icon, title: statusMessages[newStatus] || 'Cập nhật thành công' });

  // 3. Call API
  try {
    await apiService.patch(`/orders/${order.id}`, { status: newStatus });
  } catch (error) {
    // Rollback on error
    order.status = oldStatus;
    console.error("Update failed", error);
    Swal.fire('Lỗi', 'Cập nhật thất bại, đã hoàn tác thay đổi.', 'error');
  }
}

// Delete Order
async function handleDelete(order) {
  const result = await Swal.fire({
    title: 'Xóa đơn hàng?',
    text: "Hành động này sẽ xóa vĩnh viễn đơn hàng và các sản phẩm chi tiết!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Xóa ngay',
    cancelButtonText: 'Hủy'
  });

  if (result.isConfirmed) {
    try {
      // Xóa items trước (giả lập logic backend nếu cần, hoặc JSON server tự xử lý cascade nếu cấu hình)
      // Ở đây ta gọi API xóa items trước để đảm bảo sạch sẽ
      const items = (await apiService.get(`/order_items?orderId=${order.id}`)).data;
      await Promise.all(items.map(item => apiService.delete(`/order_items/${item.id}`)));
      
      // Xóa order
      await apiService.delete(`/orders/${order.id}`);

      // Remove from local array
      allOrders.value = allOrders.value.filter(o => o.id !== order.id);
      
      Swal.fire('Đã xóa!', 'Đơn hàng đã được xóa.', 'success');
    } catch (error) {
      console.error(error);
      Swal.fire('Lỗi', 'Không thể xóa đơn hàng.', 'error');
    }
  }
}

// View Details
async function openDetailModal(order) {
  selectedOrder.value = order;
  selectedOrderItems.value = [];
  isLoadingDetails.value = true;
  detailModalInstance.value.show();

  try {
    const response = await apiService.get(`/order_items?orderId=${order.id}&_expand=product`);
    selectedOrderItems.value = response.data;
  } catch (error) {
    console.error("Error fetching details:", error);
    Swal.fire('Lỗi', 'Không thể tải chi tiết sản phẩm.', 'error');
  } finally {
    isLoadingDetails.value = false;
  }
}

// --- HELPER FUNCTIONS ---

function changePage(type, page) {
  pagination[type].currentPage = page;
}

function setActiveTab(tabName) {
  activeTab.value = tabName;
}

function formatCurrency(value) {
  if (!value && value !== 0) return '0 ₫';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleString('vi-VN');
}
</script>

<template>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6"><h3 class="mb-0">Quản lý Đơn hàng</h3></div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active">Đơn hàng</li>
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
            <h3 class="card-title">Danh sách Đơn hàng</h3>
            <div class="input-group" style="width: 300px;">
              <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control border-start-0" placeholder="Tìm ID, Tên, SĐT..." v-model="searchQuery">
            </div>
          </div>

          <!-- TABS NAVIGATION -->
          <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'pending' }" href="#" @click.prevent="setActiveTab('pending')">
                <i class="bi bi-hourglass-split me-1 text-warning"></i> Chờ xử lý
                <span class="badge rounded-pill bg-warning text-dark ms-1">{{ pendingList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'approved' }" href="#" @click.prevent="setActiveTab('approved')">
                <i class="bi bi-check2-circle me-1 text-info"></i> Đã duyệt
                <span class="badge rounded-pill bg-info ms-1">{{ approvedList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'shipping' }" href="#" @click.prevent="setActiveTab('shipping')">
                <i class="bi bi-truck me-1 text-primary"></i> Đang giao
                <span class="badge rounded-pill bg-primary ms-1">{{ shippingList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'completed' }" href="#" @click.prevent="setActiveTab('completed')">
                <i class="bi bi-check-circle-fill me-1 text-success"></i> Hoàn thành
                <span class="badge rounded-pill bg-success ms-1">{{ completedList.length }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'returns' }" href="#" @click.prevent="setActiveTab('returns')">
                <i class="bi bi-arrow-counterclockwise me-1 text-secondary"></i> Hàng hoàn
                <span class="badge rounded-pill bg-secondary ms-1">{{ returnsList.length }}</span>
              </a>
            </li>
             <li class="nav-item">
              <a class="nav-link" :class="{ active: activeTab === 'cancelled' }" href="#" @click.prevent="setActiveTab('cancelled')">
                <i class="bi bi-x-circle me-1 text-danger"></i> Đã hủy
                <span class="badge rounded-pill bg-danger ms-1">{{ cancelledList.length }}</span>
              </a>
            </li>
          </ul>
        </div>

        <div class="card-body p-0">
          <!-- Loading Spinner (Initial only) -->
          <div v-if="isLoading && allOrders.length === 0" class="text-center p-5">
            <div class="spinner-border text-primary" role="status"></div>
          </div>

          <!-- Content Area -->
          <div v-else class="tab-content p-0">
            
            <!-- FIXED LOOP FOR TABS: Moved v-for to template tag to fix Vue 3 precedence error -->
            <template v-for="tab in ['pending', 'approved', 'shipping', 'completed', 'returns', 'cancelled']" :key="tab">
              <div class="tab-pane fade show active" v-if="activeTab === tab">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0 table-striped">
                    <thead class="table-light">
                      <tr>
                        <th>Mã ĐH</th>
                        <th>Khách hàng</th>
                        <th>SĐT</th>
                        <th>Tổng tiền</th>
                        <th>Ngày đặt</th>
                        <th>Trạng thái</th>
                        <th class="text-end" style="min-width: 200px;">Hành động</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Get Correct Data Source dynamically -->
                      <tr v-if="(tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).data.length === 0">
                        <td colspan="7" class="text-center py-4 text-muted">Không có đơn hàng nào trong mục này.</td>
                      </tr>
                      
                      <tr v-for="order in (tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).data" :key="order.id">
                        <td class="fw-bold text-primary">#{{ order.id }}</td>
                        <td>{{ order.customerName }}</td>
                        <td>{{ order.customerPhone }}</td>
                        <td class="fw-bold text-danger">{{ formatCurrency(order.totalAmount) }}</td>
                        <td class="small text-muted">{{ formatDate(order.createdAt) }}</td>
                        <td>
                          <!-- Status Badge -->
                           <span v-if="order.status === 'pending'" class="badge bg-warning text-dark">Chờ xử lý</span>
                           <span v-else-if="order.status === 'approved'" class="badge bg-info">Đã duyệt</span>
                           <span v-else-if="order.status === 'shipping'" class="badge bg-primary">Đang giao</span>
                           <span v-else-if="order.status === 'completed'" class="badge bg-success">Hoàn thành</span>
                           <span v-else-if="order.status === 'cancelled'" class="badge bg-danger">Đã hủy</span>
                           <span v-else-if="order.status === 'returning'" class="badge bg-secondary">Đang hoàn</span>
                           <span v-else-if="order.status === 'returned'" class="badge bg-dark">Đã nhận hoàn</span>
                           <span v-else class="badge bg-light text-dark">{{ order.status }}</span>
                        </td>
                        <td class="text-end">
                          <button class="btn btn-sm btn-outline-secondary me-1" @click="openDetailModal(order)" title="Xem chi tiết">
                            <i class="bi bi-eye"></i> Xem
                          </button>
                          
                          <!-- Actions based on status -->
                          <template v-if="order.status === 'pending'">
                            <button class="btn btn-sm btn-success me-1" @click="handleUpdateStatus(order, 'approved')" title="Duyệt đơn">
                              <i class="bi bi-check-lg"></i> Duyệt
                            </button>
                            <button class="btn btn-sm btn-outline-danger" @click="handleUpdateStatus(order, 'cancelled')" title="Hủy đơn">
                              <i class="bi bi-x-lg"></i> Hủy
                            </button>
                          </template>

                          <template v-if="order.status === 'approved'">
                            <button class="btn btn-sm btn-primary me-1" @click="handleUpdateStatus(order, 'shipping')" title="Giao hàng">
                              <i class="bi bi-truck"></i> Giao hàng
                            </button>
                            <button class="btn btn-sm btn-outline-danger" @click="handleUpdateStatus(order, 'cancelled')" title="Hủy đơn">
                              <i class="bi bi-x-lg"></i> Hủy
                            </button>
                          </template>

                          <template v-if="order.status === 'shipping'">
                            <button class="btn btn-sm btn-success me-1" @click="handleUpdateStatus(order, 'completed')" title="Hoàn thành">
                              <i class="bi bi-check2-circle"></i> Hoàn thành
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" @click="handleUpdateStatus(order, 'returning')" title="Hoàn hàng">
                              <i class="bi bi-arrow-counterclockwise"></i> Hoàn hàng
                            </button>
                          </template>

                          <template v-if="order.status === 'completed'">
                             <button class="btn btn-sm btn-outline-secondary" @click="handleUpdateStatus(order, 'returning')" title="Hoàn hàng">
                               <i class="bi bi-arrow-counterclockwise"></i> Hoàn hàng
                             </button>
                          </template>

                          <template v-if="order.status === 'returning'">
                             <button class="btn btn-sm btn-dark" @click="handleUpdateStatus(order, 'returned')" title="Xác nhận đã nhận">
                               <i class="bi bi-box-seam"></i> Đã nhận
                             </button>
                          </template>

                          <!-- Delete button -->
                          <button v-if="['cancelled', 'returned'].includes(order.status)" class="btn btn-sm btn-danger ms-1" @click="handleDelete(order)" title="Xóa">
                            <i class="bi bi-trash"></i> Xóa
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination Component -->
                <div v-if="(tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).totalPages > 1" class="d-flex justify-content-end p-3">
                   <ul class="pagination pagination-sm mb-0">
                    <li class="page-item" :class="{ disabled: pagination[tab].currentPage === 1 }">
                      <a class="page-link" href="#" @click.prevent="changePage(tab, pagination[tab].currentPage - 1)">&laquo;</a>
                    </li>
                    <li v-for="p in (tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).totalPages" :key="p" class="page-item" :class="{ active: pagination[tab].currentPage === p }">
                      <a class="page-link" href="#" @click.prevent="changePage(tab, p)">{{ p }}</a>
                    </li>
                    <li class="page-item" :class="{ disabled: pagination[tab].currentPage === (tab === 'pending' ? pagedPending : tab === 'approved' ? pagedApproved : tab === 'shipping' ? pagedShipping : tab === 'completed' ? pagedCompleted : tab === 'returns' ? pagedReturns : pagedCancelled).totalPages }">
                      <a class="page-link" href="#" @click.prevent="changePage(tab, pagination[tab].currentPage + 1)">&raquo;</a>
                    </li>
                  </ul>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL CHI TIẾT -->
  <div class="modal fade" id="detailModal" ref="detailModalRef" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Chi tiết đơn hàng #{{ selectedOrder.id }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
           <!-- Customer Info -->
           <div class="row mb-3">
             <div class="col-md-6">
               <h6 class="fw-bold text-secondary text-uppercase small">Khách hàng</h6>
               <p class="mb-1 fw-bold">{{ selectedOrder.customerName }}</p>
               <p class="mb-1 text-muted small"><i class="bi bi-telephone me-1"></i>{{ selectedOrder.customerPhone }}</p>
               <p class="mb-0 text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ selectedOrder.customerAddress }}</p>
             </div>
             <div class="col-md-6 text-md-end">
               <h6 class="fw-bold text-secondary text-uppercase small">Thông tin đơn</h6>
               <p class="mb-1">Ngày đặt: {{ formatDate(selectedOrder.createdAt) }}</p>
               <p class="mb-0 fw-bold fs-5 text-danger">{{ formatCurrency(selectedOrder.totalAmount) }}</p>
               <div class="mt-2 text-muted fst-italic small">Ghi chú: {{ selectedOrder.note || 'Không có' }}</div>
             </div>
           </div>
           
           <hr>

           <!-- Order Items -->
           <h6 class="fw-bold text-secondary text-uppercase small mb-3">Sản phẩm đặt mua</h6>
           <div v-if="isLoadingDetails" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-primary"></div>
           </div>
           <div v-else class="table-responsive">
             <table class="table table-sm table-bordered mb-0">
               <thead class="table-light">
                 <tr>
                   <th>Sản phẩm</th>
                   <th class="text-center">SL</th>
                   <th class="text-end">Đơn giá</th>
                   <th class="text-end">Thành tiền</th>
                 </tr>
               </thead>
               <tbody>
                 <tr v-for="item in selectedOrderItems" :key="item.id">
                   <td>
                     <div>{{ item.product?.name || `Product ID: ${item.productId}` }}</div>
                     <div class="small text-muted" v-if="item.color || item.size">{{ item.color }} / {{ item.size }}</div>
                   </td>
                   <td class="text-center">{{ item.quantity }}</td>
                   <td class="text-end">{{ formatCurrency(item.price) }}</td>
                   <td class="text-end fw-bold">{{ formatCurrency(item.price * item.quantity) }}</td>
                 </tr>
               </tbody>
             </table>
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
  border: none;
  border-bottom: 2px solid transparent;
}
.nav-tabs .nav-link:hover {
  color: #495057;
  border-color: transparent;
}
.nav-tabs .nav-link.active {
  color: #0d6efd;
  background: transparent;
  border-bottom: 2px solid #0d6efd;
}
.pagination .page-link {
  cursor: pointer;
}
.badge {
  font-weight: 500;
  padding: 0.5em 0.8em;
}
.btn {
  white-space: nowrap;
}
</style>