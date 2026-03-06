<?php

namespace App\Console\Commands;

use App\Models\Incident;
use Illuminate\Console\Command;

class RefreshIncidentDisplayNames extends Command
{
    protected $signature = 'app:refresh-incident-display-names';

    protected $description = 'Recompute display_name for all incidents based on their reports';

    public function handle(): int
    {
        $incidents = Incident::all();

        $this->info("Refreshing display names for {$incidents->count()} incidents...");

        foreach ($incidents as $incident) {
            $old = $incident->display_name;
            $incident->refreshDisplayName();
            $incident->refresh();

            if ($old !== $incident->display_name) {
                $this->line("  Updated: \"{$old}\" → \"{$incident->display_name}\"");
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
