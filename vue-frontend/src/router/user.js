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
        path: "/about",
        name: "about",
        component: () => import("../page/user/about.vue"),
      },
      {
        path: "/contact",
        name: "contact",
        component: () => import("../page/user/Contact.vue"),
      },
      {
        path: "/notFound",
        name: "notFound",
        component: () => import("../page/user/notFound.vue"),
      },
      {
        path: "/profile",
        name: "profile",
        component: () => import("../page/user/profile.vue"),
      },
      {
        path: "/register",
        name: "register",
        component: () => import("../page/user/register.vue"),
      },
      {
        path: "/login",
        name: "login",
        component: () => import("../page/user/login.vue"),
      },
      {
        path: "/policy",
        name: "policy",
        component: () => import("../page/user/PolicyPage.vue"),
      },
      {
        path: "/FAQ",
        name: "FAQ",
        component: () => import("../page/user/FAQ.vue"),
      },
      {
        path: "/blog",
        name: "blog",
        component: () => import("../page/user/Tintuc.vue"),
      },
    ],
  },
];

export default users;
