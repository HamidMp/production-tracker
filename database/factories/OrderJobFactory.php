<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\Order;
use App\Models\OrderJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderJob>
 */
class OrderJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'title' => fake()->randomElement([
                'Kitchen base cabinets',
                'Kitchen wall cabinets',
                'Kitchen island',
                'Pantry unit',
                'Bathroom vanity',
                'Wardrobe',
                'TV unit',
                'Laundry cabinets',
                'Shoe cabinet',
                'Office storage',
            ]),
            'status' => JobStatus::Pending,
            'installer_id' => null,
        ];
    }

    public function withStatus(JobStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function assignedTo(User $installer): static
    {
        return $this->state(['installer_id' => $installer->id]);
    }
}
