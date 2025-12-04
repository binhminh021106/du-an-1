<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from "vue";
import apiService from "../../../apiService.js";
import Swal from "sweetalert2";
import { Modal } from "bootstrap";

const rawApiUrl = import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000/api";
const API_BASE_URL = rawApiUrl.replace(/\/api\/?$/, "");

// --- Validation Constants ---
const MIN_IMAGE_WIDTH = 500;
const MIN_IMAGE_HEIGHT = 500;
const MAX_FILE_SIZE_MB = 5;
const MIN_PRICE = 1000;
const MAX_PRICE = 100000000;
const MIN_NAME_LENGTH = 5;
const MAX_NAME_LENGTH = 255;
const MAX_DESC_LENGTH = 5000;

// --- UX Helper: Toast Notification ---
const Toast = Swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  didOpen: (toast) => {
    toast.onmouseenter = Swal.stopTimer;
    toast.onmouseleave = Swal.resumeTimer;
  }
});

const showToast = (icon, title) => {
  Toast.fire({ icon, title });
};
// --------------------------------------

const currentUser = ref({});

const hasRole = (allowedRoles) => {
  const userRoleId = Number(currentUser.value?.role_id);
  let userRoleName = "";

  if (userRoleId === 11) userRoleName = "admin";
  else if (userRoleId === 12) userRoleName = "staff";
  else if (userRoleId === 13) userRoleName = "blogger";

  if (!userRoleName) return false;
  if (userRoleName === "admin") return true;

  return allowedRoles.includes(userRoleName);
};

const checkAuthState = async () => {
  const token = localStorage.getItem("adminToken");
  if (token)
    apiService.defaults.headers.common["Authorization"] = `Bearer ${token}`;

  const storedAdmin = localStorage.getItem("adminData");
  const storedUser = localStorage.getItem("user_info");
  let userData = null;

  try {
    if (storedAdmin) userData = JSON.parse(storedAdmin);
    else if (storedUser) userData = JSON.parse(storedUser);
  } catch (e) {
    console.error("Parse error", e);
  }

  if (userData) {
    currentUser.value = { ...userData, role_id: Number(userData.role_id) };
    return true;
  }

  if (token) {
    try {
      const response = await apiService.get("/user");
      let data = response.data;
      if (data.data && !data.id) data = data.data;

      currentUser.value = { ...data, role_id: Number(data.role_id) };
      localStorage.setItem("adminData", JSON.stringify(currentUser.value));
      return true;
    } catch (error) {
      console.error("Auth API Error:", error);
      return false;
    }
  }
  return false;
};

const requireLogin = () => {
  if (!currentUser.value.id) {
    Swal.fire({
      icon: "error",
      title: "Truy cập bị từ chối",
      text: "Phiên làm việc hết hạn.",
      confirmButtonText: "Đăng nhập ngay",
    });
    return false;
  }
  if (!hasRole(["admin", "staff"])) {
    Swal.fire({
      icon: "warning",
      title: "Quyền hạn",
      text: "Bạn không có quyền quản lý sản phẩm.",
    });
    return false;
  }
  return true;
};

// ==========================================
// STATE MANAGEMENT
// ==========================================
const products = ref([]);
const categories = ref([]);
const brands = ref([]);
const attributesList = ref([]);
const isLoading = ref(true);
const isSaving = ref(false);
const isEditMode = ref(false);
const activeTab = ref("active");

// Spinner States
const isCreatingAttr = ref(false);
const isManagingAttr = ref(false);
const deletingImageIds = ref([]);
const processingStatusIds = ref([]);
const isBulkDeleting = ref(false);
const selectedImageIds = ref([]);

// Modals
const modalInstance = ref(null);
const modalRef = ref(null);
const attrModalInstance = ref(null);
const attrModalRef = ref(null);
const viewModalInstance = ref(null);
const viewModalRef = ref(null);
const manageAttrModalRef = ref(null);
const manageAttrModalInstance = ref(null);

// Form Data & Helpers
const newQuickAttrName = ref("");
const selectedAttributeId = ref("");
const thumbnailFile = ref(null);
const thumbnailPreview = ref(null);
const galleryFiles = ref([]);
const galleryPreviews = ref([]);
const duplicateRowIndices = ref([]);

// Manage Attributes State
const managingAttrId = ref("");
const managingAttrName = ref("");

// Viewing Product
const viewingProduct = ref({
  name: "",
  description: "",
  categoryName: "",
  brandName: "",
  status: "",
  thumbnail_url: "",
  images: [],
  variants: [],
  attributeNames: [],
  priceRange: "",
  totalStock: 0,
  average_rating: 0,
  review_count: 0
});
const selectedViewImage = ref("");

// Search & Sort & Pagination
const searchQuery = ref("");
const selectedCategory = ref("");
const currentPage = ref(1);
const itemsPerPage = ref(10);
const sortCriteria = ref("product_id-desc");

// Main Form
const formData = reactive({
  id: null,
  name: "",
  description: "",
  category_id: null,
  brand_id: null,
  status: "inactive",
  attribute_definitions: reactive([]),
  variants: reactive([]),
  existing_images: reactive([]),
});

const errors = reactive({
  name: "",
  description: "",
  category_id: "",
  brand_id: "",
  variants: "",
  attributes: "",
});

const generateShortId = () => Math.random().toString(36).substring(2, 8);

function getImageUrl(path) {
  if (!path) return "https://placehold.co/150x150?text=No+Img";
  if (path.startsWith("blob:") || path.startsWith("http")) return path;

  const baseUrl = API_BASE_URL.endsWith("/")
    ? API_BASE_URL.slice(0, -1)
    : API_BASE_URL;
  const cleanPath = path.startsWith("/") ? path : "/" + path;
  return `${baseUrl}${cleanPath}`;
}

function flattenVariantAttributes(variant) {
  const attrs = {};
  if (Array.isArray(variant.attribute_values)) {
    variant.attribute_values.forEach((av) => {
      if (av.attribute && av.attribute.name)
        attrs[av.attribute.name] = av.value;
    });
  } else if (variant.attributes) return variant.attributes;
  return attrs;
}

// 1. Filter & Sort
const searchedAndSortedProducts = computed(() => {
  let result = products.value;

  const query = searchQuery.value.toLowerCase().trim();
  if (query) {
    result = result.filter(
      (p) =>
        p.name.toLowerCase().includes(query) ||
        (p.category?.name && p.category.name.toLowerCase().includes(query))
    );
  }

  if (selectedCategory.value) {
    result = result.filter((p) => p.category_id === selectedCategory.value);
  }

  const [key, order] = sortCriteria.value.split("-");
  const sorted = [...result];

  sorted.sort((a, b) => {
    let valA, valB;
    switch (key) {
      case "name":
        valA = a.name.toLowerCase();
        valB = b.name.toLowerCase();
        break;
      case "price":
        valA = getMinPrice(a.variants);
        valB = getMinPrice(b.variants);
        break;
      case "created_at":
        valA = new Date(a.created_at);
        valB = new Date(b.created_at);
        break;
      case "product_id":
      default:
        valA = a.product_id;
        valB = b.product_id;
        break;
    }
    return order === "asc" ? (valA > valB ? 1 : -1) : valA < valB ? 1 : -1;
  });
  return sorted;
});

// 2. Split Lists
const activeProducts = computed(() =>
  searchedAndSortedProducts.value.filter((p) => p.status === "active")
);
const inactiveProducts = computed(() =>
  searchedAndSortedProducts.value.filter((p) => p.status === "inactive")
);

// 3. Displayed List
const displayedProducts = computed(() =>
  activeTab.value === "active" ? activeProducts.value : inactiveProducts.value
);

// 4. Counts
const statusCounts = computed(() => ({
  active: activeProducts.value.length,
  inactive: inactiveProducts.value.length,
}));

// 5. Pagination
const totalPages = computed(() =>
  Math.ceil(displayedProducts.value.length / itemsPerPage.value)
);
const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return displayedProducts.value.slice(start, start + itemsPerPage.value);
});

