<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CityMunicipality, Province } from '@/types';
import type { Incident, IncidentCategory } from '@/types/incident';

const props = defineProps<{
    incident: Incident;
    provinces: Province[];
    categories: IncidentCategory[];
}>();

const form = useForm({
    category: props.incident.category,
    identifier: props.incident.identifier ?? '',
    type: props.incident.type,
    description: props.incident.description ?? '',
    status: props.incident.status,
    city_municipality_ids: (props.incident.city_municipalities ?? []).map((cm) => cm.id),
});

const showLguPicker = computed(() => form.type === 'local');

watch(
    () => form.type,
    (type) => {
        if (type === 'massive') {
            form.city_municipality_ids = [];
        }
    },
);

const selectedProvinceId = ref<number | null>(null);

const cityMunicipalities = computed<CityMunicipality[]>(() => {
    if (!selectedProvinceId.value) {
        return [];
    }
    const province = props.provinces.find((p) => p.id === selectedProvinceId.value);
    return province?.city_municipalities ?? [];
});

function toggleLgu(id: number) {
    const index = form.city_municipality_ids.indexOf(id);
    if (index > -1) {
        form.city_municipality_ids.splice(index, 1);
    } else {
        form.city_municipality_ids.push(id);
    }
}

function submit() {
    form.put(`/incidents/${props.incident.id}`);
}
</script>

<template>
    <AppLayout>
        <Head :title="`Edit ${incident.display_name ?? incident.name}`" />
        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center space-x-4">
                <Link
                    :href="`/incidents/${incident.id}`"
                    class="text-sm text-slate-500 transition-colors hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                    >&larr; Back to Incident</Link
                >
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Edit Incident</h1>
            </div>

            <form class="space-y-6 border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Category</label>
                    <select
                        v-model="form.category"
                        class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    >
                        <option value="" disabled>Select a category</option>
                        <option v-for="cat in categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
                    </select>
                    <p v-if="form.errors.category" class="mt-1 text-sm text-rose-600">{{ form.errors.category }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Identifier (optional)</label>
                    <input
                        v-model="form.identifier"
                        type="text"
                        class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        placeholder="e.g. Basyang, Intensity V"
                    />
                    <p v-if="form.errors.identifier" class="mt-1 text-sm text-rose-600">{{ form.errors.identifier }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Type</label>
                    <select
                        v-model="form.type"
                        class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    >
                        <option value="local">Local</option>
                        <option value="massive">Massive (Multi Province)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                    <select
                        v-model="form.status"
                        class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    >
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        maxlength="2000"
                        class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    ></textarea>
                </div>

                <!-- LGU Assignment (local type only) -->
                <div v-if="showLguPicker">
                    <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">Assign LGUs</label>
                    <div class="mb-3">
                        <select
                            v-model="selectedProvinceId"
                            class="block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                        >
                            <option :value="null">Select Province to filter</option>
                            <option v-for="province in provinces" :key="province.id" :value="province.id">{{ province.name }}</option>
                        </select>
                    </div>
                    <div v-if="selectedProvinceId" class="max-h-48 space-y-1 overflow-y-auto border border-slate-200 p-3 dark:border-slate-700">
                        <label v-for="cm in cityMunicipalities" :key="cm.id" class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                :checked="form.city_municipality_ids.includes(cm.id)"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                @change="toggleLgu(cm.id)"
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ cm.name }}</span>
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Selected: {{ form.city_municipality_ids.length }} LGU(s)</p>
                    <p v-if="form.errors.city_municipality_ids" class="mt-1 text-sm text-rose-600">{{ form.errors.city_municipality_ids }}</p>
                </div>

                <div class="flex items-center justify-end space-x-4">
                    <Link
                        :href="`/incidents/${incident.id}`"
                        class="border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="bg-indigo-600 px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : 'Update Incident' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
