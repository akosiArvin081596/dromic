<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Incident } from '@/types/incident';
import { pluralize } from '@/utils/pluralize';

type AugmentationItem = {
    type: string;
    approved: number;
    delivered: number;
    delivery_percent: number;
};

type LguAugmentation = {
    city_municipality_id: number;
    city_municipality_name: string;
    items: AugmentationItem[];
};

type ProvinceAugmentation = {
    province_id: number;
    province_name: string;
    items: AugmentationItem[];
    lgus: LguAugmentation[];
};

type ImpactSummary = {
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
    active_evacuation_centers: number;
    total_evacuation_centers: number;
    closed_evacuation_centers: number;
    closed_ec_percent: number;
    [key: string]: number;
};

type DashboardData = {
    impact: ImpactSummary;
    augmentation: ProvinceAugmentation[];
};

const props = defineProps<{
    incidents: Incident[];
    selectedIncidentId: number | null;
    dashboardData: DashboardData | null;
}>();

const page = usePage();
const channel = `App.Models.User.${page.props.auth.user.id}`;

useEcho(channel, 'IncidentCreated', () => router.reload({ only: ['incidents'] }));
useEcho(channel, 'ReportValidated', () => router.reload({ only: ['dashboardData'] }));
useEcho(channel, 'RequestLetterApproved', () => router.reload({ only: ['dashboardData'] }));
useEcho(channel, 'DeliveryRecorded', () => router.reload({ only: ['dashboardData'] }));

const selectedId = ref(props.selectedIncidentId);
const expandedProvinces = ref<Set<number>>(new Set());

const selectedIncident = computed(() => props.incidents.find((i) => i.id === selectedId.value) ?? null);

function onIncidentChange() {
    if (selectedId.value) {
        router.visit(`/rd-dashboard/${selectedId.value}`, { preserveState: false });
    } else {
        router.visit('/rd-dashboard', { preserveState: false });
    }
}

function toggleProvince(provinceId: number) {
    if (expandedProvinces.value.has(provinceId)) {
        expandedProvinces.value.delete(provinceId);
    } else {
        expandedProvinces.value.add(provinceId);
    }
}

