<template>
    <div id="app">
        <main class="container">
            <section class="top-section-layout">

                <nav class="categories-sidebar">
                    <div class="category-item-sodo" v-for="category in categories" :key="category.id"
                        :class="{ active: category.id === activeCategoryId }" @click="setActiveCategory(category.id)">
                        <i :class="getCategoryIcon(category.name)"></i>
                        <span>{{ category.name }}</span>
                    </div>
                </nav>

                <section class="slider" @mouseenter="stopAutoSlide" @mouseleave="startAutoSlide">
                    <div class="slider-wrapper" :style="{ transform: 'translateX(-' + (currentSlide * 100) + '%)' }">
                        <div class="slide" v-for="(slide, index) in slides" :key="index"
                            :style="{ backgroundImage: 'url(' + slide.imageUrl + ')' }">
                            <div class="slide-content">
                                <h2>{{ slide.title }}</h2>
                                <p>{{ slide.description }}</p>
                            </div>
                        </div>
                    </div>

                    <button class="slider-control prev" @click="prevSlide">
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <button class="slider-control next" @click="nextSlide">
                        <i class="fas fa-chevron-right"></i>
                    </button>

                    <div class="slider-nav">
                        <span v-for="(slide, index) in slides" :key="index" class="slider-nav-dot"
                            :class="{ active: index === currentSlide }" @click="goToSlide(index)"></span>
                    </div>
                </section>

                <aside class="utility-sidebar">
                    <div class="user-info-card">
                        <p class="user-name">Nguyễn Thị Kim Hiền</p>
                        <p class="user-phone">0xxxxxx444</p>
                        <p class="user-tier">⭐ Null</p>
                        <hr style="margin: 10px 0; border: 0; border-top: 1px solid #f0f0f0;">
                        <div class="user-loyalty-points">
                            <span class="loyalty-icon">💰</span>
                            <span class="loyalty-value">0đ</span>
                            <p class="loyalty-text">Tổng tiền tích lũy</p>
                        </div>
                        <p class="view-store-link">Xem ưu đãi của bạn &gt;</p>
                    </div>

                    <ul class="utility-menu">
                        <li><i class="fas fa-graduation-cap"></i> Ưu đãi cho giáo dục</li>
                        <li><i class="fas fa-bell"></i> Đăng ký nhận ưu đãi</li>
                        <li><i class="fas fa-retweet"></i> Thu cũ lên đời máy - Hẹn đổi</li>
                        <li style="color: #dc3545; font-weight: bold;"><i class="fas fa-gift"></i> Laptop giảm thêm 500K
                        </li>
                        <li><i class="fas fa-mobile-alt"></i> iPhone trợ giá đến 5 triệu</li>
                    </ul>

                    <div class="quick-links-grid">
                        <div class="quick-link-item"><i class="fab fa-samsung"></i> Samsung</div>
                        <div class="quick-link-item"><i class="fas fa-laptop-code"></i> Laptop</div>
                    </div>

                </aside>

            </section>

            <section class="product-grid">
                <div class="product-card" v-for="product in filteredProducts" :key="product.id">
                    <div class="product-image">
                        [Image: {{ product.name }}]
                    </div>
                    <div class="product-name">{{ product.name }}</div>
                    <div class="product-price">{{ formatCurrency(getMinPrice(product.variants)) }}</div>
                    <div class="product-stock">Tồn kho: {{ getTotalStock(product.variants) }}</div>
                    <div class="card-actions">
                        <button class="btn-view" @click="openQuickView(product)"><i class="fas fa-eye"></i> Xem</button>
                        <button class="btn-add-cart" @click="addToCart(product)"><i class="fas fa-plus"></i> Thêm
                            giỏ</button>
                    </div>
                </div>
            </section>

            <div id="app">
                <main class="container">
                    <section class="top-section-layout">
                    </section>

                    <section class="product-grid">
                    </section>

                    <hr>

                    <section class="brand-banner" style="margin-top: 20px;">
                        <a href="#">
                            <img src="https://images.fpt.shop/unsafe/fit-in/1200x200/filters:quality(90):fill(white)/fptshop.com.vn/Uploads/Originals/2024/5/1/638501810787167732_F-H5_1200x200%20(1).png"
                                alt="Brand Banner">
                        </a>
                    </section>

                    <section class="trust-block">
                        <div class="trust-item">
                            <span>✔️ **Bảo hành chính hãng**</span>
                        </div>
                        <div class="trust-item">
                            <span>🚚 **Giao hàng miễn phí**</span>
                        </div>
                        <div class="trust-item">
                            <span>🔄 **Đổi trả 30 ngày**</span>
                        </div>
                        <div class="trust-item">
                            <span>🏪 **Hơn 100+ cửa hàng**</span>
                        </div>
                    </section>

                    <section class="product-section">
                        <h2 class="section-title">✨ ĐIỆN THOẠI NỔI BẬT NHẤT</h2>
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
                                <div class="card-actions-small">
                                    <button class="btn-view" @click="openQuickView(product)"><i class="fas fa-eye"></i>
                                        Xem</button>
                                    <button class="btn-add-cart" @click="addToCart(product)"><i class="fas fa-plus"></i>
                                        Thêm giỏ</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="product-section">
                        <h2 class="section-title">💻 LAPTOP BÁN CHẠY</h2>
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
                                <div class="card-actions-small">
                                    <button class="btn-view" @click="openQuickView(product)"><i class="fas fa-eye"></i>
                                        Xem</button>
                                    <button class="btn-add-cart" @click="addToCart(product)"><i class="fas fa-plus"></i>
                                        Thêm giỏ</button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="news-section">
                        <h2 class="section-title">📰 TIN TỨC CÔNG NGHỆ</h2>
                        <div class="news-grid">
                            <div class="news-card" v-for="article in newsArticles" :key="article.id">
                                <img :src="article.img" :alt="article.title">
                                <h3 class="news-title">{{ article.title }}</h3>
                                <p class="news-excerpt">{{ article.excerpt }}</p>
                                <a href="#" class="read-more">Xem thêm &gt;</a>
                            </div>
                        </div>
                    </section>

                </main>

            </div>

        </main>

        <div class="modal-overlay" :class="{ open: isModalOpen }" @click.self="closeQuickView">
            <div class="modal-content" v-if="selectedProduct">
                <button class="modal-close-btn" @click="closeQuickView"><i class="fas fa-times"></i></button>
                <div class="modal-body">
                    <div class="modal-image">
                        [Image: {{ selectedProduct.name }}]
                    </div>
                    <div class="modal-details">
                        <h4>{{ selectedProduct.name }}</h4>
                        <div class="price">{{ formatCurrency(selectedVariant.price) }}</div>
                        <p class="stock">Tồn kho: {{ selectedVariant.stock }}</p>
                        <div class="variant-selector">
                            <label for="variant-select">Chọn phiên bản:</label>
                            <select id="variant-select" v-model="selectedVariantIndex" @change="updateSelectedVariant">
                                <option v-for="(variant, index) in selectedProduct.variants" :key="index"
                                    :value="index">
                                    Phiên bản {{ index + 1 }} - {{ formatCurrency(variant.price) }} (SL: {{
                                    variant.stock }})
                                </option>
                            </select>
                        </div>
                        <p>Danh mục: {{ selectedProduct.category.name }}</p>
                        <button class="btn-buy-now" @click="buyNow(selectedProduct, selectedVariant)">
                            <i class="fas fa-money-bill-wave"></i> Mua Ngay
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="cart-sidebar" :class="{ open: isCartOpen }">
            <div class="cart-header">
                <h3><i class="fas fa-shopping-basket"></i> Giỏ Hàng</h3>
                <button class="cart-close-btn" @click="toggleCart"><i class="fas fa-times"></i></button>
            </div>
            <div class="cart-items">
                <p v-if="cartItems.length === 0">Giỏ hàng trống.</p>
                <div class="cart-item" v-for="(item, index) in cartItems" :key="index">
                    <div class="cart-item-image"></div>
                    <div class="cart-item-info">
                        <div class="cart-item-name">{{ item.name }}</div>
                        <div class="cart-item-price">{{ formatCurrency(item.price) }} x {{ item.quantity }}</div>
                        <button style="background:none; border:none; color:red; cursor:pointer;"
                            @click="removeItemFromCart(index)">Xóa</button>
                    </div>
                </div>
            </div>
            <div class="cart-footer">
                <div class="cart-total">
                    <span>Tổng tiền:</span>
                    <span>{{ formatCurrency(cartTotal) }}</span>
                </div>
                <button class="btn-checkout">
                    Tiến hành Thanh toán
                </button>
            </div>
        </div>
    </div>
