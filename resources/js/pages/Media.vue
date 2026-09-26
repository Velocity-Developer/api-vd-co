<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    media as mediaRoute,
    mediaCategories as mediaCategoriesRoute,
} from '@/routes';

type MediaTerm = {
    id: number;
    name: string;
    slug: string;
};

type MediaItem = {
    id: number;
    collection: string;
    disk: string;
    path: string;
    url: string;
    original_name: string;
    file_name: string;
    extension: string | null;
    mime_type: string;
    size: number;
    title: string | null;
    alt_text: string | null;
    caption: string | null;
    metadata: Record<string, unknown> | null;
    mediable_type: string | null;
    mediable_id: number | null;
    creator?: { id: number; name: string } | null;
    categories?: MediaTerm[];
    tags?: MediaTerm[];
    created_at: string;
    updated_at: string;
};

type MediaType = 'all' | 'image' | 'video' | 'document';

type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};

type MediaResponse = {
    data: MediaItem[];
    meta: PaginationMeta;
};

type EditableField = 'title' | 'caption' | 'categories' | 'tags';

type CategoryOption = {
    label: string;
    value: number;
};

type MediaCategoryItem = MediaTerm & {
    parent_id: number | null;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Media',
                href: mediaRoute(),
            },
        ],
    },
});

const typeOptions: { label: string; value: MediaType }[] = [
    { label: 'Semua tipe', value: 'all' },
    { label: 'Gambar', value: 'image' },
    { label: 'Video', value: 'video' },
    { label: 'Dokumen', value: 'document' },
];

const mediaData = ref<MediaItem[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const selectedType = ref<MediaType>('all');
const selectedCategory = ref('all');
const currentPage = ref(1);
const isLoading = ref(true);
const errorMessage = ref<string | null>(null);
const selectedMedia = ref<MediaItem | null>(null);
const isPreviewOpen = ref(false);
const isUploadOpen = ref(false);
const uploadFiles = ref<File[]>([]);
const isUploading = ref(false);
const uploadProgress = ref(0);
const uploadMessage = ref<string | null>(null);
const isDeleteOpen = ref(false);
const isDeleting = ref(false);
const deleteMessage = ref<string | null>(null);
const editingField = ref<EditableField | null>(null);
const fieldDraft = ref('');
const categoryDraft = ref<number[]>([]);
const categoryOptions = ref<CategoryOption[]>([]);
const isLoadingCategories = ref(false);
const isSavingField = ref(false);
const fieldMessage = ref<string | null>(null);

const editableFields: {
    key: EditableField;
    label: string;
    placeholder: string;
    hint?: string;
    maxlength?: number;
    input: 'text' | 'textarea' | 'categories';
}[] = [
    {
        key: 'title',
        label: 'Judul',
        placeholder: 'Tulis judul...',
        hint: 'Kosongkan untuk memakai nama file.',
        maxlength: 255,
        input: 'text',
    },
    {
        key: 'caption',
        label: 'Caption',
        placeholder: 'Tulis caption...',
        maxlength: 1000,
        input: 'textarea',
    },
    {
        key: 'categories',
        label: 'Kategori',
        placeholder: 'Pilih kategori...',
        input: 'categories',
    },
    {
        key: 'tags',
        label: 'Tag',
        placeholder: 'promo, banner, september',
        hint: 'Pisahkan dengan koma.',
        input: 'textarea',
    },
];

const maxUploadFiles = 10;
const maxUploadSizeMb = 20;
const uploadAccept =
    '.jpg,.jpeg,.png,.gif,.webp,.avif,.mp4,.webm,.mov,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.zip';

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 file';
    }

    return `${meta.value.from}-${meta.value.to} dari ${meta.value.total} file`;
});

const categoryFilterOptions = computed(() => [
    { label: 'Semua kategori', value: 'all' },
    { label: 'Tanpa kategori', value: 'none' },
    ...categoryOptions.value.map((option) => ({
        label: option.label,
        value: String(option.value),
    })),
]);

