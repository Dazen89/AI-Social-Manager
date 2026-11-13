<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('x_publication_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('editorial_proposal_id')->constrained()->cascadeOnDelete();
            $table->boolean('automatic');
            $table->string('outcome')->default('started');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('x_post_id')->nullable();
            $table->text('message')->nullable();
            $table->index(['editorial_proposal_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('x_publication_attempts');
    }
};
