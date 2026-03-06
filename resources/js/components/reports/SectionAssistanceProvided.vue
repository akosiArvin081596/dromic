<script setup lang="ts">
import { emptyAssistanceProvided } from '@/composables/useReportCalculations';
import type { AssistanceProvided } from '@/types';

const rows = defineModel<AssistanceProvided[]>('rows', { required: true });

function addRow() {
    rows.value.push(emptyAssistanceProvided());
}

function removeRow(index: number) {
    rows.value.splice(index, 1);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">IX. Status of Assistance Provided</h3>
            <button type="button" class="bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="addRow">
                + Add Row
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Source</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Quantity</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Beneficiaries</th>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="(row, index) in rows" :key="index">
                        <td class="px-4 py-2 text-sm text-slate-500">{{ index + 1 }}</td>
                        <td class="px-4 py-2">
                            <input
                                v-model="row.source"
                                type="text"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                placeholder="e.g. DSWD, LGU"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model="row.type"
                                type="text"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                                placeholder="e.g. Food Pack, NFI"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model="row.quantity"
                                type="text"
                                class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model="row.beneficiaries"
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
