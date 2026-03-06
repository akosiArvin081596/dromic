<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onUnmounted, ref, toRef, watch } from 'vue';
import { useReportCalculations } from '@/composables/useReportCalculations';
import type { Barangay, Province, ReportFormData } from '@/types';

import SectionAffectedAreas from './SectionAffectedAreas.vue';
import SectionAgeDistribution from './SectionAgeDistribution.vue';
import SectionAgricultureDamages from './SectionAgricultureDamages.vue';
import SectionAssistanceProvided from './SectionAssistanceProvided.vue';
import SectionCalamityDeclaration from './SectionCalamityDeclaration.vue';
import SectionCasualties from './SectionCasualties.vue';
import SectionClassSuspensions from './SectionClassSuspensions.vue';
import SectionDamagedHouses from './SectionDamagedHouses.vue';
import SectionGapsChallenges from './SectionGapsChallenges.vue';
import SectionInfrastructureDamages from './SectionInfrastructureDamages.vue';
import SectionInsideEC from './SectionInsideEC.vue';
import SectionLifelines from './SectionLifelines.vue';
import SectionNonIdp from './SectionNonIdp.vue';
import SectionOutsideEC from './SectionOutsideEC.vue';
import SectionPortStatus from './SectionPortStatus.vue';
import SectionPreemptiveEvacuation from './SectionPreemptiveEvacuation.vue';
import SectionRelatedIncidents from './SectionRelatedIncidents.vue';
import SectionResponseActions from './SectionResponseActions.vue';
import SectionStrandedPassengers from './SectionStrandedPassengers.vue';
import SectionVulnerableSectors from './SectionVulnerableSectors.vue';
import SectionWorkSuspensions from './SectionWorkSuspensions.vue';

const props = withDefaults(
    defineProps<{
        provinces: Province[];
        initialData: ReportFormData;
        submitUrl: string;
        method: 'post' | 'put';
        showTerminalOption?: boolean;
        incidentName?: string;
        reportNumber?: string;
    }>(),
    {
        showTerminalOption: false,
        incidentName: '',
        reportNumber: '',
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const isLgu = computed(() => user.value.role === 'lgu');

// Form persistence via localStorage
const STORAGE_KEY = `dromic-report-form:${window.location.pathname}`;
const hasRestoredData = ref(false);

let formInitialData: ReportFormData = { ...props.initialData };
const savedRaw = localStorage.getItem(STORAGE_KEY);
if (savedRaw) {
    try {
        const parsed = JSON.parse(savedRaw);
        // Always use latest cutoff values from server
        delete parsed.report_date;
        delete parsed.report_time;
        formInitialData = { ...formInitialData, ...parsed };
        hasRestoredData.value = true;
    } catch {
        localStorage.removeItem(STORAGE_KEY);
    }
}

const form = useForm<ReportFormData>(formInitialData);

// Auto-save form data to localStorage (debounced)
let saveTimeout: ReturnType<typeof setTimeout>;
watch(
    () => form.data(),
    (data) => {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            } catch {
                // localStorage full or unavailable
            }
        }, 1000);
    },
    { deep: true },
);

onUnmounted(() => clearTimeout(saveTimeout));

function clearSavedData() {
    localStorage.removeItem(STORAGE_KEY);
    hasRestoredData.value = false;
}

function discardSavedData() {
    clearSavedData();
    form.defaults(props.initialData).reset();
}

// For LGU users, use their assigned city_municipality_id
const userCityMunicipalityId = computed(() => (isLgu.value ? user.value.city_municipality_id : null));

const barangays = ref<Barangay[]>([]);

// For LGU users, fetch barangays using their assigned city_municipality_id
const activeCityMunicipalityId = computed(() => userCityMunicipalityId.value ?? null);

watch(
    activeCityMunicipalityId,
    async (newId) => {
        if (newId) {
            try {
                const response = await axios.get('/api/barangays', { params: { city_municipality_id: newId } });
                barangays.value = response.data;
            } catch {
                barangays.value = [];
            }
        } else {
            barangays.value = [];
        }
    },
    { immediate: true },
);

const formRef = toRef(() => form.data());
const calculations = useReportCalculations(formRef);

// Filter barangays for III sections: only barangays selected in Part II
const affectedBarangayNames = computed(() => form.affected_areas.map((a) => a.barangay).filter((name) => name !== ''));

const filteredBarangays = computed(() => barangays.value.filter((b) => affectedBarangayNames.value.includes(b.name)));

// Validation: combined III-A + III-B totals should not exceed Part II totals
const totalIDPFamiliesCum = computed(() => calculations.totalInsideECFamiliesCum.value + calculations.totalOutsideECFamiliesCum.value);

const totalIDPPersonsCum = computed(() => calculations.totalInsideECPersonsCum.value + calculations.totalOutsideECPersonsCum.value);

