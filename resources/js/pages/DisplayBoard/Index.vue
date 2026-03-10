<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
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

// Live clock
const now = ref(new Date());
let clockInterval: ReturnType<typeof setInterval>;
const clockDisplay = computed(() =>
    now.value.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).toUpperCase(),
);
const dateDisplay = computed(() => now.value.toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }));

// Animated number counter
const displayed = ref<Record<string, number>>({});
function animateValue(key: string, target: number): void {
    const start = displayed.value[key] ?? 0;
    if (start === target) return;
    const diff = target - start;
    const steps = 30;
    let step = 0;
    const timer = setInterval(() => {
        step++;
        displayed.value[key] = Math.round(start + (diff * step) / steps);
        if (step >= steps) {
            displayed.value[key] = target;
            clearInterval(timer);
        }
    }, 16);
}
function d(key: string, value: number): string {
    if (displayed.value[key] === undefined) displayed.value[key] = value;
    return (displayed.value[key] ?? value).toLocaleString();
}

watch(
    latestCutoff,
    (c) => {
        if (!c) return;
        const t = c.totals;
        Object.entries(t).forEach(([k, v]) => {
            if (typeof v === 'number') animateValue(k, v);
        });
        animateValue('lgu_count', c.reports.length);
    },
    { immediate: true },
);