</template>

<script>
// Dữ liệu từ database (được định nghĩa trong khối script)
const database = {
    "products": [{ "id": "1", "image_url": "#", "name": "Chuột gaming Logitech G102", "category": { "id": 3, "name": "Phụ kiện" }, "variants": [{ "price": 350000, "stock": 80 }, { "price": 370000, "stock": 40 }] }, { "id": "2", "image_url": "#", "name": "Bàn phím cơ Akko 3087 Ocean Star", "category": { "id": 3, "name": "Phụ kiện" }, "variants": [{ "price": 1650000, "stock": 30 }, { "price": 1750000, "stock": 15 }] }, { "id": "3", "image_url": "#", "name": "Tai nghe Razer Kraken X", "category": { "id": 6, "name": "Âm thanh, Mic thu âm" }, "variants": [{ "price": 950000, "stock": 50 }, { "price": 990000, "stock": 30 }] }, { "id": "4", "image_url": "#", "name": "Màn hình ASUS TUF Gaming VG249Q1A 24 inch", "category": { "id": 5, "name": "PC, Màn hình, Máy in" }, "variants": [{ "price": 3650000, "stock": 20 }, { "price": 3790000, "stock": 12 }] }, { "id": "5", "image_url": "#", "name": "Laptop ASUS TUF Gaming F15 FX506HF", "category": { "id": 2, "name": "Laptop" }, "variants": [{ "price": 18500000, "stock": 6 }, { "price": 18900000, "stock": 4 }] }],
    "slides": [{ "id": "1", "title": "Xiaomi 15T Series", "description": "Chạm đỉnh tuyệt tác - Giá chỉ từ 14.49 Triệu", "imageUrl": "https://placehold.co/1200x400/ff6347/ffffff?text=Xiaomi+15T+Series", "linkUrl": "/sale/summer", "status": "published", "order": 1 }, { "id": "2", "title": "Ipad Pro Mới", "description": "Nâng cấp sức mạnh tuyệt đối", "imageUrl": "https://placehold.co/1200x400/4682b4/ffffff?text=New+Collection", "linkUrl": "/collections/new", "status": "published", "order": 2 }]
};

