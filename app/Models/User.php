<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'email',
        'fb_id',
        'auth_provider',
        'identity_level',
        'risk_status',
        'public_identity_label',
        'is_real_name_public',
        'profile_bio',
        'email_preferences',
        'identity_multiplier',
        'abuse_multiplier',
        'password',
        'trust_score',
        'is_admin',
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
            'trust_score' => 'float',
            'identity_multiplier' => 'float',
            'abuse_multiplier' => 'float',
            'is_admin' => 'boolean',
            'is_real_name_public' => 'boolean',
            'email_preferences' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin || in_array($this->email, config('truthshield.admin_emails', []), true);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function evidenceReactions(): HasMany
    {
        return $this->hasMany(EvidenceReaction::class);
    }

    public function readSessions(): HasMany
    {
        return $this->hasMany(ReadSession::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(Appeal::class);
    }

    public function trustScoreHistories(): HasMany
    {
        return $this->hasMany(TrustScoreHistory::class);
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class)->withPivot('reason')->withTimestamps();
    }

    public function verifiedClaimants(): HasMany
    {
        return $this->hasMany(VerifiedClaimant::class);
    }

    public function officialResponses(): HasMany
    {
        return $this->hasMany(OfficialResponse::class);
    }

    public function officialResponseReactions(): HasMany
    {
        return $this->hasMany(OfficialResponseReaction::class);
    }

    public function newsEvents(): HasMany
    {
        return $this->hasMany(NewsEvent::class, 'created_by');
    }

    public function publicName(): string
    {
        if ($this->is_real_name_public && $this->name) {
            return $this->name;
        }

        return $this->display_name ?: $this->name ?: 'TruthShield user';
    }
}
