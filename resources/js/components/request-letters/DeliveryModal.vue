<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { AugmentationTypeOption, DeliveryPlan, DeliveryPlanBatch, EscortUser, LedgerItem } from '@/types/request-letter';

const props = defineProps<{
    incidentId: number;
    requestLetterId: number;
    ledger: LedgerItem[];
    escortUsers: EscortUser[];
    augmentationTypes: AugmentationTypeOption[];
    deliveryPlan: DeliveryPlan | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const activeTab = ref<'plan' | 'record'>('record');

// --- Record Delivery ---
const availableItems = props.ledger.filter((item) => item.balance > 0);

const form = useForm({
    escort_user_id: null as number | null,
    delivery_items: availableItems.map((item) => ({
        type: item.type,
        quantity: item.balance,
    })),
    delivery_date: new Date().toISOString().split('T')[0],
    notes: '',
    attachments: [] as File[],
});

function augmentationLabel(typeValue: string): string {
    return props.augmentationTypes.find((t) => t.value === typeValue)?.label ?? typeValue;
}

function getMaxQuantity(type: string): number {
    return props.ledger.find((item) => item.type === type)?.balance ?? 0;
}

function getApprovedQuantity(type: string): number {
    const item = props.ledger.find((i) => i.type === type);
    if (!item) return 0;
    return item.approved ?? item.endorsed ?? item.requested;
}

function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    form.attachments = target.files ? Array.from(target.files) : [];
}

function submitDelivery(): void {
    form.post(`/incidents/${props.incidentId}/request-letters/${props.requestLetterId}/deliveries`, {
        forceFormData: true,
        onSuccess: () => emit('close'),
    });
}

// --- Delivery Plan ---
type PlanItemForm = {
    type: string;
    batches: DeliveryPlanBatch[];
};

function createEmptyBatch(): DeliveryPlanBatch {
    return {
        quantity: 0,
        delivery_date: new Date().toISOString().split('T')[0],
        escort_user_id: null,
    };
}

function initPlanItems(): PlanItemForm[] {
    if (props.deliveryPlan) {
        return props.deliveryPlan.plan_items.map((item) => ({
            type: item.type,
            batches: item.batches.map((b) => ({ ...b })),
        }));
    }

    return props.ledger.map((item) => ({
        type: item.type,
        batches: [
            {
                quantity: item.approved ?? item.endorsed ?? item.requested,
                delivery_date: new Date().toISOString().split('T')[0],
                escort_user_id: null,
            },
        ],
    }));
}

const planForm = useForm({
    plan_items: initPlanItems(),
    notes: props.deliveryPlan?.notes ?? '',
});

function addBatch(itemIndex: number): void {
    planForm.plan_items[itemIndex].batches.push(createEmptyBatch());
}

function removeBatch(itemIndex: number, batchIndex: number): void {
    planForm.plan_items[itemIndex].batches.splice(batchIndex, 1);
}

function batchTotal(itemIndex: number): number {
    return planForm.plan_items[itemIndex].batches.reduce((sum, b) => sum + (b.quantity || 0), 0);
}

function submitPlan(): void {
    planForm.post(`/incidents/${props.incidentId}/request-letters/${props.requestLetterId}/delivery-plan`, {
        onSuccess: () => emit('close'),
    });
}

