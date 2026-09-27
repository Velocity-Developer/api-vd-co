<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, TableColumn } from '@nuxt/ui';
import { useDebounceFn } from '@vueuse/core';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PublicImageField from '@/components/PublicImageField.vue';
import { dummyProducts, dummySellers as dummySellersRoute } from '@/routes';

type Seller = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image: string | null;
    image_url: string | null;
    banner: string | null;
    banner_url: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    address: string | null;
    rating: string;
    is_verified: boolean;
    products_count?: number;
    created_at: string;
    updated_at: string;
};

type PreviewProduct = {
    id: number;
    title: string;
    price: string;
    price_discount: string | null;
    image_url: string | null;
};

type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};

type SellerFormState = {
    name: string;
    slug: string;
    email: string;
    phone: string;
    city: string;
    /** UInput type="number" emits numbers; an emptied field is ''. */
    rating: number | string | null;
    isVerified: boolean;
    address: string;
    description: string;
    imageFile: File | null;
    removeImage: boolean;
    bannerFile: File | null;
    removeBanner: boolean;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dummy Products', href: dummyProducts() },
            { title: 'Seller', href: dummySellersRoute() },
        ],
    },
});

const maxImageSizeMb = 5;

const columns: TableColumn<Seller>[] = [
    { accessorKey: 'name', header: 'Seller' },
    { accessorKey: 'email', header: 'Kontak' },
    { accessorKey: 'rating', header: 'Rating' },
    { accessorKey: 'is_verified', header: 'Status' },
    { accessorKey: 'products_count', header: 'Produk' },
    { id: 'actions' },
];

const verifiedOptions = [
    { label: 'Semua status', value: 'all' },
    { label: 'Terverifikasi', value: '1' },
    { label: 'Belum verifikasi', value: '0' },
];

const emptyForm = (): SellerFormState => ({
    name: '',
    slug: '',
    email: '',
    phone: '',
    city: '',
    rating: '0',
    isVerified: false,
    address: '',
    description: '',
    imageFile: null,
    removeImage: false,
    bannerFile: null,
    removeBanner: false,
});

const state = reactive<SellerFormState>(emptyForm());

const sellerData = ref<Seller[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const selectedVerified = ref('all');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingSellerId = ref<number | null>(null);
const deletingSeller = ref<Seller | null>(null);
const previewSeller = ref<Seller | null>(null);
const isPreviewOpen = ref(false);
const previewProducts = ref<PreviewProduct[]>([]);
const previewProductsTotal = ref(0);
const isLoadingPreviewProducts = ref(false);
const previewProductsError = ref<string | null>(null);

const previewProductLimit = 8;

const currency = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 2,
    minimumFractionDigits: 0,
});

const formatPrice = (value: string): string => currency.format(Number(value));
const currentImageUrl = ref<string | null>(null);
const currentBannerUrl = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingSellerId.value !== null);

const hasActiveFilter = computed(
    () => search.value.trim() !== '' || selectedVerified.value !== 'all',
);

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 seller';
    }

    return `${meta.value.from}-${meta.value.to} dari ${meta.value.total} seller`;
});

const deleteModalDescription = computed(() => {
    const seller = deletingSeller.value;

    if (!seller) {
        return 'Seller ini akan dihapus permanen.';
    }

    const usage = seller.products_count
        ? ` ${seller.products_count} produk tetap ada, hanya seller-nya dikosongkan.`
        : '';

    return `Seller "${seller.name}" akan dihapus permanen.${usage}`;
});

const fetchSellers = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<{
            data: Seller[];
            meta: PaginationMeta;
        }>('/ajax/dummy-sellers', {
            params: {
                page,
                search: search.value.trim() || undefined,
                verified:
                    selectedVerified.value === 'all'
                        ? undefined
                        : selectedVerified.value,
            },
        });

        sellerData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data seller gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const refetchFromFirstPage = (): void => {
    if (currentPage.value === 1) {
        void fetchSellers(1);

        return;
    }

    currentPage.value = 1;
};

const debouncedSearch = useDebounceFn(refetchFromFirstPage, 400);

const resetFilters = (): void => {
    search.value = '';
    selectedVerified.value = 'all';
};