// Danh mục bổ sung từ hình ảnh tham khảo (để populate sidebar đầy đủ)
const supplementalCategories = [
    { id: '1', name: 'Điện thoại' },
    { id: '2', name: 'Laptop' },
    { id: '3', name: 'Âm thanh, Mic thu âm' },
    { id: '4', name: 'Đồng hồ, Camera' },
    { id: '5', name: 'Đồ gia dụng, Làm đẹp' },
    { id: '6', name: 'Phụ kiện' },
    { id: '7', name: 'PC, Màn hình, Máy in' },
    { id: '8', name: 'TV, Điện máy' },
    { id: '9', name: 'Thu cũ đổi mới' },
    { id: '10', name: 'Hàng cũ' },
    { id: '11', name: 'Khuyến mãi' },
    { id: '12', name: 'Tin công nghệ' }
];

// Lấy danh sách danh mục duy nhất từ sản phẩm và bổ sung
const productCategories = database.products.map(p => p.category);
const combinedCategories = [...productCategories, ...supplementalCategories];

// Lọc trùng và tạo danh sách cuối cùng
const allCategories = [...combinedCategories.reduce((map, obj) => map.set(obj.name, obj), new Map()).values()];


export default {
    name: 'Home',
    data() {
        // --- CÁC HÀM TIỆN ÍCH TẠM THỜI DÙNG CHO KHỞI TẠO DỮ LIỆU ---
        // Do data() chạy trước mounted/methods, cần định nghĩa hàm nhỏ tại đây để xử lý logic lấy giá.
        const formatPrice = (amount) => {
            if (typeof amount !== 'number' || isNaN(amount)) return '0₫';
            return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
        };
        const getMinPrice = (variants) => {
            if (!variants || variants.length === 0) return 0;
            return Math.min(...variants.map(v => v.price));
        };

        // Lấy sản phẩm Laptop từ DB để đưa vào Featured Laptop
        // (Giả định 'database' và 'allCategories' đã được định nghĩa ở phạm vi ngoài)
        const productLaptop = database.products.find(p => p.category.name === 'Laptop');

        const featuredLaptops = productLaptop ?
            [
                {
                    id: productLaptop.id,
                    name: productLaptop.name, // Lấy tên từ database
                    newPrice: formatPrice(getMinPrice(productLaptop.variants)), // Lấy giá thấp nhất từ database
                    oldPrice: formatPrice(getMinPrice(productLaptop.variants) + 3000000), // Giả lập giá cũ
                    promo: 'Giảm 500.000đ cho sinh viên',
                    img: 'https://placehold.co/180x180/ffc107/000000?text=Laptop+F15'
                },
                { id: 'l2', name: 'MacBook Air M3 13"', newPrice: '24.990.000₫', oldPrice: '27.990.000₫', promo: 'Tặng chuột không dây', img: 'https://placehold.co/180x180/6c757d/ffffff?text=Macbook+Air' },
                { id: 'l3', name: 'Laptop Văn Phòng Dell Vostro', newPrice: '12.500.000₫', oldPrice: '14.000.000₫', promo: 'Balo + Office 365', img: 'https://placehold.co/180x180/17a2b8/ffffff?text=Laptop+Dell' },
            ] : [
                { id: 'l_mock', name: 'Laptop Gaming Mẫu (Default)', newPrice: '15.990.000₫', oldPrice: '17.990.000₫', promo: 'Giảm 500.000đ', img: 'https://placehold.co/180x180/ffc107/000000?text=Laptop+Gaming' },
            ];
        // -----------------------------------------------------------------

        return {
            // Dữ liệu chung
            products: database.products,
            slides: database.slides.filter(s => s.status === 'published').sort((a, b) => a.order - b.order),
            categories: allCategories,

            // Dữ liệu giả lập cho các section nổi bật (Đã bổ sung)
            featuredPhones: [
                { id: 'p1', name: 'iPhone 15 Pro Max', newPrice: '28.990.000₫', oldPrice: '34.990.000₫', promo: 'Giảm 1.000.000đ thanh toán VNPAY', img: 'https://placehold.co/180x180/007bff/ffffff?text=iPhone+15' },
                { id: 'p2', name: 'Samsung Galaxy S24 Ultra', newPrice: '25.990.000₫', oldPrice: '29.990.000₫', promo: 'Tặng Galaxy Watch 6', img: 'https://placehold.co/180x180/28a745/ffffff?text=S24+Ultra' },
                { id: 'p3', name: 'Xiaomi 14 Ultra', newPrice: '21.990.000₫', oldPrice: '24.990.000₫', promo: 'Trả góp 0%', img: 'https://placehold.co/180x180/ffc107/000000?text=Xiaomi+14' },
            ],
            featuredLaptops: featuredLaptops,
            newsArticles: [
                { id: 'n1', title: 'Đánh giá chi tiết iPhone 16 Pro Max', excerpt: 'Camera zoom 5x, chip A18 Bionic mạnh mẽ...', img: 'https://placehold.co/300x150/dc3545/ffffff?text=Tin+tuc+1' },
                { id: 'n2', title: 'Top 5 laptop gaming đáng mua nhất 2025', excerpt: 'Các mẫu laptop có hiệu năng tốt và giá hợp lý.', img: 'https://placehold.co/300x150/007bff/ffffff?text=Tin+tuc+2' },
                { id: 'n3', title: 'Công nghệ sạc siêu nhanh 150W sắp ra mắt', excerpt: 'Sạc đầy pin chỉ trong vòng 10 phút.', img: 'https://placehold.co/300x150/28a745/ffffff?text=Tin+tuc+3' },
            ],

            // Slider state
            currentSlide: 0,
            slideInterval: null,

            // Product and Category state
            // Lấy ID của Điện thoại làm mặc định, nếu không có thì lấy ID đầu tiên
            activeCategoryId: allCategories.length > 0 ? allCategories.find(c => c.name === 'Điện thoại')?.id || allCategories[0].id : null,

            // Modal state
            isModalOpen: false,
            selectedProduct: null,
            selectedVariantIndex: 0,

            // Cart state
            isCartOpen: false,
            cartItems: [
                // Giả lập item trong giỏ hàng
                { id: '1', name: 'Chuột gaming Logitech G102', price: 350000, quantity: 1 }
            ],
        };
    },

    computed: {
        filteredProducts() {
            if (!this.activeCategoryId) {
                return this.products;
            }
            // Tìm tên danh mục từ ID
            const activeCategoryName = this.categories.find(c => c.id === this.activeCategoryId)?.name;
            if (!activeCategoryName) return this.products;

            // Lọc sản phẩm
            return this.products.filter(product => product.category.name === activeCategoryName);
        },

        selectedVariant() {
            if (this.selectedProduct && this.selectedProduct.variants && this.selectedProduct.variants.length > 0) {
                const index = Math.max(0, Math.min(this.selectedProduct.variants.length - 1, this.selectedVariantIndex));
                return this.selectedProduct.variants[index];
            }
            return { price: 0, stock: 0 };
        },

        cartTotal() {
            return this.cartItems.reduce((total, item) => total + (item.price * item.quantity), 0);
        }
    },

    mounted() {
        this.startAutoSlide();
    },

    beforeDestroy() {
        clearInterval(this.slideInterval);
    },

    methods: {
        // --- Utility Methods ---
        formatCurrency(amount) {
            if (typeof amount !== 'number' || isNaN(amount)) return '0₫';
            return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
        },

        getMinPrice(variants) {
            if (!variants || variants.length === 0) return 0;
            return Math.min(...variants.map(v => v.price));
        },

        getTotalStock(variants) {
            return variants.reduce((sum, v) => sum + v.stock, 0);
        },

        getCategoryIcon(categoryName) {
            // Mapping icon Font Awesome cho các danh mục
            const iconMap = {
                'Điện thoại': 'fas fa-mobile-alt',
                'Laptop': 'fas fa-laptop',
                'Âm thanh, Mic thu âm': 'fas fa-volume-up',
                'Đồng hồ, Camera': 'fas fa-camera',
                'Đồ gia dụng, Làm đẹp': 'fas fa-house-chimney',
                'Phụ kiện': 'fas fa-battery-full',
                'PC, Màn hình, Máy in': 'fas fa-desktop',
                'TV, Điện máy': 'fas fa-tv',
                'Thu cũ đổi mới': 'fas fa-arrows-rotate',
                'Hàng cũ': 'fas fa-recycle',
                'Khuyến mãi': 'fas fa-tags',
                'Tin công nghệ': 'fas fa-newspaper',
            };
            return iconMap[categoryName] || 'fas fa-box';
        },

        // --- Slider Methods ---
        startAutoSlide() {
            if (this.slides.length > 1 && !this.slideInterval) {
                this.slideInterval = setInterval(() => {
                    this.currentSlide = (this.currentSlide + 1) % this.slides.length;
                }, 5000);
            }
        },
        stopAutoSlide() {
            clearInterval(this.slideInterval);
            this.slideInterval = null;
        },

        goToSlide(index) {
            this.stopAutoSlide();
            this.currentSlide = index;
            this.startAutoSlide();
        },
        nextSlide() {
            this.stopAutoSlide();
            this.currentSlide = (this.currentSlide + 1) % this.slides.length;
            this.startAutoSlide();
        },
        prevSlide() {
            this.stopAutoSlide();
            this.currentSlide = (this.currentSlide - 1 + this.slides.length) % this.slides.length;
            this.startAutoSlide();
        },

        // --- Category Methods ---
        setActiveCategory(id) {
            this.activeCategoryId = id;
        },

        // --- Modal Methods (Quick View) ---
        openQuickView(product) {
            this.selectedProduct = product;
            this.selectedVariantIndex = 0;
            this.isModalOpen = true;
        },

        closeQuickView() {
            this.isModalOpen = false;
            this.selectedProduct = null;
        },

        updateSelectedVariant() {
            // Logic tự động trong computed property
        },

        buyNow(product, variant) {
            alert(`Mua ngay: ${product.name} - Giá: ${this.formatCurrency(variant.price)}`);
            this.closeQuickView();
        },

        // --- Cart Methods ---
        toggleCart() {
            this.isCartOpen = !this.isCartOpen;
        },

        // Phương thức addToCart được sửa đổi để xử lý cả sản phẩm DB và sản phẩm mock (featured)
        addToCart(product, variant = null) {
            let itemPrice;
            let itemName;

            if (variant) {
                // Thêm từ Modal/QuickView
                itemPrice = variant.price;
                itemName = `${product.name} (${variant.color})`;
            } else if (product.variants && product.variants.length > 0) {
                // Thêm từ Product Card (DB) - lấy biến thể rẻ nhất
                const cheapestVariant = product.variants.sort((a, b) => a.price - b.price)[0];
                itemPrice = cheapestVariant.price;
                itemName = product.name;
            } else {
                // Thêm từ Featured Card (Mock Data)
                // Cố gắng parse giá từ chuỗi 'newPrice' nếu là dữ liệu mock
                const priceMatch = product.newPrice ? product.newPrice.replace(/[^0-9]/g, '') : 0;
                itemPrice = priceMatch * 1;
                itemName = product.name;
            }

            if (!itemPrice || itemPrice === 0) return;

            // Tìm item có cùng ID và cùng giá (để phân biệt biến thể/mô hình)
            const existingItemIndex = this.cartItems.findIndex(item =>
                item.id === product.id && item.price === itemPrice);

            if (existingItemIndex > -1) {
                this.cartItems[existingItemIndex].quantity += 1;
            } else {
                this.cartItems.push({
                    id: product.id,
                    name: itemName,
                    price: itemPrice,
                    quantity: 1
                });
            }
            this.isCartOpen = true; // Tự động mở giỏ hàng khi thêm
        },

        removeItemFromCart(index) {
            this.cartItems.splice(index, 1);
        }
    }
};
</script>
<style scoped>
/* ------------------- Global & Variables ------------------- */
:root {
    --primary-color: #dc3545;
    /* Đỏ (CellphoneS) */
    --secondary-color: #f8f9fa;
    /* Xám nhạt */
    --text-color: #333;
    --border-radius: 8px;
    --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --transition-speed: 0.3s;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background-color: var(--secondary-color);
    color: var(--text-color);
    line-height: 1.6;
}

