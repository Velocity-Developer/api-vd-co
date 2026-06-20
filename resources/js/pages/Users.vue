<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { users as usersRoute } from '@/routes';

type UserItem = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    posts_count?: number;
    licenses_count?: number;
    created_at: string;
    updated_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

type UsersResponse = {
    data: UserItem[];
    meta: PaginationMeta;
};

type ResourceResponse<T> = {
    data: T;
};

type UserFormState = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
};

type UserPayload = {
    name: string;
    email: string;
    password?: string;
    password_confirmation?: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: usersRoute(),
            },
        ],
    },
});

const columns: TableColumn<UserItem>[] = [
    {
        accessorKey: 'name',
        header: 'User',
    },
    {
        accessorKey: 'email',
        header: 'Email',
    },
    {
        accessorKey: 'email_verified_at',
        header: 'Verified',
    },
    {
        accessorKey: 'posts_count',
        header: 'Posts',
    },
    {
        accessorKey: 'licenses_count',
        header: 'Licenses',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
    {
        id: 'actions',
    },
];

const state = reactive<UserFormState>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const userData = ref<UserItem[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingUserId = ref<number | null>(null);
const deletingUser = ref<UserItem | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingUserId.value !== null);
const modalTitle = computed(() => (isEditing.value ? 'Edit User' : 'Add User'));
const modalDescription = computed(() =>
    isEditing.value
        ? 'Perbarui data user yang sudah ada.'
        : 'Tambahkan user baru untuk mengakses admin panel.',
);
const submitLabel = computed(() =>
    isEditing.value ? 'Update User' : 'Create User',
);
const passwordHint = computed(() =>
    isEditing.value
        ? 'Kosongkan jika password tidak ingin diubah.'
        : 'Gunakan password minimal 8 karakter.',
);
const deleteModalDescription = computed(() => {
    if (!deletingUser.value) {
        return 'User ini akan dihapus secara permanen.';
    }

    return `User "${deletingUser.value.name}" akan dihapus secara permanen.`;
});

const filteredUsers = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return userData.value;
    }

    return userData.value.filter((user) => {
        const searchableContent = [
            user.name,
            user.email,
            user.email_verified_at ? 'verified' : 'unverified',
            String(user.posts_count ?? 0),
            String(user.licenses_count ?? 0),
        ]
            .join(' ')
            .toLowerCase();

        return searchableContent.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 users';
    }

    return `${meta.value.from}-${meta.value.to} of ${meta.value.total} users`;
});

const fetchUsers = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<UsersResponse>('/ajax/users', {
            params: { page },
        });

        userData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data user gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value));
};

const validate = (formState: Partial<UserFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.name?.trim()) {
        errors.push({ name: 'name', message: 'Name wajib diisi.' });
    }

    if (!formState.email?.trim()) {
        errors.push({ name: 'email', message: 'Email wajib diisi.' });
    }

    if (!isEditing.value && !formState.password?.trim()) {
        errors.push({ name: 'password', message: 'Password wajib diisi.' });
    }

    if ((formState.password ?? '') !== (formState.password_confirmation ?? '')) {
        errors.push({
            name: 'password_confirmation',
            message: 'Konfirmasi password harus sama.',
        });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const resetForm = (): void => {
    state.name = '';
    state.email = '';
    state.password = '';
    state.password_confirmation = '';
    editingUserId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (user: UserItem): void => {
    state.name = user.name;
    state.email = user.email;
    state.password = '';
    state.password_confirmation = '';
    editingUserId.value = user.id;
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

const buildPayload = (): UserPayload => {
    const payload: UserPayload = {
        name: state.name,
        email: state.email,
    };

    if (state.password !== '' || !isEditing.value) {
        payload.password = state.password;
        payload.password_confirmation = state.password_confirmation;
    }

    return payload;
};

const storeUser = async (): Promise<UserItem> => {
    const response = await axios.post<ResourceResponse<UserItem>>(
        '/ajax/users',
        buildPayload(),
    );

    return response.data.data;
};

const updateUser = async (id: number): Promise<UserItem> => {
    const response = await axios.patch<ResourceResponse<UserItem>>(
        `/ajax/users/${id}`,
        buildPayload(),
    );

    return response.data.data;
};

const openDeleteModal = (user: UserItem): void => {
    deletingUser.value = user;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingUser.value = null;
    deleteMessage.value = null;
};

const deleteUser = async (): Promise<void> => {
    if (!deletingUser.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`/ajax/users/${deletingUser.value.id}`);

        const targetPage =
            userData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        isDeleteModalOpen.value = false;
        deletingUser.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchUsers(targetPage);
        }
    } catch (error) {
        if (error instanceof AxiosError && error.response?.status === 422) {
            deleteMessage.value =
                typeof error.response.data.message === 'string'
                    ? error.response.data.message
                    : 'User gagal dihapus.';
        } else {
            deleteMessage.value = 'User gagal dihapus.';
        }
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'User gagal disimpan.';

        return;
    }

    const response = error.response.data as ValidationResponse;
    const errors = response.errors ?? {};

    serverErrors.value = Object.fromEntries(
        Object.entries(errors).map(([name, messages]) => [
            name,
            messages[0] ?? 'Invalid value.',
        ]),
    );

    if (response.message && Object.keys(serverErrors.value).length === 0) {
        formMessage.value = response.message;
    }
};

const submitUser = async (
    _event: FormSubmitEvent<UserFormState>,
): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        if (editingUserId.value) {
            await updateUser(editingUserId.value);
        } else {
            await storeUser();
        }

        isModalOpen.value = false;
        resetForm();
        await fetchUsers(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchUsers(page);
    }
});

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) {
        resetForm();
    }
});

