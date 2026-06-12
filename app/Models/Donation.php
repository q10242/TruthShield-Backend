<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'user_id',
        'provider',
        'merchant_trade_no',
        'amount',
        'purpose',
        'status',
        'donor_name',
        'donor_email',
        'message',
        'request_payload',
        'provider_payload',
        'paid_at',
        'receipt_email_status',
        'receipt_email_sent_at',
        'receipt_email_error',
    ];

    protected $casts = [
        'amount' => 'integer',
        'request_payload' => 'array',
        'provider_payload' => 'array',
        'paid_at' => 'datetime',
        'receipt_email_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
