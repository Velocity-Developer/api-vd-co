<script setup lang="ts">
import type { FormError, TableColumn } from '@nuxt/ui';
import { useDebounceFn } from '@vueuse/core';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type Term = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    products_count?: number;
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

type TermFormState = {
    name: string;
    slug: string;
    description: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

const props = defineProps<{
    /** Ajax endpoint, e.g. /ajax/dummy-product-brands */
    endpoint: string;
    /** Page heading, e.g. "Brand Produk Dummy" */
    title: string;
    /** Lowercase singular noun used in messages, e.g. "brand" */
    noun: string;
    icon: string;
}>();

const capitalizedNoun = computed(
    () => props.noun.charAt(0).toUpperCase() + props.noun.slice(1),
);

const columns: TableColumn<Term>[] = [
    { accessorKey: 'name', header: capitalizedNoun.value },
    { accessorKey: 'slug', header: 'Slug' },
    { accessorKey: 'description', header: 'Deskripsi' },
    { accessorKey: 'products_count', header: 'Produk' },
    { accessorKey: 'created_at', header: 'Dibuat' },
    { id: 'actions' },
];

const state = reactive<TermFormState>({
    name: '',
    slug: '',
    description: '',
});

const termData = ref<Term[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingTermId = ref<number | null>(null);
const deletingTerm = ref<Term | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingTermId.value !== null);

const deleteModalDescription = computed(() => {
    const term = deletingTerm.value;

    if (!term) {
        return `${capitalizedNoun.value} ini akan dihapus permanen.`;
    }

    const usage = term.products_count
        ? props.noun === 'brand'
            ? ` ${term.products_count} produk tetap ada, hanya brand-nya dikosongkan.`
            : ` ${term.products_count} produk tetap ada, hanya dilepas dari ${props.noun} ini.`
        : '';

    return `${capitalizedNoun.value} "${term.name}" akan dihapus permanen.${usage}`;
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return `0 ${props.noun}`;
    }

    return `${meta.value.from}-${meta.value.to} dari ${meta.value.total} ${props.noun}`;
});

const fetchTerms = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<{
            data: Term[];
            meta: PaginationMeta;
        }>(props.endpoint, {
            params: { page, search: search.value.trim() || undefined },
        });

        termData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = `Data ${props.noun} gagal dimuat.`;
    } finally {
        isLoading.value = false;
    }
};

const refetchFromFirstPage = (): void => {
    if (currentPage.value === 1) {
        void fetchTerms(1);

        return;
    }

    currentPage.value = 1;
};

const debouncedSearch = useDebounceFn(refetchFromFirstPage, 400);

const slugify = (value: string): string => {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value));
};

const validate = (formState: Partial<TermFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.name?.trim()) {
        errors.push({ name: 'name', message: 'Nama wajib diisi.' });
    }

    if (!formState.slug?.trim()) {
        errors.push({ name: 'slug', message: 'Slug wajib diisi.' });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const resetForm = (): void => {
    state.name = '';
    state.slug = '';
    state.description = '';
    editingTermId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (term: Term): void => {
    state.name = term.name;
    state.slug = term.slug;
    state.description = term.description ?? '';
    editingTermId.value = term.id;
    formMessage.value = null;
    serverErrors.value = {};
    isModalOpen.value = true;
};

const closeModal = (): void => {
    if (isSaving.value) {
        return;
    }

    isModalOpen.value = false;
    resetForm();
};

const openDeleteModal = (term: Term): void => {
    deletingTerm.value = term;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingTerm.value = null;
    deleteMessage.value = null;
};

const deleteTerm = async (): Promise<void> => {
    if (!deletingTerm.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`${props.endpoint}/${deletingTerm.value.id}`);

        const targetPage =
            termData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        toast.success(`${capitalizedNoun.value} dihapus.`);
        isDeleteModalOpen.value = false;
        deletingTerm.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchTerms(targetPage);
        }
    } catch {
        deleteMessage.value = `${capitalizedNoun.value} gagal dihapus.`;
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = `${capitalizedNoun.value} gagal disimpan.`;

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

const submitTerm = async (): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    const payload = {
        name: state.name.trim(),
        slug: state.slug.trim(),
        description: state.description.trim() || null,
    };

    try {
        if (editingTermId.value) {
            await axios.patch(
                `${props.endpoint}/${editingTermId.value}`,
                payload,
            );
        } else {
            await axios.post(props.endpoint, payload);
        }

        toast.success(
            isEditing.value
                ? `${capitalizedNoun.value} disimpan.`
                : `${capitalizedNoun.value} ditambahkan.`,
        );
        isModalOpen.value = false;
        resetForm();
        await fetchTerms(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchTerms(page);
    }
});

watch(search, () => {
    void debouncedSearch();
});

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
        deletingTerm.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchTerms();
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    {{ title }}
                </h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    :placeholder="`Cari ${noun}...`"
                    class="w-full sm:w-64"
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
                    :aria-label="`Muat ulang ${noun}`"
                    @click="fetchTerms(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            :title="`Gagal memuat ${noun}`"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchTerms(currentPage),
                },
            ]"
        />

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="termData"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #name-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.name"
                            :icon="icon"
                            size="lg"
                        />

                        <p class="truncate font-medium text-highlighted">
                            {{ row.original.name }}
                        </p>
                    </div>
                </template>

                <template #slug-cell="{ row }">
                    <UBadge
                        color="neutral"
                        variant="subtle"
                        :label="row.original.slug"
                    />
                </template>

                <template #description-cell="{ row }">
                    <p class="max-w-md truncate text-sm text-muted">
                        {{ row.original.description || '-' }}
                    </p>
                </template>

                <template #products_count-cell="{ row }">
                    <UBadge
                        color="primary"
                        variant="subtle"
                        :label="String(row.original.products_count ?? 0)"
                    />
                </template>

                <template #created_at-cell="{ row }">
                    {{ formatDate(row.original.created_at) }}
                </template>

                <template #actions-cell="{ row }">
                    <div class="flex justify-end gap-1">
                        <UButton
                            icon="i-lucide-pencil"
                            color="neutral"
                            variant="ghost"
                            :aria-label="`Edit ${noun}`"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            :aria-label="`Hapus ${noun}`"
                            :disabled="isLoading || isDeleting"
                            @click="openDeleteModal(row.original)"
                        />
                    </div>
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon
                            name="i-lucide-inbox"
                            class="size-8 text-muted"
                        />
                        <p class="font-medium text-highlighted">
                            {{
                                search.trim()
                                    ? `Tidak ada ${noun} yang cocok`
                                    : `Belum ada ${noun}`
                            }}
                        </p>
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
            :title="`${isEditing ? 'Edit' : 'Tambah'} ${capitalizedNoun}`"
            :ui="{ footer: 'justify-end' }"
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
                    id="dummy-product-term-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitTerm"
                >
                    <UFormField
                        name="name"
                        label="Nama"
                        required
                        :error="fieldError('name')"
                    >
                        <UInput
                            v-model="state.name"
                            :placeholder="`Nama ${noun}`"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="slug"
                        label="Slug"
                        required
                        :error="fieldError('slug')"
                    >
                        <UInput
                            v-model="state.slug"
                            :placeholder="`nama-${noun}`"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="description"
                        label="Deskripsi"
                        hint="Opsional"
                        :error="fieldError('description')"
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
                    form="dummy-product-term-form"
                    icon="i-lucide-save"
                    :label="isEditing ? 'Simpan' : 'Tambah'"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            :title="`Hapus ${capitalizedNoun}`"
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
                    @click="deleteTerm"
                />
            </template>
        </UModal>
    </div>
</template>
