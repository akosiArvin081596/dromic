<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

type LguSettings = {
    signatory_1_name: string | null;
    signatory_1_designation: string | null;
    signatory_2_name: string | null;
    signatory_2_designation: string | null;
    signatory_3_name: string | null;
    signatory_3_designation: string | null;
    logo_url: string | null;
};

const props = defineProps<{
    settings: LguSettings | null;
}>();

const signatory1Name = ref(props.settings?.signatory_1_name ?? '');
const signatory1Designation = ref(props.settings?.signatory_1_designation ?? '');
const signatory2Name = ref(props.settings?.signatory_2_name ?? '');
const signatory2Designation = ref(props.settings?.signatory_2_designation ?? '');
const signatory3Name = ref(props.settings?.signatory_3_name ?? '');
const signatory3Designation = ref(props.settings?.signatory_3_designation ?? '');
const logoFile = ref<File | null>(null);
const logoPreview = ref<string | null>(props.settings?.logo_url ?? null);
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
    formData.append('signatory_1_name', signatory1Name.value);
    formData.append('signatory_1_designation', signatory1Designation.value);
    formData.append('signatory_2_name', signatory2Name.value);
    formData.append('signatory_2_designation', signatory2Designation.value);
    formData.append('signatory_3_name', signatory3Name.value);
    formData.append('signatory_3_designation', signatory3Designation.value);
    if (logoFile.value) {
        formData.append('logo', logoFile.value);
    }
    if (removeLogo.value) {
        formData.append('remove_logo', '1');
    }

    router.post('/settings/lgu', formData, {
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
        <Head title="LGU Settings" />
        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">LGU Settings</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure signatories and logo for your reports</p>

            <form class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2" @submit.prevent="submit">
                <!-- LGU Logo -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">LGU Logo</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upload your LGU logo for reports. Max 2MB (PNG, JPG, WEBP)</p>
                    <div class="mt-4 flex items-center gap-6">
                        <div
                            class="flex h-24 w-44 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-700"
                        >
                            <img v-if="logoPreview" :src="logoPreview" alt="LGU Logo" class="h-full w-full object-contain" />
                            <span v-else class="text-xs text-slate-400">No logo uploaded</span>
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
                    <p v-if="errors.logo" class="mt-2 text-xs text-red-600">{{ errors.logo }}</p>
                </div>

                <!-- Signatories -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2 dark:border-slate-700 dark:bg-slate-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Report Signatories</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Configure the 3 signatories that appear at the bottom of your reports
                    </p>

                    <!-- Row 1: Two signatories side by side -->
                    <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Signatory 1 (Left) -->
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700/50">
                            <h3 class="mb-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Prepared by</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                                    <input
                                        v-model="signatory1Name"
                                        type="text"
                                        placeholder="e.g. Juan dela Cruz"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Designation</label>
                                    <input
                                        v-model="signatory1Designation"
                                        type="text"
                                        placeholder="e.g. LDRRMO"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Signatory 2 (Right) -->
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-600 dark:bg-slate-700/50">
                            <h3 class="mb-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Reviewed by</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                                    <input
                                        v-model="signatory2Name"
                                        type="text"
                                        placeholder="e.g. Maria Santos"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Designation</label>
                                    <input
                                        v-model="signatory2Designation"
                                        type="text"
                                        placeholder="e.g. MSWDO"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: One signatory centered below -->
                    <div class="mt-6 flex justify-center">
                        <div class="w-full rounded-lg border border-slate-100 bg-slate-50 p-4 sm:w-1/2 dark:border-slate-600 dark:bg-slate-700/50">
                            <h3 class="mb-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Noted by</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Name</label>
                                    <input
                                        v-model="signatory3Name"
                                        type="text"
                                        placeholder="e.g. Pedro Reyes"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400">Designation</label>
                                    <input
                                        v-model="signatory3Designation"
                                        type="text"
                                        placeholder="e.g. Municipal Mayor"
                                        class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end lg:col-span-2">
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
