<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import apiService from '../../../apiService';
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
const sortCriteria = ref('product_id-desc');  

// Dữ liệu cho form sản phẩm
const formData = reactive({
  id: null, // Sẽ lưu 'id' của json-server (ví dụ: "587a")
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

// --- HELPER FUNCTION: Tạo ID ngắn cho json-server ---
function generateShortId() {
  // Tạo chuỗi 4-6 ký tự ngẫu nhiên để mô phỏng ID ngắn của json-server  
  // và tránh lỗi định tuyến với UUID quá dài.
  return Math.random().toString(36).substring(2, 8);
}


// --- COMPUTED ---
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

    // Lấy giá trị để so sánh. Dùng product_id thay vì id.
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
        // Đảm bảo so sánh số hoặc chuỗi ID
        // Vẫn sắp xếp theo product_id (nghiệp vụ)
        valA = a.product_id;
        valB = b.product_id;
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

watch(searchQuery, () => {
  currentPage.value = 1;  
});

// THÊM WATCHER CHO SẮP XẾP
watch(sortCriteria, () => {
  currentPage.value = 1;  
});

onMounted(async () => {
  await fetchCategories();
  fetchProducts();         
  
  if (modalRef.value) {
    modalInstance.value = new Modal(modalRef.value, { backdrop: 'static' });
  }
  if (viewModalRef.value) {
    viewModalInstance.value = new Modal(viewModalRef.value);
  }
});


async function fetchProducts() {
  isLoading.value = true;
  try {
    const [productsRes, variantsRes, imagesRes] = await Promise.all([
      apiService.get('/products'),  
      apiService.get('/variants'),  
      apiService.get('/images')  
    ]);

    const rawProducts = productsRes.data;
    const allCategories = categories.value;
    const allVariants = variantsRes.data;
    const allImages = imagesRes.data;

    // BƯỚC 2: Nhúng (embed) thủ công Categories, Variants, Images
    products.value = rawProducts.map(p => {
      // 1. Nhúng Category (dùng category_id)
      const category = allCategories.find(c => c.category_id === p.category_id);
      
      // 2. Nhúng Variants (dùng product_id)
      // Dùng == để so sánh số/chuỗi (ví dụ product_id 1 == "1")
      const productVariants = allVariants.filter(v => v.product_id == p.product_id); 
      
      // 3. Nhúng Images (dùng product_id)
      const productImages = allImages.filter(img => img.product_id == p.product_id);
      
      // Khởi tạo các trường cần thiết nếu API không có
      const defaultData = {
        sold_count: 0,
        favorite_count: 0,
        review_count: 0,
        average_rating: 0.0,
        status: 'active',
        created_at: new Date().toISOString(),
      };

      // *** SỬA LOGIC ID ***
      // p.id LÀ ID THẬT CỦA JSON-SERVER (ví dụ: "587a", 1, "37c3")
      // p.product_id LÀ ID NGHIỆP VỤ (ví dụ: "e9fbc...", 1, "37c3")
      
      return {
        ...defaultData,
        ...p, // Giữ lại 'id' thật từ json-server (ví dụ: "587a")
        
        // id: p.product_id, // <-- *** XÓA DÒNG NÀY *** (Đây là nguyên nhân lỗi)

        // Đảm bảo product_id (nghiệp vụ) luôn tồn tại
        product_id: p.product_id || p.id, 
        
        category: category || null,
        variants: productVariants,  
        images: productImages,
        thumbnail_url: p.thumbnail_url || (productImages.length > 0 ? productImages[0].image_url : 'https://placehold.co/150x150?text=No+Img')
      };
    });
    
    // Sắp xếp lại danh sách sản phẩm sau khi nhúng (vì json-server chỉ sort trên product_id)
    const [key, order] = sortCriteria.value.split('-');
    if (key === 'product_id' && order === 'desc') {
       // Nếu là sắp xếp mặc định (product_id-desc), giữ nguyên thứ tự API trả về (thường là ID tăng dần, nhưng mình muốn mới nhất là lớn nhất)
       products.value.sort((a, b) => {
         // Chuyển ID sang chuỗi trước khi so sánh, sau đó ép về số nếu được
         // Sắp xếp theo product_id (nghiệp vụ)
         const idA = isNaN(Number(a.product_id)) ? a.product_id : Number(a.product_id);
         const idB = isNaN(Number(b.product_id)) ? b.product_id : Number(b.product_id);
         if (idA < idB) return 1;
         if (idA > idB) return -1;
         return 0;
       });
    }

  } catch (error) {
    console.error("Lỗi khi tải sản phẩm:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách sản phẩm. (Hãy đảm bảo json-server đang chạy)', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function fetchCategories() {
  try {
    // API lấy danh mục, json-server dùng category_id
    const response = await apiService.get(`/categories?status=active&_sort=order&_order=asc`);
    categories.value = response.data.map(c => ({
        ...c,
        id: c.category_id // Gán id = category_id để <select> hoạt động
    }));
  } catch (error) {
    console.error("Lỗi khi tải danh mục:", error);
  }
}

function formatCurrency(value) {
  if (value === undefined || value === null || isNaN(value)) return 'N/A';
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
}
function getFormattedDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('vi-VN');
}

// Helper để lấy giá thấp nhất (dùng cho sắp xếp)
function getMinPrice(variants) {
  if (!variants || variants.length === 0) {
    return 0;
  }
  const prices = variants.map(v => parseFloat(v.price)).filter(p => !isNaN(p));
  if (prices.length === 0) {
    return 0;
  }
  return Math.min(...prices);
}

// Tính tổng kho
function calculateTotalStock(variants) {
  if (!variants || variants.length === 0) return 0;
  return variants.reduce((acc, variant) => acc + (parseInt(variant.stock) || 0), 0);
}

// Lấy khoảng giá (dùng cho hiển thị)
function getPriceRange(variants) {
  if (!variants || variants.length === 0) {
    return 'N/A';
  }
  const prices = variants.map(v => parseFloat(v.price)).filter(p => !isNaN(p));
  if (prices.length === 0) {
    return 'N/A';
  }
  
  const minPrice = Math.min(...prices);
  const maxPrice = Math.max(...prices);

  if (minPrice === maxPrice) {
    return formatCurrency(minPrice);
  } else {
    // Trả về dạng "100.000 ₫ - 300.000 ₫"
    return `${formatCurrency(minPrice)} - ${formatCurrency(maxPrice)}`;
  }
}

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
  // Khởi tạo thuộc tính cho variants hiện có
  formData.variants.forEach(variant => {  
    if (!variant.attributes) variant.attributes = {};
    variant.attributes[name] = '';  
  });
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
    variant_id: crypto.randomUUID(), // Dùng variant_id
    price: 0,  
    stock: 0,  
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
  const removedImage = formData.existing_images.splice(index, 1);
  // Do json-server không hỗ trợ delete ảnh riêng, ta chỉ xóa khỏi form
  // if (removedImage[0]?.img_id) { // Dùng img_id theo db.json
  //   formData.images_to_delete.push(removedImage[0].img_id);
  // }
}

// --- CÁC HÀM CRUD MODAL ---

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

function openCreateModal() {
  resetForm();
  isEditMode.value = false;
  addVariantRow();  
  modalInstance.value.show();
}

function openEditModal(product) {
  resetForm();
  isEditMode.value = true;

  // *** SỬA: Dùng 'id' (ID của json-server) làm ID chính của form ***
  formData.id = product.id;  
  
  formData.name = product.name;
  formData.description = product.description;
  formData.category_id = product.category_id; // category_id từ product
  formData.status = product.status || 'active';

  // Lấy ảnh
  formData.existing_images = reactive(product.images ? product.images.map(img => ({  
    id: img.img_id, // Lấy img_id
    url: img.image_url || img.url  
  })) : []);

  // Lấy thuộc tính và biến thể
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
      allKeys.forEach(key => { variantAttributes[key] = v.attributes?.[key] || ''; });
      return reactive({  
        ...v,  
        variant_id: v.variant_id || crypto.randomUUID(), // Đảm bảo có variant_id
        attributes: variantAttributes  
      });
    })
  );
  if(formData.variants.length === 0) { addVariantRow(); }

  modalInstance.value.show();
}

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

