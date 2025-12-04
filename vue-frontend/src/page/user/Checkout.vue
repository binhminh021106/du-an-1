<script setup>
import { ref, reactive, onMounted, watch, computed, nextTick } from "vue";
import { useStore } from "vuex";
import { useRouter } from "vue-router";
import apiService from '../../apiService.js'; // Import API Service để gọi backend
import Swal from 'sweetalert2';

const store = useStore();
const router = useRouter();

// --- 1. XỬ LÝ DỮ LIỆU TỪ VUEX & LOCALSTORAGE ---
const selectedIds = ref([]);

// Lấy danh sách từ getter
const allCartItems = computed(() => store.getters.cartItems || []);


// --- HELPER: LẤY TÊN BIẾN THỂ AN TOÀN ---
const getVariantLabel = (item) => {
  if (item.variantName && item.variantName !== 'Mặc định') return item.variantName;
  if (item.variant_name && item.variant_name !== 'Mặc định') return item.variant_name;
  if (item.sku) return `SKU: ${item.sku}`;
  if (Array.isArray(item.attributes) && item.attributes.length > 0) {
    return item.attributes.map(a => `${a.name || ''}: ${a.value || ''}`).join(' - ');
  }
  return null;
};
// FIX 1: Logic lọc sản phẩm & Hiển thị biến thể
const cartItems = computed(() => {
  if (!selectedIds.value || selectedIds.value.length === 0) return [];
  if (!allCartItems.value || allCartItems.value.length === 0) return [];

  return allCartItems.value.filter(item => {
    return selectedIds.value.some(id => String(id) === String(item.cartId));
  }).map(item => {
    // Xử lý hiển thị biến thể: Nếu có attributesMap (từ ProductDetail lưu vào), format thành chuỗi đẹp
    let variantInfo = '';
    if (item.attributesMap) {
      // Ví dụ: { "Màu": "Đỏ", "Size": "XL" } -> "Màu: Đỏ, Size: XL"
      variantInfo = Object.entries(item.attributesMap)
        .map(([key, val]) => `${key}: ${val}`)
        .join(', ');
    } else if (item.variantName && item.variantName !== 'Mặc định') {
      variantInfo = item.variantName;
    }

    return {
      ...item,
      displayVariant: variantInfo // Field mới để hiển thị
    };
  });
});

// FIX 2: Tính tổng tiền an toàn
const subtotal = computed(() => {
  return cartItems.value.reduce((total, item) => {
    let price = item.price;
    if (typeof price === 'string') {
      price = parseFloat(price.replace(/[^0-9.]/g, ""));
    }
    return total + (Number(price) * Number(item.qty));
  }, 0);
});

const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/70x70?text=No+Img';
  if (path.startsWith('http') || path.startsWith('blob:')) return path;
  const SERVER_URL = 'http://127.0.0.1:8000';
  const cleanPath = path.startsWith('/') ? path : '/' + path;
  return `${SERVER_URL}${cleanPath}`;
};

// Actions tương tác với Vuex
const increaseQuantity = (item) => {
  if (item.stock && item.qty >= item.stock) {
    Swal.fire('Thông báo', `Sản phẩm này chỉ còn tối đa ${item.stock} món.`, 'warning');
    return;
  }
  store.dispatch('updateItemQty', { cartId: item.cartId, qty: item.qty + 1 });
};

const decreaseQuantity = (item) => {
  if (item.qty > 1) {
    store.dispatch('updateItemQty', { cartId: item.cartId, qty: item.qty - 1 });
  }
};

const onRemoveItemLocal = (cartId) => {
  store.dispatch('removeItem', cartId);
  selectedIds.value = selectedIds.value.filter(id => String(id) !== String(cartId));
  localStorage.setItem('checkout_items', JSON.stringify(selectedIds.value));

  if (cartItems.value.length === 0) {
    Swal.fire('Giỏ hàng trống', 'Bạn đã xóa hết sản phẩm thanh toán.', 'info').then(() => {
      router.push('/cart');
    });
  }
};

// --- CONFIG UI ---
const shippingFees = {
  "Thành phố Hà Nội": 50000,
  "Thành phố Hồ Chí Minh": 30000,
  "Thành phố Đà Nẵng": 40000,
  "Tỉnh Đắk Lắk": 0,
};
const paymentMethods = [
  { code: "COD", name: "Thanh toán khi nhận hàng (COD)", icon: "fa-box-open" },
  { code: "BANK", name: "Chuyển khoản ngân hàng", icon: "fa-building-columns" },
  { code: "CARD", name: "VN Pay", icon: "fa-credit-card" },
];

const form = reactive({
  name: "",
  email: "",
  phone: "",
  address: { province: "", district: "", ward: "", street: "" },
  paymentMethod: "COD", // Default
});

