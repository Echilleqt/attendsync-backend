<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricCredential extends Model
{
    protected $fillable = [
        'employee_id',
        'credential_id',
        'verification_method',
        'public_key_pem',
        'public_key_hash',
        'device_name',
        'verified_at',
    ];

    protected $hidden = [
        'public_key_pem',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
