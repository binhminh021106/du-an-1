import { createRouter, createWebHistory } from "vue-router";
import admin from "./admin.js";
import users from "./user.js"; // Giả sử bạn đã có file này

const routes = [...admin, ...users];

const router = createRouter({
  history: createWebHistory(),
  routes,
  linkExactActiveClass: 'active',
  scrollBehavior(to, from, savedPosition) {
    if (to.hash) {
      return {
        el: to.hash,
        behavior: 'smooth',
      }
    } else if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0, left: 0 }
    }
  },
})

router.beforeEach((to, from, next) => {
  const userToken = localStorage.getItem('user_token') || localStorage.getItem('userData');
  const adminToken = localStorage.getItem('adminToken');

  const guestRoutesForUser = ['login', 'register'];
  const guestRoutesForAdmin = ['admin-login', 'admin-register'];

  if (to.matched.some(record => record.meta.requiresAuthAdmin)) {
    if (!adminToken) {
      return next({ name: 'admin-login' });
    }
  }

  if (adminToken && guestRoutesForAdmin.includes(to.name)) {
    return next({ name: 'admin-dashboard' });
  }

  if (userToken && guestRoutesForUser.includes(to.name)) {
    return next({ name: 'home' }); 
  }

  next();
});

export default router;