const provinces = ref([]);
const districts = ref([]);
const wards = ref([]);
const selectedProvince = ref("");
const selectedDistrict = ref("");
const selectedWard = ref("");
const errors = reactive({ name: "", email: "", phone: "", address: "", paymentMethod: "" });
const shippingCost = ref(0);

const savedAddresses = ref([]);
const selectedSavedAddressId = ref("");

// COUPON STATE
const coupons = ref([]); // Danh sách coupon từ API
const couponCode = ref("");
const discountAmount = ref(0);
const appliedCoupon = ref(null); // Lưu object coupon đã áp dụng
const couponMessage = ref("");
const isCouponLoading = ref(false);

const showModal = ref(false);
const modalContent = ref({});
const isSubmitting = ref(false); // Loading khi submit đơn

// --- ON MOUNTED ---
onMounted(async () => {
  // 1. Load Checkout IDs
  const storedIds = localStorage.getItem('checkout_items');
  if (storedIds) {
    try {
      selectedIds.value = JSON.parse(storedIds);
    } catch (e) { console.error(e); }
  }

  if (!selectedIds.value || selectedIds.value.length === 0) {
    Swal.fire('Lỗi', 'Vui lòng chọn sản phẩm từ giỏ hàng trước!', 'error').then(() => router.push('/cart'));
    return;
  }

  // 2. Load User Data
  const userDataString = localStorage.getItem("userData");
  if (userDataString) {
    try {
      const parsed = JSON.parse(userDataString);
      const userData = parsed.user || parsed.data || parsed;
      form.name = userData.fullName || userData.name || "";
      form.email = userData.email || "";
      form.phone = userData.phone || "";
      if (userData.user_addresses && Array.isArray(userData.user_addresses)) {
        savedAddresses.value = userData.user_addresses;
      }
    } catch (e) { console.error(e); }
  }

  // 3. Load Provinces
  try {
    const res = await fetch("https://provinces.open-api.vn/api/?depth=3");
    provinces.value = await res.json();
  } catch (e) { console.error(e); }

  // 4. [NEW] Load Coupons from DB
  fetchCoupons();
});

const fetchCoupons = async () => {
  try {
    const res = await apiService.get('/coupons'); // API lấy danh sách coupon
    // Giả sử backend trả về { data: [...] } hoặc mảng trực tiếp
    const data = res.data.data || res.data;
    if (Array.isArray(data)) {
      // Chỉ lấy coupon còn hạn và còn lượt dùng
      const now = new Date();
      coupons.value = data.filter(c => {
        const expired = c.expires_at ? new Date(c.expires_at) < now : false;
        const outOfStock = c.usage_limit !== null && c.usage_count >= c.usage_limit;
        return !expired && !outOfStock;
      });
    }
  } catch (e) {
    console.error("Lỗi tải mã giảm giá:", e);
  }
};

// Logic Address Book
const fillAddressFromBook = async () => {
  if (!selectedSavedAddressId.value) return;
  const addr = savedAddresses.value.find(a => a.id === selectedSavedAddressId.value);
  if (addr) {
    form.name = addr.customer_name;
    form.phone = addr.customer_phone;
    form.address.street = addr.shipping_address;
    selectedProvince.value = addr.city;
    await nextTick();
    const p = provinces.value.find((p) => p.name === addr.city);
    if (p) {
      districts.value = p.districts;
      selectedDistrict.value = addr.district;
      await nextTick();
      const d = districts.value.find((d) => d.name === addr.district);
      if (d) {
        wards.value = d.wards;
        selectedWard.value = addr.ward;
      }
    }
  }
};

// Watchers Address
watch(selectedProvince, (val) => {
  const p = provinces.value.find((p) => p.name === val);
  districts.value = p ? p.districts : [];
  if (selectedSavedAddressId.value === "") {
    selectedDistrict.value = ""; wards.value = []; selectedWard.value = "";
  }
  shippingCost.value = shippingFees[val] ?? 30000;
  // Re-check coupon if total changed due to shipping (if coupon covers shipping)
  if (appliedCoupon.value) applyCouponLogic(appliedCoupon.value);
});

watch(selectedDistrict, (val) => {
  const p = provinces.value.find((p) => p.name === selectedProvince.value);
  const d = p?.districts.find((d) => d.name === val);
  wards.value = d ? d.wards : [];
  if (selectedSavedAddressId.value === "") selectedWard.value = "";
});

watch([selectedProvince, selectedDistrict, selectedWard, () => form.address.street], () => {
  form.address.province = selectedProvince.value;
  form.address.district = selectedDistrict.value;
  form.address.ward = selectedWard.value;
});

