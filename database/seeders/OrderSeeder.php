<?php

namespace Database\Seeders;

use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\JobStatusChange;
use App\Models\Order;
use App\Models\OrderJob;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $planner = User::query()->where('role', UserRole::Planner)->firstOrFail();
        $installers = User::query()->where('role', UserRole::Installer)->get();

        foreach (range(1, 15) as $i) {
            // a couple of orders not started yet and a couple of finished ones, the rest in progress
            $pickStatus = match (true) {
                $i <= 2 => fn () => JobStatus::Pending,
                $i <= 4 => fn () => JobStatus::Installed,
                default => fn () => Arr::random(JobStatus::cases()),
            };

            $orderedAt = now()->subDays(random_int(12, 40));

            $order = Order::factory()->create([
                'created_at' => $orderedAt,
                'updated_at' => $orderedAt,
            ]);

            foreach (range(1, random_int(1, 4)) as $__) {
                $this->createJob($order, $pickStatus(), $installers->random(), $planner);
            }

            $order->syncStatus();
        }
    }

    /**
     * creates a job in the given status together with a history that walks
     * the workflow up to that status, so the data is consistent.
     */
    private function createJob(Order $order, JobStatus $status, User $installer, User $planner): void
    {
        $steps = [JobStatus::Pending];
        while (end($steps) !== $status) {
            $steps[] = end($steps)->allowedNext()[0];
        }

        $changedAt = $order->created_at->copy()->addHours(random_int(1, 24));

        $job = OrderJob::factory()->for($order)->assignedTo($installer)->withStatus($status)->create([
            'created_at' => $changedAt,
        ]);

        $history = [];
        $from = null;

        foreach ($steps as $to) {
            $history[] = [
                'order_job_id' => $job->id,
                'from_status' => $from?->value,
                'to_status' => $to->value,
                'changed_by' => in_array($to, [JobStatus::Installing, JobStatus::Installed], true)
                    ? $installer->id
                    : $planner->id,
                'created_at' => $changedAt,
            ];

            $from = $to;
            $changedAt = $changedAt->copy()->addHours(random_int(3, 36));
        }

        JobStatusChange::query()->insert($history);

        $job->forceFill(['updated_at' => end($history)['created_at']])->save();
    }
}