a {
    text-decoration: none;
    color: var(--text-color);
}

/* ------------------- Header ------------------- */
.header {
    background-color: var(--primary-color);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    color: white;
}

.header a,
.logo a {
    color: white;
}

.logo a {
    font-size: 24px;
    font-weight: bold;
}

.search-bar {
    flex-grow: 1;
    max-width: 500px;
    margin: 0 30px;
}

.search-bar input {
    width: 100%;
    padding: 10px 15px;
    border: none;
    border-radius: var(--border-radius);
    font-size: 16px;
    color: var(--text-color);
}

.user-actions a {
    margin-left: 20px;
    font-size: 16px;
    color: white;
    padding: 8px 10px;
    border-radius: var(--border-radius);
    transition: background-color var(--transition-speed);
}

.user-actions a:hover {
    background-color: #a72832;
}

/* ------------------- Layout ------------------- */
.container {
    max-width: 1280px;
    margin: 20px auto;
    padding: 0 20px;
}

.top-section-layout {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

/* ------------------- Sidebar & Slider Bases ------------------- */
.categories-sidebar,
.utility-sidebar {
    flex-shrink: 0;
    background-color: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    max-height: 400px;
    overflow-y: auto;
}

.categories-sidebar {
    width: 250px;
    padding: 10px 0;
}

.utility-sidebar {
    width: 280px;
    padding: 15px;
}

/* ------------------- Categories Sidebar ------------------- */
.category-item-sodo {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    cursor: pointer;
    transition: background-color var(--transition-speed), color var(--transition-speed);
    font-size: 14px;
    font-weight: 500;
}

.category-item-sodo:hover,
.category-item-sodo.active {
    background-color: #f0f0f0;
}

.category-item-sodo.active {
    color: var(--primary-color);
    font-weight: bold;
}

.category-item-sodo i {
    font-size: 18px;
    width: 30px;
    text-align: center;
    margin-right: 10px;
    color: #777;
    transition: color var(--transition-speed);
}

.category-item-sodo.active i {
    color: var(--primary-color);
}

/* ------------------- Slider ------------------- */
.slider {
    flex-grow: 1;
    position: relative;
    overflow: hidden;
    border-radius: 12px;
    box-shadow: var(--box-shadow);
}

.slider-wrapper {
    display: flex;
    transition: transform var(--transition-speed) ease-in-out;
}

.slide {
    min-width: 100%;
    height: 400px;
    background-size: cover;
    background-position: center;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
    padding: 20px;
    position: relative;
}

.slide::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 12px;
}

