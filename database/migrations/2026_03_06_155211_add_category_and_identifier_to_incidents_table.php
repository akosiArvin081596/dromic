<?php

use App\Enums\IncidentCategory;
use App\Models\Incident;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('category')->after('display_name')->default('other');
            $table->string('identifier')->nullable()->after('category');
        });

        $categoryMap = collect(IncidentCategory::cases())->keyBy(fn (IncidentCategory $c) => mb_strtolower($c->label()));

        Incident::query()->each(function (Incident $incident) use ($categoryMap) {
            $name = $incident->getRawOriginal('name');
            $matched = false;

            // Sort by longest label first so "Tropical Cyclone" matches before partial hits
            foreach ($categoryMap->sortByDesc(fn ($c, $label) => mb_strlen($label)) as $label => $category) {
                if (str_starts_with(mb_strtolower($name), $label)) {
                    $identifier = trim(mb_substr($name, mb_strlen($label)));
                    $incident->updateQuietly([
                        'category' => $category->value,
                        'identifier' => $identifier !== '' ? $identifier : null,
                    ]);
                    $matched = true;

                    break;
                }
            }

            if (! $matched) {
                $incident->updateQuietly([
                    'category' => IncidentCategory::Other->value,
                    'identifier' => $name,
                ]);
            }
        });

        // Recompose names from category + identifier and refresh display names
        Incident::query()->each(function (Incident $incident) {
            $incident->refresh();
            $composedName = Incident::composeName($incident->category, $incident->identifier);
            $incident->update([
                'name' => $composedName,
                'display_name' => $incident->computeDisplayName(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['category', 'identifier']);
        });
    }
};
