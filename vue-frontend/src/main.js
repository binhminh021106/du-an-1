import { createApp } from "vue";
import App from "./App.vue";
import router from "./router/index.js";
import "./authStore.js";
import store from './store' 

import VueApexCharts from "vue3-apexcharts";

import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";
import "bootstrap-icons/font/bootstrap-icons.css";

import "@fortawesome/fontawesome-free/css/all.min.css";

import "./assets/css/adminlte.min.css";

import "./style.css";

const app = createApp(App);
app.use(VueApexCharts);
app.use(router);
app.use(store) // Đăng ký Vuex
app.mount("#app");

