const adminLayoutRoutes = [
  {
    path: "/admin",
    component: () => import("../layout/layoutAdmin.vue"),
    children: [
      {
        path: "",
        name: "admin-dashboard",
        component: () => import("../page/admin/index.vue"),
      },
      {
        path: "users",
        name: "admin-users",
        component: () => import("../page/admin/adminUser/index.vue"),
      },
      {
        path: "products",
        name: "admin-products",
        component: () => import("../page/admin/product/index.vue"),
      },
      {
        path: "adminUsers",
        name: "admin-adminUsers",
        component: () => import("../page/admin/adminUser/index.vue"),
      },
      {
        path: "categories",
        name: "admin-categories",
        component: () => import("../page/admin/category/index.vue"),
      },
      {
        path: "products",
        name: "admin-products",
        component: () => import("../page/admin/product/index.vue"),
      },
      {
        path: "orders",
        name: "admin-orders",
        component: () => import("../page/admin/order/index.vue"),
      },
      {
        path: "comments",
        name: "admin-comments",
        component: () => import("../page/admin/content/comment.vue"),
      },
      {
        path: "reviews",
        name: "admin-reviews",
        component: () => import("../page/admin/content/review.vue"),
      },
      {
        path: "slides",
        name: "admin-slides",
        component: () => import("../page/admin/content/slider.vue"),
      },
      {
        path: "coupons",
        name: "admin-coupons",
        component: () => import("../page/admin/content/coupon.vue"),
      },
    ],
  },
];

const adminAuthRoutes = [
  {
    path: "/admin/register",
    name: "admin-register",
    component: () => import("../page/admin/adminLoginRegister/register.vue"),
  },
  {
    path: "/admin/login",
    name: "admin-login",
    component: () => import("../page/admin/adminLoginRegister/login.vue"),
  },
];

const allAdminRoutes = [...adminLayoutRoutes, ...adminAuthRoutes];

export default allAdminRoutes;
