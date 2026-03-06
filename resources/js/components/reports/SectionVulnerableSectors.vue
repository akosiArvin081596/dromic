<script setup lang="ts">
import { computed } from 'vue';
import type { AgeGenderBreakdown, VulnerableSectors } from '@/types';

const props = defineProps<{
    totalMaleCum: number;
    totalMaleNow: number;
    totalFemaleCum: number;
    totalFemaleNow: number;
    insideEcPersonsCum: number;
    insideEcPersonsNow: number;
}>();

const totalCum = computed(() => props.totalMaleCum + props.totalFemaleCum);
const totalNow = computed(() => props.totalMaleNow + props.totalFemaleNow);
const exceedsCum = computed(() => totalCum.value > props.insideEcPersonsCum);
const exceedsNow = computed(() => totalNow.value > props.insideEcPersonsNow);

const sectors = defineModel<VulnerableSectors>('sectors', { required: true });

const sectorNames: (keyof VulnerableSectors)[] = ['Pregnant/Lactating', 'Solo Parent', 'PWD', 'Indigenous People', 'Senior Citizen'];

function sectorTotal(group: AgeGenderBreakdown, type: 'cum' | 'now'): number {
    if (type === 'cum') {
        return Number(group.male_cum || 0) + Number(group.female_cum || 0);
    }
    return Number(group.male_now || 0) + Number(group.female_now || 0);
}
</script>

<template>
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-slate-900">Vulnerable Sectors (Inside ECs)</h3>

        <div v-if="exceedsCum || exceedsNow" class="border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">Total persons exceeds Section III-A (Inside ECs):</p>
            <ul class="mt-1 list-inside list-disc">
                <li v-if="exceedsCum">
                    CUM: Vulnerable Sectors total is <strong>{{ totalCum.toLocaleString() }}</strong>, but Inside ECs total is
                    <strong>{{ insideEcPersonsCum.toLocaleString() }}</strong>
                </li>
                <li v-if="exceedsNow">
                    NOW: Vulnerable Sectors total is <strong>{{ totalNow.toLocaleString() }}</strong>, but Inside ECs total is
                    <strong>{{ insideEcPersonsNow.toLocaleString() }}</strong>
                </li>
            </ul>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 border">
                <thead class="bg-indigo-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium tracking-wider text-slate-500">Sector</th>
                        <th class="px-4 py-2 text-center text-xs font-medium tracking-wider text-slate-500" colspan="2">Male</th>
                        <th class="px-4 py-2 text-center text-xs font-medium tracking-wider text-slate-500" colspan="2">Female</th>
                        <th class="px-4 py-2 text-center text-xs font-medium tracking-wider text-slate-500" colspan="2">Total</th>
                    </tr>
                    <tr class="bg-indigo-50/50">
                        <th></th>
                        <th class="px-4 py-1 text-center text-xs text-slate-500">CUM</th>
                        <th class="px-4 py-1 text-center text-xs text-slate-500">NOW</th>
                        <th class="px-4 py-1 text-center text-xs text-slate-500">CUM</th>
                        <th class="px-4 py-1 text-center text-xs text-slate-500">NOW</th>
                        <th class="px-4 py-1 text-center text-xs text-slate-500">CUM</th>
                        <th class="px-4 py-1 text-center text-xs text-slate-500">NOW</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <tr v-for="name in sectorNames" :key="name">
                        <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ name }}</td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="sectors[name].male_cum"
                                type="number"
                                min="0"
                                class="block w-20 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="sectors[name].male_now"
                                type="number"
                                min="0"
                                class="block w-20 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="sectors[name].female_cum"
                                type="number"
                                min="0"
                                class="block w-20 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2">
                            <input
                                v-model.number="sectors[name].female_now"
                                type="number"
                                min="0"
                                class="block w-20 border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
                            />
                        </td>
                        <td class="px-4 py-2 text-center text-sm font-medium">{{ sectorTotal(sectors[name], 'cum') }}</td>
                        <td class="px-4 py-2 text-center text-sm font-medium">{{ sectorTotal(sectors[name], 'now') }}</td>
                    </tr>
                </tbody>
                <tfoot class="bg-indigo-50">
                    <tr class="font-semibold">
                        <td class="px-4 py-2">TOTAL</td>
                        <td class="px-4 py-2 text-center">{{ totalMaleCum }}</td>
                        <td class="px-4 py-2 text-center">{{ totalMaleNow }}</td>
                        <td class="px-4 py-2 text-center">{{ totalFemaleCum }}</td>
                        <td class="px-4 py-2 text-center">{{ totalFemaleNow }}</td>
                        <td class="px-4 py-2 text-center">{{ totalMaleCum + totalFemaleCum }}</td>
                        <td class="px-4 py-2 text-center">{{ totalMaleNow + totalFemaleNow }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
