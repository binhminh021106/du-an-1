<template>
  <div class="home-container">
    
    <section class="quick-links">
      <a href="#" class="quick-link-item">
        <img src="https://cdn.cellphones.com.vn/media/icons/category/cate-1644.png" alt="Icon">
        <span>Flash Sale</span>
      </a>
      <a href="#" class="quick-link-item">
        <img src="https://cdn.cellphones.com.vn/media/icons/category/cate-1646.png" alt="Icon">
        <span>Hàng Cũ Giá Tốt</span>
      </a>
      <a href="#" class="quick-link-item">
        <img src="https://cdn.cellphones.com.vn/media/icons/category/cate-87.png" alt="Icon">
        <span>Thu Cũ Đổi Mới</span>
      </a>
      <a href="#" class="quick-link-item">
        <img src="https://cdn.cellphones.com.vn/media/icons/category/cate-183.png" alt="Icon">
        <span>Phụ Kiện</span>
      </a>
      <a href="#" class="quick-link-item">
        <img src="https://cdn.cellphones.com.vn/media/icons/category/cate-1095.png" alt="Icon">
        <span>Gia Dụng Thông Minh</span>
      </a>
    </section>

    <section class="product-section">
      <h2 class="section-title">ĐIỆN THOẠI NỔI BẬT NHẤT</h2>
      <div class="product-grid">
        <div class="product-card" v-for="product in featuredPhones" :key="product.id">
          <img :src="product.img" :alt="product.name">
          <h3 class="product-name">{{ product.name }}</h3>
          <div class="product-price">
            <span class="new-price">{{ product.newPrice }}</span>
            <span class="old-price">{{ product.oldPrice }}</span>
          </div>
          <div class="product-promo" v-if="product.promo">
            {{ product.promo }}
          </div>
        </div>
      </div>
    </section>

    <section class="brand-banner">
       <a href="#">
        <img src="https://images.fpt.shop/unsafe/fit-in/1200x200/filters:quality(90):fill(white)/fptshop.com.vn/Uploads/Originals/2024/5/1/638501810787167732_F-H5_1200x200%20(1).png" alt="Brand Banner">
      </a>
    </section>

    <section class="product-section">
      <h2 class="section-title">LAPTOP BÁN CHẠY</h2>
      <div class="product-grid">
        <div class="product-card" v-for="product in featuredLaptops" :key="product.id">
          <img :src="product.img" :alt="product.name">
          <h3 class="product-name">{{ product.name }}</h3>
          <div class="product-price">
            <span class="new-price">{{ product.newPrice }}</span>
            <span class="old-price">{{ product.oldPrice }}</span>
          </div>
          <div class="product-promo" v-if="product.promo">
            {{ product.promo }}
          </div>
        </div>
      </div>
    </section>

    <section class="news-section">
      <h2 class="section-title">Tin Tức Công Nghệ</h2>
      <div class="news-grid">
        <div class="news-card" v-for="article in newsArticles" :key="article.id">
          <img :src="article.img" :alt="article.title">
          <h3 class="news-title">{{ article.title }}</h3>
          <p class="news-excerpt">{{ article.excerpt }}</p>
        </div>
      </div>
    </section>

    <section class="trust-block">
      <div class="trust-item">
        <span>✔️ Bảo hành chính hãng</span>
      </div>
      <div class="trust-item">
        <span>🚚 Giao hàng miễn phí</span>
      </div>
      <div class="trust-item">
        <span>🔄 Đổi trả 30 ngày</span>
      </div>
      <div class="trust-item">
        <span>🏪 Hơn 100+ cửa hàng</span>
      </div>
    </section>

  </div>
</template>

<script setup>
import { ref } from 'vue';