// Select All Logic
const isAllImagesSelected = computed({
  get() {
    return (
      formData.existing_images.length > 0 &&
      selectedImageIds.value.length === formData.existing_images.length
    );
  },
  set(val) {
    selectedImageIds.value = val
      ? formData.existing_images.map((img) => img.id)
      : [];
  },
});

// Watchers
watch([searchQuery, selectedCategory, sortCriteria, activeTab], () => {
  currentPage.value = 1;
});

// Watch for Managing Attribute Selection
watch(managingAttrId, (newId) => {
  if (!newId) {
    managingAttrName.value = "";
    return;
  }
  const attr = attributesList.value.find(a => a.id == newId);
  if (attr) {
    managingAttrName.value = attr.name;
  }
});

// ==========================================
// HELPERS
// ==========================================
const formatCurrency = (value) =>
  value == null || isNaN(value)
    ? "N/A"
    : new Intl.NumberFormat("vi-VN", {
      style: "currency",
      currency: "VND",
    }).format(value);
const getMinPrice = (variants) => {
  if (!variants?.length) return 0;
  const prices = variants
    .map((v) => parseFloat(v.price))
    .filter((p) => !isNaN(p));
  return prices.length ? Math.min(...prices) : 0;
};
const calculateTotalStock = (variants) =>
  variants?.reduce((acc, v) => acc + (parseInt(v.stock) || 0), 0) || 0;
const getPriceRange = (variants) => {
  if (!variants?.length) return "N/A";
  const prices = variants
    .map((v) => parseFloat(v.price))
    .filter((p) => !isNaN(p));
  if (!prices.length) return "N/A";
  const min = Math.min(...prices),
    max = Math.max(...prices);
  return min === max
    ? formatCurrency(min)
    : `${formatCurrency(min)} - ${formatCurrency(max)}`;
};
const setActiveTab = (tab) => (activeTab.value = tab);
const goToPage = (p) => {
  if (p >= 1 && p <= totalPages.value) currentPage.value = p;
};

// ==========================================
// VALIDATE IMAGE
// ==========================================
const validateImageFile = async (file) => {
  if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
    return { valid: false, msg: `Dung lượng tối đa ${MAX_FILE_SIZE_MB}MB.` };
  }
  const isFormatValid = await new Promise((resolve) => {
    const reader = new FileReader();
    reader.onloadend = (e) => {
      const arr = new Uint8Array(e.target.result).subarray(0, 4);
      let header = "";
      for (let i = 0; i < arr.length; i++) header += arr[i].toString(16);
      resolve(
        header.startsWith("ffd8ff") ||
        header.startsWith("89504e47") ||
        header.startsWith("47494638") ||
        header.startsWith("52494646")
      );
    };
    reader.readAsArrayBuffer(file.slice(0, 4));
  });

  if (!isFormatValid)
    return { valid: false, msg: "Định dạng file không hợp lệ (Chỉ nhận ảnh)." };

  return new Promise((resolve) => {
    const img = new Image();
    img.src = URL.createObjectURL(file);
    img.onload = () => {
      URL.revokeObjectURL(img.src);
      if (img.width < MIN_IMAGE_WIDTH || img.height < MIN_IMAGE_HEIGHT) {
        resolve({
          valid: false,
          msg: `Kích thước ảnh quá nhỏ! Tối thiểu ${MIN_IMAGE_WIDTH}x${MIN_IMAGE_HEIGHT}px để đảm bảo chất lượng.`,
        });
      } else {
        resolve({ valid: true });
      }
    };
    img.onerror = () => resolve({ valid: false, msg: "Lỗi đọc file ảnh." });
  });
};

// ==========================================
// API CALLS
// ==========================================
async function fetchAttributes() {
  try {
    const response = await apiService.get("/admin/attributes");
    attributesList.value = Array.isArray(response.data)
      ? response.data
      : response.data.data || [];
  } catch (error) {
    console.error("Lỗi tải attributes:", error);
  }
}

async function fetchCategories() {
  try {
    const response = await apiService.get(
      `/categories?status=active&_sort=order&_order=asc`
    );
    const data = Array.isArray(response.data)
      ? response.data
      : response.data.data || [];
    categories.value = data.map((c) => ({ ...c, id: c.id }));
  } catch (error) {
    console.error("Lỗi tải danh mục:", error);
  }
}

async function fetchBrands() {
  try {
    const response = await apiService.get("/admin/brands");
    brands.value = response.data || [];
  } catch (error) {
    console.error("Lỗi tải thương hiệu:", error);
  }
}

async function fetchProducts() {
  if (products.value.length === 0) isLoading.value = true;
  try {
    const [productsRes, variantsRes, imagesRes] = await Promise.all([
      apiService.get("/admin/products"),
      apiService.get("/variants"),
      apiService.get("/imageProducts"),
    ]);

    const rawProducts = productsRes.data.data
      ? productsRes.data.data
      : productsRes.data;
    const allCategories = categories.value;
    const allVariants = variantsRes.data;
    const allImages = imagesRes.data;

    products.value = rawProducts.map((p) => {
      const category = allCategories.find((c) => c.id === p.category_id);
      
      // --- UPDATE START: Map Brand for table display ---
      // Nếu API trả về relation 'brand' thì dùng luôn, còn không thì tìm trong danh sách brands đã fetch
      let brand = p.brand; 
      if (!brand && brands.value.length > 0) {
          brand = brands.value.find((b) => b.id === p.brand_id);
      }
      // --- UPDATE END ---

      const productVariants = allVariants.filter(
        (v) => v.product_id == p.product_id || p.id == v.product_id
      );
      const productImages = allImages.filter(
        (img) => img.product_id == p.product_id || p.id == img.product_id
      );
      let thumbPath = p.thumbnail_url;
      if (!thumbPath && productImages.length > 0)
        thumbPath = productImages[0].image_url;

      return {
        sold_count: 0,
        favorite_count: 0,
        review_count: 0,
        average_rating: 0.0,
        status: "active",
        created_at: new Date().toISOString(),
        ...p,
        product_id: p.product_id || p.id,
        category: category || null,
        brand: brand || null, // Gán brand vào object
        brand_id: p.brand_id,
        variants: productVariants,
        images: productImages,
        thumbnail_url: getImageUrl(thumbPath),
      };
    });
  } catch (error) {
    console.error("Lỗi tải sản phẩm:", error);
  } finally {
    isLoading.value = false;
  }
}

// ==========================================
// ACTIONS
// ==========================================
async function handleCreateQuickAttribute() {
  if (!newQuickAttrName.value.trim())
    return showToast("warning", "Vui lòng nhập tên thuộc tính");
  isCreatingAttr.value = true;
  try {
    const res = await apiService.post("/admin/attributes", {
      name: newQuickAttrName.value,
    });
    showToast("success", "Đã tạo thuộc tính mới!");
    await fetchAttributes();
    if (res.data && res.data.id) selectedAttributeId.value = res.data.id;
    newQuickAttrName.value = "";
    attrModalInstance.value.hide();
    modalInstance.value.show();
  } catch (error) {
    Swal.fire(
      "Lỗi",
      error.response?.data?.message || "Không thể tạo thuộc tính.",
      "error"
    );
  } finally {
    isCreatingAttr.value = false;
  }
}

// [NEW] Handle Update Attribute (Global CRUD)
async function handleUpdateGlobalAttribute() {
  if (!managingAttrId.value || !managingAttrName.value.trim()) {
    return showToast("warning", "Vui lòng chọn và nhập tên thuộc tính");
  }
  isManagingAttr.value = true;
  try {
    const oldAttr = attributesList.value.find(a => a.id == managingAttrId.value);
    const oldName = oldAttr ? oldAttr.name : "";

    await apiService.put(`/admin/attributes/${managingAttrId.value}`, {
      name: managingAttrName.value
    });

    showToast("success", "Cập nhật thành công!");
    await fetchAttributes();

    // UX: Nếu sản phẩm đang mở modal có dùng thuộc tính này, cập nhật tên luôn để người dùng không bị lú
    if (oldName) {
      const def = formData.attribute_definitions.find(d => d.id == managingAttrId.value);
      if (def) {
        def.name = managingAttrName.value;
        // Update key in variants
        formData.variants.forEach(v => {
          if (v.attributes.hasOwnProperty(oldName)) {
            v.attributes[managingAttrName.value] = v.attributes[oldName];
            delete v.attributes[oldName];
          }
        });
      }
    }

    managingAttrId.value = ""; // Reset
  } catch (error) {
    showToast("error", error.response?.data?.message || "Lỗi cập nhật");
  } finally {
    isManagingAttr.value = false;
  }
}

