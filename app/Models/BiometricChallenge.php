<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricChallenge extends Model
{
    protected $fillable = [
        'id',
        'employee_id',
        'biometric_credential_id',
        'purpose',
        'nonce',
        'expires_at',
        'consumed_at',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(
            BiometricCredential::class,
            'biometric_credential_id'
        );
    }
}