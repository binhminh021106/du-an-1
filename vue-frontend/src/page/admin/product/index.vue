<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
// Giả sử apiService được import từ đường dẫn đúng
// import apiService from '../../../apiService.js';
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
  images: [], // Thêm images để tránh lỗi khi modal render
  // SỬA LỖI: Thêm các trường thống kê để tránh lỗi 'toFixed'
  sold_count: 0,
  favorite_count: 0,
  review_count: 0,
  average_rating: 0.0 
});

// State Tìm kiếm & Phân trang
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

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

// --- DỮ LIỆU MOCK (GIẢ LẬP DB.JSON) VỚI 10 SẢN PHẨM ---
// Dữ liệu này tuân theo schema SQL bạn cung cấp
const mockDb = {
  categories: [
    {
      "name": "Phone", "description": "Điện thoại di động nổi bật", "icon": "<i class=\"fa-solid fa-mobile\"></i>",
      "order": 1, "status": "active", "id": "3"
    },
    {
      "name": "Laptop", "description": "Máy tính xách tay bán chạy", "icon": "<i class=\"bi bi-laptop\"></i>",
      "order": 4, "id": "4", "status": "active"
    },
    {
      "name": "Gaming", "description": "Phụ kiện và thiết bị chơi game", "icon": "<i class=\"fa-solid fa-gamepad\"></i>",
      "order": 2, "id": "7", "status": "active"
    },
    {
      "name": "Tai nghe", "description": "Tai nghe", "icon": "<i class=\"fa-solid fa-headphones\"></i>",
      "order": 3, "id": "e117", "status": "active"
    }
  ],
  products: [
    {
      "id": 1, "name": "Điện thoại S-Galaxy 24 Ultra", "category_id": "3",
      "thumbnail_url": "https://placehold.co/150x150/7F8B9F/EBF0F6?text=Phone+1",
      "sold_count": 150, "favorite_count": 45, "review_count": 25, "average_rating": 4.8,
      "created_at": "2024-10-01T10:00:00Z", "updated_at": "2024-10-01T10:00:00Z", "deleted_at": null,
      "description": "Điện thoại flagship hàng đầu với camera 200MP và bút S-Pen.",
      "status": "active",
      "images": [{"id": 101, "product_id": 1, "image_url": "https://placehold.co/150x150/7F8B9F/EBF0F6?text=Phone+1"}],
      "variants": [
        {"id": 1, "price": 32990000, "stock": 10, "attributes": { "Màu sắc": "Titan Xám", "Dung lượng": "256GB" }},
        {"id": 2, "price": 35990000, "stock": 5, "attributes": { "Màu sắc": "Titan Đen", "Dung lượng": "512GB" }}
      ]
    },
    {
      "id": 2, "name": "Laptop ProBook 15 G10", "category_id": "4",
      "thumbnail_url": "https://placehold.co/150x150/9F7F7F/F6EBEB?text=Laptop+1",
      "sold_count": 30, "favorite_count": 55, "review_count": 10, "average_rating": 4.7,
      "created_at": "2024-10-02T11:00:00Z", "updated_at": "2024-10-02T11:00:00Z", "deleted_at": null,
      "description": "Laptop văn phòng bền bỉ, chip Intel Core i5 thế hệ 13.",
      "status": "active",
      "images": [{"id": 102, "product_id": 2, "image_url": "https://placehold.co/150x150/9F7F7F/F6EBEB?text=Laptop+1"}],
      "variants": [
        {"id": 3, "price": 18500000, "stock": 8, "attributes": { "RAM": "16GB", "Ổ cứng": "512GB SSD" }},
        {"id": 4, "price": 21000000, "stock": 3, "attributes": { "RAM": "32GB", "Ổ cứng": "512GB SSD" }}
      ]
    },
    {
      "id": 3, "name": "Tai nghe AirBuds Pro 3", "category_id": "e117",
      "thumbnail_url": "https://placehold.co/150x150/7F9F7F/EBF6EB?text=TaiNghe+1",
      "sold_count": 500, "favorite_count": 150, "review_count": 80, "average_rating": 4.9,
      "created_at": "2024-10-03T12:00:00Z", "updated_at": "2024-10-03T12:00:00Z", "deleted_at": null,
      "description": "Tai nghe chống ồn chủ động, âm thanh không gian.",
      "status": "active",
      "images": [{"id": 103, "product_id": 3, "image_url": "https://placehold.co/150x150/7F9F7F/EBF6EB?text=TaiNghe+1"}],
      "variants": [
        {"id": 5, "price": 5990000, "stock": 30, "attributes": { "Màu sắc": "Trắng" }}
      ]
    },
    {
      "id": 4, "name": "Bàn phím cơ Gaming G915", "category_id": "7",
      "thumbnail_url": "https://placehold.co/150x150/9F9F7F/F6F6EB?text=Keyboard",
      "sold_count": 80, "favorite_count": 25, "review_count": 12, "average_rating": 4.6,
      "created_at": "2024-10-04T13:00:00Z", "updated_at": "2024-10-04T13:00:00Z", "deleted_at": null,
      "description": "Bàn phím cơ không dây, switch low-profile, LED RGB.",
      "status": "active",
      "images": [{"id": 104, "product_id": 4, "image_url": "https://placehold.co/150x150/9F9F7F/F6F6EB?text=Keyboard"}],
      "variants": [
        {"id": 7, "price": 4500000, "stock": 15, "attributes": { "Switch": "Tactile" }},
        {"id": 8, "price": 4500000, "stock": 10, "attributes": { "Switch": "Clicky" }}
      ]
    },
    {
      "id": 5, "name": "Điện thoại O-Reno 11", "category_id": "3",
      "thumbnail_url": "https://placehold.co/150x150/7F8B9F/EBF0F6?text=Phone+2",
      "sold_count": 210, "favorite_count": 90, "review_count": 45, "average_rating": 4.7,
      "created_at": "2024-10-05T14:00:00Z", "updated_at": "2024-10-05T14:00:00Z", "deleted_at": null,
      "description": "Chuyên gia chân dung, thiết kế thời trang, sạc nhanh 80W.",
      "status": "active",
      "images": [{"id": 105, "product_id": 5, "image_url": "https://placehold.co/150x150/7F8B9F/EBF0F6?text=Phone+2"}],
      "variants": [
        {"id": 9, "price": 10990000, "stock": 20, "attributes": { "Màu sắc": "Xanh", "Dung lượng": "256GB" }}
      ]
    },
    {
      "id": 6, "name": "Laptop Gaming Legion 5", "category_id": "4",
      "thumbnail_url": "https://placehold.co/150x150/9F7F7F/F6EBEB?text=Laptop+2",
      "sold_count": 65, "favorite_count": 15, "review_count": 10, "average_rating": 4.5,
      "created_at": "2024-10-06T15:00:00Z", "updated_at": "2024-10-06T15:00:00Z", "deleted_at": null,
      "description": "Laptop gaming tản nhiệt mát, RTX 4060, màn hình 165Hz.",
      "status": "active",
      "images": [{"id": 106, "product_id": 6, "image_url": "https://placehold.co/150x150/9F7F7F/F6EBEB?text=Laptop+2"}],
      "variants": [
        {"id": 10, "price": 28990000, "stock": 8, "attributes": { "CPU": "Ryzen 7", "RAM": "16GB" }}
      ]
    },
    {
      "id": 7, "name": "Điện thoại i-Phone 15 Pro", "category_id": "3",
      "thumbnail_url": "https://placehold.co/150x150/7F8B9F/EBF0F6?text=Phone+3",
      "sold_count": 300, "favorite_count": 150, "review_count": 90, "average_rating": 4.9,
      "created_at": "2024-10-07T16:00:00Z", "updated_at": "2024-10-07T16:00:00Z", "deleted_at": null,
      "description": "Khung viền Titan, chip A17 Pro, cổng sạc USB-C.",
      "status": "active",
      "images": [{"id": 107, "product_id": 7, "image_url": "https://placehold.co/150x150/7F8B9F/EBF0F6?text=Phone+3"}],
      "variants": [
        {"id": 11, "price": 27990000, "stock": 10, "attributes": { "Màu sắc": "Titan Tự nhiên", "Dung lượng": "128GB" }},
        {"id": 12, "price": 30990000, "stock": 10, "attributes": { "Màu sắc": "Titan Xanh", "Dung lượng": "256GB" }}
      ]
    },
    {
      "id": 8, "name": "Chuột Gaming G502 Hero", "category_id": "7",
      "thumbnail_url": "https://placehold.co/150x150/9F9F7F/F6F6EB?text=Mouse",
      "sold_count": 300, "favorite_count": 60, "review_count": 50, "average_rating": 4.6,
      "created_at": "2024-10-08T17:00:00Z", "updated_at": "2024-10-08T17:00:00Z", "deleted_at": null,
      "description": "Chuột gaming có dây, mắt đọc Hero 25K, tùy chỉnh tạ.",
      "status": "active",
      "images": [{"id": 108, "product_id": 8, "image_url": "https://placehold.co/150x150/9F9F7F/F6F6EB?text=Mouse"}],
      "variants": [
        {"id": 13, "price": 1290000, "stock": 50, "attributes": { "Màu sắc": "Đen" }}
      ]
    },
    {
      "id": 9, "name": "Tai nghe S-Buds 3 Pro", "category_id": "e117",
      "thumbnail_url": "https://placehold.co/150x150/7F9F7F/EBF6EB?text=TaiNghe+2",
      "sold_count": 100, "favorite_count": 35, "review_count": 20, "average_rating": 4.5,
      "created_at": "2024-10-09T18:00:00Z", "updated_at": "2024-10-09T18:00:00Z", "deleted_at": null,
      "description": "Tai nghe TWS, chống ồn thông minh, âm thanh 360.",
      "status": "active",
      "images": [{"id": 109, "product_id": 9, "image_url": "https://placehold.co/150x150/7F9F7F/EBF6EB?text=TaiNghe+2"}],
      "variants": [
        {"id": 15, "price": 4490000, "stock": 25, "attributes": { "Màu sắc": "Đen" }},
        {"id": 16, "price": 4490000, "stock": 15, "attributes": { "Màu sắc": "Bạc" }}
      ]
    },
    {
      "id": 10, "name": "Laptop UltraBook 13", "category_id": "4",
      "thumbnail_url": "https://placehold.co/150x150/9F7F7F/F6EBEB?text=Laptop+3",
      "sold_count": 45, "favorite_count": 12, "review_count": 8, "average_rating": 4.3,
      "created_at": "2024-10-10T19:00:00Z", "updated_at": "2024-10-10T19:00:00Z", "deleted_at": null,
      "description": "Laptop mỏng nhẹ, màn hình OLED, vỏ nhôm nguyên khối.",
      "status": "active",
      "images": [{"id": 110, "product_id": 10, "image_url": "https://placehold.co/150x150/9F7F7F/F6EBEB?text=Laptop+3"}],
      "variants": [
        {"id": 17, "price": 22990000, "stock": 10, "attributes": { "CPU": "Core i7", "RAM": "16GB", "Ổ cứng": "512GB SSD" }}
      ]
    }
  ]
};

