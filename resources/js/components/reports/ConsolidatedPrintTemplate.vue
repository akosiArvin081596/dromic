<script setup lang="ts">
import { computed } from 'vue';
import type {
    AffectedArea,
    AgeDistribution,
    AgeGenderBreakdown,
    AgricultureDamage,
    AssistanceProvided,
    CalamityDeclaration,
    Casualty,
    ClassSuspension,
    DamagedHouse,
    GapChallenge,
    InfrastructureDamage,
    InsideEvacuationCenter,
    LifelineRoadBridge,
    LifelineUtility,
    NonIdp,
    OutsideEvacuationCenter,
    PortStatus,
    PreemptiveEvacuation,
    RelatedIncident,
    Report,
    StrandedPassenger,
    VulnerableSectors,
    WorkSuspension,
} from '@/types';
import type { Incident } from '@/types/incident';
import { pluralize } from '@/utils/pluralize';

type WithLgu<T> = T & { lgu: string; province: string };

const props = defineProps<{
    reports: Report[];
    incident: Incident;
    cutoffLabel: string;
    cutoffDate: string;
    cutoffTime: string;
    showProvince: boolean;
    dromicLogoUrl?: string | null;
}>();

function lguName(report: Report): string {
    return report.city_municipality?.name ?? 'Unknown';
}

function provinceName(report: Report): string {
    return report.city_municipality?.province?.name ?? 'Unknown';
}

function merge<T>(section: keyof Report): WithLgu<T>[] {
    const rows = props.reports.flatMap((r) => ((r[section] as T[]) ?? []).map((row) => ({ lgu: lguName(r), province: provinceName(r), ...row })));
    if (props.showProvince) {
        rows.sort((a, b) => a.province.localeCompare(b.province) || a.lgu.localeCompare(b.lgu));
    }
    return rows;
}

type ProvinceGroup<T> = { province: string; rows: WithLgu<T>[] };

function groupByProvince<T>(section: keyof Report): ProvinceGroup<T>[] {
    const rows = merge<T>(section);
    if (!rows.length) return [];
    if (!props.showProvince) return [{ province: '', rows }];
    const groups: ProvinceGroup<T>[] = [];
    let current = '';
    let bucket: WithLgu<T>[] = [];
    for (const row of rows) {
        if (row.province !== current) {
            if (bucket.length) groups.push({ province: current, rows: bucket });
            current = row.province;
            bucket = [row];
        } else {
            bucket.push(row);
        }
    }
    if (bucket.length) groups.push({ province: current, rows: bucket });
    return groups;
}

function sumNum(arr: Record<string, unknown>[], field: string): number {
    return arr.reduce((s, r) => s + Number(r[field] || 0), 0);
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
}

function isFirstProvince<T extends { province: string }>(rows: T[], idx: number): boolean {
    return idx === 0;
}

function isFirstLgu<T extends { lgu: string }>(rows: T[], idx: number): boolean {
    return idx === 0 || rows[idx].lgu !== rows[idx - 1].lgu;
}

const dateFormatted = computed(() => {
    if (!props.cutoffDate) return '';
    const d = new Date(props.cutoffDate + 'T00:00:00');
    const day = d.getDate();
    const month = d.toLocaleDateString('en-PH', { month: 'long' });
    const year = d.getFullYear();
    return `${day} ${month} ${year}`;
});

// --- Merged sections ---
const mergedAffectedAreas = computed(() => merge<AffectedArea>('affected_areas'));
const mergedInsideEC = computed(() => merge<InsideEvacuationCenter>('inside_evacuation_centers'));
const mergedOutsideEC = computed(() => merge<OutsideEvacuationCenter>('outside_evacuation_centers'));
const mergedNonIdps = computed(() => merge<NonIdp>('non_idps'));
const mergedDamagedHouses = computed(() => merge<DamagedHouse>('damaged_houses'));
const mergedRelatedIncidents = computed(() => merge<RelatedIncident>('related_incidents'));
const mergedCasualtiesInjured = computed(() => merge<Casualty>('casualties_injured'));
const mergedCasualtiesMissing = computed(() => merge<Casualty>('casualties_missing'));
const mergedCasualtiesDead = computed(() => merge<Casualty>('casualties_dead'));
const mergedInfrastructureDamages = computed(() => merge<InfrastructureDamage>('infrastructure_damages'));
const mergedAgricultureDamages = computed(() => merge<AgricultureDamage>('agriculture_damages'));
const mergedAssistanceProvided = computed(() => merge<AssistanceProvided>('assistance_provided'));
const mergedClassSuspensions = computed(() => merge<ClassSuspension>('class_suspensions'));
const mergedWorkSuspensions = computed(() => merge<WorkSuspension>('work_suspensions'));
const mergedLifelineRoads = computed(() => merge<LifelineRoadBridge>('lifelines_roads_bridges'));
const mergedLifelinePower = computed(() => merge<LifelineUtility>('lifelines_power'));
const mergedLifelineWater = computed(() => merge<LifelineUtility>('lifelines_water'));
const mergedLifelineComm = computed(() => merge<LifelineUtility>('lifelines_communication'));
const mergedSeaports = computed(() => merge<PortStatus>('seaport_status'));
const mergedAirports = computed(() => merge<PortStatus>('airport_status'));
const mergedLandports = computed(() => merge<PortStatus>('landport_status'));
const mergedStrandedPassengers = computed(() => merge<StrandedPassenger>('stranded_passengers'));
const mergedCalamityDeclarations = computed(() => merge<CalamityDeclaration>('calamity_declarations'));
const mergedPreemptiveEvacuations = computed(() => merge<PreemptiveEvacuation>('preemptive_evacuations'));
const mergedGapsChallenges = computed(() => merge<GapChallenge>('gaps_challenges'));

