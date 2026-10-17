<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 64);
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->string('response_id', 128)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['ai_integration_id', 'occurred_at']);
            $table->index(['feature', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
    }
};
