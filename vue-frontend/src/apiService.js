import axios from "axios";

const baseURL = import.meta.env.VITE_API_BASE_URL;

const apiService = axios.create({
  baseURL: baseURL,
  headers: {
    "Content-Type": "application/json",
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
