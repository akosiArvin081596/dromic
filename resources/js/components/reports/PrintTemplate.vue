<script setup lang="ts">
import { computed, toRef } from 'vue';
import { useReportCalculations } from '@/composables/useReportCalculations';
import type { AgeDistribution, AgeGenderBreakdown, Report, VulnerableSectors } from '@/types';
import { pluralize } from '@/utils/pluralize';

type LguSettings = {
    signatory_1_name: string | null;
    signatory_1_designation: string | null;
    signatory_2_name: string | null;
    signatory_2_designation: string | null;
    signatory_3_name: string | null;
    signatory_3_designation: string | null;
    logo_url: string | null;
    ldrrmc_logo_url: string | null;
};

const props = defineProps<{
    report: Report;
    lguSettings?: LguSettings | null;
    dromicLogoUrl?: string | null;
}>();

const reportRef = toRef(() => props.report);
const calc = useReportCalculations(reportRef);

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

function groupTotal(group: AgeGenderBreakdown): number {
    return Number(group.male_cum || 0) + Number(group.female_cum || 0);
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
}

const reportDateFormatted = computed(() => {
    if (!props.report.report_date) {
        return '';
    }
    const d = new Date(props.report.report_date);
    const day = d.getDate();
    const month = d.toLocaleDateString('en-PH', { month: 'long' });
    const year = d.getFullYear();
    return `${day} ${month} ${year}`;
});

const reportTypeTitle = computed(() => {
    if (props.report.report_type === 'initial') return 'Initial Report';
    if (props.report.report_type === 'terminal') return 'Terminal Report';
    return `Progress Report No. ${props.report.sequence_number}`;
});

const locationText = computed(() => {
    const cm = props.report.city_municipality;
    if (!cm) {
        return '';
    }
    const provinceName = cm.province?.name ?? '';
    const lguProvince = provinceName ? `${cm.name}, ${provinceName}` : cm.name;

    const areas = props.report.affected_areas ?? [];
    if (areas.length === 1 && areas[0].barangay) {
        const brgy = areas[0].barangay.startsWith('Brgy.') ? areas[0].barangay : `Brgy. ${areas[0].barangay}`;
        return `${brgy}, ${lguProvince}`;
    }

    return lguProvince;
});
</script>

