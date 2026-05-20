<?php

namespace App\Models;

use App\Enums\OvertimeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'requested_hours',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'requested_hours' => 'decimal:2',
            'status' => OvertimeRequestStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
