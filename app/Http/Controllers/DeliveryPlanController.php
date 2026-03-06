<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryPlanRequest;
use App\Models\DeliveryPlan;
use App\Models\Incident;
use App\Models\RequestLetter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DeliveryPlanController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreDeliveryPlanRequest $request, Incident $incident, RequestLetter $requestLetter): RedirectResponse
    {
        $requestLetter->deliveryPlan()->delete();

        $requestLetter->deliveryPlan()->create([
            'created_by' => $request->user()->id,
            'plan_items' => $request->validated('plan_items'),
            'notes' => $request->validated('notes'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery plan saved successfully.']);

        return redirect()->back();
    }

    public function destroy(Incident $incident, RequestLetter $requestLetter, DeliveryPlan $deliveryPlan): RedirectResponse
    {
        $this->authorize('recordDelivery', $requestLetter);

        $deliveryPlan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Delivery plan deleted successfully.']);

        return redirect()->back();
    }
}
