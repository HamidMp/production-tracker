<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Jobs\NotifyInstallerJobReady;
use App\Models\OrderJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstallerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_is_queued_when_a_job_is_ready_for_installation(): void
    {
        Queue::fake();
        $job = OrderJob::factory()->withStatus(JobStatus::Assembly)->create();
        Sanctum::actingAs(User::factory()->planner()->create());

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'ready_for_installation'])->assertOk();

        Queue::assertPushed(
            NotifyInstallerJobReady::class,
            fn (NotifyInstallerJobReady $notification) => $notification->orderJob->is($job),
        );
    }

    public function test_other_stages_do_not_notify(): void
    {
        Queue::fake();
        $job = OrderJob::factory()->withStatus(JobStatus::EdgeBanding)->create();
        Sanctum::actingAs(User::factory()->planner()->create());

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'assembly'])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_the_notification_names_the_installer_and_address(): void
    {
        Log::spy();
        $installer = User::factory()->installer()->create();
        $job = OrderJob::factory()->assignedTo($installer)->create();

        (new NotifyInstallerJobReady($job))->handle();

        Log::shouldHaveReceived('info')->once()->withArgs(
            fn (string $message, array $context) => str_contains($message, $installer->name)
                && $context['installer_id'] === $installer->id
                && $context['address'] === $job->order->address,
        );
    }
}