// [NEW] Coupon Logic Realtime
const applyCouponLogic = (coupon) => {
  couponMessage.value = "";
  discountAmount.value = 0;
  appliedCoupon.value = null;

  // 1. Check Min Spend
  if (coupon.min_spend && subtotal.value < coupon.min_spend) {
    couponMessage.value = `Đơn hàng chưa đủ ${Number(coupon.min_spend).toLocaleString()}đ để dùng mã này.`;
    return;
  }

  // 2. Calculate Discount
  let discount = 0;
  if (coupon.type === 'percent') {
    discount = subtotal.value * (coupon.value / 100);
    // Có thể thêm max_discount_amount nếu DB có trường này
  } else if (coupon.type === 'fixed') {
    discount = coupon.value;
  }

  // 3. Apply
  discountAmount.value = discount;
  appliedCoupon.value = coupon;
  couponMessage.value = `Đã áp dụng: ${coupon.name} (-${discount.toLocaleString()}đ)`;
};

const handleApplyCouponCode = () => {
  const code = couponCode.value.trim().toUpperCase();
  if (!code) return;

  // Tìm trong danh sách đã load (hoặc gọi API verify nếu cần bảo mật hơn)
  const found = coupons.value.find(c => c.code.toUpperCase() === code);

  if (found) {
    applyCouponLogic(found);
  } else {
    couponMessage.value = "Mã giảm giá không tồn tại hoặc đã hết hạn.";
    discountAmount.value = 0;
    appliedCoupon.value = null;
  }
};

const quickApplyCoupon = (coupon) => {
  couponCode.value = coupon.code;
  applyCouponLogic(coupon);
};

const totalPrice = computed(() => {
  let total = subtotal.value + shippingCost.value - discountAmount.value;
  return total > 0 ? total : 0;
});

// --- VALIDATION & SUBMIT ---
const validateForm = () => {
  let valid = true;
  errors.name = errors.email = errors.phone = errors.address = errors.paymentMethod = "";

  if (!form.name.trim()) { errors.name = "Vui lòng nhập họ tên."; valid = false; }

  const emailRegex = /^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,}$/;
  if (!form.email.trim()) { errors.email = "Vui lòng nhập email."; valid = false; }
  else if (!emailRegex.test(form.email)) { errors.email = "Email không hợp lệ."; valid = false; }

  const phoneRegex = /^(0[0-9]{9,10})$/;
  if (!form.phone.trim()) { errors.phone = "Vui lòng nhập số điện thoại."; valid = false; }
  else if (!phoneRegex.test(form.phone)) { errors.phone = "Số điện thoại không hợp lệ."; valid = false; }

  if (!form.address.province || !form.address.district || !form.address.ward || !form.address.street.trim()) {
    errors.address = "Vui lòng nhập đầy đủ địa chỉ giao hàng.";
    valid = false;
  }

  if (!form.paymentMethod) { errors.paymentMethod = "Vui lòng chọn phương thức thanh toán."; valid = false; }

  return valid;
};