// [NEW] Handle Delete Attribute (Global CRUD)
async function handleDeleteGlobalAttribute() {
  if (!managingAttrId.value) return;

  if (!(await Swal.fire({
    title: 'Xóa vĩnh viễn?',
    text: 'Hành động này sẽ xóa thuộc tính khỏi hệ thống!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Xóa ngay'
  })).isConfirmed) return;

  isManagingAttr.value = true;
  try {
    const oldAttr = attributesList.value.find(a => a.id == managingAttrId.value);
    await apiService.delete(`/admin/attributes/${managingAttrId.value}`);

    showToast("success", "Đã xóa thuộc tính!");
    await fetchAttributes();

    // UX: Nếu đang dùng trong form thì xóa cột đó đi
    if (oldAttr) {
      const index = formData.attribute_definitions.findIndex(d => d.id == oldAttr.id);
      if (index > -1) {
        formData.attribute_definitions.splice(index, 1);
        formData.variants.forEach(v => {
          delete v.attributes[oldAttr.name];
        });
      }
    }
    managingAttrId.value = "";
  } catch (error) {
    showToast("error", "Không thể xóa (có thể đang được sử dụng)");
  } finally {
    isManagingAttr.value = false;
  }
}

// Image Handlers
async function handleThumbnailChange(event) {
  const file = event.target.files[0];
  if (file) {
    const check = await validateImageFile(file);
    if (!check.valid) {
      showToast("warning", check.msg);
      event.target.value = null;
      return;
    }
    thumbnailFile.value = file;
    thumbnailPreview.value = URL.createObjectURL(file);
  }
}

async function handleGalleryChange(event) {
  const files = Array.from(event.target.files);
  const validFiles = [];

  for (const file of files) {
    const check = await validateImageFile(file);
    if (check.valid) {
      validFiles.push(file);
      galleryFiles.value.push(file);
      galleryPreviews.value.push(URL.createObjectURL(file));
    } else {
      showToast("warning", `Bỏ qua ${file.name}: ${check.msg}`);
    }
  }
  if (validFiles.length === 0) event.target.value = null;
}

function removeNewGalleryImage(index) {
  URL.revokeObjectURL(galleryPreviews.value[index]);
  galleryPreviews.value.splice(index, 1);
  galleryFiles.value.splice(index, 1);
}

// [NEW] Xử lý chọn ảnh cho biến thể
async function handleVariantImageChange(event, index) {
  const file = event.target.files[0];
  if (file) {
    const check = await validateImageFile(file);
    if (!check.valid) {
      showToast("warning", check.msg);
      event.target.value = null;
      return;
    }
    formData.variants[index].imageFile = file;
    formData.variants[index].imagePreview = URL.createObjectURL(file);
  }
}

// Form & Variants
function addSelectedAttributeToForm() {
  if (!selectedAttributeId.value) {
    return showToast("warning", "Vui lòng chọn một thuộc tính.");
  }
  const attrObj = attributesList.value.find(
    (a) => a.id == selectedAttributeId.value
  );
  if (!attrObj) return;
  const exists = formData.attribute_definitions.find(
    (a) => a.id == attrObj.id || a.name === attrObj.name
  );
  if (exists) {
    showToast("info", `Thuộc tính "${attrObj.name}" đã được thêm.`);
    return;
  }
  errors.attributes = "";
  formData.attribute_definitions.push({ id: attrObj.id, name: attrObj.name });
  formData.variants.forEach((v) => {
    if (!v.attributes) v.attributes = {};
    v.attributes[attrObj.name] = "";
  });
  selectedAttributeId.value = "";
  showToast("success", `Đã thêm cột "${attrObj.name}"`);
}

function removeAttributeDefinition(attrToRemove) {
  const index = formData.attribute_definitions.findIndex(
    (attr) => attr.id === attrToRemove.id
  );
  if (index > -1) {
    formData.attribute_definitions.splice(index, 1);
    formData.variants.forEach((v) => {
      delete v.attributes[attrToRemove.name];
    });
    showToast("success", "Đã xóa cột thuộc tính khỏi bảng.");
  }
}

function addVariantRow() {
  const newAttributes = reactive({});
  formData.attribute_definitions.forEach((def) => {
    newAttributes[def.name] = "";
  });
  formData.variants.push(
    reactive({
      variant_id: generateShortId(),
      original_price: 0,
      price: 0,
      stock: 0,
      attributes: newAttributes,
      imageFile: null,
      imagePreview: null,
    })
  );
}
function removeVariantRow(index) {
  formData.variants.splice(index, 1);
  duplicateRowIndices.value = duplicateRowIndices.value.filter(i => i !== index);
}

function resetForm() {
  Object.assign(formData, {
    id: null,
    name: "",
    description: "",
    category_id: null,
    brand_id: null,
    status: "inactive",
    attribute_definitions: reactive([]),
    variants: reactive([]),
    existing_images: reactive([]),
  });
  thumbnailFile.value = null;
  thumbnailPreview.value = null;
  galleryFiles.value = [];
  galleryPreviews.value.forEach((url) => URL.revokeObjectURL(url));
  galleryPreviews.value = [];
  selectedAttributeId.value = "";
  selectedImageIds.value = [];
  duplicateRowIndices.value = [];
  Object.keys(errors).forEach((k) => (errors[k] = ""));
}

// Modals
function openCreateModal() {
  if (!requireLogin()) return;
  resetForm();
  isEditMode.value = false;
  modalInstance.value?.show();
}
function openCreateAttrModal() {
  newQuickAttrName.value = "";
  errors.attributes = "";
  attrModalInstance.value?.show();
}
// [NEW] Open Manage Modal
function openManageAttrModal() {
  managingAttrId.value = "";
  managingAttrName.value = "";
  manageAttrModalInstance.value?.show();
}

async function openVariantConfigModal(productItem) {
  if (!requireLogin()) return;
  resetForm();
  isEditMode.value = true;
  modalInstance.value?.show();
  try {
    const res = await apiService.get(`/admin/products/${productItem.id}`);
    const product = res.data.data || res.data;
    formData.id = product.id;
    formData.name = product.name;
    formData.description = product.description;
    formData.category_id = product.category_id || product.category?.id;
    formData.brand_id = product.brand_id || product.brand?.id;
    formData.status = product.status;
    thumbnailPreview.value = getImageUrl(product.thumbnail_url);
    formData.existing_images = reactive(
      (product.images || []).map((img) => ({
        id: img.id,
        url: getImageUrl(img.image_url),
      }))
    );

    const variants = product.variants || [];
    if (variants.length > 0) {
      const allAttributeNames = new Set();
      variants.forEach((v) => {
        if (v.attributes)
          Object.keys(v.attributes).forEach((key) =>
            allAttributeNames.add(key)
          );
      });
      formData.attribute_definitions = reactive(
        Array.from(allAttributeNames).map((name) => {
          const found = attributesList.value.find((a) => a.name === name);
          return { id: found ? found.id : generateShortId(), name: name };
        })
      );
      formData.variants = reactive(
        variants.map((v) => {
          const variantAttributes = reactive({});
          formData.attribute_definitions.forEach((def) => {
            variantAttributes[def.name] = "";
          });
          if (v.attributes)
            Object.entries(v.attributes).forEach(([key, value]) => {
              variantAttributes[key] = value;
            });
          return {
            variant_id: v.id,
            original_price: v.original_price || 0,
            price: v.price || 0,
            stock: v.stock || 0,
            attributes: variantAttributes,
            image: v.image,
            imagePreview: v.image ? getImageUrl(v.image) : null,
            imageFile: null,
          };
        })
      );
    } else {
      addVariantRow();
    }
  } catch (e) {
    Swal.fire("Lỗi", "Không thể tải chi tiết sản phẩm.", "error");
  }
}

