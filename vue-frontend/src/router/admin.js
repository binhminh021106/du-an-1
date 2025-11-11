const adminLayoutRoutes = [
  {
    path: "/admin",
    component: () => import("../layout/layoutAdmin.vue"),
    children: [
      {
        path: "",
        name: "admin-dashboard",
        component: () => import("../page/admin/Index.vue"),
      },
      {
        path: "products",
        name: "admin-products",
        component: () => import("../page/admin/product/index.vue"),
      },
      {
        path: "adminAccount",
        name: "admin-adminAccount",
        component: () => import("../page/admin/adminAccount/AccountAdmin.vue"),
      },
      {
        path: "userAccount",
        name: "admin-userAccount",
        component: () => import("../page/admin/adminAccount/AccountUser.vue"),
      },
      {
        path: "categories",
        name: "admin-categories",
        component: () => import("../page/admin/category/Index.vue"),
      },
      {
        path: "products",
        name: "admin-products",
        component: () => import("../page/admin/product/index.vue"),
      },
      {
        path: "orders",
        name: "admin-orders",
        component: () => import("../page/admin/order/Index.vue"),
      },
      {
        path: "comments",
        name: "admin-comments",
        component: () => import("../page/admin/content/Comment.vue"),
      },
      {
        path: "reviews",
        name: "admin-reviews",
        component: () => import("../page/admin/content/Review.vue"),
      },
      {
        path: "slides",
        name: "admin-slides",
        component: () => import("../page/admin/content/Slider.vue"),
      },
      {
        path: "coupons",
        name: "admin-coupons",
        component: () => import("../page/admin/content/Coupon.vue"),
      },
    ],
  },
];

const adminAuthRoutes = [
  {
    path: "/admin/register",
    name: "admin-register",
    component: () => import("../page/admin/adminLoginRegister/Register.vue"),
  },
  {
    path: "/admin/login",
    name: "admin-login",
    component: () => import("../page/admin/adminLoginRegister/Login.vue"),
  },
];

const allAdminRoutes = [...adminLayoutRoutes, ...adminAuthRoutes];

export default allAdminRoutes;
