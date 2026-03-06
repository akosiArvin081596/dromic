<?php

namespace App\Http\Controllers;

use App\Events\RequestLetterApproved;
use App\Events\RequestLetterEndorsed;
use App\Events\RequestLetterSubmitted;
use App\Http\Requests\ApproveRequestLetterRequest;
use App\Http\Requests\EndorseRequestLetterRequest;
use App\Http\Requests\StoreRequestLetterRequest;
use App\Models\Incident;
use App\Models\RequestLetter;
use App\Models\User;
use App\Notifications\RequestLetterApprovedNotification;
use App\Notifications\RequestLetterEndorsedNotification;
use App\Notifications\RequestLetterSubmittedNotification;
use App\Services\IncidentNotificationService;
use App\Services\RequestLetterLedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestLetterController extends Controller
{
    use AuthorizesRequests;

    public function __construct(public RequestLetterLedgerService $ledgerService) {}

    public function store(StoreRequestLetterRequest $request, Incident $incident): RedirectResponse
    {
        $user = $request->user();
        $file = $request->file('file');

        $path = $file->store('request-letters', 'local');

        $requestLetter = RequestLetter::query()->create([
            'incident_id' => $incident->id,
            'user_id' => $user->id,
            'city_municipality_id' => $user->city_municipality_id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'augmentation_items' => $request->validated('augmentation_items'),
        ]);

        $notificationService = app(IncidentNotificationService::class);
        $recipientUserIds = $notificationService->getRequestLetterRecipientUserIds(
            $user->city_municipality_id,
            $user->id,
        );

        if (count($recipientUserIds) > 0) {
            $data = RequestLetterSubmitted::serializeRequestLetter($requestLetter, $user);

            RequestLetterSubmitted::dispatch($data, $recipientUserIds);

            $recipients = User::query()->whereIn('id', $recipientUserIds)->get();
            Notification::send($recipients, new RequestLetterSubmittedNotification($data));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request letter uploaded successfully.']);

        return redirect()->back();
    }

    public function show(Incident $incident, RequestLetter $requestLetter): StreamedResponse
    {
        $this->authorize('view', $requestLetter);

        return Storage::disk('local')->download($requestLetter->file_path, $requestLetter->original_filename);
    }

    public function destroy(Incident $incident, RequestLetter $requestLetter): RedirectResponse
    {
        $this->authorize('delete', $requestLetter);

        Storage::disk('local')->delete($requestLetter->file_path);
        $requestLetter->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request letter deleted successfully.']);

        return redirect()->back();
    }

    public function endorse(EndorseRequestLetterRequest $request, Incident $incident, RequestLetter $requestLetter): RedirectResponse
    {
        $user = $request->user();

        $this->ledgerService->endorse(
            $requestLetter,
            $request->validated('augmentation_items'),
            $user
        );

        $notificationService = app(IncidentNotificationService::class);
        $recipientUserIds = $notificationService->getEndorsementRecipientUserIds(
            $requestLetter->city_municipality_id,
            $requestLetter->user_id,
            $user->id,
        );

        if (count($recipientUserIds) > 0) {
            $data = RequestLetterEndorsed::serializeRequestLetter($requestLetter, $user);

            RequestLetterEndorsed::dispatch($data, $recipientUserIds);

            $recipients = User::query()->whereIn('id', $recipientUserIds)->get();
            Notification::send($recipients, new RequestLetterEndorsedNotification($data));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request letter endorsed successfully.']);

        return redirect()->back();
    }

    public function approve(ApproveRequestLetterRequest $request, Incident $incident, RequestLetter $requestLetter): RedirectResponse
    {
        $user = $request->user();

        $this->ledgerService->approve(
            $requestLetter,
            $request->validated('augmentation_items'),
            $user
        );

        $notificationService = app(IncidentNotificationService::class);
        $recipientUserIds = $notificationService->getApprovalRecipientUserIds(
            $requestLetter->city_municipality_id,
            $requestLetter->user_id,
            $user->id,
        );

        if (count($recipientUserIds) > 0) {
            $data = RequestLetterApproved::serializeRequestLetter($requestLetter, $user);

            RequestLetterApproved::dispatch($data, $recipientUserIds);

            $recipients = User::query()->whereIn('id', $recipientUserIds)->get();
            Notification::send($recipients, new RequestLetterApprovedNotification($data));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request letter approved successfully.']);

        return redirect()->back();
    }
}
