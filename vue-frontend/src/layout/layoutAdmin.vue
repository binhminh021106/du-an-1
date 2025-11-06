<script setup>
import AdminHeader from '../components/AdminHeader.vue';
import AdminFooter from '../components/adminFooter.vue';

</script>

<template>
    <AdminHeader />
    <router-link to="/admin/users">Admin User</router-link>
    <router-link to="/admin/products">Admin Product</router-link>
    
    <router-view></router-view>
    <AdminFooter />
</template>

<style scoped></style>