const totalIDPFamiliesNow = computed(() => calculations.totalInsideECFamiliesNow.value + calculations.totalOutsideECFamiliesNow.value);

const totalIDPPersonsNow = computed(() => calculations.totalInsideECPersonsNow.value + calculations.totalOutsideECPersonsNow.value);

const familiesCumExceedAffected = computed(
    () => totalIDPFamiliesCum.value > 0 && totalIDPFamiliesCum.value > calculations.totalAffectedFamilies.value,
);

const personsCumExceedAffected = computed(() => totalIDPPersonsCum.value > 0 && totalIDPPersonsCum.value > calculations.totalAffectedPersons.value);

const familiesExceedAffected = computed(() => totalIDPFamiliesNow.value > 0 && totalIDPFamiliesNow.value > calculations.totalAffectedFamilies.value);

const personsExceedAffected = computed(() => totalIDPPersonsNow.value > 0 && totalIDPPersonsNow.value > calculations.totalAffectedPersons.value);

// Remaining CUM capacity per section = total affected - CUM used by the OTHER section
// Non-IDPs is auto-calculated as the remainder, so it doesn't consume capacity
const remainingFamiliesCumForInsideEC = computed(() => calculations.totalAffectedFamilies.value - calculations.totalOutsideECFamiliesCum.value);
const remainingPersonsCumForInsideEC = computed(() => calculations.totalAffectedPersons.value - calculations.totalOutsideECPersonsCum.value);
const remainingFamiliesCumForOutsideEC = computed(() => calculations.totalAffectedFamilies.value - calculations.totalInsideECFamiliesCum.value);
const remainingPersonsCumForOutsideEC = computed(() => calculations.totalAffectedPersons.value - calculations.totalInsideECPersonsCum.value);

// Per-barangay affected area limits for CUM field warnings in III-A / III-B
const affectedAreaLimits = computed(() => {
    const map: Record<string, { families: number; persons: number }> = {};
    for (const area of form.affected_areas) {
        if (area.barangay) {
            map[area.barangay] = { families: area.families, persons: area.persons };
        }
    }
    return map;
});

// Frontend validation for required fields
const hasSituationOverview = computed(() => (form.situation_overview ?? '').trim().length > 0);
const hasValidAffectedAreas = computed(() => form.affected_areas.some((a) => a.barangay !== ''));

const canSubmit = computed(() => hasSituationOverview.value && hasValidAffectedAreas.value);

function submit(status: 'draft' | 'for_validation') {
    const request = form.transform((data) => ({ ...data, status }));
    const options = { onSuccess: () => clearSavedData() };
    if (props.method === 'post') {
        request.post(props.submitUrl, options);
    } else {
        request.put(props.submitUrl, options);
    }
}
</script>

