<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Models\OrderJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class JobsPerStatusReportController extends Controller
{
    /**
     * number of jobs in each stage, in workflow order
     * a single GROUP BY on the indexed order_jobs.status column; stages without jobs are filled in with 0
     */
    public function __invoke(): JsonResponse
    {
        Gate::authorize('view-reports');

        $counts = OrderJob::query()
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $data = array_map(fn (JobStatus $status) => [
            'status' => $status->value,
            'total' => (int) ($counts[$status->value] ?? 0),
        ], JobStatus::cases());

        return response()->json(['data' => $data]);
    }
}