const hasActiveFilter = computed(
    () =>
        search.value.trim() !== '' ||
        selectedType.value !== 'all' ||
        selectedCategory.value !== 'all',
);

const fetchMedia = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<MediaResponse>('/ajax/media', {
            params: {
                page,
                search: search.value.trim() || undefined,
                type:
                    selectedType.value === 'all'
                        ? undefined
                        : selectedType.value,
                category:
                    selectedCategory.value === 'all'
                        ? undefined
                        : selectedCategory.value,
            },
        });

        mediaData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data media gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const refetchFromFirstPage = (): void => {
    if (currentPage.value === 1) {
        void fetchMedia(1);

        return;
    }

    currentPage.value = 1;
};

const debouncedSearch = useDebounceFn(refetchFromFirstPage, 400);

const isImage = (item: MediaItem): boolean =>
    item.mime_type.startsWith('image/');
const isVideo = (item: MediaItem): boolean =>
    item.mime_type.startsWith('video/');

const fileIcon = (item: MediaItem): string => {
    if (isVideo(item)) {
        return 'i-lucide-file-video';
    }

    if (item.mime_type === 'application/pdf') {
        return 'i-lucide-file-text';
    }

    if (item.mime_type.startsWith('audio/')) {
        return 'i-lucide-file-audio';
    }

    if (/zip|rar|tar|gzip|7z/.test(item.mime_type)) {
        return 'i-lucide-file-archive';
    }

    return 'i-lucide-file';
};

const displayName = (item: MediaItem): string =>
    item.title || item.original_name;

const fileLabel = (item: MediaItem): string =>
    (item.extension || item.mime_type.split('/').pop() || 'file').toUpperCase();

const formatSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ['KB', 'MB', 'GB'];
    let value = bytes / 1024;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex++;
    }

    return `${value.toLocaleString('id-ID', { maximumFractionDigits: 1 })} ${units[unitIndex]}`;
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const dimensions = (item: MediaItem): string | null => {
    const width = item.metadata?.width;
    const height = item.metadata?.height;

    if (typeof width !== 'number' || typeof height !== 'number') {
        return null;
    }

    return `${width} × ${height} px`;
};

const openPreview = (item: MediaItem): void => {
    selectedMedia.value = item;
    cancelFieldEdit();
    isPreviewOpen.value = true;
};

const hasFieldValue = (item: MediaItem, field: EditableField): boolean =>
    field === 'tags' || field === 'categories'
        ? Boolean(item[field]?.length)
        : Boolean(item[field]);

const fetchCategoryOptions = async (): Promise<void> => {
    isLoadingCategories.value = true;

    try {
        const response = await axios.get<{ data: MediaCategoryItem[] }>(
            '/ajax/media-categories',
            { params: { all: 1 } },
        );
        const byId = new Map(
            response.data.data.map((category) => [category.id, category]),
        );
        const categoryPath = (category: MediaCategoryItem): string => {
            const names = [category.name];
            const visited = new Set([category.id]);
            let parent = byId.get(category.parent_id ?? 0);

            while (parent && !visited.has(parent.id)) {
                names.unshift(parent.name);
                visited.add(parent.id);
                parent = byId.get(parent.parent_id ?? 0);
            }

            return names.join(' › ');
        };

        categoryOptions.value = response.data.data
            .map((category) => ({
                label: categoryPath(category),
                value: category.id,
            }))
            .sort((a, b) => a.label.localeCompare(b.label, 'id'));
    } catch {
        if (editingField.value === 'categories') {
            fieldMessage.value = 'Daftar kategori gagal dimuat.';
        } else {
            toast.error('Daftar kategori gagal dimuat.');
        }
    } finally {
        isLoadingCategories.value = false;
    }
};

const parseTags = (value: string): string[] => {
    const tags = value
        .split(',')
        .map((tag) => tag.trim().replace(/\s+/g, ' '))
        .filter((tag) => tag !== '');

    return tags.filter(
        (tag, index) =>
            tags.findIndex(
                (other) => other.toLowerCase() === tag.toLowerCase(),
            ) === index,
    );
};