<template>
    <form class="space-y-8" @submit.prevent>
        <!-- Validation errors banner -->
        <div v-if="Object.keys(form.errors).length > 0" class="border-l-4 border-rose-500 bg-rose-50 p-4 text-sm text-rose-900">
            <p class="font-medium">Please fix the following errors:</p>
            <ul class="mt-1 list-inside list-disc">
                <li v-for="(message, key) in form.errors" :key="key">{{ message }}</li>
            </ul>
        </div>

        <!-- Restored data notification -->
        <div v-if="hasRestoredData" class="flex items-center justify-between border-l-4 border-blue-500 bg-blue-50 p-4 text-sm text-blue-900">
            <p>Your previously entered data has been restored.</p>
            <button type="button" class="font-medium text-blue-700 underline hover:text-blue-900" @click="discardSavedData">
                Discard and start fresh
            </button>
        </div>

        <!-- Section I: Report Metadata -->
        <div class="border border-slate-200 bg-white p-6">
            <h3 class="mb-4 text-lg font-semibold text-slate-900">I. Report Information</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Report Number (read-only, auto-generated) -->
                <div v-if="reportNumber">
                    <label class="block text-sm font-medium text-slate-700">Report Number</label>
                    <div class="mt-1 bg-slate-50 px-3 py-2 text-sm text-slate-900">{{ reportNumber }}</div>
                </div>

                <!-- Incident Name (read-only, from incident) -->
                <div v-if="incidentName">
                    <label class="block text-sm font-medium text-slate-700">Incident Name</label>
                    <div class="mt-1 bg-slate-50 px-3 py-2 text-sm text-slate-900">{{ incidentName }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Cut-off Date</label>
                    <div class="mt-1 bg-slate-50 px-3 py-2 text-sm text-slate-900">{{ form.report_date }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Cut-off Time</label>
                    <div class="mt-1 bg-slate-50 px-3 py-2 text-sm text-slate-900">{{ form.report_time }}</div>
                    <p class="mt-1 text-xs text-slate-500">Auto-assigned based on server time</p>
                </div>

                <div class="md:col-span-2 lg:col-span-3">
                    <label class="block text-sm font-medium text-slate-700">Situation Overview <span class="text-rose-500">*</span></label>
                    <textarea
                        v-model="form.situation_overview"
                        rows="3"
                        maxlength="2000"
                        class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        placeholder="Brief overview of the situation..."
                    ></textarea>
                    <p class="mt-1 text-xs text-slate-500">{{ form.situation_overview?.length ?? 0 }}/2000 characters</p>
                </div>

                <!-- Terminal Report Option -->
                <div v-if="showTerminalOption" class="flex items-center space-x-2 md:col-span-2">
                    <input
                        id="is_terminal"
                        v-model="form.is_terminal"
                        type="checkbox"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label for="is_terminal" class="text-sm font-medium text-slate-700"
                        >Submit as Terminal Report (final report for this incident)</label
                    >
                </div>
            </div>
        </div>

        <!-- Section II: Affected Areas -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionAffectedAreas
                v-model:rows="form.affected_areas"
                :barangays="barangays"
                :total-families="calculations.totalAffectedFamilies.value"
                :total-persons="calculations.totalAffectedPersons.value"
            />
        </div>

        <!-- Section III Validation Warning -->
        <div
            v-if="familiesCumExceedAffected || personsCumExceedAffected || familiesExceedAffected || personsExceedAffected"
            class="border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-900"
        >
            <p class="font-medium">
                The total displaced families or persons inside and outside evacuation centers exceeds the total in Affected Areas:
            </p>
            <ul class="mt-1 list-inside list-disc">
                <li v-if="familiesCumExceedAffected">
                    Cumulative families: {{ totalIDPFamiliesCum.toLocaleString() }} displaced, but only
                    {{ calculations.totalAffectedFamilies.value.toLocaleString() }} affected
                </li>
                <li v-if="personsCumExceedAffected">
                    Cumulative persons: {{ totalIDPPersonsCum.toLocaleString() }} displaced, but only
                    {{ calculations.totalAffectedPersons.value.toLocaleString() }} affected
                </li>
                <li v-if="familiesExceedAffected">
                    Current families: {{ totalIDPFamiliesNow.toLocaleString() }} displaced, but only
                    {{ calculations.totalAffectedFamilies.value.toLocaleString() }} affected
                </li>
                <li v-if="personsExceedAffected">
                    Current persons: {{ totalIDPPersonsNow.toLocaleString() }} displaced, but only
                    {{ calculations.totalAffectedPersons.value.toLocaleString() }} affected
                </li>
            </ul>
        </div>

        <!-- Section III-A: Inside Evacuation Centers -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionInsideEC
                v-model:rows="form.inside_evacuation_centers"
                :all-barangays="barangays"
                :barangays="filteredBarangays"
                :affected-area-limits="affectedAreaLimits"
                :remaining-families-cum="remainingFamiliesCumForInsideEC"
                :remaining-persons-cum="remainingPersonsCumForInsideEC"
                :total-families-cum="calculations.totalInsideECFamiliesCum.value"
                :total-families-now="calculations.totalInsideECFamiliesNow.value"
                :total-persons-cum="calculations.totalInsideECPersonsCum.value"
                :total-persons-now="calculations.totalInsideECPersonsNow.value"
            />
        </div>

        <!-- Age Distribution -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionAgeDistribution
                v-model:distribution="form.age_distribution"
                :total-male-cum="calculations.totalAgeMaleCum.value"
                :total-male-now="calculations.totalAgeMaleNow.value"
                :total-female-cum="calculations.totalAgeFemaleCum.value"
                :total-female-now="calculations.totalAgeFemaleNow.value"
                :total-cum="calculations.totalAgeCum.value"
                :total-now="calculations.totalAgeNow.value"
            />
        </div>

        <!-- Vulnerable Sectors -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionVulnerableSectors
                v-model:sectors="form.vulnerable_sectors"
                :total-male-cum="calculations.totalVulnerableMaleCum.value"
                :total-male-now="calculations.totalVulnerableMaleNow.value"
                :total-female-cum="calculations.totalVulnerableFemaleCum.value"
                :total-female-now="calculations.totalVulnerableFemaleNow.value"
            />
        </div>

        <!-- Section III-B: Outside Evacuation Centers -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionOutsideEC
                v-model:rows="form.outside_evacuation_centers"
                :all-barangays="barangays"
                :barangays="filteredBarangays"
                :affected-area-limits="affectedAreaLimits"
                :remaining-families-cum="remainingFamiliesCumForOutsideEC"
                :remaining-persons-cum="remainingPersonsCumForOutsideEC"
                :total-families-cum="calculations.totalOutsideECFamiliesCum.value"
                :total-families-now="calculations.totalOutsideECFamiliesNow.value"
                :total-persons-cum="calculations.totalOutsideECPersonsCum.value"
                :total-persons-now="calculations.totalOutsideECPersonsNow.value"
            />
        </div>

        <!-- Section III-C: Non IDPs -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionNonIdp
                v-model:rows="form.non_idps"
                :affected-areas="form.affected_areas"
                :inside-e-cs="form.inside_evacuation_centers"
                :outside-e-cs="form.outside_evacuation_centers"
            />
        </div>

        <!-- Section IV: Damaged Houses -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionDamagedHouses
                v-model:rows="form.damaged_houses"
                :barangays="filteredBarangays"
                :total-totally-damaged="calculations.totalTotallyDamaged.value"
                :total-partially-damaged="calculations.totalPartiallyDamaged.value"
                :total-estimated-cost="calculations.totalEstimatedCost.value"
            />
        </div>

        <!-- Section V: Related Incidents -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionRelatedIncidents v-model:rows="form.related_incidents" :barangays="barangays" />
        </div>

        <!-- Section VI: Casualties -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionCasualties
                v-model:injured="form.casualties_injured"
                v-model:missing="form.casualties_missing"
                v-model:dead="form.casualties_dead"
                :barangays="barangays"
            />
        </div>

        <!-- Section VII: Damages to Infrastructure -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionInfrastructureDamages v-model:rows="form.infrastructure_damages" :barangays="barangays" />
        </div>

        <!-- Section VIII: Damage & Losses to Agriculture -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionAgricultureDamages v-model:rows="form.agriculture_damages" />
        </div>

        <!-- Section IX: Status of Assistance Provided -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionAssistanceProvided v-model:rows="form.assistance_provided" />
        </div>

        <!-- Section X: Class Suspension -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionClassSuspensions v-model:rows="form.class_suspensions" :barangays="barangays" />
        </div>

        <!-- Section XI: Work Suspension -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionWorkSuspensions v-model:rows="form.work_suspensions" />
        </div>

        <!-- Section XII: Status of Lifelines -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionLifelines
                v-model:roads-bridges="form.lifelines_roads_bridges"
                v-model:power="form.lifelines_power"
                v-model:water="form.lifelines_water"
                v-model:communication="form.lifelines_communication"
                :barangays="barangays"
            />
        </div>

        <!-- Section XIII: Status of Seaports -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionPortStatus v-model:rows="form.seaport_status" title="Status of Seaports" section-number="XIII" />
        </div>

        <!-- Section XIV: Status of Airports -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionPortStatus v-model:rows="form.airport_status" title="Status of Airports" section-number="XIV" />
        </div>

        <!-- Section XV: Status of Landports -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionPortStatus v-model:rows="form.landport_status" title="Status of Landports" section-number="XV" />
        </div>

        <!-- Section XVI: Stranded Passengers/Cargoes -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionStrandedPassengers v-model:rows="form.stranded_passengers" />
        </div>

        <!-- Section XVII: Declaration of State of Calamity -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionCalamityDeclaration v-model:rows="form.calamity_declarations" :barangays="barangays" />
        </div>

        <!-- Section XVIII: Pre-emptive Evacuation -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionPreemptiveEvacuation v-model:rows="form.preemptive_evacuations" :barangays="barangays" />
        </div>

        <!-- Section XIX: Gaps/Challenges -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionGapsChallenges v-model:rows="form.gaps_challenges" />
        </div>

        <!-- Section XX: Response Actions -->
        <div class="border border-slate-200 bg-white p-6">
            <SectionResponseActions v-model:response-actions="form.response_actions" />
        </div>

        <!-- Submit -->
        <div v-if="!canSubmit" class="border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">Please complete the required fields before submitting:</p>
            <ul class="mt-1 list-inside list-disc">
                <li v-if="!hasSituationOverview">Provide a Situation Overview.</li>
                <li v-if="!hasValidAffectedAreas">Select at least one barangay in Affected Areas.</li>
            </ul>
        </div>
        <div class="flex items-center justify-end space-x-4">
            <button
                type="button"
                class="border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                @click="$inertia.visit(submitUrl.replace(/\/reports.*/, ''))"
            >
                Cancel
            </button>
            <button
                type="button"
                class="border border-slate-300 bg-white px-6 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 disabled:opacity-50"
                :disabled="form.processing || !canSubmit"
                @click="submit('draft')"
            >
                {{ form.processing ? 'Saving...' : 'Save as Draft' }}
            </button>
            <button
                type="button"
                class="bg-indigo-600 px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                :disabled="form.processing || !canSubmit"
                @click="submit('for_validation')"
            >
                {{ form.processing ? 'Submitting...' : 'Submit Report' }}
            </button>
        </div>
    </form>
</template>
