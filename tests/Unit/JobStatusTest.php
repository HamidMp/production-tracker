<?php

namespace Tests\Unit;

use App\Enums\JobStatus;
use PHPUnit\Framework\TestCase;

class JobStatusTest extends TestCase
{
    public function test_each_status_can_only_move_to_the_next_stage(): void
    {
        $workflow = [
            JobStatus::Pending,
            JobStatus::Cutting,
            JobStatus::EdgeBanding,
            JobStatus::Assembly,
            JobStatus::ReadyForInstallation,
            JobStatus::Installing,
            JobStatus::Installed,
        ];

        foreach ($workflow as $index => $status) {
            $next = $workflow[$index + 1] ?? null;

            $this->assertSame($next ? [$next] : [], $status->allowedNext(), "Unexpected transitions from {$status->value}");
        }
    }

    public function test_jobs_cannot_go_back_or_skip_a_stage(): void
    {
        $this->assertTrue(JobStatus::Cutting->canTransitionTo(JobStatus::EdgeBanding));

        $this->assertFalse(JobStatus::Installed->canTransitionTo(JobStatus::Cutting));
        $this->assertFalse(JobStatus::Assembly->canTransitionTo(JobStatus::EdgeBanding));
        $this->assertFalse(JobStatus::Cutting->canTransitionTo(JobStatus::Assembly));
        $this->assertFalse(JobStatus::Cutting->canTransitionTo(JobStatus::Cutting));
    }
}
