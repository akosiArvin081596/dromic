<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { MessageCircle, Moon, Sun } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import MessengerPanel from '@/components/messenger/MessengerPanel.vue';
import ToastContainer from '@/components/ToastContainer.vue';
import { useDarkMode } from '@/composables/useDarkMode';
import { useMessenger } from '@/composables/useMessenger';
import { useNotifications } from '@/composables/useNotifications';
import { useToast } from '@/composables/useToast';
import type { Auth } from '@/types';
import type { Incident } from '@/types/incident';
import type { MessageData } from '@/types/messenger';
import type { ReportNotificationData } from '@/types/report';
import type { RequestLetterNotificationData } from '@/types/request-letter';

function requestLetterMessage(rl: RequestLetterNotificationData): string {
    const role = user.value.role;

    if (rl.action === 'submitted') {
        return `${rl.city_municipality_name} submitted a request letter for ${rl.incident_name}`;
    }

    if (rl.action === 'endorsed') {
        if (role === 'lgu') {
            return `Your request letter for ${rl.incident_name} has been endorsed`;
        }
        return `${rl.actor_name} endorsed a request letter from ${rl.city_municipality_name}`;
    }

    if (rl.action === 'approved') {
        if (role === 'lgu') {
            return `Your request letter for ${rl.incident_name} has been approved`;
        }
        return `${rl.actor_name} approved a request letter from ${rl.city_municipality_name}`;
    }

    if (rl.action === 'delivered') {
        if (role === 'lgu') {
            return `A delivery has been recorded for your request letter (${rl.incident_name})`;
        }
        return `${rl.actor_name} recorded a delivery for ${rl.city_municipality_name}'s request letter`;
    }

    return `Request letter update for ${rl.incident_name}`;
}

const page = usePage<{ auth: Auth; name: string; unreadNotificationCount: number }>();
const appName = computed(() => page.props.name);
const user = computed(() => page.props.auth.user);

const isRd = computed(() => user.value.role === 'regional_director');
const dashboardHref = computed(() => (isRd.value ? '/rd-dashboard' : '/dashboard'));

const mobileMenuOpen = ref(false);
const notificationOpen = ref(false);
const notificationPanel = ref<HTMLElement | null>(null);
const messengerOpen = ref(false);
const messengerPanel = ref<HTMLElement | null>(null);

const { notifications, unreadCount, initForUser, addNotification, addReportNotification, addRequestLetterNotification, markAsRead, markAllAsRead } =
    useNotifications();

const { addIncomingMessage, unreadCount: messengerUnreadCount, fetchUnreadCount: fetchMessengerUnreadCount } = useMessenger();

const { isDark, toggle: toggleDarkMode } = useDarkMode();

const toast = useToast();

initForUser(user.value.id);

const removeFlashListener = router.on('flash', (event) => {
    const flashToast = event.detail.flash.toast;
    if (flashToast) {
        toast[flashToast.type](flashToast.message);
    }
});

useEcho<{ incident: Incident }>(`App.Models.User.${user.value.id}`, 'IncidentCreated', (payload) => {
    addNotification(payload.incident);
    toast.info(`New incident: ${payload.incident.name}`);
});

useEcho<{ report: ReportNotificationData }>(`App.Models.User.${user.value.id}`, 'ReportSubmitted', (payload) => {
    addReportNotification(payload.report);
    toast.info(`Report submitted for ${payload.report.incident_name}`);
});

useEcho<{ report: ReportNotificationData }>(`App.Models.User.${user.value.id}`, 'ReportValidated', (payload) => {
    addReportNotification(payload.report);
    toast.success(`Report validated for ${payload.report.incident_name}`);
});

useEcho<{ report: ReportNotificationData }>(`App.Models.User.${user.value.id}`, 'ReportReturned', (payload) => {
    addReportNotification(payload.report);
    toast.error(`Report returned for ${payload.report.incident_name}`);
});

useEcho<{ request_letter: RequestLetterNotificationData }>(`App.Models.User.${user.value.id}`, 'RequestLetterSubmitted', (payload) => {
    addRequestLetterNotification(payload.request_letter);
    toast.info(`Request letter submitted for ${payload.request_letter.incident_name}`);
});