const startFieldEdit = (field: EditableField): void => {
    const item = selectedMedia.value;

    fieldMessage.value = null;
    editingField.value = field;

    if (field === 'categories') {
        categoryDraft.value = (item?.categories ?? []).map(
            (category) => category.id,
        );
        void fetchCategoryOptions();

        return;
    }

    fieldDraft.value =
        field === 'tags'
            ? (item?.tags ?? []).map((tag) => tag.name).join(', ')
            : (item?.[field] ?? '');
};

const cancelFieldEdit = (): void => {
    editingField.value = null;
    fieldDraft.value = '';
    categoryDraft.value = [];
    fieldMessage.value = null;
};

const saveField = async (): Promise<void> => {
    const field = editingField.value;

    if (!selectedMedia.value || !field) {
        return;
    }

    const label =
        editableFields.find((editable) => editable.key === field)?.label ??
        'Data';
    const payload =
        field === 'categories'
            ? { category_ids: categoryDraft.value }
            : field === 'tags'
              ? { tags: parseTags(fieldDraft.value) }
              : { [field]: fieldDraft.value.trim() || null };

    isSavingField.value = true;
    fieldMessage.value = null;

    try {
        const response = await axios.patch<{ data: MediaItem }>(
            `/ajax/media/${selectedMedia.value.id}`,
            payload,
        );
        const updatedMedia = response.data.data;

        selectedMedia.value = updatedMedia;
        mediaData.value = mediaData.value.map((item) =>
            item.id === updatedMedia.id ? updatedMedia : item,
        );
        cancelFieldEdit();
        toast.success(`${label} disimpan.`);
    } catch (error) {
        const response =
            error instanceof AxiosError && error.response?.status === 422
                ? (error.response.data as ValidationResponse)
                : null;

        fieldMessage.value =
            Object.values(response?.errors ?? {})[0]?.[0] ??
            `${label} gagal disimpan.`;
    } finally {
        isSavingField.value = false;
    }
};

const copyUrl = async (item: MediaItem): Promise<void> => {
    try {
        await navigator.clipboard.writeText(item.url);
        toast.success('URL disalin.');
    } catch {
        toast.error('URL gagal disalin.');
    }
};

const openUploadModal = (): void => {
    uploadFiles.value = [];
    uploadMessage.value = null;
    uploadProgress.value = 0;
    isUploadOpen.value = true;
};

const validateUploadFiles = (): string | null => {
    if (uploadFiles.value.length === 0) {
        return 'Pilih minimal satu file.';
    }

    if (uploadFiles.value.length > maxUploadFiles) {
        return `Maksimal ${maxUploadFiles} file sekali upload.`;
    }

    const allowedExtensions = uploadAccept.split(',');
    const invalidFile = uploadFiles.value.find((file) => {
        const extension = `.${file.name.split('.').pop()?.toLowerCase()}`;

        return !allowedExtensions.includes(extension);
    });

    if (invalidFile) {
        return `Tipe file "${invalidFile.name}" tidak diizinkan.`;
    }

    const oversizedFile = uploadFiles.value.find(
        (file) => file.size > maxUploadSizeMb * 1024 * 1024,
    );

    if (oversizedFile) {
        return `"${oversizedFile.name}" lebih dari ${maxUploadSizeMb} MB.`;
    }

    return null;
};

const uploadErrorMessage = (error: unknown): string => {
    if (!(error instanceof AxiosError)) {
        return 'Upload gagal.';
    }

    if (error.response?.status === 413) {
        return 'Total ukuran file melebihi batas server.';
    }

    if (error.response?.status === 422) {
        const response = error.response.data as ValidationResponse;
        const [field, messages] =
            Object.entries(response.errors ?? {})[0] ?? [];
        const fileIndex = field?.match(/^files\.(\d+)$/)?.[1];
        const fileName =
            fileIndex !== undefined
                ? uploadFiles.value[Number(fileIndex)]?.name
                : undefined;
        const message =
            messages?.[0] ?? response.message ?? 'File tidak valid.';

        return fileName ? `${fileName}: ${message}` : message;
    }

    return 'Upload gagal.';
};

