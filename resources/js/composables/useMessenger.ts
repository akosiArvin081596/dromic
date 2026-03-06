import axios from 'axios';
import { ref } from 'vue';
import type { ContactableUser, Conversation, MessageData } from '@/types/messenger';

const conversations = ref<Conversation[]>([]);
const activeConversationId = ref<number | null>(null);
const messages = ref<MessageData[]>([]);
const hasMoreMessages = ref(false);
const unreadCount = ref(0);
const isLoading = ref(false);

async function fetchConversations(): Promise<void> {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/messenger/conversations');
        conversations.value = data;
    } finally {
        isLoading.value = false;
    }
}

async function fetchMessages(conversationId: number, before?: number): Promise<void> {
    const params: Record<string, number> = {};
    if (before) {
        params.before = before;
    }

    const { data } = await axios.get(`/messenger/conversations/${conversationId}`, { params });

    if (before) {
        messages.value = [...data.data, ...messages.value];
    } else {
        messages.value = data.data;
    }
    hasMoreMessages.value = data.has_more;
}

async function sendMessage(conversationId: number, body: string): Promise<MessageData> {
    const { data } = await axios.post(`/messenger/conversations/${conversationId}/messages`, { body });

    messages.value.push(data);

    const conversation = conversations.value.find((c) => c.id === conversationId);
    if (conversation) {
        conversation.latest_message = data;
        reorderConversations();
    }

    return data;
}

async function startDm(userId: number): Promise<Conversation> {
    const { data } = await axios.post('/messenger/conversations/dm', { user_id: userId });

    const existing = conversations.value.find((c) => c.id === data.id);
    if (!existing) {
        conversations.value.unshift(data);
    }

    return data;
}

async function fetchUsers(search?: string): Promise<ContactableUser[]> {
    const params: Record<string, string> = {};
    if (search) {
        params.search = search;
    }
    const { data } = await axios.get('/messenger/users', { params });
    return data;
}

async function fetchUnreadCount(): Promise<void> {
    const { data } = await axios.get('/messenger/unread-count');
    unreadCount.value = data.count;
}

function addIncomingMessage(messageData: MessageData): void {
    if (activeConversationId.value === messageData.conversation_id) {
        messages.value.push(messageData);
        markAsReadSilently(messageData.conversation_id);
    }

    const conversation = conversations.value.find((c) => c.id === messageData.conversation_id);
    if (conversation) {
        conversation.latest_message = messageData;
        if (activeConversationId.value !== messageData.conversation_id) {
            conversation.unread_count = (conversation.unread_count || 0) + 1;
            unreadCount.value++;
        }
        reorderConversations();
    } else {
        unreadCount.value++;
        fetchConversations();
    }
}

function openConversation(conversationId: number): void {
    activeConversationId.value = conversationId;

    const conversation = conversations.value.find((c) => c.id === conversationId);
    if (conversation && conversation.unread_count > 0) {
        unreadCount.value = Math.max(0, unreadCount.value - conversation.unread_count);
        conversation.unread_count = 0;
    }
}

function closeConversation(): void {
    activeConversationId.value = null;
    messages.value = [];
    hasMoreMessages.value = false;
}

function reorderConversations(): void {
    conversations.value.sort((a, b) => {
        const aTime = a.latest_message?.created_at ?? a.created_at;
        const bTime = b.latest_message?.created_at ?? b.created_at;
        return new Date(bTime).getTime() - new Date(aTime).getTime();
    });
}

async function markAsReadSilently(conversationId: number): Promise<void> {
    try {
        await axios.get(`/messenger/conversations/${conversationId}`);
    } catch {
        // Silently fail
    }
}

export function useMessenger() {
    return {
        conversations,
        activeConversationId,
        messages,
        hasMoreMessages,
        unreadCount,
        isLoading,
        fetchConversations,
        fetchMessages,
        sendMessage,
        startDm,
        fetchUsers,
        fetchUnreadCount,
        addIncomingMessage,
        openConversation,
        closeConversation,
    };
}
