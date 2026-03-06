export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    role: 'admin' | 'regional' | 'provincial' | 'lgu' | 'escort' | 'regional_director';
    region_id: number | null;
    province_id: number | null;
    city_municipality_id: number | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