const confirmCheckout = async () => {
  if (!validateForm()) {
    Swal.fire('Thông tin thiếu sót', 'Vui lòng kiểm tra lại thông tin nhận hàng.', 'error');
    return;
  }

  // [IMPORTANT] Helper làm sạch giá tiền (chuyển "100.000 đ" -> 100000)
  // Đảm bảo dữ liệu gửi lên Controller là Number để khớp với kiểu bigint
  const cleanPrice = (val) => {
    if (typeof val === 'number') return val;
    return parseFloat(String(val).replace(/[^0-9.]/g, "")) || 0;
  };

  // STOCK CHECK (Optional: Call API to check stock again before submit)

  isSubmitting.value = true;

  // Cấu trúc dữ liệu gửi lên API Controller
  const newOrderData = {
    customer_name: form.name,
    customer_email: form.email,
    customer_phone: form.phone,
    shipping_address: `${form.address.street}, ${form.address.ward}, ${form.address.district}, ${form.address.province}`,
    payment_method: form.paymentMethod,
    
    // [FIX] Dùng cleanPrice để đảm bảo gửi số
    shipping_fee: cleanPrice(shippingCost.value),
    discount_amount: cleanPrice(discountAmount.value),
    coupon_code: appliedCoupon.value ? appliedCoupon.value.code : null,
    total_amount: cleanPrice(totalPrice.value),
    
    // 👇 FIX QUAN TRỌNG: LẤY ĐÚNG PRODUCT_ID VÀ VARIANT_ID
    items: cartItems.value.map(item => {
      // Logic xác định Product ID chuẩn xác:
      // 1. Nếu item từ DB (có quan hệ): Thử lấy item.product_id hoặc item.variant.product_id
      // 2. Nếu item LocalStorage (lưu thẳng): item.id thường chính là product id
      let realProductId = item.product_id; 
      
      if (!realProductId && item.variant && item.variant.product_id) {
          realProductId = item.variant.product_id;
      }
      
      // Fallback cuối cùng cho trường hợp LocalStorage (nơi item.id chính là product id)
      if (!realProductId) {
          realProductId = item.id;
      }

      return {
        product_id: realProductId, 
        
        // Lấy variant_id an toàn từ nhiều nguồn có thể
        variant_id: item.variantId || item.variant_id || (item.variant ? item.variant.id : null), 
        
        quantity: item.qty || item.quantity,
        
        // [FIX] Dùng cleanPrice để đảm bảo giá là số
        price: cleanPrice(item.price),
        
        name: item.name,
        image: item.image || item.image_url || item.thumbnail_url
      };
    })
  };

  try {
    // [NEW] GỌI API TẠO ĐƠN HÀNG THAY VÌ LƯU LOCAL
    const res = await apiService.post('/orders', newOrderData);

    // Nếu thành công
    const orderId = res.data.data?.id || res.data.id || 'N/A'; // Lấy mã đơn từ response
    const paymentMethodName = paymentMethods.find(p => p.code === form.paymentMethod)?.name || form.paymentMethod;

    // [UPDATED] Modal Content theo cấu trúc "cũ" chi tiết hơn
    modalContent.value = {
      title: `Đặt hàng thành công! (Mã: ${orderId})`,
      details: [
        { label: "Người nhận", value: form.name },
        { label: "SĐT", value: form.phone },
        { label: "Email", value: form.email },
        { label: "Địa chỉ", value: `${form.address.street}, ${form.address.ward}, ${form.address.district}, ${form.address.province}` },
        { label: "Phương thức TT", value: paymentMethodName },
      ],
      summary: [
        { label: "Tạm tính", value: `${subtotal.value.toLocaleString()} đ` },
        { label: "Phí vận chuyển", value: `${shippingCost.value.toLocaleString()} đ` },
        { label: "Phí vận chuyển", value: `${shippingCost.value.toLocaleString()} đ` },
        ...(discountAmount.value > 0 ? [{ label: "Giảm giá", value: `-${discountAmount.value.toLocaleString()} đ` }] : []),
        { label: "Tổng cộng", value: `${totalPrice.value.toLocaleString()} đ`, isTotal: true },
      ]
    };

    // Xóa giỏ hàng
    cartItems.value.forEach(item => { store.dispatch('removeItem', item.cartId); });
    localStorage.removeItem('checkout_items');

    showModal.value = true;

  } catch (error) {
    console.error("Lỗi đặt hàng:", error);
    // Hiển thị lỗi chi tiết từ backend nếu có
    const msg = error.response?.data?.message || 'Có lỗi xảy ra, vui lòng thử lại.';
    Swal.fire('Đặt hàng thất bại', msg, 'error');
  } finally {
    isSubmitting.value = false;
  }
};

const closeModal = () => {
  showModal.value = false;
  // Chuyển hướng về trang danh sách đơn hàng hoặc trang chủ
  router.push('/OrderList'); // Đảm bảo route này tồn tại
};
</script>

