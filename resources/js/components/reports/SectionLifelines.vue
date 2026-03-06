<script setup lang="ts">
import { emptyLifelineRoadBridge, emptyLifelineUtility } from '@/composables/useReportCalculations';
import type { Barangay, LifelineRoadBridge, LifelineUtility } from '@/types';

import BarangaySelect from './BarangaySelect.vue';

defineProps<{
    barangays: Barangay[];
}>();

const roadsBridges = defineModel<LifelineRoadBridge[]>('roadsBridges', { required: true });
const power = defineModel<LifelineUtility[]>('power', { required: true });
const water = defineModel<LifelineUtility[]>('water', { required: true });
const communication = defineModel<LifelineUtility[]>('communication', { required: true });

function addRoadBridge() {
    roadsBridges.value.push(emptyLifelineRoadBridge());
}

function removeRoadBridge(index: number) {
    roadsBridges.value.splice(index, 1);
}

function addUtility(list: LifelineUtility[]) {
    list.push(emptyLifelineUtility());
}

function removeUtility(list: LifelineUtility[], index: number) {
    list.splice(index, 1);
}
</script>

<template>
    <div class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">XII. Status of Lifelines</h3>

        <!-- A. Roads & Bridges -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-semibold text-slate-700">A. Roads &amp; Bridges</h4>
                <button type="button" class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="addRoadBridge">
                    + Add Row
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 border">
                    <thead class="bg-indigo-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                            <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Barangay</th>
                            <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Status</th>
                            <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Remarks</th>
                            <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="(row, index) in roadsBridges" :key="index">
                            <td class="px-4 py-2 text-sm text-slate-500">{{ index + 1 }}</td>
                            <td class="px-4 py-2">
                                <BarangaySelect v-model="row.barangay" :barangays="barangays" />
                            </td>
                            <td class="px-4 py-2">
                                <input
                                    v-model="row.name"
                                    type="text"
                                    class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                />
                            </td>
                            <td class="px-4 py-2">
                                <select
                                    v-model="row.type"
                                    class="block w-28 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">--</option>
                                    <option value="Road">Road</option>
                                    <option value="Bridge">Bridge</option>
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <select
                                    v-model="row.status"
                                    class="block w-32 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">--</option>
                                    <option value="Passable">Passable</option>
                                    <option value="Not Passable">Not Passable</option>
                                    <option value="Under Repair">Under Repair</option>
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <input
                                    v-model="row.remarks"
                                    type="text"
                                    class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                />
                            </td>
                            <td class="px-4 py-2">
                                <button type="button" class="text-sm text-rose-600 hover:text-rose-800" @click="removeRoadBridge(index)">
                                    Remove
                                </button>
                            </td>
                        </tr>
                        <tr v-if="roadsBridges.length === 0">
                            <td colspan="7" class="px-4 py-4 text-center text-sm text-slate-500">No entries.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- B/C/D. Utility sub-sections -->
        <template
            v-for="(section, sIdx) in [
                { label: 'B. Power', model: power },
                { label: 'C. Water', model: water },
                { label: 'D. Communication', model: communication },
            ]"
            :key="sIdx"
        >
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-700">{{ section.label }}</h4>
                    <button
                        type="button"
                        class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="addUtility(section.model)"
                    >
                        + Add Row
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 border">
                        <thead class="bg-indigo-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Barangay</th>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Provider</th>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Remarks</th>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-for="(row, index) in section.model" :key="index">
                                <td class="px-4 py-2 text-sm text-slate-500">{{ index + 1 }}</td>
                                <td class="px-4 py-2">
                                    <BarangaySelect v-model="row.barangay" :barangays="barangays" />
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="row.provider"
                                        type="text"
                                        class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <select
                                        v-model="row.status"
                                        class="block w-32 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">--</option>
                                        <option value="Normal">Normal</option>
                                        <option value="Interrupted">Interrupted</option>
                                        <option value="Restored">Restored</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="row.remarks"
                                        type="text"
                                        class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <button
                                        type="button"
                                        class="text-sm text-rose-600 hover:text-rose-800"
                                        @click="removeUtility(section.model, index)"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="section.model.length === 0">
                                <td colspan="6" class="px-4 py-4 text-center text-sm text-slate-500">No entries.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
