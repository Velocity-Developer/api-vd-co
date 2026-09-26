<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, TableColumn } from '@nuxt/ui';
import { useDebounceFn } from '@vueuse/core';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { mediaTags as mediaTagsRoute } from '@/routes';

type MediaTag = {
    id: number;
    name: string;
    slug: string;
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

type MediaTagsResponse = {
    data: MediaTag[];
    meta: PaginationMeta;
};

type MediaTagFormState = {
    name: string;
    slug: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tag Media',
                href: mediaTagsRoute(),
            },
        ],
    },
});

const columns: TableColumn<MediaTag>[] = [
    {
        accessorKey: 'name',
        header: 'Tag',
    },
    {
        accessorKey: 'slug',
        header: 'Slug',
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

const state = reactive<MediaTagFormState>({
    name: '',
    slug: '',
});

const tagData = ref<MediaTag[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingTagId = ref<number | null>(null);
const deletingTag = ref<MediaTag | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingTagId.value !== null);
const modalTitle = computed(() =>
    isEditing.value ? 'Edit Tag Media' : 'Tambah Tag Media',
);
const modalDescription = computed(() =>
    isEditing.value
        ? 'Ubah nama atau slug tag. Media yang memakai tag ini ikut berubah.'
        : 'Tag juga bisa dibuat langsung dari preview media.',
);

const deleteModalDescription = computed(() => {
    const tag = deletingTag.value;

    if (!tag) {
        return 'Tag ini akan dihapus permanen.';
    }

    const usage = tag.media_count
        ? ` Tag ini dilepas dari ${tag.media_count} media; file medianya tidak ikut terhapus.`
        : '';

    return `Tag "${tag.name}" akan dihapus permanen.${usage}`;
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 tag';
    }

    return `${meta.value.from}-${meta.value.to} dari ${meta.value.total} tag`;
});

const fetchTags = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<MediaTagsResponse>(
            '/ajax/media-tags',
            {
                params: { page, search: search.value.trim() || undefined },
            },
        );

        tagData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data tag media gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const refetchFromFirstPage = (): void => {
    if (currentPage.value === 1) {
        void fetchTags(1);

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

const validate = (formState: Partial<MediaTagFormState>): FormError[] => {
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
    editingTagId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (tag: MediaTag): void => {
    state.name = tag.name;
    state.slug = tag.slug;
    editingTagId.value = tag.id;
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

const openDeleteModal = (tag: MediaTag): void => {
    deletingTag.value = tag;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingTag.value = null;
    deleteMessage.value = null;
};

const deleteTag = async (): Promise<void> => {
    if (!deletingTag.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`/ajax/media-tags/${deletingTag.value.id}`);

        const targetPage =
            tagData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        toast.success('Tag media dihapus.');
        isDeleteModalOpen.value = false;
        deletingTag.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchTags(targetPage);
        }
    } catch {
        deleteMessage.value = 'Tag media gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Tag media gagal disimpan.';

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

const submitTag = async (): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    const payload = { name: state.name.trim(), slug: state.slug.trim() };

    try {
        if (editingTagId.value) {
            await axios.patch(
                `/ajax/media-tags/${editingTagId.value}`,
                payload,
            );
        } else {
            await axios.post('/ajax/media-tags', payload);
        }

        toast.success(
            isEditing.value ? 'Tag media disimpan.' : 'Tag media ditambahkan.',
        );
        isModalOpen.value = false;
        resetForm();
        await fetchTags(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchTags(page);
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
        deletingTag.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchTags();
});
</script>

<template>
    <Head title="Tag Media" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Tag Media
                </h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Cari tag..."
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
                    aria-label="Muat ulang tag media"
                    @click="fetchTags(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat tag media"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchTags(currentPage),
                },
            ]"
        />

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="tagData"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #name-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.name"
                            icon="i-lucide-tag"
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
                            aria-label="Edit tag media"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Hapus tag media"
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
                                    ? 'Tidak ada tag yang cocok'
                                    : 'Belum ada tag media'
                            }}
                        </p>
                        <p class="text-sm text-muted">
                            {{
                                search.trim()
                                    ? 'Coba kata kunci lain.'
                                    : 'Tag dibuat di sini atau langsung dari preview media.'
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
                    id="media-tag-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitTag"
                >
                    <UFormField
                        name="name"
                        label="Nama"
                        required
                        :error="fieldError('name')"
                    >
                        <UInput
                            v-model="state.name"
                            placeholder="Nama tag"
                            :maxlength="50"
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
                            placeholder="nama-tag"
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
                    form="media-tag-form"
                    icon="i-lucide-save"
                    :label="isEditing ? 'Simpan' : 'Tambah'"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Hapus Tag Media"
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
                    @click="deleteTag"
                />
            </template>
        </UModal>
    </div>
</template>