// (Giả lập apiService)
const FAKE_API_DELAY = 500;
const apiService = {
  get: (url) => {
    console.log(`[FAKE API] GET: ${url}`);
    return new Promise((resolve) => {
      setTimeout(() => {
        if (url.startsWith('/products')) {
          // Giả lập _sort, _order, _expand
          // 1. Lấy danh sách sản phẩm (tạo bản copy sâu để tránh thay đổi dữ liệu gốc)
          let productData = JSON.parse(JSON.stringify(mockDb.products));
          
          // 2. Sắp xếp
          productData.sort((a, b) => b.id - a.id); // Giả lập _sort=id&_order=desc
          
          // 3. Giả lập _expand=category
          const expandedData = productData.map(p => {
            const category = mockDb.categories.find(c => c.id === p.category_id);
            return {
              ...p,
              category: category || null // Gắn object category vào
            };
          });
          
          resolve({ data: expandedData });
        }
        if (url.startsWith('/categories')) {
          // Lọc ra các category 'active' khi get
          const activeCategories = mockDb.categories.filter(c => c.status === 'active');
          resolve({ data: JSON.parse(JSON.stringify(activeCategories)) });
        }
      }, FAKE_API_DELAY);
    });
  },
  post: (url, payload) => {
     console.log(`[FAKE API] POST: ${url}`, payload);
     if (payload instanceof FormData) {
        for (let [key, value] of payload.entries()) {
            console.log(`  ${key}:`, value);
        }
     }
     // Thêm vào mock DB
     const newId = Math.max(...mockDb.products.map(p => p.id)) + 1;
     // Tạm thời chỉ giả lập thêm, không xử lý ảnh
     const newProduct = {
        id: newId,
        name: payload.get('name'),
        description: payload.get('description'),
        category_id: payload.get('category_id'), // ID giờ là string
        status: payload.get('status'),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        thumbnail_url: 'https://placehold.co/150x150/CCCCCC/999999?text=New',
        images: [{id: 200 + newId, product_id: newId, image_url: 'https://placehold.co/150x150/CCCCCC/999999?text=New'}],
        variants: JSON.parse(payload.get('variants')),
        sold_count: 0, favorite_count: 0, review_count: 0, average_rating: 0.0,
     };
     mockDb.products.push(newProduct);
     console.log('[FAKE API] Added new product:', newProduct);
     
     return new Promise(resolve => setTimeout(resolve, FAKE_API_DELAY));
  },
  patch: (url, payload) => {
     console.log(`[FAKE API] PATCH: ${url}`, payload);
     // Cập nhật trạng thái
     const id = parseInt(url.split('/').pop());
     const product = mockDb.products.find(p => p.id === id);
     if(product && payload.status) {
        product.status = payload.status;
        console.log(`[FAKE API] Updated status for ${id} to ${payload.status}`);
     }
     return new Promise(resolve => setTimeout(resolve, FAKE_API_DELAY));
  },
  delete: (url) => {
     console.log(`[FAKE API] DELETE: ${url}`);
     // Xóa khỏi mock DB
     const id = parseInt(url.split('/').pop());
     const index = mockDb.products.findIndex(p => p.id === id);
     if(index > -1) {
        mockDb.products.splice(index, 1);
        console.log(`[FAKE API] Deleted product ${id}`);
     }
     return new Promise(resolve => setTimeout(resolve, FAKE_API_DELAY));
  }
};