<template>
  <div class="checkout-page container">
    <h2 class="checkout-title"><i class="fa-solid fa-wallet"></i> Thanh toán</h2>
    <div class="checkout-content">
      <!-- FORM BÊN TRÁI -->
      <div class="checkout-form card">
        <h3>Thông tin giao hàng</h3>
        <form @submit.prevent="confirmCheckout">
          <!-- Sổ địa chỉ -->
          <div v-if="savedAddresses.length > 0" class="form-group">
            <label>Chọn địa chỉ đã lưu</label>
            <select v-model="selectedSavedAddressId" @change="fillAddressFromBook" class="saved-addr-select">
              <option value="">-- Nhập địa chỉ mới --</option>
              <option v-for="addr in savedAddresses" :key="addr.id" :value="addr.id">
                {{ addr.customer_name }} - {{ addr.shipping_address }}
              </option>
            </select>
          </div>

          <div class="row">
            <div class="col-md-6 form-group">
              <label>Họ và tên <span class="text-danger">*</span></label>
              <input type="text" v-model="form.name" placeholder="Nhập họ tên" :class="{ 'is-invalid': errors.name }" />
              <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
            </div>
            <div class="col-md-6 form-group">
              <label>Số điện thoại <span class="text-danger">*</span></label>
              <input type="text" v-model="form.phone" placeholder="Nhập số điện thoại"
                :class="{ 'is-invalid': errors.phone }" />
              <span v-if="errors.phone" class="error-text">{{ errors.phone }}</span>
            </div>
          </div>

          <div class="form-group">
            <label>Email <span class="text-danger">*</span></label>
            <input type="email" v-model="form.email" placeholder="Nhập email nhận thông báo"
              :class="{ 'is-invalid': errors.email }" />
            <span v-if="errors.email" class="error-text">{{ errors.email }}</span>
          </div>

          <div class="form-group">
            <label>Địa chỉ nhận hàng <span class="text-danger">*</span></label>
            <div class="address-select">
              <select v-model="selectedProvince">
                <option disabled value="">Tỉnh/Thành phố</option>
                <option v-for="p in provinces" :key="p.code" :value="p.name">{{ p.name }}</option>
              </select>
              <select v-model="selectedDistrict" :disabled="!districts.length">
                <option disabled value="">Quận/Huyện</option>
                <option v-for="d in districts" :key="d.code" :value="d.name">{{ d.name }}</option>
              </select>
              <select v-model="selectedWard" :disabled="!wards.length">
                <option disabled value="">Phường/Xã</option>
                <option v-for="w in wards" :key="w.code" :value="w.name">{{ w.name }}</option>
              </select>
            </div>
            <input type="text" v-model="form.address.street" placeholder="Số nhà, tên đường..." class="mt-2"
              :class="{ 'is-invalid': errors.address }" />
            <span v-if="errors.address" class="error-text">{{ errors.address }}</span>
          </div>

          <div class="checkout-form-section">
            <h3>Phương thức thanh toán</h3>
            <div class="payment-methods-grid">
              <label v-for="method in paymentMethods" :key="method.code" class="payment-option"
                :class="{ 'selected': form.paymentMethod === method.code }">
                <input type="radio" v-model="form.paymentMethod" :value="method.code" name="paymentMethod" />
                <div class="option-content">
                  <i :class="['fa-solid', method.icon]"></i> {{ method.name }}
                </div>
              </label>
            </div>
            <p v-if="errors.paymentMethod" class="error-text">{{ errors.paymentMethod }}</p>
          </div>
        </form>
      </div>

      <!-- ORDER SUMMARY BÊN PHẢI -->
      <div class="checkout-summary card">
        <h3>Đơn hàng của bạn</h3>
        <ul class="cart-items-list">
          <li v-for="item in cartItems" :key="item.cartId" class="cart-item-summary">
            <!-- Hình ảnh: Cập nhật src để nhận cả item.image -->
            <div class="item-img-wrapper">
              <img :src="getImageUrl(item.image || item.image_url)" :alt="item.name"
                @error="$event.target.src = 'https://placehold.co/70x70?text=No+Img'" />
              <span class="item-qty-badge">{{ item.qty }}</span>
            </div>


            <div class="item-details-summary">
              <div class="item-name">{{ item.name }}</div>

              <!-- HIỂN THỊ BIẾN THỂ -->
              <div v-if="item.displayVariant" class="item-variant-badge">
                {{ item.displayVariant }}
              </div>

              <!-- THÊM NÚT TĂNG GIẢM -->
              <div class="item-quantity-controls mt-2">
                <button type="button" @click="decreaseQuantity(item)" :disabled="item.qty <= 1" class="qty-btn-inline">
                  −
                </button>
                <span class="qty-number-inline">{{ item.qty }}</span>
                <button type="button" @click="increaseQuantity(item)" class="qty-btn-inline">
                  +
                </button>
              </div>
              <div class=" item-variants" v-if="getVariantLabel(item)">
                <span class="variant-badge">
                  <i class="fa-solid fa-layer-group"></i> {{ getVariantLabel(item) }}
                </span>
              </div>
            </div>

            <div class="item-info-right">
              <div class="item-price">
                {{ (Number(item.price) * Number(item.qty)).toLocaleString() }} đ
              </div>
              <!-- NÚT XÓA -->
              <button type="button" @click="onRemoveItemLocal(item.cartId)" class=" btn btn-outline-danger">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>

          </li>
        </ul>

        <div class="summary-divider"></div>

        <div class="summary-line">
          <span>Tạm tính:</span>
          <strong>{{ subtotal.toLocaleString() }} đ</strong>
        </div>

        <div class="summary-line">
          <span>Phí vận chuyển:</span>
          <strong>{{ shippingCost === 0 ? "Miễn phí" : shippingCost.toLocaleString() + " đ" }}</strong>
        </div>

        <div v-if="discountAmount > 0" class="summary-line discount">
          <span>Giảm giá (Voucher):</span>
          <strong>-{{ discountAmount.toLocaleString() }} đ</strong>
        </div>

        <div class="summary-line total">
          <span>Tổng cộng:</span>
          <span class="total-amount">{{ totalPrice.toLocaleString() }} đ</span>
        </div>

        <!-- KHU VỰC NHẬP MÃ GIẢM GIÁ -->
        <div class="coupon-section">
          <label><i class="fa-solid fa-ticket"></i> Mã giảm giá</label>
          <div class="coupon-input-group">
            <input type="text" v-model="couponCode" placeholder="Nhập mã giảm giá"
              @keyup.enter="handleApplyCouponCode" />
            <button type="button" @click="handleApplyCouponCode" :disabled="!couponCode">Áp dụng</button>
          </div>
          <p v-if="couponMessage" :class="{ 'success-msg': appliedCoupon, 'error-msg': !appliedCoupon }">
            <i :class="appliedCoupon ? 'fa-solid fa-check-circle' : 'fa-solid fa-circle-exclamation'"></i> {{
              couponMessage }}
          </p>
        </div>

        <!-- LIST MÃ GIẢM GIÁ TỪ DB -->
        <div class="available-vouchers-container" v-if="coupons.length > 0">
          <div class="voucher-header">Mã ưu đãi dành cho bạn</div>
          <div class="voucher-list">
            <div v-for="cp in coupons" :key="cp.code" class="voucher-ticket"
              :class="{ 'active': appliedCoupon?.code === cp.code }" @click="quickApplyCoupon(cp)">
              <div class="voucher-left">
                <div class="voucher-code">{{ cp.code }}</div>
                <div class="voucher-label">{{ cp.name || cp.code }}</div>
                <div class="voucher-desc-left" v-if="cp.min_spend">Đơn từ {{ Number(cp.min_spend).toLocaleString() }}đ
                </div>
              </div>
              <div class="voucher-right">
                <div class="voucher-desc">
                  <span v-if="cp.type === 'percent'">Giảm {{ cp.value }}%</span>
                  <span v-else>Giảm {{ Number(cp.value).toLocaleString() }}đ</span>
                </div>

                <span class="apply-tag" v-if="appliedCoupon?.code !== cp.code">Dùng ngay</span>
                <span class="apply-tag used" v-else><i class="fa-solid fa-check"></i> Đã chọn</span>
              </div>
              <!-- Decor -->
              <div class="circle-notch top"></div>
              <div class="circle-notch bottom"></div>
            </div>
          </div>
        </div>

        <button type="button" @click="confirmCheckout" class="checkout-btn" :disabled="isSubmitting">
          <span v-if="isSubmitting"><i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...</span>
          <span v-else>Đặt hàng ngay</span>
        </button>
      </div>
    </div>

    <!-- MODAL SUCCESS - Đã phục hồi giao diện chi tiết -->
    <div v-if="showModal" class="custom-modal-overlay">
      <div class="custom-modal-content">
        <button @click="closeModal" class="modal-close-btn">&times;</button>

        <div class="modal-header">
          <h4 style="color: #009981; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-square-check"></i> {{ modalContent.title }}
          </h4>
        </div>

        <div class="modal-body">
          <!-- Chi tiết khách hàng -->
          <div class="modal-details">
            <p v-for="d in modalContent.details" :key="d.label" style="margin-bottom: 8px;">
              <strong>{{ d.label }}:</strong> {{ d.value }}
            </p>
          </div>

          <!-- Tóm tắt chi phí -->
          <div class="modal-summary" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
            <div v-for="sum in modalContent.summary" :key="sum.label"
              :class="['summary-line-modal', { 'total-modal': sum.isTotal }]">
              <strong>{{ sum.label }}:</strong>
              <span>{{ sum.value }}</span>
            </div>
          </div>

          <button @click="closeModal" class="modal-ok-btn" style="background-color: #009981;">Đóng</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.checkout-page {
  padding: 40px 20px;
  background: #f8f9fa;
  min-height: 100vh;
  color: #333;
}

