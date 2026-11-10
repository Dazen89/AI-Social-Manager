<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_proposals', function (Blueprint $table): void {
            $table->timestamp('x_metrics_attempted_at')->nullable();
        });

        Schema::table('x_post_metric_snapshots', function (Blueprint $table): void {
            $table->unsignedSmallInteger('milestone_hours')->nullable();
            $table->unique(['editorial_proposal_id', 'milestone_hours'], 'x_post_metrics_proposal_milestone');
        });
    }

    public function down(): void
    {
        Schema::table('x_post_metric_snapshots', function (Blueprint $table): void {
            $table->dropUnique('x_post_metrics_proposal_milestone');
            $table->dropColumn('milestone_hours');
        });

        Schema::table('editorial_proposals', function (Blueprint $table): void {
            $table->dropColumn('x_metrics_attempted_at');
        });
    }
};