async function openViewModal(productItem) {
  try {
    const res = await apiService.get(`/admin/products/${productItem.id}`);
    const product = res.data.data || res.data;
    const processedVariants = (product.variants || []).map((v) => ({
      ...v,
      attributes: flattenVariantAttributes(v),
      imageUrl: v.image ? getImageUrl(v.image) : null
    }));
    const keys = new Set();
    processedVariants.forEach((v) =>
      Object.keys(v.attributes).forEach((k) => keys.add(k))
    );

    const brandName = brands.value.find(b => b.id === product.brand_id)?.name || "N/A";

    // [NEW] Set view image default
    const thumb = getImageUrl(product.thumbnail_url);
    const gallery = (product.images || []).map((img) => getImageUrl(img.image_url));

    viewingProduct.value = {
      ...product,
      categoryName: product.category?.name || "N/A",
      brandName: brandName,
      attributeNames: Array.from(keys),
      images: gallery,
      thumbnail_url: thumb,
      priceRange: getPriceRange(product.variants),
      totalStock: calculateTotalStock(product.variants),
      variants: processedVariants,
      average_rating: parseFloat(product.average_rating || 0), // [NEW]
      review_count: parseInt(product.review_count || 0) // [NEW]
    };

    selectedViewImage.value = thumb; // [NEW]

    viewModalInstance.value?.show();
  } catch (e) {
    Swal.fire("Lỗi", "Không thể xem chi tiết.", "error");
  }
}

// Database Actions
async function deleteExistingImage(imgId, index) {
  if (!requireLogin()) return;
  if (deletingImageIds.value.includes(imgId)) return;
  if (
    !(
      await Swal.fire({
        title: "Xóa ảnh?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
      })
    ).isConfirmed
  )
    return;
  deletingImageIds.value.push(imgId);
  try {
    await apiService.delete(`/admin/imageProducts/${imgId}`);
    formData.existing_images.splice(index, 1);
    selectedImageIds.value = selectedImageIds.value.filter(
      (id) => id !== imgId
    );
    showToast("success", "Đã xóa ảnh");
  } catch (e) {
    Swal.fire("Lỗi", "Không thể xóa ảnh", "error");
  } finally {
    deletingImageIds.value = deletingImageIds.value.filter(
      (id) => id !== imgId
    );
  }
}

async function handleBulkDeleteImages() {
  if (!requireLogin()) return;
  if (selectedImageIds.value.length === 0) return;
  if (
    !(
      await Swal.fire({
        title: "Xóa nhiều ảnh?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
      })
    ).isConfirmed
  )
    return;
  isBulkDeleting.value = true;
  try {
    await apiService.post("/admin/imageProducts/bulk-delete", {
      ids: selectedImageIds.value,
    });
    formData.existing_images = formData.existing_images.filter(
      (img) => !selectedImageIds.value.includes(img.id)
    );
    selectedImageIds.value = [];
    showToast("success", "Đã xóa ảnh");
  } catch (e) {
    Swal.fire("Lỗi", "Không thể xóa ảnh", "error");
  } finally {
    isBulkDeleting.value = false;
  }
}

