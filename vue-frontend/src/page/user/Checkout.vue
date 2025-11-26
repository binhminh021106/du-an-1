<script setup>
import { ref, reactive, onMounted, watch, computed, nextTick } from "vue";
// Giả sử cartStore.js nằm cùng cấp hoặc bạn tự điều chỉnh đường dẫn import nhé
import {
  cart,
  total,
  removeItem,
  updateItemQty,
  saveCart,
} from "./cartStore.js";

// Cart
const cartItems = computed(() => cart.value);
const subtotal = computed(() => total.value);

const increaseQuantity = (item) => {
  updateItemQty(item.cartId, item.qty + 1);
};
const decreaseQuantity = (item) => {
  if (item.qty > 1) updateItemQty(item.cartId, item.qty - 1);
};
const onRemoveItemLocal = (cartId) => {
  removeItem(cartId);
};

// Shipping & Payment
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

// Form
const form = reactive({
  name: "",
  email: "",
  phone: "",
  address: { province: "", district: "", ward: "", street: "" }, // Thêm street
  paymentMethod: "",
});

const provinces = ref([]);
const districts = ref([]);
const wards = ref([]);
const selectedProvince = ref("");
const selectedDistrict = ref("");
const selectedWard = ref("");
const errors = reactive({
  name: "",
  email: "",
  phone: "",
  address: "",
  paymentMethod: "",
});
const shippingCost = ref(0);

// Address Book & Coupon State
const savedAddresses = ref([]);
const selectedSavedAddressId = ref(""); // ID của địa chỉ đang chọn
const couponCode = ref("");
const discountAmount = ref(0);
const appliedCoupon = ref("");
const couponMessage = ref("");

// Modal
const showModal = ref(false);
const modalContent = ref({});

// Load user & provinces
onMounted(async () => {
  const userDataString = localStorage.getItem("userData");
  if (userDataString) {
    try {
      const parsed = JSON.parse(userDataString);
      const userData = parsed.user || parsed.data || parsed;

      console.log("User Data for Checkout:", userData);

      form.name = userData.fullName || userData.name || "";
      form.email = userData.email || "";
      form.phone = userData.phone || "";

      // Load danh sách địa chỉ đã lưu
      if (userData.user_addresses && Array.isArray(userData.user_addresses)) {
        savedAddresses.value = userData.user_addresses;
      } else if (userData.addresses && Array.isArray(userData.addresses)) {
        savedAddresses.value = userData.addresses;
      }
    } catch (e) {
      console.error("Lỗi parse user data:", e);
    }
  }

  try {
    const res = await fetch("https://provinces.open-api.vn/api/?depth=3");
    provinces.value = await res.json();
  } catch (e) {
    console.error(e);
  }
});

// Logic điền địa chỉ từ sổ địa chỉ
const fillAddressFromBook = async () => {
  if (!selectedSavedAddressId.value) return; // Nếu chọn "Địa chỉ mới"

  const addr = savedAddresses.value.find(
    (a) => a.id === selectedSavedAddressId.value
  );
  if (addr) {
    form.name = addr.customer_name;
    form.phone = addr.customer_phone;
    form.address.street = addr.shipping_address; // Địa chỉ cụ thể

    // Điền Tỉnh -> Trigger watch -> Điền Huyện -> Trigger watch -> Điền Xã
    // Cần xử lý kỹ để tránh watcher reset dữ liệu
    selectedProvince.value = addr.city;

    // Đợi watcher province chạy xong mới set district
    await nextTick();
    // Tìm province object để set districts thủ công nếu cần (hoặc dựa vào watcher)
    // Ở đây watcher province đã chạy và load districts.value

    // Hack nhẹ: Đôi khi watcher chưa kịp fetch xong nếu là API async thật,
    // nhưng ở đây provinces đã load hết từ đầu nên watcher chạy sync khá nhanh.

    // Cần đảm bảo districts đã có dữ liệu
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
  // Chỉ reset nếu không phải đang trong quá trình auto-fill (đơn giản hoá: cứ reset, hàm fill sẽ set lại sau)
  if (selectedSavedAddressId.value === "") {
    selectedDistrict.value = "";
    wards.value = [];
    selectedWard.value = "";
  }
  shippingCost.value = shippingFees[val] ?? 30000;
});
watch(selectedDistrict, (val) => {
  const p = provinces.value.find((p) => p.name === selectedProvince.value);
  const d = p?.districts.find((d) => d.name === val);
  wards.value = d ? d.wards : [];
  if (selectedSavedAddressId.value === "") {
    selectedWard.value = "";
  }
});
watch(
  [selectedProvince, selectedDistrict, selectedWard, () => form.address.street],
  () => {
    form.address.province = selectedProvince.value;
    form.address.district = selectedDistrict.value;
    form.address.ward = selectedWard.value;
  }
);

