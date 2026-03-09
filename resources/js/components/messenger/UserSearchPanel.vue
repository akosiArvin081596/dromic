<script setup lang="ts">
import { ArrowLeft, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useMessenger } from '@/composables/useMessenger';
import type { ContactableUser, Conversation } from '@/types/messenger';

const emit = defineEmits<{
    back: [];
    'start-dm': [conversation: Conversation];
}>();

const { fetchUsers, startDm } = useMessenger();

const searchQuery = ref('');
const users = ref<ContactableUser[]>([]);
const isLoading = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

async function loadUsers(search?: string): Promise<void> {
    isLoading.value = true;
    try {
        users.value = await fetchUsers(search);
    } finally {
        isLoading.value = false;
    }
}

watch(searchQuery, (val) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadUsers(val || undefined), 300);
});

loadUsers();

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

function userLocation(user: ContactableUser): string {
    if (user.city_municipality?.name) return user.city_municipality.name;
    if (user.province?.name) return user.province.name;
    if (user.region?.name) return user.region.name;
    return '';
}

async function selectUser(user: ContactableUser): Promise<void> {
    const conversation = await startDm(user.id);
    emit('start-dm', conversation);
}
</script>

<template>
    <div class="flex h-full flex-col">
        <!-- Header -->
        <div class="flex items-center gap-2 border-b border-slate-200 px-3 py-2.5 dark:border-slate-700">
            <button
                class="rounded p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                @click="emit('back')"
            >
                <ArrowLeft :size="18" />
            </button>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">New Message</h3>
        </div>

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
            <div v-if="isLoading" class="py-8 text-center text-sm text-slate-400 dark:text-slate-500">Loading...</div>
            <div v-else-if="users.length === 0" class="py-8 text-center text-sm text-slate-400 dark:text-slate-500">No users found</div>
            <button
                v-for="user in users"
                :key="user.id"
                class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-700"
                @click="selectUser(user)"
            >
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-300 text-xs font-semibold text-white">
                    {{ user.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ user.name }}</span>
                        <span class="shrink-0 rounded px-1 py-0.5 text-[10px] font-semibold" :class="roleBadgeClass(user.role)">
                            {{ user.role }}
                        </span>
                    </div>
                    <span v-if="userLocation(user)" class="text-xs text-slate-400">{{ userLocation(user) }}</span>
                </div>
            </button>
        </div>
    </div>
</template>
