export type ConversationType = 'group' | 'dm';

export type ConversationParticipant = {
    id: number;
    name: string;
    role: string;
    city_municipality_id?: number | null;
    province_id?: number | null;
    city_municipality?: { id: number; name: string } | null;
    province?: { id: number; name: string } | null;
    pivot?: {
        last_read_at: string | null;
    };
};

export type MessageUser = {
    id: number;
    name: string;
    role: string;
    city_municipality_id?: number | null;
    province_id?: number | null;
    city_municipality?: { id: number; name: string } | null;
    province?: { id: number; name: string } | null;
};

export type MessageData = {
    id: number;
    conversation_id: number;
    user_id: number;
    user_name?: string;
    user_role?: string;
    body: string;
    created_at: string;
    user?: MessageUser;
};

export type Conversation = {
    id: number;
    type: ConversationType;
    province_id: number | null;
    dm_key: string | null;
    participants: ConversationParticipant[];
    latest_message: MessageData | null;
    province: { id: number; name: string } | null;
    unread_count: number;
    created_at: string;
    updated_at: string;
};

export type ContactableUser = {
    id: number;
    name: string;
    role: string;
    region?: { id: number; name: string } | null;
    province?: { id: number; name: string } | null;
    city_municipality?: { id: number; name: string; province?: { id: number; name: string } | null } | null;
};
