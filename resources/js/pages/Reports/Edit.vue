<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ReportForm from '@/components/reports/ReportForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Province, Report } from '@/types';
import type { Incident } from '@/types/incident';

const props = defineProps<{
    incident: Incident;
    report: Report;
    nextCutoff: { report_date: string; report_time: string };
    provinces: Province[];
}>();

const initialData = {
    report_date: props.nextCutoff.report_date,
    report_time: props.nextCutoff.report_time,
    situation_overview: props.report.situation_overview ?? '',
    affected_areas: props.report.affected_areas,
    inside_evacuation_centers: props.report.inside_evacuation_centers,
    age_distribution: props.report.age_distribution,
    vulnerable_sectors: props.report.vulnerable_sectors,
    outside_evacuation_centers: props.report.outside_evacuation_centers,
    non_idps: props.report.non_idps ?? [],
    damaged_houses: props.report.damaged_houses,
    related_incidents: props.report.related_incidents ?? [],
    casualties_injured: props.report.casualties_injured ?? [],
    casualties_missing: props.report.casualties_missing ?? [],
    casualties_dead: props.report.casualties_dead ?? [],
    infrastructure_damages: props.report.infrastructure_damages ?? [],
    agriculture_damages: props.report.agriculture_damages ?? [],
    assistance_provided: props.report.assistance_provided ?? [],
    class_suspensions: props.report.class_suspensions ?? [],
    work_suspensions: props.report.work_suspensions ?? [],
    lifelines_roads_bridges: props.report.lifelines_roads_bridges ?? [],
    lifelines_power: props.report.lifelines_power ?? [],
    lifelines_water: props.report.lifelines_water ?? [],
    lifelines_communication: props.report.lifelines_communication ?? [],
    seaport_status: props.report.seaport_status ?? [],
    airport_status: props.report.airport_status ?? [],
    landport_status: props.report.landport_status ?? [],
    stranded_passengers: props.report.stranded_passengers ?? [],
    calamity_declarations: props.report.calamity_declarations ?? [],
    preemptive_evacuations: props.report.preemptive_evacuations ?? [],
    gaps_challenges: props.report.gaps_challenges ?? [],
    response_actions: props.report.response_actions ?? null,
    is_terminal: props.report.report_type === 'terminal',
};
</script>

<template>
    <AppLayout>
        <Head :title="`Edit ${report.report_number}`" />
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-2 flex items-center space-x-4">
                <Link :href="`/incidents/${incident.id}/reports/${report.id}`" class="text-sm text-slate-500 transition-colors hover:text-slate-700">
                    &larr; Back to Report
                </Link>
            </div>
            <h1 class="mb-6 text-2xl font-bold text-slate-900">Edit Report: {{ report.report_number }}</h1>
            <ReportForm
                :provinces="provinces"
                :initial-data="initialData"
                :submit-url="`/incidents/${incident.id}/reports/${report.id}`"
                method="put"
                :incident-name="incident.name"
                :report-number="report.report_number"
            />
        </div>
    </AppLayout>
</template>
