<?php

namespace App\Services;

use App\Enums\RequestLetterStatus;
use App\Models\Delivery;
use App\Models\RequestLetter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RequestLetterLedgerService
{
    public function endorse(RequestLetter $requestLetter, array $items, User $endorser): void
    {
        $augmentationItems = $requestLetter->augmentation_items;

        foreach ($augmentationItems as &$item) {
            foreach ($items as $endorsedItem) {
                if ($item['type'] === $endorsedItem['type']) {
                    $item['endorsed_quantity'] = $endorsedItem['endorsed_quantity'];
                }
            }
        }

        $requestLetter->update([
            'augmentation_items' => $augmentationItems,
            'status' => RequestLetterStatus::Endorsed,
            'endorsed_by' => $endorser->id,
            'endorsed_at' => now(),
        ]);
    }

    public function approve(RequestLetter $requestLetter, array $items, User $approver): void
    {
        $augmentationItems = $requestLetter->augmentation_items;

        foreach ($augmentationItems as &$item) {
            foreach ($items as $approvedItem) {
                if ($item['type'] === $approvedItem['type']) {
                    $item['approved_quantity'] = $approvedItem['approved_quantity'];
                }
            }
        }

        $requestLetter->update([
            'augmentation_items' => $augmentationItems,
            'status' => RequestLetterStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    public function recordDelivery(RequestLetter $requestLetter, array $data, User $recorder): Delivery
    {
        return DB::transaction(function () use ($requestLetter, $data, $recorder) {
            $delivery = Delivery::query()->create([
                'request_letter_id' => $requestLetter->id,
                'escort_user_id' => $data['escort_user_id'] ?? null,
                'recorded_by' => $recorder->id,
                'delivery_items' => $data['delivery_items'],
                'delivery_date' => $data['delivery_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            if (! empty($data['attachments'])) {
                foreach ($data['attachments'] as $file) {
                    $path = $file->store('delivery-attachments', 'local');
                    $delivery->attachments()->create([
                        'file_path' => $path,
                        'original_filename' => $file->getClientOriginalName(),
                        'file_type' => $this->determineFileType($file->getClientMimeType()),
                    ]);
                }
            }

            $ledger = $this->getLedger($requestLetter->fresh());
            $allDelivered = collect($ledger)->every(fn (array $item): bool => $item['balance'] <= 0);

            if ($allDelivered) {
                $requestLetter->update(['status' => RequestLetterStatus::Completed]);
            } elseif ($requestLetter->status === RequestLetterStatus::Approved) {
                $requestLetter->update(['status' => RequestLetterStatus::Delivering]);
            }

            return $delivery;
        });
    }

    /**
     * @return array<int, array{type: string, requested: int, endorsed: int|null, approved: int|null, delivered: int, balance: int}>
     */
    public function getLedger(RequestLetter $requestLetter): array
    {
        $deliveries = $requestLetter->deliveries;
        $deliveredTotals = [];

        foreach ($deliveries as $delivery) {
            foreach ($delivery->delivery_items as $item) {
                $type = $item['type'];
                $deliveredTotals[$type] = ($deliveredTotals[$type] ?? 0) + $item['quantity'];
            }
        }

        $ledger = [];
        foreach ($requestLetter->augmentation_items as $item) {
            $type = $item['type'];
            $requested = (int) $item['quantity'];
            $endorsed = isset($item['endorsed_quantity']) ? (int) $item['endorsed_quantity'] : null;
            $approved = isset($item['approved_quantity']) ? (int) $item['approved_quantity'] : null;
            $delivered = $deliveredTotals[$type] ?? 0;
            $baseForBalance = $approved ?? $endorsed ?? $requested;

            $ledger[] = [
                'type' => $type,
                'requested' => $requested,
                'endorsed' => $endorsed,
                'approved' => $approved,
                'delivered' => $delivered,
                'balance' => $baseForBalance - $delivered,
            ];
        }

        return $ledger;
    }

    private function determineFileType(string $mimeType): string
    {
        return str_starts_with($mimeType, 'image/') ? 'photo' : 'document';
    }
}
