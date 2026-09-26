<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, TableColumn } from '@nuxt/ui';
import { useDebounceFn } from '@vueuse/core';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { dummyProducts as dummyProductsRoute } from '@/routes';

type Term = {
    id: number;
    name: string;
    slug: string;
};

type DummyProduct = {
    id: number;
    title: string;
    description: string | null;
    price: string;
    price_discount: string | null;
    rating: string;
    stock: number;
    weight: string | null;
    sku: string;
    image: string | null;
    image_url: string | null;
    dummy_product_brand_id: number | null;
    brand?: Term | null;
    categories?: Term[];
    created_at: string;
    updated_at: string;
};

type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};

type NumericInput = number | string | null;

type ProductFormState = {
    title: string;
    sku: string;
    brandId: number;
    categoryIds: number[];
    /** UInput type="number" emits numbers; an emptied field is ''. */
    price: NumericInput;
    priceDiscount: NumericInput;
    rating: NumericInput;
    stock: NumericInput;
    weight: NumericInput;
    imageFile: File | null;
    removeImage: boolean;
    description: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dummy Products',
                href: dummyProductsRoute(),
            },
        ],
    },
});

/** USelect cannot hold null, so 0 stands for "no brand". */
const noBrand = 0;

const columns: TableColumn<DummyProduct>[] = [
    { accessorKey: 'title', header: 'Produk' },
    { accessorKey: 'brand', header: 'Brand' },
    { accessorKey: 'categories', header: 'Kategori' },
    { accessorKey: 'price', header: 'Harga' },
    { accessorKey: 'stock', header: 'Stok' },
    { accessorKey: 'rating', header: 'Rating' },
    { id: 'actions' },
];

const emptyForm = (): ProductFormState => ({
    title: '',
    sku: '',
    brandId: noBrand,
    categoryIds: [],
    price: '',
    priceDiscount: '',
    rating: '0',
    stock: '0',
    weight: '',
    imageFile: null,
    removeImage: false,
    description: '',
});

const state = reactive<ProductFormState>(emptyForm());

const productData = ref<DummyProduct[]>([]);
const brands = ref<Term[]>([]);
const categories = ref<Term[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const selectedBrand = ref('all');
const selectedCategory = ref('all');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingProductId = ref<number | null>(null);
const deletingProduct = ref<DummyProduct | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});
/** The saved picture of the product being edited, shown until it is replaced or removed. */
const currentImageUrl = ref<string | null>(null);
const isReplacingImage = ref(false);

const maxImageSizeMb = 5;
const imageAccept = '.jpg,.jpeg,.png,.webp,.gif,.avif';

const isEditing = computed(() => editingProductId.value !== null);

const brandFilterOptions = computed(() => [
    { label: 'Semua brand', value: 'all' },
    { label: 'Tanpa brand', value: 'none' },
    ...brands.value.map((brand) => ({
        label: brand.name,
        value: String(brand.id),
    })),
]);

const categoryFilterOptions = computed(() => [
    { label: 'Semua kategori', value: 'all' },
    ...categories.value.map((category) => ({
        label: category.name,
        value: String(category.id),
    })),
]);

const brandOptions = computed(() => [
    { label: 'Tanpa brand', value: noBrand },
    ...brands.value.map((brand) => ({ label: brand.name, value: brand.id })),
]);

const categoryOptions = computed(() =>
    categories.value.map((category) => ({
        label: category.name,
        value: category.id,
    })),
);

const hasActiveFilter = computed(
    () =>
        search.value.trim() !== '' ||
        selectedBrand.value !== 'all' ||
        selectedCategory.value !== 'all',
);

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 produk';
    }

    return `${meta.value.from}-${meta.value.to} dari ${meta.value.total} produk`;
});

const showsCurrentImage = computed(
    () =>
        currentImageUrl.value !== null &&
        !state.removeImage &&
        !isReplacingImage.value,
);

const currency = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 2,
    minimumFractionDigits: 0,
});

const formatPrice = (value: string | null): string =>
    value === null ? '-' : currency.format(Number(value));

const discountPercent = (product: DummyProduct): number | null => {
    const price = Number(product.price);
    const discount = Number(product.price_discount);

    if (product.price_discount === null || price <= 0 || discount >= price) {
        return null;
    }

    return Math.round(((price - discount) / price) * 100);
};