function validateForm() {
  Object.keys(errors).forEach(key => errors[key] = '');
  let isValid = true;
  if (!formData.name.trim()) {
    errors.name = 'Vui lòng nhập tên sản phẩm.'; isValid = false;
  }
  if (!formData.category_id) {
    errors.category_id = 'Vui lòng chọn danh mục.'; isValid = false;
  }
  
  // Bỏ qua kiểm tra ảnh
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

// --- HÀM LƯU DỮ LIỆU ĐÃ SỬA ---
async function handleSave() {
  if (!validateForm()) {
    return;
  }

  isLoading.value = true;

  // 'dbId' LÀ ID CỦA JSON-SERVER (ví dụ: "587a", 1, "37c3")
  let dbId = formData.id; 
  const dbIdString = String(dbId);
  
  // 'businessProductId' LÀ ID NGHIỆP VỤ (ví dụ: "e9fbc...", 1, "37c3")
  // Khởi tạo là null, sẽ được gán
  let businessProductId = null; 
  
  // Chuẩn bị Product Data (chưa bao gồm ID)
  const productData = {
    name: formData.name,
    description: formData.description,
    category_id: formData.category_id,  
    status: formData.status,
    updated_at: new Date().toISOString(),
    thumbnail_url: formData.existing_images[0]?.url || 'https://placehold.co/150x150?text=No+Img',
    
    // Giữ các trường thống kê (Sẽ cập nhật lại khi là Edit)
    sold_count: 0,
    favorite_count: 0,
    review_count: 0,
    average_rating: 0.0,
  };

  try {
    if (isEditMode.value) {
      // 1. Lấy lại các trường thống kê cũ và ID
      // Tìm bằng 'dbId' (ID của json-server)
      const oldProduct = products.value.find(p => p.id == dbId);
      if(oldProduct) {
        productData.sold_count = oldProduct.sold_count;
        productData.favorite_count = oldProduct.favorite_count;
        productData.review_count = oldProduct.review_count;
        productData.average_rating = oldProduct.average_rating;

        // *** QUAN TRỌNG: Lấy ID nghiệp vụ và ID CSDL ***
        businessProductId = oldProduct.product_id; 
        productData.product_id = oldProduct.product_id; // Gửi lại ID nghiệp vụ
        productData.id = oldProduct.id; // Gửi lại ID CSDL
      } else {
         // Fallback (dù không nên xảy ra)
         businessProductId = dbId;
         productData.product_id = dbId;
         productData.id = dbId;
      }
      
      // 2. Cập nhật Product chính (Dùng PATCH theo ID CSDL)
      const apiEndpoint = `/products/${dbIdString}`;  
      console.log("DEBUG: API Endpoint for PATCH:", apiEndpoint);
      
      await apiService.patch(apiEndpoint, productData);  

    
    } else {
      // SỬA: Thay thế UUID dài bằng ID ngắn, đơn giản để json-server dễ xử lý.
      const newShortId = generateShortId();  
      
      // Gán cả ID CSDL và ID Nghiệp vụ
      businessProductId = newShortId;
      productData.product_id = newShortId;
      productData.id = newShortId; // Gửi 'id' để json-server nhận dạng
      productData.created_at = new Date().toISOString();

      // 1. Tạo Product mới
      const createRes = await apiService.post(`/products`, productData);
      
      // Cập nhật lại ID từ server (phòng trường hợp server tự tạo ID khác)
      dbId = createRes.data.id; 
      businessProductId = createRes.data.product_id;
    }
    
    // --- LƯU VARIANT VÀ IMAGE VÀO COLLECTION RIÊNG ---
    // *** SỬA: Dùng 'businessProductId' để liên kết ***
    
    for (const variant of formData.variants) {
      const variantPayload = {
        // Sử dụng ID nghiệp vụ (businessProductId) đã được xác định/cập nhật
        product_id: businessProductId,  
        price: variant.price,
        stock: variant.stock,
        attributes: variant.attributes
      };
      
      const variantIdString = String(variant.variant_id);

      // Kiểm tra nếu variant_id là số (ID cũ) hoặc không chứa '-' (ID cũ của json-server)
      // Đây là logic kiểm tra ID cũ vs ID mới (UUID)
      if (variantIdString && !variantIdString.includes('-') && variantIdString.length < 10) {  
        // Cập nhật variant cũ
        await apiService.patch(`/variants/${variantIdString}`, variantPayload);
      } else {
        // Tạo variant mới
        // Gán ID CSDL và ID nghiệp vụ mới cho variant
        const newVariantId = generateShortId();
        variantPayload.id = newVariantId;
        variantPayload.variant_id = newVariantId;
        await apiService.post(`/variants`, variantPayload);
      }
    }


    Swal.fire('Thành công', `Đã ${isEditMode.value ? 'cập nhật' : 'tạo mới'} sản phẩm!`, 'success');
    modalInstance.value.hide();
    fetchProducts(); // Tải lại dữ liệu

  } catch (apiError) {
    console.error("Lỗi khi lưu:", apiError);
    Swal.fire('Thất bại', 'Đã có lỗi xảy ra. Vui lòng thử lại.', 'error');
  } finally {
    isLoading.value = false;
  }
}  

// --- CÁC HÀM KHÁC (ĐÃ SỬA ID) ---

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
      // *** SỬA: Dùng PATCH trên endpoint 'id' (ID của json-server) ***
      await apiService.patch(`/products/${String(product.id)}`, { status: newStatus });
      Swal.fire('Thành công', `Đã ${newStatus === 'active' ? 'kích hoạt' : 'vô hiệu hóa'} sản phẩm.`, 'success');
    } catch (error) {
      console.error("Lỗi cập nhật trạng thái:", error); // Lỗi 404 sẽ không còn ở đây
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
      // *** SỬA: Dùng DELETE trên endpoint 'id' (ID của json-server) ***
      await apiService.delete(`/products/${String(product.id)}`);
      
      // LƯU Ý: Với json-server, bạn phải tự xóa variants và images liên quan nếu muốn
      // TẠM BỎ QUA do phức tạp, chỉ xóa sản phẩm chính

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
        <div class="card-header">
          <div class="row align-items-center gy-2">  
            <div class="col-md-5 col-12">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm kiếm theo tên sản phẩm, danh mục..." v-model="searchQuery">
              </div>
            </div>

            <!-- THAY product_id-desc làm mặc định -->
            <div class="col-md-4 col-12">
              <select class="form-select" v-model="sortCriteria" aria-label="Sắp xếp sản phẩm">
                <option value="product_id-desc">Sắp xếp: Mặc định (Mới nhất)</option>
                <option value="created_at-desc">Ngày thêm: Mới nhất</option>
                <option value="created_at-asc">Ngày thêm: Cũ nhất</option>
                <option value="name-asc">Tên: A-Z</option>
                <option value="name-desc">Tên: Z-A</option>
                <option value="price-asc">Giá: Thấp đến Cao</option>
                <option value="price-desc">Giá: Cao đến Thấp</option>
              </select>
            </div>
            
            <div class="col-md-3 col-12 text-md-end">
              <button type="button" class="btn btn-primary" @click="openCreateModal">
                <i class="bi bi-plus-lg"></i> Thêm mới Sản phẩm
              </button>
            </div>
          </div>
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 50px;">ID</th>
                  <th style="width: 80px;">Ảnh</th>
                  <th>Tên sản phẩm</th>
                  <th>Danh mục</th>
                  <th>Khoảng giá</th>
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
                <!-- SỬA: Dùng product.id làm key (vì nó là ID duy nhất của json-server) -->
                <tr v-for="product in paginatedProducts" :key="product.id">
                  <!-- Hiển thị product_id (nghiệp vụ) -->
                  <td>{{ product.product_id }}</td>  
                  <td>
                    <img :src="product.thumbnail_url || 'https://placehold.co/60x60?text=N/A'"
                      alt="Ảnh SP" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                  </td>
                  <td>{{ product.name }}</td>
                  <td>{{ product.category?.name || 'N/A' }}</td>
                  <td style="min-width: 160px;">{{ getPriceRange(product.variants) }}</td>
                  <td>{{ calculateTotalStock(product.variants) }}</td>
                  <td>
                    <span :class="['badge', product.status === 'active' ? 'text-bg-success' : 'text-bg-danger']">
                      {{ product.status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}
                    </span>
                  </td>
                  <td class="text-center">
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

  <!-- Modal Thêm/Sửa -->
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
                    <option v-for="cat in categories" :key="cat.id" :value="cat.category_id">{{ cat.name }}</option>
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
                  <tr v-for="(variant, index) in formData.variants" :key="variant.variant_id">
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

  <!-- Modal Xem Chi Tiết -->
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
              <p class="text-muted mb-0">ID: {{ viewingProduct.product_id }}</p>
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
          
          <!-- Thống kê sản phẩm -->
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

          <!-- Bảng biến thể -->
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
          
          <!-- Thư viện ảnh -->
          <hr class="my-4">
          <h5 class="mb-3">Thư viện ảnh</h5>
          <div class="image-preview-container" style="max-height: 300px;">
              <div v-if="!viewingProduct.images || viewingProduct.images.length === 0" class="text-muted p-3">
                  Không có ảnh chi tiết.
              </div>
              <div v-for="image in viewingProduct.images" :key="image.img_id" class="image-preview-item" style="width: 120px; height: 120px;">
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
/* (Style không thay đổi) */
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