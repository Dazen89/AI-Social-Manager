<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_insight_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_integration_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('range_days');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('source_hash', 64);
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->longText('source_snapshot');
            $table->longText('analysis');
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['site_id', 'range_days', 'generated_at']);
            $table->index(['site_id', 'source_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_insight_reports');
    }
};
