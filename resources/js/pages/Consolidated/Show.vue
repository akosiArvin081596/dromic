<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, ref } from 'vue';
import ConsolidatedPrintTemplate from '@/components/reports/ConsolidatedPrintTemplate.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Incident } from '@/types/incident';
import type { Report } from '@/types/report';

type ConsolidatedTotals = {
    affected_families: number;
    affected_persons: number;
    inside_ec_families_cum: number;
    inside_ec_families_now: number;
    inside_ec_persons_cum: number;
    inside_ec_persons_now: number;
    outside_ec_families_cum: number;
    outside_ec_families_now: number;
    outside_ec_persons_cum: number;
    outside_ec_persons_now: number;
    non_idp_families_cum: number;
    non_idp_persons_cum: number;
    totally_damaged: number;
    partially_damaged: number;
    estimated_cost: number;
    casualties_injured: number;
    casualties_missing: number;
    casualties_dead: number;
    infrastructure_cost: number;
    agriculture_cost: number;
    stranded_passengers: number;
    preemptive_families: number;
    preemptive_persons: number;
    inside_ec_count_cum: number;
    inside_ec_count_now: number;
};

type CutoffReport = Report & { carried_forward?: boolean };

type Cutoff = {
    label: string;
    date: string;
    time: string;
    reports: CutoffReport[];
    totals: ConsolidatedTotals;
};

const props = defineProps<{
    incident: Incident;
    cutoffs: Cutoff[];
    dromicLogoUrl?: string | null;
}>();

const page = usePage();
const userRole = page.props.auth.user.role;
const showProvince = computed(() => userRole === 'admin' || userRole === 'regional' || userRole === 'regional_director');
const channel = `App.Models.User.${page.props.auth.user.id}`;

useEcho(channel, 'ReportValidated', (e: { report: { incident_id: number } }) => {
    if (e.report.incident_id === props.incident.id) router.reload();
});

const selectedIndex = ref(props.cutoffs.length > 0 ? props.cutoffs.length - 1 : 0);
const selectedCutoff = computed(() => props.cutoffs[selectedIndex.value] ?? null);

