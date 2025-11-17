import { reactive, readonly } from "vue";

const state = reactive({
  isAuthenticated: false,
  user: null,
  token: null,
});

const checkAuth = () => {
  const token = localStorage.getItem("authToken");
  const user = localStorage.getItem("userData");
  if (token && user) {
    state.isAuthenticated = true;
    state.user = JSON.parse(user);
    state.token = token;
    apiService.defaults.headers.common["Authorization"] = `Bearer ${token}`;
  }
};

const logout = () => {
  localStorage.removeItem("authToken");
  localStorage.removeItem("userData");
  state.isAuthenticated = false;
  state.user = null;
  state.token = null;
  delete apiService.defaults.headers.common["Authorization"];
  router.push({ name: "login" });
};

checkAuth();

export default {
  state: readonly(state),
  logout,
};
