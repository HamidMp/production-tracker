<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\Order;
use App\Models\OrderJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderJobApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_can_add_a_job_to_an_order(): void
    {
        $planner = User::factory()->planner()->create();
        $installer = User::factory()->installer()->create();
        $order = Order::factory()->create();
        Sanctum::actingAs($planner);

        $response = $this->postJson("/api/orders/{$order->id}/jobs", [
            'title' => 'Kitchen wall cabinets',
            'installer_id' => $installer->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.installer.id', $installer->id)
            ->assertJsonPath('data.customer.address', $order->address);

        $this->assertDatabaseHas('job_status_changes', [
            'order_job_id' => $response->json('data.id'),
            'from_status' => null,
            'to_status' => 'pending',
            'changed_by' => $planner->id,
        ]);
    }

    public function test_job_can_only_be_assigned_to_an_installer(): void
    {
        $planner = User::factory()->planner()->create();
        Sanctum::actingAs($planner);

        $this->postJson('/api/orders/'.Order::factory()->create()->id.'/jobs', [
            'title' => 'Wardrobe',
            'installer_id' => $planner->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('installer_id');
    }

    public function test_installer_cannot_add_jobs(): void
    {
        Sanctum::actingAs(User::factory()->installer()->create());

        $this->postJson('/api/orders/'.Order::factory()->create()->id.'/jobs', ['title' => 'Wardrobe'])
            ->assertForbidden();
    }

    public function test_installer_only_sees_their_own_jobs(): void
    {
        $installer = User::factory()->installer()->create();
        $ownJobs = OrderJob::factory()->count(2)->assignedTo($installer)->create();
        OrderJob::factory()->assignedTo(User::factory()->installer()->create())->create();
        OrderJob::factory()->create();
        Sanctum::actingAs($installer);

        $response = $this->getJson('/api/jobs')->assertOk();

        $this->assertEqualsCanonicalizing($ownJobs->pluck('id')->all(), $response->json('data.*.id'));
    }

    public function test_installer_gets_404_for_another_installers_job(): void
    {
        $installer = User::factory()->installer()->create();
        $ownJob = OrderJob::factory()->assignedTo($installer)->create();
        $otherJob = OrderJob::factory()->assignedTo(User::factory()->installer()->create())->create();
        Sanctum::actingAs($installer);

        $this->getJson("/api/jobs/{$ownJob->id}")->assertOk();
        $this->getJson("/api/jobs/{$otherJob->id}")->assertNotFound();
    }

    public function test_jobs_can_be_filtered_by_status(): void
    {
        Sanctum::actingAs(User::factory()->planner()->create());
        $assemblyJob = OrderJob::factory()->withStatus(JobStatus::Assembly)->create();
        OrderJob::factory()->withStatus(JobStatus::Cutting)->create();

        $this->getJson('/api/jobs?status=assembly')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assemblyJob->id);
    }

    public function test_order_details_include_its_jobs(): void
    {
        Sanctum::actingAs(User::factory()->planner()->create());
        $order = Order::factory()->create();
        OrderJob::factory()->count(2)->for($order)->create();

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.jobs')
            ->assertJsonPath('data.jobs.0.installer', null);
    }
}
