import { createRouter, createWebHistory } from "vue-router";
import admin from "./admin.js";
import users from "./user.js";


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


export default router