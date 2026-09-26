<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DummyProductTermManager from '@/components/DummyProductTermManager.vue';
import { dummyProducts, dummyProductCategories } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dummy Products', href: dummyProducts() },
            { title: 'Kategori Produk Dummy', href: dummyProductCategories() },
        ],
    },
});
</script>

<template>
    <Head title="Kategori Produk Dummy" />

    <DummyProductTermManager
        endpoint="/ajax/dummy-product-categories"
        title="Kategori Produk Dummy"
        noun="kategori"
        icon="i-lucide-folder"
    />
</template>
