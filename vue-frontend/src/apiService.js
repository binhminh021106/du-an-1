import axios from "axios";

const baseURL = import.meta.env.VITE_API_BASE_URL;

const apiService = axios.create({
  baseURL: baseURL,
  withCredentials: false,
  headers: {
    // Để Axios tự động quyết định Content-Type
    Accept: "application/json",
  },
});

apiService.interceptors.request.use(
  (config) => {
    // Logic: Nếu URL có chữ 'admin' thì dùng Token Admin, còn lại dùng Token User
    // Dùng config.url.includes để check
    const isApiAdmin = config.url && config.url.toString().includes("admin");

    if (isApiAdmin) {
      const adminToken = localStorage.getItem("adminToken");
      if (adminToken) {
        config.headers.Authorization = `Bearer ${adminToken}`;
      }
    } else {
      const userToken = localStorage.getItem("authToken");
      if (userToken) {
        config.headers.Authorization = `Bearer ${userToken}`;
      }
    }

    return config;
  },
  (error) => Promise.reject(error)
);

export default apiService;
