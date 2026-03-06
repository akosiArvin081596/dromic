import { ref } from 'vue';

export type ToastType = 'success' | 'error' | 'info';

export type Toast = {
    id: number;
    type: ToastType;
    message: string;
};

const toasts = ref<Toast[]>([]);
let nextId = 0;

function addToast(type: ToastType, message: string, duration = 5000): void {
    const id = nextId++;
    toasts.value.push({ id, type, message });

    setTimeout(() => {
        removeToast(id);
    }, duration);
}

function removeToast(id: number): void {
    const index = toasts.value.findIndex((t) => t.id === id);
    if (index !== -1) {
        toasts.value.splice(index, 1);
    }
}

function success(message: string, duration?: number): void {
    addToast('success', message, duration);
}

function error(message: string, duration?: number): void {
    addToast('error', message, duration);
}

function info(message: string, duration?: number): void {
    addToast('info', message, duration);
}

export function useToast() {
    return {
        toasts,
        success,
        error,
        info,
        removeToast,
    };
}
