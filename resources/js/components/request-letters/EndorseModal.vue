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
        endorsed_quantity: item.quantity,
    })),
});

function augmentationLabel(typeValue: string): string {
    return props.augmentationTypes.find((t) => t.value === typeValue)?.label ?? typeValue;
}

function submit(): void {
    form.post(`/incidents/${props.incidentId}/request-letters/${props.requestLetterId}/endorse`, {
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="emit('close')">
        <div class="w-full max-w-lg bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-900">Endorse Request Letter</h3>
            <p class="mt-1 text-sm text-slate-500">Adjust quantities to endorse. Endorsed quantity cannot exceed the requested amount.</p>

            <form class="mt-4 space-y-4" @submit.prevent="submit">
                <div v-for="(item, index) in form.augmentation_items" :key="item.type" class="flex items-center gap-4">
                    <div class="flex-1">
                        <span class="text-sm font-medium text-slate-700">{{ augmentationLabel(item.type) }}</span>
                        <span class="ml-2 text-xs text-slate-400">(requested: {{ items[index].quantity }})</span>
                    </div>
                    <div class="w-32">
                        <input
                            v-model.number="item.endorsed_quantity"
                            type="number"
                            :min="0"
                            :max="items[index].quantity"
                            class="block w-full border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                        />
                        <p v-if="form.errors[`augmentation_items.${index}.endorsed_quantity`]" class="mt-0.5 text-xs text-rose-600">
                            {{ form.errors[`augmentation_items.${index}.endorsed_quantity`] }}
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
                        class="bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Endorsing...' : 'Endorse' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
