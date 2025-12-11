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
      component: () => import("../page/user/productdetail.vue"),
    },
    {
      path: "Shop",
      name: "Shop",
      component: () => import("../page/user/Shop.vue"),
    },
    // Bổ sung route tìm kiếm
    {
      path: "search",
      name: "search",
      component: () => import("../page/user/Search.vue"),
    },
    {
      path: "OrderList",
      name: "OrderList",
      component: () => import("../page/user/OrderList.vue"),
    },
    {
      path: "PostDetail/:slug/:id",
      name: "PostDetailt",
      component: () => import("../page/user/PostDetail.vue"),
    },
    {
      path: "payment/result",
      name: "PaymentResult",
      component: () => import("../page/user/PaymentResult.vue"),
    },
    {
      path: ":pathMatch(.*)*",
      name: "catchAll",
      component: () => import("../page/user/NotFound.vue"),
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
    path: "/social-callback",
    name: "social-callback",
    component: () => import("../page/user/social-callback.vue"),
  },
  {
    path: "/forgot-password",
    name: "forgot-password",
    component: () => import("../page/user/forgotPassword.vue"),
  },
  {
    path: "/reset-password",
    name: "reset-password",
    component: () => import("../page/user/resetPassword.vue"),
    props: (route) => ({ token: route.query.token, email: route.query.email }),
  },
];

const users = [...userAuthRoutes, userLayoutRoutes];

export default users;