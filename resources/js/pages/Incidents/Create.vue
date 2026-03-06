<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CityMunicipality, Province } from '@/types';

const props = defineProps<{
    provinces: Province[];
    userRole: string;
    userCityMunicipalityId: number | null;
}>();

const isLgu = props.userRole === 'lgu';

const form = useForm({
    name: '',
    type: isLgu ? 'local' : 'massive',
    description: '',
    city_municipality_ids: isLgu && props.userCityMunicipalityId ? [props.userCityMunicipalityId] : ([] as number[]),
});

const showLguPicker = computed(() => !isLgu && form.type === 'local');

watch(
    () => form.type,
    (type) => {
        if (type === 'massive' && !isLgu) {
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
    form.post('/incidents');
}
</script>

<template>
    <AppLayout>
        <Head title="Create Incident" />
        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center space-x-4">
                <Link href="/incidents" class="text-sm text-slate-500 transition-colors hover:text-slate-700">&larr; Back to Incidents</Link>
                <h1 class="text-2xl font-bold text-slate-900">Create Incident</h1>
            </div>

            <form class="space-y-6 border border-slate-200 bg-white p-6" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Incident Name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        placeholder="e.g. Typhoon Aghon"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Type</label>
                    <select
                        v-model="form.type"
                        class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        :disabled="isLgu"
                    >
                        <option value="local">Local (Single LGU)</option>
                        <option value="massive">Massive (Multi Province)</option>
                    </select>
                    <p v-if="form.errors.type" class="mt-1 text-sm text-rose-600">{{ form.errors.type }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        maxlength="2000"
                        class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        placeholder="Brief description of the incident..."
                    ></textarea>
                </div>

                <!-- LGU Assignment (non-LGU users, local type only) -->
                <div v-if="showLguPicker">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Assign LGUs</label>
                    <div class="mb-3">
                        <select
                            v-model="selectedProvinceId"
                            class="block w-full border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                            <option :value="null">Select Province to filter</option>
                            <option v-for="province in provinces" :key="province.id" :value="province.id">{{ province.name }}</option>
                        </select>
                    </div>
                    <div v-if="selectedProvinceId" class="max-h-48 space-y-1 overflow-y-auto border border-slate-200 p-3">
                        <label v-for="cm in cityMunicipalities" :key="cm.id" class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                :checked="form.city_municipality_ids.includes(cm.id)"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                @change="toggleLgu(cm.id)"
                            />
                            <span class="text-sm text-slate-700">{{ cm.name }}</span>
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Selected: {{ form.city_municipality_ids.length }} LGU(s)</p>
                    <p v-if="form.errors.city_municipality_ids" class="mt-1 text-sm text-rose-600">{{ form.errors.city_municipality_ids }}</p>
                </div>

                <div v-else-if="isLgu" class="bg-indigo-50 p-3 text-sm text-indigo-700">
                    Your LGU will be automatically assigned to this incident.
                </div>
                <div v-else class="bg-slate-50 p-3 text-sm text-slate-600">LGUs can be assigned later once affected areas are identified.</div>

                <div class="flex items-center justify-end space-x-4">
                    <Link
                        href="/incidents"
                        class="border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="bg-indigo-600 px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Incident' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
