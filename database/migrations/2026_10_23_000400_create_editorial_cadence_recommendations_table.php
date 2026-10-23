<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_cadence_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_integration_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('posts_per_week');
            $table->string('confidence', 8);
            $table->text('rationale');
            $table->longText('source_snapshot');
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
            $table->index(['site_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_cadence_recommendations');
    }
};
