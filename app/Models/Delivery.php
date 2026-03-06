<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'request_letter_id',
        'escort_user_id',
        'recorded_by',
        'delivery_items',
        'delivery_date',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'delivery_items' => 'array',
            'delivery_date' => 'date',
        ];
    }

    /** @return BelongsTo<RequestLetter, $this> */
    public function requestLetter(): BelongsTo
    {
        return $this->belongsTo(RequestLetter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function escort(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escort_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return HasMany<DeliveryAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(DeliveryAttachment::class);
    }
}
