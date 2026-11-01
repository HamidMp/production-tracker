<?php

namespace App\Enums;

enum JobStatus: string
{
    case Pending = 'pending';
    case Cutting = 'cutting';
    case EdgeBanding = 'edge_banding';
    case Assembly = 'assembly';
    case ReadyForInstallation = 'ready_for_installation';
    case Installing = 'installing';
    case Installed = 'installed';

    /**
     * The only place the workflow is defined. Jobs move one stage forward at a time
     * going back or skipping a stage is not allowed
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Cutting],
            self::Cutting => [self::EdgeBanding],
            self::EdgeBanding => [self::Assembly],
            self::Assembly => [self::ReadyForInstallation],
            self::ReadyForInstallation => [self::Installing],
            self::Installing => [self::Installed],
            self::Installed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedNext(), true);
    }
}
