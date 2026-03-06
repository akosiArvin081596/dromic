import type { CityMunicipality } from './report';

export type RequestLetterStatus = 'pending' | 'endorsed' | 'approved' | 'delivering' | 'completed';

export type AugmentationItem = {
    type: string;
    quantity: number;
    endorsed_quantity?: number;
    approved_quantity?: number;
};

export type AugmentationTypeOption = {
    value: string;
    label: string;
};

export type LedgerItem = {
    type: string;
    requested: number;
    endorsed: number | null;
    approved: number | null;
    delivered: number;
    balance: number;
};

export type DeliveryItem = {
    type: string;
    quantity: number;
};

export type DeliveryAttachment = {
    id: number;
    delivery_id: number;
    file_path: string;
    original_filename: string;
    file_type: 'photo' | 'document';
    created_at: string;
};

export type Delivery = {
    id: number;
    request_letter_id: number;
    escort_user_id: number | null;
    recorded_by: number;
    delivery_items: DeliveryItem[];
    delivery_date: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
    escort?: {
        id: number;
        name: string;
    };
    recorder?: {
        id: number;
        name: string;
    };
    attachments?: DeliveryAttachment[];
};

export type DeliveryPlanBatch = {
    quantity: number;
    delivery_date: string;
    escort_user_id: number | null;
};

export type DeliveryPlanItem = {
    type: string;
    batches: DeliveryPlanBatch[];
};

export type DeliveryPlan = {
    id: number;
    request_letter_id: number;
    created_by: number;
    plan_items: DeliveryPlanItem[];
    notes: string | null;
    created_at: string;
    updated_at: string;
    creator?: {
        id: number;
        name: string;
    };
};

export type EscortUser = {
    id: number;
    name: string;
};

export type RequestLetter = {
    id: number;
    incident_id: number;
    user_id: number;
    city_municipality_id: number;
    file_path: string;
    original_filename: string;
    augmentation_items: AugmentationItem[];
    status: RequestLetterStatus;
    endorsed_by: number | null;
    endorsed_at: string | null;
    approved_by: number | null;
    approved_at: string | null;
    created_at: string;
    updated_at: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
    city_municipality?: CityMunicipality;
    endorser?: {
        id: number;
        name: string;
    };
    approver?: {
        id: number;
        name: string;
    };
    deliveries?: Delivery[];
    delivery_plan?: DeliveryPlan | null;
    ledger?: LedgerItem[];
    can_endorse?: boolean;
    can_approve?: boolean;
    can_record_delivery?: boolean;
    can_delete?: boolean;
};

export type RequestLetterNotificationData = {
    id: number;
    incident_id: number;
    incident_name: string;
    city_municipality_name: string;
    user_name: string;
    actor_name: string;
    item_count: number;
    action: 'submitted' | 'endorsed' | 'approved' | 'delivered';
};