// --- Grouped sections ---
const groupedAffectedAreas = computed(() => groupByProvince<AffectedArea>('affected_areas'));
const groupedInsideEC = computed(() => groupByProvince<InsideEvacuationCenter>('inside_evacuation_centers'));
const groupedOutsideEC = computed(() => groupByProvince<OutsideEvacuationCenter>('outside_evacuation_centers'));
const groupedNonIdps = computed(() => groupByProvince<NonIdp>('non_idps'));
const groupedDamagedHouses = computed(() => groupByProvince<DamagedHouse>('damaged_houses'));
const groupedRelatedIncidents = computed(() => groupByProvince<RelatedIncident>('related_incidents'));
const groupedCasualtiesInjured = computed(() => groupByProvince<Casualty>('casualties_injured'));
const groupedCasualtiesMissing = computed(() => groupByProvince<Casualty>('casualties_missing'));
const groupedCasualtiesDead = computed(() => groupByProvince<Casualty>('casualties_dead'));
const groupedInfrastructureDamages = computed(() => groupByProvince<InfrastructureDamage>('infrastructure_damages'));
const groupedAgricultureDamages = computed(() => groupByProvince<AgricultureDamage>('agriculture_damages'));
const groupedAssistanceProvided = computed(() => groupByProvince<AssistanceProvided>('assistance_provided'));
const groupedClassSuspensions = computed(() => groupByProvince<ClassSuspension>('class_suspensions'));
const groupedWorkSuspensions = computed(() => groupByProvince<WorkSuspension>('work_suspensions'));
const groupedLifelineRoads = computed(() => groupByProvince<LifelineRoadBridge>('lifelines_roads_bridges'));
const groupedLifelinePower = computed(() => groupByProvince<LifelineUtility>('lifelines_power'));
const groupedLifelineWater = computed(() => groupByProvince<LifelineUtility>('lifelines_water'));
const groupedLifelineComm = computed(() => groupByProvince<LifelineUtility>('lifelines_communication'));
const groupedSeaports = computed(() => groupByProvince<PortStatus>('seaport_status'));
const groupedAirports = computed(() => groupByProvince<PortStatus>('airport_status'));
const groupedLandports = computed(() => groupByProvince<PortStatus>('landport_status'));
const groupedStrandedPassengers = computed(() => groupByProvince<StrandedPassenger>('stranded_passengers'));
const groupedCalamityDeclarations = computed(() => groupByProvince<CalamityDeclaration>('calamity_declarations'));
const groupedPreemptiveEvacuations = computed(() => groupByProvince<PreemptiveEvacuation>('preemptive_evacuations'));
const groupedGapsChallenges = computed(() => groupByProvince<GapChallenge>('gaps_challenges'));

// --- Totals ---
const totalAffectedFamilies = computed(() => sumNum(mergedAffectedAreas.value, 'families'));
const totalAffectedPersons = computed(() => sumNum(mergedAffectedAreas.value, 'persons'));

const totalInsideECFamiliesCum = computed(() => sumNum(mergedInsideEC.value, 'families_cum'));
const totalInsideECFamiliesNow = computed(() => sumNum(mergedInsideEC.value, 'families_now'));
const totalInsideECPersonsCum = computed(() => sumNum(mergedInsideEC.value, 'persons_cum'));
const totalInsideECPersonsNow = computed(() => sumNum(mergedInsideEC.value, 'persons_now'));

const totalOutsideECFamiliesCum = computed(() => sumNum(mergedOutsideEC.value, 'families_cum'));
const totalOutsideECFamiliesNow = computed(() => sumNum(mergedOutsideEC.value, 'families_now'));
const totalOutsideECPersonsCum = computed(() => sumNum(mergedOutsideEC.value, 'persons_cum'));
const totalOutsideECPersonsNow = computed(() => sumNum(mergedOutsideEC.value, 'persons_now'));

const totalIDPFamiliesCum = computed(() => totalInsideECFamiliesCum.value + totalOutsideECFamiliesCum.value);
const totalIDPPersonsCum = computed(() => totalInsideECPersonsCum.value + totalOutsideECPersonsCum.value);

const totalNonIdpFamiliesCum = computed(() => sumNum(mergedNonIdps.value, 'families_cum'));
const totalNonIdpPersonsCum = computed(() => sumNum(mergedNonIdps.value, 'persons_cum'));

const totalTotallyDamaged = computed(() => sumNum(mergedDamagedHouses.value, 'totally_damaged'));
const totalPartiallyDamaged = computed(() => sumNum(mergedDamagedHouses.value, 'partially_damaged'));
const totalEstimatedCost = computed(() => sumNum(mergedDamagedHouses.value, 'estimated_cost'));

const totalInfraCost = computed(() => sumNum(mergedInfrastructureDamages.value, 'estimated_cost'));
const totalAgriCost = computed(() => sumNum(mergedAgricultureDamages.value, 'estimated_cost'));

const totalPreemptiveFamilies = computed(() => sumNum(mergedPreemptiveEvacuations.value, 'families'));
const totalPreemptivePersons = computed(() => sumNum(mergedPreemptiveEvacuations.value, 'persons'));

const totalStrandedPassengers = computed(() => sumNum(mergedStrandedPassengers.value, 'passengers'));
const totalStrandedCargoes = computed(() => sumNum(mergedStrandedPassengers.value, 'rolling_cargoes'));
const totalStrandedVessels = computed(() => sumNum(mergedStrandedPassengers.value, 'vessels'));

