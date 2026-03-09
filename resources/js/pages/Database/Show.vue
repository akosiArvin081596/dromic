<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    table: string;
    columns: string[];
    rows: {
        data: Record<string, unknown>[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        search: string;
        sort: string;
        dir: string;
        per_page: number;
    };
    protected: boolean;
}>();

const search = ref(props.filters.search);
const perPage = ref(props.filters.per_page);
const showDeleteConfirm = ref(false);
const showTruncateConfirm = ref(false);
const rowToDelete = ref<Record<string, unknown> | null>(null);

let debounceTimer: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(
            `/database/${props.table}`,
            { search: search.value || undefined, sort: props.filters.sort, dir: props.filters.dir, per_page: perPage.value },
            { preserveState: true, replace: true },
        );
    }, 300);
});

watch(perPage, () => {
    router.get(
        `/database/${props.table}`,
        { search: search.value || undefined, sort: props.filters.sort, dir: props.filters.dir, per_page: perPage.value },
        { preserveState: true, replace: true },
    );
});

function sortBy(column: string) {
    const dir = props.filters.sort === column && props.filters.dir === 'asc' ? 'desc' : 'asc';
    router.get(
        `/database/${props.table}`,
        { search: search.value || undefined, sort: column, dir, per_page: perPage.value },
        { preserveState: true, replace: true },
    );
}

function confirmDelete(row: Record<string, unknown>) {
    rowToDelete.value = row;
    showDeleteConfirm.value = true;
}

function deleteRow() {
    if (!rowToDelete.value) return;
    const primaryColumn = props.columns[0];
    const value = rowToDelete.value[primaryColumn];
    router.delete(`/database/${props.table}/row`, {
        data: { column: primaryColumn, value },
        preserveState: false,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            rowToDelete.value = null;
        },
    });
}

function truncateTable() {
    router.delete(`/database/${props.table}/truncate`, {
        onSuccess: () => {
            showTruncateConfirm.value = false;
        },
    });
}

function formatValue(value: unknown): string {
    if (value === null) return 'NULL';
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    const str = String(value);
    if (str.length > 100) return str.slice(0, 100) + '...';
    return str;
}

function valueClass(value: unknown): string {
    if (value === null) return 'italic text-slate-400 dark:text-slate-500';
    return 'text-slate-900 dark:text-slate-100';
}
</script>

<template>
    <AppLayout>
        <Head :title="`${table} - Database Manager`" />
        <div class="mx-auto max-w-[95rem] px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <Link href="/database" class="text-sm text-slate-500 transition-colors hover:text-slate-700 dark:text-slate-400">
                            Database
                        </Link>
                        <span class="text-slate-300 dark:text-slate-600">/</span>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ table }}</h1>
                        <span
                            v-if="props.protected"
                            class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/30"
                        >
                            protected
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ rows.total.toLocaleString() }} rows &middot; {{ columns.length }} columns
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        href="/database"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        Back to Tables
                    </Link>
                    <button
                        v-if="!props.protected && rows.total > 0"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-red-700"
                        @click="showTruncateConfirm = true"
                    >
                        Truncate Table
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <svg
                                    class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                                    />
                                </svg>
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search all columns..."
                                    class="block w-full rounded-lg border border-slate-300 py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:w-72 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                            <select
                                v-model="perPage"
                                class="block rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option :value="10">10 / page</option>
                                <option :value="25">25 / page</option>
                                <option :value="50">50 / page</option>
                                <option :value="100">100 / page</option>
                            </select>
                        </div>
                        <p v-if="rows.from && rows.to" class="text-xs text-slate-500 dark:text-slate-400">
                            Showing {{ rows.from }}-{{ rows.to }} of {{ rows.total.toLocaleString() }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50/50 dark:bg-slate-900/50">
                            <tr>
                                <th
                                    v-for="column in columns"
                                    :key="column"
                                    class="cursor-pointer px-4 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase select-none hover:text-slate-700 dark:hover:text-slate-300"
                                    @click="sortBy(column)"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        {{ column }}
                                        <template v-if="filters.sort === column">
                                            <svg v-if="filters.dir === 'asc'" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            <svg v-else class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </template>
                                    </span>
                                </th>
                                <th
                                    v-if="!props.protected"
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wider text-slate-500 uppercase"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-800">
                            <tr v-for="(row, idx) in rows.data" :key="idx" class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-700/50">
                                <td
                                    v-for="column in columns"
                                    :key="column"
                                    class="max-w-xs truncate px-4 py-3 text-sm whitespace-nowrap"
                                    :class="valueClass(row[column])"
                                    :title="String(row[column] ?? 'NULL')"
                                >
                                    {{ formatValue(row[column]) }}
                                </td>
                                <td v-if="!props.protected" class="px-4 py-3 text-right text-sm whitespace-nowrap">
                                    <button class="text-red-600 transition-colors hover:text-red-800 dark:text-red-400" @click="confirmDelete(row)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="rows.data.length === 0">
                                <td :colspan="columns.length + (props.protected ? 0 : 1)" class="px-6 py-12 text-center text-sm text-slate-400">
                                    {{ search ? 'No rows match your search' : 'Table is empty' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="rows.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-6 py-3 dark:border-slate-700">
                    <p class="text-xs text-slate-500">Page {{ rows.current_page }} of {{ rows.last_page }}</p>
                    <div class="flex space-x-1">
                        <template v-for="link in rows.links" :key="link.label">
                            <a
                                v-if="link.url"
                                :href="link.url"
                                class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                                :class="
                                    link.active
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'bg-white text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300'
                                "
                                @click.prevent="router.get(link.url!, {}, { preserveState: true })"
                            >
                                <span v-html="link.label" />
                            </a>
                            <span v-else class="rounded-lg px-3 py-1.5 text-sm text-slate-300">
                                <span v-html="link.label" />
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation -->
        <Teleport to="body">
            <div
                v-if="showDeleteConfirm"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="showDeleteConfirm = false"
            >
                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Delete Row</h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Are you sure you want to delete this row? This action cannot be undone.
                    </p>
                    <div v-if="rowToDelete" class="mt-3 max-h-40 overflow-y-auto rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                        <div v-for="column in columns.slice(0, 5)" :key="column" class="text-xs">
                            <span class="font-medium text-slate-600 dark:text-slate-400">{{ column }}:</span>
                            <span class="ml-1 text-slate-900 dark:text-slate-100">{{ formatValue(rowToDelete[column]) }}</span>
                        </div>
                        <div v-if="columns.length > 5" class="mt-1 text-xs text-slate-400">...and {{ columns.length - 5 }} more columns</div>
                    </div>
                    <div class="mt-4 flex justify-end gap-3">
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                            @click="showDeleteConfirm = false"
                        >
                            Cancel
                        </button>
                        <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" @click="deleteRow">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Truncate Confirmation -->
        <Teleport to="body">
            <div
                v-if="showTruncateConfirm"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="showTruncateConfirm = false"
            >
                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Truncate Table</h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Are you sure you want to truncate <strong class="text-slate-900 dark:text-slate-100">{{ table }}</strong
                        >? This will permanently delete all <strong>{{ rows.total.toLocaleString() }}</strong> rows. This action cannot be undone.
                    </p>
                    <div class="mt-4 flex justify-end gap-3">
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                            @click="showTruncateConfirm = false"
                        >
                            Cancel
                        </button>
                        <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" @click="truncateTable">
                            Truncate
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
