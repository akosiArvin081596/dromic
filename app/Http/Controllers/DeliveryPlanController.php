<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryPlanRequest;
use App\Models\DeliveryPlan;
use App\Models\Incident;
use App\Models\RequestLetter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

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

        return redirect()->back()->with('success', 'Delivery plan saved successfully.');
    }

    public function destroy(Incident $incident, RequestLetter $requestLetter, DeliveryPlan $deliveryPlan): RedirectResponse
    {
        $this->authorize('recordDelivery', $requestLetter);

        $deliveryPlan->delete();

        return redirect()->back()->with('success', 'Delivery plan deleted successfully.');
    }
}
