<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'schedule_id',
        'effective_date',
        'end_date',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
