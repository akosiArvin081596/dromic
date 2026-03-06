<?php

namespace App\Models;

use App\Enums\RequestLetterStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RequestLetter extends Model
{
    /** @use HasFactory<\Database\Factories\RequestLetterFactory> */
    use HasFactory;

    protected $fillable = [
        'incident_id',
        'user_id',
        'city_municipality_id',
        'file_path',
        'original_filename',
        'augmentation_items',
        'status',
        'endorsed_by',
        'endorsed_at',
        'approved_by',
        'approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'augmentation_items' => 'array',
            'status' => RequestLetterStatus::class,
            'endorsed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Incident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CityMunicipality, $this> */
    public function cityMunicipality(): BelongsTo
    {
        return $this->belongsTo(CityMunicipality::class);
    }

    /** @return BelongsTo<User, $this> */
    public function endorser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorsed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /** @return HasOne<DeliveryPlan, $this> */
    public function deliveryPlan(): HasOne
    {
        return $this->hasOne(DeliveryPlan::class);
    }
}
