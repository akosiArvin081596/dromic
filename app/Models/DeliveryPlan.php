<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryPlan extends Model
{
    protected $fillable = [
        'request_letter_id',
        'created_by',
        'plan_items',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'plan_items' => 'array',
        ];
    }

    /** @return BelongsTo<RequestLetter, $this> */
    public function requestLetter(): BelongsTo
    {
        return $this->belongsTo(RequestLetter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