// Dữ liệu mẫu (sẽ được thay thế bằng API call trong dự án thật)
const featuredPhones = ref([
  { id: 1, name: 'iPhone 15 Pro Max 256GB | Chính hãng VNA', newPrice: '29.990.000₫', oldPrice: '34.990.000₫', promo: 'Giảm 500.000đ', img: 'https://cdn.cellphones.com.vn/media/catalog/product/i/p/iphone-15-pro-max_3.png' },
  { id: 2, name: 'Samsung Galaxy S24 Ultra 12GB 256GB', newPrice: '27.490.000₫', oldPrice: '33.990.000₫', promo: 'Quà 1.000.000đ', img: 'https://cdn.cellphones.com.vn/media/catalog/product/s/2/s24_ultra_gray_-_1_1_1.png' },
  { id: 3, name: 'Xiaomi 14 5G 12GB 256GB', newPrice: '15.990.000₫', oldPrice: '17.990.000₫', promo: 'Trả góp 0%', img: 'https://cdn.cellphones.com.vn/media/catalog/product/x/i/xiaomi-14-256gb_2.png' },
  { id: 4, name: 'OPPO Reno11 Pro 5G 12GB 512GB', newPrice: '10.790.000₫', oldPrice: '12.990.000₫', promo: 'Tặng phiếu mua hàng', img: 'https://cdn.cellphones.com.vn/media/catalog/product/o/p/oppo-reno11-pro_1_1.png' },
  { id: 5, name: 'Realme Note 50 4GB 128GB', newPrice: '2.890.000₫', oldPrice: '3.490.000₫', promo: 'Giá rẻ', img: 'https://cdn.cellphones.com.vn/media/catalog/product/r/e/realme-note-50-2_1_1_1.png' },
]);

const featuredLaptops = ref([
  { id: 1, name: 'MacBook Air M2 13-inch 8GB 256GB', newPrice: '22.990.000₫', oldPrice: '27.990.000₫', promo: 'Bảo hành 2 năm', img: 'https://cdn.cellphones.com.vn/media/catalog/product/m/a/macbook-air-m2-1_1.png' },
  { id: 2, name: 'HP Envy X360 14-es0013dx 2 in 1', newPrice: '17.490.000₫', oldPrice: '20.990.000₫', promo: 'Quà 1.000.000đ', img: 'https://cdn.cellphones.com.vn/media/catalog/product/h/p/hp-envy-x360_2.png' },
  { id: 3, name: 'Dell Inspiron 15 3530 i5-1335U', newPrice: '13.990.000₫', oldPrice: '16.990.000₫', promo: 'Tặng balo', img: 'https://cdn.cellphones.com.vn/media/catalog/product/d/e/dell-inspiron-15-3530_1.png' },
  { id: 4, name: 'ASUS TUF Gaming F15 FX507ZU4-LP054W', newPrice: '25.490.000₫', oldPrice: '29.490.000₫', promo: 'RTX 4050', img: 'https://cdn.cellphones.com.vn/media/catalog/product/a/s/asus-tuf-gaming-f15_5.png' },
  { id: 5, name: 'Lenovo IdeaPad Slim 3 15IRH8', newPrice: '15.890.000₫', oldPrice: '18.990.000₫', promo: 'Màn 144Hz', img: 'https://cdn.cellphones.com.vn/media/catalog/product/l/e/lenovo-ideapad-slim-3_1.png' },
]);

const newsArticles = ref([
  { id: 1, img: 'https://cdn.sforum.vn/sforum/wp-content/uploads/2024/05/galaxy-z-fold6-render-4-360x240.jpg', title: 'Rò rỉ mới nhất tiết lộ thiết kế và giá bán của Galaxy Z Fold6', excerpt: 'Chỉ còn vài tháng nữa là Samsung sẽ trình làng thế hệ smartphone màn hình gập mới...' },
  { id: 2, img: 'https://cdn.sforum.vn/sforum/wp-content/uploads/2024/05/HONOR-200-Pro-1-360x240.jpg', title: 'HONOR 200 Pro lộ ảnh render sắc nét, cấu hình chi tiết', excerpt: 'HONOR được cho là đang làm việc trên dòng smartphone HONOR 200...' },
  { id: 3, img: 'https://cdn.sforum.vn/sforum/wp-content/uploads/2024/05/Xiaomi-Pad-7-Pro-1-360x240.jpg', title: 'Xiaomi Pad 7 Pro có thể ra mắt cùng Xiaomi 15', excerpt: 'Xiaomi đã ra mắt máy tính bảng Pad 6S Pro 12.4 inch vào đầu năm nay...' },
]);

</script>

<style scoped>
.home-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 10px;
  background-color: #f5f5f7; /* Màu nền xám nhạt giống CellphoneS */
}

/* 1. Hero Banner */
.hero-banner img {
  width: 100%;
  border-radius: 10px;
  object-fit: cover;
}

