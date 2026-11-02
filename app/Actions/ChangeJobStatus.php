<?php

namespace App\Actions;

use App\Enums\JobStatus;
use App\Jobs\NotifyInstallerJobReady;
use App\Models\OrderJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeJobStatus
{
    /**
     * Moves the job to the given status and records the change
     * Authorization is the caller's job (OrderJobPolicy::changeStatus)
     *
     * @throws ValidationException when the workflow doesn't allow the transition
     */
    public function handle(OrderJob $job, JobStatus $to, User $user): OrderJob
    {
        return DB::transaction(function () use ($job, $to, $user) {
            // Reread with a row lock, so two requests can't both move the job from the same status
            $job = OrderJob::query()->lockForUpdate()->findOrFail($job->id);
            $from = $job->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "A job can't move from {$from->value} to {$to->value}.",
                ]);
            }

            $job->status = $to;
            $job->save();

            $job->recordStatusChange($from, $to, $user);
            $job->order->syncStatus();

            if ($to === JobStatus::ReadyForInstallation) {
                // Only queue it once the change is committed; a rollback shouldn't notify anyone
                NotifyInstallerJobReady::dispatch($job)->afterCommit();
            }

            return $job;
        });
    }
}
