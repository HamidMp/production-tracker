<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_can_create_an_order(): void
    {
        Sanctum::actingAs(User::factory()->planner()->create());

        $this->postJson('/api/orders', [
            'customer_name' => 'Maryam Rahimi',
            'customer_phone' => '09121234567',
            'address' => 'Tehran, Valiasr St, No. 12',
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer_name', 'Maryam Rahimi')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('orders', ['customer_phone' => '09121234567', 'status' => 'new']);
    }

    public function test_order_fields_are_validated(): void
    {
        Sanctum::actingAs(User::factory()->planner()->create());

        $this->postJson('/api/orders', ['customer_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_phone', 'address']);
    }

    public function test_installer_cannot_create_orders(): void
    {
        Sanctum::actingAs(User::factory()->installer()->create());

        $this->postJson('/api/orders', [
            'customer_name' => 'Maryam Rahimi',
            'customer_phone' => '09121234567',
            'address' => 'Tehran',
        ])->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_installer_cannot_list_orders(): void
    {
        Sanctum::actingAs(User::factory()->installer()->create());

        $this->getJson('/api/orders')->assertForbidden();
    }

    public function test_orders_can_be_filtered_by_status_and_paginated(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        Order::factory()->count(3)->create();
        Order::factory()->count(2)->create(['status' => OrderStatus::Completed]);

        $this->getJson('/api/orders?status=completed&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/orders?status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
