const adminLayoutRoutes = [
  {
    path: "/admin",
    component: () => import("../layout/layoutAdmin.vue"),
    children: [
      {
        path: "users",
        name: "admin-user",
        component: () => import("../page/admin/adminUser/index.vue"),
      },
      {
        path: "products",
        name: "admin-product",
        component: () => import("../page/admin/adminProduct/index.vue"),
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
];

const allAdminRoutes = [...adminLayoutRoutes, ...adminAuthRoutes];

export default allAdminRoutes;
