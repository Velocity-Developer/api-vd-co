<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';

type RouteItem = {
    methods: string[];
    uri: string;
    name: string | null;
    middleware: string[];
    action: string;
};

type TestResult = {
    status: number;
    headers: Record<string, string>;
    body: string;
    error?: string;
};

const props = defineProps<{
    routes: RouteItem[];
    groupedRoutes: Record<string, RouteItem[]>;
    routePrefixes: string[];
}>();

interface ExpandedState {
    [key: string]: boolean;
}

const expandedGroups = ref<ExpandedState>(
    Object.fromEntries(props.routePrefixes.map((p) => [p, true])),
);

const expandedRoutes = ref<ExpandedState>({});
const loadingRoute = ref<string | null>(null);
const testResults = ref<Record<string, TestResult | null>>({});
const requestBodies = ref<Record<string, string>>({});

function toggleGroup(prefix: string): void {
    expandedGroups.value[prefix] = !expandedGroups.value[prefix];
}

function toggleRoute(uri: string): void {
    if (expandedRoutes.value[uri]) {
        expandedRoutes.value[uri] = false;
        return;
    }
    expandedRoutes.value[uri] = true;
}

const methodColors: Record<string, string> = {
    GET: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
    POST: 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/30',
    PUT: 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
    PATCH: 'bg-violet-500/15 text-violet-600 dark:text-violet-400 border-violet-500/30',
    DELETE: 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/30',
};

function getMethodColor(method: string): string {
    return methodColors[method] || 'bg-neutral-500/15 text-neutral-600 dark:text-neutral-400 border-neutral-500/30';
}

function getDefaultBody(route: RouteItem): string {
    if (route.uri.includes('article-generator') || route.uri.includes('article-generator-by-agent')) {
        return JSON.stringify({ prompt: '' }, null, 2);
    }

    return '{}';
}

async function sendRequest(route: RouteItem): Promise<void> {
    const method = route.methods[0] || 'GET';
    const isJsonBody = ['POST', 'PUT', 'PATCH'].includes(method);
    const key = route.uri;

    loadingRoute.value = key;
    testResults.value[key] = null;

    try {
        let body: unknown = {};

        if (isJsonBody) {
            const raw = requestBodies.value[key] || getDefaultBody(route);
            try {
                body = JSON.parse(raw);
            } catch {
                body = {};
            }
        }

        const response = await axios({
            method: method.toLowerCase(),
            url: `/${route.uri}`,
            data: isJsonBody ? body : undefined,
            headers: {
                Accept: 'application/json',
            },
            validateStatus: () => true,
        });

        testResults.value[key] = {
            status: response.status,
            headers: response.headers as Record<string, string>,
            body: JSON.stringify(response.data, null, 2),
        };
    } catch (error) {
        if (axios.isAxiosError(error) && error.response) {
            testResults.value[key] = {
                status: error.response.status,
                headers: error.response.headers as Record<string, string>,
                body: JSON.stringify(error.response.data, null, 2),
            };
        } else {
            testResults.value[key] = {
                status: 0,
                headers: {},
                body: '',
                error: error instanceof Error ? error.message : 'Unknown error',
            };
        }
    } finally {
        loadingRoute.value = null;
    }
}

function getStatusColor(status: number): string {
    if (status >= 200 && status < 300) return 'text-emerald-600 dark:text-emerald-400';
    if (status >= 300 && status < 400) return 'text-blue-600 dark:text-blue-400';
    if (status >= 400 && status < 500) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
}
</script>

