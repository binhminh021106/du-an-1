const users = [
  {
    path: "/",
    component: () => import("../layout/layoutUser.vue"),
    children: [
      {
        path: "",
        name: "home",
        component: () => import("../page/user/Home.vue"),
      },
      {
        path: "about",
        name: "about",
        component: () => import("../page/user/About.vue"),
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
        path: "register",
        name: "register",
        component: () => import("../page/user/Register.vue"),
      },
      {
        path: "login",
        name: "login",
        component: () => import("../page/user/Login.vue"),
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
        path: ":pathMatch(.*)*",
        name: "catchAll",
        component: () => import("../page/user/NotFound.vue"),
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
       path: '/products/:id',
       name: "ProductDetail",
<<<<<<< Updated upstream
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
=======
       component: () => import("../page/user/productdetail.vue"),
      },
      {
       path: "trangcuahang",
       name: "trangcuahang",
       component: () => import("../page/user/trangcuahang.vue"),
>>>>>>> Stashed changes
      },
    ],
  },
];

export default users;