.slide-content {
    position: relative;
    z-index: 2;
    text-align: center;
}

.slide-content h2 {
    font-size: 3em;
    margin-bottom: 10px;
}

.slide-content p {
    font-size: 1.5em;
}

/* Slider Controls */
.slider-control {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    padding: 15px 10px;
    cursor: pointer;
    z-index: 10;
    font-size: 20px;
    opacity: 0.7;
    transition: opacity var(--transition-speed), background-color var(--transition-speed);
}

.slider-control:hover {
    opacity: 1;
    background-color: rgba(0, 0, 0, 0.7);
}

.slider-control.prev {
    left: 0;
    border-top-right-radius: var(--border-radius);
    border-bottom-right-radius: var(--border-radius);
}

.slider-control.next {
    right: 0;
    border-top-left-radius: var(--border-radius);
    border-bottom-left-radius: var(--border-radius);
}

/* ------------------- Utility Sidebar ------------------- */
.user-info-card {
    padding-bottom: 10px;
    text-align: center;
}

.user-name {
    font-size: 1.1em;
    font-weight: bold;
    color: var(--primary-color);
}

.user-tier {
    font-size: 0.9em;
    color: #8b4513;
    margin-top: 5px;
    font-weight: bold;
}

.user-loyalty-points {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 10px 0;
    flex-direction: column;
    border: 1px solid #f0f0f0;
    padding: 5px;
    border-radius: 5px;
}

