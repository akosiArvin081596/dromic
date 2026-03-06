<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import ChatView from '@/components/messenger/ChatView.vue';
import ConversationList from '@/components/messenger/ConversationList.vue';
import { useMessenger } from '@/composables/useMessenger';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Conversation } from '@/types/messenger';

defineOptions({ layout: AppLayout });

const { conversations, fetchConversations, openConversation, closeConversation } = useMessenger();

type View = 'list' | 'chat';
const currentView = ref<View>('list');
const activeConversation = ref<Conversation | null>(null);

function selectConversation(conversation: Conversation): void {
    activeConversation.value = conversation;
    openConversation(conversation.id);
    currentView.value = 'chat';
}

function goBack(): void {
    closeConversation();
    activeConversation.value = null;
    currentView.value = 'list';
    fetchConversations();
}

onMounted(fetchConversations);
</script>

<template>
    <Head title="Messages" />

    <div class="mx-auto flex h-[calc(100vh-4rem)] max-w-3xl flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
            <div class="flex items-center gap-2">
                <button
                    v-if="currentView !== 'list'"
                    class="rounded p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                    @click="goBack"
                >
                    <ArrowLeft :size="20" />
                </button>
                <h1 class="text-lg font-semibold text-slate-900">
                    {{ currentView === 'list' ? 'Messages' : activeConversation?.participants?.map((p) => p.name).join(', ') || 'Chat' }}
                </h1>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-hidden bg-white">
            <ConversationList v-if="currentView === 'list'" :conversations="conversations" @select="selectConversation" />
            <ChatView v-else-if="currentView === 'chat' && activeConversation" :conversation="activeConversation" @back="goBack" />
        </div>
    </div>
</template>
