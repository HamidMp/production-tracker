<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\Order;
use App\Models\OrderJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_moves_a_job_to_the_next_stage_and_it_is_recorded(): void
    {
        $planner = User::factory()->planner()->create();
        $job = OrderJob::factory()->withStatus(JobStatus::Cutting)->create();
        Sanctum::actingAs($planner);

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'edge_banding'])
            ->assertOk()
            ->assertJsonPath('data.status', 'edge_banding');

        $this->assertDatabaseHas('job_status_changes', [
            'order_job_id' => $job->id,
            'from_status' => 'cutting',
            'to_status' => 'edge_banding',
            'changed_by' => $planner->id,
        ]);
        $this->assertSame('in_progress', $job->order->fresh()->status->value);
    }

    public function test_a_job_cannot_go_back_to_an_earlier_stage(): void
    {
        $job = OrderJob::factory()->withStatus(JobStatus::Installed)->create();
        Sanctum::actingAs(User::factory()->planner()->create());

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'cutting'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(JobStatus::Installed, $job->fresh()->status);
        $this->assertDatabaseCount('job_status_changes', 0);
    }

    public function test_a_job_cannot_skip_a_stage(): void
    {
        $job = OrderJob::factory()->withStatus(JobStatus::Cutting)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'assembly'])
            ->assertUnprocessable();

        $this->assertSame(JobStatus::Cutting, $job->fresh()->status);
    }

    public function test_status_must_be_a_known_value(): void
    {
        $job = OrderJob::factory()->create();
        Sanctum::actingAs(User::factory()->planner()->create());

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'painting'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_installer_moves_their_own_job_through_installation(): void
    {
        $installer = User::factory()->installer()->create();
        $order = Order::factory()->create();
        $job = OrderJob::factory()->for($order)->assignedTo($installer)
            ->withStatus(JobStatus::ReadyForInstallation)
            ->create();
        Sanctum::actingAs($installer);

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'installing'])->assertOk();
        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'installed'])->assertOk();

        $this->assertDatabaseHas('job_status_changes', [
            'order_job_id' => $job->id,
            'to_status' => 'installed',
            'changed_by' => $installer->id,
        ]);
        // It was the order's only job
        $this->assertSame('completed', $order->fresh()->status->value);
    }

    public function test_installer_cannot_update_workshop_stages(): void
    {
        $installer = User::factory()->installer()->create();
        $job = OrderJob::factory()->assignedTo($installer)->withStatus(JobStatus::Cutting)->create();
        Sanctum::actingAs($installer);

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'edge_banding'])
            ->assertForbidden();

        $this->assertSame(JobStatus::Cutting, $job->fresh()->status);
    }

    public function test_installer_cannot_change_another_installers_job(): void
    {
        $job = OrderJob::factory()
            ->assignedTo(User::factory()->installer()->create())
            ->withStatus(JobStatus::ReadyForInstallation)
            ->create();
        Sanctum::actingAs(User::factory()->installer()->create());

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'installing'])
            ->assertNotFound();

        $this->assertSame(JobStatus::ReadyForInstallation, $job->fresh()->status);
    }

    public function test_history_lists_the_changes_oldest_first(): void
    {
        $planner = User::factory()->planner()->create();
        $job = OrderJob::factory()->create();
        Sanctum::actingAs($planner);

        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'cutting'])->assertOk();
        $this->patchJson("/api/jobs/{$job->id}/status", ['status' => 'edge_banding'])->assertOk();

        $this->getJson("/api/jobs/{$job->id}/history")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.from_status', 'pending')
            ->assertJsonPath('data.1.to_status', 'edge_banding')
            ->assertJsonPath('data.1.changed_by.id', $planner->id);
    }
}
