<script setup lang="ts">
import { computed, watch } from 'vue';
import type { AffectedArea, InsideEvacuationCenter, NonIdp, OutsideEvacuationCenter } from '@/types';

const props = defineProps<{
    affectedAreas: AffectedArea[];
    insideECs: InsideEvacuationCenter[];
    outsideECs: OutsideEvacuationCenter[];
}>();

const rows = defineModel<NonIdp[]>('rows', { required: true });

const computedRows = computed(() => {
    return props.affectedAreas
        .filter((a) => a.barangay !== '')
        .map((area) => {
            const insideFamilies = props.insideECs
                .filter((ec) => ec.barangay === area.barangay)
                .reduce((sum, ec) => sum + Number(ec.families_cum || 0), 0);
            const insidePersons = props.insideECs
                .filter((ec) => ec.barangay === area.barangay)
                .reduce((sum, ec) => sum + Number(ec.persons_cum || 0), 0);
            const outsideFamilies = props.outsideECs
                .filter((ec) => ec.barangay === area.barangay)
                .reduce((sum, ec) => sum + Number(ec.families_cum || 0), 0);
            const outsidePersons = props.outsideECs
                .filter((ec) => ec.barangay === area.barangay)
                .reduce((sum, ec) => sum + Number(ec.persons_cum || 0), 0);

            return {
                barangay: area.barangay,
                families_cum: Math.max(0, Number(area.families || 0) - insideFamilies - outsideFamilies),
                persons_cum: Math.max(0, Number(area.persons || 0) - insidePersons - outsidePersons),
            };
        })
        .filter((row) => row.families_cum > 0 || row.persons_cum > 0);
});

const totalFamiliesCum = computed(() => computedRows.value.reduce((sum, r) => sum + r.families_cum, 0));
const totalPersonsCum = computed(() => computedRows.value.reduce((sum, r) => sum + r.persons_cum, 0));

const inconsistentRows = computed(() => computedRows.value.filter((row) => row.families_cum === 0 && row.persons_cum > 0));

// Keep form.non_idps in sync with computed values
watch(
    computedRows,
    (newRows) => {
        rows.value = newRows;
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">III-C. Non IDPs (Served Outside Evacuation Centers, Not Displaced)</h3>
            <span class="text-xs text-slate-500">Auto-calculated from Sections II, III-A, and III-B</span>
        </div>

        <div v-if="inconsistentRows.length > 0" class="border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">Data inconsistency detected:</p>
            <ul class="mt-1 list-inside list-disc">
                <li v-for="row in inconsistentRows" :key="row.barangay">
                    {{ row.barangay }}: {{ row.persons_cum }} person(s) but 0 families. Check the families/persons entries in Sections II, III-A, and
                    III-B for this barangay.
                </li>
            </ul>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium tracking-wider text-slate-500">#</th>
                        <th class="px-3 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Barangay</th>
                        <th class="px-3 py-2 text-center text-xs font-medium tracking-wider text-slate-500">Families</th>
                        <th class="px-3 py-2 text-center text-xs font-medium tracking-wider text-slate-500">Persons</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="(row, index) in computedRows" :key="row.barangay">
                        <td class="px-3 py-2 text-sm text-slate-500">{{ index + 1 }}</td>
                        <td class="px-3 py-2 text-sm text-slate-900">{{ row.barangay }}</td>
                        <td class="px-3 py-2 text-center text-sm text-slate-900">{{ row.families_cum.toLocaleString() }}</td>
                        <td class="px-3 py-2 text-center text-sm text-slate-900">{{ row.persons_cum.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="computedRows.length === 0">
                        <td colspan="4" class="px-4 py-4 text-center text-sm text-slate-500">
                            No non-IDPs. All affected families/persons are accounted for in Sections III-A and III-B.
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-indigo-50">
                    <tr class="font-semibold">
                        <td class="px-3 py-2" colspan="2">TOTAL</td>
                        <td class="px-3 py-2 text-center">{{ totalFamiliesCum.toLocaleString() }}</td>
                        <td class="px-3 py-2 text-center">{{ totalPersonsCum.toLocaleString() }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