// Coupon Logic
const applyCoupon = () => {
  const code = couponCode.value.trim().toUpperCase();
  couponMessage.value = "";
  discountAmount.value = 0;
  appliedCoupon.value = "";

  if (!code) return;

  // Mock logic mã giảm giá
  if (code === "GIAM10") {
    discountAmount.value = subtotal.value * 0.1; // Giảm 10%
    appliedCoupon.value = "GIAM10";
    couponMessage.value = "Đã áp dụng mã giảm 10%!";
  } else if (code === "GIAM20") {
    discountAmount.value = subtotal.value * 0.2; // Giảm 20%
    appliedCoupon.value = "GIAM20";
    couponMessage.value = "Đã áp dụng mã giảm 20%!";
  } else if (code === "FREESHIP") {
    if (shippingCost.value > 0) {
      discountAmount.value = shippingCost.value; // Trừ tiền ship
      appliedCoupon.value = "FREESHIP";
      couponMessage.value = "Đã miễn phí vận chuyển!";
    } else {
      couponMessage.value = "Đơn này đã được freeship sẵn!";
    }
  } else {
    couponMessage.value = "Mã giảm giá không hợp lệ.";
    return;
  }
};

const totalPrice = computed(() => {
  let total = subtotal.value + shippingCost.value - discountAmount.value;
  return total > 0 ? total : 0;
});

// Validate
const validateForm = () => {
  let valid = true;
  errors.name =
    errors.email =
    errors.phone =
    errors.address =
    errors.paymentMethod =
      "";
  if (!form.name.trim()) {
    errors.name = "Vui lòng nhập họ tên.";
    valid = false;
  }
  const emailRegex = /^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,}$/;
  if (!form.email.trim()) {
    errors.email = "Vui lòng nhập email.";
    valid = false;
  } else if (!emailRegex.test(form.email)) {
    errors.email = "Email không hợp lệ.";
    valid = false;
  }
  const phoneRegex = /^(0[0-9]{9,10})$/;
  if (!form.phone.trim()) {
    errors.phone = "Vui lòng nhập số điện thoại.";
    valid = false;
  } else if (!phoneRegex.test(form.phone)) {
    errors.phone = "Số điện thoại không hợp lệ.";
    valid = false;
  }

  if (!form.address.province || !form.address.district || !form.address.ward) {
    errors.address = "Vui lòng chọn đầy đủ Tỉnh/Quận/Phường.";
    valid = false;
  }

  if (!form.paymentMethod) {
    errors.paymentMethod = "Vui lòng chọn phương thức thanh toán.";
    valid = false;
  }
  if (cartItems.value.length === 0) {
    valid = false;
  }
  return valid;
};

// Checkout
const confirmCheckout = () => {
  if (!validateForm()) return;
  const total = totalPrice.value;
  const paymentDetails =
    paymentMethods.find((p) => p.code === form.paymentMethod)?.name ||
    "Không xác định";
  modalContent.value = {
    title: "✅ Đặt hàng thành công!",
    details: [
      { label: "Người nhận", value: form.name },
      { label: "SĐT", value: form.phone },
      { label: "Email", value: form.email },
      {
        label: "Địa chỉ",
        value: `${form.address.street}, ${form.address.ward}, ${form.address.district}, ${form.address.province}`,
      },
      { label: "Phương thức TT", value: paymentDetails },
    ],
    summary: [
      { label: "Tổng sản phẩm", value: subtotal.value.toLocaleString() + " đ" },
      {
        label: "Phí vận chuyển",
        value: shippingCost.value.toLocaleString() + " đ",
      },
      {
        label: "Giảm giá",
        value: `-${discountAmount.value.toLocaleString()} đ`,
      },
      {
        label: "Tổng thanh toán",
        value: total.toLocaleString() + " đ",
        isTotal: true,
      },
    ],
  };
  cart.value.length = 0;
  saveCart();
  showModal.value = true;
};
const closeModal = () => {
  showModal.value = false;
};
</script>