watch(isDeleteModalOpen, (open) => {
    if (!open && !isDeleting.value) {
        deletingUser.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchUsers();
});
</script>

<template>
    <Head title="Users" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">Users</h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search users..."
                    :disabled="isLoading"
                    class="w-full sm:w-64"
                />

                <UButton
                    icon="i-lucide-plus"
                    label="Add"
                    :disabled="isLoading"
                    @click="openCreateModal"
                />

                <UButton
                    icon="i-lucide-refresh-cw"
                    color="neutral"
                    variant="outline"
                    :loading="isLoading"
                    aria-label="Refresh users"
                    @click="fetchUsers(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat user"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchUsers(currentPage),
                },
            ]"
        />

        <div class="overflow-hidden rounded-lg border border-default bg-default">
            <UTable
                :data="filteredUsers"
                :columns="columns"
                :loading="isLoading"
                sticky
                :ui="{
                    thead: 'bg-primary-700',
                    th: 'text-white',
                }"
            >
                <template #name-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.name"
                            icon="i-lucide-user-round"
                            size="lg"
                        />

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.name }}
                            </p>
                            <p class="text-xs text-muted">
                                ID {{ row.original.id }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #email-cell="{ row }">
                    <p class="truncate text-sm text-highlighted">
                        {{ row.original.email }}
                    </p>
                </template>

                <template #email_verified_at-cell="{ row }">
                    <UBadge
                        :color="
                            row.original.email_verified_at
                                ? 'success'
                                : 'neutral'
                        "
                        variant="subtle"
                        :label="
                            row.original.email_verified_at
                                ? 'Verified'
                                : 'Unverified'
                        "
                    />
                </template>

                <template #posts_count-cell="{ row }">
                    <UBadge
                        color="primary"
                        variant="subtle"
                        :label="String(row.original.posts_count ?? 0)"
                    />
                </template>

                <template #licenses_count-cell="{ row }">
                    <UBadge
                        color="neutral"
                        variant="subtle"
                        :label="String(row.original.licenses_count ?? 0)"
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
                            aria-label="Edit user"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Delete user"
                            :disabled="isLoading || isDeleting"
                            @click="openDeleteModal(row.original)"
                        />
                    </div>
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon name="i-lucide-inbox" class="size-8 text-muted" />
                        <p class="font-medium text-highlighted">
                            Tidak ada user
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
                    id="user-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitUser"
                >
                    <UFormField
                        name="name"
                        label="Name"
                        required
                        :error="fieldError('name')"
                    >
                        <UInput
                            v-model="state.name"
                            placeholder="Full name"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="email"
                        label="Email"
                        required
                        :error="fieldError('email')"
                    >
                        <UInput
                            v-model="state.email"
                            type="email"
                            placeholder="user@example.com"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <UFormField
                            name="password"
                            label="Password"
                            :required="!isEditing"
                            :hint="passwordHint"
                            :error="fieldError('password')"
                        >
                            <UInput
                                v-model="state.password"
                                type="password"
                                placeholder="Password"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            name="password_confirmation"
                            label="Confirm Password"
                            :required="!isEditing"
                            :error="fieldError('password_confirmation')"
                        >
                            <UInput
                                v-model="state.password_confirmation"
                                type="password"
                                placeholder="Repeat password"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                </UForm>
            </template>

            <template #footer>
                <UButton
                    label="Cancel"
                    color="neutral"
                    variant="outline"
                    :disabled="isSaving"
                    @click="closeModal"
                />

                <UButton
                    type="submit"
                    form="user-form"
                    icon="i-lucide-save"
                    :label="submitLabel"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Delete User"
            :description="deleteModalDescription"
            :ui="{ footer: 'justify-end' }"
        >
            <template #body>
                <UAlert
                    v-if="deleteMessage"
                    color="error"
                    variant="soft"
                    icon="i-lucide-circle-alert"
                    title="Ada masalah"
                    :description="deleteMessage"
                />
            </template>

            <template #footer>
                <UButton
                    label="Cancel"
                    color="neutral"
                    variant="outline"
                    :disabled="isDeleting"
                    @click="closeDeleteModal"
                />

                <UButton
                    label="Delete"
                    icon="i-lucide-trash"
                    color="error"
                    :loading="isDeleting"
                    @click="deleteUser"
                />
            </template>
        </UModal>
    </div>
</template>
