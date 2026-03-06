<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Barangay extends Model
{
    /** @use HasFactory<\Database\Factories\BarangayFactory> */
    use HasFactory;

    protected $fillable = [
        'psgc_code',
        'name',
        'city_municipality_id',
    ];

    /** @return BelongsTo<CityMunicipality, $this> */
    public function cityMunicipality(): BelongsTo
    {
        return $this->belongsTo(CityMunicipality::class);
    }
}
