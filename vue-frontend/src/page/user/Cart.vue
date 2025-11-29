<script setup>
import { ref, onMounted, computed } from "vue";
import { useStore } from "vuex"; 
import { useRouter } from "vue-router"; // Import router để chuyển trang bằng code

const store = useStore(); 
const router = useRouter();

const cart = computed(() => store.getters.cartItems);

// Tính tổng tiền dựa trên việc có chọn item nào không
// Nếu có chọn -> Tính tổng item đã chọn. Nếu không -> Tính tổng tất cả
const total = computed(() => {
    if (selectedItems.value.length > 0) {
        return cart.value
            .filter(item => selectedItems.value.includes(item.cartId))
            .reduce((sum, item) => sum + (Number(item.price) * Number(item.qty)), 0);
    }
    return store.getters.cartTotal;
});

const selectedItems = ref([]);

// --- CẤU HÌNH API ---
const SERVER_URL = 'http://127.0.0.1:8000'; 
const USE_STORAGE = false; 

const getImageUrl = (path) => {
  if (!path) return 'https://placehold.co/70x70?text=No+Img';
  if (path.startsWith('http')) return path;
  const cleanPath = path.startsWith('/') ? path.substring(1) : path;
  return USE_STORAGE ? `${SERVER_URL}/storage/${cleanPath}` : `${SERVER_URL}/${cleanPath}`;
};

const formatPrice = (v) => {
    if(v === undefined || v === null || isNaN(v)) return "0 ₫";
    return Number(v).toLocaleString("vi-VN") + "\u00A0₫";
}

onMounted(() => {
  store.dispatch('enrichCartData');
});

// --- ACTIONS ---
const removeItem = (cartId) => {
    store.dispatch('removeItem', cartId);
    // Xóa khỏi danh sách selected nếu đang chọn
    selectedItems.value = selectedItems.value.filter(id => id !== cartId);
}

const updateQty = (cartId, qty) => {
    let newQty = parseInt(qty);
    if (!newQty || newQty < 1) newQty = 1;
    store.dispatch('updateItemQty', { cartId, qty: newQty });
}

const removeSelected = () => {
  if (!selectedItems.value.length) return;
  if (!confirm(`Xóa ${selectedItems.value.length} sản phẩm đã chọn?`)) return;
  selectedItems.value.forEach((id) => store.dispatch('removeItem', id));
  selectedItems.value = [];
};

const removeAll = () => {
  if (!confirm("Xóa tất cả giỏ hàng?")) return;
  store.dispatch('clearCart');
  selectedItems.value = [];
};

// --- XỬ LÝ THANH TOÁN ---
const proceedToCheckout = () => {
    // Logic: Nếu có chọn checkbox -> Chỉ thanh toán item đó (Lưu vào localStorage hoặc State để trang Checkout đọc)
    // Ở đây mình làm ví dụ đơn giản: Chuyển trang và kèm query params
    
    let itemsToCheckout = [];
    if (selectedItems.value.length > 0) {
        itemsToCheckout = selectedItems.value;
    } else {
        // Nếu không chọn gì cả, mặc định là thanh toán hết (lấy tất cả cartId)
        itemsToCheckout = cart.value.map(item => item.cartId);
    }

    // Bạn có thể lưu tạm vào localStorage để trang Checkout lấy ra xử lý
    localStorage.setItem('checkout_items', JSON.stringify(itemsToCheckout));
    
    // Chuyển hướng
    router.push('/checkout');
}
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
            <i class="fas fa-trash-alt"></i> Xóa ({{ selectedItems.length }})
          </button>

          <button class="small-btn danger" @click="removeAll">
            <i class="fas fa-dumpster"></i> Xóa tất cả
          </button>
        </div>

        <table v-if="cart.length" class="cart-table">
          <thead>
            <tr>
              <th width="5%">
                <!-- Checkbox chọn tất cả (Optional) -->
              </th>
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
              <td>
                  <input type="checkbox" v-model="selectedItems" :value="item.cartId" />
              </td>
              
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
                      Phân loại: <strong>{{ item.variantName }}</strong>
                    </small>
                  </div>
                </div>
              </td>

              <!-- FIX: Đổi từ item.categoryName thành item.categoriesName cho khớp với Store -->
              <td>
                <span class="badge-category">
                  {{ item.categoriesName || "Khác" }}
                </span>
              </td>
              
              <td class="price">{{ formatPrice(item.price) }}</td>
              <td>
                <input 
                  type="number" 
                  min="1" 
                  :max="item.stock || 999" 
                  :value="item.qty"
                  @change="updateQty(item.cartId, $event.target.value)" 
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
            <h3>Tổng thanh toán ({{ selectedItems.length > 0 ? selectedItems.length : cart.length }} món):</h3>
            <span class="total-amount">{{ formatPrice(total) }}</span>
          </div>
          
          <!-- Thay router-link bằng button để xử lý logic trước khi đi -->
          <button @click="proceedToCheckout" class="checkout-btn">
             Thanh toán <i class="fas fa-arrow-right" style="margin-left:5px"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Giữ nguyên style cũ của bạn, code CSS của bạn đã khá ổn */