// --- Aggregated Age Distribution (summed across all reports) ---
const ageGroupLabels: { key: keyof AgeDistribution; label: string }[] = [
    { key: '0-5', label: 'Infants/Toddlers/Pre-schoolers (0-5 yrs old)' },
    { key: '6-12', label: 'School Age (6-12 yrs old)' },
    { key: '13-17', label: 'Teenagers (13-17 yrs old)' },
    { key: '18-35', label: 'Young Adults (18-35 yrs old)' },
    { key: '36-59', label: 'Adults (36-59 yrs old)' },
    { key: '60-69', label: 'Senior Citizens (60-69 yrs old)' },
    { key: '70+', label: 'Elderly (70 yrs old and above)' },
];

const sectorLabels: { key: keyof VulnerableSectors; label: string }[] = [
    { key: 'Pregnant/Lactating', label: 'Pregnant' },
    { key: 'Solo Parent', label: 'Solo Parents' },
    { key: 'PWD', label: 'Persons with Disabilities (PWDs)' },
    { key: 'Indigenous People', label: 'Indigenous Peoples (IPs)' },
    { key: 'Senior Citizen', label: 'Senior Citizens' },
];

function sumAgeGroupField(key: keyof AgeDistribution, field: keyof AgeGenderBreakdown): number {
    return props.reports.reduce((sum, r) => {
        const group = r.age_distribution?.[key];
        return sum + Number(group?.[field] || 0);
    }, 0);
}

function sumAllAgeField(field: keyof AgeGenderBreakdown): number {
    return ageGroupLabels.reduce((sum, ag) => sum + sumAgeGroupField(ag.key, field), 0);
}

function sumSectorField(key: keyof VulnerableSectors, field: keyof AgeGenderBreakdown): number {
    return props.reports.reduce((sum, r) => {
        const group = r.vulnerable_sectors?.[key];
        return sum + Number(group?.[field] || 0);
    }, 0);
}

function sumAllSectorField(field: keyof AgeGenderBreakdown): number {
    return sectorLabels.reduce((sum, s) => sum + sumSectorField(s.key, field), 0);
}
</script>

