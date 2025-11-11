<template>
  <div class="order-page container mx-auto p-4">
    <h2 class="text-2xl font-bold mb-4">Đơn hàng của tôi</h2>

    <div v-if="orders.length === 0" class="text-center text-gray-500">
      Bạn chưa có đơn hàng nào.
    </div>

    <div
      v-for="order in orders"
      :key="order.id"
      class="border rounded-lg p-4 mb-4 shadow bg-white"
    >
      <!-- Thông tin khách hàng -->
      <div class="mb-2 border-b pb-2">
        <p><span class="font-semibold">Tên:</span> {{ order.customer.name }}</p>
        <p><span class="font-semibold">SĐT:</span> {{ order.customer.phone }}</p>
        <p><span class="font-semibold">Địa chỉ:</span> {{ order.customer.address }}</p>
      </div>

      <!-- Thông tin đơn hàng -->
      <div class="mb-2 border-b pb-2">
        <p><span class="font-semibold">Mã đơn: </span> {{ order.id }}</p>
        <p><span class="font-semibold">Ngày đặt: </span> {{ formatDate(order.date) }}</p>
        <p>
          <span class="font-semibold">Trạng thái: </span>
          <span>
            {{ order.status }}
          </span>
        </p>
      </div>

      <!-- Chi tiết sản phẩm -->
      <div class="mt-2">
        <table class="w-full table-auto">
          <thead>
            <tr class="text-left border-b">
              <th class="pb-2">Sản phẩm</th>
              <th class="pb-2">Số lượng</th>
              <th class="pb-2">Giá</th>
              <th class="pb-2">Tổng</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.id" class="border-b">
              <td>{{ item.name }}</td>
              <td>{{ item.quantity }}</td>
              <td>{{ formatPrice(item.price) }}</td>
              <td>{{ formatPrice(item.price * item.quantity) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Tổng tiền + phí ship -->
        <div class="text-right mt-2 font-semibold">
          <p>Tổng sản phẩm: {{ formatPrice(orderTotal(order)) }}</p>
          <p>Phí vận chuyển: {{ formatPrice(order.shipFee) }}</p>
          <p class="text-lg">
            Tổng thanh toán: {{ formatPrice(orderTotal(order) + order.shipFee) }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";

const orders = ref([
  {
    id: "DH001",
    date: "2025-11-07T10:30:00",
    status: "Đang xử lý",
    shipFee: 30000,
    customer: {
      name: "Nguyễn Văn A",
      phone: "0901234567",
      address: "123 Lê Lợi, Quận 1, TP.HCM",
    },
    items: [
      { id: 1, name: "Chuột Logitech G102", quantity: 2, price: 700000 },
      { id: 2, name: "Bàn phím Akko 3087", quantity: 1, price: 1800000 },
    ],
  },
  {
    id: "DH002",
    date: "2025-11-05T15:20:00",
    status: "Đã giao",
    shipFee: 40000,
    customer: {
      name: "Trần Thị B",
      phone: "0912345678",
      address: "456 Nguyễn Trãi, Quận 5, TP.HCM",
    },
    items: [{ id: 3, name: "Tai nghe HyperX", quantity: 1, price: 1200000 }],
  },
]);

// Định dạng giá
const formatPrice = (value) => {
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(value);
};

// Tính tổng tiền sản phẩm
const orderTotal = (order) => {
  return order.items.reduce((total, item) => total + item.price * item.quantity, 0);
};

// Định dạng ngày giờ
const formatDate = (dateStr) => {
  const d = new Date(dateStr);
  return d.toLocaleDateString("vi-VN") + " " + d.toLocaleTimeString("vi-VN");
};

// Màu trạng thái
const statusClass = (status) => {
  switch (status) {
    case "Đang xử lý":
      return "bg-yellow-500";
    case "Đang giao":
      return "bg-blue-500";
    case "Đã giao":
      return "bg-green-600"; // xanh đậm hơn để chữ trắng dễ đọc
    case "Đã hủy":
      return "bg-red-500";
    default:
      return "bg-gray-500";
  }
};
</script>

<style scoped>
.order-page table th,
.order-page table td {
  padding: 0.5rem;
}
</style>