function deletePlan(): void {
    if (!props.deliveryPlan || !confirm('Are you sure you want to delete this delivery plan?')) return;
    planForm.delete(`/incidents/${props.incidentId}/request-letters/${props.requestLetterId}/delivery-plan/${props.deliveryPlan.id}`, {
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="emit('close')">
        <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto bg-white shadow-xl">
            <!-- Tabs -->
            <div class="flex border-b border-slate-200">
                <button
                    class="flex-1 px-4 py-3 text-sm font-medium transition-colors"
                    :class="activeTab === 'plan' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-slate-500 hover:text-slate-700'"
                    @click="activeTab = 'plan'"
                >
                    Delivery Plan
                    <span v-if="deliveryPlan" class="ml-1.5 inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-600">
                        Saved
                    </span>
                </button>
                <button
                    class="flex-1 px-4 py-3 text-sm font-medium transition-colors"
                    :class="activeTab === 'record' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-slate-500 hover:text-slate-700'"
                    @click="activeTab = 'record'"
                >
                    Record Delivery
                </button>
            </div>

            <div class="p-6">
                <!-- Delivery Plan Tab -->
                <div v-if="activeTab === 'plan'">
                    <p class="text-sm text-slate-500">
                        Plan delivery batches for each item. Split approved quantities across multiple planned dates.
                    </p>

                    <form class="mt-4 space-y-5" @submit.prevent="submitPlan">
                        <div v-for="(planItem, itemIndex) in planForm.plan_items" :key="planItem.type" class="border border-slate-200 p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-medium text-slate-700">{{ augmentationLabel(planItem.type) }}</span>
                                    <span class="ml-2 text-xs text-slate-400">(approved: {{ getApprovedQuantity(planItem.type) }})</span>
                                </div>
                                <span
                                    class="text-xs font-medium"
                                    :class="
                                        batchTotal(itemIndex) === getApprovedQuantity(planItem.type)
                                            ? 'text-emerald-600'
                                            : batchTotal(itemIndex) > getApprovedQuantity(planItem.type)
                                              ? 'text-rose-600'
                                              : 'text-amber-600'
                                    "
                                >
                                    Planned: {{ batchTotal(itemIndex) }} / {{ getApprovedQuantity(planItem.type) }}
                                </span>
                            </div>

                            <div class="mt-3 space-y-2">
                                <div
                                    v-for="(batch, batchIndex) in planItem.batches"
                                    :key="batchIndex"
                                    class="flex items-center gap-2 rounded bg-slate-50 p-2"
                                >
                                    <div class="flex-shrink-0 text-xs font-medium text-slate-400">{{ batchIndex + 1 }}.</div>
                                    <div class="flex-1">
                                        <input
                                            v-model.number="batch.quantity"
                                            type="number"
                                            :min="1"
                                            :max="getApprovedQuantity(planItem.type)"
                                            placeholder="Qty"
                                            class="block w-full border border-slate-300 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <input
                                            v-model="batch.delivery_date"
                                            type="date"
                                            class="block w-full border border-slate-300 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <select
                                            v-model="batch.escort_user_id"
                                            class="block w-full border border-slate-300 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                                        >
                                            <option :value="null">No escort</option>
                                            <option v-for="escort in escortUsers" :key="escort.id" :value="escort.id">{{ escort.name }}</option>
                                        </select>
                                    </div>
                                    <button
                                        v-if="planItem.batches.length > 1"
                                        type="button"
                                        class="flex-shrink-0 text-xs text-rose-500 hover:text-rose-700"
                                        @click="removeBatch(itemIndex, batchIndex)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <button type="button" class="mt-2 text-xs font-medium text-indigo-600 hover:text-indigo-800" @click="addBatch(itemIndex)">
                                + Add Batch
                            </button>

                            <p v-if="planForm.errors[`plan_items.${itemIndex}` as keyof typeof planForm.errors]" class="mt-1 text-xs text-rose-600">
                                {{ planForm.errors[`plan_items.${itemIndex}` as keyof typeof planForm.errors] }}
                            </p>
                        </div>

                        <!-- Plan Notes -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Notes (Optional)</label>
                            <textarea
                                v-model="planForm.notes"
                                rows="2"
                                class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                            />
                        </div>

                        <div class="flex justify-between pt-2">
                            <button
                                v-if="deliveryPlan"
                                type="button"
                                class="text-sm font-medium text-rose-600 hover:text-rose-800"
                                @click="deletePlan"
                            >
                                Delete Plan
                            </button>
                            <div v-else />
                            <div class="flex gap-3">
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
                                    :disabled="planForm.processing"
                                >
                                    {{ planForm.processing ? 'Saving...' : deliveryPlan ? 'Update Plan' : 'Save Plan' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Record Delivery Tab -->
                <div v-if="activeTab === 'record'">
                    <p class="text-sm text-slate-500">
                        Record a delivery against this request letter. Quantities are limited by the remaining balance.
                    </p>

                    <form class="mt-4 space-y-4" @submit.prevent="submitDelivery">
                        <!-- Escort Selection -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Escort (Optional)</label>
                            <select
                                v-model="form.escort_user_id"
                                class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                            >
                                <option :value="null">No escort assigned</option>
                                <option v-for="escort in escortUsers" :key="escort.id" :value="escort.id">{{ escort.name }}</option>
                            </select>
                        </div>

                        <!-- Delivery Date -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Delivery Date</label>
                            <input
                                v-model="form.delivery_date"
                                type="date"
                                class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                            />
                            <p v-if="form.errors.delivery_date" class="mt-0.5 text-xs text-rose-600">{{ form.errors.delivery_date }}</p>
                        </div>

                        <!-- Delivery Items -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Delivery Items</label>
                            <div v-for="(item, index) in form.delivery_items" :key="item.type" class="mb-2 flex items-center gap-4">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-slate-700">{{ augmentationLabel(item.type) }}</span>
                                    <span class="ml-2 text-xs text-slate-400">(balance: {{ getMaxQuantity(item.type) }})</span>
                                </div>
                                <div class="w-32">
                                    <input
                                        v-model.number="item.quantity"
                                        type="number"
                                        :min="1"
                                        :max="getMaxQuantity(item.type)"
                                        class="block w-full border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                                    />
                                    <p v-if="form.errors[`delivery_items.${index}.quantity`]" class="mt-0.5 text-xs text-rose-600">
                                        {{ form.errors[`delivery_items.${index}.quantity`] }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Notes (Optional)</label>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                class="mt-1 block w-full border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 focus:outline-none"
                            />
                        </div>

                        <!-- Attachments -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Attachments (Photos/Documents)</label>
                            <input
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:rounded file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                @change="handleFileChange"
                            />
                            <p v-if="form.errors.attachments" class="mt-0.5 text-xs text-rose-600">{{ form.errors.attachments }}</p>
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
                                {{ form.processing ? 'Recording...' : 'Record Delivery' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
