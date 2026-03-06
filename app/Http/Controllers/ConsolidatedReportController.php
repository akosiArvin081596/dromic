<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Services\ConsolidatedReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsolidatedReportController extends Controller
{
    public function __construct(private ConsolidatedReportService $consolidatedService) {}

    public function show(Request $request, Incident $incident): Response
    {
        $user = $request->user();

        $provinceId = $user->isProvincial() ? $user->province_id : null;
        $regionId = $user->isRegional() ? $user->region_id : null;
        $validatedOnly = $user->isRegional();

        $result = $this->consolidatedService->consolidateByCutoff($incident, $provinceId, $regionId, $validatedOnly);

        $incident->load('cityMunicipalities.province', 'creator');

        return Inertia::render('Consolidated/Show', [
            'incident' => $incident,
            'cutoffs' => $result['cutoffs'],
        ]);
    }
}
