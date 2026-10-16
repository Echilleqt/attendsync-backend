<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_id',
        'first_name',
        'middle_name',
        'last_name',
        'position',
        'department',
        'department_id',
        'schedule_id',
        'date_hired',
        'status',
    ];

    protected $casts = [
        'date_hired' => 'date',
    ];

    /**
     * An employee belongs to a user account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departmentModel(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function approvedDevices(): HasMany
    {
        return $this->hasMany(ApprovedDevice::class);
    }

    /**
     * An employee can have many attendance records.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function biometricCredentials(): HasMany
    {
        return $this->hasMany(BiometricCredential::class);
    }

    public function faceBiometricCredential(): HasOne
    {
        return $this->hasOne(FaceBiometricCredential::class);
    }

    public function faceVerificationTokens(): HasMany
    {
        return $this->hasMany(FaceVerificationToken::class);
    }

    public function attendanceCorrectionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    /**
     * Full employee name.
     */
    public function getFullNameAttribute(): string
    {
        return trim(
            $this->first_name . ' ' .
            ($this->middle_name ? $this->middle_name . ' ' : '') .
            $this->last_name
        );
    }
}