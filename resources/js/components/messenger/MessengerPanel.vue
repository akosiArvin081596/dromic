<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useMessenger } from '@/composables/useMessenger';
import type { Conversation } from '@/types/messenger';
import ChatView from './ChatView.vue';
import ConversationList from './ConversationList.vue';

const emit = defineEmits<{
    close: [];
}>();

const { conversations, fetchConversations, openConversation, closeConversation, fetchUnreadCount } = useMessenger();

type View = 'list' | 'chat';
type Tab = 'chat' | 'groups' | 'contacts';
const currentView = ref<View>('list');
const activeConversation = ref<Conversation | null>(null);
const returnTab = ref<Tab>('chat');

function selectConversation(conversation: Conversation, tab: Tab): void {
    activeConversation.value = conversation;
    returnTab.value = tab;
    openConversation(conversation.id);
    currentView.value = 'chat';
}

function goBack(): void {
    closeConversation();
    activeConversation.value = null;
    currentView.value = 'list';
    fetchConversations();
    fetchUnreadCount();
}

onMounted(fetchConversations);
</script>

<template>
    <div
        class="flex h-[32rem] w-96 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-800"
    >
        <!-- Top bar (list view only) -->
        <div v-if="currentView === 'list'" class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Messages</h2>
            <button
                class="rounded p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                @click="emit('close')"
            >
                <X :size="16" />
            </button>
        </div>

        <!-- Views -->
        <div class="flex-1 overflow-hidden">
            <ConversationList v-if="currentView === 'list'" :conversations="conversations" :initial-tab="returnTab" @select="selectConversation" />
            <ChatView v-else-if="currentView === 'chat' && activeConversation" :conversation="activeConversation" @back="goBack" />
        </div>
    </div>
</template>