<template>
  <div class="checkout-page container">
    <h2 class="checkout-title">
      <i class="fa-solid fa-wallet"></i> Thanh toán
    </h2>
    <div class="checkout-content">
      <!-- Form -->
      <div class="checkout-form card">
        <h3>Thông tin giao hàng</h3>

        <!-- Chọn địa chỉ đã lưu -->
        <div class="form-group" v-if="savedAddresses.length > 0">
          <label
            ><i class="fa-solid fa-address-book"></i> Chọn từ sổ địa chỉ</label
          >
          <select
            v-model="selectedSavedAddressId"
            @change="fillAddressFromBook"
            class="saved-addr-select"
          >
            <option value="">-- Nhập địa chỉ mới --</option>
            <option
              v-for="addr in savedAddresses"
              :key="addr.id"
              :value="addr.id"
            >
              {{ addr.customer_name }} - {{ addr.shipping_address }},
              {{ addr.ward }}, {{ addr.district }}, {{ addr.city }}
            </option>
          </select>
        </div>

        <form @submit.prevent="confirmCheckout">
          <div class="form-group">
            <label>Họ và tên</label>
            <input type="text" v-model="form.name" placeholder="Nhập họ tên" />
            <p v-if="errors.name" class="error">{{ errors.name }}</p>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" v-model="form.email" placeholder="Nhập email" />
            <p v-if="errors.email" class="error">{{ errors.email }}</p>
          </div>
          <div class="form-group">
            <label>Số điện thoại</label>
            <input
              type="text"
              v-model="form.phone"
              placeholder="Nhập số điện thoại"
            />
            <p v-if="errors.phone" class="error">{{ errors.phone }}</p>
          </div>
          <div class="form-group">
            <label>Địa chỉ nhận hàng</label>
            <div class="address-select">
              <select v-model="selectedProvince">
                <option disabled value="">Tỉnh/Thành phố</option>
                <option v-for="p in provinces" :key="p.code" :value="p.name">
                  {{ p.name }}
                </option>
              </select>
              <select v-model="selectedDistrict" :disabled="!districts.length">
                <option disabled value="">Quận/Huyện</option>
                <option v-for="d in districts" :key="d.code" :value="d.name">
                  {{ d.name }}
                </option>
              </select>
              <select v-model="selectedWard" :disabled="!wards.length">
                <option disabled value="">Phường/Xã</option>
                <option v-for="w in wards" :key="w.code" :value="w.name">
                  {{ w.name }}
                </option>
              </select>
            </div>
            <!-- Thêm ô nhập địa chỉ cụ thể -->
            <input
              type="text"
              v-model="form.address.street"
              placeholder="Số nhà, tên đường..."
              class="mt-2"
            />
            <p v-if="errors.address" class="error">{{ errors.address }}</p>
          </div>

          <!-- Payment -->
          <div class="checkout-form-section">
            <h3>Phương thức thanh toán</h3>
            <div class="payment-methods-grid">
              <label
                v-for="method in paymentMethods"
                :key="method.code"
                class="payment-option"
              >
                <input
                  type="radio"
                  v-model="form.paymentMethod"
                  :value="method.code"
                  name="paymentMethod"
                />
                <div class="option-content">
                  <i :class="['fa-solid', method.icon]"></i> {{ method.name }}
                </div>
              </label>
            </div>
            <p v-if="errors.paymentMethod" class="error">
              {{ errors.paymentMethod }}
            </p>
          </div>

          <div v-if="selectedProvince" class="shipping-fee">
            <strong>Phí vận chuyển:</strong>
            <span>{{
              shippingCost === 0
                ? "Miễn phí"
                : shippingCost.toLocaleString() + " đ"
            }}</span>
          </div>
        </form>
      </div>

      <!-- Order summary -->
      <div class="checkout-summary card">
        <h3>Đơn hàng của bạn</h3>
        <ul>
          <li
            v-for="item in cartItems"
            :key="item.cartId"
            class="cart-item-summary"
          >
            <img
              :src="item.image_url"
              alt="item.name"
              class="item-image-summary"
            />
            <div class="item-details-summary">
              <div class="item-name">{{ item.name }}</div>
              <div class="item-quantity-controls">
                <button
                  @click="decreaseQuantity(item)"
                  :disabled="item.qty <= 1"
                  class="qty-btn"
                >
                  −
                </button>
                <span class="qty-number">{{ item.qty }}</span>
                <button @click="increaseQuantity(item)" class="qty-btn">
                  +
                </button>
              </div>
            </div>
            <div class="item-info-right">
              <div class="item-price">
                {{ (item.price * item.qty).toLocaleString() }} đ
              </div>
              <button
                @click="onRemoveItemLocal(item.cartId)"
                class="remove-item-btn"
              >
                &times;
              </button>
            </div>
          </li>
        </ul>
        <div class="summary-line">
          <strong>Tạm tính:</strong>
          <span>{{ subtotal.toLocaleString() }} đ</span>
        </div>
        <div v-if="selectedProvince" class="shipping-fee">
          <strong>Phí vận chuyển:</strong>
          <span>{{
            shippingCost === 0
              ? "Miễn phí"
              : shippingCost.toLocaleString() + " đ"
          }}</span>
        </div>

        <!-- Hiển thị dòng giảm giá nếu có -->
        <div v-if="discountAmount > 0" class="summary-line discount">
          <strong>Giảm giá ({{ appliedCoupon }}):</strong>
          <span>-{{ discountAmount.toLocaleString() }} đ</span>
        </div>

        <div class="summary-line total">
          <strong>Tổng cộng:</strong>
          <span>{{ totalPrice.toLocaleString() }} đ</span>
        </div>

        <!-- MÃ GIẢM GIÁ (Đặt trên nút xác nhận) -->
        <div class="coupon-section">
          <label>Mã giảm giá</label>
          <div class="coupon-input-group">
            <input
              type="text"
              v-model="couponCode"
              placeholder="Nhập mã (VD: GIAM10)"
            />
            <button type="button" @click="applyCoupon">Áp dụng</button>
          </div>
          <p
            v-if="couponMessage"
            :class="{
              'success-msg': appliedCoupon,
              'error-msg': !appliedCoupon,
            }"
          >
            {{ couponMessage }}
          </p>
        </div>

        <button type="submit" class="checkout-btn">Xác nhận thanh toán</button>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="custom-modal-overlay" @click.self="closeModal">
      <div class="custom-modal-content">
        <button @click="closeModal" class="modal-close-btn">&times;</button>
        <div class="modal-header">
          <h4>{{ modalContent.title }}</h4>
        </div>
        <div class="modal-body">
          <div class="modal-details">
            <p v-for="d in modalContent.details" :key="d.label">
              <strong>{{ d.label }}:</strong> {{ d.value }}
            </p>
          </div>
          <div class="modal-summary">
            <div
              v-for="sum in modalContent.summary"
              :key="sum.label"
              :class="['summary-line-modal', { 'total-modal': sum.isTotal }]"
            >
              <strong>{{ sum.label }}:</strong> <span>{{ sum.value }}</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeModal" class="modal-ok-btn">Hoàn tất</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.checkout-page {
  font-family: Arial, sans-serif;
  padding: 40px 20px;
  background: #f0f2f5;
  min-height: 100vh;
}
.checkout-title {
  color: #009981;
  font-weight: 700;
  font-size: 26px;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 25px;
}
.checkout-content {
  display: flex;
  gap: 30px;
  flex-wrap: wrap;
}
.card {
  background: #fff;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  flex: 1;
}
.checkout-form h3,
.checkout-summary h3 {
  color: #333;
  border-bottom: 2px solid #009981;
  padding-bottom: 10px;
  margin-bottom: 20px;
  font-size: 20px;
}
.form-group {
  display: flex;
  flex-direction: column;
  margin-bottom: 18px;
}
.form-group label {
  font-weight: bold;
  margin-bottom: 5px;
  font-size: 14px;
  color: #555;
}
.form-group input,
.address-select select,
.saved-addr-select {
  padding: 10px 14px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.3s;
}
.form-group input:focus,
.address-select select:focus,
.saved-addr-select:focus {
  border-color: #009981;
  outline: none;
  box-shadow: 0 0 0 2px rgba(0, 153, 129, 0.2);
}
.address-select {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.address-select select {
  flex-grow: 1;
  min-width: 150px;
}
.mt-2 {
  margin-top: 10px;
}
.error {
  color: #e74c3c;
  font-size: 13px;
  margin-top: 4px;
}
.payment-methods-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 10px;
}
.payment-option {
  display: flex;
  align-items: center;
  border: 2px solid #eee;
  padding: 12px;
  border-radius: 8px;
  cursor: pointer;
  background: #fefefe;
}
.payment-option:hover {
  border-color: #009981;
}
.payment-option input[type="radio"] {
  margin-right: 12px;
  accent-color: #009981;
}
.option-content {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  color: #333;
}
.shipping-fee {
  display: flex;
  justify-content: space-between;
  font-weight: bold;
  margin-top: 15px;
}
.checkout-btn {
  margin-top: 25px;
  padding: 12px;
  width: 100%;
  border: none;
  border-radius: 8px;
  background: #009981;
  color: #fff;
  font-weight: bold;
  cursor: pointer;
  font-size: 16px;
  transition: background 0.3s;
}
.checkout-btn:hover {
  background: #006e61;
}
.cart-item-summary {
  display: flex;
  align-items: center;
  gap: 15px;
  border-bottom: 1px solid #eee;
  padding: 10px 0;
}
.item-image-summary {
  width: 60px;
  height: 60px;
  border-radius: 8px;
  object-fit: cover;
  border: 1px solid #eee;
}
.item-details-summary {
  flex-grow: 1;
}
.item-name {
  font-weight: 500;
  margin-bottom: 6px;
}
.item-quantity-controls {
  display: flex;
  align-items: center;
  gap: 6px;
}
.item-quantity-controls button {
  width: 24px;
  height: 24px;
  cursor: pointer;
}
.item-info-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}
.item-price {
  font-weight: 600;
}
.remove-item-btn {
  border: none;
  background: none;
  color: #e74c3c;
  font-size: 16px;
  cursor: pointer;
}
.summary-line {
  display: flex;
  justify-content: space-between;
  font-weight: bold;
  margin-top: 10px;
}
.summary-line.total {
  font-size: 16px;
  color: #13493f;
}
.summary-line.discount {
  color: #e74c3c;
}

