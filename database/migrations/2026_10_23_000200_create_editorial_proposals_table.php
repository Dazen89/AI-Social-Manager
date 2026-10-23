<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editorial_content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 20)->default('x');
            $table->string('status', 20)->default('draft');
            $table->text('post_text');
            $table->text('rationale');
            $table->timestamp('proposed_for')->nullable();
            $table->longText('source_snapshot')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['editorial_content_id', 'platform'], 'editorial_proposal_content_platform_unique');
            $table->index(['site_id', 'status', 'proposed_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_proposals');
    }
};
