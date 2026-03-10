<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
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
    incidents: Pick<Incident, 'id' | 'name' | 'display_name'>[];
    selectedIncident: Incident | null;
    cutoffs: Cutoff[];
}>();

const latestCutoff = computed(() => (props.cutoffs.length > 0 ? props.cutoffs[props.cutoffs.length - 1] : null));

function selectIncident(id: number): void {
    router.get(`/display-board/${id}`, {}, { preserveState: false });
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
}

function formatDate(date: string): string {
    return new Date(date + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Polling: reload data every 15 seconds
let pollInterval: ReturnType<typeof setInterval>;
onMounted(() => {
    pollInterval = setInterval(() => {
        router.reload({ only: ['cutoffs'] });
    }, 15000);
});
onUnmounted(() => {
    clearInterval(pollInterval);
});

// Fullscreen toggle
const isFullscreen = ref(false);
function toggleFullscreen(): void {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
        isFullscreen.value = true;
    } else {
        document.exitFullscreen();
        isFullscreen.value = false;
    }
}

function handleFullscreenChange(): void {
    isFullscreen.value = !!document.fullscreenElement;
}

onMounted(() => document.addEventListener('fullscreenchange', handleFullscreenChange));
onUnmounted(() => document.removeEventListener('fullscreenchange', handleFullscreenChange));
</script>

<template>
    <Head :title="`Display Board${selectedIncident ? ` - ${selectedIncident.display_name ?? selectedIncident.name}` : ''}`" />

    <div class="flex min-h-screen flex-col bg-slate-900 text-white">
        <!-- Header Bar -->
        <header class="flex shrink-0 items-center justify-between border-b border-slate-700 bg-slate-800 px-6 py-3">
            <div class="flex items-center gap-4">
                <h1 class="text-lg font-bold tracking-wide text-white uppercase">DROMIC Display Board</h1>
                <select
                    class="rounded-lg border border-slate-600 bg-slate-700 px-3 py-1.5 text-sm text-white focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                    :value="selectedIncident?.id ?? ''"
                    @change="selectIncident(Number(($event.target as HTMLSelectElement).value))"
                >
                    <option value="" disabled>Select Incident</option>
                    <option v-for="incident in incidents" :key="incident.id" :value="incident.id">
                        {{ incident.display_name ?? incident.name }}
                    </option>
                </select>
            </div>
            <div class="flex items-center gap-3">
                <span v-if="latestCutoff" class="text-xs text-slate-400">
                    {{ latestCutoff.label }} &mdash; {{ formatDate(latestCutoff.date) }}, {{ latestCutoff.time }}
                </span>
                <div class="h-2 w-2 animate-pulse rounded-full bg-emerald-400" title="Live — refreshes every 15s"></div>
                <button
                    class="rounded-lg border border-slate-600 p-1.5 text-slate-400 transition-colors hover:bg-slate-700 hover:text-white"
                    title="Toggle fullscreen"
                    @click="toggleFullscreen"
                >
                    <svg v-if="!isFullscreen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"
                        />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"
                        />
                    </svg>
                </button>
            </div>
        </header>

        <!-- Cards -->
        <main class="flex-1 overflow-auto p-6">
            <template v-if="!selectedIncident">
                <div class="flex h-full items-center justify-center">
                    <p class="text-lg text-slate-500">Select an incident to display data.</p>
                </div>
            </template>

            <template v-else-if="!latestCutoff">
                <div class="flex h-full items-center justify-center">
                    <p class="text-lg text-slate-500">No reports available yet for this incident.</p>
                </div>
            </template>

            <template v-else>
                <!-- Row 1: Total Affected + Evacuation Centers -->
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- Total Affected -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total Affected</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-900/40">
                                <svg class="h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <div class="rounded-lg bg-indigo-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-indigo-400 uppercase">Families</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.totals.affected_families.toLocaleString() }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-indigo-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-indigo-400 uppercase">Persons</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.totals.affected_persons.toLocaleString() }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-indigo-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-indigo-400 uppercase">LGUs</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.reports.length }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Evacuation Centers -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Evacuation Centers</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-900/40">
                                <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-emerald-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-emerald-400 uppercase">CUM</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.totals.inside_ec_count_cum.toLocaleString() }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-emerald-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-emerald-400 uppercase">NOW</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.totals.inside_ec_count_now.toLocaleString() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Inside EC + Outside EC -->
                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- Inside EC -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Inside Evacuation Centers</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-900/40">
                                <svg class="h-4 w-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-sky-900/30 px-3 py-2.5">
                                <div class="text-[10px] font-semibold tracking-wide text-sky-400 uppercase">Families</div>
                                <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Cum</div>
                                        <div class="text-lg font-bold text-white">
                                            {{ latestCutoff.totals.inside_ec_families_cum.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Now</div>
                                        <div class="text-lg font-bold text-white">
                                            {{ latestCutoff.totals.inside_ec_families_now.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-lg bg-sky-900/30 px-3 py-2.5">
                                <div class="text-[10px] font-semibold tracking-wide text-sky-400 uppercase">Persons</div>
                                <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Cum</div>
                                        <div class="text-lg font-bold text-white">
                                            {{ latestCutoff.totals.inside_ec_persons_cum.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Now</div>
                                        <div class="text-lg font-bold text-white">
                                            {{ latestCutoff.totals.inside_ec_persons_now.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outside EC -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Outside Evacuation Centers</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-900/40">
                                <svg class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
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
                            <div class="rounded-lg bg-amber-900/30 px-3 py-2.5">
                                <div class="text-[10px] font-semibold tracking-wide text-amber-400 uppercase">Families</div>
                                <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Cum</div>
                                        <div class="text-lg font-bold text-white">
                                            {{ latestCutoff.totals.outside_ec_families_cum.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Now</div>
                                        <div class="text-lg font-bold text-white">
                                            {{ latestCutoff.totals.outside_ec_families_now.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-lg bg-amber-900/30 px-3 py-2.5">
                                <div class="text-[10px] font-semibold tracking-wide text-amber-400 uppercase">Persons</div>
                                <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Cum</div>
                                        <div class="text-lg font-bold text-white">
                                            {{ latestCutoff.totals.outside_ec_persons_cum.toLocaleString() }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Now</div>
                                        <div class="text-lg font-bold text-white">
                                            {{ latestCutoff.totals.outside_ec_persons_now.toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Total Displaced + Non-IDPs -->
                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <!-- Total Displaced Population -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total Displaced Population</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-900/40">
                                <svg class="h-4 w-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-purple-900/30 px-3 py-2.5">
                                <div class="text-[10px] font-semibold tracking-wide text-purple-400 uppercase">Families</div>
                                <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Cum</div>
                                        <div class="text-lg font-bold text-white">
                                            {{
                                                (
                                                    latestCutoff.totals.inside_ec_families_cum + latestCutoff.totals.outside_ec_families_cum
                                                ).toLocaleString()
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Now</div>
                                        <div class="text-lg font-bold text-white">
                                            {{
                                                (
                                                    latestCutoff.totals.inside_ec_families_now + latestCutoff.totals.outside_ec_families_now
                                                ).toLocaleString()
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-lg bg-purple-900/30 px-3 py-2.5">
                                <div class="text-[10px] font-semibold tracking-wide text-purple-400 uppercase">Persons</div>
                                <div class="mt-2 grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Cum</div>
                                        <div class="text-lg font-bold text-white">
                                            {{
                                                (
                                                    latestCutoff.totals.inside_ec_persons_cum + latestCutoff.totals.outside_ec_persons_cum
                                                ).toLocaleString()
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[10px] font-medium text-slate-500 uppercase">Now</div>
                                        <div class="text-lg font-bold text-white">
                                            {{
                                                (
                                                    latestCutoff.totals.inside_ec_persons_now + latestCutoff.totals.outside_ec_persons_now
                                                ).toLocaleString()
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Non-IDPs -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Non-IDPs</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-900/40">
                                <svg class="h-4 w-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-violet-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-violet-400 uppercase">Families</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.totals.non_idp_families_cum.toLocaleString() }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-violet-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-violet-400 uppercase">Persons</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.totals.non_idp_persons_cum.toLocaleString() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Damaged Houses + Casualties + Infra/Agri -->
                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <!-- Damaged Houses -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Damaged Houses</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-900/40">
                                <svg class="h-4 w-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <div class="rounded-lg bg-rose-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-rose-400 uppercase">Totally</div>
                                <div class="mt-1 text-lg font-bold text-white">
                                    {{ latestCutoff.totals.totally_damaged.toLocaleString() }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-rose-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-rose-400 uppercase">Partially</div>
                                <div class="mt-1 text-lg font-bold text-white">
                                    {{ latestCutoff.totals.partially_damaged.toLocaleString() }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-rose-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-rose-400 uppercase">Est. Cost</div>
                                <div class="mt-1 text-sm font-bold text-white">
                                    {{ formatCurrency(latestCutoff.totals.estimated_cost) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Casualties -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Casualties</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-900/40">
                                <svg class="h-4 w-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-3">
                            <div class="rounded-lg bg-red-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-red-400 uppercase">Injured</div>
                                <div class="mt-1 text-lg font-bold text-white">
                                    {{ latestCutoff.totals.casualties_injured.toLocaleString() }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-red-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-red-400 uppercase">Missing</div>
                                <div class="mt-1 text-lg font-bold text-white">
                                    {{ latestCutoff.totals.casualties_missing.toLocaleString() }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-red-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-red-400 uppercase">Dead</div>
                                <div class="mt-1 text-lg font-bold text-white">
                                    {{ latestCutoff.totals.casualties_dead.toLocaleString() }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Infrastructure & Agriculture Damage -->
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Infra &amp; Agri Damage</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-900/40">
                                <svg class="h-4 w-4 text-orange-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-orange-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-orange-400 uppercase">Infrastructure</div>
                                <div class="mt-1 text-sm font-bold text-white">
                                    {{ formatCurrency(latestCutoff.totals.infrastructure_cost) }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-orange-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-orange-400 uppercase">Agriculture</div>
                                <div class="mt-1 text-sm font-bold text-white">
                                    {{ formatCurrency(latestCutoff.totals.agriculture_cost) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 5: Pre-emptive & Stranded (conditional) -->
                <div
                    v-if="latestCutoff.totals.preemptive_families > 0 || latestCutoff.totals.stranded_passengers > 0"
                    class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2"
                >
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Pre-emptive Evacuation</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-900/40">
                                <svg class="h-4 w-4 text-teal-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.572-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-teal-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-teal-400 uppercase">Families</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.totals.preemptive_families.toLocaleString() }}
                                </div>
                            </div>
                            <div class="rounded-lg bg-teal-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-teal-400 uppercase">Persons</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.totals.preemptive_persons.toLocaleString() }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium tracking-wide text-slate-400 uppercase">Stranded Passengers</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-900/40">
                                <svg class="h-4 w-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                    />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="rounded-lg bg-cyan-900/30 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-semibold tracking-wide text-cyan-400 uppercase">Total</div>
                                <div class="mt-1 text-2xl font-bold text-white">
                                    {{ latestCutoff.totals.stranded_passengers.toLocaleString() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </main>
    </div>
</template>