.checkout-title {
  color: #111;
  font-weight: 700;
  font-size: 28px;
  margin-bottom: 25px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.checkout-title i {
  color: #009981;
}

.checkout-content {
  display: flex;
  gap: 30px;
  flex-wrap: wrap;
}

/* Card Styles */
.card {
  background: #fff;
  padding: 30px;
  border-radius: 16px;
  box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
  flex: 1;
  min-width: 320px;
  border: 1px solid #eee;
}

.checkout-form {
  flex: 1.5;
}

.checkout-summary {
  flex: 1;
  height: fit-content;
  position: sticky;
  top: 20px;
}

h3 {
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: none;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #222;
  position: relative;
}

/* [NEW] Dấu gạch chân xanh rõ ràng - Đã làm cho nó dài hơn */
h3::after {
  content: '';
  display: block;
  width: 100%;
  /* Kéo dài line gần full width của tiêu đề */
  height: 3px;
  background-color: #009981;
  position: absolute;
  bottom: 0;
  left: 0;
  border-radius: 2px;
}

.checkout-form-section h3::after {
  width: 100%;
}

.checkout-summary h3::after {
  width: 100%;
}

/* Form Styles */
.form-group {
  margin-bottom: 18px;
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: 500;
  margin-bottom: 6px;
  font-size: 14px;
  color: #444;
}

input,
select,
textarea {
  padding: 12px 15px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.3s ease;
  font-family: inherit;
  background-color: #fff;
}

input:focus,
select:focus,
textarea:focus {
  border-color: #009981;
  outline: none;
  box-shadow: 0 0 0 3px rgba(0, 153, 129, 0.1);
}

.is-invalid {
  border-color: #e74c3c !important;
  background-color: #fff8f8;
}

.error-text {
  color: #e74c3c;
  font-size: 12px;
  margin-top: 4px;
  display: block;
}

.address-select {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.address-select select {
  flex: 1;
  min-width: 140px;
}

/* Payment Methods */
.payment-methods-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 15px;
}

.payment-option {
  display: flex;
  align-items: center;
  border: 1px solid #eee;
  padding: 15px;
  border-radius: 10px;
  cursor: pointer;
  background: #fdfdfd;
  transition: all 0.2s;
}

.payment-option:hover {
  background-color: #f0fdfa;
  border-color: #b2f5ea;
}

.payment-option.selected {
  border-color: #009981;
  background-color: #e6fffa;
  box-shadow: 0 0 0 1px #009981 inset;
}

.payment-option input {
  margin-right: 15px;
  accent-color: #009981;
  width: 18px;
  height: 18px;
}

.option-content {
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 10px;
}

.option-content i {
  color: #009981;
  font-size: 18px;
}

/* Order Summary List */
.cart-items-list {
  list-style: none;
  padding: 0;
  margin: 0 0 20px 0;
  max-height: 300px;
  overflow-y: auto;
  padding-right: 5px;
}

.cart-items-list::-webkit-scrollbar {
  width: 4px;
}

.cart-items-list::-webkit-scrollbar-thumb {
  background: #ddd;
  border-radius: 4px;
}

.cart-item-summary {
  display: flex;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px dashed #eee;
}

.item-img-wrapper {
  position: relative;
  width: 80px;
  height: 80px;
  flex-shrink: 0;
}

.item-img-wrapper img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 8px;
  border: 1px solid #eee;
}

