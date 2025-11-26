<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import apiService from '../../../apiService';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';

// --- CẤU HÌNH URL BACKEND ---
// Lấy từ .env (VITE_API_BASE_URL). Nếu có đuôi '/api' thì cắt bỏ để lấy root domain phục vụ load ảnh
const rawApiUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';
const API_BASE_URL = rawApiUrl.replace(/\/api\/?$/, '');

// --- STATE QUẢN LÝ ---
const products = ref([]);
const categories = ref([]);
const attributesList = ref([]);
const isLoading = ref(true);
const isSaving = ref(false); // State cho nút Lưu chính
const isEditMode = ref(false);

// --- STATE SPINNER & XỬ LÝ MỚI ---
const isCreatingAttr = ref(false); // State loading cho tạo thuộc tính nhanh
const deletingImageIds = ref([]);  // State mảng chứa ID các ảnh đang xóa
const processingStatusIds = ref([]); // State mảng chứa ID sản phẩm đang đổi trạng thái (Toggle Switch)

// State Xóa nhiều ảnh (Bulk Delete)
const selectedImageIds = ref([]);
const isBulkDeleting = ref(false);

// State Modal
const modalInstance = ref(null);
const modalRef = ref(null);
const attrModalInstance = ref(null);
const attrModalRef = ref(null);

// State Form phụ 
const newQuickAttrName = ref('');
const selectedAttributeId = ref('');
// Đã xóa defaultAttributeValue theo yêu cầu

// --- STATE UPLOAD ẢNH ---
const thumbnailFile = ref(null);
const thumbnailPreview = ref(null);
const galleryFiles = ref([]);
const galleryPreviews = ref([]);

// State Modal Xem
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const viewingProduct = ref({
  name: '',
  description: '',
  categoryName: '',
  status: '',
  thumbnail_url: '',
  images: [],
  variants: [],
  attributeNames: [],
  priceRange: '',
  totalStock: 0
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
});

const errors = reactive({
  name: '',
  category_id: '',
  variants: '',
  attributes: ''
});

function generateShortId() {
  return Math.random().toString(36).substring(2, 8);
}

// --- HÀM XỬ LÝ URL ẢNH ---
function getImageUrl(path) {
  if (!path) return 'https://placehold.co/150x150?text=No+Img';
  if (path.startsWith('blob:') || path.startsWith('http')) {
    return path;
  }
  const baseUrl = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL;
  const cleanPath = path.startsWith('/') ? path : '/' + path;
  return `${baseUrl}${cleanPath}`;
}

// --- HÀM HỖ TRỢ: CHUYỂN ĐỔI DỮ LIỆU THUỘC TÍNH ---
function flattenVariantAttributes(variant) {
  const attrs = {};
  if (Array.isArray(variant.attribute_values)) {
    variant.attribute_values.forEach(av => {
      if (av.attribute && av.attribute.name) {
        attrs[av.attribute.name] = av.value;
      }
    });
  } else if (variant.attributes) {
    return variant.attributes;
  }
  return attrs;
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
      case 'name': valA = a.name.toLowerCase(); valB = b.name.toLowerCase(); break;
      case 'price': valA = getMinPrice(a.variants); valB = getMinPrice(b.variants); break;
      case 'created_at': valA = new Date(a.created_at); valB = new Date(b.created_at); break;
      case 'product_id': default: valA = a.product_id; valB = b.product_id; break;
    }
    return order === 'asc' ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1);
  });

  return sorted;
});

const totalPages = computed(() => Math.ceil(filteredProducts.value.length / itemsPerPage.value));
const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return filteredProducts.value.slice(start, start + itemsPerPage.value);
});

// Computed cho Select All
const isAllImagesSelected = computed({
  get() {
    return formData.existing_images.length > 0 && selectedImageIds.value.length === formData.existing_images.length;
  },
  set(val) {
    if (val) {
      selectedImageIds.value = formData.existing_images.map(img => img.id);
    } else {
      selectedImageIds.value = [];
    }
  }
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
  } catch (error) { console.error("Lỗi tải attributes:", error); }
}

