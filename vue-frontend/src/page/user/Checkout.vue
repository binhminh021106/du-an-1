<script setup>
import { ref, reactive, onMounted, watch, computed } from "vue";

// 🛒 Giỏ hàng demo
const cartItems = ref([
  { name: "Tay cầm chơi game PS5 DualSense", price: 1800000, quantity: 2 },
  { name: "Xiaomi 15 Pro", price: 14500000, quantity: 1 },
]);

// 💰 Tổng tiền hàng
const subtotal = computed(() =>
  cartItems.value.reduce((sum, item) => sum + item.price * item.quantity, 0)
);

// 🚚 Phí ship theo tỉnh
const shippingFees = {
  "Hà Nội": 15000,
  "TP Hồ Chí Minh": 20000,
  "Đà Nẵng": 25000,
};

// 💳 Phương thức thanh toán
const paymentMethods = [
  { code: "COD", name: "Thanh toán khi nhận hàng (COD)", icon: "fa-box-open" },
  { code: "BANK", name: "Chuyển khoản ngân hàng", icon: "fa-building-columns" },
  { code: "CARD", name: "Thẻ Tín dụng/Ghi nợ", icon: "fa-credit-card" },
];

// 📦 Thông tin form
const form = reactive({
  name: "",
  email: "",
  phone: "",
  address: {
    province: "",
    district: "",
    ward: "",
  },
  paymentMethod: "", // Thêm trường phương thức thanh toán
});

// 🗺️ Dữ liệu địa chỉ VN
const provinces = ref([]);
const districts = ref([]);
const wards = ref([]);
const selectedProvince = ref("");
const selectedDistrict = ref("");
const selectedWard = ref("");

// ⚠️ Lỗi form
const errors = reactive({
  name: "",
  email: "",
  phone: "",
  address: "",
  paymentMethod: "", // Thêm trường lỗi cho phương thức thanh toán
});

// 🚛 Phí vận chuyển hiện tại
const shippingCost = ref(0);

// 💬 Custom Modal State (thay thế alert)
const showModal = ref(false);
const modalContent = ref({});

// 🧭 Lấy danh sách địa chỉ VN
onMounted(async () => {
  try {
    const res = await fetch("https://provinces.open-api.vn/api/?depth=3");
    provinces.value = await res.json();
  } catch (err) {
    console.error("Không thể tải địa chỉ:", err);
  }
});

// Khi chọn tỉnh → load quận/huyện & cập nhật phí ship
watch(selectedProvince, (val) => {
  const province = provinces.value.find((p) => p.name === val);
  districts.value = province ? province.districts : [];
  selectedDistrict.value = "";
  wards.value = [];
  selectedWard.value = "";

  // 🚛 Cập nhật phí ship
  shippingCost.value = shippingFees[val] ?? 30000; // Mặc định 30k nếu không nằm trong 3 tỉnh trên
});

// Khi chọn quận → load phường/xã
watch(selectedDistrict, (val) => {
  const province = provinces.value.find((p) => p.name === selectedProvince.value);
  const district = province?.districts.find((d) => d.name === val);
  wards.value = district ? district.wards : [];
  selectedWard.value = "";
});

// Cập nhật form địa chỉ
watch([selectedProvince, selectedDistrict, selectedWard], () => {
  form.address = {
    province: selectedProvince.value,
    district: selectedDistrict.value,
    ward: selectedWard.value,
  };
});

// 💡 Tổng thanh toán cuối cùng
const totalPrice = computed(() => subtotal.value + shippingCost.value);

