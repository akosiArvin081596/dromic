<script setup lang="ts">
import { emptyOutsideEC } from '@/composables/useReportCalculations';
import type { Barangay, OutsideEvacuationCenter } from '@/types';

import BarangaySelect from './BarangaySelect.vue';

const props = defineProps<{
    allBarangays: Barangay[];
    barangays: Barangay[];
    affectedAreaLimits: Record<string, { families: number; persons: number }>;
    remainingFamiliesCum: number;
    remainingPersonsCum: number;
    totalFamiliesCum: number;
    totalFamiliesNow: number;
    totalPersonsCum: number;
    totalPersonsNow: number;
}>();

const rows = defineModel<OutsideEvacuationCenter[]>('rows', { required: true });

function addRow() {
    if (props.remainingFamiliesCum <= 0 || props.remainingPersonsCum <= 0) {
        alert('Cannot add row. The declared affected families/persons have already been fully allocated across Sections III-A, III-B, and III-C.');
        return;
    }
    rows.value.push(emptyOutsideEC());
}

function removeRow(index: number) {
    rows.value.splice(index, 1);
}

function clampCum(event: Event, row: OutsideEvacuationCenter, field: 'families' | 'persons') {
    const input = event.target as HTMLInputElement;
    const cumKey = field === 'families' ? 'families_cum' : 'persons_cum';
    const nowKey = field === 'families' ? 'families_now' : 'persons_now';
    const remaining = field === 'families' ? props.remainingFamiliesCum : props.remainingPersonsCum;
    const perBarangay = props.affectedAreaLimits[row.origin]?.[field];
    const limit = Math.max(0, perBarangay !== undefined ? Math.min(perBarangay, remaining) : remaining);
    if (row[cumKey] > limit) {
        row[cumKey] = limit;
        input.valueAsNumber = limit;
    }
    if (row[nowKey] > row[cumKey]) {
        row[nowKey] = row[cumKey];
    }
    if (field === 'families' && row.families_cum === 0) {
        row.persons_cum = 0;
        row.persons_now = 0;
    }
    if (field === 'families' && row.families_now === 0) {
        row.persons_now = 0;
    }
}

function clampNow(event: Event, row: OutsideEvacuationCenter, field: 'families' | 'persons') {
    const input = event.target as HTMLInputElement;
    const cumKey = field === 'families' ? 'families_cum' : 'persons_cum';
    const nowKey = field === 'families' ? 'families_now' : 'persons_now';
    if (row[nowKey] > row[cumKey]) {
        row[nowKey] = row[cumKey];
        input.valueAsNumber = row[cumKey];
    }
    if (field === 'families' && row.families_now === 0) {
        row.persons_now = 0;
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">III-B. Outside Evacuation Centers</h3>
            <button type="button" class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="addRow">
                + Add Row
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                        <th class="px-3 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Brgy of Shelter</th>
                        <th class="px-3 py-2 text-center text-xs font-medium tracking-wider text-slate-500" colspan="2">Families</th>
                        <th class="px-3 py-2 text-center text-xs font-medium tracking-wider text-slate-500" colspan="2">Persons</th>
                        <th class="px-3 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Origin</th>
                        <th class="px-3 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Actions</th>
                    </tr>
                    <tr class="bg-indigo-50/50">
                        <th colspan="2"></th>
                        <th class="px-3 py-1 text-center text-xs text-slate-500">CUM</th>
                        <th class="px-3 py-1 text-center text-xs text-slate-500">NOW</th>
                        <th class="px-3 py-1 text-center text-xs text-slate-500">CUM</th>
                        <th class="px-3 py-1 text-center text-xs text-slate-500">NOW</th>
                        <th colspan="2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="(row, index) in rows" :key="index">
                        <td class="px-3 py-2 text-sm text-slate-500">{{ index + 1 }}</td>
                        <td class="px-3 py-2">
                            <BarangaySelect v-model="row.barangay" :barangays="allBarangays" />
                        </td>
                        <td class="px-3 py-2">
                            <input
                                v-model.number="row.families_cum"
                                type="number"
                                min="0"
                                class="block w-24 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                @input="clampCum($event, row, 'families')"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input
                                v-model.number="row.families_now"
                                type="number"
                                min="0"
                                class="block w-24 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                @input="clampNow($event, row, 'families')"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input
                                v-model.number="row.persons_cum"
                                type="number"
                                min="0"
                                :disabled="row.families_cum === 0"
                                class="block w-24 sm:text-sm"
                                :class="
                                    row.families_cum === 0
                                        ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'
                                        : row.persons_cum < row.families_cum
                                          ? 'border-rose-400 bg-rose-50 focus:border-rose-500 focus:ring-1 focus:ring-rose-500'
                                          : 'border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500'
                                "
                                @input="clampCum($event, row, 'persons')"
                            />
                            <p v-if="row.persons_cum < row.families_cum" class="mt-1 text-xs text-rose-600">
                                The persons must not be less than the families.
                            </p>
                        </td>
                        <td class="px-3 py-2">
                            <input
                                v-model.number="row.persons_now"
                                type="number"
                                min="0"
                                :disabled="row.families_now === 0"
                                class="block w-24 sm:text-sm"
                                :class="
                                    row.families_now === 0
                                        ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'
                                        : row.persons_now < row.families_now
                                          ? 'border-rose-400 bg-rose-50 focus:border-rose-500 focus:ring-1 focus:ring-rose-500'
                                          : 'border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500'
                                "
                                @input="clampNow($event, row, 'persons')"
                            />
                            <p v-if="row.persons_now < row.families_now" class="mt-1 text-xs text-rose-600">
                                The persons must not be less than the families.
                            </p>
                        </td>
                        <td class="px-3 py-2">
                            <BarangaySelect
                                v-model="row.origin"
                                :barangays="barangays"
                                placeholder="Select Origin"
                                :class="!row.origin ? 'border-rose-400 ring-1 ring-rose-400' : ''"
                            />
                            <p v-if="!row.origin" class="mt-1 text-xs text-rose-600">Origin is required.</p>
                        </td>
                        <td class="px-3 py-2">
                            <button type="button" class="text-sm text-rose-600 hover:text-rose-800" @click="removeRow(index)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td colspan="8" class="px-4 py-4 text-center text-sm text-slate-500">No entries. Click "+ Add Row" to add one.</td>
                    </tr>
                </tbody>
                <tfoot class="bg-indigo-50">
                    <tr class="font-semibold">
                        <td class="px-3 py-2" colspan="2">TOTAL</td>
                        <td class="px-3 py-2 text-center">{{ totalFamiliesCum.toLocaleString() }}</td>
                        <td class="px-3 py-2 text-center">{{ totalFamiliesNow.toLocaleString() }}</td>
                        <td class="px-3 py-2 text-center">{{ totalPersonsCum.toLocaleString() }}</td>
                        <td class="px-3 py-2 text-center">{{ totalPersonsNow.toLocaleString() }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
