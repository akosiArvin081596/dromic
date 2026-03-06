export type AgeGenderBreakdown = {
    male_cum: number;
    male_now: number;
    female_cum: number;
    female_now: number;
};

export type AgeDistribution = {
    '0-5': AgeGenderBreakdown;
    '6-12': AgeGenderBreakdown;
    '13-17': AgeGenderBreakdown;
    '18-35': AgeGenderBreakdown;
    '36-59': AgeGenderBreakdown;
    '60-69': AgeGenderBreakdown;
    '70+': AgeGenderBreakdown;
};

export type VulnerableSectors = {
    'Pregnant/Lactating': AgeGenderBreakdown;
    'Solo Parent': AgeGenderBreakdown;
    PWD: AgeGenderBreakdown;
    'Indigenous People': AgeGenderBreakdown;
    'Senior Citizen': AgeGenderBreakdown;
};

export type AffectedArea = {
    barangay: string;
    families: number;
    persons: number;
};

export type InsideEvacuationCenter = {
    barangay: string;
    ec_name: string;
    families_cum: number;
    families_now: number;
    persons_cum: number;
    persons_now: number;
    origin: string;
    remarks: string;
};

export type OutsideEvacuationCenter = {
    barangay: string;
    families_cum: number;
    families_now: number;
    persons_cum: number;
    persons_now: number;
    origin: string;
};

export type NonIdp = {
    barangay: string;
    families_cum: number;
    persons_cum: number;
};

export type DamagedHouse = {
    barangay: string;
    totally_damaged: number;
    partially_damaged: number;
    estimated_cost: number;
};

// V. Related Incidents
export type RelatedIncident = {
    barangay: string;
    incident_type: string;
    description: string;
};

// VI. Casualties (shared type for injured/missing/dead)
export type Casualty = {
    barangay: string;
    name: string;
    age: number;
    sex: string;
    cause: string;
    remarks: string;
};

// VII. Damages to Infrastructure
export type InfrastructureDamage = {
    barangay: string;
    facility_type: string;
    description: string;
    estimated_cost: number;
};

// VIII. Damage & Losses to Agriculture
export type AgricultureDamage = {
    commodity: string;
    no_of_farmers: number;
    area_affected_ha: number;
    volume_mt: number;
    estimated_cost: number;
};

// IX. Status of Assistance Provided
export type AssistanceProvided = {
    source: string;
    type: string;
    quantity: string;
    beneficiaries: string;
};

// X. Class Suspension
export type ClassSuspension = {
    barangay: string;
    level: string;
    date: string;
    remarks: string;
};

// XI. Work Suspension
export type WorkSuspension = {
    office: string;
    date: string;
    remarks: string;
};

// XII. Lifelines
export type LifelineRoadBridge = {
    barangay: string;
    name: string;
    type: string;
    status: string;
    remarks: string;
};

export type LifelineUtility = {
    barangay: string;
    provider: string;
    status: string;
    remarks: string;
};

// XIII-XV. Port Status (shared type)
export type PortStatus = {
    port_name: string;
    status: string;
    remarks: string;
};

// XVI. Stranded Passengers/Cargoes
export type StrandedPassenger = {
    port_name: string;
    passengers: number;
    rolling_cargoes: number;
    vessels: number;
    remarks: string;
};

// XVII. Declaration of State of Calamity
export type CalamityDeclaration = {
    barangay: string;
    date_declared: string;
    remarks: string;
};

// XVIII. Pre-emptive Evacuation
export type PreemptiveEvacuation = {
    barangay: string;
    families: number;
    persons: number;
    remarks: string;
};

// XIX. Gaps/Challenges
export type GapChallenge = {
    description: string;
    recommendation: string;
};

export type Report = {
    id: number;
    user_id: number;
    incident_id: number;
    report_number: string;
    report_type: 'initial' | 'progress' | 'terminal';
    sequence_number: number;
    previous_report_id: number | null;
    city_municipality_id: number;
    report_date: string;
    report_time: string;
    situation_overview: string | null;
    affected_areas: AffectedArea[];
    inside_evacuation_centers: InsideEvacuationCenter[];
    age_distribution: AgeDistribution;
    vulnerable_sectors: VulnerableSectors;
    outside_evacuation_centers: OutsideEvacuationCenter[];
    non_idps: NonIdp[];
    damaged_houses: DamagedHouse[];
    related_incidents: RelatedIncident[];
    casualties_injured: Casualty[];
    casualties_missing: Casualty[];
    casualties_dead: Casualty[];
    infrastructure_damages: InfrastructureDamage[];
    agriculture_damages: AgricultureDamage[];
    assistance_provided: AssistanceProvided[];
    class_suspensions: ClassSuspension[];
    work_suspensions: WorkSuspension[];
    lifelines_roads_bridges: LifelineRoadBridge[];
    lifelines_power: LifelineUtility[];
    lifelines_water: LifelineUtility[];
    lifelines_communication: LifelineUtility[];
    seaport_status: PortStatus[];
    airport_status: PortStatus[];
    landport_status: PortStatus[];
    stranded_passengers: StrandedPassenger[];
    calamity_declarations: CalamityDeclaration[];
    preemptive_evacuations: PreemptiveEvacuation[];
    gaps_challenges: GapChallenge[];
    response_actions: string | null;
    status: 'draft' | 'for_validation' | 'validated' | 'returned';
    return_reason: string | null;
    created_at: string;
    updated_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
    city_municipality?: CityMunicipality;
    incident?: {
        id: number;
        name: string;
        type: string;
    };
};

export type Barangay = {
    id: number;
    psgc_code: string;
    name: string;
    city_municipality_id: number;
};

export type CityMunicipality = {
    id: number;
    psgc_code: string;
    name: string;
    province_id: number;
    province?: Province;
    barangays?: Barangay[];
};

export type Province = {
    id: number;
    psgc_code: string;
    name: string;
    city_municipalities?: CityMunicipality[];
};

export type ReportNotificationData = {
    id: number;
    report_number: string;
    report_type: string;
    sequence_number: number;
    status: string;
    incident_id: number;
    incident_name: string;
    city_municipality_name: string;
    user_name: string;
    message: string;
    return_reason?: string;
};

export type ReportFormData = {
    report_date: string;
    report_time: string;
    situation_overview: string | null;
    affected_areas: AffectedArea[];
    inside_evacuation_centers: InsideEvacuationCenter[];
    age_distribution: AgeDistribution;
    vulnerable_sectors: VulnerableSectors;
    outside_evacuation_centers: OutsideEvacuationCenter[];
    non_idps: NonIdp[];
    damaged_houses: DamagedHouse[];
    related_incidents: RelatedIncident[];
    casualties_injured: Casualty[];
    casualties_missing: Casualty[];
    casualties_dead: Casualty[];
    infrastructure_damages: InfrastructureDamage[];
    agriculture_damages: AgricultureDamage[];
    assistance_provided: AssistanceProvided[];
    class_suspensions: ClassSuspension[];
    work_suspensions: WorkSuspension[];
    lifelines_roads_bridges: LifelineRoadBridge[];
    lifelines_power: LifelineUtility[];
    lifelines_water: LifelineUtility[];
    lifelines_communication: LifelineUtility[];
    seaport_status: PortStatus[];
    airport_status: PortStatus[];
    landport_status: PortStatus[];
    stranded_passengers: StrandedPassenger[];
    calamity_declarations: CalamityDeclaration[];
    preemptive_evacuations: PreemptiveEvacuation[];
    gaps_challenges: GapChallenge[];
    response_actions: string | null;
    is_terminal?: boolean;
};