const fetchProducts = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<{
            data: DummyProduct[];
            meta: PaginationMeta;
        }>('/ajax/dummy-products', {
            params: {
                page,
                search: search.value.trim() || undefined,
                brand:
                    selectedBrand.value === 'all'
                        ? undefined
                        : selectedBrand.value,
                category:
                    selectedCategory.value === 'all'
                        ? undefined
                        : selectedCategory.value,
            },
        });

        productData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data produk gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const fetchOptions = async (): Promise<void> => {
    try {
        const [brandResponse, categoryResponse] = await Promise.all([
            axios.get<{ data: Term[] }>('/ajax/dummy-product-brands', {
                params: { all: 1 },
            }),
            axios.get<{ data: Term[] }>('/ajax/dummy-product-categories', {
                params: { all: 1 },
            }),
        ]);

        brands.value = brandResponse.data.data;
        categories.value = categoryResponse.data.data;
    } catch {
        toast.error('Daftar brand dan kategori gagal dimuat.');
    }
};

const refetchFromFirstPage = (): void => {
    if (currentPage.value === 1) {
        void fetchProducts(1);

        return;
    }

    currentPage.value = 1;
};

const debouncedSearch = useDebounceFn(refetchFromFirstPage, 400);

const resetFilters = (): void => {
    search.value = '';
    selectedBrand.value = 'all';
    selectedCategory.value = 'all';
};

const numberOrNull = (value: NumericInput | undefined): number | null => {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }

    const number = Number(value);

    return Number.isNaN(number) ? null : number;
};

const validate = (formState: Partial<ProductFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.title?.trim()) {
        errors.push({ name: 'title', message: 'Judul wajib diisi.' });
    }

    if (!formState.sku?.trim()) {
        errors.push({ name: 'sku', message: 'SKU wajib diisi.' });
    }

    const price = numberOrNull(formState.price);
    const priceDiscount = numberOrNull(formState.priceDiscount);

    if (price === null) {
        errors.push({ name: 'price', message: 'Harga wajib diisi.' });
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

    if (price !== null && priceDiscount !== null && priceDiscount > price) {
        errors.push({
            name: 'price_discount',
            message: 'Harga diskon tidak boleh lebih besar dari harga.',
        });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const resetForm = (): void => {
    Object.assign(state, emptyForm());
    editingProductId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
    currentImageUrl.value = null;
    isReplacingImage.value = false;
};

const keepCurrentImage = (): void => {
    state.imageFile = null;
    state.removeImage = false;
    isReplacingImage.value = false;
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (product: DummyProduct): void => {
    resetForm();
    Object.assign(state, {
        title: product.title,
        sku: product.sku,
        brandId: product.dummy_product_brand_id ?? noBrand,
        categoryIds: (product.categories ?? []).map((category) => category.id),
        price: String(Number(product.price)),
        priceDiscount:
            product.price_discount === null
                ? ''
                : String(Number(product.price_discount)),
        rating: String(Number(product.rating)),
        stock: String(product.stock),
        weight: product.weight === null ? '' : String(Number(product.weight)),
        description: product.description ?? '',
    });
    currentImageUrl.value = product.image_url;
    editingProductId.value = product.id;
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
    const fields: Record<string, string | number | null> = {
        title: state.title.trim(),
        sku: state.sku.trim(),
        dummy_product_brand_id:
            state.brandId === noBrand ? null : state.brandId,
        price: numberOrNull(state.price),
        price_discount: numberOrNull(state.priceDiscount),
        rating: numberOrNull(state.rating) ?? 0,
        stock: numberOrNull(state.stock) ?? 0,
        weight: numberOrNull(state.weight),
        description: state.description.trim() || null,
    };

    if (isEditing.value) {
        formData.append('_method', 'PATCH');
    }

    // Empty strings arrive as null on the server, which clears the column.
    Object.entries(fields).forEach(([name, value]) =>
        formData.append(name, value === null ? '' : String(value)),
    );

    if (state.categoryIds.length === 0) {
        formData.append('category_ids', '');
    }

    state.categoryIds.forEach((id) =>
        formData.append('category_ids[]', String(id)),
    );

    if (state.imageFile) {
        formData.append('image_file', state.imageFile);
    } else if (state.removeImage) {
        formData.append('remove_image', '1');
    }

    return formData;
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Produk gagal disimpan.';

        return;
    }

    const response = error.response.data as ValidationResponse;

    serverErrors.value = Object.fromEntries(
        Object.entries(response.errors ?? {}).map(([name, messages]) => [
            name.startsWith('category_ids') ? 'category_ids' : name,
            messages[0] ?? 'Isian tidak valid.',
        ]),
    );
};

const submitProduct = async (): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        await axios.post(
            editingProductId.value
                ? `/ajax/dummy-products/${editingProductId.value}`
                : '/ajax/dummy-products',
            buildFormData(),
        );

        toast.success(
            isEditing.value ? 'Produk disimpan.' : 'Produk ditambahkan.',
        );
        isModalOpen.value = false;
        resetForm();
        await fetchProducts(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

const openDeleteModal = (product: DummyProduct): void => {
    deletingProduct.value = product;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingProduct.value = null;
    deleteMessage.value = null;
};

const deleteProduct = async (): Promise<void> => {
    if (!deletingProduct.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`/ajax/dummy-products/${deletingProduct.value.id}`);

        const targetPage =
            productData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        toast.success('Produk dihapus.');
        isDeleteModalOpen.value = false;
        deletingProduct.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchProducts(targetPage);
        }
    } catch {
        deleteMessage.value = 'Produk gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchProducts(page);
    }
});

