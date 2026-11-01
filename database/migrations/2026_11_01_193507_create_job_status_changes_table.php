<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $statuses = [
            'pending',
            'cutting',
            'edge_banding',
            'assembly',
            'ready_for_installation',
            'installing',
            'installed',
        ];

        Schema::create('job_status_changes', function (Blueprint $table) use ($statuses) {
            $table->id();
            $table->foreignId('order_job_id')->constrained()->cascadeOnDelete();
            // null means the job was just created
            $table->enum('from_status', $statuses)->nullable();
            $table->enum('to_status', $statuses);
            // Keep the audit trail: a user with history cant be deleted
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_job_id', 'created_at']);
            $table->index('changed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_status_changes');
    }
};
