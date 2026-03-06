<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { computed, ref } from 'vue';
import ConsolidatedPrintTemplate from '@/components/reports/ConsolidatedPrintTemplate.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Incident } from '@/types/incident';
import type { Report } from '@/types/report';
import { pluralize } from '@/utils/pluralize';

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
                    <Link :href="`/incidents/${incident.id}`" class="text-sm text-slate-500 transition-colors hover:text-slate-700"
                        >&larr; Back to Incident</Link
                    >
                    <h1 class="text-2xl font-bold text-slate-900">Consolidated Report</h1>
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
            <div class="mb-6 border border-slate-200 bg-white p-6">
                <h2 class="text-lg font-semibold text-slate-900">{{ incident.display_name ?? incident.name }}</h2>
                <p v-if="incident.description" class="mt-1 text-sm text-slate-600">{{ incident.description }}</p>
                <p class="mt-2 text-sm text-slate-500">
                    <span class="font-medium">{{ cutoffs.length }}</span> cut-off period(s) recorded
                </p>
            </div>

            <!-- No data state -->
            <div v-if="cutoffs.length === 0" class="border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                {{ userRole === 'regional' ? 'No validated reports yet.' : 'No submitted or validated reports yet.' }}
            </div>

            <template v-else>
                <!-- Cut-off Selector -->
                <div class="mb-6 flex flex-wrap items-center gap-4">
                    <label for="cutoff-select" class="text-sm font-medium text-slate-700">Cut-off Period:</label>
                    <select
                        id="cutoff-select"
                        v-model.number="selectedIndex"
                        class="border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
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
                    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="border border-l-4 border-slate-200 border-l-indigo-500 bg-white p-4">
                            <div class="text-xs font-medium text-slate-500">Affected</div>
                            <div class="mt-1 text-xl font-semibold text-slate-900">
                                {{ selectedCutoff.totals.affected_families.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.affected_families, 'family', 'families') }} /
                                {{ selectedCutoff.totals.affected_persons.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.affected_persons, 'person', 'persons') }}
                            </div>
                        </div>
                        <div class="border border-l-4 border-slate-200 border-l-sky-500 bg-white p-4">
                            <div class="text-xs font-medium text-slate-500">Inside EC</div>
                            <div class="mt-1 text-sm text-slate-700">
                                <span class="font-semibold">CUM:</span>
                                {{ selectedCutoff.totals.inside_ec_families_cum.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.inside_ec_families_cum, 'family', 'families') }} /
                                {{ selectedCutoff.totals.inside_ec_persons_cum.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.inside_ec_persons_cum, 'person', 'persons') }}
                            </div>
                            <div class="text-sm text-slate-700">
                                <span class="font-semibold">NOW:</span>
                                {{ selectedCutoff.totals.inside_ec_families_now.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.inside_ec_families_now, 'family', 'families') }} /
                                {{ selectedCutoff.totals.inside_ec_persons_now.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.inside_ec_persons_now, 'person', 'persons') }}
                            </div>
                        </div>
                        <div class="border border-l-4 border-slate-200 border-l-sky-500 bg-white p-4">
                            <div class="text-xs font-medium text-slate-500">Outside EC</div>
                            <div class="mt-1 text-sm text-slate-700">
                                <span class="font-semibold">CUM:</span>
                                {{ selectedCutoff.totals.outside_ec_families_cum.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.outside_ec_families_cum, 'family', 'families') }} /
                                {{ selectedCutoff.totals.outside_ec_persons_cum.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.outside_ec_persons_cum, 'person', 'persons') }}
                            </div>
                            <div class="text-sm text-slate-700">
                                <span class="font-semibold">NOW:</span>
                                {{ selectedCutoff.totals.outside_ec_families_now.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.outside_ec_families_now, 'family', 'families') }} /
                                {{ selectedCutoff.totals.outside_ec_persons_now.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.outside_ec_persons_now, 'person', 'persons') }}
                            </div>
                        </div>
                        <div class="border border-l-4 border-slate-200 border-l-amber-500 bg-white p-4">
                            <div class="text-xs font-medium text-slate-500">Non-IDPs</div>
                            <div class="mt-1 text-sm text-slate-700">
                                {{ selectedCutoff.totals.non_idp_families_cum.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.non_idp_families_cum, 'family', 'families') }} /
                                {{ selectedCutoff.totals.non_idp_persons_cum.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.non_idp_persons_cum, 'person', 'persons') }}
                            </div>
                        </div>
                        <div class="border border-l-4 border-slate-200 border-l-rose-500 bg-white p-4">
                            <div class="text-xs font-medium text-slate-500">Damaged Houses</div>
                            <div class="mt-1 text-sm text-slate-700">
                                <span class="font-semibold">Totally:</span>
                                {{ selectedCutoff.totals.totally_damaged.toLocaleString() }} &middot;
                                <span class="font-semibold">Partially:</span>
                                {{ selectedCutoff.totals.partially_damaged.toLocaleString() }}
                            </div>
                            <div class="text-sm text-slate-700">
                                <span class="font-semibold">Est. Cost:</span> {{ formatCurrency(selectedCutoff.totals.estimated_cost) }}
                            </div>
                        </div>
                        <div class="border border-l-4 border-slate-200 border-l-red-500 bg-white p-4">
                            <div class="text-xs font-medium text-slate-500">Casualties</div>
                            <div class="mt-1 text-sm text-slate-700">
                                <span class="font-semibold">Injured:</span>
                                {{ selectedCutoff.totals.casualties_injured.toLocaleString() }} &middot;
                                <span class="font-semibold">Missing:</span>
                                {{ selectedCutoff.totals.casualties_missing.toLocaleString() }} &middot;
                                <span class="font-semibold">Dead:</span>
                                {{ selectedCutoff.totals.casualties_dead.toLocaleString() }}
                            </div>
                        </div>
                        <div class="border border-l-4 border-slate-200 border-l-orange-500 bg-white p-4">
                            <div class="text-xs font-medium text-slate-500">Infrastructure &amp; Agriculture Damage</div>
                            <div class="mt-1 text-sm text-slate-700">
                                <span class="font-semibold">Infra:</span>
                                {{ formatCurrency(selectedCutoff.totals.infrastructure_cost) }} &middot;
                                <span class="font-semibold">Agri:</span>
                                {{ formatCurrency(selectedCutoff.totals.agriculture_cost) }}
                            </div>
                        </div>
                        <div
                            v-if="selectedCutoff.totals.preemptive_families > 0 || selectedCutoff.totals.stranded_passengers > 0"
                            class="border border-l-4 border-slate-200 border-l-violet-500 bg-white p-4"
                        >
                            <div class="text-xs font-medium text-slate-500">Pre-emptive Evac &amp; Stranded</div>
                            <div class="mt-1 text-sm text-slate-700">
                                <span class="font-semibold">Pre-emptive:</span>
                                {{ selectedCutoff.totals.preemptive_families.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.preemptive_families, 'family', 'families') }} /
                                {{ selectedCutoff.totals.preemptive_persons.toLocaleString() }}
                                {{ pluralize(selectedCutoff.totals.preemptive_persons, 'person', 'persons') }}
                            </div>
                            <div class="text-sm text-slate-700">
                                <span class="font-semibold">Stranded Passengers:</span>
                                {{ selectedCutoff.totals.stranded_passengers.toLocaleString() }}
                            </div>
                        </div>
                    </div>
                </template>
            </template>
        </div>

        <!-- Print Template -->
        <div v-if="selectedCutoff" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 print:max-w-none print:p-0">
            <div class="border border-slate-200 bg-white p-8 print:border-none print:shadow-none">
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