// ✅ Kiểm tra form
const validateForm = () => {
  let valid = true;
  errors.name = "";
  errors.email = "";
  errors.phone = "";
  errors.address = "";
  errors.paymentMethod = "";

  if (!form.name.trim()) {
    errors.name = "Vui lòng nhập họ tên.";
    valid = false;
  }

  // Sửa regex email: chỉ cần 1 dấu \\
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
    errors.phone = "Số điện thoại không hợp lệ (phải bắt đầu bằng 0 và có 10–11 số).";
    valid = false;
  }

  if (!form.address.province || !form.address.district || !form.address.ward) {
    errors.address = "Vui lòng chọn đầy đủ Tỉnh/Quận/Phường.";
    valid = false;
  }
  
  // Kiểm tra phương thức thanh toán
  if (!form.paymentMethod) {
    errors.paymentMethod = "Vui lòng chọn phương thức thanh toán.";
    valid = false;
  }

  return valid;
};

// 💳 Xác nhận thanh toán & Hiển thị Modal
const confirmCheckout = () => {
  if (!validateForm()) return;

  const total = totalPrice.value;
  const paymentDetails = paymentMethods.find(p => p.code === form.paymentMethod)?.name || 'Không xác định';

  // Chuẩn bị nội dung cho modal
  modalContent.value = {
    title: "✅ Đặt hàng thành công!",
    details: [
      { label: "Người nhận", value: form.name },
      { label: "SĐT", value: form.phone },
      { label: "Email", value: form.email },
      { label: "Địa chỉ", value: `${form.address.ward}, ${form.address.district}, ${form.address.province}` },
      { label: "Phương thức TT", value: paymentDetails },
    ],
    summary: [
      { label: "Tổng sản phẩm", value: subtotal.value.toLocaleString() + " đ" },
      { label: "Phí vận chuyển", value: shippingCost.value.toLocaleString() + " đ" },
      { label: "Tổng thanh toán", value: total.toLocaleString() + " đ", isTotal: true },
    ]
  };

  showModal.value = true;
};

// Đóng Modal
const closeModal = () => {
    showModal.value = false;
}
</script>

