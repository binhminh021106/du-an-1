<script setup>
import userHeader from '../components/user/UserHeader.vue';
import userFooter from '../components/user/UserFooter.vue';
</script>

<template>
    <userHeader />
    <router-view></router-view>
    <userFooter />
</template>

<style scoped>

</style>