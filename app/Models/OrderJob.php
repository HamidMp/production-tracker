<?php

namespace App\Models;

use App\Enums\JobStatus;
use Database\Factories\OrderJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Installers only see the jobs assigned to them
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isInstaller()) {
            $query->where('installer_id', $user->id);
        }
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->installer()->is($user);
    }

    public function recordStatusChange(?JobStatus $from, JobStatus $to, User $user): void
    {
        $this->statusChanges()->create([
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $user->id,
        ]);
    }
}