.item-qty-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  background: #666;
  color: #fff;
  font-size: 10px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  border: 2px solid #fff;
}

.item-details-summary {
  flex: 1;
  padding: 0 10px;
}

.item-name {
  font-weight: 500;
  font-size: 14px;
  line-height: 1.4;
  color: #333;
  margin-bottom: 4px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.item-variant-badge {
  display: inline-block;
  background: #f1f3f5;
  color: #666;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 500;
}

/* [NEW] Inline Quantity Controls */
.item-quantity-controls {
  display: flex;
  align-items: center;
  gap: 0;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  width: 90px;
  height: 30px;
}

.qty-btn-inline {
  width: 30px;
  height: 30px;
  border: none;
  background: transparent;
  color: #009981;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.1s;
  line-height: 1;
  padding: 0;
  display: flex;
  justify-content: center;
  align-items: center;
}

.qty-btn-inline:hover {
  background-color: #f0fdfa;
}

.qty-btn-inline:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.qty-number-inline {
  width: 30px;
  text-align: center;
  font-weight: 600;
  font-size: 14px;
  color: #333;
  /* Kẻ dọc nhẹ nhàng */
  border-left: 1px solid #e0e0e0;
  border-right: 1px solid #e0e0e0;
}

.item-info-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
  min-width: 80px;
}

.item-price {
  font-weight: 600;
  font-size: 14px;
  color: #333;
}

.remove-item-btn-inline {
  border: none;
  background: none;
  color: #e74c3c;
  font-size: 20px;
  cursor: pointer;
  transition: color 0.2s;
  line-height: 1;
}

.remove-item-btn-inline:hover {
  color: #c0392b;
}


/* Summary Totals */
.summary-divider {
  height: 1px;
  background: #eee;
  margin: 15px 0;
}

.summary-line {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  margin-bottom: 10px;
  color: #555;
}

.summary-line strong {
  color: #333;
}

.summary-line.total {
  font-size: 18px;
  color: #009981;
  border-top: 2px solid #f0f0f0;
  padding-top: 15px;
  margin-top: 15px;
  align-items: center;
}

.total-amount {
  font-size: 22px;
  font-weight: 700;
}

.summary-line.discount strong {
  color: #e74c3c;
}

/* Coupon Section */
.coupon-section {
  margin-top: 25px;
  padding-top: 20px;
  border-top: 1px dashed #ddd;
}

.coupon-input-group {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}

.coupon-input-group input {
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 1px;
}

/* [UPDATED] Button color */
.coupon-input-group button {
  padding: 0 20px;
  background: #009981;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: 0.2s;
}

.coupon-input-group button:hover {
  background: #007a67;
}

.coupon-input-group button:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.success-msg {
  color: #009981;
  font-size: 13px;
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 5px;
  font-weight: 500;
}

.error-msg {
  color: #e74c3c;
  font-size: 13px;
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 5px;
}

/* Voucher Tickets */
.available-vouchers-container {
  margin-top: 20px;
}

.voucher-header {
  font-size: 13px;
  font-weight: 600;
  color: #888;
  text-transform: uppercase;
  margin-bottom: 10px;
}

.voucher-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 250px;
  overflow-y: auto;
}

.voucher-ticket {
  display: flex;
  justify-content: space-between;
  align-items: stretch;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 0;
  cursor: pointer;
  position: relative;
  transition: all 0.2s;
  overflow: visible;
}

.voucher-ticket:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
  border-color: #009981;
}

.voucher-ticket.active {
  background: #e6fffa;
  border-color: #009981;
}

.voucher-left {
  flex: 1;
  padding: 12px 0 12px 15px;
  min-width: 150px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  border-right: 1px dashed #e0e0e0;
}