<template>
    <div class="print-template">
        <table id="print-wrapper">
            <!-- Header repeats on every printed page -->
            <thead>
                <tr>
                    <td>
                        <div class="header">
                            <div class="logo-placeholder">Kindly replace with LGU Logo</div>
                            <div v-if="dromicLogoUrl" class="center-logo">
                                <img :src="dromicLogoUrl" alt="DROMIC" />
                            </div>
                            <div v-else class="center-logo">DROMIC</div>
                            <div class="logo-placeholder">Kindly replace with LDRRMC Logo</div>
                        </div>
                    </td>
                </tr>
            </thead>

            <!-- Body content -->
            <tbody>
                <tr>
                    <td>
                        <!-- Title Section -->
                        <div class="title-section">
                            <div class="main-title">
                                {{ showProvince ? 'Regional' : 'Provincial' }} DROMIC/Situational Consolidated {{ cutoffLabel }}
                                on the
                                <span class="red-text">{{ incident.display_name ?? incident.name }}</span>
                            </div>
                            <div class="date-time">
                                as of
                                <span class="red-text">{{ dateFormatted }}, {{ cutoffTime }}</span>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="content">
                            <!-- Section II: Status of Affected Areas and Population -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">II.</span>Status of Affected Areas and Population</div>
                                <div class="description-text">
                                    A total of
                                    <span class="blank-line">{{ totalAffectedFamilies.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalAffectedFamilies, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ totalAffectedPersons.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalAffectedPersons, 'person', 'persons') }}</span>
                                    {{ pluralize(totalAffectedPersons, 'is', 'are') }} affected in
                                    <span class="blank-line">{{ mergedAffectedAreas.length }}</span>
                                    <span class="red-text">{{ pluralize(mergedAffectedAreas.length, 'Barangay', 'Barangays') }}.</span>
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince" rowspan="2">Province</th>
                                            <th rowspan="2">City/Municipality</th>
                                            <th rowspan="2">Barangays</th>
                                            <th colspan="2">Number of Affected</th>
                                        </tr>
                                        <tr>
                                            <th>Families</th>
                                            <th>Persons</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedAffectedAreas.length">
                                            <td :colspan="showProvince ? 5 : 4"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedAffectedAreas" :key="gIdx">
                                            <tr v-for="(area, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? area.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? area.lgu : '' }}</td>
                                                <td>{{ area.barangay }}</td>
                                                <td>{{ area.families.toLocaleString() }}</td>
                                                <td>{{ area.persons.toLocaleString() }}</td>
                                            </tr>
                                            <tr v-if="showProvince && groupedAffectedAreas.length > 1" class="subtotal-row">
                                                <td :colspan="showProvince ? 3 : 2">Subtotal</td>
                                                <td>{{ sumNum(group.rows, 'families').toLocaleString() }}</td>
                                                <td>{{ sumNum(group.rows, 'persons').toLocaleString() }}</td>
                                            </tr>
                                        </template>
                                        <tr v-if="mergedAffectedAreas.length" class="total-row">
                                            <td :colspan="showProvince ? 3 : 2">TOTAL</td>
                                            <td>{{ totalAffectedFamilies.toLocaleString() }}</td>
                                            <td>{{ totalAffectedPersons.toLocaleString() }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section III: Status of Displaced Population -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">III.</span>Status of Displaced Population</div>
                                <div class="description-text">
                                    A total of
                                    <span class="blank-line">{{ totalIDPFamiliesCum.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalIDPFamiliesCum, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ totalIDPPersonsCum.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalIDPPersonsCum, 'person', 'persons') }}</span>
                                    {{ pluralize(totalIDPPersonsCum, 'is', 'are') }} displaced inside and outside ECs, below is the breakdown:
                                </div>

                                <div class="subsection-title">A. Inside Evacuation Center</div>

                                <div class="description-text">
                                    A total of
                                    <span class="blank-line">{{ totalInsideECFamiliesCum.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalInsideECFamiliesCum, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ totalInsideECPersonsCum.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalInsideECPersonsCum, 'person', 'persons') }}</span>
                                    {{ pluralize(totalInsideECPersonsCum, 'has', 'have') }} evacuated in
                                    <span class="blank-line">{{ mergedInsideEC.length }}</span>
                                    <span class="red-text">{{ pluralize(mergedInsideEC.length, 'evacuation center', 'evacuation centers') }}</span
                                    >, to wit:
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince" rowspan="2">Province</th>
                                            <th rowspan="2">City/Municipality</th>
                                            <th rowspan="2">Barangay</th>
                                            <th rowspan="2">Name of Evacuation<br />Center</th>
                                            <th colspan="2">Families</th>
                                            <th colspan="2">Persons</th>
                                            <th rowspan="2">Origin/<br />Barangay</th>
                                            <th rowspan="2">Remarks*<br />(No. of classrooms used)</th>
                                        </tr>
                                        <tr>
                                            <th>Cum</th>
                                            <th>Now</th>
                                            <th>Cum</th>
                                            <th>Now</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedInsideEC.length">
                                            <td :colspan="showProvince ? 10 : 9"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedInsideEC" :key="gIdx">
                                            <tr v-for="(ec, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? ec.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? ec.lgu : '' }}</td>
                                                <td>{{ ec.barangay }}</td>
                                                <td>{{ ec.ec_name }}</td>
                                                <td>{{ ec.families_cum }}</td>
                                                <td>{{ ec.families_now }}</td>
                                                <td>{{ ec.persons_cum }}</td>
                                                <td>{{ ec.persons_now }}</td>
                                                <td>{{ ec.origin }}</td>
                                                <td>{{ ec.remarks }}</td>
                                            </tr>
                                            <tr v-if="showProvince && groupedInsideEC.length > 1" class="subtotal-row">
                                                <td :colspan="showProvince ? 4 : 3">Subtotal</td>
                                                <td>{{ sumNum(group.rows, 'families_cum') }}</td>
                                                <td>{{ sumNum(group.rows, 'families_now') }}</td>
                                                <td>{{ sumNum(group.rows, 'persons_cum') }}</td>
                                                <td>{{ sumNum(group.rows, 'persons_now') }}</td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </template>
                                        <tr v-if="mergedInsideEC.length" class="total-row">
                                            <td :colspan="showProvince ? 4 : 3">TOTAL</td>
                                            <td>{{ totalInsideECFamiliesCum }}</td>
                                            <td>{{ totalInsideECFamiliesNow }}</td>
                                            <td>{{ totalInsideECPersonsCum }}</td>
                                            <td>{{ totalInsideECPersonsNow }}</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div class="description-text"><i>*Only for Schools used as ECs</i></div>

                                <div class="description-text">
                                    Following is the breakdown of the individuals inside evacuation centers according to age distribution:
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th>Age Range/Distribution</th>
                                            <th>Male</th>
                                            <th>Female</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="ag in ageGroupLabels" :key="ag.key">
                                            <td>{{ ag.label }}</td>
                                            <td>{{ sumAgeGroupField(ag.key, 'male_cum') }}</td>
                                            <td>{{ sumAgeGroupField(ag.key, 'female_cum') }}</td>
                                            <td>{{ sumAgeGroupField(ag.key, 'male_cum') + sumAgeGroupField(ag.key, 'female_cum') }}</td>
                                        </tr>
                                        <tr class="total-row">
                                            <td>TOTAL</td>
                                            <td>{{ sumAllAgeField('male_cum') }}</td>
                                            <td>{{ sumAllAgeField('female_cum') }}</td>
                                            <td>{{ sumAllAgeField('male_cum') + sumAllAgeField('female_cum') }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div class="description-text">
                                    Further, below is the breakdown of individuals inside ECs according to their sector:
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th>Sector</th>
                                            <th>Male</th>
                                            <th>Female</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="s in sectorLabels" :key="s.key">
                                            <td>{{ s.label }}</td>
                                            <td>{{ sumSectorField(s.key, 'male_cum') }}</td>
                                            <td>{{ sumSectorField(s.key, 'female_cum') }}</td>
                                            <td>{{ sumSectorField(s.key, 'male_cum') + sumSectorField(s.key, 'female_cum') }}</td>
                                        </tr>
                                        <tr class="total-row">
                                            <td>TOTAL</td>
                                            <td>{{ sumAllSectorField('male_cum') }}</td>
                                            <td>{{ sumAllSectorField('female_cum') }}</td>
                                            <td>{{ sumAllSectorField('male_cum') + sumAllSectorField('female_cum') }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Section III.B: Outside Evacuation Center -->
                                <div class="subsection-title">B. Outside Evacuation Center</div>

                                <div class="description-text">
                                    There
                                    {{ pluralize(totalOutsideECFamiliesCum, 'is', 'are') }}
                                    <span class="blank-line">{{ totalOutsideECFamiliesCum.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalOutsideECFamiliesCum, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ totalOutsideECPersonsCum.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalOutsideECPersonsCum, 'person', 'persons') }}</span>
                                    temporarily staying with their relatives and/or friends' houses, to wit:
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince" rowspan="2">Province</th>
                                            <th rowspan="2">City/Municipality</th>
                                            <th rowspan="2">Barangay/Host Residence</th>
                                            <th colspan="2">Families</th>
                                            <th colspan="2">Persons</th>
                                            <th rowspan="2">Origin of IDPS/Barangay</th>
                                        </tr>
                                        <tr>
                                            <th>Cum</th>
                                            <th>Now</th>
                                            <th>Cum</th>
                                            <th>Now</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedOutsideEC.length">
                                            <td :colspan="showProvince ? 8 : 7"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedOutsideEC" :key="gIdx">
                                            <tr v-for="(ec, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? ec.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? ec.lgu : '' }}</td>
                                                <td>{{ ec.barangay }}</td>
                                                <td>{{ ec.families_cum }}</td>
                                                <td>{{ ec.families_now }}</td>
                                                <td>{{ ec.persons_cum }}</td>
                                                <td>{{ ec.persons_now }}</td>
                                                <td>{{ ec.origin }}</td>
                                            </tr>
                                            <tr v-if="showProvince && groupedOutsideEC.length > 1" class="subtotal-row">
                                                <td :colspan="showProvince ? 3 : 2">Subtotal</td>
                                                <td>{{ sumNum(group.rows, 'families_cum') }}</td>
                                                <td>{{ sumNum(group.rows, 'families_now') }}</td>
                                                <td>{{ sumNum(group.rows, 'persons_cum') }}</td>
                                                <td>{{ sumNum(group.rows, 'persons_now') }}</td>
                                                <td></td>
                                            </tr>
                                        </template>
                                        <tr v-if="mergedOutsideEC.length" class="total-row">
                                            <td :colspan="showProvince ? 3 : 2">TOTAL</td>
                                            <td>{{ totalOutsideECFamiliesCum }}</td>
                                            <td>{{ totalOutsideECFamiliesNow }}</td>
                                            <td>{{ totalOutsideECPersonsCum }}</td>
                                            <td>{{ totalOutsideECPersonsNow }}</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Section III.C: Non IDPs -->
                                <div class="subsection-title">C. Non IDPs (Served Outside Evacuation Centers, Not Displaced)</div>

                                <div class="description-text">
                                    There
                                    {{ pluralize(totalNonIdpFamiliesCum, 'is', 'are') }}
                                    <span class="blank-line">{{ totalNonIdpFamiliesCum.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalNonIdpFamiliesCum, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ totalNonIdpPersonsCum.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalNonIdpPersonsCum, 'person', 'persons') }}</span>
                                    served outside evacuation centers (not displaced), to wit:
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Barangay</th>
                                            <th>Families</th>
                                            <th>Persons</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedNonIdps.length">
                                            <td :colspan="showProvince ? 5 : 4"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedNonIdps" :key="gIdx">
                                            <tr v-for="(ni, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? ni.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? ni.lgu : '' }}</td>
                                                <td>{{ ni.barangay }}</td>
                                                <td>{{ ni.families_cum }}</td>
                                                <td>{{ ni.persons_cum }}</td>
                                            </tr>
                                            <tr v-if="showProvince && groupedNonIdps.length > 1" class="subtotal-row">
                                                <td :colspan="showProvince ? 3 : 2">Subtotal</td>
                                                <td>{{ sumNum(group.rows, 'families_cum') }}</td>
                                                <td>{{ sumNum(group.rows, 'persons_cum') }}</td>
                                            </tr>
                                        </template>
                                        <tr v-if="mergedNonIdps.length" class="total-row">
                                            <td :colspan="showProvince ? 3 : 2">TOTAL</td>
                                            <td>{{ totalNonIdpFamiliesCum }}</td>
                                            <td>{{ totalNonIdpPersonsCum }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section IV: Damaged Houses -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">IV.</span>Damaged Houses</div>
                                <div class="description-text">
                                    A total of
                                    <span class="blank-line">{{ (totalTotallyDamaged + totalPartiallyDamaged).toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(totalTotallyDamaged + totalPartiallyDamaged, 'house', 'houses') }}</span>
                                    {{ pluralize(totalTotallyDamaged + totalPartiallyDamaged, 'was', 'were') }}
                                    damaged; of which,
                                    <span class="blank-line">{{ totalTotallyDamaged.toLocaleString() }}</span>
                                    {{ pluralize(totalTotallyDamaged, 'is', 'are') }}
                                    <span class="red-text">totally damaged</span>
                                    and
                                    <span class="blank-line">{{ totalPartiallyDamaged.toLocaleString() }}</span>
                                    {{ pluralize(totalPartiallyDamaged, 'is', 'are') }}
                                    <span class="red-text">partially damaged.</span>
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince" rowspan="2">Province</th>
                                            <th rowspan="2">City/Municipality</th>
                                            <th rowspan="2">Barangay</th>
                                            <th colspan="3">No. of Damaged Houses</th>
                                            <th rowspan="2">Estimated Cost of Damage</th>
                                        </tr>
                                        <tr>
                                            <th>Totally</th>
                                            <th>Partially</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedDamagedHouses.length">
                                            <td :colspan="showProvince ? 7 : 6"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedDamagedHouses" :key="gIdx">
                                            <tr v-for="(house, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? house.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? house.lgu : '' }}</td>
                                                <td>{{ house.barangay }}</td>
                                                <td>{{ house.totally_damaged }}</td>
                                                <td>{{ house.partially_damaged }}</td>
                                                <td>{{ house.totally_damaged + house.partially_damaged }}</td>
                                                <td>{{ formatCurrency(house.estimated_cost) }}</td>
                                            </tr>
                                            <tr v-if="showProvince && groupedDamagedHouses.length > 1" class="subtotal-row">
                                                <td :colspan="showProvince ? 3 : 2">Subtotal</td>
                                                <td>{{ sumNum(group.rows, 'totally_damaged') }}</td>
                                                <td>{{ sumNum(group.rows, 'partially_damaged') }}</td>
                                                <td>{{ sumNum(group.rows, 'totally_damaged') + sumNum(group.rows, 'partially_damaged') }}</td>
                                                <td>{{ formatCurrency(sumNum(group.rows, 'estimated_cost')) }}</td>
                                            </tr>
                                        </template>
                                        <tr v-if="mergedDamagedHouses.length" class="total-row">
                                            <td :colspan="showProvince ? 3 : 2">TOTAL</td>
                                            <td>{{ totalTotallyDamaged }}</td>
                                            <td>{{ totalPartiallyDamaged }}</td>
                                            <td>{{ totalTotallyDamaged + totalPartiallyDamaged }}</td>
                                            <td>{{ formatCurrency(totalEstimatedCost) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section V: Related Incidents -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">V.</span>Related Incidents</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Barangay</th>
                                            <th>Incident Type</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedRelatedIncidents.length">
                                            <td :colspan="showProvince ? 5 : 4"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedRelatedIncidents" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.barangay }}</td>
                                                <td>{{ row.incident_type }}</td>
                                                <td>{{ row.description }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section VI: Casualties -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">VI.</span>Casualties</div>
                                <template
                                    v-for="(sub, sIdx) in [
                                        { label: 'A. Injured', data: mergedCasualtiesInjured, groups: groupedCasualtiesInjured },
                                        { label: 'B. Missing', data: mergedCasualtiesMissing, groups: groupedCasualtiesMissing },
                                        { label: 'C. Dead', data: mergedCasualtiesDead, groups: groupedCasualtiesDead },
                                    ]"
                                    :key="sIdx"
                                >
                                    <div class="subsection-title">{{ sub.label }} ({{ sub.data?.length ?? 0 }})</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th v-if="showProvince">Province</th>
                                                <th>City/Municipality</th>
                                                <th>Barangay</th>
                                                <th>Name</th>
                                                <th>Age</th>
                                                <th>Sex</th>
                                                <th>Cause</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="!sub.data?.length">
                                                <td :colspan="showProvince ? 8 : 7"><i>None reported</i></td>
                                            </tr>
                                            <template v-for="(group, gIdx) in sub.groups" :key="gIdx">
                                                <tr v-for="(c, cIdx) in group.rows" :key="cIdx">
                                                    <td v-if="showProvince">{{ isFirstProvince(group.rows, cIdx) ? c.province : '' }}</td>
                                                    <td>{{ isFirstLgu(group.rows, cIdx) ? c.lgu : '' }}</td>
                                                    <td>{{ c.barangay }}</td>
                                                    <td>{{ c.name }}</td>
                                                    <td>{{ c.age }}</td>
                                                    <td>{{ c.sex }}</td>
                                                    <td>{{ c.cause }}</td>
                                                    <td>{{ c.remarks }}</td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </template>
                            </div>

                            <!-- Section VII: Damages to Infrastructure -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">VII.</span>Damages to Infrastructure</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Barangay</th>
                                            <th>Facility Type</th>
                                            <th>Description</th>
                                            <th>Estimated Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedInfrastructureDamages.length">
                                            <td :colspan="showProvince ? 6 : 5"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedInfrastructureDamages" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.barangay }}</td>
                                                <td>{{ row.facility_type }}</td>
                                                <td>{{ row.description }}</td>
                                                <td>{{ formatCurrency(row.estimated_cost) }}</td>
                                            </tr>
                                            <tr v-if="showProvince && groupedInfrastructureDamages.length > 1" class="subtotal-row">
                                                <td :colspan="showProvince ? 5 : 4">Subtotal</td>
                                                <td>{{ formatCurrency(sumNum(group.rows, 'estimated_cost')) }}</td>
                                            </tr>
                                        </template>
                                        <tr v-if="mergedInfrastructureDamages.length" class="total-row">
                                            <td :colspan="showProvince ? 5 : 4">TOTAL</td>
                                            <td>{{ formatCurrency(totalInfraCost) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section VIII: Agriculture Damages -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">VIII.</span>Damage &amp; Losses to Agriculture</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Commodity</th>
                                            <th>No. of Farmers</th>
                                            <th>Area (ha)</th>
                                            <th>Volume (MT)</th>
                                            <th>Estimated Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedAgricultureDamages.length">
                                            <td :colspan="showProvince ? 7 : 6"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedAgricultureDamages" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.commodity }}</td>
                                                <td>{{ row.no_of_farmers }}</td>
                                                <td>{{ row.area_affected_ha }}</td>
                                                <td>{{ row.volume_mt }}</td>
                                                <td>{{ formatCurrency(row.estimated_cost) }}</td>
                                            </tr>
                                            <tr v-if="showProvince && groupedAgricultureDamages.length > 1" class="subtotal-row">
                                                <td :colspan="showProvince ? 3 : 2">Subtotal</td>
                                                <td>{{ sumNum(group.rows, 'no_of_farmers') }}</td>
                                                <td>{{ sumNum(group.rows, 'area_affected_ha') }}</td>
                                                <td>{{ sumNum(group.rows, 'volume_mt') }}</td>
                                                <td>{{ formatCurrency(sumNum(group.rows, 'estimated_cost')) }}</td>
                                            </tr>
                                        </template>
                                        <tr v-if="mergedAgricultureDamages.length" class="total-row">
                                            <td :colspan="showProvince ? 6 : 5">TOTAL</td>
                                            <td>{{ formatCurrency(totalAgriCost) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section IX: Assistance Provided -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">IX.</span>Status of Assistance Provided</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Source</th>
                                            <th>Type</th>
                                            <th>Quantity</th>
                                            <th>Beneficiaries</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedAssistanceProvided.length">
                                            <td :colspan="showProvince ? 6 : 5"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedAssistanceProvided" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.source }}</td>
                                                <td>{{ row.type }}</td>
                                                <td>{{ row.quantity }}</td>
                                                <td>{{ row.beneficiaries }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section X: Class Suspension -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">X.</span>Class Suspension</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Barangay</th>
                                            <th>Level</th>
                                            <th>Date</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedClassSuspensions.length">
                                            <td :colspan="showProvince ? 6 : 5"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedClassSuspensions" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.barangay }}</td>
                                                <td>{{ row.level }}</td>
                                                <td>{{ row.date }}</td>
                                                <td>{{ row.remarks }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section XI: Work Suspension -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">XI.</span>Work Suspension</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Office/Agency</th>
                                            <th>Date</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedWorkSuspensions.length">
                                            <td :colspan="showProvince ? 5 : 4"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedWorkSuspensions" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.office }}</td>
                                                <td>{{ row.date }}</td>
                                                <td>{{ row.remarks }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section XII: Status of Lifelines -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">XII.</span>Status of Lifelines</div>

                                <div class="subsection-title">A. Roads &amp; Bridges</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Barangay</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedLifelineRoads.length">
                                            <td :colspan="showProvince ? 7 : 6"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedLifelineRoads" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.barangay }}</td>
                                                <td>{{ row.name }}</td>
                                                <td>{{ row.type }}</td>
                                                <td>{{ row.status }}</td>
                                                <td>{{ row.remarks }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>

                                <template
                                    v-for="(sub, sIdx) in [
                                        { label: 'B. Power', data: mergedLifelinePower, groups: groupedLifelinePower },
                                        { label: 'C. Water', data: mergedLifelineWater, groups: groupedLifelineWater },
                                        { label: 'D. Communication', data: mergedLifelineComm, groups: groupedLifelineComm },
                                    ]"
                                    :key="sIdx"
                                >
                                    <div class="subsection-title">{{ sub.label }}</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th v-if="showProvince">Province</th>
                                                <th>City/Municipality</th>
                                                <th>Barangay</th>
                                                <th>Provider</th>
                                                <th>Status</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="!sub.data?.length">
                                                <td :colspan="showProvince ? 6 : 5"><i>None reported</i></td>
                                            </tr>
                                            <template v-for="(group, gIdx) in sub.groups" :key="gIdx">
                                                <tr v-for="(row, idx) in group.rows" :key="idx">
                                                    <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                    <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                    <td>{{ row.barangay }}</td>
                                                    <td>{{ row.provider }}</td>
                                                    <td>{{ row.status }}</td>
                                                    <td>{{ row.remarks }}</td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </template>
                            </div>

                            <!-- Sections XIII-XV: Ports -->
                            <template
                                v-for="(port, pIdx) in [
                                    { num: 'XIII', title: 'Status of Seaports', data: mergedSeaports, groups: groupedSeaports },
                                    { num: 'XIV', title: 'Status of Airports', data: mergedAirports, groups: groupedAirports },
                                    { num: 'XV', title: 'Status of Landports', data: mergedLandports, groups: groupedLandports },
                                ]"
                                :key="pIdx"
                            >
                                <div class="section">
                                    <div class="section-title">
                                        <span class="section-number">{{ port.num }}.</span>{{ port.title }}
                                    </div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th v-if="showProvince">Province</th>
                                                <th>City/Municipality</th>
                                                <th>Port Name</th>
                                                <th>Status</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="!port.data?.length">
                                                <td :colspan="showProvince ? 5 : 4"><i>None reported</i></td>
                                            </tr>
                                            <template v-for="(group, gIdx) in port.groups" :key="gIdx">
                                                <tr v-for="(row, idx) in group.rows" :key="idx">
                                                    <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                    <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                    <td>{{ row.port_name }}</td>
                                                    <td>{{ row.status }}</td>
                                                    <td>{{ row.remarks }}</td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>

                            <!-- Section XVI: Stranded Passengers -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">XVI.</span>Stranded Passengers/Cargoes</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Port Name</th>
                                            <th>Passengers</th>
                                            <th>Rolling Cargoes</th>
                                            <th>Vessels</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedStrandedPassengers.length">
                                            <td :colspan="showProvince ? 7 : 6"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedStrandedPassengers" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.port_name }}</td>
                                                <td>{{ row.passengers }}</td>
                                                <td>{{ row.rolling_cargoes }}</td>
                                                <td>{{ row.vessels }}</td>
                                                <td>{{ row.remarks }}</td>
                                            </tr>
                                            <tr v-if="showProvince && groupedStrandedPassengers.length > 1" class="subtotal-row">
                                                <td :colspan="showProvince ? 3 : 2">Subtotal</td>
                                                <td>{{ sumNum(group.rows, 'passengers') }}</td>
                                                <td>{{ sumNum(group.rows, 'rolling_cargoes') }}</td>
                                                <td>{{ sumNum(group.rows, 'vessels') }}</td>
                                                <td></td>
                                            </tr>
                                        </template>
                                        <tr v-if="mergedStrandedPassengers.length" class="total-row">
                                            <td :colspan="showProvince ? 3 : 2">TOTAL</td>
                                            <td>{{ totalStrandedPassengers }}</td>
                                            <td>{{ totalStrandedCargoes }}</td>
                                            <td>{{ totalStrandedVessels }}</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section XVII: Declaration of State of Calamity -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">XVII.</span>Declaration of State of Calamity</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Barangay</th>
                                            <th>Date Declared</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedCalamityDeclarations.length">
                                            <td :colspan="showProvince ? 5 : 4"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedCalamityDeclarations" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.barangay }}</td>
                                                <td>{{ row.date_declared }}</td>
                                                <td>{{ row.remarks }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section XVIII: Pre-emptive Evacuation -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">XVIII.</span>Pre-emptive Evacuation</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Barangay</th>
                                            <th>Families</th>
                                            <th>Persons</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedPreemptiveEvacuations.length">
                                            <td :colspan="showProvince ? 6 : 5"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedPreemptiveEvacuations" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.barangay }}</td>
                                                <td>{{ row.families }}</td>
                                                <td>{{ row.persons }}</td>
                                                <td>{{ row.remarks }}</td>
                                            </tr>
                                            <tr v-if="showProvince && groupedPreemptiveEvacuations.length > 1" class="subtotal-row">
                                                <td :colspan="showProvince ? 3 : 2">Subtotal</td>
                                                <td>{{ sumNum(group.rows, 'families') }}</td>
                                                <td>{{ sumNum(group.rows, 'persons') }}</td>
                                                <td></td>
                                            </tr>
                                        </template>
                                        <tr v-if="mergedPreemptiveEvacuations.length" class="total-row">
                                            <td :colspan="showProvince ? 3 : 2">TOTAL</td>
                                            <td>{{ totalPreemptiveFamilies }}</td>
                                            <td>{{ totalPreemptivePersons }}</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section XIX: Gaps/Challenges -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">XIX.</span>Gaps/Challenges</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th v-if="showProvince">Province</th>
                                            <th>City/Municipality</th>
                                            <th>Description</th>
                                            <th>Recommendation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!mergedGapsChallenges.length">
                                            <td :colspan="showProvince ? 4 : 3"><i>None reported</i></td>
                                        </tr>
                                        <template v-for="(group, gIdx) in groupedGapsChallenges" :key="gIdx">
                                            <tr v-for="(row, idx) in group.rows" :key="idx">
                                                <td v-if="showProvince">{{ isFirstProvince(group.rows, idx) ? row.province : '' }}</td>
                                                <td>{{ isFirstLgu(group.rows, idx) ? row.lgu : '' }}</td>
                                                <td>{{ row.description }}</td>
                                                <td>{{ row.recommendation }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<style scoped>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.print-template {
    font-family: Arial, sans-serif;
    background-color: white;
    position: relative;
}

/* Header with logos */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    position: relative;
}

.logo-placeholder {
    width: 180px;
    height: 100px;
    border: 1px solid #ccc;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: #999;
    font-size: 14px;
    padding: 10px;
}

.center-logo {
    width: 300px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    color: #312e81;
}

.center-logo img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

/* Title section */
.title-section {
    text-align: center;
    margin-bottom: 40px;
    position: relative;
    z-index: 1;
}

.main-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 10px;
}

.date-time {
    font-size: 16px;
}

.red-text {
    color: #ff0000;
}

/* Content sections */
.content {
    position: relative;
    z-index: 1;
}

.section {
    margin-bottom: 15px;
}

.section-title {
    font-size: 18px;
    font-weight: bold;
    color: #312e81;
    margin-bottom: 15px;
}

.section-number {
    display: inline-block;
    width: 40px;
}

.description-text {
    margin-bottom: 10px;
    margin-left: 40px;
}

.subsection-title {
    font-weight: bold;
    margin-bottom: 15px;
    margin-left: 40px;
}

/* Print wrapper table */
#print-wrapper {
    width: 100%;
    border-collapse: collapse;
}

#print-wrapper > thead,
#print-wrapper > tbody,
#print-wrapper > thead > tr,
#print-wrapper > tbody > tr,
#print-wrapper > thead > tr > td,
#print-wrapper > tbody > tr > td {
    border: none;
    padding: 0;
}

/* Tables */
.content table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    empty-cells: show;
}

.content th {
    background-color: #4338ca;
    color: white;
    padding: 12px;
    text-align: center;
    vertical-align: middle;
    font-weight: bold;
    border: 1px solid black;
}

.content td {
    border: 1px solid #000;
    padding: 8px;
    text-align: center;
    vertical-align: middle;
    min-height: 30px;
    height: 30px;
}

.content tr:nth-child(even) {
    background-color: #f9f9f9;
}

.total-row {
    background-color: #4338ca !important;
    color: white;
    font-weight: bold;
}

.subtotal-row td {
    background-color: #eef2ff !important;
    font-weight: 600;
}

/* Blank line style */
.blank-line {
    display: inline-block;
    min-width: 60px;
    border: none;
    border-bottom: 1px solid #000;
    margin: 0 3px;
    padding: 2px 5px;
    font-family: Arial, sans-serif;
    font-size: 16px;
    text-align: center;
    background: transparent;
}

/* Print styles */
@media print {
    @page {
        size: 13in 8.5in landscape;
        margin: 0.3in;
    }

    #print-wrapper thead {
        display: table-header-group;
    }

    #print-wrapper tbody {
        display: table-row-group;
    }

    .content table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .content table tbody tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .section-title,
    .subsection-title {
        break-after: avoid;
        page-break-after: avoid;
    }

    .content table {
        break-inside: auto;
        page-break-inside: auto;
    }
}
</style>
