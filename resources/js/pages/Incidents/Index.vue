<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Incident } from '@/types/incident';

type ReturnedReport = {
    id: number;
    incident_id: number;
    report_number: string;
    report_type: string;
    sequence_number: number;
    return_reason: string | null;
    incident?: { id: number; name: string; display_name: string | null };
};

const props = defineProps<{
    incidents: {
        data: Incident[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
    incidentCounts: {
        total: number;
        active: number;
        closed: number;
        total_reports: number;
    };
    canCreate: boolean;
    returnedReports: ReturnedReport[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const channel = `App.Models.User.${page.props.auth.user.id}`;
const reloadIncidents = () => router.reload({ only: ['incidents', 'incidentCounts'] });

useEcho(channel, 'IncidentCreated', reloadIncidents);
useEcho(channel, 'ReportSubmitted', reloadIncidents);
useEcho(channel, 'ReportValidated', reloadIncidents);
useEcho(channel, 'ReportReturned', () => router.reload({ only: ['returnedReports', 'incidents', 'incidentCounts'] }));

const roleLabel = computed(() => {
    if (user.value.role === 'lgu') return 'LGU';
    if (user.value.role === 'provincial') return 'Provincial';
    if (user.value.role === 'regional') return 'Regional';
    return 'Administrator';
});

const locationLabel = computed(() => {
    if (user.value.role === 'lgu') return user.value.city_municipality_name;
    if (user.value.role === 'provincial') return user.value.province_name;
    if (user.value.role === 'regional') return user.value.region_name;
    return 'System-wide';
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

let debounceTimer: ReturnType<typeof setTimeout>;

watch([search, status], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get('/incidents', { search: search.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

function statusBadgeClass(incidentStatus: string): string {
    return incidentStatus === 'active'
        ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20'
        : 'bg-slate-50 text-slate-700 ring-1 ring-slate-600/20';
}

function typeBadgeClass(type: string): string {
    return type === 'massive' ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20';
}

function reportTypeLabel(report: ReturnedReport): string {
    if (report.report_type === 'initial') return 'Initial Report';
    if (report.report_type === 'terminal') return 'Terminal Report';
    return `Progress Report No. ${report.sequence_number}`;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Incidents" />
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Incidents</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ roleLabel }} &middot; {{ locationLabel }}</p>
                </div>
                <Link
                    v-if="canCreate"
                    href="/incidents/create"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    New Incident
                </Link>
            </div>

            <!-- Returned Reports Banner -->
            <div v-if="returnedReports.length > 0" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/50">
                <div class="flex items-center gap-3 px-5 pt-4 pb-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-100 dark:bg-rose-900/50">
                        <svg
                            class="h-5 w-5 text-rose-600 dark:text-rose-400"
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
                    <div>
                        <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">
                            {{ returnedReports.length }} report{{ returnedReports.length !== 1 ? 's' : '' }} returned for revision
                        </p>
                        <p class="mt-0.5 text-xs text-rose-600 dark:text-rose-400">Please review and resubmit your returned reports.</p>
                    </div>
                </div>
                <div class="divide-y divide-rose-200 border-t border-rose-200 dark:divide-rose-800 dark:border-rose-800">
                    <Link
                        v-for="report in returnedReports"
                        :key="report.id"
                        :href="`/incidents/${report.incident_id}/reports/${report.id}`"
                        class="group flex items-center justify-between px-5 py-3 transition-colors hover:bg-rose-100/50 dark:hover:bg-rose-900/30"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-rose-900 group-hover:text-rose-700 dark:text-rose-100">
                                {{ reportTypeLabel(report) }}
                                <span class="font-normal text-rose-600 dark:text-rose-400"
                                    >&middot; {{ report.incident?.display_name ?? report.incident?.name }}</span
                                >
                            </p>
                            <p v-if="report.return_reason" class="mt-0.5 truncate text-xs text-rose-600 dark:text-rose-400">
                                {{ report.return_reason }}
                            </p>
                        </div>
                        <svg
                            class="ml-3 h-4 w-4 shrink-0 text-rose-400 transition-colors group-hover:text-rose-600"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <!-- Total Incidents -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total Incidents</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/50">
                            <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"
                                />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3">
                        <div class="rounded-lg bg-indigo-50 px-3 py-2.5 dark:bg-indigo-950/50">
                            <div class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ incidentCounts.total }}</div>
                        </div>
                    </div>
                </div>

                <!-- Active -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Active</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/50">
                            <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                                />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3">
                        <div class="rounded-lg bg-emerald-50 px-3 py-2.5 dark:bg-emerald-950/50">
                            <div class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ incidentCounts.active }}</div>
                        </div>
                    </div>
                </div>

                <!-- Closed -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Closed</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-900/50">
                            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3">
                        <div class="rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-900/50">
                            <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ incidentCounts.closed }}</div>
                        </div>
                    </div>
                </div>

                <!-- Total Reports -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total Reports</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-950/50">
                            <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                                />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3">
                        <div class="rounded-lg bg-amber-50 px-3 py-2.5 dark:bg-amber-950/50">
                            <div class="text-2xl font-bold text-amber-900 dark:text-amber-100">{{ incidentCounts.total_reports }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Incidents Table Card -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <!-- Card Header with Filters -->
                <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-slate-900 uppercase dark:text-slate-100">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"
                                />
                            </svg>
                            All Incidents
                        </h2>
                        <div class="flex flex-col gap-3 sm:flex-row">
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
                                    placeholder="Search incidents..."
                                    class="block w-full rounded-lg border border-slate-300 py-2 pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:w-64 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                            <select
                                v-model="status"
                                class="block rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50/50 dark:bg-slate-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                LGUs
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Reports
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Created
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-slate-800">
                        <tr
                            v-for="incident in incidents.data"
                            :key="incident.id"
                            class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-700/50"
                        >
                            <td class="px-6 py-4 text-sm font-medium whitespace-nowrap text-slate-900 dark:text-slate-100">
                                <Link
                                    :href="`/incidents/${incident.id}`"
                                    class="text-indigo-600 transition-colors hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    {{ incident.display_name ?? incident.name }}
                                </Link>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="typeBadgeClass(incident.type)"
                                >
                                    {{ incident.type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                <template v-if="incident.type === 'massive' && !incident.city_municipalities?.length">
                                    {{ incident.reporting_lgus_count ?? 0 }}
                                </template>
                                <template v-else>{{ incident.city_municipalities?.length ?? 0 }}</template>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                {{ incident.reports_count ?? 0 }}
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="statusBadgeClass(incident.status)"
                                >
                                    {{ incident.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400">
                                {{ formatDate(incident.created_at) }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                <Link
                                    :href="`/incidents/${incident.id}`"
                                    class="inline-flex items-center gap-1 text-indigo-600 transition-colors hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    View
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="incidents.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                                    />
                                </svg>
                                <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">No incidents found</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ search || status ? 'Try adjusting your filters.' : 'No incidents have been created yet.' }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div
                    v-if="incidents.last_page > 1"
                    class="flex items-center justify-between border-t border-slate-200 px-6 py-3 dark:border-slate-700"
                >
                    <p class="text-xs text-slate-500 dark:text-slate-400">Page {{ incidents.current_page }} of {{ incidents.last_page }}</p>
                    <div class="flex space-x-1">
                        <template v-for="link in incidents.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="rounded-lg px-3 py-1.5 text-sm transition-colors"
                                :class="
                                    link.active
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'bg-white text-slate-700 hover:bg-slate-100 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                <span v-html="link.label" />
                            </Link>
                            <span v-else class="rounded-lg px-3 py-1.5 text-sm text-slate-300 dark:text-slate-600">
                                <span v-html="link.label" />
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