<template>
    <Head title="API Docs" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-col gap-3 rounded-xl border border-default bg-default p-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    API Documentation
                </h1>
                <p class="text-sm text-muted">
                    Daftar seluruh route API yang tersedia. Klik "Send" untuk menguji endpoint.
                </p>
            </div>
            <div class="flex items-center gap-2 text-sm text-muted">
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-emerald-500" />
                    {{ routes.filter((r) => r.methods[0] === 'GET').length }} GET
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-blue-500" />
                    {{ routes.filter((r) => r.methods[0] === 'POST').length }} POST
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-amber-500" />
                    {{ routes.filter((r) => ['PUT', 'PATCH'].includes(r.methods[0])).length }} PUT/PATCH
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-red-500" />
                    {{ routes.filter((r) => r.methods[0] === 'DELETE').length }} DELETE
                </span>
            </div>
        </div>

        <div class="space-y-3">
            <div
                v-for="prefix in routePrefixes"
                :key="prefix"
                class="rounded-xl border border-default bg-default"
            >
                <button
                    class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-muted/20"
                    @click="toggleGroup(prefix)"
                >
                    <h2 class="text-lg font-semibold text-highlighted">
                        {{ prefix }}
                        <span class="ml-2 text-sm font-normal text-muted">
                            ({{ groupedRoutes[prefix].length }} routes)
                        </span>
                    </h2>
                    <span
                        class="text-muted transition-transform duration-200"
                        :class="{ 'rotate-180': expandedGroups[prefix] }"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </span>
                </button>

                <div v-if="expandedGroups[prefix]" class="border-t border-default">
                    <div
                        v-for="(route, index) in groupedRoutes[prefix]"
                        :key="route.uri"
                        class="border-b border-default last:border-b-0"
                        :class="{ 'bg-muted/10': expandedRoutes[route.uri] }"
                    >
                        <div class="flex items-center gap-3 px-4 py-2.5">
                            <span
                                class="inline-flex shrink-0 items-center rounded-md border px-2 py-0.5 text-xs font-semibold uppercase leading-tight"
                                :class="getMethodColor(route.methods[0] || 'GET')"
                            >
                                {{ route.methods[0] || 'GET' }}
                            </span>

                            <code
                                class="min-w-0 flex-1 truncate text-sm font-mono text-highlighted"
                            >
                                /{{ route.uri }}
                            </code>

                            <span
                                v-if="route.name"
                                class="hidden shrink-0 text-xs text-muted md:inline"
                            >
                                {{ route.name }}
                            </span>

                            <div class="flex shrink-0 items-center gap-1.5">
                                <UButton
                                    :label="expandedRoutes[route.uri] ? 'Hide' : 'Send'"
                                    :color="expandedRoutes[route.uri] ? 'neutral' : 'primary'"
                                    size="xs"
                                    :variant="expandedRoutes[route.uri] ? 'outline' : 'solid'"
                                    :loading="loadingRoute === route.uri"
                                    @click="expandedRoutes[route.uri] ? (expandedRoutes[route.uri] = false) : sendRequest(route)"
                                />
                            </div>
                        </div>

                        <div
                            v-if="expandedRoutes[route.uri]"
                            class="border-t border-default px-4 py-3"
                        >
                            <div class="mb-3 flex flex-wrap gap-4 text-xs text-muted">
                                <div>
                                    <span class="font-medium text-highlighted">Action:</span>
                                    {{ route.action }}
                                </div>
                                <div>
                                    <span class="font-medium text-highlighted">Middleware:</span>
                                    {{ route.middleware.join(', ') || 'none' }}
                                </div>
                            </div>

                            <div
                                v-if="['POST', 'PUT', 'PATCH'].includes(route.methods[0] || '')"
                                class="mb-3"
                            >
                                <label class="mb-1 block text-xs font-medium text-highlighted">
                                    Request Body (JSON):
                                </label>
                                <UTextarea
                                    v-model="requestBodies[route.uri]"
                                    :placeholder="getDefaultBody(route)"
                                    rows="4"
                                    class="w-full"
                                    :ui="{
                                        base: 'font-mono text-xs',
                                    }"
                                />
                            </div>

                            <UButton
                                label="Send Request"
                                size="sm"
                                color="primary"
                                :loading="loadingRoute === route.uri"
                                @click="sendRequest(route)"
                            />

                            <div v-if="testResults[route.uri]" class="mt-3 space-y-2">
                                <div
                                    class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                                    :class="{
                                        'border-emerald-500/30 bg-emerald-500/5': testResults[route.uri]?.status && testResults[route.uri]!.status >= 200 && testResults[route.uri]!.status < 300,
                                        'border-red-500/30 bg-red-500/5': testResults[route.uri]?.status && testResults[route.uri]!.status >= 400,
                                    }"
                                >
                                    <span class="font-medium">Status:</span>
                                    <span
                                        class="font-semibold"
                                        :class="getStatusColor(testResults[route.uri]?.status ?? 0)"
                                    >
                                        {{ testResults[route.uri]?.status }}
                                    </span>
                                    <span v-if="testResults[route.uri]?.error" class="ml-2 text-red-500">
                                        {{ testResults[route.uri]?.error }}
                                    </span>
                                </div>

                                <div
                                    v-if="testResults[route.uri]?.body"
                                    class="rounded-lg border border-default"
                                >
                                    <div class="border-b border-default px-3 py-1.5 text-xs font-medium text-muted">
                                        Response Body:
                                    </div>
                                    <pre class="max-h-96 overflow-auto p-3 text-xs font-mono text-highlighted"><code>{{ testResults[route.uri]?.body }}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