useEcho<{ request_letter: RequestLetterNotificationData }>(`App.Models.User.${user.value.id}`, 'RequestLetterEndorsed', (payload) => {
    addRequestLetterNotification(payload.request_letter);
    toast.info(`Request letter endorsed for ${payload.request_letter.incident_name}`);
});

useEcho<{ request_letter: RequestLetterNotificationData }>(`App.Models.User.${user.value.id}`, 'RequestLetterApproved', (payload) => {
    addRequestLetterNotification(payload.request_letter);
    toast.success(`Request letter approved for ${payload.request_letter.incident_name}`);
});

useEcho<{ request_letter: RequestLetterNotificationData }>(`App.Models.User.${user.value.id}`, 'DeliveryRecorded', (payload) => {
    addRequestLetterNotification(payload.request_letter);
    toast.success(`Delivery recorded for ${payload.request_letter.incident_name}`);
});

useEcho<{ message: MessageData }>(`App.Models.User.${user.value.id}`, 'MessageSent', (payload) => {
    addIncomingMessage(payload.message);
    const body = payload.message.body;
    const preview = body.length > 80 ? body.slice(0, 80) + '...' : body;
    toast.info(`${payload.message.user_name}: ${preview}`);
});

function toggleNotifications() {
    notificationOpen.value = !notificationOpen.value;
    if (notificationOpen.value) messengerOpen.value = false;
}

function toggleMessenger() {
    messengerOpen.value = !messengerOpen.value;
    if (messengerOpen.value) notificationOpen.value = false;
}

function closeDropdowns(event: MouseEvent) {
    const target = event.target as Node;
    if (notificationPanel.value && !notificationPanel.value.contains(target) && document.contains(target)) {
        notificationOpen.value = false;
    }
    if (messengerPanel.value && !messengerPanel.value.contains(target) && document.contains(target)) {
        messengerOpen.value = false;
    }
}

function timeAgo(timestamp: string): string {
    const seconds = Math.floor((Date.now() - new Date(timestamp).getTime()) / 1000);
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    return `${Math.floor(hours / 24)}d ago`;
}

onMounted(() => {
    document.addEventListener('click', closeDropdowns);
    fetchMessengerUnreadCount();
});
onBeforeUnmount(() => {
    document.removeEventListener('click', closeDropdowns);
    removeFlashListener();
});

