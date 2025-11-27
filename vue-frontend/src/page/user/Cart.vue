<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
// SỬA: Import đúng cấp
import { 
  cart, 
  total, 
  removeItem, 
  updateItemQty, 
  clearCart, 
  saveCart 
} from "./user/cartStore.js"; 

const formatPrice = (v) => {
    if(!v) return "0 ₫";
    return Number(v).toLocaleString("vi-VN") + "\u00A0₫";
}
const selectedItems = ref([]);

// --- CẤU HÌNH API ---
const API_URL = 'http://127.0.0.1:8000/api'; 
const SERVER_URL = 'http://127.0.0.1:8000';   

const USE_STORAGE = false; 

// --- 1. HÀM XỬ LÝ LINK ẢNH ---
const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/70x70?text=No+Img';
  if (path.startsWith('http')) return path;

  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  
  if (USE_STORAGE) {
    return `${SERVER_URL}/storage/${cleanPath}`; 
  } else {
    return `${SERVER_URL}/${cleanPath}`;         
  }
};

// --- 2. HÀM LẤY DỮ LIỆU MỚI TỪ API ---
const enrichCartData = async () => {
  if (!cart.value.length) return;

  const promises = cart.value.map(async (item) => {
    try {
      const response = await axios.get(`${API_URL}/product/${item.id}`);
      // Xử lý cả 2 trường hợp response
      const productData = response.data.data || response.data; 

      if (productData) {
        item.thumbnail_url = productData.thumbnail_url || productData.image_url; 
        item.category = productData.category; 
        item.name = productData.name;
        // Chỉ cập nhật stock nếu có dữ liệu hợp lệ
        if(productData.stock !== undefined) {
             item.stock = productData.stock;
        }
      }
    } catch (e) {
      console.warn(`Lỗi update SP ${item.id}`, e);
      // Không làm gì để giữ nguyên data cũ trong giỏ hàng
    }
  });

  await Promise.all(promises);
  saveCart(); // Lưu lại thông tin mới nhất
};

onMounted(() => {
  enrichCartData();
});

// --- CÁC HÀM XỬ LÝ SỰ KIỆN ---
const removeSelected = () => {
  if (!selectedItems.value.length) return;
  if (!confirm(`Xóa ${selectedItems.value.length} sản phẩm?`)) return;
  selectedItems.value.forEach((id) => removeItem(id));
  selectedItems.value = [];
};

const removeAll = () => {
  if (!confirm("Xóa tất cả giỏ hàng?")) return;
  if (typeof clearCart === 'function') {
    clearCart();
  } else {
    cart.value = [];
    saveCart();
  }
  selectedItems.value = [];
};
</script>

<template>
  <div class="cart-page">
    <div class="container">
      <div class="cart-card">
        <h2>🛍️ Giỏ hàng của bạn</h2>

        <div v-if="cart.length" class="top-actions">
          <button 
            class="small-btn danger" 
            @click="removeSelected" 
            :disabled="!selectedItems.length"
          >
            Xóa đã chọn
          </button>

          <button class="small-btn danger" @click="removeAll">
            Xóa tất cả
          </button>
        </div>

        <table v-if="cart.length" class="cart-table">
          <thead>
            <tr>
              <th width="5%"></th>
              <th width="40%">Sản phẩm</th>
              <th width="15%">Danh mục</th>
              <th width="15%">Giá</th>
              <th width="10%">Số lượng</th>
              <th width="15%">Tạm tính</th>
              <th width="5%"></th> 
            </tr>
          </thead>

          <tbody>
            <tr v-for="item in cart" :key="item.cartId">
              <td><input type="checkbox" v-model="selectedItems" :value="item.cartId" /></td>
              
              <!-- CỘT SẢN PHẨM -->
              <td>
                <div class="product-info">
                  <router-link :to="`/products/${item.id}`">
                    <img 
                      :src="getImageUrl(item.thumbnail_url || item.image_url)" 
                      alt="Product"
                      @error="$event.target.src='https://placehold.co/70x70?text=No+Img'" 
                    />
                  </router-link>
                  <div class="product-details">
                    <router-link :to="`/products/${item.id}`" class="product-name">
                      {{ item.name }}
                    </router-link>
                    
                    <small v-if="item.variantName && item.variantName !== 'Mặc định'">
                      Phân loại: {{ item.variantName }}
                    </small>
                  </div>
                </div>
              </td>

              <!-- CỘT DANH MỤC -->
              <td>
                <span class="badge-category">
                  {{ item.category?.name || "N/A" }}
                </span>
              </td>
              
              <td class="price">{{ formatPrice(item.price) }}</td>
              <td>
                <input 
                  type="number" 
                  min="1" 
                  :max="item.stock || 999" 
                  v-model.number="item.qty" 
                  @input="updateItemQty(item.cartId, item.qty)" 
                />
              </td>
              <td class="total-price">{{ formatPrice(item.qty * item.price) }}</td>
              <td>
                <button class="remove-btn" @click="removeItem(item.cartId)">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="empty-cart">
            <i class="fa-solid fa-cart-shopping"></i>
            <p>Giỏ hàng của bạn đang trống.</p>
            <router-link to="/" class="continue-shopping">Tiếp tục mua sắm</router-link>
        </div>

        <div v-if="cart.length" class="cart-footer">
          <div class="total-section">
            <h3>Tổng cộng:</h3>
            <span class="total-amount">{{ formatPrice(total) }}</span>
          </div>
          <router-link to="/checkout" class="checkout-btn">Thanh toán</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

