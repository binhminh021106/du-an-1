<script setup>
import { ref, computed, watch } from "vue";

// ✅ Sản phẩm có sẵn
const cart = ref([
  {
    id: "1",
    image_url: "#",
    name: "Chuột gaming Logitech G102",
    category: { id: 1, name: "Chuột máy tính" },
    price: 350000,
    stock: 80,
    qty: 2,
  },
  {
    id: "2",
    image_url: "#",
    name: "Bàn phím cơ Akko 3087 Ocean Star",
    category: { id: 2, name: "Bàn phím cơ" },
    price: 1500000,
    stock: 50,
    qty: 1,
  },
]);

const total = computed(() =>
  cart.value.reduce((sum, item) => sum + item.qty * item.price, 0)
);

const formatPrice = (v) => v.toLocaleString("vi-VN") + " ₫";

const removeItem = (index) => {
  cart.value.splice(index, 1);
};

// ✅ Chặn nhập số lượng âm, 0 hoặc vượt quá tồn kho
watch(
  cart,
  (newCart) => {
    newCart.forEach((item) => {
      if (item.qty < 1) item.qty = 1;
      if (item.qty > item.stock) item.qty = item.stock;
    });
  },
  { deep: true }
);
</script>

<template>
  <div class="cart-page">
    <div class="container">
      <div class="cart-card">
        <h2>🛍️ Giỏ hàng của bạn</h2>

        <table v-if="cart.length" class="cart-table">
          <thead>
            <tr>
              <th>Sản phẩm</th>
              <th>Danh mục</th>
              <th>Giá</th>
              <th>Số lượng</th>
              <th>Tạm tính</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in cart" :key="item.id">
              <td>
                <div class="product-info">
                  <img :src="item.image_url" alt="" />
                  <span>{{ item.name }}</span>
                </div>
              </td>
              <td>{{ item.category.name }}</td>
              <td>{{ formatPrice(item.price) }}</td>
              <td>
                <input
                  type="number"
                  min="1"
                  :max="item.stock"
                  v-model.number="item.qty"
                  @blur="item.qty = Math.max(1, Math.min(item.qty, item.stock))"
                />
              </td>
              <td>{{ formatPrice(item.qty * item.price) }}</td>
              <td>
                <button class="remove-btn" @click="removeItem(index)">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="empty-cart">Giỏ hàng trống.</div>

        <div v-if="cart.length" class="cart-footer">
          <h3>Tổng cộng: <span>{{ formatPrice(total) }}</span></h3>
          <router-link to="/checkout" class="checkout-btn">
            Thanh toán
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

.cart-page {
  font-family: Arial, sans-serif;
  padding: 50px 15px;
}

.cart-card {
  background: #fff;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
  max-width: 900px;
  margin: auto;
}

h2 {
  color: #009981;
  margin-bottom: 25px;
}

.cart-table {
  width: 100%;
  border-collapse: collapse;
}

.cart-table th {
  background: #009981;
  color: #fff;
  padding: 10px;
  text-align: left;
}

.cart-table td {
  padding: 12px;
  border-bottom: 1px solid #eee;
  vertical-align: middle;
}

.cart-table input {
  width: 70px;
  padding: 5px;
  text-align: center;
  border-radius: 6px;
  border: 1px solid #ccc;
}

.product-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.product-info img {
  width: 60px;
  height: 60px;
  border-radius: 8px;
  border: 1px solid #ddd;
}

.remove-btn {
  background: none;
  border: none;
  color: #e74c3c;
  font-size: 18px;
  cursor: pointer;
}

.remove-btn:hover {
  color: #c0392b;
}

.cart-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 25px;
}

.cart-footer h3 {
  color: #333;
}

.cart-footer span {
  color: #009981;
  font-weight: bold;
}

.checkout-btn {
  background-color: #009981;
  color: #fff;
  text-decoration: none;
  padding: 10px 25px;
  border-radius: 8px;
  font-weight: bold;
}

.checkout-btn:hover {
  background-color: #00483d;
}

.empty-cart {
  text-align: center;
  padding: 30px;
  color: #777;
}
</style>
