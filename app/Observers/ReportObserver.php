<?php

namespace App\Observers;

use App\Models\Report;

class ReportObserver
{
    public function created(Report $report): void
    {
        $report->incident->refreshDisplayName();
    }

    public function updated(Report $report): void
    {
        if ($report->isDirty('affected_areas') || $report->isDirty('city_municipality_id')) {
            $report->incident->refreshDisplayName();
        }
    }

    public function deleted(Report $report): void
    {
        if ($report->incident) {
            $report->incident->refreshDisplayName();
        }
    }
}
