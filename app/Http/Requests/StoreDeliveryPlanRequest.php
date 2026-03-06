<?php

namespace App\Http\Requests;

use App\Services\RequestLetterLedgerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeliveryPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordDelivery', $this->route('requestLetter'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'plan_items' => ['required', 'array', 'min:1'],
            'plan_items.*.type' => ['required', 'string'],
            'plan_items.*.batches' => ['required', 'array', 'min:1'],
            'plan_items.*.batches.*.quantity' => ['required', 'integer', 'min:1'],
            'plan_items.*.batches.*.delivery_date' => ['required', 'date'],
            'plan_items.*.batches.*.escort_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $requestLetter = $this->route('requestLetter');
            $ledgerService = app(RequestLetterLedgerService::class);
            $ledger = collect($ledgerService->getLedger($requestLetter))->keyBy('type');

            foreach ($this->input('plan_items', []) as $index => $item) {
                $type = $item['type'] ?? '';
                $ledgerItem = $ledger->get($type);

                if (! $ledgerItem) {
                    $validator->errors()->add("plan_items.{$index}.type", 'Invalid item type.');

                    continue;
                }

                $totalPlanned = collect($item['batches'] ?? [])->sum('quantity');
                $approved = $ledgerItem['approved'] ?? $ledgerItem['endorsed'] ?? $ledgerItem['requested'];

                if ($totalPlanned > $approved) {
                    $validator->errors()->add(
                        "plan_items.{$index}",
                        "Total planned quantity ({$totalPlanned}) cannot exceed approved quantity ({$approved})."
                    );
                }
            }
        });
    }
}