// --- COMPUTED ---

const filteredProducts = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  if (!query) {
    return products.value; 
  }
  return products.value.filter(product =>
    product.name.toLowerCase().includes(query) ||
    (product.category?.name && product.category.name.toLowerCase().includes(query))
  );
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
watch(searchQuery, () => {
  currentPage.value = 1; 
});

// --- VÒNG ĐỜI (LIFECYCLE) ---
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

// --- CÁC HÀM TẢI DỮ LIỆU ---

async function fetchProducts() {
  isLoading.value = true;
  try {
    // API mock giờ sẽ trả về dữ liệu có _expand=category
    const response = await apiService.get(`/products?_sort=id&_order=desc&_expand=category`);
    products.value = response.data.map(p => ({
      ...p,
      // Đổi 'images' từ SQL (image_product) thành 'images' mà code đang dùng
      // Mock API đã làm việc này, nhưng nếu API thật trả về 'image_product' thì bạn cần map ở đây
      images: p.images || (p.image_product || []), 
      status: p.status || 'active', 
      created_at: p.created_at || new Date().toISOString() 
    }));
  } catch (error) {
    console.error("Lỗi khi tải sản phẩm:", error);
    Swal.fire('Lỗi', 'Không thể tải danh sách sản phẩm.', 'error');
  } finally {
    isLoading.value = false;
  }
}

