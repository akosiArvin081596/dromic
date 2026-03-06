<?php

namespace App\Http\Requests;

use App\Services\RequestLetterLedgerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordDelivery', $this->route('requestLetter'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'escort_user_id' => ['nullable', 'exists:users,id'],
            'delivery_items' => ['required', 'array', 'min:1'],
            'delivery_items.*.type' => ['required', 'string'],
            'delivery_items.*.quantity' => ['required', 'integer', 'min:1'],
            'delivery_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $requestLetter = $this->route('requestLetter');
            $ledgerService = app(RequestLetterLedgerService::class);
            $ledger = collect($ledgerService->getLedger($requestLetter))->keyBy('type');

            foreach ($this->input('delivery_items', []) as $index => $item) {
                $type = $item['type'] ?? '';
                $quantity = (int) ($item['quantity'] ?? 0);
                $ledgerItem = $ledger->get($type);

                if (! $ledgerItem) {
                    $validator->errors()->add("delivery_items.{$index}.type", 'Invalid item type.');

                    continue;
                }

                if ($quantity > $ledgerItem['balance']) {
                    $validator->errors()->add(
                        "delivery_items.{$index}.quantity",
                        "Delivery quantity ({$quantity}) cannot exceed remaining balance ({$ledgerItem['balance']})."
                    );
                }
            }
        });
    }
}
