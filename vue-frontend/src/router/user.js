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
        path: "/notFound",
        name: "notFound",
        component: () => import("../page/user/notFound.vue"),
      },
    ],
  },
];

export default users;
