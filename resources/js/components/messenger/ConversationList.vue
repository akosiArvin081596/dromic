<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useMessenger } from '@/composables/useMessenger';
import type { Auth } from '@/types';
import type { ContactableUser, Conversation } from '@/types/messenger';

const props = defineProps<{
    conversations: Conversation[];
    initialTab?: 'chat' | 'groups' | 'contacts';
}>();

const emit = defineEmits<{
    select: [conversation: Conversation, tab: Tab];
}>();

const page = usePage<{ auth: Auth }>();
const userId = computed(() => page.props.auth.user.id);

const { fetchUsers, startDm } = useMessenger();

type Tab = 'chat' | 'groups' | 'contacts';
const activeTab = ref<Tab>(props.initialTab ?? 'chat');

// --- Group Chats ---
const groupConversations = computed(() => props.conversations.filter((c) => c.type === 'group'));

// --- Contacts ---
const contacts = ref<ContactableUser[]>([]);
const searchQuery = ref('');
const isLoadingContacts = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const dmConversations = computed(() => props.conversations.filter((c) => c.type === 'dm'));

// --- Chat History (DMs sorted by latest message) ---
const chatHistory = computed(() =>
    dmConversations.value
        .filter((c) => c.latest_message)
        .sort((a, b) => {
            const aTime = a.latest_message?.created_at ?? a.created_at;
            const bTime = b.latest_message?.created_at ?? b.created_at;
            return new Date(bTime).getTime() - new Date(aTime).getTime();
        }),
);

async function loadContacts(search?: string): Promise<void> {
    isLoadingContacts.value = true;
    try {
        contacts.value = await fetchUsers(search);
    } finally {
        isLoadingContacts.value = false;
    }
}

watch(searchQuery, (val) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadContacts(val || undefined), 300);
});

watch(activeTab, (tab) => {
    if (tab === 'contacts' && contacts.value.length === 0) {
        loadContacts();
    }
});

function getDmForContact(contactId: number): Conversation | undefined {
    return dmConversations.value.find((c) => c.participants.some((p) => p.id === contactId));
}

async function selectContact(user: ContactableUser): Promise<void> {
    const existingDm = getDmForContact(user.id);
    if (existingDm) {
        emit('select', existingDm, activeTab.value);
    } else {
        const conversation = await startDm(user.id);
        emit('select', conversation, activeTab.value);
    }
}

// --- Shared helpers ---
function conversationName(conversation: Conversation): string {
    if (conversation.type === 'group') {
        return conversation.province?.name ?? 'Group Chat';
    }
    const other = conversation.participants.find((p) => p.id !== userId.value);
    if (!other) return 'Direct Message';
    const parts = [other.city_municipality?.name, other.province?.name].filter(Boolean);
    const location = parts.join(', ');
    return location ? `${other.name} (${location})` : other.name;
}

function conversationSubtitle(conversation: Conversation): string {
    if (conversation.type === 'group') {
        return `${conversation.participants.length} members`;
    }
    const other = conversation.participants.find((p) => p.id !== userId.value);
    return other?.role ?? '';
}

function lastMessagePreview(conversation: Conversation): string {
    if (!conversation.latest_message) return 'No messages yet';
    const msg = conversation.latest_message;
    const sender = msg.user?.name ?? msg.user_name ?? '';
    const prefix = msg.user_id === userId.value ? 'You' : sender.split(' ')[0];
    const body = msg.body.length > 40 ? msg.body.slice(0, 40) + '...' : msg.body;
    return `${prefix}: ${body}`;
}

function timeAgo(dateStr: string | undefined): string {
    if (!dateStr) return '';
    const seconds = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (seconds < 60) return 'now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h`;
    return `${Math.floor(hours / 24)}d`;
}

function roleBadgeClass(role: string): string {
    switch (role) {
        case 'admin':
            return 'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300';
        case 'regional':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
        case 'provincial':
            return 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300';
        case 'lgu':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-600 dark:text-slate-300';
    }
}

function contactLocation(user: ContactableUser): string {
    const provinceName = user.province?.name ?? user.city_municipality?.province?.name;
    if (user.city_municipality?.name && provinceName) return `${user.city_municipality.name}, ${provinceName}`;
    if (user.city_municipality?.name) return user.city_municipality.name;
    if (provinceName) return provinceName;
    if (user.region?.name) return user.region.name;
    return '';
}

const totalDmUnread = computed(() => dmConversations.value.reduce((sum, c) => sum + (c.unread_count || 0), 0));
const totalGroupUnread = computed(() => groupConversations.value.reduce((sum, c) => sum + (c.unread_count || 0), 0));
</script>

