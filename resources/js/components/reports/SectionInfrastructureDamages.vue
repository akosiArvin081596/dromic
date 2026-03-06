<script setup lang="ts">
import { emptyInfrastructureDamage } from '@/composables/useReportCalculations';
import type { Barangay, InfrastructureDamage } from '@/types';

import BarangaySelect from './BarangaySelect.vue';

defineProps<{
    barangays: Barangay[];
}>();

const rows = defineModel<InfrastructureDamage[]>('rows', { required: true });

function addRow() {
    rows.value.push(emptyInfrastructureDamage());
}

function removeRow(index: number) {
    rows.value.splice(index, 1);
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
}

function totalEstimatedCost(): number {
    return rows.value.reduce((sum, r) => sum + Number(r.estimated_cost || 0), 0);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">VII. Damages to Infrastructure</h3>
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
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Facility Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Description</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Estimated Cost</th>
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
                            <input
                                v-model="row.facility_type"
                                type="text"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                placeholder="e.g. School, Bridge"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model="row.description"
                                type="text"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.estimated_cost"
                                type="number"
                                min="0"
                                step="0.01"
                                class="block w-36 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
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
                <tfoot class="bg-indigo-50">
                    <tr class="font-semibold">
                        <td class="px-4 py-2" colspan="4">TOTAL</td>
                        <td class="px-4 py-2">{{ formatCurrency(totalEstimatedCost()) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
