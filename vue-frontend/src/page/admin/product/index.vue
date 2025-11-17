<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
// BƯỚC 1: Import axios để gọi API thật
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---
const products = ref([]);
const categories = ref([]);
const isLoading = ref(true);
const isEditMode = ref(false);

// State Modal Thêm/Sửa
const modalInstance = ref(null);
const modalRef = ref(null);

// State Modal Xem
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingProduct = ref({
  variants: [],
  attributeNames: [],
  images: [],
  sold_count: 0,
  favorite_count: 0,
  review_count: 0,
  average_rating: 0.0 
});

// State Tìm kiếm & Phân trang
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

// STATE MỚI ĐỂ SẮP XẾP
const sortCriteria = ref('id-desc'); // Giá trị mặc định: Mới nhất

// Dữ liệu cho form sản phẩm
const formData = reactive({
  id: null,
  name: '',
  description: '',
  category_id: null,
  status: 'active',
  attribute_definitions: reactive([]), 
  variants: reactive([]),
  existing_images: reactive([]),
  new_images: [],
  images_to_delete: []
});

// State cho việc thêm thuộc tính mới
const newAttributeName = ref('');
const newImagePreviews = ref([]);

// Lỗi validation
const errors = reactive({
  name: '',
  category_id: '',
  images: '',
  variants: '',
  attributes: ''
});

// --- BƯỚC 2: XÓA BỎ mockDb VÀ apiService GIẢ LẬP ---
// (Toàn bộ các đối tượng mockDb và apiService giả đã bị xóa)


// --- BƯỚC 3: TẠO apiService THẬT VỚI AXIOS ---
// Trỏ đến server đang chạy của bạn: http://localhost:3000
const apiService = axios.create({
  baseURL: 'http://localhost:3000',
  headers: {
    'Content-Type': 'application/json',
  }
});


// --- COMPUTED ---
// (Không thay đổi)
const filteredProducts = computed(() => {
  // 1. Lọc (Filtering)
  const query = searchQuery.value.toLowerCase().trim();
  let filtered = products.value;
  
  if (query) {
    filtered = products.value.filter(product =>
      product.name.toLowerCase().includes(query) ||
      (product.category?.name && product.category.name.toLowerCase().includes(query))
    );
  }

  // 2. Sắp xếp (Sorting)
  const [key, order] = sortCriteria.value.split('-');
  
  // Tạo một bản sao để sắp xếp, tránh thay đổi 'products' gốc
  const sorted = [...filtered]; 

  sorted.sort((a, b) => {
    let valA, valB;

    // Lấy giá trị để so sánh
    switch (key) {
      case 'name':
        valA = a.name.toLowerCase();
        valB = b.name.toLowerCase();
        break;
      case 'price':
        // Sắp xếp theo giá của biến thể đầu tiên
        valA = a.variants[0]?.price || 0;
        valB = b.variants[0]?.price || 0;
        break;
      case 'created_at':
        valA = new Date(a.created_at);
        valB = new Date(b.created_at);
        break;
      case 'id':
      default:
        valA = a.id;
        valB = b.id;
        break;
    }

    // Logic so sánh
    let comparison = 0;
    if (valA > valB) {
      comparison = 1;
    } else if (valA < valB) {
      comparison = -1;
    }
    
    // Đảo ngược nếu là 'desc'
    return order === 'asc' ? comparison : -comparison;
  });

  return sorted;
});

const totalPages = computed(() => {
  return Math.ceil(filteredProducts.value.length / itemsPerPage.value);
});

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  const end = start + itemsPerPage.value;
  return filteredProducts.value.slice(start, end);
});

// --- WATCHERS ---
// (Không thay đổi)
watch(searchQuery, () => {
  currentPage.value = 1; 
});

