<script setup>
import { ref, reactive } from 'vue';
import Swal from 'sweetalert2';
// Kiểm tra lại đường dẫn import apiService cho đúng với project của bạn
import apiService from '../../apiService'; 

// 1. Khởi tạo biến lưu dữ liệu form
const contactForm = reactive({
  name: '',
  email: '',
  content: '',
  attachment: null // [MỚI] Biến lưu file ảnh
});

// [MỚI] Biến để hiển thị tên file đã chọn cho user thấy
const fileName = ref('');

const isSubmitting = ref(false);

// [MỚI] Hàm xử lý khi user chọn file
const handleFileUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    // Validate cơ bản phía client
    if (file.size > 5 * 1024 * 1024) { // > 5MB
      Swal.fire('File quá lớn', 'Vui lòng chọn ảnh dưới 5MB', 'warning');
      event.target.value = ''; // Reset input
      return;
    }
    contactForm.attachment = file;
    fileName.value = file.name;
  }
};

// 2. Hàm xử lý khi bấm Gửi
const submitContact = async () => {
  if (!contactForm.name || !contactForm.email || !contactForm.content) {
    Swal.fire({
      icon: 'warning',
      title: 'Thiếu thông tin',
      text: 'Vui lòng điền đầy đủ Họ tên, Email và Nội dung!',
    });
    return;
  }

  isSubmitting.value = true;

  try {
    // [QUAN TRỌNG] Chuyển sang dùng FormData để gửi file
    const formData = new FormData();
    formData.append('name', contactForm.name);
    formData.append('email', contactForm.email);
    formData.append('content', contactForm.content);
    
    if (contactForm.attachment) {
      formData.append('attachment', contactForm.attachment);
    }

    // Gọi API với config multipart/form-data (thường axios tự nhận diện FormData)
    const response = await apiService.post('/contact-submit', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    if (response.data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Thành công!',
        text: 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất!',
        confirmButtonColor: '#009981'
      });

      // Reset form
      contactForm.name = '';
      contactForm.email = '';
      contactForm.content = '';
      contactForm.attachment = null;
      fileName.value = '';
      // Reset input file (cần dùng DOM hoặc ref để reset sạch hơn nếu muốn)
      document.getElementById('fileInput').value = '';
    }

  } catch (error) {
    console.error(error);
    Swal.fire({
      icon: 'error',
      title: 'Có lỗi xảy ra',
      text: 'Không thể gửi liên hệ lúc này. Vui lòng thử lại sau.',
    });
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <section class="contact-page py-5">
    <div class="container">
      <div class="text-center mb-5">
        <h2 class="fw-bold text-primary mb-3">
          <i class="bi bi-chat-dots-fill me-3"></i> Liên hệ với chúng tôi
        </h2>
        <p class="text-muted fs-5">
          Chúng tôi luôn sẵn sàng hỗ trợ và lắng nghe ý kiến từ bạn.
          <br>Đặc biệt hỗ trợ nhanh các vấn đề hoàn hàng, bảo hành.
        </p>
      </div>

      <div class="row g-4 align-items-stretch">
        <div class="col-lg-6">
          <div class="card border-0 shadow-lg p-4 h-100 contact-form">
            <h5 class="fw-bold text-dark mb-4">Gửi yêu cầu hỗ trợ</h5>
            
            <form @submit.prevent="submitContact">
              <div class="mb-3">
                <label class="form-label">Họ và tên</label>
                <input 
                  type="text" 
                  class="form-control form-control-lg" 
                  placeholder="Nhập họ tên của bạn" 
                  v-model="contactForm.name"
                />
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input 
                  type="email" 
                  class="form-control form-control-lg" 
                  placeholder="Nhập email của bạn" 
                  v-model="contactForm.email"
                />
              </div>
              <div class="mb-3">
                <label class="form-label">Nội dung</label>
                <textarea 
                  class="form-control form-control-lg" 
                  rows="4"
                  placeholder="Bạn cần hổ trợ gì ?"
                  v-model="contactForm.content"
                ></textarea>
              </div>

              <!-- [MỚI] Input Upload Ảnh -->
              <div class="mb-4">
                <label class="form-label">Hình ảnh đính kèm (nếu có)</label>
                <div class="input-group">
                  <input 
                    type="file" 
                    class="form-control" 
                    id="fileInput"
                    accept="image/*"
                    @change="handleFileUpload"
                  >
                  <label class="input-group-text" for="fileInput"><i class="bi bi-upload"></i></label>
                </div>
                <div class="form-text text-muted" v-if="!fileName">Chấp nhận ảnh jpg, png (Max 5MB)</div>
                <div class="form-text text-success fw-bold" v-if="fileName">
                  <i class="bi bi-check-circle-fill"></i> Đã chọn: {{ fileName }}
                </div>
              </div>
              
              <button class="btn btn-primary btn-lg w-100 fw-semibold" :disabled="isSubmitting">
                <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                <span v-if="!isSubmitting">Gửi yêu cầu <i class="bi bi-send-fill ms-2"></i></span>
                <span v-else>Đang gửi...</span>
              </button>
            </form>

          </div>
        </div>

        <!-- Phần thông tin bên phải giữ nguyên -->
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

            <iframe
              src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.5127744631577!2d106.68005427590663!3d10.772938889377557!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f37d4a9e1a3%3A0xabcde1234567890!2zMTIzIE5ndXnhu4VuIFRyw6FpLCBRdeG6rW4gNSwgSOG7kyBDaMOtbmgsIFRWSCBI4buTIENow60gTWluaA!5e0!3m2!1svi!2s!4v1728730123456"
              width="100%" height="100%" style="border:0;" allowfullscreen loading="lazy"
              referrerpolicy="no-referrer-when-downgrade">
            </iframe>

          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Giữ nguyên style cũ của bạn */
.icon-logo { width: 150px; }
.form-control:focus { border-color: rgb(0, 153, 129); box-shadow: 0 0 0 0.25rem rgba(0, 153, 129, 0.25); }
.contact-page { background-color: #f0f8f7; }
.text-primary { color: rgb(0, 72, 61) !important; }
.contact-form { border-radius: 1rem; background: #fff; transition: all 0.3s ease; }
.contact-form:hover { transform: translateY(-4px); box-shadow: 0 0.5rem 1rem rgba(0, 72, 61, 0.15) !important; }
.contact-info { border-radius: 1rem; background: linear-gradient(135deg, rgb(0, 153, 129), rgb(0, 72, 61)); position: relative; z-index: 1; }
.contact-info .overlay { content: ""; position: absolute; inset: 0; background: url("https://www.transparenttextures.com/patterns/arabesque.png"); opacity: 0.15; z-index: 0; }
.contact-info * { position: relative; z-index: 2; }
.contact-info i { font-size: 1.2rem; color: #ffc107; }
button.btn-primary { background-color: rgb(0, 153, 129); border-color: rgb(0, 153, 129); transition: all 0.3s; }
button.btn-primary:hover { background-color: rgb(0, 72, 61); border-color: rgb(0, 72, 61); }

/* Style input file */
.input-group-text {
    background-color: #e9ecef;
    color: #495057;
    border: 1px solid #ced4da;
}
</style>