<template>
    <div class="print-template">
        <table id="print-wrapper">
            <!-- Header repeats on every printed page -->
            <thead>
                <tr>
                    <td>
                        <div class="header">
                            <div v-if="lguSettings?.logo_url" class="logo-image">
                                <img :src="lguSettings.logo_url" alt="LGU Logo" />
                            </div>
                            <div v-else class="logo-placeholder">Kindly replace with LGU Logo</div>
                            <div v-if="dromicLogoUrl" class="center-logo">
                                <img :src="dromicLogoUrl" alt="DROMIC" />
                            </div>
                            <div v-else class="center-logo">DROMIC</div>
                            <div v-if="lguSettings?.ldrrmc_logo_url" class="logo-image">
                                <img :src="lguSettings.ldrrmc_logo_url" alt="LDRRMC Logo" />
                            </div>
                            <div v-else class="logo-placeholder">Kindly replace with LDRRMC Logo</div>
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
                                LGU DROMIC/Situational {{ reportTypeTitle }}
                                on the
                                <span class="red-text">{{ report.incident?.display_name ?? report.incident?.name ?? '' }}</span>
                            </div>
                            <div class="subtitle">
                                <span class="red-text">{{ locationText }}</span>
                            </div>
                            <div class="date-time">
                                as of
                                <span class="red-text">{{ reportDateFormatted }}, {{ report.report_time }}</span>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="content">
                            <!-- Section I: Situation Overview -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">I.</span>Situation Overview</div>
                                <div v-if="report.situation_overview" class="description-text">
                                    {{ report.situation_overview }}
                                </div>
                                <div v-else class="instruction-text">
                                    Please narrate the current situation of the city/municipality relative to the disaster incident.
                                </div>
                            </div>

                            <!-- Section II: Status of Affected Areas and Population -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">II.</span>Status of Affected Areas and Population</div>
                                <div class="description-text">
                                    A total of
                                    <span class="blank-line">{{ calc.totalAffectedFamilies.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalAffectedFamilies.value, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ calc.totalAffectedPersons.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalAffectedPersons.value, 'person', 'persons') }}</span>
                                    {{ pluralize(calc.totalAffectedPersons.value, 'is', 'are') }} affected in
                                    <span class="blank-line">{{ report.affected_areas.length }}</span>
                                    <span class="red-text">{{ pluralize(report.affected_areas.length, 'Barangay', 'Barangays') }}.</span>
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th rowspan="2">Barangays</th>
                                            <th colspan="2">Number of Affected</th>
                                        </tr>
                                        <tr>
                                            <th>Families</th>
                                            <th>Persons</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="area in report.affected_areas" :key="area.barangay">
                                            <td>{{ area.barangay }}</td>
                                            <td>{{ area.families.toLocaleString() }}</td>
                                            <td>{{ area.persons.toLocaleString() }}</td>
                                        </tr>
                                        <tr class="total-row">
                                            <td>TOTAL</td>
                                            <td>{{ calc.totalAffectedFamilies.value.toLocaleString() }}</td>
                                            <td>{{ calc.totalAffectedPersons.value.toLocaleString() }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section III: Status of Displaced Population -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">III.</span>Status of Displaced Population</div>
                                <div class="description-text">
                                    A total of
                                    <span class="blank-line">{{ calc.totalIDPFamiliesCum.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalIDPFamiliesCum.value, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ calc.totalIDPPersonsCum.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalIDPPersonsCum.value, 'person', 'persons') }}</span>
                                    {{ pluralize(calc.totalIDPPersonsCum.value, 'is', 'are') }} displaced inside and outside ECs, below is the
                                    breakdown:
                                </div>

                                <div class="subsection-title">A. Inside Evacuation Center</div>

                                <div class="description-text">
                                    A total of
                                    <span class="blank-line">{{ calc.totalInsideECFamiliesCum.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalInsideECFamiliesCum.value, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ calc.totalInsideECPersonsCum.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalInsideECPersonsCum.value, 'person', 'persons') }}</span>
                                    {{ pluralize(calc.totalInsideECPersonsCum.value, 'has', 'have') }} evacuated in
                                    <span class="blank-line">{{ report.inside_evacuation_centers.length }}</span>
                                    <span class="red-text">{{
                                        pluralize(report.inside_evacuation_centers.length, 'evacuation center', 'evacuation centers')
                                    }}</span
                                    >, to wit:
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th rowspan="2">Brgy of EC</th>
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
                                        <tr v-for="ec in report.inside_evacuation_centers" :key="ec.ec_name">
                                            <td>{{ ec.barangay }}</td>
                                            <td>{{ ec.ec_name }}</td>
                                            <td>{{ ec.families_cum }}</td>
                                            <td>{{ ec.families_now }}</td>
                                            <td>{{ ec.persons_cum }}</td>
                                            <td>{{ ec.persons_now }}</td>
                                            <td>{{ ec.origin }}</td>
                                            <td>{{ ec.remarks }}</td>
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
                                            <td>{{ report.age_distribution[ag.key].male_cum }}</td>
                                            <td>{{ report.age_distribution[ag.key].female_cum }}</td>
                                            <td>{{ groupTotal(report.age_distribution[ag.key]) }}</td>
                                        </tr>
                                        <tr class="total-row">
                                            <td>TOTAL</td>
                                            <td>{{ calc.totalAgeMaleCum.value }}</td>
                                            <td>{{ calc.totalAgeFemaleCum.value }}</td>
                                            <td>{{ calc.totalAgeCum.value }}</td>
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
                                            <td>{{ report.vulnerable_sectors[s.key].male_cum }}</td>
                                            <td>{{ report.vulnerable_sectors[s.key].female_cum }}</td>
                                            <td>{{ groupTotal(report.vulnerable_sectors[s.key]) }}</td>
                                        </tr>
                                        <tr class="total-row">
                                            <td>TOTAL</td>
                                            <td>{{ calc.totalVulnerableMaleCum.value }}</td>
                                            <td>{{ calc.totalVulnerableFemaleCum.value }}</td>
                                            <td>{{ calc.totalVulnerableMaleCum.value + calc.totalVulnerableFemaleCum.value }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Section III.B: Outside Evacuation Center -->
                                <div class="subsection-title">B. Outside Evacuation Center</div>

                                <div class="description-text">
                                    There
                                    {{ pluralize(calc.totalOutsideECFamiliesCum.value, 'is', 'are') }}
                                    <span class="blank-line">{{ calc.totalOutsideECFamiliesCum.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalOutsideECFamiliesCum.value, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ calc.totalOutsideECPersonsCum.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalOutsideECPersonsCum.value, 'person', 'persons') }}</span>
                                    temporarily staying with their relatives and/or friends' houses, to wit:
                                </div>

                                <table>
                                    <thead>
                                        <tr>
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
                                        <tr v-for="ec in report.outside_evacuation_centers" :key="ec.barangay">
                                            <td>{{ ec.barangay }}</td>
                                            <td>{{ ec.families_cum }}</td>
                                            <td>{{ ec.families_now }}</td>
                                            <td>{{ ec.persons_cum }}</td>
                                            <td>{{ ec.persons_now }}</td>
                                            <td>{{ ec.origin }}</td>
                                        </tr>
                                        <tr class="total-row">
                                            <td>TOTAL</td>
                                            <td>{{ calc.totalOutsideECFamiliesCum.value }}</td>
                                            <td>{{ calc.totalOutsideECFamiliesNow.value }}</td>
                                            <td>{{ calc.totalOutsideECPersonsCum.value }}</td>
                                            <td>{{ calc.totalOutsideECPersonsNow.value }}</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <!-- Section III.C: Non IDPs -->
                                <div class="subsection-title">C. Non IDPs (Served Outside Evacuation Centers, Not Displaced)</div>

                                <div class="description-text">
                                    There
                                    {{ pluralize(calc.totalNonIdpFamiliesCum.value, 'is', 'are') }}
                                    <span class="blank-line">{{ calc.totalNonIdpFamiliesCum.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalNonIdpFamiliesCum.value, 'family', 'families') }}</span>
                                    or
                                    <span class="blank-line">{{ calc.totalNonIdpPersonsCum.value.toLocaleString() }}</span>
                                    <span class="red-text">{{ pluralize(calc.totalNonIdpPersonsCum.value, 'person', 'persons') }}</span>
                                    served outside evacuation centers (not displaced), to wit:
                                </div>

                                <table>
                                    <thead>
                                        <tr>
                                            <th>Barangay</th>
                                            <th>Families</th>
                                            <th>Persons</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="ni in report.non_idps" :key="ni.barangay">
                                            <td>{{ ni.barangay }}</td>
                                            <td>{{ ni.families_cum }}</td>
                                            <td>{{ ni.persons_cum }}</td>
                                        </tr>
                                        <tr class="total-row">
                                            <td>TOTAL</td>
                                            <td>{{ calc.totalNonIdpFamiliesCum.value }}</td>
                                            <td>{{ calc.totalNonIdpPersonsCum.value }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section IV: Damaged Houses -->
                            <div class="section">
                                <div class="section-title">
                                    <span class="section-number">IV.</span>
                                    Damaged Houses
                                </div>
                                <div class="description-text">
                                    A total of
                                    <span class="blank-line">{{
                                        (calc.totalTotallyDamaged.value + calc.totalPartiallyDamaged.value).toLocaleString()
                                    }}</span>
                                    <span class="red-text">{{
                                        pluralize(calc.totalTotallyDamaged.value + calc.totalPartiallyDamaged.value, 'house', 'houses')
                                    }}</span>
                                    {{ pluralize(calc.totalTotallyDamaged.value + calc.totalPartiallyDamaged.value, 'was', 'were') }}
                                    damaged; of which,
                                    <span class="blank-line">{{ calc.totalTotallyDamaged.value.toLocaleString() }}</span>
                                    {{ pluralize(calc.totalTotallyDamaged.value, 'is', 'are') }}
                                    <span class="red-text">totally damaged</span>
                                    and
                                    <span class="blank-line">{{ calc.totalPartiallyDamaged.value.toLocaleString() }}</span>
                                    {{ pluralize(calc.totalPartiallyDamaged.value, 'is', 'are') }}
                                    <span class="red-text">partially damaged.</span>
                                </div>

                                <table>
                                    <thead>
                                        <tr>
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
                                        <tr v-for="house in report.damaged_houses" :key="house.barangay">
                                            <td>{{ house.barangay }}</td>
                                            <td>{{ house.totally_damaged }}</td>
                                            <td>{{ house.partially_damaged }}</td>
                                            <td>{{ house.totally_damaged + house.partially_damaged }}</td>
                                            <td>{{ formatCurrency(house.estimated_cost) }}</td>
                                        </tr>
                                        <tr class="total-row">
                                            <td>TOTAL</td>
                                            <td>{{ calc.totalTotallyDamaged.value }}</td>
                                            <td>{{ calc.totalPartiallyDamaged.value }}</td>
                                            <td>{{ calc.totalTotallyDamaged.value + calc.totalPartiallyDamaged.value }}</td>
                                            <td>{{ formatCurrency(calc.totalEstimatedCost.value) }}</td>
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
                                            <th>Barangay</th>
                                            <th>Incident Type</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.related_incidents" :key="idx">
                                            <td>{{ row.barangay }}</td>
                                            <td>{{ row.incident_type }}</td>
                                            <td>{{ row.description }}</td>
                                        </tr>
                                        <tr v-if="!report.related_incidents?.length">
                                            <td colspan="3"><i>None reported</i></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section VI: Casualties -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">VI.</span>Casualties</div>
                                <template
                                    v-for="(sub, sIdx) in [
                                        { label: 'A. Injured', data: report.casualties_injured },
                                        { label: 'B. Missing', data: report.casualties_missing },
                                        { label: 'C. Dead', data: report.casualties_dead },
                                    ]"
                                    :key="sIdx"
                                >
                                    <div class="subsection-title">{{ sub.label }} ({{ sub.data?.length ?? 0 }})</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Barangay</th>
                                                <th>Name</th>
                                                <th>Age</th>
                                                <th>Sex</th>
                                                <th>Cause</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(c, cIdx) in sub.data" :key="cIdx">
                                                <td>{{ c.barangay }}</td>
                                                <td>{{ c.name }}</td>
                                                <td>{{ c.age }}</td>
                                                <td>{{ c.sex }}</td>
                                                <td>{{ c.cause }}</td>
                                                <td>{{ c.remarks }}</td>
                                            </tr>
                                            <tr v-if="!sub.data?.length">
                                                <td colspan="6"><i>None reported</i></td>
                                            </tr>
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
                                            <th>Barangay</th>
                                            <th>Facility Type</th>
                                            <th>Description</th>
                                            <th>Estimated Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.infrastructure_damages" :key="idx">
                                            <td>{{ row.barangay }}</td>
                                            <td>{{ row.facility_type }}</td>
                                            <td>{{ row.description }}</td>
                                            <td>{{ formatCurrency(row.estimated_cost) }}</td>
                                        </tr>
                                        <tr v-if="!report.infrastructure_damages?.length">
                                            <td colspan="4"><i>None reported</i></td>
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
                                            <th>Commodity</th>
                                            <th>No. of Farmers</th>
                                            <th>Area (ha)</th>
                                            <th>Volume (MT)</th>
                                            <th>Estimated Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.agriculture_damages" :key="idx">
                                            <td>{{ row.commodity }}</td>
                                            <td>{{ row.no_of_farmers }}</td>
                                            <td>{{ row.area_affected_ha }}</td>
                                            <td>{{ row.volume_mt }}</td>
                                            <td>{{ formatCurrency(row.estimated_cost) }}</td>
                                        </tr>
                                        <tr v-if="!report.agriculture_damages?.length">
                                            <td colspan="5"><i>None reported</i></td>
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
                                            <th>Source</th>
                                            <th>Type</th>
                                            <th>Quantity</th>
                                            <th>Beneficiaries</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.assistance_provided" :key="idx">
                                            <td>{{ row.source }}</td>
                                            <td>{{ row.type }}</td>
                                            <td>{{ row.quantity }}</td>
                                            <td>{{ row.beneficiaries }}</td>
                                        </tr>
                                        <tr v-if="!report.assistance_provided?.length">
                                            <td colspan="4"><i>None reported</i></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section X: Class Suspension -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">X.</span>Class Suspension</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Barangay</th>
                                            <th>Level</th>
                                            <th>Date</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.class_suspensions" :key="idx">
                                            <td>{{ row.barangay }}</td>
                                            <td>{{ row.level }}</td>
                                            <td>{{ row.date }}</td>
                                            <td>{{ row.remarks }}</td>
                                        </tr>
                                        <tr v-if="!report.class_suspensions?.length">
                                            <td colspan="4"><i>None reported</i></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section XI: Work Suspension -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">XI.</span>Work Suspension</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Office/Agency</th>
                                            <th>Date</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.work_suspensions" :key="idx">
                                            <td>{{ row.office }}</td>
                                            <td>{{ row.date }}</td>
                                            <td>{{ row.remarks }}</td>
                                        </tr>
                                        <tr v-if="!report.work_suspensions?.length">
                                            <td colspan="3"><i>None reported</i></td>
                                        </tr>
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
                                            <th>Barangay</th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.lifelines_roads_bridges" :key="idx">
                                            <td>{{ row.barangay }}</td>
                                            <td>{{ row.name }}</td>
                                            <td>{{ row.type }}</td>
                                            <td>{{ row.status }}</td>
                                            <td>{{ row.remarks }}</td>
                                        </tr>
                                        <tr v-if="!report.lifelines_roads_bridges?.length">
                                            <td colspan="5"><i>None reported</i></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <template
                                    v-for="(sub, sIdx) in [
                                        { label: 'B. Power', data: report.lifelines_power },
                                        { label: 'C. Water', data: report.lifelines_water },
                                        { label: 'D. Communication', data: report.lifelines_communication },
                                    ]"
                                    :key="sIdx"
                                >
                                    <div class="subsection-title">{{ sub.label }}</div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Barangay</th>
                                                <th>Provider</th>
                                                <th>Status</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(row, idx) in sub.data" :key="idx">
                                                <td>{{ row.barangay }}</td>
                                                <td>{{ row.provider }}</td>
                                                <td>{{ row.status }}</td>
                                                <td>{{ row.remarks }}</td>
                                            </tr>
                                            <tr v-if="!sub.data?.length">
                                                <td colspan="4"><i>None reported</i></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </template>
                            </div>

                            <!-- Sections XIII-XV: Ports -->
                            <template
                                v-for="(port, pIdx) in [
                                    { num: 'XIII', title: 'Status of Seaports', data: report.seaport_status },
                                    { num: 'XIV', title: 'Status of Airports', data: report.airport_status },
                                    { num: 'XV', title: 'Status of Landports', data: report.landport_status },
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
                                                <th>Port Name</th>
                                                <th>Status</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(row, idx) in port.data" :key="idx">
                                                <td>{{ row.port_name }}</td>
                                                <td>{{ row.status }}</td>
                                                <td>{{ row.remarks }}</td>
                                            </tr>
                                            <tr v-if="!port.data?.length">
                                                <td colspan="3"><i>None reported</i></td>
                                            </tr>
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
                                            <th>Port Name</th>
                                            <th>Passengers</th>
                                            <th>Rolling Cargoes</th>
                                            <th>Vessels</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.stranded_passengers" :key="idx">
                                            <td>{{ row.port_name }}</td>
                                            <td>{{ row.passengers }}</td>
                                            <td>{{ row.rolling_cargoes }}</td>
                                            <td>{{ row.vessels }}</td>
                                            <td>{{ row.remarks }}</td>
                                        </tr>
                                        <tr v-if="!report.stranded_passengers?.length">
                                            <td colspan="5"><i>None reported</i></td>
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
                                            <th>Barangay</th>
                                            <th>Date Declared</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.calamity_declarations" :key="idx">
                                            <td>{{ row.barangay }}</td>
                                            <td>{{ row.date_declared }}</td>
                                            <td>{{ row.remarks }}</td>
                                        </tr>
                                        <tr v-if="!report.calamity_declarations?.length">
                                            <td colspan="3"><i>None reported</i></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section XVIII: Pre-emptive Evacuation -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">XVIII.</span>Pre-emptive Evacuation</div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Barangay</th>
                                            <th>Families</th>
                                            <th>Persons</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.preemptive_evacuations" :key="idx">
                                            <td>{{ row.barangay }}</td>
                                            <td>{{ row.families }}</td>
                                            <td>{{ row.persons }}</td>
                                            <td>{{ row.remarks }}</td>
                                        </tr>
                                        <tr v-if="!report.preemptive_evacuations?.length">
                                            <td colspan="4"><i>None reported</i></td>
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
                                            <th>Description</th>
                                            <th>Recommendation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in report.gaps_challenges" :key="idx">
                                            <td>{{ row.description }}</td>
                                            <td>{{ row.recommendation }}</td>
                                        </tr>
                                        <tr v-if="!report.gaps_challenges?.length">
                                            <td colspan="2"><i>None reported</i></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Section XX: Response Actions -->
                            <div class="section">
                                <div class="section-title"><span class="section-number">XX.</span>Response Actions</div>
                                <div v-if="report.response_actions" class="description-text" style="white-space: pre-line">
                                    {{ report.response_actions }}
                                </div>
                                <div v-else class="instruction-text">None reported</div>
                            </div>

                            <!-- Signatories -->
                            <div
                                v-if="lguSettings?.signatory_1_name || lguSettings?.signatory_2_name || lguSettings?.signatory_3_name"
                                class="signatories"
                            >
                                <div class="signatories-row">
                                    <div v-if="lguSettings?.signatory_1_name" class="signatory">
                                        <div class="signatory-label">Prepared by:</div>
                                        <div class="signatory-name">{{ lguSettings.signatory_1_name }}</div>
                                        <div class="signatory-designation">{{ lguSettings.signatory_1_designation }}</div>
                                    </div>
                                    <div v-if="lguSettings?.signatory_2_name" class="signatory">
                                        <div class="signatory-label">Reviewed by:</div>
                                        <div class="signatory-name">{{ lguSettings.signatory_2_name }}</div>
                                        <div class="signatory-designation">{{ lguSettings.signatory_2_designation }}</div>
                                    </div>
                                </div>
                                <div v-if="lguSettings?.signatory_3_name" class="signatories-row signatories-center">
                                    <div class="signatory">
                                        <div class="signatory-label">Noted by:</div>
                                        <div class="signatory-name">{{ lguSettings.signatory_3_name }}</div>
                                        <div class="signatory-designation">{{ lguSettings.signatory_3_designation }}</div>
                                    </div>
                                </div>
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

.logo-image {
    width: 180px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
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

.subtitle {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 5px;
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

.instruction-text {
    font-style: italic;
    margin-bottom: 10px;
    margin-left: 40px;
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

/* Signatories */
.signatories {
    margin-top: 60px;
    page-break-inside: avoid;
}

.signatories-row {
    display: flex;
    justify-content: space-around;
    margin-bottom: 40px;
}

.signatories-center {
    justify-content: center;
}

.signatory {
    text-align: center;
    min-width: 250px;
}

.signatory-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 30px;
}

.signatory-name {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
    border-bottom: 1px solid #000;
    padding-bottom: 2px;
    display: inline-block;
    min-width: 200px;
}

.signatory-designation {
    font-size: 12px;
    color: #333;
    margin-top: 4px;
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
