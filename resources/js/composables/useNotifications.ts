import axios from 'axios';
import { computed, ref } from 'vue';
import type { Incident } from '@/types/incident';
import type { ReportNotificationData } from '@/types/report';
import type { RequestLetterNotificationData } from '@/types/request-letter';

export type AppNotification = {
    id: string;
    kind: 'incident' | 'report' | 'request_letter';
    incident?: Incident;
    report?: ReportNotificationData;
    request_letter?: RequestLetterNotificationData;
    read: boolean;
    timestamp: string;
};

type DatabaseNotification = {
    id: string;
    data: {
        kind: 'incident' | 'report' | 'request_letter';
        incident?: Incident;
        report?: ReportNotificationData;
        request_letter?: RequestLetterNotificationData;
    };
    read_at: string | null;
    created_at: string;
};

const notifications = ref<AppNotification[]>([]);
const unreadCount = computed(() => notifications.value.filter((n) => !n.read).length);
let initializedForUserId: number | null = null;

function mapFromDatabase(dbNotification: DatabaseNotification): AppNotification {
    return {
        id: dbNotification.id,
        kind: dbNotification.data.kind,
        incident: dbNotification.data.incident,
        report: dbNotification.data.report,
        request_letter: dbNotification.data.request_letter,
        read: dbNotification.read_at !== null,
        timestamp: dbNotification.created_at,
    };
}

async function initForUser(userId: number): Promise<void> {
    if (initializedForUserId === userId) {
        return;
    }
    initializedForUserId = userId;
    notifications.value = [];

    try {
        const response = await axios.get('/notifications');
        const dbNotifications: DatabaseNotification[] = response.data.data;
        notifications.value = dbNotifications.map(mapFromDatabase);
    } catch {
        // Silently fail — notifications will just be empty
    }
}

function addNotification(incident: Incident): void {
    // Real-time push from Echo — notification already saved server-side
    // Use a temporary ID; it will be reconciled on next fetch
    notifications.value.unshift({
        id: `rt-incident-${incident.id}-${Date.now()}`,
        kind: 'incident',
        incident,
        read: false,
        timestamp: new Date().toISOString(),
    });
}

function addReportNotification(report: ReportNotificationData): void {
    notifications.value.unshift({
        id: `rt-report-${report.id}-${Date.now()}`,
        kind: 'report',
        report,
        read: false,
        timestamp: new Date().toISOString(),
    });
}

function addRequestLetterNotification(requestLetter: RequestLetterNotificationData): void {
    notifications.value.unshift({
        id: `rt-rl-${requestLetter.id}-${Date.now()}`,
        kind: 'request_letter',
        request_letter: requestLetter,
        read: false,
        timestamp: new Date().toISOString(),
    });
}

async function markAsRead(id: string): Promise<void> {
    const notification = notifications.value.find((n) => n.id === id);
    if (notification) {
        notification.read = true;
    }

    // Only call API for real DB notifications (not real-time temporary IDs)
    if (!id.startsWith('rt-')) {
        try {
            await axios.patch(`/notifications/${id}/read`);
        } catch {
            // Silently fail
        }
    }
}

async function markAllAsRead(): Promise<void> {
    notifications.value.forEach((n) => (n.read = true));

    try {
        await axios.post('/notifications/mark-all-read');
    } catch {
        // Silently fail
    }
}

export function useNotifications() {
    return {
        notifications,
        unreadCount,
        initForUser,
        addNotification,
        addReportNotification,
        addRequestLetterNotification,
        markAsRead,
        markAllAsRead,
    };
}
