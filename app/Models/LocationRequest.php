<?php

namespace App\Models;

use App\Enums\LocationRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_by',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'status' => LocationRequestStatus::class,
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function employeeLocations(): HasMany
    {
        return $this->hasMany(EmployeeLocation::class);
    }
}
