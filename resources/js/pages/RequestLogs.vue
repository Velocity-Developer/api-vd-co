<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { requestlogsIndex } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Request Logs',
                href: requestlogsIndex(),
            },
        ],
    },
});
</script>


<template>
    <Head title="Request Logs" />

</template>
