<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricVerification extends Model
{
    protected $fillable = [
        'employee_id',
        'biometric_credential_id',
        'verification_method',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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
