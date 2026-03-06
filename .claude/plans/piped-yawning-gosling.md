# Plan: Massive Incidents Auto-Assign All LGUs in Scope

## Context
Currently, both "local" and "massive" incident types show the same "Assign LGUs" picker. For massive (multi-province) incidents, manually picking LGUs doesn't make sense — all LGUs in the user's scope should be auto-assigned. The LGU picker should be hidden when "massive" is selected.

## Changes

### 1. Frontend — `resources/js/pages/Incidents/Create.vue`
- Hide the "Assign LGUs" section (`v-if="!isLgu"` block, lines 91-116) when `form.type === 'massive'`
- Clear `city_municipality_ids` when switching to massive (backend handles auto-assign)
- Show an info banner instead: "All LGUs in your scope will be automatically assigned."
- Update type dropdown label: `Massive (Multi-Province)` instead of `Massive (Multi-LGU)`

### 2. Frontend — `resources/js/pages/Incidents/Edit.vue`
- Same pattern: hide "Assign LGUs" section when `form.type === 'massive'`
- Show the same info banner when massive is selected

### 3. Backend — `StoreIncidentRequest.php`
- Make `city_municipality_ids` conditionally required: only required when `type` is `local`
- When `type` is `massive`, the field can be absent

### 4. Backend — `IncidentController::store()`
- When type is `massive`: auto-resolve all city_municipality IDs in scope
  - **Admin** → all `CityMunicipality::pluck('id')`
  - **Regional** → `CityMunicipality::whereHas('province', fn($q) => $q->where('region_id', $user->region_id))->pluck('id')`
- Attach those IDs to the incident pivot
- Existing notification logic continues to work (it uses the resolved `$cityMunicipalityIds`)

### 5. Backend — `UpdateIncidentRequest.php` + `IncidentController::update()`
- Same conditional validation: `city_municipality_ids` only required when type is `local`
- When updating to massive: auto-resolve and sync all LGUs in scope (same logic as store)

## Files

| File | Change |
|------|--------|
| `resources/js/pages/Incidents/Create.vue` | Hide LGU picker for massive, show info banner, clear IDs on type switch |
| `resources/js/pages/Incidents/Edit.vue` | Hide LGU picker for massive, show info banner |
| `app/Http/Requests/StoreIncidentRequest.php` | Conditional validation for `city_municipality_ids` |
| `app/Http/Requests/UpdateIncidentRequest.php` | Conditional validation for `city_municipality_ids` |
| `app/Http/Controllers/IncidentController.php` | Auto-resolve LGU IDs for massive type in `store()` and `update()` |

## Verification
- `npm run lint && npm run format && npm run build`
- `vendor/bin/pint --dirty`
- `php artisan test --compact`
- Manual: create a massive incident as admin → all LGUs attached, notifications sent
- Manual: create a massive incident as regional → only region's LGUs attached
