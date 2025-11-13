import axios from 'axios';

// 1. Lấy link base URL từ file .env
//    (Project Vite của bạn tự động hiểu "import.meta.env.VITE_...")
const baseURL = import.meta.env.VITE_API_BASE_URL; //

// 2. Tạo một phiên bản (instance) của axios đã được cấu hình
const apiService = axios.create({
  baseURL: baseURL, // baseURL sẽ là "http://localhost:3000"
  headers: {
    'Content-Type': 'application/json'
    // (Sau này có login, bạn sẽ nhét Token vào đây)
  }
});

// 3. Export nó ra để toàn bộ dự án có thể xài
export default apiService;