.loyalty-icon {
    font-size: 1.5em;
    margin-right: 5px;
    /* Giữ lại nếu muốn icon và text cùng hàng */
}

.loyalty-value {
    font-size: 1.2em;
    font-weight: bold;
    color: #333;
}

.loyalty-text,
.view-store-link {
    font-size: 0.8em;
    color: #6c757d;
}

.view-store-link {
    cursor: pointer;
    font-weight: bold;
    color: #007bff;
    display: block;
    margin-top: 5px;
    text-align: center;
}

.utility-menu {
    list-style: none;
    padding: 0;
    margin: 15px 0;
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.utility-menu li {
    padding: 8px 0;
    font-size: 1em;
    cursor: pointer;
    border-bottom: 1px dotted #eee;
    color: #555;
}

.utility-menu li i {
    margin-right: 8px;
    color: var(--primary-color);
}

.quick-links-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    text-align: center;
    padding: 10px 0;
}

.quick-link-item {
    background-color: #f8f9fa;
    padding: 8px 5px;
    border-radius: 5px;
    font-size: 0.8em;
    color: #555;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.quick-link-item i {
    font-size: 1.5em;
    display: block;
    margin-bottom: 3px;
    color: #007bff;
}

/* ------------------- Product Grid ------------------- */
.product-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.product-card {
    background-color: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    padding: 15px;
    text-align: center;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.product-image {
    height: 150px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f0f0f0;
    border-radius: var(--border-radius);
    font-size: 14px;
    color: #999;
}

.product-name {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 8px;
    height: 40px;
    overflow: hidden;
}

.product-price {
    font-size: 18px;
    color: var(--primary-color);
    font-weight: bold;
    margin-bottom: 5px;
}

.product-stock {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 15px;
}

.card-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.card-actions button {
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 600;
    transition: background-color var(--transition-speed), opacity var(--transition-speed);
    color: white;
    /* Gộp màu chữ chung */
}

.btn-view {
    background-color: #007bff;
}

.btn-add-cart {
    background-color: var(--primary-color);
}

/* ------------------- Modal Styles ------------------- */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 2000;
    opacity: 0;
    visibility: hidden;
    transition: opacity var(--transition-speed), visibility var(--transition-speed);
}