const slugify = (value: string): string => {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

const numberOrNull = (
    value: number | string | null | undefined,
): number | null => {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    const number = Number(value);

    return Number.isNaN(number) ? null : number;
};

const validate = (formState: Partial<SellerFormState>): FormError[] => {
    const errors: FormError[] = [];
    const rating = numberOrNull(formState.rating);

    if (!formState.name?.trim()) {
        errors.push({ name: 'name', message: 'Nama wajib diisi.' });
    }

    if (!formState.slug?.trim()) {
        errors.push({ name: 'slug', message: 'Slug wajib diisi.' });
    }

    if (rating !== null && (rating < 0 || rating > 5)) {
        errors.push({ name: 'rating', message: 'Rating 0 sampai 5.' });
    }

    if (
        formState.imageFile &&
        formState.imageFile.size > maxImageSizeMb * 1024 * 1024
    ) {
        errors.push({
            name: 'image_file',
            message: `Ukuran gambar maksimal ${maxImageSizeMb} MB.`,
        });
    }

    if (
        formState.bannerFile &&
        formState.bannerFile.size > maxImageSizeMb * 1024 * 1024
    ) {
        errors.push({
            name: 'banner_file',
            message: `Ukuran banner maksimal ${maxImageSizeMb} MB.`,
        });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const resetForm = (): void => {
    Object.assign(state, emptyForm());
    editingSellerId.value = null;
    currentImageUrl.value = null;
    currentBannerUrl.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (seller: Seller): void => {
    resetForm();
    Object.assign(state, {
        name: seller.name,
        slug: seller.slug,
        email: seller.email ?? '',
        phone: seller.phone ?? '',
        city: seller.city ?? '',
        rating: String(Number(seller.rating)),
        isVerified: seller.is_verified,
        address: seller.address ?? '',
        description: seller.description ?? '',
    });
    currentImageUrl.value = seller.image_url;
    currentBannerUrl.value = seller.banner_url;
    editingSellerId.value = seller.id;
    isModalOpen.value = true;
};

const closeModal = (): void => {
    if (isSaving.value) {
        return;
    }

    isModalOpen.value = false;
    resetForm();
};

const buildFormData = (): FormData => {
    const formData = new FormData();
    const fields: Record<string, string> = {
        name: state.name.trim(),
        slug: state.slug.trim(),
        email: state.email.trim(),
        phone: state.phone.trim(),
        city: state.city.trim(),
        rating: String(numberOrNull(state.rating) ?? 0),
        is_verified: state.isVerified ? '1' : '0',
        address: state.address.trim(),
        description: state.description.trim(),
    };

    if (isEditing.value) {
        formData.append('_method', 'PATCH');
    }

    // Empty strings arrive as null on the server, which clears the column.
    Object.entries(fields).forEach(([name, value]) =>
        formData.append(name, value),
    );

    if (state.imageFile) {
        formData.append('image_file', state.imageFile);
    } else if (state.removeImage) {
        formData.append('remove_image', '1');
    }

    if (state.bannerFile) {
        formData.append('banner_file', state.bannerFile);
    } else if (state.removeBanner) {
        formData.append('remove_banner', '1');
    }

    return formData;
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Seller gagal disimpan.';

        return;
    }

    const response = error.response.data as ValidationResponse;

    serverErrors.value = Object.fromEntries(
        Object.entries(response.errors ?? {}).map(([name, messages]) => [
            name,
            messages[0] ?? 'Isian tidak valid.',
        ]),
    );
};

const submitSeller = async (): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        await axios.post(
            editingSellerId.value
                ? `/ajax/dummy-sellers/${editingSellerId.value}`
                : '/ajax/dummy-sellers',
            buildFormData(),
        );

        toast.success(
            isEditing.value ? 'Seller disimpan.' : 'Seller ditambahkan.',
        );
        isModalOpen.value = false;
        resetForm();
        await fetchSellers(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

const fetchPreviewProducts = async (seller: Seller): Promise<void> => {
    isLoadingPreviewProducts.value = true;
    previewProductsError.value = null;
    previewProducts.value = [];
    previewProductsTotal.value = 0;

    try {
        const response = await axios.get<{
            data: PreviewProduct[];
            meta: PaginationMeta;
        }>('/ajax/dummy-products', { params: { seller: seller.id } });

        // Ignore the answer when another seller was opened meanwhile.
        if (previewSeller.value?.id !== seller.id) {
            return;
        }

        previewProducts.value = response.data.data.slice(
            0,
            previewProductLimit,
        );
        previewProductsTotal.value = response.data.meta.total;
    } catch {
        previewProductsError.value = 'Produk seller gagal dimuat.';
    } finally {
        isLoadingPreviewProducts.value = false;
    }
};

const openPreview = (seller: Seller): void => {
    previewSeller.value = seller;
    isPreviewOpen.value = true;
    void fetchPreviewProducts(seller);
};

const editFromPreview = (): void => {
    if (!previewSeller.value) {
        return;
    }

    const seller = previewSeller.value;

    isPreviewOpen.value = false;
    openEditModal(seller);
};

const openDeleteModal = (seller: Seller): void => {
    deletingSeller.value = seller;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingSeller.value = null;
    deleteMessage.value = null;
};

const deleteSeller = async (): Promise<void> => {
    if (!deletingSeller.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`/ajax/dummy-sellers/${deletingSeller.value.id}`);

        const targetPage =
            sellerData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        toast.success('Seller dihapus.');
        isDeleteModalOpen.value = false;
        deletingSeller.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchSellers(targetPage);
        }
    } catch {
        deleteMessage.value = 'Seller gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchSellers(page);
    }
});

watch(search, () => {
    void debouncedSearch();
});

watch(selectedVerified, refetchFromFirstPage);

watch(
    () => state.name,
    (nameValue, previousName) => {
        if (
            !isEditing.value &&
            (state.slug === '' || state.slug === slugify(previousName ?? ''))
        ) {
            state.slug = slugify(nameValue);
        }
    },
);

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) {
        resetForm();
    }
});