// THÊM WATCHER CHO SẮP XẾP
watch(sortCriteria, () => {
  currentPage.value = 1; // Reset về trang 1 khi đổi sắp xếp
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
// (Không thay đổi)
onMounted(() => {
  fetchProducts();
  fetchCategories();
  if (modalRef.value) {
    modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});

// --- CÁC HÀM TẢI DỮ LIỆU (ĐÃ CẬP NHẬT) ---

async function fetchProducts() {
  isLoading.value = true;
  try {
    // BƯỚC 4.1: SỬA HÀM GET SẢN PHẨM
    // Sử dụng _expand và _embed của json-server để lấy tất cả dữ liệu liên quan
    // `_expand=category` -> Lấy 1 đối tượng category
    // `_embed=variants` -> Lấy mảng variants
    // `_embed=images` -> Lấy mảng images
    const response = await apiService.get(`/products?_sort=id&_order=desc&_expand=category&_embed=variants&_embed=images`);
    
    // axios trả về dữ liệu trong response.data
    products.value = response.data.map(p => ({
      ...p,
      // Đảm bảo các trường luôn là mảng, tránh lỗi
      images: p.images || [], 
      variants: p.variants || [],
      status: p.status || 'active', 
      created_at: p.created_at || new Date().toISOString() 
    }));
  } catch (error) {
    console.error("Lỗi khi tải sản phẩm:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách sản phẩm. (Hãy đảm bảo json-server đang chạy)', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function fetchCategories() {
  try {
    // BƯỚC 4.2: SỬA HÀM GET DANH MỤC
    // Thêm điều kiện lọc status=active mà mock API đã làm
    const response = await apiService.get(`/categories?status=active&_sort=order&_order=asc`);
    categories.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải danh mục:", error);
  }
}

// --- CÁC HÀM HELPER ---
// (Không thay đổi)
function formatCurrency(value) {
  if (value === undefined || value === null) return 'N/A';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}
function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
}
function calculateTotalStock(variants) {
  if (!variants || variants.length === 0) return 0;
  return variants.reduce((acc, variant) => acc + (parseInt(variant.stock) || 0), 0);
}

// --- CÁC HÀM QUẢN LÝ THUỘC TÍNH, BIẾN THỂ, ẢNH ---
// (Toàn bộ các hàm này không thay đổi)
function addAttributeDefinition() {
  const name = newAttributeName.value.trim();
  if (!name) {
    errors.attributes = 'Vui lòng nhập tên thuộc tính.';
    return;
  }
  if (formData.attribute_definitions.find(attr => attr.name.toLowerCase() === name.toLowerCase())) {
    errors.attributes = 'Thuộc tính này đã tồn tại.';
    return;
  }
  errors.attributes = '';
  const newAttr = reactive({ id: crypto.randomUUID(), name: name });
  formData.attribute_definitions.push(newAttr);
  formData.variants.forEach(variant => { variant.attributes[name] = ''; });
  newAttributeName.value = '';
}
function removeAttributeDefinition(attrToRemove) {
  const index = formData.attribute_definitions.findIndex(attr => attr.id === attrToRemove.id);
  if (index > -1) {
    formData.attribute_definitions.splice(index, 1);
    formData.variants.forEach(variant => { delete variant.attributes[attrToRemove.name]; });
  }
}
function addVariantRow() {
  const newAttributes = reactive({});
  formData.attribute_definitions.forEach(attrDef => { newAttributes[attrDef.name] = ''; });
  formData.variants.push(reactive({
    id: crypto.randomUUID(), price: 0, stock: 0, attributes: newAttributes
  }));
}
function removeVariantRow(index) {
  formData.variants.splice(index, 1);
}
function handleImageUpload(event) {
  errors.images = '';
  formData.new_images = Array.from(event.target.files);
  newImagePreviews.value.forEach(url => URL.revokeObjectURL(url));
  newImagePreviews.value = formData.new_images.map(file => URL.createObjectURL(file));
}
function removeNewImage(index) {
  URL.revokeObjectURL(newImagePreviews.value[index]);
  newImagePreviews.value.splice(index, 1);
  formData.new_images.splice(index, 1);
}
function removeExistingImage(index) {
  const removedImage = formData.existing_images.splice(index, 1);
  if (removedImage[0]?.id) {
    formData.images_to_delete.push(removedImage[0].id);
  }
}

// --- CÁC HÀM CRUD MODAL (ĐÃ CẬP NHẬT) ---

// (Hàm resetForm không đổi)
function resetForm() {
  formData.id = null;
  formData.name = '';
  formData.description = '';
  formData.category_id = null;
  formData.status = 'active';
  formData.attribute_definitions = reactive([]);
  formData.variants = reactive([]);
  newAttributeName.value = '';
  newImagePreviews.value.forEach(url => URL.revokeObjectURL(url));
  formData.existing_images = reactive([]);
  formData.new_images = [];
  formData.images_to_delete = [];
  newImagePreviews.value = [];
  const fileInput = document.getElementById('product_images');
  if (fileInput) fileInput.value = '';
  Object.keys(errors).forEach(key => errors[key] = '');
}

// (Hàm openCreateModal không đổi)
function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  addVariantRow(); 
  modalInstance.value.show();
}

// (Hàm openEditModal không đổi - nó sẽ hoạt động đúng
//  vì fetchProducts giờ đã lấy
//  đủ 'images' và 'variants')
function openEditModal(product) {
  resetForm();
  isEditMode.value = true;

  formData.id = product.id;
  formData.name = product.name;
  formData.description = product.description;
  formData.category_id = product.category?.id || product.category_id;
  formData.status = product.status || 'active';

  formData.existing_images = reactive(product.images ? product.images.map(img => ({ 
      id: img.id, 
      url: img.image_url || img.url 
  })) : []);

  const allKeys = new Set();
  const productVariants = product.variants || [];
  if (productVariants.length > 0) {
    productVariants.forEach(v => {
      if (v.attributes) { Object.keys(v.attributes).forEach(key => allKeys.add(key)); }
    });
  }
  formData.attribute_definitions = reactive(
    Array.from(allKeys).map(name => ({ id: crypto.randomUUID(), name: name }))
  );
  formData.variants = reactive(
    productVariants.map(v => {
      const variantAttributes = reactive({});
      allKeys.forEach(key => { variantAttributes[key] = v.attributes[key] || ''; });
      return reactive({ ...v, attributes: variantAttributes });
    })
  );
  if(formData.variants.length === 0) { addVariantRow(); }

  modalInstance.value.show();
}

// (Hàm openViewModal không đổi - 
// nó sẽ hoạt động đúng vì fetchProducts đã lấy đủ dữ liệu)
function openViewModal(product) {
  const variantKeys = (product.variants && product.variants.length > 0 && product.variants[0].attributes)
    ? Object.keys(product.variants[0].attributes)
    : [];

  viewingProduct.value = {
    ...product,
    categoryName: product.category?.name || 'N/A',
    attributeNames: variantKeys,
    sold_count: product.sold_count || 0,
    favorite_count: product.favorite_count || 0,
    review_count: product.review_count || 0,
    average_rating: product.average_rating || 0.0,
    images: product.images || [] 
  };
  viewModalInstance.value.show();
}

// (Hàm validateForm không đổi - NHƯNG tôi đã comment
//  phần kiểm tra ảnh để phù hợp với json-server)
function validateForm() {
  Object.keys(errors).forEach(key => errors[key] = '');
  let isValid = true;
  if (!formData.name.trim()) {
    errors.name = 'Vui lòng nhập tên sản phẩm.'; isValid = false;
  }
  if (!formData.category_id) {
    errors.category_id = 'Vui lòng chọn danh mục.'; isValid = false;
  }
  
  // BƯỚC 4.4: Bỏ qua kiểm tra ảnh (vì json-server không hỗ trợ upload)
  /*
  if (!isEditMode.value && formData.new_images.length === 0) {
    errors.images = 'Vui lòng chọn ít nhất 1 ảnh sản phẩm.'; isValid = false;
  }
  if (isEditMode.value && formData.existing_images.length === 0 && formData.new_images.length === 0) {
    errors.images = 'Sản phẩm phải có ít nhất 1 ảnh.'; isValid = false;
  }
  */

  if (formData.variants.length === 0) {
    errors.variants = 'Sản phẩm phải có ít nhất 1 biến thể (SKU).'; isValid = false;
  } else {
    for (const variant of formData.variants) {
      if ((variant.price === null || variant.price < 0) || (variant.stock === null || variant.stock < 0)) {
        errors.variants = 'Giá và Số lượng của biến thể không hợp lệ.'; isValid = false; break;
      }
      if(formData.attribute_definitions.length > 0) {
          for(const attrDef of formData.attribute_definitions) {
              if(!variant.attributes[attrDef.name] || !variant.attributes[attrDef.name].trim()) {
                  errors.variants = `Vui lòng điền giá trị cho thuộc tính "${attrDef.name}" của tất cả biến thể.`;
                  isValid = false; break;
              }
          }
      }
      if(!isValid) break;
    }
  }
  return isValid;
}

// --- BƯỚC 4.3: SỬA HÀM LƯU DỮ LIỆU ---
async function handleSave() {
  if (!validateForm()) {
    return;
  }

  isLoading.value = true;

  // !!! CẢNH BÁO QUAN TRỌNG VỀ JSON-SERVER !!!
  // 1. json-server KHÔNG hỗ trợ upload file (FormData).
  // 2. json-server KHÔNG hỗ trợ tự động tạo/cập nhật các bản ghi liên quan
  //    (như Variants, Images) khi bạn POST/PUT vào /products.
  //
  // Do đó, code này được ĐƠN GIẢN HÓA:
  // - Chúng ta sẽ gửi JSON, bỏ qua việc upload ảnh mới.
  // - Chúng ta sẽ lưu variants và images TRỰC TIẾP vào đối tượng product.
  //   Việc này sẽ HOẠT ĐỘNG vì hàm fetchProducts() của chúng ta dùng _embed
  //   để lấy dữ liệu liên quan (nếu có) hoặc lấy dữ liệu đã nhúng (nếu không có).
  
  const productData = {
    name: formData.name,
    description: formData.description,
    category_id: formData.category_id, // Gửi ID
    status: formData.status,
    updated_at: new Date().toISOString(),

    // Nhúng trực tiếp variants
    variants: formData.variants.map(v => ({
      price: v.price,
      stock: v.stock,
      attributes: v.attributes
      // Nếu db.json của bạn yêu cầu product_id, hãy thêm nó ở đây
      // product_id: formData.id 
    })),

    // Giữ lại ảnh cũ (json-server không xử lý images_to_delete)
    images: formData.existing_images.map(img => ({ 
        id: img.id, 
        image_url: img.url,
        product_id: formData.id // Đảm bảo có product_id
    })),
    
    // Gán thumbnail (ảnh đầu tiên hoặc placeholder)
    thumbnail_url: formData.existing_images[0]?.url || 'https://placehold.co/150x150?text=No+Img',
    
    // Các trường thống kê
    sold_count: 0,
    favorite_count: 0,
    review_count: 0,
    average_rating: 0.0,
  };

  try {
    if (isEditMode.value) {
      // Lấy lại các trường thống kê cũ khi sửa
      const oldProduct = products.value.find(p => p.id === formData.id);
      if(oldProduct) {
        productData.sold_count = oldProduct.sold_count;
        productData.favorite_count = oldProduct.favorite_count;
        productData.review_count = oldProduct.review_count;
        productData.average_rating = oldProduct.average_rating;
      }
      
      // Dùng PUT để ghi đè (axios.put)
      await apiService.put(`/products/${formData.id}`, productData);
      Swal.fire('Thành công', 'Đã cập nhật sản phẩm!', 'success');
    
    } else {
      // Thêm trường created_at cho sản phẩm mới
      productData.created_at = new Date().toISOString();
      
      // Dùng POST để tạo mới (axios.post)
      await apiService.post(`/products`, productData);
      Swal.fire('Thành công', 'Đã tạo sản phẩm mới!', 'success');
    }

    modalInstance.value.hide();
    fetchProducts(); // Tải lại dữ liệu

  } catch (apiError) {
    console.error("Lỗi khi lưu:", apiError);
    // (Phần xử lý lỗi không thay đổi)
    if (apiError.response?.data?.errors) {
      const serverErrors = apiError.response.data.errors;
      if (serverErrors.name) errors.name = serverErrors.name[0];
      if (serverErrors.category_id) errors.category_id = serverErrors.category_id[0];
      if (serverErrors.new_images) errors.images = serverErrors.new_images[0];
    } else {
      Swal.fire('Thất bại', 'Đã có lỗi xảy ra. Vui lòng thử lại.', 'error');
    }
  } finally {
    isLoading.value = false;
  }
}

// --- CÁC HÀM KHÁC (KHÔNG THAY ĐỔI) ---
// (Các hàm toggleStatus, handleDelete, goToPage không cần
// thay đổi vì chúng đã dùng đúng phương thức apiService)

async function toggleStatus(product) {
  const newStatus = product.status === 'active' ? 'disabled' : 'active';
  const confirmText = `Bạn có chắc chắn muốn ${newStatus === 'active' ? 'KÍCH HOẠT' : 'VÔ HIỆU HÓA'} sản phẩm "${product.name}"?`;
  const result = await Swal.fire({
    title: 'Thay đổi trạng thái', text: confirmText, icon: 'question',
    showCancelButton: true, confirmButtonColor: '#3085d6', cancelButtonColor: '#d33',
    confirmButtonText: 'Đồng ý', cancelButtonText: 'Hủy'
  });
  if (result.isConfirmed) {
    product.status = newStatus;
    try {
      // Dùng PATCH (axios.patch) - Hàm này đã đúng
      await apiService.patch(`/products/${product.id}`, { status: newStatus });
      Swal.fire('Thành công', `Đã ${newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa'} sản phẩm.`, 'success');
    } catch (error) {
      console.error("Lỗi cập nhật trạng thái:", error);
      product.status = newStatus === 'active' ? 'disabled' : 'active';
      Swal.fire('Lỗi', 'Không thể cập nhật trạng thái.', 'error');
    }
  }
}
async function handleDelete(product) {
  const result = await Swal.fire({
    title: 'Bạn có chắc chắn?', text: `Bạn sẽ xóa vĩnh viễn sản phẩm "${product.name}"!`,
    icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6', confirmButtonText: 'Đồng ý xóa!', cancelButtonText: 'Hủy bỏ'
  });
  if (result.isConfirmed) {
    try {
      // Dùng DELETE (axios.delete) - Hàm này đã đúng
      await apiService.delete(`/products/${product.id}`);
      Swal.fire('Đã xóa!', 'Sản phẩm đã được xóa.', 'success');
      if (paginatedProducts.value.length === 1 && currentPage.value > 1) {
        currentPage.value--;
      }
      fetchProducts();
    } catch (error) {
      console.error("Lỗi khi xóa sản phẩm:", error);
      Swal.fire('Lỗi', 'Không thể xóa sản phẩm này.', 'error');
    }
  }
}
function goToPage(page) {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
}
</script>

<template>
  <!-- (Phần Header không đổi) -->
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Sản phẩm</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item"><router-link to="/admin">Trang chủ</router-link></li>
            <li class="breadcrumb-item active" aria-current="page">Sản phẩm</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card mb-4">
        <!-- (Phần Card Header: Tìm kiếm, Thêm mới không đổi) -->
        <div class="card-header">
          <div class="row align-items-center gy-2"> <!-- Thêm gy-2 để có khoảng cách trên mobile -->
            <!-- Cập nhật cột Tìm kiếm -->
            <div class="col-md-5 col-12">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm kiếm theo tên sản phẩm, danh mục..." v-model="searchQuery">
              </div>
            </div>

            <!-- THÊM MỚI: CỘT SẮP XẾP -->
            <div class="col-md-4 col-12">
              <select class="form-select" v-model="sortCriteria" aria-label="Sắp xếp sản phẩm">
                <option value="id-desc">Sắp xếp: Mặc định (Mới nhất)</option>
                <option value="created_at-desc">Ngày thêm: Mới nhất</option>
                <option value="created_at-asc">Ngày thêm: Cũ nhất</option>
                <option value="name-asc">Tên: A-Z</option>
                <option value="name-desc">Tên: Z-A</option>
                <option value="price-asc">Giá: Thấp đến Cao</option>
                <option value="price-desc">Giá: Cao đến Thấp</option>
              </select>
            </div>
            
            <!-- Cập nhật cột Thêm mới -->
            <div class="col-md-3 col-12 text-md-end">
              <button type="button" class="btn btn-primary" @click="openCreateModal">
                <i class="bi bi-plus-lg"></i> Thêm mới Sản phẩm
              </button>
            </div>
          </div>
        </div>

        <!-- Card Body: Bảng sản phẩm (ĐÃ CẬP NHẬT <img>) -->
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 50px;">ID</th>
                  <th style="width: 80px;">Ảnh</th>
                  <th>Tên sản phẩm</th>
                  <th>Danh mục</th>
                  <th>Giá (cơ bản)</th>
                  <th>Tổng kho</th>
                  <th style="width: 120px;">Trạng thái</th>
                  <th style="width: 180px;" class="text-center">Hành động</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="isLoading">
                  <td colspan="8" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                  </td>
                </tr>
                <tr v-else-if="filteredProducts.length === 0">
                  <td colspan="8" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    {{ searchQuery ? 'Không tìm thấy sản phẩm nào.' : 'Chưa có sản phẩm nào.' }}
                  </td>
                </tr>
                <tr v-for="product in paginatedProducts" :key="product.id">
                  <td>{{ product.id }}</td>
                  <td>
                    <!-- SỬA: Dùng thumbnail_url -->
                    <img :src="product.thumbnail_url || 'https://placehold.co/60x60?text=N/A'"
                      alt="Ảnh SP" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                  </td>
                  <td>{{ product.name }}</td>
                  <td>{{ product.category?.name || 'N/A' }}</td>
                  <!-- Cập nhật: Lấy giá từ mảng variants (nếu có) -->
                  <td>{{ formatCurrency(product.variants[0]?.price) }}</td>
                  <!-- Cập nhật: Tính tổng kho từ mảng variants -->
                  <td>{{ calculateTotalStock(product.variants) }}</td>
                  <td>
                    <span :class="['badge', product.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
                      {{ product.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
                    </span>
                  </td>
                  <td class="text-center">
                    <!-- (Hành động không đổi) -->
                    <div class="d-flex justify-content-center align-items-center">
                      <div class="form-check form-switch d-inline-block align-middle me-3" title="Kích hoạt/Vô hiệu hóa">
                        <input class="form-check-input" type="checkbox" role="switch"
                          style="width: 2.5em; height: 1.25em; cursor: pointer;"
                          :id="'statusSwitch-' + product.id"
                          :checked="product.status === 'active'"
                          @click.prevent="toggleStatus(product)">
                      </div>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" title="Xem chi tiết" @click="openViewModal(product)">
                          <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" title="Chỉnh sửa" @click="openEditModal(product)">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" title="Xóa" @click="handleDelete(product)">
                          <i class="bi bi-trash"></i>
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Card Footer: Phân trang (Không đổi) -->
        <div class="card-footer clearfix" v-if="totalPages > 1">
           <div class="d-flex justify-content-between align-items-center">
             <small class="text-muted">
               Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} đến
               {{ Math.min(currentPage * itemsPerPage, filteredProducts.length) }}
               trong tổng số {{ filteredProducts.length }} sản phẩm
             </small>
             <ul class="pagination pagination-sm m-0">
               <li class="page-item" :class="{ disabled: currentPage === 1 }">
                 <button class="page-link" @click="goToPage(currentPage - 1)">&laquo;</button>
               </li>
               <li v-for="page in totalPages" :key="page" class="page-item" :class="{ active: currentPage === page }">
                 <button class="page-link" @click="goToPage(page)">{{ page }}</button>
               </li>
               <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                 <button class="page-link" @click="goToPage(currentPage + 1)">&raquo;</button>
               </li>
             </ul>
           </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Thêm/Sửa (Phần Form không đổi) -->
  <div class="modal fade" id="productModal" ref="modalRef" tabindex="-1" aria-labelledby="productModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="productModalLabel">
            {{ isEditMode ? 'Chỉnh sửa Sản phẩm' : 'Tạo Sản phẩm mới' }}
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="handleSave">
            <!-- Thông tin cơ bản -->
            <div class="row">
              <div class="col-md-7">
                <div class="mb-3">
                  <label for="name" class="form-label required">Tên sản phẩm</label>
                  <input type="text" class="form-control" :class="{ 'is-invalid': errors.name }" id="name"
                    v-model="formData.name">
                  <div class="invalid-feedback" v-if="errors.name">{{ errors.name }}</div>
                </div>
                <div class="mb-3">
                  <label for="description" class="form-label">Mô tả</label>
                  <textarea class="form-control" id="description" rows="5" v-model="formData.description"></textarea>
                </div>
              </div>
              <div class="col-md-5">
                <div class="mb-3">
                  <label for="category_id" class="form-label required">Danh mục</label>
                  <select class="form-select" :class="{ 'is-invalid': errors.category_id }" id="category_id"
                    v-model="formData.category_id">
                    <option :value="null" disabled>-- Chọn danh mục --</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                  </select>
                  <div class="invalid-feedback" v-if="errors.category_id">{{ errors.category_id }}</div>
                </div>
                <div class="mb-3" v-if="isEditMode">
                  <label for="status" class="form-label fw-bold">Trạng thái</label>
                  <select class="form-select" id="status" v-model="formData.status">
                    <option value="active">Hoạt động (Hiển thị)</option>
                    <option value="disabled">Vô hiệu hóa (Ẩn)</option>
                  </select>
                </div>
                <!-- Quản lý ảnh -->
                <div class="mb-3">
                  <label for="product_images" class="form-label">Ảnh sản phẩm (Bao gồm cả thumbnail)</label>
                  <input type="file" class="form-control" :class="{ 'is-invalid': errors.images }" id="product_images"
                    @change="handleImageUpload" accept="image/*" multiple>
                  <div class="form-text">
                    <b>Lưu ý:</b> <code>json-server</code> không hỗ trợ upload file. 
                    Ảnh mới sẽ không được lưu. Chỉ ảnh có sẵn mới được giữ lại.
                  </div>
                  <div class="invalid-feedback" v-if="errors.images">{{ errors.images }}</div>
                  <div class="image-preview-container mt-2">
                    <div v-for="(image, index) in formData.existing_images" :key="`exist-${image.id}`"
                      class="image-preview-item">
                      <img :src="image.url" class="img-thumbnail" alt="Ảnh cũ">
                      <button class="btn btn-danger btn-sm btn-remove-image"
                        @click.prevent="removeExistingImage(index)">
                        &times;
                      </button>
                    </div>
                    <div v-for="(url, index) in newImagePreviews" :key="`new-${index}`" class="image-preview-item">
                      <img :src="url" class="img-thumbnail" alt="Ảnh mới">
                      <button class="btn btn-danger btn-sm btn-remove-image"
                        @click.prevent="removeNewImage(index)">
                        &times;
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <hr>
            <!-- Định nghĩa Thuộc tính -->
            <h5>Định nghĩa Thuộc tính</h5>
            <div class="card bg-light p-3 mb-3">
              <div class="row gx-2">
                 <div class="col">
                   <label for="newAttribute" class="form-label">Tên thuộc tính mới</label>
                   <input type="text" class="form-control" id="newAttribute" 
                         placeholder="ví dụ: Màu sắc, Kích cỡ, RAM..."
                         v-model="newAttributeName"
                         @keydown.enter.prevent="addAttributeDefinition">
                 </div>
                 <div class="col-auto d-flex align-items-end">
                   <button class="btn btn-info" @click.prevent="addAttributeDefinition">
                     <i class="bi bi-plus"></i> Thêm
                   </button>
                 </div>
              </div>
              <div class="invalid-feedback d-block" v-if="errors.attributes">{{ errors.attributes }}</div>
              <div class="mt-2 d-flex flex-wrap gap-2" v-if="formData.attribute_definitions.length > 0">
                 <span v-for="attr in formData.attribute_definitions" :key="attr.id" 
                       class="badge text-bg-secondary fs-6">
                   {{ attr.name }}
                   <button type="button" class="btn-close btn-close-white ms-1" 
                           style="font-size: 0.6em;"
                           @click="removeAttributeDefinition(attr)" 
                           aria-label="Close"></button>
                 </span>
              </div>
            </div>
            <!-- Bảng Biến thể động -->
            <h5>Biến thể sản phẩm (SKUs) <span class="text-danger">*</span></h5>
            <div class="alert alert-danger" v-if="errors.variants">
              {{ errors.variants }}
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th v-for="attrDef in formData.attribute_definitions" :key="attrDef.id">
                      {{ attrDef.name }} <span class="text-danger">*</span>
                    </th>
                    <th style="min-width: 120px;">Giá (VND) <span class="text-danger">*</span></th>
                    <th style="min-width: 100px;">Số lượng <span class="text-danger">*</span></th>
                    <th style="width: 50px;">Xóa</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="formData.variants.length === 0">
                      <td :colspan="formData.attribute_definitions.length + 3" class="text-center text-muted p-3">
                        <span v-if="formData.attribute_definitions.length === 0">Vui lòng thêm thuộc tính...</span>
                        <span v-else>Chưa có biến thể nào.</span>
                      </td>
                  </tr>
                  <tr v-for="(variant, index) in formData.variants" :key="variant.id">
                    <td v-for="attrDef in formData.attribute_definitions" :key="attrDef.id">
                      <input type="text" class="form-control form-control-sm" 
                             :placeholder="attrDef.name"
                             v-model="variant.attributes[attrDef.name]">
                    </td>
                    <td>
                      <input type="number" class="form-control form-control-sm" placeholder="Giá" 
                             v-model.number="variant.price" min="0">
                    </td>
                    <td>
                      <input type="number" class="form-control form-control-sm" placeholder="SL" 
                             v-model.number="variant.stock" min="0">
                    </td>
                    <td class="text-center">
                      <button class="btn btn-danger btn-sm" @click.prevent="removeVariantRow(index)"
                              :disabled="formData.variants.length <= 1">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button class="btn btn-success btn-sm mt-2" @click.prevent="addVariantRow">
              <i class="bi bi-plus-lg"></i> Thêm biến thể
            </button>
          </form>
        </div>
        <!-- (Modal Footer không đổi) -->
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
          <button type="button" class="btn btn-primary" @click="handleSave" :disabled="isLoading">
            <span v-if="isLoading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            {{ isEditMode ? 'Lưu thay đổi' : 'Tạo sản phẩm' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Xem Chi Tiết (ĐÃ CẬP NHẬT) -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-body p-4 position-relative">
          <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
            aria-label="Close"></button>

          <!-- Status Badge -->
          <div class="position-absolute top-0 start-0 m-3">
            <span :class="['badge', viewingProduct.status === 'active' ? 'bg-success' : 'bg-secondary']">
              {{ viewingProduct.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
            </span>
          </div>

          <!-- Thông tin chung -->
          <div class="row">
            <div class="col-md-4 text-center">
              <img :src="viewingProduct.thumbnail_url || 'https://placehold.co/150x150?text=N/A'"
                class="img-thumbnail shadow-sm mb-3" alt="Ảnh sản phẩm"
                style="width: 100%; height: auto; max-height: 250px; object-fit: cover;">
              <h5 class="mt-3 mb-1">{{ viewingProduct.name }}</h5>
              <p class="text-muted mb-0">ID: {{ viewingProduct.id }}</p>
            </div>
            <div class="col-md-7">
              <div class="list-group list-group-flush">
                <div class="list-group-item px-0">
                  <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1"><i class="bi bi-tags me-3 text-primary"></i>Danh mục</h6>
                    <span>{{ viewingProduct.categoryName }}</span>
                  </div>
                </div>
                <div class="list-group-item px-0">
                  <h6 class="mb-2"><i class="bi bi-calendar-event me-3 text-muted"></i>Ngày tạo</h6>
                  <p class="mb-1 text-muted small">{{ getFormattedDate(viewingProduct.created_at) }}</p>
                </div>
                <div class="list-group-item px-0">
                  <h6 class="mb-2"><i class="bi bi-card-text me-3 text-muted"></i>Mô tả</h6>
                  <p class="mb-1 text-muted small" style="white-space: pre-wrap;">{{ viewingProduct.description || 'Không có mô tả.' }}</p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- THÊM MỚI: Thống kê sản phẩm -->
          <hr class="my-3">
          <h5 class="mb-3">Thống kê</h5>
          <div class="row g-2 text-center">
              <div class="col-6 col-md-3">
                  <div class="card p-2 shadow-sm">
                      <h6 class="text-muted mb-0">Đã bán</h6>
                      <span class="fs-4 fw-bold text-primary">{{ viewingProduct.sold_count }}</span>
                  </div>
              </div>
              <div class="col-6 col-md-3">
                  <div class="card p-2 shadow-sm">
                      <h6 class="text-muted mb-0">Yêu thích</h6>
                      <span class="fs-4 fw-bold text-danger"><i class="bi bi-heart-fill"></i> {{ viewingProduct.favorite_count }}</span>
                  </div>
              </div>
              <div class="col-6 col-md-3">
                  <div class="card p-2 shadow-sm">
                      <h6 class="text-muted mb-0">Đánh giá</h6>
                      <span class="fs-4 fw-bold text-info">{{ viewingProduct.review_count }}</span>
                  </div>
              </div>
              <div class="col-6 col-md-3">
                  <div class="card p-2 shadow-sm">
                      <h6 class="text-muted mb-0">Xếp hạng</h6>
                      <span class="fs-4 fw-bold text-warning">{{ viewingProduct.average_rating.toFixed(1) }} <i class="bi bi-star-fill"></i></span>
                  </div>
              </div>
          </div>

          <hr class="my-4">

          <!-- Bảng biến thể (Không đổi) -->
          <h5 class="mb-3">Các biến thể (SKUs)</h5>
          <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
            <table class="table table-sm table-striped table-bordered">
              <thead class="table-light sticky-top">
                <tr>
                  <th v-for="attrName in viewingProduct.attributeNames" :key="attrName">
                    {{ attrName }}
                  </th>
                  <th>Giá</th>
                  <th>Tồn kho</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!viewingProduct.variants || viewingProduct.variants.length === 0">
                    <td :colspan="viewingProduct.attributeNames.length + 2" class="text-center text-muted">
                      Không có biến thể
                    </td>
                </tr>
                <tr v-for="(variant, index) in viewingProduct.variants" :key="index">
                  <td v-for="attrName in viewingProduct.attributeNames" :key="attrName">
                    {{ variant.attributes[attrName] || 'N/A' }}
                  </td>
                  <td>{{ formatCurrency(variant.price) }}</td>
                  <td>{{ variant.stock }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- THÊM MỚI: Thư viện ảnh -->
          <hr class="my-4">
          <h5 class="mb-3">Thư viện ảnh</h5>
          <div class="image-preview-container" style="max-height: 300px;">
              <div v-if="!viewingProduct.images || viewingProduct.images.length === 0" class="text-muted p-3">
                  Không có ảnh chi tiết.
              </div>
              <div v-for="image in viewingProduct.images" :key="image.id" class="image-preview-item" style="width: 120px; height: 120px;">
                  <img :src="image.image_url || image.url" class="img-thumbnail" alt="Ảnh chi tiết">
              </div>
          </div>


        </div>
        <div class="modal-footer bg-light justify-content-center">
          <button type="button" class="btn btn-primary px-4"
            @click="() => { viewModalInstance.hide(); openEditModal(viewingProduct); }">
            <i class="bi bi-pencil me-2"></i> Chỉnh sửa sản phẩm
          </button>
        </div>
      </div>
    </div>
  </div>

</template>

<style scoped>
/* (CSS không thay đổi nhiều, giữ nguyên các style cũ) */
.image-preview-container {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 10px;
  max-height: 200px;
  overflow-y: auto;
  padding: 5px;
  border: 1px solid #dee2e6;
  border-radius: .375rem;
  background: #f8f9fa; /* Thêm nền nhẹ */
}

.image-preview-item {
  position: relative;
  width: 100px;
  height: 100px;
  flex-shrink: 0;
}

.image-preview-item .img-thumbnail {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.image-preview-item .btn-remove-image {
  position: absolute;
  top: -5px;
  right: -5px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background-color: red;
  color: white;
  border: none; 
  font-weight: bold;
  font-size: 12px;
  line-height: 1;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 10;
}

.table td .btn {
  margin-top: 2px;
  margin-bottom: 2px;
}

.required::after {
  content: " *";
  color: red;
}

.table-responsive {
    overflow-x: auto; 
}
</style>