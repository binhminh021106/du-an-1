<script setup>
import { ref, reactive, onMounted, watch, computed } from "vue";

// 🛒 Giỏ hàng demo
const cartItems = ref([
  { name: "Chuột gaming Logitech G102", price: 700000, quantity: 2 },
  { name: "Bàn phím cơ Akko 3087 Ocean Star", price: 1500000, quantity: 1 },
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
});

// 🚛 Phí vận chuyển hiện tại
const shippingCost = ref(0);

// 🧭 Lấy danh sách địa chỉ VN
onMounted(async () => {
  try {
    const res = await fetch("https://provinces.open-api.vn/api/?depth=3");
    provinces.value = await res.json();
  } catch (err) {
    console.error("Không thể tải địa chỉ:", err);
  }
});

// Khi chọn tỉnh → load quận/huyện
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

// Cập nhật form
watch([selectedProvince, selectedDistrict, selectedWard], () => {
  form.address = {
    province: selectedProvince.value,
    district: selectedDistrict.value,
    ward: selectedWard.value,
  };
});

// ✅ Kiểm tra form
const validateForm = () => {
  let valid = true;
  errors.name = "";
  errors.email = "";
  errors.phone = "";
  errors.address = "";

  if (!form.name.trim()) {
    errors.name = "Vui lòng nhập họ tên.";
    valid = false;
  }

  const emailRegex = /^[\\w.-]+@[\\w.-]+\\.[a-zA-Z]{2,}$/;
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

  return valid;
};

// 💳 Xác nhận thanh toán
const confirmCheckout = () => {
  if (!validateForm()) return;

  const total = subtotal.value + shippingCost.value;

  alert(
    `✅ Thanh toán thành công!\n\nNgười nhận: ${form.name}\nSĐT: ${form.phone}\nEmail: ${form.email}\nĐịa chỉ: ${form.address.ward}, ${form.address.district}, ${form.address.province}\n\nTổng sản phẩm: ${subtotal.value.toLocaleString()} đ\nPhí vận chuyển: ${shippingCost.value.toLocaleString()} đ\nTổng thanh toán: ${total.toLocaleString()} đ`
  );
};

// 💰 Tổng thanh toán cuối cùng
const totalPrice = computed(() => subtotal.value + shippingCost.value);
</script>

<template>
  <div class="checkout-page container">
    <h2 class="checkout-title">
      <i class="fa-solid fa-credit-card"></i> Thanh toán
    </h2>

    <div class="checkout-content">
      <!-- 🧍‍♀️ Form -->
      <div class="checkout-form">
        <h3>Thông tin giao hàng</h3>
        <form @submit.prevent="confirmCheckout">
          <div class="form-group">
            <label>Họ và tên</label>
            <input type="text" v-model="form.name" placeholder="Nhập họ tên của bạn" />
            <p v-if="errors.name" class="error">{{ errors.name }}</p>
          </div>

          <div class="form-group">
            <label>Email</label>
            <input type="email" v-model="form.email" placeholder="Nhập email" />
            <p v-if="errors.email" class="error">{{ errors.email }}</p>
          </div>

          <div class="form-group">
            <label>Số điện thoại</label>
            <input type="text" v-model="form.phone" placeholder="Nhập số điện thoại" />
            <p v-if="errors.phone" class="error">{{ errors.phone }}</p>
          </div>

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
  </div>
</template>

<style scoped>
.checkout-page {
  font-family: "Arial", sans-serif;
  padding: 40px 20px;
}

.checkout-title {
  color: #009981;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 26px;
  margin-bottom: 25px;
}

.checkout-content {
  display: flex;
  gap: 30px;
  flex-wrap: wrap;
}

.checkout-form {
  flex: 1 1 60%;
  background: #fff;
  padding: 25px 30px;
  border-radius: 10px;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
}

.checkout-summary {
  flex: 1 1 35%;
  background: #f8f9fa;
  padding: 25px 30px;
  border-radius: 10px;
}

.form-group {
  margin-bottom: 18px;
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: bold;
  margin-bottom: 5px;
}

.form-group input,
.address-select select {
  padding: 10px 14px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 14px;
}

.address-select {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.error {
  color: red;
  font-size: 13px;
  margin-top: 4px;
}

.checkout-btn {
  background-color: #009981;
  color: #fff;
  font-weight: bold;
  padding: 12px 25px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  margin-top: 10px;
}

.checkout-btn:hover {
  background-color: #006e61;
}

.summary-line {
  display: flex;
  justify-content: space-between;
  margin: 8px 0;
}

.total {
  font-size: 18px;
  font-weight: bold;
  border-top: 1px solid #ccc;
  padding-top: 10px;
}

.shipping-fee {
  margin-top: 8px;
  font-weight: bold;
  color: #333;
  display: flex;
  justify-content: space-between;
}
</style>