watch(search, () => {
    void debouncedSearch();
});

watch([selectedBrand, selectedCategory], refetchFromFirstPage);

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) {
        resetForm();
    }
});

watch(isDeleteModalOpen, (open) => {
    if (!open && !isDeleting.value) {
        deletingProduct.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchProducts();
    void fetchOptions();
});
</script>

<template>
    <Head title="Dummy Products" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Dummy Products
                </h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Cari judul atau SKU..."
                    class="w-full sm:w-60"
                />

                <USelect
                    v-model="selectedBrand"
                    :items="brandFilterOptions"
                    class="w-40"
                    aria-label="Filter brand"
                />

                <USelect
                    v-model="selectedCategory"
                    :items="categoryFilterOptions"
                    class="w-44"
                    aria-label="Filter kategori"
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
                    aria-label="Muat ulang produk"
                    @click="fetchProducts(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat produk"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchProducts(currentPage),
                },
            ]"
        />

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="productData"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #title-cell="{ row }">
                    <div class="flex min-w-56 items-center gap-3">
                        <div
                            class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-default bg-elevated"
                        >
                            <img
                                v-if="row.original.image_url"
                                :src="row.original.image_url"
                                :alt="row.original.title"
                                loading="lazy"
                                class="size-full object-cover"
                            />
                            <UIcon
                                v-else
                                name="i-lucide-package"
                                class="size-5 text-muted"
                            />
                        </div>

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.title }}
                            </p>
                            <p class="truncate text-xs text-muted">
                                {{ row.original.sku }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #brand-cell="{ row }">
                    <span v-if="row.original.brand" class="text-highlighted">
                        {{ row.original.brand.name }}
                    </span>
                    <span v-else class="text-dimmed">-</span>
                </template>

                <template #categories-cell="{ row }">
                    <div
                        v-if="row.original.categories?.length"
                        class="flex max-w-56 flex-wrap gap-1"
                    >
                        <UBadge
                            v-for="category in row.original.categories"
                            :key="category.id"
                            color="neutral"
                            variant="subtle"
                            :label="category.name"
                        />
                    </div>
                    <span v-else class="text-dimmed">-</span>
                </template>

                <template #price-cell="{ row }">
                    <div
                        v-if="row.original.price_discount !== null"
                        class="whitespace-nowrap"
                    >
                        <p class="font-medium text-highlighted">
                            {{ formatPrice(row.original.price_discount) }}
                        </p>
                        <p class="text-xs text-muted">
                            <span class="line-through">
                                {{ formatPrice(row.original.price) }}
                            </span>
                            <UBadge
                                v-if="discountPercent(row.original)"
                                color="error"
                                variant="subtle"
                                size="sm"
                                :label="`-${discountPercent(row.original)}%`"
                                class="ml-1"
                            />
                        </p>
                    </div>
                    <p
                        v-else
                        class="font-medium whitespace-nowrap text-highlighted"
                    >
                        {{ formatPrice(row.original.price) }}
                    </p>
                </template>

                <template #stock-cell="{ row }">
                    <UBadge
                        :color="row.original.stock > 0 ? 'neutral' : 'error'"
                        variant="subtle"
                        :label="
                            row.original.stock > 0
                                ? String(row.original.stock)
                                : 'Habis'
                        "
                    />
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

                <template #actions-cell="{ row }">
                    <div class="flex justify-end gap-1">
                        <UButton
                            icon="i-lucide-pencil"
                            color="neutral"
                            variant="ghost"
                            aria-label="Edit produk"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Hapus produk"
                            :disabled="isLoading || isDeleting"
                            @click="openDeleteModal(row.original)"
                        />
                    </div>
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon
                            name="i-lucide-package-open"
                            class="size-8 text-muted"
                        />
                        <p class="font-medium text-highlighted">
                            {{
                                hasActiveFilter
                                    ? 'Tidak ada produk yang cocok'
                                    : 'Belum ada produk'
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
            :title="isEditing ? 'Edit Produk' : 'Tambah Produk'"
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
                    id="dummy-product-form"
                    :state="state"
                    :validate="validate"
                    class="grid gap-4 sm:grid-cols-2"
                    @submit="submitProduct"
                >
                    <UFormField
                        name="title"
                        label="Judul"
                        required
                        :error="fieldError('title')"
                        class="content-start sm:col-span-2"
                    >
                        <UInput
                            v-model="state.title"
                            placeholder="Nama produk"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="sku"
                        label="SKU"
                        required
                        :error="fieldError('sku')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.sku"
                            placeholder="DP-0001"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="dummy_product_brand_id"
                        label="Brand"
                        :error="fieldError('dummy_product_brand_id')"
                        class="content-start"
                    >
                        <USelect
                            v-model="state.brandId"
                            :items="brandOptions"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="category_ids"
                        label="Kategori"
                        :error="fieldError('category_ids')"
                        class="content-start sm:col-span-2"
                    >
                        <USelectMenu
                            v-model="state.categoryIds"
                            :items="categoryOptions"
                            value-key="value"
                            multiple
                            placeholder="Pilih kategori..."
                            :search-input="{ placeholder: 'Cari kategori...' }"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="price"
                        label="Harga (Rp)"
                        required
                        :error="fieldError('price')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.price"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="150000"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="price_discount"
                        label="Harga diskon (Rp)"
                        hint="Opsional"
                        help="Harga setelah diskon."
                        :error="fieldError('price_discount')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.priceDiscount"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="120000"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="stock"
                        label="Stok"
                        required
                        :error="fieldError('stock')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.stock"
                            type="number"
                            min="0"
                            step="1"
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
                        name="weight"
                        label="Berat (gram)"
                        hint="Opsional"
                        :error="fieldError('weight')"
                        class="content-start"
                    >
                        <UInput
                            v-model="state.weight"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="250"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="image_file"
                        label="Gambar"
                        hint="Opsional"
                        :help="`JPG, PNG, WEBP, GIF, atau AVIF, maksimal ${maxImageSizeMb} MB.`"
                        :error="fieldError('image_file')"
                        class="content-start"
                    >
                        <div
                            v-if="showsCurrentImage"
                            class="flex items-center gap-3 rounded-md border border-default p-2"
                        >
                            <img
                                :src="currentImageUrl ?? undefined"
                                alt="Gambar produk saat ini"
                                class="size-16 shrink-0 rounded object-cover"
                            />
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <p class="text-sm text-muted">
                                    Gambar saat ini
                                </p>
                                <div class="flex gap-1">
                                    <UButton
                                        label="Ganti"
                                        icon="i-lucide-replace"
                                        color="neutral"
                                        variant="outline"
                                        size="xs"
                                        :disabled="isSaving"
                                        @click="isReplacingImage = true"
                                    />
                                    <UButton
                                        label="Hapus"
                                        icon="i-lucide-trash"
                                        color="error"
                                        variant="ghost"
                                        size="xs"
                                        :disabled="isSaving"
                                        @click="state.removeImage = true"
                                    />
                                </div>
                            </div>
                        </div>

                        <template v-else>
                            <UFileUpload
                                v-model="state.imageFile"
                                :accept="imageAccept"
                                icon="i-lucide-image-up"
                                label="Tarik gambar ke sini"
                                description="atau klik untuk memilih"
                                :disabled="isSaving"
                                class="min-h-36 w-full"
                            />
                            <UButton
                                v-if="
                                    currentImageUrl &&
                                    (state.removeImage || isReplacingImage)
                                "
                                :label="
                                    state.removeImage
                                        ? 'Batalkan hapus gambar'
                                        : 'Batal, pakai gambar lama'
                                "
                                icon="i-lucide-undo-2"
                                color="neutral"
                                variant="link"
                                size="xs"
                                class="mt-1 px-0"
                                :disabled="isSaving"
                                @click="keepCurrentImage"
                            />
                        </template>
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
                            :rows="4"
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
                    form="dummy-product-form"
                    icon="i-lucide-save"
                    :label="isEditing ? 'Simpan' : 'Tambah'"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Hapus Produk"
            :description="
                deletingProduct
                    ? `Produk &quot;${deletingProduct.title}&quot; (${deletingProduct.sku}) akan dihapus permanen.`
                    : undefined
            "
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
                    @click="deleteProduct"
                />
            </template>
        </UModal>
    </div>
</template>
