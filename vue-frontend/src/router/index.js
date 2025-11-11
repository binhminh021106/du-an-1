import { createRouter, createWebHistory } from "vue-router";
import admin from "./admin.js";
import users from "./user.js";

const routes = [...admin, ...users];

const router = createRouter({
  history: createWebHistory(),
  routes,
  linkExactActiveClass: 'active' 
})

export default router