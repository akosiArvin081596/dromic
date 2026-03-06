<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LguSetting extends Model
{
    protected $fillable = [
        'city_municipality_id',
        'signatory_1_name',
        'signatory_1_designation',
        'signatory_2_name',
        'signatory_2_designation',
        'signatory_3_name',
        'signatory_3_designation',
        'logo_path',
    ];

    /** @return BelongsTo<CityMunicipality, $this> */
    public function cityMunicipality(): BelongsTo
    {
        return $this->belongsTo(CityMunicipality::class);
    }
}
