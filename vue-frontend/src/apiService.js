import axios from "axios";

const baseURL = import.meta.env.VITE_API_BASE_URL;

const apiService = axios.create({
  baseURL: baseURL,
  headers: {
    // XÓA DÒNG Content-Type Ở ĐÂY ĐI
    // Để Axios tự động quyết định (JSON hay File)
    'Accept': 'application/json',
  },
});

apiService.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('adminToken'); 
        
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

export default apiService;