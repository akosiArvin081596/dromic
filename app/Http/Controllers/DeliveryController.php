<?php

namespace App\Http\Controllers;

use App\Events\DeliveryRecorded;
use App\Http\Requests\StoreDeliveryRequest;
use App\Models\Delivery;
use App\Models\Incident;
use App\Models\RequestLetter;
use App\Models\User;
use App\Notifications\DeliveryRecordedNotification;
use App\Services\IncidentNotificationService;
use App\Services\RequestLetterLedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class DeliveryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(public RequestLetterLedgerService $ledgerService) {}

    public function store(StoreDeliveryRequest $request, Incident $incident, RequestLetter $requestLetter): RedirectResponse
    {
        $user = $request->user();

        $this->ledgerService->recordDelivery(
            $requestLetter,
            array_merge($request->validated(), ['attachments' => $request->file('attachments', [])]),
            $user
        );

        $notificationService = app(IncidentNotificationService::class);
        $recipientUserIds = $notificationService->getDeliveryRecipientUserIds(
            $requestLetter->city_municipality_id,
            $requestLetter->user_id,
            $user->id,
        );

        if (count($recipientUserIds) > 0) {
            $data = DeliveryRecorded::serializeRequestLetter($requestLetter, $user);

            DeliveryRecorded::dispatch($data, $recipientUserIds);

            $recipients = User::query()->whereIn('id', $recipientUserIds)->get();
            Notification::send($recipients, new DeliveryRecordedNotification($data));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery recorded successfully.']);

        return redirect()->back();
    }

    public function update(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->authorize('update', $delivery);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $delivery->update(['notes' => $validated['notes'] ?? $delivery->notes]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('delivery-attachments', 'local');
                $delivery->attachments()->create([
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'file_type' => str_starts_with($file->getClientMimeType(), 'image/') ? 'photo' : 'document',
                ]);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery updated successfully.']);

        return redirect()->back();
    }
}
