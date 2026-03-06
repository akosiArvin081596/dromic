<script setup lang="ts">
import { emptyClassSuspension } from '@/composables/useReportCalculations';
import type { Barangay, ClassSuspension } from '@/types';

import BarangaySelect from './BarangaySelect.vue';

defineProps<{
    barangays: Barangay[];
}>();

const rows = defineModel<ClassSuspension[]>('rows', { required: true });

function addRow() {
    rows.value.push(emptyClassSuspension());
}

function removeRow(index: number) {
    rows.value.splice(index, 1);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">X. Class Suspension</h3>
            <button type="button" class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="addRow">
                + Add Row
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Barangay</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Level</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Remarks</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="(row, index) in rows" :key="index">
                        <td class="px-4 py-2 text-sm text-slate-500">{{ index + 1 }}</td>
                        <td class="px-4 py-2">
                            <BarangaySelect v-model="row.barangay" :barangays="barangays" />
                        </td>
                        <td class="px-4 py-2">
                            <select
                                v-model="row.level"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Select Level</option>
                                <option value="Pre-school">Pre-school</option>
                                <option value="Elementary">Elementary</option>
                                <option value="Secondary">Secondary</option>
                                <option value="Senior High">Senior High</option>
                                <option value="Tertiary">Tertiary</option>
                                <option value="All Levels">All Levels</option>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model="row.date"
                                type="text"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                placeholder="e.g. Feb 24, 2026"
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
                            <button type="button" class="text-sm text-rose-600 hover:text-rose-800" @click="removeRow(index)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="6" class="px-4 py-4 text-center text-sm text-slate-500">No entries. Click "+ Add Row" to add one.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
