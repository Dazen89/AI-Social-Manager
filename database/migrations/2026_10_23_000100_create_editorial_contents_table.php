<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editorial_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 100);
            $table->string('series_title');
            $table->string('title');
            $table->string('public_url', 2048);
            $table->timestamp('released_at')->nullable();
            $table->string('eligibility_status', 20)->default('pending');
            $table->foreignId('eligibility_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['editorial_source_id', 'external_id'], 'editorial_content_source_external_unique');
            $table->index(['site_id', 'eligibility_status', 'released_at'], 'editorial_content_site_eligibility_release');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_contents');
    }
};
