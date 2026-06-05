<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { TableColumn } from '@nuxt/ui';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';
import { index as licensesIndex } from '@/routes/licenses';
import {
    destroy as requestLogsDestroy,
    index as requestLogsIndex,
    store as requestLogsStore,
    update as requestLogsUpdate,
} from '@/routes/request-logs';
import { index as websitesIndex } from '@/routes/websites';
import { requestlogs } from '@/routes';

type WebsiteOption = {
    id: number;
    domain: string;
    status: string;
};

type LicenseOption = {
    id: number;
    code: string;
    is_active: boolean;
};

type RequestLog = {
    id: number;
    route: string;
    method: string;
    request: Record<string, unknown> | null;
    status: number;
    website_id: number;
    license_id: number;
    created_at: string;
    updated_at: string;
    website?: WebsiteOption;
    license?: LicenseOption;
};

type PaginatedResponse<T> = {
    data: T[];
};

type RequestLogForm = {
    route: string;
    method: string;
    request: string;
    status: number | null;
    website_id: number | null;
    license_id: number | null;
};

const methodItems = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

const logs = ref<RequestLog[]>([]);
const websites = ref<WebsiteOption[]>([]);
const licenses = ref<LicenseOption[]>([]);
const search = ref('');
const isLoading = ref(false);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingLog = ref<RequestLog | null>(null);
const deletingLog = ref<RequestLog | null>(null);
const formErrors = ref<Record<string, string>>({});

const form = reactive<RequestLogForm>({
    route: '',
    method: 'GET',
    request: '',
    status: 200,
    website_id: null,
    license_id: null,
});

const columns: TableColumn<RequestLog>[] = [
    {
        accessorKey: 'route',
        header: 'Route',
    },
    {
        accessorKey: 'method',
        header: 'Method',
    },
    {
        accessorKey: 'status',
        header: 'Status',
    },
    {
        accessorKey: 'website',
        header: 'Website',
    },
    {
        accessorKey: 'license',
        header: 'License',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
    {
        id: 'actions',
    },
];

const websiteItems = computed(() =>
    websites.value.map((website) => ({
        label: website.domain,
        value: website.id,
    })),
);

const licenseItems = computed(() =>
    licenses.value.map((license) => ({
        label: license.code,
        value: license.id,
    })),
);

const modalTitle = computed(() =>
    editingLog.value ? 'Edit Request Log' : 'Add Request Log',
);

const filteredLogs = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    if (!keyword) {
        return logs.value;
    }

    return logs.value.filter((log) =>
        [
            log.route,
            log.method,
            String(log.status),
            log.website?.domain,
            log.license?.code,
        ]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(keyword)),
    );
});

const resetForm = (): void => {
    editingLog.value = null;
    form.route = '';
    form.method = 'GET';
    form.request = '';
    form.status = 200;
    form.website_id = null;
    form.license_id = null;
    formErrors.value = {};
};

const loadLogs = async (): Promise<void> => {
    isLoading.value = true;

    try {
        const response = await axios.get<PaginatedResponse<RequestLog>>(
            requestLogsIndex.url(),
        );

        logs.value = response.data.data;
    } finally {
        isLoading.value = false;
    }
};

const loadFormOptions = async (): Promise<void> => {
    const [websitesResponse, licensesResponse] = await Promise.all([
        axios.get<PaginatedResponse<WebsiteOption>>(websitesIndex.url()),
        axios.get<PaginatedResponse<LicenseOption>>(licensesIndex.url()),
    ]);

    websites.value = websitesResponse.data.data;
    licenses.value = licensesResponse.data.data;
};

const openAddModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (log: RequestLog): void => {
    editingLog.value = log;
    form.route = log.route;
    form.method = log.method;
    form.request = log.request ? JSON.stringify(log.request, null, 2) : '';
    form.status = log.status;
    form.website_id = log.website_id;
    form.license_id = log.license_id;
    formErrors.value = {};
    isModalOpen.value = true;
};

