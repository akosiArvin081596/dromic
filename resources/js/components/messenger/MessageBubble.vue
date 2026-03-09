<script setup lang="ts">
import type { MessageData } from '@/types/messenger';

defineProps<{
    message: MessageData;
    isOwn: boolean;
    showSender: boolean;
}>();

function senderDisplayName(msg: MessageData): string {
    const name = msg.user?.name ?? msg.user_name ?? '';
    const location = msg.user?.city_municipality?.name ?? msg.user?.province?.name;
    return location ? `${name} (${location})` : name;
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

function formatTime(dateStr: string): string {
    const d = new Date(dateStr);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="flex" :class="isOwn ? 'justify-end' : 'justify-start'">
        <div class="max-w-[75%]">
            <div v-if="showSender && !isOwn" class="mb-0.5 flex items-center gap-1.5 px-1">
                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">{{ senderDisplayName(message) }}</span>
                <span
                    class="inline-flex rounded px-1 py-0.5 text-[10px] font-semibold"
                    :class="roleBadgeClass(message.user?.role ?? message.user_role ?? '')"
                >
                    {{ message.user?.role ?? message.user_role }}
                </span>
            </div>
            <div
                class="rounded-2xl px-3 py-2 text-sm break-words"
                :class="
                    isOwn
                        ? 'rounded-br-md bg-indigo-600 text-white'
                        : 'rounded-bl-md bg-slate-200 text-slate-900 dark:bg-slate-700 dark:text-slate-100'
                "
            >
                {{ message.body }}
            </div>
            <div class="mt-0.5 px-1 text-[10px] text-slate-400" :class="isOwn ? 'text-right' : 'text-left'">
                {{ formatTime(message.created_at) }}
            </div>
        </div>
    </div>
</template>
