const users = [
  {
    path: "/",
    component: () => import("../layout/layoutUser.vue"),
    children: [
      {
        path: "",
        name: "home",
        component: () => import("../page/user/home.vue"),
      },
      {
        path: "about",
        name: "about",
        component: () => import("../page/user/about.vue"),
      },
      {
        path: "contact",
        name: "contact",
        component: () => import("../page/user/Contact.vue"),
      },
      {
        path: "notFound",
        name: "notFound",
        component: () => import("../page/user/notFound.vue"),
      },
      {
        path: "profile",
        name: "profile",
        component: () => import("../page/user/profile.vue"),
      },
      {
        path: "register",
        name: "register",
        component: () => import("../page/user/register.vue"),
      },
      {
        path: "login",
        name: "login",
        component: () => import("../page/user/login.vue"),
      },

      // ✅ Bắt mọi đường dẫn lạ về notFound hoặc home
      {
        path: ":pathMatch(.*)*",
        name: "catchAll",
        component: () => import("../page/user/notFound.vue"),
      },
    ],
  },
];

export default users;
