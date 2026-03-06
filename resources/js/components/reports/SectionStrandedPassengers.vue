<script setup lang="ts">
import { emptyStrandedPassenger } from '@/composables/useReportCalculations';
import type { StrandedPassenger } from '@/types';

const rows = defineModel<StrandedPassenger[]>('rows', { required: true });

function addRow() {
    rows.value.push(emptyStrandedPassenger());
}

function removeRow(index: number) {
    rows.value.splice(index, 1);
}

function totalField(field: 'passengers' | 'rolling_cargoes' | 'vessels'): number {
    return rows.value.reduce((sum, r) => sum + Number(r[field] || 0), 0);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">XVI. Stranded Passengers/Cargoes</h3>
            <button type="button" class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="addRow">
                + Add Row
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Port Name</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Passengers</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Rolling Cargoes</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Vessels</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Remarks</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="(row, index) in rows" :key="index">
                        <td class="px-4 py-2 text-sm text-slate-500">{{ index + 1 }}</td>
                        <td class="px-4 py-2">
                            <input
                                v-model="row.port_name"
                                type="text"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.passengers"
                                type="number"
                                min="0"
                                class="block w-24 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.rolling_cargoes"
                                type="number"
                                min="0"
                                class="block w-24 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.vessels"
                                type="number"
                                min="0"
                                class="block w-24 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
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
                        <td colspan="7" class="px-4 py-4 text-center text-sm text-slate-500">No entries. Click "+ Add Row" to add one.</td>
                    </tr>
                </tbody>
                <tfoot class="bg-indigo-50">
                    <tr class="font-semibold">
                        <td class="px-4 py-2" colspan="2">TOTAL</td>
                        <td class="px-4 py-2">{{ totalField('passengers').toLocaleString() }}</td>
                        <td class="px-4 py-2">{{ totalField('rolling_cargoes').toLocaleString() }}</td>
                        <td class="px-4 py-2">{{ totalField('vessels').toLocaleString() }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
