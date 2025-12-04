const userLayoutRoutes = {
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
      component: () => import("../page/user/NotFound.vue"),
    },
    {
      path: "profile",
      name: "profile",
      component: () => import("../page/user/Profile.vue"),
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
    {
      path: "cart",
      name: "cart",
      component: () => import("../page/user/Cart.vue"),
    },
    {
      path: "checkout",
      name: "checkout",
      component: () => import("../page/user/Checkout.vue"),
    },
    {
      path: "wishlist",
      name: "wishlist",
      component: () => import("../page/user/wishlist.vue"),
    },
    {
      path: "/products/:id",
      name: "ProductDetail",
      component: () => import("../page/user/ProductDetail.vue"),
    },
    {
      path: "Shop",
      name: "Shop",
      component: () => import("../page/user/Shop.vue"),
    },
    {
      path: "OrderList",
      name: "OrderList",
      component: () => import("../page/user/OrderList.vue"),
    },
    {
      path: ":pathMatch(.*)*",
      name: "catchAll",
      component: () => import("../page/user/NotFound.vue"),
    },
    {
      path: "PostDetail/:id",
      name: "PostDetailt",
      component: () => import("../page/user/PostDetail.vue"),
    },
  ],
};

const userAuthRoutes = [
  {
    path: "/register",
    name: "register",
    component: () => import("../page/user/Register.vue"),
  },
  {
    path: "/login",
    name: "login",
    component: () => import("../page/user/login.vue"),
  },
  {
    path: "/google-callback",
    name: "google-callback",
    component: () => import("../page/user/GoogleCallback.vue"),
  },
];

const users = [...userAuthRoutes, userLayoutRoutes];

export default users;
