<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, ref } from 'vue';
import ApproveModal from '@/components/request-letters/ApproveModal.vue';
import DeliveryModal from '@/components/request-letters/DeliveryModal.vue';
import EndorseModal from '@/components/request-letters/EndorseModal.vue';
import RequestLetterCard from '@/components/request-letters/RequestLetterCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Incident } from '@/types/incident';
import type { Report } from '@/types/report';
import type { AugmentationTypeOption, EscortUser, RequestLetter } from '@/types/request-letter';
import { pluralize } from '@/utils/pluralize';

const props = defineProps<{
    incident: Incident;
    reportsByLgu: Record<string, Report[]>;
    canCreateReport: boolean;
    requestLettersByLgu: Record<string, RequestLetter[]>;
    canUploadRequestLetter: boolean;
    augmentationTypes: AugmentationTypeOption[];
    escortUsers: EscortUser[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const canViewConsolidated = computed(() => user.value.role === 'admin' || user.value.role === 'regional' || user.value.role === 'provincial');

// Realtime updates via Echo
const channel = `App.Models.User.${user.value.id}`;
const incidentId = props.incident.id;

const reloadReports = (e: { report: { incident_id: number } }) => {
    if (e.report.incident_id === incidentId) router.reload({ only: ['reportsByLgu'] });
};
const reloadRequestLetters = (e: { request_letter: { incident_id: number } }) => {
    if (e.request_letter.incident_id === incidentId) router.reload({ only: ['requestLettersByLgu'] });
};

useEcho(channel, 'ReportSubmitted', reloadReports);
useEcho(channel, 'ReportValidated', reloadReports);
useEcho(channel, 'ReportReturned', reloadReports);
useEcho(channel, 'RequestLetterSubmitted', reloadRequestLetters);
useEcho(channel, 'RequestLetterEndorsed', reloadRequestLetters);
useEcho(channel, 'RequestLetterApproved', reloadRequestLetters);
useEcho(channel, 'DeliveryRecorded', reloadRequestLetters);
const hasExistingReports = computed(
    () => Object.keys(props.reportsByLgu).length > 0 && Object.values(props.reportsByLgu).some((reports) => reports.length > 0),
);

const showProvinceGrouping = computed(() => user.value.role === 'admin' || user.value.role === 'regional');

const reportsByProvince = computed(() => {
    const grouped: Record<string, { provinceName: string; lgus: { lguId: string; reports: Report[] }[] }> = {};

    for (const [lguId, reports] of Object.entries(props.reportsByLgu)) {
        const province = reports[0]?.city_municipality?.province;
        const key = province?.id?.toString() ?? 'unknown';
        if (!grouped[key]) {
            grouped[key] = { provinceName: province?.name ?? 'Unknown Province', lgus: [] };
        }
        grouped[key].lgus.push({ lguId, reports });
    }

    return Object.values(grouped).sort((a, b) => a.provinceName.localeCompare(b.provinceName));
});

const expandedProvinces = ref<Set<string>>(new Set());

function toggleProvince(name: string) {
    if (expandedProvinces.value.has(name)) {
        expandedProvinces.value.delete(name);
    } else {
        expandedProvinces.value.add(name);
    }
}

const totalAffectedFamilies = computed(() => {
    const lguId = user.value.city_municipality_id;
    if (!lguId) return 0;
    const reports = props.reportsByLgu[lguId];
    if (!reports?.length) return 0;
    const latestReport = reports[reports.length - 1];
    return (latestReport.affected_areas ?? []).reduce((sum, area) => sum + (area.families ?? 0), 0);
});

function reportTypeBadgeClass(type: string): string {
    switch (type) {
        case 'initial':
            return 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/20';
        case 'progress':
            return 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20';
        case 'terminal':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20';
        default:
            return 'bg-slate-50 text-slate-700 ring-1 ring-slate-600/20';
    }
}

function statusBadgeClass(status: string): string {
    switch (status) {
        case 'draft':
            return 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20';
        case 'for_validation':
            return 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/20';
        case 'validated':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20';
        default:
            return 'bg-slate-50 text-slate-700 ring-1 ring-slate-600/20';
    }
}

function statusLabel(status: string): string {
    if (status === 'for_validation') return 'For Validation';
    return status;
}

// Request Letter Upload
const showUploadModal = ref(false);
const uploadForm = useForm<{
    file: File | null;
    augmentation_items: { type: string; quantity: number | string }[];
}>({
    file: null,
    augmentation_items: [{ type: '', quantity: '' }],
});

function addAugmentationItem(): void {
    uploadForm.augmentation_items.push({ type: '', quantity: '' });
}

function removeAugmentationItem(index: number): void {
    uploadForm.augmentation_items.splice(index, 1);
}

function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    uploadForm.file = target.files?.[0] ?? null;
}

const hasQuantityExceeded = computed(() => {
    if (totalAffectedFamilies.value <= 0) return false;
    return uploadForm.augmentation_items.some((item) => Number(item.quantity) > totalAffectedFamilies.value);
});

function submitUpload(): void {
    uploadForm.post(`/incidents/${props.incident.id}/request-letters`, {
        forceFormData: true,
        onSuccess: () => {
            showUploadModal.value = false;
            uploadForm.reset();
            uploadForm.augmentation_items = [{ type: '', quantity: '' }];
        },
    });
}

// Endorse/Approve/Delivery Modals
const endorseTarget = ref<RequestLetter | null>(null);
const approveTarget = ref<RequestLetter | null>(null);
const deliveryTarget = ref<RequestLetter | null>(null);

function openEndorse(letter: RequestLetter): void {
    endorseTarget.value = letter;
}
function openApprove(letter: RequestLetter): void {
    approveTarget.value = letter;
}
function openDelivery(letter: RequestLetter): void {
    deliveryTarget.value = letter;
}
</script>

<template>
    <AppLayout>
        <Head :title="incident.name" />
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Breadcrumb & Actions -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Link href="/incidents" class="inline-flex items-center gap-1 text-sm text-slate-400 transition-colors hover:text-indigo-600">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        Back to Incidents
                    </Link>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-slate-900">{{ incident.name }}</h1>
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
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                            :class="
                                incident.status === 'active'
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                    : 'bg-slate-50 text-slate-700 ring-slate-600/20'
                            "
                        >
                            {{ incident.status }}
                        </span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="canViewConsolidated"
                        :href="`/incidents/${incident.id}/consolidated`"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
                    >
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
                            />
                        </svg>
                        Consolidated View
                    </Link>
                    <Link
                        v-if="user.role === 'admin' || user.role === 'regional'"
                        :href="`/incidents/${incident.id}/edit`"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
                    >
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                            />
                        </svg>
                        Edit
                    </Link>
                    <Link
                        v-if="canCreateReport"
                        :href="`/incidents/${incident.id}/reports/create`"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        {{ hasExistingReports ? 'Create Next Report' : 'Create Report' }}
                    </Link>
                </div>
            </div>

            <!-- Incident Details Card -->
            <div class="mb-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="flex items-center gap-2 text-sm font-semibold tracking-wide text-slate-900 uppercase">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                            />
                        </svg>
                        Incident Details
                    </h2>
                </div>
                <div class="px-6 py-5">
                    <p v-if="incident.description" class="mb-5 text-sm leading-relaxed text-slate-600">{{ incident.description }}</p>

                    <!-- Metadata grid -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
                                <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                    />
                                </svg>
                            </span>
                            <div>
                                <p class="text-xs font-medium text-slate-400">Created by</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ incident.creator?.name ?? 'Unknown' }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100">
                                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                                    />
                                </svg>
                            </span>
                            <div>
                                <p class="text-xs font-medium text-slate-400">Date created</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-900">
                                    {{
                                        new Date(incident.created_at).toLocaleDateString('en-PH', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                        })
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"
                                    />
                                </svg>
                            </span>
                            <div>
                                <p class="text-xs font-medium text-slate-400">Assigned LGUs</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-900">
                                    {{ incident.city_municipalities?.length ?? 0 }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Assigned LGUs Tags -->
                    <div v-if="incident.city_municipalities && incident.city_municipalities.length > 0" class="mt-5 border-t border-slate-100 pt-5">
                        <p class="mb-2.5 text-xs font-medium tracking-wide text-slate-400 uppercase">Affected Areas</p>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="cm in incident.city_municipalities"
                                :key="cm.id"
                                class="inline-flex items-center rounded-lg bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200"
                            >
                                {{ cm.name }}
                                <span v-if="cm.province" class="ml-1 text-slate-400">&middot; {{ cm.province.name }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports by LGU Section -->
            <div class="mb-8">
                <h2 class="mb-4 flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-400 uppercase">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                        />
                    </svg>
                    Reports by LGU
                </h2>

                <div
                    v-if="Object.keys(reportsByLgu).length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm"
                >
                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                        />
                    </svg>
                    <p class="mt-3 text-sm font-medium text-slate-500">No reports filed yet</p>
                    <p class="mt-1 text-xs text-slate-400">Reports will appear here once LGUs submit them for this incident.</p>
                </div>

                <!-- Province-grouped view for regional/admin -->
                <div v-if="showProvinceGrouping" class="space-y-3">
                    <div
                        v-for="group in reportsByProvince"
                        :key="group.provinceName"
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-5 py-3.5 text-left transition-colors hover:bg-slate-50"
                            @click="toggleProvince(group.provinceName)"
                        >
                            <svg
                                class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                                :class="{ 'rotate-90': expandedProvinces.has(group.provinceName) }"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                            <svg class="h-4 w-4 shrink-0 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"
                                />
                            </svg>
                            <span class="text-sm font-semibold text-slate-700">{{ group.provinceName }}</span>
                            <span class="text-sm font-normal text-slate-400">
                                &middot; {{ group.lgus.length }} LGU{{ group.lgus.length !== 1 ? 's' : '' }}
                            </span>
                            <span class="ml-auto rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500 tabular-nums">
                                {{ group.lgus.reduce((sum, l) => sum + l.reports.length, 0) }} report{{
                                    group.lgus.reduce((sum, l) => sum + l.reports.length, 0) !== 1 ? 's' : ''
                                }}
                            </span>
                        </button>
                        <div
                            class="grid transition-all duration-300 ease-in-out"
                            :class="
                                expandedProvinces.has(group.provinceName)
                                    ? 'grid-rows-[1fr] border-t border-slate-200 opacity-100'
                                    : 'grid-rows-[0fr] opacity-0'
                            "
                        >
                            <div class="overflow-hidden">
                                <div class="space-y-4 p-4">
                                    <div
                                        v-for="lgu in group.lgus"
                                        :key="lgu.lguId"
                                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                                    >
                                        <div class="border-b border-slate-200 bg-slate-50/50 px-6 py-3.5">
                                            <h4 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                                <svg
                                                    class="h-4 w-4 text-slate-400"
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
                                                {{ lgu.reports[0]?.city_municipality?.name ?? 'Unknown LGU' }}
                                                <span
                                                    class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 tabular-nums"
                                                >
                                                    {{ lgu.reports.length }} report{{ lgu.reports.length !== 1 ? 's' : '' }}
                                                </span>
                                            </h4>
                                        </div>
                                        <div class="divide-y divide-slate-100">
                                            <Link
                                                v-for="report in lgu.reports"
                                                :key="report.id"
                                                :href="`/incidents/${incident.id}/reports/${report.id}`"
                                                class="group flex items-center justify-between px-6 py-3.5 transition-colors hover:bg-slate-50/50"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <span
                                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                                        :class="reportTypeBadgeClass(report.report_type)"
                                                    >
                                                        {{
                                                            report.report_type === 'initial'
                                                                ? 'Initial Report'
                                                                : report.report_type === 'terminal'
                                                                  ? 'Terminal Report'
                                                                  : `Progress Report No. ${report.sequence_number}`
                                                        }}
                                                    </span>
                                                    <span class="text-sm font-medium text-slate-700 transition-colors group-hover:text-indigo-600">
                                                        {{ report.report_number }}
                                                    </span>
                                                    <span
                                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                                        :class="statusBadgeClass(report.status)"
                                                    >
                                                        {{ statusLabel(report.status) }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs text-slate-400">{{ report.report_date }} {{ report.report_time }}</span>
                                                    <svg
                                                        class="h-4 w-4 text-slate-300 transition-colors group-hover:text-indigo-400"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke-width="1.5"
                                                        stroke="currentColor"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                    </svg>
                                                </div>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flat view for provincial/LGU -->
                <div v-else class="space-y-4">
                    <div
                        v-for="(reports, lguId) in reportsByLgu"
                        :key="lguId"
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="border-b border-slate-200 bg-slate-50/50 px-6 py-3.5">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"
                                    />
                                </svg>
                                {{ reports[0]?.city_municipality?.name ?? 'Unknown LGU' }}
                                <span v-if="reports[0]?.city_municipality?.province" class="font-normal text-slate-400">
                                    &middot; {{ reports[0].city_municipality.province.name }}
                                </span>
                                <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 tabular-nums">
                                    {{ reports.length }} report{{ reports.length !== 1 ? 's' : '' }}
                                </span>
                            </h3>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <Link
                                v-for="report in reports"
                                :key="report.id"
                                :href="`/incidents/${incident.id}/reports/${report.id}`"
                                class="group flex items-center justify-between px-6 py-3.5 transition-colors hover:bg-slate-50/50"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="reportTypeBadgeClass(report.report_type)"
                                    >
                                        {{
                                            report.report_type === 'initial'
                                                ? 'Initial Report'
                                                : report.report_type === 'terminal'
                                                  ? 'Terminal Report'
                                                  : `Progress Report No. ${report.sequence_number}`
                                        }}
                                    </span>
                                    <span class="text-sm font-medium text-slate-700 transition-colors group-hover:text-indigo-600">
                                        {{ report.report_number }}
                                    </span>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="statusBadgeClass(report.status)"
                                    >
                                        {{ statusLabel(report.status) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400">{{ report.report_date }} {{ report.report_time }}</span>
                                    <svg
                                        class="h-4 w-4 text-slate-300 transition-colors group-hover:text-indigo-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.5"
                                        stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Request Letters Section -->
            <div>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-400 uppercase">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 0 .001-.068a2.25 2.25 0 0 0-.659-1.591l-4.09-4.09a2.25 2.25 0 0 0-3.182 0l-4.09 4.09a2.25 2.25 0 0 0-.659 1.59v.069M21.75 9l-7.304-4.178a2.25 2.25 0 0 0-2.892 0L4.25 9"
                            />
                        </svg>
                        Request Letters
                    </h2>
                    <button
                        v-if="canUploadRequestLetter"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
                        @click="showUploadModal = true"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"
                            />
                        </svg>
                        Upload Request Letter
                    </button>
                </div>

                <div
                    v-if="Object.keys(requestLettersByLgu).length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm"
                >
                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M21.75 9v.906a2.25 2.25 0 0 1-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 0 0 1.183 1.981l6.478 3.488m8.839 0 .001-.068a2.25 2.25 0 0 0-.659-1.591l-4.09-4.09a2.25 2.25 0 0 0-3.182 0l-4.09 4.09a2.25 2.25 0 0 0-.659 1.59v.069M21.75 9l-7.304-4.178a2.25 2.25 0 0 0-2.892 0L4.25 9"
                        />
                    </svg>
                    <p class="mt-3 text-sm font-medium text-slate-500">No request letters yet</p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{
                            user.role === 'regional'
                                ? 'No request letters have been endorsed from provincial level for this incident.'
                                : 'Request letters will appear here once uploaded.'
                        }}
                    </p>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(letters, lguId) in requestLettersByLgu"
                        :key="lguId"
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="border-b border-slate-200 bg-slate-50/50 px-6 py-3.5">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"
                                    />
                                </svg>
                                {{ letters[0]?.city_municipality?.name ?? 'Unknown LGU' }}
                                <span v-if="letters[0]?.city_municipality?.province" class="font-normal text-slate-400">
                                    &middot; {{ letters[0].city_municipality.province.name }}
                                </span>
                                <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 tabular-nums">
                                    {{ letters.length }} letter{{ letters.length !== 1 ? 's' : '' }}
                                </span>
                            </h3>
                        </div>
                        <div class="space-y-4 p-4">
                            <RequestLetterCard
                                v-for="letter in letters"
                                :key="letter.id"
                                :letter="letter"
                                :incident-id="incident.id"
                                :augmentation-types="augmentationTypes"
                                @endorse="openEndorse"
                                @approve="openApprove"
                                @delivery="openDelivery"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Modal -->
        <Teleport to="body">
            <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showUploadModal = false">
                <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50">
                            <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"
                                />
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Upload Request Letter</h3>
                            <p class="text-sm text-slate-500">Attach a PDF with augmentation items and quantities.</p>
                        </div>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="submitUpload">
                        <!-- File Input -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700">PDF File</label>
                            <input
                                type="file"
                                accept=".pdf"
                                class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                @change="handleFileChange"
                            />
                            <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-rose-600">{{ uploadForm.errors.file }}</p>
                        </div>

                        <!-- Augmentation Items -->
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <label class="block text-sm font-medium text-slate-700">Augmentation Items</label>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:text-indigo-800" @click="addAugmentationItem">
                                    + Add Item
                                </button>
                            </div>
                            <p v-if="totalAffectedFamilies > 0" class="mb-2 text-xs text-slate-500">
                                Quantity per item must not exceed
                                <span class="font-semibold text-slate-700">{{ totalAffectedFamilies.toLocaleString() }}</span>
                                total affected {{ pluralize(totalAffectedFamilies, 'family', 'families') }}.
                            </p>
                            <div v-for="(item, index) in uploadForm.augmentation_items" :key="index" class="mb-2 flex items-start gap-2">
                                <div class="flex-1">
                                    <select
                                        v-model="item.type"
                                        class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                    >
                                        <option value="" disabled>Select type</option>
                                        <option v-for="t in augmentationTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                                    </select>
                                    <p v-if="uploadForm.errors[`augmentation_items.${index}.type`]" class="mt-0.5 text-xs text-rose-600">
                                        {{ uploadForm.errors[`augmentation_items.${index}.type`] }}
                                    </p>
                                </div>
                                <div class="w-28">
                                    <input
                                        v-model="item.quantity"
                                        type="number"
                                        min="1"
                                        :max="totalAffectedFamilies || undefined"
                                        placeholder="Qty"
                                        class="block w-full rounded-lg border px-3 py-2 text-sm focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                        :class="
                                            Number(item.quantity) > totalAffectedFamilies && totalAffectedFamilies > 0
                                                ? 'border-rose-400 focus:border-rose-500'
                                                : 'border-slate-300 focus:border-indigo-500'
                                        "
                                    />
                                    <p
                                        v-if="Number(item.quantity) > totalAffectedFamilies && totalAffectedFamilies > 0"
                                        class="mt-0.5 text-xs text-rose-600"
                                    >
                                        Max: {{ totalAffectedFamilies.toLocaleString() }}
                                    </p>
                                    <p v-if="uploadForm.errors[`augmentation_items.${index}.quantity`]" class="mt-0.5 text-xs text-rose-600">
                                        {{ uploadForm.errors[`augmentation_items.${index}.quantity`] }}
                                    </p>
                                </div>
                                <button
                                    v-if="uploadForm.augmentation_items.length > 1"
                                    type="button"
                                    class="mt-2 text-xs text-rose-600 hover:text-rose-800"
                                    @click="removeAugmentationItem(index)"
                                >
                                    Remove
                                </button>
                            </div>
                            <p v-if="uploadForm.errors.augmentation_items" class="mt-1 text-xs text-rose-600">
                                {{ uploadForm.errors.augmentation_items }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                                @click="showUploadModal = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                                :disabled="uploadForm.processing || hasQuantityExceeded"
                            >
                                {{ uploadForm.processing ? 'Uploading...' : 'Upload' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Endorse Modal -->
            <EndorseModal
                v-if="endorseTarget"
                :incident-id="incident.id"
                :request-letter-id="endorseTarget.id"
                :items="endorseTarget.augmentation_items"
                :augmentation-types="augmentationTypes"
                @close="endorseTarget = null"
            />

            <!-- Approve Modal -->
            <ApproveModal
                v-if="approveTarget"
                :incident-id="incident.id"
                :request-letter-id="approveTarget.id"
                :items="approveTarget.augmentation_items"
                :augmentation-types="augmentationTypes"
                @close="approveTarget = null"
            />

            <!-- Delivery Modal -->
            <DeliveryModal
                v-if="deliveryTarget && deliveryTarget.ledger"
                :incident-id="incident.id"
                :request-letter-id="deliveryTarget.id"
                :ledger="deliveryTarget.ledger"
                :escort-users="escortUsers"
                :augmentation-types="augmentationTypes"
                :delivery-plan="deliveryTarget.delivery_plan ?? null"
                @close="deliveryTarget = null"
            />
        </Teleport>
    </AppLayout>
</template>
