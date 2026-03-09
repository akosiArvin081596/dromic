<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type TableInfo = {
    name: string;
    rows: number;
    columns: number;
    protected: boolean;
};

const props = defineProps<{
    tables: TableInfo[];
    driver: string;
    database: string;
}>();

const search = ref('');
const showTruncateConfirm = ref(false);
const tableToTruncate = ref<TableInfo | null>(null);

const filteredTables = computed(() => {
    if (!search.value) return props.tables;
    const q = search.value.toLowerCase();
    return props.tables.filter((t) => t.name.toLowerCase().includes(q));
});

const totalRows = computed(() => props.tables.reduce((sum, t) => sum + t.rows, 0));

function confirmTruncate(table: TableInfo) {
    tableToTruncate.value = table;
    showTruncateConfirm.value = true;
}

function truncateTable() {
    if (!tableToTruncate.value) return;
    router.delete(`/database/${tableToTruncate.value.name}/truncate`, {
        onSuccess: () => {
            showTruncateConfirm.value = false;
            tableToTruncate.value = null;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Database Manager" />
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Database Manager</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Browse and manage database tables</p>
            </div>

            <!-- Stats -->
            <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Tables</span>
                    <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ tables.length }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total Rows</span>
                    <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ totalRows.toLocaleString() }}</div>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Driver</span>
                    <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ driver }}</div>
                    <p class="mt-1 truncate text-xs text-slate-400" :title="database">{{ database }}</p>
                </div>
            </div>

            <!-- Table Card -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-sm font-semibold tracking-wide text-slate-900 uppercase dark:text-slate-100">All Tables</h2>
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
                                placeholder="Filter tables..."
                                class="block w-full rounded-lg border border-slate-300 py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:w-64 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            />
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50/50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Table Name</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold tracking-wider text-slate-500 uppercase">Columns</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold tracking-wider text-slate-500 uppercase">Rows</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold tracking-wider text-slate-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-800">
                            <tr
                                v-for="table in filteredTables"
                                :key="table.name"
                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-700/50"
                            >
                                <td class="px-6 py-4 text-sm whitespace-nowrap">
                                    <Link
                                        :href="`/database/${table.name}`"
                                        class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        {{ table.name }}
                                    </Link>
                                    <span
                                        v-if="table.protected"
                                        class="ml-2 inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-900/30 dark:text-amber-400 dark:ring-amber-500/30"
                                    >
                                        protected
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ table.columns }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ table.rows.toLocaleString() }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                    <Link
                                        :href="`/database/${table.name}`"
                                        class="text-indigo-600 transition-colors hover:text-indigo-800 dark:text-indigo-400"
                                    >
                                        Browse
                                    </Link>
                                    <button
                                        v-if="!table.protected && table.rows > 0"
                                        class="ml-3 text-red-600 transition-colors hover:text-red-800 dark:text-red-400"
                                        @click="confirmTruncate(table)"
                                    >
                                        Truncate
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredTables.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-slate-400">No tables found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

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
                        Are you sure you want to truncate <strong class="text-slate-900 dark:text-slate-100">{{ tableToTruncate?.name }}</strong
                        >? This will permanently delete all <strong>{{ tableToTruncate?.rows.toLocaleString() }}</strong> rows. This action cannot be
                        undone.
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
