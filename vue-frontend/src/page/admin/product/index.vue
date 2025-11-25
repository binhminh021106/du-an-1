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
const isEditMode = ref(false);

// State Modal
const modalInstance = ref(null);
const modalRef = ref(null);
const attrModalInstance = ref(null);
const attrModalRef = ref(null);
const newQuickAttrName = ref('');

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
  status: 'active',
  attribute_definitions: reactive([]),
  variants: reactive([]),
  existing_images: reactive([]),
  new_images: [],
  images_to_delete: []
});

const selectedAttributeId = ref('');
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
function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
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

// --- FORM LOGIC ---
function addSelectedAttributeToForm() {
  if (!selectedAttributeId.value) {
    errors.attributes = 'Vui lòng chọn một thuộc tính.';
    return;
  }
  const attrObj = attributesList.value.find(a => a.id == selectedAttributeId.value);
  if (!attrObj) return;

  const exists = formData.attribute_definitions.find(a => a.id == attrObj.id || a.name === attrObj.name);
  if (exists) {
    errors.attributes = `Thuộc tính "${attrObj.name}" đã có.`;
    return;
  }
  errors.attributes = '';
  formData.attribute_definitions.push({ id: attrObj.id, name: attrObj.name });
  formData.variants.forEach(v => {
    if (!v.attributes) v.attributes = {};
    if (!(attrObj.name in v.attributes)) v.attributes[attrObj.name] = '';
  });
  selectedAttributeId.value = '';
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
  formData.attribute_definitions.forEach(def => { newAttributes[def.name] = ''; });
  formData.variants.push(reactive({
    variant_id: generateShortId(), // ID tạm là chuỗi
    original_price: 0, price: 0, stock: 0,
    attributes: newAttributes
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
  formData.existing_images.splice(index, 1);
}

// --- MODAL HANDLERS ---
function resetForm() {
  formData.id = null;
  formData.name = ''; formData.description = ''; formData.category_id = null; formData.status = 'active';
  formData.attribute_definitions = reactive([]); formData.variants = reactive([]);
  formData.existing_images = reactive([]); formData.new_images = []; formData.images_to_delete = [];
  selectedAttributeId.value = '';
  newImagePreviews.value.forEach(url => URL.revokeObjectURL(url));
  newImagePreviews.value = [];
  const fileInput = document.getElementById('product_images');
  if (fileInput) fileInput.value = '';
  Object.keys(errors).forEach(k => errors[k] = '');
}

function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  addVariantRow();
  modalInstance.value.show();
}
function openCreateAttrModal() {
  newQuickAttrName.value = '';
  errors.attributes = '';
  attrModalInstance.value.show();
}
function openEditModal(product) {
  resetForm();
  isEditMode.value = true;
  formData.id = product.id;
  formData.name = product.name;
  formData.description = product.description;
  formData.category_id = product.category_id || product.category?.id;
  formData.status = product.status || 'active';

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
      variant_id: v.id, // Sử dụng ID thật từ DB cho việc edit
      attributes: attrs
    });
  }));

  if (!formData.variants.length) addVariantRow();
  modalInstance.value.show();
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