const submitUpload = async (): Promise<void> => {
    uploadMessage.value = validateUploadFiles();

    if (uploadMessage.value) {
        return;
    }

    const formData = new FormData();

    uploadFiles.value.forEach((file) => formData.append('files[]', file));

    isUploading.value = true;
    uploadProgress.value = 0;

    try {
        const response = await axios.post<{ data: MediaItem[] }>(
            '/ajax/media',
            formData,
            {
                onUploadProgress: (event) => {
                    if (event.total) {
                        uploadProgress.value = Math.round(
                            (event.loaded / event.total) * 100,
                        );
                    }
                },
            },
        );

        toast.success(`${response.data.data.length} file berhasil diupload.`);
        isUploadOpen.value = false;
        uploadFiles.value = [];
        refetchFromFirstPage();
    } catch (error) {
        uploadMessage.value = uploadErrorMessage(error);
    } finally {
        isUploading.value = false;
    }
};

const openDeleteModal = (): void => {
    deleteMessage.value = null;
    isDeleteOpen.value = true;
};

const deleteMedia = async (): Promise<void> => {
    if (!selectedMedia.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`/ajax/media/${selectedMedia.value.id}`);

        const targetPage =
            mediaData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        toast.success('Media dihapus.');
        isDeleteOpen.value = false;
        isPreviewOpen.value = false;
        selectedMedia.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchMedia(targetPage);
        }
    } catch {
        deleteMessage.value = 'Media gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

const resetFilters = (): void => {
    search.value = '';
    selectedType.value = 'all';
    selectedCategory.value = 'all';
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchMedia(page);
    }
});

watch(search, () => {
    void debouncedSearch();
});

watch([selectedType, selectedCategory], refetchFromFirstPage);

watch(uploadFiles, () => {
    uploadMessage.value = null;
});

onMounted(() => {
    void fetchMedia();
    void fetchCategoryOptions();
});
</script>