.cart-page {
  padding: 50px 15px;
  background-color: #f8f9fa;
  min-height: 100vh;
}

.cart-card {
  background: #fff;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
  max-width: 1100px;
  margin: auto;
}

h2 {
  color: #2c3e50;
  margin-bottom: 25px;
  font-weight: 700;
  border-bottom: 2px solid #eee;
  padding-bottom: 15px;
}

.top-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
}

.small-btn {
  padding: 8px 16px;
  font-size: 13px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.2s;
}

.small-btn.danger {
  background: #fff0f0;
  color: #e74c3c;
  border: 1px solid #ffcccc;
}

.small-btn.danger:hover {
  background: #e74c3c;
  color: white;
}

.small-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #f5f5f5;
  color: #aaa;
  border-color: #eee;
}

.cart-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 15px;
}

.cart-table th {
  background: transparent;
  color: #7f8c8d;
  padding: 10px;
  text-align: left;
  font-weight: 600;
  text-transform: uppercase;
  font-size: 12px;
  letter-spacing: 0.5px;
}

.cart-table td {
  padding: 15px 10px;
  background: #fff;
  border-top: 1px solid #f1f2f6;
  border-bottom: 1px solid #f1f2f6;
  vertical-align: middle;
}

.cart-table td:first-child {
  border-left: 1px solid #f1f2f6;
  border-top-left-radius: 8px;
  border-bottom-left-radius: 8px;
}
.cart-table td:last-child {
  border-right: 1px solid #f1f2f6;
  border-top-right-radius: 8px;
  border-bottom-right-radius: 8px;
}

.product-info {
  display: flex;
  align-items: center;
  gap: 15px;
}

.product-info img {
  width: 70px;
  height: 70px;
  border-radius: 8px;
  border: 1px solid #eee;
  object-fit: cover;
  transition: transform 0.2s;
}

.product-info img:hover {
  transform: scale(1.05);
}

.product-details {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.product-name {
  font-weight: 600;
  color: #2c3e50;
  text-decoration: none;
  font-size: 15px;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.product-name:hover {
  color: #009981;
}

.badge-category {
  background-color: #eef2f7;
  color: #4a69bd;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.cart-table input[type="number"] {
  width: 60px;
  padding: 8px;
  text-align: center;
  border: 1px solid #dfe6e9;
  border-radius: 6px;
  font-weight: 600;
  color: #2d3436;
}

.cart-table input[type="number"]:focus {
  border-color: #009981;
  outline: none;
}

.total-price {
  color: #009981;
  font-weight: 700;
  font-size: 15px;
}

.remove-btn {
  width: 35px;
  height: 35px;
  border-radius: 50%;
  border: 1px solid #eee;
  background: white;
  color: #bdc3c7;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-btn:hover {
  background: #ff7675;
  color: white;
  border-color: #ff7675;
}

.cart-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 30px;
  padding-top: 25px;
  border-top: 2px dashed #dfe6e9;
}

.total-section h3 {
  font-size: 16px;
  color: #636e72;
  margin-bottom: 5px;
}

.total-amount {
  color: #d63031;
  font-size: 26px;
  font-weight: 700;
}

.checkout-btn {
  background: #009981;
  color: white;
  padding: 14px 40px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  font-size: 16px;
  box-shadow: 0 4px 10px rgba(0, 153, 129, 0.3);
  transition: transform 0.2s;
  display: flex;
  align-items: center;
  gap: 10px;
}

.checkout-btn:hover {
  transform: translateY(-2px);
  background: #007a67;
}

.empty-cart {
  text-align: center;
  padding: 80px 20px;
  color: #b2bec3;
}
.empty-cart i {
  font-size: 60px;
  margin-bottom: 20px;
  opacity: 0.5;
}
.continue-shopping {
  color: #009981;
  text-decoration: none;
  font-weight: 600;
  border: 1px solid #009981;
  padding: 10px 25px;
  border-radius: 6px;
  margin-top: 15px;
  display: inline-block;
  transition: all 0.2s;
}
.continue-shopping:hover {
  background: #009981;
  color: white;
}
</style>