function validateForm() {
  Object.keys(errors).forEach(k => errors[k] = '');
  let isValid = true;
  if (!formData.name.trim()) { errors.name = 'Nhập tên SP'; isValid = false; }
  if (!formData.category_id) { errors.category_id = 'Chọn danh mục'; isValid = false; }
  if (!formData.variants.length) { errors.variants = 'Thêm ít nhất 1 biến thể'; isValid = false; }
  else {
    for (const v of formData.variants) {
      if (v.price < 0 || v.stock < 0 || v.original_price < 0) {
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
  return isValid;
}

// --- CORE SAVE LOGIC (ĐÃ FIX) ---
async function handleSave() {
  if (!validateForm()) return;
  isLoading.value = true;
  
  let productIdForVariant = null; // ID dùng để link biến thể
  let productDbId = formData.id; // ID của sản phẩm để gọi API update

  const productPayload = {
    name: formData.name,
    description: formData.description,
    category_id: formData.category_id,
    status: formData.status,
    thumbnail_url: formData.existing_images[0]?.url || 'https://placehold.co/150x150?text=No+Img'
  };

  try {
    if (isEditMode.value) {
      // Update Product
      const oldProduct = products.value.find(p => p.id == productDbId);
      productIdForVariant = oldProduct ? oldProduct.product_id : productDbId;
      await apiService.patch(`/admin/products/${productDbId}`, productPayload);
    } else {
      // Create Product
      const newShortId = generateShortId();
      productPayload.product_id = newShortId;
      
      const res = await apiService.post('/admin/products', productPayload);
      
      // Lấy ID trả về từ server để dùng cho variants
      productDbId = res.data.id;
      // Ưu tiên dùng product_id mà server đã lưu, hoặc fallback sang newShortId
      productIdForVariant = res.data.product_id || newShortId;
    }

    // Create/Update Variants
    for (const variant of formData.variants) {
      const variantPayload = {
        product_id: productIdForVariant,
        original_price: variant.original_price,
        price: variant.price,
        stock: variant.stock,
        attributes: variant.attributes
      };

      // --- LOGIC FIX LỖI 404 Ở ĐÂY ---
      // Kiểm tra variant_id:
      // - Nếu là số (VD: 75) -> ID thật từ DB -> Gọi PATCH
      // - Nếu là chuỗi (VD: "6mrl93") -> ID tạm Frontend sinh -> Gọi POST
      
      const isNewVariant = isNaN(Number(variant.variant_id));

      if (isNewVariant) {
         // Tạo mới -> Gọi POST
         await apiService.post('/admin/variants', variantPayload);
      } else {
         // Cập nhật -> Gọi PATCH với ID thật
         await apiService.patch(`/admin/variants/${variant.variant_id}`, variantPayload);
      }
    }

    Swal.fire('Thành công', `Đã ${isEditMode.value ? 'cập nhật' : 'tạo mới'} sản phẩm!`, 'success');
    modalInstance.value.hide();
    fetchProducts();
  } catch (e) {
    console.error("Lỗi lưu:", e);
    Swal.fire('Lỗi', 'Có lỗi xảy ra khi lưu dữ liệu.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function toggleStatus(product) {
  const newStatus = product.status === 'active' ? 'inactive' : 'active';
  if ((await Swal.fire({ title: 'Xác nhận', text: `Đổi trạng thái sang ${newStatus}?`, showCancelButton: true })).isConfirmed) {
    try {
      await apiService.patch(`/admin/products/${product.id}`, { status: newStatus });
      product.status = newStatus;
      Swal.fire('OK', 'Đã cập nhật', 'success');
    } catch (e) { Swal.fire('Lỗi', 'Không thể cập nhật', 'error'); }
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
  <!-- Template HTML giữ nguyên cấu trúc cũ, chỉ đảm bảo bindings đúng -->
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6"><h3 class="mb-0">Quản lý Sản phẩm</h3></div>
        <div class="col-sm-6"><ol class="breadcrumb float-sm-end"><li class="breadcrumb-item active">Sản phẩm</li></ol></div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card mb-4">
        <div class="card-header">
          <div class="row align-items-center">
            <div class="col-md-5"><input type="text" class="form-control" placeholder="Tìm kiếm..." v-model="searchQuery"></div>
            <div class="col-md-4">
               <select class="form-select" v-model="sortCriteria">
                  <option value="product_id-desc">Mới nhất</option>
                  <option value="price-asc">Giá tăng dần</option>
               </select>
            </div>
            <div class="col-md-3 text-end">
               <button class="btn btn-primary" @click="openCreateModal"><i class="bi bi-plus-lg"></i> Thêm</button>
            </div>
          </div>
        </div>
        <div class="card-body p-0">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr><th>ID</th><th>Ảnh</th><th>Tên</th><th>Danh mục</th><th>Giá</th><th>Kho</th><th>Trạng thái</th><th>Thao tác</th></tr>
            </thead>
            <tbody>
              <tr v-if="isLoading"><td colspan="8" class="text-center p-4"><div class="spinner-border text-primary"></div></td></tr>
              <tr v-else v-for="p in paginatedProducts" :key="p.id">
                <td>{{ p.product_id }}</td>
                <td><img :src="p.thumbnail_url" class="img-thumbnail" style="width:50px;height:50px;object-fit:cover"></td>
                <td>{{ p.name }}</td>
                <td>{{ p.category?.name }}</td>
                <td>{{ getPriceRange(p.variants) }}</td>
                <td>{{ calculateTotalStock(p.variants) }}</td>
                <td><span :class="['badge', p.status==='active'?'bg-success':'bg-secondary']">{{ p.status }}</span></td>
                <td>
                  <button class="btn btn-sm btn-outline-info me-1" @click="openViewModal(p)"><i class="bi bi-eye"></i></button>
                  <button class="btn btn-sm btn-outline-primary me-1" @click="openEditModal(p)"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-danger" @click="handleDelete(p)"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card-footer" v-if="totalPages > 1">
           <ul class="pagination pagination-sm float-end m-0">
              <li class="page-item" :class="{disabled: currentPage===1}"><button class="page-link" @click="goToPage(currentPage-1)">&laquo;</button></li>
              <li class="page-item disabled"><span class="page-link">{{currentPage}} / {{totalPages}}</span></li>
              <li class="page-item" :class="{disabled: currentPage===totalPages}"><button class="page-link" @click="goToPage(currentPage+1)">&raquo;</button></li>
           </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Product -->
  <div class="modal fade" id="productModal" ref="modalRef" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{isEditMode?'Sửa':'Thêm'}} Sản phẩm</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row">
             <div class="col-md-7">
                <div class="mb-3"><label class="form-label">Tên SP</label><input type="text" class="form-control" v-model="formData.name" :class="{'is-invalid': errors.name}"><div class="invalid-feedback">{{errors.name}}</div></div>
                <div class="mb-3"><label class="form-label">Mô tả</label><textarea class="form-control" rows="3" v-model="formData.description"></textarea></div>
             </div>
             <div class="col-md-5">
                <div class="mb-3">
                   <label class="form-label">Danh mục</label>
                   <select class="form-select" v-model="formData.category_id" :class="{'is-invalid': errors.category_id}">
                      <option :value="null">-- Chọn --</option>
                      <option v-for="c in categories" :key="c.id" :value="c.id">{{c.name}}</option>
                   </select>
                   <div class="invalid-feedback">{{errors.category_id}}</div>
                </div>
                <div class="mb-3">
                   <label class="form-label">Trạng thái</label>
                   <select class="form-select" v-model="formData.status">
                      <option value="active">Hiện</option><option value="inactive">Ẩn</option>
                   </select>
                </div>
             </div>
          </div>
          <hr>
          <h5>Biến thể</h5>
          <div class="card bg-light p-3 mb-3">
             <div class="d-flex gap-2">
                <select class="form-select w-auto" v-model="selectedAttributeId">
                   <option value="">-- Chọn thuộc tính --</option>
                   <option v-for="a in attributesList" :key="a.id" :value="a.id">{{a.name}}</option>
                </select>
                <button class="btn btn-primary" @click="addSelectedAttributeToForm">Thêm</button>
                <button class="btn btn-outline-success ms-auto" @click="openCreateAttrModal">Tạo thuộc tính mới</button>
             </div>
             <div class="mt-2">
                <span v-for="attr in formData.attribute_definitions" :key="attr.id" class="badge bg-secondary me-1">
                   {{attr.name}} <i class="bi bi-x ms-1 cursor-pointer" @click="removeAttributeDefinition(attr)"></i>
                </span>
             </div>
          </div>
          <div class="table-responsive">
             <table class="table table-bordered table-sm text-center align-middle">
                <thead><tr><th v-for="d in formData.attribute_definitions" :key="d.id">{{d.name}}</th><th>Giá gốc</th><th>Giá bán</th><th>Kho</th><th>Xóa</th></tr></thead>
                <tbody>
                   <tr v-for="(v,i) in formData.variants" :key="i">
                      <td v-for="d in formData.attribute_definitions" :key="d.id">
                         <input type="text" class="form-control form-control-sm" v-model="v.attributes[d.name]">
                      </td>
                      <td><input type="number" class="form-control form-control-sm" v-model="v.original_price"></td>
                      <td><input type="number" class="form-control form-control-sm" v-model="v.price"></td>
                      <td><input type="number" class="form-control form-control-sm" v-model="v.stock"></td>
                      <td><button class="btn btn-sm text-danger" @click="removeVariantRow(i)"><i class="bi bi-trash"></i></button></td>
                   </tr>
                </tbody>
             </table>
             <button class="btn btn-sm btn-success" @click="addVariantRow">+ Thêm dòng</button>
             <div class="text-danger small mt-1" v-if="errors.variants">{{errors.variants}}</div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button><button class="btn btn-primary" @click="handleSave">Lưu</button></div>
      </div>
    </div>
  </div>

  <!-- Modal Quick Attr -->
  <div class="modal fade" id="attrModal" ref="attrModalRef" tabindex="-1" data-bs-backdrop="static">
     <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
           <div class="modal-header bg-success text-white"><h6 class="modal-title">Tạo thuộc tính</h6><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
           <div class="modal-body"><input type="text" class="form-control" v-model="newQuickAttrName" placeholder="Tên thuộc tính..."></div>
           <div class="modal-footer p-1"><button class="btn btn-sm btn-success w-100" @click="handleCreateQuickAttribute">Lưu</button></div>
        </div>
     </div>
  </div>
  
  <!-- Modal View -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-body"><h5>{{viewingProduct.name}}</h5><pre>{{viewingProduct}}</pre></div></div></div></div>
</template>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>