const openDeleteModal = (log: RequestLog): void => {
    deletingLog.value = log;
    isDeleteModalOpen.value = true;
};

const buildPayload = (): Record<string, unknown> | null => {
    formErrors.value = {};

    if (!form.route.trim()) {
        formErrors.value.route = 'Route is required.';
    }

    if (!form.method) {
        formErrors.value.method = 'Method is required.';
    }

    if (!form.status) {
        formErrors.value.status = 'Status is required.';
    }

    if (!form.website_id) {
        formErrors.value.website_id = 'Website is required.';
    }

    if (!form.license_id) {
        formErrors.value.license_id = 'License is required.';
    }

    let requestBody: Record<string, unknown> | null = null;

    if (form.request.trim()) {
        try {
            const parsed = JSON.parse(form.request) as unknown;

            if (
                typeof parsed !== 'object' ||
                parsed === null ||
                Array.isArray(parsed)
            ) {
                formErrors.value.request = 'Request must be a JSON object.';
            } else {
                requestBody = parsed as Record<string, unknown>;
            }
        } catch {
            formErrors.value.request = 'Request must be valid JSON.';
        }
    }

    if (Object.keys(formErrors.value).length > 0) {
        return null;
    }

    return {
        route: form.route.trim(),
        method: form.method,
        request: requestBody,
        status: form.status,
        website_id: form.website_id,
        license_id: form.license_id,
    };
};

const saveLog = async (): Promise<void> => {
    const payload = buildPayload();

    if (!payload) {
        return;
    }

    isSaving.value = true;

    try {
        if (editingLog.value) {
            await axios.patch(requestLogsUpdate.url(editingLog.value.id), payload);
        } else {
            await axios.post(requestLogsStore.url(), payload);
        }

        await loadLogs();
        isModalOpen.value = false;
        resetForm();
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const errors = error.response.data.errors as Record<string, string[]>;

            formErrors.value = Object.fromEntries(
                Object.entries(errors).map(([field, messages]) => [
                    field,
                    messages[0],
                ]),
            );
        }
    } finally {
        isSaving.value = false;
    }
};

const deleteLog = async (): Promise<void> => {
    if (!deletingLog.value) {
        return;
    }

    isDeleting.value = true;

    try {
        await axios.delete(requestLogsDestroy.url(deletingLog.value.id));
        await loadLogs();
        isDeleteModalOpen.value = false;
        deletingLog.value = null;
    } finally {
        isDeleting.value = false;
    }
};

const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

const statusColor = (status: number): 'success' | 'warning' | 'error' | 'info' =>
    status >= 500
        ? 'error'
        : status >= 400
          ? 'warning'
          : status >= 300
            ? 'info'
            : 'success';

void Promise.all([loadLogs(), loadFormOptions()]);
</script>