watch(isDeleteModalOpen, (open) => {
    if (!open && !isDeleting.value) {
        deletingSeller.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchSellers();
});
</script>

<template>
    <Head title="Seller" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">Seller</h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Cari nama, kota, email..."
                    class="w-full sm:w-64"
                />

                <USelect
                    v-model="selectedVerified"
                    :items="verifiedOptions"
                    class="w-44"
                    aria-label="Filter status verifikasi"
                />

                <UButton
                    icon="i-lucide-plus"
                    label="Tambah"
                    :disabled="isLoading"
                    @click="openCreateModal"
                />

                <UButton
                    icon="i-lucide-refresh-cw"
                    color="neutral"
                    variant="outline"
                    :loading="isLoading"
                    aria-label="Muat ulang seller"
                    @click="fetchSellers(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat seller"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchSellers(currentPage),
                },
            ]"
        />

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="sellerData"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #name-cell="{ row }">
                    <div class="flex min-w-48 items-center gap-3">
                        <UAvatar
                            :src="row.original.image_url ?? undefined"
                            :alt="row.original.name"
                            icon="i-lucide-store"
                            size="lg"
                        />

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.name }}
                            </p>
                            <p class="truncate text-xs text-muted">
                                {{ row.original.city || '-' }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #email-cell="{ row }">
                    <div class="min-w-40 text-sm">
                        <p class="truncate text-highlighted">
                            {{ row.original.email || '-' }}
                        </p>
                        <p class="truncate text-muted">
                            {{ row.original.phone || '-' }}
                        </p>
                    </div>
                </template>

                <template #rating-cell="{ row }">
                    <span
                        class="inline-flex items-center gap-1 whitespace-nowrap"
                    >
                        <UIcon
                            name="i-lucide-star"
                            class="size-4 text-warning"
                        />
                        {{ Number(row.original.rating).toFixed(1) }}
                    </span>
                </template>

                <template #is_verified-cell="{ row }">
                    <UBadge
                        :color="
                            row.original.is_verified ? 'success' : 'neutral'
                        "
                        variant="subtle"
                        :icon="
                            row.original.is_verified
                                ? 'i-lucide-badge-check'
                                : undefined
                        "
                        :label="
                            row.original.is_verified
                                ? 'Terverifikasi'
                                : 'Belum verifikasi'
                        "
                    />
                </template>

                <template #products_count-cell="{ row }">
                    <UBadge
                        color="primary"
                        variant="subtle"
                        :label="String(row.original.products_count ?? 0)"
                    />
                </template>

                <template #actions-cell="{ row }">
                    <div class="flex justify-end gap-1">
                        <UButton
                            icon="i-lucide-eye"
                            color="neutral"
                            variant="ghost"
                            aria-label="Preview seller"
                            :disabled="isLoading || isDeleting"
                            @click="openPreview(row.original)"
                        />

                        <UButton
                            icon="i-lucide-pencil"
                            color="neutral"
                            variant="ghost"
                            aria-label="Edit seller"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Hapus seller"
                            :disabled="isLoading || isDeleting"
                            @click="openDeleteModal(row.original)"
                        />
                    </div>
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon
                            name="i-lucide-store"
                            class="size-8 text-muted"
                        />
                        <p class="font-medium text-highlighted">
                            {{
                                hasActiveFilter
                                    ? 'Tidak ada seller yang cocok'
                                    : 'Belum ada seller'
                            }}
                        </p>
                        <UButton
                            v-if="hasActiveFilter"
                            label="Reset filter"
                            color="neutral"
                            variant="outline"
                            size="sm"
                            @click="resetFilters"
                        />
                    </div>
                </template>
            </UTable>

            <div
                v-if="meta && meta.total > meta.per_page"
                class="flex flex-col gap-3 border-t border-default px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>

                <UPagination
                    v-model:page="currentPage"
                    :total="meta.total"
                    :items-per-page="meta.per_page"
                    :disabled="isLoading"
                />
            </div>
        </div>

        <UModal
            v-model:open="isModalOpen"
            :title="isEditing ? 'Edit Seller' : 'Tambah Seller'"
            :ui="{ content: 'sm:max-w-3xl', footer: 'justify-end' }"
        >
            <template #body>
                <UAlert
                    v-if="formMessage"
                    color="error"
                    variant="soft"
                    icon="i-lucide-circle-alert"
                    title="Ada masalah"
                    :description="formMessage"
                    class="mb-4"
                />

                <UForm
                    id="dummy-seller-form"
                    :state="state"
                    :validate="validate"
                    class="grid gap-4 sm:grid-cols-2"
                    @submit="submitSeller"
                >
                    <UFormField
                        name="name"
                        label="Nama toko"
                        required
                        :error="fieldError('name')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.name"
                            placeholder="Toko Sinar Jaya"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="slug"
                        label="Slug"
                        required
                        :error="fieldError('slug')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.slug"
                            placeholder="toko-sinar-jaya"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="email"
                        label="Email"
                        hint="Opsional"
                        :error="fieldError('email')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.email"
                            type="email"
                            placeholder="halo@toko.com"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="phone"
                        label="Telepon"
                        hint="Opsional"
                        :error="fieldError('phone')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.phone"
                            type="tel"
                            placeholder="0812-3456-7890"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="city"
                        label="Kota"
                        hint="Opsional"
                        :error="fieldError('city')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.city"
                            placeholder="Bandung"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="rating"
                        label="Rating"
                        help="0 sampai 5."
                        :error="fieldError('rating')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.rating"
                            type="number"
                            min="0"
                            max="5"
                            step="0.01"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="address"
                        label="Alamat"
                        hint="Opsional"
                        :error="fieldError('address')"
                        class="content-start sm:col-span-2"
                    >
                        <UTextarea
                            v-model="state.address"
                            :rows="2"
                            autoresize
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="image_file"
                        label="Logo"
                        hint="Opsional"
                        :help="`JPG, PNG, WEBP, GIF, atau AVIF, maksimal ${maxImageSizeMb} MB.`"
                        :error="fieldError('image_file')"
                        class="content-start"
                    >
                        <PublicImageField
                            v-model:file="state.imageFile"
                            v-model:remove="state.removeImage"
                            :current-url="currentImageUrl"
                            noun="seller"
                            :disabled="isSaving"
                        />
                    </UFormField>

                    <UFormField
                        name="is_verified"
                        label="Status"
                        :error="fieldError('is_verified')"
                        class="content-start"
                    >
                        <USwitch
                            v-model="state.isVerified"
                            label="Seller terverifikasi"
                            description="Tampilkan lencana terverifikasi pada toko."
                            :disabled="isSaving"
                        />
                    </UFormField>

                    <UFormField
                        name="banner_file"
                        label="Banner"
                        hint="Opsional"
                        :help="`Gambar lebar untuk sampul toko, disarankan 1200 × 400 px. Maksimal ${maxImageSizeMb} MB.`"
                        :error="fieldError('banner_file')"
                        class="content-start sm:col-span-2"
                    >
                        <PublicImageField
                            v-model:file="state.bannerFile"
                            v-model:remove="state.removeBanner"
                            :current-url="currentBannerUrl"
                            noun="banner seller"
                            wide
                            :disabled="isSaving"
                        />
                    </UFormField>

                    <UFormField
                        name="description"
                        label="Deskripsi"
                        hint="Opsional"
                        :error="fieldError('description')"
                        class="content-start sm:col-span-2"
                    >
                        <UTextarea
                            v-model="state.description"
                            :rows="3"
                            autoresize
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>
                </UForm>
            </template>

            <template #footer>
                <UButton
                    label="Batal"
                    color="neutral"
                    variant="outline"
                    :disabled="isSaving"
                    @click="closeModal"
                />

                <UButton
                    type="submit"
                    form="dummy-seller-form"
                    icon="i-lucide-save"
                    :label="isEditing ? 'Simpan' : 'Tambah'"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Hapus Seller"
            :description="deleteModalDescription"
            :ui="{ footer: 'justify-end' }"
        >
            <template v-if="deleteMessage" #body>
                <UAlert
                    color="error"
                    variant="soft"
                    icon="i-lucide-circle-alert"
                    title="Ada masalah"
                    :description="deleteMessage"
                />
            </template>

            <template #footer>
                <UButton
                    label="Batal"
                    color="neutral"
                    variant="outline"
                    :disabled="isDeleting"
                    @click="closeDeleteModal"
                />

                <UButton
                    label="Hapus"
                    icon="i-lucide-trash"
                    color="error"
                    :loading="isDeleting"
                    @click="deleteSeller"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isPreviewOpen"
            :title="
                previewSeller
                    ? `Preview ${previewSeller.name}`
                    : 'Preview seller'
            "
            description="Tampilan halaman toko seller."
            :ui="{ content: 'sm:max-w-3xl', footer: 'justify-end' }"
        >
            <template #body>
                <div v-if="previewSeller" class="space-y-6">
                    <div
                        class="overflow-hidden rounded-lg border border-default bg-default"
                    >
                        <div class="aspect-3/1 w-full bg-elevated">
                            <img
                                v-if="previewSeller.banner_url"
                                :src="previewSeller.banner_url"
                                :alt="`Banner ${previewSeller.name}`"
                                class="size-full object-cover"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center text-sm text-dimmed"
                            >
                                Belum ada banner
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-4 px-5 pb-5 sm:flex-row sm:items-end"
                        >
                            <UAvatar
                                :src="previewSeller.image_url ?? undefined"
                                :alt="previewSeller.name"
                                icon="i-lucide-store"
                                class="-mt-10 size-20 shrink-0 text-3xl ring-4 ring-(--ui-bg)"
                            />

                            <div class="min-w-0 flex-1">
                                <h2
                                    class="flex items-center gap-2 text-xl font-semibold text-highlighted"
                                >
                                    <span class="truncate">
                                        {{ previewSeller.name }}
                                    </span>
                                    <UIcon
                                        v-if="previewSeller.is_verified"
                                        name="i-lucide-badge-check"
                                        class="size-5 shrink-0 text-success"
                                        aria-label="Terverifikasi"
                                    />
                                </h2>
                                <div
                                    class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted"
                                >
                                    <span
                                        v-if="previewSeller.city"
                                        class="inline-flex items-center gap-1"
                                    >
                                        <UIcon
                                            name="i-lucide-map-pin"
                                            class="size-4"
                                        />
                                        {{ previewSeller.city }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <UIcon
                                            name="i-lucide-star"
                                            class="size-4 text-warning"
                                        />
                                        {{
                                            Number(
                                                previewSeller.rating,
                                            ).toFixed(1)
                                        }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <UIcon
                                            name="i-lucide-package"
                                            class="size-4"
                                        />
                                        {{ previewSeller.products_count ?? 0 }}
                                        produk
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 text-sm">
                            <p class="font-medium text-highlighted">Kontak</p>
                            <p class="flex items-center gap-2 text-muted">
                                <UIcon name="i-lucide-mail" class="size-4" />
                                <a
                                    v-if="previewSeller.email"
                                    :href="`mailto:${previewSeller.email}`"
                                    class="text-highlighted hover:underline"
                                >
                                    {{ previewSeller.email }}
                                </a>
                                <span v-else>-</span>
                            </p>
                            <p class="flex items-center gap-2 text-muted">
                                <UIcon name="i-lucide-phone" class="size-4" />
                                <a
                                    v-if="previewSeller.phone"
                                    :href="`tel:${previewSeller.phone.replace(/[^0-9+]/g, '')}`"
                                    class="text-highlighted hover:underline"
                                >
                                    {{ previewSeller.phone }}
                                </a>
                                <span v-else>-</span>
                            </p>
                            <p class="flex items-start gap-2 text-muted">
                                <UIcon
                                    name="i-lucide-map"
                                    class="mt-0.5 size-4 shrink-0"
                                />
                                <span class="whitespace-pre-line">
                                    {{ previewSeller.address || '-' }}
                                </span>
                            </p>
                        </div>

                        <div class="space-y-2 text-sm">
                            <p class="font-medium text-highlighted">Tentang</p>
                            <p class="whitespace-pre-line text-muted">
                                {{
                                    previewSeller.description ||
                                    'Belum ada deskripsi.'
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-medium text-highlighted">
                            Produk
                            <span
                                v-if="
                                    previewProductsTotal >
                                    previewProducts.length
                                "
                                class="font-normal text-muted"
                            >
                                ({{ previewProducts.length }} dari
                                {{ previewProductsTotal }})
                            </span>
                        </p>

                        <div
                            v-if="isLoadingPreviewProducts"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                        >
                            <div
                                v-for="index in 4"
                                :key="index"
                                class="space-y-2"
                            >
                                <USkeleton
                                    class="aspect-square w-full rounded-md"
                                />
                                <USkeleton class="h-4 w-3/4" />
                            </div>
                        </div>

                        <UAlert
                            v-else-if="previewProductsError"
                            color="error"
                            variant="soft"
                            icon="i-lucide-circle-alert"
                            :description="previewProductsError"
                        />

                        <p
                            v-else-if="previewProducts.length === 0"
                            class="text-sm text-dimmed"
                        >
                            Seller ini belum punya produk.
                        </p>

                        <div
                            v-else
                            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                        >
                            <div
                                v-for="product in previewProducts"
                                :key="product.id"
                                class="min-w-0 space-y-1"
                            >
                                <div
                                    class="flex aspect-square items-center justify-center overflow-hidden rounded-md border border-default bg-elevated"
                                >
                                    <img
                                        v-if="product.image_url"
                                        :src="product.image_url"
                                        :alt="product.title"
                                        loading="lazy"
                                        class="size-full object-cover"
                                    />
                                    <UIcon
                                        v-else
                                        name="i-lucide-package"
                                        class="size-6 text-muted"
                                    />
                                </div>
                                <p
                                    class="truncate text-sm text-highlighted"
                                    :title="product.title"
                                >
                                    {{ product.title }}
                                </p>
                                <p class="text-sm font-medium text-highlighted">
                                    {{
                                        formatPrice(
                                            product.price_discount ??
                                                product.price,
                                        )
                                    }}
                                    <span
                                        v-if="product.price_discount !== null"
                                        class="text-xs font-normal text-muted line-through"
                                    >
                                        {{ formatPrice(product.price) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <template #footer>
                <UButton
                    label="Tutup"
                    color="neutral"
                    variant="outline"
                    @click="isPreviewOpen = false"
                />

                <UButton
                    label="Edit"
                    icon="i-lucide-pencil"
                    @click="editFromPreview"
                />
            </template>
        </UModal>
    </div>
</template>
