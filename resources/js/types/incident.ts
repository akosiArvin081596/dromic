import type { CityMunicipality } from './report';

export type IncidentCategory = {
    value: string;
    label: string;
};

export type Incident = {
    id: number;
    name: string;
    display_name: string | null;
    category: string;
    identifier: string | null;
    type: 'local' | 'massive';
    created_by: number;
    description: string | null;
    status: 'active' | 'closed';
    created_at: string;
    updated_at: string;
    creator?: {
        id: number;
        name: string;
        email: string;
    };
    city_municipalities?: CityMunicipality[];
    reports_count?: number;
    reporting_lgus_count?: number;
    message?: string;
};

export type IncidentNotification = {
    id: string;
    incident: Incident;
    read: boolean;
    timestamp: string;
};
