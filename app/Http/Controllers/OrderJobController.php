<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Http\Requests\StoreOrderJobRequest;
use App\Http\Resources\OrderJobResource;
use App\Models\Order;
use App\Models\OrderJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderJobController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(JobStatus::class)],
            'order_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $jobs = OrderJob::query()
            ->visibleTo($request->user())
            ->with(['order', 'installer'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['order_id'] ?? null, fn (Builder $query, string $orderId) => $query->where('order_id', $orderId))
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return OrderJobResource::collection($jobs);
    }

    public function store(StoreOrderJobRequest $request, Order $order): OrderJobResource
    {
        $job = DB::transaction(function () use ($request, $order) {
            $job = $order->jobs()->create($request->validated());

            // start the history at creation, so it shows who created the job and when
            $job->recordStatusChange(null, $job->status, $request->user());
            $order->syncStatus();

            return $job;
        });

        return new OrderJobResource($job->load(['order', 'installer']));
    }

    public function show(OrderJob $job): OrderJobResource
    {
        Gate::authorize('view', $job);

        return new OrderJobResource($job->load(['order', 'installer']));
    }
}
