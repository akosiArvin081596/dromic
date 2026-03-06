import { computed, type Ref } from 'vue';
import type {
    AffectedArea,
    AgricultureDamage,
    AgeDistribution,
    AgeGenderBreakdown,
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
    ReportFormData,
    StrandedPassenger,
    VulnerableSectors,
    WorkSuspension,
} from '@/types';

export function useReportCalculations(form: Ref<ReportFormData>) {
    const totalAffectedFamilies = computed(() =>
        form.value.affected_areas.reduce((sum: number, a: AffectedArea) => sum + Number(a.families || 0), 0),
    );

    const totalAffectedPersons = computed(() => form.value.affected_areas.reduce((sum: number, a: AffectedArea) => sum + Number(a.persons || 0), 0));

    const totalInsideECFamiliesCum = computed(() =>
        form.value.inside_evacuation_centers.reduce((sum: number, ec: InsideEvacuationCenter) => sum + Number(ec.families_cum || 0), 0),
    );

    const totalInsideECFamiliesNow = computed(() =>
        form.value.inside_evacuation_centers.reduce((sum: number, ec: InsideEvacuationCenter) => sum + Number(ec.families_now || 0), 0),
    );

    const totalInsideECPersonsCum = computed(() =>
        form.value.inside_evacuation_centers.reduce((sum: number, ec: InsideEvacuationCenter) => sum + Number(ec.persons_cum || 0), 0),
    );

    const totalInsideECPersonsNow = computed(() =>
        form.value.inside_evacuation_centers.reduce((sum: number, ec: InsideEvacuationCenter) => sum + Number(ec.persons_now || 0), 0),
    );

    const totalOutsideECFamiliesCum = computed(() =>
        form.value.outside_evacuation_centers.reduce((sum: number, ec: OutsideEvacuationCenter) => sum + Number(ec.families_cum || 0), 0),
    );

    const totalOutsideECFamiliesNow = computed(() =>
        form.value.outside_evacuation_centers.reduce((sum: number, ec: OutsideEvacuationCenter) => sum + Number(ec.families_now || 0), 0),
    );

    const totalOutsideECPersonsCum = computed(() =>
        form.value.outside_evacuation_centers.reduce((sum: number, ec: OutsideEvacuationCenter) => sum + Number(ec.persons_cum || 0), 0),
    );

    const totalOutsideECPersonsNow = computed(() =>
        form.value.outside_evacuation_centers.reduce((sum: number, ec: OutsideEvacuationCenter) => sum + Number(ec.persons_now || 0), 0),
    );

    const totalNonIdpFamiliesCum = computed(() =>
        Math.max(0, totalAffectedFamilies.value - totalInsideECFamiliesCum.value - totalOutsideECFamiliesCum.value),
    );

    const totalNonIdpPersonsCum = computed(() =>
        Math.max(0, totalAffectedPersons.value - totalInsideECPersonsCum.value - totalOutsideECPersonsCum.value),
    );

    const totalIDPFamiliesCum = computed(() => totalInsideECFamiliesCum.value + totalOutsideECFamiliesCum.value);
    const totalIDPFamiliesNow = computed(() => totalInsideECFamiliesNow.value + totalOutsideECFamiliesNow.value);
    const totalIDPPersonsCum = computed(() => totalInsideECPersonsCum.value + totalOutsideECPersonsCum.value);
    const totalIDPPersonsNow = computed(() => totalInsideECPersonsNow.value + totalOutsideECPersonsNow.value);

    const totalTotallyDamaged = computed(() =>
        form.value.damaged_houses.reduce((sum: number, d: DamagedHouse) => sum + Number(d.totally_damaged || 0), 0),
    );

    const totalPartiallyDamaged = computed(() =>
        form.value.damaged_houses.reduce((sum: number, d: DamagedHouse) => sum + Number(d.partially_damaged || 0), 0),
    );

    const totalEstimatedCost = computed(() =>
        form.value.damaged_houses.reduce((sum: number, d: DamagedHouse) => sum + Number(d.estimated_cost || 0), 0),
    );

    const sumAgeGroup = (field: keyof AgeGenderBreakdown) => {
        const dist = form.value.age_distribution;
        return Object.values(dist).reduce((sum: number, group: AgeGenderBreakdown) => sum + Number(group[field] || 0), 0);
    };

    const totalAgeMaleCum = computed(() => sumAgeGroup('male_cum'));
    const totalAgeMaleNow = computed(() => sumAgeGroup('male_now'));
    const totalAgeFemaleCum = computed(() => sumAgeGroup('female_cum'));
    const totalAgeFemaleNow = computed(() => sumAgeGroup('female_now'));
    const totalAgeCum = computed(() => totalAgeMaleCum.value + totalAgeFemaleCum.value);
    const totalAgeNow = computed(() => totalAgeMaleNow.value + totalAgeFemaleNow.value);

    const sumVulnerableGroup = (field: keyof AgeGenderBreakdown) => {
        const sectors = form.value.vulnerable_sectors;
        return Object.values(sectors).reduce((sum: number, group: AgeGenderBreakdown) => sum + Number(group[field] || 0), 0);
    };

    const totalVulnerableMaleCum = computed(() => sumVulnerableGroup('male_cum'));
    const totalVulnerableMaleNow = computed(() => sumVulnerableGroup('male_now'));
    const totalVulnerableFemaleCum = computed(() => sumVulnerableGroup('female_cum'));
    const totalVulnerableFemaleNow = computed(() => sumVulnerableGroup('female_now'));

    return {
        totalAffectedFamilies,
        totalAffectedPersons,
        totalInsideECFamiliesCum,
        totalInsideECFamiliesNow,
        totalInsideECPersonsCum,
        totalInsideECPersonsNow,
        totalOutsideECFamiliesCum,
        totalOutsideECFamiliesNow,
        totalOutsideECPersonsCum,
        totalOutsideECPersonsNow,
        totalIDPFamiliesCum,
        totalIDPFamiliesNow,
        totalIDPPersonsCum,
        totalIDPPersonsNow,
        totalNonIdpFamiliesCum,
        totalNonIdpPersonsCum,
        totalTotallyDamaged,
        totalPartiallyDamaged,
        totalEstimatedCost,
        totalAgeMaleCum,
        totalAgeMaleNow,
        totalAgeFemaleCum,
        totalAgeFemaleNow,
        totalAgeCum,
        totalAgeNow,
        totalVulnerableMaleCum,
        totalVulnerableMaleNow,
        totalVulnerableFemaleCum,
        totalVulnerableFemaleNow,
    };
}