// Polling: reload data every 15 seconds
let pollInterval: ReturnType<typeof setInterval>;
const lastRefresh = ref(new Date());
onMounted(() => {
    clockInterval = setInterval(() => (now.value = new Date()), 1000);
    pollInterval = setInterval(() => {
        router.reload({ only: ['cutoffs'] });
        lastRefresh.value = new Date();
    }, 15000);
});
onUnmounted(() => {
    clearInterval(pollInterval);
    clearInterval(clockInterval);
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

// Auto-hide header
const headerVisible = ref(true);
let hideTimeout: ReturnType<typeof setTimeout>;
function scheduleHide(): void {
    clearTimeout(hideTimeout);
    hideTimeout = setTimeout(() => (headerVisible.value = false), 3000);
}
function handleMouseMove(e: MouseEvent): void {
    if (e.clientY <= 48) {
        clearTimeout(hideTimeout);
        headerVisible.value = true;
    } else if (headerVisible.value) {
        scheduleHide();
    }
}
onMounted(() => {
    document.addEventListener('mousemove', handleMouseMove);
    scheduleHide();
});
onUnmounted(() => {
    document.removeEventListener('mousemove', handleMouseMove);
    clearTimeout(hideTimeout);
});

const closedPercent = computed(() => {
    if (!latestCutoff.value || latestCutoff.value.totals.inside_ec_count_cum === 0) return 0;
    const t = latestCutoff.value.totals;
    return Math.round(((t.inside_ec_count_cum - t.inside_ec_count_now) / t.inside_ec_count_cum) * 100);
});
</script>

<template>
    <Head :title="`Display Board${selectedIncident ? ` - ${selectedIncident.display_name ?? selectedIncident.name}` : ''}`" />

    <div class="display-board relative flex h-screen flex-col overflow-hidden text-white">
        <!-- Animated grid background -->
        <div class="bg-grid pointer-events-none absolute inset-0 opacity-[0.03]"></div>
        <!-- Radial glow -->
        <div
            class="pointer-events-none absolute top-0 left-1/2 h-[600px] w-[800px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-cyan-500/5 blur-3xl"
        ></div>

        <!-- Header Bar (auto-hide) -->
        <header
            class="absolute top-0 right-0 left-0 z-10 flex items-center justify-between border-b border-cyan-500/20 bg-slate-950/80 px-6 py-2.5 backdrop-blur-xl transition-transform duration-300"
            :class="headerVisible ? 'translate-y-0' : '-translate-y-full'"
            @mouseenter="
                clearTimeout(hideTimeout);
                headerVisible = true;
            "
            @mouseleave="scheduleHide()"
        >
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <div class="h-2 w-2 animate-pulse rounded-full bg-cyan-400 shadow-[0_0_8px_rgba(34,211,238,0.6)]"></div>
                    <h1 class="text-sm font-bold tracking-[0.2em] text-cyan-300 uppercase">DROMIC OPS</h1>
                </div>
                <select
                    class="rounded border border-cyan-500/30 bg-slate-900/80 px-3 py-1.5 text-xs text-cyan-100 transition-colors focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400/50 focus:outline-none"
                    :value="selectedIncident?.id ?? ''"
                    @change="selectIncident(Number(($event.target as HTMLSelectElement).value))"
                >
                    <option value="" disabled>Select Incident</option>
                    <option v-for="incident in incidents" :key="incident.id" :value="incident.id">
                        {{ incident.display_name ?? incident.name }}
                    </option>
                </select>
            </div>
            <div v-if="selectedIncident" class="absolute left-1/2 -translate-x-1/2 text-center">
                <div class="text-sm font-bold tracking-wider text-white">
                    {{ selectedIncident.display_name ?? selectedIncident.name }}
                </div>
                <div v-if="latestCutoff" class="text-[9px] tracking-wide text-slate-500">
                    {{ latestCutoff.label }} &middot; {{ formatDate(latestCutoff.date) }}, {{ latestCutoff.time }}
                </div>
            </div>
            <div class="flex items-center gap-5">
                <div class="font-mono text-xs tracking-wider text-cyan-400/80">
                    <span class="text-slate-500">{{ dateDisplay }}</span>
                    <span class="ml-2 text-cyan-300">{{ clockDisplay }}</span>
                </div>
                <button
                    class="rounded border border-slate-700 p-1.5 text-slate-500 transition-all hover:border-cyan-500/50 hover:text-cyan-400 hover:shadow-[0_0_10px_rgba(34,211,238,0.15)]"
                    title="Toggle fullscreen"
                    @click="toggleFullscreen"
                >
                    <svg v-if="!isFullscreen" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"
                        />
                    </svg>
                    <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"
                        />
                    </svg>
                </button>
            </div>
        </header>

        <!-- Incident Title (always visible) -->
        <div v-if="selectedIncident" class="relative shrink-0 pt-4 text-center">
            <h2 class="text-2xl font-bold tracking-wider text-white uppercase lg:text-3xl">
                {{ selectedIncident.display_name ?? selectedIncident.name }}
            </h2>
            <div class="text-xs tracking-wide text-slate-500">
                As of {{ dateDisplay }}, {{ clockDisplay }}
            </div>
        </div>

        <!-- Cards -->
        <main class="relative flex flex-1 flex-col overflow-auto p-5 pt-3">
            <template v-if="!selectedIncident">
                <div class="flex h-full items-center justify-center">
                    <p class="animate-pulse text-sm tracking-wider text-slate-600 uppercase">Select an incident to display data</p>
                </div>
            </template>

            <template v-else-if="!latestCutoff">
                <div class="flex h-full items-center justify-center">
                    <p class="animate-pulse text-sm tracking-wider text-slate-600 uppercase">Awaiting reports...</p>
                </div>
            </template>

            <template v-else>
                <div class="flex h-full flex-col gap-4">
                    <!-- Row 1: Total Affected + Evacuation Centers -->
                    <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-2">
                        <!-- Total Affected -->
                        <div class="card card-indigo">
                            <div class="card-header">
                                <span class="card-title text-indigo-400">Total Affected</span>
                                <svg class="card-icon text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto grid grid-cols-3 gap-3">
                                <div class="metric-cell">
                                    <div class="metric-label text-indigo-400/80">Families</div>
                                    <div class="metric-value text-indigo-200">
                                        {{ d('affected_families', latestCutoff.totals.affected_families) }}
                                    </div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-indigo-400/80">Persons</div>
                                    <div class="metric-value text-indigo-200">{{ d('affected_persons', latestCutoff.totals.affected_persons) }}</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-indigo-400/80">LGUs</div>
                                    <div class="metric-value text-indigo-200">{{ d('lgu_count', latestCutoff.reports.length) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Evacuation Centers -->
                        <div class="card card-emerald">
                            <div class="card-header">
                                <span class="card-title text-emerald-400">Evacuation Centers</span>
                                <svg class="card-icon text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto">
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="metric-cell">
                                        <div class="metric-label text-emerald-400/80">CUM</div>
                                        <div class="metric-value text-emerald-200">
                                            {{ d('inside_ec_count_cum', latestCutoff.totals.inside_ec_count_cum) }}
                                        </div>
                                    </div>
                                    <div class="metric-cell">
                                        <div class="metric-label text-emerald-400/80">NOW</div>
                                        <div class="metric-value text-emerald-200">
                                            {{ d('inside_ec_count_now', latestCutoff.totals.inside_ec_count_now) }}
                                        </div>
                                    </div>
                                </div>
                                <div v-if="latestCutoff.totals.inside_ec_count_cum > 0" class="mt-3">
                                    <div class="mb-1.5 flex items-center justify-between text-xs tracking-wider">
                                        <span class="text-slate-500 uppercase">Closed</span>
                                        <span class="font-mono text-emerald-400">{{ closedPercent }}%</span>
                                    </div>
                                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-800">
                                        <div
                                            class="progress-glow h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-300 transition-all duration-1000"
                                            :style="{ width: closedPercent + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Inside EC + Outside EC -->
                    <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-2">
                        <!-- Inside EC -->
                        <div class="card card-sky">
                            <div class="card-header">
                                <span class="card-title text-sky-400">Inside Evacuation Centers</span>
                                <svg class="card-icon text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto grid grid-cols-2 gap-3">
                                <div class="metric-cell">
                                    <div class="metric-label text-sky-400/80">Families</div>
                                    <div class="mt-2 grid grid-cols-2 gap-3 text-center">
                                        <div>
                                            <div class="sub-label">Cum</div>
                                            <div class="sub-value text-sky-200">
                                                {{ d('inside_ec_families_cum', latestCutoff.totals.inside_ec_families_cum) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="sub-label">Now</div>
                                            <div class="sub-value text-sky-200">
                                                {{ d('inside_ec_families_now', latestCutoff.totals.inside_ec_families_now) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-sky-400/80">Persons</div>
                                    <div class="mt-2 grid grid-cols-2 gap-3 text-center">
                                        <div>
                                            <div class="sub-label">Cum</div>
                                            <div class="sub-value text-sky-200">
                                                {{ d('inside_ec_persons_cum', latestCutoff.totals.inside_ec_persons_cum) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="sub-label">Now</div>
                                            <div class="sub-value text-sky-200">
                                                {{ d('inside_ec_persons_now', latestCutoff.totals.inside_ec_persons_now) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Outside EC -->
                        <div class="card card-amber">
                            <div class="card-header">
                                <span class="card-title text-amber-400">Outside Evacuation Centers</span>
                                <svg class="card-icon text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto grid grid-cols-2 gap-3">
                                <div class="metric-cell">
                                    <div class="metric-label text-amber-400/80">Families</div>
                                    <div class="mt-2 grid grid-cols-2 gap-3 text-center">
                                        <div>
                                            <div class="sub-label">Cum</div>
                                            <div class="sub-value text-amber-200">
                                                {{ d('outside_ec_families_cum', latestCutoff.totals.outside_ec_families_cum) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="sub-label">Now</div>
                                            <div class="sub-value text-amber-200">
                                                {{ d('outside_ec_families_now', latestCutoff.totals.outside_ec_families_now) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-amber-400/80">Persons</div>
                                    <div class="mt-2 grid grid-cols-2 gap-3 text-center">
                                        <div>
                                            <div class="sub-label">Cum</div>
                                            <div class="sub-value text-amber-200">
                                                {{ d('outside_ec_persons_cum', latestCutoff.totals.outside_ec_persons_cum) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="sub-label">Now</div>
                                            <div class="sub-value text-amber-200">
                                                {{ d('outside_ec_persons_now', latestCutoff.totals.outside_ec_persons_now) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Total Displaced + Non-IDPs -->
                    <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-2">
                        <!-- Total Displaced Population -->
                        <div class="card card-purple">
                            <div class="card-header">
                                <span class="card-title text-purple-400">Total Displaced Population</span>
                                <svg class="card-icon text-purple-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto grid grid-cols-2 gap-3">
                                <div class="metric-cell">
                                    <div class="metric-label text-purple-400/80">Families</div>
                                    <div class="mt-2 grid grid-cols-2 gap-3 text-center">
                                        <div>
                                            <div class="sub-label">Cum</div>
                                            <div class="sub-value text-purple-200">
                                                {{
                                                    (
                                                        latestCutoff.totals.inside_ec_families_cum + latestCutoff.totals.outside_ec_families_cum
                                                    ).toLocaleString()
                                                }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="sub-label">Now</div>
                                            <div class="sub-value text-purple-200">
                                                {{
                                                    (
                                                        latestCutoff.totals.inside_ec_families_now + latestCutoff.totals.outside_ec_families_now
                                                    ).toLocaleString()
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-purple-400/80">Persons</div>
                                    <div class="mt-2 grid grid-cols-2 gap-3 text-center">
                                        <div>
                                            <div class="sub-label">Cum</div>
                                            <div class="sub-value text-purple-200">
                                                {{
                                                    (
                                                        latestCutoff.totals.inside_ec_persons_cum + latestCutoff.totals.outside_ec_persons_cum
                                                    ).toLocaleString()
                                                }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="sub-label">Now</div>
                                            <div class="sub-value text-purple-200">
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
                        <div class="card card-violet">
                            <div class="card-header">
                                <span class="card-title text-violet-400">Non-IDPs</span>
                                <svg class="card-icon text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto grid grid-cols-2 gap-3">
                                <div class="metric-cell">
                                    <div class="metric-label text-violet-400/80">Families</div>
                                    <div class="metric-value text-violet-200">
                                        {{ d('non_idp_families_cum', latestCutoff.totals.non_idp_families_cum) }}
                                    </div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-violet-400/80">Persons</div>
                                    <div class="metric-value text-violet-200">
                                        {{ d('non_idp_persons_cum', latestCutoff.totals.non_idp_persons_cum) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Damaged Houses + Casualties + Infra/Agri -->
                    <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-3">
                        <!-- Damaged Houses -->
                        <div class="card card-rose">
                            <div class="card-header">
                                <span class="card-title text-rose-400">Damaged Houses</span>
                                <svg class="card-icon text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto grid grid-cols-3 gap-3">
                                <div class="metric-cell">
                                    <div class="metric-label text-rose-400/80">Totally</div>
                                    <div class="metric-value-sm text-rose-200">{{ d('totally_damaged', latestCutoff.totals.totally_damaged) }}</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-rose-400/80">Partially</div>
                                    <div class="metric-value-sm text-rose-200">
                                        {{ d('partially_damaged', latestCutoff.totals.partially_damaged) }}
                                    </div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-rose-400/80">Est. Cost</div>
                                    <div class="metric-value-xs text-rose-200">{{ formatCurrency(latestCutoff.totals.estimated_cost) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Casualties -->
                        <div class="card card-red">
                            <div class="card-header">
                                <span class="card-title text-red-400">Casualties</span>
                                <svg class="card-icon text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto grid grid-cols-3 gap-3">
                                <div class="metric-cell">
                                    <div class="metric-label text-red-400/80">Injured</div>
                                    <div class="metric-value-sm text-red-200">
                                        {{ d('casualties_injured', latestCutoff.totals.casualties_injured) }}
                                    </div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-red-400/80">Missing</div>
                                    <div class="metric-value-sm text-red-200">
                                        {{ d('casualties_missing', latestCutoff.totals.casualties_missing) }}
                                    </div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-red-400/80">Dead</div>
                                    <div class="metric-value-sm text-red-200">{{ d('casualties_dead', latestCutoff.totals.casualties_dead) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Infrastructure & Agriculture -->
                        <div class="card card-orange">
                            <div class="card-header">
                                <span class="card-title text-orange-400">Infra &amp; Agri Damage</span>
                                <svg class="card-icon text-orange-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto grid grid-cols-2 gap-3">
                                <div class="metric-cell">
                                    <div class="metric-label text-orange-400/80">Infrastructure</div>
                                    <div class="metric-value-xs text-orange-200">{{ formatCurrency(latestCutoff.totals.infrastructure_cost) }}</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-orange-400/80">Agriculture</div>
                                    <div class="metric-value-xs text-orange-200">{{ formatCurrency(latestCutoff.totals.agriculture_cost) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 5: Pre-emptive & Stranded (conditional) -->
                    <div
                        v-if="latestCutoff.totals.preemptive_families > 0 || latestCutoff.totals.stranded_passengers > 0"
                        class="grid min-h-0 flex-1 grid-cols-1 gap-4 lg:grid-cols-2"
                    >
                        <div class="card card-teal">
                            <div class="card-header">
                                <span class="card-title text-teal-400">Pre-emptive Evacuation</span>
                                <svg class="card-icon text-teal-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.572-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto grid grid-cols-2 gap-3">
                                <div class="metric-cell">
                                    <div class="metric-label text-teal-400/80">Families</div>
                                    <div class="metric-value text-teal-200">
                                        {{ d('preemptive_families', latestCutoff.totals.preemptive_families) }}
                                    </div>
                                </div>
                                <div class="metric-cell">
                                    <div class="metric-label text-teal-400/80">Persons</div>
                                    <div class="metric-value text-teal-200">
                                        {{ d('preemptive_persons', latestCutoff.totals.preemptive_persons) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card card-cyan">
                            <div class="card-header">
                                <span class="card-title text-cyan-400">Stranded Passengers</span>
                                <svg class="card-icon text-cyan-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                                    />
                                </svg>
                            </div>
                            <div class="mt-auto">
                                <div class="metric-cell">
                                    <div class="metric-label text-cyan-400/80">Total</div>
                                    <div class="metric-value text-cyan-200">
                                        {{ d('stranded_passengers', latestCutoff.totals.stranded_passengers) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </main>
    </div>
</template>

<style scoped>
.display-board {
    background: radial-gradient(ellipse at 50% 0%, #0f172a 0%, #020617 100%);
}
.bg-grid {
    background-image:
        linear-gradient(rgba(56, 189, 248, 0.4) 1px, transparent 1px), linear-gradient(90deg, rgba(56, 189, 248, 0.4) 1px, transparent 1px);
    background-size: 60px 60px;
}

/* Card base */
.card {
    display: flex;
    flex-direction: column;
    border-radius: 0.75rem;
    border: 1px solid rgba(51, 65, 85, 0.5);
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(12px);
    padding: 1.25rem 1.5rem;
    transition:
        border-color 0.3s,
        box-shadow 0.3s;
}
.card:hover {
    border-color: rgba(100, 116, 139, 0.4);
}

/* Glow per color */
.card-indigo:hover {
    border-color: rgba(129, 140, 248, 0.3);
    box-shadow:
        0 0 20px rgba(129, 140, 248, 0.07),
        inset 0 1px 0 rgba(129, 140, 248, 0.1);
}
.card-emerald:hover {
    border-color: rgba(52, 211, 153, 0.3);
    box-shadow:
        0 0 20px rgba(52, 211, 153, 0.07),
        inset 0 1px 0 rgba(52, 211, 153, 0.1);
}
.card-sky:hover {
    border-color: rgba(56, 189, 248, 0.3);
    box-shadow:
        0 0 20px rgba(56, 189, 248, 0.07),
        inset 0 1px 0 rgba(56, 189, 248, 0.1);
}
.card-amber:hover {
    border-color: rgba(251, 191, 36, 0.3);
    box-shadow:
        0 0 20px rgba(251, 191, 36, 0.07),
        inset 0 1px 0 rgba(251, 191, 36, 0.1);
}
.card-purple:hover {
    border-color: rgba(192, 132, 252, 0.3);
    box-shadow:
        0 0 20px rgba(192, 132, 252, 0.07),
        inset 0 1px 0 rgba(192, 132, 252, 0.1);
}
.card-violet:hover {
    border-color: rgba(167, 139, 250, 0.3);
    box-shadow:
        0 0 20px rgba(167, 139, 250, 0.07),
        inset 0 1px 0 rgba(167, 139, 250, 0.1);
}
.card-rose:hover {
    border-color: rgba(251, 113, 133, 0.3);
    box-shadow:
        0 0 20px rgba(251, 113, 133, 0.07),
        inset 0 1px 0 rgba(251, 113, 133, 0.1);
}
.card-red:hover {
    border-color: rgba(248, 113, 113, 0.3);
    box-shadow:
        0 0 20px rgba(248, 113, 113, 0.07),
        inset 0 1px 0 rgba(248, 113, 113, 0.1);
}
.card-orange:hover {
    border-color: rgba(251, 146, 60, 0.3);
    box-shadow:
        0 0 20px rgba(251, 146, 60, 0.07),
        inset 0 1px 0 rgba(251, 146, 60, 0.1);
}
.card-teal:hover {
    border-color: rgba(45, 212, 191, 0.3);
    box-shadow:
        0 0 20px rgba(45, 212, 191, 0.07),
        inset 0 1px 0 rgba(45, 212, 191, 0.1);
}
.card-cyan:hover {
    border-color: rgba(34, 211, 238, 0.3);
    box-shadow:
        0 0 20px rgba(34, 211, 238, 0.07),
        inset 0 1px 0 rgba(34, 211, 238, 0.1);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}
.card-title {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.15em;
}
.card-icon {
    height: 1.5rem;
    width: 1.5rem;
    opacity: 0.5;
}

/* Metric cells */
.metric-cell {
    border-radius: 0.5rem;
    background: rgba(30, 41, 59, 0.5);
    padding: 0.75rem 1rem;
    text-align: center;
    border: 1px solid rgba(51, 65, 85, 0.3);
}
.metric-label {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.15em;
}
.metric-value {
    font-family: ui-monospace, SFMono-Regular, monospace;
    font-size: 2.25rem;
    font-weight: 800;
    line-height: 1.2;
    margin-top: 0.25rem;
}
.metric-value-sm {
    font-family: ui-monospace, SFMono-Regular, monospace;
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.2;
    margin-top: 0.25rem;
}
.metric-value-xs {
    font-family: ui-monospace, SFMono-Regular, monospace;
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.2;
    margin-top: 0.25rem;
}
.sub-label {
    font-size: 0.7rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgb(100, 116, 139);
}
.sub-value {
    font-family: ui-monospace, SFMono-Regular, monospace;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.3;
}

/* Progress bar glow */
.progress-glow {
    box-shadow: 0 0 8px rgba(52, 211, 153, 0.4);
}
</style>
