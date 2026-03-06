<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, ChevronUp, Send } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useMessenger } from '@/composables/useMessenger';
import type { Auth } from '@/types';
import type { Conversation } from '@/types/messenger';
import MessageBubble from './MessageBubble.vue';

const props = defineProps<{
    conversation: Conversation;
}>();

const emit = defineEmits<{
    back: [];
}>();

const page = usePage<{ auth: Auth }>();
const userId = computed(() => page.props.auth.user.id);

const { messages, hasMoreMessages, fetchMessages, sendMessage } = useMessenger();

const messageInput = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const isSending = ref(false);
const isLoadingMore = ref(false);

const conversationName = computed(() => {
    if (props.conversation.type === 'group') {
        return props.conversation.province?.name ?? 'Group Chat';
    }
    const other = props.conversation.participants.find((p) => p.id !== userId.value);
    if (!other) return 'Direct Message';
    const location = other.city_municipality?.name ?? other.province?.name;
    return location ? `${other.name} (${location})` : other.name;
});

const isGroup = computed(() => props.conversation.type === 'group');
const showMembers = ref(false);

function roleBadgeClass(role: string): string {
    switch (role) {
        case 'admin':
            return 'bg-violet-100 text-violet-700';
        case 'regional':
            return 'bg-amber-100 text-amber-700';
        case 'provincial':
            return 'bg-sky-100 text-sky-700';
        case 'lgu':
            return 'bg-emerald-100 text-emerald-700';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}

function memberLocation(participant: (typeof props.conversation.participants)[0]): string {
    return participant.city_municipality?.name ?? participant.province?.name ?? '';
}

async function loadMessages(): Promise<void> {
    await fetchMessages(props.conversation.id);
    await nextTick();
    scrollToBottom();
}

async function loadOlderMessages(): Promise<void> {
    if (!hasMoreMessages.value || isLoadingMore.value) return;
    isLoadingMore.value = true;

    const container = messagesContainer.value;
    const prevHeight = container?.scrollHeight ?? 0;

    const oldest = messages.value[0];
    if (oldest) {
        await fetchMessages(props.conversation.id, oldest.id);
    }

    await nextTick();
    if (container) {
        container.scrollTop = container.scrollHeight - prevHeight;
    }
    isLoadingMore.value = false;
}

function handleScroll(): void {
    const container = messagesContainer.value;
    if (container && container.scrollTop < 50) {
        loadOlderMessages();
    }
}

async function handleSend(): Promise<void> {
    const body = messageInput.value.trim();
    if (!body || isSending.value) return;

    isSending.value = true;
    messageInput.value = '';
    try {
        await sendMessage(props.conversation.id, body);
        await nextTick();
        scrollToBottom();
    } finally {
        isSending.value = false;
    }
}

function scrollToBottom(): void {
    const container = messagesContainer.value;
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

watch(
    () => messages.value.length,
    async () => {
        const container = messagesContainer.value;
        if (container) {
            const isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
            if (isNearBottom) {
                await nextTick();
                scrollToBottom();
            }
        }
    },
);

onMounted(loadMessages);
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Header -->
        <div class="flex items-center gap-2 border-b border-slate-200 px-3 py-2.5">
            <button class="rounded p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600" @click="emit('back')">
                <ArrowLeft :size="18" />
            </button>
            <div class="min-w-0 flex-1">
                <h3 class="truncate text-sm font-semibold text-slate-900">{{ conversationName }}</h3>
                <button
                    v-if="isGroup"
                    class="flex items-center gap-0.5 text-xs text-slate-400 hover:text-slate-600"
                    @click="showMembers = !showMembers"
                >
                    {{ conversation.participants.length }} members
                    <ChevronDown v-if="!showMembers" :size="12" />
                    <ChevronUp v-else :size="12" />
                </button>
            </div>
        </div>

        <!-- Members List -->
        <div v-if="isGroup && showMembers" class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <div class="max-h-40 space-y-1.5 overflow-y-auto">
                <div v-for="participant in conversation.participants" :key="participant.id" class="flex items-center gap-2">
                    <div
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold text-white"
                        :class="participant.id === userId ? 'bg-indigo-500' : 'bg-slate-400'"
                    >
                        {{ participant.name.charAt(0).toUpperCase() }}
                    </div>
                    <span class="truncate text-xs text-slate-700">
                        {{ participant.id === userId ? 'You' : participant.name }}
                        <span v-if="memberLocation(participant)" class="text-slate-400">({{ memberLocation(participant) }})</span>
                    </span>
                    <span class="inline-flex shrink-0 rounded px-1 py-0.5 text-[10px] font-semibold" :class="roleBadgeClass(participant.role)">
                        {{ participant.role }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 space-y-2 overflow-y-auto px-3 py-3" @scroll="handleScroll">
            <div v-if="isLoadingMore" class="py-2 text-center text-xs text-slate-400">Loading...</div>
            <div v-if="messages.length === 0 && !isLoadingMore" class="flex h-full items-center justify-center text-sm text-slate-400">
                No messages yet. Say hello!
            </div>
            <MessageBubble v-for="msg in messages" :key="msg.id" :message="msg" :is-own="msg.user_id === userId" :show-sender="isGroup" />
        </div>

        <!-- Input -->
        <div class="border-t border-slate-200 px-3 py-2">
            <form class="flex items-center gap-2" @submit.prevent="handleSend">
                <input
                    v-model="messageInput"
                    type="text"
                    placeholder="Type a message..."
                    maxlength="2000"
                    class="flex-1 rounded-full border border-slate-200 px-4 py-2 text-sm transition-colors focus:border-indigo-300 focus:ring-0 focus:outline-none"
                    @keydown.enter.prevent="handleSend"
                />
                <button
                    type="submit"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="!messageInput.trim() || isSending"
                >
                    <Send :size="16" />
                </button>
            </form>
        </div>
    </div>
</template>
