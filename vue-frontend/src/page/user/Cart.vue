<script setup>
import { computed } from "vue";
// THAY THẾ bằng store
import { cart, total, removeItem, updateItemQty, saveCart } from "./user/cartStore.js"; 

// 🛠️ Đã sửa: Sử dụng &nbsp; cho khoảng trắng không ngắt dòng
const formatPrice = (v) => v.toLocaleString("vi-VN") + "\u00A0₫"; // \u00A0 là ký tự Non-breaking space

// SỬA: Hàm removeItem mới sẽ gọi hàm từ store
const onRemoveItem = (cartId) => {
  removeItem(cartId);
};
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
            <tr v-for="item in cart" :key="item.cartId"> 
              <td>
                <div class="product-info">
                  <img :src="item.image_url" alt="" />
                  <span>
                    {{ item.name }}
                    <small v-if="item.variantName && item.variantName !== 'Mặc định'">({{ item.variantName }})</small>
                  </span>
                </div>
              </td>
              <td>{{ item.category?.name || 'N/A' }}</td>
              <td>{{ formatPrice(item.price) }}</td>
              <td>
                <input
                  type="number"
                  min="1"
                  :max="item.stock"
                  v-model.number="item.qty"
                  @blur="updateItemQty(item.cartId, item.qty)" 
                  @input="updateItemQty(item.cartId, item.qty)" 
                />
              </td>
              <td>{{ formatPrice(item.qty * item.price) }}</td>
              <td>
                <button class="remove-btn" @click="onRemoveItem(item.cartId)">
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
/* Không thay đổi phần CSS */
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