<template>
    <div class="flex h-full flex-col overflow-hidden">
        <!-- Tabs -->
        <div class="flex border-b border-slate-200 dark:border-slate-700">
            <button
                class="relative flex-1 py-2.5 text-center text-xs font-semibold transition-colors"
                :class="
                    activeTab === 'chat'
                        ? 'text-indigo-600 dark:text-indigo-400'
                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                "
                @click="activeTab = 'chat'"
            >
                Chat
                <span
                    v-if="totalDmUnread > 0"
                    class="ml-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white"
                >
                    {{ totalDmUnread > 9 ? '9+' : totalDmUnread }}
                </span>
                <div v-if="activeTab === 'chat'" class="absolute inset-x-0 bottom-0 h-0.5 bg-indigo-600 dark:bg-indigo-400"></div>
            </button>
            <button
                class="relative flex-1 py-2.5 text-center text-xs font-semibold transition-colors"
                :class="
                    activeTab === 'groups'
                        ? 'text-indigo-600 dark:text-indigo-400'
                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                "
                @click="activeTab = 'groups'"
            >
                Groups
                <span
                    v-if="totalGroupUnread > 0"
                    class="ml-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white"
                >
                    {{ totalGroupUnread > 9 ? '9+' : totalGroupUnread }}
                </span>
                <div v-if="activeTab === 'groups'" class="absolute inset-x-0 bottom-0 h-0.5 bg-indigo-600 dark:bg-indigo-400"></div>
            </button>
            <button
                class="relative flex-1 py-2.5 text-center text-xs font-semibold transition-colors"
                :class="
                    activeTab === 'contacts'
                        ? 'text-indigo-600 dark:text-indigo-400'
                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                "
                @click="activeTab = 'contacts'"
            >
                Contacts
                <div v-if="activeTab === 'contacts'" class="absolute inset-x-0 bottom-0 h-0.5 bg-indigo-600 dark:bg-indigo-400"></div>
            </button>
        </div>

        <!-- Chat History Tab -->
        <div v-if="activeTab === 'chat'" class="flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
            <div v-if="chatHistory.length === 0" class="px-4 py-8 text-center text-sm text-slate-400 dark:text-slate-500">No conversations yet</div>
            <button
                v-for="conversation in chatHistory"
                :key="conversation.id"
                class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-700"
                @click="emit('select', conversation, activeTab.value)"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-400 text-sm font-semibold text-white">
                    {{ conversationName(conversation).charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between">
                        <span class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ conversationName(conversation) }}</span>
                        <span class="shrink-0 text-[10px] text-slate-400">
                            {{ timeAgo(conversation.latest_message?.created_at) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="truncate text-xs text-slate-500 dark:text-slate-400">{{ lastMessagePreview(conversation) }}</span>
                        <span
                            v-if="conversation.unread_count > 0"
                            class="ml-2 flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white"
                        >
                            {{ conversation.unread_count > 9 ? '9+' : conversation.unread_count }}
                        </span>
                    </div>
                </div>
            </button>
        </div>

        <!-- Group Chats Tab -->
        <div v-if="activeTab === 'groups'" class="flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
            <div v-if="groupConversations.length === 0" class="px-4 py-8 text-center text-sm text-slate-400 dark:text-slate-500">
                No group chats yet
            </div>
            <button
                v-for="conversation in groupConversations"
                :key="conversation.id"
                class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-700"
                @click="emit('select', conversation, activeTab.value)"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-500 text-sm font-semibold text-white">G</div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between">
                        <span class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ conversationName(conversation) }}</span>
                        <span class="shrink-0 text-[10px] text-slate-400">
                            {{ timeAgo(conversation.latest_message?.created_at) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ conversation.latest_message ? lastMessagePreview(conversation) : conversationSubtitle(conversation) }}
                        </span>
                        <span
                            v-if="conversation.unread_count > 0"
                            class="ml-2 flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white"
                        >
                            {{ conversation.unread_count > 9 ? '9+' : conversation.unread_count }}
                        </span>
                    </div>
                </div>
            </button>
        </div>

        <!-- Contacts Tab -->
        <template v-if="activeTab === 'contacts'">
            <!-- Search -->
            <div class="border-b border-slate-200 px-3 py-2 dark:border-slate-700">
                <div class="relative">
                    <Search :size="14" class="absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search users..."
                        class="w-full rounded-full border border-slate-200 py-1.5 pr-3 pl-8 text-sm focus:border-indigo-300 focus:ring-0 focus:outline-none dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 dark:placeholder-slate-400 dark:focus:border-indigo-500"
                    />
                </div>
            </div>

            <!-- User List -->
            <div class="flex-1 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-700">
                <div v-if="isLoadingContacts" class="py-8 text-center text-sm text-slate-400 dark:text-slate-500">Loading...</div>
                <div v-else-if="contacts.length === 0" class="py-8 text-center text-sm text-slate-400 dark:text-slate-500">No users found</div>
                <button
                    v-for="user in contacts"
                    :key="user.id"
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-700"
                    @click="selectContact(user)"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-400 text-xs font-semibold text-white">
                        {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ user.name }}</span>
                            <span class="shrink-0 rounded px-1 py-0.5 text-[10px] font-semibold" :class="roleBadgeClass(user.role)">
                                {{ user.role }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="truncate text-xs text-slate-400">
                                {{ getDmForContact(user.id)?.latest_message ? lastMessagePreview(getDmForContact(user.id)!) : contactLocation(user) }}
                            </span>
                            <span
                                v-if="getDmForContact(user.id)?.unread_count"
                                class="ml-2 flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white"
                            >
                                {{ getDmForContact(user.id)!.unread_count > 9 ? '9+' : getDmForContact(user.id)!.unread_count }}
                            </span>
                        </div>
                    </div>
                </button>
            </div>
        </template>
    </div>
</template>