/* 2. Quick Links */
.quick-links {
  display: flex;
  justify-content: space-around;
  background-color: #fff;
  padding: 15px 10px;
  border-radius: 10px;
  margin: 15px 0;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.quick-link-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-decoration: none;
  color: #333;
  font-size: 13px;
  text-align: center;
  font-weight: 500;
}
.quick-link-item img {
  width: 45px;
  height: 45px;
  margin-bottom: 5px;
}
.quick-link-item span {
  max-width: 80px;
}

/* 3. & 5. Product Section */
.product-section {
  background: #fff;
  border-radius: 10px;
  margin-bottom: 15px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.section-title {
  font-size: 22px;
  font-weight: 700;
  color: #d70018; /* Màu đỏ chủ đạo */
  margin-bottom: 20px;
  text-transform: uppercase;
}
.product-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr); /* 5 sản phẩm trên 1 hàng */
  gap: 15px;
}

.product-card {
  border: 1px solid #f0f0f0;
  border-radius: 8px;
  text-align: left;
  padding: 10px;
  transition: box-shadow 0.3s ease;
  text-decoration: none;
  color: inherit;
  display: flex;
  flex-direction: column;
}
.product-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  border-color: #d70018;
}
.product-card img {
  width: 100%;
  aspect-ratio: 1/1; /* Giữ ảnh vuông */
  object-fit: contain;
  margin-bottom: 10px;
}
.product-name {
  font-size: 14px;
  font-weight: 500;
  line-height: 1.4;
  height: 3.9em; /* Đảm bảo 3 dòng text, giữ chiều cao card đồng đều */
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  margin-bottom: 5px;
}
.product-price {
  margin-top: auto; /* Đẩy giá xuống dưới */
  padding-top: 10px;
}
.new-price {
  font-size: 16px;
  font-weight: bold;
  color: #d70018;
  margin-right: 5px;
}
.old-price {
  font-size: 13px;
  color: #777;
  text-decoration: line-through;
}
.product-promo {
  background-color: #fef6f7;
  border: 1px solid #d70018;
  color: #d70018;
  font-size: 11px;
  font-weight: 500;
  padding: 3px 6px;
  border-radius: 4px;
  margin-top: 8px;
  display: inline-block; /* Chỉ chiếm độ rộng của text */
}

/* 4. Brand Banner */
.brand-banner {
  margin-bottom: 15px;
}
.brand-banner img {
  width: 100%;
  border-radius: 10px;
  object-fit: cover;
}

/* 6. News Section */
.news-section {
  background: #fff;
  border-radius: 10px;
  margin-bottom: 15px;
  padding: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.news-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 15px;
}
.news-card {
  border: 1px solid #f0f0f0;
  border-radius: 8px;
  overflow: hidden;
}
.news-card img {
  width: 100%;
  aspect-ratio: 16/10;
  object-fit: cover;
}
.news-title {
  font-size: 15px;
  font-weight: 600;
  padding: 10px 15px 5px;
}
.news-excerpt {
  font-size: 13px;
  color: #555;
  padding: 0 15px 15px;
}

/* 7. Trust Block */
.trust-block {
  display: flex;
  justify-content: space-around;
  background-color: #fff;
  padding: 20px 10px;
  border-radius: 10px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.trust-item {
  font-size: 14px;
  font-weight: 500;
  color: #333;
}

/* Responsive */
@media (max-width: 1024px) {
  .product-grid {
    grid-template-columns: repeat(4, 1fr); /* 4 cột cho tablet */
  }
}

@media (max-width: 768px) {
  .product-grid {
    grid-template-columns: repeat(3, 1fr); /* 3 cột cho mobile lớn */
  }
  .news-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .quick-links {
    flex-wrap: wrap;
    gap: 10px;
  }
  .quick-link-item {
    flex-basis: 20%; /* 5 item trên hàng */
  }
  .trust-block {
    flex-wrap: wrap;
    gap: 10px;
  }
  .trust-item {
    flex-basis: 45%;
    text-align: center;
  }
}

@media (max-width: 480px) {
  .product-grid {
    grid-template-columns: repeat(2, 1fr); /* 2 cột cho mobile nhỏ */
  }
  .news-grid {
    grid-template-columns: 1fr;
  }
  .quick-link-item {
    flex-basis: 30%; /* 3 item trên hàng */
  }
  .section-title {
    font-size: 18px;
  }
}
</style>