<template>
  <div class="checkout-page container">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <h2 class="checkout-title">
      <i class="fa-solid fa-credit-card"></i> Thanh toán
    </h2>

    <div class="checkout-content">
      <!-- 🧍‍♀️ Form -->
      <div class="checkout-form">
        <h3>Thông tin giao hàng</h3>
        <form @submit.prevent="confirmCheckout">
          <!-- Form Group: Họ tên -->
          <div class="form-group">
            <label>Họ và tên</label>
            <input type="text" v-model="form.name" placeholder="Nhập họ tên của bạn" />
            <p v-if="errors.name" class="error">{{ errors.name }}</p>
          </div>

          <!-- Form Group: Email -->
          <div class="form-group">
            <label>Email</label>
            <input type="email" v-model="form.email" placeholder="Nhập email" />
            <p v-if="errors.email" class="error">{{ errors.email }}</p>
          </div>

          <!-- Form Group: Điện thoại -->
          <div class="form-group">
            <label>Số điện thoại</label>
            <input type="text" v-model="form.phone" placeholder="Nhập số điện thoại" />
            <p v-if="errors.phone" class="error">{{ errors.phone }}</p>
          </div>

          <!-- Form Group: Địa chỉ -->
          <div class="form-group">
            <label>Địa chỉ</label>
            <div class="address-select">
              <select v-model="selectedProvince">
                <option disabled value="">Chọn Tỉnh/Thành phố</option>
                <option v-for="p in provinces" :key="p.code" :value="p.name">{{ p.name }}</option>
              </select>

              <select v-model="selectedDistrict" :disabled="!districts.length">
                <option disabled value="">Chọn Quận/Huyện</option>
                <option v-for="d in districts" :key="d.code" :value="d.name">{{ d.name }}</option>
              </select>

              <select v-model="selectedWard" :disabled="!wards.length">
                <option disabled value="">Chọn Phường/Xã</option>
                <option v-for="w in wards" :key="w.code" :value="w.name">{{ w.name }}</option>
              </select>
            </div>
            <p v-if="errors.address" class="error">{{ errors.address }}</p>
          </div>

          <!-- Phần chọn phương thức thanh toán -->
          <div class="checkout-form-section">
            <h3>Phương thức thanh toán</h3>
            <div class="payment-methods-grid">
              <label v-for="method in paymentMethods" :key="method.code" class="payment-option">
                <input type="radio" :value="method.code" v-model="form.paymentMethod" name="paymentMethod" />
                <div class="option-content">
                  <i :class="['fa-solid', method.icon, 'text-xl']"></i>
                  <span>{{ method.name }}</span>
                </div>
              </label>
            </div>
            <p v-if="errors.paymentMethod" class="error">{{ errors.paymentMethod }}</p>
          </div>

          <!-- Phí vận chuyển và nút thanh toán -->
          <div v-if="selectedProvince" class="shipping-fee">
            <strong>Phí vận chuyển:</strong>
            <span>{{ shippingCost.toLocaleString() }} đ</span>
          </div>

          <button type="submit" class="checkout-btn">Xác nhận thanh toán</button>
        </form>
      </div>

      <!-- 🧾 Đơn hàng -->
      <div class="checkout-summary">
        <h3>Đơn hàng của bạn</h3>
        <ul>
          <li v-for="item in cartItems" :key="item.name">
            <div class="item-name">
              {{ item.name }} <span>(x{{ item.quantity }})</span>
            </div>
            <div class="item-price">
              {{ (item.price * item.quantity).toLocaleString() }} đ
            </div>
          </li>
        </ul>

        <hr />
        <div class="summary-line">
          <strong>Tạm tính:</strong>
          <span>{{ subtotal.toLocaleString() }} đ</span>
        </div>
        <div class="summary-line">
          <strong>Phí vận chuyển:</strong>
          <span>{{ shippingCost.toLocaleString() }} đ</span>
        </div>
        <div class="summary-line total">
          <strong>Tổng cộng:</strong>
          <span>{{ totalPrice.toLocaleString() }} đ</span>
        </div>
      </div>
    </div>

    <!-- Custom Success Modal (thay thế alert) -->
    <div v-if="showModal" class="custom-modal-overlay" @click.self="closeModal">
      <div class="custom-modal-content">
        <button @click="closeModal" class="modal-close-btn">&times;</button>
        <div class="modal-header">
          <h4>{{ modalContent.title }}</h4>
        </div>
        <div class="modal-body">
          <div class="modal-details">
            <p v-for="detail in modalContent.details" :key="detail.label">
              <strong>{{ detail.label }}:</strong> {{ detail.value }}
            </p>
          </div>
          <div class="modal-summary">
            <h5 class="font-bold mt-4 mb-2">Thông tin thanh toán:</h5>
            <div v-for="sum in modalContent.summary" :key="sum.label" :class="['summary-line-modal', { 'total-modal': sum.isTotal }]">
              <strong>{{ sum.label }}:</strong>
              <span>{{ sum.value }}</span>
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
/* GENERAL STYLES */
.checkout-page {
  font-family: "Arial", sans-serif;
  padding: 40px 20px;
  background-color: #f0f2f5;
  min-height: 100vh;
}

.checkout-title {
  color: #009981;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 26px;
  margin-bottom: 25px;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.05);
}

.checkout-content {
  display: flex;
  gap: 30px;
  flex-wrap: wrap;
}

