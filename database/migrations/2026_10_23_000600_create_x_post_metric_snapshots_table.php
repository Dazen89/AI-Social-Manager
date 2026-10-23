<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_post_metric_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('editorial_proposal_id')->constrained()->cascadeOnDelete();
            $table->longText('metrics');
            $table->timestamp('collected_at');
            $table->timestamps();
            $table->index(['editorial_proposal_id', 'collected_at'], 'x_post_metrics_proposal_collected');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_post_metric_snapshots');
    }
};
