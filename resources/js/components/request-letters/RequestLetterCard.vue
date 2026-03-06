<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { AugmentationTypeOption, RequestLetter } from '@/types/request-letter';
import LedgerTable from './LedgerTable.vue';

const props = defineProps<{
    letter: RequestLetter;
    incidentId: number;
    augmentationTypes: AugmentationTypeOption[];
}>();

const emit = defineEmits<{
    endorse: [letter: RequestLetter];
    approve: [letter: RequestLetter];
    delivery: [letter: RequestLetter];
}>();

const showLedger = ref(false);
const deleteForm = useForm({});

const deliveryProgress = computed(() => {
    if (!props.letter.ledger || !['approved', 'delivering', 'completed'].includes(props.letter.status)) {
        return null;
    }

    const totalApproved = props.letter.ledger.reduce((sum, item) => sum + (item.approved ?? item.endorsed ?? item.requested), 0);
    const totalDelivered = props.letter.ledger.reduce((sum, item) => sum + item.delivered, 0);

    if (totalApproved === 0) {
        return null;
    }

    return {
        percentage: Math.round((totalDelivered / totalApproved) * 100),
        delivered: totalDelivered,
        total: totalApproved,
    };
});

function augmentationLabel(typeValue: string): string {
    return props.augmentationTypes.find((t) => t.value === typeValue)?.label ?? typeValue;
}

function statusBadgeClass(status: string): string {
    switch (status) {
        case 'pending':
            return 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/20';
        case 'endorsed':
            return 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/20';
        case 'approved':
            return 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-600/20';
        case 'delivering':
            return 'bg-violet-50 text-violet-700 ring-1 ring-violet-600/20';
        case 'completed':
            return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20';
        default:
            return 'bg-slate-50 text-slate-700 ring-1 ring-slate-600/20';
    }
}

function deleteLetter(): void {
    if (!confirm('Are you sure you want to delete this request letter?')) return;
    deleteForm.delete(`/incidents/${props.incidentId}/request-letters/${props.letter.id}`);
}
</script>

<template>
    <div class="border border-slate-200 bg-white p-4">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <a
                        :href="`/incidents/${incidentId}/request-letters/${letter.id}`"
                        class="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-800"
                    >
                        {{ letter.original_filename }}
                    </a>
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold capitalize"
                        :class="statusBadgeClass(letter.status)"
                    >
                        {{ letter.status }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Uploaded by {{ letter.user?.name ?? 'Unknown' }}
                    <span v-if="letter.city_municipality">
                        &middot; {{ letter.city_municipality.name }}
                        <span v-if="letter.city_municipality.province">({{ letter.city_municipality.province.name }})</span>
                    </span>
                    &middot; {{ new Date(letter.created_at).toLocaleDateString() }}
                </p>
                <p v-if="letter.endorser" class="mt-0.5 text-xs text-slate-400">
                    Endorsed by {{ letter.endorser.name }}
                    <span v-if="letter.endorsed_at"> &middot; {{ new Date(letter.endorsed_at).toLocaleDateString() }}</span>
                </p>
                <p v-if="letter.approver" class="mt-0.5 text-xs text-slate-400">
                    Approved by {{ letter.approver.name }}
                    <span v-if="letter.approved_at"> &middot; {{ new Date(letter.approved_at).toLocaleDateString() }}</span>
                </p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="(item, idx) in letter.augmentation_items"
                        :key="idx"
                        class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-violet-600/20"
                    >
                        {{ augmentationLabel(item.type) }}: {{ item.quantity }}
                    </span>
                </div>

                <!-- Delivery Progress -->
                <div v-if="deliveryProgress" class="mt-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-600">{{ deliveryProgress.percentage }}% delivered</span>
                        <span class="text-slate-400">{{ deliveryProgress.delivered }} of {{ deliveryProgress.total }} total items</span>
                    </div>
                    <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="deliveryProgress.percentage >= 100 ? 'bg-emerald-500' : 'bg-indigo-500'"
                            :style="{ width: `${Math.min(deliveryProgress.percentage, 100)}%` }"
                        />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    v-if="letter.can_endorse"
                    class="text-xs font-medium text-sky-600 transition-colors hover:text-sky-800"
                    @click="emit('endorse', letter)"
                >
                    Endorse
                </button>
                <button
                    v-if="letter.can_approve"
                    class="text-xs font-medium text-emerald-600 transition-colors hover:text-emerald-800"
                    @click="emit('approve', letter)"
                >
                    Approve
                </button>
                <button
                    v-if="letter.can_record_delivery"
                    class="text-xs font-medium text-indigo-600 transition-colors hover:text-indigo-800"
                    @click="emit('delivery', letter)"
                >
                    Record Delivery
                </button>
                <button
                    v-if="letter.can_delete"
                    class="text-xs text-rose-600 transition-colors hover:text-rose-800"
                    :disabled="deleteForm.processing"
                    @click="deleteLetter"
                >
                    Delete
                </button>
            </div>
        </div>

        <!-- Ledger Toggle -->
        <div v-if="letter.status !== 'pending'" class="mt-3">
            <button class="text-xs font-medium text-slate-500 transition-colors hover:text-slate-700" @click="showLedger = !showLedger">
                {{ showLedger ? 'Hide Ledger' : 'Show Ledger' }}
            </button>
            <div v-if="showLedger" class="mt-2">
                <LedgerTable :letter="letter" :augmentation-types="augmentationTypes" />
            </div>
        </div>
    </div>
</template>
