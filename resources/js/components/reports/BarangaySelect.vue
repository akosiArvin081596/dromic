<script setup lang="ts">
import { computed } from 'vue';
import type { Barangay } from '@/types';

const props = withDefaults(
    defineProps<{
        barangays: Barangay[];
        modelValue: string;
        placeholder?: string;
        exclude?: string[];
    }>(),
    { exclude: () => [] },
);

const filteredBarangays = computed(() => (props.exclude.length ? props.barangays.filter((b) => !props.exclude.includes(b.name)) : props.barangays));

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <select
        :value="modelValue"
        class="block w-full border-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm"
        @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
        <option value="">{{ placeholder ?? 'Select Barangay' }}</option>
        <option v-for="brgy in filteredBarangays" :key="brgy.id" :value="brgy.name">
            {{ brgy.name }}
        </option>
    </select>
</template>
