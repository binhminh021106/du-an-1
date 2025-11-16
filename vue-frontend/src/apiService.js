import axios from "axios";

const baseURL = import.meta.env.VITE_API_BASE_URL;

const apiService = axios.create({
  baseURL: baseURL,
  headers: {
    "Content-Type": "application/json",
    // (Sau này có login, bạn sẽ nhét Token vào đây)
  },
});

export default apiService;
