<script setup lang="ts">
import { emptyCasualty } from '@/composables/useReportCalculations';
import type { Barangay, Casualty } from '@/types';

import BarangaySelect from './BarangaySelect.vue';

defineProps<{
    barangays: Barangay[];
}>();

const injured = defineModel<Casualty[]>('injured', { required: true });
const missing = defineModel<Casualty[]>('missing', { required: true });
const dead = defineModel<Casualty[]>('dead', { required: true });

function addRow(list: Casualty[]) {
    list.push(emptyCasualty());
}

function removeRow(list: Casualty[], index: number) {
    list.splice(index, 1);
}
</script>

<template>
    <div class="space-y-6">
        <h3 class="text-lg font-semibold text-slate-900">VI. Casualties</h3>

        <!-- Sub-tables for each category -->
        <template
            v-for="(section, sIdx) in [
                { label: 'A. Injured', model: injured },
                { label: 'B. Missing', model: missing },
                { label: 'C. Dead', model: dead },
            ]"
            :key="sIdx"
        >
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-700">{{ section.label }}</h4>
                    <button
                        type="button"
                        class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="addRow(section.model)"
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
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Age</th>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Sex</th>
                                <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Cause</th>
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
                                        v-model="row.name"
                                        type="text"
                                        class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model.number="row.age"
                                        type="number"
                                        min="0"
                                        class="block w-20 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <select
                                        v-model="row.sex"
                                        class="block w-24 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">--</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="row.cause"
                                        type="text"
                                        class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="row.remarks"
                                        type="text"
                                        class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <button type="button" class="text-sm text-rose-600 hover:text-rose-800" @click="removeRow(section.model, index)">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="section.model.length === 0">
                                <td colspan="8" class="px-4 py-4 text-center text-sm text-slate-500">No entries. Click "+ Add Row" to add one.</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-indigo-50">
                            <tr class="font-semibold">
                                <td class="px-4 py-2" colspan="7">TOTAL: {{ section.model.length }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>
