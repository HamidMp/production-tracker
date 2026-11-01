<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Named order_jobs because Laravel's queue already owns the "jobs" table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->enum('status', [
                'pending',
                'cutting',
                'edge_banding',
                'assembly',
                'ready_for_installation',
                'installing',
                'installed',
            ])->default('pending');
            $table->foreignId('installer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // PostgreSQL does not index foreign key columns on its own
            $table->index('order_id');
            // Installer's job list: WHERE installer_id = ? [AND status = ?]
            $table->index(['installer_id', 'status']);
            // Status filter and report
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_jobs');
    }
};
