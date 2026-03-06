<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Incident } from '@/types/incident';
import type { Report } from '@/types/report';

const page = usePage();
const isRegional = page.props.auth.user.role === 'regional';

const props = defineProps<{
    incident: Incident;
    reports: {
        data: Report[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    };
    filters: {
        status?: string;
    };
}>();

const channel = `App.Models.User.${page.props.auth.user.id}`;
const incidentId = props.incident.id;

const reloadReports = (e: { report: { incident_id: number } }) => {
    if (e.report.incident_id === incidentId) router.reload({ only: ['reports'] });
};

useEcho(channel, 'ReportSubmitted', reloadReports);
useEcho(channel, 'ReportValidated', reloadReports);
useEcho(channel, 'ReportReturned', reloadReports);

const status = ref(props.filters.status ?? '');

let debounceTimer: ReturnType<typeof setTimeout>;

watch([status], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(`/incidents/${props.incident.id}/reports`, { status: status.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

function deleteReport(report: Report) {
    if (confirm(`Delete report ${report.report_number}?`)) {
        router.delete(`/incidents/${props.incident.id}/reports/${report.id}`);
    }
}

function statusBadgeClass(reportStatus: string): string {
    switch (reportStatus) {
        case 'draft':
            return 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20';
        case 'for_validation':
            return 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/20';
        case 'validated':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20';
        case 'returned':
            return 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20';
        default:
            return 'bg-slate-50 text-slate-700 ring-1 ring-slate-600/20';
    }
}

function reportTypeBadge(type: string, seq: number): string {
    if (type === 'initial') return 'Initial Report';
    if (type === 'terminal') return 'Terminal Report';
    return `Progress Report No. ${seq}`;
}
</script>

<template>
    <AppLayout>
        <Head :title="`Reports - ${incident.display_name ?? incident.name}`" />
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="`/incidents/${incident.id}`" class="text-sm text-slate-500 transition-colors hover:text-slate-700"
                        >&larr; Back to Incident</Link
                    >
                    <h1 class="text-2xl font-bold text-slate-900">Reports: {{ incident.display_name ?? incident.name }}</h1>
                </div>
            </div>

            <!-- Filters -->
            <div v-if="!isRegional" class="mb-6 flex flex-col gap-4 sm:flex-row">
                <select
                    v-model="status"
                    class="block border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                >
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="for_validation">For Validation</option>
                    <option value="validated">Validated</option>
                    <option value="returned">Returned</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Report #</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold tracking-wider text-slate-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="report in reports.data" :key="report.id" class="transition-colors hover:bg-slate-50/50">
                            <td class="px-6 py-4 text-sm font-medium whitespace-nowrap text-slate-900">
                                <Link
                                    :href="`/incidents/${incident.id}/reports/${report.id}`"
                                    class="text-indigo-600 transition-colors hover:text-indigo-800"
                                >
                                    {{ report.report_number }}
                                </Link>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                    :class="{
                                        'bg-sky-50 text-sky-700 ring-sky-600/20': report.report_type === 'initial',
                                        'bg-amber-50 text-amber-700 ring-amber-600/20': report.report_type === 'progress',
                                        'bg-emerald-50 text-emerald-700 ring-emerald-600/20': report.report_type === 'terminal',
                                    }"
                                >
                                    {{ reportTypeBadge(report.report_type, report.sequence_number) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500">
                                {{ report.city_municipality?.name }}
                                <span v-if="report.city_municipality?.province" class="text-slate-400"
                                    >, {{ report.city_municipality.province.name }}</span
                                >
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-500">{{ report.report_date }}</td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="statusBadgeClass(report.status)"
                                >
                                    {{
                                        report.status === 'for_validation'
                                            ? 'For Validation'
                                            : report.status === 'returned'
                                              ? 'Returned'
                                              : report.status
                                    }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                <Link
                                    :href="`/incidents/${incident.id}/reports/${report.id}`"
                                    class="mr-3 text-indigo-600 transition-colors hover:text-indigo-800"
                                >
                                    View
                                </Link>
                                <Link
                                    v-if="report.status === 'draft' || report.status === 'returned'"
                                    :href="`/incidents/${incident.id}/reports/${report.id}/edit`"
                                    class="mr-3 text-indigo-600 transition-colors hover:text-indigo-800"
                                >
                                    Edit
                                </Link>
                                <button
                                    v-if="report.status === 'draft'"
                                    class="text-rose-600 transition-colors hover:text-rose-800"
                                    @click="deleteReport(report)"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="reports.data.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">No reports found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="reports.last_page > 1" class="mt-4 flex justify-center space-x-1">
                <template v-for="link in reports.links" :key="link.label">
                    <a
                        v-if="link.url"
                        :href="link.url"
                        class="px-3 py-2 text-sm transition-colors"
                        :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-100'"
                        v-html="link.label"
                    />
                    <span v-else class="px-3 py-2 text-sm text-slate-400" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