<template>
    <Head title="Media" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">Media</h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Cari nama file..."
                    class="w-full sm:w-64"
                />

                <USelect
                    v-model="selectedType"
                    :items="typeOptions"
                    class="w-36"
                    aria-label="Filter tipe media"
                />

                <USelect
                    v-model="selectedCategory"
                    :items="categoryFilterOptions"
                    :loading="isLoadingCategories"
                    :content="{ align: 'end' }"
                    :ui="{
                        content:
                            'w-auto min-w-(--reka-select-trigger-width) max-w-80',
                    }"
                    class="w-48"
                    aria-label="Filter kategori media"
                />

                <UButton
                    icon="i-lucide-upload"
                    label="Upload"
                    @click="openUploadModal"
                />

                <UButton
                    icon="i-lucide-refresh-cw"
                    color="neutral"
                    variant="outline"
                    :loading="isLoading"
                    aria-label="Refresh media"
                    @click="fetchMedia(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat media"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchMedia(currentPage),
                },
            ]"
        />

        <div
            v-if="isLoading && mediaData.length === 0"
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
        >
            <div v-for="index in 12" :key="index" class="space-y-2">
                <USkeleton class="aspect-square w-full rounded-lg" />
                <USkeleton class="h-4 w-3/4" />
                <USkeleton class="h-3 w-1/2" />
            </div>
        </div>

        <div
            v-else-if="mediaData.length === 0 && !errorMessage"
            class="flex flex-col items-center gap-2 rounded-lg border border-dashed border-default py-16"
        >
            <UIcon name="i-lucide-images" class="size-8 text-muted" />
            <p class="font-medium text-highlighted">
                {{
                    hasActiveFilter
                        ? 'Tidak ada media yang cocok'
                        : 'Belum ada media'
                }}
            </p>
            <p class="text-sm text-muted">
                {{
                    hasActiveFilter
                        ? 'Coba ubah kata kunci, filter tipe, atau kategori.'
                        : 'File yang sudah diupload akan tampil di sini.'
                }}
            </p>
            <UButton
                v-if="!hasActiveFilter"
                label="Upload file"
                icon="i-lucide-upload"
                size="sm"
                class="mt-2"
                @click="openUploadModal"
            />
            <UButton
                v-else
                label="Reset filter"
                color="neutral"
                variant="outline"
                size="sm"
                class="mt-2"
                @click="resetFilters"
            />
        </div>

        <div
            v-else-if="mediaData.length > 0"
            class="grid grid-cols-2 gap-4 transition-opacity sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
            :class="{ 'opacity-60': isLoading }"
        >
            <button
                v-for="item in mediaData"
                :key="item.id"
                type="button"
                class="group flex min-w-0 flex-col gap-2 rounded-lg text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                @click="openPreview(item)"
            >
                <div
                    class="relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-lg border border-default bg-elevated"
                >
                    <img
                        v-if="isImage(item)"
                        :src="item.url"
                        :alt="item.alt_text || item.original_name"
                        loading="lazy"
                        class="size-full object-cover transition-transform duration-200 group-hover:scale-105"
                    />
                    <UIcon
                        v-else
                        :name="fileIcon(item)"
                        class="size-10 text-muted"
                    />

                    <UBadge
                        color="neutral"
                        variant="solid"
                        size="sm"
                        :label="fileLabel(item)"
                        class="absolute top-2 left-2 opacity-90"
                    />
                </div>

                <div class="min-w-0 px-0.5">
                    <p
                        class="truncate text-sm font-medium text-highlighted"
                        :title="displayName(item)"
                    >
                        {{ displayName(item) }}
                    </p>
                    <p class="text-xs text-muted">
                        {{ formatSize(item.size) }}
                    </p>
                </div>
            </button>
        </div>

        <div
            v-if="meta && meta.total > meta.per_page"
            class="flex flex-col gap-3 border-t border-default pt-4 sm:flex-row sm:items-center sm:justify-between"
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

        <UModal
            v-model:open="isPreviewOpen"
            :title="selectedMedia ? displayName(selectedMedia) : 'Media'"
            :description="
                selectedMedia?.title ? selectedMedia.original_name : undefined
            "
            :ui="{ content: 'sm:max-w-4xl', footer: 'justify-end' }"
        >
            <template #body>
                <div
                    v-if="selectedMedia"
                    class="grid gap-6 md:grid-cols-[minmax(0,1fr)_16rem]"
                >
                    <div
                        class="flex min-h-64 items-center justify-center overflow-hidden rounded-lg border border-default bg-elevated"
                    >
                        <img
                            v-if="isImage(selectedMedia)"
                            :src="selectedMedia.url"
                            :alt="
                                selectedMedia.alt_text ||
                                selectedMedia.original_name
                            "
                            class="max-h-[60vh] w-auto object-contain"
                        />
                        <video
                            v-else-if="isVideo(selectedMedia)"
                            :src="selectedMedia.url"
                            controls
                            preload="metadata"
                            class="max-h-[60vh] w-full"
                        />
                        <UIcon
                            v-else
                            :name="fileIcon(selectedMedia)"
                            class="size-16 text-muted"
                        />
                    </div>

                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-muted">Tipe</dt>
                            <dd class="text-highlighted">
                                {{ selectedMedia.mime_type }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">Ukuran</dt>
                            <dd class="text-highlighted">
                                {{ formatSize(selectedMedia.size) }}
                            </dd>
                        </div>
                        <div v-if="dimensions(selectedMedia)">
                            <dt class="text-muted">Dimensi</dt>
                            <dd class="text-highlighted">
                                {{ dimensions(selectedMedia) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">Diupload</dt>
                            <dd class="text-highlighted">
                                {{ formatDate(selectedMedia.created_at) }}
                                <span
                                    v-if="selectedMedia.creator"
                                    class="text-muted"
                                >
                                    oleh {{ selectedMedia.creator.name }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">Path</dt>
                            <dd class="break-all text-highlighted">
                                {{ selectedMedia.path }}
                            </dd>
                        </div>
                        <div v-if="selectedMedia.alt_text">
                            <dt class="text-muted">Alt text</dt>
                            <dd class="text-highlighted">
                                {{ selectedMedia.alt_text }}
                            </dd>
                        </div>
                        <div v-for="field in editableFields" :key="field.key">
                            <dt
                                class="flex items-center justify-between gap-2 text-muted"
                            >
                                {{ field.label }}
                                <UButton
                                    v-if="editingField !== field.key"
                                    :label="
                                        hasFieldValue(selectedMedia, field.key)
                                            ? 'Edit'
                                            : 'Tambah'
                                    "
                                    :icon="
                                        hasFieldValue(selectedMedia, field.key)
                                            ? 'i-lucide-pencil'
                                            : 'i-lucide-plus'
                                    "
                                    :aria-label="`Edit ${field.label}`"
                                    color="neutral"
                                    variant="ghost"
                                    size="xs"
                                    :disabled="isSavingField"
                                    @click="startFieldEdit(field.key)"
                                />
                            </dt>
                            <dd
                                v-if="editingField === field.key"
                                class="mt-1 space-y-2"
                            >
                                <template v-if="field.input === 'categories'">
                                    <USelectMenu
                                        v-model="categoryDraft"
                                        :items="categoryOptions"
                                        value-key="value"
                                        multiple
                                        :loading="isLoadingCategories"
                                        :disabled="isSavingField"
                                        :placeholder="field.placeholder"
                                        :search-input="{
                                            placeholder: 'Cari kategori...',
                                        }"
                                        :color="
                                            fieldMessage ? 'error' : undefined
                                        "
                                        class="w-full"
                                    />
                                    <p
                                        v-if="
                                            !isLoadingCategories &&
                                            categoryOptions.length === 0 &&
                                            !fieldMessage
                                        "
                                        class="text-xs text-muted"
                                    >
                                        Belum ada kategori media.
                                        <ULink
                                            :to="mediaCategoriesRoute().url"
                                            class="text-primary"
                                        >
                                            Buat di Media Categories
                                        </ULink>
                                    </p>
                                </template>
                                <UInput
                                    v-else-if="field.input === 'text'"
                                    v-model="fieldDraft"
                                    :maxlength="field.maxlength"
                                    autofocus
                                    :placeholder="field.placeholder"
                                    :disabled="isSavingField"
                                    :color="fieldMessage ? 'error' : undefined"
                                    class="w-full"
                                    @keydown.enter.prevent="saveField"
                                    @keydown.esc.stop="cancelFieldEdit"
                                />
                                <UTextarea
                                    v-else
                                    v-model="fieldDraft"
                                    :rows="field.key === 'caption' ? 3 : 2"
                                    :maxlength="field.maxlength"
                                    autoresize
                                    autofocus
                                    :placeholder="field.placeholder"
                                    :disabled="isSavingField"
                                    :color="fieldMessage ? 'error' : undefined"
                                    class="w-full"
                                    @keydown.ctrl.enter="saveField"
                                    @keydown.meta.enter="saveField"
                                    @keydown.esc.stop="cancelFieldEdit"
                                />
                                <p
                                    v-if="fieldMessage"
                                    class="text-xs text-error"
                                >
                                    {{ fieldMessage }}
                                </p>
                                <p
                                    v-else-if="field.hint"
                                    class="text-xs text-muted"
                                >
                                    {{ field.hint }}
                                </p>
                                <div class="flex justify-end gap-2">
                                    <UButton
                                        label="Batal"
                                        color="neutral"
                                        variant="outline"
                                        size="xs"
                                        :disabled="isSavingField"
                                        @click="cancelFieldEdit"
                                    />
                                    <UButton
                                        label="Simpan"
                                        icon="i-lucide-check"
                                        size="xs"
                                        :loading="isSavingField"
                                        @click="saveField"
                                    />
                                </div>
                            </dd>
                            <dd
                                v-else-if="
                                    (field.key === 'title' ||
                                        field.key === 'caption') &&
                                    selectedMedia[field.key]
                                "
                                class="whitespace-pre-line text-highlighted"
                            >
                                {{ selectedMedia[field.key] }}
                            </dd>
                            <dd
                                v-else-if="
                                    (field.key === 'categories' ||
                                        field.key === 'tags') &&
                                    selectedMedia[field.key]?.length
                                "
                                class="flex flex-wrap gap-1"
                            >
                                <UBadge
                                    v-for="term in selectedMedia[field.key]"
                                    :key="term.id"
                                    :color="
                                        field.key === 'categories'
                                            ? 'primary'
                                            : 'neutral'
                                    "
                                    variant="subtle"
                                    :label="term.name"
                                />
                            </dd>
                            <dd v-else class="text-dimmed">
                                Belum ada {{ field.label.toLowerCase() }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </template>

            <template #footer>
                <template v-if="selectedMedia">
                    <UButton
                        label="Hapus"
                        icon="i-lucide-trash"
                        color="error"
                        variant="ghost"
                        class="mr-auto"
                        @click="openDeleteModal"
                    />

                    <UButton
                        label="Salin URL"
                        icon="i-lucide-copy"
                        color="neutral"
                        variant="outline"
                        @click="copyUrl(selectedMedia)"
                    />

                    <UButton
                        label="Buka file"
                        icon="i-lucide-external-link"
                        :href="selectedMedia.url"
                        target="_blank"
                        rel="noopener"
                    />
                </template>
            </template>
        </UModal>

        <UModal
            v-model:open="isUploadOpen"
            title="Upload Media"
            :description="`Maksimal ${maxUploadFiles} file, masing-masing ${maxUploadSizeMb} MB. Gambar, video, PDF, dokumen Office, CSV, TXT, atau ZIP.`"
            :dismissible="!isUploading"
            :close="!isUploading"
            :ui="{ content: 'sm:max-w-2xl', footer: 'justify-end' }"
        >
            <template #body>
                <UAlert
                    v-if="uploadMessage"
                    color="error"
                    variant="soft"
                    icon="i-lucide-circle-alert"
                    title="Upload gagal"
                    :description="uploadMessage"
                    class="mb-4"
                />

                <UFileUpload
                    v-model="uploadFiles"
                    multiple
                    :accept="uploadAccept"
                    :disabled="isUploading"
                    icon="i-lucide-upload"
                    label="Tarik file ke sini"
                    description="atau klik untuk memilih file"
                    layout="list"
                    class="min-h-48 w-full"
                />

                <UProgress
                    v-if="isUploading"
                    v-model="uploadProgress"
                    status
                    class="mt-4"
                />
            </template>

            <template #footer>
                <UButton
                    label="Batal"
                    color="neutral"
                    variant="outline"
                    :disabled="isUploading"
                    @click="isUploadOpen = false"
                />

                <UButton
                    icon="i-lucide-upload"
                    :label="
                        uploadFiles.length > 0
                            ? `Upload ${uploadFiles.length} file`
                            : 'Upload'
                    "
                    :disabled="uploadFiles.length === 0"
                    :loading="isUploading"
                    @click="submitUpload"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteOpen"
            title="Hapus Media"
            :description="
                selectedMedia
                    ? `File &quot;${selectedMedia.original_name}&quot; akan dihapus dari penyimpanan dan tidak bisa dikembalikan. Halaman yang masih memakai URL-nya akan menampilkan file rusak.`
                    : undefined
            "
            :dismissible="!isDeleting"
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
                    @click="isDeleteOpen = false"
                />

                <UButton
                    label="Hapus"
                    icon="i-lucide-trash"
                    color="error"
                    :loading="isDeleting"
                    @click="deleteMedia"
                />
            </template>
        </UModal>
    </div>
</template>
