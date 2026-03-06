<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'user_type',
        'region_id',
        'province_id',
        'city_municipality_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'user_type' => UserType::class,
        ];
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** @return HasMany<Incident, $this> */
    public function createdIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'created_by');
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return BelongsTo<CityMunicipality, $this> */
    public function cityMunicipality(): BelongsTo
    {
        return $this->belongsTo(CityMunicipality::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isRegional(): bool
    {
        return $this->role === UserRole::Regional;
    }

    public function isProvincial(): bool
    {
        return $this->role === UserRole::Provincial;
    }

    public function isLgu(): bool
    {
        return $this->role === UserRole::Lgu;
    }

    public function isRros(): bool
    {
        return $this->role === UserRole::Regional && $this->user_type === UserType::Rros;
    }

    public function isDrims(): bool
    {
        return $this->role === UserRole::Regional && $this->user_type === UserType::Drims;
    }

    /**
     * View-only users see the summary dashboard (like Regional Director).
     * They cannot create/edit reports, manage incidents, or perform workflow actions.
     */
    public function isViewOnly(): bool
    {
        if ($this->role === UserRole::Lgu) {
            return in_array($this->user_type, [UserType::DswdLgu, UserType::Ldrrmo], true);
        }

        if ($this->role === UserRole::Provincial) {
            return in_array($this->user_type, [UserType::Pdrrmo, UserType::Pswdo], true);
        }

        return false;
    }

    public function isEscort(): bool
    {
        return $this->role === UserRole::Escort;
    }

    public function isRegionalDirector(): bool
    {
        return $this->role === UserRole::RegionalDirector;
    }

    /** @return BelongsToMany<Conversation, $this> */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getActorDisplayName(): string
    {
        return match ($this->role) {
            UserRole::Lgu => $this->cityMunicipality?->name ?? $this->name,
            UserRole::Provincial => $this->province?->name ?? $this->name,
            UserRole::Regional, UserRole::RegionalDirector => $this->region?->name ?? $this->name,
            UserRole::Admin => 'Admin',
            UserRole::Escort => $this->name,
        };
    }
}