function formatDate(date: string): string {
    return new Date(date + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function cutoffLabel(cutoff: Cutoff): string {
    return `${cutoff.label} — ${formatDate(cutoff.date)}, ${cutoff.time}`;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
}

function labelBadgeClass(label: string): string {
    if (label === 'Initial Report') return 'bg-indigo-50 text-indigo-700 ring-indigo-600/20';
    return 'bg-sky-50 text-sky-700 ring-sky-600/20';
}

function printReport() {
    window.print();
}
</script>

<template>
    <AppLayout>
        <Head :title="`Consolidated - ${incident.display_name ?? incident.name}`" />

        <!-- Screen controls (hidden on print) -->
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 print:hidden">
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link
                        :href="`/incidents/${incident.id}`"
                        class="text-sm text-slate-500 transition-colors hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300"
                        >&larr; Back to Incident</Link
                    >
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Consolidated Report</h1>
                </div>
                <button
                    v-if="cutoffs.length > 0"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    @click="printReport"
                >
                    Print Report
                </button>
            </div>

            <!-- Incident Info -->
            <div class="mb-6 border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ incident.display_name ?? incident.name }}</h2>
                <p v-if="incident.description" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ incident.description }}</p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    <span class="font-medium">{{ cutoffs.length }}</span> cut-off period(s) recorded
                </p>
            </div>

            <!-- No data state -->
            <div
                v-if="cutoffs.length === 0"
                class="border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
            >
                {{ userRole === 'regional' ? 'No validated reports yet.' : 'No submitted or validated reports yet.' }}
            </div>

            <template v-else>
                <!-- Cut-off Selector -->
                <div class="mb-6 flex flex-wrap items-center gap-4">
                    <label for="cutoff-select" class="text-sm font-medium text-slate-700 dark:text-slate-300">Cut-off Period:</label>
                    <select
                        id="cutoff-select"
                        v-model.number="selectedIndex"
                        class="border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    >
                        <option v-for="(cutoff, idx) in cutoffs" :key="idx" :value="idx">
                            {{ cutoffLabel(cutoff) }}
                        </option>
                    </select>
                    <span
                        v-if="selectedCutoff"
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                        :class="labelBadgeClass(selectedCutoff.label)"
                    >
                        {{ selectedCutoff.label }}
                    </span>
                </div>

                <template v-if="selectedCutoff">
                    <!-- Summary Cards -->
                    <div class="mb-8">
                        <!-- Row 1: Total Affected + Evacuation Centers -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Total Affected -->
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total Affected</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                                        <svg
                                            class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div class="rounded-lg bg-indigo-50 px-3 py-2.5 text-center dark:bg-indigo-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">
                                            Families
                                        </div>
                                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.affected_families.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-indigo-50 px-3 py-2.5 text-center dark:bg-indigo-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400">
                                            Persons
                                        </div>
                                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.affected_persons.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Evacuation Centers -->
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Evacuation Centers</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                                        <svg
                                            class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div class="rounded-lg bg-emerald-50 px-3 py-2.5 text-center dark:bg-emerald-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-emerald-600 uppercase dark:text-emerald-400">
                                            CUM
                                        </div>
                                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.inside_ec_count_cum.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-emerald-50 px-3 py-2.5 text-center dark:bg-emerald-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-emerald-600 uppercase dark:text-emerald-400">
                                            NOW
                                        </div>
                                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.inside_ec_count_now.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Inside EC + Outside EC -->
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Inside EC -->
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Inside Evacuation Centers</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-900/30">
                                        <svg
                                            class="h-4 w-4 text-sky-600 dark:text-sky-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div class="rounded-lg bg-sky-50 px-3 py-2.5 dark:bg-sky-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-sky-600 uppercase dark:text-sky-400">Families</div>
                                        <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Cum</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{ selectedCutoff.totals.inside_ec_families_cum.toLocaleString() }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Now</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{ selectedCutoff.totals.inside_ec_families_now.toLocaleString() }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-sky-50 px-3 py-2.5 dark:bg-sky-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-sky-600 uppercase dark:text-sky-400">Persons</div>
                                        <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Cum</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{ selectedCutoff.totals.inside_ec_persons_cum.toLocaleString() }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Now</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{ selectedCutoff.totals.inside_ec_persons_now.toLocaleString() }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Outside EC -->
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Outside Evacuation Centers</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/30">
                                        <svg
                                            class="h-4 w-4 text-amber-600 dark:text-amber-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div class="rounded-lg bg-amber-50 px-3 py-2.5 dark:bg-amber-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-amber-600 uppercase dark:text-amber-400">
                                            Families
                                        </div>
                                        <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Cum</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{ selectedCutoff.totals.outside_ec_families_cum.toLocaleString() }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Now</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{ selectedCutoff.totals.outside_ec_families_now.toLocaleString() }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-amber-50 px-3 py-2.5 dark:bg-amber-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-amber-600 uppercase dark:text-amber-400">
                                            Persons
                                        </div>
                                        <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Cum</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{ selectedCutoff.totals.outside_ec_persons_cum.toLocaleString() }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Now</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{ selectedCutoff.totals.outside_ec_persons_now.toLocaleString() }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Total Displaced + Non-IDPs -->
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Total Displaced Population -->
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total Displaced Population</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-900/30">
                                        <svg
                                            class="h-4 w-4 text-purple-600 dark:text-purple-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div class="rounded-lg bg-purple-50 px-3 py-2.5 dark:bg-purple-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-purple-600 uppercase dark:text-purple-400">
                                            Families
                                        </div>
                                        <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Cum</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{
                                                        (
                                                            selectedCutoff.totals.inside_ec_families_cum +
                                                            selectedCutoff.totals.outside_ec_families_cum
                                                        ).toLocaleString()
                                                    }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Now</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{
                                                        (
                                                            selectedCutoff.totals.inside_ec_families_now +
                                                            selectedCutoff.totals.outside_ec_families_now
                                                        ).toLocaleString()
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-purple-50 px-3 py-2.5 dark:bg-purple-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-purple-600 uppercase dark:text-purple-400">
                                            Persons
                                        </div>
                                        <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Cum</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{
                                                        (
                                                            selectedCutoff.totals.inside_ec_persons_cum + selectedCutoff.totals.outside_ec_persons_cum
                                                        ).toLocaleString()
                                                    }}
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-[10px] font-medium text-slate-400 uppercase">Now</div>
                                                <div class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                                    {{
                                                        (
                                                            selectedCutoff.totals.inside_ec_persons_now + selectedCutoff.totals.outside_ec_persons_now
                                                        ).toLocaleString()
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Non-IDPs -->
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Non-IDPs</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/30">
                                        <svg
                                            class="h-4 w-4 text-violet-600 dark:text-violet-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div class="rounded-lg bg-violet-50 px-3 py-2.5 text-center dark:bg-violet-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-violet-600 uppercase dark:text-violet-400">
                                            Families
                                        </div>
                                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.non_idp_families_cum.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-violet-50 px-3 py-2.5 text-center dark:bg-violet-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-violet-600 uppercase dark:text-violet-400">
                                            Persons
                                        </div>
                                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.non_idp_persons_cum.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 4: Damaged Houses + Casualties + Infra/Agri -->
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <!-- Damaged Houses -->
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Damaged Houses</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 dark:bg-rose-900/30">
                                        <svg
                                            class="h-4 w-4 text-rose-600 dark:text-rose-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-3">
                                    <div class="rounded-lg bg-rose-50 px-3 py-2.5 text-center dark:bg-rose-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-rose-600 uppercase dark:text-rose-400">Totally</div>
                                        <div class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.totally_damaged.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-rose-50 px-3 py-2.5 text-center dark:bg-rose-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-rose-600 uppercase dark:text-rose-400">
                                            Partially
                                        </div>
                                        <div class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.partially_damaged.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-rose-50 px-3 py-2.5 text-center dark:bg-rose-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-rose-600 uppercase dark:text-rose-400">
                                            Est. Cost
                                        </div>
                                        <div class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">
                                            {{ formatCurrency(selectedCutoff.totals.estimated_cost) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Casualties -->
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Casualties</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/30">
                                        <svg
                                            class="h-4 w-4 text-red-600 dark:text-red-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-3 gap-3">
                                    <div class="rounded-lg bg-red-50 px-3 py-2.5 text-center dark:bg-red-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-red-600 uppercase dark:text-red-400">Injured</div>
                                        <div class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.casualties_injured.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-red-50 px-3 py-2.5 text-center dark:bg-red-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-red-600 uppercase dark:text-red-400">Missing</div>
                                        <div class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.casualties_missing.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-red-50 px-3 py-2.5 text-center dark:bg-red-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-red-600 uppercase dark:text-red-400">Dead</div>
                                        <div class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.casualties_dead.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Infrastructure & Agriculture Damage -->
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Infra &amp; Agri Damage</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-900/30">
                                        <svg
                                            class="h-4 w-4 text-orange-600 dark:text-orange-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div class="rounded-lg bg-orange-50 px-3 py-2.5 text-center dark:bg-orange-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-orange-600 uppercase dark:text-orange-400">
                                            Infrastructure
                                        </div>
                                        <div class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">
                                            {{ formatCurrency(selectedCutoff.totals.infrastructure_cost) }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-orange-50 px-3 py-2.5 text-center dark:bg-orange-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-orange-600 uppercase dark:text-orange-400">
                                            Agriculture
                                        </div>
                                        <div class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">
                                            {{ formatCurrency(selectedCutoff.totals.agriculture_cost) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 5: Pre-emptive & Stranded (conditional) -->
                        <div
                            v-if="selectedCutoff.totals.preemptive_families > 0 || selectedCutoff.totals.stranded_passengers > 0"
                            class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Pre-emptive Evacuation</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 dark:bg-teal-900/30">
                                        <svg
                                            class="h-4 w-4 text-teal-600 dark:text-teal-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.572-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-3">
                                    <div class="rounded-lg bg-teal-50 px-3 py-2.5 text-center dark:bg-teal-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-teal-600 uppercase dark:text-teal-400">Families</div>
                                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.preemptive_families.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-teal-50 px-3 py-2.5 text-center dark:bg-teal-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-teal-600 uppercase dark:text-teal-400">Persons</div>
                                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.preemptive_persons.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Stranded Passengers</span>
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-50 dark:bg-cyan-900/30">
                                        <svg
                                            class="h-4 w-4 text-cyan-600 dark:text-cyan-400"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="1.5"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                            />
                                        </svg>
                                    </span>
                                </div>
                                <div class="mt-3">
                                    <div class="rounded-lg bg-cyan-50 px-3 py-2.5 text-center dark:bg-cyan-900/20">
                                        <div class="text-[10px] font-semibold tracking-wide text-cyan-600 uppercase dark:text-cyan-400">Total</div>
                                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">
                                            {{ selectedCutoff.totals.stranded_passengers.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </template>
        </div>

        <!-- Print Template -->
        <div v-if="selectedCutoff" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 print:max-w-none print:p-0">
            <div class="border border-slate-200 bg-white p-8 dark:border-slate-700 dark:bg-slate-800 print:border-none print:shadow-none">
                <ConsolidatedPrintTemplate
                    :reports="selectedCutoff.reports"
                    :incident="incident"
                    :cutoff-label="selectedCutoff.label"
                    :cutoff-date="selectedCutoff.date"
                    :cutoff-time="selectedCutoff.time"
                    :show-province="showProvince"
                    :dromic-logo-url="dromicLogoUrl"
                />
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    nav,
    .print\:hidden {
        display: none !important;
    }
}
</style>
