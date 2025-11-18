import { createRouter, createWebHistory } from "vue-router";
import admin from "./admin.js";
import users from "./user.js";

const routes = [...admin, ...users];

const router = createRouter({
  history: createWebHistory(),
  routes,
  linkExactActiveClass: 'active' 
})

router.beforeEach((to, from, next) => {
  const userToken = localStorage.getItem('user_token') || localStorage.getItem('userData');
  const adminToken = localStorage.getItem('admin_token');

  const guestRoutesForUser = ['login', 'register'];
  const guestRoutesForAdmin = ['admin-login', 'admin-register'];

  if (userToken && guestRoutesForUser.includes(to.name)) {
    return next({ name: 'home' });
  }

  if (adminToken && guestRoutesForAdmin.includes(to.name)) {
    return next({ name: 'admin-dashboard' });
  }

  next();
});


export default router