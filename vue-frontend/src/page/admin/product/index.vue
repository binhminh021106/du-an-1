<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- STATE QUẢN LÝ ---
const products = ref([]);
const categories = ref([]);
const attributesList = ref([]);
const isLoading = ref(true);
const isEditMode = ref(false); // false: Tạo mới (Basic Info), true: Cấu hình biến thể (Full)

// State Modal
const modalInstance = ref(null);
const modalRef = ref(null);
const attrModalInstance = ref(null);
const attrModalRef = ref(null);

// State Form phụ (QUAN TRỌNG: Thêm biến defaultAttributeValue)
const newQuickAttrName = ref('');
const selectedAttributeId = ref('');
const defaultAttributeValue = ref(''); // <--- Biến lưu giá trị nhập ngay lúc thêm thuộc tính

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
const sortCriteria = ref('product_id-desc');

// Form Data
const formData = reactive({
  id: null,
  name: '',
  description: '',
  category_id: null,
  status: 'inactive',
  attribute_definitions: reactive([]),
  variants: reactive([]),
  existing_images: reactive([]),
  new_images: [],
  images_to_delete: []
});

const newImagePreviews = ref([]);

const errors = reactive({
  name: '',
  category_id: '',
  images: '',
  variants: '',
  attributes: ''
});

function generateShortId() {
  return Math.random().toString(36).substring(2, 8);
}

// --- COMPUTED ---
const filteredProducts = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  let filtered = products.value;

  if (query) {
    filtered = products.value.filter(product =>
      product.name.toLowerCase().includes(query) ||
      (product.category?.name && product.category.name.toLowerCase().includes(query))
    );
  }

  const [key, order] = sortCriteria.value.split('-');
  const sorted = [...filtered];

  sorted.sort((a, b) => {
    let valA, valB;
    switch (key) {
      case 'name':
        valA = a.name.toLowerCase();
        valB = b.name.toLowerCase();
        break;
      case 'price':
        valA = getMinPrice(a.variants);
        valB = getMinPrice(b.variants);
        break;
      case 'created_at':
        valA = new Date(a.created_at);
        valB = new Date(b.created_at);
        break;
      case 'product_id':
      default:
        valA = a.product_id;
        valB = b.product_id;
        break;
    }
    let comparison = 0;
    if (valA > valB) comparison = 1;
    else if (valA < valB) comparison = -1;
    return order === 'asc' ? comparison : -comparison;
  });

  return sorted;
});

const totalPages = computed(() => Math.ceil(filteredProducts.value.length / itemsPerPage.value));
const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return filteredProducts.value.slice(start, start + itemsPerPage.value);
});

watch([searchQuery, sortCriteria], () => { currentPage.value = 1; });

onMounted(async () => {
  await Promise.all([fetchCategories(), fetchAttributes()]);
  fetchProducts();

  if (modalRef.value) modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
  if (viewModalRef.value) viewModalInstance.value = new Modal(viewModalRef.value);
  if (attrModalRef.value) attrModalInstance.value = new Modal(attrModalRef.value, { backdrop: 'static' });
});

// --- API ---
async function fetchAttributes() {
  try {
    const response = await apiService.get('/admin/attributes');
    attributesList.value = Array.isArray(response.data) ? response.data : (response.data.data || []);
  } catch (error) {
    console.error("Lỗi tải attributes:", error);
  }
}

async function handleCreateQuickAttribute() {
  if (!newQuickAttrName.value.trim()) {
    Swal.fire('Lỗi', 'Vui lòng nhập tên thuộc tính', 'warning');
    return;
  }
  try {
    const res = await apiService.post('/admin/attributes', { name: newQuickAttrName.value });
    Swal.fire('Thành công', 'Đã tạo thuộc tính mới!', 'success');
    await fetchAttributes();
    if (res.data && res.data.id) selectedAttributeId.value = res.data.id;
    newQuickAttrName.value = '';
    attrModalInstance.value.hide();
    modalInstance.value.show();
  } catch (error) {
    const msg = error.response?.data?.message || 'Không thể tạo thuộc tính.';
    Swal.fire('Lỗi', msg, 'error');
  }
}

