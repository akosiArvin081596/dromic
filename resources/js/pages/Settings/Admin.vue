<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    dromicLogoUrl: string | null;
}>();

const logoFile = ref<File | null>(null);
const logoPreview = ref<string | null>(props.dromicLogoUrl);
const removeLogo = ref(false);
const processing = ref(false);
const errors = ref<Record<string, string>>({});

function onLogoChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (file) {
        logoFile.value = file;
        removeLogo.value = false;
        logoPreview.value = URL.createObjectURL(file);
    }
}

function clearLogo() {
    logoFile.value = null;
    logoPreview.value = null;
    removeLogo.value = true;
}

function submit() {
    processing.value = true;
    errors.value = {};

    const formData = new FormData();
    if (logoFile.value) {
        formData.append('dromic_logo', logoFile.value);
    }
    if (removeLogo.value) {
        formData.append('remove_dromic_logo', '1');
    }

    router.post('/settings/admin', formData, {
        preserveScroll: true,
        onError: (errs) => {
            errors.value = errs;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Admin Settings" />
        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Admin Settings</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure system-wide settings</p>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <!-- DROMIC Logo -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">DROMIC Logo</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        This logo appears at the top center of all printed reports. Max 2MB (PNG, JPG, WEBP)
                    </p>
                    <div class="mt-4 flex items-center gap-6">
                        <div
                            class="flex h-24 w-72 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-700"
                        >
                            <img v-if="logoPreview" :src="logoPreview" alt="DROMIC Logo" class="h-full w-full object-contain" />
                            <span v-else class="text-sm font-bold text-indigo-900 dark:text-indigo-300">DROMIC</span>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label
                                class="inline-flex cursor-pointer items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                Choose File
                                <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onLogoChange" />
                            </label>
                            <button v-if="logoPreview" type="button" class="text-left text-xs text-red-600 hover:text-red-800" @click="clearLogo">
                                Remove logo
                            </button>
                        </div>
                    </div>
                    <p v-if="errors.dromic_logo" class="mt-2 text-xs text-red-600">{{ errors.dromic_logo }}</p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
                        :disabled="processing"
                    >
                        {{ processing ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