async function fetchCategories() {
  try {
    // Mock API đã tự động lọc các category 'active'
    const response = await apiService.get(`/categories?_sort=order&_order=asc`);
    categories.value = response.data;
  } catch (error) {
    console.error("Lỗi khi tải danh mục:", error);
  }
}

// --- CÁC HÀM HELPER ---

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

// (Các hàm quản lý thuộc tính, biến thể, ảnh không thay đổi)
// --- CÁC HÀM QUẢN LÝ THUỘC TÍNH & BIẾN THỂ ---
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
// --- CÁC HÀM QUẢN LÝ ẢNH ---
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

  formData.id = product.id;
  formData.name = product.name;
  formData.description = product.description;
  formData.category_id = product.category?.id || product.category_id;
  formData.status = product.status || 'active';

  // Chuyển đổi 'images' (từ mock/api) thành 'existing_images'
  // Mock đang trả về {id, image_url}, chúng ta đổi tên thành {id, url}
  formData.existing_images = reactive(product.images ? product.images.map(img => ({ 
      id: img.id, 
      url: img.image_url || img.url // Chấp nhận cả 2 key
  })) : []);

  // --- LOGIC MỚI: TÁI TẠO STATE TỪ DỮ LIỆU PRODUCT ---
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
  // --- KẾT THÚC LOGIC MỚI ---

  modalInstance.value.show();
}

