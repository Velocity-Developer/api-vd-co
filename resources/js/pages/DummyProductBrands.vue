<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DummyProductTermManager from '@/components/DummyProductTermManager.vue';
import { dummyProducts, dummyProductBrands } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dummy Products', href: dummyProducts() },
            { title: 'Brand Produk Dummy', href: dummyProductBrands() },
        ],
    },
});
</script>

<template>
    <Head title="Brand Produk Dummy" />

    <DummyProductTermManager
        endpoint="/ajax/dummy-product-brands"
        title="Brand Produk Dummy"
        noun="brand"
        icon="i-lucide-badge-check"
    />
</template>
