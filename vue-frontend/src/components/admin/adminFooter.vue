<template>
  <footer class="app-footer bg-white border-top py-3 text-sm overflow-hidden">
    <!-- Bên phải (ẩn trên mobile) -->
    <div class="float-end d-none d-sm-inline opacity-75">
      Hi guys 👋
    </div>
    
    <!-- Bên trái (Nội dung chính - Marquee) -->
    <div class="marquee-container">
      <div class="marquee-content">
        <!-- Nội dung lặp lại 2 lần để tạo hiệu ứng chạy vô tận -->
        <span class="text-success ">
          THINKHUB 
          <span class="text-success">WEBSITE CÔNG NGHỆ</span>
          <span class="text-primary">Nhóm thinkhub</span> 
        </span>
        <span class="text-success">
          THINKHUB 
          <span class="text-success">WEBSITE CÔNG NGHỆ</span>
          <span class="text-primary">Nhóm thinkhub</span> 
        </span>
        <span class="text-success">
          THINKHUB 
          <span class="text-success">WEBSITE CÔNG NGHỆ</span>
          <span class="text-primary">Nhóm thinkhub</span> 
        </span>
        <span class="text-success">
          THINKHUB 
          <span class="text-success">WEBSITE CÔNG NGHỆ</span>
          <span class="text-primary">Nhóm thinkhub</span> 
        </span>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.app-footer {
  background-color: #f8f9fa !important;
  color: #495057;
  position: relative; /* Để container con absolute theo */
}

/* Container giới hạn vùng hiển thị */
.marquee-container {
  width: 100%;
  overflow: hidden; /* Ẩn phần chữ chạy ra ngoài */
  white-space: nowrap; /* Không xuống dòng */
  position: absolute; /* Đè lên footer */
  top: 50%;
  left: 0;
  transform: translateY(-50%); /* Căn giữa theo chiều dọc */
  /* Để chừa chỗ cho phần "Hi guys" bên phải nếu cần, hoặc bỏ qua */
  max-width: calc(100% - 100px); /* Trừ khoảng trống cho nút bên phải */
}

/* Phần nội dung chạy */
.marquee-content {
  display: inline-block;
  animation: marquee 20s linear infinite; /* Chạy trong 20s, lặp vô tận */
}

/* Mỗi cụm chữ */
.text-success {
  display: inline-block;
  padding-right: 50px; /* Khoảng cách giữa các cụm chữ */
  font-weight: 600;
}

/* Hiệu ứng chạy từ phải qua trái */
@keyframes marquee {
  0% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(-50%); /* Dịch chuyển 50% độ dài (vì ta nhân đôi nội dung) */
  }
}

/* Khi di chuột vào thì dừng lại cho dễ đọc */
.marquee-container:hover .marquee-content {
  animation-play-state: paused;
}

/* Custom colors */
.text-success {
  color: #009981 !important;
  font-weight: 700;
}

.text-primary {
  color: #0d6efd !important;
  font-weight: 700;
}
</style>