@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css');

.cart-page { padding: 50px 15px; background-color: #f8f9fa; min-height: 100vh; }
.cart-card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); max-width: 1100px; margin: auto; }
h2 { color: #2c3e50; margin-bottom: 25px; font-weight: 700; border-bottom: 2px solid #eee; padding-bottom: 15px; }
.top-actions { display: flex; gap: 12px; margin-bottom: 20px; }
.small-btn { padding: 8px 16px; font-size: 13px; border-radius: 6px; border: none; cursor: pointer; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; gap: 5px; }
.small-btn.danger { background: #fff0f0; color: #e74c3c; border: 1px solid #ffcccc; }
.small-btn.danger:hover { background: #e74c3c; color: white; border-color: #ff7675; }
.small-btn:disabled { opacity: 0.5; cursor: not-allowed; background: #f5f5f5; color: #aaa; border-color: #eee; }
.cart-table { width: 100%; border-collapse: separate; border-spacing: 0 15px; }
.cart-table th { background: transparent; color: #7f8c8d; padding: 10px; text-align: left; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
.cart-table td { padding: 15px 10px; background: #fff; border-top: 1px solid #f1f2f6; border-bottom: 1px solid #f1f2f6; vertical-align: middle; }
.cart-table td:first-child { border-left: 1px solid #f1f2f6; border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
.cart-table td:last-child { border-right: 1px solid #f1f2f6; border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
.product-info { display: flex; align-items: center; gap: 15px; }
.product-info img { width: 70px; height: 70px; border-radius: 8px; border: 1px solid #eee; object-fit: cover; transition: transform 0.2s; }
.product-info img:hover { transform: scale(1.05); }
.product-details { display: flex; flex-direction: column; gap: 5px; }
.product-name { font-weight: 600; color: #2c3e50; text-decoration: none; font-size: 15px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.product-name:hover { color: #009981; }
.badge-category { background-color: #eef2f7; color: #4a69bd; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.cart-table input[type="number"] { width: 60px; padding: 8px; text-align: center; border: 1px solid #dfe6e9; border-radius: 6px; font-weight: 600; color: #2d3436; }
.cart-table input[type="number"]:focus { border-color: #009981; outline: none; }
.total-price { color: #009981; font-weight: 700; font-size: 15px; }
.remove-btn { width: 35px; height: 35px; border-radius: 50%; border: 1px solid #eee; background: white; color: #bdc3c7; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
.remove-btn:hover { background: #ff7675; color: white; border-color: #ff7675; }
.cart-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 25px; border-top: 2px dashed #dfe6e9; }
.total-section h3 { font-size: 16px; color: #636e72; margin-bottom: 5px; }
.total-amount { color: #d63031; font-size: 26px; font-weight: 700; }
.checkout-btn { background: #009981; color: white; padding: 14px 40px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; box-shadow: 0 4px 10px rgba(0, 153, 129, 0.3); transition: transform 0.2s; display: flex; align-items: center; gap: 10px; border:none; cursor: pointer; }
.checkout-btn:hover { transform: translateY(-2px); background: #007a67; }
.empty-cart { text-align: center; padding: 80px 20px; color: #b2bec3; }
.empty-cart i { font-size: 60px; margin-bottom: 20px; opacity: 0.5; }
.continue-shopping { color: #009981; text-decoration: none; font-weight: 600; border: 1px solid #009981; padding: 10px 25px; border-radius: 6px; margin-top: 15px; display: inline-block; transition: all 0.2s; }
.continue-shopping:hover { background: #009981; color: white; }
</style>