<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'merchant_trade_no',
        'amount',
        'status',
        'donor_name',
        'donor_email',
        'message',
        'request_payload',
        'provider_payload',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'request_payload' => 'array',
        'provider_payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
