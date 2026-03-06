<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import type { AugmentationItem, AugmentationTypeOption } from '@/types/request-letter';

const props = defineProps<{
    incidentId: number;
    requestLetterId: number;
    items: AugmentationItem[];
    augmentationTypes: AugmentationTypeOption[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const form = useForm({
    augmentation_items: props.items.map((item) => ({
        type: item.type,
        approved_quantity: item.endorsed_quantity ?? item.quantity,
    })),
});

function augmentationLabel(typeValue: string): string {
    return props.augmentationTypes.find((t) => t.value === typeValue)?.label ?? typeValue;
}

function getMaxQuantity(index: number): number {
    return props.items[index].endorsed_quantity ?? props.items[index].quantity;
}

function submit(): void {
    form.post(`/incidents/${props.incidentId}/request-letters/${props.requestLetterId}/approve`, {
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="emit('close')">
        <div class="w-full max-w-lg bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-900">Approve Request Letter</h3>
            <p class="mt-1 text-sm text-slate-500">Set approved quantities based on stock availability. Cannot exceed endorsed amount.</p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div v-for="(item, index) in form.augmentation_items" :key="item.type" class="flex items-center gap-4">
                    <div class="flex-1">
                        <span class="text-sm font-medium text-slate-700">{{ augmentationLabel(item.type) }}</span>
                        <span class="ml-2 text-xs text-slate-400">(endorsed: {{ getMaxQuantity(index) }})</span>
                    </div>
                    <div class="w-32">
                        <input
                            v-model.number="item.approved_quantity"
                            type="number"
                            :min="0"
                            :max="getMaxQuantity(index)"
                            class="block w-full border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                        />
                        <p v-if="form.errors[`augmentation_items.${index}.approved_quantity`]" class="mt-0.5 text-xs text-rose-600">
                            {{ form.errors[`augmentation_items.${index}.approved_quantity`] }}
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <button
                        type="button"
                        class="border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50"
                        @click="emit('close')"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Approving...' : 'Approve' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
