<?php

namespace App\Jobs;

use App\Models\OrderJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Lets the installer know a job is ready to be installed
 * For now the "notification" is a log line; an SMS or push channel would plug in here
 */
class NotifyInstallerJobReady implements ShouldQueue
{
    use Queueable;

    public function __construct(public OrderJob $orderJob) {}

    public function handle(): void
    {
        $this->orderJob->loadMissing(['order', 'installer']);
        $installer = $this->orderJob->installer;

        $context = [
            'job_id' => $this->orderJob->id,
            'order_id' => $this->orderJob->order_id,
            'address' => $this->orderJob->order->address,
        ];

        if (! $installer) {
            Log::warning('Job is ready for installation but has no installer assigned.', $context);

            return;
        }

        Log::info("Job is ready for installation, notifying {$installer->name}.", $context + [
            'installer_id' => $installer->id,
        ]);
    }
}
