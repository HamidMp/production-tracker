<?php

namespace App\Http\Controllers;

use App\Actions\ChangeJobStatus;
use App\Enums\JobStatus;
use App\Http\Requests\ChangeJobStatusRequest;
use App\Http\Resources\JobStatusChangeResource;
use App\Http\Resources\OrderJobResource;
use App\Models\OrderJob;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class JobStatusController extends Controller
{
    /**
     * the job status history, oldest first
     */
    public function index(OrderJob $job): AnonymousResourceCollection
    {
        Gate::authorize('view', $job);

        $changes = $job->statusChanges()
            ->with('changedBy')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return JobStatusChangeResource::collection($changes);
    }

    public function update(ChangeJobStatusRequest $request, OrderJob $job, ChangeJobStatus $changeJobStatus): OrderJobResource
    {
        $status = $request->enum('status', JobStatus::class);

        Gate::authorize('changeStatus', [$job, $status]);

        $job = $changeJobStatus->handle($job, $status, $request->user());

        return new OrderJobResource($job->load(['order', 'installer']));
    }
}
