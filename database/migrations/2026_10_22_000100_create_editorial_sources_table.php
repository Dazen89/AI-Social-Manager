<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('name');
            $table->string('base_url', 2048);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['site_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_sources');
    }
};