// --- UPDATED: HÀM TẠO THUỘC TÍNH NHANH (CÓ SPINNER) ---
async function handleCreateQuickAttribute() {
  if (!newQuickAttrName.value.trim()) { Swal.fire('Lỗi', 'Vui lòng nhập tên thuộc tính', 'warning'); return; }

  isCreatingAttr.value = true; // Bật spinner
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
  } finally {
    isCreatingAttr.value = false; // Tắt spinner
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
      const productVariants = allVariants.filter(v => v.product_id == p.product_id || p.id == v.product_id);
      const productImages = allImages.filter(img => img.product_id == p.product_id || p.id == img.product_id);

      let thumbPath = p.thumbnail_url;
      if (!thumbPath && productImages.length > 0) {
        thumbPath = productImages[0].image_url;
      }

      return {
        sold_count: 0, favorite_count: 0, review_count: 0, average_rating: 0.0,
        status: 'active', created_at: new Date().toISOString(),
        ...p,
        product_id: p.product_id || p.id,
        category: category || null,
        variants: productVariants,
        images: productImages,
        thumbnail_url: getImageUrl(thumbPath)
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
  } catch (error) { console.error("Lỗi tải danh mục:", error); }
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

// --- XỬ LÝ ẢNH ---
function handleThumbnailChange(event) {
  const file = event.target.files[0];
  if (file) {
    thumbnailFile.value = file;
    thumbnailPreview.value = URL.createObjectURL(file);
  }
}

function handleGalleryChange(event) {
  const files = Array.from(event.target.files);
  files.forEach(file => {
    galleryFiles.value.push(file);
    galleryPreviews.value.push(URL.createObjectURL(file));
  });
}

function removeNewGalleryImage(index) {
  URL.revokeObjectURL(galleryPreviews.value[index]);
  galleryPreviews.value.splice(index, 1);
  galleryFiles.value.splice(index, 1);
}

// --- UPDATED: XÓA ẢNH VỚI SWEETALERT2 + SPINNER ---
async function deleteExistingImage(imgId, index) {
  if (deletingImageIds.value.includes(imgId)) return;

  const result = await Swal.fire({
    title: 'Xóa ảnh này?',
    text: "Hành động này sẽ xóa ảnh vĩnh viễn!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Xóa',
    cancelButtonText: 'Hủy'
  });

  if (!result.isConfirmed) return;

  deletingImageIds.value.push(imgId);
  try {
    await apiService.delete(`/admin/imageProducts/${imgId}`);
    formData.existing_images.splice(index, 1);

    // Nếu ảnh này đang được chọn trong bulk select thì bỏ chọn nó
    selectedImageIds.value = selectedImageIds.value.filter(id => id !== imgId);

    Swal.fire({
      icon: 'success',
      title: 'Đã xóa ảnh',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
  } catch (e) {
    console.error(e);
    Swal.fire('Lỗi', 'Không thể xóa ảnh', 'error');
  } finally {
    deletingImageIds.value = deletingImageIds.value.filter(id => id !== imgId);
  }
}

// --- MỚI: XỬ LÝ XÓA NHIỀU ẢNH (BULK DELETE) ---
async function handleBulkDeleteImages() {
  if (selectedImageIds.value.length === 0) return;

  const result = await Swal.fire({
    title: 'Xóa nhiều ảnh?',
    text: `Bạn có chắc muốn xóa ${selectedImageIds.value.length} ảnh đã chọn?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Xóa ngay',
    cancelButtonText: 'Hủy'
  });

  if (!result.isConfirmed) return;

  isBulkDeleting.value = true;
  try {
    // Gọi API xóa nhiều: POST /admin/imageProducts/bulk-delete với body { ids: [...] }
    await apiService.post('/admin/imageProducts/bulk-delete', { ids: selectedImageIds.value });

    // Cập nhật UI: Lọc bỏ các ảnh đã xóa khỏi danh sách hiển thị
    formData.existing_images = formData.existing_images.filter(img => !selectedImageIds.value.includes(img.id));

    selectedImageIds.value = []; // Reset danh sách chọn

    Swal.fire({
      icon: 'success',
      title: 'Đã xóa các ảnh đã chọn',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000
    });
  } catch (e) {
    console.error(e);
    Swal.fire('Lỗi', 'Không thể xóa các ảnh đã chọn', 'error');
  } finally {
    isBulkDeleting.value = false;
  }
}

// --- MỚI: XỬ LÝ TOGGLE SWITCH TRẠNG THÁI (Giao diện như hình) ---
async function handleQuickStatusToggle(product) {
  // 1. Chặn Double Click: Nếu sản phẩm này đang xử lý thì return
  if (processingStatusIds.value.includes(product.id)) return;

  const newStatus = product.status === 'active' ? 'inactive' : 'active';
  const actionText = newStatus === 'active' ? 'Hiện (Active)' : 'Ẩn (Inactive)';
  const confirmBtnColor = newStatus === 'active' ? '#198754' : '#6c757d';

  // 2. SweetAlert2 xác nhận
  const result = await Swal.fire({
    title: `Xác nhận ${actionText}?`,
    text: `Bạn có muốn thay đổi trạng thái sản phẩm "${product.name}"?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: confirmBtnColor,
    confirmButtonText: 'Đồng ý',
    cancelButtonText: 'Hủy'
  });

  if (!result.isConfirmed) {
    return;
  }

  // 3. Bắt đầu Spinner: Thêm ID vào mảng xử lý
  processingStatusIds.value.push(product.id);

  try {
    const payload = new FormData();
    payload.append('_method', 'PATCH');
    payload.append('status', newStatus);

    await apiService.post(`/admin/products/${product.id}`, payload);

    product.status = newStatus;

    Swal.fire({
      icon: 'success',
      title: 'Cập nhật thành công!',
      text: `Sản phẩm đã chuyển sang trạng thái: ${actionText}`,
      timer: 2000,
      showConfirmButton: false
    });

  } catch (e) {
    console.error("Lỗi đổi trạng thái:", e);
    Swal.fire('Lỗi', 'Không thể cập nhật trạng thái. Vui lòng thử lại.', 'error');
  } finally {
    processingStatusIds.value = processingStatusIds.value.filter(id => id !== product.id);
  }
}

// --- FORM LOGIC ---
function addSelectedAttributeToForm() {
  if (!selectedAttributeId.value) { errors.attributes = 'Vui lòng chọn một thuộc tính.'; return; }

  const attrObj = attributesList.value.find(a => a.id == selectedAttributeId.value);
  if (!attrObj) return;

  const exists = formData.attribute_definitions.find(a => a.id == attrObj.id || a.name === attrObj.name);
  if (exists) { errors.attributes = `Thuộc tính "${attrObj.name}" đã có.`; return; }

  errors.attributes = '';
  formData.attribute_definitions.push({ id: attrObj.id, name: attrObj.name });

  formData.variants.forEach(v => {
    if (!v.attributes) v.attributes = {};
    // Gán giá trị rỗng cho thuộc tính mới
    v.attributes[attrObj.name] = '';
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
    variant_id: generateShortId(),
    original_price: 0, price: 0, stock: 0,
    attributes: newAttributes
  }));
}
function removeVariantRow(index) { formData.variants.splice(index, 1); }

function resetForm() {
  formData.id = null;
  formData.name = ''; formData.description = ''; formData.category_id = null; formData.status = 'inactive';
  formData.attribute_definitions = reactive([]); formData.variants = reactive([]);
  formData.existing_images = reactive([]);

  thumbnailFile.value = null;
  thumbnailPreview.value = null;
  galleryFiles.value = [];
  galleryPreviews.value.forEach(url => URL.revokeObjectURL(url));
  galleryPreviews.value = [];

  selectedAttributeId.value = '';
  selectedImageIds.value = []; // Reset selected images
  Object.keys(errors).forEach(k => errors[k] = '');
}

function openCreateModal() { resetForm(); isEditMode.value = false; modalInstance.value.show(); }

// --- MODAL CẤU HÌNH BIẾN THỂ & SỬA ---
async function openVariantConfigModal(productItem) {
  resetForm();
  isEditMode.value = true;

  modalInstance.value.show();

  try {
    const res = await apiService.get(`/admin/products/${productItem.id}`);
    const product = res.data.data || res.data;

    formData.id = product.id;
    formData.name = product.name;
    formData.description = product.description;
    formData.category_id = product.category_id || product.category?.id;
    formData.status = product.status;
    thumbnailPreview.value = getImageUrl(product.thumbnail_url);

    formData.existing_images = reactive((product.images || []).map(img => ({
      id: img.id,
      url: getImageUrl(img.image_url)
    })));

    const variants = product.variants || [];

    if (variants.length > 0) {
      const allAttributeNames = new Set();
      variants.forEach(v => {
        if (v.attributes && typeof v.attributes === 'object') {
          Object.keys(v.attributes).forEach(key => allAttributeNames.add(key));
        }
      });

      formData.attribute_definitions = reactive(
        Array.from(allAttributeNames).map(name => {
          const found = attributesList.value.find(a => a.name === name);
          return {
            id: found ? found.id : generateShortId(),
            name: name
          };
        })
      );

      formData.variants = reactive(variants.map(v => {
        const variantAttributes = reactive({});
        formData.attribute_definitions.forEach(def => {
          variantAttributes[def.name] = '';
        });

        if (v.attributes && typeof v.attributes === 'object') {
          Object.entries(v.attributes).forEach(([key, value]) => {
            variantAttributes[key] = value;
          });
        }

        return {
          variant_id: v.id,
          original_price: v.original_price || 0,
          price: v.price || 0,
          stock: v.stock || 0,
          attributes: variantAttributes
        };
      }));
    } else {
      addVariantRow();
    }

  } catch (e) {
    console.error("❌ Lỗi tải chi tiết:", e);
    Swal.fire('Lỗi', 'Không thể tải chi tiết sản phẩm từ server', 'error');
  }
}

function openCreateAttrModal() { newQuickAttrName.value = ''; errors.attributes = ''; attrModalInstance.value.show(); }

// --- HÀM XEM CHI TIẾT ---
async function openViewModal(productItem) {
  try {
    const res = await apiService.get(`/admin/products/${productItem.id}`);
    const product = res.data.data || res.data;

    const processedVariants = (product.variants || []).map(v => ({
      ...v,
      attributes: flattenVariantAttributes(v)
    }));

    const keys = new Set();
    processedVariants.forEach(v => Object.keys(v.attributes).forEach(k => keys.add(k)));

    const fullUrlImages = (product.images || []).map(img => getImageUrl(img.image_url));

    viewingProduct.value = {
      ...product,
      categoryName: product.category?.name || 'N/A',
      attributeNames: Array.from(keys),
      images: fullUrlImages,
      thumbnail_url: getImageUrl(product.thumbnail_url),
      priceRange: getPriceRange(product.variants),
      totalStock: calculateTotalStock(product.variants),
      variants: processedVariants
    };

    viewModalInstance.value.show();
  } catch (e) {
    console.error(e);
    Swal.fire('Lỗi', 'Không thể tải chi tiết sản phẩm', 'error');
  }
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
        if (v.price < 0 || v.stock < 0) { errors.variants = 'Giá/Kho không hợp lệ'; isValid = false; break; }
        for (const def of formData.attribute_definitions) {
          if (!v.attributes[def.name]?.trim()) { errors.variants = `Điền giá trị "${def.name}"`; isValid = false; break; }
        }
        if (!isValid) break;
      }
    }
  }
  return isValid;
}

