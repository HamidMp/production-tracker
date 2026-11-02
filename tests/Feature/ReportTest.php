<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\OrderJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_counts_jobs_per_stage_including_empty_stages(): void
    {
        OrderJob::factory()->count(2)->withStatus(JobStatus::Cutting)->create();
        OrderJob::factory()->withStatus(JobStatus::Installed)->create();
        Sanctum::actingAs(User::factory()->planner()->create());

        $this->getJson('/api/reports/jobs-per-status')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['status' => 'pending', 'total' => 0],
                ['status' => 'cutting', 'total' => 2],
                ['status' => 'edge_banding', 'total' => 0],
                ['status' => 'assembly', 'total' => 0],
                ['status' => 'ready_for_installation', 'total' => 0],
                ['status' => 'installing', 'total' => 0],
                ['status' => 'installed', 'total' => 1],
            ]]);
    }

    public function test_installer_cannot_see_the_report(): void
    {
        Sanctum::actingAs(User::factory()->installer()->create());

        $this->getJson('/api/reports/jobs-per-status')->assertForbidden();
    }
}