/* Coupon Styles */
.coupon-section {
  margin-top: 20px;
  border-top: 1px solid #eee;
  padding-top: 15px;
}
.coupon-input-group {
  display: flex;
  gap: 10px;
  margin-top: 5px;
}
.coupon-input-group input {
  flex: 1;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 8px;
}
.coupon-input-group button {
  padding: 0 20px;
  background: #333;
  color: #fff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
}
.coupon-input-group button:hover {
  background: #555;
}
.success-msg {
  color: #009981;
  font-size: 13px;
  margin-top: 5px;
  font-weight: 500;
}
.error-msg {
  color: #e74c3c;
  font-size: 13px;
  margin-top: 5px;
}

/* Saved Address Select */
.saved-addr-select {
  width: 100%;
  background-color: #f9f9f9;
  border-color: #009981;
  color: #333;
  font-weight: 500;
}

.custom-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}
.custom-modal-content {
  background: #fff;
  padding: 20px;
  border-radius: 10px;
  width: 100%;
  max-width: 480px;
  position: relative;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.14);
}
.modal-close-btn {
  position: absolute;
  top: 8px;
  right: 12px;
  font-size: 22px;
  border: none;
  background: none;
  cursor: pointer;
}
.modal-ok-btn {
  margin-top: 12px;
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 8px;
  background: #009981;
  color: #fff;
  font-weight: bold;
  cursor: pointer;
}
.modal-ok-btn:hover {
  background: #006e61;
}
.item-quantity-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}
.qty-btn {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 20%;
  background-color: #e0f2f1;
  color: #009981;
  font-size: 18px;
  font-weight: bold;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}
.qty-btn:hover:not(:disabled) {
  background-color: #009981;
  color: #fff;
  transform: scale(1.1);
}
.qty-btn:disabled {
  background-color: #ddd;
  color: #aaa;
  cursor: not-allowed;
}
.qty-number {
  min-width: 24px;
  text-align: center;
  font-weight: 500;
  font-size: 16px;
}
</style>
