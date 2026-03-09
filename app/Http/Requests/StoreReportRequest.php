<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'report_date' => ['nullable', 'date'],
            'report_time' => ['nullable', 'string', 'max:20'],
            'situation_overview' => ['required', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', 'in:draft,for_validation'],
            'is_terminal' => ['sometimes', 'boolean'],

            'affected_areas' => ['required', 'array', 'min:1'],
            'affected_areas.*.barangay' => ['required', 'string'],
            'affected_areas.*.families' => ['required', 'integer', 'min:0'],
            'affected_areas.*.persons' => ['required', 'integer', 'min:0'],

            'inside_evacuation_centers' => ['present', 'array'],
            'inside_evacuation_centers.*.barangay' => ['required', 'string'],
            'inside_evacuation_centers.*.ec_name' => ['required', 'string'],
            'inside_evacuation_centers.*.families_cum' => ['required', 'integer', 'min:0'],
            'inside_evacuation_centers.*.families_now' => ['required', 'integer', 'min:0'],
            'inside_evacuation_centers.*.persons_cum' => ['required', 'integer', 'min:0'],
            'inside_evacuation_centers.*.persons_now' => ['required', 'integer', 'min:0'],
            'inside_evacuation_centers.*.origin' => ['required', 'string'],
            'inside_evacuation_centers.*.remarks' => ['nullable', 'string'],

            'age_distribution' => ['present', 'array'],
            'vulnerable_sectors' => ['present', 'array'],

            'outside_evacuation_centers' => ['present', 'array'],
            'outside_evacuation_centers.*.barangay' => ['required', 'string'],
            'outside_evacuation_centers.*.families_cum' => ['required', 'integer', 'min:0'],
            'outside_evacuation_centers.*.families_now' => ['required', 'integer', 'min:0'],
            'outside_evacuation_centers.*.persons_cum' => ['required', 'integer', 'min:0'],
            'outside_evacuation_centers.*.persons_now' => ['required', 'integer', 'min:0'],
            'outside_evacuation_centers.*.origin' => ['required', 'string'],

            'non_idps' => ['present', 'array'],
            'non_idps.*.barangay' => ['required', 'string'],
            'non_idps.*.families_cum' => ['required', 'integer', 'min:0'],
            'non_idps.*.persons_cum' => ['required', 'integer', 'min:0'],

            'damaged_houses' => ['present', 'array'],
            'damaged_houses.*.barangay' => ['required', 'string'],
            'damaged_houses.*.totally_damaged' => ['required', 'integer', 'min:0'],
            'damaged_houses.*.partially_damaged' => ['required', 'integer', 'min:0'],
            'damaged_houses.*.estimated_cost' => ['required', 'numeric', 'min:0'],

            // V. Related Incidents
            'related_incidents' => ['present', 'array'],
            'related_incidents.*.barangay' => ['required', 'string'],
            'related_incidents.*.incident_type' => ['required', 'string'],
            'related_incidents.*.description' => ['nullable', 'string'],

            // VI. Casualties
            'casualties_injured' => ['present', 'array'],
            'casualties_injured.*.barangay' => ['required', 'string'],
            'casualties_injured.*.name' => ['required', 'string'],
            'casualties_injured.*.age' => ['nullable', 'integer', 'min:0'],
            'casualties_injured.*.sex' => ['nullable', 'string', 'in:male,female'],
            'casualties_injured.*.cause' => ['nullable', 'string'],
            'casualties_injured.*.remarks' => ['nullable', 'string'],

            'casualties_missing' => ['present', 'array'],
            'casualties_missing.*.barangay' => ['required', 'string'],
            'casualties_missing.*.name' => ['required', 'string'],
            'casualties_missing.*.age' => ['nullable', 'integer', 'min:0'],
            'casualties_missing.*.sex' => ['nullable', 'string', 'in:male,female'],
            'casualties_missing.*.cause' => ['nullable', 'string'],
            'casualties_missing.*.remarks' => ['nullable', 'string'],

            'casualties_dead' => ['present', 'array'],
            'casualties_dead.*.barangay' => ['required', 'string'],
            'casualties_dead.*.name' => ['required', 'string'],
            'casualties_dead.*.age' => ['nullable', 'integer', 'min:0'],
            'casualties_dead.*.sex' => ['nullable', 'string', 'in:male,female'],
            'casualties_dead.*.cause' => ['nullable', 'string'],
            'casualties_dead.*.remarks' => ['nullable', 'string'],

            // VII. Damages to Infrastructure
            'infrastructure_damages' => ['present', 'array'],
            'infrastructure_damages.*.barangay' => ['required', 'string'],
            'infrastructure_damages.*.facility_type' => ['required', 'string'],
            'infrastructure_damages.*.description' => ['nullable', 'string'],
            'infrastructure_damages.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],

            // VIII. Damage & Losses to Agriculture
            'agriculture_damages' => ['present', 'array'],
            'agriculture_damages.*.commodity' => ['required', 'string'],
            'agriculture_damages.*.no_of_farmers' => ['nullable', 'integer', 'min:0'],
            'agriculture_damages.*.area_affected_ha' => ['nullable', 'numeric', 'min:0'],
            'agriculture_damages.*.volume_mt' => ['nullable', 'numeric', 'min:0'],
            'agriculture_damages.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],

            // IX. Status of Assistance Provided
            'assistance_provided' => ['present', 'array'],
            'assistance_provided.*.source' => ['required', 'string'],
            'assistance_provided.*.type' => ['required', 'string'],
            'assistance_provided.*.quantity' => ['nullable', 'string'],
            'assistance_provided.*.beneficiaries' => ['nullable', 'string'],

            // X. Class Suspension
            'class_suspensions' => ['present', 'array'],
            'class_suspensions.*.barangay' => ['required', 'string'],
            'class_suspensions.*.level' => ['required', 'string'],
            'class_suspensions.*.date' => ['nullable', 'string'],
            'class_suspensions.*.remarks' => ['nullable', 'string'],

            // XI. Work Suspension
            'work_suspensions' => ['present', 'array'],
            'work_suspensions.*.office' => ['required', 'string'],
            'work_suspensions.*.date' => ['nullable', 'string'],
            'work_suspensions.*.remarks' => ['nullable', 'string'],

            // XII. Status of Lifelines
            'lifelines_roads_bridges' => ['present', 'array'],
            'lifelines_roads_bridges.*.barangay' => ['required', 'string'],
            'lifelines_roads_bridges.*.name' => ['required', 'string'],
            'lifelines_roads_bridges.*.type' => ['nullable', 'string'],
            'lifelines_roads_bridges.*.status' => ['nullable', 'string'],
            'lifelines_roads_bridges.*.remarks' => ['nullable', 'string'],

            'lifelines_power' => ['present', 'array'],
            'lifelines_power.*.barangay' => ['required', 'string'],
            'lifelines_power.*.provider' => ['nullable', 'string'],
            'lifelines_power.*.status' => ['nullable', 'string'],
            'lifelines_power.*.remarks' => ['nullable', 'string'],

            'lifelines_water' => ['present', 'array'],
            'lifelines_water.*.barangay' => ['required', 'string'],
            'lifelines_water.*.provider' => ['nullable', 'string'],
            'lifelines_water.*.status' => ['nullable', 'string'],
            'lifelines_water.*.remarks' => ['nullable', 'string'],

            'lifelines_communication' => ['present', 'array'],
            'lifelines_communication.*.barangay' => ['required', 'string'],
            'lifelines_communication.*.provider' => ['nullable', 'string'],
            'lifelines_communication.*.status' => ['nullable', 'string'],
            'lifelines_communication.*.remarks' => ['nullable', 'string'],

            // XIII-XV. Ports
            'seaport_status' => ['present', 'array'],
            'seaport_status.*.port_name' => ['required', 'string'],
            'seaport_status.*.status' => ['nullable', 'string'],
            'seaport_status.*.remarks' => ['nullable', 'string'],

            'airport_status' => ['present', 'array'],
            'airport_status.*.port_name' => ['required', 'string'],
            'airport_status.*.status' => ['nullable', 'string'],
            'airport_status.*.remarks' => ['nullable', 'string'],

            'landport_status' => ['present', 'array'],
            'landport_status.*.port_name' => ['required', 'string'],
            'landport_status.*.status' => ['nullable', 'string'],
            'landport_status.*.remarks' => ['nullable', 'string'],

            // XVI. Stranded Passengers/Cargoes
            'stranded_passengers' => ['present', 'array'],
            'stranded_passengers.*.port_name' => ['required', 'string'],
            'stranded_passengers.*.passengers' => ['nullable', 'integer', 'min:0'],
            'stranded_passengers.*.rolling_cargoes' => ['nullable', 'integer', 'min:0'],
            'stranded_passengers.*.vessels' => ['nullable', 'integer', 'min:0'],
            'stranded_passengers.*.remarks' => ['nullable', 'string'],

            // XVII. Declaration of State of Calamity
            'calamity_declarations' => ['present', 'array'],
            'calamity_declarations.*.barangay' => ['required', 'string'],
            'calamity_declarations.*.date_declared' => ['nullable', 'string'],
            'calamity_declarations.*.remarks' => ['nullable', 'string'],

            // XVIII. Pre-emptive Evacuation
            'preemptive_evacuations' => ['present', 'array'],
            'preemptive_evacuations.*.barangay' => ['required', 'string'],
            'preemptive_evacuations.*.families' => ['nullable', 'integer', 'min:0'],
            'preemptive_evacuations.*.persons' => ['nullable', 'integer', 'min:0'],
            'preemptive_evacuations.*.remarks' => ['nullable', 'string'],

            // XIX. Gaps/Challenges
            'gaps_challenges' => ['present', 'array'],
            'gaps_challenges.*.description' => ['required', 'string'],
            'gaps_challenges.*.recommendation' => ['nullable', 'string'],

            // XX. Response Actions
            'response_actions' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validatePersonsNotLessThanFamilies($validator);
            $this->validateCumulativeVsCurrent($validator);
            $this->validateBarangaysInAffectedAreas($validator);
            $this->validateTotalsDoNotExceedAffected($validator);
        });
    }

    private function validatePersonsNotLessThanFamilies(Validator $validator): void
    {
        foreach ($this->input('affected_areas', []) as $index => $row) {
            $families = (int) ($row['families'] ?? 0);
            $persons = (int) ($row['persons'] ?? 0);

            if ($families === 0 && $persons > 0) {
                $validator->errors()->add(
                    "affected_areas.{$index}.persons",
                    'There cannot be persons without families in Affected Areas.'
                );
            } elseif ($persons < $families) {
                $validator->errors()->add(
                    "affected_areas.{$index}.persons",
                    'The number of persons must not be less than the number of families in Affected Areas.'
                );
            }
        }

        $sections = [
            'inside_evacuation_centers' => 'Inside Evacuation Centers',
            'outside_evacuation_centers' => 'Outside Evacuation Centers',
        ];

        foreach ($sections as $section => $label) {
            foreach ($this->input($section, []) as $index => $row) {
                $familiesCum = (int) ($row['families_cum'] ?? 0);
                $personsCum = (int) ($row['persons_cum'] ?? 0);
                $familiesNow = (int) ($row['families_now'] ?? 0);
                $personsNow = (int) ($row['persons_now'] ?? 0);

                if ($familiesCum === 0 && $personsCum > 0) {
                    $validator->errors()->add(
                        "{$section}.{$index}.persons_cum",
                        "There cannot be cumulative persons without cumulative families in {$label}."
                    );
                } elseif ($personsCum < $familiesCum) {
                    $validator->errors()->add(
                        "{$section}.{$index}.persons_cum",
                        "The cumulative persons must not be less than the cumulative families in {$label}."
                    );
                }

                if ($familiesNow === 0 && $personsNow > 0) {
                    $validator->errors()->add(
                        "{$section}.{$index}.persons_now",
                        "There cannot be current persons without current families in {$label}."
                    );
                } elseif ($personsNow < $familiesNow) {
                    $validator->errors()->add(
                        "{$section}.{$index}.persons_now",
                        "The current persons must not be less than the current families in {$label}."
                    );
                }
            }
        }
    }

    private function validateCumulativeVsCurrent(Validator $validator): void
    {
        $sections = [
            'inside_evacuation_centers' => ['families', 'persons'],
            'outside_evacuation_centers' => ['families', 'persons'],
        ];

        foreach ($sections as $section => $fields) {
            $rows = $this->input($section, []);
            foreach ($rows as $index => $row) {
                foreach ($fields as $field) {
                    $cum = (int) ($row["{$field}_cum"] ?? 0);
                    $now = (int) ($row["{$field}_now"] ?? 0);
                    if ($now > $cum) {
                        $validator->errors()->add(
                            "{$section}.{$index}.{$field}_now",
                            "The current {$field} must not be greater than the cumulative {$field}."
                        );
                    }
                }
            }
        }
    }

    private function validateBarangaysInAffectedAreas(Validator $validator): void
    {
        $affectedBarangays = collect($this->input('affected_areas', []))
            ->pluck('barangay')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sections = ['inside_evacuation_centers', 'outside_evacuation_centers', 'damaged_houses'];
        $labels = [
            'inside_evacuation_centers' => 'Inside Evacuation Centers',
            'outside_evacuation_centers' => 'Outside Evacuation Centers',
            'damaged_houses' => 'Damaged Houses',
        ];

        foreach ($sections as $section) {
            $rows = $this->input($section, []);
            foreach ($rows as $index => $row) {
                $barangay = $row['barangay'] ?? '';
                if ($barangay !== '' && ! in_array($barangay, $affectedBarangays, true)) {
                    $validator->errors()->add(
                        "{$section}.{$index}.barangay",
                        "Barangay \"{$barangay}\" in {$labels[$section]} is not listed in Affected Areas."
                    );
                }
            }
        }
    }

    private function validateTotalsDoNotExceedAffected(Validator $validator): void
    {
        $affectedFamilies = collect($this->input('affected_areas', []))
            ->sum(fn (array $row): int => (int) ($row['families'] ?? 0));
        $affectedPersons = collect($this->input('affected_areas', []))
            ->sum(fn (array $row): int => (int) ($row['persons'] ?? 0));

        $idpFamiliesCum = 0;
        $idpPersonsCum = 0;
        $idpFamiliesNow = 0;
        $idpPersonsNow = 0;

        foreach (['inside_evacuation_centers', 'outside_evacuation_centers'] as $section) {
            foreach ($this->input($section, []) as $row) {
                $idpFamiliesCum += (int) ($row['families_cum'] ?? 0);
                $idpPersonsCum += (int) ($row['persons_cum'] ?? 0);
                $idpFamiliesNow += (int) ($row['families_now'] ?? 0);
                $idpPersonsNow += (int) ($row['persons_now'] ?? 0);
            }
        }

        if ($idpFamiliesCum > $affectedFamilies) {
            $validator->errors()->add(
                'inside_evacuation_centers',
                "The total cumulative displaced families inside and outside evacuation centers ({$idpFamiliesCum}) must not exceed the total affected families ({$affectedFamilies})."
            );
        }

        if ($idpPersonsCum > $affectedPersons) {
            $validator->errors()->add(
                'outside_evacuation_centers',
                "The total cumulative displaced persons inside and outside evacuation centers ({$idpPersonsCum}) must not exceed the total affected persons ({$affectedPersons})."
            );
        }

        if ($idpFamiliesNow > $affectedFamilies) {
            $validator->errors()->add(
                'inside_evacuation_centers',
                "The total current displaced families inside and outside evacuation centers ({$idpFamiliesNow}) must not exceed the total affected families ({$affectedFamilies})."
            );
        }

        if ($idpPersonsNow > $affectedPersons) {
            $validator->errors()->add(
                'outside_evacuation_centers',
                "The total current displaced persons inside and outside evacuation centers ({$idpPersonsNow}) must not exceed the total affected persons ({$affectedPersons})."
            );
        }
    }
}