async function handleQuickStatusToggle(product) {
  if (!requireLogin()) return;
  if (processingStatusIds.value.includes(product.id)) return;

  if (product.status !== "active") {
    if (!product.variants || product.variants.length === 0) {
      return Swal.fire(
        "Chưa hoàn thiện",
        "Sản phẩm này chưa có biến thể nào. Vui lòng thêm biến thể trước khi bán.",
        "warning"
      );
    }
    const hasValidPrice = product.variants.some(
      (v) => parseFloat(v.price) >= MIN_PRICE
    );
    if (!hasValidPrice) {
      return Swal.fire(
        "Chưa hoàn thiện",
        "Sản phẩm chưa có giá bán hợp lệ.",
        "warning"
      );
    }
  }

  const newStatus = product.status === "active" ? "inactive" : "active";
  const actionText = newStatus === "active" ? "Hiện" : "Ẩn";
  if (
    !(
      await Swal.fire({
        title: `Xác nhận ${actionText}?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#009981",
      })
    ).isConfirmed
  )
    return;
  processingStatusIds.value.push(product.id);
  try {
    const payload = new FormData();
    payload.append("_method", "PATCH");
    payload.append("status", newStatus);
    await apiService.post(`/admin/products/${product.id}`, payload);
    product.status = newStatus;
    showToast("success", "Cập nhật thành công!");
  } catch (e) {
    Swal.fire("Lỗi", "Không thể cập nhật trạng thái.", "error");
  } finally {
    processingStatusIds.value = processingStatusIds.value.filter(
      (id) => id !== product.id
    );
  }
}

// [UPDATED] Advanced Validation
function validateForm() {
  Object.keys(errors).forEach((k) => (errors[k] = ""));
  duplicateRowIndices.value = []; // Reset highlights
  let isValid = true;

  // 1. Validate Product Name
  if (!formData.name.trim()) {
    errors.name = "Nhập tên SP";
    isValid = false;
  } else if (formData.name.trim().length < MIN_NAME_LENGTH) {
    errors.name = `Tên SP tối thiểu ${MIN_NAME_LENGTH} ký tự.`;
    isValid = false;
  } else if (formData.name.trim().length > MAX_NAME_LENGTH) {
    errors.name = `Tên SP tối đa ${MAX_NAME_LENGTH} ký tự.`;
    isValid = false;
  }

  // 2. Validate Description
  if (formData.description && formData.description.length > MAX_DESC_LENGTH) {
    errors.description = `Mô tả quá dài (Tối đa ${MAX_DESC_LENGTH} ký tự).`;
    isValid = false;
  }

  // 3. Validate Category & Brand
  if (!formData.category_id) {
    errors.category_id = "Chọn danh mục";
    isValid = false;
  }

  // 4. Validate Logic for Active Status
  if (isEditMode.value && formData.status === "active") {
    if (!formData.variants || formData.variants.length === 0) {
      errors.variants = "Phải có ít nhất 1 biến thể để đăng bán.";
      isValid = false;
    }
  }

  // 5. Validate Variant Details
  if (isEditMode.value && formData.variants.length > 0) {
    const signatureMap = new Map(); // Use Map to track index

    formData.variants.forEach((v, index) => {
      const price = parseFloat(v.price);
      const originalPrice = parseFloat(v.original_price);

      // Price Validation
      if (isNaN(price) || price < 0) {
        errors.variants = "Giá bán không được âm.";
        isValid = false;
      }
      else if (price > 0 && price < MIN_PRICE) {
        errors.variants = `Giá bán tối thiểu là ${formatCurrency(MIN_PRICE)}.`;
        isValid = false;
      }
      else if (price > MAX_PRICE) {
        errors.variants = `Giá bán quá lớn (> ${formatCurrency(MAX_PRICE)}).`;
        isValid = false;
      }

      // Logic: Price vs Original Price
      if (!isNaN(originalPrice) && originalPrice > 0) {
        if (price > originalPrice) {
          errors.variants = `Giá bán (${formatCurrency(price)}) không được cao hơn giá gốc (${formatCurrency(originalPrice)}).`;
          isValid = false;
        }
      }

      // Stock Validation
      const stock = Number(v.stock);
      if (isNaN(stock) || stock < 0) {
        errors.variants = "Số lượng kho không được âm.";
        isValid = false;
      }
      else if (!Number.isInteger(stock)) {
        errors.variants = "Số lượng kho phải là số nguyên.";
        isValid = false;
      }

      // Attribute Completeness & Duplication Check
      const currentAttributes = [];
      for (const def of formData.attribute_definitions) {
        const val = v.attributes[def.name];
        if (!val || !val.toString().trim()) {
          errors.variants = `Vui lòng điền giá trị cho "${def.name}"`;
          isValid = false;
        }
        currentAttributes.push(`${def.name}:${val ? val.toString().trim().toLowerCase() : ''}`);
      }

      // Duplicate Check Logic
      const variantSignature = currentAttributes.sort().join("|");
      if (signatureMap.has(variantSignature)) {
        // Found a duplicate! Highlight BOTH rows
        duplicateRowIndices.value.push(index);
        duplicateRowIndices.value.push(signatureMap.get(variantSignature));
        errors.variants = "Phát hiện các dòng biến thể trùng lặp (được tô đỏ).";
        isValid = false;
      } else {
        signatureMap.set(variantSignature, index);
      }
    });
  }

  // Unique the highlight array
  duplicateRowIndices.value = [...new Set(duplicateRowIndices.value)];

  return isValid;
}

async function handleSave() {
  if (!requireLogin()) return;
  if (!validateForm()) {
    showToast("error", "Vui lòng kiểm tra lại thông tin!"); // UX: Toast instead of big modal
    return;
  }
  isSaving.value = true;
  try {
    let savedProductId = formData.id;
    const productData = new FormData();
    productData.append("name", formData.name.trim());
    productData.append("description", formData.description || "");
    productData.append("category_id", formData.category_id);
    if (formData.brand_id) {
      productData.append("brand_id", formData.brand_id);
    }
    productData.append(
      "status",
      isEditMode.value ? formData.status : "inactive"
    );
    if (thumbnailFile.value)
      productData.append("thumbnail", thumbnailFile.value);

    if (!isEditMode.value) {
      const res = await apiService.post("/admin/products", productData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
      showToast("success", "Đã tạo nháp. Hãy thêm biến thể!");
      savedProductId = res.data.id || res.data.product_id;
    } else {
      productData.append("_method", "PATCH");
      await apiService.post(`/admin/products/${savedProductId}`, productData, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    }

    if (savedProductId && galleryFiles.value.length > 0) {
      const uploadPromises = galleryFiles.value.map((file) => {
        const imgData = new FormData();
        imgData.append("product_id", savedProductId);
        imgData.append("image", file);
        return apiService.post("/admin/imageProducts", imgData, {
          headers: { "Content-Type": "multipart/form-data" },
        });
      });
      await Promise.all(uploadPromises);
    }

    if (isEditMode.value) {
      for (const variant of formData.variants) {
        const variantData = new FormData();
        variantData.append("product_id", savedProductId);
        variantData.append("original_price", variant.original_price);
        variantData.append("price", variant.price);
        variantData.append("stock", variant.stock);

        if (variant.imageFile) {
          variantData.append("image", variant.imageFile);
        }

        let savedVariantId = variant.variant_id;

        if (isNaN(Number(savedVariantId))) {
          const res = await apiService.post("/admin/variants", variantData, {
            headers: { "Content-Type": "multipart/form-data" }
          });
          savedVariantId = res.data.id;
        } else {
          variantData.append("_method", "PUT");
          await apiService.post(
            `/admin/variants/${savedVariantId}`,
            variantData,
            { headers: { "Content-Type": "multipart/form-data" } }
          );
        }

        if (savedVariantId && Object.keys(variant.attributes).length > 0) {
          await apiService.post(
            `/admin/variants/${savedVariantId}/attributes`,
            { attributes: variant.attributes }
          );
        }
      }
      showToast("success", "Đã lưu thay đổi thành công!");
    }
    modalInstance.value?.hide();
    fetchProducts();
  } catch (e) {
    Swal.fire("Lỗi", e.response?.data?.message || "Lỗi khi lưu.", "error");
  } finally {
    isSaving.value = false;
  }
}

async function handleDelete(product) {
  if (!requireLogin()) return;
  if (
    (
      await Swal.fire({
        title: "Xóa?",
        text: "Không thể hoàn tác!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
      })
    ).isConfirmed
  ) {
    try {
      await apiService.delete(`/admin/products/${product.id}`);
      fetchProducts();
      showToast("success", "Đã xóa sản phẩm");
    } catch (e) {
      Swal.fire("Lỗi", "Không thể xóa", "error");
    }
  }
}

onMounted(async () => {
  await checkAuthState();
  if (!requireLogin()) {
    isLoading.value = false;
    return;
  }

  await Promise.all([fetchCategories(), fetchAttributes(), fetchBrands()]);
  fetchProducts();

  nextTick(() => {
    // [FIXED] Disable focus trap for nested SweetAlert
    if (modalRef.value)
      modalInstance.value = new Modal(modalRef.value, { backdrop: "static", keyboard: false, focus: false });
    if (viewModalRef.value)
      viewModalInstance.value = new Modal(viewModalRef.value);
    if (attrModalRef.value)
      attrModalInstance.value = new Modal(attrModalRef.value, { backdrop: "static" });
    // [NEW] Manage Modal
    if (manageAttrModalRef.value)
      manageAttrModalInstance.value = new Modal(manageAttrModalRef.value, { backdrop: "static" });
  });
});
</script>

<template>
  <div class="app-content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <h3 class="mb-0 text-brand">Quản lý Sản phẩm</h3>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-end">
            <li class="breadcrumb-item">
              <router-link to="/admin">Trang chủ</router-link>
            </li>
            <li class="breadcrumb-item active">Sản phẩm</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="app-content">
    <div class="container-fluid">
      <div class="card mb-4 shadow-sm border-0">
        <!-- TABS -->
        <div class="card-header border-bottom-0 pb-0 bg-white">
          <ul class="nav nav-tabs card-header-tabs custom-tabs">
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'active' }" href="#"
                @click.prevent="setActiveTab('active')">
                <i class="bi bi-check-circle me-1 text-success"></i> Đang bán
                <span class="badge rounded-pill bg-success ms-2">{{
                  statusCounts.active
                }}</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center" :class="{ active: activeTab === 'inactive' }" href="#"
                @click.prevent="setActiveTab('inactive')">
                <i class="bi bi-eye-slash me-1 text-secondary"></i> Ẩn/Nháp
                <span class="badge rounded-pill bg-secondary ms-2">{{
                  statusCounts.inactive
                }}</span>
              </a>
            </li>
          </ul>
        </div>

        <!-- FILTER BAR -->
        <div class="card-body bg-light border-bottom py-3">
          <div class="row align-items-center g-3">
            <div class="col-md-4 col-12">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" placeholder="Tìm kiếm sản phẩm..."
                  v-model="searchQuery" />
              </div>
            </div>
            <div class="col-md-3 col-6">
              <select class="form-select" v-model="selectedCategory">
                <option value="">Tất cả danh mục</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">
                  {{ c.name }}
                </option>
              </select>
            </div>
            <div class="col-md-3 col-6">
              <select class="form-select" v-model="sortCriteria">
                <option value="product_id-desc">Mới nhất</option>
                <option value="price-asc">Giá tăng dần</option>
                <option value="name-asc">Tên A-Z</option>
              </select>
            </div>
            <div class="col-md-2 col-12 text-end">
              <button class="btn btn-primary w-100 text-nowrap shadow-sm" @click="openCreateModal">
                <i class="bi bi-plus-lg me-1"></i> Thêm mới
              </button>
            </div>
          </div>
        </div>

        <!-- TABLE CONTENT -->
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 custom-table">
              <thead class="bg-light text-secondary">
                <tr>
                  <th class="ps-3" style="width: 60px">ID</th>
                  <th style="width: 80px">Ảnh</th>
                  <th>Tên sản phẩm</th>
                  <th>Danh mục</th>
                  <!-- [ADDED] Brand Column Header -->
                  <th>Thương hiệu</th>
                  <th>Giá bán</th>
                  <th>Kho</th>
                  <th>Trạng thái</th>
                  <th class="text-center pe-3">Thao tác</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="isLoading">
                  <td colspan="9" class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                  </td>
                </tr>
                <tr v-else-if="paginatedProducts.length === 0">
                  <td colspan="9" class="text-center py-5 text-muted fst-italic">
                    Không tìm thấy sản phẩm nào.
                  </td>
                </tr>
                <tr v-else v-for="p in paginatedProducts" :key="p.id">
                  <td class="ps-3 text-muted fw-bold">#{{ p.product_id }}</td>
                  <td>
                    <div class="ratio ratio-1x1 rounded overflow-hidden shadow-sm" style="width: 50px">
                      <img :src="p.thumbnail_url" class="object-fit-cover"
                        onerror="this.src='https://placehold.co/50?text=Err'" />
                    </div>
                  </td>
                  <td>
                    <span class="fw-bold text-dark d-block text-truncate" style="max-width: 250px" :title="p.name">{{
                      p.name }}</span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border">{{
                      p.category?.name || "N/A"
                    }}</span>
                  </td>
                  <!-- [ADDED] Brand Column Data -->
                  <td>
                    <span class="badge bg-white text-secondary border">{{
                      p.brand?.name || "---"
                    }}</span>
                  </td>
                  <td class="text-danger fw-bold">
                    {{ getPriceRange(p.variants) }}
                  </td>
                  <td>{{ calculateTotalStock(p.variants) }}</td>
                  <td>
                    <div class="form-check form-switch d-flex align-items-center" title="Bật/Tắt trạng thái">
                      <div v-if="processingStatusIds.includes(p.id)"
                        class="spinner-border spinner-border-sm text-primary" role="status"></div>
                      <input v-else class="form-check-input custom-switch cursor-pointer" type="checkbox" role="switch"
                        :checked="p.status === 'active'" @click.prevent="handleQuickStatusToggle(p)" />
                    </div>
                  </td>
                  <td class="text-center pe-3">
                    <div class="d-flex justify-content-center gap-1">
                      <button class="btn btn-sm btn-light text-secondary border" @click="openViewModal(p)" title="Xem">
                        <i class="bi bi-eye"></i>
                      </button>
                      <button class="btn btn-sm btn-light text-primary border" @click="openVariantConfigModal(p)"
                        title="Sửa">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-sm btn-light text-danger border" @click="handleDelete(p)" title="Xóa">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="card-footer bg-white border-top-0 py-3" v-if="totalPages > 1">
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted">Hiển thị {{ paginatedProducts.length }} sản phẩm</small>
              <ul class="pagination pagination-sm m-0">
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                  <button class="page-link border-0" @click="goToPage(currentPage - 1)">
                    <i class="bi bi-chevron-left"></i>
                  </button>
                </li>
                <li class="page-item disabled">
                  <span class="page-link border-0 text-dark">{{ currentPage }} / {{ totalPages }}</span>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                  <button class="page-link border-0" @click="goToPage(currentPage + 1)">
                    <i class="bi bi-chevron-right"></i>
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Product (Create/Edit) -->
  <!-- [FIXED] data-bs-focus="false" prevents focus trapping -->
  <div class="modal fade" id="productModal" ref="modalRef" data-bs-backdrop="static" data-bs-keyboard="false"
    data-bs-focus="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold text-brand">
            {{
              isEditMode ? "Cấu hình Sản phẩm & Biến thể" : "Thêm Sản phẩm mới"
            }}
          </h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-4">
            <!-- Left Column: Info -->
            <div class="col-lg-4 border-end">
              <h6 class="fw-bold text-secondary mb-3 text-uppercase small">
                Thông tin chung
              </h6>
              <div class="mb-3">
                <label class="form-label required fw-bold">Tên sản phẩm</label>
                <input type="text" class="form-control" v-model="formData.name" :class="{ 'is-invalid': errors.name }"
                  placeholder="Nhập tên sản phẩm..." />
                <div class="invalid-feedback">{{ errors.name }}</div>
              </div>

              <!-- CATEGORY SELECT -->
              <div class="mb-3">
                <label class="form-label required fw-bold">Danh mục</label>
                <select class="form-select" v-model="formData.category_id"
                  :class="{ 'is-invalid': errors.category_id }">
                  <option :value="null">-- Chọn danh mục --</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">
                    {{ c.name }}
                  </option>
                </select>
                <div class="invalid-feedback">{{ errors.category_id }}</div>
              </div>

              <!-- [ADDED] BRAND SELECT -->
              <div class="mb-3">
                <label class="form-label fw-bold">Thương hiệu</label>
                <select class="form-select" v-model="formData.brand_id" :class="{ 'is-invalid': errors.brand_id }">
                  <option :value="null">-- Không chọn --</option>
                  <option v-for="b in brands" :key="b.id" :value="b.id">
                    {{ b.name }}
                  </option>
                </select>
                <div class="invalid-feedback">{{ errors.brand_id }}</div>
              </div>

              <div class="mb-3">
                <label class="form-label fw-bold">Mô tả</label>
                <textarea class="form-control" :class="{ 'is-invalid': errors.description }" rows="4"
                  v-model="formData.description" placeholder="Mô tả chi tiết..."></textarea>
                <div class="invalid-feedback">{{ errors.description }}</div>
              </div>
              <div class="mb-3" v-if="isEditMode">
                <label class="form-label fw-bold">Trạng thái</label>
                <select class="form-select" v-model="formData.status">
                  <option value="active">Đang bán</option>
                  <option value="inactive">Ẩn / Nháp</option>
                </select>
              </div>
              <div class="p-3 bg-light rounded border border-dashed text-center">
                <label class="form-label fw-bold mb-2">Ảnh đại diện</label>
                <div v-if="thumbnailPreview" class="mb-2 position-relative d-inline-block">
                  <img :src="thumbnailPreview" class="img-thumbnail" style="height: 150px; object-fit: contain" />
                </div>
                <input type="file" class="form-control form-control-sm mt-2" accept="image/*"
                  @change="handleThumbnailChange" />
                <div class="form-text small mt-1">
                  Tối thiểu 500x500px, Max 5MB.
                </div>
              </div>
            </div>

            <!-- Right Column: Variants & Gallery OR Live Preview -->
            <div class="col-lg-8">
              <!-- Live Preview for New Product -->
              <div v-if="!isEditMode" class="h-100">
                <div class="card shadow-sm border-0 h-100">
                  <div class="card-header bg-white border-bottom-0 pt-3 ps-3">
                    <h6 class="fw-bold text-muted text-uppercase small mb-0">
                      <i class="bi bi-eye me-1"></i> Xem trước (Live Preview)
                    </h6>
                  </div>
                  <div
                    class="card-body d-flex flex-column justify-content-center align-items-center bg-light m-3 rounded border border-dashed">
                    <!-- Actual Card Preview -->
                    <div class="card shadow-sm border-0" style="width: 18rem; overflow: hidden">
                      <div class="ratio ratio-4x3 bg-white">
                        <img :src="thumbnailPreview ||
                          'https://placehold.co/300x200?text=Sản+Phẩm'
                          " class="card-img-top object-fit-contain p-3" />
                      </div>
                      <div class="card-body text-start">
                        <div class="mb-2">
                          <span
                            class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10">
                            {{
                              categories.find(
                                (c) => c.id == formData.category_id
                              )?.name || "Danh mục"
                            }}
                          </span>
                          <!-- [ADDED] Preview Brand -->
                          <span v-if="formData.brand_id"
                            class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10 ms-1">
                            {{brands.find(b => b.id == formData.brand_id)?.name}}
                          </span>
                        </div>
                        <h5 class="card-title fw-bold text-dark mb-1 d-block text-truncate w-100"
                          :title="formData.name">
                          {{ formData.name || "Tên sản phẩm..." }}
                        </h5>
                        <p class="card-text small text-muted mb-3 d-block text-truncate w-100">
                          {{
                            formData.description ||
                            "Mô tả ngắn của sản phẩm sẽ hiển thị tại đây..."
                          }}
                        </p>
                      </div>
                    </div>

                    <div class="mt-4 text-center">
                      <p class="text-muted small mb-2">
                        <i class="bi bi-info-circle me-1"></i> Sau khi lưu, bạn
                        có thể thêm biến thể màu sắc, size...
                      </p>
                      <button class="btn btn-primary px-4 shadow-sm" @click="handleSave" :disabled="isSaving">
                        <span v-if="isSaving" class="spinner-border spinner-border-sm me-1"></span>
                        Lưu & Tiếp tục
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Edit Mode Content (Variants & Gallery) -->
              <div v-else>
                <!-- Gallery -->
                <div class="mb-4">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-secondary text-uppercase small mb-0">
                      Thư viện ảnh
                    </h6>
                    <div v-if="formData.existing_images.length > 0">
                      <input class="form-check-input cursor-pointer me-2" type="checkbox" id="selectAll"
                        v-model="isAllImagesSelected" />
                      <label class="form-check-label small me-3 cursor-pointer" for="selectAll">Chọn tất cả</label>
                      <button v-if="selectedImageIds.length > 0" class="btn btn-xs btn-danger"
                        @click="handleBulkDeleteImages" :disabled="isBulkDeleting">
                        <i class="bi bi-trash me-1"></i> Xóa ({{
                          selectedImageIds.length
                        }})
                      </button>
                    </div>
                  </div>
                  <div class="p-3 border rounded bg-white">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                      <div v-for="(img, idx) in formData.existing_images" :key="img.id" class="position-relative">
                        <img :src="img.url" class="img-thumbnail" :class="{
                          'border-primary shadow-sm':
                            selectedImageIds.includes(img.id),
                        }" style="
                            width: 80px;
                            height: 80px;
                            object-fit: cover;
                            cursor: pointer;
                          " @click="
                            selectedImageIds.includes(img.id)
                              ? (selectedImageIds = selectedImageIds.filter(
                                (id) => id !== img.id
                              ))
                              : selectedImageIds.push(img.id)
                            " />
                        <div class="position-absolute top-0 start-0 m-1">
                          <input type="checkbox" class="form-check-input" :value="img.id" v-model="selectedImageIds"
                            @click.stop />
                        </div>
                      </div>
                      <div v-for="(url, idx) in galleryPreviews" :key="'new-' + idx" class="position-relative">
                        <img :src="url" class="img-thumbnail border-success"
                          style="width: 80px; height: 80px; object-fit: cover" />
                        <button
                          class="btn btn-sm btn-secondary rounded-circle position-absolute top-0 start-100 translate-middle p-0"
                          style="width: 20px; height: 20px; line-height: 1" @click="removeNewGalleryImage(idx)">
                          &times;
                        </button>
                      </div>
                      <label
                        class="border border-dashed rounded d-flex align-items-center justify-content-center cursor-pointer bg-light text-muted hover-bg-gray"
                        style="width: 80px; height: 80px">
                        <div class="text-center">
                          <i class="bi bi-plus-lg fs-4"></i>
                          <div style="font-size: 10px">Thêm ảnh</div>
                        </div>
                        <input type="file" class="d-none" multiple accept="image/*" @change="handleGalleryChange" />
                      </label>
                    </div>
                  </div>
                </div>

                <!-- Variants Table -->
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-secondary text-uppercase small mb-0">
                      Cấu hình biến thể
                    </h6>
                    <div class="d-flex gap-2 align-items-center">
                      <select class="form-select form-select-sm w-auto" v-model="selectedAttributeId">
                        <option value="">+ Thêm cột thuộc tính</option>
                        <option v-for="a in attributesList" :key="a.id" :value="a.id">
                          {{ a.name }}
                        </option>
                      </select>
                      <!-- [UPDATED] Action Buttons -->
                      <button class="btn btn-sm btn-primary" @click="addSelectedAttributeToForm"
                        :disabled="!selectedAttributeId">
                        Thêm
                      </button>
                      <button class="btn btn-sm btn-outline-success" @click="openCreateAttrModal">
                        Tạo mới
                      </button>
                      <!-- [NEW] Manage Button -->
                      <button class="btn btn-sm btn-outline-secondary" @click="openManageAttrModal"
                        title="Quản lý / Sửa / Xóa thuộc tính gốc">
                        <i class="bi bi-gear-fill"></i> Quản lý
                      </button>
                    </div>
                  </div>
                  <div class="table-responsive border rounded">
                    <table class="table table-sm table-bordered text-center align-middle mb-0 bg-white">
                      <thead class="bg-light">
                        <tr>
                          <!-- [NEW] Cột Ảnh -->
                          <th style="width: 80px">Ảnh</th>
                          <!-- [UPDATED] Render cột thuộc tính -->
                          <th v-for="d in formData.attribute_definitions" :key="d.id" style="min-width: 120px">
                            <div class="d-flex align-items-center justify-content-between px-2">
                              <span>{{ d.name }}</span>
                              <!-- Only "Remove Column" button remains here -->
                              <i class="bi bi-x-circle text-danger cursor-pointer ms-2"
                                title="Gỡ bỏ cột này (không xóa khỏi hệ thống)"
                                @click="removeAttributeDefinition(d)"></i>
                            </div>
                          </th>
                          <th style="width: 120px">Giá gốc</th>
                          <th style="width: 120px">Giá bán</th>
                          <th style="width: 100px">Kho</th>
                          <th style="width: 50px">
                            <i class="bi bi-trash"></i>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(v, i) in formData.variants" :key="i"
                          :class="{ 'table-danger': duplicateRowIndices.includes(i) }">
                          <!-- [NEW] Ô chọn ảnh cho từng dòng biến thể -->
                          <td>
                            <label
                              class="ratio ratio-1x1 d-block border rounded overflow-hidden cursor-pointer position-relative bg-light"
                              style="width: 50px; height: 50px;">
                              <img v-if="v.imagePreview" :src="v.imagePreview" class="w-100 h-100 object-fit-cover"
                                onerror="this.src='https://placehold.co/50?text=Err'" />
                              <div v-else
                                class="d-flex align-items-center justify-content-center w-100 h-100 text-muted">
                                <i class="bi bi-camera"></i>
                              </div>
                              <input type="file" class="d-none" accept="image/*"
                                @change="handleVariantImageChange($event, i)" />
                            </label>
                          </td>
                          <td v-for="d in formData.attribute_definitions" :key="d.id">
                            <input type="text" class="form-control form-control-sm text-center border-0 bg-transparent"
                              v-model="v.attributes[d.name]" placeholder="..." />
                          </td>
                          <td>
                            <input type="number" class="form-control form-control-sm text-end"
                              v-model="v.original_price" />
                          </td>
                          <td>
                            <input type="number" class="form-control form-control-sm text-end fw-bold text-primary"
                              v-model="v.price" />
                          </td>
                          <td>
                            <input type="number" class="form-control form-control-sm text-center" v-model="v.stock" />
                          </td>
                          <td>
                            <button class="btn btn-sm text-danger" @click="removeVariantRow(i)">
                              &times;
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <button class="btn btn-sm btn-light border w-100 mt-2 text-primary fw-bold" @click="addVariantRow">
                    <i class="bi bi-plus-lg"></i> Thêm biến thể
                  </button>
                  <div class="text-danger small mt-2" v-if="errors.variants">
                    <i class="bi bi-exclamation-triangle me-1"></i> {{ errors.variants }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light border-top-0">
          <button class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
            Đóng
          </button>
          <!-- Hide save button in create mode as it's inside the preview area -->
          <button v-if="isEditMode" class="btn btn-primary px-4" @click="handleSave" :disabled="isSaving">
            <span v-if="isSaving" class="spinner-border spinner-border-sm me-1"></span>
            Lưu thay đổi
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Helper Modals -->
  <div class="modal fade" id="attrModal" ref="attrModalRef" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <div class="modal-content shadow">
        <div class="modal-header bg-success text-white py-2">
          <h6 class="modal-title mb-0">Tạo thuộc tính mới</h6>
          <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="text" class="form-control" v-model="newQuickAttrName" placeholder="VD: Màu sắc, Size..." />
        </div>
        <div class="modal-footer p-2">
          <button class="btn btn-sm btn-success w-100" @click="handleCreateQuickAttribute" :disabled="isCreatingAttr">
            {{ isCreatingAttr ? "..." : "Tạo ngay" }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- [NEW] Manage Attribute Modal (Edit/Delete) -->
  <div class="modal fade" id="manageAttrModal" ref="manageAttrModalRef" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content shadow">
        <div class="modal-header bg-secondary text-white py-2">
          <h6 class="modal-title mb-0">Quản lý Thuộc tính hệ thống</h6>
          <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Chọn thuộc tính cần sửa:</label>
            <select class="form-select" v-model="managingAttrId">
              <option value="">-- Chọn thuộc tính --</option>
              <option v-for="a in attributesList" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>
          <div v-if="managingAttrId">
            <label class="form-label small fw-bold">Tên hiển thị mới:</label>
            <input type="text" class="form-control" v-model="managingAttrName" placeholder="Nhập tên mới..." />
            <div class="form-text small">Thay đổi này sẽ cập nhật tên thuộc tính trên toàn hệ thống.</div>
          </div>
        </div>
        <div class="modal-footer p-2 d-flex justify-content-between" v-if="managingAttrId">
          <button class="btn btn-sm btn-danger" @click="handleDeleteGlobalAttribute" :disabled="isManagingAttr">
            <span v-if="isManagingAttr" class="spinner-border spinner-border-sm me-1"></span>
            Xóa vĩnh viễn
          </button>
          <button class="btn btn-sm btn-primary" @click="handleUpdateGlobalAttribute"
            :disabled="isManagingAttr || !managingAttrName.trim()">
            <span v-if="isManagingAttr" class="spinner-border spinner-border-sm me-1"></span>
            Cập nhật
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- [UPDATED] View Detail Modal: modal-xl -->
  <div class="modal fade" id="viewModal" ref="viewModalRef" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header border-bottom-0 pb-0">
          <h5 class="modal-title fw-bold text-secondary">Chi tiết sản phẩm</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-4">
            <!-- Left: Images -->
            <div class="col-lg-5">
              <div
                class="border rounded bg-light d-flex align-items-center justify-content-center mb-3 position-relative"
                style="height: 400px; overflow: hidden;">
                <img :src="selectedViewImage || viewingProduct.thumbnail_url" class="img-fluid object-fit-contain"
                  style="max-height: 100%; max-width: 100%;"
                  onerror="this.src='https://placehold.co/400x400?text=No+Image'" />
              </div>
              <!-- Gallery Thumbnails -->
              <div class="d-flex gap-2 overflow-auto pb-2"
                v-if="viewingProduct.images && viewingProduct.images.length > 0">
                <div class="border rounded cursor-pointer flex-shrink-0"
                  :class="{ 'border-primary shadow-sm': selectedViewImage === viewingProduct.thumbnail_url }"
                  style="width: 70px; height: 70px;" @click="selectedViewImage = viewingProduct.thumbnail_url">
                  <img :src="viewingProduct.thumbnail_url" class="w-100 h-100 object-fit-cover rounded" />
                </div>
                <div v-for="(img, idx) in viewingProduct.images" :key="idx"
                  class="border rounded cursor-pointer flex-shrink-0"
                  :class="{ 'border-primary shadow-sm': selectedViewImage === img }" style="width: 70px; height: 70px;"
                  @click="selectedViewImage = img">
                  <img :src="img" class="w-100 h-100 object-fit-cover rounded" />
                </div>
              </div>
            </div>

            <!-- Right: Details -->
            <div class="col-lg-7">
              <div>
                <span class="badge bg-primary me-2">{{ viewingProduct.categoryName }}</span>
                <span v-if="viewingProduct.brandName && viewingProduct.brandName !== 'N/A'"
                  class="badge bg-secondary">{{ viewingProduct.brandName }}</span>
              </div>

              <h3 class="fw-bold mt-2 mb-2 text-dark">{{ viewingProduct.name }}</h3>

              <!-- [NEW] Rating -->
              <div class="d-flex align-items-center mb-3 text-warning">
                <span class="fw-bold me-1 text-dark">{{ viewingProduct.average_rating || 0 }}</span>
                <i v-for="n in 5" :key="n" class="bi"
                  :class="n <= Math.round(viewingProduct.average_rating) ? 'bi-star-fill' : 'bi-star'"></i>
                <span class="text-muted ms-2 small">({{ viewingProduct.review_count || 0 }} đánh giá)</span>
              </div>

              <h2 class="text-danger fw-bold mb-3">
                {{ viewingProduct.priceRange }}
              </h2>

              <div class="alert alert-light border">
                <strong>Mô tả:</strong>
                <p class="mb-0 small text-muted" style="white-space: pre-line; max-height: 150px; overflow-y: auto;">
                  {{ viewingProduct.description || 'Chưa có mô tả.' }}
                </p>
              </div>

              <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
                <h6 class="fw-bold text-uppercase text-secondary mb-0">
                  Danh sách biến thể ({{ viewingProduct.variants.length }})
                </h6>
                <span class="badge bg-light text-dark border">Tổng kho: {{ viewingProduct.totalStock }}</span>
              </div>

              <!-- [UPDATED] Variants Table View -->
              <div class="table-responsive border rounded" style="max-height: 300px;">
                <table class="table table-hover align-middle mb-0">
                  <thead class="bg-light sticky-top">
                    <tr>
                      <th style="width: 60px;">Ảnh</th>
                      <th>Thuộc tính</th>
                      <th class="text-end">Giá bán</th>
                      <th class="text-end">Giá gốc</th>
                      <th class="text-center">Kho</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="v in viewingProduct.variants" :key="v.id">
                      <td>
                        <img :src="v.imageUrl || 'https://placehold.co/50?text=NoImg'"
                          class="rounded border object-fit-cover" style="width: 50px; height: 50px;"
                          onerror="this.src='https://placehold.co/50?text=Err'" />
                      </td>
                      <td>
                        <div class="d-flex flex-wrap gap-1">
                          <span v-for="(val, key) in v.attributes" :key="key" class="badge bg-white text-dark border">
                            {{ key }}: {{ val }}
                          </span>
                        </div>
                      </td>
                      <td class="text-end fw-bold text-danger">{{ formatCurrency(v.price) }}</td>
                      <td class="text-end text-muted small text-decoration-line-through">{{
                        formatCurrency(v.original_price) }}</td>
                      <td class="text-center fw-bold">{{ v.stock }}</td>
                    </tr>
                    <tr v-if="!viewingProduct.variants || viewingProduct.variants.length === 0">
                      <td colspan="5" class="text-center py-3 text-muted">Không có biến thể nào.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* COLORS */
.text-brand {
  color: #009981 !important;
}

.text-primary {
  color: #009981 !important;
}

.bg-primary {
  background-color: #009981 !important;
}

.btn-primary {
  background-color: #009981 !important;
  border-color: #009981 !important;
  color: white !important;
}

.btn-primary:hover {
  background-color: #007a67 !important;
  border-color: #007a67 !important;
}

.btn-outline-primary {
  color: #009981 !important;
  border-color: #009981 !important;
}

.btn-outline-primary:hover {
  background-color: #009981 !important;
  color: white !important;
}

.btn-success {
  background-color: #009981 !important;
  border-color: #009981 !important;
  color: white !important;
}

.btn-success:hover {
  background-color: #007a67 !important;
  border-color: #007a67 !important;
}

/* TABS */
.custom-tabs .nav-link {
  color: #6c757d;
  border: none;
  font-weight: 500;
  padding: 12px 20px;
  border-bottom: 3px solid transparent;
  cursor: pointer;
}

.custom-tabs .nav-link:hover {
  color: #009981;
}

.custom-tabs .nav-link.active {
  color: #009981;
  background: transparent;
  border-bottom: 3px solid #009981;
}

/* COMPONENTS */
.form-label.required::after {
  content: " *";
  color: red;
}

.cursor-pointer {
  cursor: pointer;
}

.custom-switch:checked {
  background-color: #009981;
  border-color: #009981;
}

:deep(.table-striped-columns) tbody tr td:nth-child(even) {
  background-color: rgba(0, 0, 0, 0.02);
}

.custom-table th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.85rem;
  letter-spacing: 0.5px;
}

.hover-bg-gray:hover {
  background-color: #e9ecef !important;
}

/* Highlight duplicate rows */
.table-danger {
  background-color: #ffe6e6 !important;
}

/* Subtle input in table */
.form-control.bg-transparent {
  background-color: transparent !important;
}

/* Sticky Header for detail table */
.sticky-top {
  position: sticky;
  top: 0;
  z-index: 1020;
}
</style>