<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ReportForm from '@/components/reports/ReportForm.vue';
import { emptyAffectedArea, emptyAgeDistribution, emptyVulnerableSectors } from '@/composables/useReportCalculations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Province } from '@/types';
import type { Incident } from '@/types/incident';

const props = defineProps<{
    incident: Incident;
    reportNumber: string;
    reportType: string;
    sequenceNumber: number;
    previousReportId: number | null;
    prefillData: Record<string, any> | null;
    nextCutoff: { report_date: string; report_time: string };
    provinces: Province[];
}>();

const initialData = {
    report_date: props.nextCutoff.report_date,
    report_time: props.nextCutoff.report_time,
    situation_overview: props.prefillData?.situation_overview ?? '',
    affected_areas: props.prefillData?.affected_areas ?? [emptyAffectedArea()],
    inside_evacuation_centers: props.prefillData?.inside_evacuation_centers ?? [],
    age_distribution: props.prefillData?.age_distribution ?? emptyAgeDistribution(),
    vulnerable_sectors: props.prefillData?.vulnerable_sectors ?? emptyVulnerableSectors(),
    outside_evacuation_centers: props.prefillData?.outside_evacuation_centers ?? [],
    non_idps: props.prefillData?.non_idps ?? [],
    damaged_houses: props.prefillData?.damaged_houses ?? [],
    related_incidents: props.prefillData?.related_incidents ?? [],
    casualties_injured: props.prefillData?.casualties_injured ?? [],
    casualties_missing: props.prefillData?.casualties_missing ?? [],
    casualties_dead: props.prefillData?.casualties_dead ?? [],
    infrastructure_damages: props.prefillData?.infrastructure_damages ?? [],
    agriculture_damages: props.prefillData?.agriculture_damages ?? [],
    assistance_provided: props.prefillData?.assistance_provided ?? [],
    class_suspensions: props.prefillData?.class_suspensions ?? [],
    work_suspensions: props.prefillData?.work_suspensions ?? [],
    lifelines_roads_bridges: props.prefillData?.lifelines_roads_bridges ?? [],
    lifelines_power: props.prefillData?.lifelines_power ?? [],
    lifelines_water: props.prefillData?.lifelines_water ?? [],
    lifelines_communication: props.prefillData?.lifelines_communication ?? [],
    seaport_status: props.prefillData?.seaport_status ?? [],
    airport_status: props.prefillData?.airport_status ?? [],
    landport_status: props.prefillData?.landport_status ?? [],
    stranded_passengers: props.prefillData?.stranded_passengers ?? [],
    calamity_declarations: props.prefillData?.calamity_declarations ?? [],
    preemptive_evacuations: props.prefillData?.preemptive_evacuations ?? [],
    gaps_challenges: props.prefillData?.gaps_challenges ?? [],
    response_actions: props.prefillData?.response_actions ?? null,
    is_terminal: false,
};

function reportTypeLabel(type: string, seq: number): string {
    if (type === 'initial') return 'Initial Report';
    if (type === 'terminal') return 'Terminal Report';
    return `Progress Report No. ${seq}`;
}
</script>

<template>
    <AppLayout>
        <Head :title="`Create Report - ${incident.name}`" />
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-2 flex items-center space-x-4">
                <Link :href="`/incidents/${incident.id}`" class="text-sm text-slate-500 transition-colors hover:text-slate-700"
                    >&larr; Back to Incident</Link
                >
            </div>

            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ reportTypeLabel(reportType, sequenceNumber) }}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Incident: <span class="font-medium">{{ incident.name }}</span> &middot; Report #:
                    <span class="font-medium">{{ reportNumber }}</span>
                </p>
                <p v-if="prefillData" class="mt-1 text-sm text-indigo-600">Data pre-filled from previous report. Please review and update.</p>
            </div>

            <ReportForm
                :provinces="provinces"
                :initial-data="initialData"
                :submit-url="`/incidents/${incident.id}/reports`"
                method="post"
                :show-terminal-option="reportType === 'progress'"
                :incident-name="incident.name"
                :report-number="reportNumber"
            />
        </div>
    </AppLayout>
</template>
