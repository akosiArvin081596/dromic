<script setup lang="ts">
import { computed } from 'vue';
import { emptyDamagedHouse } from '@/composables/useReportCalculations';
import type { Barangay, DamagedHouse } from '@/types';

import BarangaySelect from './BarangaySelect.vue';

const props = defineProps<{
    barangays: Barangay[];
    totalTotallyDamaged: number;
    totalPartiallyDamaged: number;
    totalEstimatedCost: number;
    totalAffectedFamilies: number;
}>();

const totalDamagedHouses = computed(() => props.totalTotallyDamaged + props.totalPartiallyDamaged);
const exceedsAffectedFamilies = computed(() => totalDamagedHouses.value > props.totalAffectedFamilies);

const rows = defineModel<DamagedHouse[]>('rows', { required: true });

function addRow() {
    rows.value.push(emptyDamagedHouse());
}

function removeRow(index: number) {
    rows.value.splice(index, 1);
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">IV. Damaged Houses</h3>
            <button type="button" class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="addRow">
                + Add Row
            </button>
        </div>

        <div v-if="exceedsAffectedFamilies" class="border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">
                Total damaged houses (<strong>{{ totalDamagedHouses.toLocaleString() }}</strong>) exceeds the total affected families (<strong>{{
                    totalAffectedFamilies.toLocaleString()
                }}</strong>) declared in Section II.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Barangay</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Totally Damaged</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Partially Damaged</th>
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
                                v-model.number="row.totally_damaged"
                                type="number"
                                min="0"
                                class="block w-24 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.partially_damaged"
                                type="number"
                                min="0"
                                class="block w-24 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
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
                        <td class="px-4 py-2" colspan="2">TOTAL</td>
                        <td class="px-4 py-2">{{ totalTotallyDamaged.toLocaleString() }}</td>
                        <td class="px-4 py-2">{{ totalPartiallyDamaged.toLocaleString() }}</td>
                        <td class="px-4 py-2">{{ formatCurrency(totalEstimatedCost) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