export function emptyAffectedArea(): AffectedArea {
    return { barangay: '', families: 0, persons: 0 };
}

export function emptyInsideEC(): InsideEvacuationCenter {
    return { barangay: '', ec_name: '', families_cum: 0, families_now: 0, persons_cum: 0, persons_now: 0, origin: '', remarks: '' };
}

export function emptyOutsideEC(): OutsideEvacuationCenter {
    return { barangay: '', families_cum: 0, families_now: 0, persons_cum: 0, persons_now: 0, origin: '' };
}

export function emptyNonIdp(): NonIdp {
    return { barangay: '', families_cum: 0, persons_cum: 0 };
}

export function emptyDamagedHouse(): DamagedHouse {
    return { barangay: '', totally_damaged: 0, partially_damaged: 0, estimated_cost: 0 };
}

export function emptyAgeDistribution(): AgeDistribution {
    const empty = (): AgeGenderBreakdown => ({ male_cum: 0, male_now: 0, female_cum: 0, female_now: 0 });
    return {
        '0-5': empty(),
        '6-12': empty(),
        '13-17': empty(),
        '18-35': empty(),
        '36-59': empty(),
        '60-69': empty(),
        '70+': empty(),
    };
}

export function emptyVulnerableSectors(): VulnerableSectors {
    const empty = (): AgeGenderBreakdown => ({ male_cum: 0, male_now: 0, female_cum: 0, female_now: 0 });
    return {
        'Pregnant/Lactating': empty(),
        'Solo Parent': empty(),
        PWD: empty(),
        'Indigenous People': empty(),
        'Senior Citizen': empty(),
    };
}

export function emptyRelatedIncident(): RelatedIncident {
    return { barangay: '', incident_type: '', description: '' };
}

export function emptyCasualty(): Casualty {
    return { barangay: '', name: '', age: 0, sex: '', cause: '', remarks: '' };
}

export function emptyInfrastructureDamage(): InfrastructureDamage {
    return { barangay: '', facility_type: '', description: '', estimated_cost: 0 };
}

export function emptyAgricultureDamage(): AgricultureDamage {
    return { commodity: '', no_of_farmers: 0, area_affected_ha: 0, volume_mt: 0, estimated_cost: 0 };
}

export function emptyAssistanceProvided(): AssistanceProvided {
    return { source: '', type: '', quantity: '', beneficiaries: '' };
}

export function emptyClassSuspension(): ClassSuspension {
    return { barangay: '', level: '', date: '', remarks: '' };
}

export function emptyWorkSuspension(): WorkSuspension {
    return { office: '', date: '', remarks: '' };
}

export function emptyLifelineRoadBridge(): LifelineRoadBridge {
    return { barangay: '', name: '', type: '', status: '', remarks: '' };
}

export function emptyLifelineUtility(): LifelineUtility {
    return { barangay: '', provider: '', status: '', remarks: '' };
}

export function emptyPortStatus(): PortStatus {
    return { port_name: '', status: '', remarks: '' };
}

export function emptyStrandedPassenger(): StrandedPassenger {
    return { port_name: '', passengers: 0, rolling_cargoes: 0, vessels: 0, remarks: '' };
}

export function emptyCalamityDeclaration(): CalamityDeclaration {
    return { barangay: '', date_declared: '', remarks: '' };
}

export function emptyPreemptiveEvacuation(): PreemptiveEvacuation {
    return { barangay: '', families: 0, persons: 0, remarks: '' };
}

export function emptyGapChallenge(): GapChallenge {
    return { description: '', recommendation: '' };
}
