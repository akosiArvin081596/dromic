<script setup lang="ts">
import { Deferred, Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Incident } from '@/types/incident';

type ReportCounts = {
    draft: number;
    for_validation: number;
    validated: number;
    returned: number;
};

type ReportActivityEntry = {
    incident_id: number;
    incident_name: string;
    total_lgus: number;
    reporting_lgus: number;
    provinces?: {
        province_name: string;
        total_lgus: number;
        reporting_lgus: number;
    }[];
};

defineProps<{
    activeIncidents: Incident[];
    reportCounts: ReportCounts;
    reportActivity?: ReportActivityEntry[] | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isLgu = computed(() => user.value.role === 'lgu');
const isProvincial = computed(() => user.value.role === 'provincial');
const isRegional = computed(() => user.value.role === 'regional');
const isAdmin = computed(() => user.value.role === 'admin');

const locationLabel = computed(() => {
    if (isLgu.value) return user.value.city_municipality_name;
    if (isProvincial.value) return user.value.province_name;
    if (isRegional.value) return user.value.region_name;
    return 'System-wide';
});

const roleLabel = computed(() => {
    if (isLgu.value) return 'LGU';
    if (isProvincial.value) return 'Provincial';
    if (isRegional.value) return 'Regional';
    return 'Administrator';
});

const expandedIncidents = ref<Set<number>>(new Set());

function toggleIncident(id: number) {
    if (expandedIncidents.value.has(id)) {
        expandedIncidents.value.delete(id);
    } else {
        expandedIncidents.value.add(id);
    }
}

function coveragePercent(reporting: number, total: number): number {
    if (total === 0) return 0;
    return Math.round((reporting / total) * 100);
}

function coverageBarColor(percent: number): string {
    if (percent >= 80) return 'bg-emerald-500';
    if (percent >= 40) return 'bg-amber-400';
    return 'bg-rose-500';
}

const channel = `App.Models.User.${page.props.auth.user.id}`;

useEcho(channel, 'IncidentCreated', () => router.reload({ only: ['activeIncidents'] }));
useEcho(channel, 'ReportSubmitted', () => router.reload({ only: ['activeIncidents', 'reportCounts'] }));
useEcho(channel, 'ReportValidated', () => router.reload({ only: ['activeIncidents', 'reportCounts'] }));
useEcho(channel, 'ReportReturned', () => router.reload({ only: ['reportCounts'] }));
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Welcome Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900">Welcome, {{ user.name }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ roleLabel }} &middot; {{ locationLabel }}</p>
            </div>

            <!-- Action Banner: LGU returned reports -->
            <div
                v-if="isLgu && reportCounts.returned > 0"
                class="mb-6 flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-100">
                    <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                        />
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-rose-800">
                        {{ reportCounts.returned }} report{{ reportCounts.returned !== 1 ? 's' : '' }} returned for revision
                    </p>
                    <p class="mt-0.5 text-xs text-rose-600">Please review and resubmit your returned reports.</p>
                </div>
            </div>

            <!-- Action Banner: Provincial awaiting validation -->
            <div
                v-if="isProvincial && reportCounts.for_validation > 0"
                class="mb-6 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100">
                    <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-amber-800">
                        {{ reportCounts.for_validation }} report{{ reportCounts.for_validation !== 1 ? 's' : '' }} awaiting your validation
                    </p>
                    <p class="mt-0.5 text-xs text-amber-600">LGU reports have been submitted and need your review.</p>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <!-- Active Incidents (all roles) -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Active Incidents</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50">
                            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                                />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3">
                        <div class="rounded-lg bg-indigo-50 px-3 py-2.5">
                            <div class="text-2xl font-bold text-indigo-900">{{ activeIncidents.length }}</div>
                        </div>
                    </div>
                </div>

                <!-- LGU cards: Drafts | Pending Validation | Validated -->
                <template v-if="isLgu">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Drafts</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100">
                                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-slate-900">{{ reportCounts.draft }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Pending Validation</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50">
                                <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-amber-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-amber-900">{{ reportCounts.for_validation }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Validated</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-emerald-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-emerald-900">{{ reportCounts.validated }}</div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Provincial cards: Awaiting Validation | Validated | Returned -->
                <template v-if="isProvincial">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Awaiting Validation</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50">
                                <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-amber-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-amber-900">{{ reportCounts.for_validation }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Validated</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-emerald-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-emerald-900">{{ reportCounts.validated }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Returned</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50">
                                <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-rose-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-rose-900">{{ reportCounts.returned }}</div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Regional card: Validated only -->
                <template v-if="isRegional">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Validated</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-emerald-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-emerald-900">{{ reportCounts.validated }}</div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Admin cards: Submitted | Validated | Drafts -->
                <template v-if="isAdmin">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Submitted</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50">
                                <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-amber-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-amber-900">{{ reportCounts.for_validation }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Validated</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-emerald-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-emerald-900">{{ reportCounts.validated }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Drafts</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100">
                                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                                <div class="text-2xl font-bold text-slate-900">{{ reportCounts.draft }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Reporting Coverage (Provincial/Regional/Admin only, deferred) -->
            <Deferred v-if="!isLgu" data="reportActivity">
                <template #fallback>
                    <div class="mb-8">
                        <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-400 uppercase">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
                                />
                            </svg>
                            Reporting Coverage
                        </h3>
                        <div class="space-y-3">
                            <div v-for="n in 3" :key="n" class="animate-pulse rounded-xl border border-slate-200 bg-white p-5">
                                <div class="h-4 w-1/3 rounded bg-slate-200"></div>
                                <div class="mt-3 h-2 w-full rounded-full bg-slate-100"></div>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-if="reportActivity && reportActivity.length > 0" class="mb-8">
                    <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-400 uppercase">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
                            />
                        </svg>
                        Reporting Coverage
                    </h3>

                    <div class="space-y-3">
                        <div v-for="entry in reportActivity" :key="entry.incident_id" class="rounded-xl border border-slate-200 bg-white shadow-sm">
                            <div
                                class="flex items-center justify-between px-5 py-4"
                                :class="{ 'cursor-pointer': entry.provinces && entry.provinces.length > 0 }"
                                @click="entry.provinces && entry.provinces.length > 0 ? toggleIncident(entry.incident_id) : undefined"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <svg
                                            v-if="entry.provinces && entry.provinces.length > 0"
                                            class="h-4 w-4 shrink-0 text-indigo-400 transition-transform duration-200"
                                            :class="{ 'rotate-90': expandedIncidents.has(entry.incident_id) }"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="2.5"
                                            stroke="currentColor"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                        <span class="truncate text-sm font-semibold text-slate-900">{{ entry.incident_name }}</span>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2.5">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200">
                                            <div
                                                class="h-full rounded-full transition-all duration-500"
                                                :class="coverageBarColor(coveragePercent(entry.reporting_lgus, entry.total_lgus))"
                                                :style="{ width: `${coveragePercent(entry.reporting_lgus, entry.total_lgus)}%` }"
                                            ></div>
                                        </div>
                                        <span class="shrink-0 text-xs font-medium text-slate-500 tabular-nums">
                                            {{ entry.reporting_lgus }} / {{ entry.total_lgus }} LGUs
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Provincial breakdown (Regional/Admin) -->
                            <div
                                v-if="entry.provinces && entry.provinces.length > 0 && expandedIncidents.has(entry.incident_id)"
                                class="border-t border-slate-100 px-5 py-3"
                            >
                                <div v-for="province in entry.provinces" :key="province.province_name" class="flex items-center gap-3 py-2">
                                    <span class="w-40 shrink-0 truncate text-sm text-slate-600">{{ province.province_name }}</span>
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200">
                                        <div
                                            class="h-full rounded-full transition-all duration-500"
                                            :class="coverageBarColor(coveragePercent(province.reporting_lgus, province.total_lgus))"
                                            :style="{ width: `${coveragePercent(province.reporting_lgus, province.total_lgus)}%` }"
                                        ></div>
                                    </div>
                                    <span class="shrink-0 text-xs text-slate-500 tabular-nums">
                                        {{ province.reporting_lgus }} / {{ province.total_lgus }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Deferred>

            <!-- Two-column layout: Incidents + Sidebar widgets -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Active Incidents List (takes 2 cols) -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-slate-900 uppercase">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                                />
                            </svg>
                            Active Incidents
                        </h2>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <Link
                            v-for="(incident, index) in activeIncidents"
                            :key="incident.id"
                            :href="`/incidents/${incident.id}`"
                            class="group flex items-center justify-between px-6 py-4 transition-colors hover:bg-slate-50/50"
                            :class="index % 2 === 0 ? 'bg-white' : 'bg-slate-50/50'"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="truncate text-sm font-medium text-slate-900 group-hover:text-indigo-600">
                                        {{ incident.display_name ?? incident.name }}
                                    </span>
                                    <span class="shrink-0 text-xs text-slate-400">
                                        Date of occurrence:
                                        {{
                                            new Date(incident.created_at).toLocaleDateString('en-PH', {
                                                year: 'numeric',
                                                month: 'short',
                                                day: 'numeric',
                                            })
                                        }}
                                    </span>
                                </div>
                                <div class="mt-1 flex items-center gap-3 text-xs text-slate-500">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                        :class="
                                            incident.type === 'massive'
                                                ? 'bg-rose-50 text-rose-700 ring-rose-600/20'
                                                : 'bg-amber-50 text-amber-700 ring-amber-600/20'
                                        "
                                    >
                                        {{ incident.type }}
                                    </span>
                                    <span>{{ (incident as any).my_reports_count ?? 0 }} report(s) submitted</span>
                                </div>
                            </div>
                            <svg
                                class="h-5 w-5 shrink-0 text-slate-300 transition-colors group-hover:text-indigo-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </Link>
                        <div v-if="activeIncidents.length === 0" class="px-6 py-12 text-center">
                            <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"
                                />
                            </svg>
                            <p class="mt-3 text-sm text-slate-500">No active incidents.</p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar widgets -->
                <div class="space-y-6">
                    <!-- Quick Actions -->
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-3.5">
                            <h3 class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"
                                    />
                                </svg>
                                Quick Actions
                            </h3>
                        </div>
                        <div class="divide-y divide-slate-100 px-5">
                            <Link
                                href="/incidents"
                                class="flex items-center gap-3 py-3 text-sm text-slate-700 transition-colors hover:text-indigo-600"
                            >
                                <span class="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-50">
                                    <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"
                                        />
                                    </svg>
                                </span>
                                View All Incidents
                            </Link>
                            <Link
                                v-if="isAdmin || isRegional || isLgu"
                                href="/incidents/create"
                                class="flex items-center gap-3 py-3 text-sm text-slate-700 transition-colors hover:text-indigo-600"
                            >
                                <span class="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-50">
                                    <svg
                                        class="h-3.5 w-3.5 text-emerald-600"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </span>
                                Create New Incident
                            </Link>
                        </div>
                    </div>

                    <!-- Recent Activity (placeholder) -->
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-3.5">
                            <h3 class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                Recent Activity
                            </h3>
                        </div>
                        <div class="px-5 py-6 text-center">
                            <svg class="mx-auto h-8 w-8 text-slate-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <p class="mt-2 text-xs text-slate-400">Activity timeline coming soon</p>
                        </div>
                    </div>

                    <!-- Weather Alerts (placeholder) -->
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-3.5">
                            <h3 class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z"
                                    />
                                </svg>
                                Weather Advisories
                            </h3>
                        </div>
                        <div class="px-5 py-6 text-center">
                            <svg class="mx-auto h-8 w-8 text-slate-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z"
                                />
                            </svg>
                            <p class="mt-2 text-xs text-slate-400">PAGASA weather feed coming soon</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom widgets row -->
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Report Submission Trends (placeholder) -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-3.5">
                        <h3 class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
                                />
                            </svg>
                            Report Submission Trends
                        </h3>
                    </div>
                    <div class="flex items-center justify-center px-5 py-14">
                        <div class="text-center">
                            <div class="mx-auto flex items-end justify-center gap-1.5">
                                <div class="h-8 w-5 rounded-t bg-slate-100"></div>
                                <div class="h-12 w-5 rounded-t bg-slate-100"></div>
                                <div class="h-6 w-5 rounded-t bg-slate-100"></div>
                                <div class="h-16 w-5 rounded-t bg-slate-100"></div>
                                <div class="h-10 w-5 rounded-t bg-slate-100"></div>
                                <div class="h-14 w-5 rounded-t bg-slate-100"></div>
                                <div class="h-9 w-5 rounded-t bg-slate-100"></div>
                            </div>
                            <p class="mt-4 text-xs text-slate-400">Daily report submission chart coming soon</p>
                        </div>
                    </div>
                </div>

                <!-- Affected Population Summary (placeholder) -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-3.5">
                        <h3 class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                />
                            </svg>
                            Affected Population Summary
                        </h3>
                    </div>
                    <div class="flex items-center justify-center px-5 py-14">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center gap-4">
                                <div class="flex flex-col items-center">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-50">
                                        <svg class="h-6 w-6 text-slate-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                                            />
                                        </svg>
                                    </div>
                                    <span class="mt-1.5 text-[10px] text-slate-300">Families</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-50">
                                        <svg class="h-6 w-6 text-slate-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                            />
                                        </svg>
                                    </div>
                                    <span class="mt-1.5 text-[10px] text-slate-300">Persons</span>
                                </div>
                                <div class="flex flex-col items-center">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-50">
                                        <svg class="h-6 w-6 text-slate-200" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                                            />
                                        </svg>
                                    </div>
                                    <span class="mt-1.5 text-[10px] text-slate-300">Evac Centers</span>
                                </div>
                            </div>
                            <p class="mt-4 text-xs text-slate-400">Aggregated impact data coming soon</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