function formatType(type: string): string {
    return type
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function deliveryBarColor(percent: number): string {
    if (percent >= 100) return 'bg-emerald-500';
    if (percent >= 50) return 'bg-amber-400';
    return 'bg-rose-500';
}

function deliveryBadgeClass(percent: number): string {
    if (percent >= 100) return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    if (percent >= 50) return 'bg-amber-50 text-amber-700 ring-amber-600/20';
    return 'bg-rose-50 text-rose-700 ring-rose-600/20';
}
</script>

<template>
    <AppLayout>
        <Head title="Executive Dashboard" />

        <!-- Hero Header -->
        <div class="bg-gradient-to-r from-indigo-900 to-indigo-800 px-4 py-8 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <h1 class="text-2xl font-bold tracking-tight text-white">Executive Dashboard</h1>
                <p class="mt-1 text-sm text-indigo-200">Regional disaster impact overview and augmentation delivery tracking</p>

                <!-- Incident Selector -->
                <div class="mt-5 flex flex-wrap items-end gap-4">
                    <div class="w-full sm:w-auto sm:min-w-80">
                        <label for="incident-select" class="mb-1 block text-xs font-medium text-indigo-200">Active Incident</label>
                        <select
                            id="incident-select"
                            v-model="selectedId"
                            class="block w-full rounded-md border-0 bg-white/10 px-3 py-2.5 text-sm font-medium text-white shadow-sm ring-1 ring-white/20 backdrop-blur-sm focus:ring-2 focus:ring-white"
                            @change="onIncidentChange"
                        >
                            <option :value="null" class="text-slate-900">-- Select an incident --</option>
                            <option v-for="incident in incidents" :key="incident.id" :value="incident.id" class="text-slate-900">
                                {{ incident.display_name ?? incident.name }}
                            </option>
                        </select>
                    </div>
                    <span
                        v-if="selectedIncident"
                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                        :class="
                            selectedIncident.type === 'massive'
                                ? 'bg-rose-400/20 text-rose-100 ring-rose-300/30'
                                : 'bg-amber-400/20 text-amber-100 ring-amber-300/30'
                        "
                    >
                        {{ selectedIncident.type }}
                    </span>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Empty State -->
            <div v-if="!selectedIncident || !dashboardData" class="rounded-xl border border-dashed border-slate-300 bg-white p-16 text-center">
                <svg class="mx-auto h-16 w-16 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5"
                    />
                </svg>
                <h3 class="mt-4 text-sm font-semibold text-slate-900">No incident selected</h3>
                <p class="mt-1 text-sm text-slate-500">Choose an active incident above to view disaster impact and augmentation data.</p>
            </div>

            <!-- Dashboard Content -->
            <template v-if="selectedIncident && dashboardData">
                <!-- Incident Description -->
                <p v-if="selectedIncident.description" class="mb-6 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800">
                    {{ selectedIncident.description }}
                </p>

                <!-- Impact Summary Cards -->
                <div class="mb-8">
                    <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-400 uppercase">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
                            />
                        </svg>
                        Impact Summary
                    </h3>
                    <!-- Top row: Total Affected + Evacuation Centers -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Total Affected -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total Affected</span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50">
                                    <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                        />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div class="rounded-lg bg-indigo-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-indigo-400 uppercase">Families</div>
                                    <div class="mt-1 text-2xl font-bold text-indigo-900">
                                        {{ dashboardData.impact.affected_families.toLocaleString() }}
                                    </div>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Persons</div>
                                    <div class="mt-1 text-2xl font-bold text-slate-900">
                                        {{ dashboardData.impact.affected_persons.toLocaleString() }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Evacuation Centers -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Evacuation Centers</span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50">
                                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"
                                        />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-3">
                                <div class="rounded-lg bg-emerald-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-emerald-500 uppercase">Active</div>
                                    <div class="mt-1 text-2xl font-bold text-emerald-900">
                                        {{ dashboardData.impact.active_evacuation_centers.toLocaleString() }}
                                    </div>
                                </div>
                                <div class="rounded-lg bg-teal-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-teal-500 uppercase">Closed</div>
                                    <div class="mt-1 text-2xl font-bold text-teal-900">
                                        {{ dashboardData.impact.closed_evacuation_centers.toLocaleString() }}
                                    </div>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Total</div>
                                    <div class="mt-1 text-2xl font-bold text-slate-900">
                                        {{ dashboardData.impact.total_evacuation_centers.toLocaleString() }}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="flex items-center justify-between text-[11px] text-slate-500">
                                    <span>{{ dashboardData.impact.closed_ec_percent }}% closed</span>
                                    <span>{{ dashboardData.impact.total_evacuation_centers.toLocaleString() }} total</span>
                                </div>
                                <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                                    <div
                                        class="h-full rounded-full bg-teal-500 transition-all duration-500"
                                        :style="{ width: `${Math.min(dashboardData.impact.closed_ec_percent, 100)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inside EC + Outside EC + Non-IDPs row -->
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <!-- Inside EC -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Inside Evacuation Centers</span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50">
                                    <svg class="h-4 w-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819"
                                        />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Cumulative</div>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <div>
                                            <div class="text-lg font-bold text-slate-900">
                                                {{ dashboardData.impact.inside_ec_families_cum.toLocaleString() }}
                                            </div>
                                            <div class="text-[11px] text-slate-500">
                                                {{ pluralize(dashboardData.impact.inside_ec_families_cum, 'family', 'families') }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-lg font-bold text-slate-900">
                                                {{ dashboardData.impact.inside_ec_persons_cum.toLocaleString() }}
                                            </div>
                                            <div class="text-[11px] text-slate-500">
                                                {{ pluralize(dashboardData.impact.inside_ec_persons_cum, 'person', 'persons') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-lg bg-sky-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-sky-600 uppercase">Now Serving</div>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <div>
                                            <div class="text-lg font-bold text-sky-900">
                                                {{ dashboardData.impact.inside_ec_families_now.toLocaleString() }}
                                            </div>
                                            <div class="text-[11px] text-sky-600/70">
                                                {{ pluralize(dashboardData.impact.inside_ec_families_now, 'family', 'families') }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-lg font-bold text-sky-900">
                                                {{ dashboardData.impact.inside_ec_persons_now.toLocaleString() }}
                                            </div>
                                            <div class="text-[11px] text-sky-600/70">
                                                {{ pluralize(dashboardData.impact.inside_ec_persons_now, 'person', 'persons') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Outside EC -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Outside Evacuation Centers</span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50">
                                    <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
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
                                <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Cumulative</div>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <div>
                                            <div class="text-lg font-bold text-slate-900">
                                                {{ dashboardData.impact.outside_ec_families_cum.toLocaleString() }}
                                            </div>
                                            <div class="text-[11px] text-slate-500">
                                                {{ pluralize(dashboardData.impact.outside_ec_families_cum, 'family', 'families') }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-lg font-bold text-slate-900">
                                                {{ dashboardData.impact.outside_ec_persons_cum.toLocaleString() }}
                                            </div>
                                            <div class="text-[11px] text-slate-500">
                                                {{ pluralize(dashboardData.impact.outside_ec_persons_cum, 'person', 'persons') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="rounded-lg bg-amber-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-amber-600 uppercase">Now Serving</div>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <div>
                                            <div class="text-lg font-bold text-amber-900">
                                                {{ dashboardData.impact.outside_ec_families_now.toLocaleString() }}
                                            </div>
                                            <div class="text-[11px] text-amber-600/70">
                                                {{ pluralize(dashboardData.impact.outside_ec_families_now, 'family', 'families') }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-lg font-bold text-amber-900">
                                                {{ dashboardData.impact.outside_ec_persons_now.toLocaleString() }}
                                            </div>
                                            <div class="text-[11px] text-amber-600/70">
                                                {{ pluralize(dashboardData.impact.outside_ec_persons_now, 'person', 'persons') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Non-IDPs -->
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Non-IDPs</span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50">
                                    <svg class="h-4 w-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                                        />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div class="rounded-lg bg-violet-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-violet-400 uppercase">Families</div>
                                    <div class="mt-1 text-2xl font-bold text-violet-900">
                                        {{ dashboardData.impact.non_idp_families_cum.toLocaleString() }}
                                    </div>
                                </div>
                                <div class="rounded-lg bg-slate-50 px-3 py-2.5">
                                    <div class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Persons</div>
                                    <div class="mt-1 text-2xl font-bold text-slate-900">
                                        {{ dashboardData.impact.non_idp_persons_cum.toLocaleString() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Augmentation Delivery -->
                <div>
                    <h3 class="mb-4 flex items-center gap-2 text-xs font-semibold tracking-wider text-slate-400 uppercase">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"
                            />
                        </svg>
                        Augmentation Delivery
                    </h3>

                    <div
                        v-if="dashboardData.augmentation.length === 0"
                        class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center"
                    >
                        <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"
                            />
                        </svg>
                        <p class="mt-3 text-sm text-slate-500">No approved augmentation requests for this incident yet.</p>
                    </div>

                    <div v-else class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="px-5 py-3.5 text-center text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                        Province / LGU
                                    </th>
                                    <th class="px-4 py-3.5 text-center text-xs font-semibold tracking-wider text-slate-500 uppercase">Type</th>
                                    <th class="px-4 py-3.5 text-center text-xs font-semibold tracking-wider text-slate-500 uppercase">Approved</th>
                                    <th class="px-4 py-3.5 text-center text-xs font-semibold tracking-wider text-slate-500 uppercase">Delivered</th>
                                    <th class="w-44 px-4 py-3.5 text-center text-xs font-semibold tracking-wider text-slate-500 uppercase">
                                        Progress
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template v-for="province in dashboardData.augmentation" :key="province.province_id">
                                    <!-- Province Header Row(s) -->
                                    <tr
                                        v-for="(item, idx) in province.items"
                                        :key="`p-${province.province_id}-${item.type}`"
                                        class="cursor-pointer bg-slate-50/60 transition-colors hover:bg-indigo-50/50"
                                        @click="toggleProvince(province.province_id)"
                                    >
                                        <td v-if="idx === 0" class="px-5 py-3 text-sm font-semibold text-slate-900" :rowspan="province.items.length">
                                            <div class="flex items-center gap-2">
                                                <svg
                                                    class="h-4 w-4 shrink-0 text-indigo-400 transition-transform duration-200"
                                                    :class="{ 'rotate-90': expandedProvinces.has(province.province_id) }"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2.5"
                                                    stroke="currentColor"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                </svg>
                                                {{ province.province_name }}
                                                <span
                                                    class="shrink-0 rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-medium whitespace-nowrap text-slate-600"
                                                >
                                                    {{ province.lgus.length }} LGU{{ province.lgus.length !== 1 ? 's' : '' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ formatType(item.type) }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-slate-700 tabular-nums">
                                            {{ item.approved.toLocaleString() }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm text-slate-700 tabular-nums">
                                            {{ item.delivered.toLocaleString() }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-2.5">
                                                <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-200">
                                                    <div
                                                        class="h-full rounded-full transition-all duration-500"
                                                        :class="deliveryBarColor(item.delivery_percent)"
                                                        :style="{ width: `${Math.min(item.delivery_percent, 100)}%` }"
                                                    ></div>
                                                </div>
                                                <span
                                                    class="inline-flex min-w-12 justify-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                                                    :class="deliveryBadgeClass(item.delivery_percent)"
                                                >
                                                    {{ item.delivery_percent }}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Expanded LGU Rows -->
                                    <template v-if="expandedProvinces.has(province.province_id)">
                                        <template v-for="lgu in province.lgus" :key="`l-${lgu.city_municipality_id}`">
                                            <tr
                                                v-for="(lguItem, lguIdx) in lgu.items"
                                                :key="`l-${lgu.city_municipality_id}-${lguItem.type}`"
                                                class="bg-white transition-colors hover:bg-slate-50/50"
                                            >
                                                <td v-if="lguIdx === 0" class="py-2.5 pr-4 pl-14 text-sm text-slate-600" :rowspan="lgu.items.length">
                                                    {{ lgu.city_municipality_name }}
                                                </td>
                                                <td class="px-4 py-2.5 text-sm text-slate-500">{{ formatType(lguItem.type) }}</td>
                                                <td class="px-4 py-2.5 text-right text-sm text-slate-500 tabular-nums">
                                                    {{ lguItem.approved.toLocaleString() }}
                                                </td>
                                                <td class="px-4 py-2.5 text-right text-sm text-slate-500 tabular-nums">
                                                    {{ lguItem.delivered.toLocaleString() }}
                                                </td>
                                                <td class="px-4 py-2.5">
                                                    <div class="flex items-center justify-end gap-2.5">
                                                        <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-200">
                                                            <div
                                                                class="h-full rounded-full transition-all duration-500"
                                                                :class="deliveryBarColor(lguItem.delivery_percent)"
                                                                :style="{ width: `${Math.min(lguItem.delivery_percent, 100)}%` }"
                                                            ></div>
                                                        </div>
                                                        <span class="min-w-12 text-right text-xs text-slate-500 tabular-nums">
                                                            {{ lguItem.delivery_percent }}%
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
