<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PrintTemplate from '@/components/reports/PrintTemplate.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Report } from '@/types';
import type { Incident } from '@/types/incident';

type LguSettings = {
    signatory_1_name: string | null;
    signatory_1_designation: string | null;
    signatory_2_name: string | null;
    signatory_2_designation: string | null;
    signatory_3_name: string | null;
    signatory_3_designation: string | null;
    logo_url: string | null;
};

const props = defineProps<{
    incident: Incident;
    report: Report;
    lguSettings?: LguSettings | null;
    dromicLogoUrl?: string | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const canEdit = computed(
    () => user.value.role === 'lgu' && props.report.user_id === user.value.id && ['draft', 'returned'].includes(props.report.status),
);
const canValidate = computed(() => user.value.role === 'provincial' && props.report.status === 'for_validation');
const canReturn = computed(() => user.value.role === 'provincial' && props.report.status === 'for_validation');

const showReturnModal = ref(false);
const returnReason = ref('');
const returnProcessing = ref(false);

function validateReport() {
    if (confirm('Validate this report? This will mark it as validated.')) {
        router.post(`/incidents/${props.incident.id}/reports/${props.report.id}/validate`);
    }
}

function submitReturn() {
    returnProcessing.value = true;
    router.post(
        `/incidents/${props.incident.id}/reports/${props.report.id}/return`,
        { return_reason: returnReason.value },
        {
            onFinish: () => {
                returnProcessing.value = false;
                showReturnModal.value = false;
                returnReason.value = '';
            },
        },
    );
}

function printReport() {
    window.print();
}

function reportTypeLabel(type: string, seq: number): string {
    if (type === 'initial') return 'Initial Report';
    if (type === 'terminal') return 'Terminal Report';
    return `Progress Report No. ${seq}`;
}
</script>

<template>
    <AppLayout>
        <Head :title="report.report_number" />

        <!-- Screen controls (hidden on print) -->
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 print:hidden">
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Link :href="`/incidents/${incident.id}`" class="text-sm text-slate-500 transition-colors hover:text-slate-700"
                        >&larr; Back to Incident</Link
                    >
                    <h1 class="text-2xl font-bold text-slate-900">{{ report.report_number }}</h1>
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                        :class="{
                            'bg-sky-50 text-sky-700 ring-sky-600/20': report.report_type === 'initial',
                            'bg-amber-50 text-amber-700 ring-amber-600/20': report.report_type === 'progress',
                            'bg-emerald-50 text-emerald-700 ring-emerald-600/20': report.report_type === 'terminal',
                        }"
                    >
                        {{ reportTypeLabel(report.report_type, report.sequence_number) }}
                    </span>
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                        :class="{
                            'bg-amber-50 text-amber-700 ring-amber-600/20': report.status === 'draft',
                            'bg-sky-50 text-sky-700 ring-sky-600/20': report.status === 'for_validation',
                            'bg-emerald-50 text-emerald-700 ring-emerald-600/20': report.status === 'validated',
                            'bg-rose-50 text-rose-700 ring-rose-600/20': report.status === 'returned',
                        }"
                    >
                        {{ report.status === 'for_validation' ? 'For Validation' : report.status === 'returned' ? 'Returned' : report.status }}
                    </span>
                </div>
                <div class="flex space-x-3">
                    <Link
                        v-if="canEdit"
                        :href="`/incidents/${incident.id}/reports/${report.id}/edit`"
                        class="border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="canReturn"
                        class="bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-rose-700"
                        @click="showReturnModal = true"
                    >
                        Return Report
                    </button>
                    <button
                        v-if="canValidate"
                        class="bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700"
                        @click="validateReport"
                    >
                        Validate Report
                    </button>
                    <button
                        class="bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-700"
                        @click="printReport"
                    >
                        Print Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Return Reason Banner -->
        <div v-if="report.status === 'returned' && report.return_reason" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 print:hidden">
            <div class="mb-6 border-l-4 border-rose-400 bg-rose-50 p-4">
                <div class="flex">
                    <div>
                        <p class="text-sm font-semibold text-rose-800">Report Returned</p>
                        <p class="mt-1 text-sm text-rose-700">{{ report.return_reason }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Print Template -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 print:max-w-none print:p-0">
            <div class="border border-slate-200 bg-white p-8 print:border-none print:shadow-none">
                <PrintTemplate :report="report" :lgu-settings="lguSettings" :dromic-logo-url="dromicLogoUrl" />
            </div>
        </div>
        <!-- Return Modal -->
        <Teleport to="body">
            <div v-if="showReturnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showReturnModal = false">
                <div class="w-full max-w-md bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-slate-900">Return Report</h3>
                    <p class="mt-1 text-sm text-slate-500">Provide a reason for returning this report to the LGU.</p>
                    <textarea
                        v-model="returnReason"
                        rows="4"
                        class="mt-4 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                        placeholder="Reason for returning..."
                    />
                    <div class="mt-4 flex justify-end space-x-3">
                        <button
                            class="border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                            @click="showReturnModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            class="bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-rose-700 disabled:opacity-50"
                            :disabled="!returnReason.trim() || returnProcessing"
                            @click="submitReturn"
                        >
                            {{ returnProcessing ? 'Returning...' : 'Return Report' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style>
@media print {
    nav,
    .print\:hidden {
        display: none !important;
    }

    body {
        margin: 0;
        padding: 0;
    }
}
</style>