.modal-overlay.open {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background-color: white;
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    width: 90%;
    max-width: 700px;
    position: relative;
    transform: scale(0.9);
    transition: transform var(--transition-speed);
}

.modal-overlay.open .modal-content {
    transform: scale(1);
}

.modal-close-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #333;
}

.modal-body {
    display: flex;
    gap: 20px;
}

.modal-image {
    width: 40%;
    height: 200px;
    background-color: #f0f0f0;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.modal-details {
    width: 60%;
}

.modal-details h4 {
    font-size: 1.5em;
    margin-bottom: 10px;
    color: var(--text-color);
}

.modal-details .price {
    font-size: 2em;
    color: var(--primary-color);
    font-weight: bold;
    margin-bottom: 15px;
}

.modal-details .stock {
    font-size: 0.9em;
    color: #6c757d;
    margin-bottom: 15px;
}

.variant-selector {
    margin-bottom: 20px;
}

.variant-selector label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.variant-selector select {
    width: 100%;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.btn-buy-now {
    width: 100%;
    padding: 15px;
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    margin-top: 15px;
    transition: background-color var(--transition-speed);
}

.btn-buy-now:hover {
    background-color: #218838;
}


/* ------------------- Cart Sidebar Styles ------------------- */
.cart-sidebar {
    position: fixed;
    top: 0;
    right: 0;
    width: 350px;
    height: 100%;
    background-color: white;
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
    z-index: 3000;
    transform: translateX(100%);
    transition: transform var(--transition-speed) ease-in-out;
    display: flex;
    flex-direction: column;
}

.cart-sidebar.open {
    transform: translateX(0);
}

.cart-header {
    padding: 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-header h3 {
    margin: 0;
    font-size: 1.2em;
}

.cart-close-btn {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #333;
}

.cart-items {
    flex-grow: 1;
    overflow-y: auto;
    padding: 15px;
}

.cart-items p {
    text-align: center;
    color: #999;
    margin-top: 20px;
}

.cart-item {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px dotted #eee;
}

.cart-item-image {
    width: 50px;
    height: 50px;
    background-color: #f0f0f0;
    margin-right: 10px;
    flex-shrink: 0;
}

.cart-item-name {
    font-weight: bold;
    font-size: 0.9em;
}

.cart-item-price {
    color: var(--primary-color);
    font-size: 0.8em;
    margin-top: 5px;
}

.cart-footer {
    padding: 15px;
    border-top: 1px solid #eee;
    flex-shrink: 0;
}

.cart-total {
    display: flex;
    justify-content: space-between;
    font-size: 1.2em;
    font-weight: bold;
    margin-bottom: 10px;
}

.btn-checkout {
    width: 100%;
    padding: 15px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 1em;
    font-weight: bold;
    cursor: pointer;
    transition: background-color var(--transition-speed);
}

.btn-checkout:hover {
    background-color: #b82c39;
}


/* ------------------- Responsive Adjustments ------------------- */
@media (max-width: 992px) {
    .product-grid {
        grid-template-columns: repeat(3, 1fr);
    }

    .top-section-layout {
        flex-direction: column;
    }

    .categories-sidebar,
    .utility-sidebar {
        width: 100%;
        max-height: none;
    }

    .categories-sidebar {
        order: 1;
        white-space: nowrap;
        overflow-x: auto;
        padding: 10px 0;
        display: flex;
        gap: 10px;
    }

    .category-item-sodo {
        flex-direction: column;
        justify-content: center;
        text-align: center;
        min-width: 100px;
        padding: 10px 5px;
        flex-shrink: 0;
        border: 1px solid #eee;
        border-radius: var(--border-radius);
    }

    .category-item-sodo:hover,
    .category-item-sodo.active {
        background-color: #f0f0f0;
    }

    .category-item-sodo span {
        font-size: 12px;
    }

    .category-item-sodo i {
        margin-right: 0;
    }

    .slider {
        order: 2;
    }

    .utility-sidebar {
        order: 3;
    }
}

@media (max-width: 768px) {
    .product-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .header {
        flex-wrap: wrap;
    }

    .search-bar {
        order: 3;
        margin: 10px 0 0;
        max-width: 100%;
    }

    .slide {
        height: 250px;
    }

    .slider-control {
        padding: 10px 5px;
        font-size: 16px;
    }

    .modal-body {
        flex-direction: column;
    }

    .modal-image,
    .modal-details {
        width: 100%;
    }

    .cart-sidebar {
        width: 100%;
    }
}

.trust-block {
    display: flex;
    justify-content: space-around;
    align-items: center;
    background-color: #fff;
    padding: 15px 0;
    margin: 30px 0;
    border-radius: 10px;
    box-shadow: var(--box-shadow);
    text-align: center;
    flex-wrap: wrap;
}

.trust-item {
    flex: 1;
    min-width: 150px;
    font-size: 15px;
    color: #444;
    font-weight: 600;
    transition: transform var(--transition-speed);
}

.trust-item:hover {
    transform: translateY(-3px);
    color: var(--primary-color);
}

/* ------------------- PRODUCT SECTIONS ------------------- */
.product-section {
    margin: 50px 0;
}

.section-title {
    font-size: 1.8em;
    color: var(--primary-color);
    font-weight: bold;
    text-align: left;
    margin-bottom: 25px;
    border-left: 6px solid var(--primary-color);
    padding-left: 10px;
}

/* Card layout */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.product-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    text-align: center;
    padding: 15px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
    position: relative;
}

.product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
}