<template>
    <Head title="Request Logs" />

    <div class="space-y-6 p-4 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-default">Request Logs</h1>
                <p class="mt-1 text-sm text-muted">
                    Monitor and manage captured API request activity.
                </p>
            </div>

            <UButton
                icon="i-lucide-plus"
                label="Add Request Log"
                @click="openAddModal"
            />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <UInput
                v-model="search"
                icon="i-lucide-search"
                placeholder="Search logs..."
                class="w-full sm:max-w-sm"
            />

            <UButton
                color="neutral"
                variant="outline"
                icon="i-lucide-refresh-cw"
                label="Refresh"
                :loading="isLoading"
                @click="loadLogs"
            />
        </div>

        <div class="overflow-hidden rounded-lg border border-muted bg-default">
            <UTable :data="filteredLogs" :columns="columns" :loading="isLoading">
                <template #method-cell="{ row }">
                    <UBadge
                        :label="row.original.method"
                        color="neutral"
                        variant="subtle"
                    />
                </template>

                <template #status-cell="{ row }">
                    <UBadge
                        :label="String(row.original.status)"
                        :color="statusColor(row.original.status)"
                        variant="subtle"
                    />
                </template>

                <template #website-cell="{ row }">
                    <span class="text-sm text-default">
                        {{ row.original.website?.domain ?? `#${row.original.website_id}` }}
                    </span>
                </template>

                <template #license-cell="{ row }">
                    <span class="text-sm text-default">
                        {{ row.original.license?.code ?? `#${row.original.license_id}` }}
                    </span>
                </template>

                <template #created_at-cell="{ row }">
                    <span class="text-sm text-muted">
                        {{ formatDate(row.original.created_at) }}
                    </span>
                </template>

                <template #actions-cell="{ row }">
                    <div class="flex justify-end gap-1">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-pencil"
                            aria-label="Edit request log"
                            @click="openEditModal(row.original)"
                        />
                        <UButton
                            color="error"
                            variant="ghost"
                            icon="i-lucide-trash"
                            aria-label="Delete request log"
                            @click="openDeleteModal(row.original)"
                        />
                    </div>
                </template>

                <template #empty>
                    <div class="py-10 text-center text-sm text-muted">
                        No request logs found.
                    </div>
                </template>
            </UTable>
        </div>
    </div>

    <UModal
        v-model:open="isModalOpen"
        :title="modalTitle"
        description="Fill in the request log details below."
        :ui="{ footer: 'justify-end' }"
    >
        <template #body>
            <UForm
                id="request-log-form"
                :state="form"
                class="space-y-4"
                @submit.prevent="saveLog"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <UFormField
                        label="Route"
                        name="route"
                        :error="formErrors.route"
                    >
                        <UInput
                            v-model="form.route"
                            placeholder="/api/validate-license"
                        />
                    </UFormField>

                    <UFormField
                        label="Method"
                        name="method"
                        :error="formErrors.method"
                    >
                        <USelect v-model="form.method" :items="methodItems" />
                    </UFormField>

                    <UFormField
                        label="Status"
                        name="status"
                        :error="formErrors.status"
                    >
                        <UInput
                            v-model.number="form.status"
                            type="number"
                            min="100"
                            max="599"
                            placeholder="200"
                        />
                    </UFormField>

                    <UFormField
                        label="Website"
                        name="website_id"
                        :error="formErrors.website_id"
                    >
                        <USelect
                            v-model="form.website_id"
                            :items="websiteItems"
                            placeholder="Select website"
                        />
                    </UFormField>

                    <UFormField
                        label="License"
                        name="license_id"
                        :error="formErrors.license_id"
                        class="sm:col-span-2"
                    >
                        <USelect
                            v-model="form.license_id"
                            :items="licenseItems"
                            placeholder="Select license"
                        />
                    </UFormField>
                </div>

                <UFormField
                    label="Request JSON"
                    name="request"
                    :error="formErrors.request"
                >
                    <UTextarea
                        v-model="form.request"
                        :rows="7"
                        placeholder='{"license_key": "abc-123"}'
                    />
                </UFormField>
            </UForm>
        </template>

        <template #footer="{ close }">
            <UButton
                label="Cancel"
                color="neutral"
                variant="outline"
                @click="close"
            />
            <UButton
                type="submit"
                form="request-log-form"
                label="Save"
                icon="i-lucide-save"
                :loading="isSaving"
            />
        </template>
    </UModal>

    <UModal
        v-model:open="isDeleteModalOpen"
        title="Delete Request Log"
        description="This action cannot be undone."
        :ui="{ footer: 'justify-end' }"
    >
        <template #body>
            <p class="text-sm text-muted">
                Delete request log
                <span class="font-medium text-default">
                    {{ deletingLog?.method }} {{ deletingLog?.route }}
                </span>
                ?
            </p>
        </template>

        <template #footer="{ close }">
            <UButton
                label="Cancel"
                color="neutral"
                variant="outline"
                @click="close"
            />
            <UButton
                label="Delete"
                color="error"
                icon="i-lucide-trash"
                :loading="isDeleting"
                @click="deleteLog"
            />
        </template>
    </UModal>
</template>
