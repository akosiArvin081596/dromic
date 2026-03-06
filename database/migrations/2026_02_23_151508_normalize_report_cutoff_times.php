<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize report_time values to valid cut-off times (12:00 PM or 12:00 AM).
     *
     * AM times (e.g. 6:00 AM) → 12:00 PM same day
     * PM times (e.g. 6:00 PM) → 12:00 AM next day
     */
    public function up(): void
    {
        // AM times that aren't 12:00 AM → 12:00 PM (same date)
        DB::table('reports')
            ->where('report_time', 'like', '%AM')
            ->where('report_time', '!=', '12:00 AM')
            ->update(['report_time' => '12:00 PM']);

        // PM times that aren't 12:00 PM → 12:00 AM (next day)
        $rows = DB::table('reports')
            ->where('report_time', 'like', '%PM')
            ->where('report_time', '!=', '12:00 PM')
            ->get(['id', 'report_date']);

        foreach ($rows as $row) {
            DB::table('reports')
                ->where('id', $row->id)
                ->update([
                    'report_time' => '12:00 AM',
                    'report_date' => date('Y-m-d', strtotime($row->report_date . ' +1 day')),
                ]);
        }
    }

    public function down(): void
    {
        // Not reversible — original times are unknown
    }
};