// --- SAVE LOGIC ---
async function handleSave() {
  if (!validateForm()) return;
  isSaving.value = true; // Đã có sẵn spinner cho nút này

  try {
    let savedProductId = formData.id;

    const productData = new FormData();
    productData.append('name', formData.name);
    productData.append('description', formData.description || '');
    productData.append('category_id', formData.category_id);
    productData.append('status', isEditMode.value ? formData.status : 'inactive');

    if (thumbnailFile.value) {
      productData.append('thumbnail', thumbnailFile.value);
    }

    if (!isEditMode.value) {
      const res = await apiService.post('/admin/products', productData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã tạo sản phẩm nháp. Hãy thêm ảnh gallery và biến thể!', 'success');
      savedProductId = res.data.id || res.data.product_id;
    } else {
      productData.append('_method', 'PATCH');
      await apiService.post(`/admin/products/${savedProductId}`, productData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
    }

    if (savedProductId && galleryFiles.value.length > 0) {
      const uploadPromises = galleryFiles.value.map(file => {
        const imgData = new FormData();
        imgData.append('product_id', savedProductId);
        imgData.append('image', file);
        return apiService.post('/admin/imageProducts', imgData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
      });
      await Promise.all(uploadPromises);
    }

    if (isEditMode.value) {
      for (const variant of formData.variants) {
        const variantPayload = {
          product_id: savedProductId,
          original_price: variant.original_price,
          price: variant.price,
          stock: variant.stock
        };
        let savedVariantId = variant.variant_id;
        const isNewVariant = isNaN(Number(savedVariantId));

        if (isNewVariant) {
          const res = await apiService.post('/admin/variants', variantPayload);
          savedVariantId = res.data.id;
        } else {
          await apiService.patch(`/admin/variants/${savedVariantId}`, variantPayload);
        }

        const attributesPayload = { ...variant.attributes };
        if (savedVariantId && Object.keys(attributesPayload).length > 0) {
          await apiService.post(`/admin/variants/${savedVariantId}/attributes`, {
            attributes: attributesPayload
          });
        }
      }
      Swal.fire('Thành công', 'Đã cập nhật đầy đủ thông tin!', 'success');
    }

    modalInstance.value.hide();
    fetchProducts();
  } catch (e) {
    console.error("Lỗi lưu:", e);
    const msg = e.response?.data?.message || 'Có lỗi xảy ra khi lưu dữ liệu.';
    Swal.fire('Lỗi', msg, 'error');
  } finally {
    isSaving.value = false;
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
            <div class="col-md-3 text-end"><button class="btn btn-primary" @click="openCreateModal"><i
                  class="bi bi-plus-lg"></i> Thêm SP Mới</button></div>
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
                <th class="text-center">Thao tác</th>
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
                <td>
                  <img :src="p.thumbnail_url" class="img-thumbnail" style="width:50px;height:50px;object-fit:cover"
                    onerror="this.src='https://placehold.co/50?text=Err'">
                </td>
                <td>{{ p.name }}</td>
                <td>{{ p.category?.name }}</td>
                <td>{{ getPriceRange(p.variants) }}</td>
                <td>{{ calculateTotalStock(p.variants) }}</td>

                <!-- CỘT TRẠNG THÁI: Chỉ hiển thị Text/Badge -->
                <td>
                  <span :class="['badge', p.status === 'active' ? 'bg-success' : 'bg-secondary']">
                    {{ p.status === 'active' ? 'Đang bán' : 'Ẩn/Nháp' }}
                  </span>
                </td>

                <!-- CỘT THAO TÁC -->
                <td class="text-center">
                  <div class="d-flex justify-content-center align-items-center">
                    <!-- Toggle Switch -->
                    <div class="form-check form-switch me-3 mb-0" style="min-height: 1.5em;" title="Bật/Tắt trạng thái">
                      <div v-if="processingStatusIds.includes(p.id)"
                        class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                      <input v-else class="form-check-input cursor-pointer shadow-none" type="checkbox" role="switch"
                        :checked="p.status === 'active'" @click.prevent="handleQuickStatusToggle(p)"
                        style="transform: scale(1.3);">
                    </div>

                    <!-- Action Buttons Group -->
                    <div class="btn-group btn-group-sm">
                      <button class="btn btn-outline-secondary" @click="openViewModal(p)" title="Xem">
                        <i class="bi bi-eye"></i>
                      </button>
                      <button class="btn btn-outline-primary" @click="openVariantConfigModal(p)" title="Sửa">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-outline-danger" @click="handleDelete(p)" title="Xóa">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </div>
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
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Tên sản phẩm</label>
                <input type="text" class="form-control" v-model="formData.name" :class="{ 'is-invalid': errors.name }">
                <div class="invalid-feedback">{{ errors.name }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Danh mục</label>
                <select class="form-select" v-model="formData.category_id"
                  :class="{ 'is-invalid': errors.category_id }">
                  <option :value="null">-- Chọn --</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <div class="invalid-feedback">{{ errors.category_id }}</div>
              </div>

              <div class="mb-3 p-3 border rounded bg-light">
                <label class="form-label fw-bold">Ảnh đại diện (Thumbnail)</label>
                <input type="file" class="form-control" accept="image/*" @change="handleThumbnailChange">
                <div v-if="thumbnailPreview" class="mt-2 text-center">
                  <img :src="thumbnailPreview" class="img-thumbnail" style="height: 290px;">
                </div>
              </div>



            </div>
            <div class="col-md-6">

              <div class="mb-3">
                <label class="form-label">Mô tả</label>
                <textarea class="form-control" rows="3" v-model="formData.description"></textarea>
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
                  trạng thái <b>Ẩn</b>. Hãy lưu lại rồi thêm ảnh Gallery và biến thể sau.</div>
              </div>
              <div class="mb-3 p-3 border rounded bg-light" v-if="isEditMode">
                <label class="form-label fw-bold">Thư viện ảnh chi tiết (Gallery)</label>
                <input type="file" class="form-control" multiple accept="image/*" @change="handleGalleryChange">

                <!-- HIỂN THỊ ẢNH & CHỨC NĂNG XÓA NHIỀU -->
                <div class="mt-3">
                  <!-- Toolbar: Select All + Bulk Delete -->
                  <div v-if="formData.existing_images.length > 0"
                    class="d-flex align-items-center justify-content-between mb-2">
                    <div class="form-check">
                      <input class="form-check-input cursor-pointer" type="checkbox" id="selectAllImages"
                        v-model="isAllImagesSelected">
                      <label class="form-check-label user-select-none cursor-pointer" for="selectAllImages">Chọn tất cả
                        ({{ formData.existing_images.length }})</label>
                    </div>
                    <button v-if="selectedImageIds.length > 0" class="btn btn-sm btn-danger"
                      @click="handleBulkDeleteImages" :disabled="isBulkDeleting">
                      <span v-if="isBulkDeleting" class="spinner-border spinner-border-sm me-1"></span>
                      <span v-else><i class="bi bi-trash"></i></span> Xóa đã chọn ({{ selectedImageIds.length }})
                    </button>
                  </div>

                  <div class="d-flex flex-wrap gap-2">
                    <!-- Loop existing images with Checkbox -->
                    <div v-for="(img, idx) in formData.existing_images" :key="img.id" class="position-relative">
                      <!-- Ảnh có border khi được chọn -->
                      <img :src="img.url" class="img-thumbnail"
                        :class="{ 'border-primary shadow-sm': selectedImageIds.includes(img.id) }"
                        style="width: 90px; height: 90px; object-fit: cover; cursor: pointer;"
                        @click="selectedImageIds.includes(img.id) ? selectedImageIds = selectedImageIds.filter(id => id !== img.id) : selectedImageIds.push(img.id)">

                      <!-- Checkbox overlay -->
                      <div class="position-absolute top-0 start-0 m-1">
                        <input type="checkbox" class="form-check-input" :value="img.id" v-model="selectedImageIds"
                          @click.stop>
                      </div>

                      <!-- Nút xóa lẻ (giữ lại nếu cần xóa nhanh 1 ảnh) -->
                      <button
                        class="position-absolute top-0 start-100 translate-middle btn btn-sm btn-danger rounded-circle p-0"
                        style="width: 20px; height: 20px; line-height: 1;" :disabled="deletingImageIds.includes(img.id)"
                        @click.stop="deleteExistingImage(img.id, idx)">
                        <span v-if="deletingImageIds.includes(img.id)" class="spinner-border spinner-border-sm"
                          style="width: 12px; height: 12px; border-width: 1px;"></span>
                        <span v-else>&times;</span>
                      </button>
                    </div>

                    <!-- New images preview (ko có checkbox xóa nhiều vì chưa upload) -->
                    <div v-for="(url, idx) in galleryPreviews" :key="'new-' + idx" class="position-relative">
                      <img :src="url" class="img-thumbnail border-success"
                        style="width: 90px; height: 90px; object-fit: cover;">
                      <button
                        class="position-absolute top-0 start-100 translate-middle btn btn-sm btn-secondary rounded-circle p-0"
                        style="width: 20px; height: 20px; line-height: 1;"
                        @click="removeNewGalleryImage(idx)">&times;</button>
                    </div>
                  </div>
                </div>

                <div class="form-text small" v-if="galleryPreviews.length > 0">
                  Các ảnh viền xanh là ảnh mới sẽ được upload khi bạn bấm Lưu.
                </div>
              </div>
            </div>
          </div>
          <hr>

          <div v-if="isEditMode">
            <h5>Cấu hình Biến thể</h5>
            <div class="card bg-light p-3 mb-3">
              <div class="row g-2 align-items-center">
                <div class="col-auto"><select class="form-select" v-model="selectedAttributeId">
                    <option value="">-- Chọn thuộc tính --</option>
                    <option v-for="a in attributesList" :key="a.id" :value="a.id">{{ a.name }}</option>
                  </select></div>
                <!-- ĐÃ BỎ Ô INPUT NHẬP GIÁ TRỊ MẶC ĐỊNH TẠI ĐÂY -->
                <div class="col-auto"><button class="btn btn-primary" @click="addSelectedAttributeToForm">Thêm
                    cột</button></div>
                <div class="col-auto ms-auto"><button class="btn btn-outline-success" @click="openCreateAttrModal">Tạo
                    thuộc tính mới</button></div>
              </div>
              <div class="mt-2 text-danger small" v-if="errors.attributes">{{ errors.attributes }}</div>
              <div class="mt-2" v-if="formData.attribute_definitions.length > 0">
                <span class="text-muted me-2">Các cột đã chọn:</span>
                <span v-for="attr in formData.attribute_definitions" :key="attr.id"
                  class="badge bg-white text-dark border me-1">{{ attr.name }} <i
                    class="bi bi-x text-danger cursor-pointer ms-1" @click="removeAttributeDefinition(attr)"></i></span>
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
                    <td v-for="d in formData.attribute_definitions" :key="d.id"><input type="text"
                        class="form-control form-control-sm" v-model="v.attributes[d.name]"></td>
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
            <p class="text-muted small">Sau khi lưu, bạn có thể thêm ảnh gallery, màu sắc, kích thước và giá bán.</p>
          </div>
        </div>
        <!-- FOOTER MODAL: Nút Lưu có SPINNER và DISABLED -->
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal" :disabled="isSaving">Đóng</button>
          <button class="btn btn-primary" @click="handleSave" :disabled="isSaving">
            <span v-if="isSaving" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
            {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
          </button>
        </div>
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
        <div class="modal-footer p-1">
          <!-- UPDATED: BUTTON LƯU THUỘC TÍNH CÓ SPINNER -->
          <button class="btn btn-sm btn-success w-100" @click="handleCreateQuickAttribute" :disabled="isCreatingAttr">
            <span v-if="isCreatingAttr" class="spinner-border spinner-border-sm me-1"></span>
            {{ isCreatingAttr ? 'Đang tạo...' : 'Lưu' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal View (HIỂN THỊ CHI TIẾT SẢN PHẨM ĐẦY ĐỦ) -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Chi tiết sản phẩm</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Phần Header & Ảnh -->
          <div class="row">
            <div class="col-md-4 text-center">
              <img :src="viewingProduct.thumbnail_url" class="img-thumbnail shadow-sm"
                style="width: 100%; max-height: 300px; object-fit: contain;">
            </div>
            <div class="col-md-8">
              <h4 class="mb-2">{{ viewingProduct.name }}</h4>
              <p class="text-muted mb-2"><span class="badge bg-info me-2">{{ viewingProduct.categoryName }}</span>
                <span :class="['badge', viewingProduct.status === 'active' ? 'bg-success' : 'bg-secondary']">{{
                  viewingProduct.status === 'active' ? 'Đang bán' : 'Ẩn/Nháp' }}</span>
              </p>

              <div class="mb-2"><strong>Giá bán:</strong> <span class="text-danger fw-bold fs-5">{{
                viewingProduct.priceRange }}</span></div>
              <div class="mb-2"><strong>Tổng kho:</strong> {{ viewingProduct.totalStock }} sản phẩm</div>

              <div class="p-2 bg-light rounded border mt-3">
                <strong>Mô tả:</strong>
                <p class="mb-0 small mt-1" style="white-space: pre-line;">{{ viewingProduct.description || 'Chưa có mô tả' }}</p>
              </div>
            </div>
          </div>

          <hr>

          <!-- Phần Gallery -->
          <div v-if="viewingProduct.images && viewingProduct.images.length > 0" class="mb-4">
            <h6 class="fw-bold">Thư viện ảnh ({{ viewingProduct.images.length }})</h6>
            <div class="d-flex flex-wrap gap-2">
              <img v-for="(imgUrl, idx) in viewingProduct.images" :key="idx" :src="imgUrl" class="img-thumbnail"
                style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;">
            </div>
          </div>

          <!-- Phần Variants -->
          <div v-if="viewingProduct.variants && viewingProduct.variants.length > 0">
            <h6 class="fw-bold">Danh sách biến thể</h6>
            <div class="table-responsive">
              <table class="table table-bordered table-sm text-center align-middle">
                <thead class="table-light">
                  <tr>
                    <th v-for="attr in viewingProduct.attributeNames" :key="attr">{{ attr }}</th>
                    <th>Giá gốc</th>
                    <th>Giá bán</th>
                    <th>Kho</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="v in viewingProduct.variants" :key="v.id">
                    <td v-for="attr in viewingProduct.attributeNames" :key="attr">{{ v.attributes[attr] }}</td>
                    <td>{{ formatCurrency(v.original_price) }}</td>
                    <td class="text-danger fw-bold">{{ formatCurrency(v.price) }}</td>
                    <td>{{ v.stock }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div v-else class="alert alert-warning py-2 small text-center">Sản phẩm này chưa có biến thể nào.</div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
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

.table td .btn-group {
  min-width: 95px;
}
</style>