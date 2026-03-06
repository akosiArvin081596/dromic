<script setup lang="ts">
import { CircleAlert, CircleCheck, Info, X } from 'lucide-vue-next';

import { useToast } from '@/composables/useToast';

const { toasts, removeToast } = useToast();
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed right-0 bottom-0 z-[100] flex flex-col items-end gap-2 p-4">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-x-full opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-full opacity-0"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex w-80 items-start gap-3 rounded-lg border px-4 py-3 shadow-lg"
                    :class="{
                        'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950': toast.type === 'success',
                        'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950': toast.type === 'error',
                        'border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-sky-950': toast.type === 'info',
                    }"
                >
                    <CircleCheck v-if="toast.type === 'success'" :size="18" class="mt-0.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <CircleAlert v-else-if="toast.type === 'error'" :size="18" class="mt-0.5 shrink-0 text-red-600 dark:text-red-400" />
                    <Info v-else :size="18" class="mt-0.5 shrink-0 text-sky-600 dark:text-sky-400" />
                    <p
                        class="flex-1 text-sm font-medium"
                        :class="{
                            'text-emerald-800 dark:text-emerald-200': toast.type === 'success',
                            'text-red-800 dark:text-red-200': toast.type === 'error',
                            'text-sky-800 dark:text-sky-200': toast.type === 'info',
                        }"
                    >
                        {{ toast.message }}
                    </p>
                    <button
                        class="shrink-0 rounded p-0.5 transition-colors"
                        :class="{
                            'text-emerald-500 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-200': toast.type === 'success',
                            'text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200': toast.type === 'error',
                            'text-sky-500 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-200': toast.type === 'info',
                        }"
                        @click="removeToast(toast.id)"
                    >
                        <X :size="14" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
