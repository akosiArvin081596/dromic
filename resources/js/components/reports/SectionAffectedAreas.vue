<script setup lang="ts">
import type { AffectedArea, Barangay } from '@/types';

import BarangaySelect from './BarangaySelect.vue';

defineProps<{
    barangays: Barangay[];
    totalFamilies: number;
    totalPersons: number;
}>();

const rows = defineModel<AffectedArea[]>('rows', { required: true });

function selectedBarangaysExcept(index: number): string[] {
    return rows.value
        .filter((_, i) => i !== index)
        .map((r) => r.barangay)
        .filter((name) => name !== '');
}

function addRow() {
    rows.value.push({ barangay: '', families: 0, persons: 0 });
}

function removeRow(index: number) {
    rows.value.splice(index, 1);
}

function onFamiliesInput(row: AffectedArea) {
    if (row.families === 0) {
        row.persons = 0;
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">II. Affected Areas</h3>
            <button type="button" class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="addRow">
                + Add Row
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Barangay <span class="text-rose-500">*</span></th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">No. of Families <span class="text-rose-500">*</span></th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">No. of Persons <span class="text-rose-500">*</span></th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="(row, index) in rows" :key="index">
                        <td class="px-4 py-2 text-sm text-slate-500">{{ index + 1 }}</td>
                        <td class="px-4 py-2">
                            <BarangaySelect v-model="row.barangay" :barangays="barangays" :exclude="selectedBarangaysExcept(index)" />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.families"
                                type="number"
                                min="0"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                @input="onFamiliesInput(row)"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="row.persons"
                                type="number"
                                min="0"
                                :disabled="row.families === 0"
                                class="block w-full sm:text-sm"
                                :class="
                                    row.families === 0
                                        ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'
                                        : row.persons < row.families
                                          ? 'border-rose-400 bg-rose-50 focus:border-rose-500 focus:ring-1 focus:ring-rose-500'
                                          : 'border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500'
                                "
                            />
                            <p v-if="row.persons < row.families" class="mt-1 text-xs text-rose-600">
                                The number of persons must not be less than the number of families.
                            </p>
                        </td>
                        <td class="px-4 py-2">
                            <button
                                type="button"
                                class="text-sm text-rose-600 hover:text-rose-800"
                                :disabled="rows.length <= 1"
                                @click="removeRow(index)"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-indigo-50">
                    <tr class="font-semibold">
                        <td class="px-4 py-2" colspan="2">TOTAL</td>
                        <td class="px-4 py-2">{{ totalFamilies.toLocaleString() }}</td>
                        <td class="px-4 py-2">{{ totalPersons.toLocaleString() }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
