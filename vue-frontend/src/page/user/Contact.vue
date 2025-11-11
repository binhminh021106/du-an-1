<template>
  <section class="contact-page py-5">
    <div class="container">
      <div class="text-center mb-5">
        <h2 class="fw-bold text-primary mb-3">
          <i class="bi bi-chat-dots-fill me-3"></i> Liên hệ với chúng tôi
        </h2>
        <p class="text-muted fs-5">
          Chúng tôi luôn sẵn sàng hỗ trợ và lắng nghe ý kiến từ bạn.
          Hãy để lại lời nhắn, đội ngũ của <img class="icon-logo" src="../../../public/img/icon.png" alt=""> sẽ phản hồi
          trong thời gian sớm nhất.
        </p>
      </div>

      <div class="row g-4 align-items-stretch">
        <div class="col-lg-6">
          <div class="card border-0 shadow-lg p-4 h-100 contact-form">
            <h5 class="fw-bold text-dark mb-4">Gửi lời nhắn cho chúng tôi</h5>
            <form>
              <div class="mb-3">
                <label class="form-label">Họ và tên</label>
                <input type="text" class="form-control form-control-lg" placeholder="Nhập họ tên của bạn" />
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control form-control-lg" placeholder="Nhập email của bạn" />
              </div>
              <div class="mb-3">
                <label class="form-label">Nội dung</label>
                <textarea class="form-control form-control-lg" rows="5"
                  placeholder="Nhập nội dung liên hệ..."></textarea>
              </div>
              <button class="btn btn-primary btn-lg w-100 fw-semibold">
                Gửi liên hệ <i class="bi bi-envelope-paper-fill ms-2"></i> </button>
            </form>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card border-0 shadow-lg p-4 h-100 contact-info text-white position-relative overflow-hidden">
            <div class="overlay"></div>
            <h5 class="fw-bold mb-4">Thông tin liên hệ</h5>

            <ul class="list-unstyled fs-6 mb-4">
              <li class="mb-3">
                <i class="bi bi-shop me-2"></i> <strong>ThinkHub Store</strong>
              </li>
              <li class="mb-3">
                <i class="bi bi-pin-map me-2"></i> 123 Nguyễn Trãi, Quận 5, TP. Hồ Chí Minh
              </li>
              <li class="mb-3">
                <i class="bi bi-headset me-2"></i> 0909 123 456
              </li>
              <li class="mb-3">
                <i class="bi bi-send-check me-2"></i> support@ThinkHubstore.vn
              </li>
              <li>
                <i class="bi bi-calendar-check me-2"></i> Thứ 2 - Thứ 7: 8h00 - 21h00
              </li>
            </ul>

            <div class="rounded overflow-hidden shadow" style="height: 500px;">
              <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.5127744631577!2d106.68005427590663!3d10.772938889377557!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f37d4a9e1a3%3A0xabcde1234567890!2zMTIzIE5ndXnhu4VuIFRyw6FpLCBRdeG6rW4gNSwgSOG7kyBDaMOtbmgsIFRWSCBI4buTIENow60gTWluaA!5e0!3m2!1svi!2s!4v1728730123456"
                width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
            </div>

          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.icon-logo {
  width: 150px;
}

.brand-name {
  font-weight: bold;
  color: rgb(0, 72, 61);
  display: inline-flex;
  align-items: center;
}

.form-control:focus {
  border-color: rgb(0, 153, 129);
  box-shadow: 0 0 0 0.25rem rgba(0, 153, 129, 0.25);
}

.contact-page {
  background-color: #f0f8f7;
}

.text-primary {
  color: rgb(0, 72, 61) !important;
}


.contact-form {
  border-radius: 1rem;
  background: #fff;
  transition: all 0.3s ease;
}

.contact-form:hover {
  transform: translateY(-4px);
  box-shadow: 0 0.5rem 1rem rgba(0, 72, 61, 0.15) !important;
}

.contact-info {
  border-radius: 1rem;
  background: linear-gradient(135deg, rgb(0, 153, 129), rgb(0, 72, 61));
  position: relative;
  z-index: 1;
}

.contact-info .overlay {
  content: "";
  position: absolute;
  inset: 0;
  background: url("https://www.transparenttextures.com/patterns/arabesque.png");
  opacity: 0.15;
  z-index: 0;
}

.contact-info * {
  position: relative;
  z-index: 2;
}

.contact-info i {
  font-size: 1.2rem;
  color: #ffc107;
}

button.btn-primary {
  background-color: rgb(0, 153, 129);
  border-color: rgb(0, 153, 129);
  transition: all 0.3s;
}

button.btn-primary:hover {
  background-color: rgb(0, 72, 61);
  border-color: rgb(0, 72, 61);
}
</style>