/* Image */
.product-card img {
    width: 100%;
    height: 180px;
    object-fit: contain;
    margin-bottom: 10px;
    transition: transform 0.3s ease;
}

.product-card:hover img {
    transform: scale(1.05);
}

/* Name + Price */
.product-name {
    font-size: 1em;
    font-weight: 600;
    color: #222;
    margin: 10px 0 5px;
    min-height: 40px;
}

.product-price {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}

.new-price {
    color: var(--primary-color);
    font-weight: bold;
    font-size: 1.1em;
}

.old-price {
    color: #999;
    text-decoration: line-through;
    font-size: 0.9em;
}

/* Promo */
.product-promo {
    background-color: #ffeaea;
    color: var(--primary-color);
    padding: 4px 10px;
    border-radius: 5px;
    font-size: 0.9em;
    display: inline-block;
    margin-bottom: 10px;
}

/* Action buttons */
.card-actions-small {
    display: flex;
    justify-content: center;
    gap: 10px;
}

.card-actions-small button {
    border: none;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 0.9em;
    font-weight: 600;
    cursor: pointer;
    color: white;
    transition: all 0.3s ease;
}

.btn-view {
    background-color: #007bff;
}

.btn-add-cart {
    background-color: var(--primary-color);
}

.card-actions-small button:hover {
    opacity: 0.9;
    transform: scale(1.05);
}

/* ------------------- NEWS SECTION ------------------- */
.news-section {
    margin: 60px 0;
}

.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.news-card {
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}

.news-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
}

.news-card img {
    width: 100%;
    height: 150px;
    object-fit: cover;
}

.news-title {
    font-size: 1.1em;
    font-weight: 600;
    color: #222;
    padding: 10px 15px 0;
    min-height: 48px;
}

.news-excerpt {
    color: #666;
    font-size: 0.9em;
    padding: 0 15px 10px;
    line-height: 1.4em;
}

.read-more {
    display: block;
    color: var(--primary-color);
    font-weight: bold;
    font-size: 0.9em;
    padding: 0 15px 15px;
    text-align: right;
    transition: color var(--transition-speed);
}

.read-more:hover {
    color: #b82c39;
}

/* ------------------- Responsive ------------------- */
@media (max-width: 768px) {
    .trust-block {
        flex-direction: column;
        gap: 10px;
    }

    .section-title {
        text-align: center;
    }
}
</style>