async function fetchProducts() {
  isLoading.value = true;
  try {
    const [productsRes, variantsRes, imagesRes] = await Promise.all([
      apiService.get('/admin/products'),
      apiService.get('/variants'),
      apiService.get('/imageProducts')
    ]);

    const rawProducts = productsRes.data.data ? productsRes.data.data : productsRes.data;
    const allCategories = categories.value;
    const allVariants = variantsRes.data;
    const allImages = imagesRes.data;

    products.value = rawProducts.map(p => {
      const category = allCategories.find(c => c.id === p.category_id);
      const productVariants = allVariants.filter(v => v.product_id == p.product_id);
      const productImages = allImages.filter(img => img.product_id == p.product_id);

      return {
        sold_count: 0, favorite_count: 0, review_count: 0, average_rating: 0.0,
        status: 'active', created_at: new Date().toISOString(),
        ...p,
        product_id: p.product_id || p.id,
        category: category || null,
        variants: productVariants,
        images: productImages,
        thumbnail_url: p.thumbnail_url || (productImages.length > 0 ? productImages[0].image_url : 'https://placehold.co/150x150?text=No+Img')
      };
    });
  } catch (error) {
    console.error("Lỗi tải sản phẩm:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách sản phẩm.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function fetchCategories() {
  try {
    const response = await apiService.get(`/categories?status=active&_sort=order&_order=asc`);
    const data = Array.isArray(response.data) ? response.data : (response.data.data || []);
    categories.value = data.map(c => ({ ...c, id: c.id }));
  } catch (error) {
    console.error("Lỗi tải danh mục:", error);
  }
}

// --- HELPERS ---
function formatCurrency(value) {
  if (value == null || isNaN(value)) return 'N/A';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}
function getMinPrice(variants) {
  if (!variants?.length) return 0;
  const prices = variants.map(v => parseFloat(v.price)).filter(p => !isNaN(p));
  return prices.length ? Math.min(...prices) : 0;
}
function calculateTotalStock(variants) {
  if (!variants?.length) return 0;
  return variants.reduce((acc, v) => acc + (parseInt(v.stock) || 0), 0);
}
function getPriceRange(variants) {
  if (!variants?.length) return 'N/A';
  const prices = variants.map(v => parseFloat(v.price)).filter(p => !isNaN(p));
  if (!prices.length) return 'N/A';
  const min = Math.min(...prices), max = Math.max(...prices);
  return min === max ? formatCurrency(min) : `${formatCurrency(min)} - ${formatCurrency(max)}`;
}

// --- FORM LOGIC (ĐÃ SỬA ĐỔI: BẮT BUỘC NHẬP GIÁ TRỊ) ---
function addSelectedAttributeToForm() {
  // 1. Kiểm tra đã chọn thuộc tính chưa
  if (!selectedAttributeId.value) {
    errors.attributes = 'Vui lòng chọn một thuộc tính.';
    return;
  }

  // 2. Kiểm tra ĐÃ NHẬP GIÁ TRỊ chưa (Yêu cầu mới)
  const val = defaultAttributeValue.value.trim();
  if (!val) {
    Swal.fire({
      icon: 'warning',
      title: 'Thiếu giá trị',
      text: 'Vui lòng nhập giá trị cho thuộc tính (Ví dụ: 16GB, Đỏ, XL...) để hệ thống có thể lưu vào CSDL.',
    });
    return;
  }

  const attrObj = attributesList.value.find(a => a.id == selectedAttributeId.value);
  if (!attrObj) return;

  // 3. Kiểm tra trùng
  const exists = formData.attribute_definitions.find(a => a.id == attrObj.id || a.name === attrObj.name);
  if (exists) {
    errors.attributes = `Thuộc tính "${attrObj.name}" đã có trong bảng.`;
    return;
  }
  errors.attributes = '';

  // 4. Thêm định nghĩa cột
  formData.attribute_definitions.push({ id: attrObj.id, name: attrObj.name });

  // 5. Điền giá trị này vào TẤT CẢ các dòng biến thể hiện có
  // Việc này đảm bảo khi bấm Save, dữ liệu { "Ram": "16GB" } sẽ được gửi đi
  formData.variants.forEach(v => {
    if (!v.attributes) v.attributes = {};
    v.attributes[attrObj.name] = val;
  });

  // 6. Reset form thêm thuộc tính
  selectedAttributeId.value = '';
  defaultAttributeValue.value = '';
}

function removeAttributeDefinition(attrToRemove) {
  const index = formData.attribute_definitions.findIndex(attr => attr.id === attrToRemove.id);
  if (index > -1) {
    formData.attribute_definitions.splice(index, 1);
    formData.variants.forEach(v => { delete v.attributes[attrToRemove.name]; });
  }
}

function addVariantRow() {
  const newAttributes = reactive({});
  // Khi thêm dòng mới, khởi tạo với giá trị rỗng cho các cột hiện có
  formData.attribute_definitions.forEach(def => { newAttributes[def.name] = ''; });

  formData.variants.push(reactive({
    variant_id: generateShortId(),
    original_price: 0, price: 0, stock: 0,
    attributes: newAttributes
  }));
}

function removeVariantRow(index) {
  formData.variants.splice(index, 1);
}

// --- MODAL HANDLERS ---
function resetForm() {
  formData.id = null;
  formData.name = ''; formData.description = ''; formData.category_id = null; formData.status = 'inactive';
  formData.attribute_definitions = reactive([]); formData.variants = reactive([]);
  formData.existing_images = reactive([]); formData.new_images = []; formData.images_to_delete = [];

  selectedAttributeId.value = '';
  defaultAttributeValue.value = ''; // Reset ô giá trị mặc định

  newImagePreviews.value.forEach(url => URL.revokeObjectURL(url));
  newImagePreviews.value = [];
  Object.keys(errors).forEach(k => errors[k] = '');
}

function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  // Không tự động thêm dòng variant khi tạo mới info
  modalInstance.value.show();
}

function openVariantConfigModal(product) {
  resetForm();
  isEditMode.value = true;

  formData.id = product.id;
  formData.name = product.name;
  formData.description = product.description;
  formData.category_id = product.category_id || product.category?.id;
  formData.status = product.status;

  formData.existing_images = reactive((product.images || []).map(img => ({
    id: img.img_id || img.id,
    url: img.image_url || img.url
  })));

  const allKeys = new Set();
  (product.variants || []).forEach(v => {
    if (v.attributes) Object.keys(v.attributes).forEach(k => allKeys.add(k));
  });

  formData.attribute_definitions = reactive(Array.from(allKeys).map(name => {
    const found = attributesList.value.find(a => a.name === name);
    return { id: found ? found.id : generateShortId(), name: name };
  }));

  formData.variants = reactive((product.variants || []).map(v => {
    const attrs = reactive({});
    allKeys.forEach(k => { attrs[k] = v.attributes?.[k] || ''; });
    return reactive({
      ...v,
      variant_id: v.id,
      attributes: attrs
    });
  }));

  if (!formData.variants.length) addVariantRow();
  modalInstance.value.show();
}

function openCreateAttrModal() {
  newQuickAttrName.value = '';
  errors.attributes = '';
  attrModalInstance.value.show();
}

function openViewModal(product) {
  const keys = (product.variants?.[0]?.attributes) ? Object.keys(product.variants[0].attributes) : [];
  viewingProduct.value = {
    ...product,
    categoryName: product.category?.name || 'N/A',
    attributeNames: keys,
    images: product.images || []
  };
  viewModalInstance.value.show();
}

// --- VALIDATION ---
function validateForm() {
  Object.keys(errors).forEach(k => errors[k] = '');
  let isValid = true;

  if (!formData.name.trim()) { errors.name = 'Nhập tên SP'; isValid = false; }
  if (!formData.category_id) { errors.category_id = 'Chọn danh mục'; isValid = false; }

  if (isEditMode.value) {
    if (!formData.variants.length) { errors.variants = 'Thêm ít nhất 1 biến thể'; isValid = false; }
    else {
      for (const v of formData.variants) {
        if (v.price < 0 || v.stock < 0) {
          errors.variants = 'Giá/Kho không hợp lệ'; isValid = false; break;
        }
        for (const def of formData.attribute_definitions) {
          if (!v.attributes[def.name]?.trim()) {
            errors.variants = `Điền giá trị "${def.name}"`; isValid = false; break;
          }
        }
        if (!isValid) break;
      }
    }
  }
  return isValid;
}

// --- SAVE LOGIC (CẬP NHẬT: Tách Attribute gửi riêng) ---
async function handleSave() {
  if (!validateForm()) return;
  isLoading.value = true;

  try {
    // TRƯỜNG HỢP 1: TẠO MỚI (Chỉ lưu Product Info)
    if (!isEditMode.value) {
      // ... (Giữ nguyên logic tạo product mới)
      const productPayload = {
        name: formData.name,
        description: formData.description,
        category_id: formData.category_id,
        status: 'inactive',
        thumbnail_url: 'https://placehold.co/150x150?text=No+Img'
      };
      await apiService.post('/admin/products', productPayload);
      Swal.fire('Thành công', 'Đã tạo sản phẩm nháp. Vui lòng bấm vào nút "Cấu hình biến thể" để thêm giá và kho!', 'success');
    }

    // TRƯỜNG HỢP 2: UPDATE / CẤU HÌNH BIẾN THỂ
    else {
      const productDbId = formData.id;

      // 1. Update Product Info
      await apiService.patch(`/admin/products/${productDbId}`, {
        name: formData.name,
        description: formData.description,
        category_id: formData.category_id,
        status: formData.status
      });

      // 2. Update Variants & Attributes
      // Vòng lặp qua từng dòng biến thể trên giao diện
      for (const variant of formData.variants) {

        // A. Chuẩn bị payload cơ bản (CHỈ CÓ: Giá, Kho, ProductID)
        // Không gửi attributes vào đây nữa để tránh bị mất dữ liệu
        const variantPayload = {
          product_id: productDbId,
          original_price: variant.original_price,
          price: variant.price,
          stock: variant.stock
        };

        let savedVariantId = variant.variant_id;
        const isNewVariant = isNaN(Number(savedVariantId)); // Check xem là ID tạm (string) hay ID thật (số)

        // B. Lưu thông tin cơ bản trước
        if (isNewVariant) {
          // Nếu là mới -> Gọi POST -> Lấy ID thật trả về
          const res = await apiService.post('/admin/variants', variantPayload);
          savedVariantId = res.data.id; 
        } else {
          // Nếu là cũ -> Gọi PATCH
          await apiService.patch(`/admin/variants/${savedVariantId}`, variantPayload);
        }

        // C. GỬI RIÊNG ATTRIBUTES (Bước quan trọng)
        // Chỉ gửi nếu có attributes và đã có ID thật
        const attributesPayload = { ...variant.attributes }; // Clone ra object thuần
        
        if (savedVariantId && Object.keys(attributesPayload).length > 0) {
            // Gọi API chuyên biệt để lưu thuộc tính
            await apiService.post(`/admin/variants/${savedVariantId}/attributes`, {
                attributes: attributesPayload
            });
        }
      }
      Swal.fire('Thành công', 'Đã cập nhật sản phẩm và biến thể!', 'success');
    }

    modalInstance.value.hide();
    fetchProducts();
  } catch (e) {
    console.error("Lỗi lưu:", e); 
    Swal.fire('Lỗi', 'Có lỗi xảy ra khi lưu dữ liệu.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function handleDelete(product) {
  if ((await Swal.fire({ title: 'Xóa?', text: 'Hành động này không thể hoàn tác!', icon: 'warning', showCancelButton: true })).isConfirmed) {
    try {
      await apiService.delete(`/admin/products/${product.id}`);
      fetchProducts();
      Swal.fire('OK', 'Đã xóa', 'success');
    } catch (e) { Swal.fire('Lỗi', 'Không thể xóa', 'error'); }
  }
}

function goToPage(p) { if (p >= 1 && p <= totalPages.value) currentPage.value = p; }
</script>

<template>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0">Quản lý Sản phẩm</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item active">Sản phẩm</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card mb-4">
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col-md-5"><input type="text" class="form-control" placeholder="Tìm kiếm..."
                v-model="searchQuery"></div>
            <div class="col-md-4">
              <select class="form-select" v-model="sortCriteria">
                <option value="product_id-desc">Mới nhất</option>
                <option value="price-asc">Giá tăng dần</option>
              </select>
            </div>
            <div class="col-md-3 text-end">
              <button class="btn btn-primary" @click="openCreateModal"><i class="bi bi-plus-lg"></i> Thêm SP
                Mới</button>
            </div>
          </div>
        </div>
        <div class="card-body p-0">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Ảnh</th>
                <th>Tên</th>
                <th>Danh mục</th>
                <th>Giá</th>
                <th>Kho</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="isLoading">
                <td colspan="8" class="text-center p-4">
                  <div class="spinner-border text-primary"></div>
                </td>
              </tr>
              <tr v-else v-for="p in paginatedProducts" :key="p.id">
                <td>{{ p.product_id }}</td>
                <td><img :src="p.thumbnail_url" class="img-thumbnail" style="width:50px;height:50px;object-fit:cover">
                </td>
                <td>{{ p.name }}</td>
                <td>{{ p.category?.name }}</td>
                <td>{{ getPriceRange(p.variants) }}</td>
                <td>{{ calculateTotalStock(p.variants) }}</td>
                <td><span :class="['badge', p.status === 'active' ? 'bg-success' : 'bg-secondary']">{{ p.status ===
                  'active' ? 'Đang bán' : 'Ẩn/Nháp' }}</span></td>
                <td>
                  <button class="btn btn-sm btn-outline-info me-1" @click="openViewModal(p)" title="Xem chi tiết"><i
                      class="bi bi-eye"></i></button>
                  <button class="btn btn-sm btn-warning me-1" @click="openVariantConfigModal(p)"
                    title="Cấu hình biến thể & Sửa"><i class="bi bi-gear-fill"></i></button>
                  <button class="btn btn-sm btn-outline-danger" @click="handleDelete(p)" title="Xóa"><i
                      class="bi bi-trash"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-footer" v-if="totalPages > 1">
          <ul class="pagination pagination-sm float-end m-0">
            <li class="page-item" :class="{ disabled: currentPage === 1 }"><button class="page-link"
                @click="goToPage(currentPage - 1)">&laquo;</button></li>
            <li class="page-item disabled"><span class="page-link">{{ currentPage }} / {{ totalPages }}</span></li>
            <li class="page-item" :class="{ disabled: currentPage === totalPages }"><button class="page-link"
                @click="goToPage(currentPage + 1)">&raquo;</button></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Product -->
  <div class="modal fade" id="productModal" ref="modalRef" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ isEditMode ? 'Cấu hình Biến thể & Sửa' : 'Thêm Sản phẩm mới (Bước 1)' }}</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-7">
              <div class="mb-3"><label class="form-label">Tên SP</label><input type="text" class="form-control"
                  v-model="formData.name" :class="{ 'is-invalid': errors.name }">
                <div class="invalid-feedback">{{ errors.name }}</div>
              </div>
              <div class="mb-3"><label class="form-label">Mô tả</label><textarea class="form-control" rows="3"
                  v-model="formData.description"></textarea></div>
            </div>
            <div class="col-md-5">
              <div class="mb-3">
                <label class="form-label">Danh mục</label>
                <select class="form-select" v-model="formData.category_id"
                  :class="{ 'is-invalid': errors.category_id }">
                  <option :value="null">-- Chọn --</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <div class="invalid-feedback">{{ errors.category_id }}</div>
              </div>
              <div class="mb-3" v-if="isEditMode">
                <label class="form-label">Trạng thái</label>
                <select class="form-select" v-model="formData.status">
                  <option value="active">Đang bán (Active)</option>
                  <option value="inactive">Ẩn (Inactive)</option>
                </select>
              </div>
              <div class="mb-3" v-else>
                <div class="alert alert-warning py-2 small"><i class="bi bi-info-circle"></i> Sản phẩm mới sẽ mặc định ở
                  trạng thái <b>Ẩn</b>. Hãy lưu lại rồi cấu hình biến thể sau.</div>
              </div>
            </div>
          </div>
          <hr>

          <!-- PHẦN BIẾN THỂ (CẬP NHẬT UI) -->
          <div v-if="isEditMode">
            <h5>Cấu hình Biến thể</h5>
            <div class="card bg-light p-3 mb-3">
              <div class="row g-2 align-items-center">
                <!-- 1. Chọn thuộc tính -->
                <div class="col-auto">
                  <select class="form-select" v-model="selectedAttributeId">
                    <option value="">-- Chọn thuộc tính --</option>
                    <option v-for="a in attributesList" :key="a.id" :value="a.id">{{ a.name }}</option>
                  </select>
                </div>
                <!-- 2. Ô NHẬP GIÁ TRỊ (THÊM MỚI) -->
                <div class="col-auto">
                  <input type="text" class="form-control" v-model="defaultAttributeValue"
                    placeholder="Nhập giá trị (VD: Đỏ)">
                </div>
                <!-- 3. Nút thêm -->
                <div class="col-auto">
                  <button class="btn btn-primary" @click="addSelectedAttributeToForm">Thêm cột</button>
                </div>
                <!-- 4. Tạo thuộc tính master -->
                <div class="col-auto ms-auto">
                  <button class="btn btn-outline-success" @click="openCreateAttrModal">Tạo thuộc tính mới</button>
                </div>
              </div>
              <div class="mt-2 text-danger small" v-if="errors.attributes">{{ errors.attributes }}</div>

              <!-- Hiển thị các cột đã thêm -->
              <div class="mt-2" v-if="formData.attribute_definitions.length > 0">
                <span class="text-muted me-2">Các cột đã chọn:</span>
                <span v-for="attr in formData.attribute_definitions" :key="attr.id"
                  class="badge bg-white text-dark border me-1">
                  {{ attr.name }} <i class="bi bi-x text-danger cursor-pointer ms-1"
                    @click="removeAttributeDefinition(attr)"></i>
                </span>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-sm text-center align-middle">
                <thead>
                  <tr>
                    <th v-for="d in formData.attribute_definitions" :key="d.id">{{ d.name }}</th>
                    <th>Giá gốc</th>
                    <th>Giá bán</th>
                    <th>Kho</th>
                    <th>Xóa</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(v, i) in formData.variants" :key="i">
                    <td v-for="d in formData.attribute_definitions" :key="d.id">
                      <input type="text" class="form-control form-control-sm" v-model="v.attributes[d.name]">
                    </td>
                    <td><input type="number" class="form-control form-control-sm" v-model="v.original_price"></td>
                    <td><input type="number" class="form-control form-control-sm" v-model="v.price"></td>
                    <td><input type="number" class="form-control form-control-sm" v-model="v.stock"></td>
                    <td><button class="btn btn-sm text-danger" @click="removeVariantRow(i)"><i
                          class="bi bi-trash"></i></button></td>
                  </tr>
                </tbody>
              </table>
              <button class="btn btn-sm btn-success" @click="addVariantRow">+ Thêm dòng biến thể</button>
              <div class="text-danger small mt-1" v-if="errors.variants">{{ errors.variants }}</div>
            </div>
          </div>

          <div v-else class="text-center py-5 bg-light border border-dashed rounded">
            <i class="bi bi-arrow-down-circle fs-1 text-muted"></i>
            <h5 class="mt-2 text-muted">Lưu thông tin cơ bản trước</h5>
            <p class="text-muted small">Sau khi lưu, bạn có thể thêm màu sắc, kích thước và giá bán ở màn hình danh
              sách.</p>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button><button
            class="btn btn-primary" @click="handleSave">Lưu</button></div>
      </div>
    </div>
  </div>

  <!-- Modal Quick Attr -->
  <div class="modal fade" id="attrModal" ref="attrModalRef" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h6 class="modal-title">Tạo thuộc tính</h6><button class="btn-close btn-close-white"
            data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body"><input type="text" class="form-control" v-model="newQuickAttrName"
            placeholder="Tên thuộc tính..."></div>
        <div class="modal-footer p-1"><button class="btn btn-sm btn-success w-100"
            @click="handleCreateQuickAttribute">Lưu</button></div>
      </div>
    </div>
  </div>

  <!-- Modal View -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-body">
          <h5>{{ viewingProduct.name }}</h5>
          <pre>{{ viewingProduct }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}

.border-dashed {
  border-style: dashed !important;
}
</style>