.voucher-ticket.active .voucher-left {
  border-right-color: #b2f5ea;
}

.voucher-code {
  font-weight: 700;
  color: #009981;
  font-size: 14px;
}

.voucher-label {
  font-size: 13px;
  color: #555;
  margin-top: 2px;
}

.voucher-desc-left {
  font-size: 11px;
  color: #999;
  margin-top: 4px;
}


.voucher-right {
  flex-shrink: 0;
  width: 100px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 10px 8px;
  background: #f8f8f8;
  border-top-right-radius: 8px;
  border-bottom-right-radius: 8px;
}

.voucher-ticket.active .voucher-right {
  background: #e6fffa;
}

.voucher-desc {
  font-size: 11px;
  color: #777;
  margin-bottom: 6px;
}

/* [FIX] Đảm bảo nội dung voucher-desc không bị tràn nút */
.voucher-right {
  position: relative;
  /* Tạo context cho absolute positioning */
  width: 100px;
  padding: 10px 8px;
}

.voucher-desc {
  z-index: 2;
  /* Đưa nội dung lên trên notch */
  text-align: center;
  white-space: nowrap;
  /* Giữ mô tả trên 1 hàng (thường là Giảm X%) */
  font-weight: 600;
  color: #333;
}

.voucher-desc span {
  display: block;
  font-size: 12px;
}

.apply-tag {
  font-size: 12px;
  padding: 5px 8px;
  background: #009981;
  color: white;
  border-radius: 6px;
  font-weight: 600;
  width: 90%;
  text-align: center;
  box-shadow: 0 2px 4px rgba(0, 153, 129, 0.2);
  cursor: pointer;
  z-index: 2;
  /* Đưa nút lên trên cùng */
}

.apply-tag.used {
  background: #e67e22;
  box-shadow: none;
}

/* Notch Decor */
.circle-notch {
  position: absolute;
  width: 16px;
  height: 16px;
  background-color: #f8f9fa;
  /* Màu nền trang checkout */
  border-radius: 50%;
  right: 93px;
  z-index: 1;
  border: 1px solid #e0e0e0;
}

.circle-notch.top {
  top: -9px;
  border-bottom-color: transparent;
}

.circle-notch.bottom {
  bottom: -9px;
  border-top-color: transparent;
}

.voucher-ticket:hover .circle-notch {
  background-color: #f0fdfa;
}

.voucher-ticket.active .circle-notch {
  background-color: #f8f9fa;
  border-color: #009981;
}

/* Checkout Button */
.checkout-btn {
  margin-top: 25px;
  padding: 16px;
  width: 100%;
  border: none;
  border-radius: 10px;
  background: #009981;
  color: #fff;
  font-weight: 700;
  font-size: 16px;
  cursor: pointer;
  transition: background 0.3s, transform 0.2s;
  box-shadow: 0 4px 15px rgba(0, 153, 129, 0.3);
}

.checkout-btn:hover:not(:disabled) {
  background: #007a67;
  transform: translateY(-2px);
}

.checkout-btn:disabled {
  background: #a0c4be;
  cursor: not-allowed;
  box-shadow: none;
}

/* Modal Success */
.custom-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  backdrop-filter: blur(2px);
}

.custom-modal-content {
  background: #fff;
  padding: 40px;
  border-radius: 20px;
  width: 90%;
  max-width: 500px;
  position: relative;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
  animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
  from {
    transform: translateY(20px);
    opacity: 0;
  }

  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-close-btn {
  position: absolute;
  top: 15px;
  right: 20px;
  font-size: 24px;
  border: none;
  background: none;
  cursor: pointer;
  color: #999;
}

.modal-close-btn:hover {
  color: #333;
}

.modal-header h4 {
  font-size: 20px;
  font-weight: 700;
  color: #009981;
  margin: 0 0 20px 0;
}

.modal-details p {
  margin: 8px 0;
  color: #555;
  font-size: 14px;
  line-height: 1.5;
}

.summary-line-modal {
  display: flex;
  justify-content: space-between;
  padding: 6px 0;
  font-size: 14px;
}

.summary-line-modal.total-modal {
  font-size: 18px;
  color: #13493f;
  border-top: 2px solid #eee;
  padding-top: 12px;
  margin-top: 8px;
}

.modal-ok-btn {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 8px;
  background: #009981;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  margin-top: 20px;
}

.modal-ok-btn:hover {
  background: #007a67;
}

/* Responsive */
@media (max-width: 768px) {
  .checkout-content {
    flex-direction: column;
  }

  .checkout-summary {
    position: static;
    order: -1;
    margin-bottom: 20px;
  }

  .card {
    padding: 20px;
  }
}
.variant-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f3f4f6;
    color: #4b5563;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 13px;
    border: 1px solid #e5e7eb;
}
.item-variants {
    margin-top: 5px;
}
</style>