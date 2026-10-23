<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('interval_hours')->default(24);
            $table->string('start_time', 5)->default('09:00');
            $table->string('timezone', 64)->default('UTC');
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('running_started_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('last_imported_count')->default(0);
            $table->unsignedInteger('last_proposal_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_schedules');
    }
};
