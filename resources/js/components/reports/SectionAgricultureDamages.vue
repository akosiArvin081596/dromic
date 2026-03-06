<script setup lang="ts">
import { emptyAgricultureDamage } from '@/composables/useReportCalculations';
import type { AgricultureDamage } from '@/types';

const rows = defineModel<AgricultureDamage[]>('rows', { required: true });

function addRow() {
    rows.value.push(emptyAgricultureDamage());
}

function removeRow(index: number) {
    rows.value.splice(index, 1);
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
}

function totalField(field: 'no_of_farmers' | 'area_affected_ha' | 'volume_mt' | 'estimated_cost'): number {
    return rows.value.reduce((sum, r) => sum + Number(r[field] || 0), 0);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">VIII. Damage &amp; Losses to Agriculture</h3>
            <button type="button" class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="addRow">
                + Add Row
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Commodity</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">No. of Farmers</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Area Affected (ha)</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Volume (MT)</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Estimated Cost</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="(row, index) in rows" :key="index">
                        <td class="px-4 py-2 text-sm text-slate-500">{{ index + 1 }}</td>
                        <td class="px-4 py-2">
                            <input
                                v-model="row.commodity"
                                type="text"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                placeholder="e.g. Rice, Corn"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.no_of_farmers"
                                type="number"
                                min="0"
                                class="block w-24 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.area_affected_ha"
                                type="number"
                                min="0"
                                step="0.01"
                                class="block w-28 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.volume_mt"
                                type="number"
                                min="0"
                                step="0.01"
                                class="block w-28 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
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
                        <td colspan="7" class="px-4 py-4 text-center text-sm text-slate-500">No entries. Click "+ Add Row" to add one.</td>
                    </tr>
                </tbody>
                <tfoot class="bg-indigo-50">
                    <tr class="font-semibold">
                        <td class="px-4 py-2" colspan="2">TOTAL</td>
                        <td class="px-4 py-2">{{ totalField('no_of_farmers').toLocaleString() }}</td>
                        <td class="px-4 py-2">{{ totalField('area_affected_ha').toLocaleString() }}</td>
                        <td class="px-4 py-2">{{ totalField('volume_mt').toLocaleString() }}</td>
                        <td class="px-4 py-2">{{ formatCurrency(totalField('estimated_cost')) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
