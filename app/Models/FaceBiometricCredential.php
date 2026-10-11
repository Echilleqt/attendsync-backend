<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class FaceBiometricCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'face_template',
        'model_version',
        'enrollment_metadata',
        'verified_at',
    ];

    protected $hidden = [
        'face_template',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'enrollment_metadata' => 'array',
        ];
    }

    public function setFaceTemplateAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['face_template'] = null;

            return;
        }

        $encoded = is_array($value)
            ? json_encode($value, JSON_THROW_ON_ERROR)
            : (string) $value;

        $this->attributes['face_template'] = Crypt::encryptString($encoded);
    }

    public function getFaceTemplateAttribute(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $decoded = Crypt::decryptString($value);
            $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);

            return is_array($payload) ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