function roleBadgeClass(role: string): string {
    switch (role) {
        case 'admin':
            return 'bg-violet-400/20 text-violet-200 ring-1 ring-violet-400/30';
        case 'regional':
            return 'bg-amber-400/20 text-amber-200 ring-1 ring-amber-400/30';
        case 'provincial':
            return 'bg-sky-400/20 text-sky-200 ring-1 ring-sky-400/30';
        case 'lgu':
            return 'bg-emerald-400/20 text-emerald-200 ring-1 ring-emerald-400/30';
        case 'regional_director':
            return 'bg-rose-400/20 text-rose-200 ring-1 ring-rose-400/30';
        default:
            return 'bg-slate-400/20 text-slate-200 ring-1 ring-slate-400/30';
    }
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-900">
        <nav class="sticky top-0 z-50 bg-indigo-900 dark:bg-slate-800">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex">
                        <div class="flex shrink-0 items-center">
                            <Link :href="dashboardHref" class="text-xl font-bold text-white">{{ appName }}</Link>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-4">
                            <Link
                                :href="dashboardHref"
                                class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition-colors"
                                :class="
                                    $page.url === '/dashboard' || $page.url.startsWith('/rd-dashboard')
                                        ? 'border-white text-white'
                                        : 'border-transparent text-indigo-200 hover:border-indigo-400 hover:text-white'
                                "
                            >
                                Dashboard
                            </Link>
                            <Link
                                v-if="!isRd"
                                href="/incidents"
                                class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition-colors"
                                :class="
                                    $page.url.startsWith('/incidents')
                                        ? 'border-white text-white'
                                        : 'border-transparent text-indigo-200 hover:border-indigo-400 hover:text-white'
                                "
                            >
                                Incidents
                            </Link>
                            <Link
                                v-if="user.role === 'admin'"
                                href="/users"
                                class="inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium transition-colors"
                                :class="
                                    $page.url.startsWith('/users')
                                        ? 'border-white text-white'
                                        : 'border-transparent text-indigo-200 hover:border-indigo-400 hover:text-white'
                                "
                            >
                                Users
                            </Link>
                        </div>
                    </div>
                    <div class="hidden items-center space-x-3 sm:flex">
                        <template v-if="user">
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="roleBadgeClass(user.role)"
                            >
                                {{ user.role }}
                            </span>
                            <span class="text-sm text-indigo-100">{{ user.name }}</span>
                            <span v-if="(user as any).city_municipality_name" class="text-xs text-indigo-300">
                                {{ (user as any).city_municipality_name }}
                            </span>
                            <span v-else-if="(user as any).province_name" class="text-xs text-indigo-300">
                                {{ (user as any).province_name }}
                            </span>
                            <span v-else-if="(user as any).region_name" class="text-xs text-indigo-300">
                                {{ (user as any).region_name }}
                            </span>
                            <!-- Dark Mode Toggle -->
                            <button
                                class="relative p-1 text-indigo-200 transition-colors hover:text-white"
                                title="Toggle dark mode"
                                @click="toggleDarkMode"
                            >
                                <Moon v-if="!isDark" :size="20" />
                                <Sun v-else :size="20" />
                            </button>
                            <!-- Messenger Icon -->
                            <div ref="messengerPanel" class="relative">
                                <button class="relative p-1 text-indigo-200 transition-colors hover:text-white" @click.stop="toggleMessenger">
                                    <MessageCircle :size="20" />
                                    <span
                                        v-if="messengerUnreadCount > 0"
                                        class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                                    >
                                        {{ messengerUnreadCount > 9 ? '9+' : messengerUnreadCount }}
                                    </span>
                                </button>
                                <div v-show="messengerOpen" class="absolute right-0 z-50 mt-2">
                                    <MessengerPanel @close="messengerOpen = false" />
                                </div>
                            </div>
                            <!-- Notification Bell -->
                            <div ref="notificationPanel" class="relative">
                                <button class="relative p-1 text-indigo-200 transition-colors hover:text-white" @click.stop="toggleNotifications">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                                        />
                                    </svg>
                                    <span
                                        v-if="unreadCount > 0"
                                        class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                                    >
                                        {{ unreadCount > 9 ? '9+' : unreadCount }}
                                    </span>
                                </button>
                                <!-- Dropdown -->
                                <div
                                    v-show="notificationOpen"
                                    class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                                        <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">Notifications</span>
                                        <button
                                            v-if="unreadCount > 0"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            @click="markAllAsRead"
                                        >
                                            Mark all as read
                                        </button>
                                    </div>
                                    <div class="max-h-80 overflow-y-auto">
                                        <template v-for="notification in notifications" :key="notification.id">
                                            <!-- Incident notification -->
                                            <Link
                                                v-if="notification.kind === 'incident' && notification.incident"
                                                :href="`/incidents/${notification.incident.id}`"
                                                class="block border-b border-slate-100 px-4 py-3 transition-colors last:border-b-0 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-700"
                                                :class="!notification.read ? 'bg-indigo-50/50' : ''"
                                                @click="
                                                    markAsRead(notification.id);
                                                    notificationOpen = false;
                                                "
                                            >
                                                <div class="flex items-start justify-between">
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                                                            {{ notification.incident.name }}
                                                        </p>
                                                        <p v-if="notification.incident.message" class="mt-0.5 truncate text-xs text-slate-500">
                                                            {{ notification.incident.message }}
                                                        </p>
                                                        <div class="mt-1 flex items-center space-x-2">
                                                            <span
                                                                class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1"
                                                                :class="
                                                                    notification.incident.type === 'massive'
                                                                        ? 'bg-rose-50 text-rose-700 ring-rose-600/20'
                                                                        : 'bg-amber-50 text-amber-700 ring-amber-600/20'
                                                                "
                                                            >
                                                                {{ notification.incident.type }}
                                                            </span>
                                                            <span class="text-[11px] text-slate-400">{{ timeAgo(notification.timestamp) }}</span>
                                                        </div>
                                                    </div>
                                                    <span
                                                        v-if="!notification.read"
                                                        class="mt-1.5 ml-2 h-2 w-2 shrink-0 rounded-full bg-indigo-500"
                                                    ></span>
                                                </div>
                                            </Link>
                                            <!-- Report notification -->
                                            <Link
                                                v-else-if="notification.kind === 'report' && notification.report"
                                                :href="`/incidents/${notification.report.incident_id}/reports/${notification.report.id}`"
                                                class="block border-b border-slate-100 px-4 py-3 transition-colors last:border-b-0 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-700"
                                                :class="!notification.read ? 'bg-indigo-50/50' : ''"
                                                @click="
                                                    markAsRead(notification.id);
                                                    notificationOpen = false;
                                                "
                                            >
                                                <div class="flex items-start justify-between">
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                                                            {{
                                                                notification.report.report_type === 'initial'
                                                                    ? 'Initial Report'
                                                                    : notification.report.report_type === 'terminal'
                                                                      ? 'Terminal Report'
                                                                      : `Progress Report No. ${notification.report.sequence_number}`
                                                            }}
                                                        </p>
                                                        <p v-if="notification.report.message" class="mt-0.5 truncate text-xs text-slate-500">
                                                            {{ notification.report.message }}
                                                        </p>
                                                        <p v-else class="mt-0.5 truncate text-xs text-slate-500">
                                                            {{ notification.report.incident_name }} &middot;
                                                            {{ notification.report.city_municipality_name }}
                                                        </p>
                                                        <div class="mt-1 flex items-center space-x-2">
                                                            <span
                                                                v-if="notification.report.status === 'for_validation'"
                                                                class="inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-amber-600/20"
                                                            >
                                                                for validation
                                                            </span>
                                                            <span
                                                                v-else-if="notification.report.status === 'validated'"
                                                                class="inline-flex items-center rounded-full bg-green-50 px-1.5 py-0.5 text-[10px] font-semibold text-green-700 ring-1 ring-green-600/20"
                                                            >
                                                                validated
                                                            </span>
                                                            <span
                                                                v-else-if="notification.report.status === 'returned'"
                                                                class="inline-flex items-center rounded-full bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold text-red-700 ring-1 ring-red-600/20"
                                                            >
                                                                returned
                                                            </span>
                                                            <span class="text-[11px] text-slate-400">{{ timeAgo(notification.timestamp) }}</span>
                                                        </div>
                                                        <p
                                                            v-if="notification.report.status === 'returned' && notification.report.return_reason"
                                                            class="mt-1 truncate text-xs text-red-600"
                                                        >
                                                            {{ notification.report.return_reason }}
                                                        </p>
                                                    </div>
                                                    <span
                                                        v-if="!notification.read"
                                                        class="mt-1.5 ml-2 h-2 w-2 shrink-0 rounded-full bg-indigo-500"
                                                    ></span>
                                                </div>
                                            </Link>
                                            <!-- Request letter notification -->
                                            <Link
                                                v-else-if="notification.kind === 'request_letter' && notification.request_letter"
                                                :href="`/incidents/${notification.request_letter.incident_id}`"
                                                class="block border-b border-slate-100 px-4 py-3 transition-colors last:border-b-0 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-700"
                                                :class="!notification.read ? 'bg-indigo-50/50' : ''"
                                                @click="
                                                    markAsRead(notification.id);
                                                    notificationOpen = false;
                                                "
                                            >
                                                <div class="flex items-start justify-between">
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">Request Letter</p>
                                                        <p class="mt-0.5 truncate text-xs text-slate-500">
                                                            {{ requestLetterMessage(notification.request_letter) }}
                                                        </p>
                                                        <div class="mt-1 flex items-center space-x-2">
                                                            <span
                                                                class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold ring-1"
                                                                :class="
                                                                    notification.request_letter.action === 'endorsed'
                                                                        ? 'bg-amber-50 text-amber-700 ring-amber-600/20'
                                                                        : notification.request_letter.action === 'approved'
                                                                          ? 'bg-green-50 text-green-700 ring-green-600/20'
                                                                          : notification.request_letter.action === 'delivered'
                                                                            ? 'bg-sky-50 text-sky-700 ring-sky-600/20'
                                                                            : 'bg-purple-50 text-purple-700 ring-purple-600/20'
                                                                "
                                                            >
                                                                {{ notification.request_letter.action }}
                                                            </span>
                                                            <span class="text-[11px] text-slate-400">{{ timeAgo(notification.timestamp) }}</span>
                                                        </div>
                                                    </div>
                                                    <span
                                                        v-if="!notification.read"
                                                        class="mt-1.5 ml-2 h-2 w-2 shrink-0 rounded-full bg-indigo-500"
                                                    ></span>
                                                </div>
                                            </Link>
                                        </template>
                                        <div
                                            v-if="notifications.length === 0"
                                            class="px-4 py-8 text-center text-sm text-slate-400 dark:text-slate-500"
                                        >
                                            No notifications yet
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button class="text-sm text-indigo-200 transition-colors hover:text-white" @click="logout">Logout</button>
                        </template>
                    </div>
                    <!-- Mobile hamburger -->
                    <div class="flex items-center sm:hidden">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center p-2 text-indigo-200 hover:bg-indigo-800 hover:text-white"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path
                                    v-if="!mobileMenuOpen"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                                />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Mobile menu -->
            <div v-show="mobileMenuOpen" class="border-t border-indigo-800 sm:hidden">
                <div class="space-y-1 px-4 pt-2 pb-3">
                    <Link
                        :href="dashboardHref"
                        class="block px-3 py-2 text-base font-medium"
                        :class="
                            $page.url === '/dashboard' || $page.url.startsWith('/rd-dashboard')
                                ? 'bg-indigo-800 text-white'
                                : 'text-indigo-200 hover:bg-indigo-800 hover:text-white'
                        "
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-if="!isRd"
                        href="/incidents"
                        class="block px-3 py-2 text-base font-medium"
                        :class="
                            $page.url.startsWith('/incidents') ? 'bg-indigo-800 text-white' : 'text-indigo-200 hover:bg-indigo-800 hover:text-white'
                        "
                    >
                        Incidents
                    </Link>
                    <Link
                        v-if="user.role === 'admin'"
                        href="/users"
                        class="block px-3 py-2 text-base font-medium"
                        :class="$page.url.startsWith('/users') ? 'bg-indigo-800 text-white' : 'text-indigo-200 hover:bg-indigo-800 hover:text-white'"
                    >
                        Users
                    </Link>
                </div>
                <div v-if="user" class="border-t border-indigo-800 px-4 pt-3 pb-3">
                    <div class="flex items-center space-x-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="roleBadgeClass(user.role)">
                            {{ user.role }}
                        </span>
                        <span class="text-sm text-indigo-100">{{ user.name }}</span>
                    </div>
                    <button
                        class="mt-2 flex w-full items-center px-3 py-2 text-sm text-indigo-200 hover:bg-indigo-800 hover:text-white"
                        @click="toggleDarkMode"
                    >
                        <Moon v-if="!isDark" :size="16" class="mr-2" />
                        <Sun v-else :size="16" class="mr-2" />
                        {{ isDark ? 'Light Mode' : 'Dark Mode' }}
                    </button>
                    <Link href="/messenger" class="mt-2 flex items-center px-3 py-2 text-sm text-indigo-200 hover:bg-indigo-800 hover:text-white">
                        <MessageCircle :size="16" class="mr-2" />
                        Messages
                        <span
                            v-if="messengerUnreadCount > 0"
                            class="ml-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white"
                        >
                            {{ messengerUnreadCount > 9 ? '9+' : messengerUnreadCount }}
                        </span>
                    </Link>
                    <Link
                        href="#"
                        class="mt-2 flex items-center px-3 py-2 text-sm text-indigo-200 hover:bg-indigo-800 hover:text-white"
                        @click.prevent="notificationOpen = !notificationOpen"
                    >
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                            />
                        </svg>
                        Notifications
                        <span
                            v-if="unreadCount > 0"
                            class="ml-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold text-white"
                        >
                            {{ unreadCount > 9 ? '9+' : unreadCount }}
                        </span>
                    </Link>
                    <button
                        class="mt-2 block w-full px-3 py-2 text-left text-sm text-indigo-200 hover:bg-indigo-800 hover:text-white"
                        @click="logout"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </nav>

        <main>
            <slot />
        </main>

        <ToastContainer />
    </div>
</template>