// Mở Modal Xem (ĐÃ CẬP NHẬT để hiển thị stats)
function openViewModal(product) {
  const variantKeys = (product.variants && product.variants.length > 0 && product.variants[0].attributes)
    ? Object.keys(product.variants[0].attributes)
    : [];

  viewingProduct.value = {
    ...product,
    categoryName: product.category?.name || 'N/A',
    attributeNames: variantKeys,
    // Thêm các trường read-only
    sold_count: product.sold_count || 0,
    favorite_count: product.favorite_count || 0,
    review_count: product.review_count || 0,
    average_rating: product.average_rating || 0.0,
    // Đảm bảo images là mảng
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
  if (!isEditMode.value && formData.new_images.length === 0) {
    errors.images = 'Vui lòng chọn ít nhất 1 ảnh sản phẩm.'; isValid = false;
  }
  if (isEditMode.value && formData.existing_images.length === 0 && formData.new_images.length === 0) {
    errors.images = 'Sản phẩm phải có ít nhất 1 ảnh.'; isValid = false;
  }
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

async function handleSave() {
  if (!validateForm()) {
    return;
  }

  isLoading.value = true;
  const payload = new FormData();

  payload.append('name', formData.name);
  payload.append('description', formData.description);
  payload.append('category_id', formData.category_id);
  payload.append('status', formData.status);
  
  // Ghi chú: thumbnail_url sẽ được backend tự động cập nhật
  // khi xử lý new_images[] và images_to_delete.
  // Các trường sold_count, review_count... không được gửi đi.
  
  payload.append('variants', JSON.stringify(formData.variants.map(v => ({
      price: v.price,
      stock: v.stock,
      attributes: v.attributes
  }))));
  
  formData.new_images.forEach((file) => {
    payload.append('new_images[]', file);
  });
  
  try {
    if (isEditMode.value) {
      payload.append('_method', 'PUT');
      payload.append('images_to_delete', JSON.stringify(formData.images_to_delete));
      // Dùng post cho mock (vì mock ko có PUT cho FormData)
      await apiService.post(`/products/${formData.id}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã cập nhật sản phẩm!', 'success');
    } else {
      payload.append('created_at', new Date().toISOString());
      await apiService.post(`/products`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      Swal.fire('Thành công', 'Đã tạo sản phẩm mới!', 'success');
    }

    modalInstance.value.hide();
    fetchProducts();
  } catch (apiError) {
    console.error("Lỗi khi lưu:", apiError);
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

// (Các hàm toggleStatus, handleDelete, goToPage không đổi)
// --- CÁC HÀM KHÁC ---
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
          <div class="row align-items-center">
            <div class="col-md-6 col-12 mb-2 mb-md-0">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0"
                  placeholder="Tìm kiếm theo tên sản phẩm, danh mục..." v-model="searchQuery">
              </div>
            </div>
            <div class="col-md-6 col-12 text-md-end">
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
                  <td>{{ formatCurrency(product.variants[0]?.price) }}</td>
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
                  <label for="product_images" class="form-label required">Ảnh sản phẩm (Bao gồm cả thumbnail)</label>
                  <input type="file" class="form-control" :class="{ 'is-invalid': errors.images }" id="product_images"
                    @change="handleImageUpload" accept="image/*" multiple>
                  <div class="form-text">Backend sẽ tự động đặt ảnh đầu tiên làm Thumbnail.</div>
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