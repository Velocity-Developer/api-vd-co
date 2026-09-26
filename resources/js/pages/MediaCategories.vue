<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { mediaCategories as mediaCategoriesRoute } from '@/routes';

type MediaCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    parent?: { id: number; name: string; slug: string } | null;
    media_count?: number;
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

type MediaCategoriesResponse = {
    data: MediaCategory[];
    meta: PaginationMeta;
};

type ResourceResponse<T> = {
    data: T;
};

type MediaCategoryFormState = {
    name: string;
    slug: string;
    description: string;
    parent_id: number;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Kategori Media',
                href: mediaCategoriesRoute(),
            },
        ],
    },
});

/** USelect cannot hold a null value, so 0 stands for "no parent". */
const noParent = 0;

const columns: TableColumn<MediaCategory>[] = [
    {
        accessorKey: 'name',
        header: 'Kategori',
    },
    {
        accessorKey: 'slug',
        header: 'Slug',
    },
    {
        accessorKey: 'description',
        header: 'Deskripsi',
    },
    {
        accessorKey: 'media_count',
        header: 'Media',
    },
    {
        accessorKey: 'created_at',
        header: 'Dibuat',
    },
    {
        id: 'actions',
    },
];

const state = reactive<MediaCategoryFormState>({
    name: '',
    slug: '',
    description: '',
    parent_id: noParent,
});

const categoryData = ref<MediaCategory[]>([]);
const allCategories = ref<MediaCategory[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingCategoryId = ref<number | null>(null);
const deletingCategory = ref<MediaCategory | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingCategoryId.value !== null);
const modalTitle = computed(() =>
    isEditing.value ? 'Edit Kategori Media' : 'Tambah Kategori Media',
);
const modalDescription = computed(() =>
    isEditing.value
        ? 'Ubah detail kategori media yang sudah ada.'
        : 'Tambahkan kategori baru untuk mengelompokkan media.',
);
const submitLabel = computed(() => (isEditing.value ? 'Simpan' : 'Tambah'));

const deleteModalDescription = computed(() => {
    const category = deletingCategory.value;

    if (!category) {
        return 'Kategori ini akan dihapus permanen.';
    }

    const childCount = allCategories.value.filter(
        (item) => item.parent_id === category.id,
    ).length;
    const notes = [
        `Kategori "${category.name}" akan dihapus permanen.`,
        'File media di dalamnya tidak ikut terhapus, hanya dilepas dari kategori ini.',
    ];

    if (childCount > 0) {
        notes.push(`${childCount} subkategori akan menjadi kategori utama.`);
    }

    return notes.join(' ');
});

const ancestorNames = (category: MediaCategory): string[] => {
    const byId = new Map(allCategories.value.map((item) => [item.id, item]));
    const names: string[] = [];
    const visited = new Set([category.id]);
    let parentId = category.parent_id;

    while (parentId !== null && !visited.has(parentId)) {
        const parent = byId.get(parentId);

        if (!parent) {
            break;
        }

        names.unshift(parent.name);
        visited.add(parent.id);
        parentId = parent.parent_id;
    }

    return names;
};

const categoryPath = (category: MediaCategory): string =>
    [...ancestorNames(category), category.name].join(' › ');

const descendantIds = (categoryId: number): Set<number> => {
    const ids = new Set<number>();
    let parentIds = [categoryId];

    while (parentIds.length > 0) {
        parentIds = allCategories.value
            .filter(
                (item) =>
                    item.parent_id !== null &&
                    parentIds.includes(item.parent_id) &&
                    !ids.has(item.id),
            )
            .map((item) => item.id);
        parentIds.forEach((id) => ids.add(id));
    }

    return ids;
};

const parentOptions = computed(() => {
    const editingId = editingCategoryId.value;
    const excludedIds =
        editingId === null
            ? new Set<number>()
            : new Set([editingId, ...descendantIds(editingId)]);

    return [
        { label: 'Tanpa induk', value: noParent },
        ...allCategories.value
            .filter((category) => !excludedIds.has(category.id))
            .map((category) => ({
                label: categoryPath(category),
                value: category.id,
            }))
            .sort((a, b) => a.label.localeCompare(b.label, 'id')),
    ];
});

const filteredCategories = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return categoryData.value;
    }

    return categoryData.value.filter((category) =>
        [
            category.name,
            category.slug,
            category.description,
            category.parent?.name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(query),
    );
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 kategori';
    }

    return `${meta.value.from}-${meta.value.to} dari ${meta.value.total} kategori`;
});

