const users = [
<<<<<<< HEAD
    {
        path: "/",
        component: () => import("../layout/layoutUser.vue"),
        children: [
            {
                path: "",
                name: "home",
                component: () => import("../page/user/home.vue")
            },
            {
                path: "/about",
                name: "about",
                component: () => import("../page/user/about.vue")
            },
            {
                path: "contact",
                name: "contact",
                component: () => import("../page/user/Contact.vue"),
            },
        ]
    }
]
=======
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
      {
        path: "profile",
        name: "profile",
        component: () => import("../page/user/profile.vue"),
      },
    ],
  },
];
>>>>>>> 27a34306ddc43c12992fcbe9c015f7494926cfdb

export default users;
