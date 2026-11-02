<?php

namespace App\Models;

use App\Enums\JobStatus;
use Database\Factories\OrderJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * a job of an order (e.g. "Kitchen wall cabinets"). Called OrderJob so it doesn't clash with Laravel's queue jobs.
 *
 * Status is not fillable on purpose: it only changes through ChangeJobStatus
 */
#[Fillable(['title', 'installer_id'])]
class OrderJob extends Model
{
    /** @use HasFactory<OrderJobFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installer_id');
    }

    /**
     * @return HasMany<JobStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(JobStatusChange::class);
    }
}
