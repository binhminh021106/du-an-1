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
