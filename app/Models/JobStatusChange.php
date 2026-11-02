<?php

namespace App\Models;

use App\Enums\JobStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['from_status', 'to_status', 'changed_by'])]
class JobStatusChange extends Model
{
    // history rows are never updated
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => JobStatus::class,
            'to_status' => JobStatus::class,
        ];
    }

    /**
     * @return BelongsTo<OrderJob, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(OrderJob::class, 'order_job_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
