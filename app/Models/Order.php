<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_name', 'customer_phone', 'address'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }

    /**
     * @return HasMany<OrderJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(OrderJob::class);
    }

    /**
     * nobody sets the order status directly, it follows the jobs
     */
    public function syncStatus(): void
    {
        // two jobs of the same order can change at the same time
        // locking the order row makes those transactions take turns here, so the last one sees every job's status
        static::query()->lockForUpdate()->find($this->id);

        $statuses = $this->jobs()->pluck('status');

        $this->status = match (true) {
            $statuses->every(fn (JobStatus $status) => $status === JobStatus::Pending) => OrderStatus::New,
            $statuses->every(fn (JobStatus $status) => $status === JobStatus::Installed) => OrderStatus::Completed,
            default => OrderStatus::InProgress,
        };

        $this->save();
    }
}