.checkout-form, .checkout-summary {
  padding: 25px 30px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.checkout-form {
  flex: 1 1 550px; /* Base for larger screens */
  background: #fff;
}

.checkout-summary {
  flex: 1 1 300px;
  background: #fff;
  border: 1px solid #e0e0e0;
}

.checkout-form h3, .checkout-summary h3 {
  color: #333;
  border-bottom: 2px solid #009981;
  padding-bottom: 10px;
  margin-bottom: 20px;
  font-size: 20px;
}

/* FORM ELEMENTS */
.form-group {
  margin-bottom: 18px;
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: bold;
  margin-bottom: 5px;
  color: #555;
  font-size: 14px;
}

.form-group input,
.address-select select {
  padding: 10px 14px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.3s;
}

.form-group input:focus,
.address-select select:focus {
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

.error {
  color: #e74c3c;
  font-size: 13px;
  margin-top: 4px;
}

/* PAYMENT METHODS SECTION */
.checkout-form-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px dashed #ccc;
}
.payment-methods-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.payment-option {
    display: flex;
    align-items: center;
    border: 2px solid #eee;
    padding: 12px 15px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: #fefefe;
}
.payment-option:hover {
    border-color: #009981;
}
.payment-option input[type="radio"] {
    margin-right: 15px;
    accent-color: #009981;
    width: 16px;
    height: 16px;
}
.payment-option input[type="radio"]:checked + .option-content {
    color: #009981;
    font-weight: bold;
}
.option-content {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 15px;
    color: #333;
}
.option-content i {
    color: #009981;
    width: 20px;
    text-align: center;
}

/* SUMMARY & FOOTER */
.shipping-fee {
  margin-top: 20px;
  font-weight: bold;
  color: #333;
  display: flex;
  justify-content: space-between;
  padding: 5px 0;
}

.checkout-btn {
  background-color: #009981;
  color: #fff;
  font-weight: bold;
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  margin-top: 25px;
  width: 100%;
  font-size: 16px;
  transition: background-color 0.3s, transform 0.1s;
}

.checkout-btn:hover {
  background-color: #006e61;
  transform: translateY(-1px);
}
.checkout-btn:active {
  transform: translateY(1px);
}


/* ORDER SUMMARY */
.checkout-summary ul {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
}
.checkout-summary ul li {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dotted #e0e0e0;
    font-size: 14px;
    color: #555;
}
.item-name span {
    font-size: 12px;
    color: #999;
    margin-left: 5px;
}
.summary-line {
  display: flex;
  justify-content: space-between;
  margin: 10px 0;
  font-size: 15px;
}

.total {
  font-size: 18px;
  font-weight: bold;
  color: #009981;
  border-top: 2px solid #009981;
  padding-top: 15px;
  margin-top: 15px;
}


/* CUSTOM SUCCESS MODAL STYLES */
.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.custom-modal-content {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 500px;
    position: relative;
    animation: fadeIn 0.3s ease-out;
}

.modal-close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #aaa;
}

.modal-header h4 {
    color: #009981;
    font-size: 24px;
    margin-bottom: 20px;
    text-align: center;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.modal-body {
    margin-bottom: 20px;
}

.modal-details p {
    margin: 6px 0;
    font-size: 15px;
    color: #333;
}
.modal-details strong {
    display: inline-block;
    min-width: 120px;
}

.summary-line-modal {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    font-size: 15px;
}

.total-modal {
    font-size: 18px;
    font-weight: bold;
    color: #009981;
    border-top: 1px dashed #ccc;
    padding-top: 10px;
    margin-top: 10px;
}

.modal-footer {
    text-align: center;
}

.modal-ok-btn {
    background-color: #009981;
    color: #fff;
    font-weight: bold;
    padding: 10px 30px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s;
}
.modal-ok-btn:hover {
    background-color: #006e61;
}

@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

/* RESPONSIVENESS */
@media (max-width: 1024px) {
    .checkout-content {
        flex-direction: column;
    }
    .checkout-form, .checkout-summary {
        flex-basis: 100%;
    }
}

@media (max-width: 600px) {
    .checkout-page {
        padding: 20px 15px;
    }
    .checkout-form, .checkout-summary {
        padding: 20px;
    }
    .address-select {
        flex-direction: column;
        gap: 15px;
    }
    .address-select select {
        width: 100%;
        min-width: unset;
    }
    .payment-option {
        flex-direction: column;
        align-items: flex-start;
        padding: 10px;
    }
    .payment-option input[type="radio"] {
        align-self: flex-end;
    }
    .option-content {
        margin-top: 5px;
    }
}
</style>