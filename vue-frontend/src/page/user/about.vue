<template>
  <section class="about-page py-5">
    <div class="container">
      <!-- Tiêu đề -->
      <div class="text-center mb-5">
        <h2 class="fw-bold text-primary mb-3">
          <i class="bi bi-info-circle-fill me-3"></i> Về chúng tôi
        </h2>
        <p class="text-muted fs-5">
          <img class="icon-logo" src="../../../public/img/icon.png" alt="Logo" />
          <strong class="text-primary ms-2"></strong> là thương hiệu hướng đến 
          việc mang lại trải nghiệm mua sắm hiện đại, tiện lợi và phong cách cho mọi khách hàng.
        </p>
      </div>

      <div class="row g-5 align-items-center">
        <!-- Hình ảnh giới thiệu -->
        <div class="col-lg-6">
          <div class="about-image-wrapper">
            <img
              src="../../../public/img/imgabout.jpg"
              alt="about us"
              class="img-fluid rounded shadow-lg"
            />
          </div>
        </div>

        <!-- Nội dung giới thiệu -->
        <div class="col-lg-6">
          <h3 class="fw-bold text-primary mb-3">Câu chuyện của chúng tôi</h3>
          <p class="text-muted fs-6 mb-4">
            Được thành lập với niềm đam mê về công nghệ và phong cách sống hiện đại, 
            <strong></strong> mong muốn trở thành cầu nối giữa sản phẩm chất lượng 
            và trải nghiệm người dùng hoàn hảo. 
          </p>

          <ul class="list-unstyled fs-6 mb-4">
            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Sản phẩm chính hãng, chất lượng cao</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Giao hàng nhanh chóng và an toàn</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Hỗ trợ khách hàng tận tâm 24/7</li>
          </ul>

          <p class="text-muted fs-6 mb-4">
            Chúng tôi không chỉ bán hàng — mà còn mang đến sự hài lòng và niềm tin cho khách hàng, 
            từ những sản phẩm nhỏ nhất đến dịch vụ sau bán hàng.
          </p>

          <button class="btn btn-primary btn-lg fw-semibold">
            Khám phá sản phẩm <i class="bi bi-arrow-right-circle ms-2"></i>
          </button>
        </div>
      </div>

      <!-- Phần sứ mệnh & tầm nhìn -->
      <div class="row mt-5">
        <div class="col-lg-4">
          <div class="card border-0 shadow-lg p-4 h-100 text-center mission-card">
            <i class="bi bi-bullseye text-primary fs-1 mb-3"></i>
            <h5 class="fw-bold mb-3">Sứ mệnh</h5>
            <p class="text-muted">
              Mang lại những sản phẩm tốt nhất, giúp khách hàng tận hưởng 
              cuộc sống hiện đại với chất lượng và sự tiện nghi.
            </p>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card border-0 shadow-lg p-4 h-100 text-center mission-card">
            <i class="bi bi-eye-fill text-primary fs-1 mb-3"></i>
            <h5 class="fw-bold mb-3">Tầm nhìn</h5>
            <p class="text-muted">
              Trở thành thương hiệu đáng tin cậy hàng đầu trong lĩnh vực bán lẻ trực tuyến tại Việt Nam.
            </p>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card border-0 shadow-lg p-4 h-100 text-center mission-card">
            <i class="bi bi-heart-fill text-primary fs-1 mb-3"></i>
            <h5 class="fw-bold mb-3">Giá trị cốt lõi</h5>
            <p class="text-muted">
              Uy tín – Chất lượng – Tận tâm. Mọi hành động của chúng tôi đều hướng đến khách hàng.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.about-page {
  background-color: #f0f8f7;
}

.text-primary {
  color: rgb(0, 72, 61) !important;
}

.icon-logo {
  width: 120px;
  vertical-align: middle;
}

.about-image-wrapper img {
  transition: transform 0.4s ease, box-shadow 0.4s ease;
  border-radius: 1rem;
}
.about-image-wrapper img:hover {
  transform: scale(1.03);
  box-shadow: 0 0.75rem 1.5rem rgba(0, 72, 61, 0.25);
}

.mission-card {
  border-radius: 1rem;
  transition: all 0.3s ease;
}
.mission-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 0.5rem 1rem rgba(0, 72, 61, 0.15);
}

button.btn-primary {
  background-color: rgb(0, 153, 129);
  border-color: rgb(0, 153, 129);
  transition: all 0.3s ease;
}
button.btn-primary:hover {
  background-color: rgb(0, 72, 61);
  border-color: rgb(0, 72, 61);
}
</style>
