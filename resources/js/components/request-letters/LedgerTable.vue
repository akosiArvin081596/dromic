<script setup lang="ts">
import { computed } from 'vue';
import type { AugmentationTypeOption, RequestLetter } from '@/types/request-letter';

const props = defineProps<{
    letter: RequestLetter;
    augmentationTypes: AugmentationTypeOption[];
}>();

function augmentationLabel(typeValue: string): string {
    return props.augmentationTypes.find((t) => t.value === typeValue)?.label ?? typeValue;
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString();
}

type TimelineRow = {
    date: string;
    type: string;
    quantity: number | null;
    balance: number | null;
};

type ItemTimeline = {
    itemType: string;
    label: string;
    rows: TimelineRow[];
};

const timelines = computed<ItemTimeline[]>(() => {
    return (props.letter.augmentation_items ?? []).map((item) => {
        const rows: TimelineRow[] = [];

        // 1. Requested
        rows.push({
            date: formatDate(props.letter.created_at),
            type: 'Requested',
            quantity: item.quantity,
            balance: null,
        });

        // 2. Endorsed (Province)
        if (props.letter.endorsed_at && item.endorsed_quantity != null) {
            rows.push({
                date: formatDate(props.letter.endorsed_at),
                type: 'Endorsed (Province)',
                quantity: item.endorsed_quantity,
                balance: null,
            });
        }

        // 3. Approved (Region)
        const approved = item.approved_quantity ?? item.endorsed_quantity ?? item.quantity;
        if (props.letter.approved_at && item.approved_quantity != null) {
            rows.push({
                date: formatDate(props.letter.approved_at),
                type: 'Approved (Region)',
                quantity: item.approved_quantity,
                balance: approved,
            });
        }

        // 4. Scheduled Delivery — from delivery plan
        const planItem = props.letter.delivery_plan?.plan_items?.find((p) => p.type === item.type);
        if (planItem) {
            for (const batch of planItem.batches) {
                rows.push({
                    date: formatDate(props.letter.delivery_plan!.created_at),
                    type: `Scheduled Delivery (${formatDate(batch.delivery_date)})`,
                    quantity: batch.quantity,
                    balance: null,
                });
            }
        }

        // 5. Deliveries
        let runningBalance = approved;
        if (props.letter.deliveries) {
            for (const delivery of props.letter.deliveries) {
                const deliveryItem = delivery.delivery_items.find((di) => di.type === item.type);
                if (deliveryItem) {
                    runningBalance -= deliveryItem.quantity;
                    rows.push({
                        date: formatDate(delivery.delivery_date),
                        type: 'Delivered',
                        quantity: deliveryItem.quantity,
                        balance: runningBalance,
                    });
                }
            }
        }

        return {
            itemType: item.type,
            label: augmentationLabel(item.type),
            rows,
        };
    });
});
</script>

<template>
    <div class="space-y-4">
        <div v-for="timeline in timelines" :key="timeline.itemType">
            <h5 class="mb-1 text-xs font-semibold text-slate-700">{{ timeline.label }}</h5>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-slate-600">Date</th>
                            <th class="px-3 py-2 text-left font-medium text-slate-600">Type</th>
                            <th class="px-3 py-2 text-right font-medium text-slate-600">Quantity</th>
                            <th class="px-3 py-2 text-right font-medium text-slate-600">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, idx) in timeline.rows" :key="idx">
                            <td class="px-3 py-2 text-slate-500">{{ row.date }}</td>
                            <td class="px-3 py-2 text-slate-700">{{ row.type }}</td>
                            <td class="px-3 py-2 text-right text-slate-700">
                                {{ row.quantity != null ? row.quantity : '' }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-semibold"
                                :class="row.balance != null && row.balance > 0 ? 'text-amber-600' : row.balance === 0 ? 'text-emerald-600' : ''"
                            >
                                {{ row.balance != null ? row.balance : '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