const fetchAllCategories = async (): Promise<void> => {
    const response = await axios.get<ResourceResponse<MediaCategory[]>>(
        '/ajax/media-categories',
        { params: { all: 1 } },
    );

    allCategories.value = response.data.data;
};

const fetchCategories = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const [response] = await Promise.all([
            axios.get<MediaCategoriesResponse>('/ajax/media-categories', {
                params: { page },
            }),
            fetchAllCategories(),
        ]);

        categoryData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data kategori media gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

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

const validate = (formState: Partial<MediaCategoryFormState>): FormError[] => {
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
    state.parent_id = noParent;
    editingCategoryId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (category: MediaCategory): void => {
    state.name = category.name;
    state.slug = category.slug;
    state.description = category.description ?? '';
    state.parent_id = category.parent_id ?? noParent;
    editingCategoryId.value = category.id;
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

const buildPayload = () => ({
    name: state.name.trim(),
    slug: state.slug.trim(),
    description: state.description.trim() || null,
    parent_id: state.parent_id === noParent ? null : state.parent_id,
});

const openDeleteModal = (category: MediaCategory): void => {
    deletingCategory.value = category;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingCategory.value = null;
    deleteMessage.value = null;
};

const deleteCategory = async (): Promise<void> => {
    if (!deletingCategory.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(
            `/ajax/media-categories/${deletingCategory.value.id}`,
        );

        const targetPage =
            categoryData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        toast.success('Kategori media dihapus.');
        isDeleteModalOpen.value = false;
        deletingCategory.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchCategories(targetPage);
        }
    } catch {
        deleteMessage.value = 'Kategori media gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Kategori media gagal disimpan.';

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

const submitCategory = async (): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        if (editingCategoryId.value) {
            await axios.patch<ResourceResponse<MediaCategory>>(
                `/ajax/media-categories/${editingCategoryId.value}`,
                buildPayload(),
            );
        } else {
            await axios.post<ResourceResponse<MediaCategory>>(
                '/ajax/media-categories',
                buildPayload(),
            );
        }

        toast.success(
            isEditing.value
                ? 'Kategori media disimpan.'
                : 'Kategori media ditambahkan.',
        );
        isModalOpen.value = false;
        resetForm();
        await fetchCategories(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchCategories(page);
    }
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
        deletingCategory.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchCategories();
});
</script>

<template>
    <Head title="Kategori Media" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Kategori Media
                </h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Cari kategori..."
                    :disabled="isLoading"
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
                    aria-label="Muat ulang kategori media"
                    @click="fetchCategories(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat kategori media"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchCategories(currentPage),
                },
            ]"
        />

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="filteredCategories"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #name-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.name"
                            icon="i-lucide-folder-open"
                            size="lg"
                        />

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.name }}
                            </p>
                            <p
                                v-if="row.original.parent_id"
                                class="truncate text-xs text-muted"
                            >
                                di dalam
                                {{
                                    ancestorNames(row.original).join(' › ') ||
                                    row.original.parent?.name
                                }}
                            </p>
                        </div>
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

                <template #media_count-cell="{ row }">
                    <UBadge
                        color="primary"
                        variant="subtle"
                        :label="String(row.original.media_count ?? 0)"
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
                            aria-label="Edit kategori media"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Hapus kategori media"
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
                            Belum ada kategori media
                        </p>
                        <p class="text-sm text-muted">
                            Data belum tersedia atau tidak cocok dengan
                            pencarian.
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
            :title="modalTitle"
            :description="modalDescription"
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
                    id="media-category-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitCategory"
                >
                    <UFormField
                        name="name"
                        label="Nama"
                        required
                        :error="fieldError('name')"
                    >
                        <UInput
                            v-model="state.name"
                            placeholder="Nama kategori"
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
                            placeholder="nama-kategori"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="parent_id"
                        label="Induk"
                        hint="Opsional"
                        :error="fieldError('parent_id')"
                    >
                        <USelect
                            v-model="state.parent_id"
                            :items="parentOptions"
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
                            placeholder="Deskripsi singkat kategori"
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
                    form="media-category-form"
                    icon="i-lucide-save"
                    :label="submitLabel"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Hapus Kategori Media"
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
                    @click="deleteCategory"
                />
            </template>
        </UModal>
    </div>
</template>
